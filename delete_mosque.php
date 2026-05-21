<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkAuth();

if (!canDeleteMosque()) {
    http_response_code(403);
    die("غير مصرح بحذف المساجد");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $_SESSION['error'] = "طلب حذف غير صالح";
    header("Location: mosques.php");
    exit();
}

verify_csrf_token('mosques.php');

try {
    if (isset($_POST['selected_mosques'])) {
        $selectedMosques = array_filter((array) $_POST['selected_mosques']);

        if (empty($selectedMosques)) {
            $_SESSION['error'] = "لم يتم تحديد مسجد للحذف";
        } else {
            $placeholders = rtrim(str_repeat('?,', count($selectedMosques)), ',');
            $stmt = $pdo->prepare("DELETE FROM mosques WHERE registration_number IN ($placeholders)");
            $stmt->execute($selectedMosques);
            $_SESSION['success'] = "تم حذف " . count($selectedMosques) . " مسجد(اً) بنجاح";
        }
    } elseif (isset($_POST['id'])) {
        $stmt = $pdo->prepare("DELETE FROM mosques WHERE registration_number = ?");
        $stmt->execute([$_POST['id']]);
        $_SESSION['success'] = "تم حذف المسجد بنجاح";
    } else {
        $_SESSION['error'] = "لم يتم تحديد مسجد للحذف";
    }
} catch (PDOException $e) {
    error_log("Mosque delete error: " . $e->getMessage());
    $_SESSION['error'] = "حدث خطأ أثناء حذف المسجد. يرجى المحاولة لاحقاً";
}

header("Location: mosques.php");
exit();
?>
