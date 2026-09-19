<?php
// Unit tests for the pure Firestore value codec in firebase_init.php.
//
// php_to_firestore_fields()  PHP value       -> Firestore REST "value" object
// firestore_decode_value()   that object     -> PHP value
//
// These two are the boundary between the app and every document it reads or
// writes, and they are testable offline: including firebase_init.php only parses
// the root .env (a harmless side effect) and defines functions. Nothing here
// fetches an access token, so no network access occurs.
//
// The round trip is the interesting property: a value that survives
// encode -> decode unchanged cannot silently corrupt a Ratings scores map.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('firebase_init.php (value codec)');

require_once __DIR__ . '/../firebase_init.php';

/* =========================================================
   Encoding shape -- one Firestore type key per PHP type
   ========================================================= */

pf_case('php_to_firestore_fields encoding');

assert_same(['stringValue' => 'hello'], php_to_firestore_fields('hello'), 'a string becomes stringValue');
assert_same(['stringValue' => ''], php_to_firestore_fields(''), 'an empty string becomes an empty stringValue');
assert_same(['integerValue' => '5'], php_to_firestore_fields(5), 'an int becomes a STRING integerValue (REST requires a string)');
assert_same(['integerValue' => '-3'], php_to_firestore_fields(-3), 'negative ints are stringified too');
assert_same(['doubleValue' => 4.5], php_to_firestore_fields(4.5), 'a float becomes doubleValue');
assert_same(['booleanValue' => true], php_to_firestore_fields(true), 'true becomes booleanValue');
assert_same(['booleanValue' => false], php_to_firestore_fields(false), 'false becomes booleanValue');
assert_same(['nullValue' => null], php_to_firestore_fields(null), 'null becomes nullValue');

pf_case('php_to_firestore_fields mapping');

assert_same(
  ['mapValue' => ['fields' => ['a' => ['integerValue' => '1'], 'b' => ['stringValue' => 'two']]]],
  php_to_firestore_fields(['a' => 1, 'b' => 'two']),
  'an associative array becomes a nested mapValue'
);

/* =========================================================
   The empty-map trap
   ========================================================= */

pf_case('empty map encoding');

// PHP's json_encode turns an empty [] into JSON [] while Firestore demands an
// object for mapValue.fields -- the source swaps in stdClass to force {}. This
// must hold, or an empty scores map is rejected by the server as a list.
$emptyMap = php_to_firestore_fields([]);
assert_true(isset($emptyMap['mapValue']), 'an empty array still encodes as mapValue');
assert_true(
  $emptyMap['mapValue']['fields'] instanceof stdClass,
  'an empty mapValue.fields is a stdClass, so json_encode emits {} and not []'
);
assert_same('{}', json_encode($emptyMap['mapValue']['fields']), 'json_encode of the empty map fields is {}');

assert_same(
  '{"scores":{"mapValue":{"fields":{}}}}',
  json_encode(php_to_firestore_fields(['scores' => []])['mapValue']['fields']),
  'a nested empty map also serializes to {}'
);

/* =========================================================
   Decoding
   ========================================================= */

pf_case('firestore_decode_value');

assert_same('hello', firestore_decode_value(['stringValue' => 'hello']), 'stringValue decodes to a string');
assert_same('', firestore_decode_value(['stringValue' => '']), 'an empty stringValue decodes to an empty string');
assert_same(5, firestore_decode_value(['integerValue' => '5']), 'integerValue decodes to an int, not a numeric string');
assert_same(4.5, firestore_decode_value(['doubleValue' => 4.5]), 'doubleValue decodes to a float');
assert_same(true, firestore_decode_value(['booleanValue' => true]), 'booleanValue decodes to a bool');
assert_same(false, firestore_decode_value(['booleanValue' => false]), 'a false booleanValue stays false');
assert_same(null, firestore_decode_value(['nullValue' => null]), 'nullValue decodes to null');
assert_same(null, firestore_decode_value([]), 'an unrecognised value object decodes to null');

