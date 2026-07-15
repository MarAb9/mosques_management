<div class="empty-state">
    <div>
        <img class="empty-state__image" src="assets/images/institutional/empty-records-3d.svg" alt="" aria-hidden="true">
        <h3 class="h5 mt-3 mb-2"><?= $view->e($title ?? 'لا توجد بيانات') ?></h3>
        <?php if (!empty($message)): ?><p class="mb-0"><?= $view->e($message) ?></p><?php endif; ?>
    </div>
</div>