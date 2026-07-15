<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Config;
use App\Repositories\MosqueRepository;
use App\Validators\MosqueValidator;
use PhpOffice\PhpSpreadsheet\IOFactory;
use RuntimeException;
use ZipArchive;

/**
 * Excel import (legacy import_export.php POST branch).
 *
 * Same sheet layout (columns B..AA), same duplicate policy (skip existing
 * national codes), same transaction + per-row error tolerance, and the
 * same result message counters.
 */
final class MosqueImportService
{
    public function __construct(
        private readonly Database $db,
        private readonly MosqueRepository $mosques,
        private readonly MosqueValidator $validator,
        private readonly Config $config,
    ) {
    }

    /** @param array<string, mixed> $file @return list<string> */
    public function validateUpload(array $file): array
    {
        $errors = [];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            return ['تعذر تحميل ملف الاستيراد.'];
        }

        $size = (int) ($file['size'] ?? 0);
        $maximumSize = (int) $this->config->get('uploads.imports.max_size', 5 * 1024 * 1024);
        if ($size <= 0 || $size > $maximumSize) {
            $errors[] = 'حجم ملف الاستيراد غير صالح أو يتجاوز 5MB.';
        }

        $extension = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
        if (!in_array($extension, (array) $this->config->get('uploads.imports.allowed_extensions', []), true)) {
            $errors[] = 'يجب أن يكون ملف الاستيراد بصيغة XLSX أو XLS.';
        }

        $tmpFile = (string) ($file['tmp_name'] ?? '');
        if (!is_file($tmpFile)) {
            return [...$errors, 'ملف الاستيراد المؤقت غير موجود.'];
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo === false ? '' : (string) finfo_file($finfo, $tmpFile);
        if ($finfo !== false) finfo_close($finfo);
        if (!in_array($mime, (array) $this->config->get('uploads.imports.allowed_mime_types', []), true)) {
            $errors[] = 'محتوى ملف الاستيراد لا يطابق صيغة Excel مسموحة.';
        }

        if ($extension === 'xlsx' && !$this->zipSizeIsSafe($tmpFile)) {
            $errors[] = 'حجم محتوى ملف Excel بعد فك الضغط يتجاوز الحد المسموح.';
        }

        return $errors;
    }

    /**
     * Import a workbook from an uploaded temporary file.
     *
     * @return array{imported: int, skipped: int, duplicates: int}
     */
    public function import(string $tmpFile, string $originalName = ''): array
    {
        $pdo = $this->db->pdo();

        $type = IOFactory::identify($tmpFile);
        if (!in_array($type, ['Xlsx', 'Xls'], true)) {
            throw new RuntimeException('Unsupported spreadsheet type.');
        }
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (($type === 'Xlsx' && $extension !== 'xlsx') || ($type === 'Xls' && $extension !== 'xls')) {
            throw new RuntimeException('Spreadsheet extension does not match its content.');
        }
        $reader = IOFactory::createReader($type);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tmpFile);
        $sheet = $spreadsheet->getActiveSheet();
        $highestRow = $sheet->getHighestDataRow();
        $maximumRows = (int) $this->config->get('uploads.imports.max_rows', 5000);
        if ($highestRow > $maximumRows + 1) {
            throw new RuntimeException('Spreadsheet row limit exceeded.');
        }

        $pdo->beginTransaction();
        $importedCount = 0;
        $skippedCount = 0;
        $duplicateCount = 0;
        $importedCodes = [];

