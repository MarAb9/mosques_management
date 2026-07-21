<?php
/**
 * Mosque directory.
 * Expects the list, filters, permissions, pagination, and CSRF values supplied
 * by the existing controller.
 */

$buildQueryString = function (array $newParams = []) use ($queryParams): string {
    return http_build_query(array_merge($queryParams, $newParams));
};

$sortableHeader = function (string $title, string $sortKey) use ($queryParams, $buildQueryString): string {
    $currentSort = $queryParams['sort'] ?? '';
    $currentOrder = $queryParams['order'] ?? '';
    $newOrder = ($currentSort === $sortKey && $currentOrder === 'asc') ? 'desc' : 'asc';
    $iconDirection = ($currentSort === $sortKey && $currentOrder === 'asc') ? 'up' : 'down';
    $directionLabel = $newOrder === 'asc' ? 'تصاعدياً' : 'تنازلياً';

    return $title . '<a href="mosques.php?' . $buildQueryString(['sort' => $sortKey, 'order' => $newOrder]) . '" class="sort-link" aria-label="ترتيب ' . $title . ' ' . $directionLabel . '"><i class="fas fa-chevron-' . $iconDirection . '" aria-hidden="true"></i></a>';
};

$canEdit = $canEditContent ?? $isAdmin;
$canDelete = $canDeleteContent ?? $isAdmin;
$tableColumnCount = $canDelete ? 13 : 12;
$directoryActions = '<a class="btn btn-outline-primary" href="import_export.php?export=1"><i class="fas fa-file-export me-2" aria-hidden="true"></i>تصدير</a>';
if ($canEdit) {
    $directoryActions .= '<a class="btn btn-primary" href="add_mosque.php"><i class="fas fa-plus me-2" aria-hidden="true"></i>إضافة مسجد</a>';
}

