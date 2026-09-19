<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';

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
  require_employer_owns_user($existing + ['uid' => $uid], 'employee_view:save_profile');
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
  require_employer_owns_user($existing + ['uid' => $uid], 'employee_view:toggle_status');
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
  require_employer_owns_user($existing + ['uid' => $uid], 'employee_view:save_regularization');
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_feedback') {
  $feedbackMessage = trim((string) ($_POST['feedbackMessage'] ?? ''));
  if ($feedbackMessage === '') {
    $message = 'Please enter feedback before saving.';
    $messageTone = 'error';
  } else {
    try {
      $target = firestore_get_document('Users', $uid) ?? [];
      require_employer_owns_user($target + ['uid' => $uid], 'employee_view:save_feedback');
      firestore_write_document('Feedback', $uid . '_' . time(), [
        'employeeUid' => $uid,
        'sender' => $_SESSION['name'] ?? 'Employer',
        'role' => 'Employer',
        'message' => $feedbackMessage,
        'status' => 'Received',
        'createdAt' => date('c'),
      ]);
      $message = 'Feedback shared with the probationary employee.';
      $messageTone = 'success';
    } catch (Throwable $e) {
      $message = 'Failed to save feedback: ' . $e->getMessage();
      $messageTone = 'error';
    }
  }
}

$profile = firestore_get_document('Users', $uid);
if (!$profile) {
  header('Location: employees.php');
  exit;
}
require_employer_owns_user($profile + ['uid' => $uid], 'employee_view:read');

