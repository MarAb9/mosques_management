<?php
/**
 * Edit Quran program form.
 * Expects: $program, $programId, $responsibles, $mosques,
 *          $scheduleOptions, $positionOptions
 */
?>


<div class="container-fluid py-4">
    <?php $formActions = '<img src="assets/images/institutional/quran-book-3d.svg" width="64" height="64" alt="" aria-hidden="true">'
        . '<a href="quran_mosques.php" class="btn btn-outline-secondary align-self-center"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>رجوع</a>'; ?>
    <?= $view->partial('components.page_header', ['title' => 'تعديل مسجد تحفيظ', 'actionsHtml' => $formActions]) ?>
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger" role="alert"><?= $view->e($errorMessage) ?></div>
    <?php endif; ?>
    <div class="row">
        <div class="col-lg-10 col-xl-8 mx-auto">
            <div class="card border-0">

                <div class="card-body p-4">
                    <form method="post" action="edit_quran_mosque.php?id=<?= $programId ?>" id="quranForm" data-quran-mode="edit">
                        <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">

                        <!-- Progress Steps -->
                        <div class="step-progress mb-5">
                            <div class="steps">
                                <div class="step active" data-step="1">
                                    <div class="step-icon">1</div>
                                    <div class="step-label">البيانات</div>
                                </div>
                                <div class="step" data-step="2">
                                    <div class="step-icon">2</div>
                                    <div class="step-label">المسؤولون</div>
                                </div>
                                <div class="step" data-step="3">
                                    <div class="step-icon">3</div>
                                    <div class="step-label">الطلبة</div>
                                </div>
                            </div>
                        </div>

                        <!-- Step 1: Basic Information -->
                        <div class="step-content active" data-step="1">
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <div class="form-floating mb-3">
                                        <select class="form-select select2-enhanced mosque-select" id="mosque_registration_number" name="mosque_registration_number" required>
                                            <option value="">اختر المسجد</option>
                                            <?php foreach ($mosques as $m): ?>
                                                <option value="<?= $m['id'] ?>"
                                                    data-pashalik="<?= htmlspecialchars($m['pashalik'] ?? '') ?>"
                                                    data-circle="<?= htmlspecialchars($m['circle'] ?? '') ?>"
                                                    data-leadership="<?= htmlspecialchars($m['leadership'] ?? '') ?>"
                                                    data-community="<?= htmlspecialchars($m['community'] ?? '') ?>"
                                                    data-administrative="<?= htmlspecialchars($m['administrative_attachment'] ?? '') ?>"
                                                    <?= $m['id'] == $program['mosque_registration_number'] ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($m['mosque_name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <label for="mosque_registration_number">اسم المسجد</label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <h2 class="h6 mb-3 text-muted">خصائص البرنامج</h2>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="has_quran_school" name="has_quran_school" required>
                                                    <option value="لا" <?= $program['has_quran_school'] == 'لا' ? 'selected' : '' ?>>لا</option>
                                                    <option value="نعم" <?= $program['has_quran_school'] == 'نعم' ? 'selected' : '' ?>>نعم</option>
                                                    <option value="مركز تحفيظ" <?= $program['has_quran_school'] == 'مركز تحفيظ' ? 'selected' : '' ?>>مركز تحفيظ</option>
                                                </select>
                                                <label for="has_quran_school">البرنامج القرآني</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="has_accommodation" name="has_accommodation">
                                                    <option value="لا" <?= $program['has_accommodation'] == 'لا' ? 'selected' : '' ?>>لا</option>
                                                    <option value="نعم" <?= $program['has_accommodation'] == 'نعم' ? 'selected' : '' ?>>نعم</option>
                                                </select>
                                                <label for="has_accommodation">الإقامة</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-end mt-5 pt-3 border-top">
                                <button type="button" class="btn btn-primary px-4 next-step">
                                    التالي <i class="fas fa-arrow-left me-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 2: Program Details -->
                        <div class="step-content" data-step="2">
                            <div class="row g-4">
                                <div class="col-12">
                                    <div class="d-flex justify-content-between align-items-center mb-3">
                                        <h6 class="mb-0 text-muted"><i class="fas fa-users me-2 text-primary"></i>المسؤولون</h6>
                                        <button type="button" class="btn btn-sm btn-primary add-responsible-btn">
                                            <i class="fas fa-plus me-1"></i> إضافة مسؤول
                                        </button>
                                    </div>

                                    <div id="responsibles-container">
                                        <?php if (empty($responsibles)): ?>
                                            <div class="alert alert-info text-center">
                                                <i class="fas fa-info-circle me-2" aria-hidden="true"></i>لا يوجد مسؤولون.
                                            </div>
                                        <?php else: ?>
                                            <?php foreach ($responsibles as $index => $responsible): ?>
                                                <div class="responsible-item" data-index="<?= $index ?>">
                                                    <button type="button" class="remove-responsible" aria-label="حذف المسؤول"><i class="fas fa-times-circle"></i></button>
                                                    <div class="responsible-header">
                                                        <span class="responsible-title">مسؤول <?= $index + 1 ?></span>
                                                    </div>
                                                    <div class="row">
                                                        <div class="col-md-6">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" class="form-control" name="responsibles[<?= $index ?>][name]" placeholder="اسم المسؤول" value="<?= htmlspecialchars($responsible['responsible_name']) ?>" required>
                                                                <label>اسم المسؤول</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-floating mb-3">
                                                                <input type="text" class="form-control" name="responsibles[<?= $index ?>][national_id]" placeholder="رقم البطاقة" value="<?= htmlspecialchars($responsible['responsible_national_id']) ?>">
                                                                <label>رقم البطاقة الوطنية</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-floating mb-3">
                                                                <select class="form-select" name="responsibles[<?= $index ?>][position]">
                                                                    <option value="">اختر المنصب</option>
                                                                    <?php foreach ($positionOptions as $option): ?>
                                                                        <option value="<?= htmlspecialchars($option) ?>" <?= $responsible['responsible_position'] == $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <label>المنصب</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-floating mb-3">
                                                                <select class="form-select" name="responsibles[<?= $index ?>][has_work_program]">
                                                                    <option value="لا" <?= $responsible['has_work_program'] == 'لا' ? 'selected' : '' ?>>لا</option>
                                                                    <option value="نعم" <?= $responsible['has_work_program'] == 'نعم' ? 'selected' : '' ?>>نعم</option>
                                                                </select>
                                                                <label>برنامج عمل</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-floating mb-3">
                                                                <select class="form-select" name="responsibles[<?= $index ?>][memorization_schedule]">
                                                                    <?php foreach ($scheduleOptions as $option): ?>
                                                                        <option value="<?= htmlspecialchars($option) ?>" <?= $responsible['memorization_schedule'] == $option ? 'selected' : '' ?>><?= htmlspecialchars($option) ?></option>
                                                                    <?php endforeach; ?>
                                                                </select>
                                                                <label>جدول الحفظ</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-floating mb-3">
                                                                <input type="number" class="form-control" name="responsibles[<?= $index ?>][weekly_sessions]" min="0" value="<?= $responsible['weekly_sessions'] ?>">
                                                                <label>الجلسات الأسبوعية</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-floating mb-3">
                                                                <input type="number" step="0.5" class="form-control" name="responsibles[<?= $index ?>][session_hours]" min="0" value="<?= $responsible['session_hours'] ?>">
                                                                <label>ساعات الجلسة</label>
                                                            </div>
                                                        </div>
                                                        <div class="col-md-6">
                                                            <div class="form-floating mb-3">
                                                                <select class="form-select" name="responsibles[<?= $index ?>][regular_attendance]">
                                                                    <option value="لا" <?= $responsible['regular_attendance'] == 'لا' ? 'selected' : '' ?>>لا</option>
                                                                    <option value="نعم" <?= $responsible['regular_attendance'] == 'نعم' ? 'selected' : '' ?>>نعم</option>
                                                                </select>
                                                                <label>انتظام الحضور</label>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-5 pt-3 border-top">
                                <button type="button" class="btn btn-outline-secondary prev-step">
                                    <i class="fas fa-arrow-right me-1"></i> السابق
                                </button>
                                <button type="button" class="btn btn-primary px-4 next-step">
                                    التالي <i class="fas fa-arrow-left me-1"></i>
                                </button>
                            </div>
                        </div>

                        <!-- Step 3: Challenges and Notes -->
                        <div class="step-content" data-step="3">
                            <div class="row g-4">
                                <div id="students-container">
                                    <?php if (!empty($responsibles)): ?>
                                        <?php foreach ($responsibles as $index => $responsible): ?>
                                            <div class="responsible-students" data-index="<?= $index ?>">
                                                <h2 class="h6 mb-3 text-muted">طلبة المسؤول <?= $index + 1 ?></h2>
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <div class="form-floating mb-3">
                                                            <input type="number" class="form-control" name="responsibles[<?= $index ?>][male_students]" min="0" value="<?= $responsible['male_students'] ?>">
                                                            <label>الطلاب</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <div class="form-floating mb-3">
                                                            <input type="number" class="form-control" name="responsibles[<?= $index ?>][female_students]" min="0" value="<?= $responsible['female_students'] ?>">
                                                            <label>الطالبات</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-floating mb-3">
                                                            <textarea class="form-control" name="responsibles[<?= $index ?>][challenges]" rows="3" placeholder=" "><?= htmlspecialchars($responsible['challenges']) ?></textarea>
                                                            <label>التحديات</label>
                                                        </div>
                                                    </div>
                                                    <div class="col-12">
                                                        <div class="form-floating mb-3">
                                                            <textarea class="form-control" name="responsibles[<?= $index ?>][notes_suggestions]" rows="3" placeholder=" "><?= htmlspecialchars($responsible['notes_suggestions']) ?></textarea>
                                                            <label>ملاحظات</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <hr class="my-4">
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>

                                <div class="col-12">
                                    <div class="card border-0 shadow-sm mb-3">
                                        <div class="card-body">
                                            <h2 class="h6 mb-3 text-primary">المراجعة</h2>
                                            <div class="review-summary p-3 bg-light rounded" id="reviewSummary"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-5 pt-3 border-top">
                                <button type="button" class="btn btn-outline-secondary prev-step">
                                    <i class="fas fa-arrow-right me-1"></i> السابق
                                </button>
                                <button type="submit" class="btn btn-success px-4">
                                    <i class="fas fa-save me-2" aria-hidden="true"></i>حفظ
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>


<script type="application/json" id="quranPageData" nonce="<?= $view->e($cspNonce ?? '') ?>"><?= json_encode(['positionOptions' => $positionOptions, 'scheduleOptions' => $scheduleOptions, 'responsibleCount' => count($responsibles)], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
