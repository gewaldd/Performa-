<?php
$navItems = [
    ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => false],
    ['label' => 'Employees', 'href' => 'employees.php', 'active' => false],
    ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => true],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | KPIs</title>
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
                <p class="eyebrow">KPIs</p>
                <h1>Monitor performance trends, deadlines, and conversion readiness.</h1>
            </section>
            <section class="metrics">
                <article class="metric-card warm">
                    <div class="metric-icon">↗</div>
                    <div class="metric-meta"><span>Average KPI</span><strong>4.2</strong></div>
                    <div class="metric-badge positive">+0.3</div>
                </article>
                <article class="metric-card gold">
                    <div class="metric-icon">⌛</div>
                    <div class="metric-meta"><span>At Risk</span><strong>8</strong></div>
                    <div class="metric-badge warning">Needs follow-up</div>
                </article>
                <article class="metric-card mint">
                    <div class="metric-icon">▣</div>
                    <div class="metric-meta"><span>Review Ready</span><strong>11</strong></div>
                    <div class="metric-badge neutral">Stable</div>
                </article>
            </section>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>