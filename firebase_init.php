<?php
// Load .env file into environment (if present)
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#')
            continue;
        $parts = explode('=', $line, 2) + [null, null];
        $k = trim($parts[0]);
        $v = isset($parts[1]) ? trim($parts[1]) : '';
        if ($k !== '') {
            putenv("$k=$v");
            $_ENV[$k] = $v;
            $_SERVER[$k] = $v;
        }
    }
}

// Lightweight Firebase REST helpers using a service account JSON.
// Works without the PHP Admin SDK. Provides access token generation
// and simple Firestore / Identity Toolkit REST calls.

function firebase_credentials_path(): string
{
    $env = getenv('GOOGLE_APPLICATION_CREDENTIALS');
    if ($env) {
        $candidates = [$env];
        $isAbsoluteWindowsPath = strlen($env) >= 2 && ctype_alpha($env[0]) && $env[1] === ':';
        $isAbsoluteUnixPath = substr($env, 0, 1) === '/' || substr($env, 0, 2) === '\\';
        if (!$isAbsoluteWindowsPath && !$isAbsoluteUnixPath) {
            $candidates[] = __DIR__ . '/' . ltrim($env, '\\/');
        }
        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }
    }

    return __DIR__ . '/firebase-service-account.json';
}

function load_service_account(): array
{
    $path = firebase_credentials_path();
    if (!file_exists($path))
        throw new RuntimeException("Service account json not found: $path. Set GOOGLE_APPLICATION_CREDENTIALS to the absolute path of your Firebase service-account JSON or place firebase-service-account.json in the project root.");
    $json = json_decode(file_get_contents($path), true);
    if (!is_array($json))
        throw new RuntimeException('Invalid service account JSON');
    return $json;
}

function base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function get_service_account_access_token(): string
{
    $svc = load_service_account();
    $cacheFile = sys_get_temp_dir() . '/firebase_sa_token_' . md5($svc['client_email']) . '.json';
    if (file_exists($cacheFile)) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if (!empty($cached['access_token']) && !empty($cached['expires_at']) && $cached['expires_at'] > time() + 30) {
            return $cached['access_token'];
        }
    }

    $now = time();
    $header = ['alg' => 'RS256', 'typ' => 'JWT'];
    $scope = implode(' ', [
        'https://www.googleapis.com/auth/datastore',
        'https://www.googleapis.com/auth/cloud-platform',
        'https://www.googleapis.com/auth/identitytoolkit',
    ]);
    $claim = [
        'iss' => $svc['client_email'],
        'scope' => $scope,
        'aud' => 'https://oauth2.googleapis.com/token',
        'exp' => $now + 3600,
        'iat' => $now,
    ];

    $assertion = base64url_encode(json_encode($header)) . '.' . base64url_encode(json_encode($claim));
    $privateKey = $svc['private_key'] ?? null;
    if (!$privateKey)
        throw new RuntimeException('Service account private_key missing');
    $signature = '';
    $ok = openssl_sign($assertion, $signature, $privateKey, OPENSSL_ALGO_SHA256);
    if (!$ok)
        throw new RuntimeException('Failed to sign JWT assertion');
    $jwt = $assertion . '.' . base64url_encode($signature);

    $post = http_build_query([
        'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
        'assertion' => $jwt,
    ]);

    $ch = curl_init('https://oauth2.googleapis.com/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200) {
        throw new RuntimeException('Failed to obtain access token: ' . $resp);
    }
    $data = json_decode($resp, true);
    if (empty($data['access_token']))
        throw new RuntimeException('No access_token in response');

    $cache = ['access_token' => $data['access_token'], 'expires_at' => time() + ($data['expires_in'] ?? 3600)];
    @file_put_contents($cacheFile, json_encode($cache));
    return $data['access_token'];
}

