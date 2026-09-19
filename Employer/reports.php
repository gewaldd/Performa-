<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';

$profileName = $_SESSION['name'] ?? 'Unknown User';
$profileRole = $_SESSION['role'] ?? 'Employer';
$profileRoleDisplay = ucwords(
  str_replace(
    '_',
    ' ',
    $profileRole
  )
);

// Shared icon library (Style A cleanup).
$icons = [
  'calendar' => employer_icon('hourglass'),
  'plus' => employer_icon('plus'),
  'download' => employer_icon('download'),
  'file' => employer_icon('bar-chart'),
  'user' => employer_icon('users'),
];

$reportTypes = [
  'monthly_summary' => 'Monthly Performance Summary',
  'training_audit' => 'Learning & Development Audit',
  'probationary_status' => 'Probationary Status Report',
  'risk_analysis' => 'Underperformance Risk Analysis',
];

/* Disk-backed collection cache (shared include — see
   includes/collection_cache.php; extracted verbatim Item 5). */
require_once __DIR__ . '/includes/collection_cache.php';

/* =========================================================
   PROBATIONARY EMPLOYEES
   ========================================================= */

$employeesList = [];

$docs =
  get_cached_collection(
    'Users',
    600
  );

foreach ($docs as $doc) {
  if (normalize_role_key($doc['role'] ?? null) !== 'probationary') {
    continue;
  }

  $uid =
    trim(
      (string) (
        $doc['uid']
        ?? ''
      )
    );

  if ($uid === '') {
    continue;
  }

  $employeesList[] = [
    'uid' => $uid,

    'name' =>
      $doc['name']
      ?? (
        $doc['email']
        ?? 'Unknown'
      ),

    'industry' =>
      $doc['industry']
      ?? 'retail',
  ];
}

/* =========================================================
   REPORT GENERATION
   ========================================================= */

$genMessage = '';
$genMessageType = 'info';

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'generate_report'
) {
  $empUid =
    trim(
      (string) (
        $_POST['employee']
        ?? ''
      )
    );

  $requestedReportType =
    trim(
      (string) (
        $_POST['report_type']
        ?? ''
      )
    );

  $reportTypeKey =
    array_key_exists(
      $requestedReportType,
      $reportTypes
    )
    ? $requestedReportType
    : 'monthly_summary';

  $emp = null;

  foreach ($employeesList as $employee) {
    if ($employee['uid'] === $empUid) {
      $emp = $employee;
      break;
    }
  }

  if (!$emp) {
    $genMessage =
      'Select a valid probationary employee first.';
    $genMessageType = 'info';
  } else {
    try {
      $template =
        kpi_template_for(
          $emp['industry']
        );

      $allRatings =
        get_cached_collection(
          'Ratings',
          600
        );

      $mine = array_values(
        array_filter(
          $allRatings,
          fn($rating) =>
            ($rating['employeeUid'] ?? '') ===
            $emp['uid']
        )
      );

      usort(
        $mine,
        fn($a, $b) => strcmp(
          $b['ratedAt'] ?? '',
          $a['ratedAt'] ?? ''
        )
      );

      $scores =
        $mine[0]['scores']
        ?? [];

      $reportId =
        'report_' .
        bin2hex(
          random_bytes(12)
        );

      firestore_write_document(
        'Reports',
        $reportId,
        [
          'employeeUid' =>
            $emp['uid'],

          'employeeName' =>
            $emp['name'],

          'reportType' =>
            $reportTypeKey,

          'reportTypeLabel' =>
            $reportTypes[
              $reportTypeKey
            ],

          'industry' =>
            $emp['industry'],

          'templateLabel' =>
            $template['label'],

          'scores' =>
            $scores,

          'generatedAt' =>
            date('c'),

          'generatedBy' =>
            $_SESSION['name']
            ?? '',
        ]
      );

      clear_collection_cache(
        'Reports'
      );

      /*
       * PRG: redirect so refresh never re-submits, and the new row can be
       * highlighted + linked. The #report-<id> anchor scrolls natively.
       */
      header(
        'Location: reports.php?generated=' .
        urlencode($reportId) .
        '&name=' .
        urlencode($emp['name']) .
        '#report-' .
        urlencode($reportId)
      );
      exit;

    } catch (Throwable $e) {
      error_log(
        'Employer report generation failed: ' .
        $e->getMessage()
      );

      $genMessage =
        'The report could not be generated right now. Please try again.';

      $genMessageType = 'error';
    }
  }
}

/* =========================================================
   REPORTS LIST
   ========================================================= */

$reports = [];

$reportDocs =
  get_cached_collection(
    'Reports',
    600
  );

usort(
  $reportDocs,
  fn($a, $b) => strcmp(
    $b['generatedAt'] ?? '',
    $a['generatedAt'] ?? ''
  )
);

