<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
checkAuth();
require_once 'includes/header.php';

// Enhanced pagination with error handling
$limit = 15;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$start = ($page - 1) * $limit;

try {
    // Count mosques with coordinates
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM mosques WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
    $countStmt->execute();
    $totalWithCoords = $countStmt->fetchColumn();
    $totalPages = max(1, ceil($totalWithCoords / $limit));

    // Get paginated mosques with coordinates (for display)
    $stmt = $pdo->prepare("
        SELECT m.registration_number, m.mosque_name, m.national_code, m.address, m.imam_name, m.status, 
               m.friday_prayer, m.community, COALESCE(gi.display_name, m.guide_imam) AS guide_imam, m.latitude, m.longitude 
        FROM mosques m
        LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id
        WHERE m.latitude IS NOT NULL AND m.longitude IS NOT NULL
        ORDER BY m.mosque_name 
        LIMIT ?, ?
    ");
    $stmt->bindParam(1, $start, PDO::PARAM_INT);
    $stmt->bindParam(2, $limit, PDO::PARAM_INT);
    $stmt->execute();
    $mosques = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get ALL mosques with coordinates for search functionality
    $allMosquesStmt = $pdo->prepare("
        SELECT m.registration_number, m.mosque_name, m.national_code, m.address, m.imam_name, m.status, 
               m.friday_prayer, m.community, COALESCE(gi.display_name, m.guide_imam) AS guide_imam, m.latitude, m.longitude 
        FROM mosques m
        LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id
        WHERE m.latitude IS NOT NULL AND m.longitude IS NOT NULL
        ORDER BY m.mosque_name
    ");
    $allMosquesStmt->execute();
    $allMosques = $allMosquesStmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total mosques count
    $totalMosques = $pdo->query("SELECT COUNT(*) FROM mosques")->fetchColumn();
    
    // Get unique communities for filter
    $communitiesStmt = $pdo->prepare("
        SELECT DISTINCT community 
        FROM mosques 
        WHERE community IS NOT NULL AND community != '' AND community != 'غير محدد'
        ORDER BY community
    ");
    $communitiesStmt->execute();
    $communities = $communitiesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Get unique statuses for filter
    $statusesStmt = $pdo->prepare("
        SELECT DISTINCT status 
        FROM mosques 
        WHERE status IS NOT NULL AND status != ''
        ORDER BY status
    ");
    $statusesStmt->execute();
    $statuses = $statusesStmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    error_log("Database error in mosque_maps.php: " . $e->getMessage());
    $mosques = [];
    $allMosques = [];
    $totalWithCoords = 0;
    $totalMosques = 0;
    $totalPages = 1;
    $communities = [];
    $statuses = [];
}
?>

<div class="container-fluid">
    <!-- Modern Header Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="page-header d-flex align-items-center justify-content-between py-4">
                <div class="d-flex align-items-center">
                    <div class="header-icon bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                        <i class="fas fa-map-marked-alt text-primary fa-xl"></i>
                    </div>
                    <div>
                        <h1 class="h3 mb-1 fw-bold">خريطة المساجد</h1>
                        <p class="text-muted mb-0">تصور جغرافي لمواقع المساجد على الخريطة</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Statistics Cards -->
    <div class="row mb-5">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary bg-opacity-10 rounded-3 p-3 me-3">
                            <i class="fas fa-mosque text-primary fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">إجمالي المساجد</h6>
                            <h3 class="mb-0 fw-bold"><?= number_format($totalMosques) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success bg-opacity-10 rounded-3 p-3 me-3">
                            <i class="fas fa-map-marker-alt text-success fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">مساجد محددة الموقع</h6>
                            <h3 class="mb-0 fw-bold"><?= number_format($totalWithCoords) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info bg-opacity-10 rounded-3 p-3 me-3">
                            <i class="fas fa-percentage text-info fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">نسبة التحديد</h6>
                            <h3 class="mb-0 fw-bold">
                                <?= $totalMosques > 0 ? number_format(($totalWithCoords / $totalMosques) * 100, 1) : 0 ?>%
                            </h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="stat-card card border-0 shadow-sm h-100">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning bg-opacity-10 rounded-3 p-3 me-3">
                            <i class="fas fa-map text-warning fa-lg"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h6 class="card-title text-muted mb-1">المساجد بدون موقع</h6>
                            <h3 class="mb-0 fw-bold"><?= number_format($totalMosques - $totalWithCoords) ?></h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Enhanced Search and Filters Section -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row g-4">
                        <!-- Main Search -->
                        <div class="col-lg-4 col-md-6">
                            <div class="search-widget">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-search me-2 text-primary"></i>بحث متقدم
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 ps-3">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" id="globalSearch" class="form-control border-start-0 ps-0" 
                                           placeholder="ابحث في المساجد، العناوين، الأئمة...">
                                    <button class="btn btn-outline-secondary border-start-0" type="button" id="clearGlobalSearch">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                                <div class="search-suggestions mt-2" id="searchSuggestions"></div>
                            </div>
                        </div>

                        <!-- Community Filter -->
                        <div class="col-lg-3 col-md-6">
                            <div class="filter-widget">
                                <label class="form-label fw-semibold mb-2">
                                    <i class="fas fa-filter me-2 text-primary"></i>تصفية حسب الجماعة
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-users text-muted"></i>
                                    </span>
                                    <select id="communityFilter" class="form-select border-start-0">
                                        <option value="">جميع الجماعات</option>
                                        <?php foreach ($communities as $community): ?>
                                            <option value="<?= htmlspecialchars($community) ?>"><?= htmlspecialchars($community) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Status Filter -->
                        <div class="col-lg-3 col-md-6">
                            <div class="filter-widget">
                                <label class="form-label fw-semibold mb-2">
                                    <i class="fas fa-info-circle me-2 text-primary"></i>حالة المسجد
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-star text-muted"></i>
                                    </span>
                                    <select id="statusFilter" class="form-select border-start-0">
                                        <option value="">جميع الحالات</option>
                                        <?php foreach ($statuses as $status): ?>
                                            <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Friday Prayer Filter -->
                        <div class="col-lg-2 col-md-6">
                            <div class="filter-widget">
                                <label class="form-label fw-semibold mb-2">
                                    <i class="fas fa-calendar-alt me-2 text-primary"></i>صلاة الجمعة
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0">
                                        <i class="fas fa-mosque text-muted"></i>
                                    </span>
                                    <select id="fridayFilter" class="form-select border-start-0">
                                        <option value="">الكل</option>
                                        <option value="1">نعم</option>
                                        <option value="0">لا</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Active Filters Display -->
                    <div class="row mt-4" id="activeFiltersContainer" style="display: none;">
                        <div class="col-12">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <span class="text-muted me-2">التصفيات النشطة:</span>
                                <div id="activeFilters" class="d-flex flex-wrap gap-2"></div>
                                <button id="clearAllFilters" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-times me-1"></i>مسح الكل
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="row mb-4">
        <div class="col-12">
            <div class="pagination-container card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            الصفحة <?= $page ?> من <?= $totalPages ?>
                        </div>
                        <?= renderPagination($page, $totalPages) ?>
                        <div class="text-muted">
                            <?= number_format($start + 1) ?> - <?= number_format(min($start + $limit, $totalWithCoords)) ?> من <?= number_format($totalWithCoords) ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Main Map Section -->
    <div class="row">
        <!-- Map Container -->
        <div class="col-lg-9 col-md-8 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-3 py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-map me-2 text-primary"></i>خريطة تواجد المساجد
                    </h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <button id="fitToMarkers" class="btn btn-outline-primary btn-sm d-flex align-items-center">
                            <i class="fas fa-expand-alt me-2"></i>عرض الكل
                        </button>
                        <div class="header-actions">
                            <button id="refreshMap" class="btn btn-light btn-icon">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0 position-relative">
                    <div id="map" style="height: 700px; width: 100%;"></div>
                    <div id="mapLoading" class="position-absolute top-50 start-50 translate-middle text-center">
                        <div class="spinner-border text-primary mb-3" role="status" style="width: 3rem; height: 3rem;">
                            <span class="visually-hidden">جاري التحميل...</span>
                        </div>
                        <div class="text-primary fw-bold">جاري تحميل الخريطة...</div>
                    </div>
                    <div id="mapError" class="position-absolute top-50 start-50 translate-middle text-center d-none">
                        <div class="alert alert-danger border-0 shadow">
                            <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                            <p class="mb-2">حدث خطأ في تحميل الخريطة</p>
                            <button id="retryMap" class="btn btn-sm btn-danger mt-1">إعادة المحاولة</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

         <!-- Mosque List Sidebar -->
        <div class="col-lg-3 col-md-4 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white d-flex justify-content-between align-items-center py-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-list me-2 text-primary"></i>قائمة المساجد
                    </h5>
                    <div class="d-flex align-items-center">
                        <span class="badge bg-primary rounded-pill px-2 py-1 me-2" id="sidebarMosqueCount"><?= count($mosques) ?></span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="p-3 border-bottom bg-light">
                        <!-- Results Summary -->
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted" id="resultsSummary">عرض <?= count($mosques) ?> مسجد</small>
                        </div>
                    </div>
                    <div id="mosquesList" style="max-height: 610px; overflow-y: auto;">
                        <?php if (empty($mosques)): ?>
                            <div class="text-center py-5 text-muted">
                                <i class="fas fa-map-marker-alt fa-3x mb-3 opacity-50"></i>
                                <p class="mb-0">لا توجد مساجد محددة الموقع</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($mosques as $index => $mosque): ?>
                                <div class="mosque-list-item p-3 border-bottom" 
                                     data-mosque-id="<?= htmlspecialchars($mosque['registration_number']) ?>"
                                     data-name="<?= htmlspecialchars($mosque['mosque_name']) ?>"
                                     data-address="<?= htmlspecialchars($mosque['address']) ?>"
                                     data-imam="<?= htmlspecialchars($mosque['imam_name'] ?: '') ?>"
                                     data-community="<?= htmlspecialchars($mosque['community'] ?: '') ?>"
                                     data-status="<?= htmlspecialchars($mosque['status']) ?>"
                                     data-friday="<?= $mosque['friday_prayer'] ? '1' : '0' ?>"
                                     data-lat="<?= htmlspecialchars($mosque['latitude']) ?>"
                                     data-lng="<?= htmlspecialchars($mosque['longitude']) ?>">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 text-primary cursor-pointer mosque-name fw-bold">
                                            <?= ($start + $index + 1) ?>. <?= htmlspecialchars($mosque['mosque_name']) ?>
                                        </h6>
                                        <span class="badge bg-<?= getStatusBadgeColor($mosque['status']) ?> px-2 py-1">
                                            <?= htmlspecialchars($mosque['status']) ?>
                                        </span>
                                    </div>
                                    <p class="text-muted small mb-1">
                                        <i class="fas fa-map-marker-alt me-1"></i>
                                        <?= htmlspecialchars($mosque['address']) ?>
                                    </p>
                                    <p class="text-muted small mb-1">
                                        <i class="fas fa-user me-1"></i>
                                        <?= htmlspecialchars($mosque['imam_name'] ?: 'غير محدد') ?>
                                    </p>
                                    <?php if (!empty($mosque['community']) && $mosque['community'] !== 'غير محدد'): ?>
                                    <p class="text-muted small mb-2">
                                        <i class="fas fa-users me-1"></i>
                                        <?= htmlspecialchars($mosque['community']) ?>
                                    </p>
                                    <?php endif; ?>
                                    
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-sm btn-outline-primary flex-fill zoom-to-mosque d-flex align-items-center justify-content-center" 
                                                data-lat="<?= htmlspecialchars($mosque['latitude']) ?>" 
                                                data-lng="<?= htmlspecialchars($mosque['longitude']) ?>"
                                                data-name="<?= htmlspecialchars($mosque['mosque_name']) ?>">
                                            <i class="fas fa-search-location me-1"></i>تحديد
                                        </button>
                                        <a href="mosques.php?national_code=<?= urlencode($mosque['national_code']) ?>&from_map=<?= urlencode($mosque['national_code']) ?>" 
                                        class="btn btn-sm btn-outline-info d-flex align-items-center justify-content-center" 
                                        title="عرض التفاصيل في قائمة المساجد">
                                            <i class="fas fa-info-circle"></i>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="row mt-4">
        <div class="col-12">
            <div class="pagination-container card border-0 shadow-sm">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            الصفحة <?= $page ?> من <?= $totalPages ?>
                        </div>
                        <?= renderPagination($page, $totalPages) ?>
                        <div class="text-muted">
                            عرض <?= number_format(count($mosques)) ?> مسجد
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Include Leaflet CSS & JS -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
// Global variables
let map;
let markers = [];
let currentPage = <?= $page ?>;
let mosquesData = <?= json_encode($mosques) ?>;
let allMosquesData = <?= json_encode($allMosques) ?>;
let activeFilters = {
    search: '',
    community: '',
    status: '',
    friday: ''
};
let currentSort = 'name';

// Initialize Leaflet Map
function initMap() {
    try {
        hideMapError();
        showMapLoading();
        
        const defaultCenter = [31.7917, -7.0926];
        
        map = L.map('map').setView(defaultCenter, 7);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: false,
            maxZoom: 19
        }).addTo(map);
        
        addMosqueMarkers();
        setupEventListeners();
        hideMapLoading();
        
    } catch (error) {
        console.error('Map initialization error:', error);
        hideMapLoading();
        showMapError();
    }
}

// Add mosque markers - use ALL data for markers
function addMosqueMarkers() {
    if (!map) return;
    
    // Clear existing markers
    clearMarkers();
    
    let validMarkers = 0;
    const bounds = L.latLngBounds();
    
    // Use allMosquesData instead of mosquesData for markers
    allMosquesData.forEach((mosque, index) => {
        const lat = parseFloat(mosque.latitude);
        const lng = parseFloat(mosque.longitude);
        
        if (isNaN(lat) || isNaN(lng)) return;
        
        // Create custom icon
        const icon = createCustomIcon(mosque.status);
        
        // Create marker
        const marker = L.marker([lat, lng], { icon: icon })
            .addTo(map)
            .bindPopup(createPopupContent(mosque));
        
        // Store marker reference with searchable data
        marker.mosqueData = {
            id: mosque.registration_number,
            name: mosque.mosque_name,
            address: mosque.address,
            imam: mosque.imam_name,
            community: mosque.community,
            status: mosque.status,
            friday: mosque.friday_prayer ? '1' : '0',
            lat: lat,
            lng: lng
        };
        
        markers.push(marker);
        bounds.extend([lat, lng]);
        validMarkers++;
    });
    
    // Fit map to show all markers
    if (validMarkers > 0) {
        map.fitBounds(bounds, { padding: [50, 50] });
    }
}

// Enhanced filter functionality
function applyFilters() {
    if (!map) return;
    
    let visibleMarkers = 0;
    const bounds = L.latLngBounds();
    let filteredMosques = [];
    
    markers.forEach(marker => {
        const data = marker.mosqueData;
        let showMarker = true;
        
        // Apply search filter
        if (activeFilters.search && 
            !data.name.toLowerCase().includes(activeFilters.search) &&
            !data.address.toLowerCase().includes(activeFilters.search) &&
            !(data.imam && data.imam.toLowerCase().includes(activeFilters.search))) {
            showMarker = false;
        }
        
        // Apply community filter
        if (activeFilters.community && data.community !== activeFilters.community) {
            showMarker = false;
        }
        
        // Apply status filter
        if (activeFilters.status && data.status !== activeFilters.status) {
            showMarker = false;
        }
        
        // Apply Friday prayer filter
        if (activeFilters.friday !== '' && data.friday != activeFilters.friday) {
            showMarker = false;
        }
        
        if (showMarker) {
            marker.addTo(map);
            bounds.extend(marker.getLatLng());
            visibleMarkers++;
            filteredMosques.push(data);
        } else {
            map.removeLayer(marker);
        }
    });
    
    // Update UI
    updateActiveFiltersDisplay();
    updateSidebarList(filteredMosques);
    
    // Update map view if there are visible markers
    if (visibleMarkers > 0) {
        map.fitBounds(bounds, { padding: [50, 50] });
    }
}

// Update active filters display
function updateActiveFiltersDisplay() {
    const container = document.getElementById('activeFiltersContainer');
    const filtersDiv = document.getElementById('activeFilters');
    
    filtersDiv.innerHTML = '';
    let hasActiveFilters = false;
    
    Object.entries(activeFilters).forEach(([key, value]) => {
        if (value && value !== '') {
            hasActiveFilters = true;
            const filterBadge = document.createElement('span');
            filterBadge.className = 'badge bg-success bg-opacity-20 text-white border border-sucess';
            
            let filterText = '';
            switch(key) {
                case 'search':
                    filterText = `بحث: "${value}"`;
                    break;
                case 'community':
                    filterText = `الجماعة: ${value}`;
                    break;
                case 'status':
                    filterText = `الحالة: ${value}`;
                    break;
                case 'friday':
                    filterText = `صلاة الجمعة: ${value === '1' ? 'نعم' : 'لا'}`;
                    break;
            }
            
            filterBadge.innerHTML = `
                ${filterText}
                <button type="button" class="btn-close btn-close-sm ms-1" data-filter="${key}"></button>
            `;
            filtersDiv.appendChild(filterBadge);
        }
    });
    
    container.style.display = hasActiveFilters ? 'block' : 'none';
}

// Setup enhanced search functionality
function setupSearchFunctionality() {
    const globalSearch = document.getElementById('globalSearch');
    const clearGlobalSearch = document.getElementById('clearGlobalSearch');
    const searchSuggestions = document.getElementById('searchSuggestions');
    
    // Global search with debouncing
    let searchTimeout;
    globalSearch.addEventListener('input', function(e) {
        const searchTerm = e.target.value.trim().toLowerCase();
        activeFilters.search = searchTerm;
        
        clearTimeout(searchTimeout);
        searchTimeout = setTimeout(() => {
            if (searchTerm.length >= 2) {
                showSearchSuggestions(searchTerm);
            } else {
                searchSuggestions.innerHTML = '';
                searchSuggestions.classList.remove('show');
            }
            applyFilters();
        }, 300);
    });
    
    // Clear global search
    clearGlobalSearch.addEventListener('click', function() {
        globalSearch.value = '';
        activeFilters.search = '';
        searchSuggestions.innerHTML = '';
        searchSuggestions.classList.remove('show');
        applyFilters();
        globalSearch.focus();
    });
    
    // Search suggestions
    function showSearchSuggestions(searchTerm) {
        const suggestions = allMosquesData.filter(mosque => 
            mosque.mosque_name.toLowerCase().includes(searchTerm) ||
            mosque.address.toLowerCase().includes(searchTerm) ||
            (mosque.imam_name && mosque.imam_name.toLowerCase().includes(searchTerm))
        ).slice(0, 5);
        
        if (suggestions.length > 0) {
            let html = '<div class="suggestion-header text-muted small mb-2">نتائج مقترحة:</div>';
            suggestions.forEach(mosque => {
                html += `
                    <div class="suggestion-item p-2 border-bottom cursor-pointer" 
                         data-lat="${mosque.latitude}" 
                         data-lng="${mosque.longitude}"
                         data-name="${mosque.mosque_name}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong>${highlightText(mosque.mosque_name, searchTerm)}</strong>
                                <div class="text-muted small">${highlightText(mosque.address, searchTerm)}</div>
                            </div>
                            <span class="badge bg-${getStatusBadgeClass(mosque.status)}">${mosque.status}</span>
                        </div>
                    </div>
                `;
            });
            searchSuggestions.innerHTML = html;
            searchSuggestions.classList.add('show');
            
            // Add click handlers to suggestions
            document.querySelectorAll('.suggestion-item').forEach(item => {
                item.addEventListener('click', function() {
                    const lat = parseFloat(this.getAttribute('data-lat'));
                    const lng = parseFloat(this.getAttribute('data-lng'));
                    const name = this.getAttribute('data-name');
                    zoomToMosque(lat, lng, name);
                    searchSuggestions.classList.remove('show');
                });
            });
        } else {
            searchSuggestions.innerHTML = '<div class="text-muted text-center p-2">لا توجد نتائج مقترحة</div>';
            searchSuggestions.classList.add('show');
        }
    }
    
    // Hide suggestions when clicking outside
    document.addEventListener('click', function(e) {
        if (!globalSearch.contains(e.target) && !searchSuggestions.contains(e.target)) {
            searchSuggestions.classList.remove('show');
        }
    });
}

// Setup filter functionality
function setupFilters() {
    // Community filter
    document.getElementById('communityFilter').addEventListener('change', function() {
        activeFilters.community = this.value;
        applyFilters();
    });
    
    // Status filter
    document.getElementById('statusFilter').addEventListener('change', function() {
        activeFilters.status = this.value;
        applyFilters();
    });
    
    // Friday prayer filter
    document.getElementById('fridayFilter').addEventListener('change', function() {
        activeFilters.friday = this.value;
        applyFilters();
    });
    
    // Clear all filters
    document.getElementById('clearAllFilters').addEventListener('click', function() {
        resetAllFilters();
    });
    
    // Remove individual filter
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('btn-close')) {
            const filterKey = e.target.getAttribute('data-filter');
            removeFilter(filterKey);
        }
    });
}

