<?php
// Unit tests for supervisor_is_in_scope() / supervisor_filter_docs() in
// Supervisor/supervisor_layout.php — the single assignment-scoping rule for
// the whole Supervisor portal. Staff explicitly assigned to a different
// supervisor are out of scope; legacy/unassigned staff stay visible.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('supervisor assignment scoping');

require_once __DIR__ . '/../Supervisor/supervisor_layout.php';

pf_case('supervisor_is_in_scope');

assert_true(
    supervisor_is_in_scope(['supervisorId' => 'sup-1'], 'sup-1'),
    'staff assigned to this supervisor is in scope'
);

assert_true(
    supervisor_is_in_scope([], 'sup-1'),
    'legacy staff with no supervisorId stays visible'
);

assert_true(
    supervisor_is_in_scope(['supervisorId' => ''], 'sup-1'),
    'staff with an empty supervisorId stays visible'
);

assert_true(
    supervisor_is_in_scope(['supervisorId' => '  '], 'sup-1'),
    'whitespace-only supervisorId counts as unassigned'
);

assert_false(
    supervisor_is_in_scope(['supervisorId' => 'sup-2'], 'sup-1'),
    'staff assigned to a different supervisor is out of scope'
);

assert_false(
    supervisor_is_in_scope(['supervisorId' => 'sup-2'], ''),
    'empty own uid never matches an assigned doc'
);

pf_case('supervisor_filter_docs');

$filtered = supervisor_filter_docs(
    [
        ['uid' => 'a', 'supervisorId' => 'sup-1'],
        ['uid' => 'b'],
        ['uid' => 'c', 'supervisorId' => 'sup-2'],
        'not-an-array',
    ],
    'sup-1'
);

assert_same(
    [
        ['uid' => 'a', 'supervisorId' => 'sup-1'],
        ['uid' => 'b'],
    ],
    array_values($filtered),
    'filter keeps assigned-to-me plus legacy, drops other-supervisor docs and non-arrays'
);

assert_same(
    [],
    supervisor_filter_docs([], 'sup-1'),
    'empty input filters to empty output'
);

assert_no_php_warnings();
pf_summary();
