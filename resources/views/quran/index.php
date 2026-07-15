<?php
/**
 * Quran programs list.
 * Expects: $programs, $total, $page, $pages, $schoolCount, $accomCount,
 *          $centerCount, $studentsCount, $communities, $queryParams,
 *          $isAdmin, $csrfToken
 */

$buildQueryString = function (array $newParams = []) use ($queryParams): string {
    $params = array_merge($queryParams, $newParams);
    $params = array_filter($params, fn ($value) => $value !== '' && $value !== null);

    return http_build_query($params);
};

$sortableHeader = function (string $title, string $sortKey) use ($queryParams, $buildQueryString): string {
    $currentSort = $queryParams['sort'] ?? '';
    $currentOrder = $queryParams['order'] ?? '';
    $newOrder = ($currentSort === $sortKey && $currentOrder === 'asc') ? 'desc' : 'asc';
    $iconDirection = ($currentSort === $sortKey && $currentOrder === 'asc') ? 'up' : 'down';

    return '<a href="quran_mosques.php?' . $buildQueryString(['sort' => $sortKey, 'order' => $newOrder]) . '" class="text-decoration-none">'
        . $title . '<i class="fas fa-chevron-' . $iconDirection . ' ms-1" aria-hidden="true"></i></a>';
};

$quranActions = '<img class="quran-header-illustration" src="assets/images/institutional/quran-book-3d.svg" width="56" height="56" alt="" aria-hidden="true" loading="eager">'
    . '<span class="badge bg-light text-dark align-self-center">' . number_format($total) . ' سجل</span>';
if ($isAdmin) {
    $quranActions .= '<a class="btn btn-primary align-self-center" href="add_quran_mosque.php"><i class="fas fa-plus me-2" aria-hidden="true"></i>إضافة مسجد</a>';
}
?>

<div class="directory-page-header">
    <?= $view->partial('components.page_header', [
        'title' => 'قائمة مساجد التحفيظ',
        'subtitle' => 'إجمالي مساجد التحفيظ: ' . number_format($total),
        'actionsHtml' => $quranActions,
    ]) ?>
</div>

<section class="card border-0 shadow-sm mb-4" aria-label="ملخص مساجد التحفيظ">
    <div class="card-body py-3">
        <dl class="row g-3 mb-0 text-center">
            <div class="col-6 col-lg-3"><dt class="small text-muted">الكتاتيب</dt><dd class="h5 mb-0"><?= number_format($schoolCount) ?></dd></div>
            <div class="col-6 col-lg-3"><dt class="small text-muted">الإقامة</dt><dd class="h5 mb-0"><?= number_format($accomCount) ?></dd></div>
            <div class="col-6 col-lg-3"><dt class="small text-muted">مراكز التحفيظ</dt><dd class="h5 mb-0"><?= number_format($centerCount) ?></dd></div>
            <div class="col-6 col-lg-3"><dt class="small text-muted">الطلبة</dt><dd class="h5 mb-0"><?= number_format($studentsCount) ?></dd></div>
        </dl>
    </div>
</section>

