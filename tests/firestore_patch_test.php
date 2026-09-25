<?php
// Unit tests for firestore_patch_payload() in firebase_init.php.
//
// Partial document updates go through updateMask so untouched fields survive.
// The review approve flow and the probationary workstream completion flow both
// depend on this shape; a regression here silently corrupts documents.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('firebase_init.php (patch payload)');

require_once __DIR__ . '/../firebase_init.php';

pf_case('firestore_patch_payload URL');

$payload = firestore_patch_payload(
    'Ratings',
    'u1_2026-09-20',
    ['status' => 'completed', 'completedAt' => '2026-09-20T00:00:00+00:00'],
    'demo-project'
);

assert_same(
    'https://firestore.googleapis.com/v1/projects/demo-project/databases/(default)/documents/Ratings/u1_2026-09-20'
    . '?updateMask.fieldPaths=status&updateMask.fieldPaths=completedAt',
    $payload['url'],
    'the URL targets the document with one updateMask param per field, in order'
);

pf_case('firestore_patch_payload body');

assert_same(
    ['stringValue' => 'completed'],
    $payload['body']['fields']['status'],
    'a string field encodes as stringValue'
);

assert_same(
    ['stringValue' => '2026-09-20T00:00:00+00:00'],
    $payload['body']['fields']['completedAt'],
    'only the given fields are present in the body'
);

assert_same(
    ['url', 'body'],
    array_keys($payload),
    'the payload has exactly url and body keys'
);

pf_case('firestore_patch_payload edge cases');

$empty = firestore_patch_payload('Users', 'u9', [], 'demo-project');

assert_true(
    strpos($empty['url'], '?updateMask') === false,
    'no fields means no updateMask query string'
);

assert_true(
    $empty['body']['fields'] instanceof stdClass,
    'empty fields encode as an object, not an array'
);

$spaced = firestore_patch_payload('Users', 'u9', ['my field' => 'x'], 'demo-project');

assert_true(
    strpos($spaced['url'], 'updateMask.fieldPaths=my%20field') !== false,
    'field paths with spaces are URL-encoded'
);

assert_no_php_warnings();
pf_summary();
