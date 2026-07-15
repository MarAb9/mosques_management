<?php

declare(strict_types=1);

/**
 * End-to-end HTTP test of import/export — run inside the app container:
 *   docker compose exec -T app php tests/import_export_http_test.php
 */

require '/var/www/html/vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

const BASE = 'http://localhost';
const CODE_NEW = '990000003';

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
    $type = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    return ['status' => $status, 'body' => $body, 'type' => $type];
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

$pdo = new PDO('mysql:host=db;dbname=mosques_management;charset=utf8mb4', 'mosques', 'mosques_password');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->prepare('DELETE FROM mosques WHERE national_code = ?')->execute([CODE_NEW]);
$existingCode = (string) $pdo->query('SELECT national_code FROM mosques ORDER BY registration_number LIMIT 1')->fetchColumn();
$totalBefore = (int) $pdo->query('SELECT COUNT(*) FROM mosques')->fetchColumn();

// ── Guest is redirected ─────────────────────────────────────────────────
$r = req('import_export.php');
check('Guest redirected to login', $r['status'] === 302);

// ── Login ────────────────────────────────────────────────────────────────
$r = req('login.php');
$loginToken = csrfFrom($r['body']);
check('Login form carries CSRF token', $loginToken !== '');
$r = req('login.php', [CURLOPT_POSTFIELDS => http_build_query([
    'csrf_token' => $loginToken,
    'username' => 'admin',
    'password' => 'admin123',
    'login' => '1',
])]);
check('Login redirects to dashboard', $r['status'] === 302);

// ── Page renders ─────────────────────────────────────────────────────────
$r = req('import_export.php');
check('Page renders', $r['status'] === 200 && str_contains($r['body'], 'نظام استيراد وتصدير بيانات المساجد'));
check('Import form shown to admin', str_contains($r['body'], 'استيراد البيانات'));
check('Filter modal has guide imams', str_contains($r['body'], 'exportGuideImam'));
preg_match('/name="csrf_token" value="([a-f0-9]{64})"/', $r['body'], $m);
$token = $m[1] ?? '';
check('CSRF token present', $token !== '');

// ── Full Excel export ────────────────────────────────────────────────────
$r = req('import_export.php?export=1');
check('Excel export streams xlsx', $r['status'] === 200
    && str_starts_with($r['body'], 'PK')
    && str_contains($r['type'], 'spreadsheetml'));

$tmp = tempnam(sys_get_temp_dir(), 'xls') . '.xlsx';
file_put_contents($tmp, $r['body']);
$sheet = IOFactory::load($tmp)->getActiveSheet();
check('Export row count matches DB', $sheet->getHighestRow() === $totalBefore + 1, "rows={$sheet->getHighestRow()} db={$totalBefore}");
check('Export header intact', $sheet->getCell('B1')->getValue() === 'اسم المسجد'
    && $sheet->getCell('AA1')->getValue() === 'القيادة');
check('Export sheet is RTL', $sheet->getRightToLeft());

// ── No-location Excel export ─────────────────────────────────────────────
$r = req('import_export.php?export=1&no_location=1&group_by_guide=1');
file_put_contents($tmp, $r['body']);
$sheet = IOFactory::load($tmp)->getActiveSheet();
$noLocCount = (int) $pdo->query("SELECT COUNT(*) FROM mosques m WHERE (m.latitude IS NULL OR m.longitude IS NULL OR m.latitude = '' OR m.longitude = '') AND m.guide_imam_id IS NOT NULL")->fetchColumn();
check('No-location export shape', $sheet->getCell('A1')->getValue() === 'اسم المسجد'
    && $sheet->getCell('H1')->getValue() === 'الإمام المرشد');
check('No-location export row count', $sheet->getHighestRow() === max($noLocCount + 1, 1), "rows={$sheet->getHighestRow()} db={$noLocCount}");

// ── Word export ──────────────────────────────────────────────────────────
$r = req('import_export.php?export=1&no_location=1&group_by_guide=1&format=word');
check('Word export streams docx', $r['status'] === 200
    && str_starts_with($r['body'], 'PK')
    && str_contains($r['type'], 'wordprocessingml'));

