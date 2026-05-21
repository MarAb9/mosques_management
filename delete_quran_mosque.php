<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

checkAuth();

if (!canDeleteMosque()) {
    http_response_code(403);
    die("غير مصرح بحذف مساجد التحفيظ");
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    $_SESSION['error'] = "طلب حذف غير صالح";
    header("Location: quran_mosques.php");
    exit();
}

verify_csrf_token('quran_mosques.php');

try {
    if (isset($_POST['selected_mosques'])) {
        $selectedMosques = array_filter((array) $_POST['selected_mosques']);

        if (empty($selectedMosques)) {
            $_SESSION['error'] = "لم يتم تحديد مسجد تحفيظ للحذف";
        } else {
            $pdo->beginTransaction();

            $placeholders = rtrim(str_repeat('?,', count($selectedMosques)), ',');

            $stmt = $pdo->prepare("DELETE FROM quran_program_responsibles WHERE program_id IN ($placeholders)");
            $stmt->execute($selectedMosques);

            $stmt = $pdo->prepare("DELETE FROM quran_memorization_programs WHERE id IN ($placeholders)");
            $stmt->execute($selectedMosques);

            $pdo->commit();
            $_SESSION['success'] = "تم حذف " . count($selectedMosques) . " مسجد تحفيظ بنجاح";
        }
    } elseif (isset($_POST['id'])) {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("DELETE FROM quran_program_responsibles WHERE program_id = ?");
        $stmt->execute([$_POST['id']]);

        $stmt = $pdo->prepare("DELETE FROM quran_memorization_programs WHERE id = ?");
        $stmt->execute([$_POST['id']]);

        $pdo->commit();
        $_SESSION['success'] = "تم حذف مسجد التحفيظ بنجاح";
    } else {
        $_SESSION['error'] = "لم يتم تحديد مسجد تحفيظ للحذف";
    }
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Quran program delete error: " . $e->getMessage());
    $_SESSION['error'] = "حدث خطأ أثناء حذف مسجد التحفيظ. يرجى المحاولة لاحقاً";
}

header("Location: quran_mosques.php");
exit();
?>
