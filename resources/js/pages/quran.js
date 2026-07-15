const quranPageData = (() => { try { return JSON.parse(document.getElementById('quranPageData')?.textContent || '{}'); } catch (_) { return {}; } })();
const escapeOption = (value) => String(value ?? '').replace(/[&<>"']/g, (character) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' })[character]);
const buildOptions = (options = []) => options.map((option) => `<option value="${escapeOption(option)}">${escapeOption(option)}</option>`).join('');

if (document.body.dataset.page === 'quran_mosques') {
function escapeHtml(value) {
    if (value === null || value === undefined) return '';
    return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function escapeRecord(record) {
    const escaped = {};
    Object.entries(record || {}).forEach(([key, value]) => {
        escaped[key] = (typeof value === 'string' || typeof value === 'number')
            ? escapeHtml(value)
            : value;
    });
    return escaped;
}

// Quran Mosque Details Modal
function setupQuranMosqueDetailsModal() {
    const modalElement = document.getElementById('quranMosqueDetailsModal');
    const modal = new bootstrap.Modal(modalElement, {
        backdrop: 'static',
        keyboard: false
    });

    // Clear content when modal is hidden
    modalElement.addEventListener('hidden.bs.modal', function() {
        document.getElementById('modal-body-content').innerHTML = '';
    });

    document.querySelectorAll('.view-quran-mosque-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            const mosqueId = this.getAttribute('data-mosque-id');
            loadQuranMosqueDetails(mosqueId);
        });
    });
}

function loadQuranMosqueDetails(mosqueId) {
    const modal = bootstrap.Modal.getInstance(document.getElementById('quranMosqueDetailsModal'));
    const modalBody = document.getElementById('modal-body-content');

    modalBody.innerHTML = `
        <div class="text-center py-5 animate__animated animate__fadeIn">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">جاري التحميل...</span>
            </div>
            <p class="mt-2">جاري تحميل بيانات مسجد التحفيظ</p>
        </div>`;

    fetch(`ajax/get_quran_mosque_details.php?id=${encodeURIComponent(String(mosqueId))}`)
    .then(response => {
        if (!response.ok) throw new Error('Network response was not ok: ' + response.status);
        return response.json();
    })
    .then(data => {
        if (data.success) {
            modalBody.innerHTML = formatQuranMosqueDetails(data.data);
        } else {
            showModalError(data.message || 'حدث خطأ أثناء جلب بيانات المسجد');
        }
    })
    .catch(error => {
        console.error('Fetch error:', error);
        showModalError('حدث خطأ في الاتصال بالخادم. الرجاء التحقق من اتصال الشبكة والمحاولة مرة أخرى.');
    });

    modal.show();
}

function formatQuranMosqueDetails(mosque) {
    mosque = escapeRecord(mosque);
    let responsiblesHtml = '';
    let totalStudentsAll = 0;
    let totalSessionsAll = 0;

    if (mosque.responsibles && mosque.responsibles.length > 0) {
        mosque.responsibles.forEach((responsible, index) => {
            responsible = escapeRecord(responsible);
            const totalStudents = (responsible.male_students || 0) + (responsible.female_students || 0);
            totalStudentsAll += totalStudents;
            totalSessionsAll += parseInt(responsible.weekly_sessions || 0);

            responsiblesHtml += `
            <div class="card mb-3 animate__animated animate__fadeInUp">
                <div class="card-header bg-light d-flex justify-content-between align-items-center">
                    <h6 class="mb-0"><i class="fas fa-user-tie me-2"></i>${responsible.responsible_name}</h6>
                    ${responsible.responsible_position ? `<span class="badge bg-info">${responsible.responsible_position}</span>` : ''}
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <strong>برنامج العمل:</strong> ${responsible.has_work_program || 'لا'}<br>
                            <strong>جدول الحفظ:</strong> ${responsible.memorization_schedule || 'غير محدد'}<br>
                            <strong>الجلسات الأسبوعية:</strong> ${responsible.weekly_sessions || '0'} جلسة<br>
                            <strong>مدة الجلسة:</strong> ${responsible.session_hours || '0'} ساعة
                        </div>
                        <div class="col-md-6">
                            <strong>الطلاب الذكور:</strong> ${responsible.male_students || '0'}<br>
                            <strong>الطالبات الإناث:</strong> ${responsible.female_students || '0'}<br>
                            <strong>إجمالي الطلاب:</strong> <span class="badge bg-success">${totalStudents}</span><br>
                            <strong>انتظام الحضور:</strong> ${responsible.regular_attendance || 'لا'}
                        </div>
                    </div>
                    ${responsible.challenges ? `<div class="mt-2"><strong>التحديات:</strong> ${responsible.challenges}</div>` : ''}
                    ${responsible.notes_suggestions ? `<div class="mt-2"><strong>ملاحظات:</strong> ${responsible.notes_suggestions}</div>` : ''}
                </div>
            </div>`;
        });
    } else {
        responsiblesHtml = '<p class="text-muted">لا توجد معلومات عن المسؤولين</p>';
    }

    return `
    <div class="row animate__animated animate__fadeIn">
        <div class="col-md-6">
            <div class="card mb-4 animate__animated animate__fadeInLeft">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-info-circle me-2"></i>المعلومات الأساسية</h6>
                </div>
                <div class="card-body">
                    <dl class="row mb-0">
                        <dt class="col-sm-4">اسم المسجد:</dt>
                        <dd class="col-sm-8">${mosque.mosque_name || 'غير محدد'}</dd>

                        <dt class="col-sm-4">الرمز الوطني:</dt>
                        <dd class="col-sm-8">${mosque.national_code || 'غير محدد'}</dd>

                        <dt class="col-sm-4">الجماعة:</dt>
                        <dd class="col-sm-8">${mosque.community || 'غير محدد'}</dd>
                    </dl>
                </div>
            </div>

            <div class="card mb-4 animate__animated animate__fadeInLeft animate__delay-1s">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-users me-2"></i>المسؤولون (${mosque.responsibles ? mosque.responsibles.length : 0})</h6>
                </div>
                <div class="card-body">
                    ${responsiblesHtml}
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4 animate__animated animate__fadeInRight">
                <div class="card-header bg-light">
                    <h6 class="mb-0"><i class="fas fa-chart-pie me-2"></i>الإحصائيات</h6>
                </div>
                <div class="card-body">
                    <div class="row text-center mb-3">
                        <div class="col-md-6">
                            <div class="card bg-primary-gradient text-white mb-3">
                                <div class="card-body">
                                    <h4 class="mb-0">${totalStudentsAll}</h4>
                                    <small>إجمالي الطلاب</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card bg-success-gradient text-white mb-3">
                                <div class="card-body">
                                    <h4 class="mb-0">${totalSessionsAll}</h4>
                                    <small>إجمالي الجلسات الأسبوعية</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-home me-2"></i>مرافق الإقامة</h6>
                        </div>
                        <div class="card-body">
                            <p><strong>الإقامة:</strong> ${mosque.has_accommodation || 'لا'}</p>
                            ${mosque.accommodation_capacity ? `<p><strong>سعة الإقامة:</strong> ${mosque.accommodation_capacity} شخص</p>` : ''}
                            ${mosque.accommodation_condition ? `<p><strong>حالة الإقامة:</strong> ${mosque.accommodation_condition}</p>` : ''}
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>ملاحظات إضافية</h6>
                        </div>
                        <div class="card-body">
                            ${mosque.additional_notes ? `<p>${mosque.additional_notes}</p>` : '<p class="text-muted">لا توجد ملاحظات إضافية</p>'}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>`;
}

function showModalError(message) {
    const safeMessage = escapeHtml(message);
    document.getElementById('modal-body-content').innerHTML = `
        <div class="alert alert-danger animate__animated animate__shakeX" role="alert">
            <i class="fas fa-exclamation-triangle me-2"></i>
            ${safeMessage}
        </div>`;
}

function printQuranMosqueDetails() {
    const modalContent = document.getElementById('modal-body-content').innerHTML;
    const printWindow = window.open('', '_blank');
    const assetBaseUrl = new URL('.', window.location.href).href;
    printWindow.document.write(`
        <!DOCTYPE html>
        <html lang="ar" dir="rtl">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <base href="${assetBaseUrl}">
            <title>تفاصيل مسجد التحفيظ - طباعة</title>
            <link href="assets/vendor/bootstrap/css/bootstrap.rtl.min.css" rel="stylesheet">
            <link href="assets/vendor/fontawesome/css/all.min.css" rel="stylesheet">
            <link href="${assetBaseUrl}assets/dist/app.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container-fluid py-4">
                <div class="row mb-4">
                    <div class="col-12 text-center">
                        <h2 class="text-primary">تفاصيل مسجد التحفيظ</h2>
                        <p class="text-muted">${new Date().toLocaleString('ar-EG')}</p>
                    </div>
                </div>
                ${modalContent}
            </div>
        </body>
        </html>
    `);
    printWindow.document.close();
    printWindow.focus();
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 500);
}

