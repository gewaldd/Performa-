<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/../audit_log.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['uid'])) {
    header('Location: ../login.php');
    exit;
}

$profileName = $_SESSION['name'] ?? 'Unknown User';
$profileRole = $_SESSION['role'] ?? 'Employer';
$profileRoleDisplay = ucwords(str_replace('_', ' ', (string) $profileRole));

// Icons come from the shared library (includes/icons.php via employer_layout.php).
// $icons stays as a thin alias so existing markup below keeps working.
$icons = [
    'home' => employer_icon('home'),
    'users' => employer_icon('users'),
    'target' => employer_icon('target'),
    'bar-chart' => employer_icon('bar-chart'),
    'settings' => employer_icon('settings'),
    'search' => employer_icon('search'),
    'bell' => employer_icon('bell'),
    'plus' => employer_icon('plus'),
    'hourglass' => employer_icon('hourglass'),
    'trend' => employer_icon('trend'),
    'download' => employer_icon('download'),
    'cap' => employer_icon('cap'),
    'more' => employer_icon('more'),
];

/*
 * Manuscript note: the dashboard never writes training assignments directly.
 * Assignments are authored only by the review approve handler
 * (review_recommendations.php), which runs the human-in-the-loop step —
 * employer review before anything reaches the employee (P.121) — and records
 * the audit trail. Cards below route into that flow instead. The one exception
 * is reversal: unassigning clears the employer's own assignment record.
 */

$dashMessage = '';
$dashMessageType = 'info';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'unassign_training' &&
    !empty($_POST['uid'])
) {
    try {
        $unassignUid = trim((string) $_POST['uid']);

        $unassignTarget =
            firestore_get_document('Users', $unassignUid) ?? [];

        require_employer_owns_user(
            $unassignTarget + ['uid' => $unassignUid],
            'dashboard:unassign_training'
        );

        unset(
            $unassignTarget['assignedTraining'],
            $unassignTarget['assignedTrainingAt']
        );

        firestore_write_document('Users', $unassignUid, $unassignTarget);

        unset($_SESSION['dashboard_live_data'], $_SESSION['dashboard_cache_time']);

        record_audit_event(
            'training_unassigned',
            'Removed training assignment for ' . $unassignUid,
            ['employee' => $unassignUid]
        );

        header(
            'Location: employer_dashboard.php?unassigned=' .
            urlencode($unassignUid)
        );

        exit;
    } catch (Throwable $e) {
        $dashMessage = 'Could not remove the assignment. Please try again.';
        $dashMessageType = 'error';
    }
}

if (isset($_GET['unassigned'])) {
    $dashMessage = 'Training assignment removed.';
    $dashMessageType = 'success';
}

/* assign_course removed: direct assignment bypassed the manuscript's
 * review-before-send step (P.121). See review_recommendations.php approve. */

/*
|--------------------------------------------------------------------------
| Dashboard cache
|--------------------------------------------------------------------------
*/

$cacheKey = 'dashboard_live_data';
$cacheTimeKey = 'dashboard_cache_time';
$cacheTTL = 60;

$liveUsers = [];
$allRatings = [];

$cacheValid =
    isset($_SESSION[$cacheKey]) &&
    isset($_SESSION[$cacheTimeKey]) &&
    (time() - (int) $_SESSION[$cacheTimeKey]) < $cacheTTL;

