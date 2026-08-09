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
  'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  'chevron-down' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
  'download' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  'more-vertical' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="5" r="1"/><circle cx="12" cy="12" r="1"/><circle cx="12" cy="19" r="1"/></svg>',
];

$navItems = [
  ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => false, 'icon' => 'home'],
  ['label' => 'Employees', 'href' => 'employees.php', 'active' => true, 'icon' => 'users'],
  ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => false, 'icon' => 'target'],
  ['label' => 'Reports', 'href' => 'reports.php', 'active' => false, 'icon' => 'bar-chart'],
  ['label' => 'Settings', 'href' => 'settings.php', 'active' => false, 'icon' => 'settings'],
];

$directory = [
  [
    'name' => 'Maria Clara', 'email' => 'maria.clara@performa.ph',
    'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=160&q=80',
    'role' => 'Customer Support Spec.', 'dept' => 'Customer Success', 'deptClass' => 'dept-blue',
    'type' => 'Probationary', 'status' => 'Active', 'statusClass' => 'status-good',
  ],
  [
    'name' => 'Jose Rizal', 'email' => 'jose.rizal@performa.ph',
    'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=160&q=80',
    'role' => 'Software Engineer', 'dept' => 'Engineering', 'deptClass' => 'dept-gray',
    'type' => 'Regular', 'status' => 'Active', 'statusClass' => 'status-good',
  ],
  [
    'name' => 'Gabriela Silang', 'email' => 'gabriela.s@performa.ph',
    'avatar' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=160&q=80',
    'role' => 'Marketing Associate', 'dept' => 'Marketing', 'deptClass' => 'dept-orange',
    'type' => 'Regular', 'status' => 'On Leave', 'statusClass' => 'status-warning',
  ],
  [
    'name' => 'Andres Bonifacio', 'email' => 'andres.b@performa.ph', 'avatar' => null,
    'role' => 'Sales Director', 'dept' => 'Sales', 'deptClass' => 'dept-green',
    'type' => 'Regular', 'status' => 'Active', 'statusClass' => 'status-good',
  ],
  [
    'name' => 'Melchora Aquino', 'email' => 'm.aquino@performa.ph', 'avatar' => null,
    'role' => 'Operations Lead', 'dept' => 'Operations', 'deptClass' => 'dept-purple',
    'type' => 'Regular', 'status' => 'Active', 'statusClass' => 'status-good',
  ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | Employees</title>
  <meta name="description" content="Manage and organize your workforce directory." />
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
        <label class="search-bar" aria-label="Search employees, departments">
          <span class="search-icon"><?php echo $icons['search']; ?></span>
          <input type="search" placeholder="Search employees, departments..." />
        </label>
        <div class="topbar-actions">
          <div class="pending-pill"><?php echo $icons['bell']; ?> 2 pending approvals</div>
          <button class="icon-button" type="button" aria-label="Messages"><?php echo $icons['mail']; ?></button>
        </div>
      </header>

      <div class="page-header">
        <div>
          <h1>Employees</h1>
          <p>Manage and organize your workforce directory.</p>
        </div>
        <button class="btn-primary" type="button"><?php echo $icons['plus']; ?> Add Employee</button>
      </div>

      <div class="filter-bar">
        <div class="filter-group">
          <div class="filter-select"><span>Department:</span> All Departments <?php echo $icons['chevron-down']; ?></div>
          <div class="filter-select"><span>Status:</span> Active <?php echo $icons['chevron-down']; ?></div>
          <div class="filter-select"><span>Type:</span> All Types <?php echo $icons['chevron-down']; ?></div>
        </div>
        <div class="filter-actions">
          <button class="reset-button" type="button">Reset</button>
          <button class="icon-button-square" type="button" aria-label="Export"><?php echo $icons['download']; ?></button>
        </div>
      </div>

      <div class="directory-panel">
        <div class="directory-head">
          <span>Employee</span>
          <span>Role</span>
          <span>Department</span>
          <span>Employment Type</span>
          <span>Status</span>
          <span>Actions</span>
        </div>

        <?php foreach ($directory as $person): ?>
          <div class="directory-row">
            <div class="employee-cell">
              <div class="avatar<?php echo $person['avatar'] ? '' : ' avatar-empty'; ?>"
                <?php if ($person['avatar']): ?>style="background-image: url('<?php echo htmlspecialchars($person['avatar'], ENT_QUOTES); ?>');"<?php endif; ?>>
              </div>
              <div>
                <div class="employee-name"><?php echo htmlspecialchars($person['name'], ENT_QUOTES); ?></div>
                <div class="employee-email"><?php echo htmlspecialchars($person['email'], ENT_QUOTES); ?></div>
              </div>
            </div>
            <div><?php echo htmlspecialchars($person['role'], ENT_QUOTES); ?></div>
            <div><span class="dept-pill <?php echo htmlspecialchars($person['deptClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($person['dept'], ENT_QUOTES); ?></span></div>
            <div><?php echo htmlspecialchars($person['type'], ENT_QUOTES); ?></div>
            <div><span class="status-pill <?php echo htmlspecialchars($person['statusClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($person['status'], ENT_QUOTES); ?></span></div>
            <div><button class="edit-button" type="button" aria-label="More actions"><?php echo $icons['more-vertical']; ?></button></div>
          </div>
        <?php endforeach; ?>

        <div class="pagination-bar">
          <span>Showing <strong>5</strong> of <strong>124</strong> employees</span>
          <div class="page-buttons">
            <button class="page-btn" type="button">Previous</button>
            <button class="page-btn active" type="button">Next</button>
          </div>
        </div>
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