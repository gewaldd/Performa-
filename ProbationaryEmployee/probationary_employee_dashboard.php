<?php
require_once __DIR__ . '/data.php';

$currentUserUid = probationary_uid();
$user = probationary_user();
$profile = [
    'fullName' => $user['name'] ?? '',
    'email' => $user['email'] ?? '',
    'phone' => $user['phone'] ?? '',
    'office' => $user['office'] ?? $user['location'] ?? '',
    'mentor' => $user['supervisorName'] ?? '',
    'emergencyContact' => $user['emergencyContact'] ?? '',
];

$readonlyInfo = [
    ['label' => 'Job Role', 'value' => probationary_role_label($user)],
    ['label' => 'Hire Date', 'value' => probationary_date($user['hireDate'] ?? null, 'Not recorded')],
    ['label' => 'KPI Group', 'value' => $user['industry'] ?? $user['department'] ?? 'Not assigned'],
];

$evaluationDocuments = probationary_owned_documents('evaluations');
$ratingDocuments = probationary_owned_documents('Ratings');
$latestEvaluation = $evaluationDocuments[0] ?? $ratingDocuments[0] ?? [];
$latestScore = probationary_evaluation_score($latestEvaluation);
$acknowledgements = probationary_owned_documents('Acknowledgements');
$acknowledgementIds = array_fill_keys(array_map(static fn(array $ack): string => (string) ($ack['uid'] ?? ''), $acknowledgements), true);
foreach ($ratingDocuments as $rating) {
    $ratedAt = (string) ($rating['ratedAt'] ?? $rating['createdAt'] ?? '');
    $ratedTimestamp = $ratedAt ? strtotime($ratedAt) : false;
    if (!$ratedTimestamp) {
        continue;
    }
    $acknowledgementId = $currentUserUid . '_' . date('Y-m', $ratedTimestamp);
    if (isset($acknowledgementIds[$acknowledgementId])) {
        continue;
    }
    $acknowledgement = [
        'uid' => $acknowledgementId,
        'employeeUid' => $currentUserUid,
        'month' => date('F Y', $ratedTimestamp),
        'status' => 'Pending',
        'timestamp' => null,
    ];
    try {
        firestore_write_document('Acknowledgements', $acknowledgementId, $acknowledgement + ['createdAt' => date('c')]);
        $acknowledgements[] = $acknowledgement;
        $acknowledgementIds[$acknowledgementId] = true;
    } catch (Throwable $e) {
    }

    $feedbackId = $currentUserUid . '_' . date('Y-m', $ratedTimestamp) . '_supervisor';
    $existingFeedbackIds = array_fill_keys(array_map(static fn(array $feedback): string => (string) ($feedback['uid'] ?? ''), probationary_owned_documents('Feedback')), true);
    if (!isset($existingFeedbackIds[$feedbackId])) {
        try {
            firestore_write_document('Feedback', $feedbackId, [
                'employeeUid' => $currentUserUid,
                'sender' => ($rating['ratedByRole'] ?? '') === 'supervisor' ? 'Supervisor' : 'Employer',
                'role' => ($rating['ratedByRole'] ?? '') === 'supervisor' ? 'Supervisor' : 'Employer',
                'message' => 'Your ' . date('F Y', $ratedTimestamp) . ' KPI rating has been submitted. Review your performance summary and acknowledgement.',
                'status' => 'Received',
                'createdAt' => date('c', $ratedTimestamp),
            ]);
        } catch (Throwable $e) {
        }
    }
}
$summaryMetrics = [
    ['label' => 'Current KPI Score', 'value' => $latestScore === null ? '-' : number_format($latestScore, 1), 'badge' => 'Current', 'tone' => 'neutral', 'variant' => 'mint', 'icon' => '▣'],
    ['label' => 'Acknowledgements', 'value' => '0/' . count($acknowledgements), 'badge' => 'Pending', 'tone' => 'warning', 'variant' => 'gold', 'icon' => '⌛'],
    ['label' => 'Onboarding Progress', 'value' => (string) ($user['onboardingProgress'] ?? 0), 'suffix' => '%', 'badge' => 'Current', 'tone' => 'positive', 'variant' => 'warm', 'icon' => '✓'],
];

$monthlyTrend = [];
$previousScore = null;
foreach (array_reverse(array_merge($evaluationDocuments, $ratingDocuments)) as $evaluation) {
    $score = probationary_evaluation_score($evaluation);
    if ($score === null || $score <= 0)
        continue;
    $monthlyTrend[] = ['month' => date('M', strtotime((string) ($evaluation['createdAt'] ?? $evaluation['ratedAt'] ?? 'now'))), 'score' => number_format($score, 1), 'change' => $previousScore === null ? '-' : sprintf('%+.1f', $score - $previousScore)];
    $previousScore = $score;
}

