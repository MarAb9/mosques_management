// assets/js/mosque_form.js

// Administrative hierarchy data
const mosqueFormData = {
    pashalikData: {
        "بركان": {
            communities: ["بركان"],
            attachments: [
                "الملحقة الإدارية الأولى",
                "الملحقة الإدارية الثانية",
                "الملحقة الإدارية الثالثة",
                "الملحقة الإدارية الرابعة",
                "الملحقة الإدارية الخامسة"
            ]
        },
        "سيدي سليمان شراعة": {
            communities: ["سيدي سليمان شراعة"],
            attachments: []
        },
        "أحفير": {
            communities: ["أحفير"],
            attachments: []
        },
        "السعيدية": {
            communities: ["السعيدية"],
            attachments: ["مقاطعة ملوية", "مقاطعة القصبة"]
        },
        "أكليم": {
            communities: ["أكليم"],
            attachments: []
        },
        "عين الركادة": {
            communities: ["عين الركادة"],
            attachments: []
        }
    },
    circleData: {
        "أحفير": {
            leaderships: {
                "مداغ": ["مداغ"],
                "لعثامنة": ["لعثامنة"],
                "أغبال": ["أغبال", "فزوان"]
            }
        },
        "أكليم": {
            leaderships: {
                "الشويحية": ["الشويحية"],
                "زكزل": ["زكزل"],
                "بني وريمش": ["بوغريبة"],
                "تافوغالت": ["تافوغالت", "سيدي بوهرية", "رسلان"]
            }
        }
    }
};

function setElementHidden(element, hidden) {
    if (!element) return;

    Array.from(element.classList)
        .filter(className => className.endsWith('-collapsed-section'))
        .forEach(className => element.classList.remove(className));
    element.hidden = hidden;
    element.classList.toggle('d-none', hidden);
}

