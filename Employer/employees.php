<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/includes/collection_cache.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

$profileName = $_SESSION['name'] ?? 'Unknown User';
$profileRole = $_SESSION['role'] ?? 'Employer';
$profileRoleDisplay = ucwords(
  str_replace(
    '_',
    ' ',
    $profileRole
  )
);

// Shared icon library (Style A cleanup) — single source in includes/icons.php.
// Strip glyphs (users / hourglass / trend / cap) are the dashboard's own
// vocabulary for cohort, nearing-deadline, performance and training plans.
$icons = [
  'search' => employer_icon('search'),
  'plus' => employer_icon('plus'),
  'download' => employer_icon('download'),
  'users' => employer_icon('users'),
  'hourglass' => employer_icon('hourglass'),
  'trend' => employer_icon('trend'),
  'cap' => employer_icon('cap'),
];

/*
 * Probation-window thresholds, defined once. The metric strip, the row
 * triage tones and the dashboard's "Nearing Deadline" card all read these
 * numbers, so a row can never be amber while the strip says it is safe.
 * The dashboard predicate is daysLeft <= 30 && > 0 (employer_dashboard.php).
 */
$atRiskDays = 30;
$finalDays = 7;

/* Purged 2026-09: hash-based $deptClassCycle + department_pill_class().
   Department pills are intentionally neutral (single quiet style); random
   per-department colors asserted meaning that did not exist. */

/* =========================================================
   FAST SHORT-SESSION CACHING
   ========================================================= */

$directory = [];

$cacheKey =
  'performa_employee_directory';

$cacheTimeKey =
  'performa_employee_directory_time';

$cacheTTL = 20;

/*
 * Pending training-plan count rides the same snapshot as the rows (written
 * in the cache-miss branch below), so an in-TTL cached render shows the
 * exact same strip numbers.
 */
$pendingKey =
  'performa_employee_pending';

$directoryPending = 0;

$cacheAvailable =
  isset(
  $_SESSION[$cacheKey],
  $_SESSION[$cacheTimeKey]
) &&
  (time() - (int) $_SESSION[$cacheTimeKey] < $cacheTTL) &&
  !isset($_GET['created']);

