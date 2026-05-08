<?php

declare(strict_types=1);

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

function profile_photo_path(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        return null;
    }

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0775, true);
    }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $tmp = $file['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        return null;
    }

    $mime = mime_content_type($tmp);
    if (!isset($allowed[$mime])) {
        return null;
    }

    $name = 'profile_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
    $destination = UPLOAD_DIR . '/' . $name;
    if (move_uploaded_file($tmp, $destination)) {
        return UPLOAD_URL . '/' . $name;
    }

    return null;
}