<?php
// Per-session CSRF tokens for Employer state-changing POSTs.
//
// Usage in a page (after includes/auth.php):
//   require_once __DIR__ . '/includes/csrf.php';
//   require_csrf();              // top of script: rejects forged POSTs
//   // ... inside every POST form, echo csrf_field() to emit the hidden input.
//
// The token is minted lazily per session and rotated on every login
// (session_login.php / login_user() unset it), so a token stolen via XSS or
// a previous session cannot be replayed after re-authentication.

function csrf_token(): string
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(csrf_token(), ENT_QUOTES)
        . '" />';
}

function csrf_validate_request(): bool
{
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $sent = $_POST['csrf_token'] ?? null;
    $known = $_SESSION['csrf_token'] ?? null;
    if (!is_string($sent) || !is_string($known) || $sent === '' || $known === '') {
        return false;
    }
    return hash_equals($known, $sent);
}

// Call once near the top of any page that mutates state via POST. GET (and
// any other method) passes through untouched. On mismatch the request dies
// with 403 BEFORE any state change runs — place it before POST handling.
function require_csrf(): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !csrf_validate_request()) {
        http_response_code(403);
        echo 'Access denied. Please reload the page and try again.';
        exit;
    }
}
