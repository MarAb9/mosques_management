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
    return http_build_query($params);
}



// Database query setup
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$start = ($page - 1) * $limit;

// Sorting parameters
$sort = isset($_GET['sort']) && in_array($_GET['sort'], ['registration_number', 'mosque_name', 'national_code', 'construction_date']) 
    ? $_GET['sort'] 
    : 'registration_number';

$order = isset($_GET['order']) && strtolower($_GET['order']) == 'asc' 
    ? 'ASC' 
    : 'DESC';

// Base query without LIMIT for counting
$sql = "SELECT * FROM mosques WHERE 1=1";
$countSql = "SELECT COUNT(*) FROM mosques WHERE 1=1";
$params = [];

if (isset($_GET['national_code']) && !empty($_GET['national_code'])) {
    // Check if this is coming from map (exact search) or from user input (like search)
    if (isset($_GET['from_map']) && $_GET['from_map'] == $_GET['national_code']) {
        // Exact search for map links
        $sql .= " AND national_code = ?";
        $countSql .= " AND national_code = ?";
        $params[] = $_GET['national_code'];
    } else {
        // Like search for user input
        $sql .= " AND national_code LIKE ?";
        $countSql .= " AND national_code LIKE ?";
        $params[] = "%{$_GET['national_code']}%";
    }
}

