<?php

declare(strict_types=1);

/**
 * Guide imam Arabic normalization + reference-data checks — run inside
 * the app container: docker compose exec -T app php tests/guide_imams_test.php
 *
 * (Port of the pre-MVC test_guide_imams.php; normalization now lives in
 * App\Helpers\Arabic.)
 */

use App\Helpers\Arabic;

@session_start();

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$pdo = $app->database->pdo();

$passed = 0;
$failed = 0;

function assertTest(bool $condition, string $message): void
{
    global $passed, $failed;
    echo ($condition ? '[PASS] ' : '[FAIL] ') . $message . "\n";
    $condition ? $passed++ : $failed++;
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
    'فاطمة الزهراء' => 'فاطمه الزهراء',
];

foreach ($testCases as $input => $expected) {
    $result = Arabic::normalize($input);
    assertTest($result === $expected, "Arabic::normalize('$input') -> '$result' (Expected: '$expected')");
}

// Test 2: Dropdown consistency & absence of duplicates
try {
    $stmt = $pdo->query('SELECT id, display_name, display_name_normalized FROM guide_imams ORDER BY display_name_normalized');
    $imams = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

    assertTest(!$duplicates && count($imams) > 0, 'No duplicate IDs or display names in guide_imams (Total: ' . count($imams) . ')');

    // Sorted by normalized name
    $sorted = true;
    for ($i = 0; $i < count($imams) - 1; $i++) {
        if ($imams[$i]['display_name_normalized'] > $imams[$i + 1]['display_name_normalized']) {
            $sorted = false;
            echo "Sorting mismatch: '{$imams[$i]['display_name_normalized']}' before '{$imams[$i + 1]['display_name_normalized']}'\n";
        }
    }
    assertTest($sorted, 'Guide imams list is sorted correctly by normalized display name');
} catch (Exception $e) {
    assertTest(false, 'DB checks failed: ' . $e->getMessage());
}

echo "\n--- Tests Completed: $passed passed, $failed failed ---\n";
exit($failed > 0 ? 1 : 0);
