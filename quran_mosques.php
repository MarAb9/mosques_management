<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';

checkAuth();
require_once 'includes/header.php';

function buildQueryString($newParams = []) {
    $params = $_GET;
    foreach ($newParams as $key => $value) {
        $params[$key] = $value;
    }
    
    // Remove empty parameters
    $params = array_filter($params, function($value) {
        return $value !== '' && $value !== null;
    });
    
    return http_build_query($params);
}

// Database query setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Sorting parameters
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['id', 'mosque_name', 'responsible_name']) 
    ? $_GET['sort'] 
    : 'id';

$order = isset($_GET['order']) && strtolower($_GET['order']) == 'asc' 
    ? 'ASC' 
    : 'DESC';

// Base query without LIMIT for counting
$baseSql = "FROM quran_memorization_programs q
            JOIN mosques m ON q.mosque_registration_number = m.national_code 
            LEFT JOIN quran_program_responsibles r ON q.id = r.program_id
            WHERE 1=1";

$sql = "SELECT q.*, m.mosque_name, m.community, m.national_code,
               COALESCE(SUM(r.male_students), 0) as total_male_students,
               COALESCE(SUM(r.female_students), 0) as total_female_students,
               COALESCE(SUM(r.weekly_sessions), 0) as total_weekly_sessions,
               COUNT(r.id) as responsible_count
        $baseSql";

$countSql = "SELECT COUNT(DISTINCT q.id) $baseSql";

$params = [];
$paramTypes = [];

if (isset($_GET['query']) && !empty($_GET['query'])) {
    $searchTerm = "%{$_GET['query']}%";
    $sql .= " AND (m.mosque_name LIKE ? OR m.national_code LIKE ?)";
    $countSql .= " AND (m.mosque_name LIKE ? OR m.national_code LIKE ?)";
    $params = array_merge($params, [$searchTerm, $searchTerm]);
    $paramTypes = array_merge($paramTypes, [PDO::PARAM_STR, PDO::PARAM_STR]);
}

if (isset($_GET['national_code']) && !empty($_GET['national_code'])) {
    $sql .= " AND m.national_code LIKE ?";
    $countSql .= " AND m.national_code LIKE ?";
    $params[] = "%{$_GET['national_code']}%";
    $paramTypes[] = PDO::PARAM_STR;
}

if (isset($_GET['community']) && !empty($_GET['community'])) {
    $sql .= " AND m.community = ?";
    $countSql .= " AND m.community = ?";
    $params[] = $_GET['community'];
    $paramTypes[] = PDO::PARAM_STR;
}

if (isset($_GET['has_quran_school']) && !empty($_GET['has_quran_school'])) {
    // Change to checking in responsibles table
    $sql .= " AND q.id IN (
        SELECT program_id FROM quran_program_responsibles 
        WHERE has_work_program = ?
    )";
    $countSql .= " AND q.id IN (
        SELECT program_id FROM quran_program_responsibles 
        WHERE has_work_program = ?
    )";
    $params[] = $_GET['has_quran_school'];
    $paramTypes[] = PDO::PARAM_STR;
}

// Add GROUP BY to the main SQL query
$sql .= " GROUP BY q.id";

// Add sorting to the main SQL query
$allowedSortColumns = ['id', 'mosque_name', 'national_code', 'community', 'total_weekly_sessions', 'total_male_students', 'total_female_students'];
if (in_array($sort, $allowedSortColumns)) {
    $sql .= " ORDER BY $sort $order";
} else {
    $sql .= " ORDER BY q.id $order"; // Default sorting
}

// Get total count first
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $value) {
    $countStmt->bindValue($key + 1, $value, $paramTypes[$key]);
}
$countStmt->execute();
$total = $countStmt->fetchColumn();
$pages = ceil($total / $limit);

// add pagination to the main query
$sql .= " LIMIT ?, ?";
$params[] = $start;
$params[] = $limit;
$paramTypes[] = PDO::PARAM_INT;
$paramTypes[] = PDO::PARAM_INT;

