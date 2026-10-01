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
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
    <?php employer_brand_head(); ?>
    <title>Dashboard · Performa</title>
    <meta name="description"
        content="Employer KPI dashboard for probationary employee evaluation and training recommendations." />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=JetBrains+Mono:wght@400;500;600&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap"
        rel="stylesheet" />
    <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('styles.css'), ENT_QUOTES); ?>" />
    <link rel="stylesheet" href="<?php echo htmlspecialchars(employer_asset('../ui-refresh.css'), ENT_QUOTES); ?>" />
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES); ?>" />
</head>

<body>

    <div class="app-shell">

        <?php employer_render_shell('Dashboard'); ?>

        <main class="main dashboard-page" id="dashboard">
            <div class="in">

                <div class="top">
                    <div>
                        <h1 id="h1">Probationary overview</h1>
                        <p class="sub">Track and evaluate employees approaching regularization.</p>
                    </div>
                    <div class="tr">
                        <button type="button" class="btn txt" id="exportEvaluationsBtn">Export CSV</button>
                        <a class="btn primary" href="add_employee.php" style="text-decoration:none;">
                            + Add employee
                        </a>
                    </div>
                </div>

                <?php if ($dashMessage !== ''): ?>
                    <div class="alert alert-<?php echo $dashMessageType === 'error' ? 'error' : 'success'; ?>" role="status" style="margin-bottom:20px;">
                        <?php echo htmlspecialchars($dashMessage, ENT_QUOTES); ?>
                    </div>
                <?php endif; ?>

                <!-- 1. 4 Square Stat Cards -->
                <section class="stats" id="stats" aria-label="Key probationary metrics">
                    <div class="st">
                        <div class="l">Probationary</div>
                        <div class="v num"><?php echo (int) $probationaryCount; ?></div>
                        <div class="n">
                            <?php echo $unratedCount > 0 ? (int) $unratedCount . ' without ratings' : 'All employees rated'; ?>
                        </div>
                    </div>

                    <div class="st">
                        <div class="l">Due within 30 days</div>
                        <div class="v num"><?php echo (int) $nearDeadlineCount; ?></div>
                        <div class="n">
                            <?php if ($nearestDeadlineDays !== null): ?>
                                Next: <?php echo htmlspecialchars($nearestDeadlineName, ENT_QUOTES); ?>, <?php echo (int) $nearestDeadlineDays; ?> days
                            <?php else: ?>
                                None nearing deadline
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="st">
                        <div class="l">Needs attention</div>
                        <div class="v num" style="color:var(--bad)"><?php echo count($insightQueue); ?></div>
                        <div class="n">
                            <?php echo count($insightQueue) > 0 ? 'Intervention suggested' : 'All on track'; ?>
                        </div>
                    </div>

                    <div class="st">
                        <div class="l">Average score</div>
                        <div class="v num" style="color:var(--ok)">
                            <?php echo $overallPerformance !== null ? number_format($overallPerformance, 1) : '-'; ?><small style="font-size:14px;color:var(--mut);font-weight:400;margin-left:4px;">/ 5.0</small>
                        </div>
                        <div class="n">
                            <?php echo (int) $scoredCount; ?> rated of <?php echo (int) $probationaryCount; ?>
                        </div>
                    </div>
                </section>

                <!-- 2. Needs Your Attention Queue (Square Card) -->
                <?php if (!empty($insightQueue) || !empty($dismissedQueue)): ?>
                    <section class="cd" style="padding:20px;margin-bottom:24px;" aria-labelledby="queueHeading">
                        <div class="sh" style="margin-bottom:14px;display:flex;align-items:center;justify-content:space-between;">
                            <div style="display:flex;align-items:center;gap:10px;">
                                <h2 id="queueHeading" style="font-size:18px;font-weight:600;margin:0;">Needs your attention</h2>
                                <span class="pill status-pill-needs-review" style="font-weight:600;"><?php echo count($insightQueue); ?></span>
                            </div>
                        </div>

                        <?php if (!empty($insightQueue)): ?>
                            <div class="attention-list" role="list">
                                <?php foreach ($insightQueue as $queueEntry): ?>
                                    <?php
                                    $queueUser = $queueEntry['user'];
                                    $gap = (float) $queueEntry['gap'];
                                    $score = (float) $queueUser['score'];
                                    $target = (float) ($queueUser['targetAvg'] ?? 4.2);
                                    $queueAi = $queueUser['aiPlan'] ?? null;
                                    $queueTop = is_array($queueAi) && is_array($queueAi['top'] ?? null)
                                        ? $queueAi['top']
                                        : null;
                                    $queueAssigned = !empty($queueUser['assignedTraining']);
                                    $queuePending = !$queueAssigned
                                        && is_array($queueAi)
                                        && ($queueAi['status'] ?? '') === 'pending_approval'
                                        && !empty($queueAi['hasRecs']);
                                    $queueApproved = !$queueAssigned
                                        && is_array($queueAi)
                                        && ($queueAi['status'] ?? '') === 'approved';
                                    ?>
                                    <div class="attention-row" role="listitem" data-uid="<?php echo htmlspecialchars($queueUser['uid'], ENT_QUOTES); ?>">
                                        <div class="attention-row-main">
                                            <div class="attention-info">
                                                <div class="av" aria-hidden="true"><?php echo htmlspecialchars($queueUser['initials'], ENT_QUOTES); ?></div>
                                                <a href="employee_view.php?uid=<?php echo urlencode($queueUser['uid']); ?>" class="attention-name">
                                                    <?php echo htmlspecialchars($queueUser['name'], ENT_QUOTES); ?>
                                                </a>
                                                <div class="attention-metrics">
                                                    <span class="score-vs-target num">
                                                        <?php echo number_format($score, 1); ?> vs <?php echo number_format($target, 1); ?> target
                                                    </span>
                                                    <span class="metric-separator" aria-hidden="true">·</span>
                                                    <span class="gap-danger num">-<?php echo number_format(abs($gap), 1); ?></span>
                                                </div>
                                            </div>

                                            <div class="attention-actions">
                                                <?php if ($queuePending): ?>
                                                    <a href="review_recommendations.php" class="btn primary btn-sm" style="font-size:12.5px;padding:5px 12px;text-decoration:none;">
                                                        Review plan
                                                    </a>
                                                <?php elseif ($queueAssigned): ?>
                                                    <button type="button" class="btn txt btn-sm" disabled style="opacity:.6;">
                                                        Assigned
                                                    </button>
                                                <?php elseif ($queueApproved): ?>
                                                    <button type="button" class="btn txt btn-sm" disabled style="opacity:.6;">
                                                        Published
                                                    </button>
                                                <?php else: ?>
                                                    <a href="rate_employee.php?employee=<?php echo urlencode($queueUser['uid']); ?>" class="btn tint btn-sm" style="font-size:12.5px;padding:5px 12px;">
                                                        Rate to refresh
                                                    </a>
                                                <?php endif; ?>

                                                <form method="post" class="queue-dismiss-inline-form" style="margin:0;display:inline;">
                                                    <?php echo csrf_field(); ?>
                                                    <input type="hidden" name="action" value="queue_dismiss" />
                                                    <input type="hidden" name="uid" value="<?php echo htmlspecialchars($queueUser['uid'], ENT_QUOTES); ?>" />
                                                    <button type="submit" class="btn x" aria-label="Dismiss <?php echo htmlspecialchars($queueUser['name'], ENT_QUOTES); ?> from attention queue" title="Dismiss">&times;</button>
                                                </form>
                                            </div>
                                        </div>

                                        <details class="attention-rec-disclosure">
                                            <summary style="font-weight:500;">Recommendation</summary>
                                            <div class="attention-rec-box">
                                                <?php if ($queueTop): ?>
                                                    <div class="rec-title">
                                                        <strong>
                                                            <?php
                                                            echo htmlspecialchars(
                                                                ucwords(str_replace('_', ' ', (string) ($queueTop['competency_area'] ?? ''))) .
                                                                ' - ' . (string) ($queueTop['training_type'] ?? ''),
                                                                ENT_QUOTES
                                                            );
                                                            ?>
                                                        </strong>
                                                    </div>
                                                    <?php if (!empty($queueTop['description'])): ?>
                                                        <p style="margin:4px 0 6px;color:var(--mut);font-size:12.5px;"><?php echo htmlspecialchars($queueTop['description'], ENT_QUOTES); ?></p>
                                                    <?php endif; ?>
                                                    <div style="font-size:11.5px;color:var(--mut);">
                                                        <?php if (!empty($queueTop['timeline'])): ?>
                                                            Timeline: <?php echo htmlspecialchars($queueTop['timeline'], ENT_QUOTES); ?> ·
                                                        <?php endif; ?>
                                                        Source: <?php echo htmlspecialchars(($queueAi['generated_by'] ?? '') === 'gemini_api' ? 'RF + Gemini' : (($queueAi['generated_by'] ?? '') === 'rf_only' ? 'RF only' : 'System'), ENT_QUOTES); ?>
                                                        <?php echo htmlspecialchars($queueAi['model_version'] ?? '', ENT_QUOTES); ?>
                                                    </div>
                                                <?php else: ?>
                                                    <p style="margin:0;color:var(--mut);font-size:12.5px;">No AI plan on file yet. An employer rating generates one.</p>
                                                <?php endif; ?>
                                            </div>
                                        </details>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>

                        <?php if (!empty($dismissedQueue)): ?>
                            <details class="queue-dismissed-toggle" style="margin-top:12px;">
                                <summary style="font-size:12.5px;color:var(--mut);cursor:pointer;">Show dismissed (<?php echo count($dismissedQueue); ?>)</summary>
                                <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
                                    <?php foreach ($dismissedQueue as $dismissedUser): ?>
                                        <div style="display:flex;justify-content:space-between;align-items:center;padding:6px 10px;background:var(--hov);border-radius:4px;">
                                            <span style="font-size:13px;"><?php echo htmlspecialchars($dismissedUser['name'], ENT_QUOTES); ?></span>
                                            <form method="post" style="margin:0;">
                                                <?php echo csrf_field(); ?>
                                                <input type="hidden" name="action" value="queue_restore" />
                                                <input type="hidden" name="uid" value="<?php echo htmlspecialchars($dismissedUser['uid'], ENT_QUOTES); ?>" />
                                                <button type="submit" class="btn txt" style="font-size:12px;padding:2px 6px;">Restore</button>
                                            </form>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </details>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>

                <!-- 3. Probationers Table Section (Square Card .cd.tbl) -->
                <section class="cd tbl" aria-labelledby="probationersTitle">
                    <div class="tb">
                        <div class="seg" role="tablist" id="dashboardTabs" aria-label="Filter probationers by status">
                            <button type="button" class="tab-chip" data-filter="all" role="tab" aria-selected="true">
                                All <span class="num"><?php echo (int) $tabCounts['all']; ?></span>
                            </button>
                            <button type="button" class="tab-chip" data-filter="needs-review" role="tab" aria-selected="false">
                                Needs review <span class="num"><?php echo (int) $tabCounts['needs-review']; ?></span>
                            </button>
                            <button type="button" class="tab-chip" data-filter="on-track" role="tab" aria-selected="false">
                                On track <span class="num"><?php echo (int) $tabCounts['on-track']; ?></span>
                            </button>
                            <button type="button" class="tab-chip" data-filter="ready-for-reg" role="tab" aria-selected="false">
                                Ready <span class="num"><?php echo (int) $tabCounts['ready-for-reg']; ?></span>
                            </button>
                            <button type="button" class="tab-chip" data-filter="due-soon" role="tab" aria-selected="false">
                                Due soon <span class="num"><?php echo (int) $tabCounts['due-soon']; ?></span>
                            </button>
                        </div>

                        <div class="f">
                            <input type="search" id="dashboardSearch" placeholder="Search name or status..." autocomplete="off" aria-label="Search probationers" />
                            <select id="dashboardSort" aria-label="Sort probationers">
                                <option value="attention">Needs attention first</option>
                                <option value="days-left">Fewest days left</option>
                                <option value="score-asc">Lowest score</option>
                            </select>
                        </div>
                    </div>

                    <div class="rw rh">
                        <span>Employee</span>
                        <span>Timeline (180 days)</span>
                        <span>KPI score</span>
                        <span>Status</span>
                        <span style="text-align:right;">Action</span>
                    </div>

                    <div id="evaluationRows">
                        <?php if (empty($evaluations)): ?>
                            <div class="empty">
                                No probationary employees found.
                                <div style="margin-top:12px;">
                                    <a class="btn primary" href="add_employee.php" style="text-decoration:none;">Add Employee</a>
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($evaluations as $employee): ?>
                                <?php
                                $dueDays = (int) ($employee['daysLeftValue'] ?? 0);
                                $isDueSoon = ($dueDays > 0 && $dueDays <= 30);
                                ?>
                                <div class="row rw table-data-row" role="row"
                                     data-uid="<?php echo htmlspecialchars($employee['uid'] ?? '', ENT_QUOTES); ?>"
                                     data-search="<?php echo htmlspecialchars(strtolower($employee['name'] . ' ' . $employee['status']), ENT_QUOTES); ?>"
                                     data-filter="<?php echo htmlspecialchars($employee['statusKey'], ENT_QUOTES); ?>"
                                     data-due-soon="<?php echo $isDueSoon ? '1' : '0'; ?>"
                                     data-days-left="<?php echo $dueDays; ?>"
                                     data-score="<?php echo $employee['hasScore'] ? (float) $employee['score'] : -1; ?>">

                                    <div class="e">
                                        <div class="av" aria-hidden="true"><?php echo htmlspecialchars($employee['initials'], ENT_QUOTES); ?></div>
                                        <div class="nm">
                                            <b>
                                                <a href="employee_view.php?uid=<?php echo urlencode($employee['uid']); ?>" class="employee-name-single">
                                                    <?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?>
                                                </a>
                                            </b>
                                            <span class="sm2"><?php echo htmlspecialchars($employee['role'] ?? 'Probationary', ENT_QUOTES); ?></span>
                                        </div>
                                    </div>

                                    <div class="tm">
                                        <div style="display:flex;justify-content:space-between;align-items:baseline;">
                                            <span class="timeline-day num sm2"><?php echo htmlspecialchars($employee['day'], ENT_QUOTES); ?></span>
                                            <span class="timeline-left num sm2"><?php echo htmlspecialchars($employee['daysLeft'], ENT_QUOTES); ?></span>
                                        </div>
                                        <div class="track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $employee['progress']; ?>" aria-label="<?php echo (int) $employee['progress']; ?>% probation completed">
                                            <div class="fill <?php echo $isDueSoon ? 'late' : ''; ?>" style="width: <?php echo (int) $employee['progress']; ?>%;"></div>
                                        </div>
                                    </div>

                                    <div class="sc">
                                        <?php if ($employee['hasScore']): ?>
                                            <div style="display:flex;align-items:baseline;gap:4px;">
                                                <strong class="score-num num" style="font-size:16px;"><?php echo number_format((float) $employee['score'], 1); ?></strong>
                                                <span class="sm2 num">/ 5.0</span>
                                            </div>
                                            <div class="track" style="margin-top:4px;">
                                                <div class="fill" style="width: <?php echo min(100, max(0, (int) round(((float)$employee['score'] / 5.0) * 100))); ?>%;"></div>
                                            </div>
                                        <?php else: ?>
                                            <span class="sm2">No ratings yet</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="stt">
                                        <span class="pill status-pill status-pill-<?php echo htmlspecialchars($employee['statusKey'], ENT_QUOTES); ?>">
                                            <?php echo htmlspecialchars($employee['status'], ENT_QUOTES); ?>
                                        </span>
                                    </div>

                                    <div class="act">
                                        <a href="rate_employee.php?employee=<?php echo urlencode($employee['uid']); ?>" class="btn tint btn-rate-row" aria-label="Rate <?php echo htmlspecialchars($employee['name'], ENT_QUOTES); ?>">
                                            Rate
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="empty" id="noFilterMatches" hidden>
                        No employees match this filter combination.
                        <div style="margin-top: 10px;">
                            <button class="btn txt" type="button" id="clearDashboardFilters">Clear filters</button>
                        </div>
                    </div>

                    <div class="ft">
                        <a class="btn txt" href="employees.php" style="text-decoration:none;">View all probationary staff &rarr;</a>
                    </div>
                </section>

            </div>
        </main>

    </div>

    <script src="<?php echo htmlspecialchars(employer_asset('script.js'), ENT_QUOTES); ?>"></script>

    <script>
        window.__pfIndex = <?php echo $pfPaletteJson !== false ? $pfPaletteJson : '[]'; ?>;
    </script>

</body>

</html>