$iconCycle = [
  'blue',
  'orange',
  'green',
  'red'
];

foreach ($reportDocs as $index => $reportDoc) {
  $generatedDate =
    pf_date(
      $reportDoc['generatedAt'] ?? null,
      ''
    );

  $reportId =
    (string) (
      $reportDoc['uid']
      ?? ''
    );

  if ($reportId === '') {
    continue;
  }

  /*
   * Snapshot average for the scannable history row (Item 9). Scores were
   * frozen at generation time; empty means the employee was unrated then.
   */
  $snapshotScores =
    $reportDoc['scores'] ?? [];

  $snapshotVals = [];

  if (is_array($snapshotScores)) {
    foreach ($snapshotScores as $snapshotScore) {
      $snapshotVal = (float) $snapshotScore;
      if ($snapshotVal > 0) {
        $snapshotVals[] = $snapshotVal;
      }
    }
  }

  $reports[] = [
    'id' => $reportId,

    'title' =>
      (
        $reportDoc['employeeName']
        ?? 'Unknown'
      ) .
      ' – ' .
      (
        $reportDoc['reportTypeLabel']
        ?? 'Performance Report'
      ),

    'meta' =>
      $generatedDate !== ''
      ? 'Generated on ' . $generatedDate
      : 'Generation date unavailable',

    'typeLabel' =>
      $reportDoc['reportTypeLabel']
      ?? 'Performance Report',

    'scoreAvg' =>
      $snapshotVals
      ? array_sum($snapshotVals) / count($snapshotVals)
      : null,

    'iconClass' =>
      $iconCycle[
        $index % count($iconCycle)
      ],
  ];

}

$currentQuarter =
  'Q' .
  (int) ceil(
    (int) date('n') / 3
  ) .
  ' ' .
  date('Y');

/*
 * Command palette index from the already-loaded report rows (zero new
 * reads). Cap keeps the inline payload small.
 */
$pfPaletteIndex = [];

foreach (
  array_slice(
    $reports,
    0,
    60
  ) as $paletteReport
) {
  $paletteId =
    (string) (
      $paletteReport['id']
      ?? ''
    );

  if ($paletteId === '') {
    continue;
  }

  $pfPaletteIndex[] = [
    'label' =>
      $paletteReport['title'],
    'sub' =>
      $paletteReport['meta'] .
      ' · View report',
    'href' =>
      'report_view.php?id=' .
      urlencode($paletteId),
  ];
}

