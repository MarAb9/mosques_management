<?php
$totalStudents = (int) ($row['total_male_students'] ?? 0) + (int) ($row['total_female_students'] ?? 0);
$totalSessions = (int) ($row['total_weekly_sessions'] ?? 0);
$programStatus = $row['top_work_program']['has_work_program'] ?? 'لا';
$responsibleText = implode('، ', array_slice($row['responsible_names'] ?? [], 0, 3));
?>
<article class="card quran-record-card mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start gap-3">
            <div><span class="page-kicker">برنامج رقم <?= $view->e($row['id']) ?></span><h3 class="h6 mt-1 mb-1"><i class="fas fa-mosque me-1 text-warning" aria-hidden="true"></i><?= $view->e($row['mosque_name']) ?></h3><p class="small text-muted mb-0"><?= $view->e($row['community']) ?></p></div>
            <span class="badge bg-light text-dark"><?= $view->e($row['national_code']) ?></span>
        </div>
        <div class="row g-2 mt-3">
            <div class="col-6"><div class="p-2 rounded bg-light"><small class="text-muted d-block">الطلبة</small><strong><?= number_format($totalStudents) ?> طالب</strong></div></div>
            <div class="col-6"><div class="p-2 rounded bg-light"><small class="text-muted d-block">الجلسات</small><strong><?= number_format($totalSessions) ?> أسبوعياً</strong></div></div>
        </div>
        <dl class="row small mt-3 mb-3"><dt class="col-5 text-muted">نوع البرنامج</dt><dd class="col-7"><?= $view->e($programStatus) ?></dd><dt class="col-5 text-muted">المسؤولون</dt><dd class="col-7"><?= $view->e($responsibleText ?: 'غير محدد') ?></dd><dt class="col-5 text-muted">الإقامة</dt><dd class="col-7"><?= $view->e($row['has_accommodation'] ?? 'لا') ?></dd></dl>
        <div class="d-flex flex-wrap gap-2">
            <button type="button" class="btn btn-sm btn-info text-white view-quran-mosque-btn" data-bs-toggle="modal" data-bs-target="#quranMosqueDetailsModal" data-mosque-id="<?= $view->e($row['id']) ?>"><i class="fas fa-eye me-1" aria-hidden="true"></i>التفاصيل</button>
            <?php if ($isAdmin): ?><a class="btn btn-sm btn-primary" href="edit_quran_mosque.php?id=<?= $view->e($row['id']) ?>"><i class="fas fa-pen me-1" aria-hidden="true"></i>تعديل</a><?php endif; ?>
        </div>
    </div>
</article>
