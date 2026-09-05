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

$uid = $_GET['uid'] ?? ($_POST['uid'] ?? '');
if (!$uid) {
  header('Location: employees.php');
  exit;
}

$message = '';
$messageTone = 'info';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_profile') {
  $existing = firestore_get_document('Users', $uid) ?? [];
  $existing['name'] = trim($_POST['name'] ?? ($existing['name'] ?? ''));
  $existing['email'] = trim($_POST['email'] ?? ($existing['email'] ?? ''));
  $existing['department'] = trim($_POST['department'] ?? ($existing['department'] ?? ''));
  if (isset($_POST['industry'])) {
    $existing['industry'] = trim($_POST['industry']);
  }
  try {
    firestore_write_document('Users', $uid, $existing);
    $message = 'Profile updated.';
    $messageTone = 'success';
  } catch (\Throwable $e) {
    $message = 'Failed to save: ' . $e->getMessage();
    $messageTone = 'error';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_status') {
  $existing = firestore_get_document('Users', $uid) ?? [];
  $currentlyDisabled = ($existing['status'] ?? 'Active') === 'Disabled';
  try {
    identitytoolkit_disable_user($uid, !$currentlyDisabled);
    $existing['status'] = $currentlyDisabled ? 'Active' : 'Disabled';
    firestore_write_document('Users', $uid, $existing);
    $message = $currentlyDisabled ? 'Account reactivated.' : 'Account deactivated.';
    $messageTone = 'success';
  } catch (\Throwable $e) {
    $message = 'Failed to update account status: ' . $e->getMessage();
    $messageTone = 'error';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_regularization') {
  $existing = firestore_get_document('Users', $uid) ?? [];
  $existing['regularizationRecommendation'] = $_POST['recommendation'] ?? '';
  $existing['regularizationNotes'] = trim($_POST['notes'] ?? '');
  $existing['regularizationDecidedAt'] = date('c');
  $existing['regularizationDecidedBy'] = $_SESSION['name'] ?? '';
  try {
    firestore_write_document('Users', $uid, $existing);
    $message = 'Regularization decision saved.';
    $messageTone = 'success';
  } catch (\Throwable $e) {
    $message = 'Failed to save decision: ' . $e->getMessage();
    $messageTone = 'error';
  }
}

$profile = firestore_get_document('Users', $uid);
if (!$profile) {
  header('Location: employees.php');
  exit;
}

$roleKey = normalize_role_key($profile['role'] ?? null);
$isProbationary = $roleKey === 'probationary';
$createdAt = $profile['createdAt'] ?? '';
$createdTime = $createdAt ? strtotime($createdAt) : false;
$daysSince = $createdTime ? max(1, (int) floor((time() - $createdTime) / 86400)) : 0;
$daysLeft = $createdTime ? max(0, 180 - $daysSince) : 0;

$allRatings = [];
try {
  $allRatings = firestore_list_documents('Ratings');
} catch (Throwable $e) {
}
$ratingHistory = ratings_for_employee($allRatings, $uid);
$template = kpi_template_for($profile['industry'] ?? 'retail');
$summary = employee_kpi_summary($allRatings, $uid, $profile['industry'] ?? 'retail');

$statusLabel = ($profile['status'] ?? 'Active') === 'Disabled' ? 'Disabled' : 'Active';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | <?php echo htmlspecialchars($profile['name'] ?? 'Employee', ENT_QUOTES); ?></title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
</head>

<body>
  <div class="app-shell">
    <main class="main" style="max-width:760px;margin:0 auto;">
      <div class="page-header">
        <div>
          <a href="employees.php" class="ghost-button" style="display:inline-flex;margin-bottom:12px;">&larr; Back to Employees</a>
          <h1><?php echo htmlspecialchars($profile['name'] ?? 'Employee', ENT_QUOTES); ?></h1>
          <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $profile['role'] ?? '')), ENT_QUOTES); ?>
            &middot; Status:
            <strong style="color:<?php echo $statusLabel === 'Disabled' ? '#ed5b57' : '#16a76d'; ?>;"><?php echo $statusLabel; ?></strong>
          </p>
        </div>
        <?php if ($isProbationary): ?>
          <div style="display:flex;gap:8px;">
            <a class="ghost-button" href="rate_employee.php?employee=<?php echo urlencode($uid); ?>">Rate KPIs</a>
            <a class="ghost-button" href="kpis.php?employee=<?php echo urlencode($uid); ?>">View KPI Dashboard</a>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($message): ?>
        <div style="margin-bottom:16px;padding:12px;border-radius:8px;
          background:<?php echo $messageTone === 'error' ? 'rgba(237,91,87,0.1)' : ($messageTone === 'success' ? 'rgba(22,167,109,0.1)' : 'rgba(47,109,246,0.08)'); ?>;
          color:var(--text);"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <div class="settings-panel">
        <h4 class="settings-subhead">Profile</h4>
        <form method="post">
          <input type="hidden" name="action" value="save_profile" />
          <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid, ENT_QUOTES); ?>" />
          <div class="form-grid">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($profile['name'] ?? '', ENT_QUOTES); ?>" required />
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($profile['email'] ?? '', ENT_QUOTES); ?>" required />
            </div>
            <div class="form-group">
              <label for="department">Department</label>
              <input id="department" name="department" type="text" value="<?php echo htmlspecialchars($profile['department'] ?? '', ENT_QUOTES); ?>" />
            </div>
            <?php if ($isProbationary): ?>
              <div class="form-group">
                <label for="industry">Industry (KPI template)</label>
                <select id="industry" class="perform-select" name="industry">
                  <?php foreach (kpi_templates() as $key => $tpl): ?>
                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>" <?php echo ($profile['industry'] ?? 'retail') === $key ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($tpl['label'], ENT_QUOTES); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Save Changes</button>
          </div>
        </form>

        <hr class="section-divider" />

        <h4 class="settings-subhead">Account Status</h4>
        <p style="color:var(--muted);">
          <?php echo $statusLabel === 'Disabled' ? 'This account is disabled and cannot sign in.' : 'This account can sign in normally.'; ?>
        </p>
        <form method="post" data-confirm="<?php echo htmlspecialchars($statusLabel === 'Disabled' ? 'Reactivate this account?' : 'Deactivate this account? They will be signed out and unable to log in.', ENT_QUOTES); ?>">
          <input type="hidden" name="action" value="toggle_status" />
          <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid, ENT_QUOTES); ?>" />
          <button class="btn-cancel" type="submit"><?php echo $statusLabel === 'Disabled' ? 'Reactivate Account' : 'Deactivate Account'; ?></button>
        </form>
      </div>

      <?php if ($isProbationary): ?>
        <div class="settings-panel">
          <h4 class="settings-subhead">Probation Progress</h4>
          <p>Day <?php echo $daysSince; ?> of 180 &middot; <?php echo $daysLeft; ?> days remaining</p>

          <h4 class="settings-subhead">Latest KPI Scores (<?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?> template)</h4>
          <?php if (!$summary['hasData']): ?>
            <p>No ratings submitted yet.</p>
          <?php else: ?>
            <p>Average score: <strong><?php echo number_format($summary['score'], 1); ?></strong> / 5.0 (template target avg <?php echo number_format($summary['targetAvg'], 1); ?>)
              &middot; <?php echo $summary['ratingCount']; ?> rating<?php echo $summary['ratingCount'] === 1 ? '' : 's'; ?> on file</p>
          <?php endif; ?>

          <hr class="section-divider" />

          <h4 class="settings-subhead">Regularization Recommendation</h4>
          <?php if (!empty($profile['regularizationRecommendation'])): ?>
            <p style="color:var(--muted);">
              Current decision: <strong style="color:var(--text);"><?php echo $profile['regularizationRecommendation'] === 'recommended' ? 'Recommended for Regularization' : 'Not Yet Recommended'; ?></strong>
              <?php if (!empty($profile['regularizationDecidedAt'])): ?> &middot; <?php echo date('M j, Y', strtotime($profile['regularizationDecidedAt'])); ?><?php endif; ?>
              <?php if (!empty($profile['regularizationDecidedBy'])): ?> by <?php echo htmlspecialchars($profile['regularizationDecidedBy'], ENT_QUOTES); ?><?php endif; ?>
            </p>
            <?php if (!empty($profile['regularizationNotes'])): ?>
              <p style="color:var(--muted);">Notes: <?php echo htmlspecialchars($profile['regularizationNotes'], ENT_QUOTES); ?></p>
            <?php endif; ?>
          <?php endif; ?>

          <form method="post">
            <input type="hidden" name="action" value="save_regularization" />
            <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid, ENT_QUOTES); ?>" />
            <div class="form-grid">
              <div class="form-group">
                <label for="recommendation">Decision</label>
                <select id="recommendation" class="perform-select" name="recommendation" required>
                  <option value="">Select decision</option>
                  <option value="recommended" <?php echo ($profile['regularizationRecommendation'] ?? '') === 'recommended' ? 'selected' : ''; ?>>Recommend for Regularization</option>
                  <option value="not_recommended" <?php echo ($profile['regularizationRecommendation'] ?? '') === 'not_recommended' ? 'selected' : ''; ?>>Not Yet Recommended</option>
                </select>
              </div>
              <div class="form-group">
                <label for="notes">Notes</label>
                <input id="notes" name="notes" type="text" value="<?php echo htmlspecialchars($profile['regularizationNotes'] ?? '', ENT_QUOTES); ?>" placeholder="Optional rationale" />
              </div>
            </div>
            <div class="form-actions">
              <button class="btn-primary" type="submit">Save Decision</button>
            </div>
          </form>
        </div>
      <?php endif; ?>
    </main>
  </div>
  <script src="dropdowns.js"></script>
  <script src="script.js"></script>
</body>

</html>