<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\MosqueRepository;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpWord\PhpWord;

/**
 * Excel/Word export document builders
 * (legacy import_export.php export branch, moved verbatim).
 */
final class MosqueExportService
{
    public function __construct(private readonly MosqueRepository $mosques)
    {
    }

    /**
     * @param array<string, mixed> $query raw GET parameters
     * @return list<array<string, mixed>>
     */
    public function fetchRows(array $query): array
    {
        return $this->mosques->findForExport($query);
    }

    /**
     * Build the Word document listing mosques grouped by guide imam.
     *
     * @param list<array<string, mixed>> $mosques
     */
    public function buildWordDocument(array $mosques): PhpWord
    {
        $phpWord = new PhpWord();

        // Set document properties
        $properties = $phpWord->getDocInfo();
        $properties->setTitle('المساجد غير محددة الموقع');
        $properties->setCreator('نظام إدارة المساجد');

        // Define default styles
        $phpWord->setDefaultFontName('Arial');
        $phpWord->setDefaultFontSize(11);

        // Add a section
        $section = $phpWord->addSection([
            'orientation' => 'landscape',
            'marginLeft' => 1000,
            'marginRight' => 1000,
            'marginTop' => 1000,
            'marginBottom' => 1000,
        ]);

        // Header Title
        $section->addText(
            'قائمة المساجد غير محددة الموقع الجغرافي',
            ['name' => 'Arial', 'size' => 18, 'bold' => true, 'color' => '1B4332', 'rtl' => true],
            ['align' => 'center', 'spaceAfter' => 100, 'rtl' => true]
        );

        // Subtitle
        $section->addText(
            'تاريخ الاستخراج: ' . date('Y-m-d H:i'),
            ['name' => 'Arial', 'size' => 10, 'italic' => true, 'color' => '666666', 'rtl' => true],
            ['align' => 'center', 'spaceAfter' => 400, 'rtl' => true]
        );

        if (empty($mosques)) {
            $section->addText(
                'لا توجد مساجد غير محددة الموقع.',
                ['name' => 'Arial', 'size' => 12, 'bold' => true, 'rtl' => true],
                ['align' => 'center', 'spaceBefore' => 200, 'rtl' => true]
            );

            return $phpWord;
        }

        // Group mosques by guide_imam
        $grouped = [];
        foreach ($mosques as $mosque) {
            $guideName = $mosque['guide_imam_display'] ?: ($mosque['guide_imam'] ?: 'غير محدد');
            $grouped[$guideName][] = $mosque;
        }

        foreach ($grouped as $guideName => $guideMosques) {
            // Heading for guide imam
            $section->addText(
                "الإمام المرشد: $guideName (" . count($guideMosques) . ' مساجد)',
                ['name' => 'Arial', 'size' => 14, 'bold' => true, 'color' => '2D6A4F', 'rtl' => true],
                ['align' => 'right', 'spaceBefore' => 200, 'spaceAfter' => 100, 'rtl' => true]
            );

            // Table
            $table = $section->addTable([
                'borderSize' => 6,
                'borderColor' => 'D3D3D3',
                'cellMargin' => 80,
                'bidiVisual' => true, // RTL layout for columns
                'width' => 100 * 50,
                'unit' => \PhpOffice\PhpWord\SimpleType\TblWidth::PERCENT,
            ]);

            // Table Header
            $table->addRow(400);
            $headerStyles = ['name' => 'Arial', 'size' => 10, 'bold' => true, 'color' => 'FFFFFF', 'rtl' => true];
            $headerCellBg = '1B4332';
            $headerParaStyles = ['align' => 'center', 'rtl' => true];

            $table->addCell(800, ['valign' => 'center', 'bgColor' => $headerCellBg])->addText('رقم', $headerStyles, $headerParaStyles);
            $table->addCell(3000, ['valign' => 'center', 'bgColor' => $headerCellBg])->addText('اسم المسجد', $headerStyles, $headerParaStyles);
            $table->addCell(1500, ['valign' => 'center', 'bgColor' => $headerCellBg])->addText('الرمز الوطني', $headerStyles, $headerParaStyles);
            $table->addCell(2500, ['valign' => 'center', 'bgColor' => $headerCellBg])->addText('العنوان', $headerStyles, $headerParaStyles);
            $table->addCell(4500, ['valign' => 'center', 'bgColor' => $headerCellBg])->addText('التقسيم الإداري', $headerStyles, $headerParaStyles);

            // Table Rows
            $rowIdx = 1;
            foreach ($guideMosques as $mosque) {
                $table->addRow(350);

                $cellBg = ($rowIdx % 2 == 0) ? 'F4F9F4' : 'FFFFFF';

                $textStyles = ['name' => 'Arial', 'size' => 10, 'rtl' => true];
                $paraStyles = ['align' => 'right', 'rtl' => true];
                $centerParaStyles = ['align' => 'center', 'rtl' => true];

                $adminDiv = ($mosque['admin_type'] == 'pashalik')
                    ? 'باشوية: ' . ($mosque['pashalik'] ?: 'غير محدد') . ' / جماعة: ' . ($mosque['community'] ?: 'غير محدد') . ' / ملحقة: ' . ($mosque['administrative_attachment'] ?: 'غير محدد')
                    : 'دائرة: ' . ($mosque['circle'] ?: 'غير محدد') . ' / قيادة: ' . ($mosque['leadership'] ?: 'غير محدد') . ' / جماعة: ' . ($mosque['community'] ?: 'غير محدد');

                $table->addCell(800, ['valign' => 'center', 'bgColor' => $cellBg])->addText($rowIdx, $textStyles, $centerParaStyles);
                $table->addCell(3000, ['valign' => 'center', 'bgColor' => $cellBg])->addText($mosque['mosque_name'], $textStyles, $paraStyles);
                $table->addCell(1500, ['valign' => 'center', 'bgColor' => $cellBg])->addText($mosque['national_code'], $textStyles, $centerParaStyles);
                $table->addCell(2500, ['valign' => 'center', 'bgColor' => $cellBg])->addText($mosque['address'], $textStyles, $paraStyles);
                $table->addCell(4500, ['valign' => 'center', 'bgColor' => $cellBg])->addText($adminDiv, $textStyles, $paraStyles);

                $rowIdx++;
            }

            // Space after table
            $section->addTextBreak(1);
        }

        return $phpWord;
    }

