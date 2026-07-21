<?php
$requestPath = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
$requestPage = basename(rtrim($requestPath, '/'));
$routeAliases = [
    '' => 'index.php',
    'mosques' => 'mosques.php',
    'add_mosque' => 'add_mosque.php',
    'edit_mosque' => 'edit_mosque.php',
    'quran_mosques' => 'quran_mosques.php',
    'add_quran_mosque' => 'add_quran_mosque.php',
    'edit_quran_mosque' => 'edit_quran_mosque.php',
    'mosque_maps' => 'mosque_maps.php',
    'import_export' => 'import_export.php',
    'data_quality' => 'data_quality.php',
];
$currentPage = $routeAliases[$requestPage] ?? ($requestPage !== '' ? $requestPage : basename((string) ($_SERVER['SCRIPT_NAME'] ?? 'index.php')));
$pageConfig = [
    'index.php' => ['title' => 'لوحة التحكم', 'section' => 'الرئيسية', 'icon' => 'fa-gauge-high'],
    'mosques.php' => ['title' => 'دليل المساجد', 'section' => 'إدارة المساجد', 'icon' => 'fa-mosque'],
    'add_mosque.php' => ['title' => 'إضافة مسجد', 'section' => 'إدارة المساجد', 'icon' => 'fa-circle-plus'],
    'edit_mosque.php' => ['title' => 'تعديل مسجد', 'section' => 'إدارة المساجد', 'icon' => 'fa-pen-to-square'],
    'quran_mosques.php' => ['title' => 'برامج القرآن الكريم', 'section' => 'التحفيظ', 'icon' => 'fa-book-quran'],
    'add_quran_mosque.php' => ['title' => 'إضافة برنامج قرآني', 'section' => 'التحفيظ', 'icon' => 'fa-circle-plus'],
    'edit_quran_mosque.php' => ['title' => 'تعديل برنامج قرآني', 'section' => 'التحفيظ', 'icon' => 'fa-pen-to-square'],
    'mosque_maps.php' => ['title' => 'الخريطة التشغيلية', 'section' => 'التغطية الجغرافية', 'icon' => 'fa-map-location-dot'],
    'import_export.php' => ['title' => 'الاستيراد والتصدير', 'section' => 'إدارة البيانات', 'icon' => 'fa-arrow-right-arrow-left'],
    'data_quality.php' => ['title' => 'جودة البيانات', 'section' => 'الحوكمة', 'icon' => 'fa-clipboard-check'],
];
$page = $pageConfig[$currentPage] ?? ['title' => 'نظام إدارة مساجد إقليم بركان', 'section' => 'الإدارة', 'icon' => 'fa-mosque'];
$roleLabel = ($currentRole ?? '') === 'admin' ? 'مسؤول النظام' : 'مشاهدة واستعلام';
$select2Pages = ['mosques.php', 'quran_mosques.php', 'add_quran_mosque.php', 'edit_quran_mosque.php'];
$pageStyleAsset = match ($currentPage) {
    'mosque_maps.php' => 'assets/dist/maps.min.css',
    'add_mosque.php', 'edit_mosque.php' => 'assets/dist/mosque-form-map.min.css',
    default => null,
};
$navActive = static fn (array $pages): string => in_array($currentPage, $pages, true) ? ' is-active' : '';
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#063b30">
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <meta name="csrf-token" content="<?= $view->e($csrfToken) ?>">
    <meta name="csp-nonce" content="<?= $view->e($cspNonce ?? '') ?>">
    <title><?= $view->e($page['title']) ?> — نظام إدارة مساجد إقليم بركان</title>
    <link rel="preload" href="assets/vendor/bootstrap/css/bootstrap.rtl.min.css" as="style">
    <link rel="preload" href="assets/dist/app.min.css" as="style">
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <?php if (in_array($currentPage, $select2Pages, true)): ?><link rel="stylesheet" href="assets/vendor/select2/css/select2.min.css"><?php endif; ?>
    <?php if ($pageStyleAsset !== null): ?><link rel="stylesheet" href="<?= $view->e($pageStyleAsset) ?>"><?php endif; ?>
    <link rel="stylesheet" href="assets/vendor/sweetalert2/sweetalert2.min.css">
    <link rel="stylesheet" href="assets/dist/app.min.css">