<section class="card border-0 shadow-sm">
    <div class="card-body">
        <form method="get" action="quran_mosques.php" id="searchForm" class="mb-4">
            <input type="hidden" name="page" value="1">
            <div class="row g-2 align-items-center" id="searchCollapse">
                <div class="col-12 col-lg-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fas fa-search text-muted" aria-hidden="true"></i></span>
                        <input type="search" name="query" id="liveSearch" class="form-control border-start-0"
                            placeholder="اسم المسجد أو الرمز الوطني"
                            aria-label="البحث في مساجد التحفيظ"
                            value="<?= $view->e($queryParams['query'] ?? '') ?>">
                        <button id="clearSearch" class="btn btn-outline-secondary <?= empty($queryParams['query'] ?? '') ? 'd-none' : '' ?>" type="button" aria-label="مسح البحث" title="مسح">
                            <i class="fas fa-times" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="quranCommunityFilter">الجماعة</label>
                    <select name="community" id="quranCommunityFilter" class="form-select select2">
                        <option value="">الجماعة</option>
                        <?php foreach ($communities as $community): ?>
                            <option value="<?= $view->e($community) ?>"<?= isset($queryParams['community']) && $queryParams['community'] === $community ? ' selected' : '' ?>><?= $view->e($community) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-6 col-lg-2">
                    <label class="visually-hidden" for="quranProgramFilter">نوع البرنامج</label>
                    <select name="has_quran_school" id="quranProgramFilter" class="form-select select2">
                        <option value="">نوع البرنامج</option>
                        <option value="نعم" <?= isset($queryParams['has_quran_school']) && $queryParams['has_quran_school'] === 'نعم' ? 'selected' : '' ?>>كتاب قرآني</option>
                        <option value="لا" <?= isset($queryParams['has_quran_school']) && $queryParams['has_quran_school'] === 'لا' ? 'selected' : '' ?>>بدون برنامج</option>
                        <option value="مركز تحفيظ" <?= isset($queryParams['has_quran_school']) && $queryParams['has_quran_school'] === 'مركز تحفيظ' ? 'selected' : '' ?>>مركز تحفيظ</option>
                    </select>
                </div>
                <div class="col-12 col-lg-2 d-grid">
                    <button id="searchButton" class="btn btn-primary" type="submit"><i class="fas fa-search me-2" aria-hidden="true"></i>بحث</button>
                </div>
            </div>
        </form>

        <div class="d-flex justify-content-between align-items-center gap-2 mb-3" id="quranBulkSelectionBar" aria-label="إجراءات التحديد" hidden>
            <?php if ($isAdmin): ?>
                <button id="deleteSelected" class="btn btn-sm btn-outline-danger" disabled>
                    <i class="fas fa-trash-alt me-2" aria-hidden="true"></i>حذف
                </button>
            <?php endif; ?>
            <button id="selectedCountBtn" class="btn btn-sm btn-outline-secondary ms-auto" disabled>
                <span id="selectedCount">0</span> محدد
            </button>
        </div>

        <div class="table-responsive quran-table-wrapper">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th width="50"><input type="checkbox" id="selectAll" class="form-check-input" aria-label="تحديد كل السجلات"></th>
                        <th width="80"><?= $sortableHeader('ر.ت.ع', 'id') ?></th>
                        <th><?= $sortableHeader('اسم المسجد', 'mosque_name') ?></th>
                        <th width="130"><?= $sortableHeader('الرمز الوطني', 'national_code') ?></th>
                        <th>المسؤول</th>
                        <th>البرنامج</th>
                        <th>الإقامة</th>
                        <th>الجلسات</th>
                        <th>الطلبة</th>
                        <th>الجماعة</th>
                        <th width="120"><?= $isAdmin ? 'الإجراءات' : 'معاينة' ?></th>
                    </tr>
                </thead>
                <tbody aria-live="polite" aria-busy="false">
                    <?php if (count($programs) > 0): ?>
                        <?php foreach ($programs as $row): ?>
                            <?= $view->partial('quran._row', [
                                'row' => $row,
                                'isAdmin' => $isAdmin,
                                'csrfToken' => $csrfToken,
                            ]) ?>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="11" class="text-center py-5 text-muted">
                                <i class="fas fa-search me-2" aria-hidden="true"></i><?= !empty($queryParams['query']) ? 'لا توجد نتائج مطابقة' : 'لا توجد مساجد مسجلة' ?>
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

        <?php if ($pages > 1): ?>
            <?= $view->partial('quran._pagination', [
                'currentPage' => $page,
                'totalPages' => $pages,
                'queryParams' => $queryParams,
            ]) ?>
        <?php endif; ?>
    </div>
</section>

<div class="modal fade" id="quranMosqueDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header bg-primary text-white">
                <h2 class="modal-title h5"><i class="fas fa-book-quran me-2" aria-hidden="true"></i>تفاصيل مسجد التحفيظ</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="إغلاق"></button>
            </div>
            <div class="modal-body" id="modal-body-container">
                <div id="modal-body-content"></div>
            </div>
            <div class="modal-footer bg-light">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><i class="fas fa-times me-2" aria-hidden="true"></i>إغلاق</button>
                <button type="button" class="btn btn-primary js-print-quran-details"><i class="fas fa-print me-2" aria-hidden="true"></i>طباعة</button>
            </div>
        </div>
    </div>
</div>

<script type="application/json" id="quranPageData" nonce="<?= $view->e($cspNonce ?? '') ?>"><?= json_encode(['csrfToken' => $csrfToken], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
