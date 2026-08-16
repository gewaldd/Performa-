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
    return $templates[$key] ?? $templates['retail'];
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