<?php
/**
 * One mosque table row.
 * Expects: $row, $animationDelay, $isAdmin, $canEditContent, $canDeleteContent, $csrfToken
 */

$fridayIcon = $row['friday_prayer'] == 'نعم' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
$statusIcon = ($row['status'] == 'مفتوح') ? 'fa-check-circle text-success' : (($row['status'] == 'مغلق') ? 'fa-times-circle text-danger' : 'fa-times-circle text-warning');
?>
    <tr class="mosque-table-row reveal">
        <td>
            <input type="checkbox" name="selected_mosques[]" value="<?= $view->e($row['registration_number']) ?>" class="form-check-input mosque-checkbox">
        </td>
        <td class="fw-bold text-muted"><?= $view->e($row['registration_number']) ?></td>
        <td>
            <div class="d-flex align-items-center">
                <i class="fas fa-mosque text-primary me-2"></i>
                <span><?= $view->e($row['mosque_name']) ?></span>
            </div>
        </td>
        <td class="mobile-hidden">
            <div class="d-flex align-items-center">
                <i class="fas fa-map-marker-alt text-danger me-2"></i>
                <small class="text-muted"><?= $view->e($row['address']) ?></small>
            </div>
        </td>
        <td><span class="badge bg-light text-dark"><?= $view->e($row['national_code']) ?></span></td>
        <td>
            <span class="d-flex justify-content-center" data-bs-toggle="tooltip" title="<?= $row['friday_prayer'] == 'نعم' ? 'يوجد صلاة جمعة' : 'لا يوجد صلاة جمعة' ?>">
                <i class="fas <?= $fridayIcon ?> fa-lg"></i>
            </span>
        </td>
        <td>
            <span class="d-flex justify-content-center" data-bs-toggle="tooltip" title="<?= ($row['status'] == 'مفتوح') ? 'مسجد مفتوح' : (($row['status'] == 'مغلق') ? 'مسجد مغلق' : 'مسجد مفتوح بدون ترخيص') ?>">
                <i class="fas <?= $statusIcon ?> fa-lg"></i>
            </span>
        </td>
        <td class="mobile-hidden">
        <span class="badge bg-primary-gradient"><?= $row['construction_date'] ? date('Y', strtotime($row['construction_date'])) : '' ?></span></td>
        <td>
            <div class="d-flex align-items-center">
                <i class="fas fa-user fs-4 text-info me-2"></i>
                <span><?= $view->e($row['imam_name']) ?></span>
            </div>
        </td>
        <td>
            <div class="d-flex align-items-center">
                <i class="fas fa-user-tie fs-4 text-warning me-2"></i>
                <span><?= $view->e($row['guide_imam_display'] ?: $row['guide_imam']) ?></span>
            </div>
        </td>
        <td><span class="badge bg-info"><?= $view->e($row['community']) ?></span></td>
        <!-- ADD THIS CELL FOR GPS LOCATION -->
        <td class="mobile-hidden">
            <?php if (!empty($row['latitude']) && !empty($row['longitude'])): ?>
        <button class="btn btn-sm btn-outline-primary view-on-map"
                data-lat="<?= $view->e($row['latitude']) ?>"
                data-lng="<?= $view->e($row['longitude']) ?>"
                data-mosque="<?= $view->e($row['mosque_name']) ?>"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                aria-label="عرض المسجد على الخريطة"
                title="عرض على الخريطة">
            <i class="fas fa-map-marked-alt"></i>
        </button>
            <?php else: ?>
        <span class="text-muted" data-bs-toggle="tooltip" data-bs-placement="top" title="لم يتم تحديد الموقع">غير محدد</span>
            <?php endif; ?>
        </td>
        <td>
            <div class="d-flex gap-2">
                <!-- VIEW BUTTON - ALWAYS VISIBLE -->
                <a href="#" class="btn btn-sm btn-icon btn-info rounded-circle view-mosque-btn"
                data-bs-toggle="modal"
                data-bs-target="#mosqueDetailsModal"
                data-mosque-id="<?= $view->e($row['registration_number']) ?>"
                data-bs-tooltip="tooltip"
                data-bs-placement="top"
                aria-label="عرض تفاصيل المسجد"
                title="عرض التفاصيل">
                    <i class="fas fa-eye"></i>
                </a>

                <!-- EDIT/DELETE BUTTONS - ONLY FOR ADMIN -->
                <?php if ($canEditContent ?? $isAdmin): ?>
        <a href="edit_mosque.php?id=<?= $view->e($row['registration_number']) ?>"
            class="btn btn-sm btn-icon btn-primary rounded-circle"
            data-bs-toggle="tooltip"
            data-bs-placement="top"
            aria-label="تعديل بيانات المسجد"
            title="تعديل">
                <i class="fas fa-pen"></i>
            </a>
        <?php if ($canDeleteContent ?? $isAdmin): ?>

        <form method="POST" action="delete_mosque.php" class="d-inline js-confirm-submit" data-confirm="هل أنت متأكد من حذف هذا المسجد؟">
            <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= $view->e($row['registration_number']) ?>">
            <button type="submit"
                class="btn btn-sm btn-icon btn-danger rounded-circle"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                aria-label="حذف المسجد"
                title="حذف">
                    <i class="fas fa-trash-alt"></i>
            </button>
        </form>
                        <?php endif; ?>
                <?php endif; ?>
            </div>
        </td>
    </tr>