if ($cacheValid) {
    $liveUsers =
        $_SESSION[$cacheKey]['liveUsers']
        ?? [];

    $allRatings =
        $_SESSION[$cacheKey]['allRatings']
        ?? [];
} else {
    try {
        $allRatings =
            firestore_list_documents('Ratings');
    } catch (Throwable $e) {
        $allRatings = [];
    }

    /*
     * Latest AI plan per employee (manuscript P.121 pipeline output stored
     * on Ratings docs by rate_employee.php). Compact excerpt only — the full
     * plan renders on the review page. Newest ratedAt wins; docs without an
     * aiRecommendations map never displace one that has it.
     */
    $latestAiByUid = [];

    foreach ($allRatings as $ratingDoc) {
        $ratingUid =
            (string) (
                $ratingDoc['employeeUid']
                ?? ''
            );

        $storedAi =
            $ratingDoc['aiRecommendations']
            ?? null;

        if (
            $ratingUid === '' ||
            !is_array($storedAi)
        ) {
            continue;
        }

        $ratedAt =
            (string) (
                $ratingDoc['ratedAt']
                ?? ''
            );

        $planRecs =
            isset($storedAi['training_recommendations']) &&
            is_array($storedAi['training_recommendations'])
            ? array_values(
                $storedAi['training_recommendations']
            )
            : [];

        /*
         * Preference rule: a doc WITH recommendations always beats one
         * without, regardless of recency — otherwise a newer empty fallback
         * (e.g. recorded during a Gemini outage) clobbers an older good AI
         * plan in the card. Between two docs with equal standing, newest wins.
         */
        $newHasRecs = count($planRecs) > 0;
        $oldEntry = $latestAiByUid[$ratingUid] ?? null;
        $oldHasRecs = is_array($oldEntry) && !empty($oldEntry['hasRecs']);

        if (
            $oldEntry !== null &&
            (
                ($oldHasRecs && !$newHasRecs) ||
                (
                    $oldHasRecs === $newHasRecs &&
                    strcmp((string) $oldEntry['ratedAt'], $ratedAt) >= 0
                )
            )
        ) {
            continue;
        }

        $topRec =
            $planRecs[0]
            ?? null;

        $latestAiByUid[$ratingUid] = [
            'ratedAt' => $ratedAt,
            'status' =>
                (string) (
                    $storedAi['status']
                    ?? ''
                ),
            'generated_by' =>
                (string) (
                    $storedAi['generated_by']
                    ?? 'fallback'
                ),
            'model_version' =>
                (string) (
                    $storedAi['model_version']
                    ?? 'unknown'
                ),
            'summary' =>
                (string) (
                    $storedAi['summary']
                    ?? ''
                ),
            'error' =>
                (string) (
                    $storedAi['error']
                    ?? ''
                ),
            'top' => is_array($topRec)
                ? [
                    'competency_area' =>
                        (string) (
                            $topRec['competency_area']
                            ?? ''
                        ),
                    'training_type' =>
                        (string) (
                            $topRec['training_type']
                            ?? ''
                        ),
                    'timeline' =>
                        (string) (
                            $topRec['timeline']
                            ?? ''
                        ),
                    'description' =>
                        (string) (
                            $topRec['description']
                            ?? ''
                        ),
                ]
                : null,
            'hasRecs' =>
                count($planRecs) > 0,
        ];
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
             * The Employer dashboard is specifically about
             * probationary staff. Do not count supervisors
             * or other employee types as probationary.
             */
            if ($roleKey !== 'probationary') {
                continue;
            }

            $createdAt =
                $doc['createdAt']
                ?? '';

            $createdTime =
                $createdAt
                ? strtotime($createdAt)
                : false;

            $daysSince =
                $createdTime
                ? max(
                    0,
                    (int) floor(
                        (time() - $createdTime) /
                        86400
                    )
                )
                : 0;

            $probationPeriodDays = max(1, (int) ($doc['probationPeriodDays'] ?? 180));

            $daysLeft =
                $createdTime
                ? max(
                    0,
                    $probationPeriodDays - $daysSince
                )
                : 0;

            $progress =
                $createdTime
                ? min(
                    100,
                    (int) round(
                        ($daysSince / $probationPeriodDays) *
                        100
                    )
                )
                : 0;

            $email =
                $doc['email']
                ?? '';

            $name =
                $doc['name']
                ?? $email
                ?? 'Unknown';

            $uid =
                $doc['uid']
                ?? '';

            $industry =
                $doc['industry']
                ?? 'retail';

            /*
             * Use the actual KPI summary.
             */
            $summary =
                employee_kpi_summary(
                    $allRatings,
                    $uid,
                    $industry
                );

            $hasScore =
                !empty($summary['hasData']);

            $score =
                $hasScore
                ? (float) $summary['score']
                : null;

            /*
             * Use the real target average whenever available.
             * Fall back only if the KPI summary does not provide one.
             */
            $targetAvg =
                isset($summary['targetAvg'])
                ? (float) $summary['targetAvg']
                : 4.2;

            $targetAvg =
                $targetAvg > 0
                ? $targetAvg
                : 4.2;

            if ($hasScore) {
                $stars =
                    max(
                        0,
                        min(
                            5,
                            (int) round($score)
                        )
                    );

                $meetsTarget =
                    $score >= $targetAvg;

                $status =
                    $meetsTarget
                    ? 'On Track'
                    : 'Needs Review';

                $statusClass =
                    $meetsTarget
                    ? 'status-good'
                    : 'status-warning';

                $statusKey =
                    $meetsTarget
                    ? 'on-track'
                    : 'needs-review';

                $accentColor =
                    $meetsTarget
                    ? 'var(--color-success)'
                    : 'var(--color-warning)';
            } else {
                $stars = 0;
                $status = 'No Ratings Yet';
                $statusClass = 'status-neutral';
                $statusKey = 'no-data';
                $accentColor = 'var(--color-neutral)';
            }

            /*
             * Nearing deadline takes priority over On Track.
             */
            if (
                $daysLeft > 0 &&
                $daysLeft <= 30 &&
                $statusKey === 'on-track'
            ) {
                $status = 'Needs Review';
                $statusClass = 'status-warning';
                $statusKey = 'needs-review';
                $accentColor = 'var(--color-warning)';
            }

            /*
             * Employees with sufficient probationary tenure
             * and a qualifying KPI score are ready for regularization.
             */
            if (
                $daysSince >= max(1, $probationPeriodDays - 30) &&
                $hasScore &&
                $score >= $targetAvg
            ) {
                $status = 'Ready for Reg.';
                $statusClass = 'status-ready';
                $statusKey = 'ready-for-reg';
                $accentColor = '#1f2940';
            }

            $liveUsers[] = [
                'uid' => $uid,

                'name' => $name,

                'role' =>
                    display_role_label(
                        $doc['role'] ?? null
                    ),

                'initials' => employer_avatar_initials($name),

                'day' =>
                    pf_day($daysSince),

                'daysLeft' =>
                    $daysLeft . ' days left',

                'daysLeftValue' =>
                    $daysLeft,

                'progress' => $progress,

                'score' => $score,

                'targetAvg' => $targetAvg,

                'hasScore' => $hasScore,

                'stars' => $stars,

                'status' => $status,

                'statusClass' =>
                    $statusClass,

                'statusKey' =>
                    $statusKey,

                'accentColor' =>
                    $accentColor,

                'email' => $email,

                'createdAt' =>
                    $createdAt,

                'assignedTraining' =>
                    $doc['assignedTraining']
                    ?? null,

                'assignedTrainingAt' =>
                    $doc['assignedTrainingAt']
                    ?? null,

                'aiPlan' =>
                    $latestAiByUid[$uid]
                    ?? null,
            ];
        }
    } catch (Throwable $e) {
        $liveUsers = [];
    }

    /*
     * Cache only the derived rows. The raw $allRatings array used to be
     * stored here too, which serialized the entire Ratings collection into
     * the PHP session file on every cache miss (slow session read/write +
     * longer session-lock hold on every subsequent request). Summaries are
     * recomputed from a fresh Ratings list on the next miss instead.
     */
    $_SESSION[$cacheKey] = [
        'liveUsers' =>
            $liveUsers,
    ];

    $_SESSION[$cacheTimeKey] =
        time();
}

