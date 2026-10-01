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
      : 'Opens the print dialog - choose Save as PDF',

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

$rRows = [];
foreach ($reports as $index => $rep) {
  $rRows[] = [
    'id' => (string) ($rep['id'] ?? ''),
    'idx' => $index,
    'n' => (string) ($rep['employeeName'] ?? 'Unknown'),
    'empUid' => (string) ($rep['employeeUid'] ?? ''),
    't' => (string) ($rep['typeLabel'] ?? 'Performance Report'),
    'by' => (string) ($rep['byline'] ?? 'Employer'),
    'd' => substr((string) ($rep['generatedAt'] ?? ''), 0, 10),
    's' => (string) ($rep['displayStatus']['label'] ?? 'Finalized'),
    'pdf' => (string) ($rep['sizeLabel'] ?? ''),
    'hasFile' => !empty($rep['hasFile']),
    'sha' => (string) ($rep['fileSha'] ?? ''),
    'dl' => (string) ($rep['downloadHref'] ?? ''),
    'view' => (string) ($rep['viewHref'] ?? ''),
    'showArchive' => !empty($rep['showArchive']),
  ];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <?php employer_brand_head(); ?>
  <title>Employee reports · Performa</title>
  <meta name="description" content="Create and manage individual performance assessments." />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
  <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>" />
</head>

<body>

  <svg width="0" height="0" style="position:absolute" aria-hidden="true">
    <symbol id="i-eye" viewBox="0 0 24 24"><path d="M2.06 12.35a1 1 0 0 1 0-.7 10.75 10.75 0 0 1 19.88 0 1 1 0 0 1 0 .7 10.75 10.75 0 0 1-19.88 0"/><circle cx="12" cy="12" r="3"/></symbol>
    <symbol id="i-arch" viewBox="0 0 24 24"><rect width="20" height="5" x="2" y="3" rx="1"/><path d="M4 8v11a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8M10 12h4"/></symbol>
    <symbol id="i-dl" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M7 10l5 5 5-5M12 15V3"/></symbol>
    <symbol id="i-plus" viewBox="0 0 24 24"><path d="M5 12h14M12 5v14"/></symbol>
    <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></symbol>
    <symbol id="i-up" viewBox="0 0 24 24"><path d="m5 12 7-7 7 7M12 19V5"/></symbol>
    <symbol id="i-shield" viewBox="0 0 24 24"><path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/></symbol>
  </svg>

  <div class="app-shell">
    <?php employer_render_shell('Reports'); ?>

    <main class="main reports-page" id="reports">
      <div class="cq"><div class="wrap">
        <header>
          <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
            <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>
            </svg>
          </button>
          <div>
            <h1>Employee reports</h1>
            <p class="sub">Create and manage individual performance assessments.</p>
          </div>
          <div class="hdr-r">
            <span class="sync"><span class="dot"></span><span class="mono">Synced <?php echo $lastAuditAt !== null ? date('M j, H:i', $lastAuditAt) : date('M j, H:i'); ?></span></span>
            <a href="kpis.php" class="link">Templates</a>
            <form method="post" action="batch_download.php" style="display:inline-flex;align-items:center;margin:0;">
              <?php echo csrf_field(); ?>
              <button type="submit" class="link">Export all</button>
            </form>
          </div>
        </header>

        <?php if ($genMessage !== ''): ?>
          <div class="alert alert-<?php echo $genMessageType === 'error' ? 'error' : 'success'; ?>" role="status" style="margin-top:var(--s4);margin-bottom:var(--s2);">
            <?php echo htmlspecialchars($genMessage, ENT_QUOTES); ?>
          </div>
        <?php endif; ?>

        <?php if (isset($_GET['generated']) && $_GET['generated'] !== ''): ?>
          <div class="alert alert-success" role="status" style="margin-top:var(--s4);margin-bottom:var(--s2);">
            Report generated for <strong><?php echo htmlspecialchars($_GET['name'] ?? 'employee', ENT_QUOTES); ?></strong>.
            <a href="report_view.php?id=<?php echo urlencode($_GET['generated']); ?>" style="color:inherit;font-weight:600;margin-left:8px;">View report</a>
          </div>
        <?php endif; ?>

        <section class="gen" aria-labelledby="gh">
          <h2 id="gh">Generate a report</h2>
          <form method="post" action="reports.php" class="form" id="genForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="generate_report" />
            <div>
              <label for="emp">Employee</label>
              <select id="emp" name="employee" class="sel">
                <?php foreach ($employeesList as $empOpt): ?>
                  <option value="<?php echo htmlspecialchars($empOpt['uid'], ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($empOpt['name'], ENT_QUOTES); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <div>
              <label for="rt">Report type</label>
              <select id="rt" name="report_type" class="sel">
                <?php foreach ($reportTypes as $rKey => $rLabel): ?>
                  <option value="<?php echo htmlspecialchars($rKey, ENT_QUOTES); ?>">
                    <?php echo htmlspecialchars($rLabel, ENT_QUOTES); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="btn" id="gen"><svg class="i"><use href="#i-plus"/></svg>Generate</button>
          </form>
          <div class="note warn" id="dup" hidden><span class="dot"></span><span id="dupt"></span></div>
          <div class="note" id="noteInfo">
            <span class="dot" style="background:var(--good)"></span>
            <span id="noteText"><span class="mono"><?php echo (int) $bannerRatings; ?></span> ratings on file<?php echo $bannerDate !== '' ? ', latest <span class="mono">' . htmlspecialchars($bannerDate, ENT_QUOTES) . '</span>' : ''; ?>.</span>
            <a href="<?php echo $bannerEmployee ? 'employee_view.php?uid=' . urlencode($bannerEmployee['uid']) : '#'; ?>" class="link" id="noteLink">View history</a>
          </div>
        </section>

        <section aria-label="Reports">
          <div class="bar">
            <div class="tabs" role="tablist" id="tabs" aria-label="Status"></div>
            <div class="tools">
              <button type="button" class="chip" id="nopdf" aria-pressed="false">No PDF</button>
              <div class="search"><svg class="i"><use href="#i-search"/></svg><input id="q" class="f" type="search" placeholder="Search" aria-label="Search reports"></div>
            </div>
          </div>
          <div class="head">
            <button type="button" class="sort" data-k="name">Report<svg class="i"><use href="#i-up"/></svg></button>
            <button type="button" class="sort" data-k="date">Date<svg class="i"><use href="#i-up"/></svg></button>
            <button type="button" class="sort" data-k="status">Status<svg class="i"><use href="#i-up"/></svg></button>
            <span class="hp">PDF</span><span></span>
          </div>
          <div id="rows"></div>
          <div class="foot"><span id="count"></span><span>Click the shield on a PDF to copy its SHA-256 fingerprint.</span></div>
        </section>
      </div></div>
    </main>
  </div>

  <div class="toast" id="toast" role="status"></div>

  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

  <script>
    window.__pfIndex = <?php echo $pfPaletteJson !== false ? $pfPaletteJson : '[]'; ?>;
  </script>

  <script>
  var R = <?php echo json_encode($rRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
  var MON = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
  var MONF = ["January","February","March","April","May","June","July","August","September","October","November","December"];
  var CLS = { "Needs review": "s-review", "Needs Review": "s-review", "Finalized": "s-final", "Sent": "s-sent", "Signed off": "s-signed", "Signed Off": "s-signed", "Archived": "s-arch" };
  var ORD = { "Needs review": 0, "Needs Review": 0, "Finalized": 1, "Sent": 2, "Signed off": 3, "Signed Off": 3, "Archived": 4 };
  var NEEDS_PDF = ["Finalized", "Sent", "Signed off", "Signed Off", "Needs review", "Needs Review"];
  var st = { t: "all", q: "", nopdf: false, k: "date", dir: "desc" };
  var $ = function(i) { return document.getElementById(i); };
  var I = function(id) { return '<svg class="i"><use href="#i-' + id + '"/></svg>'; };
  var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  var empCtx = <?php echo json_encode($employeeContextMap, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

  function cap(s) { return (s || '').replace(/\b\w/g, function(c) { return c.toUpperCase(); }); }
  function esc(s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function(c) { return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]; }); }
  function fd(s) {
    if (!s) return '';
    var p = s.split("-");
    if (p.length < 3) return s;
    return MON[+p[1] - 1] + " " + (+p[2]);
  }

  function dupCheck() {
    var empSel = $("emp");
    var rtSel = $("rt");
    if (!empSel || !rtSel) return;
    var eName = empSel.options[empSel.selectedIndex]?.text.trim().toLowerCase() || "";
    var tLabel = rtSel.options[rtSel.selectedIndex]?.text.trim() || "";
    var curMonth = new Date().toISOString().slice(0, 7);
    var hit = R.filter(function(r) {
      return r.n.toLowerCase() === eName && r.t === tLabel && r.s !== "Archived" && r.d.indexOf(curMonth) === 0;
    })[0];
    $("dup").hidden = !hit;
    if (hit) {
      $("dupt").textContent = cap(eName) + " already has a " + tLabel + " from " + fd(hit.d) + " this month. You can still generate another.";
    }
  }

  function updateEmpContext() {
    var empId = $("emp").value;
    var ctx = empCtx[empId];
    if (ctx) {
      var datePart = ctx.date ? (', latest <span class="mono">' + ctx.date + '</span>') : '';
      $("noteText").innerHTML = '<span class="mono">' + ctx.count + '</span> ratings on file' + datePart + '.';
      $("noteLink").href = ctx.history || '#';
      $("noteInfo").hidden = false;
    }
  }

  function renderTabs() {
    var c = { all: R.length, Sent: 0, "Signed off": 0 };
    R.forEach(function(r) {
      var sNorm = (r.s === "Signed Off" ? "Signed off" : r.s);
      if (c[sNorm] !== undefined) c[sNorm]++;
    });
    var defs = [["all", "All"], ["Sent", "Sent"], ["Signed off", "Signed off"]];
    $("tabs").innerHTML = defs.map(function(d) {
      return '<button type="button" class="tab" role="tab" aria-selected="' + (st.t === d[0]) + '" data-t="' + d[0] + '">' + d[1] + '<span class="n">' + (c[d[0]] || 0) + '</span></button>';
    }).join("");
  }

  function val(r, k) {
    return k === "name" ? r.n.toLowerCase() : k === "date" ? r.d : (ORD[r.s] !== undefined ? ORD[r.s] : 99);
  }

  function render() {
    var rows = R.filter(function(r) {
      var sNorm = (r.s === "Signed Off" ? "Signed off" : r.s);
      if (st.t !== "all" && sNorm !== st.t) return false;
      if (st.nopdf && r.pdf) return false;
      if (st.q && (r.n + " " + r.t + " " + r.by + " " + r.s).toLowerCase().indexOf(st.q) < 0) return false;
      return true;
    });
    rows.sort(function(a, b) {
      var x = val(a, st.k), y = val(b, st.k);
      var c = x < y ? -1 : x > y ? 1 : 0;
      if (!c) c = b.idx - a.idx;
      return c * (st.dir === "asc" ? 1 : -1);
    });
    var prevM = "", cnt = {};
    if (st.k === "date") rows.forEach(function(r) { var m = r.d.slice(0, 7); cnt[m] = (cnt[m] || 0) + 1; });
    $("rows").innerHTML = rows.map(function(r) {
      var arch = r.s === "Archived", g = "";
      if (st.k === "date" && r.d.length >= 7) {
        var m = r.d.slice(0, 7);
        if (m !== prevM) {
          prevM = m;
          var monthIndex = +m.slice(5) - 1;
          var monthName = MONF[monthIndex] || m;
          g = '<div class="grp"><span>' + monthName + ' ' + m.slice(0, 4) + '</span><span class="mono">' + (cnt[m] || 0) + '</span></div>';
        }
      }
      var pdf = r.pdf
        ? '<span class="pdf"><span class="mono">' + esc(r.pdf) + '</span><button type="button" class="ibtn" data-sha="' + esc(r.sha) + '" data-tip="Copy SHA-256" aria-label="Copy SHA-256 fingerprint">' + I("shield") + '</button></span>'
        : (NEEDS_PDF.indexOf(r.s) >= 0 ? '<span class="pdf warn">Not built</span>' : '<span class="pdf none">-</span>');
      var main = r.pdf
        ? '<a class="sbtn" href="' + esc(r.dl) + '" download>' + I("dl") + 'Download</a>'
        : '<form method="post" action="reports.php" style="display:inline;margin:0;"><input type="hidden" name="csrf_token" value="' + esc(csrfToken) + '"><input type="hidden" name="action" value="build_pdf"><input type="hidden" name="report_id" value="' + esc(r.id) + '"><button type="submit" class="sbtn">Build PDF</button></form>';
      var archBtn = (arch || !r.showArchive)
        ? '<button type="button" class="ibtn" hidden-slot aria-hidden="true" tabindex="-1"></button>'
        : '<form method="post" action="reports.php" style="display:inline;margin:0;"><input type="hidden" name="csrf_token" value="' + esc(csrfToken) + '"><input type="hidden" name="action" value="archive_report"><input type="hidden" name="report_id" value="' + esc(r.id) + '"><button type="submit" class="ibtn" data-tip="Archive" aria-label="Archive report">' + I("arch") + '</button></form>';
      var dispStatus = r.s;
      if (r.s === "Needs Review" || r.s === "Needs review") dispStatus = "Needs review";
      else if (r.s === "Signed Off" || r.s === "Signed off") dispStatus = "Signed off";
      return g + '<div class="row' + (arch ? ' arch' : '') + '"><div class="who"><b>' + esc(cap(r.n)) + '</b><span>' + esc(r.t) + ' · ' + esc(r.by) + '</span></div>'
        + '<span class="date mono">' + esc(fd(r.d)) + '</span>'
        + '<span class="status ' + (CLS[r.s] || 's-final') + '"><span class="dot"></span>' + esc(dispStatus) + '</span>'
        + pdf
        + '<div class="acts">' + main + '<a class="ibtn" href="' + esc(r.view) + '" data-tip="Preview" aria-label="Preview report">' + I("eye") + '</a>'
        + archBtn + '</div></div>';
    }).join("") || '<div class="empty"><b>No reports match</b><p>Try a different filter or search.</p></div>';
    var f = st.q || st.nopdf || st.t !== "all";
    $("count").textContent = f ? rows.length + " results" : "Showing " + rows.length + " of " + R.length;
    document.querySelectorAll(".sort").forEach(function(b) {
      var on = b.dataset.k === st.k;
      b.toggleAttribute("data-on", on);
      b.dataset.dir = on ? st.dir : "asc";
    });
  }

  var timer;
  function toast(m) {
    var t = $("toast");
    t.textContent = m;
    t.classList.add("on");
    clearTimeout(timer);
    timer = setTimeout(function() { t.classList.remove("on"); }, 3500);
  }

  $("tabs").addEventListener("click", function(e) {
    var b = e.target.closest(".tab");
    if (!b) return;
    st.t = b.dataset.t;
    renderTabs();
    render();
  });

  $("q").addEventListener("input", function(e) {
    st.q = e.target.value.toLowerCase().trim();
    render();
  });

  $("nopdf").addEventListener("click", function(e) {
    st.nopdf = !st.nopdf;
    e.currentTarget.setAttribute("aria-pressed", st.nopdf);
    render();
  });

  document.querySelector(".head").addEventListener("click", function(e) {
    var b = e.target.closest(".sort");
    if (!b) return;
    if (st.k === b.dataset.k) {
      st.dir = st.dir === "asc" ? "desc" : "asc";
    } else {
      st.k = b.dataset.k;
      st.dir = b.dataset.k === "date" ? "desc" : "asc";
    }
    render();
  });

  $("emp").addEventListener("change", function() {
    updateEmpContext();
    dupCheck();
  });
  $("rt").addEventListener("change", dupCheck);

  document.addEventListener("click", function(e) {
    var sBtn = e.target.closest("[data-sha]");
    if (sBtn && sBtn.dataset.sha) {
      var sha = sBtn.dataset.sha;
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(sha);
      }
      toast("SHA-256 fingerprint copied");
    }
  });

  renderTabs();
  render();
  dupCheck();
  </script>
</body>
</html>