        try {
            for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
                $row = $sheet->rangeToArray("A{$rowNumber}:AA{$rowNumber}", null, false, true, true)[$rowNumber] ?? [];
                if ($this->cell($row, 'B') === '' || $this->cell($row, 'E') === '') {
                    $skippedCount++;
                    continue;
                }

                if ($this->mosques->nationalCodeExists($this->cell($row, 'E'))) {
                    $duplicateCount++;
                    continue;
                }

                $adminType = $this->cell($row, 'X') !== '' ? 'pashalik' : ($this->cell($row, 'Z') !== '' ? 'circle' : '');

                $data = [
                    'mosque_name' => $this->cell($row, 'B'),
                    'address' => $this->cell($row, 'C'),
                    'construction_date' => $this->normalizeConstructionDate($this->cell($row, 'D')),
                    'national_code' => $this->cell($row, 'E'),
                    'status' => $this->cell($row, 'F') ?: 'مفتوح',
                    'friday_prayer' => $this->cell($row, 'G') ?: 'لا',
                    'community' => $this->cell($row, 'H'),
                    'funding_source' => $this->cell($row, 'I') ?: 'الأوقاف',
                    'imam_name' => $this->cell($row, 'J'),
                    'imam_registration' => $this->cell($row, 'K'),
                    'imam_phone' => $this->cell($row, 'L'),
                    'preacher_name' => $this->cell($row, 'M'),
                    'preacher_registration' => $this->cell($row, 'N'),
                    'preacher_phone' => $this->cell($row, 'O'),
                    'muezzin_name' => $this->cell($row, 'P'),
                    'muezzin_registration' => $this->cell($row, 'Q'),
                    'muezzin_phone' => $this->cell($row, 'R'),
                    'quran_memorization' => $this->cell($row, 'S') ?: 'لا',
                    'literacy_program' => $this->cell($row, 'T') ?: 'لا',
                    'guidance_program' => $this->cell($row, 'U') ?: 'لا',
                    'guide_imam' => $this->cell($row, 'V'),
                    'notes' => $this->cell($row, 'W'),
                    'admin_type' => $adminType,
                    'pashalik' => $this->cell($row, 'X'),
                    'administrative_attachment' => $this->cell($row, 'Y'),
                    'circle' => $this->cell($row, 'Z'),
                    'leadership' => $this->cell($row, 'AA'),
                    'latitude' => null,
                    'longitude' => null,
                ];

                if ($this->validator->requiredFields($data, $adminType) !== []) {
                    $skippedCount++;
                    continue;
                }

                $this->mosques->insertFromImport($data);
                $importedCount++;
                $importedCodes[] = (string) $data['national_code'];
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        if ($importedCodes !== []) {
            $this->recordLastImport($importedCodes, $originalName);
        }

        return [
            'imported' => $importedCount,
            'skipped' => $skippedCount,
            'duplicates' => $duplicateCount,
            'imported_codes' => $importedCodes,
        ];
    }


