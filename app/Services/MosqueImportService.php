<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\MosqueRepository;
use PDOException;
use PhpOffice\PhpSpreadsheet\IOFactory;

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
    ) {
    }

    /**
     * Import a workbook from an uploaded temporary file.
     *
     * @return array{imported: int, skipped: int, duplicates: int}
     */
    public function import(string $tmpFile): array
    {
        $pdo = $this->db->pdo();

        $spreadsheet = IOFactory::load($tmpFile);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);

        // Remove empty rows and header
        $sheetData = array_filter($sheetData, function ($row) {
            return !empty($row['B']) && $row['B'] != 'اسم المسجد'; // Check mosque name column
        });

        $pdo->beginTransaction();
        $importedCount = 0;
        $skippedCount = 0;
        $duplicateCount = 0;

        try {
            foreach ($sheetData as $row) {
                if (empty($row['B']) || empty($row['E'])) { // Require mosque name and national code
                    $skippedCount++;
                    continue;
                }

                if ($this->mosques->nationalCodeExists((string) $row['E'])) {
                    $duplicateCount++;
                    continue;
                }

                $adminType = (!empty($row['X'])) ? 'pashalik' : (!empty($row['Z']) ? 'circle' : '');

                $data = [
                    'mosque_name' => $row['B'] ?? 'غير محدد',
                    'address' => $row['C'] ?? 'غير محدد',
                    'construction_date' => !empty($row['D']) ? date('Y', strtotime((string) $row['D'])) : null,
                    'national_code' => $row['E'] ?? null,
                    'status' => $row['F'] ?? 'مفتوح',
                    'friday_prayer' => $row['G'] ?? 'لا',
                    'community' => $row['H'] ?? 'غير محدد',
                    'funding_source' => $row['I'] ?? 'غير محدد',
                    'imam_name' => $row['J'] ?? null,
                    'imam_registration' => $row['K'] ?? null,
                    'imam_phone' => $row['L'] ?? null,
                    'preacher_name' => $row['M'] ?? null,
                    'preacher_registration' => $row['N'] ?? null,
                    'preacher_phone' => $row['O'] ?? null,
                    'muezzin_name' => $row['P'] ?? null,
                    'muezzin_registration' => $row['Q'] ?? null,
                    'muezzin_phone' => $row['R'] ?? null,
                    'quran_memorization' => $row['S'] ?? 'لا',
                    'literacy_program' => $row['T'] ?? 'لا',
                    'guidance_program' => $row['U'] ?? 'لا',
                    'guide_imam' => $row['V'] ?? null,
                    'notes' => $row['W'] ?? null,
                    'admin_type' => $adminType,
                    'pashalik' => $row['X'] ?? null,
                    'administrative_attachment' => $row['Y'] ?? null,
                    'circle' => $row['Z'] ?? null,
                    'leadership' => $row['AA'] ?? null,
                ];

                try {
                    $this->mosques->insertFromImport($data);
                    $importedCount++;
                } catch (PDOException $e) {
                    $skippedCount++;
                    continue;
                }
            }

            $pdo->commit();
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        return [
            'imported' => $importedCount,
            'skipped' => $skippedCount,
            'duplicates' => $duplicateCount,
        ];
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
