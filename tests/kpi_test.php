<?php
// Unit tests for kpi_templates.php -- the pure KPI maths shared by the rating
// entry, KPIs board and report snapshot paths.
//
// kpi_templates.php only reaches for Firestore behind
// `if (function_exists('firestore_get_document'))`, so requiring it in a CLI
// process WITHOUT firebase_init.php yields the deterministic base templates.
// That is the whole trick that makes this file testable offline: no mocking, no
// emulator, no network.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('kpi_templates.php');

require_once __DIR__ . '/../kpi_templates.php';

/* =========================================================
   Template shape (the manuscript scope: 5 industries x 4 KPIs)
   ========================================================= */

pf_case('template registry');

$templates = kpi_templates();
assert_same(5, count($templates), 'five industry templates exist');
assert_same(
  ['retail', 'bpo', 'food_service', 'logistics', 'construction'],
  array_keys($templates),
  'template keys match the documented industries'
);

foreach ($templates as $key => $template) {
  pf_case("template '{$key}'");
  assert_true(isset($template['label']) && $template['label'] !== '', "{$key} has a display label");
  assert_same(4, count($template['kpis']), "{$key} ships four KPIs");

  $shapeOk = true;
  $targetRangeOk = true;
  foreach ($template['kpis'] as $kpi) {
    if (!isset($kpi['key'], $kpi['name'], $kpi['target'])) {
      $shapeOk = false;
      continue;
    }
    if (!is_string($kpi['key']) || $kpi['key'] === '' || !is_string($kpi['name']) || $kpi['name'] === '') {
      $shapeOk = false;
    }
    if (!is_numeric($kpi['target']) || $kpi['target'] < 1.0 || $kpi['target'] > 5.0) {
      $targetRangeOk = false;
    }
  }

  assert_true($shapeOk, "{$key} KPIs each carry a non-empty key and name");
  assert_true($targetRangeOk, "{$key} targets sit inside the 1.0-5.0 scale");

  $keys = array_column($template['kpis'], 'key');
  assert_same(count($keys), count(array_unique($keys)), "{$key} KPI keys are unique");
}

/* =========================================================
   Industry resolution and fallbacks
   ========================================================= */

pf_case('kpi_template_for resolution');

assert_same('Retail', kpi_template_for('retail')['label'], 'retail resolves by key');
assert_same('Retail', kpi_template_for('RETAIL')['label'], 'lookup is case-insensitive');
assert_same('Retail', kpi_template_for('  retail  ')['label'], 'lookup is trimmed');
assert_same('BPO', kpi_template_for('bpo')['label'], 'bpo resolves');
assert_same('Food Service', kpi_template_for('food_service')['label'], 'food_service resolves');
assert_same('Logistics', kpi_template_for('logistics')['label'], 'logistics resolves');
assert_same('Construction', kpi_template_for('construction')['label'], 'construction resolves');

assert_same('Retail', kpi_template_for('')['label'], 'empty industry falls back to retail');
assert_same('Retail', kpi_template_for(null)['label'], 'null industry falls back to retail');
assert_same('Retail', kpi_template_for('does_not_exist')['label'], 'unknown industry falls back to retail');

kpi_template_invalidate();
assert_same('Retail', kpi_template_for('retail')['label'], 'full invalidation keeps lookups working');
assert_same('BPO', kpi_template_for('bpo')['label'], 'lookups still work after invalidation');
kpi_template_invalidate('retail');
assert_same('Retail', kpi_template_for('retail')['label'], 'per-industry invalidation keeps lookups working');

/* =========================================================
   kpi_status_for_score thresholds
   ========================================================= */

pf_case('kpi_status_for_score');

assert_same(
  ['status' => 'Exceeding', 'statusClass' => 'status-good'],
  kpi_status_for_score(4.5, 4.0),
  'well above target -> Exceeding'
);
assert_same(
  ['status' => 'Exceeding', 'statusClass' => 'status-good'],
  kpi_status_for_score(4.0, 4.0),
  'exactly on target -> Exceeding (the comparison is >=)'
);
assert_same(
  ['status' => 'Warning', 'statusClass' => 'status-warning'],
  kpi_status_for_score(3.9, 4.0),
  'just below target -> Warning'
);
assert_same(
  ['status' => 'Warning', 'statusClass' => 'status-warning'],
  kpi_status_for_score(3.2, 4.0),
  'exactly target - 0.8 -> Warning (the boundary is inclusive)'
);
assert_same(
  ['status' => 'Below Target', 'statusClass' => 'status-danger'],
  kpi_status_for_score(3.19, 4.0),
  'below target - 0.8 -> Below Target'
);
assert_same(
  ['status' => 'Below Target', 'statusClass' => 'status-danger'],
  kpi_status_for_score(0.0, 4.0),
  'zero score -> Below Target'
);
assert_same(
  ['status' => 'Exceeding', 'statusClass' => 'status-good'],
  kpi_status_for_score(5.0, 4.5),
  'a perfect score against a 4.5 target -> Exceeding'
);

/* =========================================================
   ratings_for_employee -- filtering and ordering
   ========================================================= */

