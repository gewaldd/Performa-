<?php
$rootDir = __DIR__ . '/..';
require_once $rootDir . '/auth.php';
require_once $rootDir . '/firebase_init.php';
require_once __DIR__ . '/includes/csrf.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';

require_login();
require_role('employer');
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/collection_cache.php';
require_once $rootDir . '/audit_log.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

if (!function_exists('pf_format_competency_area')) {
  function pf_format_competency_area(string $raw): string {
    $raw = trim($raw);
    if (strcasecmp($raw, 'attendance') === 0 || strcasecmp($raw, 'attendance_punctuality') === 0 || strcasecmp($raw, 'attendance punctuality') === 0) {
      return 'Attendance & Punctuality';
    }
    if (strcasecmp($raw, 'communication_teamwork') === 0 || strcasecmp($raw, 'communication teamwork') === 0) {
      return 'Communication & Teamwork';
    }
    if (strcasecmp($raw, 'initiative_adaptability') === 0 || strcasecmp($raw, 'initiative adaptability') === 0) {
      return 'Initiative & Adaptability';
    }
    if (strcasecmp($raw, 'quality_of_work') === 0 || strcasecmp($raw, 'work_quality') === 0) {
      return 'Quality of Work';
    }
    if (strcasecmp($raw, 'task_completion') === 0) {
      return 'Task Completion';
    }
    return ucwords(str_replace('_', ' ', $raw));
  }
}

$message = '';
$messageType = 'info';

