<?php
$rootDir = __DIR__ . '/..';
require_once $rootDir . '/auth.php';
require_once $rootDir . '/firebase_init.php';
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

$profileName = $_SESSION['name'] ?? 'Unknown User';
$profileRole = $_SESSION['role'] ?? 'Employer';
$profileRoleDisplay = ucwords(str_replace('_', ' ', $profileRole));
$profileEmail = $_SESSION['email'] ?? '';
$profileDepartment = $_SESSION['department'] ?? '';

$message = '';
$messageTone = 'info';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_profile') {
  $newName = trim($_POST['fullName'] ?? '');
  $newEmail = trim($_POST['email'] ?? '');
  $newDepartment = trim($_POST['department'] ?? '');

  if (!$newName || !$newEmail) {
    $message = 'Full name and email are required.';
    $messageTone = 'error';
  } else {
    try {
      $existing = firestore_get_document('Users', $_SESSION['uid']) ?? [];
      $existing['name'] = $newName;
      $existing['email'] = $newEmail;
      $existing['role'] = $profileRole;
      $existing['department'] = $newDepartment;
      firestore_write_document('Users', $_SESSION['uid'], $existing);
      $_SESSION['name'] = $newName;
      $_SESSION['email'] = $newEmail;
      $_SESSION['department'] = $newDepartment;
      $profileName = $newName;
      $profileEmail = $newEmail;
      $profileDepartment = $newDepartment;
      $message = 'Profile updated.';
      $messageTone = 'success';
    } catch (\Throwable $e) {
      $message = 'Failed to save: ' . $e->getMessage();
      $messageTone = 'error';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_password') {
  $newPassword = $_POST['newPassword'] ?? '';
  $confirmPassword = $_POST['confirmPassword'] ?? '';
  if (strlen($newPassword) < 8) {
    $message = 'Password must be at least 8 characters.';
    $messageTone = 'error';
  } elseif ($newPassword !== $confirmPassword) {
    $message = 'Passwords do not match.';
    $messageTone = 'error';
  } else {
    try {
      identitytoolkit_update_password($_SESSION['uid'], $newPassword);
      $message = 'Password updated.';
      $messageTone = 'success';
    } catch (\Throwable $e) {
      $message = 'Failed to update password: ' . $e->getMessage();
      $messageTone = 'error';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'deactivate_account') {
  try {
    identitytoolkit_disable_user($_SESSION['uid'], true);
    logout();
    header('Location: ../login.php?deactivated=1');
    exit;
  } catch (\Throwable $e) {
    $message = 'Failed to deactivate account: ' . $e->getMessage();
    $messageTone = 'error';
  }
}

$icons = [
  'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'target' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
  'bar-chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
  'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
  'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
  'camera' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/><circle cx="12" cy="13" r="4"/></svg>',
  'lock' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>',
  'shield' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
  'trash' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>',
];

?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | Settings</title>
  <meta name="description" content="Manage your account preferences and system configurations." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('Settings'); ?>

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
          <h1>Settings</h1>
          <p>Manage your account preferences and system configurations</p>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageTone, ENT_QUOTES); ?>"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <div class="settings-panel">
        <div class="profile-photo-row">
          <div class="profile-photo-frame">
            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($profileName); ?>&background=2f6df6&color=fff&size=200"
              alt="Profile photo" />
          </div>
          <div class="profile-photo-info">
            <h3>Profile Photo</h3>
            <p>Generated automatically from your name. Custom photo uploads aren't wired up yet.</p>
          </div>
        </div>

        <form method="post">
          <input type="hidden" name="action" value="save_profile" />
          <div class="form-grid">
            <div class="form-group">
              <label for="fullName">Full Name</label>
              <input id="fullName" name="fullName" type="text" value="<?php echo htmlspecialchars($profileName, ENT_QUOTES); ?>" required />
            </div>
            <div class="form-group">
              <label for="email">Email Address</label>
              <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($profileEmail, ENT_QUOTES); ?>" required />
            </div>
            <div class="form-group locked">
              <label for="role">Role</label>
              <input id="role" type="text" value="<?php echo htmlspecialchars($profileRoleDisplay, ENT_QUOTES); ?>" disabled />
              <span class="lock-icon"><?php echo $icons['lock']; ?></span>
            </div>
            <div class="form-group">
              <label for="department">Department</label>
              <input id="department" name="department" type="text" value="<?php echo htmlspecialchars($profileDepartment, ENT_QUOTES); ?>" placeholder="e.g. Human Resources" />
            </div>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Save Changes</button>
          </div>
        </form>

        <hr class="section-divider" />

        <h4 class="settings-subhead">Security & Password</h4>

        <form method="post">
          <input type="hidden" name="action" value="change_password" />
          <div class="form-grid">
            <div class="form-group">
              <label for="newPassword">New Password</label>
              <input id="newPassword" name="newPassword" type="password" placeholder="At least 8 characters" minlength="8" required />
            </div>
            <div class="form-group">
              <label for="confirmPassword">Confirm New Password</label>
              <input id="confirmPassword" name="confirmPassword" type="password" placeholder="Repeat password" minlength="8" required />
            </div>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Update Password</button>
          </div>
        </form>
      </div>

      <div class="settings-card-row">
        <div class="settings-card tone-blue" style="opacity:0.6;">
          <span class="settings-card-icon"><?php echo $icons['shield']; ?></span>
          <div class="settings-card-text">
            <strong>Two-Factor Authentication</strong>
            <span>Coming soon, not yet available.</span>
          </div>
          <button class="settings-card-action" type="button" disabled>Enable</button>
        </div>
        <div class="settings-card tone-red">
          <span class="settings-card-icon"><?php echo $icons['trash']; ?></span>
          <div class="settings-card-text">
            <strong>Deactivate Account</strong>
            <span>Disables your login. An admin can reactivate it later.</span>
          </div>
          <form method="post" data-confirm="Deactivate your account? You will be signed out immediately.">
            <input type="hidden" name="action" value="deactivate_account" />
            <button class="settings-card-action" type="submit">Deactivate</button>
          </form>
        </div>
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