<?php

declare(strict_types=1);

/**
 * End-to-end HTTP test of the mosque CRUD flows — run inside the app
 * container: docker compose exec -T app php tests/mosque_crud_http_test.php
 *
 * Covers: login, add form, create (with image), duplicate national code,
 * CSRF rejection, edit form, update, image removal, single delete,
 * GET-delete bounce, bulk delete, guest redirect.
 */

const BASE = 'http://localhost';
const TEST_CODE_1 = 'TESTMVC001';
const TEST_CODE_2 = 'TESTMVC002';

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
        CURLOPT_HEADER => false,
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

function csrfFrom(string $html): string
{
    preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $html, $m);

    return $m[1] ?? '';
}

// Ensure clean slate
$pdo = new PDO('mysql:host=db;dbname=mosques_management;charset=utf8mb4', 'mosques', 'mosques_password');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->prepare('DELETE FROM mosques WHERE national_code IN (?, ?)')->execute([TEST_CODE_1, TEST_CODE_2]);

// ── Guest redirect ───────────────────────────────────────────────────────
$r = req('add_mosque.php');
check('Guest add form redirects to login', $r['status'] === 302 && str_contains($r['redirect'], 'login.php'));

// ── Login ────────────────────────────────────────────────────────────────
$r = req('login.php', [CURLOPT_POSTFIELDS => http_build_query([
    'username' => 'admin', 'password' => 'admin123', 'login' => '1',
])]);
check('Login redirects to dashboard', $r['status'] === 302 && str_contains($r['redirect'], 'index.php'));

// ── Add form ─────────────────────────────────────────────────────────────
$r = req('add_mosque.php');
$token = csrfFrom($r['body']);
check('Add form renders with CSRF token', $r['status'] === 200 && $token !== ''
    && str_contains($r['body'], 'إضافة مسجد جديد'));

// ── Create with image ────────────────────────────────────────────────────
$png = tempnam(sys_get_temp_dir(), 'img') . '.png';
$im = imagecreatetruecolor(8, 8);
imagepng($im, $png);

$fields = [
    'csrf_token' => $token,
    'mosque_name' => 'مسجد اختبار الهندسة',
    'national_code' => TEST_CODE_1,
    'address' => 'شارع الاختبار رقم 1',
    'construction_year' => '2005',
    'admin_type' => 'pashalik',
    'pashalik' => 'بركان',
    'pashalik_community' => 'بركان',
    'status' => 'مفتوح',
    'friday_prayer' => 'نعم',
    'quran_memorization' => 'نعم',
    'literacy_program' => 'لا',
    'guidance_program' => 'نعم',
    'funding_source' => 'الأوقاف',
    'imam_name' => 'إمام تجريبي',
    'imam_phone' => '0612-345-678',
    'notes' => 'ملاحظات <اختبار>',
    'latitude' => '34.920917',
    'longitude' => '-2.325653',
    'main_image' => new CURLFile($png, 'image/png', 'photo.png'),
];
$r = req('add_mosque.php', [CURLOPT_POSTFIELDS => $fields]);
check('Create redirects to list', $r['status'] === 302 && str_contains($r['redirect'], 'mosques.php'), "status={$r['status']}");

$row = $pdo->query("SELECT * FROM mosques WHERE national_code = '" . TEST_CODE_1 . "'")->fetch(PDO::FETCH_ASSOC);
check('Row inserted', is_array($row));
check('Name stored', $row['mosque_name'] === 'مسجد اختبار الهندسة');
check('Construction date normalized', $row['construction_date'] === '2005-01-01');
check('Phone digits only', $row['imam_phone'] === '0612345678');
check('Notes sanitized at input', $row['notes'] === 'ملاحظات &lt;اختبار&gt;');
check('GPS stored', abs((float) $row['latitude'] - 34.920917) < 0.0001);
check('Image path stored', is_string($row['main_image']) && str_starts_with($row['main_image'], 'uploads/mosques/mosque_'));
check('Image file exists', is_string($row['main_image']) && is_file('/var/www/html/' . $row['main_image']));
$imagePath = $row['main_image'];
$regId = $row['registration_number'];

// ── Duplicate national code ──────────────────────────────────────────────
$r = req('add_mosque.php', [CURLOPT_POSTFIELDS => [
    'csrf_token' => $token,
    'mosque_name' => 'مكرر',
    'national_code' => TEST_CODE_1,
    'address' => 'ع',
    'construction_year' => '2001',
    'admin_type' => 'pashalik',
    'pashalik' => 'بركان',
    'pashalik_community' => 'بركان',
]]);
check('Duplicate code re-renders form', $r['status'] === 200 && str_contains($r['body'], 'الرمز الوطني مسجل مسبقاً'));

// ── CSRF rejection ───────────────────────────────────────────────────────
$r = req('add_mosque.php', [CURLOPT_POSTFIELDS => ['mosque_name' => 'x']]);
check('Missing CSRF rejected 403', $r['status'] === 403 && str_contains($r['body'], 'طلب غير صالح'));