// Remove specific filter
function removeFilter(filterKey) {
    activeFilters[filterKey] = '';
    
    // Reset corresponding form element
    switch(filterKey) {
        case 'search':
            document.getElementById('globalSearch').value = '';
            document.getElementById('searchSuggestions').classList.remove('show');
            break;
        case 'community':
            document.getElementById('communityFilter').value = '';
            break;
        case 'status':
            document.getElementById('statusFilter').value = '';
            break;
        case 'friday':
            document.getElementById('fridayFilter').value = '';
            break;
    }
    
    applyFilters();
}

// Reset all filters
function resetAllFilters() {
    activeFilters = {
        search: '',
        community: '',
        status: '',
        friday: ''
    };
    
    document.getElementById('globalSearch').value = '';
    document.getElementById('communityFilter').value = '';
    document.getElementById('statusFilter').value = '';
    document.getElementById('fridayFilter').value = '';
    document.getElementById('searchSuggestions').classList.remove('show');
    
    applyFilters();
}



// Apply sorting to sidebar list
function applySorting() {
    const mosquesList = document.getElementById('mosquesList');
    const items = Array.from(mosquesList.querySelectorAll('.mosque-list-item'));
    
    items.sort((a, b) => {
        const aValue = a.getAttribute(`data-${currentSort}`) || '';
        const bValue = b.getAttribute(`data-${currentSort}`) || '';
        return aValue.localeCompare(bValue, 'ar');
    });
    
    // Re-append sorted items
    items.forEach(item => mosquesList.appendChild(item));
}


