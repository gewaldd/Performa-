<?php
// Report PDF file storage (local disk; authoritative metadata lives in the
// Reports Firestore docs). Served only through report_download.php /
// batch_download.php so ownership is always checked first.

function report_storage_dir(): string
{
    $dir = dirname(__DIR__, 2) . '/storage/reports';

    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    return $dir;
}

// Filesystem path for one report ID. The ID is allow-listed so it can
// never escape the storage directory.
function report_pdf_path(string $reportId): string
{
    $safe = preg_replace('/[^A-Za-z0-9_-]/', '_', $reportId);

    if ($safe === '' || $safe === null) {
        $safe = 'report';
    }

    return report_storage_dir() . '/' . $safe . '.pdf';
}

// Canonical snapshot shape shared by generate, verify (report_view) and
// rebuild (build_pdf): identical input guarantees identical fingerprints.
// Empty arrays normalize to null because document stores may drop them;
// missing keys default to null for legacy file-less reports.
function report_snapshot_from_doc(array $doc): array
{
    $snapshot = [
        'employeeUid' => $doc['employeeUid'] ?? null,
        'employeeName' => $doc['employeeName'] ?? null,
        'reportType' => $doc['reportType'] ?? null,
        'reportTypeLabel' => $doc['reportTypeLabel'] ?? null,
        'industry' => $doc['industry'] ?? null,
        'templateLabel' => $doc['templateLabel'] ?? null,
        'scores' => $doc['scores'] ?? null,
        'targets' => $doc['targets'] ?? null,
        'aiRecommendations' => $doc['aiRecommendations'] ?? null,
        'regularizationRecommendation' => $doc['regularizationRecommendation'] ?? null,
        'regularizationNotes' => $doc['regularizationNotes'] ?? null,
        'regularizationDecidedAt' => $doc['regularizationDecidedAt'] ?? null,
        'regularizationDecidedBy' => $doc['regularizationDecidedBy'] ?? null,
        'generatedAt' => $doc['generatedAt'] ?? null,
        'generatedBy' => $doc['generatedBy'] ?? null,
    ];

    foreach ($snapshot as $key => $value) {
        if ($value === []) {
            $snapshot[$key] = null;
        }
    }

    return $snapshot;
}

// Returns the byte count on success, null when the disk write fails.
// Callers save the report WITHOUT file fields in that case (graceful).
function report_pdf_save(string $reportId, string $bytes): ?int
{
    $written = @file_put_contents(report_pdf_path($reportId), $bytes);

    if ($written === false) {
        return null;
    }

    return $written;
}
