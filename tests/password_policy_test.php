<?php
// Unit tests for the single-sourced password policy in root auth.php.
//
// performa_password_policy_error() and
// performa_password_update_failure_message() are pure (no session, header, or
// IO side effects), so they are exercised directly.
//
// Ordering matters: root auth.php starts the session at include time, so it
// is required BEFORE pf_boot()/pf_header() emit anything. Requiring it after
// output is what produces the "headers already sent" session warnings that
// fail auth_reset_gate_test.php.

require_once __DIR__ . '/lib/assert.php';
ini_set('session.save_path', sys_get_temp_dir());
require_once __DIR__ . '/../auth.php';
pf_boot();
pf_header('auth.php (password policy)');

pf_case('floor constant');

assert_same(
  8,
  PERFORMA_PASSWORD_MIN_LENGTH,
  'the shared floor is 8'
);

pf_case('length floor');

assert_same(
  'Password must be at least 8 characters.',
  performa_password_policy_error('', ''),
  'empty password hits the floor'
);
assert_same(
  'Password must be at least 8 characters.',
  performa_password_policy_error('1234567', '1234567'),
  '7 chars hits the floor'
);
assert_same(
  null,
  performa_password_policy_error('12345678', '12345678'),
  'exactly 8 matching passes'
);

pf_case('confirmation');

assert_same(
  'Passwords do not match.',
  performa_password_policy_error('12345678', '87654321'),
  'mismatching confirmations report the mismatch'
);
assert_same(
  'Password must be at least 8 characters.',
  performa_password_policy_error('short', 'different-long'),
  'floor wins over mismatch'
);
assert_same(
  null,
  performa_password_policy_error('Performa#2026!', 'Performa#2026!'),
  'long matching passes'
);

pf_case('failure message');

assert_same(
  'We could not update your password right now. Please try again.',
  performa_password_update_failure_message(),
  'generic failure string is exact'
);

assert_no_php_warnings();
pf_summary();