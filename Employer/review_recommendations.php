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

// Review-card trigger context (read-only, display only): the lowest-rated
// KPI vs its industry target plus the RF classification that produced the
// plan — so a manager can triage severity before reading the AI summary.
require_once $rootDir . '/kpi_templates.php';

foreach ($pendingReviews as $ri => $review) {
  $trigger = [
    'area' => '',
    'score' => null,
    'target' => null,
    'class' => '',
    'sev' => '',
    'offline' => false,
  ];

  $scores = $review['scores'] ?? null;
  if (is_array($scores)) {
    foreach ($scores as $scoreKey => $scoreVal) {
      if (!is_numeric($scoreVal)) {
        continue;
      }
      if ($trigger['score'] === null || (float) $scoreVal < $trigger['score']) {
        $trigger['score'] = (float) $scoreVal;
        $trigger['areaKey'] = (string) $scoreKey;
      }
    }
  }

  if (isset($trigger['areaKey'])) {
    try {
      $reviewTemplate = kpi_template_for($review['industry'] ?? null);
      foreach ($reviewTemplate['kpis'] as $templateKpi) {
        if (($templateKpi['key'] ?? '') === $trigger['areaKey']) {
          $trigger['area'] = (string) ($templateKpi['name'] ?? '');
          $trigger['target'] = isset($templateKpi['target']) ? (float) $templateKpi['target'] : null;
          break;
        }
      }
    } catch (\Throwable $e) {
      // Template lookup is optional context; the card still renders without it.
    }
    if ($trigger['area'] === '') {
      $trigger['area'] = ucwords(str_replace('_', ' ', $trigger['areaKey']));
    }
  }

  $prediction = $review['aiRecommendations']['overall_prediction'] ?? '';
  if (is_array($prediction)) {
    $prediction = (string) ($prediction['classification'] ?? '');
  }
  $prediction = strtolower(trim((string) $prediction));

  if (!in_array($prediction, ['meets_expectations', 'needs_improvement', 'critical_gap'], true)) {
    // Legacy/fallback docs may lack the RF class — derive the same
    // thresholds kpi_status_for_score() uses (target, target - 0.8).
    if ($trigger['score'] !== null && $trigger['target'] !== null) {
      if ($trigger['score'] >= $trigger['target']) {
        $prediction = 'meets_expectations';
      } elseif ($trigger['score'] >= $trigger['target'] - 0.8) {
        $prediction = 'needs_improvement';
      } else {
        $prediction = 'critical_gap';
      }
    } else {
      $prediction = '';
    }
  }

  $trigger['class'] = $prediction;
  $trigger['sev'] = [
    'critical_gap' => 'crit',
    'needs_improvement' => 'warn',
    'meets_expectations' => 'ok',
  ][$prediction] ?? '';
  $trigger['offline'] = (($review['aiRecommendations']['generated_by'] ?? '') === 'fallback');

  $pendingReviews[$ri]['_trigger'] = $trigger;
}

