<?php

declare(strict_types=1);

/**
 * End-to-end HTTP test of the Quran module — run inside the app container:
 *   docker compose exec -T app php tests/quran_http_test.php
 */

const BASE = 'http://localhost';

$cookieJar = tempnam(sys_get_temp_dir(), 'ck');
$pass = 0;
$fail = 0;

function req(string $path, array $opts = []): array
{
    global $cookieJar;
    $ch = curl_init(BASE . '/' . ltrim($path, '/'));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_COOKIEJAR => $cookieJar,
        CURLOPT_COOKIEFILE => $cookieJar,
        CURLOPT_FOLLOWLOCATION => false,
    ] + $opts);
    $body = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $redirect = (string) (curl_getinfo($ch, CURLINFO_REDIRECT_URL) ?: '');
    curl_close($ch);

    return ['status' => $status, 'body' => $body, 'redirect' => $redirect];
}

function check(string $name, bool $ok, string $extra = ''): void
{
    global $pass, $fail;
    echo ($ok ? '[PASS] ' : '[FAIL] ') . $name . ($extra !== '' ? " ($extra)" : '') . "\n";
    $ok ? $pass++ : $fail++;
}

$pdo = new PDO('mysql:host=db;dbname=mosques_management;charset=utf8mb4', 'mosques', 'mosques_password');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// A mosque without a program to use for creation
$freeCode = (string) $pdo->query('
    SELECT m.national_code FROM mosques m
    LEFT JOIN quran_memorization_programs q ON m.national_code = q.mosque_registration_number
    WHERE q.id IS NULL LIMIT 1
')->fetchColumn();
$programCountBefore = (int) $pdo->query('SELECT COUNT(*) FROM quran_memorization_programs')->fetchColumn();

// ── Login ────────────────────────────────────────────────────────────────
req('login.php', [CURLOPT_POSTFIELDS => http_build_query(['username' => 'admin', 'password' => 'admin123', 'login' => '1'])]);

// ── List page ────────────────────────────────────────────────────────────
$r = req('quran_mosques.php');
check('List renders with totals', $r['status'] === 200
    && str_contains($r['body'], 'قائمة مساجد التحفيظ')
    && str_contains($r['body'], 'إجمالي مساجد التحفيظ'));
// 10 row buttons + 2 references in the page script
check('List rows present', substr_count($r['body'], 'view-quran-mosque-btn') >= 10);
preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $r['body'], $m);
$token = $m[1] ?? '';
if ($token === '') {
    preg_match("/csrfInput\.value = '([a-f0-9]{64})'/", $r['body'], $m);
    $token = $m[1] ?? '';
}
check('List page carries CSRF token', $token !== '');

// ── List filters ─────────────────────────────────────────────────────────
$r = req('quran_mosques.php?has_quran_school=' . urlencode('نعم'));
check('School filter works', $r['status'] === 200);
$r = req('quran_mosques.php?sort=mosque_name&order=asc');
check('Sorting works', $r['status'] === 200);

// ── Details AJAX ─────────────────────────────────────────────────────────
$anyId = (int) $pdo->query('SELECT id FROM quran_memorization_programs LIMIT 1')->fetchColumn();
$r = req('ajax/get_quran_mosque_details.php?id=' . $anyId);
$json = json_decode($r['body'], true);
check('Details AJAX ok', ($json['success'] ?? false) === true
    && isset($json['data']['mosque_name'], $json['data']['responsibles']));
$r = req('ajax/get_quran_mosque_details.php?id=999999');
$json = json_decode($r['body'], true);
check('Details AJAX unknown id', ($json['success'] ?? true) === false
    && $json['message'] === 'مسجد التحفيظ غير موجود');
$r = req('ajax/get_quran_mosque_details.php');
$json = json_decode($r['body'], true);
check('Details AJAX missing id', ($json['message'] ?? '') === 'معرف المسجد غير محدد');

// ── Add form ─────────────────────────────────────────────────────────────
$r = req('add_quran_mosque.php');
check('Add form renders', $r['status'] === 200 && str_contains($r['body'], 'إضافة مسجد تحفيظ'));
check('Add form lists only mosques without programs', !str_contains($r['body'], 'value="' . $freeCode . '"') === false);
preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $r['body'], $m);
$token = $m[1] ?? $token;

// ── Create ───────────────────────────────────────────────────────────────
$r = req('add_quran_mosque.php', [CURLOPT_POSTFIELDS => http_build_query([
    'csrf_token' => $token,
    'mosque_registration_number' => $freeCode,
    'has_quran_school' => 'نعم',
    'has_accommodation' => 'لا',
    'responsibles' => [
        1 => [
            'name' => 'مسؤول تجريبي',
            'position' => 'إمام',
            'national_id' => 'AB123',
            'has_work_program' => 'نعم',
            'memorization_schedule' => 'باستمرار',
            'weekly_sessions' => '4',
            'session_hours' => '1.5',
            'male_students' => '12',
            'female_students' => '8',
            'regular_attendance' => 'نعم',
            'challenges' => 'تحدي <تجريبي>',
            'notes_suggestions' => '',
        ],
        // the real form always submits every field; an empty
        // memorization_schedule would hit the strict enum and roll back
        // (legacy-faithful behavior)
        2 => [
            'name' => 'مسؤولة ثانية',
            'position' => 'مدرر(ة)',
            'memorization_schedule' => 'بصفة منقطعة',
            'has_work_program' => 'لا',
            'weekly_sessions' => '0',
            'session_hours' => '0',
            'male_students' => '0',
            'female_students' => '0',
            'regular_attendance' => 'لا',
        ],
    ],
])]);
check('Create redirects to list', $r['status'] === 302 && str_contains($r['redirect'], 'quran_mosques.php'));

