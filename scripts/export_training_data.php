<?php
// Export real training data for the RF pipeline (WS5 data readiness).
//
// Usage (CLI only): php scripts/export_training_data.php [out.csv]
//   No arg prints CSV to STDOUT.
//
// WHAT IT PRODUCES: one row per (probationary employee, calendar month)
// with that month's per-KPI score averages (raw template keys), the
// employer-approved training for the month (if any), and the current
// regularization record. Downstream, ml/train.py normalizes raw KPI keys
// to the 5 canonical competencies via ml/predict.py KEY_ALIASES and
// ml/labels.py labeling.
//
// LABEL POLICY (proposed — confirm with the thesis adviser before
// retraining on this): the employer-approved training_type for the month
// is the observed ground-truth label; months without an approval carry an
// empty label and train only the classifier head. Do NOT retrain on
// Gemini-authored text, threshold fallbacks, or unreviewed months.
//
//_COLUMNS_: employeeUid, month (Y-m), industry, jobRole, ratingCount,
// approvedTrainingType, approvedCompetency, regularizationRecommendation,
// then one column per raw KPI key seen in the data (sorted union).
//
// NOTE: needs live Firestore credentials (GOOGLE_APPLICATION_CREDENTIALS);
// it cannot run in the offline test harness. Verify with php -l only.

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/../firebase_init.php';

$outPath = $argv[1] ?? null;

$users = [];
foreach (firestore_list_documents('Users') as $doc) {
    $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));

    if (strpos($roleKey, 'probation') === false) {
        continue;
    }

    $uid = (string) ($doc['uid'] ?? '');

    if ($uid === '') {
        continue;
    }

    $users[$uid] = $doc;
}

if (!$users) {
    fwrite(STDERR, "No probationary users found.\n");
    exit(1);
}

// Group ratings by (uid, month).
$groups = [];

foreach (firestore_list_documents('Ratings') as $rating) {
    $uid = (string) ($rating['employeeUid'] ?? '');

    if ($uid === '' || !isset($users[$uid])) {
        continue;
    }

    $ratedAt = (string) ($rating['ratedAt'] ?? '');
    $ts = $ratedAt !== '' ? strtotime($ratedAt) : false;

    if ($ts === false) {
        continue;
    }

    $month = date('Y-m', $ts);
    $key = $uid . '|' . $month;

    if (!isset($groups[$key])) {
        $groups[$key] = [
            'uid' => $uid,
            'month' => $month,
            'ratingCount' => 0,
            'scores' => [],
            'approved' => null,
        ];
    }

    $groups[$key]['ratingCount']++;

    $scores = (isset($rating['scores']) && is_array($rating['scores']))
        ? $rating['scores']
        : [];

    foreach ($scores as $kpiKey => $score) {
        if (!is_numeric($score) || (float) $score <= 0) {
            continue;
        }

        $groups[$key]['scores'][(string) $kpiKey][] = (float) $score;
    }

    $ai = $rating['aiRecommendations'] ?? null;

    if (
        $groups[$key]['approved'] === null &&
        is_array($ai) &&
        ($ai['status'] ?? '') === 'approved' &&
        !empty($ai['training_recommendations']) &&
        is_array($ai['training_recommendations'])
    ) {
        $top = $ai['training_recommendations'][0];
        $groups[$key]['approved'] = is_array($top) ? $top : null;
    }
}

// Sorted union of raw KPI keys becomes the dynamic tail of the header.
$kpiKeys = [];
foreach ($groups as $group) {
    foreach (array_keys($group['scores']) as $kpiKey) {
        $kpiKeys[$kpiKey] = true;
    }
}
$kpiKeys = array_keys($kpiKeys);
sort($kpiKeys);

$header = array_merge(
    [
        'employeeUid', 'month', 'industry', 'jobRole', 'ratingCount',
        'approvedTrainingType', 'approvedCompetency',
        'regularizationRecommendation',
    ],
    $kpiKeys
);

$handle = null;

if ($outPath !== null) {
    $handle = fopen($outPath, 'w');

    if ($handle === false) {
        fwrite(STDERR, "Cannot open {$outPath} for writing.\n");
        exit(1);
    }
} else {
    $handle = STDOUT;
}

fputcsv($handle, $header);

foreach ($groups as $group) {
    $user = $users[$group['uid']];
    $approved = $group['approved'];

    $row = [
        $group['uid'],
        $group['month'],
        $user['industry'] ?? 'retail',
        $user['jobRole'] ?? 'Probationary Employee',
        $group['ratingCount'],
        is_array($approved) ? ($approved['training_type'] ?? '') : '',
        is_array($approved) ? ($approved['competency_area'] ?? '') : '',
        $user['regularizationRecommendation'] ?? '',
    ];

    foreach ($kpiKeys as $kpiKey) {
        $vals = $group['scores'][$kpiKey] ?? [];
        $row[] = $vals ? round(array_sum($vals) / count($vals), 2) : '';
    }

    fputcsv($handle, $row);
}

if ($outPath !== null) {
    fclose($handle);
    echo 'Wrote ' . count($groups) . " month-rows to {$outPath}\n";
}
