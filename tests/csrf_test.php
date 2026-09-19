<?php
// Unit tests for Employer/includes/csrf.php -- the per-session token guarding
// every state-changing Employer POST (assign course, create user, disable
// account, save rating, update regularization).
//
// The session starts BEFORE any output so the session cookie header is still
// sendable; otherwise PHP emits "headers already sent" warnings that the
// diagnostic handler would (correctly) fail on.
//
// require_csrf() itself calls exit(), so it cannot be asserted in-process: the
// last case spawns tests/fixtures/csrf_probe.php instead.

require_once __DIR__ . '/lib/assert.php';
pf_boot();

ini_set('session.save_path', sys_get_temp_dir());
ini_set('session.use_cookies', '0');
if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

pf_header('Employer/includes/csrf.php');

require_once __DIR__ . '/../Employer/includes/csrf.php';

/* =========================================================
   Token minting
   ========================================================= */

pf_case('csrf_token');

// Start from a clean slate so "minted lazily" is genuinely observed.
unset($_SESSION['csrf_token']);

$token = csrf_token();
assert_true(is_string($token), 'the token is a string');
assert_same(64, strlen($token), 'the token is 32 random bytes rendered as 64 hex characters');
assert_same(1, preg_match('/^[0-9a-f]{64}$/', $token), 'the token is lowercase hexadecimal');
assert_same($token, csrf_token(), 'the token is stable within a session, not regenerated per call');
assert_same($token, $_SESSION['csrf_token'], 'the token is stored in the session');

// A pre-existing session value must be honoured rather than overwritten.
$_SESSION['csrf_token'] = str_repeat('a', 64);
assert_same(str_repeat('a', 64), csrf_token(), 'an existing session token is reused');

// A non-string session value must be replaced, not trusted.
$_SESSION['csrf_token'] = 12345;
assert_same(64, strlen(csrf_token()), 'a non-string session token is replaced');
assert_true(is_string($_SESSION['csrf_token']), 'the repaired token is a string');

// A rotated token takes effect immediately.
$_SESSION['csrf_token'] = str_repeat('b', 64);
assert_same(str_repeat('b', 64), csrf_token(), 'a rotated session token takes effect immediately');

/* =========================================================
   Hidden field rendering
   ========================================================= */

pf_case('csrf_field');

$_SESSION['csrf_token'] = str_repeat('c', 64);
$field = csrf_field();

assert_contains('name="csrf_token"', $field, 'the field is named csrf_token');
assert_contains('type="hidden"', $field, 'the field is a hidden input');
assert_contains('value="' . str_repeat('c', 64) . '"', $field, 'the field carries the current token');
assert_same(1, substr_count($field, '<input'), 'exactly one input element is emitted');

// The token is escaped on output, so a corrupted/hostile session value cannot
// break out of the attribute.
$_SESSION['csrf_token'] = '"><script>alert(1)</script>';
$escaped = csrf_field();
assert_not_contains('<script>', $escaped, 'a hostile token cannot inject a script tag');
assert_contains('&quot;', $escaped, 'quote characters in the token are HTML-escaped');

/* =========================================================
   Validation
   ========================================================= */

pf_case('csrf_validate_request (rejections)');

$known = str_repeat('d', 64);
$_SESSION['csrf_token'] = $known;

$_POST = [];
assert_false(csrf_validate_request(), 'a POST with no csrf_token is rejected');

$_POST['csrf_token'] = '';
assert_false(csrf_validate_request(), 'an empty csrf_token is rejected');

$_POST['csrf_token'] = ['array' => 'not a string'];
assert_false(csrf_validate_request(), 'a non-string csrf_token is rejected');

$_POST['csrf_token'] = str_repeat('d', 63);
assert_false(csrf_validate_request(), 'a wrong-length token is rejected');

$_POST['csrf_token'] = str_repeat('e', 64);
assert_false(csrf_validate_request(), 'a same-length but wrong-value token is rejected');

$_POST['csrf_token'] = $known . 'x';
assert_false(csrf_validate_request(), 'a token with extra trailing characters is rejected');

unset($_SESSION['csrf_token']);
$_POST['csrf_token'] = $known;
assert_false(csrf_validate_request(), 'a token sent against a session with no token is rejected');

pf_case('csrf_validate_request (acceptance)');

$_SESSION['csrf_token'] = $known;
$_POST['csrf_token'] = $known;
assert_true(csrf_validate_request(), 'the exact session token is accepted');

$_SESSION['csrf_token'] = str_repeat('f', 64);
$_POST['csrf_token'] = str_repeat('f', 64);
assert_true(csrf_validate_request(), 'a rotated token is accepted once the session agrees');

unset($_POST['csrf_token']);

/* =========================================================
   require_csrf() -- refusal, proven in a subprocess
   ========================================================= */

pf_case('require_csrf (subprocess)');

$probe = __DIR__ . '/fixtures/csrf_probe.php';
$known = str_repeat('a', 64);

[$forgedCode, $forgedOut] = pf_run_php($probe, ['forged-token']);
assert_contains('REACHED', $forgedOut, 'the probe executed up to the guard');
assert_not_contains('PASSED_THROUGH', $forgedOut, 'a forged token does NOT pass the guard');
assert_contains('Access denied', $forgedOut, 'a forged token gets the refusal body');

[$missingCode, $missingOut] = pf_run_php($probe, ['']);
assert_not_contains('PASSED_THROUGH', $missingOut, 'a missing token does NOT pass the guard');

[$validCode, $validOut] = pf_run_php($probe, [$known]);
assert_contains('PASSED_THROUGH', $validOut, 'the correct token passes the guard');
assert_not_contains('Access denied', $validOut, 'a valid token is not refused');

// Documented rather than asserted: under the CLI SAPI http_response_code(403)
// has no observable effect and exit() with no argument exits 0, so the refusal
// is proven by the response body, not by a status code.
assert_true(is_int($forgedCode) && is_int($validCode), 'both probe subprocesses terminated');

assert_no_php_warnings();
pf_summary();