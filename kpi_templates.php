<?php
// Pre-built KPI templates by industry, per the capstone manuscript scope
// (retail, BPO, food service, logistics, construction). Each KPI has a
// target score out of 5.0. Used by Employer/rate_employee.php (write path)
// and Employer/kpis.php (read/display path).

function kpi_templates(): array
{
    return [
        'retail' => [
            'label' => 'Retail',
            'kpis' => [
                [
                    'key' => 'sales_target',
                    'name' => 'Sales Target Achievement',
                    'target' => 4.0,
                    'category' => 'productivity',
                    'weight' => 25,
                    'description' => 'Attainment against the selling period quota.',
                ],
                [
                    'key' => 'customer_service',
                    'name' => 'Customer Service',
                    'target' => 4.2,
                    'category' => 'customer_focus',
                    'weight' => 25,
                    'description' => 'Observed service behavior and queue handling in live shifts.',
                ],
                [
                    'key' => 'inventory_accuracy',
                    'name' => 'Inventory Accuracy',
                    'target' => 4.0,
                    'category' => 'operational_precision',
                    'weight' => 25,
                    'description' => 'Cycle count accuracy and stockroom discipline.',
                ],
                [
                    'key' => 'attendance',
                    'name' => 'Attendance & Punctuality',
                    'target' => 4.5,
                    'category' => 'compliance',
                    'weight' => 25,
                    'description' => 'Reliable attendance and on time shift starts.',
                ],
            ],
        ],
        'bpo' => [
            'label' => 'BPO',
            'kpis' => [
                [
                    'key' => 'call_quality',
                    'name' => 'Call Quality Score',
                    'target' => 4.2,
                    'category' => 'quality',
                    'weight' => 25,
                    'description' => 'Monitored call quality against the evaluation form.',
                ],
                [
                    'key' => 'aht',
                    'name' => 'Average Handle Time Adherence',
                    'target' => 4.0,
                    'category' => 'productivity',
                    'weight' => 25,
                    'description' => 'Handling contacts inside the target talk time window.',
                ],
                [
                    'key' => 'customer_satisfaction',
                    'name' => 'Customer Satisfaction (CSAT)',
                    'target' => 4.3,
                    'category' => 'customer_focus',
                    'weight' => 25,
                    'description' => 'Post contact survey satisfaction scores.',
                ],
                [
                    'key' => 'attendance',
                    'name' => 'Attendance & Punctuality',
                    'target' => 4.5,
                    'category' => 'compliance',
                    'weight' => 25,
                    'description' => 'Reliable attendance and on time shift starts.',
                ],
            ],
        ],
        'food_service' => [
            'label' => 'Food Service',
            'kpis' => [
                [
                    'key' => 'food_safety',
                    'name' => 'Food Safety Compliance',
                    'target' => 4.5,
                    'category' => 'compliance',
                    'weight' => 25,
                    'description' => 'Hygiene, storage and temperature audit behavior.',
                ],
                [
                    'key' => 'service_speed',
                    'name' => 'Service Speed',
                    'target' => 4.0,
                    'category' => 'productivity',
                    'weight' => 25,
                    'description' => 'Table throughput and pickup time performance.',
                ],
                [
                    'key' => 'customer_service',
                    'name' => 'Customer Service',
                    'target' => 4.2,
                    'category' => 'customer_focus',
                    'weight' => 25,
                    'description' => 'Guest recovery, courtesy and order accuracy.',
                ],
                [
                    'key' => 'attendance',
                    'name' => 'Attendance & Punctuality',
                    'target' => 4.5,
                    'category' => 'compliance',
                    'weight' => 25,
                    'description' => 'Reliable attendance and on time shift starts.',
                ],
            ],
        ],
        'logistics' => [
            'label' => 'Logistics',
            'kpis' => [
                [
                    'key' => 'delivery_accuracy',
                    'name' => 'Delivery Accuracy',
                    'target' => 4.3,
                    'category' => 'operational_precision',
                    'weight' => 25,
                    'description' => 'Correct package, quantity and documentation.',
                ],
                [
                    'key' => 'on_time_rate',
                    'name' => 'On-Time Delivery Rate',
                    'target' => 4.2,
                    'category' => 'productivity',
                    'weight' => 25,
                    'description' => 'On schedule departures and route performance.',
                ],
                [
                    'key' => 'safety_compliance',
                    'name' => 'Safety Compliance',
                    'target' => 4.5,
                    'category' => 'compliance',
                    'weight' => 25,
                    'description' => 'Vehicle checks, PPE and warehouse safety behavior.',
                ],
                [
                    'key' => 'attendance',
                    'name' => 'Attendance & Punctuality',
                    'target' => 4.5,
                    'category' => 'compliance',
                    'weight' => 25,
                    'description' => 'Reliable attendance and on time shift starts.',
                ],
            ],
        ],
        'construction' => [
            'label' => 'Construction',
            'kpis' => [
                [
                    'key' => 'safety_compliance',
                    'name' => 'Safety Compliance',
                    'target' => 4.5,
                    'category' => 'compliance',
                    'weight' => 25,
                    'description' => 'PPE, toolbox talks and site housekeeping.',
                ],
                [
                    'key' => 'work_quality',
                    'name' => 'Work Quality',
                    'target' => 4.2,
                    'category' => 'quality',
                    'weight' => 25,
                    'description' => 'Defect free output against specifications.',
                ],
                [
                    'key' => 'productivity',
                    'name' => 'Productivity',
                    'target' => 4.0,
                    'category' => 'productivity',
                    'weight' => 25,
                    'description' => 'Daily output versus the crew plan.',
                ],
                [
                    'key' => 'attendance',
                    'name' => 'Attendance & Punctuality',
                    'target' => 4.5,
                    'category' => 'compliance',
                    'weight' => 25,
                    'description' => 'Reliable attendance and on time shift starts.',
                ],
            ],
        ],
    ];
}

