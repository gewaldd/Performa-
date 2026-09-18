<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/employer_layout.php';

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

      <section class="page-header" aria-labelledby="employeesTitle">

        <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
        </button>

        <div class="ph-main">

          <span class="eyebrow">Workforce</span>

          <h1 id="employeesTitle">
            Employees
          </h1>

          <p>
            Manage and organize your workforce directory.
          </p>

        </div>

        <div class="ph-actions">

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

        </div>

      </section>

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
                    $dept,
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

          <button class="reset-button" type="button" id="resetFiltersBtn">
            Reset
          </button>

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
                      $deptShort,
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

</body>

</html>