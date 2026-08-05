<?php
$navItems = [
    ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => false],
    ['label' => 'Employees', 'href' => 'employees.php', 'active' => false],
    ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => false],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => true],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Settings</title>
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
                        <div class="brand-subtitle">Employer Dashboard</div>
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
                <div class="profile-avatar">JD</div>
                <div>
                    <div class="profile-name">Juan Dela Cruz</div>
                    <div class="profile-role">HR Director</div>
                </div>
            </div>
        </aside>
        <main class="main">
            <section class="hero">
                <p class="eyebrow">Settings</p>
                <h1>Adjust dashboard preferences, access, and notifications.</h1>
            </section>
            <section class="panel" style="padding:18px;">
                <div class="panel-header">
                    <div>
                        <h2>Workspace Settings</h2>
                        <p>Use this page for role permissions and dashboard preferences.</p>
                    </div>
                </div>
                <div class="recommendation-box" style="margin-top:14px;">
                    <div class="recommendation-label">Current Theme</div><strong>Employer Blue</strong>
                </div>
            </section>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>