$pfPaletteJson =
  json_encode(
    $pfPaletteIndex,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
  );
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />

  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <?php employer_brand_head(); ?>

  <title>Reports · Performa</title>

  <meta name="description" content="Create and manage individual performance assessments." />

  <link rel="preconnect" href="https://fonts.googleapis.com" />

  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />

  <link
    href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet" />

  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>

  <div class="app-shell">

    <?php
    employer_render_shell(
      'Reports'
    );
    ?>

    <main class="main reports-page" id="reports">

      <?php ob_start(); ?>

          <div class="reports-period" title="Current review period">

            <span class="deadline-icon" aria-hidden="true">
              <?php
              echo $icons['calendar'];
              ?>
            </span>

            Review period:
            <?php
            echo htmlspecialchars(
              $currentQuarter,
              ENT_QUOTES
            );
            ?>

          </div>

      <?php
      $reportsActions = ob_get_clean();
      employer_page_header(
        'reportsTitle',
        'Employee Reports',
        '<span class="eyebrow">Documentation</span>',
        'Create and manage individual performance assessments.',
        $reportsActions,
        'reports-page-header'
      );
      ?>

      <?php if ($genMessage !== ''): ?>

        <div
          class="alert alert-<?php echo $genMessageType === 'success' ? 'success' : ($genMessageType === 'error' ? 'error' : 'info'); ?> reports-message"
          role="status" aria-live="polite">
          <?php
          echo htmlspecialchars(
            $genMessage,
            ENT_QUOTES
          );
          ?>
        </div>

      <?php endif; ?>

      <?php if (isset($_GET['generated']) && $_GET['generated'] !== ''): ?>

        <div class="alert alert-success reports-message" role="status" aria-live="polite">
          Report generated for
          <strong>
            <?php
            echo htmlspecialchars(
              $_GET['name'] ?? 'employee',
              ENT_QUOTES
            );
            ?>
          </strong>.
          <a href="report_view.php?id=<?php echo urlencode($_GET['generated']); ?>">
            View report
          </a>
        </div>

      <?php endif; ?>

      <section class="report-panel reports-generation-panel" aria-labelledby="generateReportTitle">

        <div class="reports-generation-head">

          <h2 id="generateReportTitle">
            Generate New Report
          </h2>

          <p>
            Select an employee and report type to generate a report from their latest saved KPI ratings.
          </p>

        </div>

        <?php if (!$employeesList): ?>

          <p class="reports-empty-note">
            No probationary employees yet.
            <a class="btn-primary" href="employees.php">Go to Employees</a>
          </p>

        <?php else: ?>

          <form method="post" class="report-form-row reports-form-row reports-generation-form">
            <?php echo csrf_field(); ?>

            <div class="form-group">

              <label class="field-label" for="employee">
                Employee
              </label>

              <div class="employee-select">

                <span class="employee-select-icon" aria-hidden="true">
                  <?php
                  echo $icons['user'];
                  ?>
                </span>

                <select id="employee" class="perform-select employee-select-control" name="employee" required>

                  <?php foreach ($employeesList as $emp): ?>

                    <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>">
                      <?php
                      echo htmlspecialchars(
                        $emp['name'],
                        ENT_QUOTES
                      );
                      ?>
                    </option>

                  <?php endforeach; ?>

                </select>

              </div>

            </div>

            <div class="form-group">

              <label class="field-label" for="report_type">
                Report Type
              </label>

              <div class="employee-select">

                <span class="employee-select-icon" aria-hidden="true">
                  <?php
                  echo $icons['file'];
                  ?>
                </span>

                <select id="report_type" class="perform-select employee-select-control" name="report_type" required>

                  <?php foreach ($reportTypes as $key => $label): ?>

                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>">
                      <?php
                      echo htmlspecialchars(
                        $label,
                        ENT_QUOTES
                      );
                      ?>
                    </option>

                  <?php endforeach; ?>

                </select>

              </div>

            </div>

            <input type="hidden" name="action" value="generate_report" />

            <button class="btn-primary" type="submit">
              <span aria-hidden="true">
                <?php
                echo $icons['plus'];
                ?>
              </span>
              Generate Report
            </button>

          </form>

        <?php endif; ?>

      </section>

      <section class="report-panel generated-reports-panel" aria-labelledby="generatedReportsTitle">

        <div class="reports-section-header">

          <h2 id="generatedReportsTitle">
            Generated Reports
          </h2>

          <span class="reports-section-count">
            Total:
            <strong>
              <?php
              echo count($reports);
              ?>
            </strong>
            reports
          </span>

        </div>

        <div class="report-list">

          <?php if (!$reports): ?>

            <div class="empty-state">
              <p>
                No reports generated yet. Use the form above to create one.
              </p>
            </div>

          <?php else: ?>

            <?php foreach ($reports as $report): ?>

              <article class="report-item" id="report-<?php echo htmlspecialchars($report['id'], ENT_QUOTES); ?>">

                <div class="file-icon <?php echo htmlspecialchars($report['iconClass'], ENT_QUOTES); ?>" aria-hidden="true">
                  <?php
                  echo $icons['file'];
                  ?>
                </div>

                <div class="report-info">

                  <div class="report-title">
                    <?php
                    echo htmlspecialchars(
                      $report['title'],
                      ENT_QUOTES
                    );
                    ?>
                  </div>

                  <div class="report-meta">
                    <?php
                    echo htmlspecialchars(
                      $report['meta'],
                      ENT_QUOTES
                    );
                    ?>
                    ·
                    <?php if ($report['scoreAvg'] !== null): ?>
                      <?php
                      echo htmlspecialchars(
                        pf_score_pair($report['scoreAvg']),
                        ENT_QUOTES
                      );
                      ?>
                    <?php else: ?>
                      No scores captured
                    <?php endif; ?>
                  </div>

                  <div class="report-type-row">
                    <span class="status-pill status-neutral">
                      <?php
                      echo htmlspecialchars(
                        $report['typeLabel'],
                        ENT_QUOTES
                      );
                      ?>
                    </span>
                  </div>

                </div>

                <div class="report-actions">

                  <a class="ghost-button" href="report_view.php?id=<?php echo urlencode($report['id']); ?>&autoprint=1">
                    <span aria-hidden="true">
                      <?php
                      echo $icons['download'];
                      ?>
                    </span>
                    Download PDF
                  </a>

                  <a class="ghost-button" href="report_view.php?id=<?php echo urlencode($report['id']); ?>">
                    <span aria-hidden="true">
                      <?php
                      echo $icons['file'];
                      ?>
                    </span>
                    View
                  </a>

                </div>

              </article>

            <?php endforeach; ?>

          <?php endif; ?>

        </div>

      </section>

    </main>

  </div>

  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

  <script>
    window.__pfIndex = <?php echo $pfPaletteJson !== false ? $pfPaletteJson : '[]'; ?>;
  </script>

</body>

</html>