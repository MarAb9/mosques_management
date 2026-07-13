<?php
/**
 * Add Quran program form (legacy add_quran_mosque.php markup, verbatim —
 * including the nested document structure the legacy page produced).
 * Expects: $mosques, $scheduleOptions, $positionOptions
 */
?>

<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مسجد تحفيظ</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css">
    <style>
    /* Same CSS as in edit_quran_mosque.php */
    :root {
        --primary: #4e73df;
        --primary-dark: #2e59d9;
        --secondary: #f8f9fc;
        --success: #1cc88a;
        --light: #f8f9fa;
        --gray: #6c757d;
        --border: #d1d3e2;
    }

    body {
        background-color: var(--light);
        font-family: 'Tajawal', sans-serif;
    }

    .card {
        border-radius: 0.75rem;
        border: none;
        box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
    }

    .card-header {
        background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
        border-bottom: none;
    }

    .step-progress {
        position: relative;
        margin-bottom: 2.5rem;
    }

    .steps {
        display: flex;
        justify-content: space-between;
        position: relative;
        padding: 0 2rem;
    }

    .steps::before {
        content: '';
        position: absolute;
        top: 50%;
        left: 50px;
        right: 50px;
        height: 3px;
        background: #e9ecef;
        z-index: 1;
        transform: translateY(-50%);
    }

    .step {
        position: relative;
        z-index: 2;
        text-align: center;
        flex: 1;
    }

    .step-icon {
        width: 50px;
        height: 50px;
        border-radius: 50%;
        background: #e9ecef;
        color: var(--gray);
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto;
        font-weight: bold;
        transition: all 0.3s;
        border: 3px solid white;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .step.active .step-icon {
        background: var(--primary);
        color: white;
        transform: scale(1.05);
        box-shadow: 0 5px 15px rgba(78, 115, 223, 0.4);
    }

    .step.completed .step-icon {
        background: var(--success);
        color: white;
    }

    .step-content {
        display: none;
        animation: fadeIn 0.4s ease-out;
    }

    .step-content.active {
        display: block;
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
        gap: 12px;
        margin: 1.5rem 0;
    }

    .feature-checkbox:checked + label {
        background: var(--primary);
        color: white;
        border-color: var(--primary);
        box-shadow: 0 4px 12px rgba(78, 115, 223, 0.25);
    }

    .form-control, .form-select, .select2-selection {
        border-radius: 0.5rem;
        padding: 1rem 0.75rem;
        border: 1px solid var(--border);
        transition: all 0.3s;
    }

    .form-control:focus, .form-select:focus, .select2-selection:focus {
        border-color: var(--primary);
        box-shadow: 0 0 0 0.25rem rgba(78, 115, 223, 0.25);
    }

    textarea.form-control {
        min-height: 120px;
        resize: none;
    }

    .badge {
        font-weight: 500;
    }

    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(15px); }
        to { opacity: 1; transform: translateY(0); }
    }

    /* Select2 custom styles */
    .select2-container--default .select2-selection--single {
        height: calc(3.5rem + 2px) !important;
        padding-top: 1.625rem;
    }

    .select2-results__option {
        padding: 0.75rem 1rem !important;
        border-bottom: 1px solid #f8f9fa;
    }

    .mosque-option {
        padding: 0.5rem;
    }

    .mosque-name {
        font-weight: 600;
        color: var(--primary-dark);
    }

    .location-badges {
        display: flex;
        flex-wrap: wrap;
        gap: 0.5rem;
        margin-top: 0.5rem;
    }

    .location-badge {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        background-color: #f0f0f0;
        border-radius: 0.25rem;
        color: #555;
    }

    .responsible-item {
        border: 1px solid #e9ecef;
        border-radius: 0.5rem;
        padding: 1rem;
        margin-bottom: 1rem;
        background: #f8f9fa;
        position: relative;
    }

    .remove-responsible {
        position: absolute;
        top: 10px;
        left: 10px;
        color: #dc3545;
        cursor: pointer;
        font-size: 1.2rem;
    }

    .add-responsible {
        margin-bottom: 1rem;
    }

    .responsible-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 1rem;
        padding-bottom: 0.5rem;
        border-bottom: 1px solid #e9ecef;
    }

    .responsible-title {
        font-weight: 600;
        color: var(--primary-dark);
    }
    </style>