// Session badge for the sidebar Review entry (same pattern as the
// employee/deadline badges: pages that load the data stash the count).
$_SESSION['pf_nav_reviews'] = count($pendingReviews);
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
    <?php employer_render_shell('Review'); ?>
    <main class="main content-narrow review-page">
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
          <h1>Review Training Suggestions</h1>
          <p>Approve a plan to send it to the employee's dashboard, or reject it to discard it. Nothing is sent until you approve.</p>
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
            <?php $trigger = $review['_trigger'] ?? null; ?>
            <div class="review-card">
              <div class="review-card-head">
                <h2><?php echo htmlspecialchars($employeeName); ?></h2>
                <span class="review-badge-pending">Pending Approval</span>
              </div>

              <?php if (is_array($trigger) && ($trigger['area'] !== '' || $trigger['class'] !== '' || $trigger['offline'])): ?>
                <div class="review-trigger" data-sev="<?php echo htmlspecialchars($trigger['sev']); ?>">
                  <?php if ($trigger['area'] !== ''): ?>
                    <span class="rt-item">
                      <span class="rt-label">Lowest-rated area</span>
                      <strong><?php echo htmlspecialchars($trigger['area']); ?></strong>
                      <span class="rt-score"><?php echo number_format((float) $trigger['score'], 1); ?> / 5.0</span>
                      <?php if ($trigger['target'] !== null): ?>
                        <span class="rt-target">target <?php echo number_format((float) $trigger['target'], 1); ?></span>
                      <?php endif; ?>
                    </span>
                  <?php endif; ?>
                  <?php if ($trigger['class'] !== ''): ?>
                    <span class="rt-item rt-sev">
                      <span class="rt-glyph" aria-hidden="true"><?php echo $trigger['sev'] === 'crit' ? '!' : ($trigger['sev'] === 'warn' ? '•' : '✓'); ?></span>
                      RF class: <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $trigger['class']))); ?></strong>
                    </span>
                  <?php endif; ?>
                  <?php if ($trigger['offline']): ?>
                    <span class="rt-item rt-offline">AI service offline — fallback summary, no training suggestions</span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <p class="review-summary">
                <strong>AI Summary:</strong> <?php echo htmlspecialchars($aiRecs['summary'] ?? 'N/A'); ?>
              </p>

              <h3 class="review-subhead">Suggested training:</h3>
              <p class="microcopy review-note">The employee will see the summary and suggestions below, marked “Manager Approved,” after you approve.</p>
              <div class="review-recs">
                <?php foreach ($recsList as $item): ?>
                  <div class="review-rec">
                    <div class="review-rec-head">
                      <?php echo htmlspecialchars(str_replace('_', ' ', $item['competency_area'] ?? '')); ?>
                      <span class="review-rec-meta">(<?php echo htmlspecialchars($item['training_type'] ?? 'training'); ?> · <?php echo htmlspecialchars($item['timeline'] ?? '2-4 weeks'); ?>)</span>
                    </div>
                    <div class="review-rec-desc"><?php echo htmlspecialchars($item['description'] ?? ''); ?></div>
                    <div class="review-rec-why"><em>Rationale: <?php echo htmlspecialchars($item['rationale'] ?? ''); ?></em></div>
                  </div>
                <?php endforeach; ?>
              </div>

              <details class="review-edit">
                <summary>Edit a recommendation (stays pending approval)</summary>
                <?php foreach ($recsList as $recIdx => $item): ?>
                  <form method="post" class="review-edit-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="edit_rec" />
                    <input type="hidden" name="rating_doc_id" value="<?php echo htmlspecialchars($review['id']); ?>" />
                    <input type="hidden" name="rec_index" value="<?php echo (int) $recIdx; ?>" />
                    <div class="review-edit-hint">
                      Skill area (set by the system — you can edit type and timeline below):
                      <strong><?php echo htmlspecialchars(str_replace('_', ' ', $item['competency_area'] ?? '')); ?></strong>
                      <?php if (!empty($item['edited'])): ?>
                        <span> · previously edited</span>
                      <?php endif; ?>
                    </div>
                    <label class="review-edit-label">Training type
                      <select name="training_type" required class="review-edit-select">
                        <?php foreach (['on-the-job coaching', 'workshop', 'self-directed learning', 'mentoring'] as $allowedType): ?>
                          <option value="<?php echo htmlspecialchars($allowedType); ?>" <?php echo ($item['training_type'] ?? '') === $allowedType ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars(ucwords($allowedType)); ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                    </label>
                    <label class="review-edit-label">Timeline
                      <input type="text" name="timeline" required value="<?php echo htmlspecialchars($item['timeline'] ?? ''); ?>" class="review-edit-input" />
                    </label>
                    <label class="review-edit-label">Description
                      <textarea name="description" required rows="3" class="review-edit-textarea"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                    </label>
                    <div>
                      <button type="submit" class="ghost-button">Save changes</button>
                    </div>
                  </form>
                <?php endforeach; ?>
              </details>

              <div class="review-actions">
                <form method="post" data-confirm="Approve this plan? The employee will see it on their dashboard.">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="rating_doc_id" value="<?php echo htmlspecialchars($review['id']); ?>" />
                  <input type="hidden" name="action" value="approve" />
                  <button type="submit" class="btn-primary">Approve & Publish</button>
                </form>
                <form method="post" data-confirm="Reject this plan? It will be discarded." data-confirm-danger>
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="rating_doc_id" value="<?php echo htmlspecialchars($review['id']); ?>" />
                  <input type="hidden" name="action" value="reject" />
                  <button type="submit" class="ghost-button review-reject">Reject Plan</button>
                </form>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
</body>

</html>