function initializeMosqueForm() {
    // DOM Elements
    const adminTypeSelect = document.getElementById('admin_type');
    const pashalikSection = document.getElementById('pashalik_section');
    const circleSection = document.getElementById('circle_section');
    const pashalikSelect = document.getElementById('pashalik');
    const pashalikCommunitySelect = document.getElementById('pashalik_community');
    const attachmentSelect = document.getElementById('administrative_attachment');
    const attachmentContainer = document.getElementById('attachment_container');
    const circleSelect = document.getElementById('circle');
    const leadershipSelect = document.getElementById('leadership');
    const circleCommunitySelect = document.getElementById('circle_community');

    // Image upload handling
    const mainImageInput = document.getElementById('main_image');
    const imagePreviewContainer = document.getElementById('image-preview-container');
    const imagePreview = document.getElementById('image-preview');
    const removeImageBtn = document.getElementById('remove-image');

    // Image preview functionality
    if (mainImageInput) {
        mainImageInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                // Client-side validation
                const validTypes = ['image/jpeg', 'image/png'];
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                if (!validTypes.includes(file.type)) {
                    alert('نوع الملف غير مسموح به. يرجى تحميل صورة (JPG, PNG)');
                    e.target.value = '';
                    return;
                }
                
                if (file.size > maxSize) {
                    alert('حجم الملف كبير جداً. الحد الأقصى 2MB');
                    e.target.value = '';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(event) {
                    imagePreview.src = event.target.result;
                    imagePreviewContainer.classList.remove('d-none');
                };
                reader.readAsDataURL(file);
            }
        });
    }

    // Remove image functionality
    if (removeImageBtn) {
        removeImageBtn.addEventListener('click', function() {
            mainImageInput.value = '';
            imagePreview.src = '';
            imagePreviewContainer.classList.add('d-none');
            
            // Add a hidden field to indicate image should be removed
            if (!document.getElementById('remove_image_flag')) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'remove_image';
                input.id = 'remove_image_flag';
                input.value = '1';
                document.querySelector('form').appendChild(input);
            }
        });
    }

    // Form submission validation
    const form = document.querySelector('form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (mainImageInput && mainImageInput.files.length > 0) {
                const file = mainImageInput.files[0];
                const maxSize = 2 * 1024 * 1024; // 2MB
                
                if (file.size > maxSize) {
                    e.preventDefault();
                    alert('حجم الملف كبير جداً. الحد الأقصى 2MB');
                    return false;
                }
            }
        });
    }

    // Admin type change handler
    if (adminTypeSelect) {
        adminTypeSelect.addEventListener('change', function() {
            const selectedType = this.value;
            
            // Hide both sections first
            setElementHidden(pashalikSection, true);
            setElementHidden(circleSection, true);
            setElementHidden(attachmentContainer, true);
            
            // Show the selected section
            if (selectedType === 'pashalik') {
                setElementHidden(pashalikSection, false);
                // Reset circle section
                if (circleSelect) circleSelect.value = '';
                if (leadershipSelect) leadershipSelect.innerHTML = '<option value="">-- اختر القيادة --</option>';
                if (circleCommunitySelect) circleCommunitySelect.innerHTML = '<option value="">-- اختر الجماعة --</option>';
            } else if (selectedType === 'circle') {
                setElementHidden(circleSection, false);
                // Reset pashalik section
                if (pashalikSelect) pashalikSelect.value = '';
                if (pashalikCommunitySelect) pashalikCommunitySelect.innerHTML = '<option value="">-- اختر الجماعة --</option>';
                if (attachmentSelect) attachmentSelect.innerHTML = '<option value="">-- اختر الملحقة/المقاطعة --</option>';
                setElementHidden(attachmentContainer, true);
            }
        });
    }

    // Pashalik hierarchy logic
    if (pashalikSelect) {
        pashalikSelect.addEventListener('change', function() {
            const selectedPashalik = this.value;
            
            // Update communities
            pashalikCommunitySelect.innerHTML = '<option value="">-- اختر الجماعة --</option>';
            if (selectedPashalik && mosqueFormData.pashalikData[selectedPashalik]) {
                mosqueFormData.pashalikData[selectedPashalik].communities.forEach(community => {
                    const option = new Option(community, community);
                    pashalikCommunitySelect.add(option);
                });
            }
            
            // Reset attachments
            if (attachmentSelect) attachmentSelect.innerHTML = '<option value="">-- اختر الملحقة/المقاطعة --</option>';
            setElementHidden(attachmentContainer, true);
        });
    }

    if (pashalikCommunitySelect) {
        pashalikCommunitySelect.addEventListener('change', function() {
            const selectedPashalik = pashalikSelect.value;
            const selectedCommunity = this.value;
            
            // Update attachments
            if (attachmentSelect) attachmentSelect.innerHTML = '<option value="">-- اختر الملحقة/المقاطعة --</option>';
            
            if (selectedPashalik && selectedCommunity && mosqueFormData.pashalikData[selectedPashalik]) {
                const attachments = mosqueFormData.pashalikData[selectedPashalik].attachments || [];
                
                if (attachments.length > 0) {
                    attachments.forEach(attachment => {
                        const option = new Option(attachment, attachment);
                        attachmentSelect.add(option);
                    });
                    setElementHidden(attachmentContainer, false);
                } else {
                    setElementHidden(attachmentContainer, true);
                }
            } else {
                setElementHidden(attachmentContainer, true);
            }
        });
    }

    // Circle hierarchy logic
    if (circleSelect) {
        circleSelect.addEventListener('change', function() {
            const selectedCircle = this.value;
            
            // Update leaderships
            leadershipSelect.innerHTML = '<option value="">-- اختر القيادة --</option>';
            if (selectedCircle && mosqueFormData.circleData[selectedCircle]) {
                Object.keys(mosqueFormData.circleData[selectedCircle].leaderships).forEach(leadership => {
                    const option = new Option(leadership, leadership);
                    leadershipSelect.add(option);
                });
            }
            
            // Reset communities
            circleCommunitySelect.innerHTML = '<option value="">-- اختر الجماعة --</option>';
        });
    }

    if (leadershipSelect) {
        leadershipSelect.addEventListener('change', function() {
            const selectedCircle = circleSelect.value;
            const selectedLeadership = this.value;
            
            // Update communities
            circleCommunitySelect.innerHTML = '<option value="">-- اختر الجماعة --</option>';
            if (selectedCircle && selectedLeadership && 
                mosqueFormData.circleData[selectedCircle] && 
                mosqueFormData.circleData[selectedCircle].leaderships[selectedLeadership]) {
                
                mosqueFormData.circleData[selectedCircle].leaderships[selectedLeadership].forEach(community => {
                    const option = new Option(community, community);
                    circleCommunitySelect.add(option);
                });
            }
        });
    }

    // Initialize based on existing values (for edit mode)
    const savedAdminType = adminTypeSelect ? adminTypeSelect.dataset.savedValue || adminTypeSelect.value || '' : '';
    const savedPashalik = pashalikSelect ? pashalikSelect.dataset.savedValue || '' : '';
    const savedCircle = circleSelect ? circleSelect.dataset.savedValue || '' : '';
    const savedCommunity = pashalikCommunitySelect ? pashalikCommunitySelect.dataset.savedValue || '' : '';
    const savedLeadership = leadershipSelect ? leadershipSelect.dataset.savedValue || '' : '';
    const savedAttachment = attachmentSelect ? attachmentSelect.dataset.savedValue || '' : '';

    if (savedAdminType && adminTypeSelect) {
        adminTypeSelect.value = savedAdminType;
        adminTypeSelect.dispatchEvent(new Event('change'));

        if (savedAdminType === 'pashalik' && savedPashalik && pashalikSelect) {
            pashalikSelect.value = savedPashalik;
            pashalikSelect.dispatchEvent(new Event('change'));

            setTimeout(() => {
                if (savedCommunity && pashalikCommunitySelect) {
                    pashalikCommunitySelect.value = savedCommunity;
                    pashalikCommunitySelect.dispatchEvent(new Event('change'));
                    
                    setTimeout(() => {
                        if (savedAttachment && attachmentSelect) {
                            attachmentSelect.value = savedAttachment;
                        }
                    }, 0);
                }
            }, 0);
        } else if (savedAdminType === 'circle' && savedCircle && circleSelect) {
            circleSelect.value = savedCircle;
            circleSelect.dispatchEvent(new Event('change'));

            setTimeout(() => {
                if (savedLeadership && leadershipSelect) {
                    leadershipSelect.value = savedLeadership;
                    leadershipSelect.dispatchEvent(new Event('change'));

                    setTimeout(() => {
                        if (savedCommunity && circleCommunitySelect) {
                            circleCommunitySelect.value = savedCommunity;
                        }
                    }, 0);
                }
            }, 0);
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', initializeMosqueForm);

function initializeEnhancedMosqueFormUx() {
    const form = document.querySelector('form[data-guard-unsaved="true"]');
    if (!form) return;

    setupUnsavedChangesWarning(form);
    setupValidationSummary(form);
    setupNationalCodeDuplicateCheck(form);
    setupImageCompression();

    const existingSummary = document.getElementById('validationSummary');
    if (existingSummary) existingSummary.focus({ preventScroll: false });
}

function setupUnsavedChangesWarning(form) {
    let dirty = false;
    const markDirty = () => { dirty = true; };
    form.addEventListener('input', markDirty, true);
    form.addEventListener('change', markDirty, true);
    form.addEventListener('submit', () => { dirty = false; });
    form.addEventListener('reset', () => { dirty = false; });

    window.addEventListener('beforeunload', function(event) {
        if (!dirty) return;
        event.preventDefault();
        event.returnValue = '';
    });
}

function setupValidationSummary(form) {
    form.addEventListener('submit', function(event) {
        const invalidFields = Array.from(form.querySelectorAll(':invalid'));
        if (invalidFields.length === 0) return;

        event.preventDefault();
        event.stopPropagation();
        form.classList.add('was-validated');

        let summary = document.getElementById('clientValidationSummary');
        if (!summary) {
            summary = document.createElement('div');
            summary.id = 'clientValidationSummary';
            summary.className = 'alert alert-warning';
            summary.setAttribute('role', 'alert');
            summary.tabIndex = -1;
            form.parentElement.insertBefore(summary, form);
        }

        const labels = invalidFields.slice(0, 8).map(field => {
            const label = form.querySelector(`label[for="${CSS.escape(field.id || field.name)}"]`);
            return label ? label.textContent.trim().replace('*', '') : (field.name || 'حقل مطلوب');
        });
        summary.innerHTML = `<strong>يرجى مراجعة الحقول المطلوبة:</strong><ul class="mb-0 mt-2">${labels.map(label => `<li>${escapeHtmlForForm(label)}</li>`).join('')}</ul>`;
        summary.focus();
        invalidFields[0].focus({ preventScroll: false });
    }, true);
}

function setupNationalCodeDuplicateCheck(form) {
    const input = document.getElementById('national_code');
    if (!input) return;

    const feedback = document.createElement('div');
    feedback.id = 'nationalCodeWarning';
    feedback.className = 'form-text';
    input.insertAdjacentElement('afterend', feedback);

    let timer;
    const check = () => {
        const value = input.value.trim();
        clearTimeout(timer);
        if (value === '') {
            feedback.textContent = '';
            input.dataset.duplicate = '0';
            return;
        }
        timer = setTimeout(async () => {
            try {
                const params = new URLSearchParams({
                    national_code: value,
                    registration_number: form.dataset.originalRegistrationNumber || ''
                });
                const response = await fetch(`check_national_code.php?${params.toString()}`, {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin'
                });
                if (!response.ok) return;
                const data = await response.json();
                input.dataset.duplicate = data.exists ? '1' : '0';
                input.classList.toggle('is-invalid', Boolean(data.exists));
                feedback.className = data.exists ? 'invalid-feedback d-block' : 'form-text text-success';
                feedback.textContent = data.exists ? 'هذا الرمز الوطني مستعمل مسبقا. يرجى التحقق قبل الحفظ.' : 'الرمز الوطني غير مكرر.';
            } catch (error) {
                console.warn('National code check failed', error);
            }
        }, 350);
    };

    input.addEventListener('input', check);
    input.addEventListener('blur', check);
    form.addEventListener('submit', function(event) {
        if (input.dataset.duplicate !== '1') return;
        event.preventDefault();
        event.stopPropagation();
        input.focus();
        feedback.className = 'invalid-feedback d-block';
        feedback.textContent = 'لا يمكن الحفظ قبل تصحيح الرمز الوطني المكرر.';
    }, true);
}

function setupImageCompression() {
    const input = document.getElementById('main_image');
    if (!input) return;

    input.addEventListener('change', async function(event) {
        const file = input.files?.[0];
        const maxSize = 2 * 1024 * 1024;
        if (!file || file.size <= maxSize || !['image/jpeg', 'image/png'].includes(file.type)) return;

        event.stopImmediatePropagation();
        try {
            const compressed = await compressImageFile(file, maxSize);
            if (compressed.size > maxSize) {
                alert('تعذر ضغط الصورة إلى أقل من 2MB. يرجى اختيار صورة أصغر.');
                input.value = '';
                return;
            }
            const transfer = new DataTransfer();
            transfer.items.add(compressed);
            input.files = transfer.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
        } catch (error) {
            console.warn('Image compression failed', error);
            alert('تعذر ضغط الصورة. يرجى اختيار صورة أصغر.');
            input.value = '';
        }
    }, true);
}

function compressImageFile(file, maxSize) {
    return new Promise((resolve, reject) => {
        const image = new Image();
        const reader = new FileReader();
        reader.onerror = reject;
        reader.onload = () => { image.src = String(reader.result || ''); };
        image.onerror = reject;
        image.onload = () => {
            const canvas = document.createElement('canvas');
            const maxDimension = 1600;
            const ratio = Math.min(1, maxDimension / Math.max(image.width, image.height));
            canvas.width = Math.round(image.width * ratio);
            canvas.height = Math.round(image.height * ratio);
            const context = canvas.getContext('2d');
            if (!context) return reject(new Error('No canvas context'));
            context.drawImage(image, 0, 0, canvas.width, canvas.height);

            let quality = 0.82;
            const attempt = () => {
                canvas.toBlob(blob => {
                    if (!blob) return reject(new Error('No compressed blob'));
                    if (blob.size <= maxSize || quality <= 0.45) {
                        resolve(new File([blob], file.name.replace(/\.(png|jpg|jpeg)$/i, '.jpg'), { type: 'image/jpeg' }));
                        return;
                    }
                    quality -= 0.12;
                    attempt();
                }, 'image/jpeg', quality);
            };
            attempt();
        };
        reader.readAsDataURL(file);
    });
}

function escapeHtmlForForm(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

document.addEventListener('DOMContentLoaded', initializeEnhancedMosqueFormUx);