// Update sidebar list with filtered results
function updateSidebarList(filteredMosques) {
    const mosquesList = document.getElementById('mosquesList');
    const sidebarCount = document.getElementById('sidebarMosqueCount');
    const resultsSummary = document.getElementById('resultsSummary');
    
    if (filteredMosques.length === 0) {
        mosquesList.innerHTML = `
            <div class="text-center py-5 text-muted">
                <i class="fas fa-search fa-3x mb-3 opacity-50"></i>
                <p class="mb-0">لا توجد نتائج للبحث</p>
                <small class="text-muted">جرب تعديل معايير البحث</small>
            </div>
        `;
        sidebarCount.textContent = '0';
        resultsSummary.textContent = 'لا توجد نتائج';
        return;
    }
    
    let html = '';
    filteredMosques.forEach((mosque, index) => {
        const searchTerm = activeFilters.search;
        const highlightedName = searchTerm ? highlightText(mosque.name, searchTerm) : mosque.name;
        const highlightedAddress = searchTerm ? highlightText(mosque.address, searchTerm) : mosque.address;
        const highlightedImam = mosque.imam ? (searchTerm ? highlightText(mosque.imam, searchTerm) : mosque.imam) : 'غير محدد';
        
        html += `
            <div class="mosque-list-item p-3 border-bottom" 
                 data-mosque-id="${mosque.id}"
                 data-name="${mosque.name}"
                 data-address="${mosque.address}"
                 data-imam="${mosque.imam || ''}"
                 data-community="${mosque.community || ''}"
                 data-status="${mosque.status}"
                 data-friday="${mosque.friday || '0'}"
                 data-lat="${mosque.lat}"
                 data-lng="${mosque.lng}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0 text-primary cursor-pointer mosque-name fw-bold">
                        ${index + 1}. ${highlightedName}
                    </h6>
                    <span class="badge bg-${getStatusBadgeClass(mosque.status)} px-2 py-1">
                        ${mosque.status}
                    </span>
                </div>
                <p class="text-muted small mb-1">
                    <i class="fas fa-map-marker-alt me-1"></i>
                    ${highlightedAddress}
                </p>
                <p class="text-muted small mb-1">
                    <i class="fas fa-user me-1"></i>
                    ${highlightedImam}
                </p>
                ${mosque.community && mosque.community !== 'غير محدد' ? `
                <p class="text-muted small mb-2">
                    <i class="fas fa-users me-1"></i>
                    ${mosque.community}
                </p>
                ` : ''}
                
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary flex-fill zoom-to-mosque d-flex align-items-center justify-content-center" 
                            data-lat="${mosque.lat}" 
                            data-lng="${mosque.lng}"
                            data-name="${mosque.name}">
                        <i class="fas fa-search-location me-1"></i>تحديد
                    </button>
                    <a href="mosques.php?national_code=${mosque.id}&from_map=${mosque.id}" 
                    class="btn btn-sm btn-outline-info d-flex align-items-center justify-content-center" 
                    title="عرض التفاصيل في قائمة المساجد">
                        <i class="fas fa-info-circle"></i>
                    </a>
                </div>
            </div>
        `;
    });
    
    mosquesList.innerHTML = html;
    sidebarCount.textContent = filteredMosques.length;
    resultsSummary.textContent = `عرض ${filteredMosques.length} مسجد`;
    
    // Re-attach event listeners
    reattachZoomEventListeners();
    applySorting();
}

