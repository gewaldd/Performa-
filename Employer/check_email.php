<?php
// Live duplicate-email check for the Add Employee form (fetch on blur).
// Authenticated employers/admins only — they can already list every user,
// so answering taken/unknown leaks nothing new. The server-side duplicate
// check in add_employee.php stays the source of truth; this is UX only.

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/roles.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');
header('Cache-Control: no-store');

$roleKey = normalize_role_key($_SESSION['role'] ?? null);

if (!in_array($roleKey, ['employer', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => 'forbidden']);
    exit;
}

$email = strtolower(trim((string) ($_GET['email'] ?? '')));
$taken = false;
$unknown = false;

if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
    try {
        foreach (firestore_list_documents('Users') as $u) {
            if (strtolower(trim((string) ($u['email'] ?? ''))) === $email) {
                $taken = true;
                break;
            }
        }
    } catch (Throwable $e) {
        // Fail open with an explicit flag: the form still validates
        // server-side on submit, so never block typing on a read error.
        $unknown = true;
    }
}

echo json_encode(['taken' => $taken, 'unknown' => $unknown]);