// Execute main query
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key + 1, $value, $paramTypes[$key]);
}
$stmt->execute();

// Helper function to generate sortable table header
function sortableHeader($title, $sortKey) {
    $currentSort = $_GET['sort'] ?? '';
    $currentOrder = $_GET['order'] ?? '';
    $newOrder = ($currentSort == $sortKey && $currentOrder == 'asc') ? 'desc' : 'asc';
    $iconDirection = ($currentSort == $sortKey && $currentOrder == 'asc') ? 'up' : 'down';
    
    return '
        <a href="quran_mosques.php?'.buildQueryString(['sort' => $sortKey, 'order' => $newOrder]).'" class="text-decoration-none">
            <i class="fas fa-chevron-'.$iconDirection.' ms-1"></i>
        </a>'.$title;
}
 
// Function to get responsible persons for a program
function getProgramStats($programId, $pdo) {
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_responsibles,
            SUM(weekly_sessions) as total_sessions,
            SUM(female_students) as total_females,
            SUM(male_students) as total_males,
            SUM(female_students + male_students) as total_students
        FROM quran_program_responsibles 
        WHERE program_id = ?
    ");
    $stmt->execute([$programId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
?>


<!-- Dashboard Overview Cards -->
<div class="row mb-4 g-4 animate__animated animate__fadeIn">
    <div class="col-md-3">
        <div class="card bg-primary-gradient text-white shadow-lg border-0 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-book-quran fa-3x opacity-75"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="card-title mb-1">إجمالي مساجد التحفيظ</h6>
                    <h2 class="mb-0"><?= number_format($total) ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success-gradient text-white shadow-lg border-0 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-school fa-3x opacity-75"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="card-title mb-1">مساجد بها كتاب قرآني</h6>
                    <h2 class="mb-0">
                        <?php 
                    $schoolCount = $pdo->query("
                        SELECT COUNT(DISTINCT q.id)
                        FROM quran_memorization_programs q
                        JOIN quran_program_responsibles r ON q.id = r.program_id
                        WHERE r.has_work_program != 'لا'
                    ")->fetchColumn();
                    echo number_format($schoolCount);
                    ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-info-gradient text-white shadow-lg border-0 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-home fa-3x opacity-75"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="card-title mb-1">مساجد بها إقامة</h6>
                    <h2 class="mb-0">
                        <?php 
                        $accomCount = $pdo->query("
                            SELECT COUNT(DISTINCT q.id) 
                            FROM quran_memorization_programs q
                            WHERE q.has_accommodation = 'نعم'
                        ")->fetchColumn();
                        echo number_format($accomCount);
                        ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
    <div class="card bg-primary-gradient text-white shadow-lg border-0 h-100">
        <div class="card-body d-flex align-items-center">
            <div class="flex-shrink-0">
                <i class="fas fa-book-quran fa-3x opacity-75"></i>
            </div>
            <div class="flex-grow-1 ms-3">
                <h6 class="card-title mb-1">مراكز التحفيظ</h6>
                <h2 class="mb-0">
                    <?php 
                    $centerCount = $pdo->query("
                        SELECT COUNT(*) 
                        FROM quran_memorization_programs 
                        WHERE has_quran_school = 'مركز تحفيظ'
                    ")->fetchColumn();
                    echo number_format($centerCount);
                    ?>
                </h2>
            </div>
        </div>
    </div>
</div>
    
    <div class="col-md-3">
        <div class="card bg-warning-gradient text-white shadow-lg border-0 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-users fa-3x opacity-75"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="card-title mb-1">إجمالي الطلاب</h6>
                    <h2 class="mb-0">
                        <?php 
                        $studentsCount = $pdo->query("
                            SELECT COALESCE(SUM(r.male_students) + SUM(r.female_students), 0) 
                            FROM quran_program_responsibles r
                        ")->fetchColumn();
                        echo number_format($studentsCount);
                        ?>
                    </h2>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row animate__animated animate__fadeIn">
    <div class="col-md-12">
        <div class="card border-0 shadow-sm glass-effect">
            <div class="card-header bg-transparent border-bottom d-flex justify-content-between align-items-center py-3">
                <div>
                    <h5 class="card-title mb-0 text-primary fw-bold">
                        <i class="fas fa-book-quran me-2"></i>قائمة مساجد التحفيظ
                    </h5>
                    <small class="text-muted">إدارة كافة مساجد التحفيظ المسجلة في النظام</small>
                </div>
                <?php if ($_SESSION['role'] == 'admin') : ?>
                <div class="d-flex gap-2">
                    <a href="add_quran_mosque.php" class="btn btn-primary rounded-pill animate__animated animate__pulse animate__infinite">
                        <i class="fas fa-plus me-2"></i>إضافة مسجد تحفيظ
                    </a>
                </div>
                <?php endif ?>
            </div>
            <div class="card-body">
                <!-- Advanced Search Panel -->
                <div class="mb-4 animate__animated animate__fadeInUp">
                    <div class="card shadow-sm border-0">
                        <div class="card-header bg-transparent border-bottom py-3">
                            <button class="btn btn-link text-decoration-none" type="button" data-bs-toggle="collapse" data-bs-target="#searchCollapse" aria-expanded="true" aria-controls="searchCollapse">
                                <i class="fas fa-search me-2"></i>البحث المتقدم
                                <i class="fas fa-chevron-down ms-2 transition-all"></i>
                            </button>
                        </div>
                        <div class="collapse show" id="searchCollapse">
                            <div class="card-body">
                                <form method="get" action="quran_mosques.php" id="searchForm">
                                    <input type="hidden" name="page" value="1">
                                    <div class="row g-3">
                                        <div class="col-md-5">
                                            <div class="input-group has-validation glass-search">
                                                <span class="input-group-text bg-white border-end-0">
                                                    <i class="fas fa-search text-muted"></i>
                                                </span>
                                                <input type="text" name="query" id="liveSearch" class="form-control border-start-0 py-2" 
                                                    placeholder="ابحث بأي معلومة (اسم المسجد، الرمز الوطني...)"
                                                    aria-label="بحث"
                                                    value="<?= htmlspecialchars($_GET['query'] ?? '') ?>">
                                                <button id="clearSearch" class="btn btn-outline-secondary border-start-0 <?= empty($_GET['query'] ?? '') ? 'd-none' : '' ?>" type="button" style="border-left: none !important;">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button id="searchButton" class="btn btn-primary px-3" type="submit">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted mt-1 d-block">اضغط Enter أو أيقونة البحث للبحث</small>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="community" class="form-select select2" onchange="this.form.submit()">
                                                <option value="">الجماعات</option>
                                                <?php
                                                $communities = $pdo->query("SELECT DISTINCT community FROM mosques WHERE community IS NOT NULL AND community != '' ORDER BY community")->fetchAll(PDO::FETCH_COLUMN);
                                                foreach ($communities as $community) {
                                                    $selected = isset($_GET['community']) && $_GET['community'] == $community ? 'selected' : '';
                                                    echo "<option value=\"" . htmlspecialchars($community) . "\" $selected>" . htmlspecialchars($community) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="has_quran_school" class="form-select select2" onchange="this.form.submit()">
                                                <option value="">كتاب قرآني</option>
                                                <option value="نعم" <?= isset($_GET['has_quran_school']) && $_GET['has_quran_school'] == 'نعم' ? 'selected' : '' ?>>نعم</option>
                                                <option value="لا" <?= isset($_GET['has_quran_school']) && $_GET['has_quran_school'] == 'لا' ? 'selected' : '' ?>>لا</option>
                                                <option value="مركز تحفيظ" <?= isset($_GET['has_quran_school']) && $_GET['has_quran_school'] == 'مركز تحفيظ' ? 'selected' : '' ?>>مركز تحفيظ</option>
                                            </select>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bulk Actions -->
                <div class="d-flex justify-content-between mb-3">
                    <div class="d-flex gap-2">
                        <?php if (canDeleteMosque()) {?>
                        <button id="deleteSelected" class="btn btn-danger rounded-pill animate__animated animate__pulse" disabled>
                            <i class="fas fa-trash-alt me-2"></i>حذف المحدد
                        </button>
                        <?php } ?>
                    </div>
                    <div class="text-muted">
                        <button id="selectedCountBtn" class="btn btn-success rounded-pill animate__animated animate__pulse" disabled>
                            <span id="selectedCount">0</span> مسجد(اً) محدد
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive animate__animated animate__fadeIn">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th width="80"><?= sortableHeader('ر.ت.ع', 'id') ?></th>
                                <th><?= sortableHeader('اسم المسجد', 'mosque_name') ?></th>
                                <th width="130"><?= sortableHeader('الرمز الوطني', 'national_code') ?></th>
                                <th>المسؤول(ة)</th>
                                <th>كتاب قرآني</th>
                                <th>إقامة</th>
                                <th>عدد الجلسات الأسبوعية</th>
                                <th>عدد الطلاب</th>
                                <th>الجماعة</th>
                                <th width="120"><?php echo ($_SESSION['role'] == 'admin') ? 'الإجراءات' : 'معاينة'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $animationDelay = 0;
                            if ($stmt->rowCount() > 0) {
                                while ($row = $stmt->fetch()) {
                                    $animationDelay += 0.05;
                                    echo renderQuranMosqueRow($row, $animationDelay, $pdo);
                                }
                            } else {
                                echo '<tr class="animate__animated animate__fadeInUp">
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        <i class="fas fa-search me-2"></i>'.(isset($_GET['query']) ? 'لا توجد نتائج مطابقة لبحثك' : 'لا توجد مساجد تحفيظ مسجلة').'
                                    </td>
                                </tr>';
                            }
                            
                            function renderQuranMosqueRow($row, $animationDelay, $pdo) {
                                    $accommodationIcon = $row['has_accommodation'] == 'نعم' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
                                    
                                    // Use the aggregated statistics from the query
                                    $totalStudents = ($row['total_male_students'] ?? 0) + ($row['total_female_students'] ?? 0);
                                    $totalSessions = $row['total_weekly_sessions'] ?? 0;
                                    
                                    // Determine Quran school status based on responsibles
                                    $quranSchoolStatus = 'لا';
                                    $badgeClass = 'bg-secondary';
                                    $tooltipText = 'لا يوجد كتاب قرآني';
                                    
                                    // Get the most common has_work_program status from responsibles
                                    $statusStmt = $pdo->prepare("
                                        SELECT has_work_program, COUNT(*) as count 
                                        FROM quran_program_responsibles 
                                        WHERE program_id = ? 
                                        GROUP BY has_work_program 
                                        ORDER BY count DESC 
                                        LIMIT 1
                                    ");
                                    $statusStmt->execute([$row['id']]);
                                    $statusResult = $statusStmt->fetch(PDO::FETCH_ASSOC);
                                    
                                    if ($statusResult) {
                                        $quranSchoolStatus = $statusResult['has_work_program'];
                                        if ($quranSchoolStatus === 'نعم') {
                                            $badgeClass = 'bg-success';
                                            $tooltipText = 'يوجد كتاب قرآني';
                                        } elseif ($quranSchoolStatus === 'مركز تحفيظ') {
                                            $badgeClass = 'bg-primary';
                                            $tooltipText = 'مركز تحفيظ';
                                        }
                                    }

                                    // Get responsible names
                                    $responsibleStmt = $pdo->prepare("SELECT responsible_name FROM quran_program_responsibles WHERE program_id = ? LIMIT 3");
                                    $responsibleStmt->execute([$row['id']]);
                                    $responsibleNames = $responsibleStmt->fetchAll(PDO::FETCH_COLUMN);
                                    $responsibleText = implode(', ', $responsibleNames);
                                    
                                    if (($row['responsible_count'] ?? 0) > 3) {
                                        $responsibleText .= ' +' . ($row['responsible_count'] - 3) . ' أكثر';
                                    }
                                    
                                    // Check if user is admin for edit/delete buttons
                                        $isAdmin = ($_SESSION['role'] == 'admin');
                                        
                                        $QuranactionButtons = '';
                                        if ($isAdmin) {
                                            $deleteToken = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
                                            $QuranactionButtons = '
                                            <a href="edit_quran_mosque.php?id='.$row['id'].'" 
                                                class="btn btn-sm btn-icon btn-primary rounded-circle"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top" 
                                                title="تعديل">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                            <form method="POST" action="delete_quran_mosque.php" class="d-inline" onsubmit="return confirm(\'هل أنت متأكد من حذف هذا المسجد؟\')">
                                                <input type="hidden" name="csrf_token" value="'.$deleteToken.'">
                                                <input type="hidden" name="id" value="'.$row['id'].'">
                                                <button type="submit"
                                                    class="btn btn-sm btn-icon btn-danger rounded-circle"
                                                    data-bs-toggle="tooltip" 
                                                    data-bs-placement="top" 
                                                    title="حذف">
                                                        <i class="fas fa-trash-alt"></i>
                                                </button>
                                            </form>';
                                        }
                                    return '
                                    <tr class="animate__animated animate__fadeInUp" style="animation-delay: '.$animationDelay.'s">
                                        <td>
                                            <input type="checkbox" name="selected_mosques[]" value="'.$row['id'].'" class="form-check-input mosque-checkbox">
                                        </td>
                                        <td class="fw-bold text-muted">'.$row['id'].'</td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-mosque text-primary me-2"></i>
                                                <span>'.$row['mosque_name'].'</span>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-dark">'.$row['national_code'].'</span></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-tie text-info me-2"></i>
                                                <span>'.$responsibleText.'</span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge '.$badgeClass.'" data-bs-toggle="tooltip" title="'.$tooltipText.'">
                                                '.$quranSchoolStatus.'
                                            </span>
                                        </td>
                                        <td>
                                            <span class="d-flex justify-content-center" data-bs-toggle="tooltip" title="'.($row['has_accommodation'] == 'نعم' ? 'يوجد إقامة' : 'لا يوجد إقامة').'">
                                                <i class="fas '.$accommodationIcon.' fa-lg"></i>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary">'.$totalSessions.' جلسات</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success">'.$totalStudents.($totalStudents >= 2 && $totalStudents <= 10 ? ' طلاب' : ' طالب').'</span>
                                        </td>
                                        <td><span class="badge bg-info">'.$row['community'].'</span></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="#" 
                                                class="btn btn-sm btn-icon btn-info rounded-circle view-quran-mosque-btn"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#quranMosqueDetailsModal"
                                                data-mosque-id="'.$row['id'].'"
                                                data-bs-tooltip="tooltip" 
                                                data-bs-placement="top" 
                                                title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                               '.$QuranactionButtons.'
                                            </div>
                                        </td>
                                    </tr>';
}
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                    <?php
                    if ($pages > 1) {
                        echo '<div class="mt-4 animate__animated animate__fadeIn">';
                        echo renderPagination($page, $pages, $_GET);
                        echo '</div>';
                    }

                    function renderPagination($currentPage, $totalPages, $queryParams) {
                        // Remove page parameter from query params
                        $filteredParams = array_filter($queryParams, function($key) {
                            return $key !== 'page';
                        }, ARRAY_FILTER_USE_KEY);
                        
                        $queryString = '';
                        if (!empty($filteredParams)) {
                            $queryString = '&' . http_build_query($filteredParams);
                        }
                        
                        $html = '<nav aria-label="Page navigation" class="mt-4 animate__animated animate__fadeIn">
                            <ul class="pagination justify-content-center" style="z-index: 1; position: relative;">';
                        
                        // Previous button
                        if ($currentPage > 1) {
                            $html .= '<li class="page-item">
                                <a class="page-link" href="quran_mosques.php?page='.($currentPage-1).$queryString.'" aria-label="Previous" style="text-decoration: none;">
                                    <span aria-hidden="true">&laquo;</span>
                                </a>
                            </li>';
                        } else {
                            $html .= '<li class="page-item disabled">
                                <span class="page-link" aria-hidden="true">&laquo;</span>
                            </li>';
                        }
                        
                        // Page numbers
                        $startPage = max(1, $currentPage - 2);
                        $endPage = min($totalPages, $currentPage + 2);
                        
                        if ($startPage > 1) {
                            $html .= '<li class="page-item"><a class="page-link" href="quran_mosques.php?page=1'.$queryString.'" style="text-decoration: none;">1</a></li>';
                            if ($startPage > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        }
                        
                        for ($i = $startPage; $i <= $endPage; $i++) {
                            $activeClass = $currentPage == $i ? 'active' : '';
                            $html .= '<li class="page-item '.$activeClass.'">
                                <a class="page-link" href="quran_mosques.php?page='.$i.$queryString.'" style="text-decoration: none;">'.$i.'</a>
                            </li>';
                        }
                        
                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            $html .= '<li class="page-item"><a class="page-link" href="quran_mosques.php?page='.$totalPages.$queryString.'" style="text-decoration: none;">'.$totalPages.'</a></li>';
                        }
                        
                        // Next button
                        if ($currentPage < $totalPages) {
                            $html .= '<li class="page-item">
                                <a class="page-link" href="quran_mosques.php?page='.($currentPage+1).$queryString.'" aria-label="Next" style="text-decoration: none;">
                                    <span aria-hidden="true">&raquo;</span>
                                </a>
                            </li>';
                        } else {
                            $html .= '<li class="page-item disabled">
                                <span class="page-link" aria-hidden="true">&raquo;</span>
                            </li>';
                        }
                        
                        $html .= '</ul></nav>';
                        return $html;
                    }
                    ?>
                
            </div>
        </div>
    </div>
</div>

<!-- Quran Mosque Details Modal -->
<div class="modal fade" id="quranMosqueDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-book-quran me-2"></i>تفاصيل مسجد التحفيظ</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body" id="modal-body-container">
                <div id="modal-body-content">
                    <!-- Content will be loaded dynamically -->
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>إغلاق
                </button>
                <button type="button" class="btn btn-primary rounded-pill" onclick="printQuranMosqueDetails()">
                    <i class="fas fa-print me-2"></i>طباعة التفاصيل
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Quran Mosque Details Modal
function setupQuranMosqueDetailsModal() {
    const modalElement = document.getElementById('quranMosqueDetailsModal');
    const modal = new bootstrap.Modal(modalElement, {
        backdrop: 'static',
        keyboard: false
    });
    
    // Clear content when modal is hidden
    modalElement.addEventListener('hidden.bs.modal', function() {
        document.getElementById('modal-body-content').innerHTML = '';
    });
    
    document.querySelectorAll('.view-quran-mosque-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const mosqueId = this.getAttribute('data-mosque-id');
            loadQuranMosqueDetails(mosqueId);
        });
    });
}

function loadQuranMosqueDetails(mosqueId) {
    const modal = bootstrap.Modal.getInstance(document.getElementById('quranMosqueDetailsModal'));
    const modalBody = document.getElementById('modal-body-content');
    
    modalBody.innerHTML = `
        <div class="text-center py-5 animate__animated animate__fadeIn">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-2">جاري تحميل بيانات مسجد التحفيظ</p>
        </div>`;
    
    fetch(`ajax/get_quran_mosque_details.php?id=${mosqueId}`)
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            modalBody.innerHTML = formatQuranMosqueDetails(data.data);
        } else {
            showModalError(data.message || 'حدث خطأ أثناء جلب بيانات المسجد');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showModalError('حدث خطأ في الاتصال بالخادم. الرجاء التحقق من اتصال الشبكة والمحاولة مرة أخرى.');
    });
    
    modal.show();
}

function formatQuranMosqueDetails(mosque) {
    let responsiblesHtml = '';
    let totalStudentsAll = 0;
    let totalSessionsAll = 0;
    
    if (mosque.responsibles && mosque.responsibles.length > 0) {
        mosque.responsibles.forEach((responsible, index) => {
            const totalStudents = (responsible.male_students || 0) + (responsible.female_students || 0);
            totalStudentsAll += totalStudents;
            totalSessionsAll += parseInt(responsible.weekly_sessions || 0);
            
            responsiblesHtml += `
            <div class="card mb-3 animate__animated animate__fadeInUp" style="animation-delay: ${index * 0.1}s">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-user-tie me-2"></i>${responsible.responsible_name}</h6>
                    ${responsible.responsible_position ? `<span class="badge bg-info">${responsible.responsible_position}</span>` : ''}
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>برنامج العمل:</strong> ${responsible.has_work_program || 'لا'}<br>
                            <strong>جدول الحفظ:</strong> ${responsible.memorization_schedule || 'غير محدد'}<br>
                            <strong>الجلسات الأسبوعية:</strong> ${responsible.weekly_sessions || '0'} جلسة<br>
                            <strong>مدة الجلسة:</strong> ${responsible.session_hours || '0'} ساعة
                        </div>
                        <div class="col-md-6">
                            <strong>الطلاب الذكور:</strong> ${responsible.male_students || '0'}<br>
                            <strong>الطالبات الإناث:</strong> ${responsible.female_students || '0'}<br>
                            <strong>إجمالي الطلاب:</strong> <span class="badge bg-success">${totalStudents}</span><br>
                            <strong>انتظام الحضور:</strong> ${responsible.regular_attendance || 'لا'}
                        </div>
                    </div>
                    ${responsible.challenges ? `<div class="mt-2"><strong>التحديات:</strong> ${responsible.challenges}</div>` : ''}
                    ${responsible.notes_suggestions ? `<div class="mt-2"><strong>ملاحظات:</strong> ${responsible.notes_suggestions}</div>` : ''}
                </div>
            </div>`;
        });
    } else {
        responsiblesHtml = '<p class="text-muted">لا توجد معلومات عن المسؤولين</p>';
    }
    
    return `
    <div class="row animate__animated animate__fadeIn">
        <div class="col-md-6">
            <div class="card mb-4 animate__animated animate__fadeInLeft">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>المعلومات الأساسية</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">اسم المسجد:</dt>
                        <dd class="col-sm-8">${mosque.mosque_name || 'غير محدد'}</dd>
                        
                        <dt class="col-sm-4">الرمز الوطني:</dt>
                        <dd class="col-sm-8">${mosque.national_code || 'غير محدد'}</dd>
                        
                        <dt class="col-sm-4">الجماعة:</dt>
                        <dd class="col-sm-8">${mosque.community || 'غير محدد'}</dd>
                    </dl>
                </div>
            </div>
            
            <div class="card mb-4 animate__animated animate__fadeInLeft animate__delay-1s">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>المسؤولون (${mosque.responsibles ? mosque.responsibles.length : 0})</h6>
                </div>
                <div class="card-body">
                    ${responsiblesHtml}
                </div>
            </div>
        </div>
        
        <div class="col-md-6">
            <div class="card mb-4 animate__animated animate__fadeInRight">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>الإحصائيات</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-md-6">
                            <div class="card bg-primary-gradient text-white mb-3">
                                <div class="card-body">
                                    <h4 class="mb-0">${totalStudentsAll}</h4>
                                    <small>إجمالي الطلاب</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-success-gradient text-white mb-3">
                                <div class="card-body">
                                    <h4 class="mb-0">${totalSessionsAll}</h4>
                                    <small>إجمالي الجلسات الأسبوعية</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-home me-2"></i>مرافق الإقامة</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>الإقامة:</strong> ${mosque.has_accommodation || 'لا'}</p>
                            ${mosque.accommodation_capacity ? `<p><strong>سعة الإقامة:</strong> ${mosque.accommodation_capacity} شخص</p>` : ''}
                            ${mosque.accommodation_condition ? `<p><strong>حالة الإقامة:</strong> ${mosque.accommodation_condition}</p>` : ''}
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>ملاحظات إضافية</h6>
                        </div>
                        <div class="card-body">
                            ${mosque.additional_notes ? `<p>${mosque.additional_notes}</p>` : '<p class="text-muted">لا توجد ملاحظات إضافية</p>'}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

function showModalError(message) {
    document.getElementById('modal-body-content').innerHTML = `
        <div class="alert alert-danger animate__animated animate__shakeX" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${message}
        </div>`;
}

function printQuranMosqueDetails() {
    const modalContent = document.getElementById('modal-body-content').innerHTML;
    const printWindow = window.open('', '_blank');
    printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>تفاصيل مسجد التحفيظ - طباعة</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
            <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
                .card { border: 1px solid #dee2e6; margin-bottom: 1rem; }
                .badge { font-size: 0.85em; }
                @media print {
                    .btn { display: none; }
                    .card-header { background-color: #f8f9fa !important; }
                }
            </style>
        </head>
        <body>
            <div class="container-fluid py-4">
                <div class="row mb-4">
                    <div class="col-12 text-center">
                        <h2 class="text-primary">تفاصيل مسجد التحفيظ</h2>
                        <p class="text-muted">${new Date().toLocaleString('ar-EG')}</p>
                    </div>
                </div>
                ${modalContent}
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    setupQuranMosqueDetailsModal();
    
    // Checkbox selection logic
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.mosque-checkbox');
    const deleteBtn = document.getElementById('deleteSelected');
    const selectedCountBtn = document.getElementById('selectedCountBtn');
    const selectedCountSpan = document.getElementById('selectedCount');
    
    // Only initialize checkbox functionality if elements exist
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectionUI();
        });
    }
    
    if (checkboxes.length > 0) {
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectionUI);
        });
    }
    
    function updateSelectionUI() {
        const selectedCount = document.querySelectorAll('.mosque-checkbox:checked').length;
        if (selectedCountSpan) {
            selectedCountSpan.textContent = selectedCount;
        }
        
        if (deleteBtn) {
            deleteBtn.disabled = selectedCount === 0;
        }
        
        if (selectedCountBtn) {
            selectedCountBtn.disabled = selectedCount === 0;
        }
    }
    
    // Delete selected functionality - only if delete button exists
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('.mosque-checkbox:checked'))
                .map(checkbox => checkbox.value);
            
            if (selectedIds.length === 0) return;
            
            if (confirm(`هل أنت متأكد من حذف ${selectedIds.length} مسجد(اً) محدد(ة)؟`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_quran_mosque.php';
                
                // Add CSRF token
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>';
                form.appendChild(csrfInput);
                
                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_mosques[]';
                    input.value = id;
                    form.appendChild(input);
                });
                
                document.body.appendChild(form);
                form.submit();
            }
        });
    }
    
    // Live search functionality
    const liveSearch = document.getElementById('liveSearch');
    const clearSearch = document.getElementById('clearSearch');
    const searchForm = document.getElementById('searchForm');
    
    if (liveSearch) {
        liveSearch.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                searchForm.submit();
            }
        });
    }
    
    if (clearSearch) {
        clearSearch.addEventListener('click', function() {
            liveSearch.value = '';
            this.classList.add('d-none');
            searchForm.submit();
        });
    }
    
    if (liveSearch) {
        liveSearch.addEventListener('input', function() {
            if (clearSearch) {
                clearSearch.classList.toggle('d-none', this.value === '');
            }
        });
    }
    
    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>