// Create custom icon
function createCustomIcon(status) {
    const colors = {
        'مفتوح': '#28a745',
        'مغلق': '#dc3545',
        'مفتوح دون ترخيص': '#ffc107',
        'default': '#6c757d'
    };
    
    const color = colors[status] || colors.default;
    
    return L.divIcon({
        className: 'custom-mosque-marker',
        html: `
            <div style="
                background-color: ${color};
                width: 30px;
                height: 30px;
                border-radius: 50% 50% 50% 0;
                transform: rotate(-45deg);
                border: 3px solid white;
                box-shadow: 0 2px 6px rgba(0,0,0,0.3);
                position: relative;
            ">
                <div style="
                    position: absolute;
                    top: 50%;
                    left: 50%;
                    transform: translate(-50%, -50%) rotate(45deg);
                    width: 8px;
                    height: 8px;
                    background: white;
                    border-radius: 50%;
                "></div>
            </div>
        `,
        iconSize: [30, 30],
        iconAnchor: [15, 30]
    });
}

// Create popup content
function createPopupContent(mosque) {
    const statusColor = getStatusBadgeClass(mosque.status);
    const fridayBadge = mosque.friday_prayer ? '<span class="badge bg-info me-1">صلاة الجمعة</span>' : '';
    
    return `
        <div class="mosque-popup text-start">
            <div class="popup-header bg-primary text-white text-center p-3 rounded-top">
                <h6 class="mb-0">
                    <i class="fas fa-mosque me-2"></i>
                    <strong>مسجد </strong>${mosque.mosque_name}
                </h6>
            </div>
            <div class="popup-body p-3">
                <div class="mb-3">
                    <span class="badge bg-${statusColor} me-2">${mosque.status}</span>
                    ${fridayBadge}
                    <span class="badge bg-secondary">${mosque.national_code}</span>
                </div>
                
                <div class="mb-2">
                    <i class="fas fa-map-marker-alt text-muted me-2"></i>
                    <strong>العنوان:</strong> ${mosque.address}
                </div>
                
                <div class="mb-2">
                    <i class="fas fa-user text-muted me-2"></i>
                    <strong>الإمام:</strong> ${mosque.imam_name || 'غير محدد'}
                </div>
                <div class="mb-2">
                    <i class="fas fa-user-tie text-muted me-2"></i>
                    <strong> الإمام المرشد: </strong> ${mosque.guide_imam || 'غير محدد'}
                </div>
                
                ${mosque.community && mosque.community !== 'غير محدد' ? `
                <div class="mb-2">
                    <i class="fas fa-users text-muted me-2"></i>
                    <strong>الجماعة:</strong> ${mosque.community}
                </div>
                ` : ''}
                
                <div class="d-flex gap-2 mt-3">
                    <a href="mosques.php?national_code=${mosque.national_code}&from_map=${mosque.national_code}" 
                       class="btn btn-sm btn-primary text-white flex-fill">
                        <i class="fas fa-info-circle me-1"></i>عرض التفاصيل
                    </a>
                    <button class="btn btn-sm btn-outline-secondary" 
                            onclick="openInGoogleMaps(${mosque.latitude}, ${mosque.longitude})"
                            title="فتح في خرائط جوجل">
                        <i class="fas fa-external-link-alt"></i>
                    </button>
                </div>
            </div>
        </div>
    `;
}