    /**
     * Build the Excel workbook (full export or the no-location subset).
     *
     * @param list<array<string, mixed>> $mosques
     */
    public function buildSpreadsheet(array $mosques, bool $isNoLocation): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        if ($isNoLocation) {
            // Write headers for no location
            $sheet->setCellValue('A1', 'اسم المسجد');
            $sheet->setCellValue('B1', 'العنوان');
            $sheet->setCellValue('C1', 'الرمز الوطني');
            $sheet->setCellValue('D1', 'الباشوية');
            $sheet->setCellValue('E1', 'الملحقة الإدارية');
            $sheet->setCellValue('F1', 'الدائرة');
            $sheet->setCellValue('G1', 'القيادة');
            $sheet->setCellValue('H1', 'الإمام المرشد');

            // Write data
            $row = 2;
            foreach ($mosques as $mosque) {
                $this->writeTextRow($sheet, $row, [
                    $mosque['mosque_name'],
                    $mosque['address'],
                    $mosque['national_code'],
                    $mosque['pashalik'],
                    $mosque['administrative_attachment'],
                    $mosque['circle'],
                    $mosque['leadership'],
                    $mosque['guide_imam_display'] ?: $mosque['guide_imam'],
                ]);
                $row++;
            }
        } else {
            // Write headers
            $sheet->setCellValue('A1', 'ر.ت.ع');
            $sheet->setCellValue('B1', 'اسم المسجد');
            $sheet->setCellValue('C1', 'العنوان');
            $sheet->setCellValue('D1', 'تاريخ البناء');
            $sheet->setCellValue('E1', 'الرمز الوطني');
            $sheet->setCellValue('F1', 'الوضعية');
            $sheet->setCellValue('G1', 'الجمعة');
            $sheet->setCellValue('H1', 'الجماعة');
            $sheet->setCellValue('I1', 'جهة الإنفاق');
            $sheet->setCellValue('J1', 'اسم الإمام');
            $sheet->setCellValue('K1', 'ر.ب.ت.و');
            $sheet->setCellValue('L1', 'رقم الهاتف');
            $sheet->setCellValue('M1', 'اسم الخطيب');
            $sheet->setCellValue('N1', 'ر.ب.ت.و');
            $sheet->setCellValue('O1', 'هاتف الخطيب');
            $sheet->setCellValue('P1', 'المؤذن');
            $sheet->setCellValue('Q1', 'ر ب ت و');
            $sheet->setCellValue('R1', 'رقم الهاتف');
            $sheet->setCellValue('S1', 'تحفيظ القرآن الكريم');
            $sheet->setCellValue('T1', 'محو الأمية');
            $sheet->setCellValue('U1', 'الوعظ والإرشاد');
            $sheet->setCellValue('V1', 'الإمام المرشد');
            $sheet->setCellValue('W1', 'ملاحظات');
            $sheet->setCellValue('X1', 'الباشوية');
            $sheet->setCellValue('Y1', 'الملحقة الإدارية');
            $sheet->setCellValue('Z1', 'الدائرة');
            $sheet->setCellValue('AA1', 'القيادة');

            // Write data
            $row = 2;
            foreach ($mosques as $mosque) {
                $this->writeTextRow($sheet, $row, [
                    $mosque['registration_number'], $mosque['mosque_name'], $mosque['address'],
                    $mosque['construction_date'], $mosque['national_code'], $mosque['status'],
                    $mosque['friday_prayer'], $mosque['community'], $mosque['funding_source'],
                    $mosque['imam_name'], $mosque['imam_registration'], $mosque['imam_phone'],
                    $mosque['preacher_name'], $mosque['preacher_registration'], $mosque['preacher_phone'],
                    $mosque['muezzin_name'], $mosque['muezzin_registration'], $mosque['muezzin_phone'],
                    $mosque['quran_memorization'], $mosque['literacy_program'], $mosque['guidance_program'],
                    $mosque['guide_imam_display'] ?: $mosque['guide_imam'], $mosque['notes'],
                    $mosque['pashalik'], $mosque['administrative_attachment'], $mosque['circle'],
                    $mosque['leadership'],
                ]);

                $row++;
            }
        }

