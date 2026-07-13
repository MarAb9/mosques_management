<?php
/**
 * Dashboard (legacy index.php markup, verbatim).
 * Expects: $totalMosques, $fridayMosques, $closedMosques, $prayerMosques,
 *          $quranMosques, $guidanceMosques, $pashalikMosques, $circleMosques,
 *          $latestMosques, $isAdmin
 */
?>

<style>
    :root {
        --primary-gradient: linear-gradient(135deg, #3A7BD5 0%, #00D2FF 100%);
        --success-gradient: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
        --info-gradient: linear-gradient(135deg, #1D2671 0%, #C33764 100%);
        --warning-gradient: linear-gradient(135deg, #f46b45 0%, #eea849 100%);
        --danger-gradient: linear-gradient(135deg, #C04848 0%, #480048 100%);
        --secondary-gradient: linear-gradient(135deg, #757F9A 0%, #D7DDE8 100%);
        --lavender-gradient: linear-gradient(135deg, #E2B0FF 0%, #9F44D3 100%);
        --teal-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        --peach-gradient: linear-gradient(135deg, #FFAFBD 0%, #ffc3a0 100%);
        --indigo-gradient: linear-gradient(135deg, #6574CD 0%, #9561E2 100%);
    }

    /* Custom animation styles */
    .animate-delay-1 { animation-delay: 0.2s; }
    .animate-delay-2 { animation-delay: 0.4s; }
    .animate-delay-3 { animation-delay: 0.6s; }
    .animate-delay-4 { animation-delay: 0.8s; }

    /* For scroll animations */
    .scroll-animate { opacity: 0; transform: translateY(20px); transition: all 0.6s ease; }
    .scroll-animate.animated { opacity: 1; transform: translateY(0); }

    /* Counting animation */
    .count-up {
        font-size: 2.5rem;
        font-weight: bold;
        font-family: 'Tajawal', sans-serif;
    }

    /* Dashboard Styles */
    .dashboard-header {
        background: rgba(255,255,255,0.9);
        backdrop-filter: blur(10px);
        border-bottom: 1px solid rgba(0,0,0,0.1);
        padding: 1.5rem 0;
        margin-bottom: 2rem;
        box-shadow: 0 2px 20px rgba(0,0,0,0.05);
    }

    .stat-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        transition: all 0.3s ease;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        height: 100%;
    }

    .stat-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0,0,0,0.12);
    }

    .stat-card .card-body {
        padding: 1.5rem;
        position: relative;
        z-index: 1;
    }

    .stat-card .card-title {
        font-size: 1rem;
        font-weight: 600;
        margin-bottom: 0.75rem;
        color: rgba(255,255,255,0.9);
    }

    .stat-card .card-icon {
        position: absolute;
        right: 1.5rem;
        top: 1.5rem;
        font-size: 2.5rem;
        opacity: 0.2;
    }

    .stat-card .card-pattern {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 100%;
        height: 100%;
        background-size: cover;
        opacity: 0.1;
        z-index: -1;
    }

    /* Table Styles */
    .data-card {
        border: none;
        border-radius: 12px;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        transition: all 0.3s ease;
    }

    .data-card:hover {
        box-shadow: 0 10px 30px rgba(0,0,0,0.12);
    }

    .data-card .card-header {
        background: var(--primary-gradient);
        border-bottom: none;
        padding: 1.25rem 1.5rem;
    }

    .data-card .card-title {
        font-weight: 600;
        margin-bottom: 0;
        color: white;
    }

    .data-card .table {
        margin-bottom: 0;
    }

    .data-card .table th {
        background-color: #f8f9fa;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.75rem;
        letter-spacing: 0.5px;
        border-top: none;
        padding: 1rem;
    }

    .data-card .table td {
        vertical-align: middle;
        padding: 1rem;
        border-top: 1px solid #f0f0f0;
    }

    .data-card .table tr:last-child td {
        border-bottom: none;
    }

    .badge {
        padding: 5px 10px;
        border-radius: 6px;
        font-weight: 500;
        font-size: 0.75rem;
    }

    .badge-success {
        background-color: rgba(40, 167, 69, 0.1);
        color: #28a745;
    }

    .badge-danger {
        background-color: rgba(220, 53, 69, 0.1);
        color: #dc3545;
    }

    .action-btn {
        width: 30px;
        height: 30px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background-color: rgba(0, 123, 255, 0.1);
        color: #007bff;
        transition: all 0.2s;
    }

    .action-btn:hover {
        background-color: #007bff;
        color: white;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
        .stat-card .card-body {
            padding: 1rem;
        }

        .count-up {
            font-size: 2rem;
        }
    }
</style>

<div class="dashboard-header animate__animated animate__fadeInDown">
    <div class="container-fluid">
        <h1 class="text-center mb-0">مرحباً بك في نظام إدارة مساجد إقليم بركان</h1>
    </div>
</div>
<!-- DYNAMIC ROLE HEADER -->
<div class="container-fluid mb-4">
    <?php if ($isAdmin): ?>
        <!-- ADMIN HEADER -->
        <div class="card bg-danger text-white">
            <div class="card-body text-center py-3">
                <h1 class="mb-1"><i class="fas fa-shield-alt me-2"></i>لوحة تحكم المسؤول</h1>
                <p class="mb-0">صلاحيات كاملة - إدارة النظام</p>
            </div>
        </div>
    <?php else: ?>
        <!-- CLIENT HEADER -->
        <div class="card bg-success text-white">
            <div class="card-body text-center py-3">
                <h1 class="mb-1"><i class="fas fa-eye me-2"></i>منصة عرض البيانات</h1>
                <p class="mb-0">صلاحيات مشاهدة واستعلام فقط</p>
            </div>
        </div>
    <?php endif; ?>
</div>
<!-- END DYNAMIC ROLE HEADER -->
<div class="container">
    <div class="row g-4">
        <!-- Stat Cards -->
        <div class="col-xl-3 col-md-6">
            <div class="stat-card text-white animate__animated animate__fadeInUp animate-delay-1 scroll-animate" style="background: var(--primary-gradient);">
                <div class="card-body">
                    <i class="fas fa-mosque card-icon"></i>
                    <div class="card-pattern" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3QgZmlsbD0idXJsKCNwYXR0ZXJuKSIgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIvPjwvc3ZnPg==');"></div>
                    <h5 class="card-title">إجمالي المساجد</h5>
                    <h2 class="card-text count-up" id="total-mosques">0</h2>
                    <div class="real-count" data-count="<?php echo $totalMosques; ?>" data-target="total-mosques" style="display:none;"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card text-white animate__animated animate__fadeInUp animate-delay-2 scroll-animate" style="background: var(--success-gradient);">
                <div class="card-body">
                    <i class="fas fa-praying-hands card-icon"></i>
                    <div class="card-pattern" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3QgZmlsbD0idXJsKCNwYXR0ZXJuKSIgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIvPjwvc3ZnPg==');"></div>
                    <h5 class="card-title">مساجد تقام بها الجمعة</h5>
                    <h2 class="card-text count-up" id="friday-mosques">0</h2>
                    <div class="real-count" data-count="<?php echo $fridayMosques; ?>" data-target="friday-mosques" style="display:none;"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card text-white animate__animated animate__fadeInUp animate-delay-3 scroll-animate" style="background: var(--info-gradient);">
                <div class="card-body">
                    <i class="fas fa-door-closed card-icon"></i>
                    <div class="card-pattern" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3QgZmlsbD0idXJsKCNwYXR0ZXJuKSIgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIvPjwvc3ZnPg==');"></div>
                    <h5 class="card-title">مساجد مغلقة</h5>
                    <h2 class="card-text count-up" id="closed-mosques">0</h2>
                    <div class="real-count" data-count="<?php echo $closedMosques; ?>" data-target="closed-mosques" style="display:none;"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card text-white animate__animated animate__fadeInUp animate-delay-4 scroll-animate" style="background: var(--warning-gradient);">
                <div class="card-body">
                    <i class="fas fa-pray card-icon"></i>
                    <div class="card-pattern" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3QgZmlsbD0idXJsKCNwYXR0ZXJuKSIgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIvPjwvc3ZnPg==');"></div>
                    <h5 class="card-title"> الصلوات الخمس</h5>
                    <h2 class="card-text count-up" id="prayer-mosques">0</h2>
                    <div class="real-count" data-count="<?php echo $prayerMosques; ?>" data-target="prayer-mosques" style="display:none;"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <a href="quran_mosques.php" style="text-decoration: none;">
                <div class="stat-card text-white animate__animated animate__fadeInUp scroll-animate" style="background: var(--lavender-gradient);">
                    <div class="card-body">
                        <i class="fas fa-book-quran card-icon"></i>
                        <div class="card-pattern" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3QgZmlsbD0idXJsKCNwYXR0ZXJuKSIgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIvPjwvc3ZnPg==');"></div>
                        <h5 class="card-title">مساجد التحفيظ</h5>
                        <h2 class="card-text count-up" id="quran-mosques">0</h2>
                        <div class="real-count" data-count="<?php echo $quranMosques; ?>" data-target="quran-mosques" style="display:none;"></div>
                    </div>
                </div>
            </a>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card text-white animate__animated animate__fadeInUp scroll-animate" style="background: var(--teal-gradient);">
                <div class="card-body">
                    <i class="fas fa-hands-helping card-icon"></i>
                    <div class="card-pattern" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3QgZmlsbD0idXJsKCNwYXR0ZXJuKSIgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIvPjwvc3ZnPg==');"></div>
                    <h5 class="card-title">مساجد الوعظ والإرشاد</h5>
                    <h2 class="card-text count-up" id="guidance-mosques">0</h2>
                    <div class="real-count" data-count="<?php echo $guidanceMosques; ?>" data-target="guidance-mosques" style="display:none;"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card text-white animate__animated animate__fadeInUp scroll-animate" style="background: var(--peach-gradient);">
                <div class="card-body">
                    <i class="fas fa-city card-icon"></i>
                    <div class="card-pattern" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3QgZmlsbD0idXJsKCNwYXR0ZXJuKSIgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIvPjwvc3ZnPg==');"></div>
                    <h5 class="card-title">إجمالي المساجد الحضرية</h5>
                    <h2 class="card-text count-up" id="pashalik-mosques">0</h2>
                    <div class="real-count" data-count="<?php echo $pashalikMosques; ?>" data-target="pashalik-mosques" style="display:none;"></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="stat-card text-white animate__animated animate__fadeInUp scroll-animate" style="background: var(--indigo-gradient);">
                <div class="card-body">
                    <i class="fas fa-tree card-icon"></i>
                    <div class="card-pattern" style="background-image: url('data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIxMDAlIiBoZWlnaHQ9IjEwMCUiPjxkZWZzPjxwYXR0ZXJuIGlkPSJwYXR0ZXJuIiB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHBhdHRlcm5Vbml0cz0idXNlclNwYWNlT25Vc2UiIHBhdHRlcm5UcmFuc2Zvcm09InJvdGF0ZSg0NSkiPjxyZWN0IHdpZHRoPSIyMCIgaGVpZ2h0PSIyMCIgZmlsbD0icmdiYSgyNTUsMjU1LDI1NSwwLjA1KSIvPjwvcGF0dGVybj48L2RlZnM+PHJlY3QgZmlsbD0idXJsKCNwYXR0ZXJuKSIgd2lkdGg9IjEwMCUiIGhlaWdodD0iMTAwJSIvPjwvc3ZnPg==');"></div>
                    <h5 class="card-title">إجمالي المساجد القروية</h5>
                    <h2 class="card-text count-up" id="circle-mosques">0</h2>
                    <div class="real-count" data-count="<?php echo $circleMosques; ?>" data-target="circle-mosques" style="display:none;"></div>
                </div>
            </div>
        </div>

        <!-- Latest Mosques Table -->
        <div class="col-12 mt-4">
            <div class="data-card scroll-animate">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list-alt me-2"></i>أحدث المساجد المضافة
                    </h5>
                    <div>
                        <?php if ($isAdmin): ?>
                        <a class="btn btn-sm btn-light" data-bs-toggle="tooltip" title="إضافة مسجد جديد" href="add_mosque.php">
                            <i class="fas fa-plus"></i>
                        </a>
                        <?php endif ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th class="text-center">#</th>
                                    <th class="text-center">الرمز الوطني</th>
                                    <th>اسم المسجد</th>
                                    <th>الجماعة</th>
                                    <th>العنوان</th>
                                    <th class="text-center">تاريخ البناء</th>
                                    <th class="text-center">الجمعة</th>
                                    <th>الإمام</th>
                                    <th>الإمام المرشد</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($latestMosques as $row): ?>
                                <tr class='animate__animated animate__fadeIn'>
                                    <td class='text-center fw-bold'><?= $view->e($row['registration_number']) ?></td>
                                    <td class='text-center'>
                                        <span class='badge bg-primary bg-opacity-10 text-primary'>
                                            <?= $view->e($row['national_code']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class='d-flex align-items-center'>
                                            <div class='symbol symbol-40px me-3'>
                                                <span class='symbol-label bg-light-primary'>
                                                    <i class='fas fa-mosque fs-3 text-primary'></i>
                                                </span>
                                            </div>
                                            <div>
                                                <div class='fw-bold'><?= $view->e($row['mosque_name']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= $view->e($row['community']) ?></td>
                                    <td>
                                        <div class='d-flex align-items-center'>
                                            <i class='fas fa-map-marker-alt text-danger me-2'></i>
                                            <span><?= $view->e($row['address']) ?></span>
                                        </div>
                                    </td>
                                    <td class='text-center'>
                                        <span class='badge bg-light text-dark'><?= $view->e($row['construction_year_only']) ?></span>
                                    </td>
                                    <td class='text-center'>
                                    <?php if ($row['friday_prayer'] == 'نعم'): ?>
                                        <span class='badge badge-success'>نعم</span>
                                    <?php else: ?>
                                        <span class='badge badge-danger'>لا</span>
                                    <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class='d-flex align-items-center'>
                                            <div class='symbol symbol-35px me-3'>
                                                <span class='symbol-label bg-light-info'>
                                                    <i class='fas fa-user fs-4 text-info'></i>
                                                </span>
                                            </div>
                                            <div>
                                                <div class='fw-bold'><?= $view->e($row['imam_name']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <div class='d-flex align-items-center'>
                                            <div class='symbol symbol-35px me-3'>
                                                <span class='symbol-label bg-light-warning'>
                                                    <i class='fas fa-user-tie fs-4 text-warning'></i>
                                                </span>
                                            </div>
                                            <div>
                                                <div class='fw-bold'><?= $view->e($row['guide_imam_display'] ?: $row['guide_imam']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-light d-flex justify-content-between align-items-center">
                    <div class="text-muted">
                        عرض 5 من <?= $totalMosques ?> مدخلات
                    </div>
                    <a href="mosques.php" class="btn btn-sm btn-primary">عرض الكل <i class="fas fa-arrow-left ms-2"></i></a>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Scroll animation trigger
document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Function to check if element is in viewport
    function isInViewport(element) {
        const rect = element.getBoundingClientRect();
        return (
            rect.top >= 0 &&
            rect.left >= 0 &&
            rect.bottom <= (window.innerHeight || document.documentElement.clientHeight) * 1.5 && // 1.5 gives some buffer
            rect.right <= (window.innerWidth || document.documentElement.clientWidth)
        );
    }

    // Function to handle scroll animation
    function handleScrollAnimation() {
        const elements = document.querySelectorAll('.scroll-animate');
        elements.forEach(element => {
            if (isInViewport(element) && !element.classList.contains('animated')) {
                element.classList.add('animated');

                // Trigger count animation if this element contains counting numbers
                const counters = element.querySelectorAll('.real-count');
                if (counters.length > 0) {
                    animateCounters();
                }
            }
        });
    }

    // Count animation function
    function animateCounters() {
        const counters = document.querySelectorAll('.real-count');
        const speed = 600; // Lower is faster

        counters.forEach(counter => {
            const target = document.getElementById(counter.getAttribute('data-target'));
            const count = +counter.getAttribute('data-count');
            const increment = count / speed;

            let current = 0;
            const updateCount = () => {
                current += increment;
                if (current < count) {
                    target.innerText = Math.floor(current);
                    setTimeout(updateCount, 1);
                } else {
                    target.innerText = count;
                }
            };

            updateCount();
        });
    }

    // Initial check
    handleScrollAnimation();

    // Check on scroll
    window.addEventListener('scroll', handleScrollAnimation);
});
</script>
