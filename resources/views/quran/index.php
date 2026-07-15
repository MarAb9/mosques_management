<?php
/**
 * Quran programs list (legacy quran_mosques.php markup, verbatim).
 * Expects: $programs, $total, $page, $pages, $schoolCount, $accomCount,
 *          $centerCount, $studentsCount, $communities, $queryParams,
 *          $isAdmin, $csrfToken
 */

$buildQueryString = function (array $newParams = []) use ($queryParams): string {
    $params = array_merge($queryParams, $newParams);

    // Remove empty parameters
    $params = array_filter($params, function ($value) {
        return $value !== '' && $value !== null;
    });

    return http_build_query($params);
};

$sortableHeader = function (string $title, string $sortKey) use ($queryParams, $buildQueryString): string {
    $currentSort = $queryParams['sort'] ?? '';
    $currentOrder = $queryParams['order'] ?? '';
    $newOrder = ($currentSort == $sortKey && $currentOrder == 'asc') ? 'desc' : 'asc';
    $iconDirection = ($currentSort == $sortKey && $currentOrder == 'asc') ? 'up' : 'down';

    return '
        <a href="quran_mosques.php?' . $buildQueryString(['sort' => $sortKey, 'order' => $newOrder]) . '" class="text-decoration-none">
            <i class="fas fa-chevron-' . $iconDirection . ' ms-1"></i>
        </a>' . $title;
};
?>

<?php $quranActions = $isAdmin ? '<a class="btn btn-primary" href="add_quran_mosque.php"><i class="fas fa-plus me-2" aria-hidden="true"></i>إضافة برنامج</a>' : ''; ?>
<div class="directory-page-header">
<?= $view->partial('components.page_header', ['kicker' => 'برامج القرآن الكريم', 'title' => 'مساجد التحفيظ', 'subtitle' => 'متابعة البرامج والمسؤولين والطلبة والجلسات ضمن الهوية الإدارية نفسها.', 'icon' => 'fa-book-quran', 'actionsHtml' => $quranActions]) ?>
</div>

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
                    <h2 class="mb-0"><?= number_format($schoolCount) ?></h2>
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
                    <h2 class="mb-0"><?= number_format($accomCount) ?></h2>
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
                <h2 class="mb-0"><?= number_format($centerCount) ?></h2>
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
                    <h2 class="mb-0"><?= number_format($studentsCount) ?></h2>
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
                <?php if ($isAdmin) : ?>
                <div class="d-flex gap-2">
                    <a href="add_quran_mosque.php" class="btn btn-primary rounded-pill">
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
                                                    value="<?= htmlspecialchars($queryParams['query'] ?? '') ?>">
                                                <button id="clearSearch" class="btn btn-outline-secondary border-start-0 <?= empty($queryParams['query'] ?? '') ? 'd-none' : '' ?>" type="button" atlas-clear-search>
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
                                                <?php foreach ($communities as $community): ?>
                                                <option value="<?= htmlspecialchars($community) ?>"<?= isset($queryParams['community']) && $queryParams['community'] == $community ? ' selected' : '' ?>><?= htmlspecialchars($community) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-2">
                                            <select name="has_quran_school" class="form-select select2" onchange="this.form.submit()">
                                                <option value="">كتاب قرآني</option>
                                                <option value="نعم" <?= isset($queryParams['has_quran_school']) && $queryParams['has_quran_school'] == 'نعم' ? 'selected' : '' ?>>نعم</option>
                                                <option value="لا" <?= isset($queryParams['has_quran_school']) && $queryParams['has_quran_school'] == 'لا' ? 'selected' : '' ?>>لا</option>
                                                <option value="مركز تحفيظ" <?= isset($queryParams['has_quran_school']) && $queryParams['has_quran_school'] == 'مركز تحفيظ' ? 'selected' : '' ?>>مركز تحفيظ</option>
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
                        <?php if ($isAdmin) {?>
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

                <div class="table-responsive animate__animated animate__fadeIn quran-table-wrapper">
                    <table class="table table-hover align-middle">
                        <thead class="table-light">
                            <tr>
                                <th width="50"><input type="checkbox" id="selectAll" class="form-check-input"></th>
                                <th width="80"><?= $sortableHeader('ر.ت.ع', 'id') ?></th>
                                <th><?= $sortableHeader('اسم المسجد', 'mosque_name') ?></th>
                                <th width="130"><?= $sortableHeader('الرمز الوطني', 'national_code') ?></th>
                                <th>المسؤول(ة)</th>
                                <th>كتاب قرآني</th>
                                <th>إقامة</th>
                                <th>عدد الجلسات الأسبوعية</th>
                                <th>عدد الطلاب</th>
                                <th>الجماعة</th>
                                <th width="120"><?= $isAdmin ? 'الإجراءات' : 'معاينة' ?></th>
                            </tr>
                        </thead>
                        <tbody aria-live="polite" aria-busy="false">
                            <?php if (count($programs) > 0): ?>
                                <?php $animationDelay = 0; ?>
                                <?php foreach ($programs as $row): ?>
                                    <?php $animationDelay += 0.05; ?>
                                    <?= $view->partial('quran._row', [
                                        'row' => $row,
                                        'animationDelay' => $animationDelay,
                                        'isAdmin' => $isAdmin,
                                        'csrfToken' => $csrfToken,
                                    ]) ?>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr class="animate__animated animate__fadeInUp">
                                    <td colspan="11" class="text-center py-4 text-muted">
                                        <i class="fas fa-search me-2"></i><?= isset($queryParams['query']) ? 'لا توجد نتائج مطابقة لبحثك' : 'لا توجد مساجد تحفيظ مسجلة' ?>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>


                <div class="quran-mobile-cards">
                    <?php foreach ($programs as $row): ?>
                        <?= $view->partial('quran._card', ['row' => $row, 'isAdmin' => $isAdmin, 'csrfToken' => $csrfToken]) ?>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pages > 1): ?>
                <div class="mt-4 animate__animated animate__fadeIn">
                    <?= $view->partial('quran._pagination', [
                        'currentPage' => $page,
                        'totalPages' => $pages,
                        'queryParams' => $queryParams,
                    ]) ?>
                </div>
                <?php endif; ?>

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
                <button type="button" class="btn btn-primary rounded-pill js-print-quran-details">
                    <i class="fas fa-print me-2"></i>طباعة التفاصيل
                </button>
            </div>
        </div>
    </div>
</div>


<script type="application/json" id="quranPageData" nonce="<?= $view->e($cspNonce ?? '') ?>"><?= json_encode(['csrfToken' => $csrfToken], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
