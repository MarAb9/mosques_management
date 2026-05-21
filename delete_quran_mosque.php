<?php
require_once 'includes/config.php';
require_once 'includes/auth.php';
require_once 'includes/auth_check.php';

checkAuth();


if (!canDeleteMosque()) {
        http_response_code(403);
    die(" غير مصرح بحذف مساجد التحفيظ");
}
// Verify CSRF token for POST requests (only for bulk deletion)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_mosques'])) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error'] = "Invalid CSRF token";
        header("Location: quran_mosques.php");
        exit();
    }
}

try {
    // Handle bulk deletion
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_mosques'])) {
        $pdo->beginTransaction();
        
        $placeholders = rtrim(str_repeat('?,', count($_POST['selected_mosques'])), ',');
        
        // First delete related responsibles
        $stmt = $pdo->prepare("DELETE FROM quran_program_responsibles WHERE program_id IN ($placeholders)");
        $stmt->execute($_POST['selected_mosques']);
        
        // Then delete the programs
        $stmt = $pdo->prepare("DELETE FROM quran_memorization_programs WHERE id IN ($placeholders)");
        $stmt->execute($_POST['selected_mosques']);
        
        $pdo->commit();
        $_SESSION['success'] = "تم حذف " . count($_POST['selected_mosques']) . " مسجد تحفيظ بنجاح";
    } 
    // Handle single deletion (GET request with ID parameter)
    elseif (isset($_GET['id'])) {
        $pdo->beginTransaction();
        
        // First delete related responsibles
        $stmt = $pdo->prepare("DELETE FROM quran_program_responsibles WHERE program_id = ?");
        $stmt->execute([$_GET['id']]);
        
        // Then delete the program
        $stmt = $pdo->prepare("DELETE FROM quran_memorization_programs WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        
        $pdo->commit();
        $_SESSION['success'] = "تم حذف مسجد التحفيظ بنجاح";
    }
    else {
        $_SESSION['error'] = "لم يتم تحديد مسجد تحفيظ للحذف";
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $_SESSION['error'] = "حدث خطأ أثناء حذف مسجد(مساجد) التحفيظ: " . $e->getMessage();
}

header("Location: quran_mosques.php");
exit();
?>