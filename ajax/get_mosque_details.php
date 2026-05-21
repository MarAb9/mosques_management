<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkAuth();

// Prevent caching
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

// Validate input
if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'ID parameter is missing or empty']);
    exit();
}

$id = $_GET['id'];

try {
    // 1. Get mosque details
    $stmt = $pdo->prepare("
        SELECT 
            m.registration_number,
            m.national_code,
            m.mosque_name,
            m.address,
            m.admin_type,
            m.pashalik,
            m.circle,
            m.leadership,
            m.community,
            m.construction_date,
            m.status,
            m.friday_prayer,
            m.funding_source,
            m.imam_name,
            m.imam_registration,
            m.imam_phone,
            m.preacher_name,
            m.preacher_registration,
            m.preacher_phone,
            m.muezzin_name,
            m.muezzin_registration,
            m.muezzin_phone,
            m.quran_memorization,
            m.literacy_program,
            m.guidance_program,
            m.guide_imam,
            m.notes,
            m.administrative_attachment,
            m.main_image
        FROM mosques m
        WHERE m.national_code = ? OR m.registration_number = ?
    ");
    $stmt->execute([$id, $id]);
    $mosque = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$mosque) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Mosque not found']);
        exit();
    }

    // 2. Get Quran memorization programs linked to this mosque
    $stmt = $pdo->prepare("
        SELECT 
            q.id,
            q.has_quran_school,
            q.has_accommodation,
            q.created_at,
            q.updated_at
        FROM quran_memorization_programs q
        WHERE q.mosque_registration_number = ?
    ");
    $stmt->execute([$mosque['national_code']]);
    $programs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. For each program, get responsibles
    foreach ($programs as &$program) {
        $stmt = $pdo->prepare("
            SELECT 
                id,
                responsible_name,
                responsible_position,
                responsible_national_id,
                memorization_schedule,
                has_work_program,
                weekly_sessions,
                session_hours,
                female_students,
                male_students,
                total_students,
                regular_attendance,
                challenges,
                notes_suggestions,
                created_at,
                updated_at
            FROM quran_program_responsibles
            WHERE program_id = ?
            ORDER BY created_at
        ");
        $stmt->execute([$program['id']]);
        $program['responsibles'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // 4. Format response
    $response = [
        'success' => true,
        'data' => [
            'registration_number' => $mosque['registration_number'],
            'national_code' => $mosque['national_code'],
            'mosque_name' => $mosque['mosque_name'],
            'address' => $mosque['address'],
            'admin_type' => $mosque['admin_type'],
            'pashalik' => $mosque['pashalik'],
            'circle' => $mosque['circle'],
            'leadership' => $mosque['leadership'],
            'community' => $mosque['community'],
            'construction_year' => $mosque['construction_date'] ? date('Y', strtotime($mosque['construction_date'])) : null,
            'status' => $mosque['status'],
            'friday_prayer' => $mosque['friday_prayer'],
            'funding_source' => $mosque['funding_source'],
            'imam_name' => $mosque['imam_name'],
            'imam_registration' => $mosque['imam_registration'],
            'imam_phone' => $mosque['imam_phone'],
            'preacher_name' => $mosque['preacher_name'],
            'preacher_registration' => $mosque['preacher_registration'],
            'preacher_phone' => $mosque['preacher_phone'],
            'muezzin_name' => $mosque['muezzin_name'],
            'muezzin_registration' => $mosque['muezzin_registration'],
            'muezzin_phone' => $mosque['muezzin_phone'],
            'quran_memorization' => $mosque['quran_memorization'],
            'literacy_program' => $mosque['literacy_program'],
            'guidance_program' => $mosque['guidance_program'],
            'guide_imam' => $mosque['guide_imam'],
            'notes' => $mosque['notes'],
            'administrative_attachment' => $mosque['administrative_attachment'],
            'main_image' => $mosque['main_image'],
            'quran_programs' => $programs
        ],
        'timestamp' => time()
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Database error',
        'error' => $e->getMessage()
    ]);
}
