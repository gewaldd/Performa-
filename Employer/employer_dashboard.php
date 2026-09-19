<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';

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
    return ucwords(
        str_replace(
            '_',
            ' ',
            (string) $role
        )
    );
}

/*
|--------------------------------------------------------------------------
| Handle POST actions before loading dashboard data
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'assign_course' &&
    !empty($_POST['uid'])
) {
    try {
        $uid = trim((string) $_POST['uid']);

        $course = trim(
            (string) (
                $_POST['course']
                ?? 'Performance Improvement Training'
            )
        );

        if ($course === '') {
            $course = 'Performance Improvement Training';
        }

        $existing =
            firestore_get_document(
                'Users',
                $uid
            ) ?? [];

        require_employer_owns_user(
            $existing + ['uid' => $uid],
            'dashboard:assign_course'
        );

        $existing['assignedTraining'] = $course;
        $existing['assignedTrainingAt'] = date('c');

        firestore_write_document(
            'Users',
            $uid,
            $existing
        );

        /*
         * Invalidate dashboard cache immediately.
         */
        unset(
            $_SESSION['dashboard_live_data'],
            $_SESSION['dashboard_cache_time']
        );

        header(
            'Location: employer_dashboard.php?assigned=1'
        );

        exit;
    } catch (Throwable $e) {
        /*
         * Keep rendering the page if the assignment fails.
         * The dashboard remains usable.
         */
    }
}

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
                    'Day ' . $daysSince,

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

$insightEmployee = null;

$worstGap = 0;

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

    $gap =
        $target - $score;

    if (
        $gap > 0 &&
        (
            $insightEmployee === null ||
            $gap > $worstGap
        )
    ) {
        $worstGap =
            $gap;

        $insightEmployee =
            $user;
    }
}

$justAssigned =
    isset($_GET['assigned']);

$insightTitle =
    $insightEmployee
    ? 'Intervention Suggested'
    : 'No Data Yet';

$insightName =
    $insightEmployee
    ? $insightEmployee['name']
    : null;

$recommendation =
    $insightEmployee
    ? 'Performance Improvement Training'
    : null;

$insightTarget =
    $insightEmployee
    ? (float) (
        $insightEmployee['targetAvg']
        ?? 4.2
    )
    : null;

$insightScore =
    $insightEmployee
    ? (float) (
        $insightEmployee['score']
        ?? 0
    )
    : null;
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

            <section class="page-header" aria-labelledby="dashboardTitle">
                <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
                </button>
                <div class="ph-main">
                    <span class="eyebrow">Regularization cycle</span>
                    <h1 id="dashboardTitle">Probationary Overview</h1>
                    <p>Track and evaluate employees approaching regularization.</p>
                </div>
                <div class="ph-actions">
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
                </div>
            </section>

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

                <aside class="insight-card" id="insight">

                    <div class="insight-top">

                        <span class="insight-icon" aria-hidden="true"><?php echo $icons['cap']; ?></span>

                        <span class="insight-label">
                            Supervisor note
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

                    <?php if ($insightEmployee): ?>

                        <p id="insightText">

                            Based on their latest KPI ratings,
                            <strong>
                                <?php
                                echo htmlspecialchars(
                                    $insightName,
                                    ENT_QUOTES
                                );
                                ?>
                            </strong>
                            is currently below the employee KPI target average and may benefit from targeted upskilling.

                        </p>

                        <div class="insight-score-row">

                            Current:

                            <strong>
                                <?php
                                echo number_format(
                                    $insightScore,
                                    1
                                );
                                ?>
                            </strong>

                            <span> / </span>

                            Target:

                            <strong>
                                <?php
                                echo number_format(
                                    $insightTarget,
                                    1
                                );
                                ?>
                            </strong>

                        </div>

                        <div class="recommendation-box">

                            <span class="recommendation-icon" aria-hidden="true">
                                <?php echo $icons['cap']; ?>
                            </span>

                            <div>

                                <div class="recommendation-label">
                                    Recommended Action:
                                </div>

                                <strong id="recommendationTitle">
                                    <?php
                                    echo htmlspecialchars(
                                        $recommendation,
                                        ENT_QUOTES
                                    );
                                    ?>
                                </strong>

                            </div>

                        </div>

                        <div class="insight-actions">

                            <?php
                            $alreadyAssigned =
                                !empty(
                                $insightEmployee['assignedTraining']
                            ) ||
                                $justAssigned;
                            ?>

                            <?php if ($alreadyAssigned): ?>

                                <button class="primary-button" type="button" disabled>
                                    Assigned
                                </button>

                            <?php else: ?>

                                <form method="post" style="flex:1;">
                                    <?php echo csrf_field(); ?>

                                    <input type="hidden" name="action" value="assign_course" />

                                    <input type="hidden" name="uid"
                                        value="<?php echo htmlspecialchars($insightEmployee['uid'], ENT_QUOTES); ?>" />

                                    <input type="hidden" name="course"
                                        value="<?php echo htmlspecialchars($recommendation, ENT_QUOTES); ?>" />

                                    <button class="primary-button" type="submit" style="width:100%;">
                                        Assign Course
                                    </button>

                                </form>

                            <?php endif; ?>

                        </div>

                    <?php else: ?>

                        <p id="insightText">
                            No employee has been rated yet. Insights will appear here once KPI ratings exist.
                        </p>

                    <?php endif; ?>

                </aside>

            </section>

        </main>

    </div>

    <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

</body>

</html>