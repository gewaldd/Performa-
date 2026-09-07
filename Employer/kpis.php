<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$icons = [
  'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
  'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
  'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  'chevron-down' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
  'edit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
  'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
  'dot' => '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="12" cy="12" r="6"/></svg>',
  'download' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
];

$trendGlyph = [
  'up' => '↑↑',
  'flat' => '↔',
  'down' => '↓↓',
];

$trendClass = [
  'up' => 'trend-up',
  'flat' => 'trend-flat',
  'down' => 'trend-down',
];

$badgeCycle = [
  'blue',
  'purple',
  'orange',
  'green',
];

$accentCycle = [
  'var(--color-success)',
  'var(--color-info)',
  'var(--color-warning)',
  'var(--color-dept-purple)',
];

/* =========================================================
   FAST DISK-BACKED DATA CACHING
   ========================================================= */
function get_cached_collection(
  string $collectionName,
  int $ttlSeconds = 600
): array {
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
      (string) file_get_contents($cacheFile),
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
        (string) file_get_contents($cacheFile),
        true
      );

      if (is_array($data)) {
        return $data;
      }
    }

    return [];
  }
}

function clear_collection_cache(
  string $collectionName
): void {
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
   EMPLOYEE SELECTION
   ========================================================= */
$probationaryEmployees = [];
$allUsers = get_cached_collection('Users', 600);

foreach ($allUsers as $doc) {
  $roleKey = strtolower(
    trim(
      (string) ($doc['role'] ?? '')
    )
  );

  if (strpos($roleKey, 'probation') === false) {
    continue;
  }

  $probationaryEmployees[] = [
    'uid' => $doc['uid'] ?? '',
    'name' => $doc['name'] ?? ($doc['email'] ?? 'Unknown'),
    'industry' => $doc['industry'] ?? 'retail',
  ];
}

$selectedEmployeeId =
  (string) ($_GET['employee'] ?? '');

$selectedEmployee = null;

foreach ($probationaryEmployees as $emp) {
  if ($emp['uid'] === $selectedEmployeeId) {
    $selectedEmployee = $emp;
    break;
  }
}

if (
  $selectedEmployee === null &&
  !empty($probationaryEmployees)
) {
  $selectedEmployee = $probationaryEmployees[0];
}

$selectedEmployeeName =
  $selectedEmployee['name']
  ?? 'No employees yet';

$currentIndustry =
  $selectedEmployee['industry']
  ?? 'retail';

/* =========================================================
   FORM HANDLERS
   ========================================================= */
$kpiMessage = '';
$kpiMessageType = 'info';

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'add_kpi'
) {
  $newName = trim(
    (string) ($_POST['kpi_name'] ?? '')
  );

  $newTarget = (float) (
    $_POST['kpi_target'] ?? 4.0
  );

  $industryForKpi =
    trim(
      (string) (
        $_POST['industry']
        ?? $currentIndustry
      )
    );

  if ($newName !== '') {
    try {
      add_custom_kpi(
        $industryForKpi,
        $newName,
        max(
          1.0,
          min(
            5.0,
            $newTarget
          )
        )
      );

      $kpiMessage = 'KPI added.';
      $kpiMessageType = 'success';

      clear_collection_cache('Ratings');
    } catch (Throwable $e) {
      error_log(
        'Employer KPI creation failed: ' .
        $e->getMessage()
      );

      $kpiMessage =
        'The KPI could not be added. Please try again.';

      $kpiMessageType = 'error';
    }
  }
}

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'edit_kpi_target'
) {
  $kpiKey = trim(
    (string) ($_POST['kpi_key'] ?? '')
  );

  $newTarget = (float) (
    $_POST['kpi_target'] ?? 4.0
  );

  $industryForKpi =
    trim(
      (string) (
        $_POST['industry']
        ?? $currentIndustry
      )
    );

  if ($kpiKey !== '') {
    try {
      set_kpi_target_override(
        $industryForKpi,
        $kpiKey,
        max(
          1.0,
          min(
            5.0,
            $newTarget
          )
        )
      );

      $kpiMessage =
        'KPI target updated.';

      $kpiMessageType = 'success';

      clear_collection_cache('Ratings');
    } catch (Throwable $e) {
      error_log(
        'Employer KPI target update failed: ' .
        $e->getMessage()
      );

      $kpiMessage =
        'The KPI target could not be updated. Please try again.';

      $kpiMessageType = 'error';
    }
  }
}

