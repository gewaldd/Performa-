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
  <?php employer_brand_head(); ?>
  <title>Employees | Performa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('Employees'); ?>

    <main class="main employees-page" id="employeesMain">
      <div class="cq"><div class="wrap">

        <header>
          <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
            <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>
            </svg>
          </button>
          <div>
            <h1 id="h1">Employees</h1>
            <p class="sub"><span class="mono"><?php echo (int) $statTotal; ?></span> employees &middot; <span class="mono"><?php echo (int) $statProb; ?></span> on probation &middot; <span class="mono"><?php echo (int) $statBelow; ?></span> below target</p>
          </div>
          <div class="hdr-r">
            <button type="button" class="link" id="exp">Export</button>
            <a href="add_employee.php" class="btn"><svg class="i"><use href="#i-plus"/></svg>Add employee</a>
          </div>
        </header>

        <?php if (!empty($resendError)): ?>
          <div class="alert alert-error" role="status" aria-live="polite" style="margin-bottom:20px;">
            <?php echo htmlspecialchars($resendError, ENT_QUOTES); ?>
          </div>
        <?php endif; ?>

          <?php if (isset($_GET['resent']) && ($_GET['emailed'] ?? '') === '1'): ?>
            <div class="alert alert-success" role="status" aria-live="polite" style="margin-bottom:20px;">
              Fresh credentials generated and emailed to <strong><?php echo htmlspecialchars($_GET['name'] ?? '', ENT_QUOTES); ?></strong>.
            </div>
          <?php elseif (isset($_GET['resent'])): ?>
            <div class="alert alert-error" role="status" aria-live="polite" style="margin-bottom:20px;">
              Password rotated for <strong><?php echo htmlspecialchars($_GET['name'] ?? '', ENT_QUOTES); ?></strong>,
              but the email could not be delivered. Check the Brevo configuration, then resend again.
            </div>
          <?php endif; ?>

        <div class="bar">
          <div class="tabs" role="tablist" id="tabs" aria-label="Employment type">
            <button type="button" class="tab" data-t="all" role="tab" aria-selected="true">All<span class="n"><?php echo (int) $statTotal; ?></span></button>
            <button type="button" class="tab" data-t="prob" role="tab" aria-selected="false">Probationary<span class="n"><?php echo (int) $statProb; ?></span></button>
            <button type="button" class="tab" data-t="reg" role="tab" aria-selected="false">Regular<span class="n"><?php echo (int) $statReg; ?></span></button>
          </div>
          <div class="tools">
            <div class="search">
              <svg class="i"><use href="#i-search"/></svg>
              <input type="search" id="q" class="f" placeholder="Search name or email" aria-label="Search employees" autocomplete="off" />
            </div>
            <select id="dept" class="f" aria-label="Department">
              <option value="all">All departments</option>
              <?php foreach ($departments as $d): ?>
                <option value="<?php echo htmlspecialchars($d, ENT_QUOTES); ?>">
                  <?php echo htmlspecialchars(ucfirst($d), ENT_QUOTES); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <select id="perf" class="f" aria-label="Performance">
              <option value="all">All performance</option>
              <option value="below">Below target</option>
              <option value="exceed">Exceeding</option>
              <option value="none">Not rated</option>
            </select>
            <button type="button" class="clear" id="clr" hidden>Clear</button>
          </div>
        </div>

        <div class="head">
          <button type="button" class="sort" data-k="name">Employee<svg class="i"><use href="#i-up"/></svg></button>
          <span>Department</span>
          <button type="button" class="sort" data-k="day">Probation<svg class="i"><use href="#i-up"/></svg></button>
          <button type="button" class="sort" data-k="score">Performance<svg class="i"><use href="#i-up"/></svg></button>
          <span></span>
        </div>

        <div id="rows">
            <?php if (!$directory): ?>
              <div class="empty"><b>No employees match</b>Try a different search or clear the filters.</div>
            <?php else: ?>
              <?php foreach ($directory as $idx => $row): ?>
                <?php
                $isProb = ($row['type'] ?? '') === 'Probationary';
                $perfFilterSlug = 'none';
                $perfDotColor = 'var(--ink-3)';
                $perfDisplayLabel = 'Not rated';

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
                <?php
                $perfLevel = 'none';
                if ($perfFilterSlug === 'below') {
                  $perfLevel = 'bad';
                } elseif ($perfFilterSlug === 'exceed') {
                  $perfLevel = 'good';
                }
                ?>
                <a class="row"
                  href="employee_view.php?uid=<?php echo urlencode($row['uid']); ?>"
                  aria-label="Open <?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>"
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

                  <div class="who">
                    <span class="av"><?php echo htmlspecialchars($row['initials'], ENT_QUOTES); ?></span>
                    <div>
                      <b><?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?></b>
                      <?php if (!empty($row['email'])): ?>
                        <span><?php echo htmlspecialchars($row['email'], ENT_QUOTES); ?></span>
                      <?php endif; ?>
                    </div>
                  </div>

                  <?php
                  $deptDisplay = !empty($row['dept']) ? ucfirst($row['dept']) : (!empty($row['role']) ? ucfirst($row['role']) : '-');
                  ?>
                  <span class="dept"><?php echo htmlspecialchars($deptDisplay, ENT_QUOTES); ?></span>

                  <div class="prob">
                    <?php if ($isProb && $row['daySinceValue'] !== null && $row['daysLeftValue'] !== null): ?>
                      <?php
                      $probPeriod = $row['probationPeriod'] ?? 180;
                      $dayVal = (int) $row['daySinceValue'];
                      $leftVal = (int) $row['daysLeftValue'];
                      $pctVal = (float) ($probPeriod > 0 ? min(100, max(0, round($dayVal / $probPeriod * 100))) : 0);
                      ?>
                      <div class="t"><span><b class="mono">Day <?php echo $dayVal; ?></b> of <?php echo (int) $probPeriod; ?></span><span class="mono"><?php echo $leftVal; ?> left</span></div>
                      <div class="track"><i style="width:<?php echo $pctVal; ?>%"></i></div>
                    <?php else: ?>
                      <span class="reg">Regular</span>
                    <?php endif; ?>
                  </div>

                  <div class="perf <?php echo $perfLevel; ?>">
                    <span class="dot"></span><?php echo htmlspecialchars($perfDisplayLabel, ENT_QUOTES); ?><?php if ($row['scoreValue'] !== null && $perfFilterSlug !== 'none'): ?><span class="mono"><?php echo number_format((float)$row['scoreValue'], 1); ?></span><?php endif; ?>
                  </div>
                  <svg class="i go"><use href="#i-go"/></svg>

                </a>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="foot" id="foot"></div>

      </div></div>
    </main>

    <div class="toast" id="toast" role="status" hidden></div>
  </div>

  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

  <script>
    window.__pfIndex = <?php echo $pfPaletteJson !== false ? $pfPaletteJson : '[]'; ?>;
  </script>

    <script>
    (function () {
      var list = document.getElementById('rows');
      if (!list) return;
      var qInput = document.getElementById('q');
      var deptSelect = document.getElementById('dept');
      var perfSelect = document.getElementById('perf');
      var clrBtn = document.getElementById('clr');
      var foot = document.getElementById('foot');
      var tabBtns = Array.from(document.querySelectorAll('#tabs .tab'));
      var expBtn = document.getElementById('exp');
      var toastEl = document.getElementById('toast');
      var toastTimer;

      function toast(msg) {
        if (!toastEl) return;
        clearTimeout(toastTimer);
        toastEl.hidden = false;
        toastEl.classList.add('on');
        toastEl.textContent = msg;
        toastTimer = setTimeout(function () {
          toastEl.classList.remove('on');
          toastEl.hidden = true;
        }, 3500);
      }

      var tab = 'all';
      var sortK = null;
      var sortDir = 'asc';
      var totals = { all: 0, prob: 0, reg: 0 };
      var rows = Array.from(list.querySelectorAll('.row'));
      rows.forEach(function (r) {
        var t = r.getAttribute('data-t');
        totals.all++;
        if (t === 'prob') totals.prob++;
        if (t === 'reg') totals.reg++;
      });

      function isDirty() {
        return (qInput && qInput.value.trim() !== '') ||
               (deptSelect && deptSelect.value !== 'all') ||
               (perfSelect && perfSelect.value !== 'all') ||
               tab !== 'all';
      }

      function sortVal(r, k) {
        if (k === 'name') return (r.getAttribute('data-n') || '').toLowerCase();
        if (k === 'day') {
          var d = parseInt(r.getAttribute('data-day') || '-1', 10);
          return d === -1 ? 9999 : d;
        }
        var s = parseFloat(r.getAttribute('data-sc') || '-1');
        return s === -1 ? -1 : s;
      }

      function getMatched() {
        var query = qInput ? qInput.value.trim().toLowerCase() : '';
        var dVal = deptSelect ? deptSelect.value : 'all';
        var pVal = perfSelect ? perfSelect.value : 'all';

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

        if (sortK) {
          filtered.sort(function (a, b) {
            var x = sortVal(a, sortK), y = sortVal(b, sortK);
            return (x < y ? -1 : x > y ? 1 : 0) * (sortDir === 'asc' ? 1 : -1);
          });
        }

        return filtered;
      }

      function render() {
        var matched = getMatched();

        rows.forEach(function (r) { r.style.display = 'none'; });

        var emptyDyn = list.querySelector('.empty-dyn');
        if (emptyDyn) emptyDyn.remove();

        if (!matched.length) {
          var el = document.createElement('div');
          el.className = 'empty empty-dyn';
          el.innerHTML = '<b>No employees match</b>Try a different search or clear the filters.';
          list.appendChild(el);
        } else {
          matched.forEach(function (r) {
            r.style.display = '';
            list.appendChild(r);
          });
        }

        var f = isDirty();
        if (foot) {
          foot.textContent = f ? matched.length + ' results' : 'Showing ' + matched.length + ' of ' + (totals[tab] !== undefined ? totals[tab] : totals.all);
        }
        if (clrBtn) clrBtn.hidden = !f;

        tabBtns.forEach(function (b) {
          var isSel = b.getAttribute('data-t') === tab;
          b.setAttribute('aria-selected', isSel ? 'true' : 'false');
        });

        document.querySelectorAll('#employeesMain .sort').forEach(function (b) {
          var on = b.dataset.k === sortK;
          b.toggleAttribute('data-on', on);
          b.dataset.dir = on ? sortDir : 'asc';
        });
      }

      tabBtns.forEach(function (b) {
        b.addEventListener('click', function () {
          tab = b.getAttribute('data-t') || 'all';
          render();
        });
      });

      if (qInput) qInput.addEventListener('input', function () { render(); });
      if (deptSelect) deptSelect.addEventListener('change', function () { render(); });
      if (perfSelect) perfSelect.addEventListener('change', function () { render(); });

      document.querySelector('#employeesMain .head').addEventListener('click', function (e) {
        var b = e.target.closest('.sort');
        if (!b) return;
        if (sortK === b.dataset.k) {
          sortDir = sortDir === 'asc' ? 'desc' : 'asc';
        } else {
          sortK = b.dataset.k;
          sortDir = sortK === 'score' ? 'desc' : 'asc';
        }
        render();
      });

      if (clrBtn) {
        clrBtn.addEventListener('click', function () {
          if (qInput) qInput.value = '';
          if (deptSelect) deptSelect.value = 'all';
          if (perfSelect) perfSelect.value = 'all';
          tab = 'all';
          sortK = null;
          render();
        });
      }

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
