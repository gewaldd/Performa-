<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}

// Shared icons (Style A cleanup).
$icons = [
  'search' => employer_icon('search'),
  'plus' => employer_icon('plus'),
  'edit' => employer_icon('settings'),
  'clock' => employer_icon('hourglass'),
  'user' => employer_icon('users'),
  'dot' => employer_icon('more'),
  'download' => employer_icon('download'),
  'target' => employer_icon('target'),
];

/* =========================================================
   FAST DISK-BACKED DATA CACHING (shared include — see
   includes/collection_cache.php; extracted verbatim Item 5)
   ========================================================= */
require_once __DIR__ . '/includes/collection_cache.php';

/* =========================================================
   EMPLOYEE SELECTION
   ========================================================= */
$probationaryEmployees = [];
$allUsers = get_cached_collection('Users', 600);

foreach ($allUsers as $doc) {
  if (normalize_role_key($doc['role'] ?? null) !== 'probationary') {
    continue;
  }

  $probationaryEmployees[] = [
    'uid' => $doc['uid'] ?? '',
    'name' => $doc['name'] ?? ($doc['email'] ?? 'Unknown'),
    'industry' => $doc['industry'] ?? 'retail',
    'createdAt' => $doc['createdAt'] ?? ($doc['hireDate'] ?? ''),
    'period' => max(1, (int) ($doc['probationPeriodDays'] ?? 180)),
  ];
}

$selectedEmployeeId =
  (string) ($_GET['employee'] ?? '');

$selectedEmployee = null;

foreach ($probationaryEmployees as $emp) {
  if ($emp['uid'] === $selectedEmployeeId) {
    $selectedEmployee = $emp;
    break;
  }
}

if (
  $selectedEmployee === null &&
  !empty($probationaryEmployees)
) {
  $selectedEmployee = $probationaryEmployees[0];
}

$selectedEmployeeName =
  $selectedEmployee['name']
  ?? 'No employees yet';

$currentIndustry =
  $selectedEmployee['industry']
  ?? 'retail';

/* =========================================================
   FORM HANDLERS
   ========================================================= */
$kpiMessage = '';
$kpiMessageType = 'info';
$highlightKey = '';

