<?php
$icons = [
  'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'target' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
  'bar-chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
  'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
  'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
  'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  'chevron-down' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
  'download' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  'file' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
];

$navItems = [
  ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => false, 'icon' => 'home'],
  ['label' => 'Employees', 'href' => 'employees.php', 'active' => false, 'icon' => 'users'],
  ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => false, 'icon' => 'target'],
  ['label' => 'Reports', 'href' => 'reports.php', 'active' => true, 'icon' => 'bar-chart'],
  ['label' => 'Settings', 'href' => 'settings.php', 'active' => false, 'icon' => 'settings'],
];

$reports = [
  ['title' => 'Maria Clara – Monthly Performance Summary', 'meta' => 'Generated on Sep 1, 2024 • 2.4 MB', 'iconClass' => 'blue'],
  ['title' => 'Jose Rizal – Learning & Development Audit', 'meta' => 'Generated on Aug 25, 2024 • 3.1 MB', 'iconClass' => 'orange'],
  ['title' => 'Gabriela Silang – Probationary Status Report', 'meta' => 'Generated on Aug 18, 2024 • 1.8 MB', 'iconClass' => 'green'],
  ['title' => 'Andres Bonifacio – Underperformance Risk Analysis', 'meta' => 'Generated on Aug 10, 2024 • 0.9 MB', 'iconClass' => 'red'],
  ['title' => 'Maria Clara – Learning & Development Audit', 'meta' => 'Generated on Aug 05, 2024 • 2.7 MB', 'iconClass' => 'blue'],
];

$contributors = [
  'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=80&q=80',
  'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=80&q=80',
  'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=80&q=80',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | Employee Reports</title>
  <meta name="description" content="Create and manage individual performance assessments." />
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
        <div></div>
        <div class="topbar-actions">
          <div class="toolbar-pill"><?php echo $icons['calendar']; ?> Review Period: Q3 2024</div>
          <button class="icon-button" type="button" aria-label="Notifications"><?php echo $icons['bell']; ?></button>
        </div>
      </header>

      <div class="page-header">
        <div>
          <h1>Employee Reports</h1>
          <p>Create and manage individual performance assessments</p>
        </div>
      </div>

      <div class="report-panel">
        <h3>Generate New Report</h3>
        <p>Select an employee and the type of report you wish to generate.</p>

        <div class="report-form-row">
          <div>
            <span class="field-label">Select Employee</span>
            <div class="select-field">Maria Clara <?php echo $icons['chevron-down']; ?></div>
          </div>
          <div>
            <span class="field-label">Report Type</span>
            <div class="select-field">Monthly Performance Summary <?php echo $icons['chevron-down']; ?></div>
          </div>
          <button class="btn-dark" type="button"><?php echo $icons['plus']; ?> Generate Report</button>
        </div>
      </div>

      <div class="reports-section-header">
        <h3>Generated Reports</h3>
        <span>Total: <?php echo count($reports); ?> reports</span>
      </div>

      <div class="report-list">
        <?php foreach ($reports as $report): ?>
          <div class="report-item">
            <div class="file-icon <?php echo htmlspecialchars($report['iconClass'], ENT_QUOTES); ?>"><?php echo $icons['file']; ?></div>
            <div class="report-info">
              <div class="report-title"><?php echo htmlspecialchars($report['title'], ENT_QUOTES); ?></div>
              <div class="report-meta"><?php echo htmlspecialchars($report['meta'], ENT_QUOTES); ?></div>
            </div>
            <div class="report-actions">
              <button class="btn-outline" type="button"><?php echo $icons['download']; ?> Download PDF</button>
              <button class="btn-outline" type="button">View</button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="contributors-row">
        <span class="contributors-label">Top Contributors This Period</span>
        <div class="avatar-stack">
          <?php foreach ($contributors as $avatar): ?>
            <div class="avatar" style="background-image: url('<?php echo htmlspecialchars($avatar, ENT_QUOTES); ?>');"></div>
          <?php endforeach; ?>
        </div>
        <span class="contributors-text">Analytics Team Lead & 12 others</span>
      </div>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa employer dashboard prototype</span>
    <span>PHP-ready for Hostinger deployment</span>
  </footer>

  <script src="script.js"></script>
</body>

</html>