        // Institutional export styling
        $lastRow = $row - 1;
        $highestColumn = $sheet->getHighestColumn();

        $sheet->setRightToLeft(true);
        $sheet->freezePane('A2');

        // Font family & Size
        $sheet->getStyle('A1:' . $highestColumn . $lastRow)->getFont()->setName('Segoe UI')->setSize(10);

        // Header Row Styling
        $headerRange = 'A1:' . $highestColumn . '1';
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('FFFFFF');
        $sheet->getStyle($headerRange)->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setRGB('1B4332'); // Deep forest green

        $sheet->getStyle($headerRange)->getAlignment()
            ->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER)
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // Data Rows Styling
        for ($r = 2; $r <= $lastRow; $r++) {
            $sheet->getRowDimension($r)->setRowHeight(22);
            // Zebra striping
            if ($r % 2 == 0) {
                $sheet->getStyle('A' . $r . ':' . $highestColumn . $r)->getFill()
                    ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                    ->getStartColor()->setRGB('F4F9F4'); // Soft green tint
            }
        }

        // Alignments
        $sheet->getStyle('A2:' . $highestColumn . $lastRow)->getAlignment()
            ->setVertical(\PhpOffice\PhpSpreadsheet\Style\Alignment::VERTICAL_CENTER);

        // Center short fields (national code, reg number, status, etc.)
        if ($isNoLocation) {
            $sheet->getStyle('C2:G' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
        } else {
            $sheet->getStyle('A2:A' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER); // registration number
            $sheet->getStyle('E2:I' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER); // national_code, status, friday, community, funding
            $sheet->getStyle('K2:L' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER); // phone numbers
            $sheet->getStyle('N2:O' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('Q2:R' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('S2:U' . $lastRow)->getAlignment()->setHorizontal(\PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_CENTER); // programs
        }

        // Apply Borders
        $borderStyle = [
            'borders' => [
                'allBorders' => [
                    'borderStyle' => \PhpOffice\PhpSpreadsheet\Style\Border::BORDER_THIN,
                    'color' => ['rgb' => 'D0D0D0'],
                ],
            ],
        ];
        $sheet->getStyle('A1:' . $highestColumn . $lastRow)->applyFromArray($borderStyle);

        // Autofit Columns
        $highestColumnIndex = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highestColumn);
        for ($col = 1; $col <= $highestColumnIndex; $col++) {
            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
            $sheet->getColumnDimension($colLetter)->setAutoSize(true);
        }

        return $spreadsheet;
    }

    /** @param list<mixed> $values */
    private function writeTextRow(Worksheet $sheet, int $row, array $values): void
    {
        foreach ($values as $index => $value) {
            $text = (string) ($value ?? '');
            if (preg_match('/^[=+\-@]/u', $text) === 1) {
                $text = "'" . $text;
            }

            $cell = Coordinate::stringFromColumnIndex($index + 1) . $row;
            $sheet->setCellValueExplicit($cell, $text, DataType::TYPE_STRING);
        }
    }

    /** Stream callback for the Excel writer. */
    public function spreadsheetWriter(Spreadsheet $spreadsheet): callable
    {
        return function () use ($spreadsheet): void {
            $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
            $writer->save('php://output');
        };
    }

    /** Stream callback for the Word writer. */
    public function wordWriter(PhpWord $phpWord): callable
    {
        return function () use ($phpWord): void {
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
        };
    }
}