function kpi_template_for(?string $industry): array
{
    $key = strtolower(trim((string) $industry));
    if ($key === '') {
        $key = 'retail';
    }

    // Per-request memo: the Employer dashboard calls this once per employee
    // (via employee_kpi_summary). Each uncached call costs up to 2 Firestore
    // round-trips, so without this an N-employee dashboard pays 2N HTTPS
    // requests on every cache miss. Templates are read-only within a request
    // except via add_custom_kpi()/set_kpi_target_override() below, which
    // invalidate this memo after writing.
    if (isset($GLOBALS['__kpi_template_memo'][$key])) {
        return $GLOBALS['__kpi_template_memo'][$key];
    }

    $templates = kpi_templates();
    $template = $templates[$key] ?? $templates['retail'];

    // Merge any employer-added custom KPIs and target overrides for this industry,
    // stored in Firestore so they persist and apply everywhere this template is used
    // (rating entry, KPIs dashboard, report snapshots).
    if (function_exists('firestore_get_document')) {
        try {
            $custom = firestore_get_document('CustomKpis', $key);
            if (!empty($custom['kpis']) && is_array($custom['kpis'])) {
                foreach ($custom['kpis'] as $ck) {
                    if (!empty($ck['key']) && !empty($ck['name'])) {
                        // Newer entries carry category/weight/description inline;
                        // legacy docs fall back the same way the page does.
                        $customCategory = strtolower(trim((string) ($ck['category'] ?? '')));
                        $customWeightRaw = $ck['weight'] ?? null;
                        $customWeight = is_numeric($customWeightRaw)
                            ? (float) $customWeightRaw
                            : null;
                        $customDescription = trim((string) ($ck['description'] ?? ''));
                        $template['kpis'][] = [
                            'key' => (string) $ck['key'],
                            'name' => $ck['name'],
                            'target' => isset($ck['target']) ? (float) $ck['target'] : 4.0,
                            'category' => $customCategory !== ''
                                ? $customCategory
                                : 'uncategorized',
                            'weight' => $customWeight,
                            'description' => $customDescription,
                        ];
                    }
                }
            }
        } catch (\Throwable $e) {
            // fall back to base template if the lookup fails
        }
        try {
            $overrides = firestore_get_document('KpiOverrides', $key);
            if (!empty($overrides) && is_array($overrides)) {
                foreach ($template['kpis'] as &$kpi) {
                    if (isset($overrides[$kpi['key']])) {
                        $kpi['target'] = (float) $overrides[$kpi['key']];
                    }
                }
                unset($kpi);
            }
        } catch (\Throwable $e) {
            // ignore, use base/custom targets
        }
    }

    $GLOBALS['__kpi_template_memo'][$key] = $template;

    return $template;
}

function kpi_template_invalidate(?string $industry = null): void
{
    if ($industry === null) {
        $GLOBALS['__kpi_template_memo'] = [];
        return;
    }

    unset($GLOBALS['__kpi_template_memo'][strtolower(trim((string) $industry))]);
}

