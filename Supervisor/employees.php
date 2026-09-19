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

$employees = [];
try {
    $docs = get_cached_collection('Users', 600);
    foreach ($docs as $doc) {
        $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
        if (strpos($roleKey, 'probation') !== false) {
            $employees[] = [
                'uid' => $doc['uid'] ?? '',
                'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
                'email' => $doc['email'] ?? '',
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php supervisor_brand_head('My Employees · Performa'); ?>
</head>

<body>
    <div class="app-shell">
        <?php supervisor_render_shell('My Employees'); ?>

        <main class="main" id="employees">
            <?php
            ob_start();
            ?>
            <label class="search-bar">
                <span class="sr-only">Search employees</span>
                <span class="search-icon" aria-hidden="true"><?php echo supervisor_layout_icon('search'); ?></span>
                <input id="employeeSearch" type="search" placeholder="Search employees by name, role..." autocomplete="off" />
            </label>
            <?php
            $headerActions = ob_get_clean();

            $eyebrowHtml = '<span class="eyebrow">Directory</span>';
            supervisor_page_header(
                'employees-title',
                'My Employees',
                $eyebrowHtml,
                'Probationary employees, evaluation timelines, and weekly KPI scores.',
                $headerActions
            );
            ?>

            <section class="content-grid" style="grid-template-columns: 1fr; margin-top: 20px;">
                <div class="panel evaluations">
                    <div class="panel-header">
                        <div>
                            <h2>Assigned Employee List</h2>
                            <p>Live progress from Firestore. Employee profiles and company templates are managed by the Employer.</p>
                        </div>
                    </div>

                    <?php if (empty($employees)): ?>
                        <div class="empty-state" style="padding: 40px 20px; text-align: center;">
                            <p class="text-muted">No probationary employees found yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap" role="table" aria-label="Employees Directory">
                            <div class="table-head" role="row">
                                <span role="columnheader">Employee</span>
                                <span role="columnheader">Timeline</span>
                                <span role="columnheader">KPI Score</span>
                                <span role="columnheader">Status</span>
                                <span role="columnheader" style="text-align: right;">Action</span>
                            </div>
                            <div id="evaluationRows">
                                <?php foreach ($employees as $emp): ?>
                                    <?php
                                    $summary = employee_kpi_summary($allRatings, $emp['uid'], $emp['industry']);

                                    $daysIn = null;
                                    $daysLeft = null;
                                    $timelineText = 'No hire date on file';
                                    $progress = 0;
                                    $probationPeriodDays = max(1, (int) ($emp['probationPeriodDays'] ?? 180));
                                    $startDate = $emp['hireDate'] ?: $emp['createdAt'];
                                    if (!empty($startDate)) {
                                        try {
                                            $hire = new DateTime($startDate);
                                            $today = new DateTime('today');
                                            $daysIn = $today < $hire ? 0 : (int) $today->diff($hire)->format('%a');
                                            $daysLeft = max(0, $probationPeriodDays - $daysIn);
                                            $timelineText = "Day {$daysIn} · {$daysLeft} days left";
                                            $progress = min(100, (int) round(($daysIn / $probationPeriodDays) * 100));
                                        } catch (\Throwable $e) {
                                        }
                                    }

                                    if ($summary['hasData']) {
                                        $statusInfo = kpi_status_for_score($summary['score'], $summary['targetAvg']);
                                    } else {
                                        $statusInfo = ['status' => 'Not Yet Rated', 'statusClass' => 'status-neutral'];
                                    }

                                    $searchBlob = strtolower($emp['name'] . ' ' . $emp['email'] . ' ' . $emp['industry'] . ' ' . $statusInfo['status']);
                                    ?>
                                    <div class="table-row" role="row" data-search="<?php echo htmlspecialchars($searchBlob, ENT_QUOTES); ?>">
                                        <div class="employee-cell" role="cell">
                                            <div class="avatar-chip" aria-hidden="true">
                                                <?php echo htmlspecialchars(supervisor_avatar_initials($emp['name']), ENT_QUOTES); ?>
                                            </div>
                                            <div>
                                                <div class="employee-name font-semibold">
                                                    <?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?>
                                                </div>
                                                <div class="employee-role text-muted text-sm">
                                                    <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $emp['industry'])), ENT_QUOTES); ?>
                                                    <?php if (!empty($emp['email'])): ?>
                                                        · <span style="font-size: 11px; opacity: 0.8;"><?php echo htmlspecialchars($emp['email'], ENT_QUOTES); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="timeline-cell" role="cell">
                                            <div class="timeline-text text-sm">
                                                <?php echo htmlspecialchars($timelineText, ENT_QUOTES); ?>
                                            </div>
                                            <div class="timeline-bar" style="height: 6px; background: var(--ui-border, #e2e8f0); border-radius: 3px; overflow: hidden; margin-top: 4px;">
                                                <span style="display: block; height: 100%; width: <?php echo (int) $progress; ?>%; background: var(--ui-blue, #245fba); border-radius: 3px;"></span>
                                            </div>
                                        </div>
                                        <div class="score-cell" role="cell">
                                            <?php echo supervisor_score_meter($summary['score'], 5.0, $summary['targetAvg']); ?>
                                        </div>
                                        <div class="status-cell" role="cell">
                                            <span class="status-pill <?php echo htmlspecialchars($statusInfo['statusClass'], ENT_QUOTES); ?>">
                                                <?php echo htmlspecialchars($statusInfo['status'], ENT_QUOTES); ?>
                                            </span>
                                        </div>
                                        <div role="cell" style="text-align: right;">
                                            <a class="ghost-button" style="padding: 6px 14px; font-size: 13px;" href="ratings.php?employee=<?php echo urlencode($emp['uid']); ?>">
                                                Rate KPI
                                            </a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div id="noFilterMatches" hidden style="padding: 32px; text-align: center; color: var(--ui-muted, #64748b);">
                            No employees match your search query.
                        </div>
                    <?php endif; ?>

                    <div style="padding: 16px 20px; border-top: 1px solid var(--panel-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
                        <span class="text-sm text-muted">Showing <strong><?php echo count($employees); ?></strong> employees</span>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="<?php echo htmlspecialchars(supervisor_asset('script.js'), ENT_QUOTES); ?>"></script>
</body>

</html>