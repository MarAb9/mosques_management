<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
require_once 'includes/mosque_functions.php';
checkAuth();

require 'vendor/autoload.php';
use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_FILES['import_file'])) {
    if (!canImportData()) {
        http_response_code(403);
        die("غير مصرح باستيراد البيانات");
    }
    verify_csrf_token();

    try {
        $file = $_FILES['import_file']['tmp_name'];
        $spreadsheet = IOFactory::load($file);
        $sheetData = $spreadsheet->getActiveSheet()->toArray(null, true, true, true);
        
        // Remove empty rows and header
        $sheetData = array_filter($sheetData, function($row) {
            return !empty($row['B']) && $row['B'] != 'اسم المسجد'; // Check mosque name column instead of registration number
        });
        
        $pdo->beginTransaction();
        $importedCount = 0;
        $skippedCount = 0;
        $duplicateCount = 0;
        
        foreach ($sheetData as $row) {
            if (empty($row['B']) || empty($row['E'])) { // Require mosque name and national code
                $skippedCount++;
                continue;
            }

            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM mosques WHERE national_code = ?");
            $checkStmt->execute([$row['E']]);
            $exists = $checkStmt->fetchColumn();
            
            if ($exists > 0) {
                $duplicateCount++;
                continue;
            }

            $adminType = (!empty($row['X'])) ? 'pashalik' : (!empty($row['Z']) ? 'circle' : '');
            
            $data = [
                'mosque_name' => $row['B'] ?? 'غير محدد',
                'address' => $row['C'] ?? 'غير محدد',
                'construction_date' => !empty($row['D']) ? date('Y', strtotime($row['D'])) : null,
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
                $stmt = $pdo->prepare("INSERT INTO mosques (
                    mosque_name, address, construction_date, 
                    national_code, status, friday_prayer, community, funding_source,
                    imam_name, imam_registration, imam_phone, preacher_name, 
                    preacher_registration, preacher_phone, muezzin_name, 
                    muezzin_registration, muezzin_phone, quran_memorization, 
                    literacy_program, guidance_program, guide_imam, notes, 
                    admin_type, pashalik, administrative_attachment, circle, leadership
                ) VALUES (
                    :mosque_name, :address, :construction_date,
                    :national_code, :status, :friday_prayer, :community, :funding_source,
                    :imam_name, :imam_registration, :imam_phone, :preacher_name,
                    :preacher_registration, :preacher_phone, :muezzin_name,
                    :muezzin_registration, :muezzin_phone, :quran_memorization,
                    :literacy_program, :guidance_program, :guide_imam, :notes,
                    :admin_type, :pashalik, :administrative_attachment, :circle, :leadership
                )");
                
                $stmt->execute($data);
                $importedCount++;
            } catch (PDOException $e) {
                $skippedCount++;
                continue;
            }
        }
        
        $pdo->commit();
        $message = "تم استيراد $importedCount مسجد بنجاح";
        if ($skippedCount > 0) $message .= " (تم تخطي $skippedCount سجلات)";
        if ($duplicateCount > 0) $message .= " (تم تجاهل $duplicateCount مسجد مكرر)";
        $_SESSION['success'] = $message;
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Import error: " . $e->getMessage());
        $_SESSION['error'] = "حدث خطأ أثناء استيراد البيانات. يرجى التحقق من الملف والمحاولة لاحقاً";
    }
    
    header("Location: import_export.php");
    exit();
}

