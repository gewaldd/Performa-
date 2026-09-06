<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../kpi_templates.php';
require_login();
require_role('supervisor');

$supervisorUid = $_SESSION['uid'];
$supervisorName = $_SESSION['name'] ?? 'Supervisor';

$navItems = [
    ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'active' => false],
    ['label' => 'My Employees', 'href' => 'employees.php', 'active' => false],
    ['label' => 'Rating Entry', 'href' => 'ratings.php', 'active' => true],
    ['label' => 'Reports', 'href' => 'reports.php', 'active' => false],
    ['label' => 'Settings', 'href' => 'settings.php', 'active' => false],
    ['label' => 'Notifications', 'href' => 'notifications.php', 'active' => false],
];

$employees = [];
try {
    $docs = firestore_list_documents('Users');
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
    if ($e['uid'] === $selectedUid)
        $selectedEmployee = $e;
}

$template = $selectedEmployee ? kpi_template_for($selectedEmployee['industry']) : ['label' => '', 'kpis' => []];

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
        $message = 'Please provide a valid score (1–5) for every KPI.';
        $messageIsError = true;
    } else {
        try {
            $docId = $selectedEmployee['uid'] . '_' . date('Y-m-d');
            firestore_write_document('Ratings', $docId, [
                'employeeUid' => $selectedEmployee['uid'],
                'employeeName' => $selectedEmployee['name'],
                'industry' => $selectedEmployee['industry'],
                'weekOf' => date('Y-m-d'),
                'ratedAt' => date('c'),
                'ratedBy' => $supervisorUid,
                'ratedByRole' => 'supervisor',
                'scores' => $scores,
            ]);
            $message = 'Rating submitted successfully and saved to the database.';
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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Rating Entry</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        .form-grid {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-group label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text);
        }

        .form-group select,
        .form-group input {
            border: 1px solid var(--panel-border);
            border-radius: 10px;
            padding: 11px 14px;
            font-size: 14px;
            font-family: inherit;
            background: #fbfcfe;
            color: var(--text);
            outline: none;
        }

        .form-group select:focus,
        .form-group input:focus {
            border-color: var(--primary, #2f6df6);
        }

        .section-divider {
            border: none;
            border-top: 1px solid var(--panel-border);
            margin: 18px 0;
        }

        .form-actions {
            margin-top: 8px;
        }
    </style>
</head>

<body>
    <div class="app-shell">
        <aside class="sidebar">
            <div>
                <div class="brand">
                    <div class="brand-mark">P</div>
                    <div>
                        <div class="brand-name">Performa</div>
                        <div class="brand-subtitle">Supervisor Dashboard</div>
                    </div>
                </div>
                <nav class="nav" aria-label="Primary">
                    <?php foreach ($navItems as $item): ?>
                        <a class="nav-item<?php echo $item['active'] ? ' active' : ''; ?>"
                            href="<?php echo htmlspecialchars($item['href'], ENT_QUOTES); ?>"><span><?php echo htmlspecialchars($item['label'], ENT_QUOTES); ?></span></a>
                    <?php endforeach; ?>
                </nav>
            </div>
            <div class="sidebar-footer">
                <div class="profile-avatar"><?php echo strtoupper(substr($supervisorName, 0, 2)); ?></div>
                <div>
                    <div class="profile-name"><?php echo htmlspecialchars($supervisorName, ENT_QUOTES); ?></div>
                    <div class="profile-role">Shift Supervisor</div>
                </div>
            </div>
        </aside>
        <main class="main">
            <section class="hero">
                <p class="eyebrow">Rating Entry</p>
                <h1>Submit a weekly KPI rating for a probationary employee.</h1>
            </section>

            <?php if ($message): ?>
                <div
                    style="margin-bottom:16px;padding:12px 16px;border-radius:10px;border:1px solid <?php echo $messageIsError ? '#e2483d' : '#16a76d'; ?>;background:<?php echo $messageIsError ? 'rgba(226,72,61,0.08)' : 'rgba(22,167,109,0.08)'; ?>;color:<?php echo $messageIsError ? '#c94a3f' : '#16a76d'; ?>;font-size:13px;">
                    <?php echo htmlspecialchars($message, ENT_QUOTES); ?>
                </div>
            <?php endif; ?>

            <?php if (empty($employees)): ?>
                <section class="panel" style="padding:24px;">
                    <p style="color:var(--muted);">No probationary employees found yet.</p>
                </section>
            <?php else: ?>
                <section class="content-grid">
                    <div class="panel" style="padding:24px;">
                        <form method="get" class="form-grid" style="margin-bottom:8px;">
                            <div class="form-group">
                                <label for="employee">Employee</label>
                                <select id="employee" name="employee" onchange="this.form.submit()">
                                    <?php foreach ($employees as $emp): ?>
                                        <option value="<?php echo htmlspecialchars($emp['uid'], ENT_QUOTES); ?>" <?php echo $emp['uid'] === $selectedUid ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($emp['name'], ENT_QUOTES); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </form>

                        <hr class="section-divider" />

                        <form method="post">
                            <input type="hidden" name="employee"
                                value="<?php echo htmlspecialchars($selectedUid, ENT_QUOTES); ?>" />
                            <p style="color:var(--muted);margin-bottom:16px;font-size:13.5px;">Industry template:
                                <strong
                                    style="color:var(--text);"><?php echo htmlspecialchars($template['label'], ENT_QUOTES); ?></strong>
                            </p>
                            <div class="form-grid">
                                <?php foreach ($template['kpis'] as $kpi): ?>
                                    <div class="form-group">
                                        <label for="score_<?php echo $kpi['key']; ?>">
                                            <?php echo htmlspecialchars($kpi['name'], ENT_QUOTES); ?> (target
                                            <?php echo number_format($kpi['target'], 1); ?>)
                                        </label>
                                        <input id="score_<?php echo $kpi['key']; ?>"
                                            name="score_<?php echo $kpi['key']; ?>" type="number" min="1" max="5"
                                            step="0.1" required />
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="form-actions">
                                <button class="primary-button" type="submit">Save Rating</button>
                            </div>
                        </form>
                    </div>

                    <aside class="insight-card">
                        <div class="insight-badge">RATING GUIDE</div>
                        <h2>How to score each KPI</h2>
                        <p>Enter a score from 1 to 5 for each KPI, compared against its target. Use the full range
                            so trends are meaningful over time.</p>

                        <div class="recommendation-box">
                            <div class="recommendation-label">4.5 – 5.0</div>
                            <strong>Consistently exceeds the target for this KPI.</strong>
                        </div>

                        <div class="recommendation-box" style="margin-top: 10px;">
                            <div class="recommendation-label">At or near target</div>
                            <strong>Performs at the expected standard, no concerns.</strong>
                        </div>

                        <div class="recommendation-box" style="margin-top: 10px;">
                            <div class="recommendation-label">Below target</div>
                            <strong>Falls short of the expected standard this week.</strong>
                        </div>

                        <p class="microcopy">Ratings submitted here are saved immediately and feed directly into
                            the employee's KPI summary and reports.</p>
                    </aside>
                </section>
            <?php endif; ?>
        </main>
    </div>
    <script src="script.js"></script>
</body>

</html>