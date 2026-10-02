<?php
$rootDir = __DIR__ . '/..';
require_once $rootDir . '/auth.php';
require_once $rootDir . '/firebase_init.php';
require_once $rootDir . '/kpi_templates.php';
require_once __DIR__ . '/includes/csrf.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';

require_login();
require_role('employer');
// Ownership helpers + forced-reset gate (same as every other Employer page).
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/collection_cache.php';
require_once __DIR__ . '/includes/roles.php';

if (session_status() === PHP_SESSION_NONE) {
  session_start();
}
if (empty($_SESSION['uid'])) {
  header('Location: ../login.php');
  exit;
}

require_once $rootDir . '/backend/ml_bridge.php';

/**
 * Invokes the Python ML Pipeline via shared bridge (manuscript: RF -> Gemini).
 * Preserves raw KPI-key scores passthrough; Python KEY_ALIASES normalizes to
 * canonical competencies. Human-in-loop pending_approval handled by caller.
 */
function run_ml_recommendation(string $empUid, string $evalMonth, array $scores, string $jobRole, string $industry): array {
  $scriptPath = realpath(__DIR__ . "/../ml/gemini_recommender.py");

  if (!$scriptPath || !file_exists($scriptPath)) {
    return [
      "status" => "error",
      "message" => "ML script not found at expected location."
    ];
  }

  // Raw KPI keys (e.g. food_safety, service_speed) pass through untouched;
  // ml/predict.py KEY_ALIASES maps them to canonical competencies.
  $payload = [
    "employee_uid" => $empUid,
    "evaluation_month" => $evalMonth,
    "job_role" => $jobRole,
    "industry" => $industry,
    "category_scores" => $scores
  ];

  $res = ml_invoke_stdin($scriptPath, $payload, ML_INVOKE_TIMEOUT_SECS);

  if (!$res['ok']) {
    return [
      "status" => "error",
      "message" => "Failed to execute Python ML process."
    ];
  }

  if ($res['timedOut']) {
    error_log("ML recommendation timed out for employee {$empUid}");
    return [
      "status" => "error",
      "message" => "Failed to execute Python ML process."
    ];
  }

  if (!empty($res['stderr'])) {
    error_log("Python Execution Warning/Error: " . substr($res['stderr'], 0, 2000));
  }

  $cleanOutput = preg_replace('/[\x00-\x1F\x7F\xEF\xBB\xBF]/', '', trim($res['output']));
  $decoded = json_decode($cleanOutput, true);

  if (json_last_error() === JSON_ERROR_NONE && !empty($decoded) && isset($decoded['summary'])) {
    ml_audit('ml_recommendation', $empUid, [
      'month' => $evalMonth,
      'industry' => $industry,
      'model' => $decoded['model_version'] ?? 'unknown',
      'exit' => $res['exit'],
    ]);
    return [
      "status" => "success",
      "data" => $decoded
    ];
  }

  return [
    "status" => "error",
    "message" => "Failed to execute Python ML process."
  ];
}

// Load probationary employees for the picker
$employees = [];
try {
  $docs = firestore_list_documents('Users');
  foreach ($docs as $doc) {
    if (normalize_role_key($doc['role'] ?? null) === 'probationary') {
      $employees[] = [
        'uid' => $doc['uid'] ?? '',
        'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
        'industry' => $doc['industry'] ?? 'retail',
        'jobRole' => $doc['jobRole'] ?? 'Probationary Employee',
        'createdBy' => $doc['createdBy'] ?? null,
        'managedByOrg' => $doc['managedByOrg'] ?? null,
      ];
    }
  }
} catch (Throwable $e) {
  // leave $employees empty
}

$selectedUid = $_GET['employee'] ?? ($_POST['employee'] ?? '');
$selectedEmployee = null;
foreach ($employees as $emp) {
  if ($emp['uid'] === $selectedUid) {
    $selectedEmployee = $emp;
    break;
  }
}
if (!$selectedEmployee && $employees) {
  $selectedEmployee = $employees[0];
  $selectedUid = $selectedEmployee['uid'];
}

