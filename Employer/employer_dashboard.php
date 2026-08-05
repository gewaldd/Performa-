<?php
$navItems = [
  ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => true],
  ['label' => 'Employees', 'href' => 'employees.php', 'active' => false],
  ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => false],
  ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
  ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
];

$metrics = [
  ['label' => 'Total Probationary', 'value' => '42', 'badge' => '+12%', 'tone' => 'positive', 'variant' => 'warm', 'icon' => '◔'],
  ['label' => 'Nearing Deadline (< 30 days)', 'value' => '8', 'badge' => 'Action Req.', 'tone' => 'warning', 'variant' => 'gold', 'icon' => '⌛'],
  ['label' => 'Overall Performance', 'value' => '4.2', 'suffix' => '/ 5.0', 'badge' => 'Avg Score', 'tone' => 'neutral', 'variant' => 'mint', 'icon' => '▣'],
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
  [
    'name' => 'Gabriela Silang',
    'role' => 'Marketing Associate',
    'avatar' => 'https://images.unsplash.com/photo-1524504388940-b1c1722653e1?auto=format&fit=crop&w=160&q=80',
    'timeline' => 'Day 178 · 2 days left',
    'progress' => 94,
    'score' => 4.8,
    'stars' => 5,
    'status' => 'Ready for Reg.',
    'statusClass' => 'status-ready',
    'statusKey' => 'ready-for-reg',
    'progressColor' => '#ed5b57',
  ],
];

$insightTitle = 'Intervention Suggested';
$insightText = 'Based on recent KPI trends, a targeted upskilling plan is recommended before the next review cycle.';
$recommendation = 'Customer Service Excellence Training Module';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Performa | Employer Dashboard</title>
  <meta name="description"
    content="Employer KPI dashboard for probationary employee evaluation and training recommendations." />
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
            <div class="brand-subtitle">Employer Dashboard</div>
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
        <div class="profile-avatar">JD</div>
        <div>
          <div class="profile-name">Juan Dela Cruz</div>
          <div class="profile-role">HR Director</div>
        </div>
      </div>
    </aside>

    <main class="main" id="dashboard">
      <header class="topbar">
        <label class="search-bar" aria-label="Search employees or reports">
          <span class="search-icon">⌕</span>
          <input id="dashboardSearch" type="search" placeholder="Search employees, reports..." />
        </label>

        <div class="topbar-actions">
          <div class="deadline-pill">2 days until regularization deadline</div>
          <button class="icon-button" type="button" aria-label="Messages">✉</button>
        </div>
      </header>

      <section class="hero">
        <p class="eyebrow">Probationary Overview</p>
        <h1>Track and evaluate employees approaching regularization.</h1>
      </section>

      <section class="metrics" id="kpis" aria-label="Key dashboard metrics">
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
        <div class="panel evaluations" id="employees">
          <div class="panel-header">
            <div>
              <h2>Active Evaluations</h2>
              <p>Review KPI progress and probation status in one place.</p>
            </div>
            <div class="panel-actions">
              <button class="ghost-button" type="button">Filter</button>
              <button class="ghost-button" type="button">Export</button>
            </div>
          </div>

          <div class="table-toolbar">
            <div class="chip-group" role="tablist" aria-label="Evaluation filters">
              <button class="filter-chip active" type="button" data-filter="all">All</button>
              <button class="filter-chip" type="button" data-filter="needs-review">Needs Review</button>
              <button class="filter-chip" type="button" data-filter="on-track">On Track</button>
              <button class="filter-chip" type="button" data-filter="ready-for-reg">Ready</button>
            </div>
            <p class="table-note">Search by name, role, or status.</p>
          </div>

          <div class="table-wrap" role="table" aria-label="Active evaluations">
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

          <span id="reports" class="section-anchor" aria-hidden="true"></span>
          <a class="view-more" href="#reports">View All Probationary Staff →</a>
        </div>

        <aside class="insight-card" id="settings">
          <div class="insight-badge">AI INSIGHT</div>
          <h2><?php echo htmlspecialchars($insightTitle, ENT_QUOTES); ?></h2>
          <p id="insightText"><?php echo htmlspecialchars($insightText, ENT_QUOTES); ?></p>

          <div class="recommendation-box">
            <div class="recommendation-label">Recommended Action</div>
            <strong id="recommendationTitle"><?php echo htmlspecialchars($recommendation, ENT_QUOTES); ?></strong>
          </div>

          <button class="primary-button" id="assignCourseButton" type="button" data-completed-label="Assigned"
            data-confirm-text="Training assigned to Maria Clara for the next review cycle.">Assign Course</button>
          <p class="microcopy">This action will later connect to your training and employee records in the live version.
          </p>
        </aside>
      </section>
    </main>
  </div>

  <footer class="site-footer">
    <span>Performa employer dashboard prototype</span>
    <span>PHP-ready for Hostinger deployment</span>
  </footer>

  <script src="script.js"></script>
</body>

</html>