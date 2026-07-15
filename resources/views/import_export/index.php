<?php
$actions = '<button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#filterModal"><i class="fas fa-sliders me-2" aria-hidden="true"></i>تصدير مخصص</button>';
?>
<div class="import-workspace">
    <?= $view->partial('components.page_header', [
        'kicker' => 'نقل البيانات الموثوق',
        'title' => 'الاستيراد والتصدير',
        'subtitle' => 'راجع ملف Excel قبل إدخاله، صدّر التقارير المطلوبة، واحتفظ بصيغ Excel وWord المعتمدة دون تغيير.',
        'icon' => 'fa-arrow-right-arrow-left',
        'actionsHtml' => $actions,
    ]) ?>

    <?php if ($successMessage !== null): ?><div class="alert alert-success alert-dismissible fade show" role="status"><i class="fas fa-circle-check me-2" aria-hidden="true"></i><?= $view->e($successMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button></div><?php endif; ?>
    <?php if ($errorMessage !== null): ?><div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="fas fa-circle-exclamation me-2" aria-hidden="true"></i><?= $view->e($errorMessage) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="إغلاق"></button></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-xl-7">
            <section class="data-panel h-100" aria-labelledby="importTitle">
                <div class="data-panel__header"><div><span class="page-kicker">المرحلة الأولى</span><h2 id="importTitle">استيراد بيانات المساجد</h2></div><span class="status-badge text-info bg-info-subtle">Excel فقط</span></div>
                <div class="data-panel__body">
                    <?php if ($canImport ?? $isAdmin): ?>
                    <form method="POST" action="" enctype="multipart/form-data" id="importPreviewForm">
                        <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                        <label class="upload-zone d-block text-center" for="import_file" data-upload-zone>
                            <span class="empty-state__icon"><i class="fas fa-file-excel" aria-hidden="true"></i></span>
                            <strong class="d-block mt-3" data-upload-name>اختر ملف Excel أو اسحبه إلى هنا</strong>
                            <span class="text-muted small d-block mt-1">الصيغ المدعومة: XLSX وXLS</span>
                            <input type="file" class="form-control mt-3" id="import_file" name="import_file" accept=".xlsx,.xls" required>
                        </label>
                        <div class="d-flex align-items-center justify-content-between gap-3 flex-wrap mt-3"><small class="text-muted"><i class="fas fa-shield-halved me-1" aria-hidden="true"></i>لن تنفذ الكتابة قبل مراجعة المعاينة.</small><button type="submit" name="preview_import" value="1" class="btn btn-primary"><i class="fas fa-eye me-2" aria-hidden="true"></i>معاينة الملف</button></div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-info mb-0"><i class="fas fa-lock me-2" aria-hidden="true"></i><strong>الاستيراد متاح للمسؤولين فقط.</strong> ما زال بإمكانك استخدام خيارات التصدير المصرح بها.</div>
                    <?php endif; ?>

                    <details class="mt-4">
                        <summary class="btn btn-sm btn-outline-secondary">متطلبات ملف الاستيراد</summary>
                        <ul class="small text-muted mt-3 mb-0">
                            <li>يحتوي الصف الأول على عناوين الأعمدة.</li>
                            <li>الأعمدة الأساسية من B إلى E موجودة.</li>
                            <li>الرمز الوطني في العمود E فريد لكل سجل.</li>
                            <li>تعرض المعاينة الصفوف الصحيحة والمكررة والناقصة قبل التنفيذ.</li>
                        </ul>
                    </details>
                </div>
            </section>
        </div>

        <div class="col-xl-5">
            <section class="data-panel h-100" aria-labelledby="exportTitle">
                <div class="data-panel__header"><div><span class="page-kicker">تقارير جاهزة</span><h2 id="exportTitle">تصدير البيانات</h2></div><span class="status-badge text-success bg-success-subtle">Excel / Word</span></div>
                <div class="data-panel__body quick-actions">
                    <a href="import_export.php?export=1" class="quick-action"><i class="fas fa-file-excel" aria-hidden="true"></i><span><strong class="d-block">جميع بيانات المساجد</strong><small class="text-muted">ملف Excel كامل</small></span></a>
                    <a href="import_export.php?export=1&amp;no_location=1&amp;group_by_guide=1" class="quick-action"><i class="fas fa-map-location-dot" aria-hidden="true"></i><span><strong class="d-block">مساجد بدون موقع</strong><small class="text-muted">Excel مجمع حسب الإمام المرشد</small></span></a>
                    <a href="import_export.php?export=1&amp;no_location=1&amp;group_by_guide=1&amp;format=word" class="quick-action"><i class="fas fa-file-word" aria-hidden="true"></i><span><strong class="d-block">تقرير بدون موقع</strong><small class="text-muted">مستند Word معتمد</small></span></a>
                    <button class="quick-action text-start w-100" type="button" data-bs-toggle="modal" data-bs-target="#filterModal"><i class="fas fa-filter" aria-hidden="true"></i><span><strong class="d-block">تصدير مخصص</strong><small class="text-muted">حدد الحالة والجماعة والبرنامج والصيغة</small></span></button>
                </div>
            </section>
        </div>
    </div>

    <?php if (!empty($importPreview)): ?>
    <section class="data-panel mt-4" aria-labelledby="previewTitle">
        <div class="data-panel__header"><div><span class="page-kicker">مراجعة قبل التنفيذ</span><h2 id="previewTitle">نتيجة تحليل ملف الاستيراد</h2></div><?php if (!empty($importPreview['errors'])): ?><a class="btn btn-sm btn-outline-danger" href="import_export.php?import_error_report=<?= urlencode((string) $importPreview['token']) ?>"><i class="fas fa-file-csv me-1" aria-hidden="true"></i>تقرير الأخطاء</a><?php endif; ?></div>
        <div class="data-panel__body">
            <div class="metric-grid mb-4">
                <?= $view->partial('components.metric_card', ['label' => 'إجمالي الصفوف', 'value' => number_format((int) ($importPreview['total_rows'] ?? 0)), 'icon' => 'fa-table-list']) ?>
                <?= $view->partial('components.metric_card', ['label' => 'جاهزة للاستيراد', 'value' => number_format((int) ($importPreview['valid_rows'] ?? 0)), 'icon' => 'fa-circle-check']) ?>
                <?= $view->partial('components.metric_card', ['label' => 'صفوف مكررة', 'value' => number_format((int) ($importPreview['duplicate_rows'] ?? 0)), 'icon' => 'fa-copy', 'variant' => 'warning']) ?>
                <?= $view->partial('components.metric_card', ['label' => 'ناقصة أو خاطئة', 'value' => number_format((int) ($importPreview['skipped_rows'] ?? 0)), 'icon' => 'fa-circle-xmark', 'variant' => 'danger']) ?>
            </div>
            <div class="table-responsive"><table class="table app-table align-middle"><thead><tr><th>السطر</th><th>المسجد</th><th>الرمز الوطني</th><th>الحالة</th><th>الملاحظة</th></tr></thead><tbody>
                <?php foreach (($importPreview['preview_rows'] ?? []) as $row): $badge = ($row['status'] ?? '') === 'valid' ? 'success' : ((($row['status'] ?? '') === 'duplicate') ? 'warning' : 'danger'); ?>
                <tr><td><?= $view->e((string) ($row['row'] ?? '')) ?></td><td><?= $view->e((string) ($row['mosque_name'] ?? '')) ?></td><td><span class="badge bg-light text-dark"><?= $view->e((string) ($row['national_code'] ?? '')) ?></span></td><td><span class="status-badge text-<?= $badge ?> bg-<?= $badge ?>-subtle"><?= $view->e((string) ($row['status'] ?? '')) ?></span></td><td><?= $view->e((string) ($row['message'] ?? '')) ?></td></tr>
                <?php endforeach; ?>
            </tbody></table></div>
            <form method="POST" action="import_export.php" class="d-flex justify-content-end gap-2 mt-3 js-confirm-submit" data-confirm="هل تريد تنفيذ الاستيراد الآن؟"><input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>"><input type="hidden" name="import_token" value="<?= $view->e((string) $importPreview['token']) ?>"><a href="import_export.php" class="btn btn-outline-secondary">إلغاء</a><button type="submit" class="btn btn-success"><i class="fas fa-check me-1" aria-hidden="true"></i>تأكيد الاستيراد</button></form>
        </div>
    </section>
    <?php endif; ?>

    <?php if (($canImport ?? $isAdmin) && !empty($lastImport)): ?>
    <section class="alert alert-warning d-flex justify-content-between align-items-center gap-3 flex-wrap mt-4 mb-0">
        <div><strong class="d-block"><i class="fas fa-clock-rotate-left me-1" aria-hidden="true"></i>آخر استيراد قابل للتراجع</strong><span><?= $view->e((string) ($lastImport['original_name'] ?? '')) ?> · <?= number_format((int) ($lastImport['row_count'] ?? 0)) ?> سجل</span></div>
        <form method="POST" action="import_export.php" class="js-confirm-submit" data-confirm="سيتم حذف السجلات التي أضيفت في آخر استيراد. هل تريد المتابعة؟"><input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>"><button type="submit" name="rollback_last_import" value="1" class="btn btn-outline-danger">تراجع عن آخر استيراد</button></form>
    </section>
    <?php endif; ?>
</div>

<div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content"><div class="modal-header"><div><span class="page-kicker">تقرير حسب الحاجة</span><h2 class="modal-title h5 mb-0" id="filterModalLabel">خيارات التصدير المخصص</h2></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button></div>
        <form id="exportForm" action="import_export.php" method="GET"><input type="hidden" name="export" value="1"><div class="modal-body"><div class="row g-3">
            <div class="col-md-6"><label for="exportStatus" class="form-label">حالة المسجد</label><select class="form-select" id="exportStatus" name="status"><option value="">الكل</option><?php foreach ($statuses as $status): ?><option value="<?= $view->e($status) ?>"><?= $view->e($status) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label for="exportFriday" class="form-label">صلاة الجمعة</label><select class="form-select" id="exportFriday" name="friday_prayer"><option value="">الكل</option><?php foreach ($fridayPrayers as $prayer): ?><option value="<?= $view->e($prayer) ?>"><?= $view->e($prayer) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label for="exportCommunity" class="form-label">الجماعة</label><select class="form-select" id="exportCommunity" name="community"><option value="">الكل</option><?php foreach ($communities as $community): ?><option value="<?= $view->e($community) ?>"><?= $view->e($community) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label for="exportLiteracy" class="form-label">محو الأمية</label><select class="form-select" id="exportLiteracy" name="literacy_program"><option value="">الكل</option><?php foreach ($literacyPrograms as $program): ?><option value="<?= $view->e($program) ?>"><?= $view->e($program) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label for="exportGuidance" class="form-label">الوعظ والإرشاد</label><select class="form-select" id="exportGuidance" name="guidance_program"><option value="">الكل</option><?php foreach ($guidancePrograms as $program): ?><option value="<?= $view->e($program) ?>"><?= $view->e($program) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label for="exportGuideImam" class="form-label">الإمام المرشد</label><select class="form-select" id="exportGuideImam" name="guide_imam"><option value="">الكل</option><?php foreach ($guideImams as $imam): ?><option value="<?= $view->e($imam['id']) ?>"><?= $view->e($imam['display_name']) ?> (<?= (int) $imam['mosque_count'] ?>)</option><?php endforeach; ?></select></div>
            <div class="col-md-6"><label for="exportFormat" class="form-label">صيغة الملف</label><select class="form-select" id="exportFormat" name="format"><option value="excel">Excel (.xlsx)</option><option value="word">Word (.docx)</option></select></div>
            <div class="col-md-6 d-flex align-items-end"><div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" id="exportNoLocation" name="no_location" value="1"><label class="form-check-label" for="exportNoLocation">المساجد غير محددة الموقع فقط</label></div></div>
        </div></div><div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">إلغاء</button><button type="submit" class="btn btn-success"><i class="fas fa-file-export me-2" aria-hidden="true"></i>إنشاء الملف</button></div></form>
    </div></div>
</div>