// Reopens the Add KPI modal when its own submit fails validation,
// so the error message and the form stay visible together.
$addKpiOpen = false;

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'add_kpi'
) {
  $newName = trim(
    (string) ($_POST['kpi_name'] ?? '')
  );

  $newTarget = (float) (
    $_POST['kpi_target'] ?? 4.0
  );

  $industryForKpi =
    trim(
      (string) (
        $_POST['industry']
        ?? $currentIndustry
      )
    );

  // Optional display-only calibration metadata from the Add KPI form
  // (category / default weight / scoring rubric). Empty values are skipped
  // by add_custom_kpi_meta(), so the KPI itself is always created exactly
  // as before.
  $newCategory =
    strtolower(
      trim(
        (string) ($_POST['kpi_category'] ?? '')
      )
    );

  $newWeightRaw =
    $_POST['kpi_weight'] ?? null;

  $newWeight =
    is_numeric($newWeightRaw)
    ? (float) $newWeightRaw
    : null;

  $newRubric =
    trim(
      (string) ($_POST['kpi_rubric'] ?? '')
    );

  // New customs start on the default scale; bounds come from the scale
  // so future non-1-5 KPIs validate correctly with zero handler changes.
  $newScale = kpi_scale_for([]);

  if ($newName === '') {
    $kpiMessage = 'Enter a KPI name.';
    $kpiMessageType = 'error';
    $addKpiOpen = true;
  } elseif (!array_key_exists($industryForKpi, kpi_templates())) {
    $kpiMessage = 'The selected KPI industry is invalid.';
    $kpiMessageType = 'error';
    $addKpiOpen = true;
  } elseif ($newTarget < $newScale['min'] || $newTarget > $newScale['max']) {
    $kpiMessage =
      'The target score must be between ' .
      $newScale['min'] . ' and ' . $newScale['max'] . '.';
    $kpiMessageType = 'error';
    $addKpiOpen = true;
  } else {
    try {
      $newSlug = add_custom_kpi(
        $industryForKpi,
        $newName,
        kpi_clamp_score([], $newTarget)
      );

      if (
        $newCategory !== '' ||
        $newWeight !== null ||
        $newRubric !== ''
      ) {
        add_custom_kpi_meta(
          $industryForKpi,
          $newSlug,
          $newCategory,
          $newWeight,
          $newRubric
        );
      }

      $kpiMessage = 'KPI added.';
      $kpiMessageType = 'success';
      $highlightKey = $newSlug;

      clear_collection_cache('Ratings');
    } catch (Throwable $e) {
      error_log(
        'Employer KPI creation failed: ' .
        $e->getMessage()
      );

      $kpiMessage =
        'The KPI could not be added: ' . $e->getMessage();

      $kpiMessageType = 'error';
      $addKpiOpen = true;
    }
  }
}

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'edit_kpi_target'
) {
  $kpiKey = trim(
    (string) ($_POST['kpi_key'] ?? '')
  );

  $newTarget = (float) (
    $_POST['kpi_target'] ?? 4.0
  );

  $industryForKpi =
    trim(
      (string) (
        $_POST['industry']
        ?? $currentIndustry
      )
    );

  // Scale-aware target editing: bounds and clamping follow the KPI's own
  // scale, and unknown keys are rejected instead of writing phantom
  // overrides.
  $editEntry = kpi_by_key(
    kpi_template_for($industryForKpi),
    $kpiKey
  );

  if ($kpiKey === '' || $editEntry === null) {
    $kpiMessage = 'Select a valid KPI first.';
    $kpiMessageType = 'error';
  } else {
    $editScale = kpi_scale_for($editEntry);

    if (
      $newTarget < $editScale['min'] ||
      $newTarget > $editScale['max']
    ) {
      $kpiMessage =
        'The target score must be between ' .
        $editScale['min'] . ' and ' . $editScale['max'] . '.';
      $kpiMessageType = 'error';
    } else {
    try {
      set_kpi_target_override(
        $industryForKpi,
        $kpiKey,
        kpi_clamp_score($editEntry, $newTarget)
      );

      $kpiMessage =
        'KPI target updated.';

      $kpiMessageType = 'success';

      clear_collection_cache('Ratings');
    } catch (Throwable $e) {
      error_log(
        'Employer KPI target update failed: ' .
        $e->getMessage()
      );

      $kpiMessage =
        'The KPI target could not be updated. Please try again.';

      $kpiMessageType = 'error';
    }
    }
  }
}

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'save_kpi_row'
) {
  $kpiKey = trim(
    (string) ($_POST['kpi_key'] ?? '')
  );

  $rowTarget = (float) (
    $_POST['kpi_target'] ?? 0
  );

  $rowWeightRaw = $_POST['kpi_weight'] ?? null;

  $industryForKpi =
    trim(
      (string) (
        $_POST['industry']
        ?? $currentIndustry
      )
    );

  $rowEntry = kpi_by_key(
    kpi_template_for($industryForKpi),
    $kpiKey
  );

  if ($kpiKey === '' || $rowEntry === null) {
    $kpiMessage = 'Select a valid KPI first.';
    $kpiMessageType = 'error';
  } else {
    $rowScale = kpi_scale_for($rowEntry);

    if (
      $rowTarget < $rowScale['min'] ||
      $rowTarget > $rowScale['max']
    ) {
      $kpiMessage =
        'The target score must be between ' .
        $rowScale['min'] . ' and ' . $rowScale['max'] . '.';
      $kpiMessageType = 'error';
    } elseif (!is_numeric($rowWeightRaw) || (float) $rowWeightRaw < 0 || (float) $rowWeightRaw > 100) {
      $kpiMessage = 'Weight must be a number between 0 and 100.';
      $kpiMessageType = 'error';
    } else {
      try {
        set_kpi_target_override(
          $industryForKpi,
          $kpiKey,
          kpi_clamp_score($rowEntry, $rowTarget)
        );

        $rowWeights = kpi_weight_overrides($industryForKpi);
        $rowWeights[$kpiKey] = max(0.0, min(100.0, (float) $rowWeightRaw));
        set_kpi_weight_overrides($industryForKpi, $rowWeights);

        $kpiMessage = 'KPI updated.';
        $kpiMessageType = 'success';

        clear_collection_cache('Ratings');
      } catch (Throwable $e) {
        error_log(
          'Employer KPI row save failed: ' .
          $e->getMessage()
        );

        $kpiMessage =
          'The KPI could not be updated. Please try again.';

        $kpiMessageType = 'error';
      }
    }
  }
}

