<?php
// Minimal, dependency-free assertion library for the Performa test harness.
//
// There is no Composer, no PHPUnit and no vendor/ in this repo on purpose, so
// the harness runs with nothing but a `php` binary. Each tests/*_test.php is a
// standalone script that requires this file, registers cases with pf_case(),
// asserts, then calls pf_summary(). tests/run.php merely runs those scripts as
// subprocesses and aggregates their exit codes.
//
// Assertions are RECORDED, not short-circuited: one run reports every failure
// instead of stopping at the first, which matters when a refactor breaks a whole
// family of cases at once.

$GLOBALS['pf_results'] = [
  'passed' => 0,
  'failed' => 0,
  'php_diagnostics' => 0,
  'current' => '(before the first case)',
];

// PHP warnings/notices/deprecations are FAILURES, not noise.
//
// This codebase is procedural and reads Firestore documents with `??`
// everywhere. Accidentally re-introducing an undefined-key access is the single
// most likely regression here, and it surfaces as E_WARNING/E_NOTICE -- never as
// a thrown exception -- so a harness that only counted exceptions would call it
// green.
function pf_diagnostic_handler(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
{
  $labels = [
    E_WARNING => 'Warning',
    E_NOTICE => 'Notice',
    E_DEPRECATED => 'Deprecated',
    E_USER_WARNING => 'User warning',
    E_USER_NOTICE => 'User notice',
    E_USER_DEPRECATED => 'User deprecated',
  ];

  // E_ERROR, E_PARSE etc. never reach a user handler; let PHP own them.
  if (!isset($labels[$errno])) {
    return false;
  }

  $GLOBALS['pf_results']['php_diagnostics']++;
  pf_fail(
    'PHP ' . $labels[$errno] . ': ' . $errstr
      . ' (' . basename($errfile) . ':' . $errline . ')'
  );

  return true; // already recorded above -- do not also dump it to the console
}

// Own the error policy so the host's ini settings cannot change the outcome.
// The repo ships a php.ini, and a test run must never append to php_errors.log,
// so diagnostics go to stdout (captured by run.php) with logging switched off.
function pf_boot(): void
{
  error_reporting(E_ALL);
  ini_set('display_errors', '1');
  ini_set('log_errors', '0');
  if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.save_path', sys_get_temp_dir());
    ini_set('session.use_cookies', '0');
  }
  // Fixed timezone so date formatting assertions are not host-dependent.
  date_default_timezone_set('UTC');
  set_error_handler('pf_diagnostic_handler');
}

function pf_header(string $title): void
{
  echo "== {$title} ==\n";
}

function pf_case(string $label): void
{
  $GLOBALS['pf_results']['current'] = $label;
  echo "-- {$label}\n";
}

function pf_pass(string $label): void
{
  $GLOBALS['pf_results']['passed']++;
  echo "  PASS  {$label}\n";
}

function pf_fail(string $label): void
{
  $GLOBALS['pf_results']['failed']++;
  echo "  FAIL  [{$GLOBALS['pf_results']['current']}] {$label}\n";
}

function pf_render($value): string
{
  if (is_bool($value)) {
    return $value ? 'true' : 'false';
  }
  if ($value === null) {
    return 'null';
  }
  if (is_string($value)) {
    return "'" . $value . "'";
  }
  if (is_scalar($value)) {
    return (string) $value;
  }
  return json_encode($value);
}

// Strict (===) comparison. Deliberately strict: type-juggling comparisons are
// how an int uid quietly becomes an equal string uid in this codebase.
function assert_same($expected, $actual, string $label = ''): bool
{
  if ($expected === $actual) {
    pf_pass($label);
    return true;
  }
  pf_fail($label . ' -- expected ' . pf_render($expected) . ', got ' . pf_render($actual));
  return false;
}

function assert_true($actual, string $label = ''): bool
{
  if ($actual === true) {
    pf_pass($label);
    return true;
  }
  pf_fail($label . ' -- expected true, got ' . pf_render($actual));
  return false;
}

function assert_false($actual, string $label = ''): bool
{
  if ($actual === false) {
    pf_pass($label);
    return true;
  }
  pf_fail($label . ' -- expected false, got ' . pf_render($actual));
  return false;
}

function assert_contains(string $needle, string $haystack, string $label = ''): bool
{
  if (strpos($haystack, $needle) !== false) {
    pf_pass($label);
    return true;
  }
  pf_fail($label . ' -- expected to contain ' . pf_render($needle) . ', got ' . pf_render($haystack));
  return false;
}

function assert_not_contains(string $needle, string $haystack, string $label = ''): bool
{
  if (strpos($haystack, $needle) === false) {
    pf_pass($label);
    return true;
  }
  pf_fail($label . ' -- expected NOT to contain ' . pf_render($needle) . ', got ' . pf_render($haystack));
  return false;
}

// Asserts that no PHP diagnostic has been recorded so far. The diagnostic
// handler already fails the moment one fires; this is the explicit "and the run
// stayed clean" assertion, useful right after a risky block.
function assert_no_php_warnings(string $label = 'no PHP warnings/notices emitted'): bool
{
  return assert_same(0, $GLOBALS['pf_results']['php_diagnostics'], $label);
}

// Run another PHP script in a subprocess: [exitCode, combinedOutput].
//
// Needed for anything that calls exit() (require_csrf()) or starts a session
// after output -- neither can be asserted honestly inside this process.
function pf_run_php(string $script, array $args = []): array
{
  $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($script);
  foreach ($args as $arg) {
    $cmd .= ' ' . escapeshellarg((string) $arg);
  }
  $cmd .= ' 2>&1';

  $output = [];
  $code = 0;
  exec($cmd, $output, $code);

  return [$code, implode("\n", $output)];
}

// Prints the summary line run.php parses and exits non-zero on any failure.
function pf_summary(): void
{
  $results = $GLOBALS['pf_results'];
  $total = $results['passed'] + $results['failed'];

  echo "\n";
  echo "RESULT {$total} assertions, {$results['passed']} passed, {$results['failed']} failed\n";

  exit($results['failed'] === 0 ? 0 : 1);
}