<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/../Employer/includes/collection_cache.php';
require_once __DIR__ . '/supervisor_layout.php';
require_login();
require_role('supervisor');
require_password_reset('settings.php');

$supervisorName = $_SESSION['name'] ?? 'Supervisor';

// Load probationary employees.
$employees = [];
try {
    $docs = get_cached_collection('Users', 600);
    foreach ($docs as $doc) {
        $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
        if (strpos($roleKey, 'probation') !== false) {
            $employees[] = [
                'uid' => $doc['uid'] ?? '',
                'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
                'industry' => $doc['industry'] ?? 'retail',
                'hireDate' => $doc['hireDate'] ?? '',
                'createdAt' => $doc['createdAt'] ?? '',
                'probationPeriodDays' => (int) ($doc['probationPeriodDays'] ?? 180),
            ];
        }
    }
} catch (\Throwable $e) {
    // leave $employees empty
}

$allRatings = [];
try {
    $allRatings = get_cached_collection('Ratings', 600);
} catch (\Throwable $e) {
    // leave empty if collection doesn't exist yet
}

$nearingDeadlineCount = 0;
$scoreSum = 0;
$scoreCount = 0;
$unratedCount = 0;
$rows = [];

foreach ($employees as $emp) {
    $summary = employee_kpi_summary($allRatings, $emp['uid'], $emp['industry']);

    $daysLeft = null;
    $daysIn = null;
    $startDate = $emp['hireDate'] ?: $emp['createdAt'];
    if (!empty($startDate)) {
        try {
            $hire = new DateTime($startDate);
            $today = new DateTime('today');
            $daysIn = $today < $hire ? 0 : (int) $today->diff($hire)->format('%a');
            $probationPeriodDays = max(1, (int) ($emp['probationPeriodDays'] ?? 180));
            $daysLeft = max(0, $probationPeriodDays - $daysIn);
            if ($daysLeft <= 30) {
                $nearingDeadlineCount++;
            }
        } catch (\Throwable $e) {
        }
    }

    if ($summary['hasData']) {
        $scoreSum += $summary['score'];
        $scoreCount++;
        $statusInfo = kpi_status_for_score($summary['score'], $summary['targetAvg']);
    } else {
        $statusInfo = ['status' => 'Not Yet Rated', 'statusClass' => 'status-neutral'];
        $unratedCount++;
    }

    $rows[] = [
        'uid' => $emp['uid'],
        'name' => $emp['name'],
        'industry' => ucfirst(str_replace('_', ' ', $emp['industry'])),
        'timeline' => $daysIn !== null ? "Day {$daysIn} · {$daysLeft} days left" : 'No hire date on file',
        'progress' => $daysIn !== null ? min(100, (int) round(($daysIn / max(1, (int) ($emp['probationPeriodDays'] ?? 180))) * 100)) : 0,
        'score' => $summary['score'],
        'target' => $summary['targetAvg'],
        'status' => $statusInfo['status'],
        'statusClass' => $statusInfo['statusClass'],
    ];
}

$avgScore = $scoreCount > 0 ? round($scoreSum / $scoreCount, 1) : 0;
$totalAssigned = count($employees);

// Stash for navigation badges
$_SESSION['pf_nav_deadline'] = $nearingDeadlineCount;
$_SESSION['pf_nav_unrated'] = $unratedCount;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php supervisor_brand_head('Dashboard · Performa'); ?>
</head>

