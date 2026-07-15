<header class="page-header reveal">
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap position-relative z-1">
        <div>
            <h1 class="mb-1"><?php if (!empty($icon)): ?><i class="fas <?= $view->e($icon) ?> me-2 text-muted" aria-hidden="true"></i><?php endif; ?><?= $view->e($title ?? '') ?></h1>
            <?php if (!empty($subtitle)): ?><p class="mb-0"><?= $view->e($subtitle) ?></p><?php endif; ?>
        </div>
        <div class="page-header__aside">
            <?php if (!empty($illustration)): ?><img class="page-header__image" src="<?= $view->e($illustration) ?>" alt="" aria-hidden="true"><?php endif; ?>
            <?php if (!empty($actionsHtml)): ?><div class="d-flex flex-wrap gap-2"><?= $actionsHtml ?></div><?php endif; ?>
        </div>
    </div>
</header>