</head>
<body>
<div class="container-fluid py-4">
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
                    <form method="post" action="add_quran_mosque.php" id="quranForm">
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    // Initialize select2 with custom templates
    $('.mosque-select').select2({
        placeholder: 'اختر المسجد',
        allowClear: true,
        templateResult: formatMosque,
        templateSelection: formatMosqueSelection
    });

    function formatMosque(mosque) {
        if (!mosque.id) return mosque.text;

        const mosqueName = mosque.text.split(' (')[0];
        const $container = $(
            `<div class="mosque-option">
                <div class="mosque-name">${mosqueName}</div>
                <div class="location-badges">
                    ${formatLocationBadge($(mosque.element).data('pashalik'))}
                    ${formatLocationBadge($(mosque.element).data('circle'))}
                    ${formatLocationBadge($(mosque.element).data('leadership'))}
                    ${formatLocationBadge($(mosque.element).data('community'))}
                    ${formatLocationBadge($(mosque.element).data('administrative'))}
                </div>
            </div>`
        );
        return $container;
    }

    function formatLocationBadge(value) {
        return value ? `<span class="location-badge">${value}</span>` : '';
    }

    function formatMosqueSelection(mosque) {
        return mosque.id ? mosque.text.split(' (')[0] : mosque.text;
    }

    // Step navigation
    $('.next-step').click(function() {
        const currentStep = $('.step-content.active').data('step');
        const nextStep = currentStep + 1;

        if (validateStep(currentStep)) {
            $(`.step[data-step="${currentStep}"]`).addClass('completed');
            navigateToStep(nextStep);
        }
    });

    $('.prev-step').click(function() {
        const currentStep = $('.step-content.active').data('step');
        const prevStep = currentStep - 1;
        navigateToStep(prevStep);
    });

    function navigateToStep(step) {
        $('.step-content.active').removeClass('active');
        $(`.step-content[data-step="${step}"]`).addClass('active');

        $('.step').removeClass('active');
        $(`.step[data-step="${step}"]`).addClass('active');

        if (step === 3) updateReviewSummary();

        // Smooth scroll to top
        $('html, body').animate({ scrollTop: $('.step-progress').offset().top - 20 }, 300);
    }

    // Auto-resize textareas
    $('textarea').on('input', function() {
        this.style.height = 'auto';
        this.style.height = (this.scrollHeight) + 'px';
    }).trigger('input');

    // Form validation for each step
    function validateStep(step) {
        let isValid = true;

        $('.is-invalid').removeClass('is-invalid');
        $('.invalid-feedback').remove();

        if (step === 1) {
            if (!$('#mosque_registration_number').val()) {
                showError($('#mosque_registration_number').parent(), 'يجب اختيار المسجد');
                isValid = false;
            }
        }

        if (step === 2) {
            // Validate at least one responsible
            if ($('.responsible-item').length === 0) {
                showError($('#responsibles-container'), 'يجب إضافة مسؤول واحد على الأقل');
                isValid = false;
            }

            // Validate each responsible
            $('.responsible-item').each(function() {
                const nameInput = $(this).find('input[name$="[name]"]');
                if (!nameInput.val().trim()) {
                    showError(nameInput.parent(), 'يجب إدخال اسم المسؤول');
                    isValid = false;
                }
            });
        }

        return isValid;
    }

    function showError(element, message) {
        element.addClass('is-invalid');
        element.after(`<div class="invalid-feedback d-block">${message}</div>`);
        $('html, body').animate({ scrollTop: element.offset().top - 100 }, 500);
    }

    // Remove validation errors when user starts typing
    $('input, select, textarea').on('input change', function() {
        $(this).removeClass('is-invalid').next('.invalid-feedback').remove();
    });

    // Update review summary
    function updateReviewSummary() {
        const mosqueElement = $('#mosque_registration_number option:selected');
        const mosqueName = mosqueElement.text().split(' (')[0];
        const locationData = [
            mosqueElement.data('pashalik'),
            mosqueElement.data('circle'),
            mosqueElement.data('leadership'),
            mosqueElement.data('community'),
            mosqueElement.data('administrative')
        ].filter(Boolean);

        const features = [
            $('#has_quran_school').val() !== 'لا' && 'كتاب قرآني',
            $('#has_accommodation').val() === 'نعم' && 'إقامة'
        ].filter(Boolean);

        let totalStudents = 0;
        let totalMale = 0;
        let totalFemale = 0;

        $('.responsible-item').each(function() {
            const male = parseInt($(this).find('input[name$="[male_students]"]').val() || 0);
            const female = parseInt($(this).find('input[name$="[female_students]"]').val() || 0);
            totalMale += male;
            totalFemale += female;
        });

        totalStudents = totalMale + totalFemale;

        const html = `
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-3">
                        <strong><i class="fas fa-mosque me-2 text-primary"></i>المسجد:</strong><br>
                        <span class="fw-bold">${mosqueName}</span><br>
                        ${locationData.map(loc => `<span class="badge bg-light text-dark me-1">${loc}</span>`).join('')}
                    </p>
                    <p><strong><i class="fas fa-star me-2 text-primary"></i>المميزات:</strong> ${features.join('، ') || 'لا يوجد'}</p>
                    <p><strong><i class="fas fa-users me-2 text-primary"></i>عدد المسؤولين:</strong> ${$('.responsible-item').length}</p>
                </div>
                <div class="col-md-6">
                    <p><strong><i class="fas fa-users me-2 text-primary"></i>إجمالي عدد الطلاب:</strong> ${totalStudents} طالب</p>
                    <p><strong><i class="fas fa-male me-2 text-primary"></i>الذكور:</strong> ${totalMale}</p>
                    <p><strong><i class="fas fa-female me-2 text-primary"></i>الإناث:</strong> ${totalFemale}</p>
                </div>
            </div>
        `;

        $('#reviewSummary').html(html);
    }

    // Update review summary when form changes
    $('#quranForm').on('input change', function() {
        if ($('.step-content.active').data('step') === 3) {
            updateReviewSummary();
        }
    });

    // Add responsible functionality
    let responsibleCount = 0;

    $('.add-responsible-btn').click(function() {
        responsibleCount++;
        const index = responsibleCount;

        const responsibleHtml = `
            <div class="responsible-item" data-index="${index}">
                <span class="remove-responsible" onclick="removeResponsible(this)"><i class="fas fa-times-circle"></i></span>
                <div class="responsible-header">
                    <span class="responsible-title">مسؤول ${index}</span>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" name="responsibles[${index}][name]" placeholder="اسم المسؤول" required>
                            <label>اسم المسؤول</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="text" class="form-control" name="responsibles[${index}][national_id]" placeholder="رقم البطاقة">
                            <label>رقم البطاقة الوطنية</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select class="form-select" name="responsibles[${index}][position]">
                                <option value="">اختر المنصب</option>
                                <?php foreach ($positionOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label>منصب المسؤول</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select class="form-select" name="responsibles[${index}][has_work_program]">
                                <option value="لا">لا</option>
                                <option value="نعم">نعم</option>
                            </select>
                            <label>برنامج عمل</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select class="form-select" name="responsibles[${index}][memorization_schedule]">
                                <?php foreach ($scheduleOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option) ?>"><?= htmlspecialchars($option) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label>جدول الحفظ</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="number" class="form-control" name="responsibles[${index}][weekly_sessions]" min="0">
                            <label>عدد الجلسات الأسبوعية</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="number" step="0.5" class="form-control" name="responsibles[${index}][session_hours]" min="0">
                            <label>عدد ساعات الجلسة</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <select class="form-select" name="responsibles[${index}][regular_attendance]">
                                <option value="لا">لا</option>
                                <option value="نعم">نعم</option>
                            </select>
                            <label>انتظام الحضور</label>
                        </div>
                    </div>
                </div>
            </div>
        `;

        const studentsHtml = `
            <div class="responsible-students" data-index="${index}">
                <h6 class="mb-3 text-muted">عدد الطلاب للمسؤول ${index}</h6>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="number" class="form-control" name="responsibles[${index}][male_students]" min="0">
                            <label>عدد الطلاب الذكور</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-floating mb-3">
                            <input type="number" class="form-control" name="responsibles[${index}][female_students]" min="0">
                            <label>عدد الطالبات الإناث</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating mb-3">
                            <textarea class="form-control" name="responsibles[${index}][challenges]" rows="3" placeholder=" "></textarea>
                            <label>التحديات</label>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="form-floating mb-3">
                            <textarea class="form-control" name="responsibles[${index}][notes_suggestions]" rows="3" placeholder=" "></textarea>
                            <label>الملاحظات والمقترحات</label>
                        </div>
                    </div>
                </div>
                <hr class="my-4">
            </div>
        `;

        // Remove the initial alert if it exists
        if ($('#responsibles-container .alert').length) {
            $('#responsibles-container .alert').remove();
        }

        $('#responsibles-container').append(responsibleHtml);
        $('#students-container').append(studentsHtml);

        // Add event listeners to new inputs
        $('input, select, textarea').off('input change').on('input change', function() {
            $(this).removeClass('is-invalid').next('.invalid-feedback').remove();
        });

        // Scroll to the new responsible
        $('html, body').animate({
            scrollTop: $('.responsible-item[data-index="' + index + '"]').offset().top - 100
        }, 500);
    });
});

function removeResponsible(element) {
    const item = $(element).closest('.responsible-item');
    const index = item.data('index');

    // Remove the responsible item
    item.remove();

    // Remove the corresponding students section
    $(`.responsible-students[data-index="${index}"]`).remove();

    // If no responsibles left, show the alert again
    if ($('.responsible-item').length === 0) {
        $('#responsibles-container').html('<div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i>لم يتم إضافة أي مسؤولين بعد</div>');
    }

    // Update the review summary if we're on step 3
    if ($('.step-content.active').data('step') === 3) {
        updateReviewSummary();
    }
}
</script>
</body>
</html>
