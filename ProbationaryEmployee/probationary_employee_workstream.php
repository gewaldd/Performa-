<?php
require_once __DIR__ . '/data.php';

$user = probationary_user();
$timeline = probationary_timeline($user);
$workstreamDocuments = probationary_owned_documents('workstream');
$goalDocuments = probationary_owned_documents('goals');
$evaluationDocuments = array_merge(probationary_owned_documents('evaluations'), probationary_owned_documents('Ratings'));
$latestEvaluation = $evaluationDocuments[0] ?? [];
$latestScore = probationary_evaluation_score($latestEvaluation);
// FIX 3: these four labels point at the pages that actually render that
// content. They previously used in-page anchors (#goals / #feedback /
// #profile) whose targets were unrelated elements — #goals was the metrics
// strip, #profile the AI-insight aside — so every click silently did nothing.
$navItems = [
    ['label' => 'Overview', 'href' => 'probationary_employee_workstream.php', 'active' => true],
    ['label' => 'My Goals', 'href' => 'probationary_employee_goals.php', 'active' => false],
    ['label' => 'Feedback', 'href' => 'probationary_employee_feedback.php', 'active' => false],
    ['label' => 'Profile', 'href' => 'probationary_employee_profile.php', 'active' => false],
];

$metrics = [
    ['label' => 'Tasks Completed', 'value' => (string) count(array_filter(array_merge($workstreamDocuments, $goalDocuments), static fn(array $item): bool => strtolower((string) ($item['status'] ?? '')) === 'completed')), 'badge' => 'Current', 'tone' => 'positive', 'variant' => 'warm', 'icon' => '✓'],
    ['label' => 'KPI Score', 'value' => $latestScore === null ? '-' : number_format($latestScore, 1), 'suffix' => '/ 5.0', 'badge' => 'Current', 'tone' => 'neutral', 'variant' => 'mint', 'icon' => '▣'],
    ['label' => 'Pending Feedback', 'value' => (string) count(array_filter(probationary_owned_documents('Feedback'), static fn(array $item): bool => strtolower((string) ($item['status'] ?? '')) === 'pending')), 'badge' => 'Needs response', 'tone' => 'warning', 'variant' => 'gold', 'icon' => '✎'],
];

$items = array_map(static function (array $item): array {
    $status = ucwords(str_replace('-', ' ', (string) ($item['status'] ?? 'Assigned')));
    $statusKey = strtolower(str_replace(' ', '-', $status));
    return [
        'name' => $item['name'] ?? $item['title'] ?? 'Workstream item',
        'category' => $item['category'] ?? 'General',
        'timeline' => $item['timeline'] ?? ($item['dueDate'] ?? 'No due date'),
        'progress' => (int) ($item['progress'] ?? 0),
        'score' => (float) ($item['score'] ?? 0),
        'stars' => max(0, min(5, (int) round((float) ($item['score'] ?? 0)))),
        'status' => $status,
        'statusClass' => $statusKey === 'on-track' || $statusKey === 'completed' ? 'status-good' : ($statusKey === 'in-progress' ? 'status-warning' : 'status-ready'),
        'statusKey' => $statusKey,
        'progressColor' => $statusKey === 'in-progress' ? '#f0a11b' : '#2f6df6',
    ];
}, array_merge($workstreamDocuments, $goalDocuments));

