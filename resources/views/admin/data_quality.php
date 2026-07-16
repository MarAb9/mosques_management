<?php
$issues = [
    'missing_coordinates' => ['label' => 'إحداثيات ناقصة', 'description' => 'مساجد لا تظهر على الخريطة التشغيلية', 'icon' => 'fa-location-dot', 'severity' => 'warning'],
    'missing_imam_phone' => ['label' => 'هاتف الإمام ناقص', 'description' => 'بيانات اتصال تحتاج استكمالاً', 'icon' => 'fa-phone-slash', 'severity' => 'danger'],
    'incomplete_addresses' => ['label' => 'عناوين غير مكتملة', 'description' => 'عناوين يصعب الاعتماد عليها', 'icon' => 'fa-map-pin', 'severity' => 'warning'],
    'invalid_years' => ['label' => 'سنوات بناء غير صالحة', 'description' => 'قيم زمنية تحتاج المراجعة', 'icon' => 'fa-calendar-xmark', 'severity' => 'danger'],
    'duplicate_national_codes' => ['label' => 'رموز وطنية مكررة', 'description' => 'تعارض في معرفات السجلات', 'icon' => 'fa-copy', 'severity' => 'danger'],
];
$totalIssues = array_sum(array_map('intval', $summary ?? []));
$actions = '<a href="import_export.php?export=1&amp;no_location=' . ($issue === 'missing_coordinates' ? '1' : '0') . '" class="btn btn-outline-primary"><i class="fas fa-file-export me-2" aria-hidden="true"></i>تصدير تقرير</a>';
?>
<div class="admin-workspace">
    <?= $view->partial('components.page_header', [
        'title' => 'جودة البيانات',
        'subtitle' => number_format((int) $totalIssues) . ' مؤشر يحتاج المراجعة',
        'icon' => 'fa-clipboard-check',
        'illustration' => 'assets/images/institutional/database-quality-3d.svg',
        'actionsHtml' => $actions,
    ]) ?>

    <?php if ($totalIssues === 0): ?>
    <section class="data-panel mb-4">
        <?= $view->partial('components.empty_state', ['icon' => 'fa-circle-check', 'title' => 'جودة البيانات مستقرة', 'message' => 'لم يرصد النظام أياً من فئات المشاكل المتابعة حالياً.']) ?>
    </section>
    <?php else: ?>
    <div class="alert alert-warning d-flex align-items-start gap-3" role="status"><i class="fas fa-triangle-exclamation fs-4 mt-1" aria-hidden="true"></i><div><strong class="d-block">يوجد <?= number_format($totalIssues) ?> مؤشراً يحتاج المراجعة</strong></div></div>
    <?php endif; ?>

    <section class="metric-grid mb-4" aria-label="فئات مشاكل جودة البيانات">
        <?php foreach ($issues as $key => $config): ?>
        <a class="metric-card metric-card--<?= $view->e($config['severity']) ?><?= $issue === $key ? ' border-primary' : '' ?>" href="data_quality.php?issue=<?= urlencode($key) ?>" aria-current="<?= $issue === $key ? 'true' : 'false' ?>">
            <div class="metric-card__top"><span class="metric-card__label"><?= $view->e($config['label']) ?></span><span class="metric-card__icon"><i class="fas <?= $view->e($config['icon']) ?>" aria-hidden="true"></i></span></div>
            <div><div class="metric-card__value"><?= number_format((int) ($summary[$key] ?? 0)) ?></div><div class="metric-card__context"><?= $view->e($config['description']) ?></div></div>
        </a>
        <?php endforeach; ?>
    </section>

    <section class="data-panel" aria-labelledby="qualitySamplesTitle">
        <div class="data-panel__header"><h2 id="qualitySamplesTitle"><?= $view->e($issues[$issue]['label'] ?? 'عينات جودة البيانات') ?></h2><span class="status-badge <?= empty($samples) ? 'text-success bg-success-subtle' : 'text-warning bg-warning-subtle' ?>"><?= count($samples) ?> نتيجة</span></div>
        <?php if (empty($samples)): ?>
            <?= $view->partial('components.empty_state', ['icon' => 'fa-circle-check', 'title' => 'لا توجد مشاكل في هذه الفئة', 'message' => 'جميع السجلات المطابقة اجتازت هذا الفحص.']) ?>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table app-table app-table--compact align-middle mb-0">
                <thead><tr><th>ر.ت.ع</th><th>الرمز الوطني</th><th>المسجد</th><th>الجماعة</th><th>العنوان</th><th>الإمام والاتصال</th><th>الموقع / السنة</th><th>الإجراء</th></tr></thead>
                <tbody>
                <?php foreach ($samples as $row): ?>
                    <tr>
                        <td class="fw-bold"><?= $view->e($row['registration_number'] ?? '') ?></td>
                        <td><span class="badge bg-light text-dark"><?= $view->e($row['national_code'] ?? '') ?></span></td>
                        <td><strong><?= $view->e($row['mosque_name'] ?? '') ?></strong></td>
                        <td><?= $view->e($row['community'] ?? '') ?></td>
                        <td class="text-muted small"><?= $view->e($row['address'] ?? '') ?></td>
                        <td><?= $view->e($row['imam_name'] ?? 'غير محدد') ?><br><span class="text-muted small"><?= $view->e($row['imam_phone'] ?? 'بدون هاتف') ?></span></td>
                        <td class="small"><?= $view->e(trim((string) ($row['latitude'] ?? '') . ', ' . (string) ($row['longitude'] ?? ''), ', ')) ?><br><?= $view->e($row['construction_date'] ?? '') ?></td>
                        <td><a class="btn btn-sm btn-primary" href="edit_mosque.php?id=<?= urlencode((string) ($row['registration_number'] ?? '')) ?>"><i class="fas fa-wrench me-1" aria-hidden="true"></i>تصحيح</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