$template = kpi_template_for(
  $currentIndustry
);

/* =========================================================
   RATINGS
   ========================================================= */
$latestScores = [];
$previousScores = [];

if ($selectedEmployee) {
  $allRatings = get_cached_collection(
    'Ratings',
    600
  );

  $mine = [];
  $targetUid = $selectedEmployee['uid'];

  foreach ($allRatings as $rating) {
    if (
      ($rating['employeeUid'] ?? '') ===
      $targetUid
    ) {
      $mine[] = $rating;
    }
  }

  if (!empty($mine)) {
    usort(
      $mine,
      static function ($a, $b): int {
        return strcmp(
          (string) ($b['ratedAt'] ?? ''),
          (string) ($a['ratedAt'] ?? '')
        );
      }
    );

    if (isset($mine[0]['scores'])) {
      $latestScores = is_array($mine[0]['scores'])
        ? $mine[0]['scores']
        : [];
    }

    if (isset($mine[1]['scores'])) {
      $previousScores = is_array($mine[1]['scores'])
        ? $mine[1]['scores']
        : [];
    }
  }
}

/* =========================================================
   KPI VIEW MODEL
   ========================================================= */
$employeeKpis = [];

foreach ($template['kpis'] as $kpi) {
  $current = isset(
    $latestScores[$kpi['key']]
  )
    ? (float) $latestScores[$kpi['key']]
    : null;

  $previous = isset(
    $previousScores[$kpi['key']]
  )
    ? (float) $previousScores[$kpi['key']]
    : null;

  $trend = 'flat';

  if (
    $current !== null &&
    $previous !== null
  ) {
    if ($current > $previous) {
      $trend = 'up';
    } elseif ($current < $previous) {
      $trend = 'down';
    }
  }

  $statusInfo =
    $current !== null
      ? kpi_status_for_score(
          $current,
          $kpi['target']
        )
      : [
          'status' => 'No Data',
          'statusClass' => 'status-neutral',
        ];

  $employeeKpis[] = [
    'key' => $kpi['key'],
    'name' => $kpi['name'],
    'sub' =>
      'Target ' .
      number_format(
        (float) $kpi['target'],
        1
      ) .
      ' / 5.0',
    'target' => (float) $kpi['target'],
    'current' => $current,
    'hasData' => $current !== null,
    'stars' =>
      $current !== null
        ? max(
            0,
            min(
              5,
              (int) round($current)
            )
          )
        : 0,
    'trend' => $trend,
    'status' => $statusInfo['status'],
    'statusClass' =>
      $statusInfo['statusClass'],
  ];
}

/* =========================================================
   CATEGORY CARDS
   ========================================================= */
$categoryCards = [];