$insightTitle = 'Performance Snapshot';
$insightText = $latestEvaluation['notes'] ?? ($items ? 'Your current workstream is shown below.' : 'No workstream items have been assigned yet.');
$recommendation = $user['workstreamRecommendation'] ?? 'Review your next assigned work item.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Probationary Employee Dashboard</title>
    <meta name="description"
        content="Probationary employee dashboard for tracking tasks, feedback, and performance goals." />
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="../ui-refresh.css" />
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div>
                <div class="brand">
                    <div class="brand-mark">P</div>
                    <div>
                        <div class="brand-name">Performa</div>
                        <div class="brand-subtitle">Probationary Employee</div>
                    </div>
                </div>

                <nav class="nav" aria-label="Primary">
                    <?php foreach ($navItems as $item): ?>
                        <a class="nav-item<?php echo $item['active'] ? ' active' : ''; ?>"
                            href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>">
                            <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
                        </a>
                    <?php endforeach; ?>
                </nav>
            </div>

            <div class="sidebar-footer">
                <div class="profile-avatar">JD</div>
                <div>
                    <div class="profile-name">
                        <?php echo htmlspecialchars($user['name'] ?? $user['email'] ?? '', ENT_QUOTES); ?>
                    </div>
                    <div class="profile-role">Probationary Employee</div>
                    <a class="logout-link" href="../logout.php" aria-label="Sign out">Sign out</a>
                </div>
            </div>
        </aside>

        <main class="main" id="dashboard">
            <header class="topbar">
                <label class="search-bar" aria-label="Search tasks or feedback">
                    <span class="search-icon">⌕</span>
                    <input id="dashboardSearch" type="search" placeholder="Search tasks, goals, feedback..." />
                </label>

                <div class="topbar-actions">
                    <div class="deadline-pill">
                        <?php echo $timeline['daysRemaining']; ?> days remaining
                    </div>
                    <button class="icon-button" type="button" aria-label="Notifications">Notifications</button>
                </div>
            </header>

            <section class="hero">
                <p class="eyebrow">Probationary Employee Overview</p>
                <h1>Track your tasks, feedback, and growth goals in one place.</h1>
            </section>

            <section class="metrics" id="goals" aria-label="Employee metrics">
                <?php foreach ($metrics as $metric): ?>
                    <article class="metric-card <?php echo htmlspecialchars($metric['variant'], ENT_QUOTES); ?>">
                        <div class="metric-icon"><?php echo htmlspecialchars($metric['icon'], ENT_QUOTES); ?></div>
                        <div class="metric-meta">
                            <span><?php echo htmlspecialchars($metric['label'], ENT_QUOTES); ?></span>
                            <strong><?php echo htmlspecialchars($metric['value'], ENT_QUOTES); ?><?php if (!empty($metric['suffix'])): ?><small>
                                        <?php echo htmlspecialchars($metric['suffix'], ENT_QUOTES); ?></small><?php endif; ?></strong>
                        </div>
                        <div class="metric-badge <?php echo htmlspecialchars($metric['tone'], ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($metric['badge'], ENT_QUOTES); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="content-grid">
                <div class="panel evaluations" id="requests">
                    <div class="panel-header">
                        <div>
                            <h2>My Workstream</h2>
                            <p>Keep up with assigned tasks, review notes, and development items.</p>
                        </div>
                        <div class="panel-actions">
                            <button class="ghost-button" type="button">Filter</button>
                            <button class="ghost-button" type="button">Export</button>
                        </div>
                    </div>

                    <div class="table-toolbar">
                        <div class="chip-group" role="tablist" aria-label="Workstream filters">
                            <button class="filter-chip active" type="button" data-filter="all">All</button>
                            <button class="filter-chip" type="button" data-filter="on-track">On Track</button>
                            <button class="filter-chip" type="button" data-filter="in-progress">In Progress</button>
                            <button class="filter-chip" type="button" data-filter="assigned">Assigned</button>
                        </div>
                        <p class="table-note">Search by task, category, or status.</p>
                    </div>

                    <div class="table-wrap" role="table" aria-label="Employee workstream">
                        <div class="table-head" role="row">
                            <span role="columnheader">Item</span>
                            <span role="columnheader">Timeline</span>
                            <span role="columnheader">Score</span>
                            <span role="columnheader">Status</span>
                        </div>

                        <div id="evaluationRows">
                            <?php foreach ($items as $item): ?>
                                <div class="table-row" role="row"
                                    data-search="<?php echo htmlspecialchars(strtolower($item['name'] . ' ' . $item['category'] . ' ' . $item['status']), ENT_QUOTES); ?>"
                                    data-filter="<?php echo htmlspecialchars($item['statusKey'], ENT_QUOTES); ?>">
                                    <div class="employee-cell" role="cell">
                                        <div class="avatar" style="background: linear-gradient(135deg, #6d8cff, #2f6df6);">
                                        </div>
                                        <div>
                                            <div class="employee-name">
                                                <?php echo htmlspecialchars($item['name'], ENT_QUOTES); ?>
                                            </div>
                                            <div class="employee-role">
                                                <?php echo htmlspecialchars($item['category'], ENT_QUOTES); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="timeline-cell" role="cell">
                                        <div class="timeline-text">
                                            <?php echo htmlspecialchars($item['timeline'], ENT_QUOTES); ?>
                                        </div>
                                        <div class="timeline-bar">
                                            <span
                                                style="width: <?php echo (int) $item['progress']; ?>%; background: <?php echo htmlspecialchars($item['progressColor'], ENT_QUOTES); ?>;"></span>
                                        </div>
                                    </div>

                                    <div class="score-cell" role="cell">
                                        <strong
                                            class="score-value"><?php echo number_format((float) $item['score'], 1); ?></strong>
                                        <div class="stars" aria-hidden="true">
                                            <?php echo str_repeat('★', (int) $item['stars']); ?>
                                            <?php echo str_repeat('☆', 5 - (int) $item['stars']); ?>
                                        </div>
                                    </div>

                                    <div class="status-cell" role="cell">
                                        <span
                                            class="status-pill <?php echo htmlspecialchars($item['statusClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($item['status'], ENT_QUOTES); ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <span id="feedback" class="section-anchor" aria-hidden="true"></span>
                    <a class="view-more" href="probationary_employee_feedback.php">View Feedback History →</a>
                </div>

                <aside class="insight-card" id="profile">
                    <div class="insight-badge">AI INSIGHT</div>
                    <h2><?php echo htmlspecialchars($insightTitle, ENT_QUOTES); ?></h2>
                    <p id="insightText"><?php echo htmlspecialchars($insightText, ENT_QUOTES); ?></p>

                    <div class="recommendation-box">
                        <div class="recommendation-label">Recommended Action</div>
                        <strong
                            id="recommendationTitle"><?php echo htmlspecialchars($recommendation, ENT_QUOTES); ?></strong>
                    </div>

                    <button class="primary-button" id="assignCourseButton" type="button"
                        data-completed-label="Completed"
                        data-confirm-text="The selected employee task has been marked complete.">Mark Complete</button>
                    <p class="microcopy">Your dashboard focuses on work progress, manager feedback, and the next action
                        to take.
                    </p>
                </aside>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <span>Performa probationary employee dashboard</span>
        <span>PHP-ready for Hostinger deployment</span>
    </footer>

    <script src="script.js"></script>
</body>

</html>