if ($cacheAvailable) {

  $directory =
    is_array(
      $_SESSION[$cacheKey]
    )
    ? $_SESSION[$cacheKey]
    : [];

  $directoryPending =
    (int) (
    $_SESSION[$pendingKey]
    ?? 0
  );

} else {

  /*
   * Latest KPI averages for the triage meta line. Single disk-cached
   * Ratings load (TTL 600, per-tenant salted); summaries come from the
   * memoized template helper, so N rows cost ~1 read, not N. Only needed
   * on a directory cache miss — session-cached rows carry their values.
   */
  $ratingsForScores = [];

  try {
    $ratingsForScores =
      get_cached_collection(
        'Ratings',
        600
      );
  } catch (Throwable $e) {
    $ratingsForScores = [];
  }

  try {

    $docs =
      firestore_list_documents('Users');

    foreach ($docs as $doc) {

      $roleKey =
        normalize_role_key(
          $doc['role'] ?? null
        );

      /*
       * Only pure admin/employer accounts are excluded here.
       * Supervisors and probationary/regular employees remain
       * available to the existing Employer directory behavior.
       */
      if (
        $roleKey === 'admin' ||
        $roleKey === 'employer'
      ) {
        continue;
      }

      $status =
        $doc['status']
        ?? 'Active';

      /*
       * Days-left triage value for client-side sorting (dates already on
       * the doc — zero new reads). Null when there is no probation clock
       * (supervisors, missing hire date); nulls sort last.
       */
      $daysLeftValue = null;
      $daySinceValue = null;

      if ($roleKey === 'probationary') {
        $directoryCreatedAt =
          $doc['createdAt']
          ?? $doc['hireDate']
          ?? '';

        $directoryCreatedTime =
          $directoryCreatedAt
          ? strtotime($directoryCreatedAt)
          : false;

        if ($directoryCreatedTime) {
          $directoryPeriod =
            max(
              1,
              (int) (
                $doc['probationPeriodDays']
                ?? 180
              )
            );

          $daySinceValue =
            max(
              0,
              (int) floor(
                (time() - $directoryCreatedTime) /
                86400
              )
            );

          $daysLeftValue =
            max(
              0,
              $directoryPeriod - $daySinceValue
            );
        }
      }

      /*
       * Latest average for the triage meta line (probationary rows only).
       * Null when unrated — the template says "Not yet rated".
       */
      $scoreValue = null;

      $targetAvgValue = null;

      if ($roleKey === 'probationary') {
        $directoryUid =
          (string) (
            $doc['uid']
            ?? ''
          );

        if ($directoryUid !== '') {
          $directorySummary =
            employee_kpi_summary(
              $ratingsForScores,
              $directoryUid,
              (string) (
                $doc['industry']
                ?? 'retail'
              )
            );

          if (!empty($directorySummary['hasData'])) {
            $scoreValue =
              (float) $directorySummary['score'];

            $targetAvgValue =
              (float) $directorySummary['targetAvg'];
          }
        }
      }

      /*
       * Performance status for the dedicated column + the status filter.
       * One helper call per row; unrated rows get a neutral class and a
       * "not-rated" slug so they stay filterable without inventing a
       * fourth performance class.
       */
      $perfStatus = null;

      if (isset($scoreValue, $targetAvgValue)) {
        $perfStatus = kpi_status_for_score(
          (float) $scoreValue,
          (float) $targetAvgValue
        );
      }

      $perfLabel = $perfStatus !== null
        ? $perfStatus['status'] . ' (' . number_format((float) $scoreValue, 1) . ')'
        : 'Not yet rated';

      $perfClass = $perfStatus['statusClass']
        ?? 'status-neutral';

      $perfSlug = $perfStatus !== null
        ? strtolower(str_replace(' ', '-', $perfStatus['status']))
        : 'not-rated';

      $roleLabel =
        display_role_label(
          $doc['role']
          ?? null,
          'Employee'
        );

      $dept =
        (
          $doc['department']
          ?? ''
        ) ?: $roleLabel;

      $directory[] = [

        'uid' =>
          $doc['uid']
          ?? '',

        'name' =>
          $doc['name']
          ?? (
            $doc['email']
            ?? 'Unknown'
          ),

        'email' =>
          $doc['email']
          ?? '',

        'initials' =>
          employer_avatar_initials(
            $doc['name']
            ?? (
              $doc['email']
              ?? 'Unknown'
            )
          ),

        'role' =>
          $roleLabel,

        'dept' =>
          $dept,

        'type' =>
          $roleKey === 'probationary'
          ? 'Probationary'
          : 'Regular',

        'status' =>
          $status,

        'daysLeftValue' =>
          $daysLeftValue,

        'daySinceValue' =>
          $daySinceValue,

        'scoreValue' =>
          $scoreValue,

        'targetAvgValue' =>
          $targetAvgValue,

        /*
         * Tone thresholds come from the shared probation-window constants
         * above: final week = red, inside the 30-day review window = amber,
         * otherwise on track. (Was 2 / 15, which disagreed with both the
         * dashboard's 30-day deadline metric and the strip card.)
         */
        'triageTone' =>
          $daysLeftValue === null
          ? ''
          : (
            $daysLeftValue <= $finalDays
            ? 'bad'
            : (
              $daysLeftValue <= $atRiskDays
              ? 'warn'
              : 'ok'
            )
          ),

        /*
         * Probation clock, pre-formatted for the timeline cell: elapsed
         * percentage drives the meter, day/period drives the caption.
         * Null for rows without a clock (supervisors, missing hire date).
         */
        'probationPercent' =>
          $daySinceValue !== null
          ? (int) min(
            100,
            round(
              $daySinceValue /
              max(
                1,
                (int) (
                  $doc['probationPeriodDays']
                  ?? 180
                )
              ) *
              100
            )
          )
          : null,

        'probationPeriod' =>
          $daySinceValue !== null
          ? max(
            1,
            (int) (
              $doc['probationPeriodDays']
              ?? 180
            )
          )
          : null,

        'statusClass' =>
          $status === 'Disabled'
          ? 'status-danger'
          : 'status-good',

        'perfLabel' =>
          $perfLabel,

        'perfClass' =>
          $perfClass,

        'perfSlug' =>
          $perfSlug,
      ];
    }

    /*
     * Pending training-plan sign-offs (the Review Plans queue). Same source
     * and same predicate review_recommendations.php uses, so the strip card
     * and the sidebar badge cannot disagree. Counted here because this is
     * the only branch that reads Ratings.
     */
    foreach ($ratingsForScores as $pendingRating) {
      $pendingPlan =
        $pendingRating['aiRecommendations']
        ?? null;

      if (
        is_array($pendingPlan) &&
        ($pendingPlan['status'] ?? '') === 'pending_approval'
      ) {
        $directoryPending++;
      }
    }

    $_SESSION[$cacheKey] =
      $directory;

    $_SESSION[$cacheTimeKey] =
      time();

    $_SESSION[$pendingKey] =
      $directoryPending;

  } catch (Throwable $e) {

    /*
     * Keep the page usable while recording the
     * server-side failure without exposing details.
     */
    error_log(
      'Employer employee directory load failed: ' .
      $e->getMessage()
    );

    $directory = [];
  }
}

