<?php
// Self-service password reset, step 2: consume the emailed token and choose
// a new password. The token is single-use, expires within the hour, and only
// its SHA-256 hash is ever stored. Choosing a password here proves email
// ownership, so mustChangePassword is cleared (unlike admin-rotated temps).

require_once __DIR__ . '/firebase_init.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/security_utils.php';
require_once __DIR__ . '/audit_log.php';
require_once __DIR__ . '/Employer/includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_csrf();

function reset_lookup(string $token)
{
    if (!preg_match('/^[0-9a-f]{64}$/', $token)) {
        return null;
    }

    try {
        $doc = firestore_get_document('PasswordResets', hash('sha256', $token));
    } catch (Throwable $e) {
        return null;
    }

    if (!is_array($doc) || !password_reset_token_valid($doc, time())) {
        return null;
    }

    return $doc;
}

$error = '';
$token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $doc = reset_lookup($token);

    if ($doc === null) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
        $token = '';
    } else {
        $newPassword = (string) ($_POST['new_password'] ?? '');
        $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
        $policyError = performa_password_policy_error($newPassword, $confirmPassword);

        if ($policyError !== null) {
            $error = $policyError;
        } else {
            try {
                $uid = (string) ($doc['uid'] ?? '');

                if ($uid === '') {
                    throw new RuntimeException('This reset link is invalid or has expired.');
                }

                $account = firestore_get_document('Users', $uid);

                if (!$account) {
                    throw new RuntimeException('This reset link is invalid or has expired.');
                }

                if (strtolower((string) ($account['status'] ?? 'Active')) === 'disabled') {
                    throw new RuntimeException('This account is disabled. Please contact your administrator.');
                }

                identitytoolkit_update_password($uid, $newPassword);
                $account['mustChangePassword'] = false;
                firestore_write_document('Users', $uid, $account);

                try {
                    $resetDoc = firestore_get_document(
                        'PasswordResets',
                        (string) ($doc['tokenHash'] ?? '')
                    );

                    if (is_array($resetDoc)) {
                        $resetDoc['usedAt'] = date('c');
                        firestore_write_document(
                            'PasswordResets',
                            (string) ($doc['tokenHash'] ?? ''),
                            $resetDoc
                        );
                    }
                } catch (Throwable $e) {
                    error_log('reset_password mark-used failed: ' . $e->getMessage());
                }

                record_audit_event(
                    'password_reset_completed',
                    'Password reset completed via emailed link',
                    ['uid' => $uid]
                );

                header('Location: login.php?reset=1');
                exit;
            } catch (Throwable $e) {
                $error = $e->getMessage();
            }
        }
    }
} else {
    $doc = reset_lookup($token);

    if ($doc === null) {
        $error = 'This reset link is invalid or has expired. Please request a new one.';
        $token = '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Choose a new password — Performa</title>
    <meta name="description" content="Choose a new Performa password." />
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23245fba'/%3E%3Cpath d='M16 53.3v-18.7M32 53.3V24M49.3 53.3V13.3' stroke='white' stroke-width='7' stroke-linecap='round' fill='none'/%3E%3C/svg%3E" />
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: "Geist", -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; color: #1f2940; background: #f3f5f8; line-height: 1.5; }
        .wrap { max-width: 460px; margin: 8vh auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #edf0f4; border-radius: 14px; box-shadow: 0 4px 16px rgba(20, 34, 54, .05); padding: 28px 26px; }
        h1 { margin: 0 0 6px; font-size: 22px; }
        .sub { margin: 0 0 18px; color: #66758a; font-size: 14px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input[type="password"] { width: 100%; min-height: 40px; padding: 9px 12px; font: inherit; font-size: 14px; border: 1px solid #dfe5ed; border-radius: 8px; }
        input[type="password"]:focus { outline: none; border-color: #245fba; box-shadow: 0 0 0 3px rgba(36, 95, 186, .14); }
        .field { margin-bottom: 12px; }
        button { width: 100%; margin-top: 6px; min-height: 40px; border: 0; border-radius: 8px; background: #245fba; color: #fff; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
        button:hover { background: #184a96; }
        .back { display: block; margin-top: 14px; text-align: center; font-size: 13px; color: #66758a; text-decoration: none; }
        .back:hover { color: #1f2940; }
        .error { background: #fdecea; border: 1px solid #f3c1bd; color: #8f1d17; border-radius: 8px; padding: 12px 14px; font-size: 13.5px; margin-bottom: 14px; }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <h1>Choose a new password</h1>

            <?php if ($token === ''): ?>
                <?php if ($error !== ''): ?>
                    <div class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
                <?php endif; ?>
                <a class="back" href="forgot_password.php">Request a new reset link</a>
                <a class="back" href="login.php">Back to sign in</a>
            <?php else: ?>
                <p class="sub">At least 8 characters. You'll sign in with this right away.</p>

                <?php if ($error !== ''): ?>
                    <div class="error" role="alert"><?php echo htmlspecialchars($error, ENT_QUOTES); ?></div>
                <?php endif; ?>

                <form method="post">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES); ?>" />
                    <div class="field">
                        <label for="new_password">New password</label>
                        <input id="new_password" name="new_password" type="password"
                            autocomplete="new-password" required minlength="8" />
                    </div>
                    <div class="field">
                        <label for="confirm_password">Confirm new password</label>
                        <input id="confirm_password" name="confirm_password" type="password"
                            autocomplete="new-password" required minlength="8" />
                    </div>
                    <button type="submit">Set new password</button>
                </form>
                <a class="back" href="login.php">Back to sign in</a>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