foreach (
  array_slice(
    $template['kpis'],
    0,
    3
  ) as $i => $kpi
) {
  $current = isset(
    $latestScores[$kpi['key']]
  )
    ? (float) $latestScores[$kpi['key']]
    : null;

  $statusInfo =
    $current !== null
      ? kpi_status_for_score(
          $current,
          $kpi['target']
        )
      : [
          'status' => 'No Data',
          'statusClass' => 'status-neutral',
        ];

  $categoryCards[] = [
    'badge' => $template['label'],
    'badgeClass' =>
      $badgeCycle[
        $i % count($badgeCycle)
      ],
    'icon' => 'clock',
    'title' => $kpi['name'],
    'target' => number_format(
      (float) $kpi['target'],
      1
    ),
    'current' =>
      $current !== null
        ? number_format($current, 1) . ' / 5.0'
        : '—',
    'progress' =>
      $current !== null
        ? min(
            100,
            max(
              0,
              (int) round(
                ($current / 5.0) * 100
              )
            )
          )
        : 0,
    'accentColor' =>
      $accentCycle[
        $i % count($accentCycle)
      ],
    'status' => $statusInfo['status'],
    'statusIcon' => 'dot',
    'statusClass' =>
      $statusInfo['statusClass'],
  ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
  />
  <title>Performa | KPIs</title>
  <meta
    name="description"
    content="Define and track organization-wide performance metrics."
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

<body class="kpi-page">
  <div class="app-shell">

    <?php
    employer_render_shell('KPIs');
    ?>

    <main class="main">

      <header class="topbar kpi-topbar" aria-label="KPI page account and utility controls">
        <div class="topbar-actions">
          <button
            class="icon-button"
            type="button"
            aria-label="Notifications"
          >
            <span aria-hidden="true">
              <?php echo $icons['bell']; ?>
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

      <section class="page-header kpi-page-header">
        <div>
          <h1>Key Performance Indicators</h1>
          <p>
            Define and track organization-wide performance metrics.
          </p>
        </div>

        <a
          class="btn-primary"
          href="#addKpiForm"
        >
          <span aria-hidden="true">
            <?php echo $icons['plus']; ?>
          </span>
          Create KPI
        </a>
      </section>

      <?php if ($kpiMessage !== ''): ?>
        <div
          class="alert <?php echo $kpiMessageType === 'success' ? 'alert-success' : ($kpiMessageType === 'error' ? 'alert-error' : 'alert-info'); ?>"
          role="status"
          aria-live="polite"
        >
          <?php
          echo htmlspecialchars(
            $kpiMessage,
            ENT_QUOTES
          );
          ?>
        </div>
      <?php endif; ?>

      <div class="kpi-search-wrap">
        <label
          class="search-bar"
          for="kpiSearch"
        >
          <span class="sr-only">
            Search KPIs and categories
          </span>

          <span
            class="search-icon"
            aria-hidden="true"
          >
            <?php echo $icons['search']; ?>
          </span>

          <input
            type="search"
            id="kpiSearch"
            aria-controls="performanceMetrics"
            placeholder="Search KPIs, categories..."
            autocomplete="off"
          />
        </label>
      </div>

      <section class="report-panel kpi-employee-panel">
        <div class="section-header kpi-section-header">
          <div>
            <h2>Individual Employee KPI</h2>
            <p>
              Manage specific performance targets and scores for individual team members.
            </p>
          </div>

          <button
            class="btn-primary"
            type="button"
            id="exportKpiBtn"
          >
            <span aria-hidden="true">
              <?php echo $icons['download']; ?>
            </span>
            Export Report
          </button>
        </div>

        <div class="employee-select-wrap kpi-employee-select-wrap">
          <label
            class="employee-select-label"
            for="employeeSelect"
          >
            Employee
          </label>

          <?php if ($probationaryEmployees): ?>
            <div class="employee-select-row kpi-employee-select-row">

              <form
                method="get"
                class="employee-select"
              >
                <span
                  class="employee-select-icon"
                  aria-hidden="true"
                >
                  <?php echo $icons['user']; ?>
                </span>

                <select
                  class="perform-select employee-select-control"
                  id="employeeSelect"
                  name="employee"
                  aria-describedby="employeeSelectHint"
                  onchange="this.form.submit()"
                >
                  <?php foreach ($probationaryEmployees as $emp): ?>
                    <option
                      value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>"
                      <?php echo ($selectedEmployee && $emp['uid'] === $selectedEmployee['uid']) ? 'selected' : ''; ?>
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
                  <?php echo $icons['chevron-down']; ?>
                </span>
              </form>

              <a
                class="ghost-button kpi-rate-button"
                href="rate_employee.php?employee=<?php echo urlencode($selectedEmployee['uid'] ?? ''); ?>"
              >
                Rate this employee
              </a>
            </div>

            <span
              id="employeeSelectHint"
              class="sr-only"
            >
              Changing this selection reloads the KPI information for that employee.
            </span>

          <?php else: ?>

            <div class="employee-select">
              <span
                class="employee-select-icon"
                aria-hidden="true"
              >
                <?php echo $icons['user']; ?>
              </span>
              <span>No probationary employees yet</span>
            </div>

          <?php endif; ?>
        </div>
      </section>

      <section
        class="metrics-panel kpi-metrics-panel"
        aria-labelledby="performanceMetricsTitle"
      >
        <div class="metrics-panel-header">
          <h2 id="performanceMetricsTitle">
            Performance Metrics
          </h2>
        </div>

        <?php if (empty($employeeKpis)): ?>
          <div class="empty-state">
            <p>
              No KPI metrics are currently available for this template.
            </p>
          </div>
        <?php else: ?>

          <div class="kpi-table-head">
            <span>KPI Name</span>
            <span>Target Score</span>
            <span>Current Score</span>
            <span>Trend</span>
            <span>Status</span>
            <span>Actions</span>
          </div>

          <?php foreach ($employeeKpis as $kpi): ?>
            <?php
            $editFormId =
              'editKpiRow_' .
              (string) $kpi['key'];

            $editFormIdJson =
              htmlspecialchars(
                json_encode(
                  $editFormId,
                  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
                ),
                ENT_QUOTES
              );
            ?>

            <article
              class="kpi-row"
              data-search="<?php echo htmlspecialchars(strtolower($kpi['name']), ENT_QUOTES); ?>"
            >

              <div data-label="KPI Name">
                <div class="kpi-name">
                  <?php
                  echo htmlspecialchars(
                    $kpi['name'],
                    ENT_QUOTES
                  );
                  ?>
                </div>

                <div class="kpi-sub">
                  <?php
                  echo htmlspecialchars(
                    $kpi['sub'],
                    ENT_QUOTES
                  );
                  ?>
                </div>
              </div>

              <div
                class="kpi-target"
                data-label="Target Score"
              >
                <?php
                echo number_format(
                  (float) $kpi['target'],
                  1
                );
                ?>
              </div>

              <div
                class="kpi-current"
                data-label="Current Score"
              >
                <div
                  class="stars"
                  aria-hidden="true"
                >
                  <?php
                  echo str_repeat(
                    '★',
                    (int) $kpi['stars']
                  );

                  echo str_repeat(
                    '☆',
                    5 - (int) $kpi['stars']
                  );
                  ?>
                </div>

                <strong>
                  <?php
                  echo $kpi['hasData']
                    ? number_format(
                        (float) $kpi['current'],
                        1
                      )
                    : '—';
                  ?>
                </strong>
              </div>

              <div
                class="trend <?php echo htmlspecialchars($trendClass[$kpi['trend']], ENT_QUOTES); ?>"
                data-label="Trend"
              >
                <span aria-hidden="true">
                  <?php
                  echo $kpi['hasData']
                    ? $trendGlyph[$kpi['trend']]
                    : '—';
                  ?>
                </span>

                <span class="sr-only">
                  <?php
                  if (!$kpi['hasData']) {
                    echo 'No trend data';
                  } elseif ($kpi['trend'] === 'up') {
                    echo 'Improving';
                  } elseif ($kpi['trend'] === 'down') {
                    echo 'Declining';
                  } else {
                    echo 'Unchanged';
                  }
                  ?>
                </span>
              </div>

              <div data-label="Status">
                <span
                  class="status-pill <?php echo htmlspecialchars($kpi['statusClass'], ENT_QUOTES); ?>"
                >
                  <?php
                  echo htmlspecialchars(
                    $kpi['status'],
                    ENT_QUOTES
                  );
                  ?>
                </span>
              </div>

              <div data-label="Actions">
                <button
                  class="edit-button"
                  type="button"
                  aria-label="Edit target for <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>"
                  aria-controls="<?php echo htmlspecialchars($editFormId, ENT_QUOTES); ?>"
                  aria-expanded="false"
                  onclick="const form = document.getElementById(<?php echo $editFormIdJson; ?>); if (form) { form.style.display = 'flex'; form.setAttribute('aria-hidden', 'false'); this.style.display = 'none'; this.setAttribute('aria-expanded', 'true'); const input = form.querySelector('input[name=\'kpi_target\']'); if (input) { input.focus(); input.select(); } }"
                >
                  <span aria-hidden="true">
                    <?php echo $icons['edit']; ?>
                  </span>
                </button>

                <form
                  method="post"
                  id="<?php echo htmlspecialchars($editFormId, ENT_QUOTES); ?>"
                  style="display:none;align-items:center;gap:6px;"
                  aria-hidden="true"
                >
                  <input
                    type="hidden"
                    name="action"
                    value="edit_kpi_target"
                  />

                  <input
                    type="hidden"
                    name="kpi_key"
                    value="<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>"
                  />

                  <input
                    type="hidden"
                    name="industry"
                    value="<?php echo htmlspecialchars($currentIndustry, ENT_QUOTES); ?>"
                  />

                  <label
                    class="sr-only"
                    for="target_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>"
                  >
                    Target score for <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>
                  </label>

                  <input
                    id="target_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>"
                    type="number"
                    name="kpi_target"
                    min="1"
                    max="5"
                    step="0.1"
                    value="<?php echo number_format((float) $kpi['target'], 1); ?>"
                    inputmode="decimal"
                  />

                  <button
                    class="btn-primary"
                    type="submit"
                  >
                    Save
                  </button>
                </form>
              </div>

            </article>
          <?php endforeach; ?>

          <a
            class="add-kpi-button kpi-add-link"
            href="#addKpiForm"
          >
            <span aria-hidden="true">
              <?php echo $icons['plus']; ?>
            </span>
            Add New KPI to Template
          </a>

        <?php endif; ?>
      </section>

      <section
        class="report-panel kpi-add-panel"
        id="addKpiForm"
        aria-labelledby="addKpiTitle"
      >
        <h2 id="addKpiTitle">
          Add a KPI to the
          <?php
          echo htmlspecialchars(
            $template['label'],
            ENT_QUOTES
          );
          ?>
          Template
        </h2>

        <p>
          Applies to every employee on the
          <?php
          echo htmlspecialchars(
            $template['label'],
            ENT_QUOTES
          );
          ?>
          industry template, not just
          <?php
          echo htmlspecialchars(
            $selectedEmployeeName,
            ENT_QUOTES
          );
          ?>.
        </p>

        <form
          method="post"
          class="report-form-row"
        >
          <input
            type="hidden"
            name="action"
            value="add_kpi"
          />

          <input
            type="hidden"
            name="industry"
            value="<?php echo htmlspecialchars($currentIndustry, ENT_QUOTES); ?>"
          />

          <div class="form-group">
            <label for="kpi_name">
              KPI Name
            </label>

            <input
              id="kpi_name"
              name="kpi_name"
              type="text"
              required
              maxlength="120"
              autocomplete="off"
              placeholder="e.g. Documentation Quality"
            />
          </div>

          <div class="form-group">
            <label for="kpi_target">
              Target Score (1-5)
            </label>

            <input
              id="kpi_target"
              name="kpi_target"
              type="number"
              min="1"
              max="5"
              step="0.1"
              value="4.0"
              inputmode="decimal"
              required
            />
          </div>

          <button
            class="btn-primary"
            type="submit"
          >
            <span aria-hidden="true">
              <?php echo $icons['plus']; ?>
            </span>
            Add KPI
          </button>
        </form>
      </section>

      <?php if (!empty($categoryCards)): ?>
        <section
          class="category-cards kpi-category-cards"
          aria-label="KPI category summaries"
        >
          <?php foreach ($categoryCards as $card): ?>
            <article class="category-card">

              <div class="category-card-top">
                <span
                  class="category-badge <?php echo htmlspecialchars($card['badgeClass'], ENT_QUOTES); ?>"
                >
                  <?php
                  echo htmlspecialchars(
                    strtoupper($card['badge']),
                    ENT_QUOTES
                  );
                  ?>
                </span>

                <span
                  class="category-icon"
                  aria-hidden="true"
                >
                  <?php echo $icons[$card['icon']]; ?>
                </span>
              </div>

              <h3 class="category-title">
                <?php
                echo htmlspecialchars(
                  $card['title'],
                  ENT_QUOTES
                );
                ?>
              </h3>

              <div class="category-target-row">
                <span>
                  Target:
                  <?php
                  echo htmlspecialchars(
                    $card['target'],
                    ENT_QUOTES
                  );
                  ?>
                </span>

                <strong>
                  <?php
                  echo htmlspecialchars(
                    $card['current'],
                    ENT_QUOTES
                  );
                  ?>
                </strong>
              </div>

              <div
                class="category-bar"
                role="progressbar"
                aria-valuemin="0"
                aria-valuemax="100"
                aria-valuenow="<?php echo (int) $card['progress']; ?>"
                aria-label="<?php echo htmlspecialchars($card['title'], ENT_QUOTES); ?> progress"
              >
                <span
                  style="width: <?php echo (int) $card['progress']; ?>%; background: <?php echo htmlspecialchars($card['accentColor'], ENT_QUOTES); ?>;"
                ></span>
              </div>

              <span
                class="category-status <?php echo htmlspecialchars($card['statusClass'], ENT_QUOTES); ?>"
              >
                <span aria-hidden="true">
                  <?php echo $icons[$card['statusIcon']]; ?>
                </span>
                <?php
                echo htmlspecialchars(
                  strtoupper($card['status']),
                  ENT_QUOTES
                );
                ?>
              </span>

            </article>
          <?php endforeach; ?>
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
  <script src="kpis.js"></script>
</body>
</html>