/* =========================================================
   METRIC MINI STRIP
   =========================================================
   Four cohort signals derived from rows already in memory (zero new reads):
   headcount, the shared 30-day review window, the manuscript "Exceeding"
   threshold, and the Review Plans queue stashed by the cache-miss branch.
   ========================================================= */

$stripCohort = 0;
$stripTracked = 0;
$stripAtRisk = 0;
$stripExceeding = 0;

foreach ($directory as $stripRow) {
  if (($stripRow['type'] ?? '') !== 'Probationary') {
    continue;
  }

  $stripCohort++;

  if (isset($stripRow['daysLeftValue'])) {
    $stripTracked++;

    if (
      $stripRow['daysLeftValue'] <= $atRiskDays &&
      $stripRow['daysLeftValue'] > 0
    ) {
      $stripAtRisk++;
    }
  }

  if (
    isset($stripRow['scoreValue'], $stripRow['targetAvgValue']) &&
    kpi_status_for_score(
      (float) $stripRow['scoreValue'],
      (float) $stripRow['targetAvgValue']
    )['status'] === 'Exceeding'
  ) {
    $stripExceeding++;
  }
}

$stripTrackedPercent = $stripCohort > 0
  ? (int) round($stripTracked / $stripCohort * 100)
  : 0;

$employeeMetrics = [
  [
    'label' => 'Active Cohort',
    'value' => (string) $stripCohort,
    'suffix' => $stripCohort === 1 ? 'Member' : 'Members',
    'note' => $stripTrackedPercent . '% tracked in 180-day window',
    'icon' => 'users',
    'modifier' => 'metric-card--info',
  ],
  [
    'label' => 'At Risk / Review',
    'value' => (string) $stripAtRisk,
    'suffix' => 'Approaching',
    'note' => 'Within ' . $atRiskDays . ' days of the deadline',
    'icon' => 'hourglass',
    'modifier' => 'metric-card--risk',
  ],
  [
    'label' => 'Exceeding KPI',
    'value' => (string) $stripExceeding,
    'suffix' => $stripExceeding === 1 ? 'Profile' : 'Profiles',
    'note' => 'Score at or above KPI target',
    'icon' => 'trend',
    'modifier' => 'metric-card--good',
  ],
  [
    'label' => 'Pending Sign-off',
    'value' => (string) $directoryPending,
    'suffix' => 'Pending',
    'note' => 'Final review stage',
    'icon' => 'cap',
    'modifier' => 'metric-card--muted',
  ],
];

$departments =
  array_values(
    array_unique(
      array_map(
        'strtolower',
        array_column(
          $directory,
          'dept'
        )
      )
    )
  );

sort($departments);

/*
 * Shell headcount badge + command palette index from the already-loaded
 * directory rows (zero new reads).
 */
$_SESSION['pf_nav_employees'] =
  count($directory);

$pfPaletteIndex = [];

