<?php
require_once __DIR__ . '/data.php';

$currentUserUid = probationary_uid();
$user = probationary_user();
$profileUpdated = false;
$profile = [
    'fullName' => $user['name'] ?? '',
    'email' => $user['email'] ?? '',
    'phone' => $user['phone'] ?? '',
    'address' => $user['address'] ?? $user['office'] ?? $user['location'] ?? '',
    'mentor' => $user['supervisorName'] ?? '',
    'emergencyContact' => $user['emergencyContact'] ?? '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['saveProfile'])) {
    $profile['fullName'] = trim($_POST['fullName'] ?? $profile['fullName']);
    $profile['email'] = trim($_POST['email'] ?? $profile['email']);
    $profile['phone'] = trim($_POST['phone'] ?? $profile['phone']);
    $profile['address'] = trim($_POST['address'] ?? $profile['address']);
    $profile['emergencyContact'] = trim($_POST['emergencyContact'] ?? $profile['emergencyContact']);

    try {
        firestore_write_document('Users', $currentUserUid, [
            'name' => $profile['fullName'],
            'email' => $profile['email'],
            'phone' => $profile['phone'],
            'address' => $profile['address'],
            'emergencyContact' => $profile['emergencyContact'],
        ]);
        $profileUpdated = true;
        $user = probationary_user();
        $profile['fullName'] = $user['name'] ?? $profile['fullName'];
        $profile['email'] = $user['email'] ?? $profile['email'];
        $profile['phone'] = $user['phone'] ?? $profile['phone'];
        $profile['address'] = $user['address'] ?? $user['office'] ?? $user['location'] ?? $profile['address'];
        $profile['mentor'] = $user['supervisorName'] ?? $profile['mentor'];
        $profile['emergencyContact'] = $user['emergencyContact'] ?? $profile['emergencyContact'];
    } catch (Throwable $e) {
        $profileUpdated = false;
    }
}

$evaluations = probationary_owned_documents('evaluations');
$ratings = probationary_owned_documents('Ratings');
$latestEvaluation = $evaluations[0] ?? $ratings[0] ?? [];
$latestScore = probationary_evaluation_score($latestEvaluation);
$navItems = [
    ['label' => 'Overview', 'href' => 'probationary_employee_dashboard.php', 'active' => false],
    ['label' => 'Profile', 'href' => 'probationary_employee_profile.php', 'active' => true],
    ['label' => 'Performance', 'href' => 'probationary_employee_dashboard.php#performance', 'active' => false],
    ['label' => 'Acknowledgements', 'href' => 'probationary_employee_dashboard.php#acknowledgements', 'active' => false],
    ['label' => 'Notifications', 'href' => 'probationary_employee_dashboard.php#notifications', 'active' => false],
];

$profileDetails = [
    ['label' => 'Name', 'value' => $profile['fullName'] ?: ($user['email'] ?? '')],
    ['label' => 'Role', 'value' => probationary_role_label($user)],
    ['label' => 'Team', 'value' => $user['department'] ?? ''],
    ['label' => 'Manager', 'value' => $profile['mentor'] ?: ($user['supervisorName'] ?? '')],
    ['label' => 'Address', 'value' => $profile['address'] ?: ($user['address'] ?? $user['office'] ?? $user['location'] ?? '')],
    ['label' => 'Email', 'value' => $profile['email'] ?: ($user['email'] ?? '')],
];

$summary = [
    ['label' => 'Performance Score', 'value' => $latestScore === null ? '-' : number_format($latestScore, 1), 'badge' => 'Current', 'tone' => 'neutral', 'variant' => 'mint', 'icon' => '▣'],
    ['label' => 'Goals On Track', 'value' => (string) count(array_filter(probationary_owned_documents('goals'), static fn(array $goal): bool => strtolower((string) ($goal['status'] ?? '')) === 'on track')), 'badge' => 'Current', 'tone' => 'positive', 'variant' => 'warm', 'icon' => '✓'],
    ['label' => 'Review Date', 'value' => probationary_date($user['reviewDate'] ?? null, 'Not scheduled'), 'badge' => 'Upcoming', 'tone' => 'warning', 'variant' => 'gold', 'icon' => '⌛'],
];

