<?php

declare(strict_types=1);

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