// Setup event listeners
function setupEventListeners() {
    // Fit to markers button
    document.getElementById('fitToMarkers').addEventListener('click', () => {
        if (markers.length > 0) {
            const group = new L.featureGroup(markers);
            map.fitBounds(group.getBounds(), { padding: [50, 50] });
        }
    });

    // Mosque list item clicks
    reattachZoomEventListeners();

    // Refresh map button
    document.getElementById('refreshMap').addEventListener('click', () => {
        window.location.reload();
    });

    // Retry map button
    document.getElementById('retryMap').addEventListener('click', initMap);
}

// Re-attach event listeners to zoom buttons
function reattachZoomEventListeners() {
    document.querySelectorAll('.zoom-to-mosque').forEach(button => {
        button.addEventListener('click', function() {
            const lat = parseFloat(this.getAttribute('data-lat'));
            const lng = parseFloat(this.getAttribute('data-lng'));
            const name = this.getAttribute('data-name');
            
            zoomToMosque(lat, lng, name);
        });
    });
}

// Zoom to specific mosque
function zoomToMosque(lat, lng, name) {
    map.setView([lat, lng], 16);
    
    // Find and open popup for the marker
    const marker = markers.find(m => {
        const markerLat = m.getLatLng().lat;
        const markerLng = m.getLatLng().lng;
        return markerLat === lat && markerLng === lng;
    });
    
    if (marker) {
        marker.openPopup();
    }
}

