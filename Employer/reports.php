<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/../audit_log.php';
require_once __DIR__ . '/includes/report_files.php';
require_once __DIR__ . '/includes/pdf_writer.php';

$profileName = $_SESSION['name'] ?? 'Unknown User';
$profileRole = $_SESSION['role'] ?? 'Employer';
$profileRoleDisplay = ucwords(
  str_replace(
    '_',
    ' ',
    $profileRole
  )
);

// Shared icon library (Style A cleanup).
$icons = [
  'plus' => employer_icon('plus'),
  'download' => employer_icon('download'),
  'search' => employer_icon('search'),
  'eye' => employer_icon('eye'),
  'archive' => employer_icon('archive'),
];

// Report type -> icon + tile color (deterministic by type, never by index).
$reportTypeChrome = [
  'monthly_summary' => ['icon' => 'file', 'class' => 'blue'],
  'training_audit' => ['icon' => 'cap', 'class' => 'green'],
  'probationary_status' => ['icon' => 'user', 'class' => 'orange'],
  'risk_analysis' => ['icon' => 'calendar', 'class' => 'red'],
];

$reportTypes = [
  'monthly_summary' => 'Monthly Performance Summary',
  'training_audit' => 'Learning & Development Audit',
  'probationary_status' => 'Probationary Status Report',
  'risk_analysis' => 'Underperformance Risk Analysis',
];

/* Disk-backed collection cache (shared include — see
   includes/collection_cache.php; extracted verbatim Item 5). */
require_once __DIR__ . '/includes/collection_cache.php';

/* =========================================================
   PROBATIONARY EMPLOYEES
   ========================================================= */

$employeesList = [];

$docs =
  get_cached_collection(
    'Users',
    600
  );

foreach ($docs as $doc) {
  if (normalize_role_key($doc['role'] ?? null) !== 'probationary') {
    continue;
  }

  $uid =
    trim(
      (string) (
        $doc['uid']
        ?? ''
      )
    );

  if ($uid === '') {
    continue;
  }

  $employeesList[] = [
    'uid' => $uid,

    'name' =>
      $doc['name']
      ?? (
        $doc['email']
        ?? 'Unknown'
      ),

    'industry' =>
      $doc['industry']
      ?? 'retail',
  ];
}

/* =========================================================
   REPORT GENERATION
   ========================================================= */

