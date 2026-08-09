<?php
// Run from CLI: php scripts/create_admin.php admin@example.com "Admin Name" "TempPass123!"
require_once __DIR__ . '/../firebase_init.php';

if (php_sapi_name() !== 'cli') {
    echo "This script must be run from the command line.\n";
    exit(1);
}

$email = $argv[1] ?? null;
$name = $argv[2] ?? 'System Admin';
$password = $argv[3] ?? bin2hex(random_bytes(6));

if (!$email) {
    echo "Usage: php scripts/create_admin.php email@example.com 'Name' [password]\n";
    exit(1);
}

try {
    $uid = identitytoolkit_create_user($name, $email, $password);
    firestore_write_document('Users', $uid, [
        'name' => $name,
        'email' => $email,
        'role' => 'admin',
        'createdAt' => date('c'),
    ]);

    echo "Created admin $email with temporary password: $password\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