// ── Import: build a workbook with 1 new + 1 duplicate + 1 incomplete ────
$wb = new Spreadsheet();
$ws = $wb->getActiveSheet();
$headers = ['A1' => 'ر.ت.ع', 'B1' => 'اسم المسجد', 'C1' => 'العنوان', 'D1' => 'تاريخ البناء', 'E1' => 'الرمز الوطني', 'F1' => 'الوضعية', 'G1' => 'الجمعة'];
foreach ($headers as $cell => $val) {
    $ws->setCellValue($cell, $val);
}
// new row (X = pashalik is required: admin_type is a strict enum, a row
// without X/Z is skipped by design — same as the legacy importer)
$ws->setCellValue('B2', 'مسجد استيراد تجريبي');
$ws->setCellValue('C2', 'عنوان مستورد');
$ws->setCellValue('D2', '2010-05-01');
$ws->setCellValue('E2', CODE_NEW);
$ws->setCellValue('F2', 'مفتوح');
$ws->setCellValue('G2', 'نعم');
$ws->setCellValue('H2', 'بركان');
$ws->setCellValue('X2', 'بركان');
// duplicate row (existing national code)
$ws->setCellValue('B3', 'مكرر');
$ws->setCellValue('C3', 'ع');
$ws->setCellValue('E3', $existingCode);
// incomplete row (name but no national code)
$ws->setCellValue('B4', 'ناقص');

$importFile = tempnam(sys_get_temp_dir(), 'imp') . '.xlsx';
(new Xlsx($wb))->save($importFile);

// Import without CSRF -> 403
$r = req('import_export.php', [CURLOPT_POSTFIELDS => [
    'import_file' => new CURLFile($importFile, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'data.xlsx'),
]]);
check('Import without CSRF rejected', $r['status'] === 403 && str_contains($r['body'], 'طلب غير صالح'));

// Preview exposes aggregate counts only: no row diagnostics or error report.
$r = req('import_export.php', [CURLOPT_POSTFIELDS => [
    'csrf_token' => $token,
    'preview_import' => '1',
    'import_file' => new CURLFile($importFile, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'data.xlsx'),
]]);
preg_match('/name="import_token" value="([a-f0-9]{32})"/', $r['body'], $previewMatch);
$previewToken = $previewMatch[1] ?? '';
check('Preview hides error logs and row details', $r['status'] === 200
    && str_contains($r['body'], 'ملخص ملف الاستيراد')
    && $previewToken !== ''
    && !str_contains($r['body'], 'import_error_report')
    && !str_contains($r['body'], '<th>السطر</th>')
    && !str_contains($r['body'], 'تقرير الأخطاء'));

// Import with CSRF
$r = req('import_export.php', [CURLOPT_POSTFIELDS => [
    'csrf_token' => $token,
    'import_file' => new CURLFile($importFile, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'data.xlsx'),
]]);
check('Import redirects back', $r['status'] === 302);

$r = req('import_export.php');
check('Import success omits row diagnostics', str_contains($r['body'], 'تم استيراد 1 مسجد بنجاح')
    && !str_contains($r['body'], 'تم تخطي')
    && !str_contains($r['body'], 'تم تجاهل'), 'concise flash rendered');

$row = $pdo->query("SELECT * FROM mosques WHERE national_code = '" . CODE_NEW . "'")->fetch(PDO::FETCH_ASSOC);
check('Imported row exists', is_array($row));
check('Imported values mapped', $row['mosque_name'] === 'مسجد استيراد تجريبي'
    && $row['construction_date'] === '2010-01-01'
    && $row['friday_prayer'] === 'نعم');

// re-import same file: everything duplicate/skipped
$r = req('import_export.php', [CURLOPT_POSTFIELDS => [
    'csrf_token' => $token,
    'import_file' => new CURLFile($importFile, 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'data.xlsx'),
]]);
$r = req('import_export.php');
check('Re-import stays concise', str_contains($r['body'], 'تم استيراد 0 مسجد بنجاح')
    && !str_contains($r['body'], 'تم تجاهل'));

// cleanup
$pdo->prepare('DELETE FROM mosques WHERE national_code = ?')->execute([CODE_NEW]);
if ($previewToken !== '') {
    foreach (glob('/var/www/html/storage/cache/import-previews/' . $previewToken . '.*') ?: [] as $previewPath) {
        @unlink($previewPath);
    }
}

echo "\n--- $pass passed, $fail failed ---\n";
exit($fail > 0 ? 1 : 0);
