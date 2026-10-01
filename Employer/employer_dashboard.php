<?php

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/csrf.php';
require_once __DIR__ . '/includes/roles.php';
require_csrf();
require_once __DIR__ . '/employer_layout.php';
require_once __DIR__ . '/../kpi_templates.php';
require_once __DIR__ . '/../audit_log.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['uid'])) {
    header('Location: ../login.php');
    exit;
}

$profileName = $_SESSION['name'] ?? 'Unknown User';
$profileRole = $_SESSION['role'] ?? 'Employer';
$profileRoleDisplay = ucwords(str_replace('_', ' ', (string) $profileRole));

// Icons come from the shared library (includes/icons.php via employer_layout.php).
// $icons stays as a thin alias so existing markup below keeps working.
$icons = [
    'home' => employer_icon('home'),
    'users' => employer_icon('users'),
    'target' => employer_icon('target'),
    'bar-chart' => employer_icon('bar-chart'),
    'settings' => employer_icon('settings'),
    'search' => employer_icon('search'),
    'bell' => employer_icon('bell'),
    'plus' => employer_icon('plus'),
    'hourglass' => employer_icon('hourglass'),
    'trend' => employer_icon('trend'),
    'download' => employer_icon('download'),
    'cap' => employer_icon('cap'),
    'more' => employer_icon('more'),
];

/*
 * Manuscript note: the dashboard never writes training assignments directly.
 * Assignments are authored only by the review approve handler
 * (review_recommendations.php), which runs the human-in-the-loop step —
 * employer review before anything reaches the employee (P.121) — and records
 * the audit trail. Cards below route into that flow instead. The one exception
 * is reversal: unassigning clears the employer's own assignment record.
 */

$dashMessage = '';
$dashMessageType = 'info';

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'unassign_training' &&
    !empty($_POST['uid'])
) {
    try {
        $unassignUid = trim((string) $_POST['uid']);

        $unassignTarget =
            firestore_get_document('Users', $unassignUid) ?? [];

        require_employer_owns_user(
            $unassignTarget + ['uid' => $unassignUid],
            'dashboard:unassign_training'
        );

        unset(
            $unassignTarget['assignedTraining'],
            $unassignTarget['assignedTrainingAt']
        );

        firestore_write_document('Users', $unassignUid, $unassignTarget);

        unset($_SESSION['dashboard_live_data'], $_SESSION['dashboard_cache_time']);

        record_audit_event(
            'training_unassigned',
            'Removed training assignment for ' . $unassignUid,
            ['employee' => $unassignUid]
        );

        header(
            'Location: employer_dashboard.php?unassigned=' .
            urlencode($unassignUid)
        );

        exit;
    } catch (Throwable $e) {
        $dashMessage = 'Could not remove the assignment. Please try again.';
        $dashMessageType = 'error';
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'queue_dismiss' &&
    !empty($_POST['uid'])
) {
    try {
        $dismissUid = trim((string) $_POST['uid']);

        $dismissTarget =
            firestore_get_document('Users', $dismissUid) ?? [];

        require_employer_owns_user(
            $dismissTarget + ['uid' => $dismissUid],
            'dashboard:queue_dismiss'
        );

        // Triage only: hides one queue card. Plan status, training and
        // scores are untouched, so the review-page gate is unaffected.
        // No data-confirm: dismissal is one click to undo via Restore.
        $dismissTarget['queueDismissedAt'] = gmdate('Y-m-d\TH:i:s\Z');
        $dismissTarget['queueDismissedBy'] = (string) ($_SESSION['uid'] ?? '');

        firestore_write_document('Users', $dismissUid, $dismissTarget);

        unset($_SESSION['dashboard_live_data'], $_SESSION['dashboard_cache_time']);

        record_audit_event(
            'queue_dismissed',
            'Hid review queue card for ' . $dismissUid,
            ['employee' => $dismissUid]
        );

        header(
            'Location: employer_dashboard.php?dismissed=' .
            urlencode($dismissUid)
        );

        exit;
    } catch (Throwable $e) {
        $dashMessage = 'Could not update the review queue. Please try again.';
        $dashMessageType = 'error';
    }
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'queue_restore' &&
    !empty($_POST['uid'])
) {
    try {
        $restoreUid = trim((string) $_POST['uid']);

        $restoreTarget =
            firestore_get_document('Users', $restoreUid) ?? [];

        require_employer_owns_user(
            $restoreTarget + ['uid' => $restoreUid],
            'dashboard:queue_restore'
        );

        unset(
            $restoreTarget['queueDismissedAt'],
            $restoreTarget['queueDismissedBy']
        );

        firestore_write_document('Users', $restoreUid, $restoreTarget);

        unset($_SESSION['dashboard_live_data'], $_SESSION['dashboard_cache_time']);

        record_audit_event(
            'queue_restored',
            'Restored review queue card for ' . $restoreUid,
            ['employee' => $restoreUid]
        );

        header(
            'Location: employer_dashboard.php?restored=' .
            urlencode($restoreUid)
        );

        exit;
    } catch (Throwable $e) {
        $dashMessage = 'Could not update the review queue. Please try again.';
        $dashMessageType = 'error';
    }
}

if (isset($_GET['unassigned'])) {
    $dashMessage = 'Training assignment removed.';
    $dashMessageType = 'success';
}

if (isset($_GET['dismissed'])) {
    $dashMessage = 'Removed from review queue.';
    $dashMessageType = 'success';
}

if (isset($_GET['restored'])) {
    $dashMessage = 'Review queue item restored.';
    $dashMessageType = 'success';
}

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'send_acknowledgement' &&
    !empty($_POST['uid'])
) {
    try {
        $ackUid = trim((string) $_POST['uid']);

        $ackTarget =
            firestore_get_document('Users', $ackUid) ?? [];

        require_employer_owns_user(
            $ackTarget + ['uid' => $ackUid],
            'dashboard:send_acknowledgement'
        );

        // Dispatch the latest approved plan for acknowledgement. The
        // approved content was authored by the review gate; this only
        // (re)creates the monthly acknowledgement + notification pair,
        // so resending is idempotent by document ID.
        $assigned = $ackTarget['assignedTraining'] ?? null;

        if (!is_string($assigned) || trim($assigned) === '') {
            throw new RuntimeException('No approved plan to send yet.');
        }

        $ackMonth = date('Y-m');
        $ackMonthLabel = date('F Y');

        firestore_write_document('Acknowledgements', $ackUid . '_' . $ackMonth, [
            'employeeUid' => $ackUid,
            'month' => $ackMonthLabel,
            'status' => 'Pending',
            'timestamp' => null,
            'createdAt' => date('c'),
            'planSummary' => $assigned,
            'dispatchedBy' => $_SESSION['name'] ?? 'Employer',
            'dispatchedAt' => date('c'),
        ]);

        firestore_write_document('notifications', $ackUid . '_' . $ackMonth . '_dispatch', [
            'employeeUid' => $ackUid,
            'title' => 'Monthly summary sent for acknowledgement',
            'detail' => 'Your ' . $ackMonthLabel . ' performance summary is ready for acknowledgement.',
            'type' => 'info',
            'createdAt' => date('c'),
        ]);

        unset($_SESSION['dashboard_live_data'], $_SESSION['dashboard_cache_time']);

        record_audit_event(
            'acknowledgement_dispatched',
            'Sent monthly summary for acknowledgement to ' . $ackUid,
            ['employee' => $ackUid]
        );

        header(
            'Location: employer_dashboard.php?dispatched=' .
            urlencode($ackUid)
        );

        exit;
    } catch (Throwable $e) {
        $dashMessage = 'Could not send the summary. Please try again.';
        $dashMessageType = 'error';
    }
}

