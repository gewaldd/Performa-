<?php
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

$navItems = [
  ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => false, 'icon' => 'home'],
  ['label' => 'Employees', 'href' => 'employees.php', 'active' => false, 'icon' => 'users'],
  ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => false, 'icon' => 'target'],
  ['label' => 'Reports', 'href' => 'reports.php', 'active' => false, 'icon' => 'bar-chart'],
  ['label' => 'Settings', 'href' => 'settings.php', 'active' => true, 'icon' => 'settings'],
];

$tabs = ['Profile', 'Company', 'Notifications', 'Security'];

$contributors = [
  'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=80&q=80',
  'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=80&q=80',
  'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=80&q=80',
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
  <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="styles.css" />
</head>

<body>
  <div class="app-shell">
    <aside class="sidebar">
      <div>
        <div class="brand">
          <div class="brand-mark">
            <span class="brand-mark-dot"></span>
          </div>
          <div class="brand-name">Performa</div>
        </div>

        <nav class="nav" aria-label="Primary">
          <?php foreach ($navItems as $item): ?>
            <a class="nav-item<?php echo $item['active'] ? ' active' : ''; ?>"
              href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>">
              <span class="nav-icon"><?php echo $icons[$item['icon']]; ?></span>
              <span><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span>
            </a>
          <?php endforeach; ?>
        </nav>
      </div>

      <div class="sidebar-footer">
        <div class="profile-avatar"
          style="background-image: url('https://randomuser.me/api/portraits/men/32.jpg');"></div>
        <div>
          <div class="profile-name">Juan Dela Cruz</div>
          <div class="profile-role">HR Director</div>
        </div>
      </div>
    </aside>

    <main class="main">
      <header class="topbar">
        <div></div>
        <div class="topbar-actions">
          <button class="icon-button" type="button" aria-label="Notifications"><?php echo $icons['bell']; ?></button>
          <div class="avatar-stack">
            <?php foreach ($contributors as $avatar): ?>
              <div class="avatar" style="background-image: url('<?php echo htmlspecialchars($avatar, ENT_QUOTES); ?>');"></div>
            <?php endforeach; ?>
          </div>
        </div>
      </header>

      <div class="page-header">
        <div>
          <h1>Settings</h1>
          <p>Manage your account preferences and system configurations</p>
        </div>
      </div>

      <div class="tabs-bar">
        <?php foreach ($tabs as $i => $tab): ?>
          <a class="tab-item<?php echo $i === 0 ? ' active' : ''; ?>" href="#"><?php echo htmlspecialchars($tab, ENT_QUOTES); ?></a>
        <?php endforeach; ?>
      </div>

      <div class="settings-panel">
        <div class="profile-photo-row">
          <div class="profile-photo-frame">
            <img src="https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=200&q=80"
              alt="Profile photo" />
            <button class="photo-upload-btn" type="button" aria-label="Change photo"><?php echo $icons['camera']; ?></button>
          </div>
          <div class="profile-photo-info">
            <h3>Profile Photo</h3>
            <p>This will be displayed on your profile and internal reports. Recommended size: 400×400px.</p>
            <div class="photo-links">
              <a class="link-blue">Upload New</a>
              <a class="link-red">Remove</a>
            </div>
          </div>
        </div>

        <div class="form-grid">
          <div class="form-group">
            <label for="fullName">Full Name</label>
            <input id="fullName" type="text" value="Juan Dela Cruz" />
          </div>
          <div class="form-group">
            <label for="email">Email Address</label>
            <input id="email" type="email" value="juan.delacruz@performa.io" />
          </div>
          <div class="form-group locked">
            <label for="role">Role</label>
            <input id="role" type="text" value="HR Director" disabled />
            <span class="lock-icon"><?php echo $icons['lock']; ?></span>
          </div>
          <div class="form-group">
            <label for="department">Department</label>
            <input id="department" type="text" value="Human Resources" />
          </div>
        </div>

        <hr class="section-divider" />

        <h4 class="settings-subhead">Security & Password</h4>

        <div class="form-grid">
          <div class="form-group">
            <label for="newPassword">New Password</label>
            <input id="newPassword" type="password" value="" placeholder="••••••••••••" />
          </div>
          <div class="form-group">
            <label for="confirmPassword">Confirm New Password</label>
            <input id="confirmPassword" type="password" value="" placeholder="••••••••••••" />
          </div>
        </div>

        <div class="form-actions">
          <button class="btn-cancel" type="button">Cancel</button>
          <button class="btn-primary" type="button">Save Changes</button>
        </div>
      </div>

      <div class="settings-card-row">
        <div class="settings-card tone-blue">
          <span class="settings-card-icon"><?php echo $icons['shield']; ?></span>
          <div class="settings-card-text">
            <strong>Two-Factor Authentication</strong>
            <span>Add an extra layer of security to your account.</span>
          </div>
          <button class="settings-card-action" type="button">Enable</button>
        </div>
        <div class="settings-card tone-red">
          <span class="settings-card-icon"><?php echo $icons['trash']; ?></span>
          <div class="settings-card-text">
            <strong>Deactivate Account</strong>
            <span>Permanently remove your profile and data.</span>
          </div>
          <button class="settings-card-action" type="button">Delete</button>
        </div>
      </div>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa employer dashboard prototype</span>
    <span>PHP-ready for Hostinger deployment</span>
  </footer>

  <script src="script.js"></script>
</body>

</html>