function identitytoolkit_create_user(string $name, string $email, string $password): string
{
    $svc = load_service_account();
    $projectId = getenv('FIREBASE_PROJECT_ID') ?: ($svc['project_id'] ?? null);
    if (!$projectId)
        throw new RuntimeException('FIREBASE_PROJECT_ID not set');
    $body = ['users' => [['email' => $email, 'password' => $password, 'displayName' => $name]]];
    $token = get_service_account_access_token();

    // Try v2 endpoint first (requires Identity Platform enabled). If 404, fall back to v1.
    $versions = ['v2', 'v1'];
    $data = null;
    $lastResp = null;
    $lastCode = null;
    foreach ($versions as $ver) {
        if ($ver === 'v2') {
            $url = "https://identitytoolkit.googleapis.com/v2/projects/{$projectId}/accounts:batchCreate";
            $callBody = $body;
        } else {
            $url = "https://identitytoolkit.googleapis.com/v1/projects/{$projectId}/accounts:batchCreate";
            // v1 expects password bytes (base64-encoded)
            $callBody = $body;
            if (isset($callBody['users'][0]['password'])) {
                $callBody['users'][0]['password'] = base64_encode($callBody['users'][0]['password']);
            }
        }

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $token]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($callBody));
        $resp = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        $lastResp = $resp;
        $lastCode = $code;
        if ($code >= 200 && $code < 300) {
            $data = json_decode($resp, true);
            break;
        }
        // if 404 try next version
        if ($code === 404)
            continue;
        // other errors: stop and report
        break;
    }
    if ($data === null) {
        throw new RuntimeException('Identity Toolkit create user failed: ' . $lastResp);
    }
    if (isset($data['users'][0]['localId'])) {
        $uid = $data['users'][0]['localId'];
    } elseif (!empty($data['localIds'][0])) {
        $uid = $data['localIds'][0];
    } else {
        // Try fallback to the signUp endpoint using API key (creates user and returns localId)
        $apiKey = getenv('FIREBASE_API_KEY') ?: null;
        if ($apiKey) {
            $url = 'https://identitytoolkit.googleapis.com/v1/accounts:signUp?key=' . urlencode($apiKey);
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['email' => $email, 'password' => $password, 'returnSecureToken' => true]));
            $r = curl_exec($ch);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            $rdata = json_decode($r, true);
            if (!empty($rdata['localId'])) {
                $uid = $rdata['localId'];
            } else {
                // If signUp failed because the email already exists, try to look up the existing user id
                if (!empty($rdata['error']['message']) && $rdata['error']['message'] === 'EMAIL_EXISTS') {
                    $lookupUrl = 'https://identitytoolkit.googleapis.com/v1/accounts:lookup?key=' . urlencode($apiKey);
                    $chL = curl_init($lookupUrl);
                    curl_setopt($chL, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($chL, CURLOPT_POST, true);
                    curl_setopt($chL, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
                    curl_setopt($chL, CURLOPT_POSTFIELDS, json_encode(['email' => [$email]]));
                    $lr = curl_exec($chL);
                    $lcode = curl_getinfo($chL, CURLINFO_HTTP_CODE);
                    curl_close($chL);
                    $ldata = json_decode($lr, true);
                    if ($lcode === 200 && !empty($ldata['users'][0]['localId'])) {
                        $uid = $ldata['users'][0]['localId'];
                    } else {
                        throw new RuntimeException('Could not obtain existing user id: ' . $r . ' | ' . $lr);
                    }
                } else {
                    throw new RuntimeException('Could not obtain created user id: ' . $lastResp . ' | ' . $r);
                }
            }
        } else {
            throw new RuntimeException('Could not obtain created user id: ' . $lastResp);
        }
    }
    return $uid;
}

function php_to_firestore_fields($value)
{
    if (is_array($value)) {
        // assume associative -> mapValue
        $fields = [];
        foreach ($value as $k => $v) {
            $fields[$k] = php_to_firestore_fields($v);
        }
        return ['mapValue' => ['fields' => $fields]];
    }
    if (is_string($value))
        return ['stringValue' => $value];
    if (is_int($value))
        return ['integerValue' => (string) $value];
    if (is_float($value))
        return ['doubleValue' => $value];
    if (is_bool($value))
        return ['booleanValue' => $value];
    if (is_null($value))
        return ['nullValue' => null];
    // fallback to string
    return ['stringValue' => (string) $value];
}

