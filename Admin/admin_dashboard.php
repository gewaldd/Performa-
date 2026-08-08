<?php
$navItems = [
  ['label' => 'Dashboard', 'href' => 'admin_dashboard.php', 'active' => true],
  ['label' => 'User Accounts', 'href' => 'accounts.php', 'active' => false],
  ['label' => 'System Settings', 'href' => 'settings.php', 'active' => false],
];

// TODO(firebase): replace with counts from a Firestore query on the Users collection.
$metrics = [
  ['label' => 'Total Employer Accounts', 'value' => '1', 'badge' => 'Active', 'tone' => 'neutral', 'variant' => 'warm', 'icon' => '◔'],
  ['label' => 'Total Supervisor Accounts', 'value' => '3', 'badge' => 'Active', 'tone' => 'neutral', 'variant' => 'gold', 'icon' => '◔'],
  ['label' => 'Total Probationary Accounts', 'value' => '10', 'badge' => 'Active', 'tone' => 'positive', 'variant' => 'mint', 'icon' => '▣'],
];

// TODO(firebase): replace with the most recent entries from an
// `auditLog` collection (write an entry whenever an account is
// created/deactivated or a system setting is changed).
$recentActivity = [
  ['action' => 'Account created', 'detail' => 'Supervisor account for Sofia Panganiban', 'when' => '2 hours ago'],
  ['action' => 'KPI template updated', 'detail' => 'Retail industry template edited by Employer', 'when' => '5 hours ago'],
  ['action' => 'Account deactivated', 'detail' => 'Probationary account for a completed hire', 'when' => 'Yesterday'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | Admin Dashboard</title>
  <meta name="description" content="Admin overview of system-wide user accounts and activity." />
  <link rel="stylesheet" href="styles.css" />
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

    <main class="main" id="dashboard">
      <section class="hero">
        <p class="eyebrow">Admin Overview</p>
        <h1>Manage user accounts and system-wide settings.</h1>
      </section>

      <section class="metrics" aria-label="Key dashboard metrics">
        <?php foreach ($metrics as $metric): ?>
          <article class="metric-card <?php echo htmlspecialchars($metric['variant'], ENT_QUOTES); ?>">
            <div class="metric-icon"><?php echo htmlspecialchars($metric['icon'], ENT_QUOTES); ?></div>
            <div class="metric-meta">
              <span><?php echo htmlspecialchars($metric['label'], ENT_QUOTES); ?></span>
              <strong><?php echo htmlspecialchars($metric['value'], ENT_QUOTES); ?></strong>
            </div>
            <div class="metric-badge <?php echo htmlspecialchars($metric['tone'], ENT_QUOTES); ?>">
              <?php echo htmlspecialchars($metric['badge'], ENT_QUOTES); ?>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="content-grid">
        <div class="panel" style="grid-column: 1 / -1;">
          <div class="panel-header">
            <div>
              <h2>Recent System Activity</h2>
              <p>Audit log of account changes and configuration updates across the platform.</p>
            </div>
          </div>
          <div class="table-wrap">
            <div class="table-head"><span>Action</span><span>Detail</span><span>When</span><span></span></div>
            <?php foreach ($recentActivity as $entry): ?>
              <div class="table-row" style="grid-template-columns: 1fr 2fr 0.7fr 0.3fr;">
                <div class="employee-name"><?php echo htmlspecialchars($entry['action'], ENT_QUOTES); ?></div>
                <div class="employee-role"><?php echo htmlspecialchars($entry['detail'], ENT_QUOTES); ?></div>
                <div class="timeline-text"><?php echo htmlspecialchars($entry['when'], ENT_QUOTES); ?></div>
                <div></div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </section>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa admin dashboard prototype</span>
    <span>System-level access · Account &amp; configuration management</span>
  </footer>

  <script src="script.js"></script>
</body>

</html>
