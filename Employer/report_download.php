<?php
// Single-report PDF download. Files live outside the web-reachable flow
// (storage/ is denied by .htaccess), so every download re-checks role +
// ownership before streaming a single byte.

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/roles.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/includes/report_files.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Logged-out visitors go to login (kept separate from the role gate below
// so admins keep access: includes/auth.php would enforce employer-only).
require_login();

$roleKey = normalize_role_key($_SESSION['role'] ?? null);

if (!in_array($roleKey, ['employer', 'admin'], true)) {
    http_response_code(403);
    exit('Forbidden.');
}

$reportId = (string) ($_GET['id'] ?? '');

if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $reportId)) {
    header('Location: reports.php');
    exit;
}

try {
    $doc = firestore_get_document('Reports', $reportId) ?? [];
} catch (Throwable $e) {
    $doc = [];
}

if ($doc === []) {
    header('Location: reports.php');
    exit;
}

try {
    $empUid = (string) ($doc['employeeUid'] ?? '');
    $empUser = $empUid !== '' ? (firestore_get_document('Users', $empUid) ?? []) : [];

    require_employer_owns_user(
        $empUser + ['uid' => $empUid],
        'report_download:serve'
    );
} catch (Throwable $e) {
    http_response_code(403);
    exit('Forbidden.');
}

$diskPath = report_pdf_path($reportId);

if (!is_file($diskPath)) {
    // Legacy file-less report: fall back to the print view.
    header('Location: report_view.php?id=' . urlencode($reportId));
    exit;
}

$filename = 'report-' . preg_replace('/[^A-Za-z0-9_-]/', '_', $reportId) . '.pdf';

header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . filesize($diskPath));
header('Cache-Control: private, max-age=0, must-revalidate');

readfile($diskPath);
exit;
