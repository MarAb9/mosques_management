<?php
/**
 * One mosque table row.
 * Expects: $row, $animationDelay, $isAdmin, $csrfToken
 */

$fridayIcon = $row['friday_prayer'] == 'نعم' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';
$statusIcon = ($row['status'] == 'مفتوح') ? 'fa-check-circle text-success' : (($row['status'] == 'مغلق') ? 'fa-times-circle text-danger' : 'fa-times-circle text-warning');
?>
    <tr class="animate__animated animate__fadeInUp" style="animation-delay: <?= $animationDelay ?>s">
        <td>
            <input type="checkbox" name="selected_mosques[]" value="<?= e($row['registration_number']) ?>" class="form-check-input mosque-checkbox">
        </td>
        <td class="fw-bold text-muted"><?= e($row['registration_number']) ?></td>
        <td>
            <div class="d-flex align-items-center">
                <i class="fas fa-mosque text-primary me-2"></i>
                <span><?= e($row['mosque_name']) ?></span>
            </div>
        </td>
        <td class="mobile-hidden">
            <div class="d-flex align-items-center">
                <i class="fas fa-map-marker-alt text-danger me-2"></i>
                <small class="text-muted"><?= e($row['address']) ?></small>
            </div>
        </td>
        <td><span class="badge bg-light text-dark"><?= e($row['national_code']) ?></span></td>
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
                <span><?= e($row['imam_name']) ?></span>
            </div>
        </td>
        <td>
            <div class="d-flex align-items-center">
                <i class="fas fa-user-tie fs-4 text-warning me-2"></i>
                <span><?= e($row['guide_imam_display'] ?: $row['guide_imam']) ?></span>
            </div>
        </td>
        <td><span class="badge bg-info"><?= e($row['community']) ?></span></td>
        <!-- ADD THIS CELL FOR GPS LOCATION -->
        <td class="mobile-hidden">
            <?php if (!empty($row['latitude']) && !empty($row['longitude'])): ?>
        <button class="btn btn-sm btn-outline-primary view-on-map"
                data-lat="<?= e($row['latitude']) ?>"
                data-lng="<?= e($row['longitude']) ?>"
                data-mosque="<?= e($row['mosque_name']) ?>"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
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
                data-mosque-id="<?= e($row['registration_number']) ?>"
                data-bs-tooltip="tooltip"
                data-bs-placement="top"
                title="عرض التفاصيل">
                    <i class="fas fa-eye"></i>
                </a>

                <!-- EDIT/DELETE BUTTONS - ONLY FOR ADMIN -->
                <?php if ($isAdmin): ?>
        <a href="edit_mosque.php?id=<?= e($row['registration_number']) ?>"
            class="btn btn-sm btn-icon btn-primary rounded-circle"
            data-bs-toggle="tooltip"
            data-bs-placement="top"
            title="تعديل">
                <i class="fas fa-pen"></i>
            </a>
        <form method="POST" action="delete_mosque.php" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المسجد؟')">
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
            <input type="hidden" name="id" value="<?= e($row['registration_number']) ?>">
            <button type="submit"
                class="btn btn-sm btn-icon btn-danger rounded-circle"
                data-bs-toggle="tooltip"
                data-bs-placement="top"
                title="حذف">
                    <i class="fas fa-trash-alt"></i>
            </button>
        </form>
                <?php endif; ?>
            </div>
        </td>
    </tr>
