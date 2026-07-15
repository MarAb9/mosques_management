<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة مساجد إقليم بركان</title>
    <meta name="csrf-token" content="<?= $view->e($csrfToken) ?>">
    <meta name="csp-nonce" content="<?= $view->e($cspNonce ?? "") ?>">

    <!-- Preload important resources -->
    <link rel="preload" href="assets/vendor/bootstrap/css/bootstrap.rtl.min.css" as="style">
    <link rel="preload" href="assets/vendor/fontawesome/css/all.min.css" as="style">
    <link rel="preload" href="assets/css/style.css" as="style">

    <!-- CSS Links -->
    <link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="assets/vendor/fontawesome/css/all.min.css">
    <link rel="stylesheet" href="assets/vendor/animate/animate.min.css"/>
    <link rel="stylesheet" href="assets/vendor/hover/hover-min.css">

    <!-- Select2 CSS -->
    <link href="assets/vendor/select2/css/select2.min.css" rel="stylesheet" />
    <link href="assets/vendor/sweetalert2/sweetalert2.min.css" rel="stylesheet" />
    <script src="assets/vendor/sweetalert2/sweetalert2.min.js"></script>

    <!-- Your custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/mosque.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/footer.css">

    <!-- Fonts are served from system fallbacks to avoid third-party requests. -->
    <script src="assets/vendor/jquery/jquery-3.6.0.min.js"></script>
</head>
<body>
    <a class="skip-link" href="#main-content">الانتقال إلى المحتوى الرئيسي</a>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand animate__animated animate__fadeIn" href="index.php">
                <i class="fas fa-mosque"></i>
                نظام مساجد بركان
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-expanded="false" aria-label="فتح أو إغلاق قائمة التنقل">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link hvr-underline-from-center" href="index.php">
                            <i class="fas fa-home"></i>
                            الرئيسية
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link hvr-underline-from-center" href="mosques.php">
                            <i class="fas fa-list"></i>
                            قائمة المساجد
                        </a>
                    </li>
                    <?php if ($canEditContent ?? $isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link hvr-underline-from-center" href="add_mosque.php">
                            <i class="fas fa-plus-circle"></i>
                            إضافة مسجد
                        </a>
                    </li>
                     <?php endif ?>
                    <?php if ($canImportData ?? $isAdmin): ?>
                    <li class="nav-item">
                        <a class="nav-link hvr-underline-from-center" href="import_export.php">
                            <i class="fas fa-exchange-alt" aria-hidden="true"></i>
                            استيراد/تصدير
                        </a>
                    </li>
                    <?php endif; ?>
                    <?php if ($canViewAudit ?? false): ?>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle hvr-underline-from-center" href="#" id="adminOpsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-shield-alt"></i>
                            المتابعة
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="adminOpsDropdown">
                            <li><a class="dropdown-item" href="data_quality.php"><i class="fas fa-clipboard-check me-2"></i>جودة البيانات</a></li>
                            <li><a class="dropdown-item" href="audit.php"><i class="fas fa-history me-2"></i>سجل التدقيق</a></li>
                            <li><a class="dropdown-item" href="trash.php"><i class="fas fa-trash-restore me-2"></i>سلة المحذوفات</a></li>
                            <li><a class="dropdown-item" href="backup.php"><i class="fas fa-download me-2"></i>نسخة احتياطية</a></li>
                        </ul>
                    </li>
                    <?php endif; ?>
                    <li class="nav-item">
                        <a href="mosque_maps.php" class="btn btn-success rounded-pill">
                            <i class="fas fa-map-marked-alt me-2"></i>خريطة المساجد
                        </a>
                    </li>
                </ul>
                <div class="user-section">
                    <form method="POST" action="logout.php" id="logoutForm">
                        <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                        <button class="logout-btn border-0" type="button" id="logoutButton">
                            <i class="fas fa-sign-out-alt" aria-hidden="true"></i>
                            <span>تسجيل الخروج</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </nav>
    <main class="container mt-4" id="main-content" tabindex="-1">

    <script nonce="<?= $view->e($cspNonce ?? '') ?>">
        // Add scroll effect to navbar
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Add active class to current page link
        document.addEventListener('DOMContentLoaded', function() {
            const currentPage = location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.nav-link');

            navLinks.forEach(link => {
                if (link.getAttribute('href') === currentPage) {
                    link.classList.add('active');
                }

                // Add click effect
                link.addEventListener('click', function() {
                    navLinks.forEach(l => l.classList.remove('active'));
                    this.classList.add('active');
                });
            });
        });
    document.getElementById('logoutButton').addEventListener('click', function(e) {
        e.preventDefault();
        Swal.fire({
            title: 'تأكيد تسجيل الخروج',
            text: 'هل أنت متأكد أنك تريد تسجيل الخروج؟',
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#ff4444',
            cancelButtonColor: '#6c757d',
            confirmButtonText: 'نعم، سجل خروج',
            cancelButtonText: 'إلغاء',
            reverseButtons: true
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('logoutForm').submit();
            }
        });
    });
    </script>
<?= $content ?>
    </main>
<footer class="footer-section">
    <div class="container-fluid">
        <div class="footer-content">
            <div class="footer-brand">
                <img src="assets/images/logo.png" alt="المجلس العلمي المحلي بركان" class="footer-logo">
            </div>
        </div>

        <div class="footer-bottom">
            <p class="copyright">
                نظام إدارة مساجد إقليم بركان &copy; <?php echo date('Y'); ?>
            </p>
        </div>

    </div>
</footer>
<!-- JavaScript Libraries -->
<script src="assets/vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Select2 JS -->
<script src="assets/vendor/select2/js/select2.min.js"></script>

<!-- Chart.js -->
<script src="assets/vendor/chartjs/chart.min.js"></script>

<script src="assets/js/script.js"></script>
<script src="assets/js/mosque.js"></script>
</body>
</html>