pf_case('ratings_for_employee');

$ratings = [
  ['employeeUid' => 'u1', 'ratedAt' => '2026-01-05T09:00:00Z', 'scores' => ['a' => 3.0]],
  ['employeeUid' => 'u1', 'ratedAt' => '2026-01-19T09:00:00Z', 'scores' => ['a' => 5.0]],
  ['employeeUid' => 'u2', 'ratedAt' => '2026-01-12T09:00:00Z', 'scores' => ['a' => 1.0]],
  ['employeeUid' => 'u1', 'ratedAt' => '2026-01-12T09:00:00Z', 'scores' => ['a' => 4.0]],
];

$mine = ratings_for_employee($ratings, 'u1');
assert_same(3, count($mine), "only the requested employee's ratings are returned");
assert_same(
  ['2026-01-19T09:00:00Z', '2026-01-12T09:00:00Z', '2026-01-05T09:00:00Z'],
  array_column($mine, 'ratedAt'),
  'ratings come back newest first'
);
assert_same([], ratings_for_employee([], 'u1'), 'an empty ratings list yields an empty result');
assert_same([], ratings_for_employee([['scores' => []]], 'u1'), 'a doc with no employeeUid is ignored');
assert_same([], ratings_for_employee($ratings, 'nobody'), 'an unknown uid yields an empty result');

// Documents that never got a ratedAt must not emit warnings while sorting.
$undated = [
  ['employeeUid' => 'u9', 'scores' => ['a' => 2.0]],
  ['employeeUid' => 'u9', 'ratedAt' => '2026-02-01T00:00:00Z', 'scores' => ['a' => 3.0]],
];
assert_same(2, count(ratings_for_employee($undated, 'u9')), 'a missing ratedAt key is tolerated');

/* =========================================================
   employee_kpi_summary -- the number the dashboard shows
   ========================================================= */

pf_case('employee_kpi_summary (no ratings)');

$empty = employee_kpi_summary([], 'u1', 'retail');
assert_same(false, $empty['hasData'], 'no ratings -> hasData is false');
assert_same(null, $empty['score'], 'no ratings -> score is null');
assert_same(0, $empty['ratingCount'], 'no ratings -> ratingCount is 0');
assert_same(null, $empty['lastRatedAt'], 'no ratings -> lastRatedAt is null');
assert_same(4.175, round($empty['targetAvg'], 3), 'targetAvg is the mean of the retail targets (4.175)');

pf_case('employee_kpi_summary (newest rating wins)');

$withData = employee_kpi_summary([
  ['employeeUid' => 'u1', 'ratedAt' => '2026-01-05T00:00:00Z', 'scores' => ['sales_target' => 2.0, 'customer_service' => 1.0]],
  ['employeeUid' => 'u1', 'ratedAt' => '2026-01-19T00:00:00Z', 'scores' => ['sales_target' => 4.0, 'customer_service' => 3.0, 'inventory_accuracy' => 0.0]],
], 'u1', 'retail');

assert_same(true, $withData['hasData'], 'a rating exists -> hasData is true');
assert_same(3.5, $withData['score'], 'score averages the NEWEST rating and discards zero scores (4.0 + 3.0 -> 3.5)');
assert_same(2, $withData['ratingCount'], 'ratingCount counts every rating, not just the newest');
assert_same('2026-01-19T00:00:00Z', $withData['lastRatedAt'], 'lastRatedAt is the newest ratedAt');
assert_same(4.175, round($withData['targetAvg'], 3), 'targetAvg still comes from the industry template');

pf_case('employee_kpi_summary (edge cases)');

$allZero = employee_kpi_summary([
  ['employeeUid' => 'u1', 'ratedAt' => '2026-01-05T00:00:00Z', 'scores' => ['sales_target' => 0.0, 'customer_service' => 0.0]],
], 'u1', 'retail');
assert_same(false, $allZero['hasData'], 'a rating whose scores are all 0 -> hasData is false');
assert_same(null, $allZero['score'], 'a rating whose scores are all 0 -> score is null');
assert_same(1, $allZero['ratingCount'], 'the all-zero rating still counts in ratingCount');

$noScores = employee_kpi_summary([
  ['employeeUid' => 'u1', 'ratedAt' => '2026-01-05T00:00:00Z'],
], 'u1', 'retail');
assert_same(false, $noScores['hasData'], 'a rating with no scores key -> hasData is false');
assert_same(null, $noScores['score'], 'a rating with no scores key -> score is null');

$numericStrings = employee_kpi_summary([
  ['employeeUid' => 'u1', 'ratedAt' => '2026-01-05T00:00:00Z', 'scores' => ['a' => '4.0', 'b' => '2.0']],
], 'u1', 'retail');
assert_same(3.0, $numericStrings['score'], 'string scores are cast to float (4.0 and 2.0 -> 3.0)');

assert_same(
  4.175,
  round(employee_kpi_summary([], 'u1', 'not_an_industry')['targetAvg'], 3),
  'an unknown industry falls back to the retail target average'
);

assert_no_php_warnings();
pf_summary();