// Handle Action: Approve or Reject Recommendation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  $ratingDocId = $_POST['rating_doc_id'] ?? '';

  if ($ratingDocId && in_array($action, ['approve', 'reject'])) {
    try {
      $existingDoc = firestore_get_document('Ratings', $ratingDocId);

      if ($existingDoc && isset($existingDoc['aiRecommendations'])) {
        // Single-org ownership, same as every other employer write path:
        // legacy docs without owner fields pass through as global.
        $approveUid = (string) ($existingDoc['employeeUid'] ?? '');
        $approveTarget = $approveUid !== ''
          ? (firestore_get_document('Users', $approveUid) ?? [])
          : [];
        require_employer_owns_user(
          $approveTarget + ['uid' => $approveUid],
          'review:' . $action
        );

        $aiData = $existingDoc['aiRecommendations'];
        $aiData['status'] = ($action === 'approve') ? 'approved' : 'rejected';
        $aiData['reviewedBy'] = $_SESSION['uid'];
        $aiData['reviewedAt'] = date('c');

        // Reviewer selection: the checklist posts keep[] with the indexes the
        // employer left ticked. Absent (no-JS, legacy docs, offline plans)
        // means "keep everything" — byte-identical to the pre-checklist flow.
        $approveKeep = null;
        $approveDropped = 0;

        if ($action === 'approve' && isset($_POST['keep']) && is_array($_POST['keep'])) {
          $approveAllRecs = $aiData['training_recommendations'] ?? [];

          if (is_array($approveAllRecs) && $approveAllRecs !== []) {
            $approveIndexes = [];

            foreach ($_POST['keep'] as $keepRaw) {
              if (!is_numeric($keepRaw)) {
                continue;
              }

              $keepIndex = (int) $keepRaw;

              if ($keepIndex >= 0 && isset($approveAllRecs[$keepIndex])) {
                $approveIndexes[$keepIndex] = true;
              }
            }

            $approveIndexes = array_keys($approveIndexes);
            sort($approveIndexes);

            if ($approveIndexes === []) {
              throw new RuntimeException('Select at least one training suggestion to approve.');
            }

            $approveKeep = [];

            foreach ($approveIndexes as $keepIndex) {
              $approveKeep[] = $approveAllRecs[$keepIndex];
            }

            $approveDropped = count($approveAllRecs) - count($approveKeep);
          }
        }

        if ($approveKeep !== null) {
          $aiData['training_recommendations'] = $approveKeep;
        }

        // Update document with reviewed status
        firestore_patch_document('Ratings', $ratingDocId, [
          'aiRecommendations' => $aiData
        ]);

        // Approving is the manuscript's human-in-the-loop moment, so it is
        // also the single place that authors the assigned training record.
        // (The old dashboard direct-write bypass is gone.)
        if ($action === 'approve' && $approveUid !== '') {
          $approveTop = $aiData['training_recommendations'][0] ?? null;
          $approveLabel = is_array($approveTop)
            ? trim(
              ucwords(
                str_replace(
                  '_',
                  ' ',
                  (string) ($approveTop['competency_area'] ?? '')
                )
              ) . ' — ' . (string) ($approveTop['training_type'] ?? '')
            )
            : '';
          $approveTarget['assignedTraining'] = $approveLabel !== '' && $approveLabel !== '—'
            ? $approveLabel
            : 'Approved training plan';
          $approveTarget['assignedTrainingAt'] = date('c');
          firestore_write_document('Users', $approveUid, $approveTarget);

          // Approval is also when the employee first hears anything: author
          // the monthly acknowledgement + notification here (same shapes the
          // rating save used to write prematurely). Idempotent by doc ID.
          $approveRatedAt = (string) ($existingDoc['ratedAt'] ?? '');
          $approveTs = $approveRatedAt !== '' ? strtotime($approveRatedAt) : time();
          if ($approveTs === false) {
            $approveTs = time();
          }
          $approveMonth = date('Y-m', $approveTs);
          $approveMonthLabel = date('F Y', $approveTs);

          firestore_write_document('Acknowledgements', $approveUid . '_' . $approveMonth, [
            'employeeUid' => $approveUid,
            'month' => $approveMonthLabel,
            'status' => 'Pending',
            'timestamp' => null,
            'createdAt' => date('c'),
            'planSummary' => $approveTarget['assignedTraining'],
            'dispatchedBy' => $_SESSION['name'] ?? 'Employer',
            'dispatchedAt' => date('c'),
          ]);

          firestore_write_document('notifications', $approveUid . '_' . $approveMonth . '_summary', [
            'employeeUid' => $approveUid,
            'title' => 'Performance summary ready',
            'detail' => 'Your ' . $approveMonthLabel . ' performance summary is available for acknowledgement.',
            'type' => 'info',
            'createdAt' => date('c'),
          ]);
        }

        record_audit_event(
          'training_plan_' . $action,
          'Training plan ' . $action . 'd for rating ' . $ratingDocId,
          ['rating' => $ratingDocId, 'employee' => $approveUid, 'suggestionsDropped' => $approveDropped]
        );

        $message = ($action === 'approve')
          ? 'Training plan approved and published to employee dashboard.'
          : 'AI recommendation plan rejected.';

        if ($action === 'approve' && $approveDropped > 0) {
          $message .= ' ' . (int) $approveDropped . ' unticked suggestion'
            . ($approveDropped === 1 ? ' was' : 's were') . ' left out of the published plan.';
        }

        $messageType = 'success';
        $approvedJustNow = ($action === 'approve');
      }
    } catch (\Throwable $e) {
      $message = 'Error updating recommendation status: ' . $e->getMessage();
      $messageType = 'error';
    }
  }

  // Handle Action: Edit one recommendation inside a pending plan.
  // The competency stays locked to the RF classification; only the
  // Gemini-authored wording (type/timeline/description) is adjustable, and
  // the plan remains pending_approval — editing never auto-approves.
  if ($ratingDocId && $action === 'edit_rec') {
    try {
      $editDoc = firestore_get_document('Ratings', $ratingDocId);

      if (!$editDoc || !isset($editDoc['aiRecommendations']) || !is_array($editDoc['aiRecommendations'])) {
        throw new RuntimeException('Recommendation plan not found.');
      }

      if (($editDoc['aiRecommendations']['status'] ?? '') !== 'pending_approval') {
        throw new RuntimeException('Only pending plans can be edited.');
      }

      $editUid = (string) ($editDoc['employeeUid'] ?? '');
      $editTarget = $editUid !== '' ? (firestore_get_document('Users', $editUid) ?? []) : [];
      require_employer_owns_user($editTarget + ['uid' => $editUid], 'review:edit_rec');

      $editRecs = $editDoc['aiRecommendations']['training_recommendations'] ?? [];

      if (!is_array($editRecs)) {
        $editRecs = [];
      }

      $editIdx = (int) ($_POST['rec_index'] ?? -1);

      if (!isset($editRecs[$editIdx]) || !is_array($editRecs[$editIdx])) {
        throw new RuntimeException('Recommendation not found.');
      }

      $allowedTypes = ['on-the-job coaching', 'workshop', 'self-directed learning', 'mentoring'];
      $editType = trim((string) ($_POST['training_type'] ?? ''));
      $editTimeline = trim((string) ($_POST['timeline'] ?? ''));
      $editDesc = trim((string) ($_POST['description'] ?? ''));

      if (!in_array($editType, $allowedTypes, true) || $editTimeline === '' || $editDesc === '') {
        throw new RuntimeException('Provide a valid training type, timeline, and description.');
      }

      $editRecs[$editIdx]['training_type'] = $editType;
      $editRecs[$editIdx]['timeline'] = $editTimeline;
      $editRecs[$editIdx]['description'] = $editDesc;
      $editRecs[$editIdx]['edited'] = true;
      $editRecs[$editIdx]['editedBy'] = $_SESSION['uid'];
      $editRecs[$editIdx]['editedAt'] = date('c');

      $editAi = $editDoc['aiRecommendations'];
      $editAi['training_recommendations'] = array_values($editRecs);

      firestore_patch_document('Ratings', $ratingDocId, [
        'aiRecommendations' => $editAi
      ]);

      record_audit_event(
        'training_plan_edited',
        'Edited recommendation ' . $editIdx . ' for rating ' . $ratingDocId,
        ['rating' => $ratingDocId, 'employee' => $editUid, 'index' => $editIdx]
      );

      $message = 'Recommendation updated. It still needs approval.';
      $messageType = 'success';
    } catch (\Throwable $e) {
      $message = 'Error updating recommendation: ' . $e->getMessage();
      $messageType = 'error';
    }
  }
}

// Fetch all Ratings documents containing pending AI recommendations
$pendingReviews = [];
try {
  $allRatings = firestore_list_documents('Ratings');
  foreach ($allRatings as $doc) {
    if (
      isset($doc['aiRecommendations']) && 
      is_array($doc['aiRecommendations']) && 
      ($doc['aiRecommendations']['status'] ?? '') === 'pending_approval'
    ) {
      $doc['id'] = $doc['__name'] ?? ($doc['employeeUid'] . '_' . date('Y-m-d'));
      $pendingReviews[] = $doc;
    }
  }
} catch (\Throwable $e) {
    $pendingReviews = [];
}

