<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

session_start();

function db(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    return $pdo;
}

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function path(string $page = 'dashboard', array $params = []): string
{
    return 'index.php?' . http_build_query(array_merge(['page' => $page], $params));
}

function redirect(string $page, array $params = []): void
{
    header('Location: ' . path($page, $params));
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        flash('error', 'Security check failed. Please try again.');
        redirect('home');
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    static $user = null;
    if ($user !== null && (int) $user['id'] === (int) $_SESSION['user_id']) {
        return $user;
    }

    $stmt = db()->prepare(
        'SELECT users.*, universities.name AS university_name, universities.domain
         FROM users
         JOIN universities ON universities.id = users.university_id
         WHERE users.id = ?'
    );
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch() ?: null;

    return $user;
}

function require_auth(): array
{
    $user = current_user();
    if (!$user) {
        flash('error', 'Please log in first.');
        redirect('login');
    }

    if (!$user['verified_at']) {
        flash('error', 'Verify your email before accessing UniHUB.');
        redirect('verify');
    }

    return $user;
}

function require_admin(): array
{
    $user = require_auth();
    if ($user['role'] !== 'admin') {
        flash('error', 'Admin access is required.');
        redirect('dashboard');
    }

    return $user;
}

function find_university_by_email(string $email): ?array
{
    $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
    if ($domain === '') {
        return null;
    }

    $stmt = db()->query('SELECT * FROM universities WHERE active = 1 ORDER BY LENGTH(domain) DESC');
    foreach ($stmt->fetchAll() as $university) {
        if ($domain === strtolower($university['domain'])) {
            return $university;
        }
    }

    return null;
}

function generate_otp(): string
{
    return (string) random_int(100000, 999999);
}

function issue_otp(int $userId, string $email): string
{
    $otp = generate_otp();
    $hash = password_hash($otp, PASSWORD_DEFAULT);
    $expires = (new DateTimeImmutable('+' . OTP_EXPIRY_MINUTES . ' minutes'))->format('Y-m-d H:i:s');

    $stmt = db()->prepare('UPDATE users SET otp_code_hash = ?, otp_expires_at = ? WHERE id = ?');
    $stmt->execute([$hash, $expires, $userId]);

    $_SESSION['last_otp'] = $otp;
    // In local XAMPP/WAMP development we expose the OTP in-session. Swap this for mail() or SMTP in production.
    return $otp;
}

function listing_image_paths(array $files): array
{
    if (empty($files['name'][0])) {
        return [];
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }

    $paths = [];
    $count = min(count($files['name']), MAX_LISTING_IMAGES);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    for ($i = 0; $i < $count; $i++) {
        if (($files['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            continue;
        }

        $tmp = $files['tmp_name'][$i];
        $mime = mime_content_type($tmp);
        if (!isset($allowed[$mime])) {
            continue;
        }

        $name = bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
        $destination = UPLOAD_DIR . '/' . $name;
        if (move_uploaded_file($tmp, $destination)) {
            $paths[] = UPLOAD_URL . '/' . $name;
        }
    }

    return $paths;
}

function money($amount): string
{
    return 'UGX ' . number_format((float) $amount, 0);
}

function table_count(string $table, ?int $universityId = null): int
{
    $allowed = ['users', 'events', 'listings'];
    if (!in_array($table, $allowed, true)) {
        return 0;
    }

    if ($universityId === null) {
        return (int) db()->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
    }

    $column = $table === 'users' ? 'university_id' : 'university_id';
    $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE {$column} = ?");
    $stmt->execute([$universityId]);
    return (int) $stmt->fetchColumn();
}
