<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../kpi_templates.php';
require_login();
require_role('employer');

$message = '';
$messageTone = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $roleKey = $_POST['role'] ?? '';
    $industry = trim($_POST['industry'] ?? 'retail');
    $password = trim($_POST['password'] ?? '');

    if (!$name || !$email || !$roleKey || !$department) {
        $message = 'Please fill out name, email, department and role.';
        $messageTone = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageTone = 'error';
    } else {
        $roleMap = [
            'probationary' => 'probationary_employee',
            'supervisor' => 'supervisor',
        ];
        $role = $roleMap[$roleKey] ?? null;
        if (!$role) {
            $message = 'Invalid role selected.';
            $messageTone = 'error';
        } else {
            // Duplicate email check against existing Users
            $emailTaken = false;
            try {
                $existingUsers = firestore_list_documents('Users');
                foreach ($existingUsers as $u) {
                    if (!empty($u['email']) && strtolower($u['email']) === strtolower($email)) {
                        $emailTaken = true;
                        break;
                    }
                }
            } catch (\Throwable $e) {
                // if the check itself fails, fall through and let create attempt surface the real error
            }

            if ($emailTaken) {
                $message = 'An account with that email already exists.';
                $messageTone = 'error';
            } else {
                if (!$password)
                    $password = 'TempPass123!';
                try {
                    $uid = identitytoolkit_create_user($name, $email, $password);

                    $newUser = [
                        'name' => $name,
                        'email' => $email,
                        'role' => $role,
                        'department' => $department,
                        'status' => 'Active',
                        'createdAt' => date('c'),
                    ];
                    if ($role === 'probationary_employee') {
                        $newUser['industry'] = $industry;
                    }
                    firestore_write_document('Users', $uid, $newUser);

                    header('Location: employees.php?created=1&name=' . urlencode($name) . '&temp_password=' . urlencode($password));
                    exit;
                } catch (\Throwable $e) {
                    $message = 'Failed to create user: ' . $e->getMessage();
                    $messageTone = 'error';
                }
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
                    <div style="margin-bottom:16px;padding:12px;border-radius:8px;
                        background:<?php echo $messageTone === 'error' ? 'rgba(237,91,87,0.1)' : 'rgba(47,109,246,0.08)'; ?>;
                        color:<?php echo $messageTone === 'error' ? '#ed5b57' : 'var(--text)'; ?>;"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
                <?php endif; ?>

                <form method="post" novalidate>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Full name <span class="required-mark">*</span></label>
                            <input id="name" name="name" type="text" value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES); ?>" required />
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span class="required-mark">*</span></label>
                            <input id="email" name="email" type="email" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>" required />
                        </div>
                        <div class="form-group">
                            <label for="department">Department <span class="required-mark">*</span></label>
                            <input id="department" name="department" type="text" value="<?php echo htmlspecialchars($_POST['department'] ?? '', ENT_QUOTES); ?>" placeholder="e.g. Customer Success" required />
                        </div>
                        <div class="form-group">
                            <label for="role">Role <span class="required-mark">*</span></label>
                            <select id="role" name="role" required>
                                <option value="" disabled <?php echo empty($_POST['role']) ? 'selected' : ''; ?>>Select role</option>
                                <option value="probationary" <?php echo ($_POST['role'] ?? '') === 'probationary' ? 'selected' : ''; ?>>Probationary Employee</option>
                                <option value="supervisor" <?php echo ($_POST['role'] ?? '') === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                            </select>
                        </div>
                        <div class="form-group" id="industryField">
                            <label for="industry">Industry (for KPI template)</label>
                            <select id="industry" name="industry">
                                <?php foreach (kpi_templates() as $key => $tpl): ?>
                                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>" <?php echo ($_POST['industry'] ?? '') === $key ? 'selected' : ''; ?>><?php echo htmlspecialchars($tpl['label'], ENT_QUOTES); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-hint">Only applies to probationary employees.</span>
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
    <script>
        // Industry only matters for probationary employees, hide it otherwise
        const roleSelect = document.getElementById('role');
        const industryField = document.getElementById('industryField');
        function syncIndustryVisibility() {
            industryField.style.display = roleSelect.value === 'probationary' ? '' : 'none';
        }
        roleSelect.addEventListener('change', syncIndustryVisibility);
        syncIndustryVisibility();
    </script>
</body>

</html>