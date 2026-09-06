<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../kpi_templates.php';
require_login();
require_role('supervisor');

$supervisorName = $_SESSION['name'] ?? 'Supervisor';

$navItems = [
    ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'active' => false],
    ['label' => 'My Employees', 'href' => 'employees.php', 'active' => true],
    ['label' => 'Rating Entry', 'href' => 'ratings.php', 'active' => false],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
    ['label' => 'Notifications', 'href' => 'notifications.php', 'active' => false],
];

$employees = [];
try {
    $docs = firestore_list_documents('Users');
    foreach ($docs as $doc) {
        $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
        if (strpos($roleKey, 'probation') !== false) {
            $employees[] = [
                'uid' => $doc['uid'] ?? '',
                'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
                'email' => $doc['email'] ?? '',
                'industry' => $doc['industry'] ?? 'retail',
                'hireDate' => $doc['hireDate'] ?? '',
            ];
        }
    }
} catch (\Throwable $e) {
}

$allRatings = [];
try {
    $allRatings = firestore_list_documents('Ratings');
} catch (\Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | My Employees</title>
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
            <section class="hero">
                <p class="eyebrow">My Employees</p>
                <h1>Probationary employees and their KPI progress.</h1>
            </section>
            <section class="panel" style="padding-top:18px;">
                <div class="panel-header">
                    <div>
                        <h2>Employee List</h2>
                        <p>Live data from Firestore. Employee profiles and KPI configuration are managed by the
                            Employer.</p>
                    </div>
                </div>
                <?php if (empty($employees)): ?>
                    <p style="padding:24px; color:var(--muted);">No probationary employees found yet.</p>
                <?php else: ?>
                    <div class="table-wrap">
                        <div class="table-head"><span>Employee</span><span>Timeline</span><span>KPI
                                Score</span><span>Status</span></div>
                        <?php foreach ($employees as $emp): ?>
                            <?php
                            $summary = employee_kpi_summary($allRatings, $emp['uid'], $emp['industry']);

                            $daysIn = null;
                            $daysLeft = null;
                            $timelineText = 'No hire date on file';
                            $progress = 0;
                            if (!empty($emp['hireDate'])) {
                                try {
                                    $hire = new DateTime($emp['hireDate']);
                                    $today = new DateTime('today');
                                    $daysIn = $today < $hire ? 0 : (int) $today->diff($hire)->format('%a');
                                    $daysLeft = max(0, 180 - $daysIn);
                                    $timelineText = "Day {$daysIn} · {$daysLeft} days left";
                                    $progress = min(100, (int) round(($daysIn / 180) * 100));
                                } catch (\Throwable $e) {
                                }
                            }

                            if ($summary['hasData']) {
                                $statusInfo = kpi_status_for_score($summary['score'], $summary['targetAvg']);
                            } else {
                                $statusInfo = ['status' => 'Not Yet Rated', 'statusClass' => 'status-neutral'];
                            }
                            ?>
                            <div class="table-row">
                                <div class="employee-cell">
                                    <div class="avatar"></div>
                                    <div>
                                        <div class="employee-name">
                                            <?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?></div>
                                        <div class="employee-role">
                                            <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $emp['industry'])), ENT_QUOTES); ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="timeline-cell">
                                    <div class="timeline-text">
                                        <?php echo htmlspecialchars($timelineText, ENT_QUOTES); ?></div>
                                    <div class="timeline-bar"><span
                                            style="width: <?php echo (int) $progress; ?>%; background: var(--primary);"></span>
                                    </div>
                                </div>
                                <div class="score-cell">
                                    <strong
                                        class="score-value"><?php echo $summary['hasData'] ? number_format($summary['score'], 1) : '—'; ?></strong>
                                </div>
                                <div class="status-cell">
                                    <span
                                        class="status-pill <?php echo htmlspecialchars($statusInfo['statusClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($statusInfo['status'], ENT_QUOTES); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>