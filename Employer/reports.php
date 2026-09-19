<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
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

function get_cached_collection($collectionName, $ttlSeconds = 600)
{
  // In-request memo: this page calls the helper up to 3x per load (Users,
  // Ratings, Reports). The disk file is the cross-request cache; this avoids
  // decoding the same large JSON file repeatedly in one request.
  static $memo = [];
  $memoKey = $collectionName . '|' . $ttlSeconds;
  if (isset($memo[$memoKey])) {
    return $memo[$memoKey];
  }

  $cacheFile =
    sys_get_temp_dir() .
    '/performa_' .
    md5($collectionName) .
    '.json';

  if (
    file_exists($cacheFile) &&
    (time() - (int) @filemtime($cacheFile) < $ttlSeconds)
  ) {
    $data = json_decode(
      (string) @file_get_contents($cacheFile),
      true
    );

    if (is_array($data)) {
      $memo[$memoKey] = $data;
      return $data;
    }
  }

  try {
    $data = firestore_list_documents($collectionName);

    @file_put_contents(
      $cacheFile,
      json_encode($data),
      LOCK_EX
    );

    $result = is_array($data) ? $data : [];
    $memo[$memoKey] = $result;
    return $result;
  } catch (Throwable $e) {
    if (file_exists($cacheFile)) {
      $data = json_decode(
        (string) @file_get_contents($cacheFile),
        true
      );

      if (is_array($data)) {
        $memo[$memoKey] = $data;
        return $data;
      }
    }

    error_log(
      'Employer reports collection load failed: ' .
      $e->getMessage()
    );

    return [];
  }
}

function clear_collection_cache($collectionName)
{
  $cacheFile =
    sys_get_temp_dir() .
    '/performa_' .
    md5($collectionName) .
    '.json';

  if (file_exists($cacheFile)) {
    @unlink($cacheFile);
  }
}

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
  $roleKey =
    strtolower(
      trim(
        (string) (
          $doc['role']
          ?? ''
        )
      )
    );

  if (
    strpos(
      $roleKey,
      'probation'
    ) === false
  ) {
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

      $genMessage =
        'Report generated for ' .
        $emp['name'] .
        '.';

      $genMessageType = 'success';

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
  $generatedTimestamp =
    !empty(
    $reportDoc['generatedAt']
  )
    ? strtotime(
      $reportDoc['generatedAt']
    )
    : false;

  $generatedDate =
    $generatedTimestamp
    ? date(
      'M j, Y',
      $generatedTimestamp
    )
    : '';

  $reportId =
    (string) (
      $reportDoc['uid']
      ?? ''
    );

  if ($reportId === '') {
    continue;
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

      <section class="page-header reports-page-header" aria-labelledby="reportsTitle">

        <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
        </button>

        <div class="ph-main">

          <span class="eyebrow">Documentation</span>

          <h1 id="reportsTitle">
            Employee Reports
          </h1>

          <p>
            Create and manage individual performance assessments.
          </p>

        </div>

        <div class="ph-actions">

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

        </div>

      </section>

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
            No probationary employees yet. Add one from the Employees page first.
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

              <article class="report-item">

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
                  </div>

                </div>

                <div class="report-actions">

                  <a class="btn-outline" href="report_view.php?id=<?php echo urlencode($report['id']); ?>&autoprint=1">
                    <span aria-hidden="true">
                      <?php
                      echo $icons['download'];
                      ?>
                    </span>
                    Download PDF
                  </a>

                  <a class="btn-outline" href="report_view.php?id=<?php echo urlencode($report['id']); ?>">
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

</body>

</html>