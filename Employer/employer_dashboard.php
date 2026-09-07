<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
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

$icons = [
    'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',

    'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',

    'target' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="16" height="16"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',

    'bar-chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',

    'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82V9a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',

    'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',

    'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',

    'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>',

    'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',

    'hourglass' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M5 22v-4a7 7 0 0 1 5-6.7A7 7 0 0 1 5 4.7V2M19 22v-4a7 7 0 0 0-5-6.7A7 7 0 0 0 19 4.7V2"/></svg>',

    'trend' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',

    'download' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',

    'cap' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.66 3 3 6 3s6-1.34 6-3v-5"/></svg>',

    'more' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>',
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

            $daysLeft =
                $createdTime
                    ? max(
                        0,
                        180 - $daysSince
                    )
                    : 0;

            $progress =
                $createdTime
                    ? min(
                        100,
                        (int) round(
                            ($daysSince / 180) *
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

            $avatarSeed =
                urlencode(
                    strtolower(
                        $email !== ''
                            ? $email
                            : $name
                    )
                );

            $avatar =
                'https://ui-avatars.com/api/?name=' .
                $avatarSeed .
                '&background=2f6df6&color=fff&size=160';

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
                        ? 'var(--color-info)'
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
                $daysSince >= 150 &&
                $hasScore &&
                $score >= $targetAvg
            ) {
                $status = 'Ready for Reg.';
                $statusClass = 'status-ready';
                $statusKey = 'ready-for-reg';
                $accentColor = 'var(--color-info)';
            }

            $liveUsers[] = [
                'uid' => $uid,

                'name' => $name,

                'role' =>
                    display_role_label(
                        $doc['role'] ?? null
                    ),

                'avatar' => $avatar,

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

    $_SESSION[$cacheKey] = [
        'liveUsers' =>
            $liveUsers,

        'allRatings' =>
            $allRatings,
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

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    />

    <title>Performa | Employer Dashboard</title>

    <meta
        name="description"
        content="Employer KPI dashboard for probationary employee evaluation and training recommendations."
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

    <style>
        .status-cell {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .eval-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;

            width: 40px;
            height: 40px;

            flex: 0 0 40px;

            border-radius: 50%;

            background: var(--color-info-bg);
            color: var(--primary);

            text-decoration: none;

            transition:
                background-color 160ms ease,
                color 160ms ease,
                transform 120ms ease,
                box-shadow 160ms ease;
        }

        .eval-btn:hover {
            background: var(--primary);
            color: #ffffff;

            transform: translateY(-1px);

            box-shadow:
                0 6px 14px
                var(--color-info-focus-ring);
        }

        .eval-btn:active {
            transform: translateY(0);
        }

        .eval-btn svg {
            width: 15px;
            height: 15px;
        }

        .insight-score-row {
            display: flex;
            align-items: center;
            gap: 8px;

            margin-top: -4px;

            color: rgba(255, 255, 255, 0.68);

            font-size: 12px;
        }

        .insight-score-row strong {
            color: #ffffff;
        }

        .sr-only {
            position: absolute !important;
            width: 1px !important;
            height: 1px !important;
            padding: 0 !important;
            margin: -1px !important;
            overflow: hidden !important;
            clip: rect(0, 0, 0, 0) !important;
            white-space: nowrap !important;
            border: 0 !important;
        }

        .dashboard-empty {
            padding: 42px 20px;
            text-align: center;
            color: var(--muted);
            font-size: 14px;
        }

        @media (max-width: 760px) {
            .status-cell {
                align-items: center;
            }

            .eval-btn {
                width: 40px;
                height: 40px;
                flex-basis: 40px;
            }
        }
    </style>
</head>

<body>

<div class="app-shell">

    <?php employer_render_shell('Dashboard'); ?>

    <main
        class="main"
        id="dashboard"
    >

        <header class="topbar">

            <label class="search-bar">
                <span class="sr-only">
                    Search employees or reports
                </span>

                <span
                    class="search-icon"
                    aria-hidden="true"
                >
                    <?php echo $icons['search']; ?>
                </span>

                <input
                    id="dashboardSearch"
                    type="search"
                    placeholder="Search employees, reports..."
                    autocomplete="off"
                />
            </label>

            <div class="topbar-actions">

                <?php if ($nearestDeadlineDays !== null): ?>

                    <div class="deadline-pill">

                        <span
                            class="deadline-icon"
                            aria-hidden="true"
                        >
                            <?php echo $icons['bell']; ?>
                        </span>

                        <?php echo (int) $nearestDeadlineDays; ?>

                        day<?php echo $nearestDeadlineDays === 1 ? '' : 's'; ?>

                        until nearest regularization deadline

                    </div>

                <?php endif; ?>

                <button
                    class="icon-button"
                    type="button"
                    aria-label="Messages"
                >
                    <?php echo $icons['mail']; ?>
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

        <section class="hero">

            <div class="hero-top">

                <div>

                    <h1>
                        Probationary Overview
                    </h1>

                    <p>
                        Track and evaluate employees approaching regularization.
                    </p>

                </div>

                <a
                    class="btn-primary"
                    href="add_employee.php"
                >
                    <?php echo $icons['plus']; ?>
                    Add Employee
                </a>

            </div>

        </section>

        <section
            class="metrics"
            id="kpis"
            aria-label="Key dashboard metrics"
        >

            <?php foreach ($metrics as $metric): ?>

                <article class="metric-card">

                    <div class="metric-card-top">

                        <div
                            class="metric-icon <?php echo htmlspecialchars($metric['iconClass'], ENT_QUOTES); ?>"
                        >
                            <?php
                            echo $icons[
                                $metric['icon']
                            ];
                            ?>
                        </div>

                        <div
                            class="metric-badge <?php echo htmlspecialchars($metric['tone'], ENT_QUOTES); ?>"
                        >
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

            <div
                class="panel evaluations"
                id="employees"
            >

                <div class="panel-header">

                    <div>

                        <h2>
                            Active Evaluations
                        </h2>

                    </div>

                    <div class="panel-actions">

                        <button
                            class="ghost-button"
                            type="button"
                            id="exportEvaluationsBtn"
                        >
                            <?php echo $icons['download']; ?>
                            Export CSV
                        </button>

                    </div>

                </div>

                <div class="table-toolbar">

                    <div
                        class="chip-group"
                        role="group"
                        aria-label="Evaluation filters"
                    >

                        <button
                            class="filter-chip active"
                            type="button"
                            data-filter="all"
                        >
                            All
                        </button>

                        <button
                            class="filter-chip"
                            type="button"
                            data-filter="needs-review"
                        >
                            Needs Review
                        </button>

                        <button
                            class="filter-chip"
                            type="button"
                            data-filter="on-track"
                        >
                            On Track
                        </button>

                        <button
                            class="filter-chip"
                            type="button"
                            data-filter="ready-for-reg"
                        >
                            Ready
                        </button>

                    </div>

                </div>

                <div
                    class="table-wrap"
                    role="table"
                    aria-label="Active probationary evaluations"
                >

                    <div
                        class="table-head"
                        role="row"
                    >
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

                                <div
                                    class="table-row"
                                    role="row"
                                    data-search="<?php
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
                                    ?>"
                                    data-filter="<?php
                                    echo htmlspecialchars(
                                        $employee['statusKey'],
                                        ENT_QUOTES
                                    );
                                    ?>"
                                >

                                    <div
                                        class="employee-cell"
                                        role="cell"
                                    >

                                        <div
                                            class="avatar"
                                            style="background-image:url('<?php echo htmlspecialchars($employee['avatar'], ENT_QUOTES); ?>');"
                                            aria-hidden="true"
                                        ></div>

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

                                    <div
                                        class="timeline-cell"
                                        role="cell"
                                        data-label="Timeline"
                                    >

                                        <div class="timeline-text">

                                            <span class="timeline-day">
                                                <?php
                                                echo htmlspecialchars(
                                                    $employee['day'],
                                                    ENT_QUOTES
                                                );
                                                ?>
                                            </span>

                                            <span
                                                class="timeline-left"
                                                style="color:<?php echo htmlspecialchars($employee['accentColor'], ENT_QUOTES); ?>;"
                                            >
                                                <?php
                                                echo htmlspecialchars(
                                                    $employee['daysLeft'],
                                                    ENT_QUOTES
                                                );
                                                ?>
                                            </span>

                                        </div>

                                        <div class="timeline-bar">

                                            <span
                                                style="
                                                    width:<?php echo (int) $employee['progress']; ?>%;
                                                    background:<?php echo htmlspecialchars($employee['accentColor'], ENT_QUOTES); ?>;
                                                "
                                            ></span>

                                        </div>

                                    </div>

                                    <div
                                        class="score-cell"
                                        role="cell"
                                        data-label="KPI Score"
                                    >

                                        <?php if ($employee['hasScore']): ?>

                                            <strong class="score-value">
                                                <?php
                                                echo number_format(
                                                    (float) $employee['score'],
                                                    1
                                                );
                                                ?>
                                            </strong>

                                            <div
                                                class="stars"
                                                aria-hidden="true"
                                            >
                                                <?php
                                                echo str_repeat(
                                                    '★',
                                                    (int) $employee['stars']
                                                );

                                                echo str_repeat(
                                                    '☆',
                                                    5 - (int) $employee['stars']
                                                );
                                                ?>
                                            </div>

                                        <?php else: ?>

                                            <strong
                                                class="score-value"
                                                style="color:var(--muted);"
                                            >
                                                —
                                            </strong>

                                            <div
                                                class="stars"
                                                style="color:var(--muted);"
                                            >
                                                No Ratings Yet
                                            </div>

                                        <?php endif; ?>

                                    </div>

                                    <div
                                        class="status-cell"
                                        role="cell"
                                        data-label="Status"
                                    >

                                        <span
                                            class="status-pill <?php echo htmlspecialchars($employee['statusClass'], ENT_QUOTES); ?>"
                                        >
                                            <?php
                                            echo htmlspecialchars(
                                                $employee['status'],
                                                ENT_QUOTES
                                            );
                                            ?>
                                        </span>

                                        <a
                                            href="evaluate.php?uid=<?php echo urlencode($employee['uid']); ?>"
                                            class="eval-btn"
                                            title="Evaluate Employee"
                                            aria-label="Evaluate <?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?>"
                                        >
                                            <?php echo $icons['target']; ?>
                                        </a>

                                    </div>

                                </div>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </div>

                </div>

                <a
                    class="view-more"
                    href="employees.php"
                >
                    View All Probationary Staff →
                </a>

            </div>

            <aside
                class="insight-card"
                id="insight"
            >

                <div class="insight-top">

                    <span
                        class="insight-icon"
                        aria-hidden="true"
                    ></span>

                    <span class="insight-label">
                        INSIGHT
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

                        <span
                            class="recommendation-icon"
                            aria-hidden="true"
                        >
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

                            <button
                                class="primary-button"
                                type="button"
                                disabled
                            >
                                Assigned
                            </button>

                        <?php else: ?>

                            <form
                                method="post"
                                style="flex:1;"
                            >

                                <input
                                    type="hidden"
                                    name="action"
                                    value="assign_course"
                                />

                                <input
                                    type="hidden"
                                    name="uid"
                                    value="<?php echo htmlspecialchars($insightEmployee['uid'], ENT_QUOTES); ?>"
                                />

                                <input
                                    type="hidden"
                                    name="course"
                                    value="<?php echo htmlspecialchars($recommendation, ENT_QUOTES); ?>"
                                />

                                <button
                                    class="primary-button"
                                    type="submit"
                                    style="width:100%;"
                                >
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

<footer class="site-footer">

    <span>
        Performa employer dashboard
    </span>

    <span>
        Powered by PHP &amp; Firebase
    </span>

</footer>

<script src="script.js"></script>

</body>
</html>