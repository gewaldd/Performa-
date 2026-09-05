<?php
$rootDir = __DIR__ . '/..';
require_once $rootDir . '/auth.php';
require_once $rootDir . '/firebase_init.php';

require_login();
require_role('employer');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (empty($_SESSION['uid'])) {
  header('Location: ../login.php');
  exit;
}

$profileName = $_SESSION['name'] ?? 'Unknown User';
$profileRole = $_SESSION['role'] ?? 'Employer';
$profileRoleDisplay = ucwords(str_replace('_', ' ', $profileRole));

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

function normalize_role_key(?string $role): string
{
  $roleKey = strtolower(trim((string) $role));
  if (strpos($roleKey, 'probation') !== false)
    return 'probationary';
  if (strpos($roleKey, 'supervis') !== false)
    return 'supervisor';
  if (strpos($roleKey, 'employ') !== false)
    return 'employer';
  if (strpos($roleKey, 'admin') !== false)
    return 'admin';
  return $roleKey;
}

function display_role_label(?string $role): string
{
  return ucwords(str_replace('_', ' ', (string) $role));
}

$deptClassCycle = ['dept-blue', 'dept-gray', 'dept-orange', 'dept-green', 'dept-purple'];
$directory = [];
try {
  $docs = firestore_list_documents('Users');
  $i = 0;
  foreach ($docs as $doc) {
    $roleKey = normalize_role_key($doc['role'] ?? null);
    if ($roleKey === 'admin' || $roleKey === 'employer') {
      continue;
    }
    $avatarSeed = urlencode(strtolower($doc['email'] ?? ($doc['name'] ?? 'user')));
    $status = $doc['status'] ?? 'Active';
    $directory[] = [
      'uid' => $doc['uid'] ?? '',
      'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
      'email' => $doc['email'] ?? '',
      'avatar' => 'https://ui-avatars.com/api/?name=' . $avatarSeed . '&background=2f6df6&color=fff&size=160',
      'role' => display_role_label($doc['role'] ?? null),
      'dept' => ($doc['department'] ?? '') ?: display_role_label($doc['role'] ?? null),
      'deptClass' => $deptClassCycle[$i % count($deptClassCycle)],
      'type' => $roleKey === 'probationary' ? 'Probationary' : 'Regular',
      'status' => $status,
      'statusClass' => $status === 'Disabled' ? 'status-danger' : 'status-good',
    ];
    $i++;
  }
} catch (Throwable $e) {
  // leave $directory empty so the page still renders
}