if (
  $_SERVER['REQUEST_METHOD'] === 'POST' &&
  ($_POST['action'] ?? '') === 'delete_kpi'
) {
  $kpiKey = trim(
    (string) ($_POST['kpi_key'] ?? '')
  );

  $industryForKpi =
    trim(
      (string) (
        $_POST['industry']
        ?? $currentIndustry
      )
    );

  $delEntry = kpi_by_key(
    kpi_template_for($industryForKpi),
    $kpiKey
  );

  if ($delEntry === null || !kpi_is_deletable($delEntry)) {
    $kpiMessage = 'Only custom KPIs can be deleted. Built-in template metrics are permanent.';
    $kpiMessageType = 'error';
  } else {
    try {
      $removed = delete_custom_kpi($industryForKpi, $kpiKey);

      if (!$removed) {
        throw new RuntimeException('KPI was already removed.');
      }

      $kpiMessage = 'KPI deleted.';
      $kpiMessageType = 'success';

      clear_collection_cache('Ratings');
    } catch (Throwable $e) {
      error_log(
        'Employer KPI delete failed: ' .
        $e->getMessage()
      );

      $kpiMessage =
        'The KPI could not be deleted. Please try again.';

      $kpiMessageType = 'error';
    }
  }
}

$template = kpi_template_for(
  $currentIndustry
);

/* =========================================================
   DISPLAY WEIGHTS (per-row "% weight" chips + the "Total Weight:
   100%" chip). kpi_display_weights() renormalizes the whole set to
   100%, so the total chip is always honest. Display-only: scoring,
   status classification and ML never read these.
   ========================================================= */
$displayWeights = kpi_display_weights(
  $template['kpis'],
  kpi_weight_overrides($currentIndustry)
);

$totalWeight = array_sum($displayWeights);

function kpi_weight_label(?float $weight): string
{
  if ($weight === null || !is_numeric($weight)) {
    return '-';
  }

  return rtrim(
    rtrim(
      number_format((float) $weight, 2),
      '0'
    ),
    '.'
  ) . '%';
}

/* =========================================================
   RATINGS
   ========================================================= */
$latestScores = [];
$previousScores = [];

// Hoisted out of the per-employee branch so the cohort stats block
// below can reuse the same cached collection (zero new reads).
$allRatings = get_cached_collection(
  'Ratings',
  600
);

if ($selectedEmployee) {
  $mine = [];
  $targetUid = $selectedEmployee['uid'];

  foreach ($allRatings as $rating) {
    if (
      ($rating['employeeUid'] ?? '') ===
      $targetUid
    ) {
      $mine[] = $rating;
    }
  }

  if (!empty($mine)) {
    usort(
      $mine,
      static function ($a, $b): int {
        return strcmp(
          (string) ($b['ratedAt'] ?? ''),
          (string) ($a['ratedAt'] ?? '')
        );
      }
    );

    if (isset($mine[0]['scores'])) {
      $latestScores = is_array($mine[0]['scores'])
        ? $mine[0]['scores']
        : [];
    }

    if (isset($mine[1]['scores'])) {
      $previousScores = is_array($mine[1]['scores'])
        ? $mine[1]['scores']
        : [];
    }
  }
}

/* =========================================================
   PICKER CONTEXT STRIP (selected employee at a glance; all values
   derived from already-loaded collections — zero new reads)
   ========================================================= */
$pickerScoreVals = array_values(
  array_filter(
    array_map('floatval', $latestScores),
    static function ($v): bool {
      return $v > 0;
    }
  )
);

$pickerAvg = $pickerScoreVals
  ? array_sum($pickerScoreVals) / count($pickerScoreVals)
  : null;

$pickerPeriod = max(1, (int) ($selectedEmployee['period'] ?? 180));

$pickerCreatedTime = !empty($selectedEmployee['createdAt'])
  ? strtotime($selectedEmployee['createdAt'])
  : false;

$pickerDaysLeft = $pickerCreatedTime
  ? max(
    0,
    $pickerPeriod - max(
      0,
      (int) floor(
        (time() - $pickerCreatedTime) / 86400
      )
    )
  )
  : null;

$pickerTone = $pickerDaysLeft === null
  ? ''
  : (
    $pickerDaysLeft <= 2
    ? 'bad'
    : (
      $pickerDaysLeft <= 15
      ? 'warn'
      : 'ok'
    )
  );

/* =========================================================
   KPI VIEW MODEL
   ========================================================= */
