<?php
/**
 * Quran list pagination (legacy quran_mosques.php renderPagination variant:
 * disabled prev/next at bounds, empty params filtered out).
 * Expects: $currentPage, $totalPages, $queryParams
 */

$filteredParams = array_filter($queryParams, function ($key) {
    return $key !== 'page';
}, ARRAY_FILTER_USE_KEY);

$queryString = '';
if (!empty($filteredParams)) {
    $queryString = '&' . http_build_query($filteredParams);
}
?>
<nav aria-label="التنقل بين صفحات النتائج" class="mt-4 animate__animated animate__fadeIn">
    <ul class="pagination justify-content-center" style="z-index: 1; position: relative;">
<?php if ($currentPage > 1): ?>
        <li class="page-item">
            <a class="page-link" href="quran_mosques.php?page=<?= $currentPage - 1 ?><?= $view->e($queryString) ?>" aria-label="الصفحة السابقة" style="text-decoration: none;">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
<?php else: ?>
        <li class="page-item disabled">
            <span class="page-link" aria-hidden="true">&laquo;</span>
        </li>
<?php endif; ?>
<?php
$startPage = max(1, $currentPage - 2);
$endPage = min($totalPages, $currentPage + 2);

if ($startPage > 1): ?>
        <li class="page-item"><a class="page-link" href="quran_mosques.php?page=1<?= $view->e($queryString) ?>" style="text-decoration: none;">1</a></li>
    <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
<?php endif; ?>
<?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <li class="page-item <?= $currentPage == $i ? 'active' : '' ?>">
            <a class="page-link" href="quran_mosques.php?page=<?= $i ?><?= $view->e($queryString) ?>" style="text-decoration: none;"><?= $i ?></a>
        </li>
<?php endfor; ?>
<?php if ($endPage < $totalPages): ?>
    <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="quran_mosques.php?page=<?= $totalPages ?><?= $view->e($queryString) ?>" style="text-decoration: none;"><?= $totalPages ?></a></li>
<?php endif; ?>
<?php if ($currentPage < $totalPages): ?>
        <li class="page-item">
            <a class="page-link" href="quran_mosques.php?page=<?= $currentPage + 1 ?><?= $view->e($queryString) ?>" aria-label="الصفحة التالية" style="text-decoration: none;">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
<?php else: ?>
        <li class="page-item disabled">
            <span class="page-link" aria-hidden="true">&raquo;</span>
        </li>
<?php endif; ?>
    </ul>
</nav>
