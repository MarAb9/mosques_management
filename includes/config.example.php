<?php
/**
 * Application Configuration — EXAMPLE TEMPLATE
 *
 * Copy this file to config.php in the same directory:
 *     cp includes/config.example.php includes/config.php
 *
 * Then replace the placeholder values in db.php defaults or set environment
 * variables for your real database credentials.
 * NEVER commit config.php to version control.
 */

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    } else {
        session_set_cookie_params(0, '/; samesite=Lax', '', $isHttps, true);
    }

    session_start();
}

require_once __DIR__ . '/csrf.php';

// إعدادات قاعدة البيانات
function appEnv($name, $default = '') {
    $value = getenv($name);

    return $value === false || $value === '' ? $default : $value;
}

// Database connection — extracted to db.php
require_once __DIR__ . '/db.php';

// Shared helpers
require_once __DIR__ . '/flash.php';
require_once __DIR__ . '/redirect.php';
require_once __DIR__ . '/helpers.php';

// التحقق من تسجيل الدخول
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>
