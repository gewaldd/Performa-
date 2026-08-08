<?php
$navItems = [
    ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'active' => false],
    ['label' => 'My Employees', 'href' => 'employees.php', 'active' => false],
    ['label' => 'Rating Entry', 'href' => 'ratings.php', 'active' => false],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
    ['label' => 'Notifications', 'href' => 'notifications.php', 'active' => true],
];

// TODO(firebase): replace with a Firestore query on the notifications
// collection, filtered to the current supervisor's uid.
$notifications = [
    ['title' => 'Maria Clara is nearing her deadline', 'detail' => '45 days left until regularization decision.', 'time' => '2 hours ago', 'unread' => true],
    ['title' => 'New KPI added by Employer', 'detail' => '"Quality of Work" was added to the Customer Support role.', 'time' => 'Yesterday', 'unread' => true],
    ['title' => 'Rating submitted successfully', 'detail' => 'Your weekly rating for Jose Rizal was recorded.', 'time' => '3 days ago', 'unread' => false],
];
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
            background: var(--primary);
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

        .notif-time {
            font-size: 11.5px;
            color: var(--muted);
            white-space: nowrap;
            margin-left: auto;
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
                <div class="profile-avatar">SP</div>
                <div>
                    <div class="profile-name">Sofia Panganiban</div>
                    <div class="profile-role">Shift Supervisor</div>
                </div>
            </div>
        </aside>
        <main class="main">
            <section class="hero">
                <p class="eyebrow">Notifications</p>
                <h1>Alerts and updates.</h1>
            </section>
            <section class="panel" style="padding:18px;">
                <div class="panel-header">
                    <div>
                        <h2>Recent Notifications</h2>
                        <p>Deadline reminders and updates relevant to your assigned employees.</p>
                    </div>
                </div>
                <div class="notif-list">
                    <?php foreach ($notifications as $notif): ?>
                        <div class="notif-row <?php echo $notif['unread'] ? '' : 'read'; ?>">
                            <span class="notif-dot"></span>
                            <div>
                                <div class="notif-title"><?php echo htmlspecialchars($notif['title'], ENT_QUOTES); ?></div>
                                <div class="notif-detail"><?php echo htmlspecialchars($notif['detail'], ENT_QUOTES); ?></div>
                            </div>
                            <span class="notif-time"><?php echo htmlspecialchars($notif['time'], ENT_QUOTES); ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>