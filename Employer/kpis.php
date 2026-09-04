<?php
$rootDir = __DIR__ . '/..';
require_once $rootDir . '/auth.php';
require_once $rootDir . '/firebase_init.php';
require_once $rootDir . '/kpi_templates.php';

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
  'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  'chevron-down' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
  'edit' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>',
  'code' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
  'clock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>',
  'video' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/></svg>',
  'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
  'star' => '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
  'dot' => '<svg viewBox="0 0 24 24" fill="currentColor" stroke="none"><circle cx="12" cy="12" r="6"/></svg>',
  'alert' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
  'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>',
  'download' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
];

$navItems = [
  ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => false, 'icon' => 'home'],
  ['label' => 'Employees', 'href' => 'employees.php', 'active' => false, 'icon' => 'users'],
  ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => true, 'icon' => 'target'],
  ['label' => 'Reports', 'href' => 'reports.php', 'active' => false, 'icon' => 'bar-chart'],
  ['label' => 'Settings', 'href' => 'settings.php', 'active' => false, 'icon' => 'settings'],
];

$trendGlyph = ['up' => '↑↑', 'flat' => '↔', 'down' => '↓↓'];
$trendClass = ['up' => 'trend-up', 'flat' => 'trend-flat', 'down' => 'trend-down'];
$badgeCycle = ['blue', 'purple', 'orange', 'green'];
$accentCycle = ['#16a76d', '#2f6df6', '#eb9e21', '#8b5cf6'];

// Load probationary employees for the picker, with industry for template lookup
$probationaryEmployees = [];
try {
  $docs = firestore_list_documents('Users');
  foreach ($docs as $doc) {
    $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
    if (strpos($roleKey, 'probation') !== false) {
      $probationaryEmployees[] = [
        'uid' => $doc['uid'] ?? '',
        'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
        'industry' => $doc['industry'] ?? 'retail',
      ];
    }
  }
} catch (Throwable $e) {
  // leave $probationaryEmployees empty so the page still renders
}

$selectedEmployeeId = $_GET['employee'] ?? '';
$selectedEmployee = null;
if ($probationaryEmployees) {
  foreach ($probationaryEmployees as $emp) {
    if ($emp['uid'] === $selectedEmployeeId) {
      $selectedEmployee = $emp;
      break;
    }
  }
  if (!$selectedEmployee) {
    $selectedEmployee = $probationaryEmployees[0];
  }
}
$selectedEmployeeName = $selectedEmployee ? $selectedEmployee['name'] : 'No employees yet';
$currentIndustry = $selectedEmployee['industry'] ?? 'retail';

$kpiMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'add_kpi') {
  $newName = trim($_POST['kpi_name'] ?? '');
  $newTarget = (float) ($_POST['kpi_target'] ?? 4.0);
  $industryForKpi = $_POST['industry'] ?? $currentIndustry;
  if ($newName) {
    try {
      add_custom_kpi($industryForKpi, $newName, max(1.0, min(5.0, $newTarget)));
      $kpiMessage = 'KPI added.';
    } catch (\Throwable $e) {
      $kpiMessage = 'Failed to add KPI: ' . $e->getMessage();
    }
  }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit_kpi_target') {
  $kpiKey = $_POST['kpi_key'] ?? '';
  $newTarget = (float) ($_POST['kpi_target'] ?? 4.0);
  $industryForKpi = $_POST['industry'] ?? $currentIndustry;
  if ($kpiKey) {
    try {
      set_kpi_target_override($industryForKpi, $kpiKey, max(1.0, min(5.0, $newTarget)));
      $kpiMessage = 'KPI target updated.';
    } catch (\Throwable $e) {
      $kpiMessage = 'Failed to update KPI: ' . $e->getMessage();
    }
  }
}

$template = kpi_template_for($currentIndustry);

// Load this employee's ratings, newest first, to get current + previous scores for trend
$latestScores = [];
$previousScores = [];
if ($selectedEmployee) {
  try {
    $allRatings = firestore_list_documents('Ratings');
    $mine = array_filter($allRatings, fn($r) => ($r['employeeUid'] ?? '') === $selectedEmployee['uid']);
    usort($mine, fn($a, $b) => strcmp($b['ratedAt'] ?? '', $a['ratedAt'] ?? ''));
    $mine = array_values($mine);
    if (isset($mine[0]['scores'])) {
      $latestScores = $mine[0]['scores'];
    }
    if (isset($mine[1]['scores'])) {
      $previousScores = $mine[1]['scores'];
    }
  } catch (Throwable $e) {
    // leave scores empty, page still renders with "no ratings yet"
  }
}

