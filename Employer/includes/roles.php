<?php
// Shared role normalization for the Employer portal.
//
// Firestore stores `role` as free text and the value has drifted across seeds
// ("probationary_employee", "Probationary", "Employer", ...), so callers match
// on substrings. First match wins, in this exact order:
//
//   probation -> 'probationary'
//   supervis  -> 'supervisor'
//   employ    -> 'employer'
//   admin     -> 'admin'
//   anything else -> the trimmed, lowercased raw value ('regular', '')
//
// Order matters: 'probationary_employee' contains both 'probation' and
// 'employ', and must resolve to 'probationary'. Do not reorder these checks
// and do not add aliases -- role string drift is exactly how the Employer
// dashboard, directory, and KPI picker fell out of sync before.
//
// Extracted from three byte-identical copies that lived in
// employer_dashboard.php, employees.php, and employee_view.php. One
// implementation now, so the filters cannot drift, and it is unit-testable
// without rendering a page (see tests/roles_test.php).

function normalize_role_key(?string $role): string
{
  $roleKey = strtolower(trim((string) $role));

  if (strpos($roleKey, 'probation') !== false) {
    return 'probationary';
  }

  if (strpos($roleKey, 'supervis') !== false) {
    return 'supervisor';
  }

  if (strpos($roleKey, 'employ') !== false) {
    return 'employer';
  }

  if (strpos($roleKey, 'admin') !== false) {
    return 'admin';
  }

  return $roleKey;
}

// Raw role string -> human label for table cells and headings.
//
// $fallback is explicit because the two historical callers disagreed on the
// empty case: employer_dashboard.php rendered '' while employees.php rendered
// 'Employee'. Both keep their existing output by passing their own fallback,
// so consolidating them changes nothing on screen.
function display_role_label(?string $role, string $fallback = ''): string
{
  $raw = trim((string) $role);

  return $raw !== ''
    ? ucwords(str_replace('_', ' ', $raw))
    : $fallback;
}