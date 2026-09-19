<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../Employer/includes/collection_cache.php';
require_once __DIR__ . '/supervisor_layout.php';
require_login();
require_role('supervisor');
require_password_reset('settings.php');

$supervisorName = $_SESSION['name'] ?? 'Supervisor';

$notifications = [];
$unreadCount = 0;

try {
    $employees = [];
    foreach (get_cached_collection('Users', 600) as $doc) {
        $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
        if (strpos($roleKey, 'probation') !== false) {
            $employees[$doc['uid']] = [
                'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
                'uid' => $doc['uid'],
            ];
        }
    }

    $ratings = [];
    try {
        $ratings = get_cached_collection('Ratings', 600);
    } catch (\Throwable $e) {
    }

    $ratedUids = [];
    foreach ($ratings as $r) {
        $ratedUids[$r['employeeUid'] ?? ''] = true;
    }

    // Notification 1: employees who have never been rated.
    foreach ($employees as $uid => $emp) {
        if (empty($ratedUids[$uid])) {
            $notifications[] = [
                'title' => $emp['name'] . ' has not been rated yet',
                'detail' => 'Submit an initial weekly rating for this employee in Rating Entry.',
                'unread' => true,
                'targetUrl' => 'ratings.php?employee=' . urlencode($uid),
                'actionText' => 'Rate now',
                'time' => 'Action required',
            ];
            $unreadCount++;
        }
    }

    // Notification 2: most recent ratings submitted (last 5).
    usort($ratings, fn($a, $b) => strcmp($b['ratedAt'] ?? '', $a['ratedAt'] ?? ''));
    $recent = array_slice($ratings, 0, 5);
    foreach ($recent as $r) {
        $notifications[] = [
            'title' => 'Rating submitted for ' . ($r['employeeName'] ?? 'an employee'),
            'detail' => 'Rated by ' . ($r['ratedBy'] === ($_SESSION['uid'] ?? '') ? 'you' : ($r['ratedByRole'] ?? 'a teammate')) . ' on ' . (!empty($r['ratedAt']) ? date('M j, Y', strtotime($r['ratedAt'])) : '—'),
            'unread' => false,
            'targetUrl' => 'ratings.php?employee=' . urlencode($r['employeeUid'] ?? ''),
            'actionText' => 'View rating',
            'time' => !empty($r['ratedAt']) ? date('M j', strtotime($r['ratedAt'])) : 'Recent',
        ];
    }
} catch (\Throwable $e) {
}

// Update session badge
$_SESSION['pf_nav_unrated'] = $unreadCount;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php supervisor_brand_head('Notifications · Performa'); ?>
</head>

<body>
    <div class="app-shell">
        <?php supervisor_render_shell('Notifications'); ?>

        <main class="main" id="notifications">
            <?php
            ob_start();
            ?>
            <a class="btn-primary" href="ratings.php">
                <?php echo supervisor_layout_icon('target'); ?>
                <span>Rate Employee</span>
            </a>
            <?php
            $headerActions = ob_get_clean();

            $eyebrowHtml = '<span class="eyebrow">Activity Feed</span>';
            supervisor_page_header(
                'notifications-title',
                'Notifications',
                $eyebrowHtml,
                'Weekly rating alerts and evaluation activity for your assigned team.',
                $headerActions
            );
            ?>

            <section class="content-grid" style="grid-template-columns: 1fr; margin-top: 20px;">
                <div class="panel evaluations">
                    <div class="panel-header">
                        <div>
                            <h2>Recent Alerts & Activity</h2>
                            <p>Derived live from Firestore ratings and employee records.</p>
                        </div>
                        <?php if ($unreadCount > 0): ?>
                            <span class="status-pill status-warning" style="padding: 4px 10px; font-size: 12px;">
                                <?php echo $unreadCount; ?> unrated employee<?php echo $unreadCount === 1 ? '' : 's'; ?>
                            </span>
                        <?php endif; ?>
                    </div>

                    <?php if (empty($notifications)): ?>
                        <div class="empty-state" style="padding: 40px 20px; text-align: center;">
                            <p class="text-muted">No notifications at this time.</p>
                        </div>
                    <?php else: ?>
                        <div class="notif-list">
                            <?php foreach ($notifications as $notif): ?>
                                <div class="notif-row">
                                    <span class="notif-dot <?php echo $notif['unread'] ? '' : 'read'; ?>" aria-hidden="true"></span>
                                    <div class="notif-body">
                                        <div class="notif-title">
                                            <?php echo htmlspecialchars($notif['title'], ENT_QUOTES); ?>
                                        </div>
                                        <div class="notif-detail">
                                            <?php echo htmlspecialchars($notif['detail'], ENT_QUOTES); ?>
                                        </div>
                                    </div>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <span class="notif-meta font-mono"><?php echo htmlspecialchars($notif['time'], ENT_QUOTES); ?></span>
                                        <?php if (!empty($notif['targetUrl'])): ?>
                                            <a class="ghost-button" style="padding: 5px 12px; font-size: 12px;" href="<?php echo htmlspecialchars($notif['targetUrl'], ENT_QUOTES); ?>">
                                                <?php echo htmlspecialchars($notif['actionText'], ENT_QUOTES); ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div style="padding: 16px 20px; border-top: 1px solid var(--panel-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center;">
                        <span class="text-sm text-muted">Showing <strong><?php echo count($notifications); ?></strong> items</span>
                    </div>
                </div>
            </section>
        </main>
    </div>

    <script src="<?php echo htmlspecialchars(supervisor_asset('script.js'), ENT_QUOTES); ?>"></script>
</body>

</html>