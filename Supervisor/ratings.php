<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/../Employer/includes/collection_cache.php';
require_once __DIR__ . '/supervisor_layout.php';
require_login();
require_role('supervisor');
require_password_reset('settings.php');

$supervisorUid = $_SESSION['uid'];
$supervisorName = $_SESSION['name'] ?? 'Supervisor';

$employees = [];
try {
    $docs = get_cached_collection('Users', 600);
    foreach ($docs as $doc) {
        $roleKey = strtolower(trim((string) ($doc['role'] ?? '')));
        if (strpos($roleKey, 'probation') !== false) {
            $employees[] = [
                'uid' => $doc['uid'] ?? '',
                'name' => $doc['name'] ?? $doc['email'] ?? 'Unknown',
                'industry' => $doc['industry'] ?? 'retail',
            ];
        }
    }
} catch (\Throwable $e) {
}

$selectedUid = $_GET['employee'] ?? ($_POST['employee'] ?? ($employees[0]['uid'] ?? ''));
$selectedEmployee = null;
foreach ($employees as $e) {
    if ($e['uid'] === $selectedUid) {
        $selectedEmployee = $e;
        break;
    }
}
if (!$selectedEmployee && $employees) {
    $selectedEmployee = $employees[0];
    $selectedUid = $selectedEmployee['uid'];
}

