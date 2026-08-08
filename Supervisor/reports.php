<?php
$navItems = [
    ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'active' => false],
    ['label' => 'My Employees', 'href' => 'employees.php', 'active' => false],
    ['label' => 'Rating Entry', 'href' => 'ratings.php', 'active' => false],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => true],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
    ['label' => 'Notifications', 'href' => 'notifications.php', 'active' => false],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Reports</title>
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
                <p class="eyebrow">Reports</p>
                <h1>Export summaries and performance snapshots for your team.</h1>
            </section>
            <section class="panel" style="padding:18px;">
                <div class="panel-header">
                    <div>
                        <h2>Report Center</h2>
                        <p>Prepare evaluation reports for your assigned employees.</p>
                    </div>
                    <div class="panel-actions"><button class="ghost-button" type="button">Export PDF</button><button
                            class="ghost-button" type="button">Download CSV</button></div>
                </div>
                <div class="metric-card" style="margin-top:14px;">
                    <div class="metric-icon">◔</div>
                    <div class="metric-meta"><span>Assigned Employees</span><strong>5</strong></div>
                    <div class="metric-badge positive">Ready</div>
                </div>
            </section>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>