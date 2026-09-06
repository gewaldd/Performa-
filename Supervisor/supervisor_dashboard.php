<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../kpi_templates.php';
require_login();
require_role('supervisor');

$supervisorName = $_SESSION['name'] ?? 'Supervisor';

$navItems = [
    ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'active' => true],
    ['label' => 'My Employees', 'href' => 'employees.php', 'active' => false],
    ['label' => 'Rating Entry', 'href' => 'ratings.php', 'active' => false],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
    ['label' => 'Notifications', 'href' => 'notifications.php', 'active' => false],
];

// Load probationary employees.
// TODO: once Employer's add_employee.php has a supervisorId field again,
// filter this to only employees assigned to $_SESSION['uid'].
$employees = [];
try {
    $docs = firestore_list_documents('Users');
    foreach ($docs as $doc) {
        $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
        if (strpos($roleKey, 'probation') !== false) {
            $employees[] = [
                'uid' => $doc['uid'] ?? '',
                'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
                'industry' => $doc['industry'] ?? 'retail',
                'hireDate' => $doc['hireDate'] ?? '',
            ];
        }
    }
} catch (\Throwable $e) {
    // leave $employees empty
}

$allRatings = [];
try {
    $allRatings = firestore_list_documents('Ratings');
} catch (\Throwable $e) {
    // leave empty if collection doesn't exist yet
}

$nearingDeadlineCount = 0;
$scoreSum = 0;
$scoreCount = 0;
$rows = [];

foreach ($employees as $emp) {
    $summary = employee_kpi_summary($allRatings, $emp['uid'], $emp['industry']);

    $daysLeft = null;
    $daysIn = null;
    if (!empty($emp['hireDate'])) {
        try {
            $hire = new DateTime($emp['hireDate']);
            $today = new DateTime('today');
            $daysIn = $today < $hire ? 0 : (int) $today->diff($hire)->format('%a');
            $daysLeft = max(0, 180 - $daysIn);
            if ($daysLeft <= 30)
                $nearingDeadlineCount++;
        } catch (\Throwable $e) {
        }
    }

    if ($summary['hasData']) {
        $scoreSum += $summary['score'];
        $scoreCount++;
        $statusInfo = kpi_status_for_score($summary['score'], $summary['targetAvg']);
    } else {
        $statusInfo = ['status' => 'Not Yet Rated', 'statusClass' => 'status-neutral'];
    }

    $rows[] = [
        'name' => $emp['name'],
        'industry' => ucfirst(str_replace('_', ' ', $emp['industry'])),
        'timeline' => $daysIn !== null ? "Day {$daysIn} · {$daysLeft} days left" : 'No hire date on file',
        'progress' => $daysIn !== null ? min(100, (int) round(($daysIn / 180) * 100)) : 0,
        'score' => $summary['score'],
        'status' => $statusInfo['status'],
        'statusClass' => $statusInfo['statusClass'],
    ];
}

$avgScore = $scoreCount > 0 ? round($scoreSum / $scoreCount, 1) : 0;
$totalAssigned = count($employees);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Supervisor Dashboard</title>
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
                            href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>"><span><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span></a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="sidebar-footer">
                <div class="profile-avatar"><?php echo strtoupper(substr($supervisorName, 0, 2)); ?></div>
                <div>
                    <div class="profile-name"><?php echo htmlspecialchars($supervisorName, ENT_QUOTES); ?></div>
                    <div class="profile-role">Shift Supervisor</div>
                </div>
            </div>
        </aside>

        <main class="main">
            <header class="topbar">
                <label class="search-bar" aria-label="Search assigned employees">
                    <span class="search-icon">⌕</span>
                    <input id="dashboardSearch" type="search" placeholder="Search your employees..." />
                </label>
                <div class="topbar-actions">
                    <div class="deadline-pill"><?php echo $nearingDeadlineCount; ?> employee<?php echo $nearingDeadlineCount === 1 ? '' : 's'; ?>
                        nearing deadline</div>
                </div>
            </header>

            <section class="hero">
                <p class="eyebrow">Supervisor Overview</p>
                <h1>Track KPI progress for probationary employees.</h1>
            </section>

            <section class="metrics" aria-label="Key dashboard metrics">
                <article class="metric-card warm">
                    <div class="metric-icon">◔</div>
                    <div class="metric-meta"><span>Employees</span><strong><?php echo $totalAssigned; ?></strong>
                    </div>
                    <div class="metric-badge neutral">Active</div>
                </article>
                <article class="metric-card gold">
                    <div class="metric-icon">⌛</div>
                    <div class="metric-meta"><span>Nearing Deadline (&lt; 30 days)</span><strong><?php echo $nearingDeadlineCount; ?></strong>
                    </div>
                    <div class="metric-badge warning">Action Req.</div>
                </article>
                <article class="metric-card mint">
                    <div class="metric-icon">▣</div>
                    <div class="metric-meta"><span>Avg. KPI Score</span><strong><?php echo $avgScore; ?><small>/
                                5.0</small></strong></div>
                    <div class="metric-badge positive">This Month</div>
                </article>
            </section>

            <section class="content-grid">
                <div class="panel evaluations" style="grid-column: 1 / -1;">
                    <div class="panel-header">
                        <div>
                            <h2>Probationary Employees</h2>
                            <p>Live data from Firestore.</p>
                        </div>
                    </div>

                    <?php if (empty($rows)): ?>
                        <p style="padding:24px; color:var(--muted);">No probationary employees found yet.</p>
                    <?php else: ?>
                        <div class="table-wrap" role="table" aria-label="Employees">
                            <div class="table-head" role="row">
                                <span role="columnheader">Employee</span>
                                <span role="columnheader">Timeline</span>
                                <span role="columnheader">KPI Score</span>
                                <span role="columnheader">Status</span>
                            </div>
                            <div id="evaluationRows">
                                <?php foreach ($rows as $row): ?>
                                    <div class="table-row" role="row">
                                        <div class="employee-cell" role="cell">
                                            <div class="avatar"></div>
                                            <div>
                                                <div class="employee-name">
                                                    <?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?></div>
                                                <div class="employee-role">
                                                    <?php echo htmlspecialchars($row['industry'], ENT_QUOTES); ?></div>
                                            </div>
                                        </div>
                                        <div class="timeline-cell" role="cell">
                                            <div class="timeline-text">
                                                <?php echo htmlspecialchars($row['timeline'], ENT_QUOTES); ?></div>
                                            <div class="timeline-bar"><span
                                                    style="width: <?php echo (int) $row['progress']; ?>%; background: var(--primary);"></span>
                                            </div>
                                        </div>
                                        <div class="score-cell" role="cell">
                                            <strong
                                                class="score-value"><?php echo $row['score'] !== null ? number_format($row['score'], 1) : '—'; ?></strong>
                                        </div>
                                        <div class="status-cell" role="cell">
                                            <span
                                                class="status-pill <?php echo htmlspecialchars($row['statusClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($row['status'], ENT_QUOTES); ?></span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <a class="view-more" href="employees.php">View Full Employee List →</a>
                </div>
            </section>
        </main>
    </div>

    <script src="script.js"></script>
</body>

</html>