// Clear markers
function clearMarkers() {
    markers.forEach(marker => {
        map.removeLayer(marker);
    });
    markers = [];
}

// Utility functions
function openInGoogleMaps(lat, lng) {
    const url = `https://www.google.com/maps?q=${lat},${lng}&z=17`;
    window.open(url, '_blank');
}

function showMapLoading() {
    document.getElementById('mapLoading').classList.remove('d-none');
}

function hideMapLoading() {
    document.getElementById('mapLoading').classList.add('d-none');
}

function showMapError() {
    document.getElementById('mapError').classList.remove('d-none');
}

function hideMapError() {
    document.getElementById('mapError').classList.add('d-none');
}

function getStatusBadgeClass(status) {
    const classes = {
        'مفتوح': 'success',
        'مغلق': 'danger',
        'مفتوح دون ترخيص': 'warning',
        'default': 'secondary'
    };
    return classes[status] || classes['default'];
}

// Highlight matching text in search results
function highlightText(text, searchTerm) {
    if (!searchTerm || !text) return text;
    const regex = new RegExp(`(${searchTerm})`, 'gi');
    return text.replace(regex, '<mark class="bg-warning">$1</mark>');
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    initMap();
    setupSearchFunctionality();
    setupFilters();
});
</script>

<style>
/* Modern Design Enhancements */
:root {
    --primary-color: #4361ee;
    --secondary-color: #3f37c9;
    --success-color: #4cc9f0;
    --info-color: #4895ef;
    --warning-color: #f72585;
    --light-bg: #f8f9fa;
    --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    --hover-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
}