function firestore_write_document(string $collection, string $documentId, array $data): void
{
    $svc = load_service_account();
    $projectId = getenv('FIREBASE_PROJECT_ID') ?: ($svc['project_id'] ?? null);
    if (!$projectId)
        throw new RuntimeException('FIREBASE_PROJECT_ID not set');
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}";

    $fields = [];
    foreach ($data as $k => $v) {
        $fields[$k] = php_to_firestore_fields($v);
    }
    $body = ['fields' => $fields];

    $token = get_service_account_access_token();
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PATCH');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Authorization: Bearer ' . $token]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code < 200 || $code >= 300) {
        throw new RuntimeException('Firestore write failed: ' . $resp);
    }
}

function firestore_get_document(string $collection, string $documentId): ?array
{
    $svc = load_service_account();
    $projectId = getenv('FIREBASE_PROJECT_ID') ?: ($svc['project_id'] ?? null);
    if (!$projectId)
        throw new RuntimeException('FIREBASE_PROJECT_ID not set');
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}/{$documentId}";
    $token = get_service_account_access_token();
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $data = json_decode($resp, true);
        // convert Firestore fields to plain array (simple string/bool/number handling)
        $out = [];
        $fields = $data['fields'] ?? [];
        foreach ($fields as $k => $v) {
            if (isset($v['stringValue']))
                $out[$k] = $v['stringValue'];
            elseif (isset($v['integerValue']))
                $out[$k] = (int) $v['integerValue'];
            elseif (isset($v['doubleValue']))
                $out[$k] = (float) $v['doubleValue'];
            elseif (isset($v['booleanValue']))
                $out[$k] = (bool) $v['booleanValue'];
            elseif (isset($v['mapValue'])) {
                $sub = $v['mapValue']['fields'] ?? [];
                $out[$k] = [];
                foreach ($sub as $sk => $sv) {
                    $out[$k][$sk] = $sv['stringValue'] ?? ($sv['integerValue'] ?? null);
                }
            } else {
                $out[$k] = null;
            }
        }
        return $out;
    }
    if ($code === 404)
        return null;
    throw new RuntimeException('Firestore get failed: ' . $resp);
}

function firestore_list_documents(string $collection): array
{
    $svc = load_service_account();
    $projectId = getenv('FIREBASE_PROJECT_ID') ?: ($svc['project_id'] ?? null);
    if (!$projectId)
        throw new RuntimeException('FIREBASE_PROJECT_ID not set');
    $url = "https://firestore.googleapis.com/v1/projects/{$projectId}/databases/(default)/documents/{$collection}";
    $token = get_service_account_access_token();
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code === 200) {
        $data = json_decode($resp, true);
        $out = [];
        $docs = $data['documents'] ?? [];
        foreach ($docs as $doc) {
            $fields = $doc['fields'] ?? [];
            $item = [];
            foreach ($fields as $k => $v) {
                if (isset($v['stringValue']))
                    $item[$k] = $v['stringValue'];
                elseif (isset($v['integerValue']))
                    $item[$k] = (int) $v['integerValue'];
                elseif (isset($v['doubleValue']))
                    $item[$k] = (float) $v['doubleValue'];
                elseif (isset($v['booleanValue']))
                    $item[$k] = (bool) $v['booleanValue'];
                elseif (isset($v['mapValue'])) {
                    $sub = $v['mapValue']['fields'] ?? [];
                    $item[$k] = [];
                    foreach ($sub as $sk => $sv) {
                        $item[$k][$sk] = $sv['stringValue'] ?? ($sv['integerValue'] ?? null);
                    }
                } else {
                    $item[$k] = null;
                }
            }
            // include document name/uid if present
            if (!empty($doc['name'])) {
                $parts = explode('/', $doc['name']);
                $uid = end($parts);
                $item['uid'] = $uid;
            }
            $out[] = $item;
        }
        return $out;
    }
    if ($code === 404)
        return [];
    throw new RuntimeException('Firestore list failed: ' . $resp);
}

?>