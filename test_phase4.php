<?php
/**
 * Phase 4.1 verification script — run inside Docker container.
 * Tests that all helpers load correctly via config.php.
 */

// Suppress session warnings in CLI
@session_start();

require_once __DIR__ . '/includes/config.php';

$results = [];

// Test 1: PDO connection
$results['PDO connection'] = ($pdo instanceof PDO) ? 'PASS' : 'FAIL';

// Test 2: DB constants defined
$results['DB_HOST defined'] = defined('DB_HOST') ? 'PASS' : 'FAIL';
$results['DB_NAME defined'] = defined('DB_NAME') ? 'PASS' : 'FAIL';

// Test 3: Core functions
$results['checkAuth exists'] = function_exists('checkAuth') ? 'PASS' : 'FAIL';
$results['appEnv exists'] = function_exists('appEnv') ? 'PASS' : 'FAIL';

// Test 4: CSRF helpers
$results['csrf_token exists'] = function_exists('csrf_token') ? 'PASS' : 'FAIL';
$results['csrf_field exists'] = function_exists('csrf_field') ? 'PASS' : 'FAIL';
$results['verify_csrf_token exists'] = function_exists('verify_csrf_token') ? 'PASS' : 'FAIL';

// Test 5: Flash helpers
$results['set_flash exists'] = function_exists('set_flash') ? 'PASS' : 'FAIL';
$results['get_flash exists'] = function_exists('get_flash') ? 'PASS' : 'FAIL';
$results['has_flash exists'] = function_exists('has_flash') ? 'PASS' : 'FAIL';
$results['clear_flash exists'] = function_exists('clear_flash') ? 'PASS' : 'FAIL';
$results['flash_message exists'] = function_exists('flash_message') ? 'PASS' : 'FAIL';

// Test 6: Redirect helpers
$results['redirect_to exists'] = function_exists('redirect_to') ? 'PASS' : 'FAIL';
$results['redirect_with_flash exists'] = function_exists('redirect_with_flash') ? 'PASS' : 'FAIL';

// Test 7: Output helpers
$results['e exists'] = function_exists('e') ? 'PASS' : 'FAIL';
$results['safe_trim exists'] = function_exists('safe_trim') ? 'PASS' : 'FAIL';
$results['selected exists'] = function_exists('selected') ? 'PASS' : 'FAIL';
$results['checked exists'] = function_exists('checked') ? 'PASS' : 'FAIL';

// Test 8: Flash helper behavior
set_flash('success', 'test message');
$results['set_flash works'] = (get_flash('success') === 'test message') ? 'PASS' : 'FAIL';
$results['has_flash works'] = has_flash('success') ? 'PASS' : 'FAIL';
clear_flash('success');
$results['clear_flash works'] = !has_flash('success') ? 'PASS' : 'FAIL';

// Test 9: Output helper behavior
$results['e() escapes HTML'] = (e('<script>') === '&lt;script&gt;') ? 'PASS' : 'FAIL';
$results['e() handles null'] = (e(null) === '') ? 'PASS' : 'FAIL';
$results['safe_trim works'] = (safe_trim('  hi  ') === 'hi') ? 'PASS' : 'FAIL';
$results['selected match'] = (selected('a', 'a') === ' selected') ? 'PASS' : 'FAIL';
$results['selected no match'] = (selected('a', 'b') === '') ? 'PASS' : 'FAIL';
$results['checked match'] = (checked('1', '1') === ' checked') ? 'PASS' : 'FAIL';

// Test 10: DB query works
try {
    $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM mosques");
    $count = $stmt->fetchColumn();
    $results['DB query works'] = ($count > 0) ? "PASS (mosques=$count)" : 'FAIL (0 mosques)';
} catch (Exception $ex) {
    $results['DB query works'] = 'FAIL: ' . $ex->getMessage();
}

// Test 11: Backward compat - $_SESSION flash keys
$_SESSION['success'] = 'compat test';
$results['$_SESSION compat'] = (get_flash('success') === 'compat test') ? 'PASS' : 'FAIL';
unset($_SESSION['success']);

// Output
$pass = 0;
$fail = 0;
foreach ($results as $name => $status) {
    $icon = (strpos($status, 'PASS') === 0) ? 'PASS' : 'FAIL';
    echo str_pad($name, 35) . " : $status\n";
    if ($icon === 'PASS') $pass++; else $fail++;
}
echo "\n--- $pass passed, $fail failed ---\n";
