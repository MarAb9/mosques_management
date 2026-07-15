<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">سلة المساجد المحذوفة</h1>
            <p class="text-muted mb-0">الحذف من الواجهة يؤرشف نسخة قابلة للاستعادة قبل إزالة السجل من الجدول الرئيسي.</p>
        </div>
        <a href="audit.php?q=mosque.delete" class="btn btn-outline-primary">عرض عمليات الحذف</a>
    </div>

    <?php if ($successMessage !== null): ?><div class="alert alert-success"><?= $view->e($successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== null): ?><div class="alert alert-danger"><?= $view->e($errorMessage) ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table app-table--compact">
                <thead><tr><th>وقت الحذف</th><th>المسجد</th><th>الرمز الوطني</th><th>حذف بواسطة</th><th>إجراء</th></tr></thead>
                <tbody>
                <?php if (empty($deletedMosques)): ?>
                    <tr><td colspan="5" class="text-center text-muted py-5">لا توجد مساجد في السلة.</td></tr>
                <?php endif; ?>
                <?php foreach ($deletedMosques as $entry): $mosque = $entry['mosque'] ?? []; ?>
                    <tr>
                        <td class="small"><?= $view->e($entry['deleted_at'] ?? '') ?></td>
                        <td><?= $view->e($mosque['mosque_name'] ?? '') ?></td>
                        <td><span class="badge bg-light text-dark"><?= $view->e($mosque['national_code'] ?? '') ?></span></td>
                        <td><?= $view->e($entry['deleted_by']['username'] ?? '') ?></td>
                        <td>
                            <form method="post" action="restore_mosque.php" class="d-inline js-confirm-submit" data-confirm="تأكيد استعادة المسجد؟">
                                <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                                <input type="hidden" name="registration_number" value="<?= $view->e($mosque['registration_number'] ?? '') ?>">
                                <button class="btn btn-sm btn-success"><i class="fas fa-undo me-1"></i>استعادة</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>