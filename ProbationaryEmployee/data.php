<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';
require_once __DIR__ . '/../Employer/includes/collection_cache.php';

require_login();
require_role('probationary_employee');
require_password_reset('probationary_employee_profile.php');

function probationary_uid(): string
{
    return (string) $_SESSION['uid'];
}

function probationary_user(): array
{
    $uid = probationary_uid();
    try {
        $user = firestore_get_document('Users', $uid);
        return is_array($user) ? array_merge(['uid' => $uid], $user) : ['uid' => $uid];
    } catch (Throwable $e) {
        return ['uid' => $uid];
    }
}

function probationary_timeline(array $user): array
{
    $periodDays = max(1, (int) ($user['probationPeriodDays'] ?? 180));
    $hireDate = (string) ($user['hireDate'] ?? $user['createdAt'] ?? '');
    $hireTimestamp = $hireDate ? strtotime($hireDate) : false;
    $daysSinceHire = $hireTimestamp ? max(0, (int) floor((time() - $hireTimestamp) / 86400)) : 0;

    return [
        'periodDays' => $periodDays,
        'daysSinceHire' => $daysSinceHire,
        'daysRemaining' => $hireTimestamp ? max(0, $periodDays - $daysSinceHire) : $periodDays,
    ];
}

function probationary_collection(string $collection): array
{
    // Disk-cached + in-request memoized via the shared helper (L3); the
    // static memo below mirrors it so this function's contract
    // (array-always, never throws) holds even if the helper changes.
    static $memo = [];
    if (isset($memo[$collection])) {
        return $memo[$collection];
    }
    try {
        $result = get_cached_collection($collection, 600);
    } catch (Throwable $e) {
        return [];
    }
    $memo[$collection] = is_array($result) ? $result : [];
    return $memo[$collection];
}

function probationary_owned(array $document, string $uid): bool
{
    foreach (['employeeUid', 'employeeId', 'userUid', 'userId', 'uid', 'probationaryUid'] as $field) {
        if (isset($document[$field]) && (string) $document[$field] === $uid) {
            return true;
        }
    }
    return false;
}

function probationary_owned_documents(string $collection): array
{
    $uid = probationary_uid();
    return array_values(array_filter(probationary_collection($collection), static function (array $document) use ($uid): bool {
        return probationary_owned($document, $uid);
    }));
}

function probationary_date($value, string $fallback = 'Unknown'): string
{
    if (!$value) {
        return $fallback;
    }
    $timestamp = strtotime((string) $value);
    return $timestamp ? date('M j, Y', $timestamp) : (string) $value;
}

function probationary_role_label(array $user): string
{
    return ucwords(str_replace('_', ' ', (string) ($user['role'] ?? 'Probationary Employee')));
}

function probationary_evaluation_score(array $evaluation): ?float
{
    if (isset($evaluation['score']) && is_numeric($evaluation['score'])) {
        return (float) $evaluation['score'];
    }
    if (isset($evaluation['averageScore']) && is_numeric($evaluation['averageScore'])) {
        return (float) $evaluation['averageScore'];
    }
    $scores = $evaluation['scores'] ?? [];
    if (!is_array($scores)) {
        return null;
    }
    $values = array_values(array_filter(array_map('floatval', $scores), static fn(float $score): bool => $score > 0));
    return $values ? array_sum($values) / count($values) : null;
}