// ── Edit form ────────────────────────────────────────────────────────────
$r = req("edit_mosque.php?id={$regId}");
check('Edit form shows current values', $r['status'] === 200
    && str_contains($r['body'], 'مسجد اختبار الهندسة')
    && str_contains($r['body'], 'تعديل بيانات المسجد')
    && str_contains($r['body'], '2005'));

$editToken = csrfFrom($r['body']);

// ── Update (change name + remove image) ──────────────────────────────────
$r = req("edit_mosque.php?id={$regId}", [CURLOPT_POSTFIELDS => [
    'csrf_token' => $editToken,
    'mosque_name' => 'مسجد الاختبار المعدل',
    'national_code' => TEST_CODE_1,
    'address' => 'شارع الاختبار رقم 1',
    'construction_year' => '2006',
    'admin_type' => 'pashalik',
    'pashalik' => 'بركان',
    'pashalik_community' => 'بركان',
    'remove_image' => '1',
]]);
check('Update redirects to list', $r['status'] === 302 && str_contains($r['redirect'], 'mosques.php'));

$row = $pdo->query("SELECT * FROM mosques WHERE registration_number = {$regId}")->fetch(PDO::FETCH_ASSOC);
check('Name updated', $row['mosque_name'] === 'مسجد الاختبار المعدل');
check('Year updated', $row['construction_date'] === '2006-01-01');
check('Image cleared in DB', $row['main_image'] === null);
clearstatcache(); // the earlier is_file() cached a positive stat for this path
check('Image file deleted from disk', !is_file('/var/www/html/' . $imagePath));

// ── Edit with unknown id redirects ───────────────────────────────────────
$r = req('edit_mosque.php?id=99999999');
check('Edit unknown id redirects to list', $r['status'] === 302 && str_contains($r['redirect'], 'mosques.php'));

// ── Validation errors on update ──────────────────────────────────────────
$r = req("edit_mosque.php?id={$regId}", [CURLOPT_POSTFIELDS => [
    'csrf_token' => $editToken,
    'mosque_name' => '',
    'national_code' => TEST_CODE_1,
    'address' => '',
    'construction_year' => 'bad',
    'admin_type' => 'circle',
]]);
check('Update validation errors shown', $r['status'] === 200
    && str_contains($r['body'], 'اسم المسجد مطلوب')
    && str_contains($r['body'], 'العنوان مطلوب')
    && str_contains($r['body'], 'سنة البناء مطلوبة')
    && str_contains($r['body'], 'الدائرة مطلوبة'));

// ── GET delete bounces ───────────────────────────────────────────────────
$r = req('delete_mosque.php?id=' . $regId);
check('GET delete bounces to list', $r['status'] === 302 && str_contains($r['redirect'], 'mosques.php'));
$row = $pdo->query("SELECT COUNT(*) FROM mosques WHERE registration_number = {$regId}")->fetchColumn();
check('GET delete did not delete', (int) $row === 1);

// ── Single POST delete ───────────────────────────────────────────────────
$r = req('delete_mosque.php', [CURLOPT_POSTFIELDS => http_build_query([
    'csrf_token' => $editToken, 'id' => $regId,
])]);
check('POST delete redirects', $r['status'] === 302 && str_contains($r['redirect'], 'mosques.php'));
$count = $pdo->query("SELECT COUNT(*) FROM mosques WHERE registration_number = {$regId}")->fetchColumn();
check('Row deleted', (int) $count === 0);

// ── Bulk delete ──────────────────────────────────────────────────────────
$pdo->prepare("INSERT INTO mosques (mosque_name, national_code, address) VALUES ('م1', ?, 'ع'), ('م2', ?, 'ع')")
    ->execute([TEST_CODE_1, TEST_CODE_2]);
$ids = $pdo->query("SELECT registration_number FROM mosques WHERE national_code IN ('" . TEST_CODE_1 . "','" . TEST_CODE_2 . "')")
    ->fetchAll(PDO::FETCH_COLUMN);
$r = req('bulk_delete_mosques.php', [CURLOPT_POSTFIELDS => http_build_query([
    'csrf_token' => $editToken,
    'selected_mosques' => $ids,
])]);
check('Bulk delete redirects', $r['status'] === 302);
$count = $pdo->query("SELECT COUNT(*) FROM mosques WHERE national_code IN ('" . TEST_CODE_1 . "','" . TEST_CODE_2 . "')")->fetchColumn();
check('Bulk rows deleted', (int) $count === 0);

// ── Bulk delete without selection ────────────────────────────────────────
$r = req('bulk_delete_mosques.php', [CURLOPT_POSTFIELDS => http_build_query([
    'csrf_token' => $editToken,
])]);
check('Bulk delete without selection bounces', $r['status'] === 302);

echo "\n--- $pass passed, $fail failed ---\n";
exit($fail > 0 ? 1 : 0);