$template = $selectedEmployee ? kpi_template_for($selectedEmployee['industry']) : kpi_template_for('retail');

/*
 * Read-only history reference (UX #3): the selected employee's latest
 * rating on file, shown per KPI under each slider.
 */
$prevScores = [];

if ($selectedEmployee) {
  try {
    $historyRatings = get_cached_collection('Ratings', 600);
    $historyMine = ratings_for_employee($historyRatings, $selectedUid);
    if (!empty($historyMine[0]['scores']) && is_array($historyMine[0]['scores'])) {
      $prevScores = $historyMine[0]['scores'];
    }
  } catch (\Throwable $e) {
    $prevScores = [];
  }
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedEmployee) {
  require_employer_owns_user($selectedEmployee, 'rate_employee:save_rating');
  $weekOf = date('Y-\WW');
  $evalMonth = date('Y-m');
  $scores = [];
  foreach ($template['kpis'] as $kpi) {
    $raw = $_POST['score_' . $kpi['key']] ?? null;
    // Server-side clamp into the KPI's own scale (browser min/max is not
    // a trust boundary); missing inputs stay 0.0 = unrated, as before.
    $scores[$kpi['key']] = $raw !== null ? kpi_clamp_score($kpi, $raw) : 0.0;
  }

  // Execute ML Pipeline
  $jobRole = $selectedEmployee['jobRole'] ?? 'Probationary Employee';
  $industry = $selectedEmployee['industry'] ?? 'retail';
  $mlResponse = run_ml_recommendation($selectedUid, $evalMonth, $scores, $jobRole, $industry);

  // Fallback payload structure if Python script execution fails
  $aiRecommendationsData = [
    'summary' => 'Performance evaluation saved. AI recommendation service unavailable at this time.',
    'training_recommendations' => [],
    'generated_by' => 'fallback',
    'status' => 'pending_approval'
  ];

  if ($mlResponse['status'] === 'success' && isset($mlResponse['data']) && is_array($mlResponse['data'])) {
    $aiData = $mlResponse['data'];
    $aiRecommendationsData = [
      'summary' => $aiData['summary'] ?? 'Evaluation recorded successfully.',
      'training_recommendations' => $aiData['training_recommendations'] ?? [],
      'generated_by' => $aiData['generated_by'] ?? 'gemini_api',
      'status' => 'pending_approval', // Human-in-the-Loop constraint
      // Audit trail (manuscript P.502-504): RF provenance + backend error cause.
      // Existing readers use only the keys above; extra keys are additive.
      'model_version' => $aiData['model_version'] ?? 'unknown',
      'overall_prediction' => $aiData['overall_prediction']['classification'] ?? ($aiData['overall_prediction'] ?? 'unknown'),
      'ml_error' => $aiData['error'] ?? null,
    ];
    if (in_array($aiRecommendationsData['generated_by'] ?? '', ['fallback', 'rf_only'], true) && !empty($aiRecommendationsData['ml_error'])) {
      error_log('ML fallback reason: ' . substr((string) $aiRecommendationsData['ml_error'], 0, 500));
    }
  }

  $docId = $selectedUid . '_' . date('Y-m-d');
  try {
    // Single atomic commit: rating + ML recommendations + employer feedback.
    // Acknowledgements + notifications are NOT created here: per the
    // manuscript nothing reaches the employee until the employer approves
    // (review gate), which authors them — see review_recommendations.php.
    firestore_batch_write([
      [
        'collection' => 'Ratings',
        'documentId' => $docId,
        'data' => [
          'employeeUid' => $selectedUid,
          'employeeName' => $selectedEmployee['name'],
          'industry' => $selectedEmployee['industry'],
          'weekOf' => $weekOf,
          'ratedAt' => date('c'),
          'ratedBy' => $_SESSION['uid'],
          'scores' => $scores,
          'aiRecommendations' => $aiRecommendationsData
        ],
      ],
      [
        'collection' => 'Feedback',
        'documentId' => $selectedUid . '_' . date('Y-m') . '_employer',
        'data' => [
          'employeeUid' => $selectedUid,
          'sender' => $_SESSION['name'] ?? 'Employer',
          'role' => 'Employer',
          'message' => 'Your ' . date('F Y') . ' KPI rating has been submitted. Review your performance summary and acknowledgement.',
          'status' => 'Received',
          'createdAt' => date('c'),
        ],
      ],
    ]);
    
    if ($aiRecommendationsData && ($aiRecommendationsData['status'] ?? '') === 'pending_approval') {
      $message = 'Rating & AI Recommendations saved for ' . htmlspecialchars($selectedEmployee['name']) . ' (Pending Manager Approval). <a href="review_recommendations.php">Review AI plan</a>';
    } else {
      $message = 'Rating saved for ' . htmlspecialchars($selectedEmployee['name']) . '.';
    }
  } catch (\Throwable $e) {
    $message = 'Failed to save rating: ' . $e->getMessage();
  }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php employer_brand_head(); ?>
  <meta name="description" content="Score this week's KPIs for a probationary employee." />
  <title>Rate Employee · Performa</title>
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
    <main class="main content-narrow pf-rate-page">
      <?php
      employer_page_header(
        'rateTitle',
        'Weekly Performance Rating',
        '<a href="kpis.php" class="ghost-button back-link"><svg class="i"><use href="#i-back"/></svg>KPIs</a>'
          . '<nav class="ph-crumb" aria-label="Breadcrumb"><span>KPIs</span>'
          . '<span aria-hidden="true">/</span><span>Rate</span></nav>',
        'Score this week\'s KPIs for a probationary employee. Use the slider or type a value from 1.0 to 5.0.',
        ''
      );
      ?>

      <div class="settings-panel pf-rate-panel">
        <?php if ($message): ?>
          <div class="alert alert-info" role="status"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if (!$employees): ?>
          <div class="empty-state">
            <p>No probationary employees yet.</p>
            <a class="btn-primary" href="add_employee.php">Add Employee</a>
          </div>
        <?php else: ?>
          <form method="get" class="form-grid single-field-grid" style="margin-bottom:8px;">
            <div class="form-group">
              <label for="employee">Employee</label>
              <select id="employee" class="perform-select" name="employee" onchange="window.pfRatePickEmployee ? pfRatePickEmployee(this) : this.form.submit()">
                <?php foreach ($employees as $emp): ?>
                  <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>" <?php echo $emp['uid'] === $selectedUid ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </form>

          <hr class="section-divider" />

          <form method="post" id="rateForm">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selectedUid, ENT_QUOTES); ?>" />
            <p class="microcopy" style="margin:0 0 12px;">Industry template: <strong><?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?></strong></p>
            <div class="pf-rate-preview" id="ratePreview" aria-live="polite"></div>
            <p class="microcopy" style="margin:0 0 12px;">Scores start at 3.0 — adjust each KPI before saving.</p>
            <div class="pf-rate-grid">
              <?php foreach ($template['kpis'] as $kpi): ?>
                <?php
                $rateTarget = (float) $kpi['target'];
                // Scale-driven widget: defaults render identically to the old
                // hardcoded 1/5/3.0 math (midpoint of 1-5 is 3.0); non-1-5
                // scales re-render min/max/step/fill automatically.
                $rateScale = kpi_scale_for($kpi);
                $rateDefault = ($rateScale['min'] + $rateScale['max']) / 2;
                $rateFill = $rateScale['max'] > $rateScale['min']
                  ? max(0, min(100, (($rateDefault - $rateScale['min']) / ($rateScale['max'] - $rateScale['min'])) * 100))
                  : 0;
                $rateStatus = kpi_status_for_scale($rateDefault, $rateTarget, $kpi);
                ?>
                <div class="pf-rate-row" data-rate-row>
                  <div class="pf-rate-head">
                    <label for="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>">
                      <span class="pf-rate-name"><?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?></span>
                      <span class="pf-rate-meta microcopy">target <?php echo number_format($rateTarget, 1); ?> · <?php echo isset($prevScores[$kpi['key']]) ? 'Last week: ' . number_format((float) $prevScores[$kpi['key']], 1) : 'No prior rating'; ?></span>
                    </label>
                    <span class="status-pill pf-rate-pill <?php echo htmlspecialchars($rateStatus['statusClass'], ENT_QUOTES); ?>" data-rate-pill><?php echo htmlspecialchars($rateStatus['status'], ENT_QUOTES); ?></span>
                  </div>
                  <div class="pf-rate-controls">
                    <input type="range" min="<?php echo htmlspecialchars((string) $rateScale['min'], ENT_QUOTES); ?>" max="<?php echo htmlspecialchars((string) $rateScale['max'], ENT_QUOTES); ?>" step="<?php echo htmlspecialchars((string) $rateScale['step'], ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string) $rateDefault, ENT_QUOTES); ?>" data-rate-slider="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>"
                      style="--pf-fill: <?php echo number_format($rateFill, 1); ?>%;"
                      aria-label="<?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?> slider" />
                    <input id="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>" name="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>" class="pf-rate-value" type="number" min="<?php echo htmlspecialchars((string) $rateScale['min'], ENT_QUOTES); ?>"
                      max="<?php echo htmlspecialchars((string) $rateScale['max'], ENT_QUOTES); ?>" step="<?php echo htmlspecialchars((string) $rateScale['step'], ENT_QUOTES); ?>" value="<?php echo htmlspecialchars((string) $rateDefault, ENT_QUOTES); ?>" required />
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
            <div class="form-actions">
              <a class="ghost-button" id="rateCancel" href="kpis.php">Cancel</a>
              <button class="btn-primary" type="submit">Save Rating & Generate AI Plan</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </main>
  </div>
  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
  <script>
    window.__pfRateTargets = <?php
      $rateTargets = [];
      $rateScales = [];
      foreach ($template['kpis'] as $rateKpi) {
        $rateTargets[$rateKpi['key']] = (float) $rateKpi['target'];
        $rateScales[$rateKpi['key']] = kpi_scale_for($rateKpi);
      }
      echo json_encode(
        $rateTargets,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
      ) ?: '{}';
    ?>;
    // Per-KPI scales for generic clamping/fills/bands below. Defaults keep
    // every formula below identical to the old hardcoded 1/5 math.
    window.__pfRateScales = <?php
      echo json_encode(
        $rateScales,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
      ) ?: '{}';
    ?>;
  </script>
  <script>
    // Scale helpers shared by every block below. With default 1-5 scales
    // each formula reproduces the old hardcoded math exactly.
    function rateScaleFor(key) {
      var s = (window.__pfRateScales || {})[key];
      if (!s || isNaN(parseFloat(s.min)) || isNaN(parseFloat(s.max)) || !(parseFloat(s.max) > parseFloat(s.min))) {
        return { min: 1, max: 5 };
      }
      return { min: parseFloat(s.min), max: parseFloat(s.max) };
    }
    function rateClamp(key, v) {
      var s = rateScaleFor(key);
      return Math.max(s.min, Math.min(s.max, v));
    }
    function rateFillPct(key, v) {
      var s = rateScaleFor(key);
      if (!(s.max > s.min)) return 0;
      return Math.max(0, Math.min(100, ((v - s.min) / (s.max - s.min)) * 100));
    }
    window.__pfRateScaleFor = rateScaleFor;
    window.__pfRateClamp = rateClamp;
    window.__pfRateFillPct = rateFillPct;
    document.querySelectorAll('[data-rate-slider]').forEach(function (slider) {
      var target = document.getElementById(slider.getAttribute('data-rate-slider'));
      if (!target) return;
      slider.addEventListener('input', function () { target.value = slider.value; });
      target.addEventListener('input', function () {
        var v = parseFloat(target.value);
        if (!isNaN(v)) slider.value = window.__pfRateClamp(slider.getAttribute('data-rate-slider').replace(/^score_/, ''), v);
      });
    });

    // Preview strip painter: average / below-target / adjusted counts, plus the
    // below-target confirm dialog on submit. Declared before the chrome pass
    // further down: paint() resets the strip with innerHTML = '', so the tone
    // dot has to be inserted by that later pass or a repaint would wipe it.
    (function () {
      var form = document.getElementById('rateForm');
      var preview = document.getElementById('ratePreview');
      if (!form || !preview) return;
      var targets = window.__pfRateTargets || {};

      // Unsaved-changes baseline: each KPI's score as rendered on load. The
      // progress counter and the dirty guard both compare against these.
      var baseline = {};
      form.querySelectorAll('input[name^="score_"]').forEach(function (input) {
        var v = parseFloat(input.value);
        var key = input.name.replace(/^score_/, '');
        if (isNaN(v)) {
          var sc = window.__pfRateScaleFor(key);
          v = (sc.min + sc.max) / 2;
        }
        baseline[key] = v;
      });

      function readScores() {
        var vals = [];
        form.querySelectorAll('input[name^="score_"]').forEach(function (input) {
          var v = parseFloat(input.value);
          if (isNaN(v)) return;
          var key = input.name.replace(/^score_/, '');
          vals.push({ key: key, value: window.__pfRateClamp(key, v) });
        });
        return vals;
      }

      function summarize() {
        var vals = readScores();
        if (!vals.length) return { avg: null, below: 0, total: 0, adjusted: 0 };
        var sum = 0, below = 0, adjusted = 0;
        vals.forEach(function (s) {
          sum += s.value;
          var t = parseFloat(targets[s.key]);
          if (!isNaN(t) && s.value < t) below++;
          var b = baseline[s.key];
          if (typeof b === 'number' && Math.abs(s.value - b) > 1e-9) adjusted++;
        });
        return { avg: sum / vals.length, below: below, total: vals.length, adjusted: adjusted };
      }

      // The unsaved-changes guard below reads its counts through this hook.
      window.__pfRateSummarize = summarize;

      function paint() {
        var s = summarize();
        if (s.avg === null) { preview.textContent = ''; return; }
        preview.innerHTML = '';
        preview.append('Average ');
        var avgSpan = document.createElement('span');
        avgSpan.style.whiteSpace = 'nowrap';
        avgSpan.textContent = s.avg.toFixed(1) + ' / 5.0';
        preview.append(avgSpan);
        if (s.below > 0) {
          preview.append(' · ');
          var belowSpan = document.createElement('span');
          belowSpan.style.whiteSpace = 'nowrap';
          belowSpan.textContent = s.below + ' of ' + s.total + ' below target';
          preview.append(belowSpan);
        } else {
          preview.append(' · all at or above target');
        }
        preview.append(' · ');
        var progressSpan = document.createElement('span');
        progressSpan.className = 'pf-rate-progress';
        progressSpan.textContent = s.adjusted + ' of ' + s.total + ' adjusted';
        preview.append(progressSpan);
      }

      form.addEventListener('input', paint);
      paint();

      form.addEventListener('submit', function (event) {
        if (form.dataset.ratedConfirmed === 'true') return;
        var s = summarize();
        if (s.below <= 0) return;
        event.preventDefault();

        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        var dialog = document.createElement('div');
        dialog.className = 'confirm-dialog';
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-label', 'Confirm below-target rating');
        var heading = document.createElement('h2');
        heading.textContent = 'Save anyway?';
        var message = document.createElement('p');
        message.textContent = s.below + ' of ' + s.total + ' scores are below target (average '
          + s.avg.toFixed(1) + '). This writes the weekly rating immediately.';
        var actions = document.createElement('div');
        actions.className = 'confirm-dialog-actions';
        var reviewButton = document.createElement('button');
        reviewButton.type = 'button';
        reviewButton.className = 'ghost-button';
        reviewButton.textContent = 'Review scores';
        var saveButton = document.createElement('button');
        saveButton.type = 'button';
        saveButton.className = 'btn-primary';
        saveButton.textContent = 'Save anyway';
        actions.append(reviewButton, saveButton);
        dialog.append(heading, message, actions);
        backdrop.append(dialog);
        document.body.append(backdrop);

        var closeDialog = function () {
          backdrop.remove();
          document.removeEventListener('keydown', handleKeydown);
          saveButton.focus({ preventScroll: true });
        };
        var handleKeydown = function (keyEvent) {
          if (keyEvent.key === 'Escape') { closeDialog(); reviewButton.focus(); }
        };
        reviewButton.addEventListener('click', function () { backdrop.remove(); document.removeEventListener('keydown', handleKeydown); });
        saveButton.addEventListener('click', function () {
          form.dataset.ratedConfirmed = 'true';
          backdrop.remove();
          document.removeEventListener('keydown', handleKeydown);
          HTMLFormElement.prototype.submit.call(form);
        });
        document.addEventListener('keydown', handleKeydown);
        saveButton.focus();
      });
    })();
    // Live card chrome: slider fill, per-card status pill, preview tone.
    // Visual only -- mirrors kpi_status_for_scale() (target / 20%-of-scale
    // warning band; identical to target-0.8 on default scales).
    // Runs after the preview painter above: paint() resets the strip with
    // innerHTML = '', so the tone dot is (re)inserted here, never before.
    (function () {
      var form = document.getElementById('rateForm');
      var preview = document.getElementById('ratePreview');
      if (!form) return;
      var targets = window.__pfRateTargets || {};
      function statusFor(v, t, key) {
        if (isNaN(t)) return { text: '', cls: '' };
        if (v >= t) return { text: 'Exceeding', cls: 'status-good' };
        var s = window.__pfRateScaleFor(key);
        if (v >= t - 0.2 * (s.max - s.min)) return { text: 'Warning', cls: 'status-warning' };
        return { text: 'Below Target', cls: 'status-danger' };
      }
      function refresh() {
        var below = 0, total = 0;
        form.querySelectorAll('[data-rate-row]').forEach(function (row) {
          var num = row.querySelector('input[name^="score_"]');
          var slider = row.querySelector('[data-rate-slider]');
          var pill = row.querySelector('[data-rate-pill]');
          if (!num) return;
          var v = parseFloat(num.value);
          if (isNaN(v)) return;
          var key = num.name.replace(/^score_/, '');
          v = window.__pfRateClamp(key, v);
          var t = parseFloat(targets[key]);
          total++;
          if (!isNaN(t) && v < t) below++;
          if (slider) slider.style.setProperty('--pf-fill', (window.__pfRateFillPct(key, v)).toFixed(1) + '%');
          if (pill && !isNaN(t)) {
            var st = statusFor(v, t, key);
            pill.textContent = st.text;
            pill.classList.remove('status-good', 'status-warning', 'status-danger');
            if (st.cls) pill.classList.add(st.cls);
            row.setAttribute('data-status', st.cls || 'none');
          }
        });
        if (preview) {
          preview.removeAttribute('data-tone');
          var dot = preview.querySelector(':scope > .pf-rate-dot');
          if (total > 0) {
            preview.setAttribute('data-tone', below <= 0 ? 'ok' : (below < total ? 'warn' : 'bad'));
            if (!dot) {
              dot = document.createElement('span');
              dot.className = 'pf-rate-dot';
              dot.setAttribute('aria-hidden', 'true');
            }
            preview.insertBefore(dot, preview.firstChild);
          } else if (dot) {
            dot.remove();
          }
        }
      }
      form.addEventListener('input', refresh);
      refresh();
    })();

    // Unsaved-changes guard. Exits we own (Cancel link, employee picker) get
    // the in-app discard dialog; every other exit (sidebar, browser back or
    // refresh) gets the native beforeunload prompt. A genuine save bypasses
    // both, so a successful submit never nags.
    (function () {
      var form = document.getElementById('rateForm');
      if (!form || typeof window.__pfRateSummarize !== 'function') return;
      var summarize = window.__pfRateSummarize;

      function isDirty() {
        return (summarize().adjusted || 0) > 0;
      }

      var leaving = false;
      form.addEventListener('submit', function (event) {
        // Runs after the below-target dialog listener (source order): when it
        // cancels the submit, defaultPrevented is already true here.
        if (!event.defaultPrevented) leaving = true;
      });
      window.addEventListener('beforeunload', function (event) {
        if (leaving || form.dataset.ratedConfirmed === 'true') return;
        if (!isDirty()) return;
        event.preventDefault();
        event.returnValue = '';
      });

      var activeDialog = null;
      function openDiscardDialog(confirmLabel, onDiscard, onKeep) {
        if (activeDialog) return;
        var s = summarize();
        var backdrop = document.createElement('div');
        backdrop.className = 'modal-backdrop';
        var dialog = document.createElement('div');
        dialog.className = 'confirm-dialog confirm-danger';
        dialog.setAttribute('role', 'alertdialog');
        dialog.setAttribute('aria-label', 'Discard unsaved score adjustments');
        var heading = document.createElement('h2');
        heading.textContent = 'Discard unsaved scores?';
        var message = document.createElement('p');
        message.textContent = 'You adjusted ' + s.adjusted + ' of ' + s.total
          + ' KPIs but have not saved this rating yet. Leaving now discards those adjustments.';
        var actions = document.createElement('div');
        actions.className = 'confirm-dialog-actions';
        var keepButton = document.createElement('button');
        keepButton.type = 'button';
        keepButton.className = 'ghost-button';
        keepButton.textContent = 'Keep editing';
        var discardButton = document.createElement('button');
        discardButton.type = 'button';
        discardButton.className = 'btn-danger';
        discardButton.textContent = confirmLabel;
        actions.append(keepButton, discardButton);
        dialog.append(heading, message, actions);
        backdrop.append(dialog);
        document.body.append(backdrop);
        activeDialog = backdrop;

        var close = function () {
          backdrop.remove();
          document.removeEventListener('keydown', handleKeydown);
          activeDialog = null;
        };
        var keep = function () {
          close();
          if (onKeep) onKeep();
        };
        var handleKeydown = function (keyEvent) {
          if (keyEvent.key === 'Escape') keep();
        };
        keepButton.addEventListener('click', keep);
        discardButton.addEventListener('click', function () {
          close();
          onDiscard();
        });
        document.addEventListener('keydown', handleKeydown);
        // Focus the safe option, unlike the save-anyway dialog above.
        keepButton.focus();
      }

      var cancelLink = document.getElementById('rateCancel');
      if (cancelLink) {
        cancelLink.addEventListener('click', function (event) {
          if (!isDirty()) return;
          event.preventDefault();
          openDiscardDialog('Discard & leave', function () {
            leaving = true;
            window.location.href = cancelLink.href;
          });
        });
      }

      // Employee picker: the inline onchange calls this before the GET form
      // submits, so a dirty rating prompts instead of vanishing silently.
      // "Keep editing" restores the previously selected employee.
      var picker = document.getElementById('employee');
      if (picker) picker.setAttribute('data-pf-prev', picker.value);
      window.pfRatePickEmployee = function (select) {
        var go = function () {
          leaving = true;
          select.form.submit();
        };
        if (!isDirty()) {
          go();
          return;
        }
        openDiscardDialog('Discard & switch', go, function () {
          select.value = select.getAttribute('data-pf-prev') || select.value;
        });
      };
    })();

  </script>
</body>

</html>