// Initialize when document is ready
document.addEventListener('DOMContentLoaded', function() {
    setupQuranMosqueDetailsModal();
    document.addEventListener('submit', function(e) {
        const form = e.target.closest('.js-confirm-submit');
        if (!form) return;
        if (!confirm(form.dataset.confirm || 'هل أنت متأكد من الحذف؟')) {
            e.preventDefault();
        }
    });

    document.querySelector('.js-print-quran-details')?.addEventListener('click', printQuranMosqueDetails);

    // Checkbox selection logic
    const selectAll = document.getElementById('selectAll');
    const checkboxes = document.querySelectorAll('.mosque-checkbox');
    const deleteBtn = document.getElementById('deleteSelected');
    const selectedCountBtn = document.getElementById('selectedCountBtn');
    const selectedCountSpan = document.getElementById('selectedCount');

    // Only initialize checkbox functionality if elements exist
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateSelectionUI();
        });
    }

    if (checkboxes.length > 0) {
        checkboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateSelectionUI);
        });
    }

    function updateSelectionUI() {
        const selectedCount = document.querySelectorAll('.mosque-checkbox:checked').length;
        if (selectedCountSpan) {
            selectedCountSpan.textContent = selectedCount;
        }

        if (deleteBtn) {
            deleteBtn.disabled = selectedCount === 0;
        }

        if (selectedCountBtn) {
            selectedCountBtn.disabled = selectedCount === 0;
        }
    }

    // Delete selected functionality - only if delete button exists
    if (deleteBtn) {
        deleteBtn.addEventListener('click', function() {
            const selectedIds = Array.from(document.querySelectorAll('.mosque-checkbox:checked'))
                .map(checkbox => checkbox.value);

            if (selectedIds.length === 0) return;

            if (confirm(`هل أنت متأكد من حذف ${selectedIds.length} مسجد(اً) محدد(ة)؟`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'delete_quran_mosque.php';

                // Add CSRF token
                const csrfInput = document.createElement('input');
                csrfInput.type = 'hidden';
                csrfInput.name = 'csrf_token';
                csrfInput.value = quranPageData.csrfToken || '';
                form.appendChild(csrfInput);

                selectedIds.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'selected_mosques[]';
                    input.value = id;
                    form.appendChild(input);
                });

                document.body.appendChild(form);
                form.submit();
            }
        });
    }

    // Live search functionality
    const liveSearch = document.getElementById('liveSearch');
    const clearSearch = document.getElementById('clearSearch');
    const searchForm = document.getElementById('searchForm');

    if (liveSearch) {
        liveSearch.addEventListener('keyup', function(e) {
            if (e.key === 'Enter') {
                searchForm.submit();
            }
        });
    }

    if (clearSearch) {
        clearSearch.addEventListener('click', function() {
            liveSearch.value = '';
            this.classList.add('d-none');
            searchForm.submit();
        });
    }

    if (liveSearch) {
        liveSearch.addEventListener('input', function() {
            if (clearSearch) {
                clearSearch.classList.toggle('d-none', this.value === '');
            }
        });
    }

    // Initialize tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});
}
if (document.body.dataset.page === 'add_quran_mosque') {
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

        const mosqueName = escapeHtml(mosque.text.split(' (')[0]);
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
        return value ? `<span class="location-badge">${escapeHtml(value)}</span>` : '';
    }

    function formatMosqueSelection(mosque) {
        return mosque.id ? mosque.text.split(' (')[0] : mosque.text;
    }

    function escapeHtml(value) {
        return $('<div>').text(String(value ?? '')).html();
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
        const mosqueName = escapeHtml(mosqueElement.text().split(' (')[0]);
        const locationData = [
            mosqueElement.data('pashalik'),
            mosqueElement.data('circle'),
            mosqueElement.data('leadership'),
            mosqueElement.data('community'),
            mosqueElement.data('administrative')
        ].filter(Boolean).map(escapeHtml);

        const features = [
            $('#has_quran_school').val() !== 'لا' && 'كتاب قرآني',
            $('#has_accommodation').val() === 'نعم' && 'إقامة'
        ].filter(Boolean).map(escapeHtml);

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
    $('#responsibles-container').on('click', '.remove-responsible', function() {
        const item = $(this).closest('.responsible-item');
        const index = item.data('index');

        item.remove();
        $(`.responsible-students[data-index="${index}"]`).remove();

        if ($('.responsible-item').length === 0) {
            $('#responsibles-container').html('<div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i>لم يتم إضافة أي مسؤولين بعد</div>');
        }

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
                <button type="button" class="remove-responsible" aria-label="حذف المسؤول"><i class="fas fa-times-circle"></i></button>
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
                                <option value="">اختر المنصب</option>${buildOptions(quranPageData.positionOptions)}
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
                            <select class="form-select" name="responsibles[${index}][memorization_schedule]">${buildOptions(quranPageData.scheduleOptions)}
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
}
if (document.body.dataset.page === 'edit_quran_mosque') {
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

        const mosqueName = escapeHtml(mosque.text.split(' (')[0]);
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
        return value ? `<span class="location-badge">${escapeHtml(value)}</span>` : '';
    }

    function formatMosqueSelection(mosque) {
        return mosque.id ? mosque.text.split(' (')[0] : mosque.text;
    }

    function escapeHtml(value) {
        return $('<div>').text(String(value ?? '')).html();
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
        const mosqueName = escapeHtml(mosqueElement.text().split(' (')[0]);
        const locationData = [
            mosqueElement.data('pashalik'),
            mosqueElement.data('circle'),
            mosqueElement.data('leadership'),
            mosqueElement.data('community'),
            mosqueElement.data('administrative')
        ].filter(Boolean).map(escapeHtml);

        const features = [
            $('#has_quran_school').val() !== 'لا' && 'كتاب قرآني',
            $('#has_accommodation').val() === 'نعم' && 'إقامة'
        ].filter(Boolean).map(escapeHtml);

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
    $('#responsibles-container').on('click', '.remove-responsible', function() {
        const item = $(this).closest('.responsible-item');
        const index = item.data('index');

        item.remove();
        $(`.responsible-students[data-index="${index}"]`).remove();

        if ($('.responsible-item').length === 0) {
            $('#responsibles-container').html('<div class="alert alert-info text-center"><i class="fas fa-info-circle me-2"></i>لم يتم إضافة أي مسؤولين بعد</div>');
        }

        if ($('.step-content.active').data('step') === 3) {
            updateReviewSummary();
        }
    });
    // Add responsible functionality
    let responsibleCount = Number(quranPageData.responsibleCount || 0);

    $('.add-responsible-btn').click(function() {
        responsibleCount++;
        const index = responsibleCount;

        const responsibleHtml = `
            <div class="responsible-item" data-index="${index}">
                <button type="button" class="remove-responsible" aria-label="حذف المسؤول"><i class="fas fa-times-circle"></i></button>
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
                                <option value="">اختر المنصب</option>${buildOptions(quranPageData.positionOptions)}
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
                            <select class="form-select" name="responsibles[${index}][memorization_schedule]">${buildOptions(quranPageData.scheduleOptions)}
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
}