$departments = array_values(array_unique(array_column($directory, 'dept')));
sort($departments);
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
          style="background-image: url('<?php echo "https://ui-avatars.com/api/?name=" . urlencode($profileName) . "&background=2f6df6&color=fff&size=160"; ?>');"></div>
        <div>
          <div class="profile-name"><?php echo htmlspecialchars($profileName, ENT_QUOTES); ?></div>
          <div class="profile-role"><?php echo htmlspecialchars($profileRoleDisplay, ENT_QUOTES); ?></div>
        </div>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <label class="search-bar" aria-label="Search employees, departments">
          <span class="search-icon"><?php echo $icons['search']; ?></span>
          <input type="search" id="employeeSearch" placeholder="Search employees, departments..." />
        </label>
        <div class="topbar-actions">
          <button class="icon-button" type="button" aria-label="Messages"><?php echo $icons['mail']; ?></button>
          <a class="ghost-button" href="../logout.php" aria-label="Sign out">Sign out</a>
        </div>
      </header>

      <div class="page-header">
        <div>
          <h1>Employees</h1>
          <p>Manage and organize your workforce directory.</p>
        </div>
        <a class="btn-primary" href="add_employee.php"><?php echo $icons['plus']; ?> Add Employee</a>
      </div>

      <?php if (isset($_GET['created'])): ?>
        <div class="alert-banner alert-success">
          Account created for <strong><?php echo htmlspecialchars($_GET['name'] ?? '', ENT_QUOTES); ?></strong>.
          <?php if (!empty($_GET['temp_password'])): ?>
            Temporary password: <code><?php echo htmlspecialchars($_GET['temp_password'], ENT_QUOTES); ?></code>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <div class="filter-bar">
        <div class="filter-group">
          <label class="filter-select"><span>Department:</span>
            <select id="deptFilter">
              <option value="">All Departments</option>
              <?php foreach ($departments as $dept): ?>
                <option value="<?php echo htmlspecialchars($dept, ENT_QUOTES); ?>"><?php echo htmlspecialchars($dept, ENT_QUOTES); ?></option>
              <?php endforeach; ?>
            </select>
          </label>
          <label class="filter-select"><span>Status:</span>
            <select id="statusFilter">
              <option value="">All Statuses</option>
              <option value="Active">Active</option>
              <option value="Disabled">Disabled</option>
            </select>
          </label>
          <label class="filter-select"><span>Type:</span>
            <select id="typeFilter">
              <option value="">All Types</option>
              <option value="Probationary">Probationary</option>
              <option value="Regular">Regular</option>
            </select>
          </label>
        </div>
        <div class="filter-actions">
          <button class="reset-button" type="button" id="resetFiltersBtn">Reset</button>
          <button class="icon-button-square" type="button" id="exportDirectoryBtn" aria-label="Export"><?php echo $icons['download']; ?></button>
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

        <?php if (empty($directory)): ?>
          <div class="empty-state">
            <p>No employees yet. Click "Add Employee" to create the first profile.</p>
          </div>
        <?php else: ?>
          <div id="directoryRows">
            <?php foreach ($directory as $person): ?>
              <div class="directory-row"
                data-search="<?php echo htmlspecialchars(strtolower($person['name'] . ' ' . $person['email'] . ' ' . $person['dept']), ENT_QUOTES); ?>"
                data-dept="<?php echo htmlspecialchars($person['dept'], ENT_QUOTES); ?>"
                data-status="<?php echo htmlspecialchars($person['status'], ENT_QUOTES); ?>"
                data-type="<?php echo htmlspecialchars($person['type'], ENT_QUOTES); ?>">
                <div class="employee-cell">
                  <div class="avatar" style="background-image: url('<?php echo htmlspecialchars($person['avatar'], ENT_QUOTES); ?>');">
                  </div>
                  <div>
                    <div class="employee-name"><?php echo htmlspecialchars($person['name'], ENT_QUOTES); ?></div>
                    <div class="employee-email"><?php echo htmlspecialchars($person['email'], ENT_QUOTES); ?></div>
                  </div>
                </div>
                <div data-label="Role"><?php echo htmlspecialchars($person['role'], ENT_QUOTES); ?></div>
                <div data-label="Department"><span class="dept-pill <?php echo htmlspecialchars($person['deptClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($person['dept'], ENT_QUOTES); ?></span></div>
                <div data-label="Employment Type"><?php echo htmlspecialchars($person['type'], ENT_QUOTES); ?></div>
                <div data-label="Status"><span class="status-pill <?php echo htmlspecialchars($person['statusClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($person['status'], ENT_QUOTES); ?></span></div>
                <div data-label="Actions"><a class="edit-button" href="employee_view.php?uid=<?php echo urlencode($person['uid']); ?>" aria-label="Manage employee" style="text-decoration:none;display:inline-flex;align-items:center;gap:4px;">Manage</a></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <div class="pagination-bar">
          <span id="paginationSummary">Showing <strong><?php echo count($directory); ?></strong> of <strong><?php echo count($directory); ?></strong> employees</span>
          <div class="page-buttons">
            <button class="page-btn" type="button" id="prevPageBtn">Previous</button>
            <span id="pageIndicator" style="align-self:center;font-size:13px;color:var(--muted);"></span>
            <button class="page-btn" type="button" id="nextPageBtn">Next</button>
          </div>
        </div>
      </div>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa employer dashboard prototype</span>
    <span>Powered by PHP &amp; Firebase</span>
  </footer>

  <script src="employees.js"></script>
</body>

</html>