$feedbackItems = [];
foreach (probationary_owned_documents('Feedback') as $feedback) {
    $feedbackItems[] = ['source' => $feedback['sender'] ?? $feedback['ratedByName'] ?? 'Supervisor', 'text' => $feedback['message'] ?? $feedback['notes'] ?? '', 'date' => probationary_date($feedback['createdAt'] ?? null)];
}

$notifications = [];
foreach (probationary_owned_documents('notifications') as $notification) {
    $notifications[] = ['title' => $notification['title'] ?? 'Notification', 'detail' => $notification['detail'] ?? $notification['message'] ?? '', 'date' => probationary_date($notification['createdAt'] ?? null), 'type' => $notification['type'] ?? 'info'];
}
$notificationIds = array_fill_keys(array_map(static fn(array $notification): string => (string) ($notification['uid'] ?? ''), probationary_owned_documents('notifications')), true);
foreach ($ratingDocuments as $rating) {
    $ratedAt = (string) ($rating['ratedAt'] ?? $rating['createdAt'] ?? '');
    $ratedTimestamp = $ratedAt ? strtotime($ratedAt) : false;
    if (!$ratedTimestamp) {
        continue;
    }
    $notificationId = $currentUserUid . '_' . date('Y-m', $ratedTimestamp) . '_summary';
    if (isset($notificationIds[$notificationId])) {
        continue;
    }
    $notification = [
        'uid' => $notificationId,
        'employeeUid' => $currentUserUid,
        'title' => 'Performance summary ready',
        'detail' => 'Your ' . date('F Y', $ratedTimestamp) . ' performance summary is available for acknowledgement.',
        'type' => 'info',
        'createdAt' => date('c', $ratedTimestamp),
    ];
    try {
        firestore_write_document('notifications', $notificationId, $notification);
        $notifications[] = ['title' => $notification['title'], 'detail' => $notification['detail'], 'date' => probationary_date($notification['createdAt']), 'type' => $notification['type']];
        $notificationIds[$notificationId] = true;
    } catch (Throwable $e) {
    }
}
$profileUpdated = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveProfile'])) {
    $profile['fullName'] = trim($_POST['fullName'] ?? $profile['fullName']);
    $profile['email'] = trim($_POST['email'] ?? $profile['email']);
    $profile['phone'] = trim($_POST['phone'] ?? $profile['phone']);
    $profile['emergencyContact'] = trim($_POST['emergencyContact'] ?? $profile['emergencyContact']);
    try {
        firestore_write_document('Users', $currentUserUid, ['name' => $profile['fullName'], 'email' => $profile['email'], 'phone' => $profile['phone'], 'emergencyContact' => $profile['emergencyContact']]);
        $profileUpdated = true;
    } catch (Throwable $e) {
        $profileUpdated = false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['acknowledgeSummary'])) {
    $requestedMonth = trim($_POST['acknowledgeSummary']);
    $requestedAcknowledgementId = trim((string) ($_POST['acknowledgementId'] ?? ''));
    foreach ($acknowledgements as &$acknowledgement) {
        if ($acknowledgement['month'] === $requestedMonth && $acknowledgement['status'] === 'Pending') {
            $acknowledgedAt = date('c');
            $monthTimestamp = strtotime('1 ' . $requestedMonth) ?: time();
            $acknowledgementId = $requestedAcknowledgementId !== ''
                ? $requestedAcknowledgementId
                : (string) ($acknowledgement['uid'] ?? ($currentUserUid . '_' . date('Y-m', $monthTimestamp)));
            try {
                $acknowledgementData = ['employeeUid' => $currentUserUid, 'month' => $requestedMonth, 'status' => 'Acknowledged', 'timestamp' => $acknowledgedAt];
                firestore_write_document('Acknowledgements', $acknowledgementId, $acknowledgementData);
                $savedAcknowledgement = firestore_get_document('Acknowledgements', $acknowledgementId);
                if (($savedAcknowledgement['status'] ?? '') !== 'Acknowledged' || empty($savedAcknowledgement['timestamp'])) {
                    throw new RuntimeException('Firestore did not confirm the acknowledgement update.');
                }
                $acknowledgement['status'] = 'Acknowledged';
                $acknowledgement['timestamp'] = $acknowledgedAt;
                $acknowledgement['uid'] = $acknowledgementId;
            } catch (Throwable $e) {
                error_log('Unable to save acknowledgement: ' . $e->getMessage());
            }
            break;
        }
    }
    unset($acknowledgement);
    $_SESSION['probationary_acknowledgements'] = $acknowledgements;
}

