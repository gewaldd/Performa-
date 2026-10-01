<?php
// Batch export: one combined PDF (cover index + one section per report).
// A ZIP was considered and rejected — the zip extension is not installed
// on the target runtime, and a single paginated document prints cleanly.

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/includes/pdf_writer.php';
require_once __DIR__ . '/includes/report_files.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Logged-out visitors go to login (kept separate from the role gate below
// so admins keep access: includes/auth.php would enforce employer-only).
require_login();

require_csrf();

$roleKey = normalize_role_key($_SESSION['role'] ?? null);

if (!in_array($roleKey, ['employer', 'admin'], true)) {
    http_response_code(403);
    exit('Forbidden.');
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header('Location: reports.php');
    exit;
}

try {
    $docs = firestore_list_documents('Reports');
} catch (Throwable $e) {
    $docs = [];
}

usort(
    $docs,
    static function ($a, $b): int {
        return strcmp(
            (string) ($b['generatedAt'] ?? ''),
            (string) ($a['generatedAt'] ?? '')
        );
    }
);

// Bounded: newest 100 only, ownership-checked per employee.
$docs = array_slice($docs, 0, 100);

$sections = [];
$indexLines = [];
$n = 0;

foreach ($docs as $doc) {
    if (!is_array($doc)) {
        continue;
    }

    $empUid = (string) ($doc['employeeUid'] ?? '');

    try {
        $empUser = $empUid !== '' ? (firestore_get_document('Users', $empUid) ?? []) : [];
        require_employer_owns_user(
            $empUser + ['uid' => $empUid],
            'batch_download:serve'
        );
    } catch (Throwable $e) {
        continue;
    }

    $template = kpi_template_for($doc['industry'] ?? 'retail');
    $empName = (string) ($doc['employeeName'] ?? 'Unknown');
    $typeLabel = (string) ($doc['reportTypeLabel'] ?? 'Performance Report');
    $genRaw = (string) ($doc['generatedAt'] ?? '');
    $genTs = $genRaw !== '' ? strtotime($genRaw) : false;
    $genDate = $genTs !== false ? date('M j, Y g:ia', $genTs) : $genRaw;
    $byName = (string) ($doc['generatedBy'] ?? '');
    if ($byName === '') {
        $byName = 'Employer';
    }
    $meta = [
        (string) ($template['label'] ?? ''),
        'Generated ' . $genDate . ' by ' . $byName,
    ];

    $snapshot = [
        'scores' => $doc['scores'] ?? [],
        'targets' => $doc['targets'] ?? [],
        'aiRecommendations' => $doc['aiRecommendations'] ?? null,
        'regularizationRecommendation' => $doc['regularizationRecommendation'] ?? null,
        'regularizationNotes' => $doc['regularizationNotes'] ?? null,
        'regularizationDecidedAt' => $doc['regularizationDecidedAt'] ?? null,
        'regularizationDecidedBy' => $doc['regularizationDecidedBy'] ?? null,
    ];

    $docBlocks = report_pdf_blocks($snapshot, $template);
    $docSha = (string) ($doc['fileSha256'] ?? '');
    if ($docSha === '') {
        $docSha = report_pdf_fingerprint(report_snapshot_from_doc($doc));
    }
    $docBlocks[] = ['small', 'SHA-256 fingerprint: ' . substr($docSha, 0, 12) . '... (full value retained in the system)'];

    $sections[] = [
        'eyebrow' => $typeLabel,
        'title' => $empName,
        'meta' => $meta,
        'blocks' => $docBlocks,
        'footer' => '',
    ];

    $n++;
    $indexLines[] = $n . '. ' . $typeLabel . ' - ' . $empName;
}

if ($sections === []) {
    header('Location: reports.php');
    exit;
}

array_unshift($sections, [
    'eyebrow' => 'Performa Reports',
    'title' => 'Batch Report Export',
    'meta' => [
        $n . ' reports · exported ' . date('M j, Y g:ia') .
        ' by ' . ($_SESSION['name'] ?? 'Employer'),
    ],
    'blocks' => array_merge([['h', 'Index']], array_map(
        static function ($line): array {
            return ['p', $line];
        },
        $indexLines
    )),
    'footer' => '',
]);

$pdf = report_pdf_build_multi($sections);
$filename = 'reports-batch-' . date('Ymd-Hi') . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . strlen($pdf));
header('Cache-Control: private, max-age=0, must-revalidate');

echo $pdf;
exit;