    /**
     * Store an uploaded workbook temporarily and return a safe row preview.
     *
     * @param array<string, mixed> $file
     * @return array<string, mixed>
     */
    public function storePreview(array $file): array
    {
        $token = bin2hex(random_bytes(16));
        $extension = strtolower(pathinfo((string) ($file['name'] ?? 'import.xlsx'), PATHINFO_EXTENSION)) ?: 'xlsx';
        $path = $this->previewDir() . DIRECTORY_SEPARATOR . $token . '.' . $extension;
        $tmpFile = (string) ($file['tmp_name'] ?? '');

        if (!@move_uploaded_file($tmpFile, $path) && !@copy($tmpFile, $path)) {
            throw new RuntimeException('Unable to store import preview file.');
        }

        $preview = $this->preview($path, (string) ($file['name'] ?? basename($path)));
        $preview['token'] = $token;
        $preview['stored_name'] = basename($path);
        file_put_contents($this->previewDir() . DIRECTORY_SEPARATOR . $token . '.errors.json', json_encode($preview['errors'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return $preview;
    }

    /** @return array<string, mixed> */
    public function preview(string $tmpFile, string $originalName = ''): array
    {
        [$sheet, $highestRow] = $this->loadSheet($tmpFile, $originalName);
        $maximumRows = (int) $this->config->get('uploads.imports.max_rows', 5000);
        if ($highestRow > $maximumRows + 1) {
            throw new RuntimeException('Spreadsheet row limit exceeded.');
        }

        $previewRows = [];
        $errors = [];
        $valid = 0;
        $duplicates = 0;
        $skipped = 0;

        for ($rowNumber = 2; $rowNumber <= $highestRow; $rowNumber++) {
            $row = $sheet->rangeToArray("A{$rowNumber}:AA{$rowNumber}", null, false, true, true)[$rowNumber] ?? [];
            $mosqueName = $this->cell($row, 'B');
            $nationalCode = $this->cell($row, 'E');
            $status = 'valid';
            $message = 'جاهز للاستيراد';

            if ($mosqueName === '' || $nationalCode === '') {
                $status = 'skipped';
                $message = 'اسم المسجد أو الرمز الوطني فارغ';
                $skipped++;
            } elseif ($this->mosques->nationalCodeExists($nationalCode)) {
                $status = 'duplicate';
                $message = 'رمز وطني مكرر داخل قاعدة البيانات';
                $duplicates++;
            } else {
                $adminType = $this->cell($row, 'X') !== '' ? 'pashalik' : ($this->cell($row, 'Z') !== '' ? 'circle' : '');
                $validationData = [
                    'mosque_name' => $mosqueName,
                    'address' => $this->cell($row, 'C'),
                    'construction_date' => $this->normalizeConstructionDate($this->cell($row, 'D')),
                    'national_code' => $nationalCode,
                    'admin_type' => $adminType,
                    'pashalik' => $this->cell($row, 'X'),
                    'circle' => $this->cell($row, 'Z'),
                    'leadership' => $this->cell($row, 'AA'),
                    'community' => $this->cell($row, 'H'),
                ];
                $validationErrors = $this->validator->requiredFields($validationData, $adminType);
                if ($validationErrors !== []) {
                    $status = 'error';
                    $message = implode('، ', $validationErrors);
                    $skipped++;
                } else {
                    $valid++;
                }
            }

            if ($status !== 'valid') {
                $errors[] = [
                    'row' => $rowNumber,
                    'national_code' => $nationalCode,
                    'mosque_name' => $mosqueName,
                    'status' => $status,
                    'message' => $message,
                ];
            }

            if (count($previewRows) < 20) {
                $previewRows[] = [
                    'row' => $rowNumber,
                    'mosque_name' => $mosqueName,
                    'national_code' => $nationalCode,
                    'address' => $this->cell($row, 'C'),
                    'status' => $status,
                    'message' => $message,
                ];
            }
        }

        return [
            'original_name' => $originalName,
            'total_rows' => max(0, $highestRow - 1),
            'valid_rows' => $valid,
            'duplicate_rows' => $duplicates,
            'skipped_rows' => $skipped,
            'preview_rows' => $previewRows,
            'errors' => array_slice($errors, 0, 500),
        ];
    }

    /** @return array{imported: int, skipped: int, duplicates: int, imported_codes: list<string>} */
    public function importCached(string $token): array
    {
        $path = $this->previewPath($token);
        if ($path === null) {
            throw new RuntimeException('Import preview file not found.');
        }

        $result = $this->import($path, basename($path));
        @unlink($path);

        return $result;
    }

    /** @return array{deleted: int, codes: list<string>} */
    public function rollbackLastImport(): array
    {
        $path = $this->lastImportPath();
        if (!is_file($path)) {
            return ['deleted' => 0, 'codes' => []];
        }

        $payload = json_decode((string) file_get_contents($path), true);
        $codes = array_values(array_filter((array) ($payload['national_codes'] ?? [])));
        $deleted = $codes === [] ? 0 : $this->mosques->deleteByNationalCodes($codes);
        @unlink($path);

        return ['deleted' => $deleted, 'codes' => $codes];
    }

    /** @return array<string, mixed>|null */
    public function lastImportSummary(): ?array
    {
        $path = $this->lastImportPath();
        if (!is_file($path)) return null;
        $payload = json_decode((string) file_get_contents($path), true);
        if (!is_array($payload)) return null;

        return $payload;
    }

    public function errorReportCsv(string $token): string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) {
            throw new RuntimeException('Invalid import token.');
        }
        $path = $this->previewDir() . DIRECTORY_SEPARATOR . $token . '.errors.json';
        $errors = is_file($path) ? (array) json_decode((string) file_get_contents($path), true) : [];

        $stream = fopen('php://temp', 'r+');
        fputcsv($stream, ['row', 'national_code', 'mosque_name', 'status', 'message']);
        foreach ($errors as $error) {
            fputcsv($stream, [
                $error['row'] ?? '',
                $error['national_code'] ?? '',
                $error['mosque_name'] ?? '',
                $error['status'] ?? '',
                $error['message'] ?? '',
            ]);
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return (string) $csv;
    }

    /** @return array{0: \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet, 1: int} */
    private function loadSheet(string $tmpFile, string $originalName): array
    {
        $type = IOFactory::identify($tmpFile);
        if (!in_array($type, ['Xlsx', 'Xls'], true)) {
            throw new RuntimeException('Unsupported spreadsheet type.');
        }
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if ($extension !== '' && (($type === 'Xlsx' && $extension !== 'xlsx') || ($type === 'Xls' && $extension !== 'xls'))) {
            throw new RuntimeException('Spreadsheet extension does not match its content.');
        }
        $reader = IOFactory::createReader($type);
        $reader->setReadDataOnly(true);
        $spreadsheet = $reader->load($tmpFile);
        $sheet = $spreadsheet->getActiveSheet();

        return [$sheet, $sheet->getHighestDataRow()];
    }

    private function previewDir(): string
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache' . DIRECTORY_SEPARATOR . 'import-previews';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir;
    }

    private function previewPath(string $token): ?string
    {
        if (!preg_match('/^[a-f0-9]{32}$/', $token)) return null;
        $matches = glob($this->previewDir() . DIRECTORY_SEPARATOR . $token . '.*');
        foreach ($matches ?: [] as $match) {
            if (!str_ends_with($match, '.errors.json')) return $match;
        }

        return null;
    }

    private function lastImportPath(): string
    {
        $dir = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cache';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        return $dir . DIRECTORY_SEPARATOR . 'last-import.json';
    }

    /** @param list<string> $nationalCodes */
    private function recordLastImport(array $nationalCodes, string $originalName): void
    {
        file_put_contents($this->lastImportPath(), json_encode([
            'created_at' => date(DATE_ATOM),
            'original_name' => $originalName,
            'row_count' => count($nationalCodes),
            'national_codes' => array_values($nationalCodes),
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    /** @param array<string, mixed> $row */
    private function cell(array $row, string $column): string
    {
        return trim((string) ($row[$column] ?? ''));
    }

    private function normalizeConstructionDate(string $value): ?string
    {
        if ($value === '') return null;
        $timestamp = strtotime($value);
        if ($timestamp === false) return null;
        $year = (int) date('Y', $timestamp);
        $maximumYear = (int) date('Y') + 1;

        return $year >= 1000 && $year <= $maximumYear ? sprintf('%04d-01-01', $year) : null;
    }

    private function zipSizeIsSafe(string $path): bool
    {
        if (!class_exists(ZipArchive::class)) return false;
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) return false;

        $totalSize = 0;
        $maximumSize = (int) $this->config->get('uploads.imports.max_uncompressed_size', 50 * 1024 * 1024);
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            $totalSize += (int) ($stat['size'] ?? 0);
            if ($totalSize > $maximumSize) {
                $zip->close();
                return false;
            }
        }
        $zip->close();

        return true;
    }

    /** Legacy success message, including the optional counters. */
    public function successMessage(array $result): string
    {
        $message = "تم استيراد {$result['imported']} مسجد بنجاح";
        if ($result['skipped'] > 0) {
            $message .= " (تم تخطي {$result['skipped']} سجلات)";
        }
        if ($result['duplicates'] > 0) {
            $message .= " (تم تجاهل {$result['duplicates']} مسجد مكرر)";
        }

        return $message;
    }
}
