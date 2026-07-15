<?php
/**
 * Mosque list page (legacy mosques.php markup, verbatim).
 * Expects: $mosques, $total, $page, $pages, $openCount, $fridayCount,
 *          $communityCount, $communities, $statuses, $fridayOptions,
 *          $guideImams, $queryParams, $isAdmin, $csrfToken
 */

$buildQueryString = function (array $newParams = []) use ($queryParams): string {
    return http_build_query(array_merge($queryParams, $newParams));
};

$sortableHeader = function (string $title, string $sortKey) use ($queryParams, $buildQueryString): string {
    $currentSort = $queryParams['sort'] ?? '';
    $currentOrder = $queryParams['order'] ?? '';
    $newOrder = ($currentSort == $sortKey && $currentOrder == 'asc') ? 'desc' : 'asc';
    $iconDirection = ($currentSort == $sortKey && $currentOrder == 'asc') ? 'up' : 'down';

    return '
        <a href="mosques.php?' . $buildQueryString(['sort' => $sortKey, 'order' => $newOrder]) . '" class="text-decoration-none">
            <i class="fas fa-chevron-' . $iconDirection . ' ms-1"></i>
        </a>' . $title;
};
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
                    <h2 class="mb-0"><?= number_format($openCount) ?></h2>
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
                    <h2 class="mb-0"><?= number_format($fridayCount) ?></h2>
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
                    <h2 class="mb-0"><?= number_format($communityCount) ?></h2>
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
                    <?php if ($isAdmin): ?>
                        <a href="add_mosque.php" class="btn btn-primary rounded-pill">
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
                                                    value="<?= htmlspecialchars($queryParams['query'] ?? '') ?>">
                                                <button id="clearSearch" class="btn btn-outline-secondary border-start-0 <?= empty($queryParams['query'] ?? '') ? 'd-none' : '' ?>" type="button" style="border-left: none !important;">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                                <button id="searchButton" class="btn btn-primary px-3" type="submit">
                                                    <i class="fas fa-search"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted mt-1 d-block">اضغط Enter أو أيقونة البحث للبحث</small>
                                            <small id="filterChangedHint" class="text-primary mt-1 d-none">تم تعديل التصفية. اضغط زر البحث لتطبيقها.</small>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="community" class="form-select select2">
                                                <option value="">الجماعات</option>
                                                <?php foreach ($communities as $community): ?>
                                                <option value="<?= htmlspecialchars($community) ?>"<?= isset($queryParams['community']) && $queryParams['community'] == $community ? ' selected' : '' ?>><?= htmlspecialchars($community) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="status" class="form-select select2">
                                                <option value="">الوضعية</option>
                                                <?php foreach ($statuses as $status): ?>
                                                <option value="<?= htmlspecialchars($status) ?>"<?= isset($queryParams['status']) && $queryParams['status'] == $status ? ' selected' : '' ?>><?= htmlspecialchars($status) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-3">
                                            <select name="friday_prayer" class="form-select select2">
                                                <option value="">صلاة الجمعة</option>
                                                <?php foreach ($fridayOptions as $option): ?>
                                                <option value="<?= htmlspecialchars($option) ?>"<?= isset($queryParams['friday_prayer']) && $queryParams['friday_prayer'] == $option ? ' selected' : '' ?>><?= htmlspecialchars(($option == 'نعم') ? 'مساجد الجمعة' : 'مساجد بدون جمعة') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="guide_imam" class="form-select select2">
                                                <option value="">الإمام المرشد</option>
                                                <?php $currentSelection = $queryParams['guide_imam'] ?? ''; ?>
                                                <?php foreach ($guideImams as $imam): ?>
                                                <option value="<?= $imam['id'] ?>"<?= ($currentSelection === (string) $imam['id'] || $currentSelection === $imam['display_name']) ? ' selected' : '' ?>><?= htmlspecialchars($imam['display_name']) ?> (<?= $imam['mosque_count'] ?>)</option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>


                <?php
                $activeFilterLabels = [
                    'query' => 'بحث',
                    'community' => 'الجماعة',
                    'status' => 'الوضعية',
                    'friday_prayer' => 'صلاة الجمعة',
                    'guide_imam' => 'الإمام المرشد',
                    'national_code' => 'الرمز الوطني',
                ];
                $activeFilters = [];
                foreach ($activeFilterLabels as $filterKey => $filterLabel) {
                    if (($queryParams[$filterKey] ?? '') !== '') {
                        $activeFilters[$filterKey] = $filterLabel . ': ' . (string) $queryParams[$filterKey];
                    }
                }
                ?>
                <div class="mosque-list-toolbar card border-0 bg-light mb-3">
                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-3 py-3">
                        <div>
                            <div class="fw-bold">نتائج القائمة: <?= number_format((int) $total) ?></div>
                            <div class="d-flex flex-wrap gap-2 mt-2" aria-label="التصفيات النشطة">
                                <?php if (empty($activeFilters)): ?>
                                    <span class="badge bg-secondary-subtle text-secondary">لا توجد تصفيات نشطة</span>
                                <?php endif; ?>
                                <?php foreach ($activeFilters as $filterKey => $filterText): ?>
                                    <?php $resetParams = $queryParams; unset($resetParams[$filterKey], $resetParams['page']); ?>
                                    <a class="badge bg-primary-subtle text-primary text-decoration-none" href="mosques.php?<?= http_build_query($resetParams) ?>">
                                        <?= $view->e($filterText) ?> <i class="fas fa-times ms-1" aria-hidden="true"></i>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="mosques.php" class="btn btn-outline-secondary btn-sm"><i class="fas fa-rotate-left me-1"></i>إعادة ضبط</a>
                            <button class="btn btn-outline-primary btn-sm" type="button" id="densityToggle"><i class="fas fa-table-list me-1"></i>كثافة الجدول</button>
                            <div class="dropdown">
                                <button class="btn btn-outline-primary btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false"><i class="fas fa-columns me-1"></i>الأعمدة</button>
                                <div class="dropdown-menu p-3 shadow" style="min-width: 220px;">
                                    <?php foreach ([4 => 'العنوان', 8 => 'سنة البناء', 10 => 'الإمام المرشد', 12 => 'الموقع'] as $col => $label): ?>
                                    <div class="form-check">
                                        <input class="form-check-input js-column-toggle" type="checkbox" checked value="<?= $col ?>" id="colToggle<?= $col ?>">
                                        <label class="form-check-label" for="colToggle<?= $col ?>"><?= $view->e($label) ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <!-- Bulk Actions -->
                <div class="d-flex justify-content-between mb-3">
                    <div class="d-flex gap-2">
                        <?php if ($isAdmin): ?>
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

                <div class="table-responsive animate__animated animate__fadeIn mosque-table-wrapper">
                    <table class="table table-hover align-middle app-table">
                            <thead class="table-light text-center">
                                <tr>
                                    <th width="50"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                    <th width="80"><?= $sortableHeader('ر.ت.ع', 'registration_number') ?></th>
                                    <th><?= $sortableHeader('اسم المسجد', 'mosque_name') ?></th>
                                    <th class="mobile-hidden">العنوان</th> <!-- Hide address on mobile -->
                                    <th width="130"><?= $sortableHeader('الرمز الوطني', 'national_code') ?></th>
                                    <th width="90">الجمعة</th>
                                    <th width="90">الوضعية</th>
                                    <th width="90" class="mobile-hidden"><?= $sortableHeader('سنة البناء', 'construction_date') ?></th> <!-- Hide construction date -->
                                    <th width="150">الإمام</th>
                                    <th width="120">الإمام المرشد</th>
                                    <th width="120">الجماعة</th>
                                    <th width="100" class="mobile-hidden">الموقع</th> <!-- Hide coords on mobile -->
                                    <th width="120"><?= $isAdmin ? 'الإجراءات' : 'معاينة' ?></th>
                                </tr>
                            </thead>
                        <tbody aria-live="polite" aria-busy="false">
                            <?php if (count($mosques) > 0): ?>
                                <?php $animationDelay = 0; ?>
                                <?php foreach ($mosques as $row): ?>
                                    <?php $animationDelay += 0.05; ?>
                                    <?= $view->partial('mosques._row', [
                                        'row' => $row,
                                        'animationDelay' => $animationDelay,
                                        'isAdmin' => $isAdmin,
                                        'canEditContent' => $canEditContent ?? $isAdmin,
                                        'canDeleteContent' => $canDeleteContent ?? $isAdmin,
                                        'csrfToken' => $csrfToken,
                                    ]) ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="animate__animated animate__fadeInUp">
                                    <td colspan="10" class="text-center py-4 text-muted">
                                        <i class="fas fa-search me-2"></i><?= isset($queryParams['query']) ? 'لا توجد نتائج مطابقة لبحثك' : 'لا توجد مساجد مسجلة' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>


                <div class="mosque-mobile-cards d-lg-none">
                    <?php if (count($mosques) > 0): ?>
                        <?php foreach ($mosques as $row): ?>
                        <article class="card border-0 shadow-sm mb-3 mosque-mobile-card">
                            <div class="card-body">
                                <div class="d-flex justify-content-between gap-2">
                                    <div>
                                        <h3 class="h6 mb-1"><i class="fas fa-mosque text-primary me-1"></i><?= $view->e($row['mosque_name']) ?></h3>
                                        <div class="small text-muted"><?= $view->e($row['address']) ?></div>
                                    </div>
                                    <span class="badge bg-light text-dark align-self-start"><?= $view->e($row['national_code']) ?></span>
                                </div>
                                <dl class="row small mt-3 mb-2">
                                    <dt class="col-5">الجماعة</dt><dd class="col-7"><?= $view->e($row['community']) ?></dd>
                                    <dt class="col-5">الإمام</dt><dd class="col-7"><?= $view->e($row['imam_name']) ?></dd>
                                    <dt class="col-5">الوضعية</dt><dd class="col-7"><?= $view->e($row['status']) ?></dd>
                                    <dt class="col-5">صلاة الجمعة</dt><dd class="col-7"><?= $view->e($row['friday_prayer']) ?></dd>
                                </dl>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-sm btn-info text-white view-mosque-btn" data-bs-toggle="modal" data-bs-target="#mosqueDetailsModal" data-mosque-id="<?= $view->e($row['registration_number']) ?>"><i class="fas fa-eye me-1"></i>عرض</button>
                                    <?php if ($canEditContent ?? $isAdmin): ?><a class="btn btn-sm btn-primary" href="edit_mosque.php?id=<?= $view->e($row['registration_number']) ?>"><i class="fas fa-pen me-1"></i>تعديل</a><?php endif; ?>
                                    <?php if (!empty($row['latitude']) && !empty($row['longitude'])): ?><button type="button" class="btn btn-sm btn-outline-primary view-on-map" data-lat="<?= $view->e($row['latitude']) ?>" data-lng="<?= $view->e($row['longitude']) ?>" data-mosque="<?= $view->e($row['mosque_name']) ?>"><i class="fas fa-map me-1"></i>خريطة</button><?php endif; ?>
                                </div>
                            </div>
                        </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                <div class="mt-4 animate__animated animate__fadeIn">
                    <?= $view->partial('components.pagination', [
                        'currentPage' => $page,
                        'totalPages' => $pages,
                        'baseUrl' => 'mosques.php',
                        'queryParams' => $queryParams,
                    ]) ?>
                </div>
                <?php endif; ?>
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
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
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
                <button type="button" class="btn btn-primary rounded-pill js-print-mosque-details">
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
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
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


<script nonce="<?= $view->e($cspNonce ?? '') ?>">
    //Global variable
    const IS_ADMIN = <?= $isAdmin ? 'true' : 'false' ?>;
    const CSRF_TOKEN = <?= json_encode((string) $csrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;


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

