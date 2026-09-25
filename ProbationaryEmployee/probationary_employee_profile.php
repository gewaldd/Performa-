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

// Forced-reset escape hatch. data.php's require_password_reset() bounces every
// navigation click from a flagged account to this page, so this page is the
// only one such a user can reach — the password form below is what releases
// the gate. Mirrors Employer/settings.php's change_password branch, except for
// the "fresh login" window: a freshness gate here would re-trap the user with
// nowhere left to go, so it is deliberately omitted.
$passwordMessage = '';
$passwordMessageTone = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changePassword'])) {
    $newPassword = (string) ($_POST['newPassword'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

    // Single-sourced policy: floor + mismatch strings live in root auth.php.
    // The no-freshness-window flow documented above is unchanged.
    if (($pwErr = performa_password_policy_error($newPassword, $confirmPassword)) !== null) {
        $passwordMessage = $pwErr;
        $passwordMessageTone = 'error';
    } else {
        try {
            identitytoolkit_update_password($currentUserUid, $newPassword);

            // Password is now user-chosen: lift the forced-reset flag both in
            // Firestore (source of truth, read at next login) and in the live
            // session (so the redirect gate releases immediately). Called
            // directly here — never routed through another module's file.
            try {
                $self = firestore_get_document('Users', $currentUserUid) ?? [];
                if (!empty($self['mustChangePassword'])) {
                    $self['mustChangePassword'] = false;
                    firestore_write_document('Users', $currentUserUid, $self);
                }
            } catch (Throwable $e) {
                error_log('Probationary profile mustChangePassword clear failed: ' . $e->getMessage());
            }

            unset($_SESSION['must_change_password']);
            // Fresh session ID after a credential change (fixation defense).
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['login_at'] = time();

            $passwordMessage = 'Password updated. Your account is no longer limited to this page.';
            $passwordMessageTone = 'success';
        } catch (Throwable $e) {
            error_log('Probationary profile password update failed: ' . $e->getMessage());
            $passwordMessage = performa_password_update_failure_message();
            $passwordMessageTone = 'error';
        }
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

                </div>
            </header>

            <section class="hero">
                <p class="eyebrow">Profile</p>
                <h1>See your employee details, performance snapshot, and upcoming review plan.</h1>
            </section>

            <?php if (!empty($_SESSION['must_change_password']) && $passwordMessage === ''): ?>
                <div class="alert-banner">
                    Your account is using a temporary password. Please set your own password below to continue —
                    the rest of the dashboard stays locked until you do.
                </div>
            <?php endif; ?>

            <section class="content-grid">
                <div class="panel evaluations" style="grid-column: 1 / -1;">
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

                <div class="panel evaluations" style="grid-column: 1 / -1;" id="changePassword">
                    <div class="panel-header">
                        <div>
                            <h2>Change Password</h2>
                            <p>Set your own password to replace the temporary one issued to you.</p>
                        </div>
                    </div>

                    <?php if ($passwordMessage !== ''): ?>
                        <div class="alert-banner<?php echo $passwordMessageTone === 'error' ? ' error' : ''; ?>">
                            <?php echo htmlspecialchars($passwordMessage, ENT_QUOTES); ?>
                        </div>
                    <?php endif; ?>

                    <form class="profile-form" method="post">
                        <div class="form-grid">
                            <div class="field-group">
                                <label for="newPassword">New Password</label>
                                <input id="newPassword" name="newPassword" type="password" autocomplete="new-password"
                                    minlength="8" required placeholder="At least 8 characters" />
                            </div>
                            <div class="field-group">
                                <label for="confirmPassword">Confirm New Password</label>
                                <input id="confirmPassword" name="confirmPassword" type="password"
                                    autocomplete="new-password" minlength="8" required placeholder="Repeat password" />
                            </div>
                        </div>

                        <div class="profile-footer">
                            <p class="microcopy">Choose a password only you know. It replaces the temporary password
                                issued to you and unlocks the rest of your dashboard.</p>
                            <button class="primary-button" type="submit" name="changePassword" value="1">Update
                                Password</button>
                        </div>
                    </form>
                </div>

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