<?php
// If auth_check.php hasn't been included yet, include it
if (!function_exists('canCreateMosque')) {
    require_once 'auth_check.php';
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>نظام إدارة مساجد إقليم بركان</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">

    <!-- Preload important resources -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" as="style">
    <link rel="preload" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" as="style">
    <link rel="preload" href="assets/css/style.css" as="style">
    
    <!-- CSS Links -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/hover.css/2.3.1/css/hover-min.css">
    
    <!-- Select2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <!-- Your custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/mosque.css">
    <link rel="stylesheet" href="assets/css/header.css">
    <link rel="stylesheet" href="assets/css/footer.css">
    
    <!-- Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-light">
        <div class="container">
            <a class="navbar-brand animate__animated animate__fadeIn" href="index.php">
                <i class="fas fa-mosque"></i>
                نظام مساجد بركان
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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
                    <?php if (canCreateMosque()): ?>
                    <li class="nav-item">
                        <a class="nav-link hvr-underline-from-center" href="add_mosque.php">
                            <i class="fas fa-plus-circle"></i>
                            إضافة مسجد
                        </a>
                    </li>
                     <?php endif ?>
                    <li class="nav-item">
                        <a class="nav-link hvr-underline-from-center" href="import_export.php">
                            <i class="fas fa-<?= canImportData() ? 'exchange-alt' : 'file-export' ?>"></i>
                            <?= canImportData() ? 'استيراد/تصدير' : 'تصدير البيانات' ?>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="mosque_maps.php" class="btn btn-success rounded-pill">
                            <i class="fas fa-map-marked-alt me-2"></i>خريطة المساجد
                        </a>
                    </li>
                </ul>
                <div class="user-section">
                    <a class="logout-btn" href="logout.php" id="logoutButton">
                        <i class="fas fa-sign-out-alt"></i>
                        <span>تسجيل الخروج</span>
                    </a>
                </div>
            </div>
        </div>
    </nav>
    <div class="container mt-4">

    <script>
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
                window.location.href = this.href;
            }
        });
    });
    </script>