assert_same(
  ['a' => 1, 'b' => 'two'],
  firestore_decode_value(['mapValue' => ['fields' => ['a' => ['integerValue' => '1'], 'b' => ['stringValue' => 'two']]]]),
  'a mapValue decodes to an associative array'
);
assert_same(
  [1, 2, 3],
  firestore_decode_value(['arrayValue' => ['values' => [['integerValue' => '1'], ['integerValue' => '2'], ['integerValue' => '3']]]]),
  'an arrayValue decodes to a list'
);
assert_same(
  [],
  firestore_decode_value(['arrayValue' => ['values' => []]]),
  'an empty arrayValue decodes to an empty list'
);

/* =========================================================
   Round trip
   ========================================================= */

pf_case('round trip integrity');

$values = [
  'string' => 'Performa',
  'empty string' => '',
  'int' => 42,
  'negative int' => -7,
  'float' => 3.75,
  'true' => true,
  'false' => false,
  'null' => null,
  'nested map' => ['inner' => ['deep' => 1]],
  'scores-like map' => ['sales_target' => 4.0, 'customer_service' => 3.5],
];

foreach ($values as $label => $value) {
  assert_same(
    $value,
    firestore_decode_value(php_to_firestore_fields($value)),
    "the round trip preserves {$label}"
  );
}

// 0.0 must stay 0.0 and not collapse into null: employee_kpi_summary() treats a
// missing score and a zero score differently.
assert_same(
  0.0,
  firestore_decode_value(php_to_firestore_fields(0.0)),
  'a 0.0 score survives the round trip as 0.0'
);

// KNOWN WART, asserted so any change is deliberate: every PHP array is encoded
// as a map ("assume associative -> mapValue"), so a sequential list comes back
// with STRING keys rather than integer ones.
assert_same(
  ['0' => 1, '1' => 2],
  firestore_decode_value(php_to_firestore_fields([1, 2])),
  'a sequential array round-trips as a map keyed "0","1" (existing behaviour, not a list)'
);

/* =========================================================
   firestore_batch_commit_payload -- pure request builder
   ========================================================= */

pf_case('firestore_batch_commit_payload');

$payload = firestore_batch_commit_payload([
  ['collection' => 'Ratings', 'documentId' => 'u1_2026-01-05', 'data' => ['weekOf' => '2026-W01', 'score' => 1.5]],
  ['collection' => 'notifications', 'documentId' => 'n1', 'data' => ['title' => 'Ready']],
], 'demo-project');

assert_same(['writes'], array_keys($payload), 'the payload has exactly one writes key');
assert_same(2, count($payload['writes']), 'one write entry per operation');
assert_same(
  'projects/demo-project/databases/(default)/documents/Ratings/u1_2026-01-05',
  $payload['writes'][0]['update']['name'],
  'the document name is the full Firestore resource path'
);
assert_same(
  ['weekOf' => ['stringValue' => '2026-W01'], 'score' => ['doubleValue' => 1.5]],
  $payload['writes'][0]['update']['fields'],
  'each operation data field is encoded'
);
assert_same(
  'projects/demo-project/databases/(default)/documents/notifications/n1',
  $payload['writes'][1]['update']['name'],
  'each operation targets its own collection'
);
assert_same([], firestore_batch_commit_payload([], 'demo-project')['writes'], 'no operations means no writes');

$noData = firestore_batch_commit_payload([
  ['collection' => 'Users', 'documentId' => 'u9', 'data' => []],
], 'demo-project');
assert_true(
  $noData['writes'][0]['update']['fields'] instanceof stdClass,
  'an operation carrying no data still encodes fields as an object, not an array'
);

// The rating write path batches four documents in one commit; a regression in
// this builder would silently drop the acknowledgement/notification writes.
$fourDoc = firestore_batch_commit_payload([
  ['collection' => 'Ratings', 'documentId' => 'r', 'data' => ['scores' => ['a' => 4.0]]],
  ['collection' => 'Acknowledgements', 'documentId' => 'a', 'data' => ['status' => 'Pending']],
  ['collection' => 'notifications', 'documentId' => 'n', 'data' => ['type' => 'info']],
  ['collection' => 'Feedback', 'documentId' => 'f', 'data' => ['body' => 'ok']],
], 'demo-project');
assert_same(4, count($fourDoc['writes']), 'the weekly-rating batch carries all four writes');

assert_no_php_warnings();
pf_summary();