<?php
// Unit tests for ml/training_catalog.py (RF-owned training picks).
// Shells out to the project Python (offline-safe: the module is stdlib-only
// and --dump/--pick never touch the network, the model, or Gemini).
// If no Python interpreter exists, the file reports zero assertions and
// passes so non-ML environments are not broken by it.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('training catalog');

function ml_python()
{
    foreach (['python', 'py'] as $bin) {
        $code = 0;
        exec(escapeshellarg($bin) . ' --version 2>&1', $ignored, $code);

        if ($code === 0) {
            return $bin;
        }
    }

    return null;
}

$python = ml_python();

if ($python === null) {
    echo "note: no Python interpreter found; catalog checks skipped\n";
    pf_summary();
}

function ml_catalog_run(string $python, string $args): array
{
    $cmd = escapeshellarg($python) . ' '
        . escapeshellarg(__DIR__ . '/../ml/training_catalog.py')
        . ' ' . $args . ' 2>&1';
    $output = [];
    $code = 0;
    exec($cmd, $output, $code);

    return [$code, implode("\n", $output)];
}

pf_case('catalog shape');

[$code, $dump] = ml_catalog_run($python, '--dump');
assert_same(0, $code, 'catalog --dump exits clean');
$catalog = json_decode($dump, true);
assert_true(is_array($catalog), 'catalog dumps valid JSON');
assert_same(10, count($catalog), 'ten (competency x weak-class) entries');

$types = ['on-the-job coaching', 'workshop', 'self-directed learning', 'mentoring'];
$seenPairs = [];

foreach ($catalog as $entry) {
    assert_true(
        isset($entry['competency'], $entry['rf_class'], $entry['training_type'],
            $entry['title'], $entry['description'], $entry['timeline']),
        'entry carries all pick fields'
    );
    assert_true(
        in_array($entry['training_type'], $types, true),
        'training type stays inside the manuscript four'
    );
    assert_true(
        $entry['title'] !== '' && $entry['description'] !== '' && $entry['timeline'] !== '',
        'pick text is non-empty'
    );
    assert_true(
        in_array($entry['rf_class'], ['needs_improvement', 'critical_gap'], true),
        'only weak classes have picks'
    );
    $seenPairs[$entry['competency'] . '|' . $entry['rf_class']] = true;
}

assert_same(10, count($seenPairs), 'every pair unique');

pf_case('pick lookup');

[$code, $pickJson] = ml_catalog_run($python, '--pick task_completion critical_gap');
assert_same(0, $code, 'pick exits clean');
$pick = json_decode($pickJson, true);
assert_same('task_completion', $pick['competency_area'], 'pick echoes competency');
assert_same('critical_gap', $pick['rf_class'], 'pick echoes class');
assert_same('mentoring', $pick['training_type'], 'critical task gap mentors');

[$code, $noneJson] = ml_catalog_run($python, '--pick task_completion meets_expectations');
assert_same(0, $code, 'non-weak pick exits clean');
assert_same(null, json_decode($noneJson, true), 'meets_expectations picks nothing');

pf_case('no diagnostics');

assert_no_php_warnings();

pf_summary();