/*
 * Urgency order: soonest regularization deadline first, so the most urgent
 * employee is always row one. Applied here — before metrics, palette index,
 * badges, and the insight queue derive — so every consumer shares the
 * order. Reorders loaded rows only: zero new reads.
 */
usort(
    $liveUsers,
    static function ($a, $b): int {
        $da = $a['daysLeftValue'] ?? null;
        $db = $b['daysLeftValue'] ?? null;

        if ($da === null && $db === null) {
            return 0;
        }
        if ($da === null) {
            return 1;
        }
        if ($db === null) {
            return -1;
        }

        return (int) $da <=> (int) $db;
    }
);

/*
 |--------------------------------------------------------------------------
 | Dashboard metrics
 |--------------------------------------------------------------------------
 */

$probationaryCount =
    count($liveUsers);

$nearDeadlineCount = 0;

$scoreTotal = 0.0;

$scoredCount = 0;

foreach ($liveUsers as $user) {
    if (
        isset($user['daysLeftValue']) &&
        $user['daysLeftValue'] <= 30 &&
        $user['daysLeftValue'] > 0
    ) {
        $nearDeadlineCount++;
    }

    if ($user['hasScore']) {
        $scoreTotal +=
            (float) $user['score'];

        $scoredCount++;
    }
}