$template = $selectedEmployee ? kpi_template_for($selectedEmployee['industry']) : kpi_template_for('retail');

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
$messageIsError = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $selectedEmployee) {
    $scores = [];
    $allValid = true;
    foreach ($template['kpis'] as $kpi) {
        $val = $_POST['score_' . $kpi['key']] ?? null;
        if ($val === null || $val === '' || (float) $val < 1 || (float) $val > 5) {
            $allValid = false;
        }
        $scores[$kpi['key']] = (float) $val;
    }

    if (!$allValid) {
        $message = 'Please provide a valid score (1.0–5.0) for every KPI.';
        $messageIsError = true;
    } else {
        try {
            $docId = $selectedEmployee['uid'] . '_' . date('Y-m-d');
            firestore_batch_write([
                [
                    'collection' => 'Ratings',
                    'documentId' => $docId,
                    'data' => [
                        'employeeUid' => $selectedEmployee['uid'],
                        'employeeName' => $selectedEmployee['name'],
                        'industry' => $selectedEmployee['industry'],
                        'weekOf' => date('Y-m-d'),
                        'ratedAt' => date('c'),
                        'ratedBy' => $supervisorUid,
                        'ratedByRole' => 'supervisor',
                        'scores' => $scores,
                    ],
                ],
                [
                    'collection' => 'Acknowledgements',
                    'documentId' => $selectedEmployee['uid'] . '_' . date('Y-m'),
                    'data' => [
                        'employeeUid' => $selectedEmployee['uid'],
                        'month' => date('F Y'),
                        'status' => 'Pending',
                        'timestamp' => null,
                        'createdAt' => date('c'),
                    ],
                ],
                [
                    'collection' => 'notifications',
                    'documentId' => $selectedEmployee['uid'] . '_' . date('Y-m') . '_summary',
                    'data' => [
                        'employeeUid' => $selectedEmployee['uid'],
                        'title' => 'Performance summary ready',
                        'detail' => 'Your ' . date('F Y') . ' performance summary is available for acknowledgement.',
                        'type' => 'info',
                        'createdAt' => date('c'),
                    ],
                ],
                [
                    'collection' => 'Feedback',
                    'documentId' => $selectedEmployee['uid'] . '_' . date('Y-m') . '_supervisor',
                    'data' => [
                        'employeeUid' => $selectedEmployee['uid'],
                        'sender' => $supervisorName,
                        'role' => 'Supervisor',
                        'message' => 'Your ' . date('F Y') . ' KPI rating has been submitted. Review your performance summary and acknowledgement.',
                        'status' => 'Received',
                        'createdAt' => date('c'),
                    ],
                ],
            ]);
            $message = 'Rating submitted successfully and saved to Firestore.';
            // Refresh previous scores
            $prevScores = $scores;
        } catch (\Throwable $e) {
            $message = 'Failed to save rating: ' . $e->getMessage();
            $messageIsError = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php supervisor_brand_head('Rating Entry · Performa'); ?>
</head>

<body>
    <div class="app-shell">
        <?php supervisor_render_shell('Rating Entry'); ?>

        <main class="main" id="rating-entry">
            <?php
            $eyebrowHtml = '<span class="eyebrow">Performance Assessment</span>';
            supervisor_page_header(
                'rating-title',
                'Weekly KPI Rating',
                $eyebrowHtml,
                'Score this week\'s KPIs for a probationary employee. Use the interactive slider or type a score from 1.0 to 5.0.'
            );
            ?>

            <div class="settings-panel supervisor-rate-card" style="margin-top: 20px;">
                <?php if ($message): ?>
                    <div class="alert <?php echo $messageIsError ? 'alert-error' : 'alert-info'; ?>" role="status" style="margin-bottom: 20px;">
                        <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
                    </div>
                <?php endif; ?>

                <?php if (empty($employees)): ?>
                    <div class="empty-state" style="padding: 40px; text-align: center;">
                        <p class="text-muted">No probationary employees found to rate.</p>
                    </div>
                <?php else: ?>
                    <form method="get" class="form-grid single-field-grid" style="margin-bottom: 12px;">
                        <div class="form-group">
                            <label for="employee" style="font-weight: 600; font-size: 13px;">Selected Employee</label>
                            <select id="employee" class="perform-select" name="employee" onchange="this.form.submit()">
                                <?php foreach ($employees as $emp): ?>
                                    <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>" <?php echo $emp['uid'] === $selectedUid ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?> (<?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $emp['industry'])), ENT_QUOTES); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>

                    <hr class="section-divider" style="margin: 16px 0 20px;" />

                    <form method="post" id="rateForm">
                        <input type="hidden" name="employee" value="<?php echo htmlspecialchars($selectedUid, ENT_QUOTES); ?>" />
                        <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 12px;">
                            <span class="microcopy">Industry template: <strong><?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?></strong></span>
                        </div>

                        <div class="pf-rate-preview" id="ratePreview" aria-live="polite"></div>

                        <div class="pf-rate-grid">
                            <?php foreach ($template['kpis'] as $kpi): ?>
                                <?php
                                $valDefault = isset($prevScores[$kpi['key']]) ? number_format((float) $prevScores[$kpi['key']], 1) : '3.0';
                                ?>
                                <div class="pf-rate-row">
                                    <label for="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>">
                                        <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>
                                        <span class="microcopy"> · target <?php echo number_format((float) $kpi['target'], 1); ?></span>
                                        <span class="microcopy"> · <?php echo isset($prevScores[$kpi['key']]) ? 'Last rating: ' . number_format((float) $prevScores[$kpi['key']], 1) : 'No prior rating'; ?></span>
                                    </label>
                                    <div class="pf-rate-controls">
                                        <input type="range" min="1" max="5" step="0.1" value="<?php echo $valDefault; ?>"
                                            data-rate-slider="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>"
                                            aria-label="<?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?> slider" />
                                        <input id="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>"
                                            name="score_<?php echo htmlspecialchars($kpi['key'], ENT_QUOTES); ?>"
                                            class="pf-rate-value" type="number" min="1" max="5" step="0.1"
                                            value="<?php echo $valDefault; ?>" required />
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-actions" style="display: flex; gap: 12px; margin-top: 24px;">
                            <a class="ghost-button" href="supervisor_dashboard.php">Cancel</a>
                            <button class="btn-primary" type="submit">Save Rating</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script src="<?php echo htmlspecialchars(supervisor_asset('script.js'), ENT_QUOTES); ?>"></script>
    <script>
        // Targets for live average calculation
        window.__pfRateTargets = <?php
            $rateTargets = [];
            foreach ($template['kpis'] as $rateKpi) {
                $rateTargets[$rateKpi['key']] = (float) $rateKpi['target'];
            }
            echo json_encode($rateTargets, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}';
        ?>;

        // Slider <-> number sync
        document.querySelectorAll('[data-rate-slider]').forEach(function (slider) {
            var target = document.getElementById(slider.getAttribute('data-rate-slider'));
            if (!target) return;
            slider.addEventListener('input', function () { target.value = slider.value; });
            target.addEventListener('input', function () {
                var v = parseFloat(target.value);
                if (!isNaN(v)) slider.value = Math.max(1, Math.min(5, v));
            });
        });

        // Live average preview & confirm dialog
        (function () {
            var form = document.getElementById('rateForm');
            var preview = document.getElementById('ratePreview');
            if (!form || !preview) return;
            var targets = window.__pfRateTargets || {};

            function readScores() {
                var vals = [];
                form.querySelectorAll('input[name^="score_"]').forEach(function (input) {
                    var v = parseFloat(input.value);
                    if (isNaN(v)) return;
                    var key = input.name.replace(/^score_/, '');
                    vals.push({ key: key, value: Math.max(1, Math.min(5, v)) });
                });
                return vals;
            }

            function summarize() {
                var vals = readScores();
                if (!vals.length) return { avg: null, below: 0, total: 0 };
                var sum = 0, below = 0;
                vals.forEach(function (s) {
                    sum += s.value;
                    var t = parseFloat(targets[s.key]);
                    if (!isNaN(t) && s.value < t) below++;
                });
                return { avg: sum / vals.length, below: below, total: vals.length };
            }

            function paint() {
                var s = summarize();
                if (s.avg === null) { preview.textContent = ''; return; }
                preview.innerHTML = '';
                preview.append('Average ');
                var avgSpan = document.createElement('span');
                avgSpan.style.whiteSpace = 'nowrap';
                avgSpan.style.fontWeight = '700';
                avgSpan.textContent = s.avg.toFixed(1) + ' / 5.0';
                preview.append(avgSpan);
                if (s.below > 0) {
                    preview.append(' · ');
                    var belowSpan = document.createElement('span');
                    belowSpan.style.whiteSpace = 'nowrap';
                    belowSpan.style.color = '#c2410c';
                    belowSpan.textContent = s.below + ' of ' + s.total + ' below target';
                    preview.append(belowSpan);
                } else {
                    preview.append(' · all at or above target');
                }
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
    </script>
</body>

</html>