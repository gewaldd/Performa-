<?php
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../firebase_init.php';

// Require login but allow any role so admin can inspect the session
require_login();

header('Content-Type: text/plain; charset=utf-8');
echo "PHP Session:\n";
echo "------------\n";
echo var_export($_SESSION, true) . "\n\n";

$uid = $_SESSION['uid'] ?? null;
if ($uid) {
    echo "Firestore Users/{$uid} document:\n";
    echo "---------------------------\n";
    try {
        $doc = firestore_get_document('Users', $uid);
        if ($doc === null) {
            echo "(no document found)\n";
        } else {
            echo var_export($doc, true) . "\n";
        }
    } catch (Throwable $e) {
        echo "Error fetching Firestore doc: " . $e->getMessage() . "\n";
    }
} else {
    echo "No uid in session. Are you logged in?\n";
}

echo "\nRequest headers:\n";
echo "----------------\n";
foreach (getallheaders() as $k => $v) {
    echo "$k: $v\n";
}

?>