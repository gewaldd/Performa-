<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';

$profileName = $_SESSION['name'] ?? 'Unknown User';
$profileRole = $_SESSION['role'] ?? 'Employer';
$profileRoleDisplay = ucwords(str_replace('_', ' ', $profileRole));
$profileEmail = $_SESSION['email'] ?? '';
$profileDepartment = $_SESSION['department'] ?? '';

$notifyMilestones = true;

try {
  if (!empty($_SESSION['uid'])) {
    $prefDoc = firestore_get_document('Users', $_SESSION['uid']) ?? [];

    if (is_array($prefDoc) && array_key_exists('notifyMilestones', $prefDoc)) {
      $notifyMilestones = (bool) $prefDoc['notifyMilestones'];
    }
  }
} catch (Throwable $e) {
  // Preference read failure keeps alerts on (fail-open for deadlines).
}

$message = '';
$messageTone = 'info';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_profile') {
  $newName = trim((string) ($_POST['fullName'] ?? ''));
  $newEmail = trim((string) ($_POST['email'] ?? ''));
  $newDepartment = trim((string) ($_POST['department'] ?? ''));

  if ($newName === '' || $newEmail === '') {
    $message = 'Full name and email are required.';
    $messageTone = 'error';
  } elseif (!filter_var($newEmail, FILTER_VALIDATE_EMAIL)) {
    $message = 'Please enter a valid email address.';
    $messageTone = 'error';
  } else {
    try {
      $existing = firestore_get_document('Users', $_SESSION['uid']) ?? [];
      $existing['name'] = $newName;
      $existing['email'] = $newEmail;
      $existing['role'] = $profileRole;
      $existing['department'] = $newDepartment;

      firestore_write_document(
        'Users',
        $_SESSION['uid'],
        $existing
      );

      $_SESSION['name'] = $newName;
      $_SESSION['email'] = $newEmail;
      $_SESSION['department'] = $newDepartment;

      $profileName = $newName;
      $profileEmail = $newEmail;
      $profileDepartment = $newDepartment;

      $message = 'Profile updated.';
      $messageTone = 'success';
    } catch (Throwable $e) {
      error_log(
        'Employer settings profile update failed: ' .
        $e->getMessage()
      );

      $message = 'We could not save your profile right now. Please try again.';
      $messageTone = 'error';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_notifications') {
  try {
    if (empty($_SESSION['uid'])) {
      throw new RuntimeException('You must be signed in.');
    }

    $existing = firestore_get_document('Users', $_SESSION['uid']) ?? [];
    $existing['notifyMilestones'] = !empty($_POST['notifyMilestones']);
    firestore_write_document('Users', $_SESSION['uid'], $existing);
    $notifyMilestones = $existing['notifyMilestones'];

    $message = 'Notification preferences saved.';
    $messageTone = 'success';
  } catch (Throwable $e) {
    error_log(
      'Employer settings notification update failed: ' .
      $e->getMessage()
    );

    $message = 'We could not save your preferences right now. Please try again.';
    $messageTone = 'error';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'change_password') {
  $newPassword = (string) ($_POST['newPassword'] ?? '');
  $confirmPassword = (string) ($_POST['confirmPassword'] ?? '');

  // Fresh re-authentication: a stolen-but-aging session must not be enough
  // to permanently take over the account. Requiring a recent login is the
  // standard "confirm it's you" gate (same pattern as Google/GitHub) and
  // needs no extra credentials store. Anyone past the window re-logs in.
  // Forced-reset users are exempt: settings.php is the ONLY page the
  // require_password_reset() gate lets them reach, so demanding a fresh
  // re-login here re-traps them with nowhere to go. This mirrors the
  // ProbationaryEmployee profile handler, which omits the window entirely.
  $isForcedReset = !empty($_SESSION['must_change_password']);
  $showReLoginLink = false;
  $loginAge = time() - (int) ($_SESSION['login_at'] ?? 0);
  $freshWindowSeconds = 15 * 60;

  if (!$isForcedReset && $loginAge > $freshWindowSeconds) {
    $message = 'For security, please sign out and sign in again, then change your password.';
    $messageTone = 'error';
    $showReLoginLink = true;
  } elseif (($pwErr = performa_password_policy_error($newPassword, $confirmPassword)) !== null) {
    // Single-sourced policy: floor + mismatch strings live in root auth.php.
    // This branch keeps Employer's freshness gate, CSRF, and success path.
    $message = $pwErr;
    $messageTone = 'error';
  } else {
    try {
      identitytoolkit_update_password(
        $_SESSION['uid'],
        $newPassword
      );

      // Password is now user-chosen: lift any forced-reset flag both in
      // Firestore (source of truth, read at next login) and in the live
      // session (so the redirect gate releases immediately).
      try {
        $self = firestore_get_document('Users', $_SESSION['uid']) ?? [];
        if (!empty($self['mustChangePassword'])) {
          $self['mustChangePassword'] = false;
          firestore_write_document('Users', $_SESSION['uid'], $self);
        }
      } catch (Throwable $e) {
        error_log(
          'Employer settings mustChangePassword clear failed: ' .
          $e->getMessage()
        );
      }
      unset($_SESSION['must_change_password']);
      // Fresh session ID after a credential change (fixation defense).
      if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
      }
      $_SESSION['login_at'] = time();

      $message = 'Password updated.';
      $messageTone = 'success';
    } catch (Throwable $e) {
      error_log(
        'Employer settings password update failed: ' .
        $e->getMessage()
      );

      $message = performa_password_update_failure_message();
      $messageTone = 'error';
    }
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'deactivate_account') {
  try {
    identitytoolkit_disable_user(
      $_SESSION['uid'],
      true
    );

    logout();

    header(
      'Location: ../login.php?deactivated=1'
    );
    exit;
  } catch (Throwable $e) {
    error_log(
      'Employer settings account deactivation failed: ' .
      $e->getMessage()
    );

    $message = 'We could not deactivate your account right now. Please try again.';
    $messageTone = 'error';
  }
}

// Shared icon library (Style A cleanup).
$icons = [
  'lock' => employer_icon('settings'),
  'shield' => employer_icon('trend'),
  'trash' => employer_icon('download'),
];

$profileInitials = employer_avatar_initials($profileName);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <?php employer_brand_head(); ?>
  <title>Settings · Performa</title>
  <meta name="description" content="Manage your account preferences and security." />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body class="settings-page-body">
  <div class="app-shell">
    <?php employer_render_shell('Settings'); ?>

    <main class="main settings-page" id="settingsPage">
      <div class="cq"><div class="wrap">
        <header>
          <div>
            <div class="crumb">Settings / <b>Account</b></div>
            <h1>Settings</h1>
            <p class="sub">Manage your account preferences and security.</p>
          </div>
        </header>

        <?php if ($message && $messageTone === 'error'): ?>
          <div class="msg" style="padding: 12px 16px; border-radius: var(--r-ctl); background: rgba(201, 52, 42, 0.1); border: 1px solid var(--bad); color: var(--bad); margin-top: var(--s4);" role="alert">
            <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
            <?php if (!empty($showReLoginLink)): ?>
              <a href="../login.php" style="color: inherit; font-weight: 700; text-decoration: underline; margin-left: 8px;">Sign in again</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($_SESSION['must_change_password']) && !$message): ?>
          <div class="msg" style="padding: 12px 16px; border-radius: var(--r-ctl); background: rgba(0, 113, 227, 0.1); border: 1px solid var(--accent); color: var(--accent); margin-top: var(--s4);" role="status" aria-live="polite">
            Your account is using a temporary password. Please set your own password below to continue.
          </div>
        <?php endif; ?>

        <div class="layout">
          <nav class="side-nav" aria-label="Settings sections">
            <a href="#profile">Profile</a><a href="#appearance">Appearance</a><a href="#security">Security</a><a href="#notifications">Notifications</a><a href="#account">Account</a>
          </nav>

          <div>
            <section class="s-sec" id="profile" aria-labelledby="h-profile">
              <h2 id="h-profile">Profile</h2>
              <p class="d">The information used for your employer account.</p>
              <div class="who">
                <div class="av" id="av"><?php echo htmlspecialchars($profileInitials, ENT_QUOTES); ?></div>
                <div><b>Profile photo</b><span>Generated automatically from your name.</span></div>
              </div>
              <form method="post" id="profileForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_profile" />
                <div class="fgrid">
                  <div>
                    <label for="fn">Full name</label>
                    <input id="fn" name="fullName" class="in" value="<?php echo htmlspecialchars($profileName, ENT_QUOTES); ?>" autocomplete="name" required maxlength="120" />
                  </div>
                  <div>
                    <label for="em">Email address</label>
                    <input id="em" name="email" class="in" type="email" value="<?php echo htmlspecialchars($profileEmail, ENT_QUOTES); ?>" placeholder="name@company.com" autocomplete="email" required maxlength="254" />
                  </div>
                  <div class="fw">
                    <label for="ro">Role</label>
                    <input id="ro" class="in pad" value="<?php echo htmlspecialchars($profileRoleDisplay, ENT_QUOTES); ?>" disabled />
                    <svg class="i ico"><use href="#i-lock"/></svg>
                  </div>
                  <div>
                    <label for="dp">Department</label>
                    <input id="dp" name="department" class="in" value="<?php echo htmlspecialchars($profileDepartment, ENT_QUOTES); ?>" placeholder="e.g. Human Resources" maxlength="120" />
                  </div>
                </div>
                <div class="acts">
                  <button class="btn" id="saveP" type="submit" disabled>Save changes</button>
                </div>
              </form>
            </section>

            <section class="s-sec" id="appearance" aria-labelledby="h-ap">
              <h2 id="h-ap">Appearance</h2>
              <p class="d">Choose how Performa looks. Changes apply instantly. System follows your device.</p>
              <div class="ap">
                <div class="ap-item">
                  <label class="eyebrow">Theme Mode</label>
                  <div class="ap-row">
                    <div class="seg" id="themeSeg" role="radiogroup" aria-label="Theme">
                      <button class="tab" type="button" role="radio" data-v="light" aria-checked="false" aria-selected="false"><svg class="i"><use href="#i-sun"/></svg>Light</button>
                      <button class="tab" type="button" role="radio" data-v="dark" aria-checked="false" aria-selected="false"><svg class="i"><use href="#i-moon"/></svg>Dark</button>
                      <button class="tab is-active" type="button" role="radio" data-v="system" aria-checked="true" aria-selected="true"><svg class="i"><use href="#i-mon"/></svg>System</button>
                    </div>
                    <small id="active" aria-live="polite"></small>
                  </div>
                </div>
                <div class="ap-item">
                  <label class="eyebrow">Color Palette</label>
                  <div class="ap-row">
                    <div class="pal-group" id="palGroup" role="group" aria-label="Color Palette">
                      <button class="sw" type="button" data-p="midnight" aria-label="Midnight theme" title="Midnight" aria-pressed="false"><i style="background:#090b10"></i><i style="background:#3b82f6"></i></button>
                      <button class="sw" type="button" data-p="dusk" aria-label="Dusk theme" title="Dusk" aria-pressed="false"><i style="background:#0c0a14"></i><i style="background:#8b5cf6"></i></button>
                      <button class="sw" type="button" data-p="paper" aria-label="Paper theme" title="Paper" aria-pressed="false"><i style="background:#f6f3ed"></i><i style="background:#c04e22"></i></button>
                      <button class="sw" type="button" data-p="mist" aria-label="Mist theme" title="Mist" aria-pressed="false"><i style="background:#f0f4f8"></i><i style="background:#0284c7"></i></button>
                      <button class="sw" type="button" data-p="sage" aria-label="Sage theme" title="Sage" aria-pressed="false"><i style="background:#edf2ee"></i><i style="background:#0f766e"></i></button>
                    </div>
                    <small id="palName" aria-live="polite"></small>
                  </div>
                </div>
              </div>
            </section>

            <section class="s-sec" id="security" aria-labelledby="h-sec">
              <h2 id="h-sec">Security</h2>
              <p class="d">Change the password used to access your account.</p>
              <form method="post" id="securityForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="change_password" />
                <div class="fgrid">
                  <div class="fw">
                    <label for="p1">New password</label>
                    <input id="p1" name="newPassword" class="in pad" type="password" placeholder="At least 8 characters" autocomplete="new-password" minlength="8" required />
                    <button class="ibtn plain" type="button" data-for="p1" aria-label="Show password"><svg class="i"><use href="#i-eye"/></svg></button>
                  </div>
                  <div class="fw">
                    <label for="p2">Confirm new password</label>
                    <input id="p2" name="confirmPassword" class="in pad" type="password" placeholder="Repeat password" autocomplete="new-password" minlength="8" required />
                    <button class="ibtn plain" type="button" data-for="p2" aria-label="Show password"><svg class="i"><use href="#i-eye"/></svg></button>
                  </div>
                </div>
                <p class="msg" id="pwmsg" hidden role="alert"></p>
                <div class="acts">
                  <button class="btn" id="updPw" type="submit" disabled>Update password</button>
                </div>
              </form>
            </section>

            <section class="s-sec" id="notifications" aria-labelledby="h-no">
              <h2 id="h-no">Notifications</h2>
              <p class="d">Deadline alerts for your probationers.</p>
              <form method="post" id="notifForm">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="action" value="save_notifications" />
                <input type="hidden" name="notifyMilestones" id="notifyMilestonesInput" value="<?php echo $notifyMilestones ? '1' : ''; ?>" />
                <div class="row">
                  <div>
                    <b id="ml">Milestone alerts</b>
                    <span class="t">Notify me when a probationer reaches day <span class="mono">150</span>, <span class="mono">165</span> or <span class="mono">178</span>.</span>
                  </div>
                  <button class="sw-toggle" id="sw" type="button" role="switch" aria-checked="<?php echo $notifyMilestones ? 'true' : 'false'; ?>" aria-labelledby="ml"></button>
                </div>
              </form>
            </section>

            <section class="s-sec" id="account" aria-labelledby="h-ac">
              <h2 id="h-ac">Account</h2>
              <p class="d">Advanced security and account status.</p>
              <div class="row mute">
                <div>
                  <b id="tf">Two-factor authentication</b>
                  <span class="t">Not available yet.</span>
                </div>
                <button class="sw-toggle" role="switch" aria-checked="false" aria-labelledby="tf" disabled></button>
              </div>
              <div class="row">
                <div>
                  <b>Deactivate account</b>
                  <span class="t">Disables your login. An admin can reactivate it later.</span>
                </div>
                <div>
                  <button class="btn danger" id="deact" type="button">Deactivate...</button>
                  <form method="post" id="deactForm" style="display:inline">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="deactivate_account" />
                    <div class="cf" id="cf" hidden>
                      <button class="btn ghost" id="cx" type="button">Cancel</button>
                      <button class="btn danger fill" id="ok" type="submit">Deactivate</button>
                    </div>
                  </form>
                </div>
              </div>
            </section>
          </div>
        </div>
      </div></div>
    </main>
  </div>

  <div class="toast" id="toast" role="status"></div>

  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
  <script>
    var $ = function(i) { return document.getElementById(i); };
    var I = function(n) { return '<svg class="i"><use href="#i-' + n + '"/></svg>'; };
    var timer;
    function toast(m) {
      var t = $("toast");
      if (!t) return;
      t.textContent = m;
      t.classList.add("on");
      clearTimeout(timer);
      timer = setTimeout(function() { t.classList.remove("on"); }, 2500);
    }

    <?php if ($message && $messageTone === 'success'): ?>
      toast(<?php echo json_encode($message); ?>);
    <?php endif; ?>

    /* Theme controls are owned entirely by the shared engine in script.js
       (delegated clicks, OS-change handling, initial paint). Nothing local. */

    /* Profile: Save enables only when dirty; avatar follows name */
    var init = {
      fn: <?php echo json_encode($profileName); ?>,
      em: <?php echo json_encode($profileEmail); ?>,
      dp: <?php echo json_encode($profileDepartment); ?>
    };

    function ini(n) {
      var w = (n || '').trim().split(/\s+/).filter(Boolean);
      return w.length ? (w.length > 1 ? w[0][0] + w[w.length - 1][0] : w[0][0]).toUpperCase() : "?";
    }

    function chkP() {
      var dirty = ["fn", "em", "dp"].some(function(k) {
        var el = $(k);
        return el && el.value !== init[k];
      });
      var fnEl = $("fn");
      if ($("saveP")) $("saveP").disabled = !dirty || !(fnEl && fnEl.value.trim());
      if ($("av") && fnEl) $("av").textContent = ini(fnEl.value);
    }

    ["fn", "em", "dp"].forEach(function(k) {
      var el = $(k);
      if (el) el.addEventListener("input", chkP);
    });

    /* Password: live validate, show/hide */
    function chkPw() {
      var p1 = $("p1"), p2 = $("p2"), msg = $("pwmsg"), upd = $("updPw");
      if (!p1 || !p2 || !msg || !upd) return;
      var a = p1.value, b = p2.value;
      upd.disabled = !(a.length >= 8 && a === b);
      if (a && a.length < 8) {
        msg.textContent = "Use at least 8 characters.";
        msg.hidden = false;
      } else if (b && a !== b) {
        msg.textContent = "Passwords don't match.";
        msg.hidden = false;
      } else {
        msg.hidden = true;
      }
    }

    ["p1", "p2"].forEach(function(k) {
      var el = $(k);
      if (el) el.addEventListener("input", chkPw);
    });

    document.querySelectorAll("[data-for]").forEach(function(btn) {
      btn.addEventListener("click", function() {
        var f = $(btn.dataset.for);
        if (!f) return;
        var show = f.type === "password";
        f.type = show ? "text" : "password";
        btn.innerHTML = I(show ? "eyeoff" : "eye");
        btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
      });
    });

    /* Notifications: instant toggle and submit */
    if ($("sw")) {
      $("sw").addEventListener("click", function() {
        var on = this.getAttribute("aria-checked") !== "true";
        this.setAttribute("aria-checked", on ? "true" : "false");
        var inp = $("notifyMilestonesInput");
        if (inp) inp.value = on ? "1" : "";
        if ($("notifForm")) $("notifForm").submit();
      });
    }

    /* Deactivate inline confirm */
    if ($("deact")) {
      $("deact").addEventListener("click", function() {
        this.hidden = true;
        if ($("cf")) $("cf").hidden = false;
        if ($("cx")) $("cx").focus();
      });
    }

    if ($("cx")) {
      $("cx").addEventListener("click", function() {
        if ($("cf")) $("cf").hidden = true;
        if ($("deact")) {
          $("deact").hidden = false;
          $("deact").focus();
        }
      });
    }

    /* Side nav highlights the section in view */
    var sideLinks = document.querySelectorAll("nav.side-nav a");
    if (sideLinks.length && window.IntersectionObserver) {
      var io = new IntersectionObserver(function(entries) {
        entries.forEach(function(entry) {
          if (entry.isIntersecting) {
            sideLinks.forEach(function(a) {
              a.setAttribute("aria-current", a.getAttribute("href") === "#" + entry.target.id ? "true" : "false");
            });
          }
        });
      }, { rootMargin: "-15% 0px -70% 0px" });

      document.querySelectorAll("#settingsPage section.s-sec[id]").forEach(function(sec) {
        io.observe(sec);
      });
      sideLinks[0].setAttribute("aria-current", "true");
    }

    chkP();
  </script>
</body>

</html>
