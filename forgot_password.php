<?php
// Self-service password reset, step 1: request a reset link.
//
// Anyone (logged out) may ask for a link for any address, but the response
// is ALWAYS the same generic message — the page never reveals whether an
// account exists, is disabled, or was emailed. Token creation is throttled
// to one live token per address per 15 minutes.

require_once __DIR__ . '/firebase_init.php';
require_once __DIR__ . '/security_utils.php';
require_once __DIR__ . '/mailer.php';
require_once __DIR__ . '/audit_log.php';
require_once __DIR__ . '/Employer/includes/csrf.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_csrf();

$sent = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
        try {
            $user = null;
            $uid = '';

            foreach (firestore_list_documents('Users') as $doc) {
                if (strtolower(trim((string) ($doc['email'] ?? ''))) === $email) {
                    $user = $doc;
                    $uid = (string) ($doc['uid'] ?? '');
                    break;
                }
            }

            if (
                $user !== null && $uid !== '' &&
                strtolower((string) ($user['status'] ?? 'Active')) !== 'disabled'
            ) {
                $now = time();
                $recent = false;

                foreach (firestore_list_documents('PasswordResets') as $tok) {
                    if (strtolower(trim((string) ($tok['email'] ?? ''))) !== $email) {
                        continue;
                    }

                    if (!empty($tok['usedAt'])) {
                        continue;
                    }

                    $created = strtotime((string) ($tok['createdAt'] ?? ''));

                    if ($created !== false && ($now - $created) < 900) {
                        $recent = true;
                        break;
                    }
                }

                if (!$recent) {
                    $pair = generate_password_reset_token();

                    firestore_write_document('PasswordResets', $pair['hash'], [
                        'tokenHash' => $pair['hash'],
                        'uid' => $uid,
                        'email' => $email,
                        'createdAt' => date('c', $now),
                        'expiresAt' => date('c', $now + 3600),
                        'usedAt' => null,
                    ]);

                    $resetUrl = app_base_url() . '/reset_password.php?token=' . $pair['token'];

                    send_transactional_email(
                        $email,
                        $user['name'] ?? $email,
                        'Reset your Performa password',
                        password_reset_link_email_html($user['name'] ?? $email, $resetUrl)
                    );
                }

                record_audit_event(
                    'password_reset_requested',
                    'Password reset requested',
                    ['uid' => $uid]
                );
            }
        } catch (Throwable $e) {
            error_log('forgot_password failed: ' . $e->getMessage());
        }
    }

    // Generic either way: success, unknown address, invalid input and even
    // throttling all render the same message below.
    $sent = true;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>Forgot password — Performa</title>
    <meta name="description" content="Request a Performa password reset link." />
    <link rel="icon" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 64 64'%3E%3Crect width='64' height='64' rx='14' fill='%23245fba'/%3E%3Cpath d='M16 53.3v-18.7M32 53.3V24M49.3 53.3V13.3' stroke='white' stroke-width='7' stroke-linecap='round' fill='none'/%3E%3C/svg%3E" />
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; font-family: -apple-system, BlinkMacSystemFont, "SF Pro Text", "IBM Plex Sans", "Segoe UI", sans-serif; color: #1f2940; background: #f3f5f8; line-height: 1.5; }
        .wrap { max-width: 460px; margin: 8vh auto; padding: 0 16px; }
        .card { background: #fff; border: 1px solid #edf0f4; border-radius: 14px; box-shadow: 0 4px 16px rgba(20, 34, 54, .05); padding: 28px 26px; }
        h1 { margin: 0 0 6px; font-size: 22px; }
        .sub { margin: 0 0 18px; color: #66758a; font-size: 14px; }
        label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 6px; }
        input[type="email"] { width: 100%; min-height: 40px; padding: 9px 12px; font: inherit; font-size: 14px; border: 1px solid #dfe5ed; border-radius: 8px; }
        input[type="email"]:focus { outline: none; border-color: #245fba; box-shadow: 0 0 0 3px rgba(36, 95, 186, .14); }
        button { width: 100%; margin-top: 14px; min-height: 40px; border: 0; border-radius: 8px; background: #245fba; color: #fff; font: inherit; font-size: 14px; font-weight: 600; cursor: pointer; }
        button:hover { background: #184a96; }
        .back { display: block; margin-top: 14px; text-align: center; font-size: 13px; color: #66758a; text-decoration: none; }
        .back:hover { color: #1f2940; }
        .note { background: #e6f4ec; border: 1px solid #bfe3cf; color: #0e5c38; border-radius: 8px; padding: 12px 14px; font-size: 13.5px; }
    </style>
</head>

<body>
    <div class="wrap">
        <div class="card">
            <h1>Forgot password</h1>

            <?php if ($sent): ?>
                <div class="note" role="status">
                    If an account exists for that email, a reset link is on its way.
                    It works once and expires within the hour.
                </div>
                <a class="back" href="login.php">Back to sign in</a>
            <?php else: ?>
                <p class="sub">Enter your account email and we'll send you a one-time reset link.</p>
                <form method="post">
                    <?php echo csrf_field(); ?>
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" autocomplete="email"
                        placeholder="you@company.com" required />
                    <button type="submit">Send reset link</button>
                </form>
                <a class="back" href="login.php">Back to sign in</a>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>