$roleKey = normalize_role_key($profile['role'] ?? null);
$isProbationary = $roleKey === 'probationary';
$createdAt = $profile['createdAt'] ?? '';
$createdTime = $createdAt ? strtotime($createdAt) : false;
$probationPeriodDays = max(1, (int) ($profile['probationPeriodDays'] ?? 180));
$daysSince = $createdTime ? max(1, (int) floor((time() - $createdTime) / 86400)) : 0;
$daysLeft = $createdTime ? max(0, $probationPeriodDays - $daysSince) : 0;

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
  <?php employer_brand_head(); ?>
  <meta name="description" content="View and manage an employee profile, probation progress, and regularization decision." />
  <title><?php echo htmlspecialchars($profile['name'] ?? 'Employee', ENT_QUOTES); ?> · Performa</title>
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
    <?php employer_render_shell('Employees'); ?>
    <main class="main content-narrow">
      <div class="page-header">
        <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
          <?php echo employer_icon('menu'); ?>
        </button>
        <div class="ph-main">
          <a href="employees.php" class="ghost-button back-link">&larr; Back to Employees</a>
          <nav class="ph-crumb" aria-label="Breadcrumb">
            <span>Employees</span>
            <span aria-hidden="true">/</span>
            <span><?php echo htmlspecialchars($profile['name'] ?? 'Employee', ENT_QUOTES); ?></span>
          </nav>
          <h1><?php echo htmlspecialchars($profile['name'] ?? 'Employee', ENT_QUOTES); ?></h1>
          <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $profile['role'] ?? '')), ENT_QUOTES); ?>
            &middot; <span class="status-pill <?php echo $statusLabel === 'Disabled' ? 'status-danger' : 'status-good'; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?></span>
          </p>
        </div>
        <?php if ($isProbationary): ?>
          <div class="ph-actions">
            <a class="ghost-button" href="rate_employee.php?employee=<?php echo urlencode($uid); ?>">Rate KPIs</a>
            <a class="ghost-button" href="kpis.php?employee=<?php echo urlencode($uid); ?>">View KPI Dashboard</a>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($message): ?>
        <div class="alert <?php echo $messageTone === 'error' ? 'alert-error' : ($messageTone === 'success' ? 'alert-success' : 'alert-info'); ?>" role="<?php echo $messageTone === 'error' ? 'alert' : 'status'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <div class="settings-panel">
        <h4 class="settings-subhead">Profile</h4>
        <form method="post">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save_profile" />
          <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid, ENT_QUOTES); ?>" />
          <div class="form-grid">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input id="name" name="name" type="text"
                value="<?php echo htmlspecialchars($profile['name'] ?? '', ENT_QUOTES); ?>" required />
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input id="email" name="email" type="email"
                value="<?php echo htmlspecialchars($profile['email'] ?? '', ENT_QUOTES); ?>" required />
            </div>
            <div class="form-group">
              <label for="department">Department</label>
              <input id="department" name="department" type="text"
                value="<?php echo htmlspecialchars(pf_dept_label($profile['department'] ?? ''), ENT_QUOTES); ?>" />
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
        <p class="microcopy">
          <?php echo $statusLabel === 'Disabled' ? 'This account is disabled and cannot sign in.' : 'This account can sign in normally.'; ?>
        </p>
        <form method="post"
          data-confirm="<?php echo htmlspecialchars($statusLabel === 'Disabled' ? 'Reactivate this account?' : 'Deactivate ' . ($profile['name'] ?? 'this account') . '? They will be signed out immediately and unable to log in.', ENT_QUOTES); ?>"<?php echo $statusLabel === 'Disabled' ? '' : ' data-confirm-danger'; ?>>
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="toggle_status" />
          <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid, ENT_QUOTES); ?>" />
          <button class="<?php echo $statusLabel === 'Disabled' ? 'ghost-button' : 'btn-danger'; ?>"
            type="submit"><?php echo $statusLabel === 'Disabled' ? 'Reactivate Account' : 'Deactivate Account'; ?></button>
        </form>
      </div>

      <?php if ($isProbationary): ?>
        <div class="settings-panel">
          <h4 class="settings-subhead">Probation Progress</h4>
          <p>Day <?php echo $daysSince; ?> of <?php echo $probationPeriodDays; ?> &middot; <?php echo $daysLeft; ?> days remaining</p>

          <h4 class="settings-subhead">Latest KPI Scores (<?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?>
            template)</h4>
          <?php if (!$summary['hasData']): ?>
            <p>No ratings submitted yet.</p>
          <?php else: ?>
            <p>Average score: <strong><?php echo number_format($summary['score'], 1); ?></strong> / 5.0 (template target avg
              <?php echo number_format($summary['targetAvg'], 1); ?>)
              &middot; <?php echo $summary['ratingCount']; ?> rating<?php echo $summary['ratingCount'] === 1 ? '' : 's'; ?>
              on file
            </p>
          <?php endif; ?>

          <hr class="section-divider" />

          <h4 class="settings-subhead">Employer Feedback</h4>
          <p class="microcopy">Share feedback that will appear on the employee's Feedback page.</p>
          <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_feedback" />
            <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid, ENT_QUOTES); ?>" />
            <div class="form-group">
              <label for="feedbackMessage">Feedback</label>
              <textarea id="feedbackMessage" name="feedbackMessage" rows="4"
                placeholder="Write feedback for this employee..." required></textarea>
            </div>
            <div class="form-actions">
              <button class="btn-primary" type="submit">Share Feedback</button>
            </div>
          </form>

          <hr class="section-divider" />

          <h4 class="settings-subhead">Regularization Recommendation</h4>
          <?php if (!empty($profile['regularizationRecommendation'])): ?>
            <p class="microcopy">
              Current decision: <strong><?php echo $profile['regularizationRecommendation'] === 'recommended' ? 'Recommended for Regularization' : 'Not Yet Recommended'; ?></strong>
              <?php if (!empty($profile['regularizationDecidedAt'])): ?> &middot;
                <?php echo htmlspecialchars(pf_date($profile['regularizationDecidedAt'], ''), ENT_QUOTES); ?>     <?php endif; ?>
              <?php if (!empty($profile['regularizationDecidedBy'])): ?> by
                <?php echo htmlspecialchars($profile['regularizationDecidedBy'], ENT_QUOTES); ?>     <?php endif; ?>
            </p>
            <?php if (!empty($profile['regularizationNotes'])): ?>
              <p class="microcopy">Notes:
                <?php echo htmlspecialchars($profile['regularizationNotes'], ENT_QUOTES); ?>
              </p>
            <?php endif; ?>
          <?php endif; ?>

          <form method="post">
            <?php echo csrf_field(); ?>
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
                <input id="notes" name="notes" type="text"
                  value="<?php echo htmlspecialchars($profile['regularizationNotes'] ?? '', ENT_QUOTES); ?>"
                  placeholder="Optional rationale" />
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
  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
</body>

</html>