$insightTitle = 'Profile Snapshot';
$insightText = $latestEvaluation['notes'] ?? 'No performance insight has been recorded yet.';
$recommendation = $user['profileRecommendation'] ?? 'Keep your contact and role information current.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Profile</title>
    <meta name="description"
        content="Probationary employee profile page for viewing personal and performance information." />
    <link rel="stylesheet" href="styles.css" />
    <link rel="stylesheet" href="../ui-refresh.css" />
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div>
                <div class="brand">
                    <div class="brand-mark">P</div>
                    <div>
                        <div class="brand-name">Performa</div>
                        <div class="brand-subtitle">Probationary Employee Profile</div>
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
                    <div class="profile-name">
                        <?php echo htmlspecialchars($user['name'] ?? $user['email'] ?? '', ENT_QUOTES); ?>
                    </div>
                    <div class="profile-role">Probationary Employee</div>
                    <a class="logout-link" href="../logout.php" aria-label="Sign out">Sign out</a>
                </div>
            </div>
        </aside>

        <main class="main" id="dashboard">
            <header class="topbar">
                <label class="search-bar" aria-label="Search profile details">
                    <span class="search-icon">⌕</span>
                    <input id="dashboardSearch" type="search" placeholder="Search profile fields..." />
                </label>

                <div class="topbar-actions">
                    <div class="deadline-pill">Complete your profile update</div>
                    <button class="icon-button" type="button" aria-label="Notifications">Notifications</button>
                </div>
            </header>

            <section class="hero">
                <p class="eyebrow">Profile</p>
                <h1>See your employee details, performance snapshot, and upcoming review plan.</h1>
            </section>

            <section class="metrics" aria-label="Profile summary metrics">
                <?php foreach ($summary as $metric): ?>
                    <article class="metric-card <?php echo htmlspecialchars($metric['variant'], ENT_QUOTES); ?>">
                        <div class="metric-icon"><?php echo htmlspecialchars($metric['icon'], ENT_QUOTES); ?></div>
                        <div class="metric-meta">
                            <span><?php echo htmlspecialchars($metric['label'], ENT_QUOTES); ?></span>
                            <strong><?php echo htmlspecialchars($metric['value'], ENT_QUOTES); ?></strong>
                        </div>
                        <div class="metric-badge <?php echo htmlspecialchars($metric['tone'], ENT_QUOTES); ?>">
                            <?php echo htmlspecialchars($metric['badge'], ENT_QUOTES); ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>

            <section class="content-grid">
                <div class="panel evaluations">
                    <div class="panel-header">
                        <div>
                            <h2>Personal Details</h2>
                            <p>Your profile information and role details are listed here.</p>
                        </div>
                    </div>

                    <?php if ($profileUpdated): ?>
                        <div class="alert-banner">Profile updated.</div>
                    <?php endif; ?>

                    <form id="profileForm" class="profile-form" method="post">
                        <div class="form-grid">
                            <div class="field-group">
                                <label for="fullName">Full Name</label>
                                <input id="fullName" name="fullName" type="text"
                                    value="<?php echo htmlspecialchars($profile['fullName'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group">
                                <label for="email">Email</label>
                                <input id="email" name="email" type="email"
                                    value="<?php echo htmlspecialchars($profile['email'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group">
                                <label for="phone">Phone</label>
                                <input id="phone" name="phone" type="tel"
                                    value="<?php echo htmlspecialchars($profile['phone'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group">
                                <label for="address">Address</label>
                                <input id="address" name="address" type="text"
                                    value="<?php echo htmlspecialchars($profile['address'], ENT_QUOTES); ?>" />
                            </div>
                            <div class="field-group field-full">
                                <label for="emergencyContact">Emergency Contact</label>
                                <input id="emergencyContact" name="emergencyContact" type="text"
                                    value="<?php echo htmlspecialchars($profile['emergencyContact'], ENT_QUOTES); ?>" />
                            </div>
                        </div>

                        <div class="profile-footer">
                            <div class="readonly-panel">
                                <h3>Role Details</h3>
                                <?php foreach ($profileDetails as $detail): ?>
                                    <div class="readonly-row">
                                        <span><?php echo htmlspecialchars($detail['label'], ENT_QUOTES); ?></span>
                                        <strong><?php echo htmlspecialchars($detail['value'], ENT_QUOTES); ?></strong>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <button class="primary-button" type="submit" name="saveProfile">Save changes</button>
                        </div>
                    </form>

                    <a class="view-more" href="probationary_employee_goals.php">Back to Goals →</a>
                </div>

                <aside class="insight-card">
                    <div class="insight-badge">AI INSIGHT</div>
                    <h2><?php echo htmlspecialchars($insightTitle, ENT_QUOTES); ?></h2>
                    <p><?php echo htmlspecialchars($insightText, ENT_QUOTES); ?></p>

                    <div class="recommendation-box">
                        <div class="recommendation-label">Recommended Action</div>
                        <strong><?php echo htmlspecialchars($recommendation, ENT_QUOTES); ?></strong>
                    </div>

                    <button class="primary-button" id="assignCourseButton" type="submit" form="profileForm"
                        data-completed-label="Updated" data-confirm-text="Your profile action has been noted.">Update Profile</button>
                    <p class="microcopy">Use this page to keep your employee information accurate and aligned with your
                        role.</p>
                </aside>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <span>Performa probationary employee profile page</span>
        <span>PHP and CSS implementation</span>
    </footer>

    <script src="script.js"></script>
</body>

</html>