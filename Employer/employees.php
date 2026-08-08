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
  'filter' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>',
  'download' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
];

$navItems = [
  ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => false, 'icon' => 'home'],
  ['label' => 'Employees', 'href' => 'employees.php', 'active' => true, 'icon' => 'users'],
  ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => false, 'icon' => 'target'],
  ['label' => 'Reports', 'href' => 'reports.php', 'active' => false, 'icon' => 'bar-chart'],
  ['label' => 'Settings', 'href' => 'settings.php', 'active' => false, 'icon' => 'settings'],
];

$employees = [
  [
    'name' => 'Maria Clara',
    'role' => 'Customer Support Spec.',
    'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=160&q=80',
    'timeline' => '45 days left',
    'progress' => 72,
    'score' => 3.8,
    'stars' => 4,
    'status' => 'Needs Review',
    'statusClass' => 'status-warning',
    'statusKey' => 'needs-review',
    'accentColor' => '#f0a11b',
  ],
  [
    'name' => 'Jose Rizal',
    'role' => 'Software Engineer',
    'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=160&q=80',
    'timeline' => '90 days left',
    'progress' => 54,
    'score' => 4.5,
    'stars' => 5,
    'status' => 'On Track',
    'statusClass' => 'status-good',
    'statusKey' => 'on-track',
    'accentColor' => '#2f6df6',
  ],
  [
    'name' => 'Gabriela Silang',
    'role' => 'Marketing Associate',
    'avatar' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=160&q=80',
    'timeline' => '2 days left',
    'progress' => 94,
    'score' => 4.8,
    'stars' => 5,
    'status' => 'Ready for Reg.',
    'statusClass' => 'status-ready',
    'statusKey' => 'ready-for-reg',
    'accentColor' => '#ed5b57',
  ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | Employees</title>
  <meta name="description" content="Review probationary employees, their roles, and current status." />
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
        <label class="search-bar" aria-label="Search employees">
          <span class="search-icon"><?php echo $icons['search']; ?></span>
          <input id="employeeSearch" type="search" placeholder="Search employees, reports..." />
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
        <h1>Employees</h1>
        <p>Review probationary employees, their roles, and current status.</p>
      </section>

      <section class="content-grid content-grid--single">
        <div class="panel evaluations">
          <div class="panel-header">
            <div>
              <h2>Employee List</h2>
              <p>Track who is on track, who needs review, and who is ready for regularization.</p>
            </div>
            <div class="panel-actions">
              <button class="ghost-button" type="button"><?php echo $icons['filter']; ?> Filter</button>
              <button class="ghost-button icon-only" type="button" aria-label="Export"><?php echo $icons['download']; ?></button>
            </div>
          </div>

          <div class="table-toolbar">
            <div class="chip-group" role="tablist" aria-label="Employee filters">
              <button class="filter-chip active" type="button" data-filter="all">All</button>
              <button class="filter-chip" type="button" data-filter="needs-review">Needs Review</button>
              <button class="filter-chip" type="button" data-filter="on-track">On Track</button>
              <button class="filter-chip" type="button" data-filter="ready-for-reg">Ready</button>
            </div>
          </div>

          <div class="table-wrap" role="table" aria-label="Employee list">
            <div class="table-head" role="row">
              <span role="columnheader">Employee</span>
              <span role="columnheader">Timeline</span>
              <span role="columnheader">KPI Score</span>
              <span role="columnheader">Status</span>
            </div>

            <div id="employeeRows">
              <?php foreach ($employees as $employee): ?>
                <div class="table-row" role="row"
                  data-search="<?php echo htmlspecialchars(strtolower($employee['name'] . ' ' . $employee['role'] . ' ' . $employee['status']), ENT_QUOTES); ?>"
                  data-filter="<?php echo htmlspecialchars($employee['statusKey'], ENT_QUOTES); ?>">
                  <div class="employee-cell" role="cell">
                    <div class="avatar"
                      style="background-image: url('<?php echo htmlspecialchars($employee['avatar'], ENT_QUOTES); ?>');">
                    </div>
                    <div>
                      <div class="employee-name"><?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?></div>
                      <div class="employee-role"><?php echo htmlspecialchars($employee['role'], ENT_QUOTES); ?></div>
                    </div>
                  </div>

                  <div class="timeline-cell" role="cell">
                    <div class="timeline-text">
                      <span class="timeline-left"
                        style="color: <?php echo htmlspecialchars($employee['accentColor'], ENT_QUOTES); ?>;"><?php echo htmlspecialchars($employee['timeline'], ENT_QUOTES); ?></span>
                    </div>
                    <div class="timeline-bar">
                      <span
                        style="width: <?php echo (int) $employee['progress']; ?>%; background: <?php echo htmlspecialchars($employee['accentColor'], ENT_QUOTES); ?>;"></span>
                    </div>
                  </div>

                  <div class="score-cell" role="cell">
                    <strong class="score-value"><?php echo number_format((float) $employee['score'], 1); ?></strong>
                    <div class="stars" aria-hidden="true">
                      <?php echo str_repeat('★', (int) $employee['stars']); ?>
                      <?php echo str_repeat('☆', 5 - (int) $employee['stars']); ?>
                    </div>
                  </div>

                  <div class="status-cell" role="cell">
                    <span
                      class="status-pill <?php echo htmlspecialchars($employee['statusClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($employee['status'], ENT_QUOTES); ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
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