<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/includes/pdf_writer.php';
require_once __DIR__ . '/includes/report_files.php';

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

// Fingerprint verification: recompute over the same null-stripped snapshot
// shape generation used. States: none (legacy file-less report), ok,
// tampered, nofile (metadata references a missing file).
$verifyState = 'none';
$verifyShort = '';
$canDownload = false;

if ($report && (string) ($report['fileSha256'] ?? '') !== '') {
  $storedSha = (string) $report['fileSha256'];

  $verifySnapshot = report_snapshot_from_doc(is_array($report) ? $report : []);

  $verifyShort = substr($storedSha, 0, 12);

  if (!hash_equals($storedSha, report_pdf_fingerprint($verifySnapshot))) {
    $verifyState = 'tampered';
  } else {
    $diskPath = report_pdf_path($reportId);
    $verifyState = is_file($diskPath) ? 'ok' : 'nofile';
    $canDownload = $verifyState === 'ok';
  }
}
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

<body class="reports-page">
  <div class="app-shell">
    <?php employer_render_shell('Reports'); ?>
    <main class="main content-narrow">
      <?php
      employer_page_header(
        'reportTitle',
        'Report',
        '<a href="reports.php" class="ghost-button back-link">&larr; Back to Reports</a>'
          . '<nav class="ph-crumb" aria-label="Breadcrumb"><span>Reports</span>'
          . '<span aria-hidden="true">/</span><span>View</span></nav>',
        'Review scores, targets, and documentation details. Download the archived PDF or print it.',
        ($canDownload
          ? '<a class="btn-primary" href="report_download.php?id=' . urlencode($reportId) . '">Download PDF</a>'
          : '')
          . '<button class="' . ($canDownload ? 'ghost-button' : 'btn-primary') . '" type="button" data-print>Print / Save as PDF</button>',
        'no-print'
      );
      ?>

      <?php if (!$report): ?>
        <div class="settings-panel report-view-panel">
          <p class="microcopy">Report not found. It may have been deleted.</p>
        </div>
      <?php else: ?>
        <div class="settings-panel report-view-panel">
          <h1><?php echo htmlspecialchars($report['employeeName'] ?? 'Unknown', ENT_QUOTES); ?></h1>
            <p class="microcopy">
              <?php echo htmlspecialchars($report['reportTypeLabel'] ?? '', ENT_QUOTES); ?>
              &middot; Generated
            <?php echo !empty($report['generatedAt']) ? date('M j, Y g:ia', strtotime($report['generatedAt'])) : ''; ?>
            <?php if (!empty($report['generatedBy'])): ?> by
              <?php echo htmlspecialchars($report['generatedBy'], ENT_QUOTES); ?>  <?php endif; ?>
          </p>
          <p class="microcopy">Job type:
            <?php echo htmlspecialchars($report['templateLabel'] ?? '', ENT_QUOTES); ?></p>

          <?php if ($verifyState === 'ok'): ?>
            <p class="microcopy"><span class="verify-chip">SHA-256 verified
              <code><?php echo htmlspecialchars($verifyShort, ENT_QUOTES); ?>…</code></span></p>
          <?php elseif ($verifyState === 'tampered'): ?>
            <p class="microcopy verify-bad">Fingerprint mismatch — this report's
              content changed since generation. Treat it as unverified.</p>
          <?php elseif ($verifyState === 'nofile'): ?>
            <p class="microcopy">The archived PDF file is missing from storage; print view remains available.</p>
          <?php endif; ?>

          <hr class="section-divider" />

          <h4 class="settings-subhead">KPI Scores</h4>
          <?php $scores = $report['scores'] ?? []; ?>
          <?php $frozenTargets = $report['targets'] ?? []; ?>
          <?php if (!$scores): ?>
            <p>No KPI ratings were on file for this employee when the report was generated.</p>
          <?php else: ?>
            <div class="form-grid">
              <?php foreach ($template['kpis'] as $kpi): ?>
                <?php $val = isset($scores[$kpi['key']]) ? (float) $scores[$kpi['key']] : null; ?>
                <?php $frozenTarget = isset($frozenTargets[$kpi['key']]) ? (float) $frozenTargets[$kpi['key']] : (float) $kpi['target']; ?>
                <div class="form-group report-score-row">
                  <div class="report-score-head">
                    <label><?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?></label>
                    <span class="report-score-target">Target <?php echo number_format($frozenTarget, 1); ?></span>
                  </div>
                  <?php echo employer_score_meter($val, 5.0, $frozenTarget); ?>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php $savedAi = $report['aiRecommendations'] ?? null; ?>
          <?php if (is_array($savedAi) && !empty($savedAi['training_recommendations'])): ?>
            <hr class="section-divider" />
            <h4 class="settings-subhead">AI Training Recommendations (approved)</h4>
            <?php if (!empty($savedAi['summary'])): ?>
              <p class="microcopy">
                <?php echo htmlspecialchars($savedAi['summary'], ENT_QUOTES); ?>
              </p>
            <?php endif; ?>
            <div class="form-grid">
              <?php foreach ($savedAi['training_recommendations'] as $rec): ?>
                <div class="form-group">
                  <label><?php
                    echo htmlspecialchars(
                      ucwords(str_replace('_', ' ', (string) ($rec['competency_area'] ?? ''))) .
                      ' — ' . (string) ($rec['training_type'] ?? ''),
                      ENT_QUOTES
                    );
                  ?></label>
                  <div>
                    <?php echo htmlspecialchars((string) ($rec['description'] ?? ''), ENT_QUOTES); ?>
                    <?php if (!empty($rec['timeline'])): ?>
                      <span class="microcopy">Timeline: <?php echo htmlspecialchars($rec['timeline'], ENT_QUOTES); ?></span>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>

          <?php if (!empty($report['regularizationRecommendation'])): ?>
            <hr class="section-divider" />
            <h4 class="settings-subhead">Regularization Decision Record</h4>
            <p class="microcopy">
              Recommendation:
              <strong><?php echo htmlspecialchars($report['regularizationRecommendation'], ENT_QUOTES); ?></strong>
            </p>
            <?php if (!empty($report['regularizationNotes'])): ?>
              <p class="microcopy">
                <?php echo htmlspecialchars($report['regularizationNotes'], ENT_QUOTES); ?>
              </p>
            <?php endif; ?>
            <?php if (!empty($report['regularizationDecidedAt']) || !empty($report['regularizationDecidedBy'])): ?>
              <p class="microcopy">
                Decided
                <?php echo !empty($report['regularizationDecidedAt']) ? date('M j, Y g:ia', strtotime($report['regularizationDecidedAt'])) : ''; ?>
                <?php if (!empty($report['regularizationDecidedBy'])): ?> by
                  <?php echo htmlspecialchars($report['regularizationDecidedBy'], ENT_QUOTES); ?><?php endif; ?>
              </p>
            <?php endif; ?>
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