<?php
$rootDir = __DIR__ . '/..';
require_once $rootDir . '/auth.php';
require_once $rootDir . '/firebase_init.php';
require_once $rootDir . '/kpi_templates.php';
require_once __DIR__ . '/employer_layout.php';

require_login();
require_role('employer');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (empty($_SESSION['uid'])) {
  header('Location: ../login.php');
  exit;
}

// Load probationary employees for the picker
$employees = [];
try {
  $docs = firestore_list_documents('Users');
  foreach ($docs as $doc) {
    $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
    if (strpos($roleKey, 'probation') !== false) {
      $employees[] = [
        'uid' => $doc['uid'] ?? '',
        'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
        'industry' => $doc['industry'] ?? 'retail',
      ];
    }
  }
} catch (Throwable $e) {
  // leave $employees empty
}

$selectedUid = $_GET['employee'] ?? ($_POST['employee'] ?? '');
$selectedEmployee = null;
foreach ($employees as $emp) {
  if ($emp['uid'] === $selectedUid) {
    $selectedEmployee = $emp;
    break;
  }
}
if (!$selectedEmployee && $employees) {
  $selectedEmployee = $employees[0];
  $selectedUid = $selectedEmployee['uid'];
}

$template = $selectedEmployee ? kpi_template_for($selectedEmployee['industry']) : kpi_template_for('retail');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedEmployee) {
  $weekOf = date('Y-\WW');
  $scores = [];
  foreach ($template['kpis'] as $kpi) {
    $raw = $_POST['score_' . $kpi['key']] ?? null;
    $scores[$kpi['key']] = $raw !== null ? (float) $raw : 0.0;
  }
  $docId = $selectedUid . '_' . date('Y-m-d');
  try {
    firestore_write_document('Ratings', $docId, [
      'employeeUid' => $selectedUid,
      'employeeName' => $selectedEmployee['name'],
      'industry' => $selectedEmployee['industry'],
      'weekOf' => $weekOf,
      'ratedAt' => date('c'),
      'ratedBy' => $_SESSION['uid'],
      'scores' => $scores,
    ]);
    firestore_write_document('Acknowledgements', $selectedUid . '_' . date('Y-m'), [
      'employeeUid' => $selectedUid,
      'month' => date('F Y'),
      'status' => 'Pending',
      'timestamp' => null,
      'createdAt' => date('c'),
    ]);
    firestore_write_document('notifications', $selectedUid . '_' . date('Y-m') . '_summary', [
      'employeeUid' => $selectedUid,
      'title' => 'Performance summary ready',
      'detail' => 'Your ' . date('F Y') . ' performance summary is available for acknowledgement.',
      'type' => 'info',
      'createdAt' => date('c'),
    ]);
    firestore_write_document('Feedback', $selectedUid . '_' . date('Y-m') . '_employer', [
      'employeeUid' => $selectedUid,
      'sender' => $_SESSION['name'] ?? 'Employer',
      'role' => 'Employer',
      'message' => 'Your ' . date('F Y') . ' KPI rating has been submitted. Review your performance summary and acknowledgement.',
      'status' => 'Received',
      'createdAt' => date('c'),
    ]);
    $message = 'Rating saved for ' . htmlspecialchars($selectedEmployee['name']) . '.';
  } catch (\Throwable $e) {
    $message = 'Failed to save rating: ' . $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php employer_brand_head(); ?>
  <meta name="description" content="Score this week's KPIs for a probationary employee." />
  <title>Rate Employee · Performa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('KPIs'); ?>
    <main class="main content-narrow">
      <div class="page-header">
        <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
        </button>
        <div class="ph-main">
          <a href="kpis.php" class="ghost-button back-link">&larr; Back to KPIs</a>
          <nav class="ph-crumb" aria-label="Breadcrumb">
            <span>KPIs</span>
            <span aria-hidden="true">/</span>
            <span>Rate</span>
          </nav>
          <h1>Weekly Performance Rating</h1>
          <p>Score this week's KPIs for a probationary employee. Use the slider or type a value from 1.0 to 5.0.</p>
        </div>
      </div>

      <div class="settings-panel">
        <?php if ($message): ?>
          <div class="alert alert-info" role="status"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!$employees): ?>
          <p>No probationary employees yet. Add one first from the Employees page.</p>
        <?php else: ?>
          <form method="get" class="form-grid single-field-grid" style="margin-bottom:8px;">
            <div class="form-group">
              <label for="employee">Employee</label>
              <select id="employee" class="perform-select" name="employee" onchange="this.form.submit()">
                <?php foreach ($employees as $emp): ?>
                  <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>" <?php echo $emp['uid'] === $selectedUid ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </form>

          <hr class="section-divider" />

          <form method="post">
            <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selectedUid, ENT_QUOTES); ?>" />
            <p class="microcopy" style="margin:0 0 12px;">Industry template: <strong><?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?></strong></p>
            <div class="pf-rate-grid">
              <?php foreach ($template['kpis'] as $kpi): ?>
                <div class="pf-rate-row">
                  <label for="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>
                    <span class="microcopy"> · target <?php echo number_format((float) $kpi['target'], 1); ?></span>
                  </label>
                  <div class="pf-rate-controls">
                    <input type="range" min="1" max="5" step="0.1" value="3.0" data-rate-slider="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>"
                      aria-label="<?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?> slider" />
                    <input id="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>" name="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>" class="pf-rate-value" type="number" min="1"
                      max="5" step="0.1" value="3.0" required />
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="form-actions">
              <a class="btn-cancel" href="kpis.php">Cancel</a>
              <button class="btn-primary" type="submit">Save Rating</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
  <script>
    // Slider <-> number sync (presentation only; submitted name stays score_*).
    document.querySelectorAll('[data-rate-slider]').forEach(function (slider) {
      var target = document.getElementById(slider.getAttribute('data-rate-slider'));
      if (!target) return;
      slider.addEventListener('input', function () { target.value = slider.value; });
      target.addEventListener('input', function () {
        var v = parseFloat(target.value);
        if (!isNaN(v)) slider.value = Math.max(1, Math.min(5, v));
      });
    });
  </script>
</body>

</html>