if (isset($_GET['dispatched'])) {
    $dashMessage = 'Monthly summary sent for acknowledgement.';
    $dashMessageType = 'success';
}

/* assign_course removed: direct assignment bypassed the manuscript's
 * review-before-send step (P.121). See review_recommendations.php approve. */

/*
|--------------------------------------------------------------------------
| Dashboard cache
|--------------------------------------------------------------------------
*/

$cacheKey = 'dashboard_live_data';
$cacheTimeKey = 'dashboard_cache_time';
$cacheTTL = 60;

$liveUsers = [];
$allRatings = [];

$cacheValid =
    isset($_SESSION[$cacheKey]) &&
    isset($_SESSION[$cacheTimeKey]) &&
    (time() - (int) $_SESSION[$cacheTimeKey]) < $cacheTTL;

if ($cacheValid) {
    $liveUsers =
        $_SESSION[$cacheKey]['liveUsers']
        ?? [];

    $allRatings =
        $_SESSION[$cacheKey]['allRatings']
        ?? [];
} else {
    try {
        $allRatings =
            firestore_list_documents('Ratings');
    } catch (Throwable $e) {
        $allRatings = [];
    }

    /*
     * Latest AI plan per employee (manuscript P.121 pipeline output stored
     * on Ratings docs by rate_employee.php). Compact excerpt only — the full
     * plan renders on the review page. Newest ratedAt wins; docs without an
     * aiRecommendations map never displace one that has it.
     */
    $latestAiByUid = [];

    foreach ($allRatings as $ratingDoc) {
        $ratingUid =
            (string) (
                $ratingDoc['employeeUid']
                ?? ''
            );

        $storedAi =
            $ratingDoc['aiRecommendations']
            ?? null;

        if (
            $ratingUid === '' ||
            !is_array($storedAi)
        ) {
            continue;
        }

        $ratedAt =
            (string) (
                $ratingDoc['ratedAt']
                ?? ''
            );

        $planRecs =
            isset($storedAi['training_recommendations']) &&
            is_array($storedAi['training_recommendations'])
            ? array_values(
                $storedAi['training_recommendations']
            )
            : [];

        /*
         * Preference rule: a doc WITH recommendations always beats one
         * without, regardless of recency — otherwise a newer empty fallback
         * (e.g. recorded during a Gemini outage) clobbers an older good AI
         * plan in the card. Between two docs with equal standing, newest wins.
         */
        $newHasRecs = count($planRecs) > 0;
        $oldEntry = $latestAiByUid[$ratingUid] ?? null;
        $oldHasRecs = is_array($oldEntry) && !empty($oldEntry['hasRecs']);

        if (
            $oldEntry !== null &&
            (
                ($oldHasRecs && !$newHasRecs) ||
                (
                    $oldHasRecs === $newHasRecs &&
                    strcmp((string) $oldEntry['ratedAt'], $ratedAt) >= 0
                )
            )
        ) {
            continue;
        }

        $topRec =
            $planRecs[0]
            ?? null;

        $latestAiByUid[$ratingUid] = [
            'ratedAt' => $ratedAt,
            'status' =>
                (string) (
                    $storedAi['status']
                    ?? ''
                ),
            'generated_by' =>
                (string) (
                    $storedAi['generated_by']
                    ?? 'fallback'
                ),
            'model_version' =>
                (string) (
                    $storedAi['model_version']
                    ?? 'unknown'
                ),
            'summary' =>
                (string) (
                    $storedAi['summary']
                    ?? ''
                ),
            'error' =>
                (string) (
                    $storedAi['error']
                    ?? ''
                ),
            'top' => is_array($topRec)
                ? [
                    'competency_area' =>
                        (string) (
                            $topRec['competency_area']
                            ?? ''
                        ),
                    'training_type' =>
                        (string) (
                            $topRec['training_type']
                            ?? ''
                        ),
                    'timeline' =>
                        (string) (
                            $topRec['timeline']
                            ?? ''
                        ),
                    'description' =>
                        (string) (
                            $topRec['description']
                            ?? ''
                        ),
                ]
                : null,
            'hasRecs' =>
                count($planRecs) > 0,
        ];
    }

    try {
        $docs =
            firestore_list_documents('Users');

        foreach ($docs as $doc) {
            $roleKey =
                normalize_role_key(
                    $doc['role'] ?? null
                );

            /*
             * The Employer dashboard is specifically about
             * probationary staff. Do not count supervisors
             * or other employee types as probationary.
             */
            if ($roleKey !== 'probationary') {
                continue;
            }

            $createdAt =
                $doc['createdAt']
                ?? '';

            $createdTime =
                $createdAt
                ? strtotime($createdAt)
                : false;

            $daysSince =
                $createdTime
                ? max(
                    0,
                    (int) floor(
                        (time() - $createdTime) /
                        86400
                    )
                )
                : 0;

            $probationPeriodDays = max(1, (int) ($doc['probationPeriodDays'] ?? 180));

            $daysLeft =
                $createdTime
                ? max(
                    0,
                    $probationPeriodDays - $daysSince
                )
                : 0;

            $progress =
                $createdTime
                ? min(
                    100,
                    (int) round(
                        ($daysSince / $probationPeriodDays) *
                        100
                    )
                )
                : 0;

            $email =
                $doc['email']
                ?? '';

            $name =
                $doc['name']
                ?? $email
                ?? 'Unknown';

            $uid =
                $doc['uid']
                ?? '';

            $industry =
                $doc['industry']
                ?? 'retail';

            /*
             * Use the actual KPI summary.
             */
            $summary =
                employee_kpi_summary(
                    $allRatings,
                    $uid,
                    $industry
                );

            $hasScore =
                !empty($summary['hasData']);

            $score =
                $hasScore
                ? (float) $summary['score']
                : null;

            /*
             * Use the real target average whenever available.
             * Fall back only if the KPI summary does not provide one.
             */
            $targetAvg =
                isset($summary['targetAvg'])
                ? (float) $summary['targetAvg']
                : 4.2;

            $targetAvg =
                $targetAvg > 0
                ? $targetAvg
                : 4.2;

            if ($hasScore) {
                $stars =
                    max(
                        0,
                        min(
                            5,
                            (int) round($score)
                        )
                    );

                $meetsTarget =
                    $score >= $targetAvg;

                $status =
                    $meetsTarget
                    ? 'On Track'
                    : 'Needs Review';

                $statusClass =
                    $meetsTarget
                    ? 'status-good'
                    : 'status-warning';

                $statusKey =
                    $meetsTarget
                    ? 'on-track'
                    : 'needs-review';

                $accentColor =
                    $meetsTarget
                    ? 'var(--color-success)'
                    : 'var(--color-warning)';
            } else {
                $stars = 0;
                $status = 'No Ratings Yet';
                $statusClass = 'status-neutral';
                $statusKey = 'no-data';
                $accentColor = 'var(--color-neutral)';
            }

            /*
             * Nearing deadline takes priority over On Track.
             */
            if (
                $daysLeft > 0 &&
                $daysLeft <= 30 &&
                $statusKey === 'on-track'
            ) {
                $status = 'Needs Review';
                $statusClass = 'status-warning';
                $statusKey = 'needs-review';
                $accentColor = 'var(--color-warning)';
            }

            /*
             * Employees with sufficient probationary tenure
             * and a qualifying KPI score are ready for regularization.
             */
            if (
                $daysSince >= max(1, $probationPeriodDays - 30) &&
                $hasScore &&
                $score >= $targetAvg
            ) {
                $status = 'Ready for regularization';
                $statusClass = 'status-ready';
                $statusKey = 'ready-for-reg';
                $accentColor = '#1f2940';
            }

            $liveUsers[] = [
                'uid' => $uid,

                'name' => $name,

                'role' =>
                    display_role_label(
                        $doc['role'] ?? null
                    ),

                'initials' => employer_avatar_initials($name),

                'day' =>
                    pf_day($daysSince),

                'daysSince' =>
                    $daysSince,

                'periodDays' =>
                    $probationPeriodDays,

                'daysLeft' =>
                    $daysLeft . ' days left',

                'daysLeftValue' =>
                    $daysLeft,

                'progress' => $progress,

                'score' => $score,

                'targetAvg' => $targetAvg,

                'hasScore' => $hasScore,

                'stars' => $stars,

                'status' => $status,

                'statusClass' =>
                    $statusClass,

                'statusKey' =>
                    $statusKey,

                'accentColor' =>
                    $accentColor,

                'email' => $email,

                'createdAt' =>
                    $createdAt,

                'assignedTraining' =>
                    $doc['assignedTraining']
                    ?? null,

                'assignedTrainingAt' =>
                    $doc['assignedTrainingAt']
                    ?? null,

                'queueDismissedAt' =>
                    $doc['queueDismissedAt']
                    ?? null,

                'lastRatedAt' =>
                    $summary['lastRatedAt']
                    ?? null,

                'aiPlan' =>
                    $latestAiByUid[$uid]
                    ?? null,
            ];
        }
    } catch (Throwable $e) {
        $liveUsers = [];
    }

    /*
     * Cache only the derived rows. The raw $allRatings array used to be
     * stored here too, which serialized the entire Ratings collection into
     * the PHP session file on every cache miss (slow session read/write +
     * longer session-lock hold on every subsequent request). Summaries are
     * recomputed from a fresh Ratings list on the next miss instead.
     */
    $_SESSION[$cacheKey] = [
        'liveUsers' =>
            $liveUsers,
    ];

    $_SESSION[$cacheTimeKey] =
        time();
}

