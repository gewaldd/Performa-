<?php
$icons = [
  'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'target' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
  'bar-chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
  'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
  'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
  'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
  'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>',
  'hourglass' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M5 22v-4a7 7 0 0 1 5-6.7A7 7 0 0 1 5 4.7V2M19 22v-4a7 7 0 0 0-5-6.7A7 7 0 0 0 19 4.7V2"/></svg>',
  'trend' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
  'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>',
];

$navItems = [
  ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => false, 'icon' => 'home'],
  ['label' => 'Employees', 'href' => 'employees.php', 'active' => false, 'icon' => 'users'],
  ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => true, 'icon' => 'target'],
  ['label' => 'Reports', 'href' => 'reports.php', 'active' => false, 'icon' => 'bar-chart'],
  ['label' => 'Settings', 'href' => 'settings.php', 'active' => false, 'icon' => 'settings'],
];

$metrics = [
  ['label' => 'Average KPI', 'value' => '4.2', 'badge' => '+0.3', 'tone' => 'positive', 'iconClass' => 'icon-warm', 'icon' => 'trend'],
  ['label' => 'At Risk', 'value' => '8', 'badge' => 'Needs follow-up', 'tone' => 'warning', 'iconClass' => 'icon-gold', 'icon' => 'hourglass'],
  ['label' => 'Review Ready', 'value' => '11', 'badge' => 'Stable', 'tone' => 'neutral', 'iconClass' => 'icon-mint', 'icon' => 'check'],
];

$kpiBreakdown = [
  ['label' => 'Customer Support Spec.', 'metric' => 'Response Time & CSAT', 'score' => 3.8, 'accentColor' => '#f0a11b', 'progress' => 76],
  ['label' => 'Software Engineer', 'metric' => 'Sprint Velocity & Code Quality', 'score' => 4.5, 'accentColor' => '#2f6df6', 'progress' => 90],
  ['label' => 'Marketing Associate', 'metric' => 'Campaign Output & Engagement', 'score' => 4.8, 'accentColor' => '#ed5b57', 'progress' => 96],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | KPIs</title>
  <meta name="description" content="Monitor performance trends, deadlines, and conversion readiness." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
</head>

<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div>
        <div class="brand">
          <div class="brand-mark">
            <span class="brand-mark-dot"></span>
          </div>
          <div class="brand-name">Performa</div>
        </div>

        <nav class="nav" aria-label="Primary">
          <?php foreach ($navItems as $item): ?>
            <a class="nav-item<?php echo $item['active'] ? ' active' : ''; ?>"
              href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>">
              <span class="nav-icon"><?php echo $icons[$item['icon']]; ?></span>
              <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <div class="sidebar-footer">
        <div class="profile-avatar"
          style="background-image: url('https://randomuser.me/api/portraits/men/32.jpg');"></div>
        <div>
          <div class="profile-name">Juan Dela Cruz</div>
          <div class="profile-role">HR Director</div>
        </div>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <label class="search-bar" aria-label="Search KPIs">
          <span class="search-icon"><?php echo $icons['search']; ?></span>
          <input id="kpiSearch" type="search" placeholder="Search employees, reports..." />
        </label>

        <div class="topbar-actions">
          <div class="deadline-pill">
            <span class="deadline-icon"><?php echo $icons['bell']; ?></span>
            2 days until regularization deadline
          </div>
          <button class="icon-button" type="button" aria-label="Messages"><?php echo $icons['mail']; ?></button>
        </div>
      </header>

      <section class="hero">
        <h1>KPIs</h1>
        <p>Monitor performance trends, deadlines, and conversion readiness.</p>
      </section>

      <section class="metrics" aria-label="KPI summary">
        <?php foreach ($metrics as $metric): ?>
          <article class="metric-card">
            <div class="metric-card-top">
              <div class="metric-icon <?php echo htmlspecialchars($metric['iconClass'], ENT_QUOTES); ?>">
                <?php echo $icons[$metric['icon']]; ?>
              </div>
              <div class="metric-badge <?php echo htmlspecialchars($metric['tone'], ENT_QUOTES); ?>">
                <?php echo htmlspecialchars($metric['badge'], ENT_QUOTES); ?>
              </div>
            </div>
            <div class="metric-meta">
              <span><?php echo htmlspecialchars($metric['label'], ENT_QUOTES); ?></span>
              <strong><?php echo htmlspecialchars($metric['value'], ENT_QUOTES); ?></strong>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="content-grid content-grid--single">
        <div class="panel evaluations">
          <div class="panel-header">
            <div>
              <h2>KPI Breakdown by Role</h2>
              <p>Current scoring trend for each probationary employee's core metric.</p>
            </div>
          </div>

          <div id="kpiRows">
            <?php foreach ($kpiBreakdown as $row): ?>
              <div class="table-row" style="grid-template-columns: 1.4fr 1fr 0.6fr;" role="row">
                <div>
                  <div class="employee-name"><?php echo htmlspecialchars($row['label'], ENT_QUOTES); ?></div>
                  <div class="employee-role"><?php echo htmlspecialchars($row['metric'], ENT_QUOTES); ?></div>
                </div>
                <div class="timeline-cell" role="cell">
                  <div class="timeline-bar">
                    <span
                      style="width: <?php echo (int) $row['progress']; ?>%; background: <?php echo htmlspecialchars($row['accentColor'], ENT_QUOTES); ?>;"></span>
                  </div>
                </div>
                <div class="score-cell" role="cell">
                  <strong class="score-value"><?php echo number_format((float) $row['score'], 1); ?></strong>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa employer dashboard prototype</span>
    <span>PHP-ready for Hostinger deployment</span>
  </footer>

  <script src="script.js"></script>
</body>

</html>