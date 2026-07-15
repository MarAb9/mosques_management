<?php
/**
 * Add Quran program form (legacy add_quran_mosque.php markup, verbatim —
 * including the nested document structure the legacy page produced).
 * Expects: $mosques, $scheduleOptions, $positionOptions
 */
?>


<div class="container-fluid py-4">
    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger" role="alert"><?= $view->e($errorMessage) ?></div>
    <?php endif; ?>
    <div class="row animate__animated animate__fadeIn">
        <div class="col-lg-10 col-xl-8 mx-auto">
            <div class="card border-0">
                <div class="card-header text-white py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="card-title mb-1 fw-bold">
                                <i class="fas fa-plus-circle me-2"></i>إضافة مسجد تحفيظ
                            </h3>
                            <small class="text-white-80">إضافة مسجد تحفيظ جديد إلى النظام</small>
                        </div>
                        <div>
                            <a href="quran_mosques.php" class="btn btn-light btn-sm rounded-pill px-3">
                                <i class="fas fa-arrow-left me-1"></i> عودة
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card-body p-4">
                    <form method="post" action="add_quran_mosque.php" id="quranForm" data-quran-mode="create">
                        <input type="hidden" name="csrf_token" value="<?= $view->e($csrfToken) ?>">

                        <!-- Progress Steps -->
                        <div class="step-progress mb-5">
                            <div class="steps">
                                <div class="step active" data-step="1">
                                    <div class="step-icon">1</div>
                                    <div class="step-label">المعلومات الأساسية</div>
                                </div>
                                <div class="step" data-step="2">
                                    <div class="step-icon">2</div>
                                    <div class="step-label">تفاصيل البرنامج</div>
                                </div>
                                <div class="step" data-step="3">
                                    <div class="step-icon">3</div>
                                    <div class="step-label">الطلاب والتحديات</div>
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
                                    <h6 class="mb-3 text-muted"><i class="fas fa-star-of-life me-2 text-primary"></i>المميزات المتوفرة</h6>
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="has_quran_school" name="has_quran_school" required>
                                                    <option value="لا">لا</option>
                                                    <option value="نعم">نعم</option>
                                                    <option value="مركز تحفيظ">مركز تحفيظ</option>
                                                </select>
                                                <label for="has_quran_school">المسجد به كتاب قرآني</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-floating mb-3">
                                                <select class="form-select" id="has_accommodation" name="has_accommodation">
                                                    <option value="لا">لا</option>
                                                    <option value="نعم">نعم</option>
                                                </select>
                                                <label for="has_accommodation">يتوفر على إقامة</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-5 pt-3 border-top">
                                <button type="button" class="btn btn-outline-secondary disabled">
                                    <i class="fas fa-arrow-right me-1"></i> السابق
                                </button>
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
                                            <i class="fas fa-info-circle me-2"></i>لم يتم إضافة أي مسؤولين بعد
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
                                            <h5 class="mb-3 text-primary"><i class="fas fa-check-circle me-2"></i>مراجعة المعلومات</h5>
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
                                    <i class="fas fa-save me-2"></i>حفظ البيانات
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