$employeeKpis = [];

foreach ($template['kpis'] as $kpi) {
  $current = isset(
    $latestScores[$kpi['key']]
  )
    ? (float) $latestScores[$kpi['key']]
    : null;

  $previous = isset(
    $previousScores[$kpi['key']]
  )
    ? (float) $previousScores[$kpi['key']]
    : null;

  $trend = 'flat';

  if (
    $current !== null &&
    $previous !== null
  ) {
    if ($current > $previous) {
      $trend = 'up';
    } elseif ($current < $previous) {
      $trend = 'down';
    }
  }

  $statusInfo =
    $current !== null
    ? kpi_status_for_score(
      $current,
      $kpi['target']
    )
    : [
      'status' => 'No Data',
      'statusClass' => 'status-neutral',
    ];

  $employeeKpis[] = [
    'key' => $kpi['key'],
    'name' => $kpi['name'],
    'category' => kpi_category_label(
      $kpi['category'] ?? ''
    ),
    'description' => trim(
      (string) ($kpi['description'] ?? '')
    ),
    'weightLabel' => kpi_weight_label(
      isset($displayWeights[$kpi['key']])
      ? (float) $displayWeights[$kpi['key']]
      : null
    ),
    'scale' => kpi_scale_for($kpi),
    'sub' =>
      'Target ' .
      number_format(
        (float) $kpi['target'],
        1
      ) .
      ' / 5.0',
    'target' => (float) $kpi['target'],
    'current' => $current,
    'hasData' => $current !== null,
    'stars' =>
      $current !== null
      ? max(
        0,
        min(
          5,
          (int) round($current)
        )
      )
      : 0,
    'trend' => $trend,
    'status' => $statusInfo['status'],
    'statusClass' =>
      $statusInfo['statusClass'],
  ];
}

/* =========================================================
   COHORT STATS (folded into calibration rows — per-KPI averages across
   probationers on the same industry template, rendered as sub-lines under
   each score meter instead of a separate table)
   ========================================================= */
$cohortIndustry = strtolower(
  trim((string) $currentIndustry)
);

$cohortMembers = array_values(
  array_filter(
    $probationaryEmployees,
    static function ($emp) use ($cohortIndustry): bool {
      return strtolower(
        trim((string) ($emp['industry'] ?? 'retail'))
      ) === $cohortIndustry;
    }
  )
);

$cohortStats = [];

foreach ($template['kpis'] as $kpi) {
  $cohortVals = [];

  foreach ($cohortMembers as $member) {
    $memberRatings = ratings_for_employee(
      $allRatings,
      (string) ($member['uid'] ?? '')
    );

    if (
      !empty($memberRatings) &&
      isset(
        $memberRatings[0]['scores'][$kpi['key']]
      ) &&
      (float) $memberRatings[0]['scores'][$kpi['key']] > 0
    ) {
      $cohortVals[] =
        (float) $memberRatings[0]['scores'][$kpi['key']];
    }
  }

  $cohortAvg = $cohortVals
    ? array_sum($cohortVals) / count($cohortVals)
    : null;

  $cohortTarget = (float) $kpi['target'];

  $cohortStats[(string) $kpi['key']] = [
    'avg' => $cohortAvg,
    'rated' => count($cohortVals),
    'delta' =>
      $cohortAvg !== null && $cohortTarget > 0
      ? ($cohortAvg - $cohortTarget) / $cohortTarget * 100
      : null,
    'statusClass' =>
      $cohortAvg !== null
      ? kpi_status_for_score(
        $cohortAvg,
        $cohortTarget
      )['statusClass']
      : 'status-neutral',
  ];
}

foreach ($employeeKpis as $index => $row) {
  $stats = $cohortStats[(string) ($row['key'] ?? '')] ?? [
    'avg' => null,
    'rated' => 0,
    'delta' => null,
    'statusClass' => 'status-neutral',
  ];

  $employeeKpis[$index]['cohortAvg'] = $stats['avg'];
  $employeeKpis[$index]['cohortRated'] = $stats['rated'];
  $employeeKpis[$index]['cohortDelta'] = $stats['delta'];
  $employeeKpis[$index]['cohortClass'] = $stats['statusClass'];
};

$employeeInitials = $selectedEmployee
  ? employer_avatar_initials($selectedEmployeeName)
  : 'P';

$industryLabel = $template['label'] ?? 'Retail';

