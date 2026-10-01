<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/../security_utils.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../audit_log.php';
require_once __DIR__ . '/includes/collection_cache.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Resend welcome credentials (used when the original welcome email failed
// delivery). Rotates to a FRESH password rather than re-sending anything
// previously displayed, forces rotation, and emails it — the manual relay
// path stays a last resort, never the default.
$resendError = '';

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'resend_welcome' &&
  !empty($_POST['email'])
) {
  $resendEmail = strtolower(trim((string) $_POST['email']));

  try {
    $match = null;

    foreach (firestore_list_documents('Users') as $doc) {
      if (strtolower(trim((string) ($doc['email'] ?? ''))) === $resendEmail) {
        $match = $doc;

        break;
      }
    }

    if (!$match || empty($match['uid'])) {
      throw new RuntimeException('Account not found for that email.');
    }

    require_employer_owns_user(
      $match + ['uid' => $match['uid']],
      'employees:resend_welcome'
    );

    $freshPassword = generate_secure_password(12);
    identitytoolkit_update_password($match['uid'], $freshPassword);
    $match['mustChangePassword'] = true;
    firestore_write_document('Users', $match['uid'], $match);

    $loginUrl = app_base_url() . '/login.php';
    $resent = send_transactional_email(
      $match['email'],
      $match['name'] ?? $match['email'],
      'Your Performa account is ready',
      welcome_email_html(
        $match['name'] ?? $match['email'],
        $match['email'],
        $freshPassword,
        $loginUrl
      )
    );

    unset($_SESSION['reveal_once_password'], $_SESSION['reveal_once_email']);

    record_audit_event(
      'welcome_email_resent',
      'Resent welcome credentials to ' . $match['email'],
      ['uid' => $match['uid'], 'emailDelivered' => $resent]
    );

    header(
      'Location: employees.php?' . http_build_query([
        'resent' => '1',
        'name' => $match['name'] ?? $match['email'],
        'email' => $match['email'],
        'emailed' => $resent ? '1' : '0',
      ])
    );

    exit;
  } catch (Throwable $e) {
    $resendError =
      'Could not resend the welcome email: ' . $e->getMessage();
  }
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
  'mail' => employer_icon('mail'),
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

/* =========================================================
   STATS CALCULATION FOR DIRECTORY STATS CARDS
   ========================================================= */
$statTotal = count($directory);
$statProb = 0;
$statReg = 0;
$statDue30 = 0;
$statBelow = 0;
$statExceed = 0;

foreach ($directory as $e) {
  $isProb = ($e['type'] ?? '') === 'Probationary';
  if ($isProb) {
    $statProb++;
    if (isset($e['daysLeftValue']) && $e['daysLeftValue'] <= 30 && $e['daysLeftValue'] > 0) {
      $statDue30++;
    }
  } else {
    $statReg++;
  }

  $slug = $e['perfSlug'] ?? 'none';
  $lbl = strtolower((string)($e['perfLabel'] ?? ''));
  if ($slug === 'below' || strpos($lbl, 'needs review') !== false || strpos($lbl, 'below') !== false) {
    $statBelow++;
  } elseif ($slug === 'exceed' || strpos($lbl, 'exceed') !== false) {
    $statExceed++;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <title>Employees | Performa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('Employees'); ?>

    <main class="main employees-page" id="employeesMain">
      <div class="in">

        <div class="top">
          <div>
            <h1 id="h1">Employees</h1>
            <p class="sub">Manage and organize your workforce directory.</p>
          </div>
          <div class="tr">
            <button type="button" class="btn txt" id="exp">Export list</button>
            <a href="register_employee.php" class="btn primary" style="text-decoration:none;">+ Add employee</a>
          </div>
        </div>

        <?php if (!empty($resendError)): ?>
          <div class="alert alert-error" role="status" aria-live="polite" style="margin-bottom:20px;">
            <?php echo htmlspecialchars($resendError, ENT_QUOTES); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($flashEmail)): ?>
          <div class="alert alert-success" role="status" aria-live="polite" style="margin-bottom:20px;">
            Fresh credentials generated and emailed to <strong><?php echo htmlspecialchars($flashEmail, ENT_QUOTES); ?></strong>.
          </div>
        <?php endif; ?>

        <section class="stats" id="stats">
          <div class="st">
            <div class="l">Employees</div>
            <div class="v num"><?php echo (int) $statTotal; ?></div>
            <div class="n"><?php echo (int) $statReg; ?> regular</div>
          </div>
          <div class="st">
            <div class="l">On probation</div>
            <div class="v num"><?php echo (int) $statProb; ?></div>
            <div class="n"><?php echo (int) $statDue30; ?> due within 30 days</div>
          </div>
          <div class="st">
            <div class="l">Below target</div>
            <div class="v num" style="color:var(--bad)"><?php echo (int) $statBelow; ?></div>
            <div class="n">Latest score under target</div>
          </div>
          <div class="st">
            <div class="l">Exceeding target</div>
            <div class="v num" style="color:var(--ok)"><?php echo (int) $statExceed; ?></div>
            <div class="n">Score at or above target</div>
          </div>
        </section>

        <section class="cd tbl">
          <div class="tb">
            <div class="seg" role="tablist" id="tabs">
              <button type="button" data-t="all" role="tab" aria-selected="true">All<span class="num"><?php echo (int) $statTotal; ?></span></button>
              <button type="button" data-t="prob" role="tab" aria-selected="false">Probationary<span class="num"><?php echo (int) $statProb; ?></span></button>
              <button type="button" data-t="reg" role="tab" aria-selected="false">Regular<span class="num"><?php echo (int) $statReg; ?></span></button>
            </div>
            <div class="f">
              <input type="search" id="q" placeholder="Search name, email, department" aria-label="Search employees" autocomplete="off" />
              <select id="dept" aria-label="Department">
                <option value="all">All departments</option>
                <?php foreach ($departments as $d): ?>
                  <option value="<?php echo htmlspecialchars($d, ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars(ucfirst($d), ENT_QUOTES); ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <select id="perf" aria-label="Performance">
                <option value="all">All performance</option>
                <option value="below">Below target</option>
                <option value="exceed">Exceeding</option>
                <option value="none">Not yet rated</option>
              </select>
              <select id="sort" aria-label="Sort">
                <option value="def">Default order</option>
                <option value="name">Name A to Z</option>
                <option value="left">Fewest days left</option>
                <option value="score">Lowest score</option>
              </select>
              <button type="button" class="btn txt" id="clr" hidden>Clear filters</button>
            </div>
          </div>

          <div class="rw rh">
            <span>Employee</span>
            <span>Department</span>
            <span>Probation timeline</span>
            <span>Performance</span>
            <span></span>
          </div>

          <div id="list">
            <?php if (!$directory): ?>
              <div class="empty">No employees found.</div>
            <?php else: ?>
              <?php foreach ($directory as $idx => $row): ?>
                <?php
                $isProb = ($row['type'] ?? '') === 'Probationary';
                $perfFilterSlug = 'none';
                $perfDotColor = 'var(--mut)';
                $perfDisplayLabel = 'Not yet rated';

                if ($row['scoreValue'] !== null) {
                  $sc = (float) $row['scoreValue'];
                  $target = isset($row['targetAvgValue']) ? (float) $row['targetAvgValue'] : 3.0;
                  if ($sc < $target) {
                    $perfFilterSlug = 'below';
                    $perfDotColor = 'var(--bad)';
                    $perfDisplayLabel = 'Below target';
                  } else {
                    $perfFilterSlug = 'exceed';
                    $perfDotColor = 'var(--ok)';
                    $perfDisplayLabel = 'Exceeding';
                  }
                } elseif (!empty($row['perfLabel'])) {
                  $lbl = strtolower((string) $row['perfLabel']);
                  if (strpos($lbl, 'below') !== false || strpos($lbl, 'risk') !== false || strpos($lbl, 'review') !== false) {
                    $perfFilterSlug = 'below';
                    $perfDotColor = 'var(--bad)';
                    $perfDisplayLabel = 'Below target';
                  } elseif (strpos($lbl, 'exceed') !== false) {
                    $perfFilterSlug = 'exceed';
                    $perfDotColor = 'var(--ok)';
                    $perfDisplayLabel = 'Exceeding';
                  }
                }
                ?>
                <div class="row rw"
                  data-idx="<?php echo (int) $idx; ?>"
                  data-id="<?php echo htmlspecialchars($row['uid'], ENT_QUOTES); ?>"
                  data-n="<?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>"
                  data-em="<?php echo htmlspecialchars($row['email'], ENT_QUOTES); ?>"
                  data-d="<?php echo htmlspecialchars(strtolower((string)($row['dept'] ?? '')), ENT_QUOTES); ?>"
                  data-t="<?php echo $isProb ? 'prob' : 'reg'; ?>"
                  data-day="<?php echo $isProb && $row['daySinceValue'] !== null ? (int)$row['daySinceValue'] : -1; ?>"
                  data-left="<?php echo $isProb && $row['daysLeftValue'] !== null ? (int)$row['daysLeftValue'] : -1; ?>"
                  data-sc="<?php echo $row['scoreValue'] !== null ? (float)$row['scoreValue'] : -1; ?>"
                  data-pf="<?php echo htmlspecialchars($perfFilterSlug, ENT_QUOTES); ?>">

                  <div class="e" data-a="view" data-uid="<?php echo htmlspecialchars($row['uid'], ENT_QUOTES); ?>" style="cursor:pointer;">
                    <span class="av"><?php echo htmlspecialchars($row['initials'], ENT_QUOTES); ?></span>
                    <div class="nm">
                      <b>
                        <?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>
                        <?php if ($isProb): ?>
                          <span class="ty" style="display:inline">Probationary</span>
                        <?php endif; ?>
                      </b>
                      <?php if (!empty($row['email'])): ?>
                        <span class="sm2"><?php echo htmlspecialchars($row['email'], ENT_QUOTES); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <span class="dp sm2" style="font-size:13.5px"><?php echo htmlspecialchars(!empty($row['dept']) ? ucfirst($row['dept']) : '-', ENT_QUOTES); ?></span>

                  <div class="tm">
                    <?php if ($isProb && $row['daySinceValue'] !== null && $row['daysLeftValue'] !== null): ?>
                      <?php
                      $probPeriod = $row['probationPeriod'] ?? 180;
                      $dayVal = (int) $row['daySinceValue'];
                      $leftVal = (int) $row['daysLeftValue'];
                      $pctVal = (float) ($probPeriod > 0 ? min(100, max(0, round($dayVal / $probPeriod * 100))) : 0);
                      $isLate = $leftVal <= 30;
                      ?>
                      <span class="num" style="font-size:12.5px">Day <?php echo $dayVal; ?></span>
                      <span class="sm2 num"> · <?php echo $leftVal; ?> left</span>
                      <div class="track">
                        <div class="fill <?php echo $isLate ? 'late' : ''; ?>" style="width:<?php echo $pctVal; ?>%"></div>
                      </div>
                    <?php else: ?>
                      <span class="sm2">-</span>
                    <?php endif; ?>
                  </div>

                  <span class="ps stt">
                    <i style="background:<?php echo $perfDotColor; ?>"></i>
                    <?php echo htmlspecialchars($perfDisplayLabel, ENT_QUOTES); ?>
                    <?php if ($row['scoreValue'] !== null && $perfFilterSlug !== 'none'): ?>
                      <span class="num sm2"><?php echo number_format((float)$row['scoreValue'], 1); ?></span>
                    <?php endif; ?>
                  </span>

                  <div class="act">
                    <a href="employee_view.php?uid=<?php echo urlencode($row['uid']); ?>" class="btn tint" style="text-decoration:none;">View</a>
                    <?php if (!empty($row['email'])): ?>
                      <a href="mailto:<?php echo htmlspecialchars($row['email'], ENT_QUOTES); ?>" class="btn x" title="Email <?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>" aria-label="Email <?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                      </a>
                    <?php else: ?>
                      <button type="button" class="btn x" disabled title="No email on file">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/></svg>
                      </button>
                    <?php endif; ?>
                  </div>

                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="ft">
            <span id="cap"></span>
            <div>
              <button type="button" class="btn tint" id="prev" disabled>Previous</button>
              <button type="button" class="btn tint" id="next" disabled>Next</button>
            </div>
          </div>
        </section>

      </div>
    </main>

    <div class="toast" id="toast" hidden></div>
  </div>

  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

  <script>
    window.__pfIndex = <?php echo $pfPaletteJson !== false ? $pfPaletteJson : '[]'; ?>;
  </script>

  <script>
    (function () {
      var list = document.getElementById('list');
      if (!list) return;
      var qInput = document.getElementById('q');
      var deptSelect = document.getElementById('dept');
      var perfSelect = document.getElementById('perf');
      var sortSelect = document.getElementById('sort');
      var clrBtn = document.getElementById('clr');
      var cap = document.getElementById('cap');
      var prevBtn = document.getElementById('prev');
      var nextBtn = document.getElementById('next');
      var tabBtns = Array.from(document.querySelectorAll('#tabs button'));
      var expBtn = document.getElementById('exp');
      var toastEl = document.getElementById('toast');
      var toastTimer;

      function toast(msg) {
        if (!toastEl) return;
        clearTimeout(toastTimer);
        toastEl.hidden = false;
        toastEl.textContent = msg;
        toastTimer = setTimeout(function () { toastEl.hidden = true; }, 3500);
      }

      var PS = 8;
      var tab = 'all';
      var page = 1;
      var rows = Array.from(list.querySelectorAll('.row'));

      function isDirty() {
        return (qInput && qInput.value.trim() !== '') ||
               (deptSelect && deptSelect.value !== 'all') ||
               (perfSelect && perfSelect.value !== 'all') ||
               (sortSelect && sortSelect.value !== 'def') ||
               tab !== 'all';
      }

      function getMatched() {
        var query = qInput ? qInput.value.trim().toLowerCase() : '';
        var dVal = deptSelect ? deptSelect.value : 'all';
        var pVal = perfSelect ? perfSelect.value : 'all';
        var sVal = sortSelect ? sortSelect.value : 'def';

        var filtered = rows.filter(function (r) {
          if (tab !== 'all') {
            var tType = r.getAttribute('data-t') || '';
            if (tType !== tab) return false;
          }
          if (dVal !== 'all') {
            var rDept = r.getAttribute('data-d') || '';
            if (rDept !== dVal) return false;
          }
          if (pVal !== 'all') {
            var rPerf = r.getAttribute('data-pf') || '';
            if (rPerf !== pVal) return false;
          }
          if (query) {
            var n = (r.getAttribute('data-n') || '').toLowerCase();
            var em = (r.getAttribute('data-em') || '').toLowerCase();
            var dp = (r.getAttribute('data-d') || '').toLowerCase();
            if (n.indexOf(query) === -1 && em.indexOf(query) === -1 && dp.indexOf(query) === -1) {
              return false;
            }
          }
          return true;
        });

        filtered.sort(function (a, b) {
          if (sVal === 'name') {
            var na = (a.getAttribute('data-n') || '').toLowerCase();
            var nb = (b.getAttribute('data-n') || '').toLowerCase();
            return na.localeCompare(nb);
          } else if (sVal === 'left') {
            var la = parseInt(a.getAttribute('data-left') || '-1', 10);
            var lb = parseInt(b.getAttribute('data-left') || '-1', 10);
            if (la === -1 && lb === -1) return 0;
            if (la === -1) return 1;
            if (lb === -1) return -1;
            return la - lb;
          } else if (sVal === 'score') {
            var sa = parseFloat(a.getAttribute('data-sc') || '-1');
            var sb = parseFloat(b.getAttribute('data-sc') || '-1');
            if (sa === -1 && sb === -1) return 0;
            if (sa === -1) return 1;
            if (sb === -1) return -1;
            return sa - sb;
          } else {
            var ia = parseInt(a.getAttribute('data-idx') || '0', 10);
            var ib = parseInt(b.getAttribute('data-idx') || '0', 10);
            return ia - ib;
          }
        });

        return filtered;
      }

      function render() {
        var matched = getMatched();
        var total = matched.length;
        var pages = Math.max(1, Math.ceil(total / PS));
        if (page > pages) page = pages;

        rows.forEach(function (r) { r.style.display = 'none'; });

        var start = (page - 1) * PS;
        var end = Math.min(page * PS, total);
        matched.slice(start, end).forEach(function (r) {
          r.style.display = '';
          list.appendChild(r);
        });

        if (cap) {
          cap.textContent = total ? ('Showing ' + (total === 0 ? 0 : start + 1) + ' to ' + end + ' of ' + total) : 'No employees match.';
        }

        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = page >= pages || total === 0;
        if (clrBtn) clrBtn.hidden = !isDirty();

        tabBtns.forEach(function (b) {
          var isSel = b.getAttribute('data-t') === tab;
          b.setAttribute('aria-selected', isSel ? 'true' : 'false');
        });
      }

      tabBtns.forEach(function (b) {
        b.addEventListener('click', function () {
          tab = b.getAttribute('data-t') || 'all';
          page = 1;
          render();
        });
      });

      if (qInput) qInput.addEventListener('input', function () { page = 1; render(); });
      if (deptSelect) deptSelect.addEventListener('change', function () { page = 1; render(); });
      if (perfSelect) perfSelect.addEventListener('change', function () { page = 1; render(); });
      if (sortSelect) sortSelect.addEventListener('change', function () { page = 1; render(); });

      if (clrBtn) {
        clrBtn.addEventListener('click', function () {
          if (qInput) qInput.value = '';
          if (deptSelect) deptSelect.value = 'all';
          if (perfSelect) perfSelect.value = 'all';
          if (sortSelect) sortSelect.value = 'def';
          tab = 'all';
          page = 1;
          render();
        });
      }

      if (prevBtn) {
        prevBtn.addEventListener('click', function () {
          if (page > 1) { page--; render(); }
        });
      }
      if (nextBtn) {
        nextBtn.addEventListener('click', function () {
          page++; render();
        });
      }

      document.addEventListener('click', function (e) {
        var viewEl = e.target.closest('.e[data-a="view"]');
        if (viewEl) {
          var uid = viewEl.getAttribute('data-uid');
          if (uid) {
            window.location.href = 'employee_view.php?uid=' + encodeURIComponent(uid);
          }
        }
      });

      if (expBtn) {
        expBtn.addEventListener('click', function () {
          var matched = getMatched();
          if (!matched.length) {
            toast('No employees to export');
            return;
          }
          var csvRows = [["Name", "Email", "Department", "Type", "Day", "Days Left", "Score", "Performance"].join(',')];
          matched.forEach(function (r) {
            var n = '"' + (r.getAttribute('data-n') || '').replace(/"/g, '""') + '"';
            var em = '"' + (r.getAttribute('data-em') || '').replace(/"/g, '""') + '"';
            var d = '"' + (r.getAttribute('data-d') || '').replace(/"/g, '""') + '"';
            var t = r.getAttribute('data-t') === 'prob' ? 'Probationary' : 'Regular';
            var day = r.getAttribute('data-day') !== '-1' ? r.getAttribute('data-day') : '';
            var left = r.getAttribute('data-left') !== '-1' ? r.getAttribute('data-left') : '';
            var sc = r.getAttribute('data-sc') !== '-1' ? r.getAttribute('data-sc') : '';
            var pf = r.getAttribute('data-pf') || '';
            csvRows.push([n, em, d, t, day, left, sc, pf].join(','));
          });
          var blob = new Blob([csvRows.join('\n')], { type: 'text/csv;charset=utf-8;' });
          var url = URL.createObjectURL(blob);
          var a = document.createElement('a');
          a.href = url;
          a.download = 'employees_directory.csv';
          document.body.appendChild(a);
          a.click();
          document.body.removeChild(a);
          URL.revokeObjectURL(url);
          toast('Exported ' + matched.length + ' employees');
        });
      }

      render();
    })();
  </script>
</body>
</html>
