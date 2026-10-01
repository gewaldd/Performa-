<?php
// Unit tests for the self-service password-reset flow (security_utils.php
// token helpers + mailer.php link template). Token issue/consume paths that
// need Firestore are exercised live; everything assertable offline lives
// here: token shape, hash binding, expiry/single-use rules, link escaping.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('password reset');

require_once __DIR__ . '/../security_utils.php';
require_once __DIR__ . '/../mailer.php';

/* =========================================================
   Token minting
   ========================================================= */

pf_case('token shape');

$pair = generate_password_reset_token();
assert_same(64, strlen($pair['token']), 'token is 64 hex chars');
assert_true((bool) preg_match('/^[0-9a-f]{64}$/', $pair['token']), 'token is lowercase hex');
assert_same(hash('sha256', $pair['token']), $pair['hash'], 'stored hash binds the token');

$seen = [];
for ($i = 0; $i < 25; $i++) {
    $seen[generate_password_reset_token()['token']] = true;
}
assert_same(25, count($seen), '25 minted tokens are all unique');

/* =========================================================
   Token validity (fixed clock: 2026-09-30T12:00:00Z)
   ========================================================= */

pf_case('validity matrix');

$now = strtotime('2026-09-30T12:00:00Z');

$base = [
    'tokenHash' => str_repeat('a', 64),
    'createdAt' => '2026-09-30T11:30:00+00:00',
    'usedAt' => null,
];
assert_true(password_reset_token_valid($base, $now), 'fresh unused token valid');

assert_false(
    password_reset_token_valid(['createdAt' => '2026-09-30T11:30:00+00:00'], $now),
    'missing hash invalid'
);
assert_false(
    password_reset_token_valid(['tokenHash' => '  ', 'createdAt' => '2026-09-30T11:30:00+00:00'], $now),
    'blank hash invalid'
);

$used = $base;
$used['usedAt'] = '2026-09-30T11:45:00+00:00';
assert_false(password_reset_token_valid($used, $now), 'used token invalid');

$expired = $base;
$expired['createdAt'] = '2026-09-30T10:59:59+00:00';
assert_false(password_reset_token_valid($expired, $now), '61-minute-old token expired');

$edge = $base;
$edge['createdAt'] = '2026-09-30T11:00:00+00:00';
assert_true(password_reset_token_valid($edge, $now), 'exactly-TTL token still valid');

$future = $base;
$future['createdAt'] = '2026-09-30T12:00:01+00:00';
assert_false(password_reset_token_valid($future, $now), 'future-dated token invalid');

$garbage = $base;
$garbage['createdAt'] = 'not-a-date';
assert_false(password_reset_token_valid($garbage, $now), 'unparseable timestamp invalid');

$fresh = [
    'tokenHash' => str_repeat('b', 64),
    'createdAt' => date('c', $now),
    'usedAt' => null,
];
assert_true(
    password_reset_token_valid($fresh, $now, 60),
    'custom short TTL honored when fresh'
);
assert_false(
    password_reset_token_valid($fresh, $now + 61, 60),
    'custom short TTL expires'
);

/* =========================================================
   Link email template
   ========================================================= */

pf_case('link template');

$evil = '<script>alert(1)</script>';
$url = 'https://example.test/reset_password.php?token=abc123';
$html = password_reset_link_email_html($evil, $url);
assert_not_contains($evil, $html, 'name is escaped');
assert_not_contains('<script>', $html, 'no raw markup survives');
assert_contains(htmlspecialchars($evil, ENT_QUOTES), $html, 'escaped name present');
assert_contains($url, $html, 'reset link present');
assert_contains('expires within the hour', $html, 'expiry stated');
assert_not_contains('Temporary password', $html, 'no credential wording (link only)');

pf_case('no diagnostics');

assert_no_php_warnings();

pf_summary();