$activeFilterLabels = [
    'query' => 'بحث',
    'community' => 'الجماعة',
    'status' => 'الوضعية',
    'friday_prayer' => 'الجمعة',
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

<div class="directory-page">
    <?= $view->partial('components.page_header', [
        'title' => 'دليل المساجد',
        'subtitle' => number_format((int) $total) . ' مسجد',
        'icon' => 'fa-mosque',
        'illustration' => 'assets/images/institutional/mosque-building-3d.svg',
        'actionsHtml' => $directoryActions,
    ]) ?>

    <section class="data-panel directory-panel reveal" aria-label="أدوات دليل المساجد">
        <form class="directory-toolbar" method="GET" action="mosques.php" id="searchForm">
            <input type="hidden" name="page" value="1">
            <div class="directory-toolbar__primary">
                <div class="directory-search input-group">
                    <span class="input-group-text" aria-hidden="true"><i class="fas fa-magnifying-glass"></i></span>
                    <label class="visually-hidden" for="liveSearch">البحث في المساجد</label>
                    <input class="form-control" type="search" name="query" id="liveSearch" value="<?= $view->e($queryParams['query'] ?? '') ?>" placeholder="اسم المسجد، الرمز، العنوان أو الإمام" autocomplete="off">
                    <button id="clearSearch" class="btn btn-outline-secondary <?= empty($queryParams['query'] ?? '') ? 'd-none' : '' ?>" type="button" aria-label="مسح البحث"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                    <button id="searchButton" class="btn btn-primary" type="submit"><span>بحث</span></button>
                </div>
                <button class="btn btn-outline-primary directory-filter-toggle d-md-none" type="button" data-bs-toggle="collapse" data-bs-target="#directoryFilters" aria-controls="directoryFilters" aria-expanded="false"><i class="fas fa-filter me-1" aria-hidden="true"></i>التصفية</button>
            </div>

            <div class="collapse d-md-block directory-filters" id="directoryFilters">
                <div class="row g-2">
                    <div class="col-md-3">
                        <label class="visually-hidden" for="communityFilter">الجماعة</label>
                        <select name="community" id="communityFilter" class="form-select select2">
                            <option value="">كل الجماعات</option>
                            <?php foreach ($communities as $community): ?>
                                <option value="<?= $view->e($community) ?>"<?= isset($queryParams['community']) && $queryParams['community'] === $community ? ' selected' : '' ?>><?= $view->e($community) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="visually-hidden" for="statusFilter">الوضعية</label>
                        <select name="status" id="statusFilter" class="form-select select2">
                            <option value="">كل الوضعيات</option>
                            <?php foreach ($statuses as $status): ?>
                                <option value="<?= $view->e($status) ?>"<?= isset($queryParams['status']) && $queryParams['status'] === $status ? ' selected' : '' ?>><?= $view->e($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="visually-hidden" for="fridayFilter">صلاة الجمعة</label>
                        <select name="friday_prayer" id="fridayFilter" class="form-select select2">
                            <option value="">صلاة الجمعة</option>
                            <?php foreach ($fridayOptions as $option): ?>
                                <option value="<?= $view->e($option) ?>"<?= isset($queryParams['friday_prayer']) && $queryParams['friday_prayer'] === $option ? ' selected' : '' ?>><?= $option === 'نعم' ? 'تقام' : 'لا تقام' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="visually-hidden" for="guideFilter">الإمام المرشد</label>
                        <select name="guide_imam" id="guideFilter" class="form-select select2">
                            <option value="">كل الأئمة المرشدين</option>
                            <?php $currentSelection = $queryParams['guide_imam'] ?? ''; ?>
                            <?php foreach ($guideImams as $imam): ?>
                                <option value="<?= $view->e((string) $imam['id']) ?>"<?= ($currentSelection === (string) $imam['id'] || $currentSelection === $imam['display_name']) ? ' selected' : '' ?>><?= $view->e($imam['display_name']) ?> (<?= number_format((int) $imam['mosque_count']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>

            <?php if ($activeFilters !== []): ?>
                <div class="active-filters" aria-label="التصفيات النشطة">
                    <?php foreach ($activeFilters as $filterKey => $filterText): ?>
                        <?php $resetParams = $queryParams; unset($resetParams[$filterKey], $resetParams['page']); ?>
                        <a class="active-filter" href="mosques.php?<?= $view->e(http_build_query($resetParams)) ?>"><?= $view->e($filterText) ?><i class="fas fa-xmark" aria-hidden="true"></i></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </form>

        <div class="directory-list-toolbar">
            <div class="directory-result-summary">
                <strong><?= number_format((int) $total) ?> نتيجة</strong>
                <?php if ($canDelete): ?>
                    <div class="bulk-selection-bar" id="bulkSelectionBar" hidden>
                        <output id="selectedCountOutput" aria-live="polite"><span id="selectedCount">0</span> محدد</output>
                        <button id="deleteSelected" class="btn btn-sm btn-danger" type="button" disabled><i class="fas fa-trash me-1" aria-hidden="true"></i>حذف</button>
                    </div>
                <?php endif; ?>
            </div>
            <div class="directory-table-tools">
                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" id="columnsDropdown" data-bs-toggle="dropdown" data-bs-auto-close="outside" data-bs-boundary="viewport" data-bs-reference="parent" aria-expanded="false"><i class="fas fa-table-columns me-1" aria-hidden="true"></i>الأعمدة</button>
                    <div class="dropdown-menu dropdown-menu-end directory-column-menu" aria-labelledby="columnsDropdown">
                        <fieldset>
                            <legend class="visually-hidden">الأعمدة الاختيارية</legend>
                            <?php foreach ([
                                'friday' => 'صلاة الجمعة',
                                'construction' => 'سنة البناء',
                                'guide' => 'الإمام المرشد',
                                'location' => 'الموقع',
                            ] as $column => $label): ?>
                                <label class="directory-column-option" for="column-<?= $view->e($column) ?>">
                                    <input class="form-check-input js-column-toggle" type="checkbox" value="<?= $view->e($column) ?>" id="column-<?= $view->e($column) ?>">
                                    <span><?= $view->e($label) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </fieldset>
                    </div>
                </div>
                <button class="btn btn-sm btn-outline-secondary" type="button" id="quickStatsButton" data-bs-toggle="modal" data-bs-target="#quickStatsModal"><i class="fas fa-chart-pie me-1" aria-hidden="true"></i>الإحصائيات</button>
                <a class="btn btn-sm btn-link" href="mosques.php">مسح التصفيات</a>
            </div>
        </div>

        <div class="directory-results">
            <div class="table-responsive mosque-table-wrapper">
                <table class="table align-middle app-table directory-table mb-0">
                    <thead>
                        <tr>
                            <?php if ($canDelete): ?><th scope="col" data-column="selection"><label class="visually-hidden" for="selectAll">تحديد كل المساجد</label><input type="checkbox" id="selectAll" class="form-check-input"></th><?php endif; ?>
                            <th scope="col" data-column="registration"><?= $sortableHeader('رقم التسجيل', 'registration_number') ?></th>
                            <th scope="col" data-column="name"><?= $sortableHeader('اسم المسجد', 'mosque_name') ?></th>
                            <th scope="col" data-column="address">العنوان</th>
                            <th scope="col" data-column="national"><?= $sortableHeader('الرمز الوطني', 'national_code') ?></th>
                            <th scope="col" data-column="friday" class="column-hidden">الجمعة</th>
                            <th scope="col" data-column="status">الوضعية</th>
                            <th scope="col" data-column="construction" class="column-hidden"><?= $sortableHeader('سنة البناء', 'construction_date') ?></th>
                            <th scope="col" data-column="imam">الإمام</th>
                            <th scope="col" data-column="guide" class="column-hidden">الإمام المرشد</th>
                            <th scope="col" data-column="community">الجماعة</th>
                            <th scope="col" data-column="location" class="column-hidden">الموقع</th>
                            <th scope="col" data-column="actions"><?= $canEdit || $canDelete ? 'الإجراءات' : 'عرض' ?></th>
                        </tr>
                    </thead>
                    <tbody aria-live="polite" aria-busy="false">
                        <?php if ($mosques !== []): ?>
                            <?php foreach ($mosques as $row): ?>
                                <?= $view->partial('mosques._row', [
                                    'row' => $row,
                                    'isAdmin' => $isAdmin,
                                    'canEditContent' => $canEdit,
                                    'canDeleteContent' => $canDelete,
                                    'csrfToken' => $csrfToken,
                                ]) ?>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="<?= $tableColumnCount ?>" class="text-center py-5 text-muted"><i class="fas fa-search me-2" aria-hidden="true"></i><?= !empty($queryParams['query']) ? 'لا توجد نتائج مطابقة' : 'لا توجد مساجد مسجلة' ?></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="mosque-mobile-cards">
                <?php foreach ($mosques as $row): ?>
                    <article class="mosque-mobile-card">
                        <div class="mosque-mobile-card__header">
                            <div><h2><?= $view->e($row['mosque_name']) ?></h2><p><?= $view->e($row['address'] ?: 'العنوان غير محدد') ?></p></div>
                            <div class="mosque-mobile-card__badges">
                                <span class="badge bg-light text-dark"><?= $view->e($row['national_code']) ?></span>
                                <span class="status-badge <?= ($row['status'] ?? '') === 'مفتوح' ? 'text-success bg-success-subtle' : ((($row['status'] ?? '') === 'مغلق') ? 'text-danger bg-danger-subtle' : 'text-warning bg-warning-subtle') ?>"><?= $view->e($row['status'] ?: 'غير محدد') ?></span>
                            </div>
                        </div>
                        <dl>
                            <div><dt>الجماعة</dt><dd><?= $view->e($row['community'] ?: '—') ?></dd></div>
                            <div><dt>الإمام</dt><dd><?= $view->e($row['imam_name'] ?: '—') ?></dd></div>
                        </dl>
                        <div class="record-actions">
                            <button type="button" class="btn btn-sm btn-outline-primary view-mosque-btn" data-bs-toggle="modal" data-bs-target="#mosqueDetailsModal" data-mosque-id="<?= $view->e($row['registration_number']) ?>"><i class="fas fa-eye me-1" aria-hidden="true"></i>عرض</button>
                            <?php if ($canEdit): ?><a class="btn btn-sm btn-primary" href="edit_mosque.php?id=<?= $view->e($row['registration_number']) ?>"><i class="fas fa-pen me-1" aria-hidden="true"></i>تعديل</a><?php endif; ?>
                            <?php if ($canDelete): ?>
                                <form method="POST" action="delete_mosque.php" class="js-confirm-submit" data-confirm="هل أنت متأكد من حذف هذا المسجد؟">
                                    <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>"><input type="hidden" name="id" value="<?= $view->e($row['registration_number']) ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash me-1" aria-hidden="true"></i>حذف</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

            <?php if ($pages > 1): ?>
                <?= $view->partial('components.pagination', ['currentPage' => $page, 'totalPages' => $pages, 'baseUrl' => 'mosques.php', 'queryParams' => $queryParams]) ?>
            <?php endif; ?>
        </div>
    </section>
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>إغلاق
                </button>
                <button type="button" class="btn btn-primary js-print-mosque-details">
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
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>إغلاق
                </button>
            </div>
        </div>
    </div>
</div>
