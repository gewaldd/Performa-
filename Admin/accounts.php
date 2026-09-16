<?php
require_once __DIR__ . '/../auth.php';

require_login();
require_role('admin');

$navItems = [
  ['label' => 'Dashboard', 'href' => 'admin_dashboard.php', 'active' => false],
  ['label' => 'User Accounts', 'href' => 'accounts.php', 'active' => true],
  ['label' => 'System Settings', 'href' => 'settings.php', 'active' => false],
];

require_once __DIR__ . '/../firebase_init.php';

$accountMessage = '';
$accountMessageType = 'info';
$resetPasswordPopup = null;
$probationDaysPopup = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $uid = trim((string) ($_POST['uid'] ?? ''));

  if ($uid === '' || !in_array($action, ['reset_password', 'toggle_status', 'set_probation_days'], true)) {
    $accountMessage = 'Invalid account action.';
    $accountMessageType = 'error';
  } elseif ($action === 'set_probation_days') {
    $probationDays = filter_input(INPUT_POST, 'probationPeriodDays', FILTER_VALIDATE_INT);
    if ($probationDays === false || $probationDays < 1 || $probationDays > 3650) {
      $accountMessage = 'Probation days must be between 1 and 3650.';
      $accountMessageType = 'error';
    } else {
      try {
        $account = firestore_get_document('Users', $uid);
        if (!$account || strpos(strtolower((string) ($account['role'] ?? '')), 'probation') === false) {
          throw new RuntimeException('Only probationary employee accounts can be adjusted.');
        }
        $account['probationPeriodDays'] = $probationDays;
        firestore_write_document('Users', $uid, $account);
        $probationDaysPopup = [
          'name' => $account['name'] ?? $account['email'] ?? 'employee',
          'days' => $probationDays,
        ];
        $accountMessageType = 'success';
      } catch (Throwable $e) {
        $accountMessage = $e->getMessage();
        $accountMessageType = 'error';
      }
    }
  } else {
    try {
      $account = firestore_get_document('Users', $uid);
      if (!$account) {
        throw new RuntimeException('User account was not found.');
      }

      if ($action === 'reset_password') {
        $temporaryPassword = bin2hex(random_bytes(6));
        identitytoolkit_update_password($uid, $temporaryPassword);
        $resetPasswordPopup = [
          'name' => $account['name'] ?? $account['email'] ?? 'user',
          'password' => $temporaryPassword,
        ];
        $accountMessageType = 'success';
      } else {
        if ($uid === ($_SESSION['uid'] ?? '')) {
          throw new RuntimeException('You cannot deactivate your own admin account.');
        }
        $currentlyDisabled = strtolower((string) ($account['status'] ?? 'Active')) === 'disabled';
        identitytoolkit_disable_user($uid, !$currentlyDisabled);
        $account['status'] = $currentlyDisabled ? 'Active' : 'Disabled';
        firestore_write_document('Users', $uid, $account);
        $accountMessage = $currentlyDisabled ? 'Account reactivated.' : 'Account deactivated.';
        $accountMessageType = 'success';
      }
    } catch (Throwable $e) {
      $accountMessage = $e->getMessage();
      $accountMessageType = 'error';
    }
  }
}

