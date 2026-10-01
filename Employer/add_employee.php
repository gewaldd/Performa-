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

$message = '';
$messageTone = 'info';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $rawRole = strtolower(trim((string) ($_POST['role'] ?? 'probationary')));
    $roleKey = strpos($rawRole, 'super') !== false ? 'supervisor' : 'probationary';
    $industry = 'retail';
    $hireDate = date('Y-m-d');
    $supervisorId = '';
    $jobRole = '';

    if (!$name || !$email || !$department) {
        $message = 'Please fill out name, email, and department.';
        $messageTone = 'error';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageTone = 'error';
    } else {
        $roleMap = [
            'probationary' => 'probationary_employee',
            'supervisor' => 'supervisor',
        ];
        $role = $roleMap[$roleKey] ?? 'probationary_employee';
        if ($role === 'probationary_employee' && !$hireDate) {
            $hireDate = date('Y-m-d');
        }

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
                // Always server-generated - no manual/typed password path.
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
                        $newUser['jobRole'] = $jobRole;
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
                    // see it if email delivery actually failed - and even
                    // then, a one-time session flash, never a URL param, so
                    // it can't be bookmarked, shared by accident, or sit in
                    // server access logs.
                    $redirectParams = ['created' => '1', 'name' => $name, 'email' => $email, 'emailed' => $emailSent ? '1' : '0'];
                    if (!$emailSent) {
                        $_SESSION['reveal_once_password'] = $password;
                        $_SESSION['reveal_once_email'] = $email;
                    }

                    // Land on the new profile so the next step (rate, resend,
                    // review) needs no search; the banner there carries the
                    // credential state.
                    header('Location: employee_view.php?uid=' . urlencode($uid) . '&' . http_build_query($redirectParams));
                    exit;
                } catch (\Throwable $e) {
                    $message = 'Failed to create user: ' . $e->getMessage();
                    $messageTone = 'error';
                }
            }
        }
    }
