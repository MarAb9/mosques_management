<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h3 mb-1">سجل التدقيق</h1>
            <p class="text-muted mb-0">آخر العمليات الإدارية المسجلة في النظام.</p>
        </div>
        <form class="d-flex gap-2" method="get" action="audit.php">
            <input class="form-control" name="q" value="<?= $view->e($q) ?>" placeholder="بحث في السجل">
            <button class="btn btn-primary">بحث</button>
        </form>
    </div>
    <div class="card border-0 shadow-sm">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 app-table app-table--compact">
                <thead><tr><th>الوقت</th><th>العملية</th><th>النتيجة</th><th>المستخدم</th><th>المسار</th><th>السياق</th></tr></thead>
                <tbody>
                <?php if (empty($events)): ?>
                    <tr><td colspan="6" class="text-center text-muted py-5">لا توجد أحداث مطابقة.</td></tr>
                <?php endif; ?>
                <?php foreach ($events as $event): ?>
                    <tr>
                        <td class="small text-nowrap"><?= $view->e($event['timestamp'] ?? '') ?></td>
                        <td><span class="badge bg-primary-subtle text-primary"><?= $view->e($event['action'] ?? '') ?></span></td>
                        <td><?= $view->e($event['outcome'] ?? '') ?></td>
                        <td><?= $view->e($event['actor']['username'] ?? 'غير محدد') ?><br><span class="small text-muted"><?= $view->e($event['actor']['role'] ?? '') ?></span></td>
                        <td class="small"><?= $view->e($event['request']['route'] ?? '') ?></td>
                        <td><code class="small"><?= $view->e(json_encode($event['context'] ?? [], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE)) ?></code></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>