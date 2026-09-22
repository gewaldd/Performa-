<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/../security_utils.php';
require_once __DIR__ . '/../mailer.php';
require_once __DIR__ . '/../audit_log.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fetch existing supervisors so the Employer can assign one to a new probationary employee.
$supervisors = [];
try {
    $allUsers = firestore_list_documents('Users');
    foreach ($allUsers as $u) {
        $roleKey = strtolower(trim((string) ($u['role'] ?? '')));
        if ($roleKey === 'supervisor') {
            $supervisors[] = ['uid' => $u['uid'] ?? '', 'name' => $u['name'] ?? $u['email'] ?? 'Unnamed Supervisor'];
        }
    }
} catch (\Throwable $e) {
    // Non-fatal: dropdown will just be empty
}

$message = '';
$messageTone = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $roleKey = $_POST['role'] ?? '';
    $industry = trim($_POST['industry'] ?? 'retail');
    $hireDate = trim($_POST['hireDate'] ?? '');
    $supervisorId = trim($_POST['supervisorId'] ?? '');

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
        } elseif ($role === 'probationary_employee' && !$hireDate) {
            $message = 'Please provide a hire date for the probationary employee.';
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
                // Always server-generated — no manual/typed password path.
                // A human-chosen "temporary password" defeats the point of
                // requiring a reset, and was previously falling back to a
                // hardcoded TempPass123! when left blank, which is worse.
                $password = generate_secure_password(12);
                try {
                    $uid = identitytoolkit_create_user($name, $email, $password);

                    // Look up the supervisor's name for a denormalized display field.
                    $supervisorName = '';
                    if ($supervisorId) {
                        foreach ($supervisors as $s) {
                            if ($s['uid'] === $supervisorId) {
                                $supervisorName = $s['name'];
                                break;
                            }
                        }
                    }

                    $newUser = [
                        'name' => $name,
                        'email' => $email,
                        'role' => $role,
                        'department' => $department,
                        'status' => 'Active',
                        'createdAt' => date('c'),
                        // Single-org pilot ownership: the creating employer
                        // owns this account. Enforced at the dangerous points
                        // (employee_view, assign_course, rate_employee);
                        // docs without these fields are treated as legacy
                        // global so pre-existing rows keep working.
                        'createdBy' => $_SESSION['uid'] ?? null,
                        'managedByOrg' => $_SESSION['uid'] ?? null,
                        // Server-generated temporary password: force the new
                        // user to choose their own on first login.
                        'mustChangePassword' => true,
                    ];
                    if ($role === 'probationary_employee') {
                        $newUser['industry'] = $industry;
                        $newUser['hireDate'] = $hireDate;
                        $newUser['supervisorId'] = $supervisorId;
                        $newUser['supervisorName'] = $supervisorName;
                    }
                    firestore_write_document('Users', $uid, $newUser);

                    // Deliver the credential by email instead of exposing it
                    // in a redirect query string (bookmarkable, logged in
                    // server access logs, visible in browser history).
                    $loginUrl = app_base_url() . '/login.php';
                    $emailSent = send_transactional_email(
                        $email,
                        $name,
                        'Your Performa account is ready',
                        welcome_email_html($name, $email, $password, $loginUrl)
                    );

                    record_audit_event(
                        'employee_account_created',
                        "Created {$role} account for {$name} ({$email})",
                        ['uid' => $uid, 'role' => $role, 'emailDelivered' => $emailSent]
                    );

                    // Only ever hold the password somewhere the employer can
                    // see it if email delivery actually failed — and even
                    // then, a one-time session flash, never a URL param, so
                    // it can't be bookmarked, shared by accident, or sit in
                    // server access logs.
                    $redirectParams = ['created' => '1', 'name' => $name, 'emailed' => $emailSent ? '1' : '0'];
                    if (!$emailSent) {
                        $_SESSION['reveal_once_password'] = $password;
                        $_SESSION['reveal_once_email'] = $email;
                    }

                    header('Location: employees.php?' . http_build_query($redirectParams));
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
    <?php employer_brand_head(); ?>
    <meta name="description" content="Create an account for a new probationary employee or supervisor." />
    <title>Add Employee · Performa</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
    <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>
    <div class="app-shell">
        <?php employer_render_shell('Employees'); ?>
        <main class="main content-narrow">
            <div class="page-header">
                <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
                    <?php echo employer_icon('menu'); ?>
                </button>
                <div class="ph-main">
                    <a href="employees.php" class="ghost-button back-link">&larr; Back to Employees</a>
                    <nav class="ph-crumb" aria-label="Breadcrumb">
                        <span>Employees</span>
                        <span aria-hidden="true">/</span>
                        <span>Add</span>
                    </nav>
                    <h1>Add Employee</h1>
                    <p>Create a workforce account for a new probationary employee or supervisor.</p>
                </div>
            </div>

            <div class="settings-panel pf-add-panel">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($messageTone, ENT_QUOTES); ?>" role="<?php echo $messageTone === 'error' ? 'alert' : 'status'; ?>">
                        <?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
                <?php endif; ?>

                <form method="post" novalidate class="pf-add-form">
                    <?php echo csrf_field(); ?>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="name">Full name <span class="required-mark">*</span></label>
                            <input id="name" name="name" type="text"
                                value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES); ?>" required />
                        </div>
                        <div class="form-group">
                            <label for="email">Email <span class="required-mark">*</span></label>
                            <input id="email" name="email" type="email"
                                value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>" required />
                        </div>
                        <div class="form-group">
                            <label for="role">Role <span class="required-mark">*</span></label>
                            <select id="role" class="perform-select" name="role" required>
                                <option value="" disabled <?php echo empty($_POST['role']) ? 'selected' : ''; ?>>Select
                                    role</option>
                                <option value="probationary" <?php echo ($_POST['role'] ?? '') === 'probationary' ? 'selected' : ''; ?>>Probationary Employee</option>
                                <option value="supervisor" <?php echo ($_POST['role'] ?? '') === 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="department">Department <span class="required-mark">*</span></label>
                            <input id="department" name="department" type="text"
                                value="<?php echo htmlspecialchars($_POST['department'] ?? '', ENT_QUOTES); ?>"
                                placeholder="e.g. Customer Success" required />
                        </div>
                        <div class="pf-conditional" id="probationarySection">
                            <div class="pf-conditional-head"><span>Probationary details</span></div>
                            <div class="pf-conditional-grid">
                        <div class="form-group" id="industryField">
                            <label for="industry">Industry (for KPI template)</label>
                            <select id="industry" class="perform-select" name="industry">
                                <?php foreach (kpi_templates() as $key => $tpl): ?>
                                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>" <?php echo ($_POST['industry'] ?? '') === $key ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($tpl['label'], ENT_QUOTES); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-hint">Only applies to probationary employees.</span>
                        </div>
                        <div class="form-group" id="hireDateField">
                            <label for="hireDate">Hire Date <span class="required-mark">*</span></label>
                            <input id="hireDate" name="hireDate" type="date"
                                max="<?php echo date('Y-m-d'); ?>"
                                value="<?php echo htmlspecialchars($_POST['hireDate'] ?? date('Y-m-d'), ENT_QUOTES); ?>" />
                            <span class="field-hint">Only applies to probationary employees.</span>
                        </div>
                        <div class="form-group" id="supervisorField">
                            <label for="supervisorId">Assign Supervisor</label>
                            <select id="supervisorId" class="perform-select" name="supervisorId">
                                <option value="">— No supervisor assigned yet —</option>
                                <?php foreach ($supervisors as $s): ?>
                                    <option value="<?php echo htmlspecialchars($s['uid'], ENT_QUOTES); ?>" <?php echo ($_POST['supervisorId'] ?? '') === $s['uid'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($s['name'], ENT_QUOTES); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <span class="field-hint">Only applies to probationary employees.
                                <?php echo empty($supervisors) ? 'No supervisors yet — create one by choosing the Supervisor role above.' : ''; ?></span>
                        </div>
                            </div><!-- /.pf-conditional-grid -->
                        </div><!-- /#probationarySection -->
                        <div class="form-group pf-add-note">
                            <span class="field-hint">A secure password is generated automatically and emailed to the
                                new employee — there's no manual password field, they'll be asked to change it on
                                first login.</span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <a class="ghost-button" href="employees.php">Cancel</a>
                        <button class="btn-primary" type="submit">Create account</button>
                    </div>
                </form>
            </div>
        </main>
    </div>
    <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
    <script>
        // Industry, Hire Date, and Assign Supervisor only matter for probationary employees.
        // They live inside #probationarySection, so toggling the wrapper hides the
        // whole group at once (hidden subtrees leave the accessibility tree too).
        const roleSelect = document.getElementById('role');
        const probSection = document.getElementById('probationarySection');
        const hireDateInput = document.getElementById('hireDate');

        function syncIndustryVisibility() {
            const hidden = roleSelect.value !== 'probationary';
            if (probSection) probSection.hidden = hidden;
            hireDateInput.required = !hidden;
        }
        roleSelect.addEventListener('change', syncIndustryVisibility);
        syncIndustryVisibility();
    </script>
</body>

</html>