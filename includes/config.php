<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

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
    die("Connection failed: " . $e->getMessage());
}

// التحقق من تسجيل الدخول
function checkAuth() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}
?>
