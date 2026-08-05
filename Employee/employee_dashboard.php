<?php
$navItems = [
    ['label' => 'Overview', 'href' => '#dashboard', 'active' => true],
    ['label' => 'My Goals', 'href' => '#goals', 'active' => false],
    ['label' => 'Feedback', 'href' => '#feedback', 'active' => false],
    ['label' => 'Requests', 'href' => '#requests', 'active' => false],
    ['label' => 'Profile', 'href' => '#profile', 'active' => false],
];

$metrics = [
    ['label' => 'Tasks Completed', 'value' => '18', 'badge' => '+4 this week', 'tone' => 'positive', 'variant' => 'warm', 'icon' => '✓'],
    ['label' => 'KPI Score', 'value' => '4.6', 'suffix' => '/ 5.0', 'badge' => 'Strong', 'tone' => 'neutral', 'variant' => 'mint', 'icon' => '▣'],
    ['label' => 'Pending Feedback', 'value' => '3', 'badge' => 'Needs response', 'tone' => 'warning', 'variant' => 'gold', 'icon' => '✎'],
];

$items = [
    [
        'name' => 'Weekly Sales Report',
        'category' => 'Finance',
        'timeline' => 'Due in 2 days',
        'progress' => 82,
        'score' => 4.7,
        'stars' => 5,
        'status' => 'On Track',
        'statusClass' => 'status-good',
        'statusKey' => 'on-track',
        'progressColor' => '#2f6df6',
    ],
    [
        'name' => 'Customer Follow-up Log',
        'category' => 'Operations',
        'timeline' => 'Review today',
        'progress' => 64,
        'score' => 4.2,
        'stars' => 4,
        'status' => 'In Progress',
        'statusClass' => 'status-warning',
        'statusKey' => 'in-progress',
        'progressColor' => '#f0a11b',
    ],
    [
        'name' => 'Improvement Plan',
        'category' => 'Development',
        'timeline' => 'Next check-in next week',
        'progress' => 45,
        'score' => 4.0,
        'stars' => 4,
        'status' => 'Assigned',
        'statusClass' => 'status-ready',
        'statusKey' => 'assigned',
        'progressColor' => '#16a76d',
    ],
];

$insightTitle = 'Performance Snapshot';
$insightText = 'You are meeting expectations. Focus on consistency, faster follow-through, and keeping feedback cycles active.';
$recommendation = 'Complete the customer service refresher module';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Employee Dashboard</title>
    <meta name="description" content="Employee dashboard for tracking tasks, feedback, and performance goals." />
    <link rel="stylesheet" href="styles.css" />
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div>
                <div class="brand">
                    <div class="brand-mark">P</div>
                    <div>
                        <div class="brand-name">Performa</div>
                        <div class="brand-subtitle">Employee Dashboard</div>
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
                    <div class="profile-name">Juan Dela Cruz</div>
                    <div class="profile-role">Employee</div>
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
                    <div class="deadline-pill">Next review in 14 days</div>
                    <button class="icon-button" type="button" aria-label="Notifications">◌</button>
                </div>
            </header>

            <section class="hero">
                <p class="eyebrow">Employee Overview</p>
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
                    <a class="view-more" href="#feedback">View Feedback History →</a>
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
        <span>Performa employee dashboard prototype</span>
        <span>PHP-ready for Hostinger deployment</span>
    </footer>

    <script src="script.js"></script>
</body>

</html>