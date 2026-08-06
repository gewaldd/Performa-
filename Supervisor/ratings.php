<?php
$navItems = [
    ['label' => 'Dashboard', 'href' => 'supervisor_dashboard.php', 'active' => false],
    ['label' => 'My Employees', 'href' => 'employees.php', 'active' => false],
    ['label' => 'Rating Entry', 'href' => 'ratings.php', 'active' => true],
];

// TODO(firebase): replace with a Firestore query for assigned employees.
$employees = [
    ['id' => 'emp001', 'name' => 'Maria Clara', 'role' => 'Customer Support Spec.'],
    ['id' => 'emp002', 'name' => 'Jose Rizal', 'role' => 'Software Engineer'],
];

// TODO(firebase): replace with the KPIs configured by the Employer for the
// selected employee's job role (read from the `kpis` collection).
$kpis = [
    ['id' => 'kpi001', 'label' => 'Task Completion Rate'],
    ['id' => 'kpi002', 'label' => 'Communication & Teamwork'],
    ['id' => 'kpi003', 'label' => 'Attendance & Punctuality'],
    ['id' => 'kpi004', 'label' => 'Quality of Work'],
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Performa | Rating Entry</title>
    <link rel="stylesheet" href="styles.css" />
    <style>
        .rating-form {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .form-row {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .form-row label {
            font-size: 12.5px;
            font-weight: 600;
            color: var(--text);
        }

        .form-row select {
            border: 1px solid var(--panel-border);
            border-radius: var(--radius-sm);
            padding: 11px 14px;
            font-size: 14px;
            font-family: inherit;
            background: #fbfcfe;
            color: var(--text);
        }

        .kpi-score-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .kpi-score-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 16px;
            border: 1px solid var(--panel-border);
            border-radius: var(--radius-md);
            background: #fbfcfe;
        }

        .kpi-score-row span {
            font-size: 13.5px;
            font-weight: 600;
        }

        .star-input {
            display: flex;
            gap: 4px;
            font-size: 20px;
            cursor: pointer;
            color: #d8dde8;
        }

        .star-input .star.active {
            color: #f0a11b;
        }

        .form-note {
            font-size: 12.5px;
            color: var(--muted);
        }

        .save-confirmation {
            display: none;
            font-size: 13px;
            color: var(--positive);
            background: rgba(22, 167, 109, 0.08);
            border: 1px solid rgba(22, 167, 109, 0.2);
            padding: 10px 14px;
            border-radius: var(--radius-sm);
        }

        .save-confirmation.visible {
            display: block;
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
                <div class="profile-avatar">SP</div>
                <div>
                    <div class="profile-name">Sofia Panganiban</div>
                    <div class="profile-role">Shift Supervisor</div>
                </div>
            </div>
        </aside>
        <main class="main">
            <section class="hero">
                <p class="eyebrow">Rating Entry</p>
                <h1>Submit a weekly KPI rating for an assigned employee.</h1>
            </section>

            <section class="content-grid">
            <div class="panel" style="padding-top:18px;">
                <form class="rating-form" id="ratingForm">
                    <div class="form-row">
                        <label for="employeeSelect">Employee</label>
                        <select id="employeeSelect" name="employeeId" required>
                            <option value="" disabled selected>Select an employee</option>
                            <?php foreach ($employees as $emp): ?>
                                <option value="<?php echo htmlspecialchars($emp['id'], ENT_QUOTES); ?>">
                                    <?php echo htmlspecialchars($emp['name'] . ' — ' . $emp['role'], ENT_QUOTES); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-row">
                        <label for="weekEnding">Week Ending</label>
                        <input type="date" id="weekEnding" name="weekEnding" required
                            style="border: 1px solid var(--panel-border); border-radius: var(--radius-sm); padding: 11px 14px; font-size: 14px; font-family: inherit; background: #fbfcfe; color: var(--text);" />
                    </div>

                    <div class="form-row">
                        <label>KPI Scores</label>
                        <div class="kpi-score-list" id="kpiScoreList">
                            <?php foreach ($kpis as $kpi): ?>
                                <div class="kpi-score-row" data-kpi-id="<?php echo htmlspecialchars($kpi['id'], ENT_QUOTES); ?>">
                                    <span><?php echo htmlspecialchars($kpi['label'], ENT_QUOTES); ?></span>
                                    <div class="star-input" data-score="0">
                                        <span class="star" data-value="1">★</span>
                                        <span class="star" data-value="2">★</span>
                                        <span class="star" data-value="3">★</span>
                                        <span class="star" data-value="4">★</span>
                                        <span class="star" data-value="5">★</span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <p class="form-note">Click a star to rate each KPI from 1 (needs improvement) to 5 (excellent).</p>
                    </div>

                    <div class="form-row">
                        <label for="notes">Notes (optional)</label>
                        <textarea id="notes" name="notes" rows="3" placeholder="Any observations for this rating period..."
                            style="border: 1px solid var(--panel-border); border-radius: var(--radius-sm); padding: 11px 14px; font-size: 14px; font-family: inherit; background: #fbfcfe; color: var(--text); resize: vertical;"></textarea>
                    </div>

                    <div class="save-confirmation" id="saveConfirmation">Rating submitted successfully.</div>

                    <button class="primary-button" type="submit" id="submitRatingBtn">Submit Rating</button>
                </form>
            </div>

                <aside class="insight-card">
                    <div class="insight-badge">AI INSIGHTS</div>
                    <h2>How to score each KPI</h2>
                    <p>Use the full 1–5 range so trends are meaningful over time. Avoid defaulting to the middle score for every KPI.</p>

                    <div class="recommendation-box">
                        <div class="recommendation-label">5 — Excellent</div>
                        <strong>Consistently exceeds expectations for this KPI.</strong>
                    </div>

                    <div class="recommendation-box" style="margin-top: 10px;">
                        <div class="recommendation-label">3 — Meets Expectations</div>
                        <strong>Performs at the expected standard, no concerns.</strong>
                    </div>

                    <div class="recommendation-box" style="margin-top: 10px;">
                        <div class="recommendation-label">1 — Needs Improvement</div>
                        <strong>Falls short of the expected standard this week.</strong>
                    </div>

                    <p class="microcopy">Ratings submitted here feed directly into the employee's monthly performance summary and regularization recommendation.</p>
                </aside>
            </section>
        </main>
    </div>

    <footer class="site-footer">
        <span>Performa supervisor dashboard prototype</span>
        <span>View-only access · No KPI configuration rights</span>
    </footer>

    <script src="script.js"></script>
    <script src="ratings-script.js"></script>
</body>

</html>
