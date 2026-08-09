<?php
/**
 * CLI helper to inspect and delete documents in Firestore `Users` collection.
 * Usage:
 *  php scripts/cleanup_users.php --list
 *  php scripts/cleanup_users.php --delete-email=someone@example.com [--confirm]
 *  php scripts/cleanup_users.php --delete-uid=UID [--confirm]
 *  php scripts/cleanup_users.php --delete-orphans [--confirm]
 */

require_once __DIR__ . '/../firebase_init.php';

function usage()
{
    echo "Usage:\n";
    echo "  php scripts/cleanup_users.php --list\n";
    echo "  php scripts/cleanup_users.php --delete-email=someone@example.com [--confirm]\n";
    echo "  php scripts/cleanup_users.php --delete-uid=UID [--confirm]\n";
    echo "  php scripts/cleanup_users.php --delete-orphans [--confirm]\n";
    exit(1);
}

$opts = [];
foreach ($argv as $a) {
    if ($a === $argv[0])
        continue;
    if (strpos($a, '=') !== false) {
        [$k, $v] = explode('=', $a, 2);
        $opts[ltrim($k, '-')] = $v;
    } else {
        $opts[ltrim($a, '-')] = true;
    }
}

if (empty($opts))
    usage();

try {
    $projectId = getenv('FIREBASE_PROJECT_ID') ?: (load_service_account()['project_id'] ?? null);
    if (!$projectId)
        throw new RuntimeException('FIREBASE_PROJECT_ID not set');

    $users = firestore_list_documents('Users');

    if (!empty($opts['list'])) {
        echo "Found " . count($users) . " documents in Users:\n";
        foreach ($users as $u) {
            $uid = $u['uid'] ?? '(no-uid)';
            $email = $u['email'] ?? '(no-email)';
            $role = $u['role'] ?? ($u['roles'] ?? '(no-role)');
            echo sprintf("- %s  | %s  | %s\n", $uid, $email, $role);
        }
        exit(0);
    }

    $confirm = !empty($opts['confirm']);

    // helper delete function
    $deleteDoc = function ($docId) use ($projectId, $confirm) {
        $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/Users/" . urlencode($docId);
        $token = get_service_account_access_token();
        if (!$confirm) {
            echo "Would DELETE: {$docId}\n";
            return;
        }
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code >= 200 && $code < 300) {
            echo "Deleted {$docId}\n";
        } else {
            echo "Failed to delete {$docId}: HTTP {$code} - {$resp}\n";
        }
    };

    if (!empty($opts['delete-email'])) {
        $target = strtolower($opts['delete-email']);
        $found = false;
        foreach ($users as $u) {
            if (!empty($u['email']) && strtolower($u['email']) === $target) {
                $found = true;
                $docId = $u['uid'] ?? null;
                if (!$docId) {
                    echo "Skipping entry with no uid: " . json_encode($u) . "\n";
                    continue;
                }
                $deleteDoc($docId);
            }
        }
        if (!$found)
            echo "No user found with email {$target}\n";
        exit(0);
    }

    if (!empty($opts['delete-uid'])) {
        $docId = $opts['delete-uid'];
        $deleteDoc($docId);
        exit(0);
    }

    if (!empty($opts['delete-orphans'])) {
        foreach ($users as $u) {
            $uid = $u['uid'] ?? null;
            $email = $u['email'] ?? null;
            $role = $u['role'] ?? null;
            if (empty($uid) || empty($email) || empty($role)) {
                $docId = $uid ?? (isset($u['uid']) ? $u['uid'] : null);
                if ($docId) {
                    $deleteDoc($docId);
                } else {
                    // if there's no uid, try to print the doc for manual cleanup
                    echo "Orphan entry (no uid): " . json_encode($u) . "\n";
                }
            }
        }
        exit(0);
    }

    usage();
} catch (Throwable $e) {
    fwrite(STDERR, "Error: " . $e->getMessage() . "\n");
    exit(2);
}
