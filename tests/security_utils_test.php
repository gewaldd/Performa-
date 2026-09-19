<?php
// Unit tests for security_utils.php::generate_secure_password().
//
// This generates the temporary password handed to a brand-new employee, so the
// guarantees are security-relevant: it must never be predictable and it must
// never ship a password that silently fails the "one of each" rule a human
// reads off the welcome email.
//
// The character pools are asserted against the LITERAL strings in the source
// (see security_utils.php:22-26), including the deliberate exclusion of the
// look-alike characters 0/O and 1/l/I.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('security_utils.php');

require_once __DIR__ . '/../security_utils.php';

$LOWER = 'abcdefghijkmnpqrstuvwxyz';
$UPPER = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
$DIGITS = '23456789';
$SYMBOLS = '!@#$%^&*-_=+';

/* =========================================================
   Length and clamping
   ========================================================= */

pf_case('length');

assert_same(12, strlen(generate_secure_password()), 'the default length is 12');
assert_same(16, strlen(generate_secure_password(16)), 'an explicit length is honoured');
assert_same(8, strlen(generate_secure_password(4)), 'a length under 8 is clamped up to 8');
assert_same(8, strlen(generate_secure_password(8)), 'exactly 8 is left alone');
assert_same(64, strlen(generate_secure_password(64)), 'a long password is produced in full');

/* =========================================================
   Character policy -- no ambiguous characters, ever
   ========================================================= */

pf_case('character policy (200 iterations)');

$ambiguous = '0O1lI';
$ambiguousHits = 0;
$missingLower = 0;
$missingUpper = 0;
$missingDigit = 0;
$missingSymbol = 0;
$outsidePool = 0;
$duplicate = 0;
$lengths = [];
$seen = [];

for ($i = 0; $i < 200; $i++) {
  $password = generate_secure_password(12);
  $lengths[strlen($password)] = true;

  // Duplicates across 200 draws would mean the generator is not using a real
  // CSPRNG (or is far weaker than the needle-in-a-haystack odds imply).
  if (isset($seen[$password])) {
    $duplicate++;
  }
  $seen[$password] = true;

  if (strpbrk($password, $ambiguous) !== false) {
    $ambiguousHits++;
  }
  if (strpbrk($password, $LOWER) === false) {
    $missingLower++;
  }
  if (strpbrk($password, $UPPER) === false) {
    $missingUpper++;
  }
  if (strpbrk($password, $DIGITS) === false) {
    $missingDigit++;
  }
  if (strpbrk($password, $SYMBOLS) === false) {
    $missingSymbol++;
  }

  // Nothing may leak in from outside the four declared pools. Checked by
  // removal rather than strpbrk: every character must be consumed by one pool.
  $remainder = str_replace(str_split($LOWER . $UPPER . $DIGITS . $SYMBOLS), '', $password);
  if ($remainder !== '') {
    $outsidePool++;
  }
}

assert_same(0, $ambiguousHits, 'never emits the look-alike characters 0 O 1 l I');
assert_same(0, $missingLower, 'every password has at least one lowercase letter');
assert_same(0, $missingUpper, 'every password has at least one uppercase letter');
assert_same(0, $missingDigit, 'every password has at least one digit (the classic CSPRNG bug)');
assert_same(0, $missingSymbol, 'every password has at least one symbol');
assert_same(0, $outsidePool, 'no character outside the four declared pools appears');
assert_same(0, $duplicate, '200 draws produced 200 distinct passwords');
assert_same(1, count($lengths), 'all 200 draws share the requested length');

/* =========================================================
   Documented pool contents (guards against accidental edits)
   ========================================================= */

pf_case('declared pools');

assert_same(24, strlen($LOWER), 'the lowercase pool still excludes l and o');
assert_same(24, strlen($UPPER), 'the uppercase pool still excludes I and O');
assert_same(8, strlen($DIGITS), 'the digit pool still excludes 0 and 1');
assert_same(12, strlen($SYMBOLS), 'the symbol pool is unchanged');

$source = file_get_contents(__DIR__ . '/../security_utils.php');
assert_contains('\'abcdefghijkmnpqrstuvwxyz\'', $source, 'the lowercase pool literal matches the source');
assert_contains('\'ABCDEFGHJKLMNPQRSTUVWXYZ\'', $source, 'the uppercase pool literal matches the source');
assert_contains('\'23456789\'', $source, 'the digit pool literal matches the source');
assert_contains('\'!@#$%^&*-_=+\'', $source, 'the symbol pool literal matches the source');
assert_contains('random_int(', $source, 'the generator uses the CSPRNG random_int, not mt_rand/uniqid');

assert_no_php_warnings();
pf_summary();