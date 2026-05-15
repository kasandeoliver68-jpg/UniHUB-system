<?php

declare(strict_types=1);

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