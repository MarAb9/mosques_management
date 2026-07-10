<?php
/**
 * One Quran program table row (legacy renderQuranMosqueRow, verbatim).
 * Expects: $row (with top_work_program + responsible_names precomputed),
 *          $animationDelay, $isAdmin, $csrfToken
 */

$accommodationIcon = $row['has_accommodation'] == 'نعم' ? 'fa-check-circle text-success' : 'fa-times-circle text-danger';

// Use the aggregated statistics from the query
$totalStudents = ($row['total_male_students'] ?? 0) + ($row['total_female_students'] ?? 0);
$totalSessions = $row['total_weekly_sessions'] ?? 0;

// Determine Quran school status based on responsibles
$quranSchoolStatus = 'لا';
$badgeClass = 'bg-secondary';
$tooltipText = 'لا يوجد كتاب قرآني';

$statusResult = $row['top_work_program'];
if ($statusResult) {
    $quranSchoolStatus = $statusResult['has_work_program'];
    if ($quranSchoolStatus === 'نعم') {
        $badgeClass = 'bg-success';
        $tooltipText = 'يوجد كتاب قرآني';
    } elseif ($quranSchoolStatus === 'مركز تحفيظ') {
        $badgeClass = 'bg-primary';
        $tooltipText = 'مركز تحفيظ';
    }
}

$responsibleText = implode(', ', $row['responsible_names']);
if (($row['responsible_count'] ?? 0) > 3) {
    $responsibleText .= ' +' . ($row['responsible_count'] - 3) . ' أكثر';
}
?>
                                    <tr class="animate__animated animate__fadeInUp" style="animation-delay: <?= $animationDelay ?>s">
                                        <td>
                                            <input type="checkbox" name="selected_mosques[]" value="<?= e($row['id']) ?>" class="form-check-input mosque-checkbox">
                                        </td>
                                        <td class="fw-bold text-muted"><?= e($row['id']) ?></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-mosque text-primary me-2"></i>
                                                <span><?= e($row['mosque_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><span class="badge bg-light text-dark"><?= e($row['national_code']) ?></span></td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-user-tie text-info me-2"></i>
                                                <span><?= e($responsibleText) ?></span>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge <?= $badgeClass ?>" data-bs-toggle="tooltip" title="<?= $tooltipText ?>">
                                                <?= e($quranSchoolStatus) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="d-flex justify-content-center" data-bs-toggle="tooltip" title="<?= $row['has_accommodation'] == 'نعم' ? 'يوجد إقامة' : 'لا يوجد إقامة' ?>">
                                                <i class="fas <?= $accommodationIcon ?> fa-lg"></i>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?= $totalSessions ?> جلسات</span>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?= $totalStudents ?><?= ($totalStudents >= 2 && $totalStudents <= 10 ? ' طلاب' : ' طالب') ?></span>
                                        </td>
                                        <td><span class="badge bg-info"><?= e($row['community']) ?></span></td>
                                        <td>
                                            <div class="d-flex gap-2">
                                                <a href="#"
                                                class="btn btn-sm btn-icon btn-info rounded-circle view-quran-mosque-btn"
                                                data-bs-toggle="modal"
                                                data-bs-target="#quranMosqueDetailsModal"
                                                data-mosque-id="<?= e($row['id']) ?>"
                                                data-bs-tooltip="tooltip"
                                                data-bs-placement="top"
                                                title="عرض التفاصيل">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                               <?php if ($isAdmin): ?>
                                            <a href="edit_quran_mosque.php?id=<?= e($row['id']) ?>"
                                                class="btn btn-sm btn-icon btn-primary rounded-circle"
                                                data-bs-toggle="tooltip"
                                                data-bs-placement="top"
                                                title="تعديل">
                                                    <i class="fas fa-pen"></i>
                                                </a>
                                            <form method="POST" action="delete_quran_mosque.php" class="d-inline" onsubmit="return confirm('هل أنت متأكد من حذف هذا المسجد؟')">
                                                <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                                <input type="hidden" name="id" value="<?= e($row['id']) ?>">
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
