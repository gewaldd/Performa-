<?php
$navItems = [
    ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => false],
    ['label' => 'Employees', 'href' => 'employees.php', 'active' => false],
    ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => false],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => true],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
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
                <p class="eyebrow">Reports</p>
                <h1>Export summaries, completion records, and performance snapshots.</h1>
            </section>
            <section class="panel" style="padding:18px;">
                <div class="panel-header">
                    <div>
                        <h2>Report Center</h2>
                        <p>Prepare evaluation reports for meetings and reviews.</p>
                    </div>
                    <div class="panel-actions"><button class="ghost-button" type="button">Export PDF</button><button
                            class="ghost-button" type="button">Download CSV</button></div>
                </div>
                <div class="metric-card" style="margin-top:14px;">
                    <div class="metric-icon">◔</div>
                    <div class="metric-meta"><span>Monthly Summary</span><strong>42</strong></div>
                    <div class="metric-badge positive">Ready</div>
                </div>
            </section>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>