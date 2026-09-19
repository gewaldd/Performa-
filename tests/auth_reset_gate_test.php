<?php
// Unit tests for the forced-password-reset gate in auth.php.
//
// password_reset_redirect_target() is deliberately pure -- the doc comment in
// auth.php says the matrix was split out so it is "unit-testable without
// triggering header()/exit" -- so this file finally exercises that promise.
//
// auth.php runs session_start() at include time (the hardened cookie policy
// lives at the top of the file). pf_boot() has already pointed the session store
// at the OS temp dir and disabled cookies, so this is side-effect-free for the
// repo. NOTE: this file intentionally never calls load_users()/create_user(),
// which would write data/users.json INSIDE the repository.

require_once __DIR__ . '/lib/assert.php';
ini_set('session.save_path', sys_get_temp_dir());
require_once __DIR__ . '/../auth.php';
pf_boot();
pf_header('auth.php (forced password reset gate)');

/* =========================================================
   Flag absent -- never redirect
   ========================================================= */

pf_case('flag not set');

unset($_SESSION['must_change_password']);

assert_same(
  null,
  password_reset_redirect_target('/Employer/dashboard.php', 'settings.php'),
  'a normal user is never redirected'
);
assert_same(
  null,
  password_reset_redirect_target('/Employer/settings.php', 'settings.php'),
  'a normal user on settings is never redirected'
);
assert_same(
  null,
  password_reset_redirect_target(null, 'settings.php'),
  'a normal user with no script name is never redirected'
);

/* =========================================================
   Flag set -- bounce everything except settings
   ========================================================= */

pf_case('flag set');

$_SESSION['must_change_password'] = true;

assert_same(
  'settings.php?force_reset=1',
  password_reset_redirect_target('/Employer/dashboard.php', 'settings.php'),
  'a flagged user on the dashboard is sent to settings'
);
assert_same(
  'settings.php?force_reset=1',
  password_reset_redirect_target('/Employer/employees.php', 'settings.php'),
  'a flagged user on the directory is sent to settings'
);
assert_same(
  'settings.php?force_reset=1',
  password_reset_redirect_target('/Supervisor/ratings.php', 'settings.php'),
  'a flagged user in another portal is sent to settings'
);
assert_same(
  'settings.php?force_reset=1',
  password_reset_redirect_target('/Admin/accounts.php', 'settings.php'),
  'a flagged admin is sent to settings'
);
assert_same(
  'settings.php?force_reset=1',
  password_reset_redirect_target('', 'settings.php'),
  'a flagged user with an empty script name is sent to settings'
);
assert_same(
  'settings.php?force_reset=1',
  password_reset_redirect_target(null, 'settings.php'),
  'a flagged user with no script name at all is sent to settings'
);

/* =========================================================
   The loop guard: settings must be exempt
   ========================================================= */

pf_case('settings is exempt (loop guard)');

assert_same(
  null,
  password_reset_redirect_target('/Employer/settings.php', 'settings.php'),
  'a flagged user already ON settings is not redirected (no redirect loop)'
);
assert_same(
  null,
  password_reset_redirect_target('settings.php', 'settings.php'),
  'a bare settings filename is exempt'
);
assert_same(
  null,
  password_reset_redirect_target('/deep/nested/path/settings.php', 'settings.php'),
  'settings is matched by basename, not by full path'
);

/* =========================================================
   Truthiness of the flag
   ========================================================= */

pf_case('flag truthiness');

$_SESSION['must_change_password'] = '';
assert_same(
  null,
  password_reset_redirect_target('/Employer/dashboard.php', 'settings.php'),
  'an empty-string flag does not trigger the gate'
);

$_SESSION['must_change_password'] = '0';
assert_same(
  null,
  password_reset_redirect_target('/Employer/dashboard.php', 'settings.php'),
  'the string "0" does not trigger the gate'
);

$_SESSION['must_change_password'] = 1;
assert_same(
  'settings.php?force_reset=1',
  password_reset_redirect_target('/Employer/dashboard.php', 'settings.php'),
  'an integer 1 does trigger the gate'
);

unset($_SESSION['must_change_password']);

assert_no_php_warnings();
pf_summary();