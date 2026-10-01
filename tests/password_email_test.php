<?php
// Unit tests for credential delivery: secure password generation
// (security_utils.php) and the Brevo email templates (mailer.php).
// All offline: generation is local CSPRNG math, templates are pure
// string building, and the misconfigured mailer path returns false
// before touching the network.

require_once __DIR__ . '/lib/assert.php';
pf_boot();
pf_header('credential delivery');

require_once __DIR__ . '/../security_utils.php';
require_once __DIR__ . '/../mailer.php';

/* =========================================================
   generate_secure_password()
   ========================================================= */

pf_case('generator shape');

$pw = generate_secure_password();
assert_same(12, strlen($pw), 'default length is 12');
assert_same(8, strlen(generate_secure_password(4)), 'short lengths clamp to 8');
assert_same(20, strlen(generate_secure_password(20)), 'long lengths honored');

pf_case('generator character classes');

$hasLower = preg_match('/[a-z]/', $pw) === 1;
$hasUpper = preg_match('/[A-Z]/', $pw) === 1;
$hasDigit = preg_match('/[0-9]/', $pw) === 1;
$hasSymbol = (bool) strpbrk($pw, '!@#$%^&*-_=+');
assert_true($hasLower && $hasUpper && $hasDigit && $hasSymbol, 'all four classes guaranteed');

$ambiguous = ['0', 'O', '1', 'l', 'I'];
$clean = true;
foreach ($ambiguous as $ch) {
    if (strpos($pw, $ch) !== false) {
        $clean = false;
    }
}
assert_true($clean, 'ambiguous look-alikes excluded');

pf_case('generator uniqueness');

$seen = [];
for ($i = 0; $i < 50; $i++) {
    $seen[generate_secure_password()] = true;
}
assert_same(50, count($seen), '50 generated passwords are all unique');

/* =========================================================
   Email templates (escaping + content)
   ========================================================= */

pf_case('welcome template');

$_SERVER['HTTP_HOST'] = 'example.test';
unset($_SERVER['HTTPS']);
$loginUrl = app_base_url() . '/login.php';
assert_same('http://example.test/login.php', $loginUrl, 'login URL builds from host');

$evil = '<script>alert(1)</script>';
$html = welcome_email_html($evil, 'a@b.c', 'p@ss<w>', $loginUrl);
assert_not_contains($evil, $html, 'name is escaped');
assert_not_contains('p@ss<w>', $html, 'password is escaped');
assert_contains(htmlspecialchars($evil, ENT_QUOTES), $html, 'escaped name present');
assert_contains('Temporary password', $html, 'labels the credential as temporary');
assert_contains('change this password right away', $html, 'urges rotation');
assert_contains($loginUrl, $html, 'contains the login link');

pf_case('reset template');

$resetHtml = password_reset_email_html($evil, 'r&set', $loginUrl);
assert_not_contains($evil, $resetHtml, 'reset name is escaped');
assert_not_contains('r&set', $resetHtml, 'reset password is escaped');
assert_contains('was reset', $resetHtml, 'reset copy differs from welcome copy');
assert_not_contains('has been created for you', $resetHtml, 'no welcome wording');
assert_contains($loginUrl, $resetHtml, 'reset contains the login link');

/* =========================================================
   Misconfigured mailer (offline-safe: returns before curl)
   ========================================================= */

pf_case('mailer without configuration');

putenv('BREVO_API_KEY');
putenv('BREVO_SENDER_EMAIL');
assert_false(
    send_transactional_email('a@b.c', 'N', 'S', '<p>x</p>'),
    'unconfigured mailer returns false without sending'
);

pf_case('no diagnostics');

assert_no_php_warnings();

pf_summary();
