<?php
$rootDir = __DIR__ . '/..';
require_once $rootDir . '/auth.php';
require_once $rootDir . '/firebase_init.php';

require_login();
require_role('employer');

$icons = [
  'home' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 9 9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
  'users' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>',
  'target' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>',
  'bar-chart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/></svg>',
  'settings' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>',
  'search' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>',
  'bell' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>',
  'mail' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="2"/><path d="m22 6-10 7L2 6"/></svg>',
  'hourglass' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 22h14M5 2h14M5 22v-4a7 7 0 0 1 5-6.7A7 7 0 0 1 5 4.7V2M19 22v-4a7 7 0 0 0-5-6.7A7 7 0 0 0 19 4.7V2"/></svg>',
  'trend' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>',
  'filter' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="22 3 2 3 10 12.46 10 19 14 21 14 12.46 22 3"/></svg>',
  'download' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>',
  'cap' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c0 1.66 3 3 6 3s6-1.34 6-3v-5"/></svg>',
  'sparkle' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v4M12 17v4M5 5l2.8 2.8M16.2 16.2 19 19M3 12h4M17 12h4M5 19l2.8-2.8M16.2 7.8 19 5"/></svg>',
  'more' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>',
];

$navItems = [
  ['label' => 'Dashboard', 'href' => 'employer_dashboard.php', 'active' => true, 'icon' => 'home'],
  ['label' => 'Employees', 'href' => 'employees.php', 'active' => false, 'icon' => 'users'],
  ['label' => 'KPIs', 'href' => 'kpis.php', 'active' => false, 'icon' => 'target'],
  ['label' => 'Reports', 'href' => 'reports.php', 'active' => false, 'icon' => 'bar-chart'],
  ['label' => 'Settings', 'href' => 'settings.php', 'active' => false, 'icon' => 'settings'],
];

// session: show signed-in user's name/role if present
session_start();
if (empty($_SESSION['uid'])) {
  // not logged in — redirect to project-relative login
  header('Location: ../login.php');
  exit;
}

$profileName = $_SESSION['name'] ?? 'Unknown User';
$profileRole = $_SESSION['role'] ?? 'Employer';
// Normalize role display
$profileRoleDisplay = ucwords(str_replace('_', ' ', $profileRole));

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

function display_role_label(?string $role): string
{
  return ucwords(str_replace('_', ' ', (string) $role));
}

$liveUsers = [];
try {
  $docs = firestore_list_documents('Users');
  foreach ($docs as $doc) {
    $roleKey = normalize_role_key($doc['role'] ?? null);
    if ($roleKey === 'admin' || $roleKey === 'employer') {
      continue;
    }
    $createdAt = $doc['createdAt'] ?? '';
    $createdTime = $createdAt ? strtotime($createdAt) : false;
    $daysSince = $createdTime ? max(1, (int) floor((time() - $createdTime) / 86400)) : 0;
    $daysLeft = $createdTime ? max(0, 180 - $daysSince) : 0;
    $progress = $createdTime ? min(100, (int) round(($daysSince / 180) * 100)) : 0;
    $statusClass = $daysLeft <= 30 ? 'status-warning' : 'status-good';
    $status = $daysLeft <= 30 ? 'Needs Review' : 'On Track';
    $accentColor = $daysLeft <= 30 ? '#f0a11b' : '#2f6df6';
    $avatarSeed = urlencode(strtolower($doc['email'] ?? ($doc['name'] ?? 'user')));
    $avatar = 'https://ui-avatars.com/api/?name=' . $avatarSeed . '&background=2f6df6&color=fff&size=160';
    $liveUsers[] = [
      'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
      'role' => display_role_label($doc['role'] ?? null),
      'avatar' => $avatar,
      'day' => $daysSince ? ('Day ' . $daysSince) : 'Day 0',
      'daysLeft' => $daysLeft . ' days left',
      'progress' => $progress,
      'score' => $daysLeft <= 30 ? 3.8 : 4.5,
      'stars' => $daysLeft <= 30 ? 4 : 5,
      'status' => $status,
      'statusClass' => $statusClass,
      'statusKey' => $daysLeft <= 30 ? 'needs-review' : 'on-track',
      'accentColor' => $accentColor,
      'email' => $doc['email'] ?? '',
      'createdAt' => $createdAt,
    ];
  }
} catch (Throwable $e) {
  // leave $liveUsers empty so the page still renders
}

$probationaryCount = 0;
$nearDeadlineCount = 0;
$scoreTotal = 0.0;
foreach ($liveUsers as $user) {
  $probationaryCount++;
  if (str_starts_with($user['daysLeft'], '0 ') || (int) $user['daysLeft'] <= 30) {
    $nearDeadlineCount++;
  }
  $scoreTotal += (float) $user['score'];
}

$metrics = [
  ['label' => 'Total Probationary', 'value' => (string) $probationaryCount, 'badge' => '+Live', 'tone' => 'positive', 'iconClass' => 'icon-warm', 'icon' => 'users'],
  ['label' => 'Nearing Deadline (< 30 days)', 'value' => (string) $nearDeadlineCount, 'badge' => 'Live Count', 'tone' => 'warning', 'iconClass' => 'icon-gold', 'icon' => 'hourglass'],
  ['label' => 'Overall Performance', 'value' => $probationaryCount > 0 ? number_format($scoreTotal / $probationaryCount, 1) : '0.0', 'suffix' => '/ 5.0', 'badge' => 'Avg Score', 'tone' => 'neutral', 'iconClass' => 'icon-mint', 'icon' => 'trend'],
];

