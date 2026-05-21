<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkAuth();

// Only admin can perform bulk deletions
if (!canDeleteMosque()) {
    http_response_code(403);
    die("غير مصرح بالحذف الجماعي");
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    die("طلب غير صالح");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['selected_mosques'])) {
    $selectedMosques = $_POST['selected_mosques'];
    
    // Validate input
    if (empty($selectedMosques)) {
        $_SESSION['error'] = "لم يتم تحديد أي مساجد للحذف";
        header("Location: mosques.php");
        exit();
    }
    
    // Prepare placeholders for IN clause
    $placeholders = str_repeat('?,', count($selectedMosques) - 1) . '?';
    
    // Delete selected mosques
    $stmt = $pdo->prepare("DELETE FROM mosques WHERE registration_number IN ($placeholders)");
    
    if ($stmt->execute($selectedMosques)) {
        $_SESSION['success'] = "تم حذف " . count($selectedMosques) . " مسجد(اً) بنجاح";
    } else {
        $_SESSION['error'] = "حدث خطأ أثناء الحذف الجماعي";
    }
    
    header("Location: mosques.php");
    exit();
} else {
    $_SESSION['error'] = "طلب غير صالح";
    header("Location: mosques.php");
    exit();
}
?>