$genMessage = '';
$genMessageType = 'info';

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'generate_report'
) {
  $empUid =
    trim(
      (string) (
        $_POST['employee']
        ?? ''
      )
    );

  $requestedReportType =
    trim(
      (string) (
        $_POST['report_type']
        ?? ''
      )
    );

  $reportTypeKey =
    array_key_exists(
      $requestedReportType,
      $reportTypes
    )
    ? $requestedReportType
    : 'monthly_summary';

  $emp = null;

  foreach ($employeesList as $employee) {
    if ($employee['uid'] === $empUid) {
      $emp = $employee;
      break;
    }
  }

  if (!$emp) {
    $genMessage =
      'Select a valid probationary employee first.';
    $genMessageType = 'info';
  } else {
    try {
      $template =
        kpi_template_for(
          $emp['industry']
        );

      $allRatings =
        get_cached_collection(
          'Ratings',
          600
        );

      $mine = array_values(
        array_filter(
          $allRatings,
          fn($rating) =>
            ($rating['employeeUid'] ?? '') ===
            $emp['uid']
        )
      );

      usort(
        $mine,
        fn($a, $b) => strcmp(
          $b['ratedAt'] ?? '',
          $a['ratedAt'] ?? ''
        )
      );

      $scores =
        $mine[0]['scores']
        ?? [];

      // Frozen per-KPI targets (render from snapshot, never live template).
      $targets = [];

      foreach ($template['kpis'] as $kpi) {
        if (isset($kpi['key'])) {
          $targets[(string) $kpi['key']] =
            (float) $kpi['target'];
        }
      }

      // Latest approved AI plan for the manuscript-required report content.
      $latestApproved = null;

      foreach ($mine as $rating) {
        $candidateAi =
          $rating['aiRecommendations']
          ?? null;

        if (
          is_array($candidateAi) &&
          ($candidateAi['status'] ?? '') ===
          'approved'
        ) {
          $latestApproved =
            $candidateAi;

          break;
        }
      }

      // Regularization decision record (may be absent).
      $empUser = [];

      try {
        $empUser =
          firestore_get_document(
            'Users',
            $emp['uid']
          ) ?? [];
      } catch (Throwable $e) {
        // regularization fields stay null below
      }

      $reportId =
        'report_' .
        bin2hex(
          random_bytes(12)
        );

      $generatedAt = date('c');

      // Canonical snapshot: everything the PDF and the fingerprint cover.
      // Frozen now so later template edits can never rewrite history.
      // Normalized through the shared helper so generate, verify and
      // rebuild hash byte-identical shapes.
      $snapshot = report_snapshot_from_doc([
        'employeeUid' => $emp['uid'],
        'employeeName' => $emp['name'],
        'reportType' => $reportTypeKey,
        'reportTypeLabel' => $reportTypes[$reportTypeKey],
        'industry' => $emp['industry'],
        'templateLabel' => $template['label'],
        'scores' => $scores,
        'targets' => $targets,
        'aiRecommendations' => $latestApproved,
        'regularizationRecommendation' =>
          $empUser['regularizationRecommendation'] ?? null,
        'regularizationNotes' =>
          $empUser['regularizationNotes'] ?? null,
        'regularizationDecidedAt' =>
          $empUser['regularizationDecidedAt'] ?? null,
        'regularizationDecidedBy' =>
          $empUser['regularizationDecidedBy'] ?? null,
        'generatedAt' => $generatedAt,
        'generatedBy' => $_SESSION['name'] ?? '',
      ]);

      $fileSha = report_pdf_fingerprint($snapshot);

      // Render + store the PDF. A storage failure still saves the report
      // (view/download fall back to print); it never blocks generation.
      $fileBytes = null;

      try {
      $reportBlocks = report_pdf_blocks($snapshot, $template);
      $reportBlocks[] = ['small', 'SHA-256 fingerprint: ' . substr($fileSha, 0, 12) . '... (full value retained in the system)'];
      $genBy = (string) ($snapshot['generatedBy'] ?? '');
      if ($genBy === '') {
          $genBy = 'Employer';
      }
      $genTs = strtotime((string) ($snapshot['generatedAt'] ?? ''));
      $genDate = $genTs !== false ? date('M j, Y g:ia', $genTs) : (string) ($snapshot['generatedAt'] ?? '');
      $repName = (string) ($snapshot['employeeName'] ?? '');
      if ($repName === '') {
          $repName = 'Unknown';
      }
      $repType = (string) ($snapshot['reportTypeLabel'] ?? '');
      if ($repType === '') {
          $repType = 'Performance Report';
      }
      $repTemplate = (string) ($snapshot['templateLabel'] ?? '');
      $pdfBytes = report_pdf_build(
        $repName,
        [
          $repTemplate,
          'Generated ' . $genDate . ' by ' . $genBy,
        ],
        $reportBlocks,
        '',
        $repType
      );

        $fileBytes = report_pdf_save($reportId, $pdfBytes);
      } catch (Throwable $pdfError) {
        error_log('Employer report PDF build failed: ' . $pdfError->getMessage());
      }

      // Supersede older unsent reports of the same employee + type so only
      // one Finalized generation stays current. Sent/Signed Off/Archived
      // history is never touched.
      try {
        foreach (get_cached_collection('Reports', 600) as $oldDoc) {
          if (
            ($oldDoc['employeeUid'] ?? '') === $emp['uid'] &&
            ($oldDoc['reportType'] ?? '') === $reportTypeKey &&
            ($oldDoc['status'] ?? 'Finalized') === 'Finalized'
          ) {
            $oldId = (string) ($oldDoc['uid'] ?? '');

            if ($oldId !== '' && $oldId !== $reportId) {
              $oldDoc['status'] = 'Archived';

              firestore_write_document('Reports', $oldId, $oldDoc);
            }
          }
        }
      } catch (Throwable $e) {
        error_log('Employer report supersede failed: ' . $e->getMessage());
      }

      firestore_write_document(
        'Reports',
        $reportId,
        array_merge($snapshot, [
          'uid' => $reportId,
          'status' => 'Finalized',
          'filePath' => $fileBytes !== null
            ? 'storage/reports/' . $reportId . '.pdf'
            : null,
          'fileBytes' => $fileBytes,
          'fileSha256' => $fileSha,
        ])
      );

      record_audit_event(
        'report_generated',
        'Generated ' . $snapshot['reportTypeLabel'] . ' for ' . $snapshot['employeeName'],
        ['report' => $reportId, 'employee' => $emp['uid'], 'fileBytes' => $fileBytes]
      );

      clear_collection_cache(
        'Reports'
      );

      /*
       * PRG: redirect so refresh never re-submits, and the new row can be
       * highlighted + linked. The #report-<id> anchor scrolls natively.
       */
      header(
        'Location: reports.php?generated=' .
        urlencode($reportId) .
        '&name=' .
        urlencode($emp['name']) .
        '#report-' .
        urlencode($reportId)
      );
      exit;

    } catch (Throwable $e) {
      error_log(
        'Employer report generation failed: ' .
        $e->getMessage()
      );

      $genMessage =
        'The report could not be generated right now. Please try again.';

      $genMessageType = 'error';
    }
  }
}