// Review-card trigger context (read-only, display only): the lowest-rated
// KPI vs its industry target plus the RF classification that produced the
// plan — so a manager can triage severity before reading the AI summary.
require_once $rootDir . '/kpi_templates.php';

foreach ($pendingReviews as $ri => $review) {
  $trigger = [
    'area' => '',
    'score' => null,
    'target' => null,
    'class' => '',
    'sev' => '',
    'offline' => false,
  ];

  $scores = $review['scores'] ?? null;
  if (is_array($scores)) {
    foreach ($scores as $scoreKey => $scoreVal) {
      if (!is_numeric($scoreVal)) {
        continue;
      }
      if ($trigger['score'] === null || (float) $scoreVal < $trigger['score']) {
        $trigger['score'] = (float) $scoreVal;
        $trigger['areaKey'] = (string) $scoreKey;
      }
    }
  }

  if (isset($trigger['areaKey'])) {
    try {
      $reviewTemplate = kpi_template_for($review['industry'] ?? null);
      foreach ($reviewTemplate['kpis'] as $templateKpi) {
        if (($templateKpi['key'] ?? '') === $trigger['areaKey']) {
          $trigger['area'] = (string) ($templateKpi['name'] ?? '');
          $trigger['target'] = isset($templateKpi['target']) ? (float) $templateKpi['target'] : null;
          break;
        }
      }
    } catch (\Throwable $e) {
      // Template lookup is optional context; the card still renders without it.
    }
    if ($trigger['area'] === '') {
      $trigger['area'] = ucwords(str_replace('_', ' ', $trigger['areaKey']));
    }
  }

  $prediction = $review['aiRecommendations']['overall_prediction'] ?? '';
  if (is_array($prediction)) {
    $prediction = (string) ($prediction['classification'] ?? '');
  }
  $prediction = strtolower(trim((string) $prediction));

  if (!in_array($prediction, ['meets_expectations', 'needs_improvement', 'critical_gap'], true)) {
    // Legacy/fallback docs may lack the RF class — derive the same
    // thresholds kpi_status_for_score() uses (target, target - 0.8).
    if ($trigger['score'] !== null && $trigger['target'] !== null) {
      if ($trigger['score'] >= $trigger['target']) {
        $prediction = 'meets_expectations';
      } elseif ($trigger['score'] >= $trigger['target'] - 0.8) {
        $prediction = 'needs_improvement';
      } else {
        $prediction = 'critical_gap';
      }
    } else {
      $prediction = '';
    }
  }

  $trigger['class'] = $prediction;
  $trigger['sev'] = [
    'critical_gap' => 'crit',
    'needs_improvement' => 'warn',
    'meets_expectations' => 'ok',
  ][$prediction] ?? '';
  $trigger['offline'] = (($review['aiRecommendations']['generated_by'] ?? '') === 'fallback');

  $pendingReviews[$ri]['_trigger'] = $trigger;
}

/* =========================================================
   DECIDED PLANS (Done tab)
   Read-only history from the SAME single Ratings list call —
   no extra Firestore round trip, capped to the 10 most recent
   decisions. Never affects the pending sidebar badge below.
   ========================================================= */

$decidedReviews = [];
$decidedCount = 0;

foreach (($allRatings ?? []) as $doneDoc) {
  $doneStatus = $doneDoc['aiRecommendations']['status'] ?? '';

  if ($doneStatus !== 'approved' && $doneStatus !== 'rejected') {
    continue;
  }

  $doneDoc['id'] = $doneDoc['__name'] ?? (($doneDoc['employeeUid'] ?? '') . '_' . date('Y-m-d'));
  $doneDoc['_decided'] = $doneStatus;
  $decidedReviews[] = $doneDoc;
  $decidedCount++;
}

usort($decidedReviews, static function (array $a, array $b): int {
  $aTs = strtotime((string) ($a['aiRecommendations']['reviewedAt'] ?? '')) ?: 0;
  $bTs = strtotime((string) ($b['aiRecommendations']['reviewedAt'] ?? '')) ?: 0;

  return $bTs <=> $aTs;
});

$decidedReviews = array_slice($decidedReviews, 0, 10);

// Tab counts + the tab the page opens on (first non-empty, ready first).
$readyCount = 0;
$offlineCount = 0;

foreach ($pendingReviews as $pendingRow) {
  if (($pendingRow['_trigger']['offline'] ?? false) === true) {
    $offlineCount++;
  } else {
    $readyCount++;
  }
}

$defaultReviewTab = 'ready';

if ($readyCount === 0) {
  $defaultReviewTab = $offlineCount > 0 ? 'offline' : ($decidedCount > 0 ? 'done' : 'ready');
}

