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
  'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
  'calendar' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>',
  'plus' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>',
  'chevron-down' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>',
  'download' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  'file' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>',
  'user' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>',
];

$navItems = [
  ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => false, 'icon' => 'home'],
  ['label' => 'Employees', 'href' => 'employees.php', 'active' => false, 'icon' => 'users'],
  ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => false, 'icon' => 'target'],
  ['label' => 'Reports', 'href' => 'reports.php', 'active' => true, 'icon' => 'bar-chart'],
  ['label' => 'Settings', 'href' => 'settings.php', 'active' => false, 'icon' => 'settings'],
];

$reportTypes = [
  'monthly_summary' => 'Monthly Performance Summary',
  'training_audit' => 'Learning & Development Audit',
  'probationary_status' => 'Probationary Status Report',
  'risk_analysis' => 'Underperformance Risk Analysis',
];

// Probationary employees, for the Generate Report picker
$employeesList = [];
try {
  $docs = firestore_list_documents('Users');
  foreach ($docs as $doc) {
    $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
    if (strpos($roleKey, 'probation') !== false) {
      $employeesList[] = [
        'uid' => $doc['uid'] ?? '',
        'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
        'industry' => $doc['industry'] ?? 'retail',
      ];
    }
  }
} catch (Throwable $e) {
  // leave $employeesList empty
}

$genMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate_report') {
  $empUid = $_POST['employee'] ?? '';
  $reportTypeKey = $_POST['report_type'] ?? 'monthly_summary';
  $emp = null;
  foreach ($employeesList as $e) {
    if ($e['uid'] === $empUid) {
      $emp = $e;
      break;
    }
  }
  if (!$emp) {
    $genMessage = 'Select an employee first.';
  } else {
    try {
      $template = kpi_template_for($emp['industry']);
      $allRatings = firestore_list_documents('Ratings');
      $mine = array_filter($allRatings, fn($r) => ($r['employeeUid'] ?? '') === $emp['uid']);
      usort($mine, fn($a, $b) => strcmp($b['ratedAt'] ?? '', $a['ratedAt'] ?? ''));
      $mine = array_values($mine);
      $scores = $mine[0]['scores'] ?? [];

      $reportId = uniqid('report_');
      firestore_write_document('Reports', $reportId, [
        'employeeUid' => $emp['uid'],
        'employeeName' => $emp['name'],
        'reportType' => $reportTypeKey,
        'reportTypeLabel' => $reportTypes[$reportTypeKey] ?? 'Performance Report',
        'industry' => $emp['industry'],
        'templateLabel' => $template['label'],
        'scores' => $scores,
        'generatedAt' => date('c'),
        'generatedBy' => $_SESSION['name'] ?? '',
      ]);
      $genMessage = 'Report generated for ' . htmlspecialchars($emp['name']) . '.';
    } catch (\Throwable $e) {
      $genMessage = 'Failed to generate report: ' . $e->getMessage();
    }
  }
}

