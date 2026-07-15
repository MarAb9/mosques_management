<?php
$totalStudents = (int) ($row['total_male_students'] ?? 0) + (int) ($row['total_female_students'] ?? 0);
$totalSessions = (int) ($row['total_weekly_sessions'] ?? 0);
$programStatus = $row['top_work_program']['has_work_program'] ?? 'لا';
$responsibleText = implode('، ', array_slice($row['responsible_names'] ?? [], 0, 3));
?>
<article class="card quran-record-card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div>
                <h3 class="h6 mb-1"><i class="fas fa-mosque me-1 text-primary" aria-hidden="true"></i><?= $view->e($row['mosque_name']) ?></h3>
                <p class="small text-muted mb-0"><?= $view->e($row['community']) ?> · رقم <?= $view->e($row['id']) ?></p>
            </div>
            <span class="badge bg-light text-dark"><?= $view->e($row['national_code']) ?></span>
        </div>
        <div class="row g-2 mt-3">
            <div class="col-6"><div class="p-2 rounded bg-light"><small class="text-muted d-block">الطلبة</small><strong><?= number_format($totalStudents) ?> طالب</strong></div></div>
            <div class="col-6"><div class="p-2 rounded bg-light"><small class="text-muted d-block">الجلسات</small><strong><?= number_format($totalSessions) ?> أسبوعياً</strong></div></div>
        </div>
        <dl class="row small mt-3 mb-3"><dt class="col-5 text-muted">نوع البرنامج</dt><dd class="col-7"><?= $view->e($programStatus) ?></dd><dt class="col-5 text-muted">المسؤولون</dt><dd class="col-7"><?= $view->e($responsibleText ?: 'غير محدد') ?></dd><dt class="col-5 text-muted">الإقامة</dt><dd class="col-7"><?= $view->e($row['has_accommodation'] ?? 'لا') ?></dd></dl>
        <div class="d-flex flex-wrap gap-2" aria-label="إجراءات السجل">
            <button type="button" class="btn btn-sm btn-outline-secondary view-quran-mosque-btn" data-bs-toggle="modal" data-bs-target="#quranMosqueDetailsModal" data-mosque-id="<?= $view->e($row['id']) ?>" aria-label="عرض تفاصيل مسجد التحفيظ"><i class="fas fa-eye me-1" aria-hidden="true"></i>عرض</button>
            <?php if ($isAdmin): ?>
                <a class="btn btn-sm btn-primary" href="edit_quran_mosque.php?id=<?= $view->e($row['id']) ?>"><i class="fas fa-pen me-1" aria-hidden="true"></i>تعديل</a>
                <form method="POST" action="delete_quran_mosque.php" class="d-inline js-confirm-submit" data-confirm="هل أنت متأكد من حذف هذا المسجد؟">
                    <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                    <input type="hidden" name="id" value="<?= $view->e($row['id']) ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash-alt me-1" aria-hidden="true"></i>حذف</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</article>
