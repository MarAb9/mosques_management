<?php
$canEdit = $canEditContent ?? $isAdmin;
$canDelete = $canDeleteContent ?? $isAdmin;
$isOpen = ($row['status'] ?? '') === 'مفتوح';
$statusClass = $isOpen ? 'text-success bg-success-subtle' : (($row['status'] ?? '') === 'مغلق' ? 'text-danger bg-danger-subtle' : 'text-warning bg-warning-subtle');
$constructionYear = !empty($row['construction_date']) ? date('Y', strtotime((string) $row['construction_date'])) : '—';
?>
<tr class="mosque-table-row reveal">
    <?php if ($canDelete): ?>
        <td data-column="selection"><label class="visually-hidden" for="mosque-<?= $view->e($row['registration_number']) ?>">تحديد <?= $view->e($row['mosque_name']) ?></label><input type="checkbox" id="mosque-<?= $view->e($row['registration_number']) ?>" name="selected_mosques[]" value="<?= $view->e($row['registration_number']) ?>" class="form-check-input mosque-checkbox"></td>
    <?php endif; ?>
    <td data-column="registration" class="text-muted"><?= $view->e($row['registration_number']) ?></td>
    <td data-column="name"><strong class="record-name"><i class="fas fa-mosque" aria-hidden="true"></i><?= $view->e($row['mosque_name']) ?></strong></td>
    <td data-column="address"><span class="record-address"><?= $view->e($row['address'] ?: '—') ?></span></td>
    <td data-column="national"><span class="badge bg-light text-dark"><?= $view->e($row['national_code'] ?: '—') ?></span></td>
    <td data-column="friday" class="column-hidden"><?= $view->e($row['friday_prayer'] ?: '—') ?></td>
    <td data-column="status"><span class="status-badge <?= $statusClass ?>"><?= $view->e($row['status'] ?: 'غير محدد') ?></span></td>
    <td data-column="construction" class="column-hidden"><?= $view->e($constructionYear) ?></td>
    <td data-column="imam"><?= $view->e($row['imam_name'] ?: '—') ?></td>
    <td data-column="guide" class="column-hidden"><?= $view->e(($row['guide_imam_display'] ?: $row['guide_imam']) ?: '—') ?></td>
    <td data-column="community"><?= $view->e($row['community'] ?: '—') ?></td>
    <td data-column="location" class="column-hidden">
        <?php if (!empty($row['latitude']) && !empty($row['longitude'])): ?>
            <button type="button" class="row-action view-on-map" data-mosque-id="<?= $view->e($row['registration_number']) ?>" data-mosque="<?= $view->e($row['mosque_name']) ?>" aria-label="عرض المسجد على الخريطة" title="عرض على الخريطة"><i class="fas fa-map-location-dot" aria-hidden="true"></i></button>
        <?php else: ?>—<?php endif; ?>
    </td>
    <td data-column="actions">
        <div class="record-actions">
            <button type="button" class="row-action view-mosque-btn" data-bs-toggle="modal" data-bs-target="#mosqueDetailsModal" data-mosque-id="<?= $view->e($row['registration_number']) ?>" aria-label="عرض تفاصيل المسجد" title="عرض"><i class="fas fa-eye" aria-hidden="true"></i></button>
            <?php if ($canEdit): ?><a class="row-action" href="edit_mosque.php?id=<?= $view->e($row['registration_number']) ?>" aria-label="تعديل بيانات المسجد" title="تعديل"><i class="fas fa-pen" aria-hidden="true"></i></a><?php endif; ?>
            <?php if ($canDelete): ?>
                <form method="POST" action="delete_mosque.php" class="js-confirm-submit" data-confirm="هل أنت متأكد من حذف هذا المسجد؟">
                    <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>"><input type="hidden" name="id" value="<?= $view->e($row['registration_number']) ?>">
                    <button type="submit" class="row-action row-action--danger" aria-label="حذف المسجد" title="حذف"><i class="fas fa-trash" aria-hidden="true"></i></button>
                </form>
            <?php endif; ?>
        </div>
    </td>
</tr>
