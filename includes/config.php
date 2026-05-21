<?php
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

define('DB_HOST', appEnv('DB_HOST', '127.0.0.1'));
define('DB_PORT', appEnv('DB_PORT', '3306'));
define('DB_USER', appEnv('DB_USER', 'mosques'));
define('DB_PASS', appEnv('DB_PASS', 'mosques_password'));
define('DB_NAME', appEnv('DB_NAME', 'mosques_management'));

// إنشاء اتصال بقاعدة البيانات
try {
    $dsn = "mysql:host=".DB_HOST.";port=".DB_PORT.";dbname=".DB_NAME.";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    error_log("Database connection error: " . $e->getMessage());
    http_response_code(500);
    die("حدث خطأ في الاتصال بقاعدة البيانات");
}

// التحقق من تسجيل الدخول
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>
