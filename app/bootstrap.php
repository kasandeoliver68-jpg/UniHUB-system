<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';
session_start();

require_once __DIR__ . '/modules/database.php';
require_once __DIR__ . '/modules/security.php';
require_once __DIR__ . '/modules/auth.php';
require_once __DIR__ . '/modules/universities.php';
require_once __DIR__ . '/modules/otp.php';
require_once __DIR__ . '/modules/uploads.php';
require_once __DIR__ . '/modules/formatting.php';
