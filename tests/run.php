<?php
// Performa test runner.  Run from the repository root:
//
//   php tests/run.php
//
// Each tests/*_test.php runs as its OWN php process rather than being included.
// That is deliberate and buys three things this harness needs:
//   1. a test can start a session before any output, so the session-backed tests
//      never trip "headers already sent" warnings,
//   2. a test that exits (require_csrf()) cannot kill the whole run,
//   3. each file's diagnostics and exit code stay isolated and attributable.
//
// No Composer, no PHPUnit, no network, no Firestore. Exit code 0 only when every
// file passes; a file that prints no RESULT line counts as a failure (that is
// how a fatal error looks from here).

$files = glob(__DIR__ . '/*_test.php');
sort($files);

if (!$files) {
  fwrite(STDERR, "No tests/*_test.php files found.\n");
  exit(1);
}

echo "Performa test harness -- " . count($files) . " file(s)\n";

$totals = ['assertions' => 0, 'passed' => 0, 'failed' => 0];
$failedFiles = [];

foreach ($files as $file) {
  $name = basename($file);
  echo "\n########## {$name} ##########\n";

  // 2>&1 because the child may write a fatal error to stderr; the harness wants
  // the whole story in one captured string.
  $cmd = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($file) . ' 2>&1';
  $output = [];
  $code = 0;
  exec($cmd, $output, $code);

  $text = implode("\n", $output);
  echo $text, "\n";

  $matched = false;
  foreach ($output as $line) {
    if (preg_match('/^RESULT (\d+) assertions, (\d+) passed, (\d+) failed$/', trim($line), $m)) {
      $matched = true;
      $totals['assertions'] += (int) $m[1];
      $totals['passed'] += (int) $m[2];
      $totals['failed'] += (int) $m[3];
    }
  }

  if ($code !== 0 || !$matched) {
    // Report WHY, so "no RESULT line" (fatal/parse error) is distinguishable
    // from "ran fine but failed assertions".
    $failedFiles[] = $matched ? $name : $name . ' (no RESULT line -- fatal error?)';
  }
}

echo "\n============================================================\n";
echo "TOTAL {$totals['assertions']} assertions, {$totals['passed']} passed, {$totals['failed']} failed\n";

if ($failedFiles) {
  echo "FAILED FILES:\n";
  foreach ($failedFiles as $failed) {
    echo "  - {$failed}\n";
  }
  exit(1);
}

echo "ALL FILES PASSED\n";
exit(0);