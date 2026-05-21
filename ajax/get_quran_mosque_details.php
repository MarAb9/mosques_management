<?php
require_once '../includes/config.php';
checkAuth();

if (!isset($_GET['id'])) {
    echo json_encode(['success' => false, 'message' => 'معرف المسجد غير محدد']);
    exit;
}

$programId = $_GET['id'];

try {
    // Fetch program details
    $stmt = $pdo->prepare("
        SELECT q.*, m.mosque_name, m.community, m.national_code
        FROM quran_memorization_programs q 
        JOIN mosques m ON q.mosque_registration_number = m.national_code 
        WHERE q.id = ?
    ");
    $stmt->execute([$programId]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$program) {
        echo json_encode(['success' => false, 'message' => 'مسجد التحفيظ غير موجود']);
        exit;
    }

    // Fetch responsibles
    $responsiblesStmt = $pdo->prepare("
        SELECT * FROM quran_program_responsibles 
        WHERE program_id = ? 
        ORDER BY id
    ");
    $responsiblesStmt->execute([$programId]);
    $responsibles = $responsiblesStmt->fetchAll(PDO::FETCH_ASSOC);

    $program['responsibles'] = $responsibles;

    echo json_encode(['success' => true, 'data' => $program]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'حدث خطأ: ' . $e->getMessage()]);
}
?>