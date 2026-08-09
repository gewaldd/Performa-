<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';

require_login();
require_role('admin');

$message = '';
$message_type = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $role = $_POST['role'] ?? '';
    $password = trim($_POST['password'] ?? '');

    if (!$name || !$email || !$role) {
        $message = 'Name, email and role are required.';
    } else {
        if (!$password)
            $password = bin2hex(random_bytes(6));
        try {
            $uid = identitytoolkit_create_user($name, $email, $password);
            firestore_write_document('Users', $uid, [
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'createdAt' => date('c'),
                'createdBy' => $_SESSION['uid'] ?? null,
            ]);

            $message = "User created for $name. Temporary password: $password";
            $message_type = 'success';
        } catch (\Throwable $e) {
            $err = $e->getMessage();
            // Friendly handling for existing accounts created in Auth
            if (stripos($err, 'EMAIL_EXISTS') !== false || stripos($err, 'existing user id') !== false || stripos($err, 'localId is missing') !== false) {
                $message = "Warning: An account for " . htmlspecialchars($name) . " (" . htmlspecialchars($email) . ") already exists.";
                $message_type = 'warning';
            } else {
                $message = 'Error: ' . $err;
                $message_type = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Create User — Admin</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        .account-form {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            align-items: start;
        }

        .account-form .form-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .account-form label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
        }

        .account-form input,
        .account-form select {
            border: 1px solid var(--panel-border);
            border-radius: 12px;
            padding: 10px 12px;
            font-size: 14px;
            background: #fbfcfe;
            color: var(--text);
            width: 100%;
            box-sizing: border-box;
        }

        .account-form .full-width {
            grid-column: 1 / -1;
        }

        .form-actions {
            margin-top: 12px;
        }
    </style>
</head>

<body>
    <div class="app-shell" style="grid-template-columns:1fr;">
        <main class="main" style="padding:28px;">
            <div class="panel" style="max-width:820px;margin:0 auto;padding:22px;border-radius:18px;">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <div>
                        <a href="accounts.php" style="color:var(--muted);text-decoration:none;">← Back</a>
                        <h1 style="margin:8px 0 0;">Create User</h1>
                        <div style="color:var(--muted);font-size:13px;margin-top:6px;">Create login and profile for a
                            new user.</div>
                    </div>
                </div>

                <?php if ($message): ?>
                    <?php
                    $cls = 'message';
                    $icon = '';
                    if ($message_type === 'success') {
                        $cls .= ' message-success';
                        $icon = '✓';
                    } elseif ($message_type === 'warning') {
                        $cls .= ' message-warning';
                        $icon = '⚠';
                    } elseif ($message_type === 'error') {
                        $cls .= ' message-error';
                        $icon = '✖';
                    }
                    ?>
                    <div class="<?php echo $cls; ?>"
                        style="margin:8px 0 16px;padding:12px;border-radius:10px;display:flex;align-items:center;gap:12px;">
                        <div class="icon" aria-hidden="true"><?php echo $icon; ?></div>
                        <div class="message-text" style="font-weight:600;"><?php echo htmlspecialchars($message); ?></div>
                    </div>
                    <style>
                        .message {
                            align-items: center;
                        }

                        .message .icon {
                            width: 40px;
                            height: 40px;
                            display: grid;
                            place-items: center;
                            border-radius: 8px;
                            color: #fff;
                            font-weight: 700;
                            font-size: 18px;
                        }

                        .message-success {
                            background: linear-gradient(180deg, rgba(22, 167, 109, 0.08), rgba(22, 167, 109, 0.03));
                            border: 1px solid rgba(22, 167, 109, 0.18);
                            color: var(--positive);
                        }

                        .message-success .icon {
                            background: linear-gradient(135deg, var(--positive), #0f9a5e);
                        }

                        .message-warning {
                            background: linear-gradient(180deg, rgba(235, 158, 33, 0.08), rgba(235, 158, 33, 0.03));
                            border: 1px solid rgba(235, 158, 33, 0.18);
                            color: var(--warning);
                        }

                        .message-warning .icon {
                            background: linear-gradient(135deg, var(--warning), #d37a00);
                        }

                        .message-error {
                            background: linear-gradient(180deg, rgba(255, 120, 120, 0.06), rgba(255, 120, 120, 0.02));
                            border: 1px solid rgba(255, 120, 120, 0.18);
                            color: #b00020;
                        }

                        .message-error .icon {
                            background: linear-gradient(135deg, #d32f2f, #9b0000);
                        }
                    </style>
                <?php endif; ?>

                <form method="post">
                    <div class="account-form" style="gap:14px;">
                        <div class="form-row">
                            <label for="name">Full name</label>
                            <input id="name" name="name" type="text" required />
                        </div>
                        <div class="form-row">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" required />
                        </div>
                        <div class="form-row">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="" disabled selected>Select role</option>
                                <option value="employer">Employer</option>
                                <option value="supervisor">Supervisor</option>
                                <option value="probationary_employee">Probationary Employee</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="form-row">
                            <label for="password">Temporary password (optional)</label>
                            <input id="password" name="password" type="text" />
                        </div>
                        <div class="full-width" style="margin-top:6px;">
                            <button class="primary-button" type="submit">Create account</button>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>