if (isset($_GET['export'])) {
    try {
        $where = [];
        $params = [];
        
        // List of all filterable fields
        $filters = [
            'status',
            'friday_prayer',
            'community',
            'literacy_program',
            'guidance_program',
            'guide_imam',
            'quran_memorization'
        ];
        
        // Build WHERE clause based on provided filters
        foreach ($filters as $filter) {
            if (!empty($_GET[$filter])) {
                // Special handling for guide_imam to match either order of names
                if ($filter == 'guide_imam') {
                    $guideFilter = trim($_GET['guide_imam']);
                    if (is_numeric($guideFilter)) {
                        $where[] = "m.guide_imam_id = ?";
                        $params[] = (int)$guideFilter;
                    } else {
                        // fallback to name string matching for backward compatibility
                        $guideName = preg_replace('/\s*\(\d+\)$/', '', $guideFilter);
                        $normalizedSearch = normalizeArabic($guideName);
                        $where[] = "m.guide_imam_id IN (SELECT id FROM guide_imams WHERE display_name_normalized LIKE ?)";
                        $params[] = "%{$normalizedSearch}%";
                    }
                } else {
                    $where[] = "m.$filter = ?";
                    $params[] = $_GET[$filter];
                }
            }
        }
        
        // Filter for undetermined location
        if (isset($_GET['no_location']) && $_GET['no_location'] == '1') {
            $where[] = "(m.latitude IS NULL OR m.longitude IS NULL OR m.latitude = '' OR m.longitude = '')";
        }

        $orderBy = "m.registration_number";
        if (isset($_GET['group_by_guide']) && $_GET['group_by_guide'] == '1') {
            $where[] = "m.guide_imam_id IS NOT NULL";
            $orderBy = "gi.display_name_normalized, m.registration_number";
        }

        $query = "SELECT m.*, gi.display_name AS guide_imam_display FROM mosques m LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id";
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }
        $query .= " ORDER BY " . $orderBy;
        
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $mosques = $stmt->fetchAll();
        
        if (isset($_GET['format']) && $_GET['format'] == 'word') {
            // Generate Word document
            $phpWord = new \PhpOffice\PhpWord\PhpWord();
            
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
            } else {
                // Group mosques by guide_imam
                $grouped = [];
                foreach ($mosques as $mosque) {
                    $guideName = $mosque['guide_imam_display'] ?: ($mosque['guide_imam'] ?: 'غير محدد');
                    $grouped[$guideName][] = $mosque;
                }
                
                foreach ($grouped as $guideName => $guideMosques) {
                    // Heading for guide imam
                    $section->addText(
                        "الإمام المرشد: $guideName (" . count($guideMosques) . " مساجد)",
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
                            ? "باشوية: " . ($mosque['pashalik'] ?: 'غير محدد') . " / جماعة: " . ($mosque['community'] ?: 'غير محدد') . " / ملحقة: " . ($mosque['administrative_attachment'] ?: 'غير محدد') 
                            : "دائرة: " . ($mosque['circle'] ?: 'غير محدد') . " / قيادة: " . ($mosque['leadership'] ?: 'غير محدد') . " / جماعة: " . ($mosque['community'] ?: 'غير محدد');
                        
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
            }
            
            // Output Word file
            $filename = "مساجد_غير_محددة_الموقع.docx";
            header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
            header('Content-Disposition: attachment;filename="' . $filename . '"');
            header('Cache-Control: max-age=0');
            
            $writer = \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007');
            $writer->save('php://output');
            exit();
        }

        $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        $isNoLocation = (isset($_GET['no_location']) && $_GET['no_location'] == '1');

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
                $sheet->setCellValue('A' . $row, $mosque['mosque_name']);
                $sheet->setCellValue('B' . $row, $mosque['address']);
                $sheet->setCellValue('C' . $row, $mosque['national_code']);
                $sheet->setCellValue('D' . $row, $mosque['pashalik']);
                $sheet->setCellValue('E' . $row, $mosque['administrative_attachment']);
                $sheet->setCellValue('F' . $row, $mosque['circle']);
                $sheet->setCellValue('G' . $row, $mosque['leadership']);
                $sheet->setCellValue('H' . $row, $mosque['guide_imam_display'] ?: $mosque['guide_imam']);
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
                $sheet->setCellValue('A' . $row, $mosque['registration_number']);
                $sheet->setCellValue('B' . $row, $mosque['mosque_name']);
                $sheet->setCellValue('C' . $row, $mosque['address']);
                $sheet->setCellValue('D' . $row, $mosque['construction_date']);
                $sheet->setCellValue('E' . $row, $mosque['national_code']);
                $sheet->setCellValue('F' . $row, $mosque['status']);
                $sheet->setCellValue('G' . $row, $mosque['friday_prayer']);
                $sheet->setCellValue('H' . $row, $mosque['community']);
                $sheet->setCellValue('I' . $row, $mosque['funding_source']);
                $sheet->setCellValue('J' . $row, $mosque['imam_name']);
                $sheet->setCellValue('K' . $row, $mosque['imam_registration']);
                $sheet->setCellValue('L' . $row, $mosque['imam_phone']);
                $sheet->setCellValue('M' . $row, $mosque['preacher_name']);
                $sheet->setCellValue('N' . $row, $mosque['preacher_registration']);
                $sheet->setCellValue('O' . $row, $mosque['preacher_phone']);
                $sheet->setCellValue('P' . $row, $mosque['muezzin_name']);
                $sheet->setCellValue('Q' . $row, $mosque['muezzin_registration']);
                $sheet->setCellValue('R' . $row, $mosque['muezzin_phone']);
                $sheet->setCellValue('S' . $row, $mosque['quran_memorization']);
                $sheet->setCellValue('T' . $row, $mosque['literacy_program']);
                $sheet->setCellValue('U' . $row, $mosque['guidance_program']);
                $sheet->setCellValue('V' . $row, $mosque['guide_imam_display'] ?: $mosque['guide_imam']);
                $sheet->setCellValue('W' . $row, $mosque['notes']);
                $sheet->setCellValue('X' . $row, $mosque['pashalik']);
                $sheet->setCellValue('Y' . $row, $mosque['administrative_attachment']);
                $sheet->setCellValue('Z' . $row, $mosque['circle']);
                $sheet->setCellValue('AA' . $row, $mosque['leadership']);
                
                $row++;
            }
        }

        // Premium Styling Upgrade
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
        
        $filename = "مساجد_إقليم_بركان.xlsx";
        if (isset($_GET['no_location']) && $_GET['no_location'] == '1') {
            $filename = "مساجد_غير_محددة_الموقع.xlsx";
        }
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save('php://output');
        exit();
    } catch (Exception $e) {
        error_log("Export error: " . $e->getMessage());
        $_SESSION['error'] = "حدث خطأ أثناء تصدير البيانات. يرجى المحاولة لاحقاً";
        header("Location: import_export.php");
        exit();
    }
}

