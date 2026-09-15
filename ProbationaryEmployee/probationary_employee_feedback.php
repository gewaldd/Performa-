<?php
require_once __DIR__ . '/data.php';

$user = probationary_user();
$feedbackDocuments = probationary_owned_documents('Feedback');
$evaluationDocuments = probationary_owned_documents('evaluations');
$navItems = [
    ['label' => 'Overview', 'href' => 'probationary_employee_workstream.php', 'active' => false],
    ['label' => 'My Goals', 'href' => 'probationary_employee_goals.php', 'active' => false],
    ['label' => 'Feedback', 'href' => 'probationary_employee_feedback.php', 'active' => true],
    ['label' => 'Profile', 'href' => 'probationary_employee_profile.php', 'active' => false],
];

$feedbackEntries = array_map(static function (array $entry): array {
    $status = $entry['status'] ?? 'Received';
    $statusKey = strtolower(str_replace(' ', '-', (string) $status));
    return [
        'sender' => $entry['sender'] ?? $entry['ratedByName'] ?? 'Supervisor',
        'role' => $entry['role'] ?? $entry['ratedByRole'] ?? 'Supervisor',
        'message' => $entry['message'] ?? $entry['notes'] ?? 'Performance feedback recorded.',
        'date' => probationary_date($entry['createdAt'] ?? $entry['ratedAt'] ?? null),
        'status' => $status,
        'statusClass' => $statusKey === 'received' ? 'status-good' : ($statusKey === 'action-required' ? 'status-warning' : 'status-ready'),
        'rating' => (int) ($entry['rating'] ?? $entry['score'] ?? 0),
    ];
}, array_merge($feedbackDocuments, $evaluationDocuments));

$insightTitle = 'Feedback Summary';
$insightText = $feedbackEntries ? 'Your latest feedback is available for review.' : 'No feedback has been recorded yet.';
$recommendation = 'Review the latest feedback with your supervisor.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Feedback</title>
    <meta name="description" content="Probationary employee feedback page for review history and manager comments." />
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
                        <div class="brand-subtitle">Probationary Employee Feedback</div>
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
                <label class="search-bar" aria-label="Search feedback">
                    <span class="search-icon">⌕</span>
                    <input id="dashboardSearch" type="search" placeholder="Search manager feedback..." />
                </label>

                <div class="topbar-actions">
                    <div class="deadline-pill">3 new comments this week</div>
                    <button class="icon-button" type="button" aria-label="Notifications">Notifications</button>
                </div>
            </header>

            <section class="hero">
                <p class="eyebrow">Feedback</p>
                <h1>Review manager comments and team feedback in one place.</h1>
            </section>

            <section class="content-grid">
                <div class="panel evaluations">
                    <div class="panel-header">
                        <div>
                            <h2>Feedback History</h2>
                            <p>See the latest comments and your current response status.</p>
                        </div>
                        <div class="panel-actions">
                            <button class="ghost-button" type="button">Sort</button>
                            <button class="ghost-button" type="button">Filter</button>
                        </div>
                    </div>

                    <div class="table-toolbar">
                        <div class="chip-group" role="tablist" aria-label="Feedback filters">
                            <button class="filter-chip active" type="button" data-filter="all">All</button>
                            <button class="filter-chip" type="button" data-filter="received">Received</button>
                            <button class="filter-chip" type="button" data-filter="action-required">Action
                                Required</button>
                            <button class="filter-chip" type="button" data-filter="pending">Pending</button>
                        </div>
                        <p class="table-note">Search by sender, topic, or status.</p>
                    </div>

                    <div class="table-wrap" role="table" aria-label="Feedback list">
                        <div class="table-head" role="row">
                            <span role="columnheader">From</span>
                            <span role="columnheader">Message</span>
                            <span role="columnheader">Date</span>
                            <span role="columnheader">Status</span>
                        </div>

                        <div id="evaluationRows">
                            <?php foreach ($feedbackEntries as $entry): ?>
                                <div class="table-row" role="row"
                                    data-search="<?php echo htmlspecialchars(strtolower($entry['sender'] . ' ' . $entry['role'] . ' ' . $entry['message']), ENT_QUOTES); ?>"
                                    data-filter="<?php echo htmlspecialchars(strtolower(str_replace(' ', '-', $entry['status'])), ENT_QUOTES); ?>">
                                    <div class="employee-cell" role="cell">
                                        <div class="avatar" style="background: linear-gradient(135deg, #6d8cff, #2f6df6);">
                                        </div>
                                        <div>
                                            <div class="employee-name">
                                                <?php echo htmlspecialchars($entry['sender'], ENT_QUOTES); ?>
                                            </div>
                                            <div class="employee-role">
                                                <?php echo htmlspecialchars($entry['role'], ENT_QUOTES); ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="timeline-cell" role="cell">
                                        <div class="timeline-text">
                                            <?php echo htmlspecialchars($entry['message'], ENT_QUOTES); ?>
                                        </div>
                                    </div>

                                    <div class="score-cell" role="cell">
                                        <strong
                                            class="score-value"><?php echo htmlspecialchars($entry['date'], ENT_QUOTES); ?></strong>
                                    </div>

                                    <div class="status-cell" role="cell">
                                        <span
                                            class="status-pill <?php echo htmlspecialchars($entry['statusClass'], ENT_QUOTES); ?>">
                                            <?php echo htmlspecialchars($entry['status'], ENT_QUOTES); ?>
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
                        data-confirm-text="Your feedback review has been noted.">Acknowledge Feedback</button>
                    <p class="microcopy">Use your feedback page to track what needs a response and where you are already
                        aligned.</p>
                </aside>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <span>Performa probationary employee feedback page</span>
        <span>PHP and CSS implementation</span>
    </footer>

    <script src="script.js"></script>
</body>

</html>