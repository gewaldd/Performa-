<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_login();
require_role('supervisor');

$supervisorName = $_SESSION['name'] ?? 'Supervisor';

$navItems = [
    ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'active' => false],
    ['label' => 'My Employees', 'href' => 'employees.php', 'active' => false],
    ['label' => 'Rating Entry', 'href' => 'ratings.php', 'active' => false],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
    ['label' => 'Notifications', 'href' => 'notifications.php', 'active' => true],
];

$notifications = [];

try {
    $employees = [];
    foreach (firestore_list_documents('Users') as $doc) {
        $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
        if (strpos($roleKey, 'probation') !== false) {
            $employees[$doc['uid']] = $doc['name'] ?? $doc['email'] ?? 'Unknown';
        }
    }

    $ratings = [];
    try {
        $ratings = firestore_list_documents('Ratings');
    } catch (\Throwable $e) {
    }

    $ratedUids = [];
    foreach ($ratings as $r) {
        $ratedUids[$r['employeeUid'] ?? ''] = true;
    }

    // Notification 1: employees who have never been rated.
    foreach ($employees as $uid => $name) {
        if (empty($ratedUids[$uid])) {
            $notifications[] = [
                'title' => $name . ' has not been rated yet',
                'detail' => 'Submit a weekly rating for this employee in Rating Entry.',
                'unread' => true,
            ];
        }
    }

    // Notification 2: most recent ratings submitted (last 5).
    usort($ratings, fn($a, $b) => strcmp($b['ratedAt'] ?? '', $a['ratedAt'] ?? ''));
    $recent = array_slice($ratings, 0, 5);
    foreach ($recent as $r) {
        $notifications[] = [
            'title' => 'Rating submitted for ' . ($r['employeeName'] ?? 'an employee'),
            'detail' => 'Rated by ' . ($r['ratedBy'] === ($_SESSION['uid'] ?? '') ? 'you' : ($r['ratedByRole'] ?? 'a teammate')) . ' on ' . (!empty($r['ratedAt']) ? date('M j, Y', strtotime($r['ratedAt'])) : '—'),
            'unread' => false,
        ];
    }
} catch (\Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Notifications</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        .notif-list {
            display: flex;
            flex-direction: column;
        }

        .notif-row {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding: 16px 4px;
            border-bottom: 1px solid var(--panel-border);
        }

        .notif-row:last-child {
            border-bottom: none;
        }

        .notif-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary, #2f6df6);
            margin-top: 6px;
            flex-shrink: 0;
        }

        .notif-row.read .notif-dot {
            background: transparent;
        }

        .notif-title {
            font-size: 13.5px;
            font-weight: 600;
            color: var(--text);
        }

        .notif-detail {
            font-size: 12.5px;
            color: var(--muted);
            margin-top: 2px;
        }
    </style>
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div>
                <div class="brand">
                    <div class="brand-mark">P</div>
                    <div>
                        <div class="brand-name">Performa</div>
                        <div class="brand-subtitle">Supervisor Dashboard</div>
                    </div>
                </div>
                <nav class="nav" aria-label="Primary">
                    <?php foreach ($navItems as $item): ?>
                        <a class="nav-item<?php echo $item['active'] ? ' active' : ''; ?>"
                            href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>"><span><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span></a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="sidebar-footer">
                <div class="profile-avatar"><?php echo strtoupper(substr($supervisorName, 0, 2)); ?></div>
                <div>
                    <div class="profile-name"><?php echo htmlspecialchars($supervisorName, ENT_QUOTES); ?></div>
                    <div class="profile-role">Shift Supervisor</div>
                </div>
            </div>
        </aside>
        <main class="main">
            <section class="hero">
                <p class="eyebrow">Notifications</p>
                <h1>Alerts based on your employees' rating activity.</h1>
            </section>
            <section class="panel" style="padding:18px;">
                <div class="panel-header">
                    <div>
                        <h2>Recent Notifications</h2>
                        <p>Live data derived from Firestore employee and rating records.</p>
                    </div>
                </div>
                <?php if (empty($notifications)): ?>
                    <p style="padding:24px 0; color:var(--muted);">Nothing to show yet.</p>
                <?php else: ?>
                    <div class="notif-list">
                        <?php foreach ($notifications as $notif): ?>
                            <div class="notif-row <?php echo $notif['unread'] ? '' : 'read'; ?>">
                                <span class="notif-dot"></span>
                                <div>
                                    <div class="notif-title"><?php echo htmlspecialchars($notif['title'], ENT_QUOTES); ?></div>
                                    <div class="notif-detail"><?php echo htmlspecialchars($notif['detail'], ENT_QUOTES); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>