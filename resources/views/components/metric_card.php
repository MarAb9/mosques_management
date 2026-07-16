<?php
$variant = $variant ?? '';
$href = $href ?? null;
$tag = $href ? 'a' : 'article';
?>
<<?= $tag ?> class="metric-card<?= $variant !== '' ? ' metric-card--' . $view->e($variant) : '' ?> reveal"<?= $href ? ' href="' . $view->e($href) . '"' : '' ?>>
    <div class="metric-card__top">
        <span class="metric-card__label"><?= $view->e($label ?? '') ?></span>
        <?php if (!empty($image)): ?>
            <img class="metric-card__image" src="<?= $view->e($image) ?>" alt="" aria-hidden="true">
        <?php else: ?>
            <span class="metric-card__icon" aria-hidden="true"><i class="fas <?= $view->e($icon ?? 'fa-chart-simple') ?>"></i></span>
        <?php endif; ?>
    </div>
    <div>
        <div class="metric-card__value"<?= isset($countUp) ? ' data-count-up="' . $view->e((string) $countUp) . '"' : '' ?>><?= $view->e($value ?? '0') ?></div>
        <?php if (!empty($context)): ?><div class="metric-card__context"><?= $view->e($context) ?></div><?php endif; ?>
    </div>
</<?= $tag ?>>