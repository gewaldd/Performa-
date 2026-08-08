<?php
$navItems = [
  ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'active' => true],
  ['label' => 'My Employees', 'href' => 'employees.php', 'active' => false],
  ['label' => 'Rating Entry', 'href' => 'ratings.php', 'active' => false],
  ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
  ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
  ['label' => 'Notifications', 'href' => 'notifications.php', 'active' => false],
];

// TODO(firebase): replace with a Firestore query filtered to
// employees where assignedSupervisorId == current user's uid.
$metrics = [
  ['label' => 'Assigned Employees', 'value' => '5', 'badge' => 'Active', 'tone' => 'neutral', 'variant' => 'warm', 'icon' => '◔'],
  ['label' => 'Nearing Deadline (< 30 days)', 'value' => '2', 'badge' => 'Action Req.', 'tone' => 'warning', 'variant' => 'gold', 'icon' => '⌛'],
  ['label' => 'Avg. KPI Score', 'value' => '4.1', 'suffix' => '/ 5.0', 'badge' => 'This Month', 'tone' => 'positive', 'variant' => 'mint', 'icon' => '▣'],
];

$evaluations = [
  [
    'name' => 'Maria Clara',
    'role' => 'Customer Support Spec.',
    'avatar' => 'https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=160&q=80',
    'timeline' => 'Day 135 · 45 days left',
    'progress' => 72,
    'score' => 3.8,
    'stars' => 4,
    'status' => 'Needs Review',
    'statusClass' => 'status-warning',
    'statusKey' => 'needs-review',
    'progressColor' => '#f0a11b',
  ],
  [
    'name' => 'Jose Rizal',
    'role' => 'Software Engineer',
    'avatar' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?auto=format&fit=crop&w=160&q=80',
    'timeline' => 'Day 90 · 90 days left',
    'progress' => 54,
    'score' => 4.5,
    'stars' => 5,
    'status' => 'On Track',
    'statusClass' => 'status-good',
    'statusKey' => 'on-track',
    'progressColor' => '#2f6df6',
  ],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | Supervisor Dashboard</title>
  <meta name="description" content="Supervisor overview of assigned probationary employees and KPI progress." />
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
            <div class="brand-subtitle">Supervisor Dashboard</div>
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
        <div class="profile-avatar">SP</div>
        <div>
          <div class="profile-name">Sofia Panganiban</div>
          <div class="profile-role">Shift Supervisor</div>
        </div>
      </div>
    </aside>

    <main class="main" id="dashboard">
      <header class="topbar">
        <label class="search-bar" aria-label="Search assigned employees">
          <span class="search-icon">⌕</span>
          <input id="dashboardSearch" type="search" placeholder="Search your employees..." />
        </label>

        <div class="topbar-actions">
          <div class="deadline-pill">2 assigned employees nearing deadline</div>
        </div>
      </header>

      <section class="hero">
        <p class="eyebrow">Supervisor Overview</p>
        <h1>Track KPI progress for the employees assigned to you.</h1>
      </section>

      <section class="metrics" aria-label="Key dashboard metrics">
        <?php foreach ($metrics as $metric): ?>
          <article class="metric-card <?php echo htmlspecialchars($metric['variant'], ENT_QUOTES); ?>">
            <div class="metric-icon"><?php echo htmlspecialchars($metric['icon'], ENT_QUOTES); ?></div>
            <div class="metric-meta">
              <span><?php echo htmlspecialchars($metric['label'], ENT_QUOTES); ?></span>
              <strong><?php echo htmlspecialchars($metric['value'], ENT_QUOTES); ?><?php if (!empty($metric['suffix'])): ?><small>
                    <?php echo htmlspecialchars($metric['suffix'], ENT_QUOTES); ?></small><?php endif; ?></strong>
            </div>
            <div class="metric-badge <?php echo htmlspecialchars($metric['tone'], ENT_QUOTES); ?>">
              <?php echo htmlspecialchars($metric['badge'], ENT_QUOTES); ?>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="content-grid">
        <div class="panel evaluations" style="grid-column: 1 / -1;">
          <div class="panel-header">
            <div>
              <h2>Assigned Employees</h2>
              <p>View-only overview. Use Rating Entry to submit a weekly score.</p>
            </div>
          </div>

          <div class="table-toolbar">
            <div class="chip-group" role="tablist" aria-label="Evaluation filters">
              <button class="filter-chip active" type="button" data-filter="all">All</button>
              <button class="filter-chip" type="button" data-filter="needs-review">Needs Review</button>
              <button class="filter-chip" type="button" data-filter="on-track">On Track</button>
            </div>
            <p class="table-note">Search by name, role, or status.</p>
          </div>

          <div class="table-wrap" role="table" aria-label="Assigned employees">
            <div class="table-head" role="row">
              <span role="columnheader">Employee</span>
              <span role="columnheader">Timeline</span>
              <span role="columnheader">KPI Score</span>
              <span role="columnheader">Status</span>
            </div>

            <div id="evaluationRows">
              <?php foreach ($evaluations as $employee): ?>
                <div class="table-row" role="row"
                  data-search="<?php echo htmlspecialchars(strtolower($employee['name'] . ' ' . $employee['role'] . ' ' . $employee['status']), ENT_QUOTES); ?>"
                  data-filter="<?php echo htmlspecialchars($employee['statusKey'], ENT_QUOTES); ?>">
                  <div class="employee-cell" role="cell">
                    <div class="avatar"
                      style="background-image: url('<?php echo htmlspecialchars($employee['avatar'], ENT_QUOTES); ?>');">
                    </div>
                    <div>
                      <div class="employee-name"><?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?></div>
                      <div class="employee-role"><?php echo htmlspecialchars($employee['role'], ENT_QUOTES); ?></div>
                    </div>
                  </div>

                  <div class="timeline-cell" role="cell">
                    <div class="timeline-text"><?php echo htmlspecialchars($employee['timeline'], ENT_QUOTES); ?></div>
                    <div class="timeline-bar">
                      <span
                        style="width: <?php echo (int) $employee['progress']; ?>%; background: <?php echo htmlspecialchars($employee['progressColor'], ENT_QUOTES); ?>;"></span>
                    </div>
                  </div>

                  <div class="score-cell" role="cell">
                    <strong class="score-value"><?php echo number_format((float) $employee['score'], 1); ?></strong>
                    <div class="stars" aria-hidden="true">
                      <?php echo str_repeat('★', (int) $employee['stars']); ?>
                      <?php echo str_repeat('☆', 5 - (int) $employee['stars']); ?>
                    </div>
                  </div>

                  <div class="status-cell" role="cell">
                    <span
                      class="status-pill <?php echo htmlspecialchars($employee['statusClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($employee['status'], ENT_QUOTES); ?></span>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

          <a class="view-more" href="employees.php">View Full Employee List →</a>
        </div>
      </section>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa supervisor dashboard prototype</span>
    <span>View-only access · No KPI configuration rights</span>
  </footer>

  <script src="script.js"></script>
</body>

</html>