/* =========================================================
   REPORT PDF BACKFILL (legacy file-less rows)
   ========================================================= */

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'build_pdf' &&
  !empty($_POST['report_id'])
) {
  try {
    $buildId = trim((string) $_POST['report_id']);

    if (!preg_match('/^[A-Za-z0-9_-]{1,128}$/', $buildId)) {
      throw new RuntimeException('Report not found.');
    }

    $buildDoc = firestore_get_document('Reports', $buildId) ?? [];

    if ($buildDoc === []) {
      throw new RuntimeException('Report not found.');
    }

    $buildEmpUid = (string) ($buildDoc['employeeUid'] ?? '');
    $buildEmp = $buildEmpUid !== ''
      ? (firestore_get_document('Users', $buildEmpUid) ?? [])
      : [];

    require_employer_owns_user(
      $buildEmp + ['uid' => $buildEmpUid],
      'reports:build_pdf'
    );

    // Render strictly from the frozen snapshot — never live templates.
    $buildSnapshot = report_snapshot_from_doc($buildDoc);
    $buildTemplate = kpi_template_for($buildDoc['industry'] ?? 'retail');
    $buildSha = report_pdf_fingerprint($buildSnapshot);

    $buildPdf = report_pdf_build(
      ($buildDoc['reportTypeLabel'] ?? 'Performance Report') .
      ' - ' . ($buildDoc['employeeName'] ?? 'Unknown'),
      [
        ($buildDoc['employeeName'] ?? 'Unknown') . ' · ' .
        ($buildDoc['templateLabel'] ?? $buildTemplate['label']),
        'Generated ' . (string) ($buildDoc['generatedAt'] ?? '') .
        ' by ' . (string) ($buildDoc['generatedBy'] ?? 'Employer'),
      ],
      report_pdf_blocks($buildSnapshot, $buildTemplate),
      'SHA-256: ' . $buildSha
    );

    $buildBytes = report_pdf_save($buildId, $buildPdf);

    if ($buildBytes === null) {
      throw new RuntimeException('Could not write the PDF file.');
    }

    $buildDoc['filePath'] = 'storage/reports/' . $buildId . '.pdf';
    $buildDoc['fileBytes'] = $buildBytes;
    $buildDoc['fileSha256'] = $buildSha;
    firestore_write_document('Reports', $buildId, $buildDoc);

    clear_collection_cache('Reports');

    record_audit_event(
      'report_pdf_built',
      'Built archived PDF for report ' . $buildId,
      ['report' => $buildId, 'employee' => $buildEmpUid, 'fileBytes' => $buildBytes]
    );

    header(
      'Location: reports.php?built=1#report-' . urlencode($buildId)
    );
    exit;
  } catch (Throwable $e) {
    $genMessage = 'The PDF could not be built. Please try again.';
    $genMessageType = 'error';
  }
}

/* =========================================================
   REPORT ARCHIVE (manual; supersede on generate is automatic)
   ========================================================= */

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'archive_report' &&
  !empty($_POST['report_id'])
) {
  try {
    $archiveId = trim((string) $_POST['report_id']);
    $archiveDoc = firestore_get_document('Reports', $archiveId) ?? [];

    if ($archiveDoc === []) {
      throw new RuntimeException('Report not found.');
    }

    $archiveEmpUid = (string) ($archiveDoc['employeeUid'] ?? '');
    $archiveEmp = $archiveEmpUid !== ''
      ? (firestore_get_document('Users', $archiveEmpUid) ?? [])
      : [];

    require_employer_owns_user(
      $archiveEmp + ['uid' => $archiveEmpUid],
      'reports:archive_report'
    );

    $archiveDoc['status'] = 'Archived';
    firestore_write_document('Reports', $archiveId, $archiveDoc);

    clear_collection_cache('Reports');

    record_audit_event(
      'report_archived',
      'Archived report ' . $archiveId,
      ['report' => $archiveId, 'employee' => $archiveEmpUid]
    );

    header(
      'Location: reports.php?archived=1#report-' . urlencode($archiveId)
    );
    exit;
  } catch (Throwable $e) {
    $genMessage = 'The report could not be archived. Please try again.';
    $genMessageType = 'error';
  }
}

/* =========================================================
   REPORTS LIST
   ========================================================= */

if (isset($_GET['archived'])) {
  $genMessage = 'Report archived.';
  $genMessageType = 'success';
}

if (isset($_GET['built'])) {
  $genMessage = 'PDF built for this report.';
  $genMessageType = 'success';
}

$reports = [];

$reportDocs =
  get_cached_collection(
    'Reports',
    600
  );

usort(
  $reportDocs,
  fn($a, $b) => strcmp(
    $b['generatedAt'] ?? '',
    $a['generatedAt'] ?? ''
  )
);

$iconCycle = [
  'blue',
  'orange',
  'green',
  'red'
];

// Display status per row. Stored states: Finalized (generate), Archived
// (manual or superseded). Signed Off / Sent derive from the employee's
// monthly Acknowledgements (the G9 dispatch flow); Needs Review derives
// from a still-pending AI plan. Priority: Archived > Signed Off > Sent >
// Needs Review > Finalized. Zero new reads: Ratings + Acknowledgements
// ride the shared disk cache.
$ackByMonth = [];
$pendingByUid = [];
$latestRatingByUid = [];
$ratingCountByUid = [];

try {
  foreach (get_cached_collection('Acknowledgements', 600) as $ackDoc) {
    if (!is_array($ackDoc)) {
      continue;
    }

    $ackKey = (string) ($ackDoc['employeeUid'] ?? '') . '|' . (string) ($ackDoc['month'] ?? '');
    $ackStatus = (string) ($ackDoc['status'] ?? '');

    if ($ackStatus === 'Acknowledged' || !isset($ackByMonth[$ackKey])) {
      $ackByMonth[$ackKey] = $ackStatus;
    }
  }
} catch (Throwable $e) {
  // status derivation degrades to stored states only
}

