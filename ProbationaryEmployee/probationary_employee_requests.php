<?php
$navItems = [
    ['label' => 'Overview', 'href' => 'probationary_employee_workstream.php', 'active' => false],
    ['label' => 'My Goals', 'href' => 'probationary_employee_goals.php', 'active' => false],
    ['label' => 'Feedback', 'href' => 'probationary_employee_feedback.php', 'active' => false],
    ['label' => 'Requests', 'href' => 'probationary_employee_requests.php', 'active' => true],
    ['label' => 'Profile', 'href' => 'probationary_employee_profile.php', 'active' => false],
];

$requests = [
    ['name' => 'Training slot request', 'category' => 'Development', 'timeline' => 'Submitted 2 days ago', 'status' => 'Pending', 'statusClass' => 'status-ready', 'statusKey' => 'pending'],
    ['name' => 'Equipment replacement', 'category' => 'Operations', 'timeline' => 'Submitted 5 days ago', 'status' => 'Approved', 'statusClass' => 'status-good', 'statusKey' => 'approved'],
    ['name' => 'Shift adjustment', 'category' => 'Scheduling', 'timeline' => 'Submitted 1 day ago', 'status' => 'Needs Review', 'statusClass' => 'status-warning', 'statusKey' => 'needs-review'],
];

$insightTitle = 'Request Status';
$insightText = 'One request is pending and your equipment replacement has been approved. Follow up on the shift change request if you need a faster response.';
$recommendation = 'Send a quick note to HR about the shift request timeline.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Requests</title>
    <meta name="description" content="Employee requests page for tracking submitted support and training requests." />
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
                        <div class="brand-subtitle">Employee Requests</div>
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
                <label class="search-bar" aria-label="Search requests">
                    <span class="search-icon">⌕</span>
                    <input id="dashboardSearch" type="search" placeholder="Search request history..." />
                </label>

                <div class="topbar-actions">
                    <div class="deadline-pill">2 requests updated this week</div>
                    <button class="icon-button" type="button" aria-label="Notifications">◌</button>
                </div>
            </header>

            <section class="hero">
                <p class="eyebrow">Requests</p>
                <h1>Track the status of your submitted requests and next actions.</h1>
            </section>

            <section class="content-grid">
                <div class="panel evaluations">
                    <div class="panel-header">
                        <div>
                            <h2>Request Tracker</h2>
                            <p>Monitor approval progress and follow up on anything still pending.</p>
                        </div>
                        <div class="panel-actions">
                            <button class="ghost-button" type="button">Sort</button>
                            <button class="ghost-button" type="button">New Request</button>
                        </div>
                    </div>

                    <div class="table-toolbar">
                        <div class="chip-group" role="tablist" aria-label="Request filters">
                            <button class="filter-chip active" type="button" data-filter="all">All</button>
                            <button class="filter-chip" type="button" data-filter="approved">Approved</button>
                            <button class="filter-chip" type="button" data-filter="pending">Pending</button>
                            <button class="filter-chip" type="button" data-filter="needs-review">Needs Review</button>
                        </div>
                        <p class="table-note">Search by request type, category, or status.</p>
                    </div>

                    <div class="table-wrap" role="table" aria-label="Request list">
                        <div class="table-head" role="row">
                            <span role="columnheader">Request</span>
                            <span role="columnheader">Category</span>
                            <span role="columnheader">Submitted</span>
                            <span role="columnheader">Status</span>
                        </div>

                        <div id="evaluationRows">
                            <?php foreach ($requests as $request): ?>
                                <div class="table-row" role="row"
                                    data-search="<?php echo htmlspecialchars(strtolower($request['name'] . ' ' . $request['category'] . ' ' . $request['status']), ENT_QUOTES); ?>"
                                    data-filter="<?php echo htmlspecialchars($request['statusKey'], ENT_QUOTES); ?>">
                                    <div class="employee-cell" role="cell">
                                        <div class="avatar" style="background: linear-gradient(135deg, #6d8cff, #2f6df6);"></div>
                                        <div>
                                            <div class="employee-name"><?php echo htmlspecialchars($request['name'], ENT_QUOTES); ?></div>
                                            <div class="employee-role"><?php echo htmlspecialchars($request['category'], ENT_QUOTES); ?></div>
                                        </div>
                                    </div>

                                    <div class="timeline-cell" role="cell">
                                        <div class="timeline-text"><?php echo htmlspecialchars($request['timeline'], ENT_QUOTES); ?></div>
                                    </div>

                                    <div class="score-cell" role="cell">
                                        <strong class="score-value">-</strong>
                                    </div>

                                    <div class="status-cell" role="cell">
                                        <span class="status-pill <?php echo htmlspecialchars($request['statusClass'], ENT_QUOTES); ?>">
                                            <?php echo htmlspecialchars($request['status'], ENT_QUOTES); ?>
                                        </span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <a class="view-more" href="probationary_employee_profile.php">View Profile →</a>
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
                        data-completed-label="Acknowledged"
                        data-confirm-text="Your request review status has been updated." >Acknowledge Status</button>
                    <p class="microcopy">Requests show you the current workflow status and what to follow up on next.</p>
                </aside>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <span>Performa employee request tracker</span>
        <span>PHP and CSS implementation</span>
    </footer>

    <script src="script.js"></script>
</body>

</html>
