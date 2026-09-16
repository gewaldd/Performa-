<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/../audit_log.php';

require_login();
require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  header('Content-Type: application/json; charset=utf-8');

  $probationPeriodDays = filter_input(INPUT_POST, 'probationPeriodDays', FILTER_VALIDATE_INT);
  $alertAt1 = filter_input(INPUT_POST, 'alertAt1', FILTER_VALIDATE_INT);
  $alertAt2 = filter_input(INPUT_POST, 'alertAt2', FILTER_VALIDATE_INT);
  $alertAt3 = filter_input(INPUT_POST, 'alertAt3', FILTER_VALIDATE_INT);

  if ($probationPeriodDays === false || $alertAt1 === false || $alertAt2 === false || $alertAt3 === false
    || $probationPeriodDays <= 0 || $alertAt1 <= 0 || $alertAt2 <= 0 || $alertAt3 <= 0
    || $alertAt1 >= $alertAt2 || $alertAt2 >= $alertAt3 || $alertAt3 > $probationPeriodDays) {
    http_response_code(422);
    echo json_encode(['error' => 'Enter valid alert days in ascending order within the probation period.']);
    exit;
  }

  try {
    firestore_write_document('systemSettings', 'deadlineTracker', [
      'probationPeriodDays' => $probationPeriodDays,
      'alertAt1' => $alertAt1,
      'alertAt2' => $alertAt2,
      'alertAt3' => $alertAt3,
      'updatedAt' => date('c'),
    ]);
    record_audit_event(
      'System settings updated',
      sprintf('%s updated the probation deadline and alert thresholds.', $_SESSION['name'] ?? 'System Admin'),
      [
        'probationPeriodDays' => $probationPeriodDays,
        'alertAt1' => $alertAt1,
        'alertAt2' => $alertAt2,
        'alertAt3' => $alertAt3,
      ]
    );
    echo json_encode(['success' => true]);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
  }
  exit;
}

$navItems = [
  ['label' => 'Dashboard', 'href' => 'admin_dashboard.php', 'active' => false],
  ['label' => 'User Accounts', 'href' => 'accounts.php', 'active' => false],
  ['label' => 'System Settings', 'href' => 'settings.php', 'active' => true],
];

$deadlineSettings = [
  'probationPeriodDays' => 180,
  'alertAt1' => 150,
  'alertAt2' => 165,
  'alertAt3' => 178,
];
try {
  $storedDeadlineSettings = firestore_get_document('systemSettings', 'deadlineTracker');
  if (is_array($storedDeadlineSettings)) {
    $deadlineSettings = array_merge($deadlineSettings, $storedDeadlineSettings);
  }
} catch (Throwable $e) {
  // Keep the documented defaults when Firestore is unavailable.
}

