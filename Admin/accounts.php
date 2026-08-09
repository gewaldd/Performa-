<?php
$navItems = [
  ['label' => 'Dashboard', 'href' => 'admin_dashboard.php', 'active' => false],
  ['label' => 'User Accounts', 'href' => 'accounts.php', 'active' => true],
  ['label' => 'System Settings', 'href' => 'settings.php', 'active' => false],
];

require_once __DIR__ . '/../firebase_init.php';

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
      'uid' => $d['uid'] ?? null,
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
                <button class="ghost-button" type="button">Reset Password</button>
                <button class="ghost-button" type="button">Deactivate</button>
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

  <script src="script.js"></script>
  <script src="accounts-script.js"></script>
</body>

</html>