function add_custom_kpi(string $industry, string $name, float $target): void
{
    $key = strtolower(trim($industry));
    $slug = 'custom_' . preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($name)));
    $doc = firestore_get_document('CustomKpis', $key) ?? ['kpis' => []];
    $doc['kpis'] = $doc['kpis'] ?? [];
    $doc['kpis'][] = ['key' => $slug, 'name' => $name, 'target' => $target];
    firestore_write_document('CustomKpis', $key, $doc);
    kpi_template_invalidate($key);
}

// Display-only calibration metadata for one CustomKpis entry: category,
// weight and rubric. Stored inline on the SAME CustomKpis entry the targets
// live on (same doc, same row), never as a parallel document or side map,
// so targets, weights and rubrics read and invalidate together. Target
// overrides keep their own KpiOverrides doc; weights keep KpiWeights; merge
// order stays merge-custom -> override-targets, unchanged by these fields.
//
// NOTE: kept for the legacy scoring path (and its tests); live page code
// writes inline metadata through add_custom_kpi entries plus the shared
// add_custom_kpi_meta() shape described there.
function add_custom_kpi_meta(string $industry, string $kpiKey, ?string $category, ?float $weight, ?string $description): void
{
    $key = strtolower(trim($industry));
    $kpiKey = trim((string) $kpiKey);
    $doc = firestore_get_document('CustomKpis', $key) ?? [];
    $entries = (isset($doc['kpis']) && is_array($doc['kpis'])) ? $doc['kpis'] : [];

    $entry = [];
    $categorySlug = strtolower(trim((string) ($category ?? '')));
    if ($categorySlug !== '') {
        $entry['category'] = $categorySlug;
    }
    if (is_numeric($weight)) {
        $entry['weight'] = max(0.0, min(100.0, (float) $weight));
    }
    $rubricText = trim((string) ($description ?? ''));
    if ($rubricText !== '') {
        $entry['description'] = $rubricText;
    }

    if ($entry === []) {
        // Nothing to store — a bare add_custom_kpi() entry is complete.
        return;
    }

    $matched = false;
    foreach ($entries as $index => $row) {
        if (is_array($row) && ((string) ($row['key'] ?? '')) === $kpiKey) {
            $entries[$index] = array_merge($row, $entry);
            $matched = true;
            break;
        }
    }

    if (!$matched) {
        // Keep write+read symmetric for pre-inline docs that only carry the
        // slug: the entry still lands under the requested key; the page merge
        // (inline-first, side-map only for duplicates) resolves it the same.
        $entries[] = array_merge(['key' => $kpiKey], $entry);
    }

    $doc['kpis'] = array_values($entries);
    firestore_write_document('CustomKpis', $key, $doc);
    kpi_template_invalidate($key);
}

function set_kpi_target_override(string $industry, string $kpiKey, float $target): void
{
    $key = strtolower(trim($industry));
    $doc = firestore_get_document('KpiOverrides', $key) ?? [];
    $doc[$kpiKey] = $target;
    firestore_write_document('KpiOverrides', $key, $doc);
    kpi_template_invalidate($key);
}

// KPI weight overrides (the mockup's "Total Weight" / per-row "% weight"
// chips). Same shape as the target overrides above — sibling KpiWeights docs
// keyed by lowercase industry with numeric values per KPI key — but with a
// separate write path so a weight batch never clobbers target overrides:
//
//   setter: set_kpi_weight_overrides($industry, ['call_quality' => 25]).
//   reader: kpi_weight_overrides($industry) -> ['call_quality' => 25].
//
// Both live at the template level (every industry template carries its own
// weights), exactly like targets. Display-only: nothing in the scoring,
// status-classification or ML paths reads these.
function kpi_weight_overrides(?string $industry): array
{
    $key = strtolower(trim((string) $industry));

    if ($key === '' || !function_exists('firestore_get_document')) {
        return [];
    }

    if (isset($GLOBALS['__kpi_weight_memo'][$key])) {
        return $GLOBALS['__kpi_weight_memo'][$key];
    }

    $weights = [];

    try {
        $doc = firestore_get_document('KpiWeights', $key) ?? [];
        if (is_array($doc)) {
            foreach ($doc as $kpiKey => $weightRaw) {
                $weights[(string) $kpiKey] = (float) $weightRaw;
            }
        }
    } catch (\Throwable $e) {
        // fall through to no overrides — base/equal-split targets still render
    }

    $GLOBALS['__kpi_weight_memo'][$key] = $weights;

    return $weights;
}