</head>
<body data-page="<?= $view->e(pathinfo($currentPage, PATHINFO_FILENAME)) ?>" data-is-admin="<?= $isAdmin ? 'true' : 'false' ?>" data-can-edit="<?= ($canEditContent ?? $isAdmin) ? 'true' : 'false' ?>" data-can-delete="<?= ($canDeleteContent ?? $isAdmin) ? 'true' : 'false' ?>">
    <a class="skip-link" href="#main-content">الانتقال إلى المحتوى الرئيسي</a>
    <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

    <aside class="app-sidebar" id="appSidebar" aria-label="التنقل الرئيسي">
        <div class="sidebar-brand">
            <span class="sidebar-brand__mark" aria-hidden="true"><img src="assets/images/logo.png" width="34" height="34" alt=""></span>
            <span class="sidebar-brand__text"><span class="sidebar-brand__title">نظام إدارة مساجد إقليم بركان</span><span class="sidebar-brand__subtitle">المجلس العلمي المحلي بإقليم بركان</span></span>
            <button class="sidebar-collapse d-none d-lg-grid" type="button" id="sidebarCollapse" aria-label="طي الشريط الجانبي" aria-expanded="true" title="طي الشريط الجانبي"><i class="fas fa-angles-right" aria-hidden="true"></i></button>
            <button class="topbar-icon d-lg-none ms-auto" type="button" id="mobileNavClose" aria-label="إغلاق القائمة"><i class="fas fa-xmark" aria-hidden="true"></i></button>
        </div>

        <div class="sidebar-scroll">
            <nav>
                <section class="nav-group" aria-labelledby="nav-overview">
                    <span class="nav-group__label" id="nav-overview">الرئيسية</span>
                    <ul class="sidebar-nav">
                        <li><a class="sidebar-nav__link<?= $navActive(['index.php']) ?>" href="index.php" title="لوحة التحكم"><i class="fas fa-gauge-high" aria-hidden="true"></i><span>لوحة التحكم</span></a></li>
                        <li><a class="sidebar-nav__link<?= $navActive(['mosque_maps.php']) ?>" href="mosque_maps.php" title="الخريطة"><i class="fas fa-map-location-dot" aria-hidden="true"></i><span>الخريطة</span></a></li>
                    </ul>
                </section>
                <section class="nav-group" aria-labelledby="nav-records">
                    <span class="nav-group__label" id="nav-records">إدارة المساجد</span>
                    <ul class="sidebar-nav">
                        <li><a class="sidebar-nav__link<?= $navActive(['mosques.php', 'add_mosque.php', 'edit_mosque.php']) ?>" href="mosques.php" title="دليل المساجد"><i class="fas fa-mosque" aria-hidden="true"></i><span>دليل المساجد</span></a></li>
                        <li><a class="sidebar-nav__link<?= $navActive(['quran_mosques.php', 'add_quran_mosque.php', 'edit_quran_mosque.php']) ?>" href="quran_mosques.php" title="برامج القرآن"><i class="fas fa-book-quran" aria-hidden="true"></i><span>برامج القرآن</span></a></li>
                        <?php if ($canImportData ?? $isAdmin): ?><li><a class="sidebar-nav__link<?= $navActive(['import_export.php']) ?>" href="import_export.php" title="الاستيراد والتصدير"><i class="fas fa-arrow-right-arrow-left" aria-hidden="true"></i><span>الاستيراد والتصدير</span></a></li><?php endif; ?>
                    </ul>
                </section>
                <?php if ($isAdmin): ?>
                <section class="nav-group" aria-labelledby="nav-governance">
                    <span class="nav-group__label" id="nav-governance">الإدارة</span>
                    <ul class="sidebar-nav">
                        <li><a class="sidebar-nav__link<?= $navActive(['data_quality.php']) ?>" href="data_quality.php" title="جودة البيانات"><i class="fas fa-clipboard-check" aria-hidden="true"></i><span>جودة البيانات</span></a></li>
                        <li><a class="sidebar-nav__link" href="backup.php" title="نسخة احتياطية"><i class="fas fa-shield-halved" aria-hidden="true"></i><span>نسخة احتياطية</span></a></li>
                    </ul>
                </section>
                <?php endif; ?>
            </nav>
        </div>

        <div class="sidebar-account">
            <div class="account-card">
                <span class="account-avatar" aria-hidden="true"><i class="fas fa-user-shield"></i></span>
                <span class="account-meta"><strong><?= $view->e($roleLabel) ?></strong></span>
                <form method="POST" action="logout.php" id="logoutForm">
                    <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                    <button class="logout-btn border-0" type="button" id="logoutButton" aria-label="تسجيل الخروج"><i class="fas fa-arrow-right-from-bracket" aria-hidden="true"></i></button>
                </form>
            </div>
        </div>
    </aside>

    <div class="app-main">
        <header class="app-topbar">
            <div class="d-flex align-items-center gap-3 min-w-0">
                <button class="mobile-nav-toggle" type="button" id="mobileNavToggle" aria-label="فتح القائمة" aria-controls="appSidebar" aria-expanded="false"><i class="fas fa-bars" aria-hidden="true"></i></button>
                <div class="topbar-title"><strong><?= $view->e($page['title']) ?></strong></div>
            </div>
            <div class="topbar-actions">
                <button class="topbar-icon" type="button" id="globalSearchToggle" aria-label="البحث في المساجد" aria-controls="globalSearchDialog" aria-haspopup="dialog" aria-expanded="false"><i class="fas fa-magnifying-glass" aria-hidden="true"></i></button>
                <div class="dropdown">
                    <button class="account-control dropdown-toggle" type="button" id="accountMenu" data-bs-toggle="dropdown" aria-expanded="false" aria-label="قائمة الحساب"><span class="account-control__avatar" aria-hidden="true"><i class="fas fa-user"></i></span><span><?= $view->e($roleLabel) ?></span></button>
                    <div class="dropdown-menu dropdown-menu-end account-menu" aria-labelledby="accountMenu">
                        <div class="account-menu__label"><small>الحساب الحالي</small><strong><?= $view->e($roleLabel) ?></strong></div>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="logout.php" id="topbarLogoutForm">
                            <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                            <button class="dropdown-item text-danger" type="button" id="topbarLogoutButton"><i class="fas fa-arrow-right-from-bracket me-2" aria-hidden="true"></i>تسجيل الخروج</button>
                        </form>
                    </div>
                </div>
            </div>
        </header>
        <dialog class="global-search" id="globalSearchDialog" aria-labelledby="globalSearchTitle">
            <form class="global-search__surface" id="globalSearchForm" action="mosques.php" method="GET">
                <div class="global-search__header">
                    <div>
                        <h2 id="globalSearchTitle">البحث في المساجد</h2>
                        <p>ابحث بالاسم أو الرمز أو رقم التسجيل</p>
                    </div>
                    <button class="topbar-icon" type="button" id="globalSearchClose" aria-label="إغلاق البحث"><i class="fas fa-xmark" aria-hidden="true"></i></button>
                </div>
                <label class="visually-hidden" for="globalSearchInput">عبارة البحث</label>
                <div class="global-search__field">
                    <i class="fas fa-magnifying-glass" aria-hidden="true"></i>
                    <input class="form-control" id="globalSearchInput" name="query" type="search" autocomplete="off" placeholder="ابحث في سجلات المساجد" aria-describedby="globalSearchStatus">
                    <button class="btn btn-primary" type="submit">عرض النتائج</button>
                </div>
                <p class="global-search__status" id="globalSearchStatus" role="status" aria-live="polite">اكتب حرفين على الأقل لعرض النتائج.</p>
                <ul class="global-search__results" id="globalSearchResults" aria-label="نتائج البحث المقترحة"></ul>
            </form>
        </dialog>
        <main class="app-content" id="main-content" tabindex="-1"><?= $content ?></main>
        <footer class="footer-section"><span>نظام إدارة مساجد إقليم بركان</span><span aria-hidden="true"> · </span><span>&copy; <?= date('Y') ?> المجلس العلمي المحلي بإقليم بركان</span></footer>
    </div>

    <?php if (in_array($currentPage, $select2Pages, true)): ?><script src="assets/vendor/jquery/jquery-3.6.0.min.js"></script><?php endif; ?>
    <script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
    <?php if (in_array($currentPage, $select2Pages, true)): ?><script src="assets/vendor/select2/js/select2.min.js"></script><?php endif; ?>
    <?php if ($currentPage === 'mosques.php'): ?><script src="assets/vendor/chartjs/chart.min.js"></script><?php endif; ?>
    <script src="assets/vendor/sweetalert2/sweetalert2.min.js"></script>
    <script src="assets/dist/app.min.js"></script>
    <script src="assets/dist/backup-confirm.min.js"></script>
    <?php if ($currentPage === 'index.php'): ?><script src="assets/dist/dashboard.min.js"></script><?php endif; ?>
    <?php if ($currentPage === 'mosques.php'): ?><script src="assets/js/mosque.js"></script><?php endif; ?>
    <?php if (in_array($currentPage, ['quran_mosques.php', 'add_quran_mosque.php', 'edit_quran_mosque.php'], true)): ?><script src="assets/dist/quran.min.js"></script><?php endif; ?>
    <?php if ($currentPage === 'import_export.php'): ?><script src="assets/dist/import-export.min.js"></script><?php endif; ?>
</body>
</html>