$employeeKpis = [];
foreach ($template['kpis'] as $kpi) {
  $current = isset($latestScores[$kpi['key']]) ? (float) $latestScores[$kpi['key']] : null;
  $previous = isset($previousScores[$kpi['key']]) ? (float) $previousScores[$kpi['key']] : null;

  $trend = 'flat';
  if ($current !== null && $previous !== null) {
    if ($current > $previous)
      $trend = 'up';
    elseif ($current < $previous)
      $trend = 'down';
  }

  $statusInfo = $current !== null ? kpi_status_for_score($current, $kpi['target']) : ['status' => 'No Data', 'statusClass' => 'status-neutral'];

  $employeeKpis[] = [
    'key' => $kpi['key'],
    'name' => $kpi['name'],
    'sub' => 'Target ' . number_format($kpi['target'], 1) . ' / 5.0',
    'target' => $kpi['target'],
    'current' => $current ?? 0.0,
    'hasData' => $current !== null,
    'stars' => $current !== null ? (int) round($current) : 0,
    'trend' => $trend,
    'status' => $statusInfo['status'],
    'statusClass' => $statusInfo['statusClass'],
  ];
}

$categoryCards = [];
foreach (array_slice($template['kpis'], 0, 3) as $i => $kpi) {
  $current = isset($latestScores[$kpi['key']]) ? (float) $latestScores[$kpi['key']] : null;
  $statusInfo = $current !== null ? kpi_status_for_score($current, $kpi['target']) : ['status' => 'No Data', 'statusClass' => 'status-neutral'];
  $categoryCards[] = [
    'badge' => $template['label'], 'badgeClass' => $badgeCycle[$i % count($badgeCycle)], 'icon' => 'clock',
    'title' => $kpi['name'], 'target' => number_format($kpi['target'], 1),
    'current' => ($current !== null ? number_format($current, 1) : '—') . ' / 5.0',
    'progress' => $current !== null ? min(100, (int) round(($current / 5.0) * 100)) : 0,
    'accentColor' => $accentCycle[$i % count($accentCycle)],
    'status' => $statusInfo['status'], 'statusIcon' => 'dot', 'statusClass' => $statusInfo['statusClass'],
  ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | KPIs</title>
  <meta name="description" content="Define and track organization-wide performance metrics." />
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
      <div class="page-header">
        <div>
          <h1>Key Performance Indicators</h1>
          <p>Define and track organization-wide performance metrics.</p>
        </div>
        <a class="btn-primary" href="#addKpiForm"><?php echo $icons['plus']; ?> Create KPI</a>
      </div>

      <?php if ($kpiMessage): ?>
        <div style="margin-bottom:16px;padding:12px;border-radius:8px;background:rgba(47,109,246,0.08);color:var(--text);"><?php echo htmlspecialchars($kpiMessage, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <div class="toolbar">
        <label class="search-bar" aria-label="Search KPIs, categories">
          <span class="search-icon"><?php echo $icons['search']; ?></span>
          <input type="search" id="kpiSearch" placeholder="Search KPIs, categories..." />
        </label>
        <button class="icon-button" type="button" aria-label="Notifications"><?php echo $icons['bell']; ?></button>
        <a class="ghost-button" href="../logout.php" aria-label="Sign out">Sign out</a>
      </div>

      <div class="section-header">
        <div>
          <h2>Individual Employee KPI</h2>
          <p>Manage specific performance targets and scores for individual team members.</p>
        </div>
        <button class="btn-primary" type="button" id="exportKpiBtn"><?php echo $icons['download']; ?> Export Report</button>
      </div>

      <div class="employee-select-wrap">
        <span class="employee-select-label">Select Employee:</span>
        <?php if ($probationaryEmployees): ?>
          <form method="get" class="employee-select" style="padding:0;">
            <span class="employee-select-icon"><?php echo $icons['user']; ?></span>
            <select name="employee" onchange="this.form.submit()"
              style="border:none;background:transparent;font:inherit;color:inherit;appearance:none;cursor:pointer;">
              <?php foreach ($probationaryEmployees as $emp): ?>
                <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>"
                  <?php echo ($selectedEmployee && $emp['uid'] === $selectedEmployee['uid']) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?>
                </option>
              <?php endforeach; ?>
            </select>
            <span class="employee-select-chevron"><?php echo $icons['chevron-down']; ?></span>
          </form>
          <a class="ghost-button" href="rate_employee.php?employee=<?php echo urlencode($selectedEmployee['uid'] ?? ''); ?>">Rate this employee</a>
        <?php else: ?>
          <div class="employee-select">
            <span class="employee-select-icon"><?php echo $icons['user']; ?></span>
            <span>No probationary employees yet</span>
          </div>
        <?php endif; ?>
      </div>

      <div class="metrics-panel">
        <div class="metrics-panel-header">
          <h3>Performance Metrics</h3>
        </div>

        <div class="kpi-table-head">
          <span>KPI Name</span>
          <span>Target Score</span>
          <span>Current Score</span>
          <span>Trend</span>
          <span>Status</span>
          <span>Actions</span>
        </div>

        <?php foreach ($employeeKpis as $kpi): ?>
          <div class="kpi-row" data-search="<?php echo htmlspecialchars(strtolower($kpi['name']), ENT_QUOTES); ?>">
            <div>
              <div class="kpi-name"><?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?></div>
              <div class="kpi-sub"><?php echo htmlspecialchars($kpi['sub'], ENT_QUOTES); ?></div>
            </div>
            <div class="kpi-target"><?php echo number_format($kpi['target'], 1); ?></div>
            <div class="kpi-current">
              <div class="stars" aria-hidden="true">
                <?php echo str_repeat('★', (int) $kpi['stars']) . str_repeat('☆', 5 - (int) $kpi['stars']); ?>
              </div>
              <strong><?php echo number_format($kpi['current'], 1); ?></strong>
            </div>
            <div class="trend <?php echo $trendClass[$kpi['trend']]; ?>"><?php echo $trendGlyph[$kpi['trend']]; ?></div>
            <div>
              <span class="status-pill <?php echo htmlspecialchars($kpi['statusClass'], ENT_QUOTES); ?>">
                <?php echo htmlspecialchars($kpi['status'], ENT_QUOTES); ?>
              </span>
            </div>
            <div>
              <button class="edit-button" type="button" aria-label="Edit KPI target"
                onclick="document.getElementById('editKpiRow_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>').style.display='flex'; this.style.display='none';">
                <?php echo $icons['edit']; ?>
              </button>
              <form method="post" id="editKpiRow_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>" style="display:none;align-items:center;gap:6px;">
                <input type="hidden" name="action" value="edit_kpi_target" />
                <input type="hidden" name="kpi_key" value="<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>" />
                <input type="hidden" name="industry" value="<?php echo htmlspecialchars($currentIndustry, ENT_QUOTES); ?>" />
                <input type="number" name="kpi_target" min="1" max="5" step="0.1" value="<?php echo number_format($kpi['target'], 1); ?>" style="width:60px;padding:4px;" />
                <button class="btn-primary" type="submit" style="padding:4px 10px;">Save</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>

        <a class="add-kpi-button" href="#addKpiForm" style="text-decoration:none;display:inline-flex;align-items:center;gap:6px;"><?php echo $icons['plus']; ?> Add New KPI for this Employee</a>
      </div>

      <div class="metrics-panel" id="addKpiForm" style="margin-top:16px;">
        <div class="metrics-panel-header">
          <h3>Add a KPI to the <?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?> Template</h3>
        </div>
        <form method="post" class="form-grid" style="padding:16px;">
          <input type="hidden" name="action" value="add_kpi" />
          <input type="hidden" name="industry" value="<?php echo htmlspecialchars($currentIndustry, ENT_QUOTES); ?>" />
          <div class="form-group">
            <label for="kpi_name">KPI Name</label>
            <input id="kpi_name" name="kpi_name" type="text" required placeholder="e.g. Documentation Quality" />
          </div>
          <div class="form-group">
            <label for="kpi_target">Target Score (1-5)</label>
            <input id="kpi_target" name="kpi_target" type="number" min="1" max="5" step="0.1" value="4.0" required />
          </div>
          <div class="form-actions" style="grid-column:1/-1;">
            <button class="btn-primary" type="submit">Add KPI</button>
          </div>
        </form>
        <p style="padding:0 16px 16px;color:var(--muted);font-size:13px;">
          Applies to every employee on the <?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?> industry template, not just <?php echo htmlspecialchars($selectedEmployeeName, ENT_QUOTES); ?>.
        </p>
      </div>

      <div class="category-cards">
        <?php foreach ($categoryCards as $card): ?>
          <article class="category-card">
            <div class="category-card-top">
              <span class="category-badge <?php echo htmlspecialchars($card['badgeClass'], ENT_QUOTES); ?>">
                <?php echo htmlspecialchars(strtoupper($card['badge']), ENT_QUOTES); ?>
              </span>
              <span class="category-icon"><?php echo $icons[$card['icon']]; ?></span>
            </div>
            <h4 class="category-title"><?php echo htmlspecialchars($card['title'], ENT_QUOTES); ?></h4>
            <div class="category-target-row">
              <span>Target: <?php echo htmlspecialchars($card['target'], ENT_QUOTES); ?></span>
              <strong><?php echo htmlspecialchars($card['current'], ENT_QUOTES); ?></strong>
            </div>
            <div class="category-bar">
              <span
                style="width: <?php echo (int) $card['progress']; ?>%; background: <?php echo htmlspecialchars($card['accentColor'], ENT_QUOTES); ?>;"></span>
            </div>
            <span class="category-status <?php echo htmlspecialchars($card['statusClass'], ENT_QUOTES); ?>">
              <?php echo $icons[$card['statusIcon']]; ?>
              <?php echo htmlspecialchars(strtoupper($card['status']), ENT_QUOTES); ?>
            </span>
          </article>
        <?php endforeach; ?>
      </div>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa employer dashboard prototype</span>
    <span>Powered by PHP &amp; Firebase</span>
  </footer>

  <script src="script.js"></script>
  <script src="kpis.js"></script>
</body>

</html>