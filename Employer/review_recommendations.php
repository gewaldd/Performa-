<?php
$rootDir = __DIR__ . '/..';
require_once $rootDir . '/auth.php';
require_once $rootDir . '/firebase_init.php';
require_once __DIR__ . '/includes/csrf.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';

require_login();
require_role('employer');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/collection_cache.php';
require_once $rootDir . '/audit_log.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$message = '';
$messageType = 'info';

// Handle Action: Approve or Reject Recommendation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $ratingDocId = $_POST['rating_doc_id'] ?? '';

  if ($ratingDocId && in_array($action, ['approve', 'reject'])) {
    try {
      $existingDoc = firestore_get_document('Ratings', $ratingDocId);

      if ($existingDoc && isset($existingDoc['aiRecommendations'])) {
        // Single-org ownership, same as every other employer write path:
        // legacy docs without owner fields pass through as global.
        $approveUid = (string) ($existingDoc['employeeUid'] ?? '');
        $approveTarget = $approveUid !== ''
          ? (firestore_get_document('Users', $approveUid) ?? [])
          : [];
        require_employer_owns_user(
          $approveTarget + ['uid' => $approveUid],
          'review:' . $action
        );

        $aiData = $existingDoc['aiRecommendations'];
        $aiData['status'] = ($action === 'approve') ? 'approved' : 'rejected';
        $aiData['reviewedBy'] = $_SESSION['uid'];
        $aiData['reviewedAt'] = date('c');

        // Update document with reviewed status
        firestore_patch_document('Ratings', $ratingDocId, [
          'aiRecommendations' => $aiData
        ]);

        // Approving is the manuscript's human-in-the-loop moment, so it is
        // also the single place that authors the assigned training record.
        // (The old dashboard direct-write bypass is gone.)
        if ($action === 'approve' && $approveUid !== '') {
          $approveTop = $aiData['training_recommendations'][0] ?? null;
          $approveLabel = is_array($approveTop)
            ? trim(
              ucwords(
                str_replace(
                  '_',
                  ' ',
                  (string) ($approveTop['competency_area'] ?? '')
                )
              ) . ' — ' . (string) ($approveTop['training_type'] ?? '')
            )
            : '';
          $approveTarget['assignedTraining'] = $approveLabel !== '' && $approveLabel !== '—'
            ? $approveLabel
            : 'Approved training plan';
          $approveTarget['assignedTrainingAt'] = date('c');
          firestore_write_document('Users', $approveUid, $approveTarget);
        }

        record_audit_event(
          'training_plan_' . $action,
          'Training plan ' . $action . 'd for rating ' . $ratingDocId,
          ['rating' => $ratingDocId, 'employee' => $approveUid]
        );

        $message = ($action === 'approve') 
          ? 'Training plan approved and published to employee dashboard.' 
          : 'AI recommendation plan rejected.';
        $messageType = 'success';
      }
    } catch (\Throwable $e) {
      $message = 'Error updating recommendation status: ' . $e->getMessage();
      $messageType = 'error';
    }
  }

  // Handle Action: Edit one recommendation inside a pending plan.
  // The competency stays locked to the RF classification; only the
  // Gemini-authored wording (type/timeline/description) is adjustable, and
  // the plan remains pending_approval — editing never auto-approves.
  if ($ratingDocId && $action === 'edit_rec') {
    try {
      $editDoc = firestore_get_document('Ratings', $ratingDocId);

      if (!$editDoc || !isset($editDoc['aiRecommendations']) || !is_array($editDoc['aiRecommendations'])) {
        throw new RuntimeException('Recommendation plan not found.');
      }

      if (($editDoc['aiRecommendations']['status'] ?? '') !== 'pending_approval') {
        throw new RuntimeException('Only pending plans can be edited.');
      }

      $editUid = (string) ($editDoc['employeeUid'] ?? '');
      $editTarget = $editUid !== '' ? (firestore_get_document('Users', $editUid) ?? []) : [];
      require_employer_owns_user($editTarget + ['uid' => $editUid], 'review:edit_rec');

      $editRecs = $editDoc['aiRecommendations']['training_recommendations'] ?? [];

      if (!is_array($editRecs)) {
        $editRecs = [];
      }

      $editIdx = (int) ($_POST['rec_index'] ?? -1);

      if (!isset($editRecs[$editIdx]) || !is_array($editRecs[$editIdx])) {
        throw new RuntimeException('Recommendation not found.');
      }

      $allowedTypes = ['on-the-job coaching', 'workshop', 'self-directed learning', 'mentoring'];
      $editType = trim((string) ($_POST['training_type'] ?? ''));
      $editTimeline = trim((string) ($_POST['timeline'] ?? ''));
      $editDesc = trim((string) ($_POST['description'] ?? ''));

      if (!in_array($editType, $allowedTypes, true) || $editTimeline === '' || $editDesc === '') {
        throw new RuntimeException('Provide a valid training type, timeline, and description.');
      }

      $editRecs[$editIdx]['training_type'] = $editType;
      $editRecs[$editIdx]['timeline'] = $editTimeline;
      $editRecs[$editIdx]['description'] = $editDesc;
      $editRecs[$editIdx]['edited'] = true;
      $editRecs[$editIdx]['editedBy'] = $_SESSION['uid'];
      $editRecs[$editIdx]['editedAt'] = date('c');

      $editAi = $editDoc['aiRecommendations'];
      $editAi['training_recommendations'] = array_values($editRecs);

      firestore_patch_document('Ratings', $ratingDocId, [
        'aiRecommendations' => $editAi
      ]);

      record_audit_event(
        'training_plan_edited',
        'Edited recommendation ' . $editIdx . ' for rating ' . $ratingDocId,
        ['rating' => $ratingDocId, 'employee' => $editUid, 'index' => $editIdx]
      );

      $message = 'Recommendation updated. It still needs approval.';
      $messageType = 'success';
    } catch (\Throwable $e) {
      $message = 'Error updating recommendation: ' . $e->getMessage();
      $messageType = 'error';
    }
  }
}