$kpiTemplateLibrary = [];
try {
  foreach (firestore_list_documents('kpiTemplates') as $template) {
    $kpis = $template['kpis'] ?? [];
    $kpiTemplateLibrary[] = [
      'industry' => $template['label'] ?? $template['industry'] ?? 'Unnamed',
      'kpiCount' => is_array($kpis) ? count($kpis) : (int) ($template['kpiCount'] ?? 0),
    ];
  }
} catch (Throwable $e) {
  // Leave the library empty when Firestore is unavailable.
}
if (!$kpiTemplateLibrary) {
  foreach (kpi_templates() as $template) {
    $kpiCount = count($template['kpis']);
    $industryKey = strtolower(str_replace(' ', '_', $template['label']));
    try {
      $customTemplate = firestore_get_document('CustomKpis', $industryKey);
      if (!empty($customTemplate['kpis']) && is_array($customTemplate['kpis'])) {
        $kpiCount += count($customTemplate['kpis']);
      }
    } catch (Throwable $e) {
      // Keep the built-in KPI count when custom KPI data is unavailable.
    }
    $kpiTemplateLibrary[] = [
      'industry' => $template['label'],
      'kpiCount' => $kpiCount,
    ];
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | System Settings</title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="../ui-refresh.css" />
  <style>
    .settings-form {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
      padding: 0 18px 18px;
    }

    .settings-form .form-row {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .settings-form label {
      font-size: 12.5px;
      font-weight: 600;
      color: var(--text);
    }

    .settings-form input {
      border: 1px solid var(--panel-border);
      border-radius: var(--radius-sm);
      padding: 11px 14px;
      font-size: 14px;
      font-family: inherit;
      background: #fbfcfe;
      color: var(--text);
    }

    .settings-form .full-width {
      grid-column: 1 / -1;
    }

    .settings-note {
      font-size: 12.5px;
      color: var(--muted);
      margin: 0 0 14px;
      padding: 0 18px;
    }
  </style>
</head>

<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div>
        <div class="brand">
          <div class="brand-mark">P</div>
          <div>
            <div class="brand-name">Performa</div>
            <div class="brand-subtitle">Admin Dashboard</div>
          </div>
        </div>

        <nav class="nav" aria-label="Primary">
          <?php foreach ($navItems as $item): ?>
            <a class="nav-item<?php echo $item['active'] ? ' active' : ''; ?>"
              href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>">
              <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <div class="sidebar-footer">
        <div class="profile-avatar">AD</div>
        <div>
          <div class="profile-name">System Admin</div>
          <div class="profile-role">Administrator</div>
        </div>
      </div>
    </aside>

    <main class="main">
      <section class="hero">
        <p class="eyebrow">System Settings</p>
        <h1>Configure system-wide thresholds and view the KPI template library.</h1>
      </section>

      <section class="panel" style="padding-top:18px;">
        <div class="panel-header">
          <div>
            <h2>Probationary Deadline Tracker</h2>
            <p>Global thresholds used to trigger deadline alerts across all Employers.</p>
          </div>
        </div>
        <form class="settings-form" id="deadlineSettingsForm">
          <div class="form-row">
            <label for="probationPeriodDays">Probation Period (days)</label>
            <input type="number" id="probationPeriodDays" name="probationPeriodDays"
              value="<?php echo htmlspecialchars($deadlineSettings['probationPeriodDays'], ENT_QUOTES); ?>" required />
          </div>
          <div class="form-row">
            <label for="alertAt1">First Alert (day)</label>
            <input type="number" id="alertAt1" name="alertAt1"
              value="<?php echo htmlspecialchars($deadlineSettings['alertAt1'], ENT_QUOTES); ?>" required />
          </div>
          <div class="form-row">
            <label for="alertAt2">Second Alert (day)</label>
            <input type="number" id="alertAt2" name="alertAt2"
              value="<?php echo htmlspecialchars($deadlineSettings['alertAt2'], ENT_QUOTES); ?>" required />
          </div>
          <div class="form-row">
            <label for="alertAt3">Final Alert (day)</label>
            <input type="number" id="alertAt3" name="alertAt3"
              value="<?php echo htmlspecialchars($deadlineSettings['alertAt3'], ENT_QUOTES); ?>" required />
          </div>
          <div class="full-width">
            <button class="primary-button" type="submit">Save Settings</button>
          </div>
        </form>
      </section>

      <section class="panel" style="padding-top:18px; margin-top:16px;">
        <div class="panel-header">
          <div>
            <h2>KPI Template Library</h2>
            <p>Read-only view of the pre-built industry KPI templates. Editing happens in the Employer's KPI
              Configuration page.</p>
          </div>
        </div>
        <div class="table-wrap">
          <div class="table-head" style="grid-template-columns: 1fr 1fr;">
            <span>Industry</span><span>KPIs Defined</span>
          </div>
          <?php foreach ($kpiTemplateLibrary as $template): ?>
            <div class="table-row" style="grid-template-columns: 1fr 1fr;">
              <div class="employee-name"><?php echo htmlspecialchars($template['industry'], ENT_QUOTES); ?></div>
              <div class="timeline-text"><?php echo htmlspecialchars($template['kpiCount'], ENT_QUOTES); ?> KPIs</div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa admin dashboard prototype</span>
    <span>System-level access · Account &amp; configuration management</span>
  </footer>

  <script src="script.js"></script>
  <script type="module" src="settings-script.js"></script>
</body>

</html>