/*
 * Urgency order: soonest regularization deadline first, so the most urgent
 * employee is always row one. Applied here — before metrics, palette index,
 * badges, and the insight queue derive — so every consumer shares the
 * order. Reorders loaded rows only: zero new reads.
 */
usort(
    $liveUsers,
    static function ($a, $b): int {
        $da = $a['daysLeftValue'] ?? null;
        $db = $b['daysLeftValue'] ?? null;

        if ($da === null && $db === null) {
            return 0;
        }
        if ($da === null) {
            return 1;
        }
        if ($db === null) {
            return -1;
        }

        return (int) $da <=> (int) $db;
    }
);

/*
 |--------------------------------------------------------------------------
 | Dashboard metrics
 |--------------------------------------------------------------------------
 */

$probationaryCount =
    count($liveUsers);

$nearDeadlineCount = 0;

$scoreTotal = 0.0;

$scoredCount = 0;

foreach ($liveUsers as $user) {
    if (
        isset($user['daysLeftValue']) &&
        $user['daysLeftValue'] <= 30 &&
        $user['daysLeftValue'] > 0
    ) {
        $nearDeadlineCount++;
    }

    if ($user['hasScore']) {
        $scoreTotal +=
            (float) $user['score'];

        $scoredCount++;
    }
}

