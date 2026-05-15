<?php

declare(strict_types=1);

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

    $stmt = db()->prepare("SELECT COUNT(*) FROM {$table} WHERE university_id = ?");
    $stmt->execute([$universityId]);
    return (int) $stmt->fetchColumn();
}