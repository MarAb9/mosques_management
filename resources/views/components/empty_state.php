<div class="empty-state">
    <div>
        <span class="empty-state__icon" aria-hidden="true"><i class="fas <?= $view->e($icon ?? 'fa-inbox') ?>"></i></span>
        <h3 class="h5 mt-3 mb-2"><?= $view->e($title ?? 'لا توجد بيانات') ?></h3>
        <?php if (!empty($message)): ?><p class="mb-0"><?= $view->e($message) ?></p><?php endif; ?>
    </div>
</div>
