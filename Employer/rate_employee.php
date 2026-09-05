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
  <title>Performa | Rate Employee</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
</head>

<body>
  <div class="app-shell">
    <main class="main" style="max-width:640px;margin:0 auto;">
      <div class="page-header">
        <div>
          <a href="kpis.php" class="ghost-button" style="display:inline-flex;margin-bottom:12px;">&larr; Back to KPIs</a>
          <h1>Weekly Performance Rating</h1>
          <p>Score this week's KPIs for a probationary employee.</p>
        </div>
      </div>

      <div class="settings-panel">
        <?php if ($message): ?>
          <div style="margin-bottom:16px;padding:12px;border-radius:8px;background:rgba(47,109,246,0.08);color:var(--text);"><?php echo $message; ?></div>
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
            <p style="color:var(--muted);margin-bottom:12px;">Industry template: <strong style="color:var(--text);"><?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?></strong></p>
            <div class="form-grid">
              <?php foreach ($template['kpis'] as $kpi): ?>
                <div class="form-group">
                  <label for="score_<?php echo $kpi['key']; ?>">
                    <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?> (target <?php echo number_format($kpi['target'], 1); ?>)
                  </label>
                  <input id="score_<?php echo $kpi['key']; ?>" name="score_<?php echo $kpi['key']; ?>" type="number"
                    min="1" max="5" step="0.1" required />
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
  <script src="dropdowns.js"></script>
</body>

</html>