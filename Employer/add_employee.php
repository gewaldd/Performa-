<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_login();
require_role('employer');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $roleKey = $_POST['role'] ?? '';
    $password = trim($_POST['password'] ?? '');

    if (!$name || !$email || !$roleKey) {
        $message = 'Please fill out name, email and role.';
    } else {
        $roleMap = [
            'probationary' => 'probationary_employee',
            'supervisor' => 'supervisor',
        ];
        $role = $roleMap[$roleKey] ?? null;
        if (!$role) {
            $message = 'Invalid role selected.';
        } else {
            if (!$password)
                $password = 'TempPass123!';
            try {
                $uid = identitytoolkit_create_user($name, $email, $password);

                // Write profile to Firestore
                firestore_write_document('Users', $uid, [
                    'name' => $name,
                    'email' => $email,
                    'role' => $role,
                    'createdAt' => date('c'),
                ]);

                $message = 'Account created for ' . htmlspecialchars($name) . '. Temporary password: ' . htmlspecialchars($password);
            } catch (\Throwable $e) {
                $message = 'Failed to create user: ' . $e->getMessage();
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
    <title>Add Employee — Employer</title>
    <link rel="stylesheet" href="../styles.css" />
</head>

<body>
    <main style="max-width:700px;margin:28px auto;padding:18px;">
        <a href="employer_dashboard.php">← Back</a>
        <h1>Add Employee</h1>
        <?php if ($message): ?>
            <div style="margin:8px 0;padding:10px;border:1px solid #ddd;background:#fafafa;"><?php echo $message; ?></div>
        <?php endif; ?>

        <form method="post">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div>
                    <label for="name">Full name</label>
                    <input id="name" name="name" type="text" required style="width:100%;padding:8px;" />
                </div>
                <div>
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" required style="width:100%;padding:8px;" />
                </div>
                <div>
                    <label for="role">Role</label>
                    <select id="role" name="role" required style="width:100%;padding:8px;">
                        <option value="" disabled selected>Select role</option>
                        <option value="probationary">Probationary Employee</option>
                        <option value="supervisor">Supervisor</option>
                    </select>
                </div>
                <div>
                    <label for="password">Temporary password (optional)</label>
                    <input id="password" name="password" type="text" style="width:100%;padding:8px;" />
                </div>
            </div>
            <div style="margin-top:12px;">
                <button class="primary-button" type="submit">Create account</button>
            </div>
        </form>
    </main>
</body>

</html>