$overallPerformance =
    $scoredCount > 0
    ? $scoreTotal / $scoredCount
    : null;

$metrics = [
    [
        'label' =>
            'Total Probationary',

        'value' =>
            (string) $probationaryCount,

        'badge' =>
            'Live',

        'tone' =>
            'positive',

        'iconClass' =>
            'icon-warm',

        'icon' =>
            'users',
    ],
    [
        'label' =>
            'Nearing Deadline',

        'value' =>
            (string) $nearDeadlineCount,

        'badge' =>
            '< 30 days',

        'tone' =>
            'warning',

        'iconClass' =>
            'icon-gold',

        'icon' =>
            'hourglass',
    ],
    [
        'label' =>
            'Overall Performance',

        'value' =>
            $overallPerformance !== null
            ? number_format(
                $overallPerformance,
                1
            )
            : '-',

        'suffix' =>
            $overallPerformance !== null
            ? '/ 5.0'
            : '',

        'badge' =>
            $overallPerformance !== null
            ? 'Average'
            : 'No Ratings Yet',

        'tone' =>
            'neutral',

        'iconClass' =>
            'icon-mint',

        'icon' =>
            'trend',
    ],
];

/*
|--------------------------------------------------------------------------
| Nearest regularization deadline
|--------------------------------------------------------------------------
*/

$nearestDeadlineDays = null;
$nearestDeadlineName = null;

foreach ($liveUsers as $user) {
    $days =
        (int) (
            $user['daysLeftValue']
            ?? 0
        );

    if (
        $days > 0 &&
        (
            $nearestDeadlineDays === null ||
            $days < $nearestDeadlineDays
        )
    ) {
        $nearestDeadlineDays = $days;
        $nearestDeadlineName = (string) ($user['name'] ?? '');
    }
}

/*
 * Automated milestone alerts (manuscript 150/165/178-day tracker).
 * Runs on dashboard load; idempotent by document ID ({uid}_milestone_{m});
 * honors the employer's notification preference (default on). The
 * notification document IS the record, so no audit event is written.
 */
$notifyMilestonesPref = true;