$overallPerformance =
    $scoredCount > 0
    ? $scoreTotal / $scoredCount
    : null;

$metrics = [
    [
        'label' =>
            'Total Probationary',

        'value' =>
            (string) $probationaryCount,

        'badge' =>
            'Live',

        'tone' =>
            'positive',

        'iconClass' =>
            'icon-warm',

        'icon' =>
            'users',
    ],
    [
        'label' =>
            'Nearing Deadline',

        'value' =>
            (string) $nearDeadlineCount,

        'badge' =>
            '< 30 days',

        'tone' =>
            'warning',

        'iconClass' =>
            'icon-gold',

        'icon' =>
            'hourglass',
    ],
    [
        'label' =>
            'Overall Performance',

        'value' =>
            $overallPerformance !== null
            ? number_format(
                $overallPerformance,
                1
            )
            : '—',

        'suffix' =>
            $overallPerformance !== null
            ? '/ 5.0'
            : '',

        'badge' =>
            $overallPerformance !== null
            ? 'Average'
            : 'No Ratings Yet',

        'tone' =>
            'neutral',

        'iconClass' =>
            'icon-mint',

        'icon' =>
            'trend',
    ],
];

/*
|--------------------------------------------------------------------------
| Nearest regularization deadline
|--------------------------------------------------------------------------
*/

$nearestDeadlineDays = null;

foreach ($liveUsers as $user) {
    $days =
        (int) (
            $user['daysLeftValue']
            ?? 0
        );

    if (
        $days > 0 &&
        (
            $nearestDeadlineDays === null ||
            $days < $nearestDeadlineDays
        )
    ) {
        $nearestDeadlineDays = $days;
    }
}

/*
|--------------------------------------------------------------------------
| Insight / intervention recommendation
|--------------------------------------------------------------------------
*/

$evaluations =
    $liveUsers;

/*
 * Ranked attention queue: scored employees classified needs_improvement or
 * critical_gap (meets_expectations never enters), worst gap first, top 3.
 * Replaces the old single-employee insight so staff beyond the first are
 * no longer invisible.
 */
$insightQueue = [];

foreach ($liveUsers as $user) {
    if (!$user['hasScore']) {
        continue;
    }

    $target =
        (float) (
            $user['targetAvg']
            ?? 4.2
        );

    $score =
        (float) $user['score'];

    /*
     * Manuscript threshold semantics (same buffer rule as
     * kpi_status_for_score): meets_expectations never enters the queue.
     * Only needs_improvement and critical_gap do.
     */
    if ($score >= $target) {
        continue;
    }

    $gap =
        $target - $score;

    $insightQueue[] = [
        'user' => $user,
        'gap' => $gap,
        'tone' =>
            $score < $target - 0.8
            ? 'critical'
            : 'needs',
    ];
}

usort(
    $insightQueue,
    static fn($a, $b) =>
        $b['gap'] <=> $a['gap']
);

$insightQueue =
    array_slice(
        $insightQueue,
        0,
        3
    );

$anyScored = false;

foreach ($liveUsers as $scoredUser) {
    if (!empty($scoredUser['hasScore'])) {
        $anyScored = true;

        break;
    }
}

/*
 * Shell badges + command palette index. Counts come from the rows already
 * loaded above (zero new reads); the palette reuses the same rows.
 */
$_SESSION['pf_nav_employees'] =
    $probationaryCount;

$_SESSION['pf_nav_deadline'] =
    $nearDeadlineCount;