// Fetch all Ratings documents containing pending AI recommendations
$pendingReviews = [];
try {
  $allRatings = firestore_list_documents('Ratings');
  foreach ($allRatings as $doc) {
    if (
      isset($doc['aiRecommendations']) && 
      is_array($doc['aiRecommendations']) && 
      ($doc['aiRecommendations']['status'] ?? '') === 'pending_approval'
    ) {
      $doc['id'] = $doc['__name'] ?? ($doc['employeeUid'] . '_' . date('Y-m-d'));
      $pendingReviews[] = $doc;
    }
  }
} catch (\Throwable $e) {
  $pendingReviews = [];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php employer_brand_head(); ?>
  <title>Review AI Recommendations · Performa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('KPIs'); ?>
    <main class="main content-narrow">
      <div class="page-header">
        <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
          <?php echo employer_icon('menu'); ?>
        </button>
        <div class="ph-main">
          <a href="kpis.php" class="ghost-button back-link">&larr; Back to KPIs</a>
          <nav class="ph-crumb" aria-label="Breadcrumb">
            <span>KPIs</span>
            <span aria-hidden="true">/</span>
            <span>Review AI Plans</span>
          </nav>
          <h1>Human-in-the-Loop Review</h1>
          <p>Review and approve AI-generated training recommendations before they are published to employees.</p>
        </div>
      </div>

      <div class="settings-panel">
        <?php if ($message): ?>
          <div class="alert alert-<?php echo $messageType; ?>" role="status"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if (empty($pendingReviews)): ?>
          <div class="empty-state">
            <p>No pending AI recommendations to review.</p>
            <a class="btn-primary" href="rate_employee.php">Score An Employee</a>
          </div>
        <?php else: ?>
          <?php foreach ($pendingReviews as $review): ?>
            <?php 
              $aiRecs = $review['aiRecommendations'];
              $employeeName = $review['employeeName'] ?? 'Employee';
              $recsList = $aiRecs['training_recommendations'] ?? [];
            ?>
            <div class="card" style="margin-bottom: 24px; padding: 20px; border: 1px solid var(--border-color, #e0e0e0); border-radius: 8px;">
              <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                <h2 style="margin: 0; font-size: 1.2rem;"><?php echo htmlspecialchars($employeeName); ?></h2>
                <span class="badge badge-warning" style="background: #fef3c7; color: #92400e; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                  Pending Approval
                </span>
              </div>

              <p style="font-size: 0.95rem; color: #4b5563; margin-bottom: 16px;">
                <strong>AI Summary:</strong> <?php echo htmlspecialchars($aiRecs['summary'] ?? 'N/A'); ?>
              </p>

              <h3 style="font-size: 1rem; margin-bottom: 8px;">Targeted Training Interventions:</h3>
              <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                <?php foreach ($recsList as $item): ?>
                  <div style="background: #f9fafb; padding: 12px; border-radius: 6px; border-left: 4px solid #3b82f6;">
                    <div style="font-weight: 600; text-transform: capitalize;">
                      <?php echo htmlspecialchars(str_replace('_', ' ', $item['competency_area'] ?? '')); ?>
                      <span style="font-weight: normal; color: #6b7280;">(<?php echo htmlspecialchars($item['training_type'] ?? 'training'); ?> · <?php echo htmlspecialchars($item['timeline'] ?? '2-4 weeks'); ?>)</span>
                    </div>
                    <div style="font-size: 0.9rem; margin-top: 4px;"><?php echo htmlspecialchars($item['description'] ?? ''); ?></div>
                    <div style="font-size: 0.825rem; color: #6b7280; margin-top: 4px;"><em>Rationale: <?php echo htmlspecialchars($item['rationale'] ?? ''); ?></em></div>
                  </div>
                <?php endforeach; ?>
              </div>

              <details style="margin-bottom: 20px;">
                <summary style="cursor: pointer; font-weight: 600; font-size: 0.9rem;">
                  Edit a recommendation (stays pending approval)
                </summary>
                <?php foreach ($recsList as $recIdx => $item): ?>
                  <form method="post" style="display: grid; gap: 8px; margin-top: 12px; padding: 12px; background: #f9fafb; border-radius: 6px;">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="edit_rec" />
                    <input type="hidden" name="rating_doc_id" value="<?php echo htmlspecialchars($review['id']); ?>" />
                    <input type="hidden" name="rec_index" value="<?php echo (int) $recIdx; ?>" />
                    <div style="font-size: 0.85rem; color: #6b7280;">
                      Competency (locked to RF classification):
                      <strong><?php echo htmlspecialchars(str_replace('_', ' ', $item['competency_area'] ?? '')); ?></strong>
                      <?php if (!empty($item['edited'])): ?>
                        <span> · previously edited</span>
                      <?php endif; ?>
                    </div>
                    <label style="font-size: 0.85rem;">Training type
                      <select name="training_type" required style="display: block; width: 100%; margin-top: 4px; padding: 8px; border-radius: 6px; border: 1px solid #d1d5db;">
                        <?php foreach (['on-the-job coaching', 'workshop', 'self-directed learning', 'mentoring'] as $allowedType): ?>
                          <option value="<?php echo htmlspecialchars($allowedType); ?>" <?php echo ($item['training_type'] ?? '') === $allowedType ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucwords($allowedType)); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label style="font-size: 0.85rem;">Timeline
                      <input type="text" name="timeline" required value="<?php echo htmlspecialchars($item['timeline'] ?? ''); ?>" style="display: block; width: 100%; margin-top: 4px; padding: 8px; border-radius: 6px; border: 1px solid #d1d5db;" />
                    </label>
                    <label style="font-size: 0.85rem;">Description
                      <textarea name="description" required rows="3" style="display: block; width: 100%; margin-top: 4px; padding: 8px; border-radius: 6px; border: 1px solid #d1d5db;"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                    </label>
                    <div>
                      <button type="submit" class="ghost-button">Save changes</button>
                    </div>
                  </form>
                <?php endforeach; ?>
              </details>

              <form method="post" style="display: flex; gap: 12px; justify-content: flex-end;">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="rating_doc_id" value="<?php echo htmlspecialchars($review['id']); ?>" />
                <button type="submit" name="action" value="reject" class="ghost-button" style="color: #dc2626;">Reject Plan</button>
                <button type="submit" name="action" value="approve" class="btn-primary">Approve & Publish</button>
              </form>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
</body>

</html>