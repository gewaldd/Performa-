<?php
$navItems = [
    ['label' => 'Overview', 'href' => 'probationary_employee_workstream.php', 'active' => false],
    ['label' => 'My Goals', 'href' => 'probationary_employee_goals.php', 'active' => true],
    ['label' => 'Feedback', 'href' => 'probationary_employee_feedback.php', 'active' => false],
    ['label' => 'Requests', 'href' => 'probationary_employee_requests.php', 'active' => false],
    ['label' => 'Profile', 'href' => 'probationary_employee_profile.php', 'active' => false],
];

$metrics = [
    ['label' => 'Goal Completion', 'value' => '76', 'suffix' => '%', 'badge' => 'On track', 'tone' => 'positive', 'variant' => 'warm', 'icon' => '↗'],
    ['label' => 'Tasks Open', 'value' => '9', 'badge' => '4 overdue', 'tone' => 'warning', 'variant' => 'gold', 'icon' => '⚠'],
    ['label' => 'KPI Score', 'value' => '4.5', 'suffix' => '/ 5.0', 'badge' => 'Strong', 'tone' => 'neutral', 'variant' => 'mint', 'icon' => '▣'],
];

$goals = [
    [
        'name' => 'Finish quarterly review presentation',
        'category' => 'Reporting',
        'timeline' => 'Due in 3 days',
        'progress' => 88,
        'score' => 4.2,
        'stars' => 4,
        'status' => 'On Track',
        'statusClass' => 'status-good',
        'statusKey' => 'on-track',
        'progressColor' => '#16a76d',
    ],
    [
        'name' => 'Submit customer feedback audit',
        'category' => 'Quality',
        'timeline' => 'Due tomorrow',
        'progress' => 52,
        'score' => 3.8,
        'stars' => 4,
        'status' => 'Needs Focus',
        'statusClass' => 'status-warning',
        'statusKey' => 'needs-focus',
        'progressColor' => '#f0a11b',
    ],
    [
        'name' => 'Complete new onboarding module',
        'category' => 'Training',
        'timeline' => 'Due next week',
        'progress' => 64,
        'score' => 4.1,
        'stars' => 4,
        'status' => 'In Progress',
        'statusClass' => 'status-ready',
        'statusKey' => 'in-progress',
        'progressColor' => '#2f6df6',
    ],
];

$insightTitle = 'Goal Focus';
$insightText = 'Prioritize the presentation and customer audit first, then close the onboarding module with coach support.';
$recommendation = 'Schedule a quick alignment with your supervisor before Friday.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | My Goals</title>
    <meta name="description" content="Employee goals page for tracking task progress and goal completion." />
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
                        <div class="brand-subtitle">Employee Goals</div>
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
                <label class="search-bar" aria-label="Search goals">
                    <span class="search-icon">⌕</span>
                    <input id="dashboardSearch" type="search" placeholder="Search your goals..." />
                </label>

                <div class="topbar-actions">
                    <div class="deadline-pill">Next one-on-one in 10 days</div>
                    <button class="icon-button" type="button" aria-label="Notifications">◌</button>
                </div>
            </header>

            <section class="hero">
                <p class="eyebrow">My Goals</p>
                <h1>View current priorities, progress, and the next action to finish strong.</h1>
            </section>

            <section class="metrics" aria-label="Goal metrics">
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
                <div class="panel evaluations">
                    <div class="panel-header">
                        <div>
                            <h2>Goal Tracker</h2>
                            <p>Follow the progress for each active goal and see what needs attention.</p>
                        </div>
                        <div class="panel-actions">
                            <button class="ghost-button" type="button">Filter</button>
                            <button class="ghost-button" type="button">Export</button>
                        </div>
                    </div>

                    <div class="table-toolbar">
                        <div class="chip-group" role="tablist" aria-label="Goal filters">
                            <button class="filter-chip active" type="button" data-filter="all">All</button>
                            <button class="filter-chip" type="button" data-filter="on-track">On Track</button>
                            <button class="filter-chip" type="button" data-filter="needs-focus">Needs Focus</button>
                            <button class="filter-chip" type="button" data-filter="in-progress">In Progress</button>
                        </div>
                        <p class="table-note">Search by goal, category, or status.</p>
                    </div>

                    <div class="table-wrap" role="table" aria-label="Employee goal tracker">
                        <div class="table-head" role="row">
                            <span role="columnheader">Goal</span>
                            <span role="columnheader">Timeline</span>
                            <span role="columnheader">Score</span>
                            <span role="columnheader">Status</span>
                        </div>

                        <div id="evaluationRows">
                            <?php foreach ($goals as $goal): ?>
                                <div class="table-row" role="row"
                                    data-search="<?php echo htmlspecialchars(strtolower($goal['name'] . ' ' . $goal['category'] . ' ' . $goal['status']), ENT_QUOTES); ?>"
                                    data-filter="<?php echo htmlspecialchars($goal['statusKey'], ENT_QUOTES); ?>">
                                    <div class="employee-cell" role="cell">
                                        <div class="avatar" style="background: linear-gradient(135deg, #6d8cff, #2f6df6);"></div>
                                        <div>
                                            <div class="employee-name"><?php echo htmlspecialchars($goal['name'], ENT_QUOTES); ?></div>
                                            <div class="employee-role"><?php echo htmlspecialchars($goal['category'], ENT_QUOTES); ?></div>
                                        </div>
                                    </div>

                                    <div class="timeline-cell" role="cell">
                                        <div class="timeline-text"><?php echo htmlspecialchars($goal['timeline'], ENT_QUOTES); ?></div>
                                        <div class="timeline-bar">
                                            <span style="width: <?php echo (int) $goal['progress']; ?>%; background: <?php echo htmlspecialchars($goal['progressColor'], ENT_QUOTES); ?>;"></span>
                                        </div>
                                    </div>

                                    <div class="score-cell" role="cell">
                                        <strong class="score-value"><?php echo number_format((float) $goal['score'], 1); ?></strong>
                                        <div class="stars" aria-hidden="true">
                                            <?php echo str_repeat('★', (int) $goal['stars']); ?>
                                            <?php echo str_repeat('☆', 5 - (int) $goal['stars']); ?>
                                        </div>
                                    </div>

                                    <div class="status-cell" role="cell">
                                        <span class="status-pill <?php echo htmlspecialchars($goal['statusClass'], ENT_QUOTES); ?>">
                                            <?php echo htmlspecialchars($goal['status'], ENT_QUOTES); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <a class="view-more" href="probationary_employee_feedback.php">Jump to Feedback →</a>
                </div>

                <aside class="insight-card">
                    <div class="insight-badge">AI INSIGHT</div>
                    <h2><?php echo htmlspecialchars($insightTitle, ENT_QUOTES); ?></h2>
                    <p><?php echo htmlspecialchars($insightText, ENT_QUOTES); ?></p>

                    <div class="recommendation-box">
                        <div class="recommendation-label">Recommended Action</div>
                        <strong><?php echo htmlspecialchars($recommendation, ENT_QUOTES); ?></strong>
                    </div>

                    <button class="primary-button" id="assignCourseButton" type="button"
                        data-completed-label="Planned"
                        data-confirm-text="Meeting added to your schedule for goal alignment.">Plan Next Step</button>
                    <p class="microcopy">This page helps you keep goals visible as you work through the rest of your tasks.</p>
                </aside>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <span>Performa employee goals page</span>
        <span>PHP and CSS implementation</span>
    </footer>

    <script src="script.js"></script>
</body>

</html>
