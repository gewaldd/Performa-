<?php
require_once __DIR__ . '/../../auth.php';
require_login();
require_role('employer');

// Forced password reset: accounts created with a server-generated temporary
// password carry must_change_password until they set their own. Such users
// may only visit settings.php (where the change happens); everything else
// bounces them back there. The flag is seeded at login from Firestore and
// cleared by settings.php after a successful change.
if (!empty($_SESSION['must_change_password'])) {
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if ($script !== 'settings.php') {
        header('Location: settings.php?force_reset=1');
        exit;
    }
}