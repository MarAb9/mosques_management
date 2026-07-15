<header class="page-header reveal">
    <?php if (!empty($kicker)): ?><span class="page-kicker"><?= $view->e($kicker) ?></span><?php endif; ?>
    <div class="d-flex align-items-start justify-content-between gap-3 flex-wrap position-relative z-1">
        <div>
            <h1 class="mb-2"><?php if (!empty($icon)): ?><i class="fas <?= $view->e($icon) ?> me-2 text-muted" aria-hidden="true"></i><?php endif; ?><?= $view->e($title ?? '') ?></h1>
            <?php if (!empty($subtitle)): ?><p class="mb-0"><?= $view->e($subtitle) ?></p><?php endif; ?>
        </div>
        <?php if (!empty($actionsHtml)): ?><div class="d-flex flex-wrap gap-2"><?= $actionsHtml ?></div><?php endif; ?>
    </div>
</header>