function set_kpi_weight_overrides(?string $industry, array $weights): void
{
    $key = strtolower(trim((string) $industry));
    $doc = [];

    foreach ($weights as $kpiKey => $weightRaw) {
        $kpiKey = trim((string) $kpiKey);
        $weight = is_numeric($weightRaw) ? (float) $weightRaw : 0.0;
        $doc[$kpiKey] = max(0.0, min(100.0, $weight));
    }

    firestore_write_document('KpiWeights', $key, $doc);
    kpi_template_invalidate($key);
    unset($GLOBALS['__kpi_weight_memo'][$key]);
}

/*
 * Display weights for one merged template list. Merges base weights with any
 * KpiWeights overrides, then normalizes the WHOLE admitted list to 100%
 * when a non-empty set exists on the first pass (the no-override equal-split
 * rule AD-2), clamping every entry to a non-negative float. Entries with no
 * weight source keep their key in the output with the equal-split fallback.
 *
 * $kpis: the template's admitted KPI entries (base + custom + merged
 * description where relevant). $overrides: kpi_weight_overrides().
 * Returns ['<kpiKey>' => <display weight float>].
 */
function kpi_display_weights(array $kpis, array $overrides = []): array
{
    $admitted = [];
    foreach ($kpis as $kpi) {
        if (isset($kpi['key']) && ($kpi['__excluded'] ?? null) !== true) {
            $admitted[] = $kpi;
        }
    }

    $count = count($admitted);
    if ($count === 0) {
        // (1) Empty/zero-admitted input is unrenderable — always [].
        return [];
    }

    $baseRaw = [];
    foreach ($admitted as $kpi) {
        $baseRaw[] = is_numeric($kpi['weight'] ?? null) ? (float) $kpi['weight'] : null;
    }

    $display = [];
    $equal = 100.0 / $count;
    foreach ($admitted as $index => $kpi) {
        $key = (string) $kpi['key'];
        // (2)-(3) Overrides win; base weights fill the gaps; equal-split fills the rest.
        if (isset($overrides[$key]) && is_numeric($overrides[$key])) {
            $display[$key] = max(0.0, (float) $overrides[$key]);
            continue;
        }
        $display[$key] = $baseRaw[$index] !== null ? max(0.0, (float) $baseRaw[$index]) : $equal;
    }

    // (4) Renormalize the full set to 100% so the chip total is always honest.
    $total = array_sum($display);
    if ($total > 0) {
        foreach ($display as $key => $w) {
            $display[$key] = $w / $total * 100.0;
        }
    }

    // (5) Two decimals, stored high; the page rounds again for the chips.
    $out = [];
    foreach ($display as $key => $w) {
        $out[$key] = max(0.0, round($w, 2));
    }

    return $out;
}

/*
 * Category display label for a template entry. Raw stored values are
 * 'quality' / 'productivity' / 'customer_focus' / 'compliance' /
 * 'operational_precision'; anything else is title-cased from the slug.
 * Pure (offline-testable).
 */
function kpi_category_label(?string $category): string
{
    $raw = strtolower(trim((string) $category));

    if ($raw === '') {
        return '';
    }

    $known = [
        'quality' => 'Quality',
        'productivity' => 'Productivity',
        'customer_focus' => 'Customer Focus',
        'compliance' => 'Compliance',
        'operational_precision' => 'Operational Precision',
    ];

    if (isset($known[$raw])) {
        return $known[$raw];
    }

    return ucwords(str_replace(['_', '-'], ' ', $raw));
}

