<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkAuth();

if (!canDeleteMosque()) {
    http_response_code(403);
    die("غير مصرح بالحذف الجماعي");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $_SESSION['error'] = "طلب غير صالح";
    header("Location: mosques.php");
    exit();
}

verify_csrf_token('mosques.php');

if (!isset($_POST['selected_mosques'])) {
    $_SESSION['error'] = "طلب غير صالح";
    header("Location: mosques.php");
    exit();
}

$selectedMosques = array_filter((array) $_POST['selected_mosques']);

if (empty($selectedMosques)) {
    $_SESSION['error'] = "لم يتم تحديد أي مساجد للحذف";
    header("Location: mosques.php");
    exit();
}

try {
    $placeholders = str_repeat('?,', count($selectedMosques) - 1) . '?';
    $stmt = $pdo->prepare("DELETE FROM mosques WHERE registration_number IN ($placeholders)");

    if ($stmt->execute($selectedMosques)) {
        $_SESSION['success'] = "تم حذف " . count($selectedMosques) . " مسجد(اً) بنجاح";
    } else {
        $_SESSION['error'] = "حدث خطأ أثناء الحذف الجماعي";
    }
} catch (PDOException $e) {
    error_log("Bulk mosque delete error: " . $e->getMessage());
    $_SESSION['error'] = "حدث خطأ أثناء الحذف الجماعي. يرجى المحاولة لاحقاً";
}

header("Location: mosques.php");
exit();
?>