<body>
    <div class="app-shell">
        <?php supervisor_render_shell('Dashboard'); ?>

        <main class="main" id="dashboard">
            <?php
            ob_start();
            ?>
            <label class="search-bar">
                <span class="sr-only">Search assigned employees</span>
                <span class="search-icon" aria-hidden="true"><?php echo supervisor_layout_icon('search'); ?></span>
                <input id="dashboardSearch" type="search" placeholder="Search your employees..." autocomplete="off" />
            </label>
            <?php if ($nearingDeadlineCount > 0): ?>
                <div class="status-pill status-warning" style="padding: 6px 12px; font-size: 13px;">
                    <?php echo $nearingDeadlineCount; ?> nearing deadline (&lt; 30d)
                </div>
            <?php endif; ?>
            <?php
            $headerActions = ob_get_clean();

            $eyebrowHtml = '<span class="eyebrow">Supervisor Overview</span>';
            supervisor_page_header(
                'dashboard-title',
                'Supervisor Dashboard',
                $eyebrowHtml,
                'Track KPI progress and evaluation timelines for probationary employees.',
                $headerActions
            );
            ?>

            <section class="metrics" aria-label="Key supervisor metrics">
                <article class="metric-card">
                    <div class="metric-icon icon-warm" aria-hidden="true">
                        <?php echo supervisor_layout_icon('users'); ?>
                    </div>
                    <div class="metric-meta">
                        <span class="metric-label">Assigned Employees</span>
                        <strong class="metric-value"><?php echo $totalAssigned; ?></strong>
                    </div>
                    <div class="metric-badge neutral">Active</div>
                </article>

                <article class="metric-card">
                    <div class="metric-icon icon-gold" aria-hidden="true">
                        <?php echo supervisor_layout_icon('hourglass'); ?>
                    </div>
                    <div class="metric-meta">
                        <span class="metric-label">Nearing Deadline</span>
                        <strong class="metric-value"><?php echo $nearingDeadlineCount; ?></strong>
                    </div>
                    <div class="metric-badge <?php echo $nearingDeadlineCount > 0 ? 'warning' : 'neutral'; ?>">
                        <?php echo $nearingDeadlineCount > 0 ? 'Action Req.' : 'On Track'; ?>
                    </div>
                </article>

                <article class="metric-card">
                    <div class="metric-icon icon-mint" aria-hidden="true">
                        <?php echo supervisor_layout_icon('trend'); ?>
                    </div>
                    <div class="metric-meta">
                        <span class="metric-label">Avg. KPI Score</span>
                        <strong class="metric-value font-mono"><?php echo $avgScore > 0 ? number_format($avgScore, 1) : '—'; ?><small>/ 5.0</small></strong>
                    </div>
                    <div class="metric-badge <?php echo $avgScore >= 3.5 ? 'positive' : 'neutral'; ?>">This Month</div>
                </article>
            </section>

            <section class="content-grid" style="grid-template-columns: 1fr; margin-top: 24px;">
                <div class="panel evaluations">
                    <div class="panel-header">
                        <div>
                            <h2>Probationary Employees</h2>
                            <p>Live progress from Firestore.</p>
                        </div>
                        <a class="ghost-button" href="ratings.php">
                            <?php echo supervisor_layout_icon('target'); ?>
                            <span>Rate Employee</span>
                        </a>
                    </div>

                    <?php if (empty($rows)): ?>
                        <div class="empty-state">
                            <p>No probationary employees found yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap" role="table" aria-label="Employees">
                            <div class="table-head" role="row">
                                <span role="columnheader">Employee</span>
                                <span role="columnheader">Timeline</span>
                                <span role="columnheader">KPI Score</span>
                                <span role="columnheader">Status</span>
                                <span role="columnheader" style="text-align: right;">Action</span>
                            </div>
                            <div id="evaluationRows">
                                <?php foreach ($rows as $row): ?>
                                    <div class="table-row" role="row" data-search="<?php echo htmlspecialchars(strtolower($row['name'] . ' ' . $row['industry'] . ' ' . $row['status']), ENT_QUOTES); ?>">
                                        <div class="employee-cell" role="cell">
                                            <div class="avatar-chip" aria-hidden="true">
                                                <?php echo htmlspecialchars(supervisor_avatar_initials($row['name']), ENT_QUOTES); ?>
                                            </div>
                                            <div>
                                                <div class="employee-name font-semibold">
                                                    <?php echo htmlspecialchars($row['name'], ENT_QUOTES); ?>
                                                </div>
                                                <div class="employee-role text-muted">
                                                    <?php echo htmlspecialchars($row['industry'], ENT_QUOTES); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="timeline-cell" role="cell">
                                            <div class="timeline-text text-sm">
                                                <?php echo htmlspecialchars($row['timeline'], ENT_QUOTES); ?>
                                            </div>
                                            <div class="timeline-bar" style="height: 6px; background: var(--ui-border, #e2e8f0); border-radius: 3px; overflow: hidden; margin-top: 4px;">
                                                <span style="display: block; height: 100%; width: <?php echo (int) $row['progress']; ?>%; background: var(--ui-blue, #245fba); border-radius: 3px;"></span>
                                            </div>
                                        </div>
                                        <div class="score-cell" role="cell">
                                            <?php echo supervisor_score_meter($row['score'], 5.0, $row['target']); ?>
                                        </div>
                                        <div class="status-cell" role="cell">
                                            <span class="status-pill <?php echo htmlspecialchars($row['statusClass'], ENT_QUOTES); ?>">
                                                <?php echo htmlspecialchars($row['status'], ENT_QUOTES); ?>
                                            </span>
                                        </div>
                                        <div role="cell" style="text-align: right;">
                                            <a class="ghost-button" style="padding: 6px 12px; font-size: 12px;" href="ratings.php?employee=<?php echo urlencode($row['uid']); ?>">Rate</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="padding: 16px 20px; border-top: 1px solid var(--panel-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
                        <span class="text-sm text-muted">Showing <?php echo count($rows); ?> employee<?php echo count($rows) === 1 ? '' : 's'; ?></span>
                        <a class="view-more" href="employees.php">View Full Employee Directory →</a>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="<?php echo htmlspecialchars(supervisor_asset('script.js'), ENT_QUOTES); ?>"></script>
</body>

</html>