// Category group card payloads for one merged template list: per-category
// KPI counts plus the worst current-vs-target status in the group
// (score-significant only — the 'critical' tone is deliberately not used).
// $scoreByKey: ['<kpiKey>' => <float|null>] from the cohort or employee
// latest-rating lookup. Pure (offline-testable).
function kpi_category_groups(array $kpis, array $scoreByKey = []): array
{
    $groups = [];

    foreach ($kpis as $kpi) {
        if (!isset($kpi['key'])) {
            continue;
        }

        $category = strtolower(trim((string) ($kpi['category'] ?? '')));
        if ($category === '') {
            $category = 'uncategorized';
        }

        $target = isset($kpi['target']) && is_numeric($kpi['target']) ? (float) $kpi['target'] : null;
        $score = array_key_exists((string) $kpi['key'], $scoreByKey) ? $scoreByKey[(string) $kpi['key']] : null;
        $score = is_numeric($score) ? (float) $score : null;

        if (!isset($groups[$category])) {
            $groups[$category] = [
                'category' => $category,
                'label' => kpi_category_label($category),
                'count' => 0,
                'score' => null,
                'target' => null,
                'status' => 'No Data',
                'statusClass' => 'status-neutral',
            ];
        }

        $groups[$category]['count']++;

        $tone = $score !== null && $target !== null
            ? kpi_status_for_score($score, $target)['status']
            : 'No Data';

        $severity = [
            'No Data' => 0,
            'Exceeding' => 1,
            'Warning' => 2,
            'Below Target' => 3,
        ];

        $current = $groups[$category]['status'];
        if (($severity[$tone] ?? 0) > ($severity[$current] ?? 0)) {
            $groups[$category]['status'] = $tone;
            $groups[$category]['score'] = $score;
            $groups[$category]['target'] = $target;
            $groups[$category]['statusClass'] = kpi_status_for_score($score, $target)['statusClass'];
        }
    }

    return array_values($groups);
}

/*
 * Weighted calibration score for one rating: weighted average of its
 * positive scores (zeros excluded, per employee_kpi_summary), using display
 * weights renormalized over the rated subset only. DISPLAY-ONLY — the
 * unweighted average remains the score of record. Returns null with no
 * rated KPIs (supports the "why two numbers" copy).
 */
function employee_kpi_weighted_score(array $scores, array $weights): array
{
    $rated = [];
    foreach ($weights as $key => $w) {
        if (isset($scores[$key]) && (float) $scores[$key] > 0 && is_numeric($w) && (float) $w > 0) {
            $rated[$key] = ['score' => (float) $scores[$key], 'weight' => (float) $w];
        }
    }

    if (!$rated) {
        return ['score' => null, 'unweightedAvg' => null];
    }

    $total = array_sum(array_column($rated, 'weight'));
    $acc = 0.0;
    foreach ($rated as $row) {
        $acc += $row['score'] * ($row['weight'] / $total);
    }

    $vals = array_values(array_filter(array_map('floatval', $scores), function ($v) {
        return $v > 0;
    }));

    return [
        'score' => $acc,
        'unweightedAvg' => $vals ? array_sum($vals) / count($vals) : null,
    ];
}

function kpi_status_for_score(float $current, float $target): array
{
    if ($current >= $target) {
        return ['status' => 'Exceeding', 'statusClass' => 'status-good'];
    }
    if ($current >= $target - 0.8) {
        return ['status' => 'Warning', 'statusClass' => 'status-warning'];
    }
    return ['status' => 'Below Target', 'statusClass' => 'status-danger'];
}

// Fetch every Ratings doc for one employee, newest first.
function ratings_for_employee(array $allRatings, string $employeeUid): array
{
    $mine = array_values(array_filter($allRatings, fn($r) => ($r['employeeUid'] ?? '') === $employeeUid));
    usort($mine, fn($a, $b) => strcmp($b['ratedAt'] ?? '', $a['ratedAt'] ?? ''));
    return $mine;
}

// Real average KPI score (0-5) for one employee from their latest rating,
// plus whether they are meeting their industry template's average target.
// Returns null score/no rating yet.
function employee_kpi_summary(array $allRatings, string $employeeUid, string $industry): array
{
    $mine = ratings_for_employee($allRatings, $employeeUid);
    $template = kpi_template_for($industry);
    $targetAvg = array_sum(array_column($template['kpis'], 'target')) / max(1, count($template['kpis']));

    if (!$mine) {
        return ['hasData' => false, 'score' => null, 'targetAvg' => $targetAvg, 'ratingCount' => 0, 'lastRatedAt' => null];
    }

    $scores = $mine[0]['scores'] ?? [];
    $vals = array_values(array_filter(array_map('floatval', $scores), fn($v) => $v > 0));
    $avg = $vals ? array_sum($vals) / count($vals) : null;

    return [
        'hasData' => $avg !== null,
        'score' => $avg,
        'targetAvg' => $targetAvg,
        'ratingCount' => count($mine),
        'lastRatedAt' => $mine[0]['ratedAt'] ?? null,
    ];
}