$pfPaletteIndex = [];

foreach (
    array_slice(
        $liveUsers,
        0,
        60
    ) as $paletteUser
) {
    $paletteUid =
        (string) (
            $paletteUser['uid']
            ?? ''
        );

    if ($paletteUid === '') {
        continue;
    }

    $pfPaletteIndex[] = [
        'label' =>
            $paletteUser['name'],
        'sub' =>
            $paletteUser['status'] .
            ' · View profile',
        'href' =>
            'employee_view.php?uid=' .
            urlencode($paletteUid),
    ];

    $pfPaletteIndex[] = [
        'label' =>
            'Rate ' .
            $paletteUser['name'],
        'sub' =>
            'Weekly KPI rating',
        'href' =>
            'rate_employee.php?employee=' .
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

$insightTitle =
    $insightQueue
    ? 'Intervention Suggested'
    : 'No Data Yet';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />

    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <?php employer_brand_head(); ?>

    <title>Dashboard · Performa</title>

    <meta name="description"
        content="Employer KPI dashboard for probationary employee evaluation and training recommendations." />

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

        <?php employer_render_shell('Dashboard'); ?>

        <main class="main" id="dashboard">

            <?php ob_start(); ?>
                    <label class="search-bar">
                        <span class="sr-only">
                            Search employees or reports
                        </span>

                        <span class="search-icon" aria-hidden="true">
                            <?php echo $icons['search']; ?>
                        </span>

                        <input id="dashboardSearch" type="search" placeholder="Search employees, reports..."
                            autocomplete="off" />
                    </label>

                    <?php if ($nearestDeadlineDays !== null): ?>

                        <div class="deadline-pill" title="<?php echo (int) $nearestDeadlineDays; ?> day<?php echo $nearestDeadlineDays === 1 ? '' : 's'; ?> until nearest regularization deadline">

                            <span class="deadline-icon" aria-hidden="true">
                                <?php echo $icons['bell']; ?>
                            </span>

                            <?php echo (int) $nearestDeadlineDays; ?>

                            day<?php echo $nearestDeadlineDays === 1 ? '' : 's'; ?>

                            until nearest regularization deadline

                        </div>

                    <?php endif; ?>

                    <a class="btn-primary" href="add_employee.php">
                        <?php echo $icons['plus']; ?>
                        Add Employee
                    </a>
            <?php
            $dashboardActions = ob_get_clean();
            employer_page_header(
                'dashboardTitle',
                'Probationary Overview',
                '<span class="eyebrow">Regularization cycle</span>',
                'Track and evaluate employees approaching regularization.',
                $dashboardActions
            );
            ?>

            <?php if ($dashMessage !== ''): ?>
                <div class="alert alert-<?php echo $dashMessageType === 'error' ? 'error' : 'success'; ?>" role="status">
                    <?php echo htmlspecialchars($dashMessage, ENT_QUOTES); ?>
                </div>
            <?php endif; ?>

            <section class="metrics" id="kpis" aria-label="Key dashboard metrics">

                <?php foreach ($metrics as $metric): ?>

                    <article class="metric-card">

                        <div class="metric-card-top">

                            <div class="metric-icon <?php echo htmlspecialchars($metric['iconClass'], ENT_QUOTES); ?>">
                                <?php
                                echo $icons[
                                    $metric['icon']
                                ];
                                ?>
                            </div>

                            <div class="metric-badge <?php echo htmlspecialchars($metric['tone'], ENT_QUOTES); ?>">
                                <?php
                                echo htmlspecialchars(
                                    $metric['badge'],
                                    ENT_QUOTES
                                );
                                ?>
                        </div>

                        <div class="dashboard-empty" id="noFilterMatches" hidden>
                            No employees match this filter combination.
                            <button class="ghost-button" type="button" id="clearDashboardFilters">
                                Clear filters
                            </button>
                        </div>

                    </div>

                        <div class="metric-meta">

                            <span>
                                <?php
                                echo htmlspecialchars(
                                    $metric['label'],
                                    ENT_QUOTES
                                );
                                ?>
                            </span>

                            <strong>

                                <?php
                                echo htmlspecialchars(
                                    $metric['value'],
                                    ENT_QUOTES
                                );
                                ?>

                                <?php if (!empty($metric['suffix'])): ?>

                                    <small>
                                        <?php
                                        echo htmlspecialchars(
                                            $metric['suffix'],
                                            ENT_QUOTES
                                        );
                                        ?>
                                    </small>

                                <?php endif; ?>

                            </strong>

                        </div>

                    </article>

                <?php endforeach; ?>

            </section>

            <section class="content-grid">

                <div class="panel evaluations" id="employees">

                    <div class="panel-header">

                        <div>

                            <span class="eyebrow">Probation ledger · <?php echo (int) $probationaryCount; ?> active</span>

                            <h2>
                                Active Evaluations
                            </h2>

                        </div>

                        <div class="panel-actions">

                            <button class="ghost-button" type="button" id="exportEvaluationsBtn">
                                <?php echo $icons['download']; ?>
                                Export CSV
                            </button>

                        </div>

                    </div>

                    <div class="table-toolbar">

                        <div class="chip-group" role="group" aria-label="Evaluation filters">

                            <button class="filter-chip active" type="button" data-filter="all">
                                All
                            </button>

                            <button class="filter-chip" type="button" data-filter="needs-review">
                                Needs Review
                            </button>

                            <button class="filter-chip" type="button" data-filter="on-track">
                                On Track
                            </button>

                            <button class="filter-chip" type="button" data-filter="ready-for-reg">
                                Ready
                            </button>

                        </div>

                    </div>

                    <div class="table-wrap" role="table" aria-label="Active probationary evaluations">

                        <div class="table-head" role="row">
                            <span role="columnheader">
                                EMPLOYEE
                            </span>

                            <span role="columnheader">
                                TIMELINE PROGRESS
                            </span>

                            <span role="columnheader">
                                KPI SCORE
                            </span>

                            <span role="columnheader">
                                STATUS
                            </span>
                        </div>

                        <div id="evaluationRows">

                            <?php if (empty($evaluations)): ?>

                                <div class="dashboard-empty">
                                    No probationary employees are currently available.
                                    <a class="btn-primary" href="add_employee.php">Add Employee</a>
                                </div>

                            <?php else: ?>

                                <?php foreach ($evaluations as $employee): ?>

                                    <div class="table-row" role="row" data-search="<?php
                                    echo htmlspecialchars(
                                        strtolower(
                                            $employee['name'] .
                                            ' ' .
                                            $employee['role'] .
                                            ' ' .
                                            $employee['status']
                                        ),
                                        ENT_QUOTES
                                    );
                                    ?>" data-filter="<?php
                                    echo htmlspecialchars(
                                        $employee['statusKey'],
                                        ENT_QUOTES
                                    );
                                    ?>">

                                        <div class="employee-cell" role="cell">

                                            <div class="avatar avatar-local"
                                                title="<?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?>"
                                                aria-hidden="true"><?php echo htmlspecialchars($employee['initials'], ENT_QUOTES); ?></div>

                                            <div>

                                                <div class="employee-name">
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $employee['name'],
                                                        ENT_QUOTES
                                                    );
                                                    ?>
                                                </div>

                                                <div class="employee-role">
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $employee['role'],
                                                        ENT_QUOTES
                                                    );
                                                    ?>
                                                </div>

                                            </div>

                                        </div>

                                        <div class="timeline-cell" role="cell" data-label="Timeline">

                                            <div class="timeline-text">

                                                <span class="timeline-day">
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $employee['day'],
                                                        ENT_QUOTES
                                                    );
                                                    ?>
                                                </span>

                                                <span class="timeline-left"
                                                    style="color:<?php echo htmlspecialchars($employee['accentColor'], ENT_QUOTES); ?>;">
                                                    <?php
                                                    echo htmlspecialchars(
                                                        $employee['daysLeft'],
                                                        ENT_QUOTES
                                                    );
                                                    ?>
                                                </span>

                                            </div>

                                            <div class="timeline-bar">

                                                <span style="
                                                    width:<?php echo (int) $employee['progress']; ?>%;
                                                    background:<?php echo htmlspecialchars($employee['accentColor'], ENT_QUOTES); ?>;
                                                "></span>

                                            </div>

                                        </div>

                                        <div class="score-cell" role="cell" data-label="KPI Score">
                                            <?php
                                            echo employer_score_meter(
                                                $employee['hasScore'] ? (float) $employee['score'] : null,
                                                5.0,
                                                isset($employee['targetAvg']) ? (float) $employee['targetAvg'] : null
                                            );
                                            ?>
                                        </div>

                                        <div class="status-cell" role="cell" data-label="Status">

                                            <span
                                                class="status-pill <?php echo htmlspecialchars($employee['statusClass'], ENT_QUOTES); ?>">
                                                <?php
                                                echo htmlspecialchars(
                                                    $employee['status'],
                                                    ENT_QUOTES
                                                );
                                                ?>
                                            </span>

                                            <a href="rate_employee.php?employee=<?php echo urlencode($employee['uid']); ?>"
                                                class="eval-btn" title="Evaluate Employee"
                                                aria-label="Evaluate <?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?>">
                                                <?php echo $icons['target']; ?>
                                            </a>

                                        </div>

                                    </div>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </div>

                    </div>

                    <a class="view-more" href="employees.php">
                        View All Probationary Staff →
                    </a>

                </div>

                <aside class="insight-card pf-panel" id="insight">

                    <div class="insight-top">

                        <span class="insight-icon" aria-hidden="true"><?php echo $icons['cap']; ?></span>

                        <span class="insight-label">
                            Review queue
                        </span>

                    </div>

                    <h2>
                        <?php
                        echo htmlspecialchars(
                            $insightTitle,
                            ENT_QUOTES
                        );
                        ?>
                    </h2>

                    <?php if ($insightQueue): ?>

                        <ol class="insight-queue">
                            <?php foreach ($insightQueue as $queuePos => $queueEntry): ?>
                                <?php
                                $queueUser = $queueEntry['user'];
                                $queueTone = $queueEntry['tone'] ?? 'needs';
                                $queueAi = $queueUser['aiPlan'] ?? null;
                                $queueTop = is_array($queueAi) && is_array($queueAi['top'] ?? null)
                                    ? $queueAi['top']
                                    : null;
                                $queueAssigned = !empty($queueUser['assignedTraining']);
                                $queuePending = !$queueAssigned
                                    && is_array($queueAi)
                                    && ($queueAi['status'] ?? '') === 'pending_approval'
                                    && $queueAi['hasRecs'];
                                $queueApproved = !$queueAssigned
                                    && is_array($queueAi)
                                    && ($queueAi['status'] ?? '') === 'approved';
                                ?>
                                <li class="insight-queue-item tone-<?php echo htmlspecialchars($queueTone, ENT_QUOTES); ?>">
                                    <div class="insight-queue-head">
                                        <strong>
                                            <?php
                                            echo htmlspecialchars(
                                                $queueUser['name'],
                                                ENT_QUOTES
                                            );
                                            ?>
                                        </strong>
                                        <span class="insight-queue-gap">
                                            <?php
                                            echo number_format(
                                                (float) $queueUser['score'],
                                                1
                                            );
                                            ?>
                                            /
                                            <?php
                                            echo number_format(
                                                (float) ($queueUser['targetAvg'] ?? 4.2),
                                                1
                                            );
                                            ?>
                                            target
                                        </span>
                                    </div>

                                    <div class="recommendation-box">

                                        <span class="recommendation-icon" aria-hidden="true">
                                            <?php echo $icons['cap']; ?>
                                        </span>

                                        <div>

                                            <?php if ($queueTop): ?>
                                                <div class="recommendation-label">
                                                    Recommended Action:
                                                </div>

                                                <strong>
                                                    <?php
                                                    echo htmlspecialchars(
                                                        ucwords(
                                                            str_replace(
                                                                '_',
                                                                ' ',
                                                                (string) ($queueTop['competency_area'] ?? '')
                                                            )
                                                        ) . ' — ' . (string) ($queueTop['training_type'] ?? ''),
                                                        ENT_QUOTES
                                                    );
                                                    ?>
                                                </strong>

                                                <?php if (!empty($queueTop['description'])): ?>
                                                    <div class="microcopy">
                                                        <?php
                                                        echo htmlspecialchars(
                                                            $queueTop['description'],
                                                            ENT_QUOTES
                                                        );
                                                        ?>
                                                    </div>
                                                <?php endif; ?>

                                                <div class="microcopy">
                                                    <?php if (!empty($queueTop['timeline'])): ?>
                                                        Timeline: <?php echo htmlspecialchars($queueTop['timeline'], ENT_QUOTES); ?> ·
                                                    <?php endif; ?>
                                                    Source: <?php echo htmlspecialchars(($queueAi['generated_by'] ?? '') === 'gemini_api' ? 'RF + Gemini' : 'system', ENT_QUOTES); ?>
                                                    <?php echo htmlspecialchars($queueAi['model_version'] ?? '', ENT_QUOTES); ?>
                                                </div>
                                            <?php else: ?>
                                                <?php
                                                $queueErr = is_array($queueAi)
                                                    ? (string) ($queueAi['error'] ?? '')
                                                    : '';
                                                $queueNoPlanReason = !is_array($queueAi)
                                                    ? 'No AI plan on file yet — an employer rating generates one.'
                                                    : (
                                                        stripos($queueErr, '429') !== false ||
                                                        stripos($queueErr, 'RESOURCE_EXHAUSTED') !== false ||
                                                        stripos($queueErr, 'quota') !== false
                                                        ? 'The AI service hit its usage quota for that cycle — re-submitting a rating regenerates the plan.'
                                                        : 'The AI service was unavailable for that cycle — re-submitting a rating regenerates the plan.'
                                                    );
                                                ?>
                                                <div class="recommendation-label">
                                                    No AI plan yet
                                                </div>

                                                <div class="microcopy">
                                                    <?php echo htmlspecialchars($queueNoPlanReason, ENT_QUOTES); ?>
                                                </div>
                                            <?php endif; ?>

                                        </div>

                                    </div>

                                    <div class="insight-actions">

                                        <?php if ($queueAssigned): ?>

                                            <button class="btn-primary" type="button" disabled>
                                                Assigned
                                            </button>

                                            <form method="post" style="margin-top:8px;"
                                                data-confirm="Remove this training assignment? The employee keeps their ratings and AI plan.">
                                                <?php echo csrf_field(); ?>

                                                <input type="hidden" name="action" value="unassign_training" />

                                                <input type="hidden" name="uid"
                                                    value="<?php echo htmlspecialchars((string) ($queueUser['uid'] ?? ''), ENT_QUOTES); ?>" />

                                                <button class="ghost-button" type="submit" style="width:100%;">
                                                    Unassign
                                                </button>

                                            </form>

                                        <?php elseif ($queuePending): ?>

                                            <a class="btn-primary" style="width:100%; text-align:center;"
                                                href="review_recommendations.php">
                                                Review plan
                                            </a>

                                        <?php elseif ($queueApproved): ?>

                                            <button class="btn-primary" type="button" disabled>
                                                Published
                                            </button>

                                        <?php else: ?>

                                            <a class="ghost-button" style="width:100%; text-align:center;"
                                                href="rate_employee.php?employee=<?php echo urlencode((string) ($queueUser['uid'] ?? '')); ?>">
                                                Rate to refresh
                                            </a>

                                        <?php endif; ?>

                                    </div>
                                </li>
                            <?php endforeach; ?>
                        </ol>

                    <?php else: ?>

                        <p id="insightText">
                            <?php if ($anyScored): ?>
                                No interventions needed — every rated employee meets their target.
                            <?php else: ?>
                                No employee has been rated yet. Insights will appear here once KPI ratings exist.
                            <?php endif; ?>
                        </p>

                    <?php endif; ?>

                </aside>

            </section>

        </main>

    </div>

    <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

    <script>
        window.__pfIndex = <?php echo $pfPaletteJson !== false ? $pfPaletteJson : '[]'; ?>;
    </script>

</body>

</html>