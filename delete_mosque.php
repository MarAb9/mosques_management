<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkAuth();

if (!canDeleteMosque()) {
    http_response_code(403);
    die("غير مصرح بحذف المساجد");
}

// Verify CSRF token for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: mosques.php");
        exit();
    }
}

try {
    // Handle bulk deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_mosques'])) {
        // ADD AUTHORIZATION CHECK FOR EACH MOSQUE IF NEEDED
        $placeholders = rtrim(str_repeat('?,', count($_POST['selected_mosques'])), ',');
        $stmt = $pdo->prepare("DELETE FROM mosques WHERE registration_number IN ($placeholders)");
        $stmt->execute($_POST['selected_mosques']);
        $_SESSION['success'] = "تم حذف " . count($_POST['selected_mosques']) . " مسجد(اً) بنجاح";
    } 
    // Handle single deletion
    elseif (isset($_GET['id'])) {
        $stmt = $pdo->prepare("DELETE FROM mosques WHERE registration_number = ?");
        $stmt->execute([$_GET['id']]);
        $_SESSION['success'] = "تم حذف المسجد بنجاح";
    }
    else {
        $_SESSION['error'] = "لم يتم تحديد مسجد للحذف";
    }
} catch (PDOException $e) {
    $_SESSION['error'] = "حدث خطأ أثناء حذف المسجد(المساجد): " . $e->getMessage();
}

header("Location: mosques.php");
exit();
?>