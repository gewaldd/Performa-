<?php
// Hardened session bootstrap. Must run before session_start() on every
// request, so it lives here: this file is required first by all role
// modules (via */includes/auth.php) and by session_login.php.
if (session_status() === PHP_SESSION_NONE) {
    // Reject uninitialized session IDs instead of accepting attacker-chosen
    // ones (session fixation via planted/provided IDs).
    ini_set('session.use_strict_mode', '1');
    // Never fall back to URL-based session IDs (would leak IDs via Referer,
    // logs, and shared links).
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    // Secure/HttpOnly/SameSite enforced in code so protection does not
    // depend on a particular php.ini. `secure` follows the current scheme
    // so plain-http localhost development keeps working.
    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_start();

function users_file_path(): string
{
    return __DIR__ . '/data/users.json';
}

function ensure_users_file(): void
{
    $path = users_file_path();
    $dir = dirname($path);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    if (!file_exists($path)) {
        $admin = [
            'uid' => uniqid('admin_'),
            'name' => 'System Admin',
            'email' => 'admin@local',
            'role' => 'admin',
            'password' => password_hash('admin123', PASSWORD_DEFAULT),
            'created' => time(),
        ];
        file_put_contents($path, json_encode([$admin], JSON_PRETTY_PRINT));
    }
}

function load_users(): array
{
    ensure_users_file();
    $json = file_get_contents(users_file_path());
    $arr = json_decode($json, true);
    return is_array($arr) ? $arr : [];
}

function save_users(array $users): void
{
    $path = users_file_path();
    file_put_contents($path, json_encode(array_values($users), JSON_PRETTY_PRINT));
}

function find_user_by_email(string $email): ?array
{
    $users = load_users();
    foreach ($users as $u) {
        if (strcasecmp($u['email'], $email) === 0) {
            return $u;
        }
    }
    return null;
}

function create_user(string $name, string $email, string $role, string $password): ?array
{
    if (find_user_by_email($email)) {
        return null;
    }
    $users = load_users();
    $user = [
        'uid' => uniqid($role . '_'),
        'name' => $name,
        'email' => $email,
        'role' => $role,
        'password' => password_hash($password, PASSWORD_DEFAULT),
        'created' => time(),
    ];
    $users[] = $user;
    save_users($users);
    return $user;
}

function verify_user(string $email, string $password)
{
    $u = find_user_by_email($email);
    if (!$u)
        return false;
    if (password_verify($password, $u['password']))
        return $u;
    return false;
}

function login_user(array $user): void
{
    // Privilege change: fresh session ID so a pre-login (fixated) ID can
    // never become authenticated.
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_regenerate_id(true);
    }
    $_SESSION['uid'] = $user['uid'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['login_at'] = time();
    // A new login must never inherit the previous session's CSRF token.
    unset($_SESSION['csrf_token']);
}

function require_login(): void
{
    if (empty($_SESSION['uid'])) {
        $scriptPath = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
        $appPath = dirname(dirname($scriptPath));
        $appPath = $appPath === '/' ? '' : rtrim($appPath, '/');
        header('Location: ' . $appPath . '/login.php');
        exit;
    }
}

function require_role(string $role): void
{
    if (($_SESSION['role'] ?? '') !== $role) {
        http_response_code(403);
        echo 'Access denied. Insufficient role.';
        exit;
    }
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(
            session_name(),
            '',
            time() - 42000,
            $params['path'],
            $params['domain'],
            $params['secure'],
            $params['httponly']
        );
    }
    session_destroy();
}

?>