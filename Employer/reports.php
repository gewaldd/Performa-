<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';

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

$reportTypes = [
  'monthly_summary' => 'Monthly Performance Summary',
  'training_audit' => 'Learning & Development Audit',
  'probationary_status' => 'Probationary Status Report',
  'risk_analysis' => 'Underperformance Risk Analysis',
];

/* =========================================================
   FAST DISK-BACKED DATA CACHING (BYPASS FIRESTORE WAITS)
   ========================================================= */
function get_cached_collection($collectionName, $ttlSeconds = 600) {
  $cacheFile = sys_get_temp_dir() . '/performa_' . md5($collectionName) . '.json';
  if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $ttlSeconds)) {
    $data = json_decode(file_get_contents($cacheFile), true);
    if (is_array($data)) return $data;
  }
  
  try {
    $data = firestore_list_documents($collectionName);
    @file_put_contents($cacheFile, json_encode($data), LOCK_EX);
    return $data;
  } catch (Throwable $e) {
    if (file_exists($cacheFile)) {
      $data = json_decode(file_get_contents($cacheFile), true);
      if (is_array($data)) return $data;
    }
    return [];
  }
}

function clear_collection_cache($collectionName) {
  $cacheFile = sys_get_temp_dir() . '/performa_' . md5($collectionName) . '.json';
  if (file_exists($cacheFile)) @unlink($cacheFile);
}

// 1. Instant Probationary Employees List
$employeesList = [];
$docs = get_cached_collection('Users', 600);
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

// 2. Report Generation Action
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
      $allRatings = get_cached_collection('Ratings', 600);
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
      
      // Invalidate cache so newly created report appears immediately
      clear_collection_cache('Reports');
      $genMessage = 'Report generated for ' . htmlspecialchars($emp['name']) . '.';
    } catch (\Throwable $e) {
      $genMessage = 'Failed to generate report: ' . $e->getMessage();
    }
  }
}

// 3. Instant Reports List
$reports = [];
$contributorCounts = [];
$reportDocs = get_cached_collection('Reports', 600);

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
  $contributor = trim((string) ($r['generatedBy'] ?? ''));
  if ($contributor !== '') {
    $contributorCounts[$contributor] = ($contributorCounts[$contributor] ?? 0) + 1;
  }
}

arsort($contributorCounts);
$topContributors = array_slice(array_keys($contributorCounts), 0, 3);
$otherContributorCount = max(0, count($contributorCounts) - count($topContributors));

$currentQuarter = 'Q' . (int) ceil((int) date('n') / 3) . ' ' . date('Y');
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
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('Reports'); ?>

    <main class="main">
      <header class="topbar">
        <div></div>
        <div class="topbar-actions">
          <div class="deadline-pill" style="background: rgba(47, 109, 246, 0.12); color: var(--primary-dark);">
            <span class="deadline-icon"><?php echo $icons['calendar']; ?></span>
            Review Period: <?php echo htmlspecialchars($currentQuarter, ENT_QUOTES); ?>
          </div>
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
        <div class="alert alert-info"><?php echo htmlspecialchars($genMessage, ENT_QUOTES); ?></div>
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
              <div class="employee-select">
                <span class="employee-select-icon"><?php echo $icons['user']; ?></span>
                <select id="employee" class="perform-select employee-select-control" name="employee">
                  <?php foreach ($employeesList as $emp): ?>
                    <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?></option>
                  <?php endforeach; ?>
                </select>
                <span class="employee-select-chevron"><?php echo $icons['chevron-down']; ?></span>
              </div>
            </div>
            <div class="form-group">
              <label for="report_type">Report Type</label>
              <div class="employee-select">
                <span class="employee-select-icon"><?php echo $icons['file']; ?></span>
                <select id="report_type" class="perform-select employee-select-control" name="report_type">
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
              <a class="btn-outline" href="report_view.php?id=<?php echo urlencode($report['id']); ?>&autoprint=1"><?php echo $icons['download']; ?> Download PDF</a>
              <a class="btn-outline" href="report_view.php?id=<?php echo urlencode($report['id']); ?>"><?php echo $icons['file']; ?> View</a>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($topContributors): ?>
        <div class="reports-section-header" style="margin-top: 24px; border-top: 1px solid var(--panel-border); padding-top: 16px;">
          <span style="font-size:12px;font-weight:700;letter-spacing:0.04em;color:var(--muted);text-transform:uppercase;">Top Contributors This Period</span>
          <div style="display:flex;align-items:center;gap:8px;">
            <div style="display:flex;">
              <?php foreach ($topContributors as $index => $name): ?>
                <div class="avatar" style="width:28px;height:28px;border:2px solid #fff;margin-left:<?php echo $index === 0 ? '0' : '-8px'; ?>;background-image: url('https://ui-avatars.com/api/?name=<?php echo urlencode($name); ?>&background=2f6df6&color=fff&size=64');"
                  title="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>"></div>
              <?php endforeach; ?>
            </div>
            <span style="font-size:13px;color:var(--muted);">
              <?php echo htmlspecialchars($topContributors[0], ENT_QUOTES); ?><?php echo $otherContributorCount > 0 ? ' & ' . $otherContributorCount . ' other' . ($otherContributorCount === 1 ? '' : 's') : ''; ?>
            </span>
          </div>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa employer dashboard prototype</span>
    <span>Powered by PHP &amp; Firebase</span>
  </footer>

  <script src="dropdowns.js"></script>
  <script src="script.js"></script>
</body>

</html>