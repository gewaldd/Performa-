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
$supervisorUid = (string) ($_SESSION['uid'] ?? '');

// Load probationary employees assigned to this supervisor. Unassigned
// (legacy) staff stay visible so existing pilots keep working.
$employees = [];
try {
    $docs = get_cached_collection('Users', 600);
    foreach ($docs as $doc) {
        $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
        if (strpos($roleKey, 'probation') !== false) {
            if (!supervisor_is_in_scope($doc, $supervisorUid)) {
                continue;
            }
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
                            <p>No probationary employees found yet. Ask your Employer to assign probationers to you.</p>
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
                                            <div class="avatar avatar-local" aria-hidden="true">
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

            <?php
            /*
             * Manuscript scope for supervisors: view dashboards, scores,
             * deadlines, and recommendations — no rating decisions, no
             * assignment writes, no POST forms here. Latest AI excerpt per
             * employee comes from already-loaded Ratings (zero new reads).
             */
            $supervisorAiByUid = [];
            foreach ($allRatings as $supRating) {
                $supUid = (string) ($supRating['employeeUid'] ?? '');
                $supAi = $supRating['aiRecommendations'] ?? null;
                if ($supUid === '' || !is_array($supAi)) {
                    continue;
                }
                $supAt = (string) ($supRating['ratedAt'] ?? '');
                if (
                    isset($supervisorAiByUid[$supUid]) &&
                    strcmp((string) $supervisorAiByUid[$supUid]['ratedAt'], $supAt) >= 0
                ) {
                    continue;
                }
                $supRecs = isset($supAi['training_recommendations']) && is_array($supAi['training_recommendations'])
                    ? array_values($supAi['training_recommendations'])
                    : [];
                $supTop = $supRecs[0] ?? null;
                $supervisorAiByUid[$supUid] = [
                    'ratedAt' => $supAt,
                    'status' => (string) ($supAi['status'] ?? ''),
                    'top' => is_array($supTop) ? [
                        'competency_area' => (string) ($supTop['competency_area'] ?? ''),
                        'training_type' => (string) ($supTop['training_type'] ?? ''),
                        'timeline' => (string) ($supTop['timeline'] ?? ''),
                    ] : null,
                    'hasRecs' => count($supRecs) > 0,
                ];
            }
            $supervisionQueue = [];
            foreach ($rows as $supRow) {
                if (!in_array($supRow['statusClass'] ?? '', ['status-warning', 'status-danger'], true)) {
                    continue;
                }
                // Weakest KPI from recorded scores (threshold rule, explicitly
                // not AI): gives the card something truthful to show when no
                // AI plan exists. Raw industry key comes from $employees since
                // $rows carries the display-formatted label.
                $supRawIndustry = 'retail';
                foreach ($employees as $supEmp) {
                    if (($supEmp['uid'] ?? '') === ($supRow['uid'] ?? '')) {
                        $supRawIndustry = $supEmp['industry'] ?? 'retail';
                        break;
                    }
                }
                $supMine = ratings_for_employee($allRatings, $supRow['uid'] ?? '');
                $supScores = (isset($supMine[0]['scores']) && is_array($supMine[0]['scores']))
                    ? $supMine[0]['scores']
                    : [];
                $supTpl = kpi_template_for($supRawIndustry);
                $supWeakest = null;
                foreach ($supTpl['kpis'] as $supKpi) {
                    if (!isset($supScores[$supKpi['key']])) {
                        continue;
                    }
                    $supGap = (float) $supKpi['target'] - (float) $supScores[$supKpi['key']];
                    if ($supGap > 0 && ($supWeakest === null || $supGap > $supWeakest['gap'])) {
                        $supWeakest = [
                            'name' => $supKpi['name'],
                            'score' => (float) $supScores[$supKpi['key']],
                            'target' => (float) $supKpi['target'],
                            'gap' => $supGap,
                        ];
                    }
                }
                $supervisionQueue[] = [
                    'row' => $supRow,
                    'ai' => $supervisorAiByUid[$supRow['uid']] ?? null,
                    'weakest' => $supWeakest,
                ];
                if (count($supervisionQueue) >= 3) {
                    break;
                }
            }
            ?>
            <?php if ($supervisionQueue): ?>
            <section class="panel" aria-label="Interventions to watch" style="margin-top: 24px;">
                <div class="panel-header">
                    <div>
                        <h2>Interventions to Watch</h2>
                        <p>Read-only. Training decisions stay with the employer.</p>
                    </div>
                </div>
                <ol class="insight-queue">
                    <?php foreach ($supervisionQueue as $supEntry): ?>
                        <?php $supRow = $supEntry['row']; $supTop = $supEntry['ai']['top'] ?? null; ?>
                        <li class="insight-queue-item">
                            <div class="insight-queue-head">
                                <strong><?php echo htmlspecialchars($supRow['name'], ENT_QUOTES); ?></strong>
                                <span class="insight-queue-gap">
                                    <?php echo number_format((float) $supRow['score'], 1); ?>
                                    /
                                    <?php echo number_format((float) $supRow['target'], 1); ?>
                                    target
                                </span>
                            </div>
                            <div class="recommendation-box">
                                <div>
                                    <div class="recommendation-label">Status:</div>
                                    <span class="status-pill <?php echo htmlspecialchars($supRow['statusClass'], ENT_QUOTES); ?>">
                                        <?php echo htmlspecialchars($supRow['status'], ENT_QUOTES); ?>
                                    </span>
                                </div>
                                <div style="margin-top: 8px;">
                                    <?php if ($supTop): ?>
                                        <div class="recommendation-label">Suggested focus:</div>
                                        <strong><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $supTop['competency_area'])), ENT_QUOTES); ?></strong>
                                        <span class="microcopy"> · <?php echo htmlspecialchars($supTop['training_type'], ENT_QUOTES); ?><?php echo $supTop['timeline'] !== '' ? ' · ' . htmlspecialchars($supTop['timeline'], ENT_QUOTES) : ''; ?></span>
                                    <?php else: ?>
                                        <?php $supWeak = $supEntry['weakest'] ?? null; ?>
                                        <?php if ($supWeak): ?>
                                            <div class="recommendation-label">Needs attention in:</div>
                                            <strong><?php echo htmlspecialchars($supWeak['name'], ENT_QUOTES); ?></strong>
                                            <span class="microcopy"> · <?php echo number_format($supWeak['score'], 1); ?> / target <?php echo number_format($supWeak['target'], 1); ?> (from recorded scores, not AI)</span>
                                        <?php endif; ?>
                                        <div class="microcopy" style="margin-top: 4px;">
                                            <?php if (($supEntry['ai']['status'] ?? '') === 'pending_approval'): ?>
                                                Full AI plan pending employer review.
                                            <?php else: ?>
                                                No AI plan yet — the employer generates it from this employee's recorded ratings.
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="insight-actions">
                                <a class="ghost-button" style="width: 100%; text-align: center;" href="ratings.php?employee=<?php echo urlencode($supRow['uid']); ?>">Rate</a>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
            <?php endif; ?>
        </main>
    </div>

    <script src="<?php echo htmlspecialchars(supervisor_asset('script.js'), ENT_QUOTES); ?>"></script>
</body>

</html>