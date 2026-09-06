<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_login();
require_role('supervisor');

$supervisorUid = $_SESSION['uid'];
$supervisorName = $_SESSION['name'] ?? 'Supervisor';

$navItems = [
    ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'active' => false],
    ['label' => 'My Employees', 'href' => 'employees.php', 'active' => false],
    ['label' => 'Rating Entry', 'href' => 'ratings.php', 'active' => false],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => true],
    ['label' => 'Notifications', 'href' => 'notifications.php', 'active' => false],
];

$profile = null;
try {
    $profile = firestore_get_document('Users', $supervisorUid);
} catch (\Throwable $e) {
}

$message = '';
$messageIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newPassword = $_POST['newPassword'] ?? '';
    $confirmPassword = $_POST['confirmPassword'] ?? '';

    if (strlen($newPassword) < 6) {
        $message = 'Password must be at least 6 characters.';
        $messageIsError = true;
    } elseif ($newPassword !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageIsError = true;
    } else {
        try {
            identitytoolkit_update_password($supervisorUid, $newPassword);
            $message = 'Password updated successfully.';
        } catch (\Throwable $e) {
            $message = 'Failed to update password: ' . $e->getMessage();
            $messageIsError = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Settings</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        .settings-wrap {
            display: flex;
            justify-content: center;
            padding-top: 8px;
        }

        .settings-card {
            width: 100%;
            max-width: 460px;
            padding: 32px;
        }

        .settings-avatar-row {
            display: flex;
            align-items: center;
            gap: 14px;
            margin-bottom: 22px;
            padding-bottom: 22px;
            border-bottom: 1px solid var(--panel-border);
        }

        .settings-avatar-row .big-avatar {
            width: 52px;
            height: 52px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary, #2f6df6), #6c8cff);
            color: #fff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }

        .settings-avatar-row .name {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
        }

        .settings-avatar-row .role {
            font-size: 12.5px;
            color: var(--muted);
        }

        .settings-field {
            margin-bottom: 16px;
        }

        .settings-field label {
            display: block;
            font-size: 11.5px;
            font-weight: 700;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.03em;
            margin-bottom: 6px;
        }

        .settings-field input {
            width: 100%;
            border: 1px solid var(--panel-border);
            border-radius: 10px;
            padding: 12px 14px;
            font-size: 14px;
            font-family: inherit;
            color: var(--text);
            outline: none;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .settings-field input:disabled {
            background: #f4f6fa;
            color: var(--muted);
            cursor: not-allowed;
        }

        .settings-field input:not(:disabled) {
            background: #fbfcfe;
        }

        .settings-field input:not(:disabled):focus {
            border-color: var(--primary, #2f6df6);
            box-shadow: 0 0 0 3px rgba(47, 109, 246, 0.12);
        }

        .settings-section-title {
            font-size: 16px;
            font-weight: 700;
            color: var(--text);
            margin: 0 0 4px;
        }

        .settings-section-hint {
            font-size: 12.5px;
            color: var(--muted);
            margin: 0 0 18px;
        }

        .settings-divider {
            border: none;
            border-top: 1px solid var(--panel-border);
            margin: 24px 0;
        }

        .settings-submit {
            width: 100%;
            margin-top: 4px;
        }
    </style>
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
            <section class="hero" style="text-align:center;">
                <p class="eyebrow">Settings</p>
                <h1>Your account details.</h1>
            </section>

            <div class="settings-wrap">
                <div style="width:100%; max-width:460px;">
                    <?php if ($message): ?>
                        <div
                            style="margin-bottom:16px;padding:12px 16px;border-radius:10px;border:1px solid <?php echo $messageIsError ? '#e2483d' : '#16a76d'; ?>;background:<?php echo $messageIsError ? 'rgba(226,72,61,0.08)' : 'rgba(22,167,109,0.08)'; ?>;color:<?php echo $messageIsError ? '#c94a3f' : '#16a76d'; ?>;font-size:13px;">
                            <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
                        </div>
                    <?php endif; ?>

                    <section class="panel settings-card">
                        <div class="settings-avatar-row">
                            <div class="big-avatar"><?php echo strtoupper(substr($supervisorName, 0, 2)); ?></div>
                            <div>
                                <div class="name"><?php echo htmlspecialchars($profile['name'] ?? $supervisorName, ENT_QUOTES); ?>
                                </div>
                                <div class="role">Shift Supervisor</div>
                            </div>
                        </div>

                        <div class="settings-field">
                            <label>Full Name</label>
                            <input type="text"
                                value="<?php echo htmlspecialchars($profile['name'] ?? '—', ENT_QUOTES); ?>" disabled />
                        </div>
                        <div class="settings-field">
                            <label>Email</label>
                            <input type="email"
                                value="<?php echo htmlspecialchars($profile['email'] ?? '—', ENT_QUOTES); ?>" disabled />
                        </div>

                        <hr class="settings-divider" />

                        <p class="settings-section-title">Change Password</p>
                        <p class="settings-section-hint">Name and email cannot be changed here — contact your
                            Employer if these need to be updated.</p>

                        <form method="post">
                            <div class="settings-field">
                                <label for="newPassword">New Password</label>
                                <input id="newPassword" name="newPassword" type="password" minlength="6" required />
                            </div>
                            <div class="settings-field">
                                <label for="confirmPassword">Confirm New Password</label>
                                <input id="confirmPassword" name="confirmPassword" type="password" minlength="6"
                                    required />
                            </div>
                            <button class="primary-button settings-submit" type="submit">Update Password</button>
                        </form>
                    </section>
                </div>
            </div>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>