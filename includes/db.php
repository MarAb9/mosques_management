<?php
/**
 * Database Connection Helper
 *
 * Creates and provides the global $pdo connection.
 * Loaded by includes/config.php — do not include directly unless
 * you are sure session and environment setup have already run.
 */

// Prevent re-defining constants if this file is included more than once
// via different paths. require_once in config.php handles the normal case.
if (!defined('DB_HOST')) {
    // appEnv() must already be available from config.php
    define('DB_HOST', appEnv('DB_HOST', '127.0.0.1'));
    define('DB_PORT', appEnv('DB_PORT', '3306'));
    define('DB_USER', appEnv('DB_USER', 'mosques'));
    define('DB_PASS', appEnv('DB_PASS', 'mosques_password'));
    define('DB_NAME', appEnv('DB_NAME', 'mosques_management'));
}

// إنشاء اتصال بقاعدة البيانات
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    die("حدث خطأ في الاتصال بقاعدة البيانات");
}
