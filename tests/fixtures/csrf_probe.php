<?php
// Subprocess probe for require_csrf().
//
// require_csrf() calls exit() on a rejected POST, so its behaviour cannot be
// asserted inside the test process -- this script is spawned by
// tests/csrf_test.php instead.
//
// argv[1] is the token to send as the posted csrf_token; '' means "forged".
// "REACHED" prints before the guard and "PASSED_THROUGH" after it, so the test
// can distinguish "refused" from "the script died before the check".
//
// LIMITATION: http_response_code(403) has no observable effect under the CLI
// SAPI and exit() with no argument yields status 0, so the refusal is asserted
// on the body text, not on the exit code.

ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

require_once __DIR__ . '/../../Employer/includes/csrf.php';

// Stand in for a logged-in session: a known, deterministic token.
$_SESSION['csrf_token'] = str_repeat('a', 64);
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['csrf_token'] = (string) ($argv[1] ?? '');

echo "REACHED\n";
require_csrf();
echo "PASSED_THROUGH\n";