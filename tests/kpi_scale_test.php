<?php
// Unit tests for flexible KPI scoring scales (kpi_templates.php):
// kpi_scale_for() defaults + sanitization, kpi_clamp_score(), the
// scale-relative kpi_status_for_scale() (including byte-compatibility
// with the legacy fixed 0.8 band on 1-5), and kpi_by_key().

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('kpi scales');

require_once __DIR__ . '/../kpi_templates.php';

/* =========================================================
   kpi_scale_for()
   ========================================================= */

pf_case('scale defaults');

$def = kpi_scale_for(['key' => 'x']);
assert_same(1.0, $def['min'], 'default min is 1.0');
assert_same(5.0, $def['max'], 'default max is 5.0');
assert_same(0.1, $def['step'], 'default step is 0.1');
assert_same(['min' => 1.0, 'max' => 5.0, 'step' => 0.1], kpi_scale_for([]), 'empty entry defaults');
assert_same(['min' => 1.0, 'max' => 5.0, 'step' => 0.1], kpi_scale_for(null), 'null defaults');

pf_case('scale sanitization');

assert_same(
    ['min' => 0.0, 'max' => 100.0, 'step' => 1.0],
    kpi_scale_for(['scale' => ['min' => 0, 'max' => 100, 'step' => 1]]),
    'percent scale honored'
);
assert_same(
    ['min' => 1.0, 'max' => 5.0, 'step' => 0.1],
    kpi_scale_for(['scale' => ['min' => 5, 'max' => 1]]),
    'inverted range falls back to default'
);
assert_same(
    ['min' => 1.0, 'max' => 5.0, 'step' => 0.1],
    kpi_scale_for(['scale' => ['min' => 'low', 'max' => 'high', 'step' => 'wide']]),
    'non-numeric scale falls back to default'
);
assert_same(0.1, kpi_scale_for(['scale' => ['min' => 0, 'max' => 10, 'step' => 0]])['step'], 'zero step falls back to 0.1');

/* =========================================================
   kpi_clamp_score()
   ========================================================= */

pf_case('clamping');

assert_same(5.0, kpi_clamp_score([], 9.9), 'clamps above max');
assert_same(1.0, kpi_clamp_score([], -3.0), 'clamps below min');
assert_same(3.7, kpi_clamp_score([], 3.7), 'in-range passes through');
assert_same(100.0, kpi_clamp_score(['scale' => ['min' => 0, 'max' => 100, 'step' => 1]], 140.0), 'percent clamps high');
assert_same(0.0, kpi_clamp_score(['scale' => ['min' => 0, 'max' => 100, 'step' => 1]], -2.0), 'percent clamps low');
assert_same(1.0, kpi_clamp_score([], 'nonsense'), 'non-numeric becomes min');

/* =========================================================
   kpi_status_for_scale()
   ========================================================= */

pf_case('default scale matches legacy bands');

foreach ([4.0, 4.2, 4.5] as $target) {
    foreach ([5.0, 4.0, 3.0, 1.0] as $score) {
        assert_same(
            kpi_status_for_score($score, $target),
            kpi_status_for_scale($score, $target),
            "identical to legacy at score {$score} target {$target}"
        );
        assert_same(
            kpi_status_for_score($score, $target),
            kpi_status_for_scale($score, $target, ['min' => 1.0, 'max' => 5.0, 'step' => 0.1]),
            "identical with explicit 1-5 scale at {$score}/{$target}"
        );
    }
}

pf_case('percent scale bands');

assert_same('Exceeding', kpi_status_for_scale(95.0, 90.0, ['min' => 0.0, 'max' => 100.0, 'step' => 1.0])['status'], 'percent exceeding');
assert_same('Warning', kpi_status_for_scale(75.0, 90.0, ['min' => 0.0, 'max' => 100.0, 'step' => 1.0])['status'], 'percent warning (band is 20)');
assert_same('Below Target', kpi_status_for_scale(69.9, 90.0, ['min' => 0.0, 'max' => 100.0, 'step' => 1.0])['status'], 'percent below');

/* =========================================================
   kpi_by_key()
   ========================================================= */

pf_case('lookup');

$tpl = kpi_template_for('retail');
$found = kpi_by_key($tpl, 'attendance');
assert_true(is_array($found) && $found['name'] === 'Attendance & Punctuality', 'finds KPI by key');
assert_same(null, kpi_by_key($tpl, 'nope'), 'missing key returns null');
assert_same(null, kpi_by_key(['kpis' => []], 'attendance'), 'empty template returns null');

pf_case('no diagnostics');

assert_no_php_warnings();

pf_summary();