// Load real generated reports
$reports = [];
try {
  $reportDocs = firestore_list_documents('Reports');
  usort($reportDocs, fn($a, $b) => strcmp($b['generatedAt'] ?? '', $a['generatedAt'] ?? ''));
  $iconCycle = ['blue', 'orange', 'green', 'red'];
  foreach ($reportDocs as $i => $r) {
    $genDate = !empty($r['generatedAt']) ? date('M j, Y', strtotime($r['generatedAt'])) : '';
    $reports[] = [
      'id' => $r['uid'] ?? '',
      'title' => ($r['employeeName'] ?? 'Unknown') . ' – ' . ($r['reportTypeLabel'] ?? 'Performance Report'),
      'meta' => 'Generated on ' . $genDate,
      'iconClass' => $iconCycle[$i % count($iconCycle)],
    ];
  }
} catch (Throwable $e) {
  // leave $reports empty
}
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
          style="background-image: url('<?php echo "https://ui-avatars.com/api/?name=" . urlencode($profileName) . "&background=2f6df6&color=fff&size=160"; ?>');"></div>
        <div>
          <div class="profile-name"><?php echo htmlspecialchars($profileName, ENT_QUOTES); ?></div>
          <div class="profile-role"><?php echo htmlspecialchars($profileRoleDisplay, ENT_QUOTES); ?></div>
        </div>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <div></div>
        <div class="topbar-actions">
          <button class="icon-button" type="button" aria-label="Notifications"><?php echo $icons['bell']; ?></button>
          <a class="ghost-button" href="../logout.php" aria-label="Sign out">Sign out</a>
        </div>
      </header>

      <div class="page-header">
        <div>
          <h1>Employee Reports</h1>
          <p>Create and manage individual performance assessments</p>
        </div>
      </div>

      <?php if ($genMessage): ?>
        <div style="margin-bottom:16px;padding:12px;border-radius:8px;background:rgba(47,109,246,0.08);color:var(--text);"><?php echo htmlspecialchars($genMessage, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <div class="report-panel">
        <h3>Generate New Report</h3>
        <p>Select an employee and the type of report you wish to generate. Pulls their latest saved KPI ratings.</p>

        <?php if (!$employeesList): ?>
          <p>No probationary employees yet. Add one first from the Employees page.</p>
        <?php else: ?>
          <form method="post" class="report-form-row">
            <div class="form-group">
              <label for="employee">Select Employee</label>
              <div class="employee-select" style="width:100%;">
                <span class="employee-select-icon"><?php echo $icons['user']; ?></span>
                <select id="employee" name="employee"
                  style="border:none;background:transparent;font:inherit;color:inherit;appearance:none;cursor:pointer;width:100%;">
                  <?php foreach ($employeesList as $emp): ?>
                    <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="employee-select-chevron"><?php echo $icons['chevron-down']; ?></span>
              </div>
            </div>
            <div class="form-group">
              <label for="report_type">Report Type</label>
              <div class="employee-select" style="width:100%;">
                <span class="employee-select-icon"><?php echo $icons['file']; ?></span>
                <select id="report_type" name="report_type"
                  style="border:none;background:transparent;font:inherit;color:inherit;appearance:none;cursor:pointer;width:100%;">
                  <?php foreach ($reportTypes as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>"><?php echo htmlspecialchars($label, ENT_QUOTES); ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="employee-select-chevron"><?php echo $icons['chevron-down']; ?></span>
              </div>
            </div>
            <input type="hidden" name="action" value="generate_report" />
            <button class="btn-primary" type="submit"><?php echo $icons['plus']; ?> Generate Report</button>
          </form>
        <?php endif; ?>
      </div>

      <div class="reports-section-header">
        <h3>Generated Reports</h3>
        <span>Total: <?php echo count($reports); ?> reports</span>
      </div>

      <div class="report-list">
        <?php if (!$reports): ?>
          <div class="empty-state">
            <p>No reports generated yet. Use the form above to create one.</p>
          </div>
        <?php endif; ?>
        <?php foreach ($reports as $report): ?>
          <div class="report-item">
            <div class="file-icon <?php echo htmlspecialchars($report['iconClass'], ENT_QUOTES); ?>"><?php echo $icons['file']; ?></div>
            <div class="report-info">
              <div class="report-title"><?php echo htmlspecialchars($report['title'], ENT_QUOTES); ?></div>
              <div class="report-meta"><?php echo htmlspecialchars($report['meta'], ENT_QUOTES); ?></div>
            </div>
            <div class="report-actions">
              <a class="btn-outline" href="report_view.php?id=<?php echo urlencode($report['id']); ?>"><?php echo $icons['file']; ?> View</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa employer dashboard prototype</span>
    <span>Powered by PHP &amp; Firebase</span>
  </footer>

  <script src="script.js"></script>
</body>

</html>