body {
    background-color: #f5f7fb;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* Enhanced Header */
.page-header {
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
    background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
    border-radius: 12px;
    margin-bottom: 1.5rem;
}

.header-icon {
    background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
    color: white;
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.header-actions .btn-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Enhanced Statistics Cards */
.stat-card {
    transition: all 0.3s ease;
    border-radius: 12px;
    overflow: hidden;
    background: white;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--hover-shadow);
}

.stat-icon {
    width: 60px;
    height: 60px;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* Enhanced Map Container */
#map {
    border-radius: 12px;
    overflow: hidden;
}

.leaflet-container {
    background: #f8f9fa;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.custom-mosque-marker {
    background: transparent !important;
    border: none !important;
}

/* Enhanced Popup styles */
.leaflet-popup-content {
    margin: 0;
    width: 300px !important;
}

.leaflet-popup-content-wrapper {
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

/* Enhanced Search and Filter Styles */
.search-widget, .filter-widget {
    position: relative;
}

.search-suggestions {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0 0 8px 8px;
    border-top: none;
    z-index: 1000;
    display: none;
    max-height: 200px;
    overflow-y: auto;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.search-suggestions.show {
    display: block;
}

.suggestion-header {
    padding: 0.5rem 1rem;
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.suggestion-item {
    cursor: pointer;
    transition: background-color 0.2s;
}

.suggestion-item:hover {
    background-color: #f8f9fa;
}

/* Active Filters Styles */
#activeFilters .badge {
    padding: 0.5rem 0.75rem;
    font-weight: 500;
}

#activeFilters .btn-close {
    font-size: 0.7rem;
    padding: 0.25rem;
}

/* Enhanced Form Controls */
.form-label {
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.input-group-lg .form-control {
    border-radius: 0 8px 8px 0;
}

.input-group-lg .input-group-text {
    border-radius: 8px 0 0 8px;
}

/* Enhanced Card Styles */
.card {
    border-radius: 12px;
    border: none;
    box-shadow: var(--card-shadow);
    transition: all 0.3s ease;
}

.card:hover {
    box-shadow: var(--hover-shadow);
}

.card-header {
    border-radius: 12px 12px 0 0 !important;
    border-bottom: 1px solid rgba(0, 0, 0, 0.05);
}

/* Enhanced List Items */
.mosque-list-item {
    transition: all 0.3s ease;
    border-left: 3px solid transparent;
}

.mosque-list-item:hover {
    background: linear-gradient(135deg, #f8f9fa, #e9ecef);
    border-left-color: var(--primary-color);
    transform: translateX(2px);
}

.mosque-name:hover {
    color: var(--secondary-color) !important;
}

/* Enhanced Buttons */
.btn {
    border-radius: 8px;
    transition: all 0.3s ease;
    font-weight: 500;
}

.btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.btn-sm {
    padding: 0.375rem 0.75rem;
    font-size: 0.875rem;
}

/* Enhanced Badges */
.badge {
    border-radius: 6px;
    font-weight: 500;
}

/* Enhanced Inputs */
.form-control, .form-select {
    border-radius: 8px;
    transition: all 0.3s ease;
}

.form-control:focus, .form-select:focus {
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.25);
    border-color: var(--primary-color);
}

.input-group-text {
    border-radius: 8px;
}

/* Enhanced Pagination */
.pagination-container {
    border-radius: 12px;
}

.pagination {
    margin-bottom: 0;
}

.page-link {
    border-radius: 6px;
    margin: 0 2px;
    border: none;
    color: #6c757d;
}

.page-item.active .page-link {
    background-color: var(--primary-color);
    border-color: var(--primary-color);
}

.page-link:hover {
    color: var(--primary-color);
    background-color: #e9ecef;
    border-color: #dee2e6;
}



/* Enhanced Dropdown */
.dropdown-menu {
    border-radius: 8px;
    border: none;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.dropdown-item {
    padding: 0.5rem 1rem;
    transition: background-color 0.2s;
}

.dropdown-item:hover {
    background-color: #f8f9fa;
}

/* Export Button */
#exportResults {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

/* Custom Scrollbar */
#mosquesList::-webkit-scrollbar {
    width: 6px;
}

#mosquesList::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

#mosquesList::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

#mosquesList::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Loading animation */
#mapLoading {
    background: rgba(255,255,255,0.95);
    padding: 30px;
    border-radius: 12px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.1);
    backdrop-filter: blur(10px);
    z-index: 1000;
}

/* Map attribution */
.leaflet-control-attribution {
    display: none !important;
}

/* Responsive Design */
@media (max-width: 768px) {
    #map {
        height: 400px;
    }
    
    .search-suggestions {
        position: fixed;
        top: auto;
        left: 0;
        right: 0;
        bottom: 0;
        max-height: 50vh;
        border-radius: 12px 12px 0 0;
    }
    
    .filter-widget {
        margin-bottom: 1rem;
    }
    
    #activeFilters {
        justify-content: center;
    }
    
    .page-header {
        flex-direction: column;
        align-items: flex-start !important;
    }
    
    .header-actions {
        margin-top: 1rem;
        width: 100%;
    }
}

