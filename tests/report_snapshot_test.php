<?php
// Unit tests for report_snapshot_from_doc() (Employer/includes/report_files.php):
// the shared shape behind generate, verify and rebuild. If these three ever
// hash different shapes, live reports falsely flip to "tampered".

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('report snapshot');

require_once __DIR__ . '/../Employer/includes/report_files.php';
require_once __DIR__ . '/../Employer/includes/pdf_writer.php';
require_once __DIR__ . '/../kpi_templates.php';

pf_case('shape');

$snap = report_snapshot_from_doc([]);
assert_same(15, count($snap), 'fifteen canonical keys');
assert_true(!isset($snap['fileBytes']) && !isset($snap['status']), 'no file/status keys leak in');

$full = report_snapshot_from_doc([
    'employeeUid' => 'u1',
    'scores' => ['aht' => 4.0],
    'targets' => [],
    'aiRecommendations' => null,
    'extra' => 'dropped',
]);
assert_same('u1', $full['employeeUid'], 'values pass through');
assert_same(null, $full['targets'], 'empty arrays normalize to null');
assert_same(null, $full['generatedBy'], 'missing keys default to null');
assert_false(isset($full['extra']), 'unknown keys dropped');

pf_case('fingerprint stability across the lifecycle');

$atGenerate = report_snapshot_from_doc([
    'employeeUid' => 'u1',
    'employeeName' => 'Test User',
    'reportType' => 'monthly_summary',
    'reportTypeLabel' => 'Monthly Performance Summary',
    'industry' => 'bpo',
    'templateLabel' => 'BPO',
    'scores' => ['aht' => 4.0, 'attendance' => 5.0],
    'targets' => ['aht' => 4.0, 'attendance' => 4.5],
    'generatedAt' => '2026-09-30T10:00:00+00:00',
    'generatedBy' => 'Ralph Rocha',
]);

// What the stored doc looks like later: file fields added, key order
// shuffled by the round trip, nulls possibly stripped.
$atVerify = [
    'fileSha256' => 'x',
    'fileBytes' => 1234,
    'filePath' => 'storage/reports/r.pdf',
    'status' => 'Finalized',
    'generatedBy' => 'Ralph Rocha',
    'generatedAt' => '2026-09-30T10:00:00+00:00',
    'targets' => ['attendance' => 4.5, 'aht' => 4.0],
    'scores' => ['attendance' => 5.0, 'aht' => 4.0],
    'employeeName' => 'Test User',
    'employeeUid' => 'u1',
    'reportTypeLabel' => 'Monthly Performance Summary',
    'reportType' => 'monthly_summary',
    'industry' => 'bpo',
    'templateLabel' => 'BPO',
];

assert_same(
    report_pdf_fingerprint($atGenerate),
    report_pdf_fingerprint(report_snapshot_from_doc($atVerify)),
    'generate-time and verify-time shapes hash identically'
);
assert_true(
    report_pdf_fingerprint($atGenerate) !== report_pdf_fingerprint(
        array_merge($atGenerate, ['scores' => ['aht' => 3.0, 'attendance' => 5.0]])
    ),
    'score tampering changes the fingerprint'
);

pf_case('content blocks');

$blocks = report_pdf_blocks($atGenerate, ['kpis' => [
    ['key' => 'aht', 'name' => 'Average Handle Time', 'target' => 4.0],
    ['key' => 'attendance', 'name' => 'Attendance', 'target' => 4.5],
]]);
assert_same(3, count($blocks), 'heading + KPI table + average line emitted');
assert_same('table', $blocks[1][0], 'second block is the KPI table');
assert_same(2, count($blocks[1][1]['rows']), 'both KPI rows present');
assert_same(['KPI', 'Score', 'Target', 'Status'], $blocks[1][1]['head'], 'status column present');
assert_same('Average 4.5 across 2 rated KPIs.', $blocks[2][1], 'average line math');

$unrated = report_pdf_blocks(
    ['scores' => ['aht' => 0.0], 'targets' => ['aht' => 4.0]],
    ['kpis' => [['key' => 'aht', 'name' => 'Average Handle Time', 'target' => 4.0]]]
);
assert_same('No rated KPIs in this report.', end($unrated)[1], 'all-unrated edge');

$withStatus = report_pdf_blocks(
    ['scores' => ['aht' => 4.5], 'targets' => ['aht' => 4.0]],
    ['kpis' => [['key' => 'aht', 'name' => 'Average Handle Time', 'target' => 4.0]]]
);
assert_same('Exceeding', $withStatus[1][1]['rows'][0][3], 'status mirrors threshold rule');

$withAi = $atGenerate;
$withAi['aiRecommendations'] = [
    'summary' => 'Needs coaching.',
    'training_recommendations' => [[
        'competency_area' => 'task_completion',
        'training_type' => 'mentoring',
        'description' => 'Pair up.',
        'timeline' => '4-6 weeks',
    ]],
];
$aiBlocks = report_pdf_blocks($withAi, ['kpis' => []]);
assert_true(count($aiBlocks) > count($blocks), 'AI section appears when recs exist');

$withReg = $atGenerate;
$withReg['regularizationRecommendation'] = 'recommended';
$regBlocks = report_pdf_blocks($withReg, ['kpis' => []]);
assert_true(count($regBlocks) > count($blocks), 'regularization section appears when decided');

pf_case('no diagnostics');

assert_no_php_warnings();

pf_summary();
