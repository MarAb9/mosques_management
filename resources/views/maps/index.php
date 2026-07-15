<?php
/**
 * Mosque map page (legacy mosque_maps.php markup, moved verbatim).
 * Expects: $mosques, $allMosques, $totalMosques, $totalWithCoords,
 *          $totalPages, $page, $communities, $statuses
 */
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
                                     data-friday="<?= $mosque['friday_prayer'] === 'نعم' ? '1' : '0' ?>"
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

<!-- Google Maps API-ready implementation. Add GOOGLE_MAPS_API_KEY to .env to activate. -->
<?php $hasGoogleMapsKey = trim((string) ($googleMapsApiKey ?? '')) !== ''; ?>
<script nonce="<?= $view->e($cspNonce ?? '') ?>">
let map;
let infoWindow;
let markers = [];
let clusterMarkers = [];
let mosquesData = <?= json_encode($mosques, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
let allMosquesData = <?= json_encode($allMosques, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?>;
const mapDefaults = <?= json_encode($mapDefaults ?? ['latitude' => 34.6814, 'longitude' => -1.9086, 'zoom' => 9], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const hasGoogleMapsKey = <?= $hasGoogleMapsKey ? 'true' : 'false' ?>;

let activeFilters = {
    search: '',
    community: '',
    status: '',
    friday: ''
};
let currentSort = 'name';

function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeRegExp(value) {
    return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function statusColor(status) {
    const value = String(status || '');
    if (value.includes('مغلق')) return '#dc3545';
    if (value.includes('دون') || value.includes('ترخيص')) return '#ffc107';
    if (value.includes('مفتوح')) return '#198754';
    return '#6c757d';
}

function statusBadgeClass(status) {
    const value = String(status || '');
    if (value.includes('مغلق')) return 'danger';
    if (value.includes('دون') || value.includes('ترخيص')) return 'warning';
    if (value.includes('مفتوح')) return 'success';
    return 'secondary';
}

function markerIcon(status) {
    return {
        path: google.maps.SymbolPath.CIRCLE,
        fillColor: statusColor(status),
        fillOpacity: 0.95,
        strokeColor: '#ffffff',
        strokeWeight: 2,
        scale: 9
    };
}

window.initGoogleMosqueMap = function initGoogleMosqueMap() {
    try {
        hideMapError();
        showMapLoading();

        const center = {
            lat: Number(mapDefaults.latitude) || 34.6814,
            lng: Number(mapDefaults.longitude) || -1.9086
        };

        map = new google.maps.Map(document.getElementById('map'), {
            center,
            zoom: Number(mapDefaults.zoom) || 9,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            gestureHandling: 'greedy'
        });
        infoWindow = new google.maps.InfoWindow();

        addMosqueMarkers();
        setupMapButtons();
        hideMapLoading();
    } catch (error) {
        console.error('Google Maps initialization error:', error);
        hideMapLoading();
        showMapError('تعذر تحميل خرائط Google. تحقق من المفتاح أو اتصال الشبكة.');
    }
};

function addMosqueMarkers() {
    clearMarkers();
    const bounds = new google.maps.LatLngBounds();
    let validMarkers = 0;

    allMosquesData.forEach(mosque => {
        const lat = Number.parseFloat(mosque.latitude);
        const lng = Number.parseFloat(mosque.longitude);
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;

        const marker = new google.maps.Marker({
            position: { lat, lng },
            title: String(mosque.mosque_name || ''),
            icon: markerIcon(mosque.status),
            optimized: true
        });

        marker.filteredVisible = true;
        marker.mosqueData = {
            id: mosque.registration_number,
            national_code: mosque.national_code,
            name: mosque.mosque_name,
            address: mosque.address,
            imam: mosque.imam_name,
            guide_imam: mosque.guide_imam,
            community: mosque.community,
            status: mosque.status,
            friday: mosque.friday_prayer === 'نعم' ? '1' : '0',
            lat,
            lng,
            raw: mosque
        };
        marker.addListener('click', () => openMarker(marker));

        markers.push(marker);
        bounds.extend(marker.getPosition());
        validMarkers++;
    });

    if (validMarkers > 0) {
        map.fitBounds(bounds, { top: 50, right: 50, bottom: 50, left: 50 });
    }

    google.maps.event.addListener(map, 'idle', renderClusters);
    renderClusters();
}

function renderClusters() {
    if (!map || !window.google) return;

    clearClusterMarkers();
    markers.forEach(marker => marker.setMap(null));

    const visibleMarkers = markers.filter(marker => marker.filteredVisible);
    const zoom = map.getZoom() || Number(mapDefaults.zoom) || 9;

    if (zoom >= 15 || visibleMarkers.length < 60) {
        visibleMarkers.forEach(marker => marker.setMap(map));
        return;
    }

    const gridSize = Math.max(0.0005, 360 / Math.pow(2, zoom + 4));
    const groups = new Map();

    visibleMarkers.forEach(marker => {
        const position = marker.getPosition();
        const key = `${Math.floor(position.lat() / gridSize)}:${Math.floor(position.lng() / gridSize)}`;
        if (!groups.has(key)) groups.set(key, []);
        groups.get(key).push(marker);
    });

    groups.forEach(group => {
        if (group.length < 3) {
            group.forEach(marker => marker.setMap(map));
            return;
        }

        const lat = group.reduce((sum, marker) => sum + marker.getPosition().lat(), 0) / group.length;
        const lng = group.reduce((sum, marker) => sum + marker.getPosition().lng(), 0) / group.length;
        const cluster = new google.maps.Marker({
            map,
            position: { lat, lng },
            label: { text: String(group.length), color: '#ffffff', fontWeight: '700' },
            icon: {
                path: google.maps.SymbolPath.CIRCLE,
                fillColor: '#0d6efd',
                fillOpacity: 0.9,
                strokeColor: '#ffffff',
                strokeWeight: 3,
                scale: 17
            },
            title: `${group.length} مسجد`
        });
        cluster.addListener('click', () => {
            map.setCenter(cluster.getPosition());
            map.setZoom(Math.min((map.getZoom() || 9) + 2, 18));
        });
        clusterMarkers.push(cluster);
    });
}

function applyFilters() {
    if (!map) return;

    const filteredMosques = [];
    markers.forEach(marker => {
        const data = marker.mosqueData;
        const haystack = [data.name, data.address, data.imam, data.community, data.national_code]
            .filter(Boolean)
            .join(' ')
            .toLowerCase();
        let showMarker = true;

        if (activeFilters.search && !haystack.includes(activeFilters.search)) showMarker = false;
        if (activeFilters.community && data.community !== activeFilters.community) showMarker = false;
        if (activeFilters.status && data.status !== activeFilters.status) showMarker = false;
        if (activeFilters.friday !== '' && data.friday !== activeFilters.friday) showMarker = false;

        marker.filteredVisible = showMarker;
        if (showMarker) filteredMosques.push(data);
    });

    updateActiveFiltersDisplay();
    updateSidebarList(filteredMosques);
    fitToVisibleMarkers(false);
    renderClusters();
}

function fitToVisibleMarkers(force = true) {
    if (!map) return;
    const visibleMarkers = markers.filter(marker => marker.filteredVisible);
    if (visibleMarkers.length === 0) return;

    const bounds = new google.maps.LatLngBounds();
    visibleMarkers.forEach(marker => bounds.extend(marker.getPosition()));

    if (visibleMarkers.length === 1) {
        map.setCenter(visibleMarkers[0].getPosition());
        if (force) map.setZoom(16);
        return;
    }

    map.fitBounds(bounds, { top: 50, right: 50, bottom: 50, left: 50 });
}

function setupSearchFunctionality() {
    const globalSearch = document.getElementById('globalSearch');
    const clearGlobalSearch = document.getElementById('clearGlobalSearch');
    const searchSuggestions = document.getElementById('searchSuggestions');
    if (!globalSearch || !clearGlobalSearch || !searchSuggestions) return;

    let searchTimeout;
    globalSearch.addEventListener('input', function(event) {
        const searchTerm = event.target.value.trim().toLowerCase();
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
        }, 250);
    });

    clearGlobalSearch.addEventListener('click', function() {
        globalSearch.value = '';
        activeFilters.search = '';
        searchSuggestions.innerHTML = '';
        searchSuggestions.classList.remove('show');
        applyFilters();
        globalSearch.focus();
    });

    document.addEventListener('click', function(event) {
        if (!globalSearch.contains(event.target) && !searchSuggestions.contains(event.target)) {
            searchSuggestions.classList.remove('show');
        }
    });
}

function showSearchSuggestions(searchTerm) {
    const searchSuggestions = document.getElementById('searchSuggestions');
    const suggestions = allMosquesData.filter(mosque => {
        return [mosque.mosque_name, mosque.address, mosque.imam_name, mosque.national_code]
            .filter(Boolean)
            .join(' ')
            .toLowerCase()
            .includes(searchTerm);
    }).slice(0, 6);

    if (suggestions.length === 0) {
        searchSuggestions.innerHTML = '<div class="text-muted text-center p-2">لا توجد نتائج مقترحة</div>';
        searchSuggestions.classList.add('show');
        return;
    }

    searchSuggestions.innerHTML = '<div class="suggestion-header text-muted small mb-2">نتائج مقترحة:</div>' + suggestions.map(mosque => {
        const lat = Number.parseFloat(mosque.latitude);
        const lng = Number.parseFloat(mosque.longitude);
        return `
            <button type="button" class="suggestion-item p-2 border-bottom cursor-pointer text-start w-100 bg-white border-0"
                    data-lat="${Number.isFinite(lat) ? lat : ''}"
                    data-lng="${Number.isFinite(lng) ? lng : ''}"
                    data-name="${escapeHtml(mosque.mosque_name)}">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <strong>${highlightText(mosque.mosque_name, searchTerm)}</strong>
                        <div class="text-muted small">${highlightText(mosque.address, searchTerm)}</div>
                    </div>
                    <span class="badge bg-${statusBadgeClass(mosque.status)}">${escapeHtml(mosque.status)}</span>
                </div>
            </button>`;
    }).join('');
    searchSuggestions.classList.add('show');

    searchSuggestions.querySelectorAll('.suggestion-item').forEach(item => {
        item.addEventListener('click', function() {
            zoomToMosque(Number.parseFloat(this.dataset.lat || ''), Number.parseFloat(this.dataset.lng || ''), this.dataset.name || '');
            searchSuggestions.classList.remove('show');
        });
    });
}

function setupFilters() {
    const bindings = [
        ['communityFilter', 'community'],
        ['statusFilter', 'status'],
        ['fridayFilter', 'friday']
    ];
    bindings.forEach(([elementId, filterKey]) => {
        const element = document.getElementById(elementId);
        if (element) element.addEventListener('change', function() {
            activeFilters[filterKey] = this.value;
            applyFilters();
        });
    });

    document.getElementById('clearAllFilters')?.addEventListener('click', resetAllFilters);
    document.addEventListener('click', function(event) {
        const button = event.target instanceof Element ? event.target.closest('[data-filter]') : null;
        if (button) removeFilter(button.dataset.filter);
    });
}

function updateActiveFiltersDisplay() {
    const container = document.getElementById('activeFiltersContainer');
    const filtersDiv = document.getElementById('activeFilters');
    if (!container || !filtersDiv) return;

    filtersDiv.innerHTML = '';
    let hasActiveFilters = false;
    const labels = {
        search: 'بحث',
        community: 'الجماعة',
        status: 'الحالة',
        friday: 'صلاة الجمعة'
    };

    Object.entries(activeFilters).forEach(([key, value]) => {
        if (!value) return;
        hasActiveFilters = true;
        const filterBadge = document.createElement('span');
        filterBadge.className = 'badge bg-primary-subtle text-primary border';
        filterBadge.appendChild(document.createTextNode(`${labels[key] || key}: ${key === 'friday' ? (value === '1' ? 'نعم' : 'لا') : value} `));

        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'btn-close btn-close-sm ms-1';
        removeButton.dataset.filter = key;
        removeButton.setAttribute('aria-label', 'إزالة عامل التصفية');
        filterBadge.appendChild(removeButton);
        filtersDiv.appendChild(filterBadge);
    });

    container.style.display = hasActiveFilters ? 'block' : 'none';
}

function removeFilter(filterKey) {
    activeFilters[filterKey] = '';
    const inputMap = {
        search: 'globalSearch',
        community: 'communityFilter',
        status: 'statusFilter',
        friday: 'fridayFilter'
    };
    const element = document.getElementById(inputMap[filterKey]);
    if (element) element.value = '';
    document.getElementById('searchSuggestions')?.classList.remove('show');
    applyFilters();
}

function resetAllFilters() {
    activeFilters = { search: '', community: '', status: '', friday: '' };
    ['globalSearch', 'communityFilter', 'statusFilter', 'fridayFilter'].forEach(id => {
        const element = document.getElementById(id);
        if (element) element.value = '';
    });
    document.getElementById('searchSuggestions')?.classList.remove('show');
    applyFilters();
}

function updateSidebarList(filteredMosques) {
    const mosquesList = document.getElementById('mosquesList');
    const sidebarCount = document.getElementById('sidebarMosqueCount');
    const resultsSummary = document.getElementById('resultsSummary');
    if (!mosquesList || !sidebarCount || !resultsSummary) return;

    if (filteredMosques.length === 0) {
        mosquesList.innerHTML = `
            <div class="empty-state text-center py-5 text-muted">
                <i class="fas fa-search fa-3x mb-3 opacity-50"></i>
                <p class="mb-0">لا توجد نتائج للبحث</p>
                <small class="text-muted">جرب تعديل معايير البحث</small>
            </div>`;
        sidebarCount.textContent = '0';
        resultsSummary.textContent = 'لا توجد نتائج';
        return;
    }

    mosquesList.innerHTML = filteredMosques.map((mosque, index) => {
        const safeId = escapeHtml(mosque.id);
        const safeCode = encodeURIComponent(String(mosque.national_code || mosque.id || ''));
        return `
            <div class="mosque-list-item p-3 border-bottom" data-mosque-id="${safeId}">
                <div class="d-flex justify-content-between align-items-start mb-2">
                    <h6 class="mb-0 text-primary cursor-pointer mosque-name fw-bold">${index + 1}. ${highlightText(mosque.name, activeFilters.search)}</h6>
                    <span class="badge bg-${statusBadgeClass(mosque.status)} px-2 py-1">${escapeHtml(mosque.status)}</span>
                </div>
                <p class="text-muted small mb-1"><i class="fas fa-map-marker-alt me-1"></i>${highlightText(mosque.address, activeFilters.search)}</p>
                <p class="text-muted small mb-1"><i class="fas fa-user me-1"></i>${escapeHtml(mosque.imam || 'غير محدد')}</p>
                ${mosque.community ? `<p class="text-muted small mb-2"><i class="fas fa-users me-1"></i>${escapeHtml(mosque.community)}</p>` : ''}
                <div class="d-flex gap-2">
                    <button class="btn btn-sm btn-outline-primary flex-fill zoom-to-mosque d-flex align-items-center justify-content-center"
                            data-lat="${mosque.lat}" data-lng="${mosque.lng}" data-name="${escapeHtml(mosque.name)}">
                        <i class="fas fa-search-location me-1"></i>تحديد
                    </button>
                    <a href="mosques.php?national_code=${safeCode}&from_map=${safeCode}" class="btn btn-sm btn-outline-info d-flex align-items-center justify-content-center" title="عرض التفاصيل">
                        <i class="fas fa-info-circle"></i>
                    </a>
                </div>
            </div>`;
    }).join('');

    sidebarCount.textContent = String(filteredMosques.length);
    resultsSummary.textContent = `عرض ${filteredMosques.length} مسجد`;
    reattachZoomEventListeners();
}

function createPopupContent(mosque) {
    const data = mosque.raw || mosque;
    const lat = Number.parseFloat(data.latitude || mosque.lat);
    const lng = Number.parseFloat(data.longitude || mosque.lng);
    const nationalCode = data.national_code || mosque.national_code || mosque.id || '';
    const nationalCodeQuery = encodeURIComponent(String(nationalCode));
    const fridayBadge = data.friday_prayer === 'نعم' ? '<span class="badge bg-info me-1">صلاة الجمعة</span>' : '';

    return `
        <div class="mosque-popup text-start">
            <div class="popup-header bg-primary text-white text-center p-3 rounded-top">
                <h6 class="mb-0"><i class="fas fa-mosque me-2"></i>${escapeHtml(data.mosque_name || mosque.name)}</h6>
            </div>
            <div class="popup-body p-3">
                <div class="mb-3">
                    <span class="badge bg-${statusBadgeClass(data.status || mosque.status)} me-2">${escapeHtml(data.status || mosque.status)}</span>
                    ${fridayBadge}
                    <span class="badge bg-secondary">${escapeHtml(nationalCode)}</span>
                </div>
                <div class="mb-2"><i class="fas fa-map-marker-alt text-muted me-2"></i><strong>العنوان:</strong> ${escapeHtml(data.address || mosque.address)}</div>
                <div class="mb-2"><i class="fas fa-user text-muted me-2"></i><strong>الإمام:</strong> ${escapeHtml(data.imam_name || mosque.imam || 'غير محدد')}</div>
                <div class="mb-2"><i class="fas fa-user-tie text-muted me-2"></i><strong>الإمام المرشد:</strong> ${escapeHtml(data.guide_imam || mosque.guide_imam || 'غير محدد')}</div>
                ${data.community || mosque.community ? `<div class="mb-2"><i class="fas fa-users text-muted me-2"></i><strong>الجماعة:</strong> ${escapeHtml(data.community || mosque.community)}</div>` : ''}
                <div class="d-flex gap-2 mt-3">
                    <a href="mosques.php?national_code=${nationalCodeQuery}&from_map=${nationalCodeQuery}" class="btn btn-sm btn-primary text-white flex-fill"><i class="fas fa-info-circle me-1"></i>عرض التفاصيل</a>
                    <button class="btn btn-sm btn-outline-secondary js-open-google-maps" data-lat="${lat}" data-lng="${lng}" title="فتح في خرائط Google"><i class="fas fa-external-link-alt"></i></button>
                </div>
            </div>
        </div>`;
}

function setupMapButtons() {
    document.getElementById('fitToMarkers')?.addEventListener('click', () => fitToVisibleMarkers(true));
    document.getElementById('refreshMap')?.addEventListener('click', () => window.location.reload());
    document.getElementById('retryMap')?.addEventListener('click', () => window.location.reload());
    reattachZoomEventListeners();
}

function reattachZoomEventListeners() {
    document.querySelectorAll('.zoom-to-mosque').forEach(button => {
        button.addEventListener('click', function() {
            zoomToMosque(Number.parseFloat(this.dataset.lat || ''), Number.parseFloat(this.dataset.lng || ''), this.dataset.name || '');
        });
    });
    document.querySelectorAll('.mosque-list-item').forEach(item => {
        item.addEventListener('click', function(event) {
            if (event.target instanceof Element && event.target.closest('a,button')) return;
            const id = this.dataset.mosqueId;
            const marker = markers.find(candidate => String(candidate.mosqueData.id) === String(id));
            if (marker) openMarker(marker);
        });
    });
}

function openMarker(marker) {
    if (!map || !marker) return;
    marker.setMap(map);
    infoWindow.setContent(createPopupContent(marker.mosqueData));
    infoWindow.open({ map, anchor: marker });
    highlightSidebarItem(marker.mosqueData.id);
}

function zoomToMosque(lat, lng, name) {
    if (!map || !Number.isFinite(lat) || !Number.isFinite(lng)) return;
    map.setCenter({ lat, lng });
    map.setZoom(16);
    const marker = markers.find(candidate => Math.abs(candidate.getPosition().lat() - lat) < 0.000001 && Math.abs(candidate.getPosition().lng() - lng) < 0.000001);
    if (marker) openMarker(marker);
}

function highlightSidebarItem(id) {
    document.querySelectorAll('.mosque-list-item').forEach(item => item.classList.remove('is-active'));
    const item = document.querySelector(`.mosque-list-item[data-mosque-id="${CSS.escape(String(id))}"]`);
    if (item) {
        item.classList.add('is-active');
        item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }
}

function clearMarkers() {
    markers.forEach(marker => marker.setMap(null));
    markers = [];
    clearClusterMarkers();
}

function clearClusterMarkers() {
    clusterMarkers.forEach(marker => marker.setMap(null));
    clusterMarkers = [];
}

function openInGoogleMaps(lat, lng) {
    window.open(`https://www.google.com/maps?q=${lat},${lng}&z=17`, '_blank', 'noopener,noreferrer');
}

function showMapLoading() {
    document.getElementById('mapLoading')?.classList.remove('d-none');
}

function hideMapLoading() {
    document.getElementById('mapLoading')?.classList.add('d-none');
}

function showMapError(message) {
    const mapError = document.getElementById('mapError');
    if (!mapError) return;
    if (message) {
        const paragraph = mapError.querySelector('p');
        if (paragraph) paragraph.textContent = message;
    }
    mapError.classList.remove('d-none');
}

function hideMapError() {
    document.getElementById('mapError')?.classList.add('d-none');
}

function highlightText(text, searchTerm) {
    const safeText = escapeHtml(text);
    if (!searchTerm || !text) return safeText;
    const regex = new RegExp(`(${escapeRegExp(searchTerm)})`, 'gi');
    return safeText.replace(regex, '<mark class="bg-warning">$1</mark>');
}

function bootMapPage() {
    setupSearchFunctionality();
    setupFilters();

    document.addEventListener('click', function(event) {
        const target = event.target instanceof Element ? event.target.closest('.js-open-google-maps') : null;
        if (!target) return;
        const lat = Number.parseFloat(target.dataset.lat || '');
        const lng = Number.parseFloat(target.dataset.lng || '');
        if (Number.isFinite(lat) && Number.isFinite(lng)) openInGoogleMaps(lat, lng);
    });

    if (!hasGoogleMapsKey) {
        hideMapLoading();
        showMapError('مفتاح Google Maps غير مضبوط بعد. أضف GOOGLE_MAPS_API_KEY في ملف .env لتفعيل الخريطة.');
    }
}

document.addEventListener('DOMContentLoaded', bootMapPage);
</script>
<?php if ($hasGoogleMapsKey): ?>
<script nonce="<?= $view->e($cspNonce ?? '') ?>" src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode((string) $googleMapsApiKey) ?>&callback=initGoogleMosqueMap&loading=async" async defer></script>
<?php endif; ?>


<style nonce="<?= $view->e($cspNonce ?? '') ?>">
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

.gm-style {
    background: #f8f9fa;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

.custom-mosque-marker {
    background: transparent !important;
    border: none !important;
}

/* Enhanced Google Maps popup styles */
.gm-style .gm-style-iw-c {
    padding: 0;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
}

.gm-style .gm-style-iw-d {
    overflow: auto !important;
}

.mosque-popup {
    width: 300px;
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

.mosque-list-item.is-active {
    background: #e7f1ff;
    border-left-color: #0d6efd;
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

    $html = '<nav aria-label="التنقل بين صفحات النتائج"><ul class="pagination pagination-sm mb-0">';

    // Previous button
    if ($currentPage > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="?page='.($currentPage - 1).'" aria-label="الصفحة السابقة"><i class="fas fa-chevron-right"></i></a></li>';
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
        $html .= '<li class="page-item"><a class="page-link" href="?page='.($currentPage + 1).'" aria-label="الصفحة التالية"><i class="fas fa-chevron-left"></i></a></li>';
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


