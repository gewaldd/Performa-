<?php
// Unit tests for Employer/includes/roles.php -- the single role normalizer that
// replaced three byte-identical copies in employer_dashboard.php, employees.php
// and employee_view.php.
//
// This file is the direct payoff of that extraction: before it, normalize_role_key()
// lived inside page scripts that emit HTML, so it could not be tested at all.
//
// The precedence cases matter more than they look. 'probationary_employee'
// contains both 'probation' and 'employ', and 'admin supervisor' contains both
// 'supervis' and 'admin' -- the match ORDER decides which filter each page
// applies, which is exactly how the dashboard and the directory fell out of sync
// before.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('Employer/includes/roles.php');

require_once __DIR__ . '/../Employer/includes/roles.php';

/* =========================================================
   normalize_role_key -- canonical role strings
   ========================================================= */

pf_case('normalize_role_key (known roles)');

$cases = [
  'probationary_employee' => 'probationary',
  'probationary' => 'probationary',
  'Probationary' => 'probationary',
  'probation' => 'probationary',
  'PROBATIONARY EMPLOYEE' => 'probationary',
  'supervisor' => 'supervisor',
  'Supervisor' => 'supervisor',
  'employer' => 'employer',
  'Employer' => 'employer',
  'Employee' => 'employer',
  'admin' => 'admin',
  'administrator' => 'admin',
  'regular' => 'regular',
];

foreach ($cases as $input => $expected) {
  assert_same($expected, normalize_role_key($input), "normalize_role_key('{$input}')");
}

/* =========================================================
   Empty and unknown input
   ========================================================= */

pf_case('normalize_role_key (edges)');

assert_same('', normalize_role_key(null), 'null normalizes to an empty string');
assert_same('', normalize_role_key(''), 'an empty string stays empty');
assert_same('', normalize_role_key('   '), 'whitespace-only normalizes to an empty string');
assert_same('admin', normalize_role_key('  Admin  '), 'surrounding whitespace and case are normalized');
assert_same('manager', normalize_role_key('Manager'), 'an unrecognised role is returned lowercased');
assert_same('team lead', normalize_role_key('Team Lead'), 'an unrecognised role keeps its inner spacing');

/* =========================================================
   Precedence -- the part that actually bit people
   ========================================================= */

pf_case('normalize_role_key (match precedence)');

assert_same(
  'probationary',
  normalize_role_key('probationary_employee'),
  "'probation' is matched before 'employ', so probationary employees are not employers"
);
assert_same(
  'supervisor',
  normalize_role_key('admin supervisor'),
  "'supervis' is matched before 'admin', so a supervisor admin is a supervisor"
);
assert_same(
  'employer',
  normalize_role_key('employer admin'),
  "'employ' is matched before 'admin', so an employer admin is an employer"
);
assert_same(
  'probationary',
  normalize_role_key('Probationary Supervisor'),
  "'probation' outranks 'supervis'"
);

/* =========================================================
   display_role_label
   ========================================================= */

pf_case('display_role_label');

assert_same('Probationary Employee', display_role_label('probationary_employee'), 'underscores become spaces and each word is title-cased');
assert_same('Supervisor', display_role_label('supervisor'), 'a single word is title-cased');
assert_same('Supervisor', display_role_label('  supervisor  '), 'surrounding whitespace is trimmed before casing');
assert_same('Admin', display_role_label('admin'), 'admin renders as Admin');

// The two historical callers disagreed on the empty case, which is why
// $fallback exists. Both behaviours are pinned here.
assert_same('', display_role_label(''), 'no fallback: an empty role renders as an empty string (employer_dashboard behaviour)');
assert_same('', display_role_label(null), 'no fallback: null renders as an empty string');
assert_same('Employee', display_role_label('', 'Employee'), "explicit fallback: an empty role renders as 'Employee' (employees.php behaviour)");
assert_same('Employee', display_role_label(null, 'Employee'), 'explicit fallback applies to null too');
assert_same('N/A', display_role_label('', 'N/A'), 'an arbitrary fallback is honoured');

assert_no_php_warnings();
pf_summary();