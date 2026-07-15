<?php
$qualityIssueCount = array_sum(array_map('intval', $dataQuality ?? []));
?>
<div class="dashboard-page">
    <section class="dashboard-hero reveal" aria-labelledby="dashboardWelcome">
        <div class="dashboard-hero__content">
            <h1 id="dashboardWelcome">لوحة التحكم</h1>
            <p>متابعة المساجد والبرامج وجودة البيانات.</p>
            <div class="dashboard-hero__actions">
                <a class="btn btn-light" href="mosques.php"><i class="fas fa-magnifying-glass me-2" aria-hidden="true"></i>دليل المساجد</a>
                <a class="btn btn-outline-light" href="mosque_maps.php"><i class="fas fa-map-location-dot me-2" aria-hidden="true"></i>خريطة المساجد</a>
            </div>
        </div>
        <img class="dashboard-hero__image" src="assets/images/institutional/statistics-3d.svg" alt="" aria-hidden="true">
    </section>
    <?php if ($isAdmin && ($qualityIssueCount > 0 || (int) $closedMosques > 0)): ?>
    <section class="alert alert-warning reveal d-flex align-items-start gap-3 mb-0" aria-labelledby="urgentTitle">
        <i class="fas fa-triangle-exclamation fs-4 mt-1" aria-hidden="true"></i>
        <div class="flex-grow-1"><h2 class="h6 mb-1" id="urgentTitle">متابعة تشغيلية مطلوبة</h2><p class="mb-2">توجد سجلات تحتاج المراجعة: <?= number_format((int) ($dataQuality['missing_coordinates'] ?? 0)) ?> بدون إحداثيات، <?= number_format((int) ($dataQuality['missing_imam_phone'] ?? 0)) ?> بدون هاتف إمام، و<?= number_format((int) $closedMosques) ?> مسجد مغلق.</p><a class="alert-link" href="data_quality.php">فتح لوحة جودة البيانات</a></div>
    </section>
    <?php endif; ?>

    <section aria-labelledby="mainMetrics">
        <div class="section-heading"><h2 class="h5 mb-0" id="mainMetrics">المؤشرات الرئيسية</h2></div>
        <div class="metric-grid">
            <?= $view->partial('components.metric_card', ['label' => 'إجمالي المساجد', 'value' => number_format((int) $totalMosques), 'countUp' => (int) $totalMosques, 'context' => 'جميع السجلات الحالية', 'icon' => 'fa-mosque', 'image' => 'assets/images/institutional/mosque-building-3d.svg', 'href' => 'mosques.php']) ?>
            <?= $view->partial('components.metric_card', ['label' => 'مساجد الجمعة', 'value' => number_format((int) $fridayMosques), 'countUp' => (int) $fridayMosques, 'context' => 'تقام بها صلاة الجمعة', 'icon' => 'fa-calendar-day', 'href' => 'mosques.php?friday_prayer=' . urlencode('نعم')]) ?>
            <?= $view->partial('components.metric_card', ['label' => 'المساجد المفتوحة', 'value' => number_format((int) $prayerMosques), 'countUp' => (int) $prayerMosques, 'context' => 'غير مصنفة كمغلقة', 'icon' => 'fa-door-open', 'href' => 'mosques.php?status=' . urlencode('مفتوح')]) ?>
            <?= $view->partial('components.metric_card', ['label' => 'المساجد المغلقة', 'value' => number_format((int) $closedMosques), 'countUp' => (int) $closedMosques, 'context' => 'تحتاج متابعة الحالة', 'icon' => 'fa-door-closed', 'href' => 'mosques.php?status=' . urlencode('مغلق'), 'variant' => 'danger']) ?>
            <?= $view->partial('components.metric_card', ['label' => 'برامج القرآن', 'value' => number_format((int) $quranMosques), 'countUp' => (int) $quranMosques, 'context' => 'مساجد مسجلة في وحدة التحفيظ', 'icon' => 'fa-book-quran', 'image' => 'assets/images/institutional/quran-book-3d.svg', 'href' => 'quran_mosques.php']) ?>
            <?= $view->partial('components.metric_card', ['label' => 'الوعظ والإرشاد', 'value' => number_format((int) $guidanceMosques), 'countUp' => (int) $guidanceMosques, 'context' => 'مساجد تغطيها البرامج', 'icon' => 'fa-hands-holding-circle']) ?>
            <?= $view->partial('components.metric_card', ['label' => 'المجال الحضري', 'value' => number_format((int) $pashalikMosques), 'countUp' => (int) $pashalikMosques, 'context' => 'مساجد الباشويات', 'icon' => 'fa-city']) ?>
            <?= $view->partial('components.metric_card', ['label' => 'المجال القروي', 'value' => number_format((int) $circleMosques), 'countUp' => (int) $circleMosques, 'context' => 'مساجد الدوائر', 'icon' => 'fa-tree-city']) ?>
        </div>
    </section>

    <div class="dashboard-grid">
        <section class="data-panel reveal" aria-labelledby="coverageTitle">
            <div class="data-panel__header"><div><h2 id="coverageTitle">جاهزية البيانات التشغيلية</h2></div><a class="btn btn-sm btn-outline-primary" href="data_quality.php">التفاصيل</a></div>
            <div class="data-panel__body">
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-bold">التغطية الجغرافية</span><strong><?= $view->e($mapCoveragePercent) ?>%</strong></div>
                    <?= $view->partial('components.progress', ['value' => $mapCoveragePercent, 'label' => 'نسبة المساجد محددة الموقع']) ?>
                    <small class="text-muted d-block mt-2"><?= number_format((int) $mappedMosques) ?> من <?= number_format((int) $totalMosques) ?> مسجد محدد الموقع</small>
                </div>
                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2"><span class="fw-bold">تغطية برامج القرآن</span><strong><?= $view->e($quranCoveragePercent) ?>%</strong></div>
                    <?= $view->partial('components.progress', ['value' => $quranCoveragePercent, 'label' => 'نسبة المساجد المسجلة في برامج القرآن']) ?>
                    <small class="text-muted d-block mt-2"><?= number_format((int) $quranMosques) ?> برنامجاً مرتبطاً بالمساجد</small>
                </div>
                <div class="row g-3">
                    <div class="col-sm-6"><a class="quick-action" href="data_quality.php?issue=missing_coordinates"><i class="fas fa-location-dot" aria-hidden="true"></i><span><strong class="d-block"><?= number_format((int) ($dataQuality['missing_coordinates'] ?? 0)) ?></strong><small>بدون إحداثيات</small></span></a></div>
                    <div class="col-sm-6"><a class="quick-action" href="data_quality.php?issue=missing_imam_phone"><i class="fas fa-phone-slash" aria-hidden="true"></i><span><strong class="d-block"><?= number_format((int) ($dataQuality['missing_imam_phone'] ?? 0)) ?></strong><small>هاتف ناقص</small></span></a></div>
                </div>
            </div>
        </section>

        <aside class="data-panel reveal" aria-labelledby="quickActionsTitle">
            <div class="data-panel__header"><div><h2 id="quickActionsTitle">إجراءات سريعة</h2></div></div>
            <div class="data-panel__body quick-actions">
                <?php if ($isAdmin): ?><a class="quick-action" href="add_mosque.php"><i class="fas fa-plus" aria-hidden="true"></i><span><strong class="d-block">إضافة مسجد</strong><small class="text-muted">إنشاء سجل جديد</small></span></a><?php endif; ?>
                <a class="quick-action" href="mosque_maps.php"><i class="fas fa-map-location-dot" aria-hidden="true"></i><span><strong class="d-block">الخريطة</strong><small class="text-muted">فحص التغطية المكانية</small></span></a>
                <a class="quick-action" href="quran_mosques.php"><i class="fas fa-book-quran" aria-hidden="true"></i><span><strong class="d-block">برامج القرآن</strong><small class="text-muted">المعلمون والطلبة</small></span></a>
                <?php if ($isAdmin): ?><a class="quick-action" href="backup.php"><i class="fas fa-shield-halved" aria-hidden="true"></i><span><strong class="d-block">نسخة احتياطية</strong><small class="text-muted">تنزيل JSON آمن</small></span></a><?php endif; ?>
            </div>
        </aside>
    </div>

    <section class="data-panel reveal" aria-labelledby="latestTitle">
        <div class="data-panel__header"><div><h2 id="latestTitle">أحدث المساجد</h2></div><a class="btn btn-sm btn-outline-primary" href="mosques.php">عرض الدليل</a></div>
        <?php if (empty($latestMosques)): ?>
            <?= $view->partial('components.empty_state', ['icon' => 'fa-mosque', 'title' => 'لا توجد مساجد مسجلة', 'message' => 'ستظهر أحدث السجلات هنا بعد إضافتها.']) ?>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table app-table align-middle mb-0">
                <thead><tr><th>المسجد</th><th>الرمز الوطني</th><th>الجماعة</th><th>العنوان</th><th>الجمعة</th><th>الإمام</th></tr></thead>
                <tbody>
                    <?php foreach ($latestMosques as $row): ?>
                    <tr>
                        <td><div class="d-flex align-items-center gap-2"><span class="metric-card__icon"><i class="fas fa-mosque" aria-hidden="true"></i></span><strong><?= $view->e($row['mosque_name']) ?></strong></div></td>
                        <td><span class="badge bg-light text-dark"><?= $view->e($row['national_code']) ?></span></td>
                        <td><?= $view->e($row['community']) ?></td>
                        <td class="text-muted"><?= $view->e($row['address']) ?></td>
                        <td><span class="status-badge <?= $row['friday_prayer'] === 'نعم' ? 'text-success bg-success-subtle' : 'text-secondary bg-light' ?>"><?= $view->e($row['friday_prayer']) ?></span></td>
                        <td><?= $view->e($row['imam_name'] ?: 'غير محدد') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>

</div>
