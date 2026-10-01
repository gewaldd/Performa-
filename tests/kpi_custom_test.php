<?php
// Unit tests for custom-KPI lifecycle helpers (kpi_templates.php).
// kpi_is_deletable() is pure and fully covered here. delete_custom_kpi()
// writes Firestore directly and is therefore exercised live, not in this
// offline harness; its guard rule (custom_ prefix only) is asserted below
// through the same predicate it enforces.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('custom KPI lifecycle');

require_once __DIR__ . '/../kpi_templates.php';

pf_case('kpi_is_deletable');

assert_true(kpi_is_deletable(['key' => 'custom_upsell_rate']), 'custom slug deletable');
assert_true(kpi_is_deletable(['key' => 'custom_a']), 'short custom slug deletable');
assert_false(kpi_is_deletable(['key' => 'attendance']), 'base KPI not deletable');
assert_false(kpi_is_deletable(['key' => 'call_quality']), 'base KPI not deletable');
assert_false(kpi_is_deletable(['key' => '']), 'empty key not deletable');
assert_false(kpi_is_deletable([]), 'missing key not deletable');
assert_false(kpi_is_deletable(['key' => 'notcustom_x']), 'lookalike prefix not deletable');
assert_false(kpi_is_deletable(['key' => 'CUSTOM_x']), 'prefix match is case-sensitive');

pf_case('base templates stay protected');

foreach (kpi_templates() as $industry => $template) {
    foreach ($template['kpis'] as $kpi) {
        assert_false(
            kpi_is_deletable($kpi),
            "{$industry}/{$kpi['key']} is a protected base KPI"
        );
    }
}

pf_case('no diagnostics');

assert_no_php_warnings();

pf_summary();
