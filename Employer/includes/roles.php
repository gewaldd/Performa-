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
// $fallback is explicit because the two historical callers disagreed on
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

// Single-org pilot ownership: an employer manages a Users doc when it
// carries no owner fields (legacy / admin-seeded global row) or when its
// createdBy/managedByOrg matches the session employer. Anything else is a
// different org's account and must be denied at the dangerous points.
// (Lives here rather than includes/auth.php so sessionless endpoints like
// report_download.php can use it without tripping the strict
// require_role('employer') gate that would lock out admins.)
function employer_can_manage_user(array $userDoc): bool
{
    $owner = $userDoc['managedByOrg'] ?? null;
    $creator = $userDoc['createdBy'] ?? null;
    if (($owner === null || $owner === '') && ($creator === null || $creator === '')) {
        return true;
    }
    $me = (string) ($_SESSION['uid'] ?? '');
    if ($me !== '' && ((string) $owner === $me || (string) $creator === $me)) {
        return true;
    }
    return false;
}

// Deny with 403 + error_log when the target doc's owner doesn't match the
// session employer. Call after loading the target Users doc, before any
// write (or before rendering a non-list profile read).
function require_employer_owns_user(array $userDoc, string $context): void
{
    if (!employer_can_manage_user($userDoc)) {
        error_log(
            'Employer ownership deny: employer=' . ($_SESSION['uid'] ?? '?')
            . ' target=' . ($userDoc['uid'] ?? $userDoc['email'] ?? '?')
            . ' ctx=' . $context
        );
        http_response_code(403);
        echo 'Access denied. You do not manage this employee.';
        exit;
    }
}