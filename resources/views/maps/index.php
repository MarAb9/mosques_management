<?php
/**
 * Mosque map page (legacy mosque_maps.php markup, moved verbatim).
 * Expects: $mosques, $allMosques, $totalMosques, $totalWithCoords,
 *          $totalPages, $page, $communities, $statuses
 */
?>

<div class="container-fluid map-workspace">
    <?= $view->partial('components.page_header', [
        'title' => 'خريطة المساجد',
        'subtitle' => number_format((int) $totalWithCoords) . ' مسجد محدد الموقع من ' . number_format((int) $totalMosques),
        'icon' => 'fa-map-location-dot',
    ]) ?>

    <div class="map-summary reveal" aria-label="ملخص التغطية الجغرافية">
        <span><strong><?= number_format((int) $totalMosques) ?></strong> مسجد</span>
        <span><strong><?= number_format((int) $totalWithCoords) ?></strong> محدد الموقع</span>
        <span><strong><?= $totalMosques > 0 ? number_format(($totalWithCoords / $totalMosques) * 100, 1) : 0 ?>%</strong> تغطية</span>
    </div>

    <!-- Enhanced Search and Filters Section -->
    <div class="row mb-0 map-filter-row">
        <div class="col-12">
            <div class="card map-toolbar-card">
                <div class="card-body">
                    <div class="map-toolbar-heading">
                        <div>
                            <strong>البحث والتصفية</strong>
                            <span>تتحدث الخريطة والقائمة مباشرة</span>
                        </div>
                        <div class="map-toolbar-actions">
                            <span class="map-visible-count"><strong id="toolbarMosqueCount"><?= number_format((int) $totalWithCoords) ?></strong> نتيجة</span>
                            <button type="button" id="clearAllFilters" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-rotate-left" aria-hidden="true"></i><span>إعادة ضبط</span>
                            </button>
                            <button type="button" id="mapFilterToggle" class="map-filter-toggle" aria-expanded="true" aria-controls="mapFilterPanel">
                                <i class="fas fa-sliders-h" aria-hidden="true"></i><span>التصفية</span>
                            </button>
                        </div>
                    </div>
                    <div class="row g-3 map-filter-grid" id="mapFilterPanel">
                        <!-- Main Search -->
                        <div class="col-lg-4 col-md-6">
                            <div class="search-widget">
                                <label class="form-label fw-semibold">
                                    <i class="fas fa-search me-2 text-primary"></i>بحث
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text bg-white border-end-0 ps-3">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" id="globalSearch" class="form-control border-start-0 ps-0"
                                           placeholder="ابحث في المساجد، العناوين، الأئمة...">
                                    <button class="btn btn-outline-secondary border-start-0" type="button" id="clearGlobalSearch" aria-label="مسح البحث" title="مسح البحث">
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
                                    <i class="fas fa-filter me-2 text-primary"></i>الجماعة
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
                                    <i class="fas fa-info-circle me-2 text-primary"></i>الوضعية
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
                    <div class="row mt-4" id="activeFiltersContainer" hidden>
                        <div class="col-12">
                            <div class="d-flex align-items-center flex-wrap gap-2">
                                <span class="text-muted me-2">التصفيات النشطة:</span>
                                <div id="activeFilters" class="d-flex flex-wrap gap-2"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
