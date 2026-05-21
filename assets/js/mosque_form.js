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

function initializeMosqueForm() {
    // DOM Elements
    const adminTypeSelect = document.getElementById('admin_type');
    const pashalikSection = document.getElementById('pashalik_section');
    const circleSection = document.getElementById('circle_section');
    const pashalikSelect = document.getElementById('pashalik');
    const pashalikCommunitySelect = document.getElementById('pashalik_community');
    const attachmentSelect = document.getElementById('administrative_attachment');
    const attachmentContainer = document.getElementById('attachment_container') || { style: { display: 'none' } };
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
            pashalikSection.style.display = 'none';
            circleSection.style.display = 'none';
            if (attachmentContainer) attachmentContainer.style.display = 'none';
            
            // Show the selected section
            if (selectedType === 'pashalik') {
                pashalikSection.style.display = 'block';
                // Reset circle section
                if (circleSelect) circleSelect.value = '';
                if (leadershipSelect) leadershipSelect.innerHTML = '<option value="">-- اختر القيادة --</option>';
                if (circleCommunitySelect) circleCommunitySelect.innerHTML = '<option value="">-- اختر الجماعة --</option>';
            } else if (selectedType === 'circle') {
                circleSection.style.display = 'block';
                // Reset pashalik section
                if (pashalikSelect) pashalikSelect.value = '';
                if (pashalikCommunitySelect) pashalikCommunitySelect.innerHTML = '<option value="">-- اختر الجماعة --</option>';
                if (attachmentSelect) attachmentSelect.innerHTML = '<option value="">-- اختر الملحقة/المقاطعة --</option>';
                if (attachmentContainer) attachmentContainer.style.display = 'none';
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
            if (attachmentContainer) attachmentContainer.style.display = 'none';
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
                    if (attachmentContainer) attachmentContainer.style.display = 'block';
                } else {
                    if (attachmentContainer) attachmentContainer.style.display = 'none';
                }
            } else {
                if (attachmentContainer) attachmentContainer.style.display = 'none';
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
    const savedAdminType = adminTypeSelect ? adminTypeSelect.dataset.savedValue || '' : '';
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