try {
  foreach (get_cached_collection('Ratings', 600) as $ratingDoc) {
    if (!is_array($ratingDoc)) {
      continue;
    }

    $rowUid = (string) ($ratingDoc['employeeUid'] ?? '');
    $pendingAi = $ratingDoc['aiRecommendations'] ?? null;

    $ratingCountByUid[$rowUid] = ($ratingCountByUid[$rowUid] ?? 0) + 1;

    if (
      is_array($pendingAi) &&
      ($pendingAi['status'] ?? '') === 'pending_approval'
    ) {
      $pendingByUid[$rowUid] = true;
    }

    $rowTs = strtotime((string) ($ratingDoc['ratedAt'] ?? ''));

    if ($rowTs !== false) {
      $prev = $latestRatingByUid[$rowUid] ?? null;

      if ($prev === null || $rowTs > $prev['ts']) {
        $latestRatingByUid[$rowUid] = [
          'ts' => $rowTs,
          'week' => (string) ($ratingDoc['weekOf'] ?? ''),
        ];
      }
    }
  }
} catch (Throwable $e) {
  // status derivation degrades to stored states only
}

// Per-employee context for the "ratings on file" line (zero new reads).
$employeeContextMap = [];

foreach ($employeesList as $ctxEmp) {
  $ctxUid = (string) ($ctxEmp['uid'] ?? '');

  if ($ctxUid === '') {
    continue;
  }

  $ctxCount = (int) ($ratingCountByUid[$ctxUid] ?? 0);
  $ctxLatest = $latestRatingByUid[$ctxUid] ?? null;
  $ctxDate = '';
  $ctxTs = null;

  if (is_array($ctxLatest) && isset($ctxLatest['ts'])) {
    $ctxTs = (int) $ctxLatest['ts'];
    $ctxDate = date('M j', $ctxTs);
  }

  $employeeContextMap[$ctxUid] = [
    'name' => (string) ($ctxEmp['name'] ?? 'Unknown'),
    'count' => $ctxCount,
    'date' => $ctxDate,
    'history' => 'employee_view.php?uid=' . urlencode($ctxUid),
  ];
}

// Verification banner for the default-selected employee (first option).
$bannerEmployee = $employeesList[0] ?? null;
$bannerRatings = 0;
$bannerWeek = '';
$bannerDate = '';

if ($bannerEmployee !== null) {
  $bannerRatings = (int) ($ratingCountByUid[$bannerEmployee['uid']] ?? 0);
  $bannerLatest = $latestRatingByUid[$bannerEmployee['uid']] ?? null;

  if ($bannerLatest !== null) {
    $bannerWeek = (string) ($bannerLatest['week'] ?? '');
    $bannerDate = date('M j, Y', (int) $bannerLatest['ts']);
  }
}

// Latest audit event for the "Audit synced" header pill.
$lastAuditAt = null;

try {
  foreach (get_cached_collection('auditLog', 600) as $auditDoc) {
    $auditTs = strtotime((string) ($auditDoc['createdAt'] ?? ''));

    if ($auditTs !== false && ($lastAuditAt === null || $auditTs > $lastAuditAt)) {
      $lastAuditAt = $auditTs;
    }
  }
} catch (Throwable $e) {
  // pill falls back to a static label below
}