$prog = $pdo->prepare('SELECT * FROM quran_memorization_programs WHERE mosque_registration_number = ?');
$prog->execute([$freeCode]);
$program = $prog->fetch(PDO::FETCH_ASSOC);
check('Program row created', is_array($program) && $program['has_quran_school'] === 'نعم');
$resp = $pdo->prepare('SELECT * FROM quran_program_responsibles WHERE program_id = ? ORDER BY id');
$resp->execute([$program['id']]);
$responsibles = $resp->fetchAll(PDO::FETCH_ASSOC);
check('Two responsibles inserted', count($responsibles) === 2);
check('Responsible values mapped', $responsibles[0]['responsible_name'] === 'مسؤول تجريبي'
    && (int) $responsibles[0]['weekly_sessions'] === 4
    && (float) $responsibles[0]['session_hours'] === 1.5
    && (int) $responsibles[0]['male_students'] === 12
    && str_contains($responsibles[0]['challenges'], '&lt;تجريبي&gt;'));

$programId = (int) $program['id'];

// ── Create without CSRF ─────────────────────────────────────────────────
$r = req('add_quran_mosque.php', [CURLOPT_POSTFIELDS => http_build_query(['mosque_registration_number' => 'x'])]);
check('Create without CSRF bounces to add page', $r['status'] === 302 && str_contains($r['redirect'], 'add_quran_mosque.php'));

// ── Edit form ────────────────────────────────────────────────────────────
$r = req('edit_quran_mosque.php?id=' . $programId);
check('Edit form shows program + responsibles', $r['status'] === 200
    && str_contains($r['body'], 'تعديل مسجد تحفيظ')
    && str_contains($r['body'], 'مسؤول تجريبي'));
preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $r['body'], $m);
$token = $m[1] ?? $token;

$r = req('edit_quran_mosque.php');
check('Edit without id redirects', $r['status'] === 302 && str_contains($r['redirect'], 'quran_mosques.php'));
$r = req('edit_quran_mosque.php?id=999999');
check('Edit unknown id redirects', $r['status'] === 302);

// ── Update (replace responsibles with one) ──────────────────────────────
$r = req('edit_quran_mosque.php?id=' . $programId, [CURLOPT_POSTFIELDS => http_build_query([
    'csrf_token' => $token,
    'mosque_registration_number' => $freeCode,
    'has_quran_school' => 'مركز تحفيظ',
    'has_accommodation' => 'نعم',
    'responsibles' => [
        0 => [
            'name' => 'مسؤول محدث',
            'memorization_schedule' => 'باستمرار',
            'weekly_sessions' => '2',
            'male_students' => '5',
            'female_students' => '5',
        ],
    ],
])]);
check('Update redirects to list', $r['status'] === 302 && str_contains($r['redirect'], 'quran_mosques.php'));

$prog->execute([$freeCode]);
$program = $prog->fetch(PDO::FETCH_ASSOC);
check('Program updated', $program['has_quran_school'] === 'مركز تحفيظ' && $program['has_accommodation'] === 'نعم');
$resp->execute([$programId]);
$responsibles = $resp->fetchAll(PDO::FETCH_ASSOC);
check('Responsibles replaced (2 -> 1)', count($responsibles) === 1
    && $responsibles[0]['responsible_name'] === 'مسؤول محدث');

// ── Update with bad CSRF bounces to edit?id ──────────────────────────────
$r = req('edit_quran_mosque.php?id=' . $programId, [CURLOPT_POSTFIELDS => http_build_query(['csrf_token' => 'bad'])]);
check('Update bad CSRF bounces to edit page', $r['status'] === 302
    && str_contains($r['redirect'], 'edit_quran_mosque.php?id=' . $programId));

// ── GET delete bounces ──────────────────────────────────────────────────
$r = req('delete_quran_mosque.php?id=' . $programId);
check('GET delete bounces', $r['status'] === 302 && str_contains($r['redirect'], 'quran_mosques.php'));
$count = (int) $pdo->query("SELECT COUNT(*) FROM quran_memorization_programs WHERE id = {$programId}")->fetchColumn();
check('GET delete did not delete', $count === 1);

// ── POST delete (single) ────────────────────────────────────────────────
$r = req('delete_quran_mosque.php', [CURLOPT_POSTFIELDS => http_build_query([
    'csrf_token' => $token, 'id' => $programId,
])]);
check('POST delete redirects', $r['status'] === 302);
$count = (int) $pdo->query("SELECT COUNT(*) FROM quran_memorization_programs WHERE id = {$programId}")->fetchColumn();
$orphans = (int) $pdo->query("SELECT COUNT(*) FROM quran_program_responsibles WHERE program_id = {$programId}")->fetchColumn();
check('Program deleted with responsibles', $count === 0 && $orphans === 0);

$programCountAfter = (int) $pdo->query('SELECT COUNT(*) FROM quran_memorization_programs')->fetchColumn();
check('Program count back to baseline', $programCountAfter === $programCountBefore);

echo "\n--- $pass passed, $fail failed ---\n";
exit($fail > 0 ? 1 : 0);
