<?php
$rootDir = __DIR__ . '/..';
require_once $rootDir . '/auth.php';
require_once $rootDir . '/firebase_init.php';
require_once $rootDir . '/kpi_templates.php';
require_once __DIR__ . '/employer_layout.php';

require_login();
require_role('employer');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (empty($_SESSION['uid'])) {
  header('Location: ../login.php');
  exit;
}

$reportId = $_GET['id'] ?? '';
$report = $reportId ? firestore_get_document('Reports', $reportId) : null;
$template = $report ? kpi_template_for($report['industry'] ?? 'retail') : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | Report</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
  <style>
    @media print {
      .no-print { display: none !important; }
    }
  </style>
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('Reports'); ?>
    <main class="main" style="max-width:720px;margin:0 auto;">
      <div class="page-header no-print">
        <div>
          <a href="reports.php" class="ghost-button" style="display:inline-flex;margin-bottom:12px;">&larr; Back to Reports</a>
        </div>
        <button class="btn-primary" type="button" onclick="window.print()">Print / Save as PDF</button>
      </div>

      <?php if (!$report): ?>
        <div class="settings-panel">
          <p>Report not found. It may have been deleted.</p>
        </div>
      <?php else: ?>
        <div class="settings-panel">
          <h1><?php echo htmlspecialchars($report['employeeName'] ?? 'Unknown', ENT_QUOTES); ?></h1>
          <p style="color:var(--muted);">
            <?php echo htmlspecialchars($report['reportTypeLabel'] ?? '', ENT_QUOTES); ?>
            &middot; Generated <?php echo !empty($report['generatedAt']) ? date('F j, Y g:ia', strtotime($report['generatedAt'])) : ''; ?>
            <?php if (!empty($report['generatedBy'])): ?> by <?php echo htmlspecialchars($report['generatedBy'], ENT_QUOTES); ?><?php endif; ?>
          </p>
          <p style="color:var(--muted);">Industry template: <?php echo htmlspecialchars($report['templateLabel'] ?? '', ENT_QUOTES); ?></p>

          <hr class="section-divider" />

          <h4 class="settings-subhead">KPI Scores</h4>
          <?php $scores = $report['scores'] ?? []; ?>
          <?php if (!$scores): ?>
            <p>No KPI ratings were on file for this employee when the report was generated.</p>
          <?php else: ?>
            <div class="form-grid">
              <?php foreach ($template['kpis'] as $kpi): ?>
                <?php $val = isset($scores[$kpi['key']]) ? (float) $scores[$kpi['key']] : null; ?>
                <div class="form-group">
                  <label><?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?></label>
                  <div><?php echo $val !== null ? number_format($val, 1) : '—'; ?> / 5.0 (target <?php echo number_format($kpi['target'], 1); ?>)</div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
</body>

</html>