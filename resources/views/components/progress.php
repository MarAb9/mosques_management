<?php $safeValue = max(0, min(100, (float) ($value ?? 0))); ?>
<div class="progress-meter" data-progress="<?= $view->e((string) $safeValue) ?>" role="progressbar" aria-label="<?= $view->e($label ?? 'نسبة الإنجاز') ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $view->e((string) $safeValue) ?>"></div>
