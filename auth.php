<?php
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
    $_SESSION['uid'] = $user['uid'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['name'] = $user['name'];
}

function require_login(): void
{
    if (empty($_SESSION['uid'])) {
        header('Location: /login.php');
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