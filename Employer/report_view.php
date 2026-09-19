<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';

$reportId = $_GET['id'] ?? '';
$report = null;
try {
  $report = $reportId ? firestore_get_document('Reports', $reportId) : null;
} catch (Throwable $e) {
  // leave $report null so the page shows "Report not found" instead of a
  // silent blank page (display_errors is off in production).
  $report = null;
}
$template = $report ? kpi_template_for($report['industry'] ?? 'retail') : null;
$autoPrint = $report && !empty($_GET['autoprint']);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php employer_brand_head(); ?>
  <meta name="description" content="View and print an employee performance report." />
  <title>Report · Performa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
  <?php if ($autoPrint): ?>
    <script>
      // "Download PDF" on the Reports list opens straight into the browser's
      // print dialog (Save as PDF) instead of just showing the report.
      window.addEventListener('load', () => window.print());
    </script>
  <?php endif; ?>
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('Reports'); ?>
    <main class="main content-narrow">
      <div class="page-header no-print">
        <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
          <?php echo employer_icon('menu'); ?>
        </button>
        <div class="ph-main">
          <a href="reports.php" class="ghost-button back-link">&larr; Back to Reports</a>
          <nav class="ph-crumb" aria-label="Breadcrumb">
            <span>Reports</span>
            <span aria-hidden="true">/</span>
            <span>View</span>
          </nav>
          <h1>Report</h1>
          <p>Review scores, targets, and documentation details before printing.</p>
        </div>
        <div class="ph-actions">
          <button class="btn-primary" type="button" data-print>Print / Save as PDF</button>
        </div>
      </div>

      <?php if (!$report): ?>
        <div class="settings-panel">
          <p class="microcopy" style="padding:16px 18px;">Report not found. It may have been deleted.</p>
        </div>
      <?php else: ?>
        <div class="settings-panel">
          <h1 style="padding:16px 18px 0;"><?php echo htmlspecialchars($report['employeeName'] ?? 'Unknown', ENT_QUOTES); ?></h1>
          <p class="microcopy" style="padding:0 18px;">
            <?php echo htmlspecialchars($report['reportTypeLabel'] ?? '', ENT_QUOTES); ?>
            &middot; Generated
            <?php echo !empty($report['generatedAt']) ? date('M j, Y g:ia', strtotime($report['generatedAt'])) : ''; ?>
            <?php if (!empty($report['generatedBy'])): ?> by
              <?php echo htmlspecialchars($report['generatedBy'], ENT_QUOTES); ?>  <?php endif; ?>
          </p>
          <p class="microcopy" style="padding:0 18px;">Industry template:
            <?php echo htmlspecialchars($report['templateLabel'] ?? '', ENT_QUOTES); ?></p>

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
                  <div><?php echo $val !== null ? number_format($val, 1) : '—'; ?> / 5.0 (target
                    <?php echo number_format($kpi['target'], 1); ?>)</div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>
  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
  <script>
    document.querySelectorAll('[data-print]').forEach(function (btn) {
      btn.addEventListener('click', function () { window.print(); });
    });
  </script>
</body>

</html>