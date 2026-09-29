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

  if ($newName === '') {
    $kpiMessage = 'Enter a KPI name.';
    $kpiMessageType = 'error';
    $addKpiOpen = true;
  } elseif (!array_key_exists($industryForKpi, kpi_templates())) {
    $kpiMessage = 'The selected KPI industry is invalid.';
    $kpiMessageType = 'error';
    $addKpiOpen = true;
  } elseif ($newTarget < 1.0 || $newTarget > 5.0) {
    $kpiMessage = 'The target score must be between 1 and 5.';
    $kpiMessageType = 'error';
    $addKpiOpen = true;
  } else {
    try {
      $newSlug = add_custom_kpi(
        $industryForKpi,
        $newName,
        max(
          1.0,
          min(
            5.0,
            $newTarget
          )
        )
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

  if ($kpiKey !== '') {
    try {
      set_kpi_target_override(
        $industryForKpi,
        $kpiKey,
        max(
          1.0,
          min(
            5.0,
            $newTarget
          )
        )
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
    return '—';
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
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php employer_brand_head(); ?>
  <title>KPIs · Performa</title>
  <meta name="description" content="Define and track organization-wide performance metrics." />

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link
    href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap"
    rel="stylesheet" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
  <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
</head>

<body class="kpi-page">
  <div class="app-shell">

    <?php
    employer_render_shell('KPIs');
    ?>

    <main class="main">

      <?php ob_start(); ?>
          <span class="framework-chip">
            <span aria-hidden="true">
              <?php echo $icons['target']; ?>
            </span>
            <?php
            echo htmlspecialchars(
              $industryLabel . ' Framework',
              ENT_QUOTES
            );
            ?>
          </span>

          <button class="ghost-button" type="button" id="exportKpiBtn">
            <span aria-hidden="true">
              <?php echo $icons['download']; ?>
            </span>
            Export Report
          </button>
      <?php
      $kpiActions = ob_get_clean();
      employer_page_header(
        'kpiTitle',
        'KPIs',
        '<span class="eyebrow">Performance framework</span>',
        'Define the weekly rating framework per industry template.',
        $kpiActions,
        'kpi-page-header'
      );
      ?>

      <?php if ($kpiMessage !== ''): ?>
        <div
          class="alert <?php echo $kpiMessageType === 'success' ? 'alert-success' : ($kpiMessageType === 'error' ? 'alert-error' : 'alert-info'); ?>"
          role="status" aria-live="polite">
          <?php
          echo htmlspecialchars(
            $kpiMessage,
            ENT_QUOTES
          );
          ?>
        </div>
      <?php endif; ?>


      <section class="kpi-panel kpi-employee" aria-labelledby="employeeKpiTitle">
        <div class="kpi-panel-head">
          <div>
            <h2 id="employeeKpiTitle">Individual Employee KPI</h2>
            <p>
              Customized weights and target calibration for active probationer
            </p>
          </div>

          <div class="kpi-panel-tools">
            <label class="search-bar kpi-search" for="kpiSearch">
              <span class="sr-only">
                Search KPIs and categories
              </span>

              <span class="search-icon" aria-hidden="true">
                <?php echo $icons['search']; ?>
              </span>

              <input type="search" id="kpiSearch" aria-controls="employeeKpiList"
                placeholder="Search KPIs..." autocomplete="off" />
            </label>

            <span class="weight-total">
              Total Weight:
              <strong><?php echo htmlspecialchars(kpi_weight_label($totalWeight), ENT_QUOTES); ?></strong>
            </span>
          </div>
        </div>

        <div class="kpi-employee-bar">
          <div class="kpi-employee-id">
            <span class="kpi-avatar" aria-hidden="true"><?php echo htmlspecialchars($employeeInitials, ENT_QUOTES); ?></span>

            <div class="kpi-employee-meta">
              <strong><?php echo htmlspecialchars($selectedEmployeeName, ENT_QUOTES); ?></strong>

              <?php if ($selectedEmployee): ?>
                <span><?php echo htmlspecialchars($industryLabel . ' · ' . ($selectedEmployee['uid'] ?? ''), ENT_QUOTES); ?></span>
              <?php endif; ?>
            </div>
          </div>

          <?php if ($probationaryEmployees): ?>
            <div class="kpi-employee-pick">
              <form method="get" class="employee-select">
                <label class="sr-only" for="employeeSelect">
                  Switch employee
                </label>

                <span class="employee-select-icon" aria-hidden="true">
                  <?php echo $icons['user']; ?>
                </span>

                <select class="perform-select employee-select-control" id="employeeSelect" name="employee"
                  aria-describedby="employeeSelectHint" onchange="this.form.submit()">
                  <?php foreach ($probationaryEmployees as $emp): ?>
                    <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>" <?php echo ($selectedEmployee && $emp['uid'] === $selectedEmployee['uid']) ? 'selected' : ''; ?>>
                      <?php
                      echo htmlspecialchars(
                        $emp['name'],
                        ENT_QUOTES
                      );
                      ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </form>

              <a class="btn-primary kpi-rate-button"
                href="rate_employee.php?employee=<?php echo urlencode($selectedEmployee['uid'] ?? ''); ?>">
                Rate this employee
              </a>
            </div>

            <span id="employeeSelectHint" class="sr-only">
              Changing this selection reloads the KPI information for that employee.
            </span>

            <?php if ($selectedEmployee): ?>
              <div class="kpi-picker-meta" aria-live="polite">
                <span>
                  Template:
                  <strong><?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?></strong>
                </span>
                <span aria-hidden="true">·</span>
                <?php if ($pickerAvg !== null): ?>
                  <span>
                    Latest:
                    <strong><?php echo number_format($pickerAvg, 1); ?> / 5.0</strong>
                  </span>
                <?php else: ?>
                  <span>Not yet rated</span>
                <?php endif; ?>
                <?php if ($pickerDaysLeft !== null): ?>
                  <span aria-hidden="true">·</span>
                <span<?php echo $pickerTone !== '' ? ' class="tone-' . $pickerTone . '"' : ''; ?>>
                  <?php echo (int) $pickerDaysLeft; ?> days left
                </span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

          <?php else: ?>

            <div class="employee-select">
              <span class="employee-select-icon" aria-hidden="true">
                <?php echo $icons['user']; ?>
              </span>
              <span>No probationary employees yet</span>
            </div>

          <?php endif; ?>
        </div>

        <?php if (empty($employeeKpis)): ?>
          <div class="empty-state">
            <p>
              No KPI metrics are currently available for this template.
            </p>
          </div>
        <?php else: ?>

          <div class="kpi-rows" id="employeeKpiList">
            <?php foreach ($employeeKpis as $kpi): ?>
              <?php
              $editFormId =
                'editKpiRow_' .
                (string) $kpi['key'];
              ?>

              <article class="kpi-row" data-search="<?php echo htmlspecialchars(strtolower($kpi['name'] . ' ' . $kpi['category']), ENT_QUOTES); ?>"
                data-target="<?php echo htmlspecialchars(number_format((float) $kpi['target'], 1), ENT_QUOTES); ?>"
                data-status="<?php echo htmlspecialchars($kpi['status'], ENT_QUOTES); ?>">

                <div class="kpi-row-main" data-label="KPI Name">
                  <div class="kpi-row-title">
                    <span class="kpi-name">
                      <?php
                      echo htmlspecialchars(
                        $kpi['name'],
                        ENT_QUOTES
                      );
                      ?>
                    </span>

                    <?php if ($kpi['category'] !== ''): ?>
                      <span class="kpi-pill">
                        <?php
                        echo htmlspecialchars(
                          $kpi['category'],
                          ENT_QUOTES
                        );
                        ?>
                      </span>
                    <?php endif; ?>
                  </div>

                  <?php if ($kpi['description'] !== ''): ?>
                    <div class="kpi-desc">
                      <?php
                      echo htmlspecialchars(
                        $kpi['description'],
                        ENT_QUOTES
                      );
                      ?>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="kpi-current" data-label="Current Score">
                  <?php echo employer_score_meter($kpi['hasData'] ? (float) $kpi['current'] : null, 5.0, (float) $kpi['target']); ?>
                  <?php if ($kpi['cohortAvg'] !== null): ?>
                    <span class="kpi-cohort-line <?php echo htmlspecialchars($kpi['cohortClass'], ENT_QUOTES); ?>">
                      Cohort <strong><?php echo htmlspecialchars(number_format($kpi['cohortAvg'], 1), ENT_QUOTES); ?></strong>
                      (<?php echo htmlspecialchars(sprintf('%+.1f%%', $kpi['cohortDelta']), ENT_QUOTES); ?>, n=<?php echo (int) $kpi['cohortRated']; ?>)
                    </span>
                  <?php else: ?>
                    <span class="kpi-cohort-line status-neutral">Cohort: no ratings yet</span>
                  <?php endif; ?>
                </div>

                <div class="kpi-status" data-label="Status">
                  <?php
                  $trendGlyphs = ['up' => '↗', 'flat' => '→', 'down' => '↘'];
                  $trendLabels = ['up' => 'Improving', 'flat' => 'Steady', 'down' => 'Declining'];
                  $trendKey = $kpi['trend'] ?? 'flat';
                  $hasTrend = (bool) $kpi['hasData'] && isset($trendGlyphs[$trendKey]);
                  ?>
                  <span class="status-pill <?php echo htmlspecialchars($kpi['statusClass'], ENT_QUOTES); ?>"
                    <?php if ($hasTrend): ?>
                      title="<?php echo htmlspecialchars($trendLabels[$trendKey], ENT_QUOTES); ?>"
                    <?php endif; ?>>
                    <?php if ($hasTrend): ?>
                      <span aria-hidden="true"><?php echo $trendGlyphs[$trendKey]; ?></span>
                    <?php endif; ?>
                    <?php
                    echo htmlspecialchars(
                      $kpi['status'],
                      ENT_QUOTES
                    );
                    ?>
                    <?php if ($hasTrend): ?>
                      <span class="sr-only">(<?php echo htmlspecialchars($trendLabels[$trendKey], ENT_QUOTES); ?>)</span>
                    <?php endif; ?>
                  </span>
                </div>

                <div class="kpi-cal" data-label="Calibration">
                  <span class="kpi-target-text">
                    Target
                    <strong><?php echo htmlspecialchars(number_format((float) $kpi['target'], 1), ENT_QUOTES); ?></strong>
                  </span>

                  <span class="kpi-weight">
                    <?php
                    echo htmlspecialchars(
                      $kpi['weightLabel'],
                      ENT_QUOTES
                    );
                    ?> weight
                  </span>

                  <button class="edit-button" type="button" data-kpi-edit="<?php echo htmlspecialchars($editFormId, ENT_QUOTES); ?>"
                    aria-label="Edit target for <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>"
                    aria-controls="<?php echo htmlspecialchars($editFormId, ENT_QUOTES); ?>" aria-expanded="false">
                    <span aria-hidden="true">
                      <?php echo $icons['edit']; ?>
                    </span>
                  </button>

                  <form method="post" id="<?php echo htmlspecialchars($editFormId, ENT_QUOTES); ?>"
                    class="kpi-edit-form" aria-hidden="true">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="action" value="edit_kpi_target" />

                    <input type="hidden" name="kpi_key" value="<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>" />

                    <input type="hidden" name="industry"
                      value="<?php echo htmlspecialchars($currentIndustry, ENT_QUOTES); ?>" />

                    <label class="sr-only" for="target_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>">
                      Target score for <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>
                    </label>

                    <input id="target_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>" type="number"
                      name="kpi_target" min="1" max="5" step="0.1"
                      value="<?php echo number_format((float) $kpi['target'], 1); ?>" inputmode="decimal" />

                    <button class="btn-primary" type="submit">
                      Save
                    </button>
                  </form>
                </div>

              </article>
            <?php endforeach; ?>
          </div>

        <?php endif; ?>

          <div class="kpi-foot">
            <button type="button" class="add-kpi-button kpi-add-link" data-kpi-add-open>
              <span aria-hidden="true">
                <?php echo $icons['plus']; ?>
              </span>
              Add New KPI to Template
            </button>

            <p class="kpi-footnote">
              Target changes apply to upcoming evaluations.
            </p>
          </div>
      </section>



      <dialog class="kpi-panel kpi-add" id="addKpiDialog" aria-labelledby="addKpiTitle" data-auto-open="<?php echo $addKpiOpen ? '1' : '0'; ?>">
        <div class="kpi-add-head">
          <span class="kpi-add-icon" aria-hidden="true">
            <?php echo $icons['plus']; ?>
          </span>

          <span class="kpi-add-titles">
            <span class="kpi-add-title" id="addKpiTitle">
              Add KPI
            </span>

            <span class="kpi-add-sub">
              Create a new evaluation metric for the
              <?php
              echo htmlspecialchars(
                $template['label'],
                ENT_QUOTES
              );
              ?>
              template
            </span>
          </span>

          <button type="button" class="ghost-button kpi-dialog-close" data-kpi-add-close>Close</button>
        </div>

        <p class="kpi-add-note">
          Applies to every employee on the
          <?php
          echo htmlspecialchars(
            $template['label'],
            ENT_QUOTES
          );
          ?>
          industry template, not just
          <?php
          echo htmlspecialchars(
            $selectedEmployeeName,
            ENT_QUOTES
          );
          ?>.
        </p>

        <form method="post" class="kpi-add-form">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="add_kpi" />

          <input type="hidden" name="industry" value="<?php echo htmlspecialchars($currentIndustry, ENT_QUOTES); ?>" />

          <div class="form-group">
            <label for="kpi_name">
              KPI Name
              <span class="req" aria-hidden="true">*</span>
            </label>

            <input id="kpi_name" name="kpi_name" type="text" required maxlength="120" autocomplete="off"
              placeholder="e.g. Omnichannel Response Time" />
          </div>

          <div class="form-group">
            <label for="kpi_category">
              Category
            </label>

            <select id="kpi_category" name="kpi_category">
              <option value="quality" selected>Quality</option>
              <option value="productivity">Productivity</option>
              <option value="customer_focus">Customer Focus</option>
              <option value="compliance">Compliance</option>
              <option value="operational_precision">Operational Precision</option>
            </select>
          </div>

          <div class="kpi-add-duo">
            <div class="form-group">
              <label for="kpi_target">
                Target (1.0 - 5.0)
              </label>

              <input id="kpi_target" name="kpi_target" type="number" min="1" max="5" step="0.1" value="4.0"
                inputmode="decimal" required />
            </div>

            <div class="form-group">
              <label for="kpi_weight">
                Default Weight (%)
              </label>

              <input id="kpi_weight" name="kpi_weight" type="number" min="0" max="100" step="1" value="20"
                inputmode="numeric" />
            </div>
          </div>

          <div class="form-group">
            <div class="kpi-label-row">
              <label for="kpi_rubric">
                Scoring Rubric
              </label>
              <span class="kpi-optional">Optional</span>
            </div>

            <textarea id="kpi_rubric" name="kpi_rubric" rows="3" maxlength="2000"
              placeholder="Define scoring criteria for 1.0 (unsatisfactory) through 5.0 (exceptional)..."></textarea>
          </div>

          <button class="btn-primary kpi-add-submit" type="submit">
            <span aria-hidden="true">
              <?php echo $icons['plus']; ?>
            </span>
            Add KPI
          </button>
        </form>
      </dialog>

    </main>
  </div>

  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
  <script src="<?php echo htmlspecialchars(employer_asset('kpis.js'), ENT_QUOTES); ?>"></script>
</body>

</html>