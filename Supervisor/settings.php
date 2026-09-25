<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/supervisor_layout.php';
require_once __DIR__ . '/../Employer/includes/csrf.php';
require_login();
require_role('supervisor');
require_csrf();
require_password_reset('settings.php');

$supervisorUid = $_SESSION['uid'];
$supervisorName = $_SESSION['name'] ?? 'Supervisor';

$profile = null;
try {
    $profile = firestore_get_document('Users', $supervisorUid);
} catch (\Throwable $e) {
}

$message = '';
$messageIsError = false;

// Submit-name guard — any stray POST must not run password logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['changePassword'])) {
    $newPassword = (string) ($_POST['newPassword'] ?? '');
    $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

    // Single-sourced password policy validation
    if (($pwErr = performa_password_policy_error($newPassword, $confirmPassword)) !== null) {
        $message = $pwErr;
        $messageIsError = true;
    } else {
        try {
            identitytoolkit_update_password($supervisorUid, $newPassword);
            // Lift forced-reset flag if set
            try {
                $self = firestore_get_document('Users', $supervisorUid) ?? [];
                if (!empty($self['mustChangePassword'])) {
                    $self['mustChangePassword'] = false;
                    firestore_write_document('Users', $supervisorUid, $self);
                }
            } catch (\Throwable $e) {
                error_log('Supervisor settings mustChangePassword clear failed: ' . $e->getMessage());
            }
            unset($_SESSION['must_change_password']);
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
            $_SESSION['login_at'] = time();
            $message = 'Password updated successfully.';
        } catch (\Throwable $e) {
            error_log('Supervisor settings password update failed: ' . $e->getMessage());
            $message = performa_password_update_failure_message();
            $messageIsError = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php supervisor_brand_head('Settings · Performa'); ?>
</head>

<body>
    <div class="app-shell">
        <?php supervisor_render_shell('Settings'); ?>

        <main class="main" id="settings">
            <?php
            $eyebrowHtml = '<span class="eyebrow">Account</span>';
            supervisor_page_header(
                'settings-title',
                'Supervisor Settings',
                $eyebrowHtml,
                'Review your profile information and update your account security credentials.'
            );
            ?>

            <div class="settings-wrap" style="display: flex; justify-content: center; padding-top: 16px;">
                <div style="width: 100%; max-width: 520px;">
                    <?php if (!empty($_SESSION['must_change_password']) && !$message): ?>
                        <div class="alert alert-info" role="alert" style="margin-bottom: 20px;">
                            Your account is using a temporary password. Please set your own password below to continue — other pages remain locked until updated.
                        </div>
                    <?php endif; ?>

                    <?php if ($message): ?>
                        <div class="alert <?php echo $messageIsError ? 'alert-error' : 'alert-success'; ?>" role="status" style="margin-bottom: 20px;">
                            <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
                        </div>
                    <?php endif; ?>

                    <section class="settings-panel" style="padding: 28px;">
                        <div style="display: flex; align-items: center; gap: 16px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid var(--panel-border, #e2e8f0);">
                            <div class="avatar avatar-local" style="width: 52px; height: 52px; font-size: 18px; flex-basis: 52px;" aria-hidden="true">
                                <?php echo htmlspecialchars(supervisor_avatar_initials($profile['name'] ?? $supervisorName), ENT_QUOTES); ?>
                            </div>
                            <div>
                                <div style="font-size: 17px; font-weight: 700; color: var(--ui-text, #0f172a);">
                                    <?php echo htmlspecialchars($profile['name'] ?? $supervisorName, ENT_QUOTES); ?>
                                </div>
                                <div class="text-sm text-muted">Supervisor</div>
                            </div>
                        </div>

                        <div class="form-grid" style="display: flex; flex-direction: column; gap: 16px;">
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600; color: var(--ui-text, #0f172a);">Full Name</label>
                                <input type="text" value="<?php echo htmlspecialchars($profile['name'] ?? $supervisorName, ENT_QUOTES); ?>" disabled class="perform-input" style="background: var(--ui-soft, #f8fafc);" />
                            </div>
                            <div class="form-group">
                                <label style="font-size: 13px; font-weight: 600; color: var(--ui-text, #0f172a);">Email Address</label>
                                <input type="email" value="<?php echo htmlspecialchars($profile['email'] ?? '—', ENT_QUOTES); ?>" disabled class="perform-input" style="background: var(--ui-soft, #f8fafc);" />
                            </div>
                        </div>

                        <hr class="section-divider" style="margin: 24px 0;" />

                        <div style="margin-bottom: 18px;">
                            <h3 style="font-size: 15px; font-weight: 700; margin: 0 0 4px; color: var(--ui-text, #0f172a);">Change Password</h3>
                            <p class="microcopy" style="margin: 0;">Name and email are managed by your Employer. You may update your account password below.</p>
                        </div>

                        <form method="post" class="form-grid" style="display: flex; flex-direction: column; gap: 16px;">
                            <?php echo csrf_field(); ?>
                            <div class="form-group">
                                <label for="newPassword" style="font-size: 13px; font-weight: 600;">New Password</label>
                                <div style="display: flex; gap: 8px; align-items: stretch;">
                                    <input id="newPassword" name="newPassword" type="password" minlength="8" required class="perform-input" placeholder="At least 8 characters" autocomplete="new-password" style="flex: 1; min-width: 0;" />
                                    <button class="ghost-button" type="button" data-pw-toggle="newPassword" aria-pressed="false" style="flex: none;">Show</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label for="confirmPassword" style="font-size: 13px; font-weight: 600;">Confirm New Password</label>
                                <div style="display: flex; gap: 8px; align-items: stretch;">
                                    <input id="confirmPassword" name="confirmPassword" type="password" minlength="8" required class="perform-input" placeholder="Repeat new password" autocomplete="new-password" style="flex: 1; min-width: 0;" />
                                    <button class="ghost-button" type="button" data-pw-toggle="confirmPassword" aria-pressed="false" style="flex: none;">Show</button>
                                </div>
                            </div>
                            <div style="margin-top: 8px;">
                                <button class="btn-primary" style="width: 100%; justify-content: center;" type="submit" name="changePassword" value="1">
                                    Update Password
                                </button>
                            </div>
                        </form>
                    </section>
                </div>
            </div>
        </main>
    </div>

    <script src="<?php echo htmlspecialchars(supervisor_asset('script.js'), ENT_QUOTES); ?>"></script>
</body>

</html>