$evaluations = $liveUsers;

$insightTitle = 'Intervention Suggested';
$insightName = 'Maria Clara';
$insightText = 'Based on recent KPI trends for %s, targeted upskilling is recommended before day 150.';
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
        <div class="profile-avatar" style="background-image: url('https://randomuser.me/api/portraits/men/32.jpg');">
        </div>
        <div>
          <div class="profile-name"><?php echo htmlspecialchars($profileName, ENT_QUOTES); ?></div>
          <div class="profile-role"><?php echo htmlspecialchars($profileRoleDisplay, ENT_QUOTES); ?></div>
        </div>
      </div>
    </aside>

    <main class="main" id="dashboard">
      <header class="topbar">
        <label class="search-bar" aria-label="Search employees or reports">
          <span class="search-icon"><?php echo $icons['search']; ?></span>
          <input id="dashboardSearch" type="search" placeholder="Search employees, reports..." />
        </label>

        <div class="topbar-actions">
          <div class="deadline-pill">
            <span class="deadline-icon"><?php echo $icons['bell']; ?></span>
            2 days until regularization deadline
          </div>
          <button class="icon-button" type="button" aria-label="Messages"><?php echo $icons['mail']; ?></button>
          <a class="ghost-button" href="../logout.php" style="margin-left:8px;" aria-label="Sign out">Sign out</a>
        </div>
      </header>

      <section class="hero">
        <div style="display:flex;gap:12px;align-items:center;">
          <h1 style="margin:0;">Probationary Overview</h1>
          <a class="primary-button" href="add_employee.php" style="margin-left:12px;">Add Employee</a>
        </div>
        <p>Track and evaluate employees approaching regularization.</p>
      </section>

      <section class="metrics" id="kpis" aria-label="Key dashboard metrics">
        <?php foreach ($metrics as $metric): ?>
          <article class="metric-card">
            <div class="metric-card-top">
              <div class="metric-icon <?php echo htmlspecialchars($metric['iconClass'], ENT_QUOTES); ?>">
                <?php echo $icons[$metric['icon']]; ?>
              </div>
              <div class="metric-badge <?php echo htmlspecialchars($metric['tone'], ENT_QUOTES); ?>">
                <?php echo htmlspecialchars($metric['badge'], ENT_QUOTES); ?>
              </div>
            </div>
            <div class="metric-meta">
              <span><?php echo htmlspecialchars($metric['label'], ENT_QUOTES); ?></span>
              <strong><?php echo htmlspecialchars($metric['value'], ENT_QUOTES); ?><?php if (!empty($metric['suffix'])): ?><small>
                    <?php echo htmlspecialchars($metric['suffix'], ENT_QUOTES); ?></small><?php endif; ?></strong>
            </div>
          </article>
        <?php endforeach; ?>
      </section>

      <section class="content-grid">
        <div class="panel evaluations" id="employees">
          <div class="panel-header">
            <div>
              <h2>Active Evaluations</h2>
            </div>
            <div class="panel-actions">
              <button class="ghost-button" type="button"><?php echo $icons['filter']; ?> Filter</button>
              <button class="ghost-button icon-only" type="button"
                aria-label="Export"><?php echo $icons['download']; ?></button>
            </div>
          </div>

          <div class="table-toolbar">
            <div class="chip-group" role="tablist" aria-label="Evaluation filters">
              <button class="filter-chip active" type="button" data-filter="all">All</button>
              <button class="filter-chip" type="button" data-filter="needs-review">Needs Review</button>
              <button class="filter-chip" type="button" data-filter="on-track">On Track</button>
              <button class="filter-chip" type="button" data-filter="ready-for-reg">Ready</button>
            </div>
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
                    <div class="timeline-text">
                      <span class="timeline-day"><?php echo htmlspecialchars($employee['day'], ENT_QUOTES); ?></span>
                      <span class="timeline-left"
                        style="color: <?php echo htmlspecialchars($employee['accentColor'], ENT_QUOTES); ?>;"><?php echo htmlspecialchars($employee['daysLeft'], ENT_QUOTES); ?></span>
                    </div>
                    <div class="timeline-bar">
                      <span
                        style="width: <?php echo (int) $employee['progress']; ?>%; background: <?php echo htmlspecialchars($employee['accentColor'], ENT_QUOTES); ?>;"></span>
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
          <div class="insight-top">
            <span class="insight-icon"></span>
            <span class="insight-label">AI INSIGHT</span>
          </div>
          <h2><?php echo htmlspecialchars($insightTitle, ENT_QUOTES); ?></h2>
          <p id="insightText">
            <?php
            $parts = explode('%s', $insightText);
            echo htmlspecialchars($parts[0], ENT_QUOTES);
            echo '<strong>' . htmlspecialchars($insightName, ENT_QUOTES) . '</strong>';
            echo htmlspecialchars($parts[1], ENT_QUOTES);
            ?>
          </p>

          <div class="recommendation-box">
            <span class="recommendation-icon"><?php echo $icons['cap']; ?></span>
            <div>
              <div class="recommendation-label">Recommended Action:</div>
              <strong id="recommendationTitle"><?php echo htmlspecialchars($recommendation, ENT_QUOTES); ?></strong>
            </div>
          </div>

          <div class="insight-actions">
            <button class="primary-button" id="assignCourseButton" type="button" data-completed-label="Assigned"
              data-confirm-text="Training assigned to Maria Clara for the next review cycle.">Assign Course</button>
            <button class="more-button" type="button" aria-label="More options"><?php echo $icons['more']; ?></button>
          </div>
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