<?php
require_once __DIR__ . '/../../auth.php';
require_login();
require_role('employer');

// Single-org pilot ownership: an employer manages a Users doc when it
// carries no owner fields (legacy / admin-seeded global row) or when its
// createdBy/managedByOrg matches the session employer. Anything else is a
// different org's account and must be denied at the dangerous points.
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
// Forced password reset: accounts created with a server-generated temporary
// password carry must_change_password until they set their own. Such users
// may only visit settings.php (where the change happens); everything else
// bounces them back there. The flag is seeded at login from Firestore and
// cleared by settings.php after a successful change.
require_password_reset('settings.php');