<?php
// Never let a stray PHP warning/notice get printed into what must be a
// pure JSON response — that's what breaks the frontend's res.json() call.
ini_set('display_errors', '0');
error_reporting(E_ALL);
ob_start();

function send_json(array $payload, int $code = 200): void
{
    // Discard anything already buffered (stray warnings/notices) so the
    // response body is guaranteed to be pure JSON.
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

require_once __DIR__ . '/firebase_init.php';
// Hardened session bootstrap (cookie flags, strict mode) + session_start().
require_once __DIR__ . '/auth.php';

// Expect JSON body with { idToken }. The client also sends `email`, which is
// accepted but NEVER trusted for auth decisions: only identity proved by a
// verified Firebase token may create a session. (Previously an unverified
// email alone minted a session — a full auth bypass for anyone who knew an
// address on file.)
$data = json_decode(file_get_contents('php://input'), true);
$idToken = (string) ($data['idToken'] ?? '');

if ($idToken === '') {
    // Deliberately generic on every failure path: distinct messages/codes
    // would let attackers probe which emails or accounts exist.
    send_json(['error' => 'Sign-in failed. Please try again.'], 400);
}

try {
    // Verify token via Google's tokeninfo endpoint (REST fallback) using curl
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
    curl_setopt($ch, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    firebase_curl_timeouts($ch);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr = curl_error($ch);
    // The curl handle is intentionally not closed: closing is deprecated in
    // PHP 8.5 and has been a no-op since 8.0 -- the handle is released when
    // $ch goes out of scope.

    if ($resp === false || $code !== 200) {
        // tokeninfo failed — try Identity Toolkit accounts:lookup with API key as a fallback
        $apiKey = getenv('FIREBASE_API_KEY') ?: null;
        if ($apiKey) {
            $lookupUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($apiKey);
            $ch2 = curl_init($lookupUrl);
            curl_setopt($ch2, CURLOPT_CAINFO, __DIR__ . '/cacert.pem');
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            firebase_curl_timeouts($ch2);
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));
            $r2 = curl_exec($ch2);
            $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            if ($r2 !== false && $code2 === 200) {
                $t2 = json_decode($r2, true);
                if (!empty($t2['users'][0]['localId'])) {
                    $t = [
                        'sub' => $t2['users'][0]['localId'],
                        'email' => $t2['users'][0]['email'] ?? null,
                        'name' => $t2['users'][0]['displayName'] ?? null,
                        'aud' => $apiKey,
                    ];
                } else {
                    throw new RuntimeException('token verification failed');
                }
            } else {
                throw new RuntimeException('token verification failed');
            }
        } else {
            throw new RuntimeException('token verification failed');
        }
    } else {
        $t = json_decode($resp, true);
    }
    if (empty($t['sub']))
        throw new RuntimeException('Invalid token');

    $projectId = getenv('FIREBASE_PROJECT_ID') ?: (load_service_account()['project_id'] ?? null);
    // Accept either project ID or API key as audience (tokeninfo may vary)
    $apiKey = getenv('FIREBASE_API_KEY') ?: null;
    if ($projectId && !empty($t['aud']) && $t['aud'] !== $projectId && ($apiKey === null || $t['aud'] !== $apiKey)) {
        throw new RuntimeException('Token audience mismatch');
    }

    $uid = $t['sub'];
    $name = $t['name'] ?? ($t['email'] ?? '');

    // Read role from Firestore Users collection. Every lookup below keys
    // off the VERIFIED token (uid, then the token's own email claim) — the
    // client-supplied email is never consulted, so it cannot be used to
    // steer the session onto someone else's account.
    $role = null;
    $mustChangePassword = false;
    $doc = firestore_get_document('Users', $uid);
    if ($doc) {
        $role = $doc['role'] ?? null;
        $name = $doc['name'] ?? $name;
        $mustChangePassword = !empty($doc['mustChangePassword']);
    } else {
        // Fallback: sometimes a Users document was created with a different id
        // (or an import) — try to find by the verified token email.
        $emailToFind = $t['email'] ?? null;
        if ($emailToFind) {
            try {
                $all = firestore_list_documents('Users');
                foreach ($all as $item) {
                    if (!empty($item['email']) && strtolower($item['email']) === strtolower($emailToFind)) {
                        $role = $item['role'] ?? $item['roles'] ?? null;
                        $name = $item['name'] ?? $name;
                        $mustChangePassword = !empty($item['mustChangePassword']);
                        break;
                    }
                }
            } catch (Throwable $e) {
                // ignore — no matching record below denies the login
            }
        }
    }

    if ($role === null) {
        // Valid token, unknown account: no session. (Previously such users
        // were logged in with a default role.)
        throw new RuntimeException('unknown account');
    }

    // Establish minimal PHP session on a fresh ID (fixation defense).
    session_regenerate_id(true);
    $_SESSION['uid'] = $uid;
    $_SESSION['name'] = $name;
    $_SESSION['role'] = $role;
    $_SESSION['login_at'] = time();
    $_SESSION['must_change_password'] = $mustChangePassword;
    // A new login must never inherit the previous session's CSRF token.
    unset($_SESSION['csrf_token']);

    send_json(['ok' => true, 'role' => $_SESSION['role'], 'mustChangePassword' => $mustChangePassword]);
} catch (\Throwable $e) {
    // Generic message: exception details (audience, endpoint responses) are
    // server-side information and must not reach the login screen.
    error_log('session_login failed: ' . $e->getMessage());
    send_json(['error' => 'Sign-in failed. Please try again.'], 401);
}