foreach (
  array_slice(
    $directory,
    0,
    60
  ) as $paletteEntry
) {
  $paletteUid =
    (string) (
      $paletteEntry['uid']
      ?? ''
    );

  if ($paletteUid === '') {
    continue;
  }

  $pfPaletteIndex[] = [
    'label' =>
      $paletteEntry['name'],
    'sub' =>
      (
        $paletteEntry['role']
        ?? ''
      ) .
      ' · View profile',
    'href' =>
      'employee_view.php?uid=' .
      urlencode($paletteUid),
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

  <title>
    Employees · Performa
  </title>

  <meta name="description" content="Manage and organize your workforce directory." />

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
      'Employees'
    );
    ?>

    <main class="main employees-page">

      <?php ob_start(); ?>

          <button class="ghost-button" type="button" id="exportDirectoryBtn">
            <span aria-hidden="true">
              <?php
              echo $icons['download'];
              ?>
            </span>
            Export List
          </button>

          <a class="btn-primary" href="add_employee.php">
            <span aria-hidden="true">
              <?php
              echo $icons['plus'];
              ?>
            </span>

            Add Employee
          </a>

      <?php
      $employeesActions = ob_get_clean();
      employer_page_header(
        'employeesTitle',
        'Employees',
        '<span class="eyebrow">Workforce</span>',
        'Manage and organize your workforce directory.',
        $employeesActions
      );
      ?>

      <?php if (isset($_GET['created'])): ?>

        <div class="alert-banner alert-success" role="status" aria-live="polite">

          Account created for
          <strong>
            <?php
            echo htmlspecialchars(
              $_GET['name'] ?? '',
              ENT_QUOTES
            );
            ?>
          </strong>.

          <?php if (!empty($_GET['emailed']) && $_GET['emailed'] === '1'): ?>

            Login credentials were emailed to them directly.

          <?php elseif (!empty($_SESSION['reveal_once_password'])): ?>

            The welcome email couldn't be sent, so here's the temporary password once
            (it will not be shown again after you leave this page) — share it with
            <strong><?php echo htmlspecialchars($_SESSION['reveal_once_email'] ?? '', ENT_QUOTES); ?></strong>
            through a secure channel:
            <code>
                  <?php
                  echo htmlspecialchars(
                    $_SESSION['reveal_once_password'],
                    ENT_QUOTES
                  );
                  unset($_SESSION['reveal_once_password'], $_SESSION['reveal_once_email']);
                  ?>
                </code>

          <?php else: ?>

            The welcome email couldn't be sent and no password is available to display —
            check the Brevo configuration in <code>.env</code>, then reset their password
            from the employee's profile.

          <?php endif; ?>

        </div>

      <?php endif; ?>

      <!-- Metric mini strip: probationary cohort signals for the 180-day window. -->
      <section class="metrics" aria-label="Probationary cohort metrics">

        <?php foreach ($employeeMetrics as $employeeMetric): ?>

          <article class="metric-card <?php echo htmlspecialchars($employeeMetric['modifier'], ENT_QUOTES); ?>">

            <div class="metric-card-top">

              <span class="metric-icon" aria-hidden="true">
                <?php echo $icons[$employeeMetric['icon']]; ?>
              </span>

            </div>

            <div class="metric-meta">

              <span>
                <?php echo htmlspecialchars($employeeMetric['label'], ENT_QUOTES); ?>
              </span>

              <strong class="metric-value">
                <?php echo htmlspecialchars($employeeMetric['value'], ENT_QUOTES); ?>
                <small><?php echo htmlspecialchars($employeeMetric['suffix'], ENT_QUOTES); ?></small>
              </strong>

              <small class="metric-note">
                <?php echo htmlspecialchars($employeeMetric['note'], ENT_QUOTES); ?>
              </small>

            </div>

          </article>

        <?php endforeach; ?>

      </section>

      <section class="filter-bar" aria-label="Directory filters">

        <div class="filter-group">

          <label class="search-bar" for="employeeSearch">

            <span class="sr-only">
              Search employees and departments
            </span>

            <span class="search-icon" aria-hidden="true">
              <?php
              echo $icons['search'];
              ?>
            </span>

            <input type="search" id="employeeSearch" placeholder="Search employees, departments..." autocomplete="off" />

          </label>

          <label class="filter-select">

            <span class="sr-only">
              Department
            </span>

            <select id="deptFilter" class="perform-select perform-select--filter">

              <option value="">
                All departments
              </option>

              <?php foreach ($departments as $deptLower): ?>

                <?php
                // Options are deduped case-insensitively above; value carries
                // the canonical lowercase form (JS normalizes both sides the
                // same way). Display uses the first-seen original casing.
                $deptRaw = '';

                foreach ($directory as $deptRow) {
                  if (
                    strtolower(
                      trim(
                        (string) ($deptRow['dept'] ?? '')
                      )
                    ) === $deptLower
                  ) {
                    $deptRaw = (string) ($deptRow['dept'] ?? '');

                    break;
                  }
                }
                ?>

                <option value="<?php echo htmlspecialchars($deptLower, ENT_QUOTES); ?>">
                  <?php
                  echo htmlspecialchars(
                    pf_dept_label($deptRaw) !== '' ? pf_dept_label($deptRaw) : $deptLower,
                    ENT_QUOTES
                  );
                  ?>
                </option>

              <?php endforeach; ?>

            </select>

          </label>

          <label class="filter-select">

            <span class="sr-only">
              Performance status
            </span>

            <select id="perfFilter" class="perform-select perform-select--filter">

              <option value="">
                All statuses
              </option>

              <option value="exceeding">
                Exceeding
              </option>

              <option value="warning">
                Warning
              </option>

              <option value="below-target">
                Below Target
              </option>

              <option value="not-rated">
                Not yet rated
              </option>

            </select>

          </label>

          <label class="filter-select">

            <span class="sr-only">
              Account status
            </span>

            <select id="statusFilter" class="perform-select perform-select--filter">

              <option value="">
                All account statuses
              </option>

              <option value="Active">
                Active
              </option>

              <option value="Disabled">
                Disabled
              </option>

            </select>

          </label>

          <label class="filter-select">

            <span class="sr-only">
              Employment type
            </span>

            <select id="typeFilter" class="perform-select perform-select--filter">

              <option value="">
                All types
              </option>

              <option value="Probationary">
                Probationary
              </option>

              <option value="Regular">
                Regular
              </option>

            </select>

          </label>

        </div>

        <div class="filter-actions">

          <span class="directory-count" id="directoryCount" aria-live="polite">
            <?php echo count($directory); ?> employees
          </span>

          <label class="filter-select">

            <span class="sr-only">
              Sort by
            </span>

            <select id="sortDirectory" class="perform-select perform-select--filter">

              <option value="default">
                Default order
              </option>

              <option value="name">
                Name A–Z
              </option>

              <option value="days-left">
                Days left (soonest)
              </option>

            </select>

          </label>

          <button class="ghost-button" type="button" id="resetFiltersBtn">
            Reset
          </button>

        </div>

      </section>

      <section class="directory-panel" role="table" aria-label="Employee directory">

        <div class="directory-head" role="row">

          <span role="columnheader">
            Employee
          </span>

          <span role="columnheader">
            Department
          </span>

          <span role="columnheader">
            Probation Timeline / Triage
          </span>

          <span role="columnheader">
            Current Performance Status
          </span>

          <span role="columnheader">
            Action
          </span>

        </div>

        <?php if (empty($directory)): ?>

          <div class="empty-state">

            <p>
              No employees yet.
              Create the first profile to get started.
            </p>

            <a class="btn-primary" href="add_employee.php">
              Add Employee
            </a>

          </div>

        <?php else: ?>

          <div id="directoryRows">

            <?php foreach ($directory as $person): ?>

              <div class="directory-row" role="row" data-search="<?php
              echo htmlspecialchars(
                strtolower(
                  $person['name'] .
                  ' ' .
                  $person['email'] .
                  ' ' .
                  $person['dept'] .
                  ' ' .
                  $person['role'] .
                  ' ' .
                  $person['status']
                ),
                ENT_QUOTES
              );
              ?>" data-dept="<?php
              echo htmlspecialchars(
                $person['dept'],
                ENT_QUOTES
              );
              ?>" data-status="<?php
              echo htmlspecialchars(
                $person['status'],
                ENT_QUOTES
              );
              ?>" data-type="<?php
              echo htmlspecialchars(
                $person['type'],
                ENT_QUOTES
              );
              ?>" data-perf="<?php
              echo htmlspecialchars(
                $person['perfSlug'],
                ENT_QUOTES
              );
              ?>" data-role="<?php
              echo htmlspecialchars(
                $person['role'],
                ENT_QUOTES
              );
              ?>" data-days-left="<?php
              echo htmlspecialchars(
                isset($person['daysLeftValue'])
                ? (string) $person['daysLeftValue']
                : '',
                ENT_QUOTES
              );
              ?>" data-name="<?php
              echo htmlspecialchars(
                strtolower($person['name']),
                ENT_QUOTES
              );
              ?>" data-score="<?php
              echo htmlspecialchars(
                isset($person['scoreValue'])
                ? number_format((float) $person['scoreValue'], 1)
                : '',
                ENT_QUOTES
              );
              ?>">

                <div class="employee-cell" role="cell">

                  <div class="avatar avatar-local"
                    title="<?php echo htmlspecialchars($person['name'], ENT_QUOTES); ?>"
                    aria-hidden="true"><?php echo htmlspecialchars($person['initials'], ENT_QUOTES); ?></div>

                  <div>

                    <div class="employee-name">

                      <?php
                      echo htmlspecialchars(
                        $person['name'],
                        ENT_QUOTES
                      );
                      ?>

                    </div>

                    <div class="employee-email">

                      <?php
                      echo htmlspecialchars(
                        $person['email'],
                        ENT_QUOTES
                      );
                      ?>

                    </div>

                    <div class="employee-flags">

                      <span class="employee-flag<?php echo ($person['type'] ?? '') === 'Probationary' ? ' employee-flag-probation' : ''; ?>">
                        <?php echo htmlspecialchars($person['type'], ENT_QUOTES); ?>
                      </span>

                      <?php if (($person['status'] ?? '') === 'Disabled'): ?>

                        <span class="employee-flag employee-flag-disabled">
                          Disabled
                        </span>

                      <?php endif; ?>

                    </div>

                  </div>

                </div>

                <div role="cell" data-label="Department">

                  <?php
                  // Shorten role-derived pseudo-departments so the pill never
                  // forces its column wide; raw value stays in title.
                  $deptShort = $person['dept'];
                  if (strtolower(trim($deptShort)) === 'probationary employee') {
                    $deptShort = 'Probationary';
                  }
                  ?>

                  <span class="dept-pill" title="<?php
                  echo htmlspecialchars(
                    $person['dept'],
                    ENT_QUOTES
                  );
                  ?>">

                    <?php
                    echo htmlspecialchars(
                      pf_dept_label($deptShort),
                      ENT_QUOTES
                    );
                    ?>

                  </span>

                </div>

                <div role="cell" data-label="Probation Timeline">

                  <?php if (($person['type'] ?? '') === 'Probationary' && isset($person['daysLeftValue'], $person['daySinceValue'], $person['probationPercent'])): ?>

                    <div class="timeline-cell" data-tone="<?php echo htmlspecialchars($person['triageTone'] !== '' ? $person['triageTone'] : 'ok', ENT_QUOTES); ?>">

                      <span class="employee-triage<?php echo $person['triageTone'] !== '' ? ' triage-' . htmlspecialchars($person['triageTone'], ENT_QUOTES) : ''; ?>">
                        <?php echo (int) $person['daysLeftValue']; ?> days left
                        <span class="timeline-period">(Day <?php echo (int) $person['daySinceValue']; ?>/<?php echo (int) $person['probationPeriod']; ?>)</span>
                      </span>

                      <span class="pf-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                        aria-valuenow="<?php echo (int) $person['probationPercent']; ?>"
                        aria-label="Probation progress: day <?php echo (int) $person['daySinceValue']; ?> of <?php echo (int) $person['probationPeriod']; ?>">
                        <span class="pf-meter-fill" style="width: <?php echo (int) $person['probationPercent']; ?>%"></span>
                      </span>

                    </div>

                  <?php else: ?>

                    <span class="muted-cell">—</span>

                  <?php endif; ?>

                </div>

                <div role="cell" data-label="Performance">

                  <span class="status-pill <?php
                  echo htmlspecialchars(
                    $person['perfClass'],
                    ENT_QUOTES
                  );
                  ?>">

                    <?php
                    echo htmlspecialchars(
                      $person['perfLabel'],
                      ENT_QUOTES
                    );
                    ?>

                  </span>

                </div>

                <div role="cell" data-label="Actions">

                  <a class="ghost-button" href="employee_view.php?uid=<?php echo urlencode($person['uid']); ?>"
                    aria-label="Manage <?php echo htmlspecialchars($person['name'], ENT_QUOTES); ?>">
                    View
                  </a>

                </div>

              </div>

            <?php endforeach; ?>

          </div>

          <div id="noFilterResults" class="dashboard-empty" hidden>
            <p>No employees match these filters.</p>
            <button class="ghost-button" type="button" id="clearFiltersBtn">Reset filters</button>
          </div>

        <?php endif; ?>

        <div class="pagination-bar">

          <span id="paginationSummary" aria-live="polite">

            Showing
            <strong>
              <?php
              echo min(
                count($directory),
                8
              );
              ?>
            </strong>

            of

            <strong>
              <?php
              echo count($directory);
              ?>
            </strong>

            employees

          </span>

          <div class="page-buttons">

            <button class="page-btn" type="button" id="prevPageBtn">
              Previous
            </button>

            <nav class="page-numbers" id="pageNumbers" aria-label="Directory pages"></nav>

            <span id="pageIndicator" class="sr-only" aria-live="polite"></span>

            <button class="page-btn" type="button" id="nextPageBtn">
              Next
            </button>

          </div>

        </div>

      </section>

    </main>

  </div>

  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
  <script src="<?php echo htmlspecialchars(employer_asset('employees.js'), ENT_QUOTES); ?>"></script>

  <script>
    window.__pfIndex = <?php echo $pfPaletteJson !== false ? $pfPaletteJson : '[]'; ?>;
  </script>

</body>

</html>