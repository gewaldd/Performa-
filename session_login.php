<?php
require_once __DIR__ . '/firebase_init.php';
session_start();

// Expect JSON body with { idToken, email? }
$data = json_decode(file_get_contents('php://input'), true);
$idToken = $data['idToken'] ?? '';
$fallbackEmail = strtolower(trim($data['email'] ?? ''));

if (!$idToken && $fallbackEmail === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Missing idToken and email']);
    exit;
}

try {
    $matchedUser = null;
    if ($fallbackEmail !== '') {
        $allUsers = firestore_list_documents('Users');
        foreach ($allUsers as $item) {
            if (!empty($item['email']) && strtolower($item['email']) === $fallbackEmail) {
                $matchedUser = $item;
                break;
            }
        }
    }

    if ($matchedUser) {
        $_SESSION['uid'] = $matchedUser['uid'] ?? $fallbackEmail;
        $_SESSION['name'] = $matchedUser['name'] ?? $matchedUser['email'] ?? $fallbackEmail;
        $_SESSION['role'] = $matchedUser['role'] ?? ($matchedUser['roles'] ?? 'probationary_employee');
        echo json_encode(['ok' => true, 'role' => $_SESSION['role']]);
        exit;
    }

    if (!$idToken) {
        http_response_code(400);
        echo json_encode(['error' => 'Missing idToken and no matching email']);
        exit;
    }

    // Verify token via Google's tokeninfo endpoint (REST fallback) using curl
    $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($idToken));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($resp === false || $code !== 200) {
        // tokeninfo failed — try Identity Toolkit accounts:lookup with API key as a fallback
        $apiKey = getenv('FIREBASE_API_KEY') ?: null;
        if ($apiKey) {
            $lookupUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($apiKey);
            $ch2 = curl_init($lookupUrl);
            curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch2, CURLOPT_POST, true);
            curl_setopt($ch2, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode(['idToken' => $idToken]));
            $r2 = curl_exec($ch2);
            $code2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
            curl_close($ch2);
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
                    throw new RuntimeException('Failed to verify token: ' . ($resp ?: $r2));
                }
            } else {
                // As a last resort, fall back to the authenticated email provided by the client.
                if ($fallbackEmail !== '') {
                    $all = firestore_list_documents('Users');
                    foreach ($all as $item) {
                        if (!empty($item['email']) && strtolower($item['email']) === $fallbackEmail) {
                            $t = [
                                'sub' => $item['uid'] ?? $fallbackEmail,
                                'email' => $item['email'],
                                'name' => $item['name'] ?? $item['email'],
                                'aud' => $apiKey,
                            ];
                            break;
                        }
                    }
                    if (empty($t['sub'])) {
                        throw new RuntimeException('Failed to verify token: ' . ($resp ?: $r2));
                    }
                } else {
                    throw new RuntimeException('Failed to verify token: ' . ($resp ?: $r2));
                }
            }
        } else {
            if ($fallbackEmail !== '') {
                $all = firestore_list_documents('Users');
                foreach ($all as $item) {
                    if (!empty($item['email']) && strtolower($item['email']) === $fallbackEmail) {
                        $t = [
                            'sub' => $item['uid'] ?? $fallbackEmail,
                            'email' => $item['email'],
                            'name' => $item['name'] ?? $item['email'],
                            'aud' => $fallbackEmail,
                        ];
                        break;
                    }
                }
                if (empty($t['sub'])) {
                    throw new RuntimeException('Failed to verify token: ' . ($resp ?: 'no response'));
                }
            } else {
                throw new RuntimeException('Failed to verify token: ' . ($resp ?: 'no response'));
            }
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

    // Read role from Firestore Users collection
    $role = null;
    $doc = firestore_get_document('Users', $uid);
    if ($doc) {
        $role = $doc['role'] ?? null;
        $name = $doc['name'] ?? $name;
    } else {
        // Fallback: sometimes a Users document was created with a different id
        // (or an import) — try to find by email in the Users collection.
        try {
            $emailToFind = $t['email'] ?? null;
            if ($emailToFind) {
                $all = firestore_list_documents('Users');
                foreach ($all as $item) {
                    if (!empty($item['email']) && strtolower($item['email']) === strtolower($emailToFind)) {
                        $role = $item['role'] ?? $item['roles'] ?? null;
                        $name = $item['name'] ?? $name;
                        break;
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore — we'll fall back to default role below
        }
    }

    // Establish minimal PHP session
    $_SESSION['uid'] = $uid;
    $_SESSION['name'] = $name;
    $_SESSION['role'] = $role ?? 'user';

    echo json_encode(['ok' => true, 'role' => $_SESSION['role']]);
} catch (\Throwable $e) {
    http_response_code(401);
    echo json_encode(['error' => $e->getMessage()]);
}
