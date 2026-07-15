<div class="admin-workspace">
    <?= $view->partial('components.page_header', [
        'title' => 'سلة المحذوفات',
        'subtitle' => count($deletedMosques) . ' سجل',
        'icon' => 'fa-trash-can-arrow-up',
        'illustration' => 'assets/images/institutional/backup-shield-3d.svg',
    ]) ?>

    <?php if ($successMessage !== null): ?><div class="alert alert-success" role="status"><i class="fas fa-circle-check me-2" aria-hidden="true"></i><?= $view->e($successMessage) ?></div><?php endif; ?>
    <?php if ($errorMessage !== null): ?><div class="alert alert-danger" role="alert"><i class="fas fa-circle-exclamation me-2" aria-hidden="true"></i><?= $view->e($errorMessage) ?></div><?php endif; ?>

    <div class="alert alert-warning"><i class="fas fa-shield-halved me-2" aria-hidden="true"></i>تأكد من هوية السجل قبل الاستعادة.</div>

    <section class="data-panel" aria-label="السجلات المحذوفة">
        <div class="data-panel__header"><div><span class="page-kicker">الأرشيف القابل للاستعادة</span><h2 id="trashTitle"><?= count($deletedMosques) ?> سجلاً في السلة</h2></div></div>
        <?php if (empty($deletedMosques)): ?>
            <?= $view->partial('components.empty_state', ['icon' => 'fa-trash-can-arrow-up', 'title' => 'سلة المحذوفات فارغة', 'message' => 'لا توجد مساجد مؤرشفة وقابلة للاستعادة حالياً.']) ?>
        <?php else: ?>
        <div class="row g-3 p-3">
            <?php foreach ($deletedMosques as $entry): $mosque = $entry['mosque'] ?? []; ?>
            <div class="col-xl-4 col-md-6">
                <article class="card h-100 border-0 mosque-mobile-card">
                    <div class="card-body">
                        <div class="d-flex align-items-start justify-content-between gap-3 mb-3"><span class="metric-card__icon"><i class="fas fa-mosque" aria-hidden="true"></i></span><span class="badge bg-light text-dark"><?= $view->e($mosque['national_code'] ?? '') ?></span></div>
                        <h3 class="h6 mb-2"><?= $view->e($mosque['mosque_name'] ?? 'مسجد غير مسمى') ?></h3>
                        <dl class="row small mb-3"><dt class="col-5 text-muted">وقت الحذف</dt><dd class="col-7"><?= $view->e($entry['deleted_at'] ?? '') ?></dd><dt class="col-5 text-muted">حذف بواسطة</dt><dd class="col-7"><?= $view->e($entry['deleted_by']['username'] ?? 'غير محدد') ?></dd></dl>
                        <form method="post" action="restore_mosque.php" class="js-confirm-submit" data-confirm="تأكيد استعادة المسجد؟">
                            <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
                            <input type="hidden" name="registration_number" value="<?= $view->e($mosque['registration_number'] ?? '') ?>">
                            <button class="btn btn-success w-100"><i class="fas fa-rotate-left me-2" aria-hidden="true"></i>استعادة السجل</button>
                        </form>
                    </div>
                </article>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </section>
</div>