require_once 'includes/header.php';
?>

<div class="container py-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <!-- Alerts -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <!-- Main Card -->
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-exchange-alt me-2"></i>نظام استيراد وتصدير بيانات المساجد
                    </h4>
                </div>
                
                <div class="card-body">
                    <!-- Import Section -->
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <div class="mb-5">
                            <h5 class="mb-3 text-dark">
                                <i class="fas fa-file-import text-primary me-2"></i>استيراد البيانات
                            </h5>
                            <form method="POST" action="" enctype="multipart/form-data" class="row g-3">
                                <?= csrf_field() ?>
                                <!-- KEEP YOUR EXISTING IMPORT FORM -->
                                <div class="col-md-8">
                                    <label for="import_file" class="form-label">اختر ملف Excel</label>
                                    <input type="file" class="form-control" id="import_file" name="import_file" accept=".xlsx, .xls" required>
                                </div>
                                <div class="col-md-4 d-flex align-items-end">
                                    <button type="submit" class="btn btn-primary w-100">
                                        <i class="fas fa-upload me-2"></i>استيراد
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php else: ?>
                        <!-- IMPORT RESTRICTION FOR CLIENTS -->
                        <div class="alert alert-info mb-5">
                            <i class="fas fa-info-circle me-2"></i>
                            <strong>الاستيراد متاح للمسؤولين فقط:</strong> يمكنك تصدير البيانات ولكن لا يمكنك استيرادها.
                        </div>
                    <?php endif; ?>
                    
                    <!-- Export Section -->
                    <div>
                        <h5 class="mb-3 text-dark">
                            <i class="fas fa-file-export text-success me-2"></i>تصدير البيانات
                        </h5>
                        
                        <div class="d-grid gap-2">
                            <a href="import_export.php?export=1" class="btn btn-success">
                                <i class="fas fa-file-excel me-2"></i>تصدير جميع البيانات
                            </a>
                            
                            <a href="import_export.php?export=1&no_location=1&group_by_guide=1" class="btn btn-warning">
                                <i class="fas fa-map-marker-alt me-2"></i>تصدير مساجد الإمام المرشد غير محددة الموقع (Excel)
                            </a>
                            
                            <a href="import_export.php?export=1&no_location=1&group_by_guide=1&format=word" class="btn btn-info text-white">
                                <i class="fas fa-file-word me-2"></i>تصدير مساجد الإمام المرشد غير محددة الموقع (Word)
                            </a>
                            
                            <button class="btn btn-outline-success" data-bs-toggle="modal" data-bs-target="#filterModal">
                                <i class="fas fa-filter me-2"></i>تصدير مخصص
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Instructions -->
                <div class="card-footer bg-light">
                    <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#instructionsCollapse">
                        <i class="fas fa-info-circle me-2 text-info"></i>تعليمات الاستيراد والتصدير
                    </button>
                    
                    <div class="collapse mt-2" id="instructionsCollapse">
                        <div class="small">
                            <ul class="mb-0">
                                <li>يجب أن يحتوي ملف الاستيراد على الأعمدة الأساسية (B-E)</li>
                                <li>البيانات في العمود E (الرمز الوطني) يجب أن تكون فريدة</li>
                                <li>يجب أن يكون الصف الأول يحتوي على العناوين</li>
                                <li>يمكنك تصدير البيانات كاملة أو باستخدام عوامل التصفية</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Filter Modal -->
