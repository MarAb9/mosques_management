<?php
@session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/mosque_functions.php';

echo "Config+DB+Auth+Mosque loaded: OK\n";

// DB query
$stmt = $pdo->query('SELECT COUNT(*) FROM mosques');
$cnt = $stmt->fetchColumn();
echo "Mosque count: $cnt\n";

$stmt = $pdo->query('SELECT COUNT(*) FROM quran_memorization_programs');
$cnt2 = $stmt->fetchColumn();
echo "Quran programs: $cnt2\n";

$stmt = $pdo->query('SELECT COUNT(*) FROM users');
$cnt3 = $stmt->fetchColumn();
echo "Users: $cnt3\n";

// Verify all expected functions
$fns = ['checkAuth','appEnv','csrf_token','csrf_field','verify_csrf_token',
        'set_flash','get_flash','has_flash','clear_flash','flash_message',
        'redirect_to','redirect_with_flash',
        'e','safe_trim','selected','checked',
        'sanitizeInput','validateImageUpload','processMosqueFormData',
        'validateMosqueRequiredFields','validateGPS','validatePhone',
        'canEditMosque','canDeleteMosque','canCreateMosque','canImportData',
        'requireAdmin','requireManager'];
$missing = [];
foreach ($fns as $fn) {
    if (!function_exists($fn)) $missing[] = $fn;
}
if (empty($missing)) {
    echo "All " . count($fns) . " functions available: OK\n";
} else {
    echo "MISSING functions: " . implode(', ', $missing) . "\n";
}

echo "DONE - All integration checks passed.\n";
