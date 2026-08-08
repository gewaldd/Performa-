<?php
session_start();

$currentUserUid = $_SESSION['uid'] ?? 'probationary-uid-123';
$userRole = $_SESSION['role'] ?? 'probationary_employee';

if ($userRole !== 'probationary_employee') {
    http_response_code(403);
    echo 'Access denied. This page is for probationary employees only.';
    exit;
}

$profile = [
    'fullName' => 'Maria Clara',
    'email' => 'maria.clara@example.com',
    'phone' => '+63 912 345 6789',
    'office' => 'Branch 4B',
    'mentor' => 'Maria Santos',
    'emergencyContact' => 'Jose Clara',
];

$readonlyInfo = [
    ['label' => 'Job Role', 'value' => 'Customer Support Specialist'],
    ['label' => 'Hire Date', 'value' => 'Jan 12, 2025'],
    ['label' => 'KPI Group', 'value' => 'Customer Satisfaction'],
];

$summaryMetrics = [
    ['label' => 'Current KPI Score', 'value' => '4.4', 'badge' => 'Stable', 'tone' => 'neutral', 'variant' => 'mint', 'icon' => '▣'],
    ['label' => 'Acknowledgements', 'value' => '1/3', 'badge' => 'Pending', 'tone' => 'warning', 'variant' => 'gold', 'icon' => '⌛'],
    ['label' => 'Onboarding Progress', 'value' => '76', 'suffix' => '%', 'badge' => 'Good', 'tone' => 'positive', 'variant' => 'warm', 'icon' => '✓'],
];

$monthlyTrend = [
    ['month' => 'Jan', 'score' => '4.2', 'change' => '+0.1'],
    ['month' => 'Feb', 'score' => '4.4', 'change' => '+0.2'],
    ['month' => 'Mar', 'score' => '4.3', 'change' => '-0.1'],
];

$feedbackItems = [
    ['source' => 'Leadership Review', 'text' => 'Your monthly score looks positive; continue to focus on response time.', 'date' => 'Apr 28'],
    ['source' => 'Training Coordinator', 'text' => 'Your coaching session is scheduled for next week.', 'date' => 'Apr 20'],
];

$notifications = [
    ['title' => 'April performance summary ready', 'detail' => 'Your April performance summary has been published and is available for acknowledgement.', 'date' => 'May 10', 'type' => 'info'],
    ['title' => 'Reminder: probation check-in', 'detail' => 'Your next check-in is scheduled in 5 days with your supervisor.', 'date' => 'May 8', 'type' => 'reminder'],
];

$acknowledgements = [
    ['month' => 'April 2026', 'status' => 'Pending', 'timestamp' => null],
    ['month' => 'March 2026', 'status' => 'Acknowledged', 'timestamp' => '2026-04-05 14:23'],
    ['month' => 'February 2026', 'status' => 'Acknowledged', 'timestamp' => '2026-03-07 10:15'],
];

