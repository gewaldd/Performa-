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
  <title>KPIs | Performa</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body>
  <div class="app-shell">
    <?php employer_render_shell('KPIs'); ?>

    <main class="main kpi-page" id="kpisMain">
      <div class="in">

        <div class="top">
          <div>
            <h1 id="h1">KPIs</h1>
            <p class="sub"><?php echo htmlspecialchars($industryLabel); ?> template</p>
          </div>
        </div>

        <?php if (!empty($kpiMessage)): ?>
          <div class="alert alert-<?php echo $kpiMessageType === 'success' ? 'success' : 'error'; ?>" role="status" aria-live="polite" style="margin-bottom:20px;">
            <?php echo htmlspecialchars($kpiMessage, ENT_QUOTES); ?>
          </div>
        <?php endif; ?>

        <div class="tb">
          <div class="seg" role="tablist">
            <button type="button" id="tab-sc" role="tab" aria-selected="true">Scorecard</button>
            <button type="button" id="tab-st" role="tab" aria-selected="false">Template settings</button>
          </div>
        </div>

        <!-- Section 1: Scorecard -->
        <section id="v-sc">
          <?php if ($selectedEmployee): ?>
            <div class="emp">
              <span class="av"><?php echo htmlspecialchars($employeeInitials, ENT_QUOTES); ?></span>
              <div class="grow">
                <b><?php echo htmlspecialchars($selectedEmployeeName, ENT_QUOTES); ?></b>
                <span class="sm2"><?php echo htmlspecialchars($industryLabel); ?> · <?php echo $pickerDaysLeft !== null ? (int)$pickerDaysLeft . ' days left' : 'Active'; ?></span>
              </div>
              <form method="get" id="empSwitchForm" style="display:inline;margin:0;">
                <select name="employee" aria-label="Select employee" onchange="this.form.submit();">
                  <?php foreach ($probationaryEmployees as $emp): ?>
                    <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>" <?php echo $emp['uid'] === $selectedEmployeeId ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </form>
              <a href="rate_employee.php?employee=<?php echo urlencode($selectedEmployeeId); ?>" class="btn primary" style="text-decoration:none;">Rate this employee</a>
            </div>
          <?php endif; ?>

          <div class="stats">
            <div class="st">
              <div class="l">Latest overall</div>
              <div class="v num" id="t-o">
                <?php echo $latestOverallVal !== null ? number_format($latestOverallVal, 1) : '-'; ?><small>/ 5.0</small>
              </div>
            </div>
            <div class="st">
              <div class="l">Weighted target</div>
              <div class="v num" id="t-t">
                <?php echo number_format($weightedTargetVal, 1); ?><small>/ 5.0</small>
              </div>
            </div>
            <div class="st">
              <div class="l">Gap to target</div>
              <div class="v num <?php echo $gapVal !== null ? ($gapVal >= 0 ? 'ok' : 'bad') : ''; ?>" id="t-g" style="<?php echo $gapVal !== null ? ($gapVal >= 0 ? 'color:var(--ok);' : 'color:var(--bad);') : ''; ?>">
                <?php echo $gapVal !== null ? ($gapVal > 0 ? '+' : '') . number_format($gapVal, 1) : '-'; ?>
              </div>
            </div>
          </div>

          <section class="cd tbl">
            <div id="list">
              <?php foreach ($employeeKpis as $kpi): ?>
                <?php
                $cur = $kpi['current'];
                $tg = $kpi['target'];
                $g = $cur !== null ? $cur - $tg : null;
                $col = $cur !== null ? ($cur >= $tg ? 'var(--ok)' : ($cur / max(0.1, $tg) >= 0.85 ? 'var(--amber)' : 'var(--bad)')) : 'var(--mut)';
                $hasCohort = !empty($kpi['cohortAvg']) && ($kpi['cohortRated'] ?? 0) >= 5;
                ?>
                <div class="row kpi">
                  <div>
                    <h3>
                      <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>
                      <span class="tag"><?php echo htmlspecialchars($kpi['category'], ENT_QUOTES); ?></span>
                    </h3>
                    <p><?php echo htmlspecialchars($kpi['description'], ENT_QUOTES); ?></p>
                  </div>
                  <div class="bc">
                    <div class="scr">
                      <b class="num"><?php echo $cur !== null ? number_format($cur, 1) : '-'; ?></b>
                      <span class="num sm2">/ 5.0</span>
                      <?php if ($hasCohort && $cur !== null): ?>
                        <span class="num sm2 r">
                          <?php echo ($cur >= $kpi['cohortAvg'] ? '+' : '') . number_format($cur - $kpi['cohortAvg'], 1); ?> vs cohort <?php echo number_format($kpi['cohortAvg'], 1); ?>
                        </span>
                      <?php endif; ?>
                    </div>
                    <div class="track">
                      <div class="fill" style="width:<?php echo $cur !== null ? min(100, max(0, ($cur / 5.0) * 100)) : 0; ?>%;background:<?php echo $col; ?>;"></div>
                      <div class="tick" style="left:<?php echo min(100, max(0, ($tg / 5.0) * 100)); ?>%;" title="Target <?php echo number_format($tg, 1); ?>"></div>
                      <?php if ($hasCohort): ?>
                        <div class="dot" style="left:<?php echo min(100, max(0, ($kpi['cohortAvg'] / 5.0) * 100)); ?>%;position:absolute;top:-3px;width:10px;height:10px;border-radius:50%;background:var(--mut);" title="Cohort average <?php echo number_format($kpi['cohortAvg'], 1); ?>"></div>
                      <?php endif; ?>
                    </div>
                  </div>
                  <div class="gp">
                    <?php if ($cur !== null): ?>
                      <div class="num" style="color:<?php echo $col; ?>;">
                        <?php echo ($g > 0 ? '+' : '') . number_format($g, 1); ?>
                      </div>
                      <div class="num sm2" style="font-size:11px;">vs <?php echo number_format($tg, 1); ?></div>
                      <?php if ($g >= 0): ?>
                        <div><span class="pill p-ok">On target</span></div>
                      <?php elseif ($cur / max(0.1, $tg) >= 0.85): ?>
                        <div><span class="pill p-warn">Near target</span></div>
                      <?php endif; ?>
                    <?php else: ?>
                      <div class="num sm2">Unrated</div>
                      <div class="num sm2" style="font-size:11px;">Target <?php echo number_format($tg, 1); ?></div>
                    <?php endif; ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </section>

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
                ?>
                <div class="row set">
                  <div class="nm">
                    <b><?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?><span class="tag"><?php echo htmlspecialchars(kpi_category_label($kpi['category'] ?? ''), ENT_QUOTES); ?></span></b>
                    <span class="sm2"><?php echo htmlspecialchars($kpi['description'] ?? '', ENT_QUOTES); ?></span>
                  </div>
                  <input type="number" step="0.1" min="0" max="5" value="<?php echo htmlspecialchars($kpi['target'], ENT_QUOTES); ?>" data-i="<?php echo $i; ?>" data-f="tg" aria-label="Target for <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>">
                  <input type="number" step="1" min="0" max="100" value="<?php echo $w; ?>" data-i="<?php echo $i; ?>" data-f="w" aria-label="Weight for <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>">
                  <button type="button" class="btn x" aria-label="Delete <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>" title="Delete" data-del="<?php echo $i; ?>">&times;</button>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="ft" style="padding:14px 20px;">
              <button type="button" class="btn txt" id="addKpiBtn">+ Add new KPI to template</button>
              <span><span id="wt" class="num"><?php echo (int) $totalWeight; ?>%</span> total weight. Changes apply to upcoming evaluations; generated reports keep frozen targets.</span>
            </div>
          </section>
        </section>

      </div>
    </main>

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
        toastEl.textContent = msg;
        toastTimer = setTimeout(function () { toastEl.hidden = true; }, 3500);
      }

      function show(t) {
        if (vSc) vSc.hidden = t !== 'sc';
        if (vSt) vSt.hidden = t !== 'st';
        if (tabSc) tabSc.setAttribute('aria-selected', t === 'sc');
        if (tabSt) tabSt.setAttribute('aria-selected', t === 'st');
      }

      if (tabSc) tabSc.onclick = function () { show('sc'); };
      if (tabSt) tabSt.onclick = function () { show('st'); };

      var slist = document.getElementById('slist');
      var wt = document.getElementById('wt');
      var wb = document.getElementById('wb');

      function updateWeights() {
        if (!slist || !wt) return;
        var inputs = Array.from(slist.querySelectorAll('input[data-f="w"]'));
        var sum = 0;
        inputs.forEach(function (inp) {
          sum += parseFloat(inp.value) || 0;
        });
        wt.textContent = Math.round(sum) + '%';
        wt.style.color = Math.round(sum) === 100 ? 'var(--ok)' : 'var(--bad)';
        if (wb) {
          wb.hidden = Math.round(sum) === 100;
          if (Math.round(sum) !== 100) {
            wb.textContent = 'Weights total ' + Math.round(sum) + '%. They must add up to 100% before saving.';
          }
        }
      }

      if (slist) {
        slist.addEventListener('input', function (e) {
          if (e.target.dataset.f === 'w') {
            updateWeights();
          }
        });
        slist.addEventListener('click', function (e) {
          var b = e.target.closest('[data-del]');
          if (!b) return;
          var row = b.closest('.row.set');
          if (row) {
            row.remove();
            updateWeights();
            toast('KPI removed from template preview');
          }
        });
      }

      var addKpiBtn = document.getElementById('addKpiBtn');
      if (addKpiBtn) {
        addKpiBtn.addEventListener('click', function () {
          toast('Use Settings > KPI Catalog to author new custom metrics');
        });
      }
    })();
  </script>
</body>
</html>