if (isset($_GET['query']) && !empty($_GET['query'])) {
    $searchTerm = "%{$_GET['query']}%";
    
    if (preg_match('/^\d+$/', $_GET['query'])) {
        $sql .= " AND (registration_number = ? OR mosque_name LIKE ? OR imam_name LIKE ? OR preacher_name LIKE ? OR muezzin_name LIKE ?)";
        $countSql .= " AND (registration_number = ? OR mosque_name LIKE ? OR imam_name LIKE ? OR preacher_name LIKE ? OR muezzin_name LIKE ?)";
        $exactTerm = $_GET['query'];

        $params = array_merge($params, [$exactTerm, $searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    } else {
        // Normal search
        $sql .= " AND (mosque_name LIKE ? OR imam_name LIKE ? OR preacher_name LIKE ? OR muezzin_name LIKE ?)";
        $countSql .= " AND (mosque_name LIKE ? OR imam_name LIKE ? OR preacher_name LIKE ? OR muezzin_name LIKE ?)";
        $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    }
}

if (isset($_GET['national_code']) && !empty($_GET['national_code'])) {
    $sql .= " AND national_code LIKE ?";
    $countSql .= " AND national_code LIKE ?";
    $params[] = "%{$_GET['national_code']}%";
}

if (isset($_GET['imam_registration']) && !empty($_GET['imam_registration'])) {
    $sql .= " AND imam_registration LIKE ?";
    $countSql .= " AND imam_registration LIKE ?";
    $params[] = "%{$_GET['imam_registration']}%";
}

// Add community filter
if (isset($_GET['community']) && !empty($_GET['community'])) {
    $sql .= " AND community = ?";
    $countSql .= " AND community = ?";
    $params[] = $_GET['community'];
}

// Add status filter
if (isset($_GET['status']) && !empty($_GET['status'])) {
    $sql .= " AND status = ?";
    $countSql .= " AND status = ?";
    $params[] = $_GET['status'];
}

// Add Friday prayer filter
if (isset($_GET['friday_prayer']) && !empty($_GET['friday_prayer'])) {
    $sql .= " AND friday_prayer = ?";
    $countSql .= " AND friday_prayer = ?";
    $params[] = $_GET['friday_prayer'];
}
// Add guide_imam filter

if (isset($_GET['guide_imam']) && !empty($_GET['guide_imam'])) {
    // Remove any count in parentheses if present
    $guideName = preg_replace('/\s*\(\d+\)$/', '', $_GET['guide_imam']);
    $names = explode(' ', trim($guideName), 2);
    
    if (count($names) == 2) {
        // Search for both name orders
        $sql .= " AND (guide_imam LIKE ? OR guide_imam LIKE ?)";
        $countSql .= " AND (guide_imam LIKE ? OR guide_imam LIKE ?)";
        $params[] = "%{$names[0]}%{$names[1]}%"; // First Last
        $params[] = "%{$names[1]}%{$names[0]}%"; // Last First
    } else {
        // Single name search
        $sql .= " AND guide_imam LIKE ?";
        $countSql .= " AND guide_imam LIKE ?";
        $params[] = "%{$guideName}%";
    }
}
// Add sorting
if ($sort == 'construction_date') {
    $sql .= " ORDER BY YEAR(construction_date) $order";
} else {
    $sql .= " ORDER BY $sort $order";
}

// Get total count first
$countStmt = $pdo->prepare($countSql);
foreach ($params as $key => $value) {
    $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $countStmt->bindValue($key + 1, $value, $paramType);
}
$countStmt->execute();
$total = $countStmt->fetchColumn();
$pages = ceil($total / $limit);

// Now add pagination to the main query
$sql .= " LIMIT ?, ?";
$params[] = $start;
$params[] = $limit;

// Execute main query
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
    $stmt->bindValue($key + 1, $value, $paramType);
}
$stmt->execute();

// Helper function to generate sortable table header
function sortableHeader($title, $sortKey) {
    $currentSort = $_GET['sort'] ?? '';
    $currentOrder = $_GET['order'] ?? '';
    $newOrder = ($currentSort == $sortKey && $currentOrder == 'asc') ? 'desc' : 'asc';
    $iconDirection = ($currentSort == $sortKey && $currentOrder == 'asc') ? 'up' : 'down';
    
    return '
        <a href="mosques.php?'.buildQueryString(['sort' => $sortKey, 'order' => $newOrder]).'" class="text-decoration-none">
            <i class="fas fa-chevron-'.$iconDirection.' ms-1"></i>
        </a>'.$title;
}
?>



<!-- Dashboard Overview Cards -->
<div class="row mb-4 g-4 animate__animated animate__fadeIn">
    <div class="col-md-3">
        <div class="card bg-primary-gradient text-white shadow-lg border-0 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-mosque fa-3x opacity-75"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="card-title mb-1">إجمالي المساجد</h6>
                    <h2 class="mb-0"><?= number_format($total) ?></h2>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-md-3">
        <div class="card bg-success-gradient text-white shadow-lg border-0 h-100">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0">
                    <i class="fas fa-check-circle fa-3x opacity-75"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="card-title mb-1">مساجد مفتوحة</h6>
                    <h2 class="mb-0">
                        <?php 
                        $openCount = $pdo->query("SELECT COUNT(*) FROM mosques WHERE status = 'مفتوح'")->fetchColumn();
                        echo number_format($openCount);
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
                    <i class="fas fa-calendar-alt fa-3x opacity-75"></i>
                </div>
                <div class="flex-grow-1 ms-3">
                    <h6 class="card-title mb-1">مساجد الجمعة</h6>
                    <h2 class="mb-0">
                        <?php 
                        $fridayCount = $pdo->query("SELECT COUNT(*) FROM mosques WHERE friday_prayer = 'نعم'")->fetchColumn();
                        echo number_format($fridayCount);
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
                    <h6 class="card-title mb-1">الجماعات</h6>
                    <h2 class="mb-0">
                        <?php 
                        $communityCount = $pdo->query("SELECT COUNT(DISTINCT community) FROM mosques WHERE community IS NOT NULL")->fetchColumn();
                        echo number_format($communityCount);
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
                        <i class="fas fa-mosque me-2"></i>قائمة المساجد
                    </h5>
                    <small class="text-muted">إدارة كافة المساجد المسجلة في النظام</small>
                </div>
                <div class="d-flex gap-2">
                    <?php if ($_SESSION['role'] == 'admin'): ?>
                        <a href="add_mosque.php" class="btn btn-primary rounded-pill animate__animated animate__pulse animate__infinite">
                            <i class="fas fa-plus me-2"></i>إضافة مسجد جديد
                        </a>
                    <?php endif; ?>
                </div>
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
                                <form method="get" action="mosques.php" id="searchForm">
                                    <input type="hidden" name="page" value="1">
                                    <div class="row g-3">
                                        <div class="col-md-5">
                                            <div class="input-group has-validation glass-search">
                                                <span class="input-group-text bg-white border-end-0">
                                                    <i class="fas fa-search text-muted"></i>
                                                </span>
                                                <input type="text" name="query" id="liveSearch" class="form-control border-start-0 py-2" 
                                                    placeholder="ابحث بأي معلومة (اسم المسجد، الإمام، الرمز الوطني...)"
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
                                            <select name="status" class="form-select select2" onchange="this.form.submit()">
                                                <option value="">الوضعية</option>
                                                <?php
                                                $statuses = $pdo->query("SELECT DISTINCT status FROM mosques WHERE status IS NOT NULL AND status != '' ORDER BY status")->fetchAll(PDO::FETCH_COLUMN);
                                                foreach ($statuses as $status) {
                                                    $selected = isset($_GET['status']) && $_GET['status'] == $status ? 'selected' : '';
                                                    echo "<option value=\"" . htmlspecialchars($status) . "\" $selected>" . htmlspecialchars($status) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select name="friday_prayer" class="form-select select2" onchange="this.form.submit()">
                                                <option value="">صلاة الجمعة</option>
                                                <?php
                                                $fridayOptions = $pdo->query("SELECT DISTINCT friday_prayer FROM mosques WHERE friday_prayer IS NOT NULL AND friday_prayer != '' ORDER BY friday_prayer")->fetchAll(PDO::FETCH_COLUMN);
                                                foreach ($fridayOptions as $option) {
                                                    $selected = isset($_GET['friday_prayer']) && $_GET['friday_prayer'] == $option ? 'selected' : '';
                                                    $displayText = ($option == 'نعم') ? 'مساجد الجمعة' : 'مساجد بدون جمعة';
                                                    echo "<option value=\"" . htmlspecialchars($option) . "\" $selected>" . htmlspecialchars($displayText) . "</option>";
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="guide_imam" class="form-select select2" onchange="this.form.submit()">
                                                <option value="">الإمام المرشد</option>
                                                <?php
                                                // Get all unique guide imams with their mosque counts
                                                $guideImams = $pdo->query("
                                                    SELECT guide_imam, COUNT(*) as mosque_count 
                                                    FROM mosques 
                                                    WHERE guide_imam IS NOT NULL AND guide_imam != ''
                                                    GROUP BY guide_imam
                                                    ORDER BY guide_imam
                                                ")->fetchAll(PDO::FETCH_ASSOC);
                                                
                                                // Create a normalized list of names to avoid duplicates
                                                $normalizedNames = [];
                                                foreach ($guideImams as $imam) {
                                                    $name = trim($imam['guide_imam']);
                                                    $names = explode(' ', $name, 2);
                                                    
                                                    if (count($names) == 2) {
                                                        // Create both possible name orders
                                                        $normalized1 = $names[0] . ' ' . $names[1];
                                                        $normalized2 = $names[1] . ' ' . $names[0];
                                                        
                                                        // Check if either variation already exists
                                                        if (!isset($normalizedNames[$normalized1]) && !isset($normalizedNames[$normalized2])) {
                                                            $normalizedNames[$normalized1] = [
                                                                'display' => $normalized1,
                                                                'count' => $imam['mosque_count']
                                                            ];
                                                        } else {
                                                            // If a variation exists, merge the counts
                                                            $existingKey = isset($normalizedNames[$normalized1]) ? $normalized1 : $normalized2;
                                                            $normalizedNames[$existingKey]['count'] += $imam['mosque_count'];
                                                        }
                                                    } else {
                                                        // Single name
                                                        $normalizedNames[$name] = [
                                                            'display' => $name,
                                                            'count' => $imam['mosque_count']
                                                        ];
                                                    }
                                                }
                                                
                                                // Sort the normalized names alphabetically
                                                ksort($normalizedNames);
                                                
                                                // Get current selection (without count)
                                                $currentSelection = isset($_GET['guide_imam']) ? 
                                                    preg_replace('/\s*\(\d+\)$/', '', $_GET['guide_imam']) : '';
                                                
                                                // Output the options
                                                foreach ($normalizedNames as $nameData) {
                                                    $selected = (trim($currentSelection) === trim($nameData['display'])) ? 'selected' : '';
                                                    echo "<option value=\"" . htmlspecialchars($nameData['display']) . "\" $selected>" . 
                                                        htmlspecialchars($nameData['display']) . " ({$nameData['count']})</option>";
                                                }
                                                ?>
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
                        <?php if ($_SESSION['role'] == 'admin'): ?>
                            <button id="deleteSelected" class="btn btn-danger rounded-pill animate__animated animate__pulse" disabled>
                                <i class="fas fa-trash-alt me-2"></i>حذف المحدد
                            </button>
                        <?php endif; ?>
                    </div>
                    <div class="text-muted">
                        <button id="selectedCountBtn" class="btn btn-success rounded-pill animate__animated animate__pulse" disabled>
                            <span id="selectedCount">0</span> مسجد(اً) محدد
                        </button>
                    </div>
                </div>
                
                <div class="table-responsive animate__animated animate__fadeIn">
                    <table class="table table-hover align-middle">
                            <thead class="table-light text-center">
                                <tr>
                                    <th width="50"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                    <th width="80"><?= sortableHeader('ر.ت.ع', 'registration_number') ?></th>
                                    <th><?= sortableHeader('اسم المسجد', 'mosque_name') ?></th>
                                    <th class="mobile-hidden">العنوان</th> <!-- Hide address on mobile -->
                                    <th width="130"><?= sortableHeader('الرمز الوطني', 'national_code') ?></th>
                                    <th width="90">الجمعة</th>
                                    <th width="90">الوضعية</th>
                                    <th width="90" class="mobile-hidden"><?= sortableHeader('سنة البناء', 'construction_date') ?></th> <!-- Hide construction date -->
                                    <th width="150">الإمام</th>
                                    <th width="120">الإمام المرشد</th>
                                    <th width="120">الجماعة</th>
                                    <th width="100" class="mobile-hidden">الموقع</th> <!-- Hide coords on mobile -->
                                    <th width="120"><?php echo ($_SESSION['role'] == 'admin') ? 'الإجراءات' : 'معاينة'; ?></th>                            
                                </tr>
                            </thead>
                        <tbody>
                            <?php
                            $animationDelay = 0;
                            if ($stmt->rowCount() > 0) {
                                while ($row = $stmt->fetch()) {
                                    $animationDelay += 0.05;
                                    echo renderMosqueRow($row, $animationDelay);
                                }
                            } else {
                                echo '<tr class="animate__animated animate__fadeInUp">
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="fas fa-search me-2"></i>'.(isset($_GET['query']) ? 'لا توجد نتائج مطابقة لبحثك' : 'لا توجد مساجد مسجلة').'
                                    </td>
                                </tr>';
                            }
                            
function renderMosqueRow($row, $animationDelay) {

        $highlightClass = '';
    if (isset($_SESSION['highlight_mosque_national_code']) && 
        $_SESSION['highlight_mosque_national_code'] == $row['national_code']) {
        $highlightClass = 'table-success';
        // Clear the highlight after use
        unset($_SESSION['highlight_mosque_national_code']);
    }
    $fridayIcon = $row['friday_prayer'] == 'نعم' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
    $statusIcon = ($row['status'] == 'مفتوح') ? 'fa-check-circle text-success' : (($row['status'] == 'مغلق') ? 'fa-times-circle text-danger' : 'fa-times-circle text-warning');
    
    // Check if user is admin for edit/delete buttons
    $isAdmin = ($_SESSION['role'] == 'admin');
    
    $actionButtons = '';
    if ($isAdmin) {
        $deleteToken = htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8');
        $actionButtons = '
        <a href="edit_mosque.php?id='.$row['registration_number'].'" 
            class="btn btn-sm btn-icon btn-primary rounded-circle"
            data-bs-toggle="tooltip"
            data-bs-placement="top" 
            title="تعديل">
                <i class="fas fa-pen"></i>
            </a>
        <form method="POST" action="delete_mosque.php" class="d-inline" onsubmit="return confirm(\'هل أنت متأكد من حذف هذا المسجد؟\')">
            <input type="hidden" name="csrf_token" value="'.$deleteToken.'">
            <input type="hidden" name="id" value="'.$row['registration_number'].'">
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
            <input type="checkbox" name="selected_mosques[]" value="'.$row['registration_number'].'" class="form-check-input mosque-checkbox">
        </td>
        <td class="fw-bold text-muted">'.$row['registration_number'].'</td>
        <td>
            <div class="d-flex align-items-center">
                <i class="fas fa-mosque text-primary me-2"></i>
                <span>'.$row['mosque_name'].'</span>
            </div>
        </td>
        <td class="mobile-hidden">
            <div class="d-flex align-items-center">
                <i class="fas fa-map-marker-alt text-danger me-2"></i>
                <small class="text-muted">'.$row['address'].'</small>
            </div>
        </td>
        <td><span class="badge bg-light text-dark">'.$row['national_code'].'</span></td>
        <td>
            <span class="d-flex justify-content-center" data-bs-toggle="tooltip" title="'.($row['friday_prayer'] == 'نعم' ? 'يوجد صلاة جمعة' : 'لا يوجد صلاة جمعة').'">
                <i class="fas '.$fridayIcon.' fa-lg"></i>
            </span>
        </td>
        <td>
            <span class="d-flex justify-content-center" data-bs-toggle="tooltip" title="'.(($row['status'] == 'مفتوح') ? 'مسجد مفتوح' : (($row['status'] == 'مغلق') ? 'مسجد مغلق' : 'مسجد مفتوح بدون ترخيص')).'">
                <i class="fas '.$statusIcon.' fa-lg"></i>
            </span>
        </td>
        <td class="mobile-hidden">
        <span class="badge bg-primary-gradient">' . ($row['construction_date'] ? date('Y', strtotime($row['construction_date'])) : '') . '</span></td>
        <td>
            <div class="d-flex align-items-center">
                <i class="fas fa-user fs-4 text-info me-2"></i>
                <span>'.$row['imam_name'].'</span>
            </div>
        </td>
        <td>
            <div class="d-flex align-items-center">
                <i class="fas fa-user-tie fs-4 text-warning me-2"></i>
                <span>'.$row['guide_imam'].'</span>
            </div>
        </td>
        <td><span class="badge bg-info">'.$row['community'].'</span></td>
        <!-- ADD THIS CELL FOR GPS LOCATION -->
        <td class="mobile-hidden">
            '.renderLocationCell($row).'
        </td>
        <td>
            <div class="d-flex gap-2">
                <!-- VIEW BUTTON - ALWAYS VISIBLE -->
                <a href="#" class="btn btn-sm btn-icon btn-info rounded-circle view-mosque-btn"
                data-bs-toggle="modal" 
                data-bs-target="#mosqueDetailsModal"
                data-mosque-id="'.$row['registration_number'].'"
                data-bs-tooltip="tooltip" 
                data-bs-placement="top" 
                title="عرض التفاصيل">
                    <i class="fas fa-eye"></i>
                </a>
                
                <!-- EDIT/DELETE BUTTONS - ONLY FOR ADMIN -->
                '.$actionButtons.'
            </div>
        </td>
    </tr>';
}

function renderLocationCell($row) {
    if (!empty($row['latitude']) && !empty($row['longitude'])) {
        return '
        <button class="btn btn-sm btn-outline-primary view-on-map" 
                data-lat="'.htmlspecialchars($row['latitude']).'" 
                data-lng="'.htmlspecialchars($row['longitude']).'"
                data-mosque="'.htmlspecialchars($row['mosque_name']).'"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                title="عرض على الخريطة">
            <i class="fas fa-map-marked-alt"></i>
        </button>';
    } else {
        return '<span class="text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="لم يتم تحديد الموقع">غير محدد</span>';
    }
}
                            ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Pagination -->
                <?php
                if ($pages > 1) {
                    echo '<div class="mt-4 animate__animated animate__fadeIn">';
                    echo renderPagination($page, $pages);
                    echo '</div>';
                }
                
                function renderPagination($currentPage, $totalPages) {
                    $queryString = '';
                    foreach (['query', 'national_code', 'imam_registration', 'community', 'status', 'friday_prayer','guide_imam', 'sort', 'order'] as $param) {
                        if (isset($_GET[$param])) {
                            $queryString .= '&' . $param . '=' . urlencode($_GET[$param]);
                        }
                    }
                    
                    $html = '<nav aria-label="Page navigation" class="mt-4 animate__animated animate__fadeIn">
                        <ul class="pagination justify-content-center">';
                    
                    // Previous button
                    if ($currentPage > 1) {
                        $html .= '<li class="page-item">
                            <a class="page-link" href="mosques.php?page='.($currentPage-1).$queryString.'" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>';
                    }
                    
                    // Page numbers
                    $startPage = max(1, $currentPage - 2);
                    $endPage = min($totalPages, $currentPage + 2);
                    
                    if ($startPage > 1) {
                        $html .= '<li class="page-item"><a class="page-link" href="mosques.php?page=1'.$queryString.'">1</a></li>';
                        if ($startPage > 2) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                    }
                    
                    for ($i = $startPage; $i <= $endPage; $i++) {
                        $html .= '<li class="page-item '.($currentPage == $i ? 'active' : '').'">
                            <a class="page-link" href="mosques.php?page='.$i.$queryString.'">'.$i.'</a>
                        </li>';
                    }
                    
                    if ($endPage < $totalPages) {
                        if ($endPage < $totalPages - 1) $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
                        $html .= '<li class="page-item"><a class="page-link" href="mosques.php?page='.$totalPages.$queryString.'">'.$totalPages.'</a></li>';
                    }
                    
                    // Next button
                    if ($currentPage < $totalPages) {
                        $html .= '<li class="page-item">
                            <a class="page-link" href="mosques.php?page='.($currentPage+1).$queryString.'" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
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

<!-- Mosque Details Modal -->
<div class="modal fade" id="mosqueDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title"><i class="fas fa-mosque me-2"></i>تفاصيل المسجد</h5>
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
                <button type="button" class="btn btn-primary rounded-pill" onclick="printMosqueDetails()">
                    <i class="fas fa-print me-2"></i>طباعة التفاصيل
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Quick Stats Modal -->
<div class="modal fade" id="quickStatsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-info text-white">
                <h5 class="modal-title"><i class="fas fa-chart-pie me-2"></i>إحصائيات المساجد</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-chart-bar me-2"></i>توزيع المساجد حسب الوضعية</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="statusChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-4 border-0 shadow-sm">
                            <div class="card-header bg-light">
                                <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>مساجد الجمعة</h6>
                            </div>
                            <div class="card-body">
                                <canvas id="fridayChart" height="200"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-users me-2"></i>توزيع المساجد حسب الجماعة</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="communityChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary rounded-pill" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>إغلاق
                </button>
            </div>
        </div>
    </div>
</div>


<script>
    //Global variable
    const IS_ADMIN = <?= $_SESSION['role'] == 'admin' ? 'true' : 'false' ?>;
    const CSRF_TOKEN = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>';

    
    // Bulk delete handler
document.getElementById('deleteSelected')?.addEventListener('click', function() {
    const selected = document.querySelectorAll('.mosque-checkbox:checked');
    const ids = Array.from(selected).map(cb => cb.value);
    
    if (ids.length > 0 && confirm(`هل أنت متأكد من حذف ${ids.length} مسجد(اً)؟`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'bulk_delete_mosques.php';
        
        const tokenInput = document.createElement('input');
        tokenInput.type = 'hidden';
        tokenInput.name = 'csrf_token';
        tokenInput.value = CSRF_TOKEN;
        form.appendChild(tokenInput);
        
        ids.forEach(id => {
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
</script>
<?php require_once 'includes/footer.php'; ?>
