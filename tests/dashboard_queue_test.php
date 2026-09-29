<?php
// Unit tests for queue_is_dismissed() (kpi_templates.php) — the dashboard
// review-queue triage rule. Pure function, no Firestore, runs offline like
// kpi_test.php.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('queue dismissal');

require_once __DIR__ . '/../kpi_templates.php';

pf_case('absent flag is never dismissed');

assert_false(queue_is_dismissed([]), 'missing flag stays visible');
assert_false(
    queue_is_dismissed(['queueDismissedAt' => '']),
    'empty flag stays visible'
);
assert_false(
    queue_is_dismissed(['queueDismissedAt' => '   ']),
    'whitespace flag stays visible'
);
assert_false(
    queue_is_dismissed(['queueDismissedAt' => '2026-09-20T10:00:00Z'], '2026-09-21T10:00:00Z'),
    'unrelated: covered again below, flag with newer rating resurfaces'
);

pf_case('deliberate hide wins without recency proof');

assert_true(
    queue_is_dismissed(['queueDismissedAt' => '2026-09-20T10:00:00Z']),
    'flag with no rating reference stays hidden'
);
assert_true(
    queue_is_dismissed(['queueDismissedAt' => '2026-09-20T10:00:00Z'], ''),
    'flag with empty rating reference stays hidden'
);
assert_true(
    queue_is_dismissed(['queueDismissedAt' => 'not-a-date'], '2026-09-21T10:00:00Z'),
    'unparseable flag keeps the deliberate hide'
);
assert_true(
    queue_is_dismissed(['queueDismissedAt' => '2026-09-20T10:00:00Z'], 'not-a-date'),
    'unparseable rating keeps the deliberate hide'
);

pf_case('recency decides ties and order');

assert_true(
    queue_is_dismissed(['queueDismissedAt' => '2026-09-20T10:00:00Z'], '2026-09-19T10:00:00Z'),
    'older rating stays hidden'
);
assert_true(
    queue_is_dismissed(['queueDismissedAt' => '2026-09-20T10:00:00Z'], '2026-09-20T10:00:00Z'),
    'same-instant rating stays hidden (dismissal wins ties)'
);
assert_false(
    queue_is_dismissed(['queueDismissedAt' => '2026-09-20T10:00:00Z'], '2026-09-20T10:00:01Z'),
    'strictly newer rating resurfaces'
);

pf_case('unix timestamps work on both sides');

assert_true(
    queue_is_dismissed(['queueDismissedAt' => 1758352800], 1758266400),
    'older unix rating stays hidden'
);
assert_false(
    queue_is_dismissed(['queueDismissedAt' => 1758266400], 1758352800),
    'newer unix rating resurfaces'
);
assert_true(
    queue_is_dismissed(['queueDismissedAt' => '2026-09-20T10:00:00Z'], 1758266400),
    'mixed formats compare numerically (older rating hidden)'
);

pf_case('no diagnostics');

assert_no_php_warnings();

pf_summary();