$selectedRoleKey = ($roleKey ?? 'probationary') === 'supervisor' ? 'supervisor' : 'probationary';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php employer_brand_head(); ?>
  <meta name="description" content="Create an account for a new probationary employee or supervisor." />
  <title>Add employee · Performa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('Employees'); ?>
    <main class="main add-page pf-add-page">
      <div class="cq">
        <div class="wrap">
          <a href="employees.php" class="back"><svg class="i"><use href="#i-back"/></svg>Employees</a>
          <div id="v-form">
            <h1>Add employee</h1>
            <p class="sub">Create a workforce account for a new probationary employee or supervisor.</p>

            <?php if ($message): ?>
              <div class="err-box" role="<?php echo $messageTone === 'error' ? 'alert' : 'status'; ?>" style="margin-top: var(--s3); margin-bottom: var(--s2); color: <?php echo $messageTone === 'error' ? 'var(--bad)' : 'var(--good)'; ?>; font-size: var(--t-13); font-weight: 500;">
                <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
              </div>
            <?php endif; ?>

            <form class="form" id="f" method="post" novalidate>
              <?php echo csrf_field(); ?>
              <div>
                <label class="l" for="fn">Full name <span class="req">required</span></label>
                <input id="fn" name="name" class="in" autocomplete="off" value="<?php echo htmlspecialchars($_POST['name'] ?? '', ENT_QUOTES); ?>" required>
                <p class="err" id="e-fn" hidden>Enter the employee's full name.</p>
              </div>

              <div>
                <label class="l" for="em">Email <span class="req">required</span></label>
                <input id="em" name="email" class="in" type="email" autocomplete="off" placeholder="name@company.com" value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES); ?>" required>
                <p class="err" id="e-em" hidden></p>
              </div>

              <div>
                <span class="l" style="display:block;font-size:12px;font-weight:500;color:var(--ink-2);margin-bottom:8px">Role</span>
                <div class="seg full" id="role" role="radiogroup" aria-label="Role">
                  <button type="button" class="tab<?php echo $selectedRoleKey !== 'supervisor' ? ' is-active' : ''; ?>" role="radio" aria-selected="<?php echo $selectedRoleKey !== 'supervisor' ? 'true' : 'false'; ?>" data-v="probationary">Probationary employee</button>
                  <button type="button" class="tab<?php echo $selectedRoleKey === 'supervisor' ? ' is-active' : ''; ?>" role="radio" aria-selected="<?php echo $selectedRoleKey === 'supervisor' ? 'true' : 'false'; ?>" data-v="supervisor">Supervisor</button>
                </div>
                <input type="hidden" name="role" id="roleInput" value="<?php echo htmlspecialchars($selectedRoleKey, ENT_QUOTES); ?>">
              </div>

              <div>
                <label class="l" for="dp">Department <span class="req">required</span></label>
                <input id="dp" name="department" class="in" list="depts" placeholder="Start typing to pick an existing one" autocomplete="off" value="<?php echo htmlspecialchars($_POST['department'] ?? '', ENT_QUOTES); ?>" required>
                <datalist id="depts">
                  <option value="Construction">
                  <option value="Customer Success">
                  <option value="Food Service">
                  <option value="Human Resources">
                  <option value="Sales">
                </datalist>
                <p class="err" id="e-dp" hidden>Choose or enter a department.</p>
              </div>

              <div class="pw">
                <svg class="i"><use href="#i-info"/></svg>
                <span>A secure password is generated and emailed to the new employee. They'll be asked to change it on first login.</span>
              </div>

              <div class="acts">
                <a href="employees.php" class="btn ghost" id="cancel">Cancel</a>
                <button type="submit" class="btn">Create account</button>
              </div>
            </form>
          </div>
        </div>
      </div>
    </main>
  </div>
  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
  <script>
    (function () {
      var roleGroup = document.getElementById('role');
      var roleInput = document.getElementById('roleInput');
      var form = document.getElementById('f');
      var fn = document.getElementById('fn');
      var em = document.getElementById('em');
      var dp = document.getElementById('dp');
      var shown = {};

      if (roleGroup) {
        roleGroup.addEventListener('click', function (e) {
          var btn = e.target.closest('.tab');
          if (!btn) return;
          roleGroup.querySelectorAll('.tab').forEach(function (t) {
            var active = (t === btn);
            t.classList.toggle('is-active', active);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
          });
          var val = btn.getAttribute('data-v') || 'probationary';
          if (roleInput) roleInput.value = val;
        });
      }

      function val(k) {
        var el = document.getElementById(k);
        if (!el) return '';
        var v = el.value.trim();
        if (k === 'fn') return v ? '' : "Enter the employee's full name.";
        if (k === 'dp') return v ? '' : 'Choose or enter a department.';
        if (!v) return 'Enter an email address.';
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v)) return "That doesn't look like a valid email.";
        return '';
      }

      function show(k) {
        var m = val(k);
        var e = document.getElementById('e-' + k);
        var el = document.getElementById(k);
        if (e) {
          e.textContent = m;
          e.hidden = !m;
        }
        if (el) {
          el.classList.toggle('bad', !!m);
        }
        return !m;
      }

      ['fn', 'em', 'dp'].forEach(function (k) {
        var el = document.getElementById(k);
        if (!el) return;
        el.addEventListener('blur', function () {
          shown[k] = 1;
          show(k);
        });
        el.addEventListener('input', function () {
          if (shown[k]) show(k);
        });
      });

      // Live duplicate email check
      if (em) {
        em.addEventListener('blur', async function () {
          var v = em.value.trim();
          if (!v || v.indexOf('@') === -1) return;
          try {
            var res = await fetch('check_email.php?email=' + encodeURIComponent(v), {
              headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            var data = await res.json();
            if (data && data.taken) {
              var eEm = document.getElementById('e-em');
              if (eEm) {
                eEm.textContent = 'An account with this email already exists.';
                eEm.hidden = false;
              }
              em.classList.add('bad');
            }
          } catch (err) { /* server validates on submit */ }
        });
      }

      if (form) {
        form.addEventListener('submit', function (e) {
          var ok = ['fn', 'em', 'dp'].map(function (k) {
            shown[k] = 1;
            return show(k);
          }).every(Boolean);

          if (!ok) {
            e.preventDefault();
            var firstBad = form.querySelector('.in.bad');
            if (firstBad) firstBad.focus();
          }
        });
      }
    })();
  </script>
</body>

</html>