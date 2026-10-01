<?php
require_once __DIR__ . '/../../auth.php';
require_login();
require_role('employer');

// Single-org ownership helpers (employer_can_manage_user,
// require_employer_owns_user) live in includes/roles.php so endpoints that
// must stay admin-compatible can use them without this file's strict
// require_role('employer') gate.

// Forced password reset: accounts created with a server-generated temporary
// password carry must_change_password until they set their own. Such users
// may only visit settings.php (where the change happens); everything else
// bounces them back there. The flag is seeded at login from Firestore and
// cleared by settings.php after a successful change.
require_password_reset('settings.php');