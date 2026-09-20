<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';

$uid = $_GET['uid'] ?? ($_POST['uid'] ?? '');
if (!$uid) {
  header('Location: employees.php');
  exit;
}

$message = '';
$messageTone = 'info';
$action = $_POST['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_profile') {
  $existing = firestore_get_document('Users', $uid) ?? [];
  require_employer_owns_user($existing + ['uid' => $uid], 'employee_view:save_profile');
  $existing['name'] = trim($_POST['name'] ?? ($existing['name'] ?? ''));
  $existing['email'] = trim($_POST['email'] ?? ($existing['email'] ?? ''));
  $existing['department'] = trim($_POST['department'] ?? ($existing['department'] ?? ''));
  if (isset($_POST['industry'])) {
    $existing['industry'] = trim($_POST['industry']);
  }
  try {
    firestore_write_document('Users', $uid, $existing);
    $message = 'Profile updated.';
    $messageTone = 'success';
  } catch (\Throwable $e) {
    $message = 'Failed to save: ' . $e->getMessage();
    $messageTone = 'error';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'toggle_status') {
  $existing = firestore_get_document('Users', $uid) ?? [];
  require_employer_owns_user($existing + ['uid' => $uid], 'employee_view:toggle_status');
  $currentlyDisabled = ($existing['status'] ?? 'Active') === 'Disabled';
  try {
    identitytoolkit_disable_user($uid, !$currentlyDisabled);
    $existing['status'] = $currentlyDisabled ? 'Active' : 'Disabled';
    firestore_write_document('Users', $uid, $existing);
    $message = $currentlyDisabled ? 'Account reactivated.' : 'Account deactivated.';
    $messageTone = 'success';
  } catch (\Throwable $e) {
    $message = 'Failed to update account status: ' . $e->getMessage();
    $messageTone = 'error';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_regularization') {
  $existing = firestore_get_document('Users', $uid) ?? [];
  require_employer_owns_user($existing + ['uid' => $uid], 'employee_view:save_regularization');
  $existing['regularizationRecommendation'] = $_POST['recommendation'] ?? '';
  $existing['regularizationNotes'] = trim($_POST['notes'] ?? '');
  $existing['regularizationDecidedAt'] = date('c');
  $existing['regularizationDecidedBy'] = $_SESSION['name'] ?? '';
  try {
    firestore_write_document('Users', $uid, $existing);
    $message = 'Regularization decision saved.';
    $messageTone = 'success';
  } catch (\Throwable $e) {
    $message = 'Failed to save decision: ' . $e->getMessage();
    $messageTone = 'error';
  }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'save_feedback') {
  $feedbackMessage = trim((string) ($_POST['feedbackMessage'] ?? ''));
  if ($feedbackMessage === '') {
    $message = 'Please enter feedback before saving.';
    $messageTone = 'error';
  } else {
    try {
      $target = firestore_get_document('Users', $uid) ?? [];
      require_employer_owns_user($target + ['uid' => $uid], 'employee_view:save_feedback');
      firestore_write_document('Feedback', $uid . '_' . time(), [
        'employeeUid' => $uid,
        'sender' => $_SESSION['name'] ?? 'Employer',
        'role' => 'Employer',
        'message' => $feedbackMessage,
        'status' => 'Received',
        'createdAt' => date('c'),
      ]);
      $message = 'Feedback shared with the probationary employee.';
      $messageTone = 'success';
    } catch (Throwable $e) {
      $message = 'Failed to save feedback: ' . $e->getMessage();
      $messageTone = 'error';
    }
  }
}

$profile = firestore_get_document('Users', $uid);
if (!$profile) {
  header('Location: employees.php');
  exit;
}
require_employer_owns_user($profile + ['uid' => $uid], 'employee_view:read');

$roleKey = normalize_role_key($profile['role'] ?? null);
$isProbationary = $roleKey === 'probationary';
$createdAt = $profile['createdAt'] ?? '';
$createdTime = $createdAt ? strtotime($createdAt) : false;
$probationPeriodDays = max(1, (int) ($profile['probationPeriodDays'] ?? 180));
$daysSince = $createdTime ? max(1, (int) floor((time() - $createdTime) / 86400)) : 0;
$daysLeft = $createdTime ? max(0, $probationPeriodDays - $daysSince) : 0;

$allRatings = [];
try {
  $allRatings = firestore_list_documents('Ratings');
} catch (Throwable $e) {
}
$ratingHistory = ratings_for_employee($allRatings, $uid);
$template = kpi_template_for($profile['industry'] ?? 'retail');
$summary = employee_kpi_summary($allRatings, $uid, $profile['industry'] ?? 'retail');

$statusLabel = ($profile['status'] ?? 'Active') === 'Disabled' ? 'Disabled' : 'Active';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <?php employer_brand_head(); ?>
  <meta name="description" content="View and manage an employee profile, probation progress, and regularization decision." />
  <title><?php echo htmlspecialchars($profile['name'] ?? 'Employee', ENT_QUOTES); ?> · Performa</title>
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
    <?php employer_render_shell('Employees'); ?>
    <main class="main content-narrow pf-employee-view">
      <div class="page-header">
        <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
          <?php echo employer_icon('menu'); ?>
        </button>
        <div class="ph-main">
          <a href="employees.php" class="ghost-button back-link">&larr; Back to Employees</a>
          <nav class="ph-crumb" aria-label="Breadcrumb">
            <span>Employees</span>
            <span aria-hidden="true">/</span>
            <span><?php echo htmlspecialchars($profile['name'] ?? 'Employee', ENT_QUOTES); ?></span>
          </nav>
          <h1><?php echo htmlspecialchars($profile['name'] ?? 'Employee', ENT_QUOTES); ?></h1>
          <p><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $profile['role'] ?? '')), ENT_QUOTES); ?>
            &middot; <span class="status-pill <?php echo $statusLabel === 'Disabled' ? 'status-danger' : 'status-good'; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?></span>
          </p>
        </div>
        <?php if ($isProbationary): ?>
          <div class="ph-actions">
            <a class="ghost-button" href="rate_employee.php?employee=<?php echo urlencode($uid); ?>">Rate KPIs</a>
            <a class="ghost-button" href="kpis.php?employee=<?php echo urlencode($uid); ?>">View KPI Dashboard</a>
          </div>
        <?php endif; ?>
      </div>

      <?php if ($message): ?>
        <div class="alert <?php echo $messageTone === 'error' ? 'alert-error' : ($messageTone === 'success' ? 'alert-success' : 'alert-info'); ?>" role="<?php echo $messageTone === 'error' ? 'alert' : 'status'; ?>"><?php echo htmlspecialchars($message, ENT_QUOTES); ?></div>
      <?php endif; ?>

      <div class="settings-panel pf-view-panel">
        <?php
        $nameParts = preg_split('/\s+/', trim((string) ($profile['name'] ?? '')));
        $profileInitials = strtoupper(substr((string) ($nameParts[0] ?? '?'), 0, 1) . substr((string) ($nameParts[1] ?? ''), 0, 1));
        ?>
        <div class="pf-identity-hero">
          <span class="pf-avatar" aria-hidden="true"><?php echo htmlspecialchars($profileInitials, ENT_QUOTES); ?></span>
          <div class="pf-identity-meta">
            <div class="pf-identity-name"><?php echo htmlspecialchars($profile['name'] ?? 'Employee', ENT_QUOTES); ?></div>
            <div class="pf-identity-sub">
              <span class="pf-chip"><?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $profile['role'] ?? '')), ENT_QUOTES); ?></span>
              <?php if (!empty($profile['department'])): ?>
                <span class="pf-chip"><?php echo htmlspecialchars(pf_dept_label($profile['department']), ENT_QUOTES); ?></span>
              <?php endif; ?>
              <span class="status-pill <?php echo $statusLabel === 'Disabled' ? 'status-danger' : 'status-good'; ?>"><?php echo htmlspecialchars($statusLabel, ENT_QUOTES); ?></span>
            </div>
          </div>
        </div>
        <dl class="pf-identity-facts">
          <div>
            <dt>Email</dt>
            <dd><?php echo htmlspecialchars($profile['email'] ?? '—', ENT_QUOTES); ?></dd>
          </div>
          <div>
            <dt>Department</dt>
            <dd><?php echo pf_dept_label($profile['department'] ?? '') !== '' ? htmlspecialchars(pf_dept_label($profile['department']), ENT_QUOTES) : '—'; ?></dd>
          </div>
          <?php if ($isProbationary && !empty($profile['hireDate'])): ?>
            <div>
              <dt>Hire date</dt>
              <dd><?php echo htmlspecialchars(pf_date($profile['hireDate'], ''), ENT_QUOTES); ?></dd>
            </div>
          <?php endif; ?>
          <div>
            <dt>Member since</dt>
            <dd><?php echo $createdTime ? htmlspecialchars(pf_date($profile['createdAt'], ''), ENT_QUOTES) : '—'; ?></dd>
          </div>
        </dl>

        <hr class="section-divider" />

        <h4 class="settings-subhead">Profile</h4>
        <details class="pf-edit-details">
          <summary>Edit profile</summary>
        <form method="post">
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="save_profile" />
          <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid, ENT_QUOTES); ?>" />
          <div class="form-grid">
            <div class="form-group">
              <label for="name">Full Name</label>
              <input id="name" name="name" type="text"
                value="<?php echo htmlspecialchars($profile['name'] ?? '', ENT_QUOTES); ?>" required />
            </div>
            <div class="form-group">
              <label for="email">Email</label>
              <input id="email" name="email" type="email"
                value="<?php echo htmlspecialchars($profile['email'] ?? '', ENT_QUOTES); ?>" required />
            </div>
            <div class="form-group">
              <label for="department">Department</label>
              <input id="department" name="department" type="text"
                value="<?php echo htmlspecialchars(pf_dept_label($profile['department'] ?? ''), ENT_QUOTES); ?>" />
            </div>
            <?php if ($isProbationary): ?>
              <div class="form-group">
                <label for="industry">Industry (KPI template)</label>
                <select id="industry" class="perform-select" name="industry">
                  <?php foreach (kpi_templates() as $key => $tpl): ?>
                    <option value="<?php echo htmlspecialchars($key, ENT_QUOTES); ?>" <?php echo ($profile['industry'] ?? 'retail') === $key ? 'selected' : ''; ?>>
                      <?php echo htmlspecialchars($tpl['label'], ENT_QUOTES); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            <?php endif; ?>
          </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Save Changes</button>
          </div>
        </form>
        </details>

      </div>

      <?php if ($isProbationary): ?>
        <?php
        $countdownTone = $daysLeft <= 0 ? 'bad' : ($daysLeft <= 15 ? 'warn' : 'ok');
        $countdownPct = max(0, min(100, ($daysSince / $probationPeriodDays) * 100));
        $latestScores = $ratingHistory[0]['scores'] ?? [];
        $recentHistory = array_slice($ratingHistory, 0, 5);
        $milestones = [];
        foreach ([150, 165, 178] as $mDay) {
          if ($mDay < $probationPeriodDays) {
            $milestones[] = ['day' => $mDay, 'pct' => max(0, min(100, ($mDay / $probationPeriodDays) * 100))];
          }
        }
        ?>
        <div class="settings-panel pf-view-panel">
          <h4 class="settings-subhead">Probation Countdown</h4>
          <div class="pf-countdown pf-banner" data-tone="<?php echo $countdownTone; ?>">
            <div class="pf-countdown-number"><?php echo $daysLeft; ?><span>days left</span></div>
            <div class="pf-countdown-bar" role="progressbar" aria-valuemin="0" aria-valuemax="<?php echo $probationPeriodDays; ?>" aria-valuenow="<?php echo $daysSince; ?>" aria-label="Probation progress">
              <span class="pf-countdown-fill" style="width: <?php echo number_format($countdownPct, 1); ?>%;"></span>
              <?php foreach ($milestones as $ms): ?>
                <i class="pf-milestone" style="left: <?php echo number_format($ms['pct'], 1); ?>%;" title="Day <?php echo $ms['day']; ?> review point"></i>
              <?php endforeach; ?>
            </div>
            <p class="pf-countdown-meta">Day <?php echo $daysSince; ?> of <?php echo $probationPeriodDays; ?> &middot; <?php echo $daysLeft; ?> days remaining</p>
          </div>

          <hr class="section-divider" />

          <h4 class="settings-subhead">Performance Snapshot (<?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?>
            template)</h4>
          <?php if (!$summary['hasData']): ?>
            <p>No ratings submitted yet.</p>
          <?php else: ?>
            <div class="pf-perf-hero">
              <span class="pf-perf-score"><?php echo number_format($summary['score'], 1); ?><small>/ 5.0</small></span>
              <span class="microcopy">template target avg <?php echo number_format($summary['targetAvg'], 1); ?>
                &middot; <?php echo $summary['ratingCount']; ?> rating<?php echo $summary['ratingCount'] === 1 ? '' : 's'; ?>
                on file</span>
            </div>
            <ul class="pf-kpi-pills">
              <?php foreach ($template['kpis'] as $kpi): ?>
                <?php
                $kpiScore = isset($latestScores[$kpi['key']]) ? (float) $latestScores[$kpi['key']] : null;
                $kpiStatus = $kpiScore === null ? null : kpi_status_for_score($kpiScore, (float) $kpi['target']);
                $kpiFill = $kpiScore === null ? 0 : max(0, min(100, ($kpiScore / 5) * 100));
                $kpiTick = max(0, min(100, (((float) $kpi['target'] - 1) / 4) * 100));
                ?>
                <li<?php echo $kpiStatus ? ' data-status="' . htmlspecialchars($kpiStatus['statusClass'], ENT_QUOTES) . '"' : ''; ?>>
                  <div class="pf-meter-top">
                    <span class="pf-kpi-pills-name"><?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?></span>
                    <span class="microcopy"><?php echo $kpiScore === null ? 'no data' : number_format($kpiScore, 1) . ' / target ' . number_format((float) $kpi['target'], 1); ?></span>
                    <?php if ($kpiStatus): ?>
                      <span class="status-pill <?php echo htmlspecialchars($kpiStatus['statusClass'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($kpiStatus['status'], ENT_QUOTES); ?></span>
                    <?php endif; ?>
                  </div>
                  <div class="pf-meter" role="img" aria-label="<?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?>: <?php echo $kpiScore === null ? 'no data' : number_format($kpiScore, 1) . ' out of 5, target ' . number_format((float) $kpi['target'], 1); ?>">
                    <span class="pf-meter-fill" style="width: <?php echo number_format($kpiFill, 1); ?>%;"></span>
                    <span class="pf-meter-tick" style="left: <?php echo number_format($kpiTick, 1); ?>%;"></span>
                  </div>
                </li>
              <?php endforeach; ?>
            </ul>
            <?php if (count($recentHistory) > 1): ?>
              <h4 class="settings-subhead pf-history-head">Recent ratings</h4>
              <ul class="pf-history pf-chart" aria-label="Recent rating averages">
                <?php foreach ($recentHistory as $h): ?>
                  <?php
                  $hScores = array_values(array_filter(array_map('floatval', (array) ($h['scores'] ?? [])), function ($v) { return $v > 0; }));
                  $hAvg = $hScores ? array_sum($hScores) / count($hScores) : null;
                  $hHeight = $hAvg === null ? 0 : max(4, min(100, ($hAvg / 5) * 100));
                  ?>
                  <li>
                    <strong><?php echo $hAvg === null ? '—' : number_format($hAvg, 1); ?></strong>
                    <span class="pf-chart-bar" aria-hidden="true"><span style="height: <?php echo number_format($hHeight, 1); ?>%;"></span></span>
                    <span class="microcopy"><?php echo !empty($h['ratedAt']) ? htmlspecialchars(pf_date($h['ratedAt'], ''), ENT_QUOTES) : '—'; ?></span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          <?php endif; ?>

          <hr class="section-divider" />

          <h4 class="settings-subhead">Employer Feedback</h4>
          <p class="microcopy">Share feedback that will appear on the employee's Feedback page.</p>
          <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_feedback" />
            <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid, ENT_QUOTES); ?>" />
            <div class="form-group">
              <label for="feedbackMessage">Feedback</label>
              <textarea id="feedbackMessage" name="feedbackMessage" rows="4"
                placeholder="Write feedback for this employee..." required></textarea>
            </div>
            <div class="form-actions">
              <button class="btn-primary" type="submit">Share Feedback</button>
            </div>
          </form>

          <hr class="section-divider" />

          <h4 class="settings-subhead">Regularization Recommendation</h4>
          <?php if (!empty($profile['regularizationRecommendation'])): ?>
            <p class="microcopy">
              Current decision: <strong><?php echo $profile['regularizationRecommendation'] === 'recommended' ? 'Recommended for Regularization' : 'Not Yet Recommended'; ?></strong>
              <?php if (!empty($profile['regularizationDecidedAt'])): ?> &middot;
                <?php echo htmlspecialchars(pf_date($profile['regularizationDecidedAt'], ''), ENT_QUOTES); ?>     <?php endif; ?>
              <?php if (!empty($profile['regularizationDecidedBy'])): ?> by
                <?php echo htmlspecialchars($profile['regularizationDecidedBy'], ENT_QUOTES); ?>     <?php endif; ?>
            </p>
            <?php if (!empty($profile['regularizationNotes'])): ?>
              <p class="microcopy">Notes:
                <?php echo htmlspecialchars($profile['regularizationNotes'], ENT_QUOTES); ?>
              </p>
            <?php endif; ?>
          <?php endif; ?>

          <form method="post">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="action" value="save_regularization" />
            <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid, ENT_QUOTES); ?>" />
            <div class="form-grid">
              <div class="form-group">
                <label for="recommendation">Decision</label>
                <select id="recommendation" class="perform-select" name="recommendation" required>
                  <option value="">Select decision</option>
                  <option value="recommended" <?php echo ($profile['regularizationRecommendation'] ?? '') === 'recommended' ? 'selected' : ''; ?>>Recommend for Regularization</option>
                  <option value="not_recommended" <?php echo ($profile['regularizationRecommendation'] ?? '') === 'not_recommended' ? 'selected' : ''; ?>>Not Yet Recommended</option>
                </select>
              </div>
              <div class="form-group">
                <label for="notes">Notes</label>
                <input id="notes" name="notes" type="text"
                  value="<?php echo htmlspecialchars($profile['regularizationNotes'] ?? '', ENT_QUOTES); ?>"
                  placeholder="Optional rationale" />
              </div>
            </div>
          <div class="form-actions">
            <button class="btn-primary" type="submit">Save Decision</button>
          </div>
        </form>
      </div>
      <?php endif; ?>

      <div class="settings-panel pf-view-panel pf-danger-zone">
        <h4 class="settings-subhead">Account Status</h4>
        <p class="microcopy">
          <?php echo $statusLabel === 'Disabled' ? 'This account is disabled and cannot sign in.' : 'This account can sign in normally.'; ?>
        </p>
        <form method="post"
          data-confirm="<?php echo htmlspecialchars($statusLabel === 'Disabled' ? 'Reactivate this account?' : 'Deactivate ' . ($profile['name'] ?? 'this account') . '? They will be signed out immediately and unable to log in.', ENT_QUOTES); ?>"<?php echo $statusLabel === 'Disabled' ? '' : ' data-confirm-danger'; ?>>
          <?php echo csrf_field(); ?>
          <input type="hidden" name="action" value="toggle_status" />
          <input type="hidden" name="uid" value="<?php echo htmlspecialchars($uid, ENT_QUOTES); ?>" />
          <button class="<?php echo $statusLabel === 'Disabled' ? 'ghost-button' : 'btn-danger'; ?>"
            type="submit"><?php echo $statusLabel === 'Disabled' ? 'Reactivate Account' : 'Deactivate Account'; ?></button>
        </form>
      </div>
    </main>
  </div>
  <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>
</body>

</html>