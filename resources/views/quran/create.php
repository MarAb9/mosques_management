<?php
/**
 * Add Quran program form.
 * Expects: $mosques, $scheduleOptions, $positionOptions
 */
?>


<div class="container-fluid py-4">
    <?php $formActions = '<img src="assets/images/institutional/quran-book-3d.svg" width="64" height="64" alt="" aria-hidden="true">'
        . '<a href="quran_mosques.php" class="btn btn-outline-secondary align-self-center"><i class="fas fa-arrow-left me-1" aria-hidden="true"></i>رجوع</a>'; ?>
    <?= $view->partial('components.page_header', ['title' => 'إضافة مسجد تحفيظ', 'actionsHtml' => $formActions]) ?>
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger" role="alert"><?= $view->e($errorMessage) ?></div>
    <?php endif; ?>
    <div class="row">
        <div class="col-lg-10 col-xl-8 mx-auto">
            <div class="card border-0">

                <div class="card-body p-4">
                    <form method="post" action="add_quran_mosque.php" id="quranForm" data-quran-mode="create">
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
                                                    data-administrative="<?= htmlspecialchars($m['administrative_attachment'] ?? '') ?>">
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
                                                    <option value="لا">لا</option>
                                                    <option value="نعم">نعم</option>
                                                    <option value="مركز تحفيظ">مركز تحفيظ</option>
                                                </select>
                                                <label for="has_quran_school">البرنامج القرآني</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="has_accommodation" name="has_accommodation">
                                                    <option value="لا">لا</option>
                                                    <option value="نعم">نعم</option>
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
                                        <div class="alert alert-info text-center">
                                            <i class="fas fa-info-circle me-2" aria-hidden="true"></i>لا يوجد مسؤولون.
                                        </div>
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
                                    <!-- Students sections will be added dynamically -->
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


<script type="application/json" id="quranPageData" nonce="<?= $view->e($cspNonce ?? '') ?>"><?= json_encode(['positionOptions' => $positionOptions, 'scheduleOptions' => $scheduleOptions], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE) ?></script>
