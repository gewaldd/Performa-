<?php
$navItems = [
    ['label' => 'Overview', 'href' => 'probationary_employee_workstream.php', 'active' => false],
    ['label' => 'My Goals', 'href' => 'probationary_employee_goals.php', 'active' => false],
    ['label' => 'Feedback', 'href' => 'probationary_employee_feedback.php', 'active' => false],
    ['label' => 'Requests', 'href' => 'probationary_employee_requests.php', 'active' => false],
    ['label' => 'Profile', 'href' => 'probationary_employee_profile.php', 'active' => true],
];

$profileDetails = [
    ['label' => 'Name', 'value' => 'Juan Dela Cruz'],
    ['label' => 'Role', 'value' => 'Employee'],
    ['label' => 'Team', 'value' => 'Customer Success'],
    ['label' => 'Manager', 'value' => 'Maria Santos'],
    ['label' => 'Location', 'value' => 'Office 4B'],
    ['label' => 'Email', 'value' => 'juan.delacruz@example.com'],
];

$summary = [
    ['label' => 'Performance Score', 'value' => '4.6', 'badge' => 'Strong', 'tone' => 'neutral', 'variant' => 'mint', 'icon' => '▣'],
    ['label' => 'Goals On Track', 'value' => '3', 'badge' => 'Out of 4', 'tone' => 'positive', 'variant' => 'warm', 'icon' => '✓'],
    ['label' => 'Review Date', 'value' => 'Jun 18', 'badge' => '12 days left', 'tone' => 'warning', 'variant' => 'gold', 'icon' => '⌛'],
];

$insightTitle = 'Profile Snapshot';
$insightText = 'Your work is consistent, and your next checkpoint is approaching. Keep communication strong with your manager.';
$recommendation = 'Review your responsibilities and update your daily focus list.';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Profile</title>
    <meta name="description" content="Employee profile page for viewing personal and performance information." />
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
                        <div class="brand-subtitle">Employee Profile</div>
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
                    <div class="profile-name">Juan Dela Cruz</div>
                    <div class="profile-role">Employee</div>
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
                    <button class="icon-button" type="button" aria-label="Notifications">◌</button>
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

                    <div class="table-wrap">
                        <div class="table-head" role="row">
                            <span>Name</span>
                            <span>Value</span>
                        </div>
                        <?php foreach ($profileDetails as $detail): ?>
                            <div class="table-row">
                                <div class="employee-cell">
                                    <div class="avatar" style="background: linear-gradient(135deg, #6d8cff, #2f6df6);"></div>
                                    <div>
                                        <div class="employee-name"><?php echo htmlspecialchars($detail['label'], ENT_QUOTES); ?></div>
                                    </div>
                                </div>
                                <div class="timeline-cell">
                                    <div class="timeline-text"><?php echo htmlspecialchars($detail['value'], ENT_QUOTES); ?></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

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

                    <button class="primary-button" id="assignCourseButton" type="button"
                        data-completed-label="Updated"
                        data-confirm-text="Your profile action has been noted.">Update Profile</button>
                    <p class="microcopy">Use this page to keep your employee information accurate and aligned with your role.</p>
                </aside>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <span>Performa employee profile page</span>
        <span>PHP and CSS implementation</span>
    </footer>

    <script src="script.js"></script>
</body>

</html>
