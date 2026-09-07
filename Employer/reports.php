<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
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

$icons = [
  'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'target' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
  'bar-chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
  'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 1 .33-1.82V9a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
  'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
  'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  'chevron-down' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
  'download' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  'file' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
  'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
];

$reportTypes = [
  'monthly_summary' => 'Monthly Performance Summary',
  'training_audit' => 'Learning & Development Audit',
  'probationary_status' => 'Probationary Status Report',
  'risk_analysis' => 'Underperformance Risk Analysis',
];

function get_cached_collection($collectionName, $ttlSeconds = 600)
{
  $cacheFile =
    sys_get_temp_dir() .
    '/performa_' .
    md5($collectionName) .
    '.json';

  if (
    file_exists($cacheFile) &&
    (time() - filemtime($cacheFile) < $ttlSeconds)
  ) {
    $data = json_decode(
      file_get_contents($cacheFile),
      true
    );

    if (is_array($data)) {
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

    return is_array($data) ? $data : [];
  } catch (Throwable $e) {
    if (file_exists($cacheFile)) {
      $data = json_decode(
        file_get_contents($cacheFile),
        true
      );

      if (is_array($data)) {
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
$contributorCounts = [];

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

  $contributor =
    trim(
      (string) (
        $reportDoc['generatedBy']
        ?? ''
      )
    );

  if ($contributor !== '') {
    $contributorCounts[
      $contributor
    ] =
      (
        $contributorCounts[
          $contributor
        ]
        ?? 0
      ) + 1;
  }
}

arsort(
  $contributorCounts
);

$topContributors =
  array_slice(
    array_keys(
      $contributorCounts
    ),
    0,
    3
  );

$otherContributorCount =
  max(
    0,
    count($contributorCounts) -
    count($topContributors)
  );

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

  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
  />

  <title>Performa | Employee Reports</title>

  <meta
    name="description"
    content="Create and manage individual performance assessments."
  />

  <link
    rel="preconnect"
    href="https://fonts.googleapis.com"
  />

  <link
    rel="preconnect"
    href="https://fonts.gstatic.com"
    crossorigin
  />

  <link
    href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet"
  />

  <link
    rel="stylesheet"
    href="styles.css"
  />
</head>

<body>

  <div class="app-shell">

    <?php
    employer_render_shell(
      'Reports'
    );
    ?>

    <main
      class="main reports-page"
      id="reports"
    >

      <header class="topbar reports-topbar">

        <div class="reports-period">

          <span
            class="deadline-icon"
            aria-hidden="true"
          >
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

        <div class="topbar-actions">

          <button
            class="icon-button"
            type="button"
            aria-label="Notifications"
          >
            <span aria-hidden="true">
              <?php
              echo $icons['bell'];
              ?>
            </span>
          </button>

          <a
            class="ghost-button"
            href="../logout.php"
            aria-label="Sign out"
          >
            Sign out
          </a>

        </div>

      </header>

      <section class="page-header reports-page-header">

        <div>

          <h1>
            Employee Reports
          </h1>

          <p>
            Create and manage individual performance assessments.
          </p>

        </div>

      </section>

      <?php if ($genMessage !== ''): ?>

        <div
          class="alert alert-<?php echo $genMessageType === 'success' ? 'success' : ($genMessageType === 'error' ? 'error' : 'info'); ?> reports-message"
          role="status"
          aria-live="polite"
        >
          <?php
          echo htmlspecialchars(
            $genMessage,
            ENT_QUOTES
          );
          ?>
        </div>

      <?php endif; ?>

      <section
        class="report-panel reports-generation-panel"
        aria-labelledby="generateReportTitle"
      >

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

          <form
            method="post"
            class="report-form-row reports-form-row reports-generation-form"
          >

            <div class="form-group">

              <label
                class="field-label"
                for="employee"
              >
                Employee
              </label>

              <div class="employee-select">

                <span
                  class="employee-select-icon"
                  aria-hidden="true"
                >
                  <?php
                  echo $icons['user'];
                  ?>
                </span>

                <select
                  id="employee"
                  class="perform-select employee-select-control"
                  name="employee"
                  required
                >

                  <?php foreach ($employeesList as $emp): ?>

                    <option
                      value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>"
                    >
                      <?php
                      echo htmlspecialchars(
                        $emp['name'],
                        ENT_QUOTES
                      );
                      ?>
                    </option>

                  <?php endforeach; ?>

                </select>

                <span
                  class="employee-select-chevron"
                  aria-hidden="true"
                >
                  <?php
                  echo $icons['chevron-down'];
                  ?>
                </span>

              </div>

            </div>

            <div class="form-group">

              <label
                class="field-label"
                for="report_type"
              >
                Report Type
              </label>

              <div class="employee-select">

                <span
                  class="employee-select-icon"
                  aria-hidden="true"
                >
                  <?php
                  echo $icons['file'];
                  ?>
                </span>

                <select
                  id="report_type"
                  class="perform-select employee-select-control"
                  name="report_type"
                  required
                >

                  <?php foreach ($reportTypes as $key => $label): ?>

                    <option
                      value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>"
                    >
                      <?php
                      echo htmlspecialchars(
                        $label,
                        ENT_QUOTES
                      );
                      ?>
                    </option>

                  <?php endforeach; ?>

                </select>

                <span
                  class="employee-select-chevron"
                  aria-hidden="true"
                >
                  <?php
                  echo $icons['chevron-down'];
                  ?>
                </span>

              </div>

            </div>

            <input
              type="hidden"
              name="action"
              value="generate_report"
            />

            <button
              class="btn-primary"
              type="submit"
            >
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

      <section aria-labelledby="generatedReportsTitle">

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

                <div
                  class="file-icon <?php echo htmlspecialchars($report['iconClass'], ENT_QUOTES); ?>"
                  aria-hidden="true"
                >
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

                  <a
                    class="btn-outline"
                    href="report_view.php?id=<?php echo urlencode($report['id']); ?>&autoprint=1"
                  >
                    <span aria-hidden="true">
                      <?php
                      echo $icons['download'];
                      ?>
                    </span>
                    Download PDF
                  </a>

                  <a
                    class="btn-outline"
                    href="report_view.php?id=<?php echo urlencode($report['id']); ?>"
                  >
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

      <?php if ($topContributors): ?>

        <section
          class="reports-contributors"
          aria-labelledby="contributorsTitle"
        >

          <div>
            <h2
              id="contributorsTitle"
              class="contributors-label"
            >
              Top contributors this period
            </h2>
          </div>

          <div class="contributors-content">

            <div
              class="contributors-avatars"
              aria-hidden="true"
            >

              <?php foreach ($topContributors as $index => $name): ?>

                <div
                  class="contributors-avatar"
                  style="background-image:url('https://ui-avatars.com/api/?name=<?php echo urlencode($name); ?>&background=2f6df6&color=fff&size=64');"
                ></div>

              <?php endforeach; ?>

            </div>

            <span class="contributors-summary">

              <?php
              echo htmlspecialchars(
                $topContributors[0],
                ENT_QUOTES
              );
              ?>

              <?php if ($otherContributorCount > 0): ?>

                &amp;
                <?php
                echo (int) $otherContributorCount;
                ?>
                other<?php echo $otherContributorCount === 1 ? '' : 's'; ?>

              <?php endif; ?>

            </span>

          </div>

        </section>

      <?php endif; ?>

    </main>

  </div>

  <footer class="site-footer">

    <span>
      Performa employer dashboard prototype
    </span>

    <span>
      Powered by PHP &amp; Firebase
    </span>

  </footer>

  <script src="dropdowns.js"></script>
  <script src="script.js"></script>

</body>
</html>
