<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../kpi_templates.php';
require_login();
require_role('employer');

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $roleKey = $_POST['role'] ?? '';
    $industry = trim($_POST['industry'] ?? 'retail');
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
                    'industry' => $industry,
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Add Employee</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="styles.css" />
</head>

<body>
    <div class="app-shell">
        <main class="main" style="max-width:640px;margin:0 auto;">
            <div class="page-header">
                <div>
                    <a href="employees.php" class="ghost-button" style="display:inline-flex;margin-bottom:12px;">&larr; Back to Employees</a>
                    <h1>Add Employee</h1>
                    <p>Create an account for a new probationary employee or supervisor.</p>
                </div>
            </div>

            <div class="settings-panel">
                <?php if ($message): ?>
                    <div style="margin-bottom:16px;padding:12px;border-radius:8px;background:rgba(47,109,246,0.08);color:var(--text);"><?php echo $message; ?></div>
                <?php endif; ?>

                <form method="post">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Full name</label>
                            <input id="name" name="name" type="text" required />
                        </div>
                        <div class="form-group">
                            <label for="email">Email</label>
                            <input id="email" name="email" type="email" required />
                        </div>
                        <div class="form-group">
                            <label for="role">Role</label>
                            <select id="role" name="role" required>
                                <option value="" disabled selected>Select role</option>
                                <option value="probationary">Probationary Employee</option>
                                <option value="supervisor">Supervisor</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="industry">Industry (for KPI template)</label>
                            <select id="industry" name="industry">
                                <?php foreach (kpi_templates() as $key => $tpl): ?>
                                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>"><?php echo htmlspecialchars($tpl['label'], ENT_QUOTES); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="password">Temporary password (optional)</label>
                            <input id="password" name="password" type="text" placeholder="Auto-generated if left blank" />
                        </div>
                    </div>

                    <div class="form-actions">
                        <a class="btn-cancel" href="employees.php">Cancel</a>
                        <button class="btn-primary" type="submit">Create account</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
</body>

</html>