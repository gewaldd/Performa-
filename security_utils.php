<?php
/**
 * Focused security helpers for account credentials: secure password
 * generation plus the self-service password-reset token flow
 * (forgot_password.php request, reset_password.php consume).
 */

/**
 * Generate a random password using PHP's CSPRNG (random_int, backed by
 * random_bytes), not mt_rand()/uniqid()/anything predictable. Guarantees
 * at least one lowercase, one uppercase, one digit, and one symbol, then
 * fills the rest randomly and shuffles — so it's never "random but happens
 * to have no digit". Ambiguous look-alike characters (0/O, 1/l/I) are
 * excluded since a human has to type this in on first login.
 */
function generate_secure_password(int $length = 12): string
{
    if ($length < 8) {
        $length = 8;
    }

    $lower = 'abcdefghijkmnpqrstuvwxyz';
    $upper = 'ABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits = '23456789';
    $symbols = '!@#$%^&*-_=+';
    $all = $lower . $upper . $digits . $symbols;

    $pick = function (string $pool): string {
        return $pool[random_int(0, strlen($pool) - 1)];
    };

    $chars = [$pick($lower), $pick($upper), $pick($digits), $pick($symbols)];
    for ($i = count($chars); $i < $length; $i++) {
        $chars[] = $pick($all);
    }

    // Fisher-Yates shuffle using random_int (not the non-cryptographic
    // shuffle()), so the guaranteed characters above aren't predictably
    // stuck in the first four positions.
    for ($i = count($chars) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
    }

    return implode('', $chars);
}

/**
 * Mint a self-service password-reset token. Returns the public token (goes
 * in the emailed link, 64 hex chars) and its SHA-256 hash (the only thing
 * stored — a database read never yields a usable reset link).
 */
function generate_password_reset_token(): array
{
    $token = bin2hex(random_bytes(32));

    return ['token' => $token, 'hash' => hash('sha256', $token)];
}

/**
 * Validate a stored PasswordResets doc without any I/O. True only when a
 * hash is present, the token is unused, and createdAt is within $ttlSeconds
 * of $now. Pure (offline-testable).
 */
function password_reset_token_valid(array $doc, int $now, int $ttlSeconds = 3600): bool
{
    if (trim((string) ($doc['tokenHash'] ?? '')) === '') {
        return false;
    }

    if (!empty($doc['usedAt'])) {
        return false;
    }

    $created = strtotime((string) ($doc['createdAt'] ?? ''));

    if ($created === false) {
        return false;
    }

    $age = $now - $created;

    return $age >= 0 && $age <= $ttlSeconds;
}
