<?php
/**
 * Automated tests for guide imam name ordering, sorting, and Arabic normalization.
 */

@session_start();
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/mosque_functions.php';

$passed = 0;
$failed = 0;

function assertTest($condition, $message) {
    global $passed, $failed;
    if ($condition) {
        echo "[PASS] $message\n";
        $passed++;
    } else {
        echo "[FAIL] $message\n";
        $failed++;
    }
}

echo "=== Running Guide Imams & Name Normalization Tests ===\n\n";

// Test 1: Arabic Normalization assertions
$testCases = [
    'أحمد شاكر' => 'احمد شاكر',
    'إبراهيمي محمد' => 'ابراهيمي محمد',
    'الزياني عبد العزيز' => 'الزياني عبد العزيز',
    'العشي طارق' => 'العشي طارق',
    'طَارِقُ العَشِيّ' => 'طارق العشي', // Diacritics removal
    'أسامة' => 'اسامه', // Alef and Teh Marbuta normalization
    'فاطمة الزهراء' => 'فاطمه الزهراء'
];

foreach ($testCases as $input => $expected) {
    $result = normalizeArabic($input);
    assertTest($result === $expected, "normalizeArabic('$input') -> '$result' (Expected: '$expected')");
}

// Test 2: Dropdown consistency & Absence of duplicates
try {
    $stmt = $pdo->query("SELECT id, display_name, display_name_normalized FROM guide_imams ORDER BY display_name_normalized");
    $imams = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check for duplicate IDs or display names
    $ids = [];
    $names = [];
    $duplicates = false;
    foreach ($imams as $imam) {
        if (in_array($imam['id'], $ids)) {
            $duplicates = true;
            echo "Duplicate ID found: {$imam['id']}\n";
        }
        if (in_array($imam['display_name'], $names)) {
            $duplicates = true;
            echo "Duplicate display name found: {$imam['display_name']}\n";
        }
        $ids[] = $imam['id'];
        $names[] = $imam['display_name'];
    }
    
    assertTest(!$duplicates && count($imams) > 0, "No duplicate IDs or display names found in the guide imams table (Total: " . count($imams) . ")");
    
    // Check that names are correctly sorted alphabetically using normalized name
    $sorted = true;
    for ($i = 0; $i < count($imams) - 1; $i++) {
        if (strcoll($imams[$i]['display_name_normalized'], $imams[$i + 1]['display_name_normalized']) > 0) {
            // Note: PHP strcoll uses locale settings, let's do a simple comparison check
            if ($imams[$i]['display_name_normalized'] > $imams[$i + 1]['display_name_normalized']) {
                $sorted = false;
                echo "Sorting mismatch: '{$imams[$i]['display_name_normalized']}' comes before '{$imams[$i+1]['display_name_normalized']}'\n";
            }
        }
    }
    assertTest($sorted, "Guide imams list is sorted correctly by normalized display name");
    
} catch (Exception $e) {
    assertTest(false, "DB checks failed: " . $e->getMessage());
}

echo "\n--- Tests Completed: $passed passed, $failed failed ---\n";
if ($failed > 0) {
    exit(1);
} else {
    exit(0);
}
