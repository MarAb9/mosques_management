<?php
$actions = '<form class="d-flex gap-2" method="get" action="audit.php" role="search"><label class="visually-hidden" for="auditSearch">بحث في سجل التدقيق</label><input class="form-control" id="auditSearch" name="q" value="' . $view->e($q) . '" placeholder="عملية، مستخدم، أو مسار"><button class="btn btn-primary"><i class="fas fa-search me-1" aria-hidden="true"></i>بحث</button></form>';
?>
<div class="admin-workspace">
    <?= $view->partial('components.page_header', [
        'kicker' => 'المساءلة والتتبع',
        'title' => 'سجل التدقيق',
        'subtitle' => 'سياق منظم لآخر العمليات الإدارية ونتائجها والمسارات التي نفذتها.',
        'icon' => 'fa-clock-rotate-left',
        'actionsHtml' => $actions,
    ]) ?>

    <?php if ($q !== ''): ?><div class="alert alert-info d-flex justify-content-between align-items-center gap-3"><span><i class="fas fa-filter me-2" aria-hidden="true"></i>النتائج المطابقة لـ «<?= $view->e($q) ?>»</span><a class="btn btn-sm btn-outline-info" href="audit.php">مسح البحث</a></div><?php endif; ?>

    <section class="data-panel" aria-labelledby="auditEventsTitle">
        <div class="data-panel__header"><div><span class="page-kicker">آخر العمليات</span><h2 id="auditEventsTitle"><?= count($events) ?> حدثاً معروضاً</h2></div><span class="role-badge"><i class="fas fa-shield-halved" aria-hidden="true"></i><span>سجل للقراءة فقط</span></span></div>
        <?php if (empty($events)): ?>
            <?= $view->partial('components.empty_state', ['icon' => 'fa-magnifying-glass', 'title' => 'لا توجد أحداث مطابقة', 'message' => 'غيّر عبارة البحث أو اعرض سجل العمليات الكامل.']) ?>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table app-table audit-table align-middle mb-0">
                <thead><tr><th>الوقت</th><th>العملية</th><th>النتيجة</th><th>الفاعل</th><th>المسار</th><th>السياق</th></tr></thead>
                <tbody>
                <?php foreach ($events as $event): $success = ($event['outcome'] ?? '') === 'success'; ?>
                    <tr>
                        <td class="small text-nowrap"><i class="far fa-clock me-1 text-muted" aria-hidden="true"></i><?= $view->e($event['timestamp'] ?? '') ?></td>
                        <td><strong><?= $view->e($event['action'] ?? '') ?></strong></td>
                        <td><span class="status-badge <?= $success ? 'text-success bg-success-subtle' : 'text-warning bg-warning-subtle' ?>"><?= $view->e($event['outcome'] ?? '') ?></span></td>
                        <td><span class="fw-bold"><?= $view->e($event['actor']['username'] ?? 'غير محدد') ?></span><br><small class="text-muted"><?= $view->e($event['actor']['role'] ?? '') ?></small></td>
                        <td><code><?= $view->e($event['request']['route'] ?? '') ?></code></td>
                        <td class="audit-context"><details><summary class="btn btn-sm btn-outline-secondary">عرض التفاصيل</summary><code class="d-block mt-2"><?= $view->e(json_encode($event['context'] ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PRETTY_PRINT)) ?></code></details></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </section>
</div>
