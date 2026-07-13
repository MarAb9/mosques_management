<?php
/**
 * Pagination control (same markup as the legacy renderPagination()).
 * Expects: $currentPage, $totalPages, $baseUrl, $queryParams
 * Optional: $pageParamNames (filter params to carry through links)
 */

$pageParamNames = $pageParamNames
    ?? ['query', 'national_code', 'imam_registration', 'community', 'status', 'friday_prayer', 'guide_imam', 'sort', 'order'];

$queryString = '';
foreach ($pageParamNames as $param) {
    if (isset($queryParams[$param])) {
        $queryString .= '&' . $param . '=' . urlencode((string) $queryParams[$param]);
    }
}
?>
<nav aria-label="Page navigation" class="mt-4 animate__animated animate__fadeIn">
    <ul class="pagination justify-content-center">
<?php if ($currentPage > 1): ?>
        <li class="page-item">
            <a class="page-link" href="<?= $baseUrl ?>?page=<?= $currentPage - 1 ?><?= $view->e($queryString) ?>" aria-label="Previous">
                <span aria-hidden="true">&laquo;</span>
            </a>
        </li>
<?php endif; ?>
<?php
$startPage = max(1, $currentPage - 2);
$endPage = min($totalPages, $currentPage + 2);

if ($startPage > 1): ?>
        <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>?page=1<?= $view->e($queryString) ?>">1</a></li>
    <?php if ($startPage > 2): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
<?php endif; ?>
<?php for ($i = $startPage; $i <= $endPage; $i++): ?>
        <li class="page-item <?= $currentPage == $i ? 'active' : '' ?>">
            <a class="page-link" href="<?= $baseUrl ?>?page=<?= $i ?><?= $view->e($queryString) ?>"><?= $i ?></a>
        </li>
<?php endfor; ?>
<?php if ($endPage < $totalPages): ?>
    <?php if ($endPage < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">...</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?= $baseUrl ?>?page=<?= $totalPages ?><?= $view->e($queryString) ?>"><?= $totalPages ?></a></li>
<?php endif; ?>
<?php if ($currentPage < $totalPages): ?>
        <li class="page-item">
            <a class="page-link" href="<?= $baseUrl ?>?page=<?= $currentPage + 1 ?><?= $view->e($queryString) ?>" aria-label="Next">
                <span aria-hidden="true">&raquo;</span>
            </a>
        </li>
<?php endif; ?>
    </ul>
</nav>