<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title" id="filterModalLabel">
                    <i class="fas fa-filter me-2"></i>تصدير مخصص
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="exportForm" action="import_export.php" method="GET">
                <input type="hidden" name="export" value="1">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="exportStatus" class="form-label">حالة المسجد</label>
                            <select class="form-select" id="exportStatus" name="status">
                                <option value="">الكل</option>
                                <?php
                                $statuses = $pdo->query("SELECT DISTINCT status FROM mosques WHERE status IS NOT NULL ORDER BY status")->fetchAll();
                                foreach ($statuses as $status) {
                                    echo '<option value="' . htmlspecialchars($status['status']) . '">' . htmlspecialchars($status['status']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="exportFriday" class="form-label">صلاة الجمعة</label>
                            <select class="form-select" id="exportFriday" name="friday_prayer">
                                <option value="">الكل</option>
                                <?php
                                $fridayPrayers = $pdo->query("SELECT DISTINCT friday_prayer FROM mosques WHERE friday_prayer IS NOT NULL ORDER BY friday_prayer")->fetchAll();
                                foreach ($fridayPrayers as $prayer) {
                                    echo '<option value="' . htmlspecialchars($prayer['friday_prayer']) . '">' . htmlspecialchars($prayer['friday_prayer']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="exportCommunity" class="form-label">الجماعة</label>
                            <select class="form-select" id="exportCommunity" name="community">
                                <option value="">الكل</option>
                                <?php
                                $communities = $pdo->query("SELECT DISTINCT community FROM mosques WHERE community IS NOT NULL ORDER BY community")->fetchAll();
                                foreach ($communities as $community) {
                                    echo '<option value="' . htmlspecialchars($community['community']) . '">' . htmlspecialchars($community['community']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="exportLiteracy" class="form-label">محو الأمية</label>
                            <select class="form-select" id="exportLiteracy" name="literacy_program">
                                <option value="">الكل</option>
                                <?php
                                $literacyPrograms = $pdo->query("SELECT DISTINCT literacy_program FROM mosques WHERE literacy_program IS NOT NULL ORDER BY literacy_program")->fetchAll();
                                foreach ($literacyPrograms as $program) {
                                    echo '<option value="' . htmlspecialchars($program['literacy_program']) . '">' . htmlspecialchars($program['literacy_program']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="exportGuidance" class="form-label">الوعظ والإرشاد</label>
                            <select class="form-select" id="exportGuidance" name="guidance_program">
                                <option value="">الكل</option>
                                <?php
                                $guidancePrograms = $pdo->query("SELECT DISTINCT guidance_program FROM mosques WHERE guidance_program IS NOT NULL ORDER BY guidance_program")->fetchAll();
                                foreach ($guidancePrograms as $program) {
                                    echo '<option value="' . htmlspecialchars($program['guidance_program']) . '">' . htmlspecialchars($program['guidance_program']) . '</option>';
                                }
                                ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="exportGuideImam" class="form-label">الإمام المرشد</label>
                            <select class="form-select" id="exportGuideImam" name="guide_imam">
                                <option value="">الكل</option>
                                <?php
                                $guideImams = $pdo->query("
                                    SELECT gi.id, gi.display_name, COUNT(m.registration_number) as mosque_count 
                                    FROM guide_imams gi
                                    LEFT JOIN mosques m ON gi.id = m.guide_imam_id
                                    GROUP BY gi.id
                                    ORDER BY gi.display_name_normalized
                                ")->fetchAll(PDO::FETCH_ASSOC);
                                
                                foreach ($guideImams as $imam) {
                                    echo '<option value="' . $imam['id'] . '">' . htmlspecialchars($imam['display_name']) . ' (' . $imam['mosque_count'] . ')</option>';
                                }
                                ?>
                            </select>
                        </div>
                    </div>
                    <div class="row mt-2">
                        <div class="col-md-6 mb-3">
                            <label for="exportFormat" class="form-label">صيغة الملف</label>
                            <select class="form-select" id="exportFormat" name="format">
                                <option value="excel">Excel (.xlsx)</option>
                                <option value="word">Word (.docx)</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3 d-flex align-items-end">
                            <div class="form-check form-switch mb-2">
                                <input class="form-check-input" type="checkbox" id="exportNoLocation" name="no_location" value="1">
                                <label class="form-check-label" for="exportNoLocation">تصدير المساجد غير محددة الموقع فقط</label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">إلغاء</button>
                    <button type="submit" class="btn btn-success">تصدير البيانات</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
