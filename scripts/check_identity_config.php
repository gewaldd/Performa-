<?php
require_once __DIR__ . '/../firebase_init.php';

try {
    $token = get_service_account_access_token();
    $project = getenv('FIREBASE_PROJECT_ID') ?: (load_service_account()['project_id'] ?? null);
    if (!$project)
        throw new RuntimeException('FIREBASE_PROJECT_ID not set');

    $url = "https://identitytoolkit.googleapis.com/v1/projects/{$project}/config";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $token]);
    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    echo "HTTP/" . $code . "\n";
    echo $resp . "\n";
} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