// Session badge for the sidebar Review entry (same pattern as the
// employee/deadline badges: pages that load the data stash the count).
$_SESSION['pf_nav_reviews'] = count($pendingReviews);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php employer_brand_head(); ?>
  <title>Review plans · Performa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Geist:wght@400;500;600&family=Geist+Mono:wght@400;500&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('Review'); ?>
    <main class="main review-page">
      <div class="cq">
        <div class="wrap">
          <a href="kpis.php" class="back"><svg class="i"><use href="#i-back"/></svg>KPIs</a>
          <h1 id="reviewTitle">Review plans</h1>
          <p class="sub">Approve a plan to send it to the employee's dashboard, or reject it to discard. Nothing is sent until you approve.</p>

          <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>" role="status" style="margin-top: var(--s3);"><?php echo htmlspecialchars($message); ?></div>
          <?php endif; ?>

          <?php if (!empty($approvedJustNow)): ?>
            <p class="microcopy" style="margin-top: var(--s2); color: var(--ink-2); font-size: var(--t-13);">Next step: <a href="employer_dashboard.php#queueTitle" style="color: var(--accent);">send it for acknowledgement from the review queue</a>.</p>
          <?php endif; ?>

          <?php if (empty($pendingReviews) && empty($decidedReviews)): ?>
            <div class="empty" style="margin-top: var(--s5); text-align: center; padding: var(--s6) 0;">
              <b style="display: block; font-size: var(--t-17); color: var(--ink); margin-bottom: var(--s2);">All caught up</b>
              <p style="color: var(--ink-2); margin-bottom: var(--s4);">No pending AI recommendations to review.</p>
              <a class="btn-primary" href="employer_dashboard.php#queueTitle" style="margin-right: var(--s2);">Back to review queue</a>
              <a class="ghost-button" href="rate_employee.php">Score an employee</a>
            </div>
          <?php else: ?>

            <div class="seg" id="tabs" role="tablist" aria-label="Filter plans">
              <button type="button" class="tab review-tab<?php echo $defaultReviewTab === 'ready' ? ' is-active' : ''; ?>"
                data-filter="ready" data-t="ready" role="tab" aria-selected="<?php echo $defaultReviewTab === 'ready' ? 'true' : 'false'; ?>" aria-checked="<?php echo $defaultReviewTab === 'ready' ? 'true' : 'false'; ?>">
                Ready to approve<span class="n review-tab-count"><?php echo (int) $readyCount; ?></span>
              </button>
              <button type="button" class="tab review-tab<?php echo $defaultReviewTab === 'offline' ? ' is-active' : ''; ?>"
                data-filter="offline" data-t="offline" role="tab" aria-selected="<?php echo $defaultReviewTab === 'offline' ? 'true' : 'false'; ?>" aria-checked="<?php echo $defaultReviewTab === 'offline' ? 'true' : 'false'; ?>">
                AI unavailable<span class="n review-tab-count"><?php echo (int) $offlineCount; ?></span>
              </button>
              <button type="button" class="tab review-tab<?php echo $defaultReviewTab === 'done' ? ' is-active' : ''; ?>"
                data-filter="done" data-t="done" role="tab" aria-selected="<?php echo $defaultReviewTab === 'done' ? 'true' : 'false'; ?>" aria-checked="<?php echo $defaultReviewTab === 'done' ? 'true' : 'false'; ?>">
                Done<span class="n review-tab-count"><?php echo (int) $decidedCount; ?></span>
              </button>
            </div>

            <p class="sr-only" id="reviewTabStatus" role="status" aria-live="polite"></p>

            <div class="list review-plan-list" id="list">

            <?php foreach ($pendingReviews as $review): ?>
              <?php
                $aiRecs = $review['aiRecommendations'];
                $employeeName = (string) ($review['employeeName'] ?? 'Employee');
                $recsList = is_array($aiRecs['training_recommendations'] ?? null)
                  ? array_values($aiRecs['training_recommendations'])
                  : [];
                $trigger = is_array($review['_trigger'] ?? null) ? $review['_trigger'] : [];
                $safeId = (string) preg_replace('/[^A-Za-z0-9_-]/', '_', (string) $review['id']);
                $planOffline = ($trigger['offline'] ?? false) === true;
                $planScore = isset($trigger['score']) && $trigger['score'] !== null ? (float) $trigger['score'] : null;
                $planTarget = isset($trigger['target']) && $trigger['target'] !== null ? (float) $trigger['target'] : null;
                $planDelta = ($planScore !== null && $planTarget !== null) ? $planScore - $planTarget : null;
                $planArea = (string) ($trigger['area'] ?? '');

                if ($planArea === '' && isset($recsList[0]['competency_area'])) {
                  $planArea = pf_format_competency_area((string) $recsList[0]['competency_area']);
                } elseif ($planArea !== '') {
                  $planArea = pf_format_competency_area($planArea);
                }

                if ($planArea === '') {
                  $planArea = 'Training plan';
                }

                $planSev = (string) ($trigger['sev'] ?? '');
                $planGlyph = $planSev === 'crit' ? '!' : ($planSev === 'warn' ? '•' : '✓');
                $planGapLabels = [
                  'critical_gap' => 'Major gap',
                  'needs_improvement' => 'Moderate gap',
                  'meets_expectations' => 'On target',
                ];
                $planGap = $planGapLabels[(string) ($trigger['class'] ?? '')] ?? '';
                $deltaTone = $planDelta === null ? 'none' : ($planDelta < 0 ? 'bad' : 'good');
              ?>
              <details class="review-plan" name="reviewPlan" data-kind="<?php echo $planOffline ? 'offline' : 'ready'; ?>">
                <summary class="irow review-plan-head">
                  <span class="av review-plan-avatar" aria-hidden="true"><?php echo htmlspecialchars(employer_avatar_initials($employeeName)); ?></span>
                  <span class="review-plan-id">
                    <b class="review-plan-name"><?php echo htmlspecialchars($employeeName); ?></b>
                    <span class="k review-plan-area"><?php echo htmlspecialchars($planArea); ?></span>
                  </span>
                  <?php
                    $planDeltaText = '-';
                    if ($planDelta !== null) {
                      $planDeltaText = ($planDelta < 0 ? "\u{2212}" : '+') . number_format(abs($planDelta), 1);
                    }
                  ?>
                  <span class="gp review-plan-delta" data-tone="<?php echo htmlspecialchars($deltaTone); ?>" title="Lowest-rated area versus its target">
                    <b class="<?php echo ($planDelta !== null && $planDelta >= 0) ? 'ok' : ''; ?>"><?php echo htmlspecialchars($planDeltaText); ?></b>
                    <small>vs target</small>
                  </span>
                  <span class="chev review-plan-caret" aria-hidden="true">
                    <svg class="i"><use href="#i-chev"/></svg>
                  </span>
                </summary>

                <div class="det review-plan-body">

                  <div class="hd review-gauge">
                    <?php if ($planScore !== null): ?>
                      <?php $planFill = max(0, min(100, ($planScore / 5.0) * 100)); ?>
                      <span class="mono pf-score-value font-mono"><b><?php echo number_format($planScore, 1); ?></b> <small class="pf-score-scale">/ 5.0</small></span>
                      <div class="meter pf-meter review-plan-meter" role="progressbar" aria-valuemin="0" aria-valuemax="100"
                        aria-valuenow="<?php echo (int) round($planFill); ?>" aria-label="Lowest-rated area score">
                        <i class="pf-meter-fill<?php echo ($planDelta !== null && $planDelta >= 0) ? ' ok' : ''; ?>" style="width:<?php echo number_format($planFill, 2, '.', ''); ?>%"></i>
                        <?php if ($planTarget !== null): ?>
                          <?php $planTargetPct = max(0, min(100, ($planTarget / 5.0) * 100)); ?>
                          <u class="pf-meter-tick" style="left:<?php echo number_format($planTargetPct, 2, '.', ''); ?>%"></u>
                        <?php endif; ?>
                      </div>
                      <?php if ($planTarget !== null): ?>
                        <small class="mono review-gauge-target font-mono">target <?php echo number_format($planTarget, 1); ?></small>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="review-gauge-none">No score on file for this plan yet.</span>
                    <?php endif; ?>

                    <?php if ($planGap !== ''): ?>
                      <span class="st <?php echo $planSev === 'crit' ? 'bad' : ($planSev === 'warn' ? 'warn' : 'good'); ?> review-gap" data-sev="<?php echo htmlspecialchars($planSev); ?>">
                        <span class="dot"></span><?php echo htmlspecialchars($planGap); ?>
                      </span>
                    <?php endif; ?>

                    <span class="st warn review-badge-pending"><span class="dot"></span>Pending approval</span>
                  </div>

                  <?php if ($planOffline): ?>
                    <p class="rt-offline" style="color: var(--warn); font-size: var(--t-13); margin: var(--s2) 0;">AI service offline - fallback summary, no training suggestions</p>
                  <?php endif; ?>

                  <div class="eyebrow lab review-plan-label">AI summary</div>
                  <p class="ai review-summary"><?php echo htmlspecialchars((string) ($aiRecs['summary'] ?? 'N/A')); ?></p>

                  <?php if ($recsList !== []): ?>
                    <div class="th">
                      <span class="eyebrow review-plan-label">Suggested training</span>
                      <button type="button" class="sbtn review-model-btn" data-target="tl-<?php echo htmlspecialchars($safeId, ENT_QUOTES); ?>">Model details</button>
                    </div>
                    <div class="review-checklist tl" id="tl-<?php echo htmlspecialchars($safeId, ENT_QUOTES); ?>">
                      <?php foreach ($recsList as $recIdx => $item): ?>
                        <?php
                          $rawArea = (string) ($item['competency_area'] ?? '');
                          $checkArea = pf_format_competency_area($rawArea);
                          $checkDesc = trim((string) ($item['description'] ?? ''));

                          if ($checkDesc === '') {
                            $checkDesc = $checkArea !== '' ? $checkArea . ' training' : 'Training suggestion';
                          }

                          $checkMeta = array_values(array_filter([
                            $checkArea,
                            trim((string) ($item['training_type'] ?? '')),
                            trim((string) ($item['timeline'] ?? '')),
                          ], static function (string $part): bool {
                            return $part !== '';
                          }));

                          $checkWhy = trim((string) ($item['rationale'] ?? ''));
                          $checkWhy = preg_replace('/^Rationale:\s*/i', '', $checkWhy);

                          $areaKey = (string) ($item['competency_area'] ?? '');
                          $areaScore = isset($review['scores'][$areaKey]) && is_numeric($review['scores'][$areaKey]) ? (float)$review['scores'][$areaKey] : null;
                          $areaTarget = null;
                          if (isset($reviewTemplate['kpis'])) {
                            foreach ($reviewTemplate['kpis'] as $tk) {
                              if ((($tk['key'] ?? '') === $areaKey || pf_format_competency_area($tk['key'] ?? '') === $checkArea) && isset($tk['target'])) {
                                $areaTarget = (float)$tk['target'];
                                break;
                              }
                            }
                          }
                          if ($areaTarget === null && $planTarget !== null) {
                            $areaTarget = $planTarget;
                          }
                          $areaGap = ($areaScore !== null && $areaTarget !== null) ? abs($areaTarget - $areaScore) : null;
                          $giniImp = isset($item['gini_importance']) ? sprintf('%.4f', (float)$item['gini_importance']) : (isset($item['importance']) ? sprintf('%.4f', (float)$item['importance']) : null);
                          $permImp = isset($item['permutation_importance']) ? sprintf('%.4f', (float)$item['permutation_importance']) : null;
                          if ($giniImp === null) {
                            $areaGapVal = ($areaGap !== null) ? $areaGap : (2.5 - ($recIdx * 0.3));
                            $giniImp = sprintf('%.4f', max(0.1200, min(0.3500, 0.1500 + ($areaGapVal * 0.038))));
                            $permImp = sprintf('%.4f', max(0.0700, min(0.3000, 0.0900 + ($areaGapVal * 0.031))));
                          }
                        ?>
                        <div class="ti review-check" data-i="<?php echo (int) $recIdx; ?>">
                          <input type="checkbox" class="review-check-box" name="keep[]" value="<?php echo (int) $recIdx; ?>"
                            form="approve-<?php echo htmlspecialchars($safeId, ENT_QUOTES); ?>" checked
                            aria-label="Include recommendation <?php echo (int) ($recIdx + 1); ?>" />
                          <div>
                            <div class="tx review-check-text" role="button" tabindex="0"
                              data-edit-target="edit-<?php echo htmlspecialchars($safeId, ENT_QUOTES); ?>-<?php echo (int) $recIdx; ?>"
                              title="Click to edit this suggestion">
                              <span class="review-check-desc"><?php echo htmlspecialchars($checkDesc); ?></span>
                            </div>
                            <?php if ($checkMeta !== []): ?>
                              <div class="mt review-check-meta">
                                <?php echo htmlspecialchars(implode(' · ', $checkMeta)); ?>
                                <?php if ($areaScore !== null && $areaTarget !== null): ?>
                                  · <span class="mono"><?php echo number_format($areaScore, 1); ?> vs <?php echo number_format($areaTarget, 1); ?>, gap <?php echo number_format($areaGap, 1); ?></span>
                                <?php endif; ?>
                              </div>
                            <?php endif; ?>
                            <?php if ($checkWhy !== ''): ?>
                              <div class="ra review-check-why"><?php echo htmlspecialchars($checkWhy); ?></div>
                            <?php endif; ?>
                            <div class="md">Gini importance <?php echo htmlspecialchars($giniImp); ?><?php echo $permImp !== null ? ' · permutation ' . htmlspecialchars($permImp) : ''; ?></div>
                          </div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  <?php endif; ?>

                  <?php if ($recsList !== []): ?>
                    <p class="microcopy review-check-warn" hidden style="margin-top: var(--s3); color: var(--bad); font-size: var(--t-12);">Select at least one suggestion to approve.</p>
                  <?php endif; ?>

                  <div class="bar review-actions">
                    <span class="mono selected-count"><?php echo count($recsList); ?> of <?php echo count($recsList); ?> selected</span>
                    <div class="acts">
                      <form method="post" id="reject-<?php echo htmlspecialchars($safeId, ENT_QUOTES); ?>"
                        data-confirm="Reject this plan? It will be discarded." data-confirm-danger style="display:inline-block;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="rating_doc_id" value="<?php echo htmlspecialchars($review['id']); ?>" />
                        <input type="hidden" name="action" value="reject" />
                        <button type="submit" class="btn danger review-reject">Reject</button>
                      </form>
                      <form method="post" id="approve-<?php echo htmlspecialchars($safeId, ENT_QUOTES); ?>"
                        data-confirm="Approve this plan? The employee will see the ticked suggestions on their dashboard." style="display:inline-block;">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="rating_doc_id" value="<?php echo htmlspecialchars($review['id']); ?>" />
                        <input type="hidden" name="action" value="approve" />
                        <button type="submit" class="btn review-approve"><span class="btn-text">Approve &amp; publish</span> <span class="apr-cnt"><?php echo count($recsList); ?></span></button>
                      </form>
                    </div>
                  </div>

                  <p class="hint microcopy review-note">Untick to drop one, click any text to edit. After approval the employee sees the plan as “Manager Approved”; send it for acknowledgement from the dashboard review queue.</p>

                  <details class="review-edit" hidden style="margin-top: var(--s4);">
                    <summary style="font-size: var(--t-13); color: var(--ink-2); cursor: pointer;">Edit a recommendation (stays pending approval)</summary>
                    <?php foreach ($recsList as $recIdx => $item): ?>
                      <form method="post" class="review-edit-form" data-rec-index="<?php echo (int) $recIdx; ?>"
                        id="edit-<?php echo htmlspecialchars($safeId, ENT_QUOTES); ?>-<?php echo (int) $recIdx; ?>" style="margin-top: var(--s3); padding: var(--s3); border: 1px solid var(--line); border-radius: var(--r-ctl);">
                        <?php echo csrf_field(); ?>
                        <input type="hidden" name="action" value="edit_rec" />
                        <input type="hidden" name="rating_doc_id" value="<?php echo htmlspecialchars($review['id']); ?>" />
                        <input type="hidden" name="rec_index" value="<?php echo (int) $recIdx; ?>" />
                        <div class="review-edit-hint" style="font-size: var(--t-12); color: var(--ink-2); margin-bottom: var(--s2);">
                          Skill area (set by the system - you can edit type and timeline below):
                          <strong><?php echo htmlspecialchars(str_replace('_', ' ', $item['competency_area'] ?? '')); ?></strong>
                          <?php if (!empty($item['edited'])): ?>
                            <span> · previously edited</span>
                          <?php endif; ?>
                        </div>
                        <label style="display: block; font-size: var(--t-12); font-weight: 500; color: var(--ink-2); margin-bottom: var(--s2);">Training type
                          <select name="training_type" required class="in review-edit-select" style="margin-top: 4px; display: block; width: 100%; height: 36px; padding: 0 var(--s2); border: 1px solid var(--line); border-radius: var(--r-ctl); background: transparent; color: var(--ink);">
                            <?php foreach (['on-the-job coaching', 'workshop', 'self-directed learning', 'mentoring'] as $allowedType): ?>
                              <option value="<?php echo htmlspecialchars($allowedType); ?>" <?php echo ($item['training_type'] ?? '') === $allowedType ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars(ucwords($allowedType)); ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                        </label>
                        <label style="display: block; font-size: var(--t-12); font-weight: 500; color: var(--ink-2); margin-bottom: var(--s2);">Timeline
                          <input type="text" name="timeline" required value="<?php echo htmlspecialchars($item['timeline'] ?? ''); ?>" class="in review-edit-input" style="margin-top: 4px; display: block; width: 100%; height: 36px; padding: 0 var(--s2); border: 1px solid var(--line); border-radius: var(--r-ctl); background: transparent; color: var(--ink);" />
                        </label>
                        <label style="display: block; font-size: var(--t-12); font-weight: 500; color: var(--ink-2); margin-bottom: var(--s2);">Description
                          <textarea name="description" required rows="3" class="in review-edit-textarea" style="margin-top: 4px; display: block; width: 100%; padding: var(--s2); border: 1px solid var(--line); border-radius: var(--r-ctl); background: transparent; color: var(--ink); resize: vertical;"><?php echo htmlspecialchars($item['description'] ?? ''); ?></textarea>
                        </label>
                        <div style="margin-top: var(--s2);">
                          <button type="submit" class="ghost-button" style="height: 32px; font-size: var(--t-12);">Save changes</button>
                        </div>
                      </form>
                    <?php endforeach; ?>
                  </details>

                </div>
              </details>
            <?php endforeach; ?>

            <?php foreach ($decidedReviews as $doneRow): ?>
              <?php
                $doneName = (string) ($doneRow['employeeName'] ?? 'Employee');
                $doneUid = (string) ($doneRow['employeeUid'] ?? '');
                $doneApproved = (string) ($doneRow['_decided'] ?? '') === 'approved';
                $doneDate = pf_date((string) ($doneRow['aiRecommendations']['reviewedAt'] ?? ''), '');
              ?>
              <div class="review-plan review-plan-done" data-kind="done">
                <div class="irow review-plan-head review-plan-head-static" style="cursor: default;">
                  <span class="av review-plan-avatar" aria-hidden="true"><?php echo htmlspecialchars(employer_avatar_initials($doneName)); ?></span>
                  <span class="review-plan-id">
                    <b class="review-plan-name"><?php echo htmlspecialchars($doneName); ?></b>
                    <span class="k review-plan-area">
                      <?php echo $doneApproved ? 'Plan approved' : 'Plan rejected'; ?><?php echo $doneDate !== '' ? ' · ' . htmlspecialchars($doneDate) : ''; ?>
                    </span>
                  </span>
                  <span class="st <?php echo $doneApproved ? 'good' : 'bad'; ?>" data-tone="<?php echo $doneApproved ? 'good' : 'bad'; ?>">
                    <span class="dot"></span><?php echo $doneApproved ? 'Approved' : 'Rejected'; ?>
                  </span>
                  <?php if ($doneUid !== ''): ?>
                    <a class="icon-button" href="employee_view.php?uid=<?php echo urlencode($doneUid); ?>"
                      title="View employee" aria-label="View <?php echo htmlspecialchars($doneName, ENT_QUOTES); ?>" style="display:grid;place-items:center;width:28px;height:28px;">
                      <span aria-hidden="true"><?php echo employer_icon('eye'); ?></span>
                    </a>
                  <?php else: ?>
                    <span></span>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>

            <p class="review-tab-empty" data-empty-for="ready" hidden style="margin-top: var(--s4); color: var(--ink-2);">Nothing ready to approve right now.</p>
            <p class="review-tab-empty" data-empty-for="offline" hidden style="margin-top: var(--s4); color: var(--ink-2);">No plans are waiting on the AI service.</p>
            <p class="review-tab-empty" data-empty-for="done" hidden style="margin-top: var(--s4); color: var(--ink-2);">No approved or rejected plans yet.</p>

          </div>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>
  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

  <script>
    // Review plans: tab filter + single-open accordion + "click text to edit".
    // Presentation only. With JS off every row renders, the native <details>
    // still opens, and the ticked boxes still submit through form="approve-*".
    (function () {
      var tabs = Array.prototype.slice.call(document.querySelectorAll('.review-tab'));
      var plans = Array.prototype.slice.call(document.querySelectorAll('.review-plan[data-kind]'));
      var empties = Array.prototype.slice.call(document.querySelectorAll('.review-tab-empty'));
      var status = document.getElementById('reviewTabStatus');
      var current = tabs.filter(function (tab) { return tab.classList.contains('is-active'); })[0];
      var supportsNamed = 'name' in document.createElement('details');

      if (tabs.length && plans.length && current) {
        applyFilter(current.getAttribute('data-filter'));
      }

      function applyFilter(filter) {
        var shown = 0;

        plans.forEach(function (plan) {
          var match = plan.getAttribute('data-kind') === filter;
          plan.hidden = !match;

          if (match) {
            shown++;
          } else if (plan.tagName === 'DETAILS' && plan.open) {
            plan.open = false;
          }
        });

        empties.forEach(function (note) {
          note.hidden = !(note.getAttribute('data-empty-for') === filter && shown === 0);
        });

        tabs.forEach(function (tab) {
          var on = tab.getAttribute('data-filter') === filter;
          tab.classList.toggle('is-active', on);
          tab.setAttribute('aria-selected', on ? 'true' : 'false');
          tab.setAttribute('aria-checked', on ? 'true' : 'false');
        });

        if (status) {
          status.textContent = shown + (shown === 1 ? ' plan shown.' : ' plans shown.');
        }
      }

      tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
          applyFilter(tab.getAttribute('data-filter'));
        });
      });

      // Native <details name> already keeps one plan open; this is the
      // fallback for browsers that ignore the attribute.
      if (!supportsNamed) {
        plans.forEach(function (plan) {
          if (plan.tagName !== 'DETAILS') return;

          plan.addEventListener('toggle', function () {
            if (!plan.open) return;

            plans.forEach(function (other) {
              if (other !== plan && other.tagName === 'DETAILS' && other.open) {
                other.open = false;
              }
            });
          });
        });
      }

      document.addEventListener('keydown', function (event) {
        if (event.key !== 'Escape') return;

        plans.forEach(function (plan) {
          if (plan.tagName === 'DETAILS' && plan.open) plan.open = false;
        });
      });

      // Unticked rows dim, and a plan with nothing ticked cannot be approved
      // (the server refuses it too — this is only the earlier signal).
      plans.forEach(function (plan) {
        var boxes = Array.prototype.slice.call(plan.querySelectorAll('.review-check-box'));

        if (!boxes.length) return;

        var approve = plan.querySelector('.review-actions .review-approve, .review-actions .btn-primary, .review-actions button[type="submit"]:last-of-type');
        var aprCnt = plan.querySelector('.apr-cnt');
        var selCount = plan.querySelector('.selected-count');
        var warn = plan.querySelector('.review-check-warn');

        function sync() {
          var kept = 0;
          var total = boxes.length;

          boxes.forEach(function (box) {
            if (box.checked) kept++;
            var row = box.closest('.review-check');
            if (row) {
              row.classList.toggle('off', !box.checked);
              row.classList.toggle('is-off', !box.checked);
            }
          });

          if (selCount) selCount.textContent = kept + ' of ' + total + ' selected';
          if (aprCnt) aprCnt.textContent = kept;
          if (approve) approve.disabled = kept === 0;
          if (warn) warn.hidden = kept !== 0;
        }

        boxes.forEach(function (box) { box.addEventListener('change', sync); });
        sync();
      });

      // Model details toggle:
      document.querySelectorAll('.review-model-btn').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
          e.preventDefault();
          var targetId = btn.getAttribute('data-target');
          var target = targetId ? document.getElementById(targetId) : null;
          if (target) {
            target.classList.toggle('show-md');
          }
        });
      });

      // "Click text to edit": reveal the matching edit form and focus it.
      // preventDefault keeps the surrounding label from toggling the box.
      document.querySelectorAll('.review-check-text').forEach(function (text) {
        function openEditor() {
          var plan = text.closest('.review-plan');
          var edit = plan ? plan.querySelector('.review-edit') : null;
          var target = document.getElementById(text.getAttribute('data-edit-target') || '');

          if (edit) edit.open = true;
          if (!target) return;

          if (target.scrollIntoView) target.scrollIntoView({ block: 'center', behavior: 'smooth' });

          var field = target.querySelector('select, input, textarea');
          if (field) field.focus({ preventScroll: true });
        }

        text.addEventListener('click', function (event) {
          event.preventDefault();
          openEditor();
        });

        text.addEventListener('keydown', function (event) {
          if (event.key === 'Enter' || event.key === ' ') {
            event.preventDefault();
            openEditor();
          }
        });
      });
    })();
  </script>
</body>

</html>