<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../Employer/includes/collection_cache.php';
require_once __DIR__ . '/supervisor_layout.php';
require_login();
require_role('supervisor');
require_password_reset('settings.php');

$supervisorName = $_SESSION['name'] ?? 'Supervisor';

$reports = [];
try {
    $reportDocs = get_cached_collection('Reports', 600);
    usort($reportDocs, fn($a, $b) => strcmp($b['generatedAt'] ?? '', $a['generatedAt'] ?? ''));
    $reports = $reportDocs;
} catch (\Throwable $e) {
    // leave $reports empty if the collection doesn't exist yet
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php supervisor_brand_head('Reports · Performa'); ?>
</head>

<body>
    <div class="app-shell">
        <?php supervisor_render_shell('Reports'); ?>

        <main class="main" id="reports">
            <?php
            $eyebrowHtml = '<span class="eyebrow">Documentation</span>';
            supervisor_page_header(
                'reports-title',
                'Performance Reports',
                $eyebrowHtml,
                'View performance summaries and evaluation records generated for your assigned employees.'
            );
            ?>

            <section class="content-grid" style="grid-template-columns: 1fr; margin-top: 20px;">
                <div class="panel evaluations">
                    <div class="panel-header">
                        <div>
                            <h2>Generated Reports</h2>
                            <p>Live documentation from Firestore. Formal reports and exports are initiated by the Employer.</p>
                        </div>
                    </div>

                    <?php if (empty($reports)): ?>
                        <div class="empty-state" style="padding: 40px 20px; text-align: center;">
                            <p class="text-muted">No reports have been generated yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-wrap" role="table" aria-label="Reports Directory">
                            <div class="table-head" role="row">
                                <span role="columnheader">Employee</span>
                                <span role="columnheader">Report Type</span>
                                <span role="columnheader">Industry</span>
                                <span role="columnheader">Date Generated</span>
                            </div>
                            <div id="evaluationRows">
                                <?php foreach ($reports as $r): ?>
                                    <div class="table-row" role="row">
                                        <div class="employee-cell" role="cell">
                                            <div class="avatar-chip" aria-hidden="true">
                                                <?php echo htmlspecialchars(supervisor_avatar_initials($r['employeeName'] ?? 'Unknown'), ENT_QUOTES); ?>
                                            </div>
                                            <div>
                                                <div class="employee-name font-semibold">
                                                    <?php echo htmlspecialchars($r['employeeName'] ?? 'Unknown', ENT_QUOTES); ?>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="timeline-cell" role="cell">
                                            <span class="status-pill status-good">
                                                <?php echo htmlspecialchars($r['reportTypeLabel'] ?? 'Performance Report', ENT_QUOTES); ?>
                                            </span>
                                        </div>
                                        <div class="timeline-cell" role="cell">
                                            <span class="dept-pill">
                                                <?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $r['industry'] ?? 'General')), ENT_QUOTES); ?>
                                            </span>
                                        </div>
                                        <div class="timeline-cell text-sm text-muted font-mono" role="cell">
                                            <?php echo !empty($r['generatedAt']) ? date('M j, Y', strtotime($r['generatedAt'])) : '—'; ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <div style="padding: 16px 20px; border-top: 1px solid var(--panel-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
                        <span class="text-sm text-muted">Total: <strong><?php echo count($reports); ?></strong> report<?php echo count($reports) === 1 ? '' : 's'; ?></span>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="<?php echo htmlspecialchars(supervisor_asset('script.js'), ENT_QUOTES); ?>"></script>
</body>

</html>