foreach ($reportDocs as $index => $reportDoc) {
  $generatedDate =
    pf_date(
      $reportDoc['generatedAt'] ?? null,
      ''
    );

  $reportId =
    (string) (
      $reportDoc['uid']
      ?? ''
    );

  if ($reportId === '') {
    continue;
  }

  /*
   * Snapshot average for the scannable history row (Item 9). Scores were
   * frozen at generation time; empty means the employee was unrated then.
   */
  $snapshotScores =
    $reportDoc['scores'] ?? [];

  $snapshotVals = [];

  if (is_array($snapshotScores)) {
    foreach ($snapshotScores as $snapshotScore) {
      $snapshotVal = (float) $snapshotScore;
      if ($snapshotVal > 0) {
        $snapshotVals[] = $snapshotVal;
      }
    }
  }

  $reports[] = [
    'id' => $reportId,

    'title' =>
      (
        $reportDoc['employeeName']
        ?? 'Unknown'
      ) .
      ' – ' .
      (
        $reportDoc['reportTypeLabel']
        ?? 'Performance Report'
      ),

    'meta' =>
      $generatedDate !== ''
      ? 'Generated on ' . $generatedDate
      : 'Generation date unavailable',

    'typeLabel' =>
      $reportDoc['reportTypeLabel']
      ?? 'Performance Report',

    'employeeUid' =>
      (string) (
        $reportDoc['employeeUid']
        ?? ''
      ),

    'generatedAt' =>
      (string) (
        $reportDoc['generatedAt']
        ?? ''
      ),

    'generatedBy' =>
      (string) (
        $reportDoc['generatedBy']
        ?? ''
      ),

    'scoreAvg' =>
      $snapshotVals
      ? array_sum($snapshotVals) / count($snapshotVals)
      : null,

    'iconClass' =>
      $reportTypeChrome[
        (string) ($reportDoc['reportType'] ?? '')
      ]['class']
      ?? $iconCycle[
        $index % count($iconCycle)
      ],

    'icon' =>
      $reportTypeChrome[
        (string) ($reportDoc['reportType'] ?? '')
      ]['icon']
      ?? 'file',

    'sizeLabel' =>
      (function () use ($reportDoc): string {
        $bytes = (int) ($reportDoc['fileBytes'] ?? 0);

        if ($bytes <= 0) {
          return '';
        }

        if ($bytes >= 1048576) {
          return number_format($bytes / 1048576, 1) . ' MB';
        }

        return max(1, (int) round($bytes / 1024)) . ' KB';
      })(),

    'hasFile' =>
      (string) ($reportDoc['filePath'] ?? '') !== '',

    'employeeName' =>
      (string) (
        $reportDoc['employeeName']
        ?? 'Unknown'
      ),

    'dateShort' => $generatedDate,

    'dateTable' =>
      (function () use ($reportDoc): string {
        $ts = strtotime((string) ($reportDoc['generatedAt'] ?? ''));

        return $ts !== false ? date('M j', $ts) : '-';
      })(),

    'sortTs' =>
      (function () use ($reportDoc): int {
        $ts = strtotime((string) ($reportDoc['generatedAt'] ?? ''));

        return $ts !== false ? $ts : 0;
      })(),

    'generatedAt' =>
      (string) (
        $reportDoc['generatedAt']
        ?? ''
      ),

    'generatedBy' =>
      (string) (
        $reportDoc['generatedBy']
        ?? ''
      ),

    'downloadHref' =>
      (string) ($reportDoc['filePath'] ?? '') !== ''
      ? 'report_download.php?id=' . urlencode($reportId)
      : 'report_view.php?id=' . urlencode($reportId) . '&autoprint=1',

    'downloadTitle' =>
      (string) ($reportDoc['filePath'] ?? '') !== ''
      ? 'Download the archived PDF file'
      : 'Opens the print dialog — choose Save as PDF',

    'viewHref' => 'report_view.php?id=' . urlencode($reportId),

    'fileSha' =>
      is_string($reportDoc['fileSha256'] ?? null)
      ? (string) $reportDoc['fileSha256']
      : '',

    'shortSha' =>
      (function () use ($reportDoc): string {
        $full = (string) ($reportDoc['fileSha256'] ?? '');

        if ($full === '') {
          return '';
        }

        return strlen($full) > 12
          ? substr($full, 0, 4) . '…' . substr($full, -4)
          : $full;
      })(),

    'sub' =>
      (string) ($reportDoc['employeeName'] ?? 'Unknown') .
      ' · ' .
      (string) ($reportDoc['reportTypeLabel'] ?? 'Performance Report'),

    'byline' =>
      (function () use ($reportDoc): string {
        $by = trim((string) ($reportDoc['generatedBy'] ?? ''));

        return $by !== '' ? $by : 'Employer';
      })(),

    'searchHaystack' =>
      strtolower(
        (string) ($reportDoc['employeeName'] ?? '') . ' ' .
        (string) ($reportDoc['reportTypeLabel'] ?? '') . ' ' .
        (string) ($reportDoc['generatedBy'] ?? '')
      ),

    'showArchive' =>
      (string) ($reportDoc['status'] ?? 'Finalized') === 'Finalized',

    'storedStatus' =>
      (string) (
        $reportDoc['status']
        ?? 'Finalized'
      ),

    'displayStatus' =>
      (function () use ($reportDoc, $ackByMonth, $pendingByUid): array {
        $stored = (string) ($reportDoc['status'] ?? 'Finalized');

        if ($stored === 'Archived') {
          return ['label' => 'Archived', 'class' => 'status-neutral'];
        }

        $empUid = (string) ($reportDoc['employeeUid'] ?? '');
        $genTs = strtotime((string) ($reportDoc['generatedAt'] ?? ''));
        $monthLabel = $genTs !== false ? date('F Y', $genTs) : '';
        $ackStatus = $ackByMonth[$empUid . '|' . $monthLabel] ?? '';

        if ($ackStatus === 'Acknowledged') {
          return ['label' => 'Signed Off', 'class' => 'status-good'];
        }

        if ($ackStatus !== '') {
          return ['label' => 'Sent', 'class' => 'status-ready'];
        }

        if (!empty($pendingByUid[$empUid])) {
          return ['label' => 'Needs Review', 'class' => 'status-warning'];
        }

        return ['label' => 'Finalized', 'class' => 'status-neutral'];
      })(),
  ];

}

/*
 * Command palette index from the already-loaded report rows (zero new
 * reads). Cap keeps the inline payload small.
 */
$pfPaletteIndex = [];

foreach (
  array_slice(
    $reports,
    0,
    60
  ) as $paletteReport
) {
  $paletteId =
    (string) (
      $paletteReport['id']
      ?? ''
    );

  if ($paletteId === '') {
    continue;
  }

  $pfPaletteIndex[] = [
    'label' =>
      $paletteReport['title'],
    'sub' =>
      $paletteReport['meta'] .
      ' · View report',
    'href' =>
      'report_view.php?id=' .
      urlencode($paletteId),
  ];
}