// Load users from Firestore; fall back to an empty list on error.
$accounts = [];
try {
  $docs = firestore_list_documents('Users');
  foreach ($docs as $d) {
    $roleRaw = $d['role'] ?? ($d['roles'] ?? 'probationary_employee');
    // Normalize role for display
    $roleDisplay = str_replace('_', ' ', $roleRaw);
    $roleDisplay = ucwords($roleDisplay);
    // Compute a short normalized role key for client-side filtering
    $roleKey = strtolower($roleRaw);
    if (strpos($roleKey, 'probation') !== false) {
      $roleKey = 'probationary';
    } elseif (strpos($roleKey, 'employ') !== false) {
      $roleKey = 'employer';
    } elseif (strpos($roleKey, 'supervis') !== false) {
      $roleKey = 'supervisor';
    } elseif (strpos($roleKey, 'admin') !== false) {
      $roleKey = 'admin';
    }
    $status = $d['status'] ?? 'Active';
    $statusClass = strtolower($status) === 'active' ? 'status-good' : 'status-bad';
    $accounts[] = [
      'name' => $d['name'] ?? $d['email'] ?? 'Unknown',
      'email' => $d['email'] ?? '',
      'role' => $roleDisplay,
      'roleKey' => $roleKey,
      'status' => $status,
      'statusClass' => $statusClass,
      'disabled' => strtolower($status) === 'disabled',
      'uid' => $d['uid'] ?? null,
      'probationPeriodDays' => (int) ($d['probationPeriodDays'] ?? 180),
    ];
  }
} catch (Throwable $e) {
  // keep $accounts empty and allow the static demo UI to show nothing
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | User Accounts</title>
  <link rel="stylesheet" href="styles.css" />
  <link rel="stylesheet" href="../ui-refresh.css" />
  <style>
    .role-filter-group {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .account-form {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }

    .account-form .form-row {
      display: flex;
      flex-direction: column;
      gap: 6px;
    }

    .account-form label {
      font-size: 12.5px;
      font-weight: 600;
      color: var(--text);
    }

    .account-form input,
    .account-form select {
      border: 1px solid var(--panel-border);
      border-radius: var(--radius-sm);
      padding: 11px 14px;
      font-size: 14px;
      font-family: inherit;
      background: #fbfcfe;
      color: var(--text);
    }

    .account-form .full-width {
      grid-column: 1 / -1;
    }

    .row-actions {
      display: flex;
      gap: 8px;
    }

    .password-modal {
      position: fixed;
      inset: 0;
      z-index: 20;
      display: grid;
      place-items: center;
      padding: 20px;
      background: rgba(15, 23, 42, 0.42);
    }

    .password-modal[hidden] {
      display: none;
    }

    .password-dialog {
      width: min(100%, 420px);
      padding: 26px;
      border: 1px solid var(--ui-border);
      border-radius: 14px;
      background: #fff;
      box-shadow: 0 20px 60px rgba(15, 23, 42, 0.2);
    }

    .password-dialog h2 {
      margin: 0 0 8px;
      color: var(--ui-text);
      font-size: 1.25rem;
    }

    .password-dialog p {
      margin: 0 0 18px;
      color: var(--ui-muted);
      font-size: 13px;
      line-height: 1.5;
    }

    .temporary-password {
      display: block;
      margin-bottom: 20px;
      padding: 14px 16px;
      border: 1px solid #cfe0f5;
      border-radius: 9px;
      background: #f2f7fd;
      color: var(--ui-blue-dark);
      font-family: "JetBrains Mono", Consolas, monospace;
      font-size: 16px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-align: center;
      user-select: all;
    }

    .password-dialog-actions {
      display: flex;
      justify-content: flex-end;
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
        <p class="eyebrow">User Accounts</p>
        <h1>Create and manage accounts for every role in the system.</h1>
      </section>

      <?php if ($accountMessage && $resetPasswordPopup === null): ?>
        <div class="alert alert-<?php echo htmlspecialchars($accountMessageType, ENT_QUOTES); ?>" role="status">
          <?php echo htmlspecialchars($accountMessage, ENT_QUOTES); ?>
        </div>
      <?php endif; ?>

      <section class="panel" style="padding-top:18px;">
        <div class="panel-header">
          <div>
            <h2>All Accounts</h2>
            <p>Employer, Supervisor, and Probationary Employee accounts across the system.</p>
          </div>
        </div>

        <div class="table-toolbar">
          <div class="chip-group role-filter-group" role="tablist" aria-label="Role filters">
            <button class="filter-chip active" type="button" data-filter="all">All</button>
            <button class="filter-chip" type="button" data-filter="employer">Employer</button>
            <button class="filter-chip" type="button" data-filter="supervisor">Supervisor</button>
            <button class="filter-chip" type="button" data-filter="admin">Admin</button>
            <button class="filter-chip" type="button" data-filter="probationary">Probationary</button>
          </div>
          <p class="table-note">Search by name, email, or role.</p>
        </div>

        <div class="table-wrap">
          <div class="table-head" style="grid-template-columns: 1.4fr 1fr 0.7fr 0.9fr;">
            <span>Name / Email</span><span>Role</span><span>Status</span><span>Actions</span>
          </div>
          <?php foreach ($accounts as $account): ?>
            <div class="table-row" style="grid-template-columns: 1.4fr 1fr 0.7fr 0.9fr;"
              data-role="<?php echo htmlspecialchars($account['roleKey'] ?? '', ENT_QUOTES); ?>"
              data-filter="<?php echo htmlspecialchars($account['roleKey'] ?? '', ENT_QUOTES); ?>">
              <div class="employee-cell">
                <div class="avatar"></div>
                <div>
                  <div class="employee-name"><?php echo htmlspecialchars($account['name'], ENT_QUOTES); ?></div>
                  <div class="employee-role"><?php echo htmlspecialchars($account['email'], ENT_QUOTES); ?></div>
                </div>
              </div>
              <div class="timeline-text"><?php echo htmlspecialchars($account['role'], ENT_QUOTES); ?></div>
              <div class="status-cell">
                <span
                  class="status-pill <?php echo htmlspecialchars($account['statusClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($account['status'], ENT_QUOTES); ?></span>
              </div>
              <div class="row-actions">
                <?php if (!empty($account['uid'])): ?>
                  <?php if (($account['roleKey'] ?? '') === 'probationary'): ?>
                    <form method="post" style="display:flex;align-items:center;gap:6px;">
                      <input type="hidden" name="action" value="set_probation_days" />
                      <input type="hidden" name="uid" value="<?php echo htmlspecialchars($account['uid'], ENT_QUOTES); ?>" />
                      <input type="number" name="probationPeriodDays" min="1" max="3650"
                        value="<?php echo (int) $account['probationPeriodDays']; ?>" required
                        aria-label="Probation period days" style="width:76px;padding:8px;border:1px solid var(--panel-border);border-radius:8px;" />
                      <button class="ghost-button" type="submit">Save Days</button>
                    </form>
                  <?php endif; ?>
                  <form method="post">
                    <input type="hidden" name="action" value="reset_password" />
                    <input type="hidden" name="uid" value="<?php echo htmlspecialchars($account['uid'], ENT_QUOTES); ?>" />
                    <button class="ghost-button" type="submit">Reset Password</button>
                  </form>
                  <form method="post">
                    <input type="hidden" name="action" value="toggle_status" />
                    <input type="hidden" name="uid" value="<?php echo htmlspecialchars($account['uid'], ENT_QUOTES); ?>" />
                    <button class="ghost-button"
                      type="submit"><?php echo $account['disabled'] ? 'Reactivate' : 'Deactivate'; ?></button>
                  </form>
                <?php endif; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </section>

      <section class="panel" style="padding-top:18px; margin-top:16px;">
        <div class="panel-header">
          <div>
            <h2>Create New Account</h2>
            <p>Admin creates the login for a new Employer, Supervisor, or Probationary Employee.</p>
          </div>
        </div>
        <div style="padding: 18px;">
          <a href="create_user.php" class="primary-button"
            style="display:inline-block;padding:12px 18px;border-radius:12px;text-decoration:none;">Create Account</a>
        </div>
      </section>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa admin dashboard prototype</span>
    <span>System-level access · Account &amp; configuration management</span>
  </footer>

  <?php if ($resetPasswordPopup !== null): ?>
    <div class="password-modal" id="passwordModal" role="presentation">
      <section class="password-dialog" role="dialog" aria-modal="true" aria-labelledby="passwordModalTitle">
        <h2 id="passwordModalTitle">Password reset successful</h2>
        <p>A temporary password has been generated for
          <?php echo htmlspecialchars($resetPasswordPopup['name'], ENT_QUOTES); ?>.</p>
        <span
          class="temporary-password"><?php echo htmlspecialchars($resetPasswordPopup['password'], ENT_QUOTES); ?></span>
        <div class="password-dialog-actions">
          <button class="primary-button" type="button" id="closePasswordModal">Done</button>
        </div>
      </section>
    </div>
  <?php endif; ?>

  <?php if ($probationDaysPopup !== null): ?>
    <div class="password-modal" id="probationDaysModal" role="presentation">
      <section class="password-dialog" role="dialog" aria-modal="true" aria-labelledby="probationDaysModalTitle">
        <h2 id="probationDaysModalTitle">Probation period updated</h2>
        <p>The probation period for
          <?php echo htmlspecialchars($probationDaysPopup['name'], ENT_QUOTES); ?> has been saved.</p>
        <span class="temporary-password"><?php echo (int) $probationDaysPopup['days']; ?> days</span>
        <div class="password-dialog-actions">
          <button class="primary-button" type="button" id="closeProbationDaysModal">Done</button>
        </div>
      </section>
    </div>
  <?php endif; ?>

  <script src="script.js"></script>
  <script src="accounts-script.js"></script>
  <?php if ($resetPasswordPopup !== null): ?>
    <script>
      const passwordModal = document.getElementById('passwordModal');
      const closePasswordModal = document.getElementById('closePasswordModal');
      closePasswordModal?.addEventListener('click', () => passwordModal?.setAttribute('hidden', ''));
      passwordModal?.addEventListener('click', (event) => {
        if (event.target === passwordModal) passwordModal.setAttribute('hidden', '');
      });
    </script>
  <?php endif; ?>
  <?php if ($probationDaysPopup !== null): ?>
    <script>
      const probationDaysModal = document.getElementById('probationDaysModal');
      const closeProbationDaysModal = document.getElementById('closeProbationDaysModal');
      closeProbationDaysModal?.addEventListener('click', () => probationDaysModal?.setAttribute('hidden', ''));
      probationDaysModal?.addEventListener('click', (event) => {
        if (event.target === probationDaysModal) probationDaysModal.setAttribute('hidden', '');
      });
    </script>
  <?php endif; ?>
</body>

</html>