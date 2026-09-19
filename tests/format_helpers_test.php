<?php
// Unit tests for Employer/includes/format_helpers.php -- the single voice for
// dates, scores and day-counts across the Employer pages.
//
// These are pure string formatters with no dependencies, so the whole file is
// deterministic. pf_boot() pins the timezone to UTC, otherwise the pf_date
// assertions would flip depending on the host's zone.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('Employer/includes/format_helpers.php');

require_once __DIR__ . '/../Employer/includes/format_helpers.php';

/* =========================================================
   pf_date -- 'M j, Y' with an explicit fallback
   ========================================================= */

pf_case('pf_date');

assert_same('Jan 5, 2026', pf_date('2026-01-05T00:00:00Z'), 'an ISO-8601 timestamp formats as M j, Y');
assert_same('Dec 31, 2026', pf_date('2026-12-31'), 'a bare date string formats');
assert_same('Jun 1, 2026', pf_date('2026-06-01T12:30:45+00:00'), 'an offset timestamp formats');
assert_same('Unknown', pf_date(null), 'null falls back to the default label');
assert_same('Unknown', pf_date(''), 'an empty string falls back');
assert_same('Unknown', pf_date('not a date at all'), 'an unparseable string falls back');
assert_same('Never', pf_date(null, 'Never'), 'a caller-supplied fallback is used');
assert_same('Never', pf_date('', 'Never'), 'the caller-supplied fallback applies to empty strings too');

/* =========================================================
   pf_score / pf_score_pair -- one decimal, em-dash for junk
   ========================================================= */

pf_case('pf_score');

assert_same('4.0', pf_score(4.0), 'a float formats to one decimal');
assert_same('4.2', pf_score(4.2), 'a fractional float keeps one decimal');
assert_same('4.0', pf_score('4.0'), 'a numeric string formats like a float');
assert_same('0.0', pf_score(0), 'integer zero is a real score, not a dash');
assert_same('0.0', pf_score('0'), 'string zero is a real score, not a dash');
assert_same('—', pf_score(null), 'null renders as an em-dash');
assert_same('—', pf_score(''), 'an empty string renders as an em-dash');
assert_same('—', pf_score('abc'), 'a non-numeric string renders as an em-dash');

pf_case('pf_score_pair');

assert_same('4.0 / 5.0', pf_score_pair(4.0), 'the default maximum is 5.0');
assert_same('3.0 / 4.5', pf_score_pair(3.0, 4.5), 'an explicit maximum is honoured');
assert_same('0.0 / 5.0', pf_score_pair(0), 'zero is a real value, not a dash');
assert_same('—', pf_score_pair(null), 'null renders as an em-dash');
assert_same('—', pf_score_pair(''), 'an empty string renders as an em-dash');
assert_same('—', pf_score_pair('abc'), 'a non-numeric string renders as an em-dash');

/* =========================================================
   pf_day -- "Day N" with negatives clamped
   ========================================================= */

pf_case('pf_day');

assert_same('Day 0', pf_day(0), 'day zero is Day 0');
assert_same('Day 1', pf_day(1), 'day one is Day 1');
assert_same('Day 180', pf_day(180), 'the 180-day probation limit renders plainly');
assert_same('Day 0', pf_day(-1), 'a negative day is clamped to 0, never shown as Day -1');

/* =========================================================
   pf_dept_label -- display normalization only
   ========================================================= */

pf_case('pf_dept_label');

assert_same('Food', pf_dept_label('food'), 'a lowercase department is title-cased');
assert_same('Sales', pf_dept_label('  SALES '), 'surrounding whitespace is trimmed and casing normalized');
assert_same('Food Service', pf_dept_label('food service'), 'multi-word departments keep their spaces');
assert_same('', pf_dept_label(''), 'an empty department stays empty');
assert_same('', pf_dept_label(null), 'null normalizes to an empty string');
assert_same('', pf_dept_label('   '), 'whitespace-only normalizes to an empty string');

assert_no_php_warnings();
pf_summary();