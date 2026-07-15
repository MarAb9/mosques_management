<?php
$labels = [
    'missing_coordinates' => 'مساجد بدون إحداثيات',
    'missing_imam_phone' => 'أرقام هاتف الإمام ناقصة',
    'incomplete_addresses' => 'عناوين ناقصة',
    'invalid_years' => 'سنوات بناء غير صالحة',
    'duplicate_national_codes' => 'رموز وطنية مكررة',
];
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">لوحة جودة البيانات</h1>
            <p class="text-muted mb-0">مؤشرات قابلة للتنفيذ لتحسين قاعدة بيانات المساجد.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary"><i class="fas fa-arrow-right me-2"></i>العودة للوحة التحكم</a>
    </div>

    <div class="row g-3 mb-4">
        <?php foreach ($labels as $key => $label): ?>
        <div class="col-xl col-md-4 col-sm-6">
            <a class="card h-100 text-decoration-none <?= $issue === $key ? 'border-primary shadow' : 'border-0 shadow-sm' ?>" href="data_quality.php?issue=<?= urlencode($key) ?>">
                <div class="card-body">
                    <div class="text-muted small"><?= $view->e($label) ?></div>
                    <div class="display-6 fw-bold text-primary"><?= number_format((int) ($summary[$key] ?? 0)) ?></div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h2 class="h5 mb-0"><?= $view->e($labels[$issue] ?? 'عينات جودة البيانات') ?></h2>
            <a class="btn btn-sm btn-success" href="import_export.php?export=1&no_location=<?= $issue === 'missing_coordinates' ? '1' : '0' ?>">
                <i class="fas fa-file-export me-1"></i>تصدير تقرير
            </a>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table app-table--compact">
                <thead><tr><th>ر.ت.ع</th><th>الرمز الوطني</th><th>المسجد</th><th>الجماعة</th><th>العنوان</th><th>الإمام/الهاتف</th><th>الموقع/السنة</th><th>إجراء</th></tr></thead>
                <tbody>
                <?php if (empty($samples)): ?>
                    <tr><td colspan="8" class="text-center text-muted py-5"><i class="fas fa-check-circle fa-2x d-block mb-2 text-success"></i>لا توجد مشاكل في هذا التصنيف.</td></tr>
                <?php endif; ?>
                <?php foreach ($samples as $row): ?>
                    <tr>
                        <td><?= $view->e($row['registration_number'] ?? '') ?></td>
                        <td><span class="badge bg-light text-dark"><?= $view->e($row['national_code'] ?? '') ?></span></td>
                        <td><?= $view->e($row['mosque_name'] ?? '') ?></td>
                        <td><?= $view->e($row['community'] ?? '') ?></td>
                        <td class="text-muted small"><?= $view->e($row['address'] ?? '') ?></td>
                        <td><?= $view->e($row['imam_name'] ?? '') ?><br><span class="text-muted small"><?= $view->e($row['imam_phone'] ?? '') ?></span></td>
                        <td class="small"><?= $view->e(trim((string)($row['latitude'] ?? '') . ', ' . (string)($row['longitude'] ?? ''), ', ')) ?><br><?= $view->e($row['construction_date'] ?? '') ?></td>
                        <td><a class="btn btn-sm btn-primary" href="edit_mosque.php?id=<?= urlencode((string) ($row['registration_number'] ?? '')) ?>">تصحيح</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>