try {
    if (!empty($_SESSION['uid'])) {
        $prefDoc = firestore_get_document('Users', $_SESSION['uid']) ?? [];

        if (is_array($prefDoc) && array_key_exists('notifyMilestones', $prefDoc)) {
            $notifyMilestonesPref = (bool) $prefDoc['notifyMilestones'];
        }
    }
} catch (Throwable $e) {
    // Preference read failure keeps alerts on (fail-open for deadlines).
}

if ($notifyMilestonesPref) {
    try {
        $seenMilestones = [];

        foreach (firestore_list_documents('notifications') as $note) {
            if (isset($note['employeeUid'], $note['milestoneDay'])) {
                $seenMilestones[
                    (string) $note['employeeUid'] . '|' . (string) $note['milestoneDay']
                ] = true;
            }
        }

        foreach ($liveUsers as $user) {
            $period = max(1, (int) ($user['periodDays'] ?? 180));
            $since = (int) ($user['daysSince'] ?? 0);
            $left = (int) ($user['daysLeftValue'] ?? 0);

            foreach ([150, 165, 178] as $milestoneDay) {
                if ($milestoneDay >= $period || $since < $milestoneDay) {
                    continue;
                }

                $seenKey = (string) ($user['uid'] ?? '') . '|' . (string) $milestoneDay;

                if (isset($seenMilestones[$seenKey])) {
                    continue;
                }

                firestore_write_document(
                    'notifications',
                    (string) ($user['uid'] ?? '') . '_milestone_' . (string) $milestoneDay,
                    [
                        'employeeUid' => $user['uid'] ?? '',
                        'milestoneDay' => $milestoneDay,
                        'title' => 'Probation milestone: day ' . $milestoneDay,
                        'detail' => ($user['name'] ?? 'A probationer') .
                            ' reached day ' . $milestoneDay .
                            ' of ' . $period .
                            ' (' . $left . ' days left).',
                        'type' => 'deadline',
                        'createdAt' => date('c'),
                    ]
                );

                $seenMilestones[$seenKey] = true;
            }
        }
    } catch (Throwable $e) {
        error_log('Dashboard milestone alerts failed: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| Insight / intervention recommendation
|--------------------------------------------------------------------------
*/

$evaluations =
    $liveUsers;

/*
 * Ranked attention queue: scored employees classified needs_improvement or
 * critical_gap (meets_expectations never enters), worst gap first, top 3.
 * Replaces the old single-employee insight so staff beyond the first are
 * no longer invisible.
 */
$insightQueue = [];

foreach ($liveUsers as $user) {
    if (!$user['hasScore']) {
        continue;
    }

    // Employer triage: dismissed cards stay out until a newer rating
    // resurfaces them (queue_is_dismissed). Plan status untouched.
    if (
        queue_is_dismissed(
            ['queueDismissedAt' => $user['queueDismissedAt'] ?? null],
            $user['lastRatedAt'] ?? null
        )
    ) {
        continue;
    }

    $target =
        (float) (
            $user['targetAvg']
            ?? 4.2
        );

    $score =
        (float) $user['score'];

    /*
     * Manuscript threshold semantics (same buffer rule as
     * kpi_status_for_score): meets_expectations never enters the queue.
     * Only needs_improvement and critical_gap do.
     */
    if ($score >= $target) {
        continue;
    }

    $gap =
        $target - $score;

    $insightQueue[] = [
        'user' => $user,
        'gap' => $gap,
        'tone' =>
            $score < $target - 0.8
            ? 'critical'
            : 'needs',
    ];
}

usort(
    $insightQueue,
    static fn($a, $b) =>
        $b['gap'] <=> $a['gap']
);

$insightQueue =
    array_slice(
        $insightQueue,
        0,
        3
    );

// Still-dismissed rows (flag set, no resurfacing rating) for the
// "Show dismissed" toggle. Restoring unsets the flag; the plan
// itself was never touched.
$dismissedQueue = [];

foreach ($liveUsers as $user) {
    if (
        empty($user['queueDismissedAt']) ||
        !queue_is_dismissed(
            ['queueDismissedAt' => $user['queueDismissedAt']],
            $user['lastRatedAt'] ?? null
        )
    ) {
        continue;
    }

    $dismissedQueue[] = $user;
}

$anyScored = false;

foreach ($liveUsers as $scoredUser) {
    if (!empty($scoredUser['hasScore'])) {
        $anyScored = true;

        break;
    }
}

/*
 * Shell badges + command palette index. Counts come from the rows already
 * loaded above (zero new reads); the palette reuses the same rows.
 */
$_SESSION['pf_nav_employees'] =
    $probationaryCount;

$_SESSION['pf_nav_deadline'] =
    $nearDeadlineCount;

$pfPaletteIndex = [];

foreach (
    array_slice(
        $liveUsers,
        0,
        60
    ) as $paletteUser
) {
    $paletteUid =
        (string) (
            $paletteUser['uid']
            ?? ''
        );

    if ($paletteUid === '') {
        continue;
    }

    $pfPaletteIndex[] = [
        'label' =>
            $paletteUser['name'],
        'sub' =>
            $paletteUser['status'] .
            ' · View profile',
        'href' =>
            'employee_view.php?uid=' .
            urlencode($paletteUid),
    ];

    $pfPaletteIndex[] = [
        'label' =>
            'Rate ' .
            $paletteUser['name'],
        'sub' =>
            'Weekly KPI rating',
        'href' =>
            'rate_employee.php?employee=' .
            urlencode($paletteUid),
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

$unratedCount = $probationaryCount - $scoredCount;

$tabCounts = [
    'all' => $probationaryCount,
    'needs-review' => 0,
    'on-track' => 0,
    'ready-for-reg' => 0,
    'due-soon' => 0,
];

foreach ($liveUsers as $u) {
    $sk = $u['statusKey'] ?? '';
    if (isset($tabCounts[$sk])) {
        $tabCounts[$sk]++;
    }
    $dLeft = (int) ($u['daysLeftValue'] ?? 0);
    if ($dLeft > 0 && $dLeft <= 30) {
        $tabCounts['due-soon']++;
    }
}

$ivData = [];
foreach ($insightQueue as $item) {
    $u = $item['user'];
    $uScore = (float) ($u['score'] ?? 0);
    $uTarget = (float) ($u['targetAvg'] ?? 4.2);
    $uGap = $uScore - $uTarget;
    $queueAi = $u['aiPlan'] ?? null;
    $queueAssigned = !empty($u['assignedTraining']);
    $queuePending = !$queueAssigned
        && is_array($queueAi)
        && ($queueAi['status'] ?? '') === 'pending_approval'
        && !empty($queueAi['hasRecs']);
    $queueApproved = !$queueAssigned
        && is_array($queueAi)
        && ($queueAi['status'] ?? '') === 'approved';

    $actLabel = 'Rate to refresh';
    $actHref = 'rate_employee.php?employee=' . urlencode($u['uid']);
    if ($queuePending) {
        $actLabel = 'Review plan';
        $actHref = 'review_recommendations.php';
    } elseif ($queueAssigned) {
        $actLabel = 'Assigned';
        $actHref = 'employee_view.php?uid=' . urlencode($u['uid']);
    } elseif ($queueApproved) {
        $actLabel = 'Published';
        $actHref = 'employee_view.php?uid=' . urlencode($u['uid']);
    }

    $ivData[] = [
        'n' => (string) ($u['name'] ?? 'Unknown'),
        'uid' => (string) ($u['uid'] ?? ''),
        's' => round($uScore, 1),
        't' => round($uTarget, 1),
        'g' => round($uGap, 1),
        'a' => $actLabel,
        'href' => 'employee_view.php?uid=' . urlencode($u['uid']),
        'actHref' => $actHref,
    ];
}

$eData = [];
foreach ($liveUsers as $u) {
    $uScore = (float) ($u['score'] ?? 0);
    $hasScore = !empty($u['hasScore']);
    $sk = (string) ($u['statusKey'] ?? '');
    $st = 'good';
    if (!$hasScore) {
        $st = 'unrated';
    } elseif ($sk === 'needs-review') {
        $st = 'review';
    } elseif ($sk === 'ready-for-reg') {
        $st = 'ready';
    } else {
        $st = 'good';
    }

    $period = max(1, (int) ($u['periodDays'] ?? 180));
    $day = (int) ($u['daysSince'] ?? 0);
    $left = (int) ($u['daysLeftValue'] ?? max(0, $period - $day));

    $eData[] = [
        'n' => (string) ($u['name'] ?? 'Unknown'),
        'uid' => (string) ($u['uid'] ?? ''),
        'day' => $day,
        'left' => $left,
        's' => round($uScore, 1),
        'hasScore' => $hasScore,
        'st' => $st,
    ];
}

$dismissedUids = [];
foreach ($dismissedQueue as $du) {
    if (!empty($du['uid'])) {
        $dismissedUids[] = (string) $du['uid'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <?php employer_brand_head(); ?>
    <title>Probationary · Performa</title>
    <meta name="description"
        content="Employer KPI dashboard for probationary employee evaluation and training recommendations." />
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
        <symbol id="i-search" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></symbol>
        <symbol id="i-plus" viewBox="0 0 24 24"><path d="M5 12h14M12 5v14"/></symbol>
        <symbol id="i-up" viewBox="0 0 24 24"><path d="m5 12 7-7 7 7M12 19V5"/></symbol>
        <symbol id="i-x" viewBox="0 0 24 24"><path d="M18 6 6 18M6 6l12 12"/></symbol>
    </svg>

    <div class="app-shell">

        <?php employer_render_shell('Dashboard'); ?>

        <main class="main dashboard-page" id="dashboard">
            <div class="cq"><div class="wrap">

                <header>
                    <button class="icon-button pf-menu-btn" type="button" data-sidebar-toggle aria-label="Open navigation" aria-expanded="false">
                        <svg class="icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <line x1="4" y1="7" x2="20" y2="7"/><line x1="4" y1="12" x2="20" y2="12"/><line x1="4" y1="17" x2="20" y2="17"/>
                        </svg>
                    </button>
                    <div>
                        <h1>Probationary</h1>
                        <p class="sub"><span class="mono"><?php echo (int) $probationaryCount; ?></span> on probation · <span class="mono"><?php echo count($insightQueue); ?></span> need intervention · average <span class="mono"><?php echo $overallPerformance !== null ? number_format($overallPerformance, 1) : 'N/A'; ?></span> / 5.0 · <span class="mono"><?php echo (int) $unratedCount; ?></span> unrated</p>
                    </div>
                    <div class="hdr-r">
                        <button type="button" class="link" id="exportEvaluationsBtn">Export CSV</button>
                        <a href="add_employee.php" class="btn" style="text-decoration:none;"><svg class="i"><use href="#i-plus"/></svg>Add employee</a>
                    </div>
                </header>

                <?php if ($dashMessage !== ''): ?>
                    <div class="alert alert-<?php echo $dashMessageType === 'error' ? 'error' : 'success'; ?>" role="status" style="margin-top:var(--s4);margin-bottom:var(--s2);">
                        <?php echo htmlspecialchars($dashMessage, ENT_QUOTES); ?>
                    </div>
                <?php endif; ?>

                <section class="iv" id="iv" aria-labelledby="iv-h" <?php if (empty($ivData)) echo 'hidden'; ?>>
                    <h2 id="iv-h">Intervention suggested <span class="mono" id="ivn"><?php echo count($ivData); ?></span></h2>
                    <p class="hint">Scores furthest below target, worst first.</p>
                    <div class="ivl" id="ivl"></div>
                </section>

                <div class="bar">
                    <div class="tabs" role="tablist" id="tabs" aria-label="Status"></div>
                    <div class="search"><svg class="i"><use href="#i-search"/></svg><input id="q" class="f" type="search" placeholder="Search employees" aria-label="Search employees"></div>
                </div>

                <div class="head">
                    <button type="button" class="sort" data-k="name">Employee<svg class="i"><use href="#i-up"/></svg></button>
                    <button type="button" class="sort" data-k="day">Timeline · 180 days<svg class="i"><use href="#i-up"/></svg></button>
                    <button type="button" class="sort" data-k="score">KPI<svg class="i"><use href="#i-up"/></svg></button>
                    <button type="button" class="sort" data-k="status">Status<svg class="i"><use href="#i-up"/></svg></button>
                    <span></span>
                </div>

                <div id="rows"></div>
                <div class="foot" id="foot"></div>

            </div></div>
        </main>

    </div>

    <div class="toast" id="toast" role="status"><span id="tmsg"></span><button type="button" id="undo">Undo</button></div>

    <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

    <script>
    window.__pfIndex = <?php echo $pfPaletteJson !== false ? $pfPaletteJson : '[]'; ?>;

    var IV = <?php echo json_encode($ivData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var E = <?php echo json_encode($eData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var LAB = { review: "Needs review", good: "On track", ready: "Ready for reg", unrated: "Unrated" };
    var COL = { review: "var(--warn)", good: "var(--good)", ready: "var(--accent)", unrated: "var(--ink-3)" };
    var st = { t: "all", q: "", k: "score", dir: "asc" };
    var dismissed = <?php echo json_encode($dismissedUids, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;
    var last = null, timer;
    var $ = function(i) { return document.getElementById(i); };
    var I = function(id) { return '<svg class="i"><use href="#i-' + id + '"/></svg>'; };
    var csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    function ini(n) {
      var w = (n || '').trim().split(/\s+/);
      return (w.length > 1 ? w[0][0] + w[1][0] : (w[0] ? w[0][0] : '?')).toUpperCase();
    }

    function renderIV() {
      var list = IV.filter(function(r) { return dismissed.indexOf(r.uid) < 0; });
      $("iv").hidden = !list.length;
      $("ivn").textContent = list.length;
      $("ivl").innerHTML = list.map(function(r) {
        var sPct = Math.min(100, Math.max(0, (r.s / 5 * 100)));
        var tPct = Math.min(100, Math.max(0, (r.t / 5 * 100)));
        var gapStr = r.g < 0 ? ("−" + Math.abs(r.g).toFixed(1)) : ("+" + r.g.toFixed(1));
        return '<div class="ir"><div class="who"><span class="av">' + ini(r.n) + '</span><a class="nm" href="' + r.href + '">' + r.n + '</a></div>'
          + '<div class="meter" aria-hidden="true"><i style="width:' + sPct + '%"></i><u style="left:' + tPct + '%"></u></div>'
          + '<span class="vs">' + r.s.toFixed(1) + ' vs ' + r.t.toFixed(1) + ' target</span><span class="gap">' + gapStr + '</span>'
          + '<a class="sbtn" href="' + r.actHref + '" style="text-decoration:none;">' + r.a + '</a>'
          + '<button type="button" class="ibtn" data-dis="' + r.uid + '" data-name="' + r.n + '" data-tip="Dismiss" aria-label="Dismiss suggestion for ' + r.n + '">' + I("x") + '</button></div>';
      }).join("");
    }

    function renderTabs() {
      var c = { all: E.length, review: 0, good: 0, ready: 0, unrated: 0 };
      E.forEach(function(r) { if (c[r.st] !== undefined) c[r.st]++; });
      var defs = [["all", "All"], ["review", "Needs review"], ["good", "On track"], ["ready", "Ready for reg"], ["unrated", "Unrated"]];
      $("tabs").innerHTML = defs.filter(function(d) { return d[0] === "all" || c[d[0]] > 0; }).map(function(d) {
        return '<button type="button" class="tab" role="tab" aria-selected="' + (st.t === d[0]) + '" data-t="' + d[0] + '">' + d[1] + '<span class="n">' + c[d[0]] + '</span></button>';
      }).join("");
    }

    function val(r, k) {
      return k === "name" ? r.n.toLowerCase() : k === "day" ? r.day : k === "score" ? (r.hasScore ? r.s : -1) : r.st;
    }

    function render() {
      var rows = E.filter(function(r) {
        if (st.t !== "all" && r.st !== st.t) return false;
        if (st.q && (r.n + " " + (LAB[r.st] || "")).toLowerCase().indexOf(st.q) < 0) return false;
        return true;
      });
      rows.sort(function(a, b) {
        var x = val(a, st.k), y = val(b, st.k);
        return (x < y ? -1 : x > y ? 1 : 0) * (st.dir === "asc" ? 1 : -1);
      });
      $("rows").innerHTML = rows.map(function(r) {
        var totalDays = r.day + r.left;
        var pct = totalDays > 0 ? Math.min(100, Math.round(r.day / totalDays * 100)) : 0;
        return '<div class="row" data-uid="' + r.uid + '"><div class="who"><span class="av">' + ini(r.n) + '</span><a class="nm" href="employee_view.php?uid=' + encodeURIComponent(r.uid) + '">' + r.n + '</a></div>'
          + '<div class="prob"><div class="t"><span><b class="mono">Day ' + r.day + '</b>&nbsp;of ' + totalDays + '</span><span class="mono">' + r.left + ' left</span></div><div class="track"><i style="width:' + pct + '%"></i></div></div>'
          + '<span class="score">' + (r.hasScore ? (r.s.toFixed(1) + ' <small>/ 5.0</small>') : '<small style="color:var(--ink-3)">Unrated</small>') + '</span>'
          + '<span class="status"><span class="dot" style="background:' + COL[r.st] + '"></span>' + LAB[r.st] + '</span>'
          + '<a class="rate" href="rate_employee.php?employee=' + encodeURIComponent(r.uid) + '">Rate</a></div>';
      }).join("") || '<div class="empty"><b>No employees match</b>Try a different search or status.</div>';
      $("foot").textContent = (st.q ? rows.length + " results" : "Showing " + rows.length + " of " + (st.t === "all" ? E.length : rows.length));
      document.querySelectorAll(".sort").forEach(function(b) {
        var on = b.dataset.k === st.k;
        b.toggleAttribute("data-on", on);
        b.dataset.dir = on ? st.dir : "asc";
      });
    }

    function toast(msg) {
      $("tmsg").textContent = msg;
      $("toast").classList.add("on");
      clearTimeout(timer);
      timer = setTimeout(function() { $("toast").classList.remove("on"); }, 6000);
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

    document.querySelector(".head").addEventListener("click", function(e) {
      var b = e.target.closest(".sort");
      if (!b) return;
      if (st.k === b.dataset.k) {
        st.dir = st.dir === "asc" ? "desc" : "asc";
      } else {
        st.k = b.dataset.k;
        st.dir = "asc";
      }
      render();
    });

    $("ivl").addEventListener("click", function(e) {
      var b = e.target.closest("[data-dis]");
      if (!b) return;
      var uid = b.dataset.dis;
      var name = b.dataset.name;
      last = { uid: uid, name: name };
      dismissed.push(uid);
      renderIV();
      toast("Dismissed suggestion for " + name);

      var fd = new FormData();
      fd.append("action", "queue_dismiss");
      fd.append("uid", uid);
      fd.append("csrf_token", csrfToken);
      fetch("employer_dashboard.php", { method: "POST", body: fd });
    });

    $("undo").addEventListener("click", function() {
      if (!last) return;
      var restoredUid = last.uid;
      dismissed = dismissed.filter(function(u) { return u !== restoredUid; });
      renderIV();
      $("toast").classList.remove("on");

      var fd = new FormData();
      fd.append("action", "queue_restore");
      fd.append("uid", restoredUid);
      fd.append("csrf_token", csrfToken);
      fetch("employer_dashboard.php", { method: "POST", body: fd });
      last = null;
    });

    $("exportEvaluationsBtn").addEventListener("click", function() {
      var lines = [["Employee", "Timeline", "Days Left", "KPI Score", "Status"]];
      E.forEach(function(r) {
        var scoreStr = r.hasScore ? r.s.toFixed(1) : "Unrated";
        var statusStr = LAB[r.st] || r.st;
        lines.push([r.n, "Day " + r.day + " of " + (r.day + r.left), r.left + " left", scoreStr, statusStr].map(function(v) {
          return '"' + String(v).replace(/"/g, '""') + '"';
        }).join(","));
      });
      var blob = new Blob([lines.join("\n")], { type: "text/csv;charset=utf-8;" });
      var url = URL.createObjectURL(blob);
      var a = document.createElement("a");
      a.href = url;
      a.download = "probationary_employees.csv";
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    });

    renderIV();
    renderTabs();
    render();
    </script>

</body>

</html>
