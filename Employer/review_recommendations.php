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
        $aiData = $existingDoc['aiRecommendations'];
        $aiData['status'] = ($action === 'approve') ? 'approved' : 'rejected';
        $aiData['reviewedBy'] = $_SESSION['uid'];
        $aiData['reviewedAt'] = date('c');

        // Update document with reviewed status
        firestore_patch_document('Ratings', $ratingDocId, [
          'aiRecommendations' => $aiData
        ]);

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