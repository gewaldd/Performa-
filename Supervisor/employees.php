<?php
$navItems = [
    ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'active' => false],
    ['label' => 'My Employees', 'href' => 'employees.php', 'active' => true],
    ['label' => 'Rating Entry', 'href' => 'ratings.php', 'active' => false],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
    ['label' => 'Notifications', 'href' => 'notifications.php', 'active' => false],
];

// TODO(firebase): replace with a Firestore query filtered to
// employees where assignedSupervisorId == current user's uid.
$employees = [
    ['name' => 'Maria Clara', 'role' => 'Customer Support Spec.', 'status' => 'Needs Review', 'statusClass' => 'status-warning', 'timeline' => '45 days left', 'score' => '3.8'],
    ['name' => 'Jose Rizal', 'role' => 'Software Engineer', 'status' => 'On Track', 'statusClass' => 'status-good', 'timeline' => '90 days left', 'score' => '4.5'],
];
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
                <div class="profile-avatar">SP</div>
                <div>
                    <div class="profile-name">Sofia Panganiban</div>
                    <div class="profile-role">Shift Supervisor</div>
                </div>
            </div>
        </aside>
        <main class="main">
            <section class="hero">
                <p class="eyebrow">My Employees</p>
                <h1>Employees assigned to you for KPI tracking.</h1>
            </section>
            <section class="panel" style="padding-top:18px;">
                <div class="panel-header">
                    <div>
                        <h2>Assigned Employee List</h2>
                        <p>View-only. Employee profiles and KPI configuration are managed by the Employer.</p>
                    </div>
                </div>
                <div class="table-wrap">
                    <div class="table-head"><span>Employee</span><span>Timeline</span><span>KPI
                            Score</span><span>Status</span></div>
                    <?php foreach ($employees as $employee): ?>
                        <div class="table-row">
                            <div class="employee-cell">
                                <div class="avatar"></div>
                                <div>
                                    <div class="employee-name">
                                        <?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?></div>
                                    <div class="employee-role">
                                        <?php echo htmlspecialchars($employee['role'], ENT_QUOTES); ?></div>
                                </div>
                            </div>
                            <div class="timeline-cell">
                                <div class="timeline-text">
                                    <?php echo htmlspecialchars($employee['timeline'], ENT_QUOTES); ?></div>
                                <div class="timeline-bar"><span style="width: 68%; background: #2f6df6;"></span></div>
                            </div>
                            <div class="score-cell"><strong
                                    class="score-value"><?php echo htmlspecialchars($employee['score'], ENT_QUOTES); ?></strong>
                                <div class="stars" aria-hidden="true">★★★★☆</div>
                            </div>
                            <div class="status-cell"><span
                                    class="status-pill <?php echo htmlspecialchars($employee['statusClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($employee['status'], ENT_QUOTES); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>