/* =========================================================
   SCORECARD STATS CALCULATION
   ========================================================= */
$sumScoreWeight = 0;
$sumTargetWeight = 0;
$sumWeight = 0;

foreach ($template['kpis'] as $kpi) {
  $w = isset($displayWeights[$kpi['key']]) ? (float)$displayWeights[$kpi['key']] : (100 / count($template['kpis']));
  $tg = (float) $kpi['target'];
  $sumTargetWeight += $tg * $w;
  $sumWeight += $w;
  if (isset($latestScores[$kpi['key']]) && (float)$latestScores[$kpi['key']] > 0) {
    $sumScoreWeight += (float)$latestScores[$kpi['key']] * $w;
  }
}

$weightedTargetVal = $sumWeight > 0 ? $sumTargetWeight / $sumWeight : 3.0;
$latestOverallVal = ($sumWeight > 0 && !empty($latestScores)) ? $sumScoreWeight / $sumWeight : null;
$gapVal = $latestOverallVal !== null ? ($latestOverallVal - $weightedTargetVal) : null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
  <?php employer_brand_head(); ?>
  <title>KPIs | Performa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=Geist:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('KPIs'); ?>

    <main class="main kpi-page" id="kpisMain">
      <div class="cq"><div class="wrap">

        <header>
          <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
            <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
              <line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>
            </svg>
          </button>
          <div>
            <h1 id="h1">KPIs</h1>
            <p class="sub"><?php echo htmlspecialchars($industryLabel); ?> template</p>
          </div>
        </header>

        <?php if (!empty($kpiMessage)): ?>
          <div class="alert alert-<?php echo $kpiMessageType === 'success' ? 'success' : 'error'; ?>" role="status" aria-live="polite" style="margin-bottom:20px;">
            <?php echo htmlspecialchars($kpiMessage, ENT_QUOTES); ?>
          </div>
        <?php endif; ?>

        <div class="seg" id="tabs" role="tablist" aria-label="KPI views">
          <button type="button" class="tab is-active" id="tab-sc" role="tab" data-p="score" aria-selected="true" aria-checked="true">Scorecard</button>
          <button type="button" class="tab" id="tab-st" role="tab" data-p="tpl" aria-selected="false" aria-checked="false">Template settings</button>
        </div>

        <!-- Section 1: Scorecard -->
        <section id="v-sc">
          <?php if ($selectedEmployee): ?>
            <div class="emp">
              <div class="who">
                <span class="av"><?php echo htmlspecialchars($employeeInitials, ENT_QUOTES); ?></span>
                <div>
                  <b><?php echo htmlspecialchars($selectedEmployeeName, ENT_QUOTES); ?></b>
                  <span class="sl"><?php echo htmlspecialchars($industryLabel); ?> &middot; <span class="mono"><?php echo $pickerDaysLeft !== null ? (int)$pickerDaysLeft . ' days left' : 'Active'; ?></span></span>
                </div>
              </div>
              <form method="get" id="empSwitchForm" style="display:inline;margin:0;">
                <label class="sr-only" for="emp">Employee</label>
                <select name="employee" id="emp" class="sel" aria-label="Select employee" onchange="this.form.submit();">
                  <?php foreach ($probationaryEmployees as $emp): ?>
                    <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>" <?php echo $emp['uid'] === $selectedEmployeeId ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </form>
              <a href="rate_employee.php?employee=<?php echo urlencode($selectedEmployeeId); ?>" class="btn"<?php echo $selectedEmployeeId === '' ? ' hidden' : ''; ?>>Rate this employee</a>
            </div>
          <?php endif; ?>

          <div class="stats">
            <div class="stat">
              <span>Latest overall</span>
              <b id="t-o"><?php echo $latestOverallVal !== null ? number_format($latestOverallVal, 1) : '-'; ?></b><small>/ 5.0</small>
            </div>
            <div class="stat">
              <span>Weighted target</span>
              <b id="t-t"><?php echo number_format($weightedTargetVal, 1); ?></b><small>/ 5.0</small>
            </div>
            <div class="stat">
              <span>Gap to target</span>
              <b id="t-g" class="<?php echo $gapVal !== null && $gapVal < 0 ? 'bad' : ''; ?>"><?php echo $gapVal !== null ? ($gapVal > 0 ? '+' : '') . number_format($gapVal, 1) : '-'; ?></b>
            </div>
          </div>

          <div class="lh">
            <span class="eyebrow">Competencies</span>
            <div class="seg" id="sort" role="radiogroup" aria-label="Sort">
              <button type="button" class="tab is-active" role="radio" data-m="gap" aria-selected="true" aria-checked="true">Worst gap</button>
              <button type="button" class="tab" role="radio" data-m="tpl" aria-selected="false" aria-checked="false">Template order</button>
            </div>
          </div>
            <div id="list">
              <?php $kpiIdx = 0; ?>
              <?php foreach ($employeeKpis as $kpi): ?>
                <?php
                $cur = $kpi['current'];
                $tg = $kpi['target'];
                $g = $cur !== null ? $cur - $tg : null;
                $gapSort = $g !== null ? $g : 9999;
                $nearTarget = $cur !== null && $cur < $tg && ($cur / max(0.1, $tg) >= 0.85);
                $gapStr = $g !== null ? (($g > 0 ? '+' : ($g < 0 ? "\u{2212}" : '')) . number_format(abs($g), 1)) : null;
                $hasCohort = !empty($kpi['cohortAvg']) && ($kpi['cohortRated'] ?? 0) >= 5;
                ?>
                <div class="kr" data-gap="<?php echo $gapSort; ?>" data-idx="<?php echo (int) $kpiIdx++; ?>">
                  <div>
                    <b><?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?></b>
                    <span class="d"><?php echo htmlspecialchars($kpi['category'], ENT_QUOTES); ?> &middot; <?php echo htmlspecialchars($kpi['description'], ENT_QUOTES); ?></span>
                  </div>
                  <div class="sc">
                    <div class="num">
                      <b><?php echo $cur !== null ? number_format($cur, 1) : '-'; ?></b><small>/ 5.0</small>
                      <?php if ($hasCohort && $cur !== null): ?>
                        <small class="coh"><?php echo ($cur >= $kpi['cohortAvg'] ? '+' : '') . number_format($cur - $kpi['cohortAvg'], 1); ?> vs cohort <?php echo number_format($kpi['cohortAvg'], 1); ?></small>
                      <?php endif; ?>
                    </div>
                    <div class="meter" role="img" aria-label="<?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>: <?php echo $cur === null ? 'not yet rated' : ('score ' . number_format($cur, 1) . ' of 5, target ' . number_format($tg, 1)); ?>">
                      <i style="width:<?php echo $cur !== null ? min(100, max(0, ($cur / 5.0) * 100)) : 0; ?>%"></i><u style="left:<?php echo min(100, max(0, ($tg / 5.0) * 100)); ?>%;"></u>
                      <?php if ($hasCohort): ?>
                        <span class="cdot" style="left:<?php echo min(100, max(0, ($kpi['cohortAvg'] / 5.0) * 100)); ?>%;" title="Cohort average <?php echo number_format($kpi['cohortAvg'], 1); ?>"></span>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="gp">
                    <?php if ($g !== null): ?>
                      <b class="<?php echo $g >= 0 ? 'ok' : ''; ?>"><?php echo $gapStr; ?></b><small>vs <?php echo number_format($tg, 1); ?></small>
                      <?php if ($g >= 0): ?>
                        <span class="pill p-ok">On target</span>
                      <?php elseif ($nearTarget): ?>
                        <span class="pill p-warn">Near target</span>
                      <?php endif; ?>
                    <?php else: ?>
                      <b class="na">-</b><small>Target <?php echo number_format($tg, 1); ?></small>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>

          <p class="note">The tick marks the target. Cohort average appears only when the cohort has 5 or more people.</p>
        </section>

        <!-- Section 2: Template settings -->
        <section id="v-st" hidden>
          <div class="warnbar" id="wb" hidden></div>
          <section class="cd tbl">
            <div class="set rh" style="padding:10px 20px;">
              <span>KPI</span>
              <span style="text-align:right">Target</span>
              <span style="text-align:right">Weight %</span>
              <span></span>
            </div>
            <div id="slist">
              <?php foreach ($template['kpis'] as $i => $kpi): ?>
                <?php
                $w = isset($displayWeights[$kpi['key']]) ? (int)round($displayWeights[$kpi['key']]) : (int)round(100 / count($template['kpis']));
                $canDel = kpi_is_deletable($kpi);
                $rowScale = kpi_scale_for($kpi);
                ?>
                <form method="post" class="row set<?php echo ($highlightKey !== '' && $highlightKey === $kpi['key']) ? ' hl' : ''; ?>" id="kpirow-<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>">
                  <?php echo csrf_field(); ?>
                  <input type="hidden" name="industry" value="<?php echo htmlspecialchars($currentIndustry, ENT_QUOTES); ?>" />
                  <input type="hidden" name="kpi_key" value="<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>" />
                  <div class="nm">
                    <b><?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?><span class="tag"><?php echo htmlspecialchars(kpi_category_label($kpi['category'] ?? ''), ENT_QUOTES); ?></span></b>
                    <span class="sm2"><?php echo htmlspecialchars($kpi['description'] ?? '', ENT_QUOTES); ?></span>
                  </div>
                  <input type="number" name="kpi_target" step="<?php echo htmlspecialchars((string) $rowScale['step'], ENT_QUOTES); ?>" min="<?php echo htmlspecialchars((string) $rowScale['min'], ENT_QUOTES); ?>" max="<?php echo htmlspecialchars((string) $rowScale['max'], ENT_QUOTES); ?>" value="<?php echo htmlspecialchars($kpi['target'], ENT_QUOTES); ?>" aria-label="Target for <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>" required />
                  <input type="number" name="kpi_weight" data-f="w" step="1" min="0" max="100" value="<?php echo $w; ?>" aria-label="Weight for <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>" required />
                  <div class="rowbtns">
                    <button type="submit" name="action" value="save_kpi_row" class="btn sm">Save</button>
                    <?php if ($canDel): ?>
                      <button type="submit" name="action" value="delete_kpi" class="btn x" formnovalidate aria-label="Delete <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>" title="Delete custom KPI" onclick="return confirm('Delete this custom KPI? Its target and weight settings go with it.');">&times;</button>
                    <?php endif; ?>
                  </div>
                </form>
              <?php endforeach; ?>
            </div>
            <div class="ft">
              <span><span id="wt" class="mono"><?php echo (int) $totalWeight; ?>%</span> total weight. Changes apply to upcoming evaluations; generated reports keep frozen targets. Display weights renormalize to 100%.</span>
              <button type="button" class="btn" id="addKpiBtn">Add KPI</button>
            </div>
          </section>
        </section>

      </div></div>
    </main>

    <div class="kmodal" id="kpiModal"<?php echo $addKpiOpen ? ' data-open="1"' : ''; ?> hidden>
      <div class="kdialog" role="dialog" aria-modal="true" aria-labelledby="kpiModalTitle">
        <h2 id="kpiModalTitle">Add KPI</h2>
        <p class="ksub">Applies to the <?php echo htmlspecialchars($industryLabel, ENT_QUOTES); ?> template for every employee on it.</p>
        <form method="post" id="addKpiForm">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="add_kpi" />
          <input type="hidden" name="industry" value="<?php echo htmlspecialchars($currentIndustry, ENT_QUOTES); ?>" />
          <label class="kfield">KPI name
            <input name="kpi_name" type="text" required maxlength="120" placeholder="e.g. Upsell rate" />
          </label>
          <label class="kfield">Category
            <select name="kpi_category">
              <option value="quality">Quality</option>
              <option value="productivity">Productivity</option>
              <option value="customer_focus">Customer Focus</option>
              <option value="compliance">Compliance</option>
              <option value="operational_precision">Operational Precision</option>
            </select>
          </label>
          <div class="krow2">
            <label class="kfield">Target (1.0 – 5.0)
              <input name="kpi_target" type="number" min="1" max="5" step="0.1" value="4.0" required />
            </label>
            <label class="kfield">Weight %
              <input name="kpi_weight" type="number" min="0" max="100" step="1" placeholder="e.g. 25" />
            </label>
          </div>
          <label class="kfield">Scoring rubric
            <textarea name="kpi_rubric" rows="3" placeholder="What does each score level mean?"></textarea>
          </label>
          <div class="kacts">
            <button type="button" class="btn ghost" id="kpiModalCancel">Cancel</button>
            <button type="submit" class="btn">Add KPI</button>
          </div>
        </form>
      </div>
    </div>

    <div class="toast" id="toast" hidden></div>
  </div>

  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

  <script>
    (function () {
      var tabSc = document.getElementById('tab-sc');
      var tabSt = document.getElementById('tab-st');
      var vSc = document.getElementById('v-sc');
      var vSt = document.getElementById('v-st');
      var toastEl = document.getElementById('toast');
      var toastTimer;

      function toast(msg) {
        if (!toastEl) return;
        clearTimeout(toastTimer);
        toastEl.hidden = false;
        toastEl.classList.add('on');
        toastEl.textContent = msg;
        toastTimer = setTimeout(function () {
          toastEl.classList.remove('on');
          toastEl.hidden = true;
        }, 3500);
      }

      function show(t) {
        if (vSc) vSc.hidden = t !== 'sc';
        if (vSt) vSt.hidden = t !== 'st';
        if (tabSc) {
          tabSc.setAttribute('aria-selected', t === 'sc');
          tabSc.setAttribute('aria-checked', t === 'sc');
          tabSc.classList.toggle('is-active', t === 'sc');
        }
        if (tabSt) {
          tabSt.setAttribute('aria-selected', t === 'st');
          tabSt.setAttribute('aria-checked', t === 'st');
          tabSt.classList.toggle('is-active', t === 'st');
        }
      }

      if (tabSc) tabSc.onclick = function () { show('sc'); };
      if (tabSt) tabSt.onclick = function () { show('st'); };

      var sortSeg = document.getElementById('sort');
      var kpiList = document.getElementById('list');
      if (sortSeg && kpiList) {
        sortSeg.addEventListener('click', function (e) {
          var b = e.target.closest('.tab');
          if (!b) return;
          Array.from(sortSeg.querySelectorAll('.tab')).forEach(function (t) {
            var active = (t === b);
            t.setAttribute('aria-selected', active ? 'true' : 'false');
            t.setAttribute('aria-checked', active ? 'true' : 'false');
            t.classList.toggle('is-active', active);
          });
          var mode = b.getAttribute('data-m');
          var cards = Array.from(kpiList.querySelectorAll('.kr'));
          cards.sort(function (a, b2) {
            if (mode === 'gap') {
              return parseFloat(a.getAttribute('data-gap')) - parseFloat(b2.getAttribute('data-gap'));
            }
            return parseInt(a.getAttribute('data-idx'), 10) - parseInt(b2.getAttribute('data-idx'), 10);
          });
          cards.forEach(function (c) { kpiList.appendChild(c); });
        });
      }

      var slist = document.getElementById('slist');
      var wt = document.getElementById('wt');
      var wb = document.getElementById('wb');

      function updateWeights() {
        if (!slist || !wt) return;
        var inputs = Array.from(slist.querySelectorAll('input[name="kpi_weight"]'));
        var sum = 0;
        inputs.forEach(function (inp) {
          sum += parseFloat(inp.value) || 0;
        });
        wt.textContent = Math.round(sum) + '%';
        wt.style.color = Math.round(sum) === 100 ? 'var(--ok)' : 'var(--bad)';
        if (wb) {
          wb.hidden = Math.round(sum) === 100;
          if (Math.round(sum) !== 100) {
            wb.textContent = 'Weights total ' + Math.round(sum) + '%. Display weights renormalize to 100%.';
          }
        }
      }

      if (slist) {
        slist.addEventListener('input', function (e) {
          if (e.target.dataset.f === 'w' || e.target.name === 'kpi_weight') {
            updateWeights();
          }
        });
        updateWeights();
      }

      var modal = document.getElementById('kpiModal');
      var addKpiBtn = document.getElementById('addKpiBtn');
      function openModal() {
        if (!modal) return;
        modal.hidden = false;
        var f = modal.querySelector('input[name="kpi_name"]');
        if (f) f.focus();
      }
      function closeModal() {
        if (modal) modal.hidden = true;
      }
      if (addKpiBtn) addKpiBtn.addEventListener('click', openModal);
      var cancelBtn = document.getElementById('kpiModalCancel');
      if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
      if (modal) {
        modal.addEventListener('click', function (e) {
          if (e.target === modal) closeModal();
        });
        document.addEventListener('keydown', function (e) {
          if (e.key === 'Escape' && !modal.hidden) closeModal();
        });
        if (modal.getAttribute('data-open') === '1') openModal();
      }
      <?php if ($highlightKey !== ''): ?>
      (function () {
        var row = document.getElementById(<?php echo json_encode('kpirow-' . $highlightKey, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>);
        if (row && row.scrollIntoView) row.scrollIntoView({ block: 'nearest' });
      })();
      <?php endif; ?>
    })();
  </script>
</body>
</html>