$pfPaletteJson =
  json_encode(
    $pfPaletteIndex,
    JSON_HEX_TAG |
    JSON_HEX_APOS |
    JSON_HEX_QUOT |
    JSON_HEX_AMP
  );

$tabAll = count($reports);
$tabSent = 0;
$tabSignedOff = 0;
foreach ($reports as $r) {
  $st = strtolower((string) ($r['displayStatus']['label'] ?? ''));
  if ($st === 'sent') {
    $tabSent++;
  } elseif ($st === 'signed off' || $st === 'signed') {
    $tabSignedOff++;
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  <?php employer_brand_head(); ?>
  <title>Employee reports | Performa</title>
  <meta name="description" content="Create and manage individual performance assessments." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('Reports'); ?>

    <main class="main reports-page" id="reports">
      <div class="in">

        <div class="top">
          <div>
            <h1 id="h1">Employee reports</h1>
            <p class="sub">Create and manage individual performance assessments.</p>
          </div>
          <div class="tr">
            <span class="sync">
              <?php if ($lastAuditAt !== null): ?>
                Synced <?php echo htmlspecialchars(date('M j, H:i', $lastAuditAt), ENT_QUOTES); ?>
              <?php else: ?>
                Audit log active
              <?php endif; ?>
            </span>
            <a href="kpis.php" class="btn tint">Templates</a>
            <form method="post" action="batch_download.php" style="display:inline;margin:0;">
              <?php echo csrf_field(); ?>
              <button type="submit" class="btn txt">Export all</button>
            </form>
          </div>
        </div>

        <?php if ($genMessage !== ''): ?>
          <div class="alert alert-<?php echo $genMessageType === 'success' ? 'success' : ($genMessageType === 'error' ? 'error' : 'info'); ?> reports-message" role="status" aria-live="polite" style="margin-bottom:20px;">
            <?php echo htmlspecialchars($genMessage, ENT_QUOTES); ?>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['generated']) && $_GET['generated'] !== ''): ?>
          <div class="alert alert-success reports-message" role="status" aria-live="polite" style="margin-bottom:20px;">
            Report generated for
            <strong><?php echo htmlspecialchars($_GET['name'] ?? 'employee', ENT_QUOTES); ?></strong>.
            <a href="report_view.php?id=<?php echo urlencode($_GET['generated']); ?>" style="color:var(--brand);font-weight:600;">
              View report
            </a>
          </div>
        <?php endif; ?>

        <section class="blk">
          <div class="sh"><h2>Generate a report</h2></div>
          <p class="help" id="help"
            data-context='<?php echo htmlspecialchars(json_encode($employeeContextMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES); ?>'>
            <?php if ($bannerEmployee !== null && $bannerRatings > 0): ?>
              <?php echo (int) $bannerRatings; ?> ratings on file, latest <?php echo htmlspecialchars($bannerDate !== '' ? date('M j', strtotime($bannerDate)) : $bannerDate, ENT_QUOTES); ?>. AI synthesis and remarks compile automatically. <a href="employee_view.php?uid=<?php echo urlencode($bannerEmployee['uid']); ?>">View history</a>
            <?php elseif ($bannerEmployee !== null): ?>
              No ratings on file yet for <?php echo htmlspecialchars($bannerEmployee['name'], ENT_QUOTES); ?>. <a href="rate_employee.php?employee=<?php echo urlencode($bannerEmployee['uid']); ?>">Rate first</a>
            <?php endif; ?>
          </p>

          <?php if (!$employeesList): ?>
            <p class="reports-empty-note">
              No probationary employees yet.
              <a class="btn primary" href="employees.php">Go to Employees</a>
            </p>
          <?php else: ?>
            <form method="post" class="frm">
              <?php echo csrf_field(); ?>
              <div>
                <label class="l" for="emp">Employee</label>
                <select id="emp" name="employee" required>
                  <?php foreach ($employeesList as $emp): ?>
                    <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>">
                      <?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div>
                <label class="l" for="typ">Report type</label>
                <select id="typ" name="report_type" required>
                  <?php foreach ($reportTypes as $key => $label): ?>
                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>">
                      <?php echo htmlspecialchars($label, ENT_QUOTES); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <input type="hidden" name="action" value="generate_report" />
              <button class="btn primary" type="submit" id="gen">+ Generate</button>
            </form>
          <?php endif; ?>
        </section>

        <section>
          <div class="sh"><h2>Reports</h2></div>
          <div class="tb">
            <div class="seg" role="tablist" id="tabs">
              <button type="button" data-t="all" role="tab" aria-selected="true">All<span class="num"><?php echo (int) $tabAll; ?></span></button>
              <button type="button" data-t="sent" role="tab" aria-selected="false">Sent<span class="num"><?php echo (int) $tabSent; ?></span></button>
              <button type="button" data-t="signed" role="tab" aria-selected="false">Signed off<span class="num"><?php echo (int) $tabSignedOff; ?></span></button>
            </div>
            <div class="f">
              <label class="chk"><input type="checkbox" id="nopdf">No PDF yet</label>
              <input type="search" id="q" placeholder="Search" aria-label="Search reports" autocomplete="off">
              <select id="sort" aria-label="Sort">
                <option value="new">Newest first</option>
                <option value="old">Oldest first</option>
              </select>
            </div>
          </div>

          <div class="rw rh">
            <span>Report</span>
            <span>Date</span>
            <span>Status</span>
            <span>PDF</span>
            <span></span>
          </div>

          <div id="list">
            <?php if (!$reports): ?>
              <div class="empty">No reports generated yet. Use the form above to create one.</div>
            <?php else: ?>
              <?php foreach ($reports as $report): ?>
                <?php
                $rowStatus = strtolower((string) ($report['displayStatus']['label'] ?? ''));
                $rowStatusKey = 'other';
                $dotColor = 'var(--mut)';
                if ($rowStatus === 'sent') {
                  $rowStatusKey = 'sent';
                  $dotColor = 'var(--brand)';
                } elseif ($rowStatus === 'signed off' || $rowStatus === 'signed') {
                  $rowStatusKey = 'signed';
                  $dotColor = 'var(--ok)';
                } elseif (strpos($rowStatus, 'review') !== false) {
                  $rowStatusKey = 'review';
                  $dotColor = 'var(--amber)';
                }

                $hasPdf = !empty($report['hasFile']);
                ?>
                <div class="row rw"
                  data-id="<?php echo htmlspecialchars($report['id'], ENT_QUOTES); ?>"
                  data-who="<?php echo htmlspecialchars($report['employeeName'], ENT_QUOTES); ?>"
                  data-type="<?php echo htmlspecialchars($report['typeLabel'], ENT_QUOTES); ?>"
                  data-tag="<?php echo htmlspecialchars($report['byline'], ENT_QUOTES); ?>"
                  data-search="<?php echo htmlspecialchars($report['searchHaystack'], ENT_QUOTES); ?>"
                  data-ts="<?php echo (int) $report['sortTs']; ?>"
                  data-status="<?php echo htmlspecialchars($rowStatusKey, ENT_QUOTES); ?>"
                  data-has-pdf="<?php echo $hasPdf ? '1' : '0'; ?>">

                  <div class="nm">
                    <b><?php echo htmlspecialchars($report['employeeName'], ENT_QUOTES); ?></b>
                    <span class="sm2"><?php echo htmlspecialchars($report['typeLabel'] . ' · ' . $report['byline'], ENT_QUOTES); ?></span>
                  </div>

                  <span class="d num sm2"><?php echo htmlspecialchars($report['dateTable'], ENT_QUOTES); ?></span>

                  <span class="s stt">
                    <i style="background:<?php echo $dotColor; ?>"></i>
                    <?php echo htmlspecialchars($report['displayStatus']['label'], ENT_QUOTES); ?>
                  </span>

                  <span class="p pdf">
                    <?php if ($hasPdf): ?>
                      <span class="num"><?php echo htmlspecialchars($report['sizeLabel'] !== '' ? $report['sizeLabel'] : 'PDF', ENT_QUOTES); ?></span>
                      <?php if ($report['shortSha'] !== ''): ?>
                        <button class="num" type="button" data-h="<?php echo htmlspecialchars($report['fileSha'], ENT_QUOTES); ?>" title="Copy SHA-256 fingerprint">
                          <?php echo htmlspecialchars($report['shortSha'], ENT_QUOTES); ?>
                        </button>
                      <?php endif; ?>
                    <?php else: ?>
                      No PDF
                    <?php endif; ?>
                  </span>

                  <div class="act">
                    <?php if ($hasPdf): ?>
                      <a href="<?php echo htmlspecialchars($report['downloadHref'], ENT_QUOTES); ?>" class="btn tint" title="<?php echo htmlspecialchars($report['downloadTitle'], ENT_QUOTES); ?>">
                        Download
                      </a>
                    <?php else: ?>
                      <form method="post" style="display:inline;margin:0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="build_pdf" />
                        <input type="hidden" name="report_id" value="<?php echo htmlspecialchars($report['id'], ENT_QUOTES); ?>" />
                        <button type="submit" class="btn tint" title="Render the PDF from this report's frozen snapshot">
                          Build PDF
                        </button>
                      </form>
                    <?php endif; ?>

                    <a href="<?php echo htmlspecialchars($report['viewHref'], ENT_QUOTES); ?>" class="btn x" title="View report" aria-label="View <?php echo htmlspecialchars($report['title'], ENT_QUOTES); ?>">
                      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/></svg>
                    </a>

                    <?php if ($report['showArchive']): ?>
                      <form method="post" style="display:inline;margin:0;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="archive_report" />
                        <input type="hidden" name="report_id" value="<?php echo htmlspecialchars($report['id'], ENT_QUOTES); ?>" />
                        <button type="submit" class="btn x" title="Archive report" aria-label="Archive <?php echo htmlspecialchars($report['title'], ENT_QUOTES); ?>">
                          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"><path d="M3 4h18v4H3zM5 8v12h14V8M10 12h4"/></svg>
                        </button>
                      </form>
                    <?php endif; ?>
                  </div>

                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>

          <div class="ft">
            <span id="cap"></span>
            <div>
              <button type="button" class="btn tint" id="prev" disabled>Previous</button>
              <button type="button" class="btn tint" id="next" disabled>Next</button>
            </div>
          </div>

          <p class="note">Every PDF carries a SHA-256 fingerprint, verifiable on its report page.</p>
        </section>

      </div>
    </main>

    <div class="toast" id="toast" hidden></div>
  </div>

  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

  <script>
    window.__pfIndex = <?php echo $pfPaletteJson !== false ? $pfPaletteJson : '[]'; ?>;
  </script>

  <script>
    (function () {
      var list = document.getElementById('list');
      if (!list) return;
      var qInput = document.getElementById('q');
      var sortSelect = document.getElementById('sort');
      var noPdfCheck = document.getElementById('nopdf');
      var cap = document.getElementById('cap');
      var prevBtn = document.getElementById('prev');
      var nextBtn = document.getElementById('next');
      var tabBtns = Array.from(document.querySelectorAll('#tabs button'));
      var empSelect = document.getElementById('emp');
      var helpText = document.getElementById('help');
      var toastEl = document.getElementById('toast');
      var toastTimer;

      function toast(msg) {
        if (!toastEl) return;
        clearTimeout(toastTimer);
        toastEl.hidden = false;
        toastEl.textContent = msg;
        toastTimer = setTimeout(function () { toastEl.hidden = true; }, 3500);
      }

      var PS = 6;
      var tab = 'all';
      var page = 1;
      var ctxMap = {};

      try {
        ctxMap = helpText ? JSON.parse(helpText.getAttribute('data-context') || '{}') : {};
      } catch (e) { ctxMap = {}; }

      function updateHelp() {
        if (!empSelect || !helpText) return;
        var entry = ctxMap[empSelect.value];
        if (!entry) return;
        if ((entry.count || 0) > 0) {
          var latest = entry.date ? ', latest ' + entry.date : '';
          helpText.innerHTML = entry.count + ' ratings on file' + latest + '. AI synthesis and remarks compile automatically. <a href="' + entry.history + '">View history</a>';
        } else {
          helpText.innerHTML = 'No ratings on file yet for ' + entry.name + '. <a href="rate_employee.php?employee=' + encodeURIComponent(empSelect.value) + '">Rate first</a>';
        }
      }

      var rows = Array.from(list.querySelectorAll('.row'));

      function render() {
        var query = qInput ? qInput.value.trim().toLowerCase() : '';
        var sortVal = sortSelect ? sortSelect.value : 'new';
        var noPdfOnly = noPdfCheck ? noPdfCheck.checked : false;

        var matched = rows.filter(function (r) {
          if (tab !== 'all') {
            var st = r.getAttribute('data-status') || '';
            if (st !== tab) return false;
          }
          if (noPdfOnly) {
            var hasPdf = r.getAttribute('data-has-pdf');
            if (hasPdf !== '0') return false;
          }
          if (query) {
            var searchTxt = r.getAttribute('data-search') || '';
            if (searchTxt.indexOf(query) === -1) return false;
          }
          return true;
        });

        matched.sort(function (a, b) {
          var ta = parseInt(a.getAttribute('data-ts') || '0', 10);
          var tb = parseInt(b.getAttribute('data-ts') || '0', 10);
          if (ta === tb) return 0;
          return sortVal === 'new' ? tb - ta : ta - tb;
        });

        var total = matched.length;
        var pages = Math.max(1, Math.ceil(total / PS));
        if (page > pages) page = pages;

        rows.forEach(function (r) { r.style.display = 'none'; });

        var start = (page - 1) * PS;
        var end = Math.min(page * PS, total);
        matched.slice(start, end).forEach(function (r) {
          r.style.display = '';
          list.appendChild(r);
        });

        if (cap) {
          cap.textContent = total ? ('Showing ' + (total === 0 ? 0 : start + 1) + ' to ' + end + ' of ' + total) : 'No reports match this filter.';
        }

        if (prevBtn) prevBtn.disabled = page <= 1;
        if (nextBtn) nextBtn.disabled = page >= pages || total === 0;

        tabBtns.forEach(function (b) {
          var isSel = b.getAttribute('data-t') === tab;
          b.setAttribute('aria-selected', isSel ? 'true' : 'false');
        });
      }

      tabBtns.forEach(function (b) {
        b.addEventListener('click', function () {
          tab = b.getAttribute('data-t') || 'all';
          page = 1;
          render();
        });
      });

      if (qInput) qInput.addEventListener('input', function () { page = 1; render(); });
      if (sortSelect) sortSelect.addEventListener('change', function () { page = 1; render(); });
      if (noPdfCheck) noPdfCheck.addEventListener('change', function () { page = 1; render(); });

      if (prevBtn) {
        prevBtn.addEventListener('click', function () {
          if (page > 1) { page--; render(); }
        });
      }
      if (nextBtn) {
        nextBtn.addEventListener('click', function () {
          page++; render();
        });
      }

      if (empSelect) empSelect.addEventListener('change', updateHelp);

      document.addEventListener('click', function (e) {
        var hBtn = e.target.closest('[data-h]');
        if (hBtn) {
          var hash = hBtn.getAttribute('data-h') || hBtn.textContent;
          if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(hash);
          }
          toast('Fingerprint copied');
        }
      });

      render();
    })();
  </script>
</body>
</html>