<!-- Main Map Section -->
    <div class="map-view-switcher" role="tablist" aria-label="اختيار عرض الخريطة أو القائمة">
        <button type="button" role="tab" data-map-view="map" aria-selected="true" aria-controls="mapCanvasColumn">
            <i class="fas fa-map" aria-hidden="true"></i><span>الخريطة</span>
        </button>
        <button type="button" role="tab" data-map-view="list" aria-selected="false" aria-controls="mapListColumn">
            <i class="fas fa-list" aria-hidden="true"></i><span>القائمة</span>
        </button>
    </div>
    <div class="row map-main-row" id="mapMainRow">
        <!-- Map Container -->
        <div class="map-canvas-column" id="mapCanvasColumn">
            <div class="card map-canvas-card">
                <div class="card-header map-canvas-toolbar">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-map me-2 text-primary"></i>المواقع
                    </h5>
                    <div class="d-flex gap-2 flex-wrap">
                        <button id="fitToMarkers" class="btn btn-outline-primary btn-sm d-flex align-items-center">
                            <i class="fas fa-expand-alt me-2"></i>عرض الكل
                        </button>
                        <div class="header-actions">
                            <button id="refreshMap" class="btn btn-light btn-icon" aria-label="تحديث الخريطة" title="تحديث الخريطة">
                                <i class="fas fa-sync-alt"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="card-body p-0 position-relative map-canvas-shell">
                    <div id="map"></div>
                    <aside id="selectedMosquePanel" class="map-selection-panel" aria-live="polite" aria-labelledby="selectedMosqueTitle" aria-hidden="true" hidden>
                        <div class="map-selection-panel__header">
                            <div>
                                <span class="map-selection-panel__eyebrow">المسجد المحدد</span>
                                <h2 id="selectedMosqueTitle">-</h2>
                            </div>
                            <button type="button" id="selectedMosqueClose" class="map-selection-panel__close" aria-label="إغلاق بطاقة المسجد">
                                <i class="fas fa-times" aria-hidden="true"></i>
                            </button>
                        </div>
                        <div class="map-selection-panel__meta" aria-label="حالة المسجد">
                            <span id="selectedMosqueStatus">-</span>
                            <span id="selectedMosqueCode">-</span>
                            <span>الجمعة: <strong id="selectedMosqueFriday">-</strong></span>
                        </div>
                        <div class="map-selection-panel__body">
                            <dl class="map-selection-panel__details">
                                <div><dt>العنوان</dt><dd id="selectedMosqueAddress">-</dd></div>
                                <div><dt>الإمام</dt><dd id="selectedMosqueImam">-</dd></div>
                                <div><dt>الإمام المرشد</dt><dd id="selectedMosqueGuideImam">-</dd></div>
                                <div><dt>الجماعة</dt><dd id="selectedMosqueCommunity">-</dd></div>
                            </dl>
                        </div>
                        <div class="map-selection-panel__actions">
                            <a id="selectedMosqueDetails" class="btn btn-primary btn-sm" href="mosques.php">عرض التفاصيل</a>
                            <button type="button" id="selectedMosqueGoogleMaps" class="btn btn-outline-secondary btn-sm js-open-google-maps">فتح في Google Maps</button>
                        </div>
                    </aside>
                    <div id="mapLoading" class="position-absolute top-50 start-50 translate-middle text-center">
                        <div class="spinner-border text-primary mb-3 map-loading-spinner" role="status">
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
        <div class="map-list-column" id="mapListColumn">
            <div class="card map-list-card">
                <div class="card-header map-list-header">
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
                    <div id="mosquesList">
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

</div>

<!-- Google Maps API-ready implementation. Add GOOGLE_MAPS_API_KEY to .env to activate. -->
<?php $hasGoogleMapsKey = trim((string) ($googleMapsApiKey ?? '')) !== ''; ?>
<style nonce="<?= $view->e($cspNonce ?? '') ?>">/* Nonce seed used by Google Maps for API-injected styles. */</style>
<script type="application/json" id="mapPageData" nonce="<?= $view->e($cspNonce ?? '') ?>"><?= json_encode(['mosques' => $mosques, 'allMosques' => $allMosques, 'mapDefaults' => $mapDefaults ?? ['latitude' => 34.6814, 'longitude' => -1.9086, 'zoom' => 9], 'hasGoogleMapsKey' => $hasGoogleMapsKey], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
<script src="assets/dist/maps.min.js"></script>
<?php if ($hasGoogleMapsKey): ?>
<script nonce="<?= $view->e($cspNonce ?? '') ?>" src="https://maps.googleapis.com/maps/api/js?key=<?= rawurlencode((string) $googleMapsApiKey) ?>&callback=initGoogleMosqueMap&loading=async" async defer></script>
<?php endif; ?>




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


