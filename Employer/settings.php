<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';

$profileName = $_SESSION['name'] ?? 'Unknown User';
$profileRole = $_SESSION['role'] ?? 'Employer';
$profileRoleDisplay = ucwords(str_replace('_', ' ', $profileRole));
$profileEmail = $_SESSION['email'] ?? '';
$profileDepartment = $_SESSION['department'] ?? '';

$message = '';
$messageTone = 'info';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_profile') {
  $newName = trim((string) ($_POST['fullName'] ?? ''));
  $newEmail = trim((string) ($_POST['email'] ?? ''));
  $newDepartment = trim((string) ($_POST['department'] ?? ''));

  if ($newName === '' || $newEmail === '') {
    $message = 'Full name and email are required.';
    $messageTone = 'error';
  } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    $message = 'Please enter a valid email address.';
    $messageTone = 'error';
  } else {
    try {
      $existing = firestore_get_document('Users', $_SESSION['uid']) ?? [];
      $existing['name'] = $newName;
      $existing['email'] = $newEmail;
      $existing['role'] = $profileRole;
      $existing['department'] = $newDepartment;

      firestore_write_document(
        'Users',
        $_SESSION['uid'],
        $existing
      );

      $_SESSION['name'] = $newName;
      $_SESSION['email'] = $newEmail;
      $_SESSION['department'] = $newDepartment;

      $profileName = $newName;
      $profileEmail = $newEmail;
      $profileDepartment = $newDepartment;

      $message = 'Profile updated.';
      $messageTone = 'success';
    } catch (Throwable $e) {
      error_log(
        'Employer settings profile update failed: ' .
        $e->getMessage()
      );

      $message = 'We could not save your profile right now. Please try again.';
      $messageTone = 'error';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_password') {
  $newPassword = (string) ($_POST['newPassword'] ?? '');
  $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

  // Fresh re-authentication: a stolen-but-aging session must not be enough
  // to permanently take over the account. Requiring a recent login is the
  // standard "confirm it's you" gate (same pattern as Google/GitHub) and
  // needs no extra credentials store. Anyone past the window re-logs in.
  $loginAge = time() - (int) ($_SESSION['login_at'] ?? 0);
  $freshWindowSeconds = 15 * 60;

  if ($loginAge > $freshWindowSeconds) {
    $message = 'For security, please sign out and sign in again, then change your password.';
    $messageTone = 'error';
  } elseif (strlen($newPassword) < 8) {
    $message = 'Password must be at least 8 characters.';
    $messageTone = 'error';
  } elseif ($newPassword !== $confirmPassword) {
    $message = 'Passwords do not match.';
    $messageTone = 'error';
  } else {
    try {
      identitytoolkit_update_password(
        $_SESSION['uid'],
        $newPassword
      );

      // Password is now user-chosen: lift any forced-reset flag both in
      // Firestore (source of truth, read at next login) and in the live
      // session (so the redirect gate releases immediately).
      try {
        $self = firestore_get_document('Users', $_SESSION['uid']) ?? [];
        if (!empty($self['mustChangePassword'])) {
          $self['mustChangePassword'] = false;
          firestore_write_document('Users', $_SESSION['uid'], $self);
        }
      } catch (Throwable $e) {
        error_log(
          'Employer settings mustChangePassword clear failed: ' .
          $e->getMessage()
        );
      }
      unset($_SESSION['must_change_password']);
      // Fresh session ID after a credential change (fixation defense).
      if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
      }
      $_SESSION['login_at'] = time();

      $message = 'Password updated.';
      $messageTone = 'success';
    } catch (Throwable $e) {
      error_log(
        'Employer settings password update failed: ' .
        $e->getMessage()
      );

      $message = 'We could not update your password right now. Please try again.';
      $messageTone = 'error';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'deactivate_account') {
  try {
    identitytoolkit_disable_user(
      $_SESSION['uid'],
      true
    );

    logout();

    header(
      'Location: ../login.php?deactivated=1'
    );
    exit;
  } catch (Throwable $e) {
    error_log(
      'Employer settings account deactivation failed: ' .
      $e->getMessage()
    );

    $message = 'We could not deactivate your account right now. Please try again.';
    $messageTone = 'error';
  }
}

// Shared icon library (Style A cleanup).
$icons = [
  'lock' => employer_icon('settings'),
  'shield' => employer_icon('trend'),
  'trash' => employer_icon('download'),
];

$profileInitials = employer_avatar_initials($profileName);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php employer_brand_head(); ?>
  <title>Settings · Performa</title>
  <meta name="description" content="Manage your account preferences and system configurations." />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body class="settings-page-body">

  <div class="app-shell">

    <?php employer_render_shell('Settings'); ?>

    <main class="main settings-page" id="settingsPage">

      <header class="settings-page-header page-header" aria-labelledby="settingsTitle">
        <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" aria-hidden="true"><line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/></svg>
        </button>
        <div class="ph-main">
          <nav class="ph-crumb" aria-label="Breadcrumb">
            <span>Settings</span>
            <span aria-hidden="true">/</span>
            <span>Account</span>
          </nav>
          <h1 id="settingsTitle">Settings</h1>
          <p>Manage your account preferences and security.</p>
        </div>
      </header>

      <?php if ($message): ?>
        <div class="alert alert-<?php echo htmlspecialchars($messageTone, ENT_QUOTES); ?>"
          role="<?php echo $messageTone === 'error' ? 'alert' : 'status'; ?>" aria-live="polite">
          <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($_SESSION['must_change_password']) && !$message): ?>
        <div class="alert alert-info" role="status" aria-live="polite">
          Your account is using a temporary password. Please set your own password below to continue.
        </div>
      <?php endif; ?>

      <section class="settings-panel settings-profile-panel">

        <div class="settings-section-heading">
          <div>
            <h2>Profile</h2>
            <p>Update the information used for your employer account.</p>
          </div>
        </div>

        <div class="profile-photo-row">
          <div class="profile-photo-frame profile-photo-initials" role="img"
            aria-label="Profile photo for <?php echo htmlspecialchars($profileName, ENT_QUOTES); ?>">
            <?php echo htmlspecialchars($profileInitials, ENT_QUOTES); ?>
          </div>

          <div class="profile-photo-info">
            <h3>Profile Photo</h3>
            <p>Avatar generated automatically from your name.</p>
          </div>
        </div>

        <form method="post" class="settings-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save_profile" />

          <div class="form-grid">

            <div class="form-group">
              <label for="fullName">Full Name</label>
              <input id="fullName" name="fullName" type="text"
                value="<?php echo htmlspecialchars($profileName, ENT_QUOTES); ?>" autocomplete="name" maxlength="120"
                required />
            </div>

            <div class="form-group">
              <label for="email">Email Address</label>
              <input id="email" name="email" type="email"
                value="<?php echo htmlspecialchars($profileEmail, ENT_QUOTES); ?>" autocomplete="email" maxlength="254"
                required />
            </div>

            <div class="form-group locked">
              <label for="role">Role</label>
              <input id="role" type="text" value="<?php echo htmlspecialchars($profileRoleDisplay, ENT_QUOTES); ?>"
                disabled aria-disabled="true" />

              <span class="lock-icon" aria-hidden="true">
                <?php echo $icons['lock']; ?>
              </span>
            </div>

            <div class="form-group">
              <label for="department">Department</label>
              <input id="department" name="department" type="text"
                value="<?php echo htmlspecialchars($profileDepartment, ENT_QUOTES); ?>" autocomplete="organization"
                maxlength="120" placeholder="e.g. Human Resources" />
            </div>

          </div>

          <div class="form-actions">
            <button class="btn-primary" type="submit">
              Save Changes
            </button>
          </div>
        </form>

      </section>

      <section class="settings-panel settings-security-panel">

        <div class="settings-section-heading">
          <div>
            <h2>Security</h2>
            <p>Change the password used to access your account.</p>
          </div>
        </div>

        <form method="post" class="settings-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="change_password" />

          <div class="form-grid">

            <div class="form-group">
              <label for="newPassword">New Password</label>
              <input id="newPassword" name="newPassword" type="password" autocomplete="new-password" minlength="8"
                required placeholder="At least 8 characters" />
            </div>

            <div class="form-group">
              <label for="confirmPassword">Confirm New Password</label>
              <input id="confirmPassword" name="confirmPassword" type="password" autocomplete="new-password"
                minlength="8" required placeholder="Repeat password" />
            </div>

          </div>

          <div class="form-actions">
            <button class="btn-primary" type="submit">
              Update Password
            </button>
          </div>
        </form>

      </section>

      <section class="settings-card-row settings-account-actions" aria-label="Account actions">

        <article class="settings-card tone-blue settings-disabled-card">
          <span class="settings-card-icon" aria-hidden="true">
            <?php echo $icons['shield']; ?>
          </span>

          <div class="settings-card-text">
            <strong>Two-Factor Authentication</strong>
            <span>Not available yet.</span>
          </div>

          <span class="status-pill status-neutral">Off</span>
        </article>

        <article class="settings-card tone-red">
          <span class="settings-card-icon" aria-hidden="true">
            <?php echo $icons['trash']; ?>
          </span>

          <div class="settings-card-text">
            <strong>Deactivate Account</strong>
            <span>Disables your login. An admin can reactivate it later.</span>
          </div>

          <form method="post" class="settings-card-form"
            data-confirm="Deactivate your account? You will be signed out immediately. An admin can reactivate it later."
            data-confirm-danger>
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="deactivate_account" />

            <button class="settings-card-action settings-card-action-danger" type="submit">
              Deactivate
            </button>
          </form>
        </article>

      </section>

    </main>

  </div>

  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

</body>

</html>