$acknowledgedCount = count(array_filter($acknowledgements, static fn(array $ack): bool => $ack['status'] === 'Acknowledged'));
$pendingAcknowledgementCount = count($acknowledgements) - $acknowledgedCount;
$summaryMetrics[1]['value'] = $acknowledgedCount . '/' . count($acknowledgements);
$summaryMetrics[1]['badge'] = $pendingAcknowledgementCount > 0 ? 'Pending' : 'Complete';
$summaryMetrics[1]['tone'] = $pendingAcknowledgementCount > 0 ? 'warning' : 'positive';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Probationary Employee</title>
    <meta name="description" content="Probationary employee page for KPI review and acknowledgement." />
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
                    <a class="nav-item active" href="#dashboard" data-section="dashboard"
                        aria-current="page"><span>Overview</span></a>
                    <a class="nav-item" href="#profile" data-section="profile"><span>Profile</span></a>
                    <a class="nav-item" href="#performance" data-section="performance"><span>Performance</span></a>
                    <a class="nav-item" href="#acknowledgements"
                        data-section="acknowledgements"><span>Acknowledgements</span></a>
                    <a class="nav-item" href="#notifications"
                        data-section="notifications"><span>Notifications</span></a>
                </nav>
            </div>

            <div class="sidebar-footer">
                <div class="profile-avatar">MC</div>
                <div>
                    <div class="profile-name"><?php echo htmlspecialchars($profile['fullName'], ENT_QUOTES); ?></div>
                    <div class="profile-role">Probationary Employee</div>
                </div>
                <a class="logout-link" href="../logout.php" aria-label="Sign out">Sign out</a>
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
                    <button class="icon-button" type="button" aria-label="Notifications">Notifications</button>
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

            <section class="content-grid" data-tab-group>
                <div class="panel tab-section" id="profile" data-tab-section="profile">
                    <div class="panel-header">
                        <div>
                            <h2>Profile</h2>
                            <p>Update your contact details. Employer-managed data is displayed as read-only.</p>
                        </div>
                    </div>

                    <?php if ($profileUpdated): ?>
                        <div class="alert-banner">Profile updated.</div>
                    <?php endif; ?>

                    <form class="profile-form" method="post">
                        <div class="form-grid">
                            <div class="field-group">
                                <label for="fullName">Full Name</label>
                                <input id="fullName" name="fullName" type="text"
                                    value="<?php echo htmlspecialchars($profile['fullName'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email"
                                    value="<?php echo htmlspecialchars($profile['email'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group">
                                <label for="phone">Phone</label>
                                <input id="phone" name="phone" type="tel"
                                    value="<?php echo htmlspecialchars($profile['phone'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group">
                                <label for="office">Office / Location</label>
                                <input id="office" type="text"
                                    value="<?php echo htmlspecialchars($profile['office'], ENT_QUOTES); ?>" readonly />
                            </div>
                            <div class="field-group field-full">
                                <label for="emergencyContact">Emergency Contact</label>
                                <input id="emergencyContact" name="emergencyContact" type="text"
                                    value="<?php echo htmlspecialchars($profile['emergencyContact'], ENT_QUOTES); ?>" />
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

                <div class="panel tab-section" id="performance" data-tab-section="performance">
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

            <section class="content-grid" data-tab-group>
                <div class="panel tab-section" id="acknowledgements" data-tab-section="acknowledgements">
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
                                <span
                                    class="ack-timestamp"><?php echo $ack['timestamp'] ? htmlspecialchars($ack['timestamp'], ENT_QUOTES) : 'Not yet'; ?></span>
                                <?php if ($ack['status'] === 'Pending'): ?>
                                    <form method="post">
                                        <input type="hidden" name="acknowledgementId"
                                            value="<?php echo htmlspecialchars($ack['uid'] ?? '', ENT_QUOTES); ?>" />
                                        <button class="acknowledge-button" type="submit" name="acknowledgeSummary"
                                            value="<?php echo htmlspecialchars($ack['month'], ENT_QUOTES); ?>">Acknowledge</button>
                                    </form>
                                <?php else: ?>
                                    <button class="acknowledge-button acknowledged" type="button" disabled>Acknowledged</button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <aside class="insight-card tab-section" id="notifications" data-tab-section="notifications">
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

                    <p class="microcopy">Only your own probationary alerts are shown here - no other employees are
                        visible.</p>
                </aside>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <span>Performa probationary employee page</span>
        <span>Plain PHP surface view only</span>
    </footer>

    <script src="script.js?v=2"></script>
</body>

</html>