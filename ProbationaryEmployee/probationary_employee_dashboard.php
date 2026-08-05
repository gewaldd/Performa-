<?php
$navItems = [
    ['label' => 'Overview', 'href' => '#dashboard', 'active' => true],
    ['label' => 'Goals', 'href' => '#goals', 'active' => false],
    ['label' => 'Coaching', 'href' => '#coaching', 'active' => false],
    ['label' => 'Review', 'href' => '#review', 'active' => false],
    ['label' => 'Plan', 'href' => '#plan', 'active' => false],
];

$metrics = [
    ['label' => 'Days in Probation', 'value' => '87', 'badge' => '53 left', 'tone' => 'neutral', 'variant' => 'mint', 'icon' => '⌛'],
    ['label' => 'Goal Completion', 'value' => '76', 'suffix' => '%', 'badge' => 'Growing', 'tone' => 'positive', 'variant' => 'warm', 'icon' => '↗'],
    ['label' => 'Manager Notes', 'value' => '5', 'badge' => '2 urgent', 'tone' => 'warning', 'variant' => 'gold', 'icon' => '✉'],
];

$items = [
    [
        'name' => 'Onboarding Checklist',
        'category' => 'Core Training',
        'timeline' => 'Due this Friday',
        'progress' => 88,
        'score' => 4.4,
        'stars' => 4,
        'status' => 'Nearly Done',
        'statusClass' => 'status-good',
        'statusKey' => 'nearly-done',
        'progressColor' => '#16a76d',
    ],
    [
        'name' => 'Weekly KPI Check-in',
        'category' => 'Evaluation',
        'timeline' => 'Tomorrow 3:00 PM',
        'progress' => 61,
        'score' => 3.9,
        'stars' => 4,
        'status' => 'In Review',
        'statusClass' => 'status-warning',
        'statusKey' => 'in-review',
        'progressColor' => '#f0a11b',
    ],
    [
        'name' => 'Customer Communication Drill',
        'category' => 'Coaching',
        'timeline' => 'Next week',
        'progress' => 39,
        'score' => 3.6,
        'stars' => 4,
        'status' => 'Needs Focus',
        'statusClass' => 'status-ready',
        'statusKey' => 'needs-focus',
        'progressColor' => '#2f6df6',
    ],
];

$insightTitle = 'Probation Progress';
$insightText = 'You are on a healthy path. Strengthen consistency in communication and finish the remaining learning items before the next review.';
$recommendation = 'Schedule a coaching session with your supervisor';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Probationary Employee Dashboard</title>
    <meta name="description"
        content="Probationary employee dashboard for tracking onboarding, coaching, and review progress." />
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
                        <div class="brand-subtitle">Probationary Dashboard</div>
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
                <div class="profile-avatar">MC</div>
                <div>
                    <div class="profile-name">Maria Clara</div>
                    <div class="profile-role">Probationary Employee</div>
                </div>
            </div>
        </aside>

        <main class="main" id="dashboard">
            <header class="topbar">
                <label class="search-bar" aria-label="Search tasks or coaching items">
                    <span class="search-icon">⌕</span>
                    <input id="dashboardSearch" type="search" placeholder="Search tasks, coaching, reviews..." />
                </label>

                <div class="topbar-actions">
                    <div class="deadline-pill">Regularization review in 53 days</div>
                    <button class="icon-button" type="button" aria-label="Notifications">◌</button>
                </div>
            </header>

            <section class="hero">
                <p class="eyebrow">Probationary Overview</p>
                <h1>Keep track of your onboarding, coaching, and review progress.</h1>
            </section>

            <section class="metrics" id="goals" aria-label="Probationary metrics">
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
                <div class="panel evaluations" id="review">
                    <div class="panel-header">
                        <div>
                            <h2>Progress Checklist</h2>
                            <p>View the items that matter before your next probation review.</p>
                        </div>
                        <div class="panel-actions">
                            <button class="ghost-button" type="button">Filter</button>
                            <button class="ghost-button" type="button">Export</button>
                        </div>
                    </div>

                    <div class="table-toolbar">
                        <div class="chip-group" role="tablist" aria-label="Progress filters">
                            <button class="filter-chip active" type="button" data-filter="all">All</button>
                            <button class="filter-chip" type="button" data-filter="nearly-done">Nearly Done</button>
                            <button class="filter-chip" type="button" data-filter="in-review">In Review</button>
                            <button class="filter-chip" type="button" data-filter="needs-focus">Needs Focus</button>
                        </div>
                        <p class="table-note">Search by item, category, or status.</p>
                    </div>

                    <div class="table-wrap" role="table" aria-label="Probationary progress">
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

                    <span id="coaching" class="section-anchor" aria-hidden="true"></span>
                    <a class="view-more" href="#coaching">View Coaching Plan →</a>
                </div>

                <aside class="insight-card" id="plan">
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
                        data-confirm-text="Your probationary action has been saved for the next review cycle.">Mark
                        Completed</button>
                    <p class="microcopy">Your probation dashboard highlights the next milestone so you can prepare for
                        regularization.
                    </p>
                </aside>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <span>Performa probationary employee dashboard prototype</span>
        <span>PHP-ready for Hostinger deployment</span>
    </footer>

    <script src="script.js"></script>
</body>

</html>