$profileUpdated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveProfile'])) {
    $profile['fullName'] = trim($_POST['fullName'] ?? $profile['fullName']);
    $profile['email'] = trim($_POST['email'] ?? $profile['email']);
    $profile['phone'] = trim($_POST['phone'] ?? $profile['phone']);
    $profile['office'] = trim($_POST['office'] ?? $profile['office']);
    $profile['emergencyContact'] = trim($_POST['emergencyContact'] ?? $profile['emergencyContact']);
    $profileUpdated = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Probationary Employee</title>
    <meta name="description" content="Probationary employee page for KPI review and acknowledgement." />
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
                        <div class="brand-subtitle">Probationary Employee</div>
                    </div>
                </div>

                <nav class="nav" aria-label="Primary">
                    <a class="nav-item active" href="#dashboard"><span>Overview</span></a>
                    <a class="nav-item" href="#profile"><span>Profile</span></a>
                    <a class="nav-item" href="#performance"><span>Performance</span></a>
                    <a class="nav-item" href="#acknowledgements"><span>Acknowledgements</span></a>
                    <a class="nav-item" href="#notifications"><span>Notifications</span></a>
                </nav>
            </div>

            <div class="sidebar-footer">
                <div class="profile-avatar">MC</div>
                <div>
                    <div class="profile-name"><?php echo htmlspecialchars($profile['fullName'], ENT_QUOTES); ?></div>
                    <div class="profile-role">Probationary Employee</div>
                </div>
            </div>
        </aside>

        <main class="main" id="dashboard">
            <header class="topbar">
                <label class="search-bar" aria-label="Search page sections">
                    <span class="search-icon">⌕</span>
                    <input id="dashboardSearch" type="search" placeholder="Search summaries, notifications..." />
                </label>
                <div class="topbar-actions">
                    <div class="deadline-pill">Next review in 53 days</div>
                    <button class="icon-button" type="button" aria-label="Notifications">🔔</button>
                </div>
            </header>

            <section class="hero">
                <p class="eyebrow">Probationary Self-Monitoring</p>
                <h1>View your own progress, profile details, and acknowledgement trail.</h1>
            </section>

            <section class="metrics" aria-label="Performance summary metrics">
                <?php foreach ($summaryMetrics as $metric): ?>
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
                <div class="panel" id="profile">
                    <div class="panel-header">
                        <div>
                            <h2>Profile</h2>
                            <p>Update your contact details. Employer-managed data is displayed as read-only.</p>
                        </div>
                    </div>

                    <?php if ($profileUpdated): ?>
                        <div class="alert-banner">Profile updated locally. Firestore sync will be added later.</div>
                    <?php endif; ?>

                    <form class="profile-form" method="post">
                        <div class="form-grid">
                            <div class="field-group">
                                <label for="fullName">Full Name</label>
                                <input id="fullName" name="fullName" type="text" value="<?php echo htmlspecialchars($profile['fullName'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($profile['email'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group">
                                <label for="phone">Phone</label>
                                <input id="phone" name="phone" type="tel" value="<?php echo htmlspecialchars($profile['phone'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group">
                                <label for="office">Office / Location</label>
                                <input id="office" name="office" type="text" value="<?php echo htmlspecialchars($profile['office'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group field-full">
                                <label for="emergencyContact">Emergency Contact</label>
                                <input id="emergencyContact" name="emergencyContact" type="text" value="<?php echo htmlspecialchars($profile['emergencyContact'], ENT_QUOTES); ?>" />
                            </div>
                        </div>

                        <div class="profile-footer">
                            <div class="readonly-panel">
                                <h3>Employer-managed data</h3>
                                <?php foreach ($readonlyInfo as $info): ?>
                                    <div class="readonly-row">
                                        <span><?php echo htmlspecialchars($info['label'], ENT_QUOTES); ?></span>
                                        <strong><?php echo htmlspecialchars($info['value'], ENT_QUOTES); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button class="primary-button" type="submit" name="saveProfile">Save changes</button>
                        </div>
                    </form>
                </div>

                <div class="panel" id="performance">
                    <div class="panel-header">
                        <div>
                            <h2>Performance Summary</h2>
                            <p>Your KPI scores and trend data are read-only for self-monitoring.</p>
                        </div>
                    </div>

                    <div class="summary-card">
                        <h3>Monthly trend</h3>
                        <div class="summary-table" aria-label="Monthly KPI performance trend">
                            <div class="summary-row summary-head">
                                <span>Month</span>
                                <span>Score</span>
                                <span>Change</span>
                            </div>
                            <?php foreach ($monthlyTrend as $row): ?>
                                <div class="summary-row">
                                    <span><?php echo htmlspecialchars($row['month'], ENT_QUOTES); ?></span>
                                    <span><?php echo htmlspecialchars($row['score'], ENT_QUOTES); ?></span>
                                    <span><?php echo htmlspecialchars($row['change'], ENT_QUOTES); ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="summary-card">
                        <h3>Employer feedback</h3>
                        <ul class="feedback-list">
                            <?php foreach ($feedbackItems as $item): ?>
                                <li>
                                    <div class="feedback-meta">
                                        <strong><?php echo htmlspecialchars($item['source'], ENT_QUOTES); ?></strong>
                                        <span><?php echo htmlspecialchars($item['date'], ENT_QUOTES); ?></span>
                                    </div>
                                    <p><?php echo htmlspecialchars($item['text'], ENT_QUOTES); ?></p>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </div>
            </section>

            <section class="content-grid">
                <div class="panel" id="acknowledgements">
                    <div class="panel-header">
                        <div>
                            <h2>Acknowledgements</h2>
                            <p>Confirm receipt of each monthly performance summary.</p>
                        </div>
                    </div>

                    <div class="acknowledgement-table">
                        <div class="table-head acknowledge-head">
                            <span>Summary</span>
                            <span>Status</span>
                            <span>Timestamp</span>
                            <span></span>
                        </div>
                        <?php foreach ($acknowledgements as $ack): ?>
                            <div class="ack-row">
                                <span><?php echo htmlspecialchars($ack['month'], ENT_QUOTES); ?></span>
                                <span class="ack-status"><?php echo htmlspecialchars($ack['status'], ENT_QUOTES); ?></span>
                                <span class="ack-timestamp"><?php echo $ack['timestamp'] ? htmlspecialchars($ack['timestamp'], ENT_QUOTES) : 'Not yet'; ?></span>
                                <button class="acknowledge-button<?php echo $ack['status'] !== 'Pending' ? ' acknowledged' : ''; ?>" type="button"<?php echo $ack['status'] !== 'Pending' ? ' disabled' : ''; ?>><?php echo $ack['status'] === 'Pending' ? 'Acknowledge' : 'Acknowledged'; ?></button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <aside class="insight-card" id="notifications">
                    <div class="insight-badge">Notifications</div>
                    <h2>System alerts</h2>
                    <p>Read-only reminders and updates from the employer/system side.</p>

                    <ul class="notification-list">
                        <?php foreach ($notifications as $note): ?>
                            <li class="notification-item <?php echo htmlspecialchars($note['type'], ENT_QUOTES); ?>">
                                <div>
                                    <strong><?php echo htmlspecialchars($note['title'], ENT_QUOTES); ?></strong>
                                    <p><?php echo htmlspecialchars($note['detail'], ENT_QUOTES); ?></p>
                                </div>
                                <span class="note-date"><?php echo htmlspecialchars($note['date'], ENT_QUOTES); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>

                    <p class="microcopy">Only your own probationary alerts are shown here � no other employees are visible.</p>
                </aside>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <span>Performa probationary employee page</span>
        <span>Plain PHP surface view only</span>
    </footer>

    <script src="script.js"></script>
</body>

</html>