/* Highlight Text */
mark {
    background-color: #fff3cd;
    padding: 0.1rem 0.2rem;
    border-radius: 2px;
}

/* Smooth Transitions */
.search-suggestions,
.suggestion-item,
#activeFiltersContainer {
    transition: all 0.3s ease;
}

/* Loading States */
.search-loading .input-group-text::after {
    content: '';
    display: inline-block;
    width: 12px;
    height: 12px;
    border: 2px solid transparent;
    border-top: 2px solid #6c757d;
    border-radius: 50%;
    animation: spin 1s linear infinite;
    margin-left: 8px;
}

@keyframes spin {
    0% { transform: rotate(0deg); }
    100% { transform: rotate(360deg); }
}

/* Enhanced Focus States */
.form-control:focus,
.form-select:focus {
    box-shadow: 0 0 0 0.2rem rgba(67, 97, 238, 0.15);
    border-color: #4361ee;
}

/* Custom Scrollbar for Suggestions */
.search-suggestions::-webkit-scrollbar {
    width: 6px;
}

.search-suggestions::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.search-suggestions::-webkit-scrollbar-thumb {
    background: #c1c1c1;
    border-radius: 3px;
}

.search-suggestions::-webkit-scrollbar-thumb:hover {
    background: #a8a8a8;
}

/* Status indicator in list */
.status-indicator {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
    margin-right: 5px;
}

.status-open {
    background-color: #28a745;
}

.status-closed {
    background-color: #dc3545;
}

.status-unauthorized {
    background-color: #ffc107;
}

/* Improved spacing */
.mb-4, .my-4 {
    margin-bottom: 1.5rem !important;
}

.mb-5 {
    margin-bottom: 3rem !important;
}

/* Enhanced text hierarchy */
h1, h2, h3, h4, h5, h6 {
    font-weight: 600;
}

.text-muted {
    color: #6c757d !important;
}
</style>

<?php
// Helper functions
function renderPagination($currentPage, $totalPages) {
    if ($totalPages <= 1) return '';
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination pagination-sm mb-0">';
    
    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page='.($currentPage - 1).'" aria-label="Previous"><i class="fas fa-chevron-right"></i></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-right"></i></span></li>';
    }
    
    // Page numbers
    $startPage = max(1, $currentPage - 2);
    $endPage = min($totalPages, $currentPage + 2);
    
    if ($startPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page=1">1</a></li>';
        if ($startPage > 2) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
    }
    
    for ($i = $startPage; $i <= $endPage; $i++) {
        $active = $i == $currentPage ? ' active' : '';
        $html .= '<li class="page-item'.$active.'"><a class="page-link" href="?page='.$i.'">'.$i.'</a></li>';
    }
    
    if ($endPage < $totalPages) {
        if ($endPage < $totalPages - 1) {
            $html .= '<li class="page-item disabled"><span class="page-link">...</span></li>';
        }
        $html .= '<li class="page-item"><a class="page-link" href="?page='.$totalPages.'">'.$totalPages.'</a></li>';
    }
    
    // Next button
    if ($currentPage < $totalPages) {
        $html .= '<li class="page-item"><a class="page-link" href="?page='.($currentPage + 1).'" aria-label="Next"><i class="fas fa-chevron-left"></i></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><span class="page-link"><i class="fas fa-chevron-left"></i></span></li>';
    }
    
    $html .= '</ul></nav>';
    return $html;
}

function getStatusBadgeColor($status) {
    $colors = [
        'مفتوح' => 'success',
        'مغلق' => 'danger', 
        'مفتوح دون ترخيص' => 'warning'
    ];
    return $colors[$status] ?? 'secondary';
}

function getStatusBadgeClass($status) {
    $classes = [
        'مفتوح' => 'success',
        'مغلق' => 'danger',
        'مفتوح دون ترخيص' => 'warning',
        'default' => 'secondary'
    ];
    return $classes[$status] ?? $classes['default'];
}
?>

<?php require_once 'includes/footer.php'; ?>