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
                ['key' => 'sales_target', 'name' => 'Sales Target Achievement', 'target' => 4.0],
                ['key' => 'customer_service', 'name' => 'Customer Service', 'target' => 4.2],
                ['key' => 'inventory_accuracy', 'name' => 'Inventory Accuracy', 'target' => 4.0],
                ['key' => 'attendance', 'name' => 'Attendance & Punctuality', 'target' => 4.5],
            ],
        ],
        'bpo' => [
            'label' => 'BPO',
            'kpis' => [
                ['key' => 'call_quality', 'name' => 'Call Quality Score', 'target' => 4.2],
                ['key' => 'aht', 'name' => 'Average Handle Time Adherence', 'target' => 4.0],
                ['key' => 'customer_satisfaction', 'name' => 'Customer Satisfaction (CSAT)', 'target' => 4.3],
                ['key' => 'attendance', 'name' => 'Attendance & Punctuality', 'target' => 4.5],
            ],
        ],
        'food_service' => [
            'label' => 'Food Service',
            'kpis' => [
                ['key' => 'food_safety', 'name' => 'Food Safety Compliance', 'target' => 4.5],
                ['key' => 'service_speed', 'name' => 'Service Speed', 'target' => 4.0],
                ['key' => 'customer_service', 'name' => 'Customer Service', 'target' => 4.2],
                ['key' => 'attendance', 'name' => 'Attendance & Punctuality', 'target' => 4.5],
            ],
        ],
        'logistics' => [
            'label' => 'Logistics',
            'kpis' => [
                ['key' => 'delivery_accuracy', 'name' => 'Delivery Accuracy', 'target' => 4.3],
                ['key' => 'on_time_rate', 'name' => 'On-Time Delivery Rate', 'target' => 4.2],
                ['key' => 'safety_compliance', 'name' => 'Safety Compliance', 'target' => 4.5],
                ['key' => 'attendance', 'name' => 'Attendance & Punctuality', 'target' => 4.5],
            ],
        ],
        'construction' => [
            'label' => 'Construction',
            'kpis' => [
                ['key' => 'safety_compliance', 'name' => 'Safety Compliance', 'target' => 4.5],
                ['key' => 'work_quality', 'name' => 'Work Quality', 'target' => 4.2],
                ['key' => 'productivity', 'name' => 'Productivity', 'target' => 4.0],
                ['key' => 'attendance', 'name' => 'Attendance & Punctuality', 'target' => 4.5],
            ],
        ],
    ];
}

function kpi_template_for(?string $industry): array
{
    $templates = kpi_templates();
    $key = strtolower(trim((string) $industry));
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
                        $template['kpis'][] = [
                            'key' => $ck['key'],
                            'name' => $ck['name'],
                            'target' => isset($ck['target']) ? (float) $ck['target'] : 4.0,
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

    return $template;
}

function add_custom_kpi(string $industry, string $name, float $target): void
{
    $key = strtolower(trim($industry));
    $slug = 'custom_' . preg_replace('/[^a-z0-9]+/', '_', strtolower(trim($name)));
    $doc = firestore_get_document('CustomKpis', $key) ?? ['kpis' => []];
    $doc['kpis'] = $doc['kpis'] ?? [];
    $doc['kpis'][] = ['key' => $slug, 'name' => $name, 'target' => $target];
    firestore_write_document('CustomKpis', $key, $doc);
}

function set_kpi_target_override(string $industry, string $kpiKey, float $target): void
{
    $key = strtolower(trim($industry));
    $doc = firestore_get_document('KpiOverrides', $key) ?? [];
    $doc[$kpiKey] = $target;
    firestore_write_document('KpiOverrides', $key, $doc);
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