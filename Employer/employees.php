<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
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
$icons = [
  'search' => employer_icon('search'),
  'plus' => employer_icon('plus'),
  'download' => employer_icon('download'),
];

function normalize_role_key(?string $role): string
{
  $roleKey = strtolower(trim((string) $role));

  if (strpos($roleKey, 'probation') !== false) {
    return 'probationary';
  }

  if (strpos($roleKey, 'supervis') !== false) {
    return 'supervisor';
  }

  if (strpos($roleKey, 'employ') !== false) {
    return 'employer';
  }

  if (strpos($roleKey, 'admin') !== false) {
    return 'admin';
  }

  return $roleKey;
}

function display_role_label(?string $role): string
{
  $raw = trim((string) $role);

  return $raw !== ''
    ? ucwords(
      str_replace(
        '_',
        ' ',
        $raw
      )
    )
    : 'Employee';
}

$deptClassCycle = [
  'dept-blue',
  'dept-gray',
  'dept-orange',
  'dept-green',
  'dept-purple'
];

function department_pill_class(
  string $dept,
  array $cycle
): string {
  $index =
    abs(
      crc32(
        strtolower($dept)
      )
    ) % count($cycle);

  return $cycle[$index];
}

/* =========================================================
   FAST SHORT-SESSION CACHING
   ========================================================= */

$directory = [];

$cacheKey =
  'performa_employee_directory';

$cacheTimeKey =
  'performa_employee_directory_time';

$cacheTTL = 20;

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
          }
        }
      }

      $roleLabel =
        display_role_label(
          $doc['role']
          ?? null
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

        'deptClass' =>
          department_pill_class(
            $dept,
            $deptClassCycle
          ),

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

        'statusClass' =>
          $status === 'Disabled'
          ? 'status-danger'
          : 'status-good',
      ];
    }

    $_SESSION[$cacheKey] =
      $directory;

    $_SESSION[$cacheTimeKey] =
      time();

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

$departments =
  array_values(
    array_unique(
      array_column(
        $directory,
        'dept'
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

    <main class="main">

      <?php ob_start(); ?>

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

      <div class="filter-bar">

        <div class="filter-group">

          <label class="filter-select">

            <span>
              Department:
            </span>

            <select id="deptFilter" class="perform-select perform-select--filter">

              <option value="">
                All Departments
              </option>

              <?php foreach ($departments as $dept): ?>

                <option value="<?php echo htmlspecialchars($dept, ENT_QUOTES); ?>">
                  <?php
                  echo htmlspecialchars(
                    pf_dept_label($dept) !== '' ? pf_dept_label($dept) : $dept,
                    ENT_QUOTES
                  );
                  ?>
                </option>

              <?php endforeach; ?>

            </select>

          </label>

          <label class="filter-select">

            <span>
              Status:
            </span>

            <select id="statusFilter" class="perform-select perform-select--filter">

              <option value="">
                All Statuses
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

            <span>
              Type:
            </span>

            <select id="typeFilter" class="perform-select perform-select--filter">

              <option value="">
                All Types
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

          <button class="ghost-button" type="button" id="resetFiltersBtn">
            Reset
          </button>

          <label class="filter-select">

            <span>
              Sort by:
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

          <button class="ghost-button" type="button" id="exportDirectoryBtn">
            <span aria-hidden="true">
              <?php
              echo $icons['download'];
              ?>
            </span>
            Export
          </button>

        </div>

      </div>

      <section class="directory-panel" role="table" aria-label="Employee directory">

        <div class="directory-head" role="row">

          <span role="columnheader">
            Employee
          </span>

          <span role="columnheader">
            Role
          </span>

          <span role="columnheader">
            Department
          </span>

          <span role="columnheader">
            Employment Type
          </span>

          <span role="columnheader">
            Status
          </span>

          <span role="columnheader">
            Actions
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
                  $person['dept']
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

                    <?php if (($person['type'] ?? '') === 'Probationary'): ?>

                      <div class="employee-triage">

                        <?php if (isset($person['daySinceValue']) && isset($person['daysLeftValue'])): ?>
                          <?php echo htmlspecialchars(pf_day((int) $person['daySinceValue']), ENT_QUOTES); ?>
                          ·
                          <?php echo (int) $person['daysLeftValue']; ?> days left
                        <?php endif; ?>

                        <?php if (isset($person['scoreValue'])): ?>
                          ·
                          <?php echo htmlspecialchars(pf_score_pair($person['scoreValue']), ENT_QUOTES); ?>
                        <?php else: ?>
                          ·
                          Not yet rated
                        <?php endif; ?>

                      </div>

                    <?php endif; ?>

                  </div>

                </div>

                <div class="muted-cell" role="cell" data-label="Role">

                  <?php
                  echo htmlspecialchars(
                    $person['role'],
                    ENT_QUOTES
                  );
                  ?>

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

                  <span class="dept-pill <?php
                  echo htmlspecialchars(
                    $person['deptClass'],
                    ENT_QUOTES
                  );
                  ?>" title="<?php
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

                <div class="muted-cell" role="cell" data-label="Employment Type">

                  <?php
                  echo htmlspecialchars(
                    $person['type'],
                    ENT_QUOTES
                  );
                  ?>

                </div>

                <div role="cell" data-label="Status">

                  <span class="status-pill <?php
                  echo htmlspecialchars(
                    $person['statusClass'],
                    ENT_QUOTES
                  );
                  ?>">

                    <?php
                    echo htmlspecialchars(
                      $person['status'],
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
              echo count($directory);
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

            <span id="pageIndicator" class="page-indicator"
              aria-live="polite"></span>

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