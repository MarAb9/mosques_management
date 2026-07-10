<?php
require_once 'includes/config.php';
require_once 'includes/auth_check.php';
require_once 'includes/mosque_functions.php';
checkAuth();


if (!canCreateMosque()) {
    http_response_code(403);
    die("غير مصرح بإضافة مساجد جديدة");
}

// Initialize form data and errors
$formData = [];
$errors = [];

// Image upload settings
$uploadDir = 'uploads/mosques/';
$maxFileSize = 2 * 1024 * 1024; // 2MB

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf_token();

    try {
        // Process form data
        $formData = processMosqueFormData($_POST);

        // Validate required fields
        $errors = validateMosqueRequiredFields($formData, $formData['admin_type']);
        
        // Check for duplicate national code
        if (empty($errors['national_code'])) {
            $stmt = $pdo->prepare("SELECT registration_number FROM mosques WHERE national_code = ?");
            $stmt->execute([$formData['national_code']]);
            if ($stmt->fetch()) {
                $errors['national_code'] = "الرمز الوطني مسجل مسبقاً";
            }
        }

        // Process form if no errors
        if (empty($errors)) {
            $imagePath = null;
            
            // Handle file upload if provided
            if (!empty($_FILES['main_image']['name'])) {
                $file = $_FILES['main_image'];
                $uploadErrors = validateImageUpload($file, $maxFileSize);
                
                if (!empty($uploadErrors)) {
                    $errors['main_image'] = implode("<br>", $uploadErrors);
                } else {
                    // Create upload directory if it doesn't exist
                    if (!file_exists($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = uniqid('mosque_') . '.' . $fileExt;
                    $destination = $uploadDir . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $destination)) {
                        $imagePath = $destination;
                    } else {
                        $errors['main_image'] = "فشل تحميل الملف. يرجى المحاولة مرة أخرى";
                    }
                }
            }
            
            // Only proceed with database insertion if no errors
            if (empty($errors)) {
                $formData['main_image'] = $imagePath;
                
                $stmt = $pdo->prepare("INSERT INTO mosques (
                    mosque_name, address, construction_date, national_code, status, 
                    friday_prayer, community, funding_source, imam_name, imam_registration, imam_phone, 
                    preacher_name, preacher_registration, preacher_phone, muezzin_name, muezzin_registration, 
                    muezzin_phone, quran_memorization, literacy_program, guidance_program, guide_imam, 
                    notes, administrative_attachment, admin_type, pashalik, circle, leadership, main_image,
                    latitude, longitude, guide_imam_id
                ) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

                $stmt->execute(array_values($formData));
                
                $_SESSION['success'] = "تمت إضافة المسجد بنجاح";
                header("Location: mosques.php");
                exit();
            }
        }
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        $errors['database'] = "حدث خطأ في النظام. يرجى المحاولة لاحقاً";
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="page-title-box d-flex align-items-center justify-content-between">
                <h4 class="mb-0">إضافة مسجد جديد</h4>
            </div>
        </div>
    </div>
    <br>
    <?php if (!empty($errors['database'])): ?>
        <div class="alert alert-danger"><?= $errors['database'] ?></div>
    <?php endif; ?>

    <form method="POST" action="" class="needs-validation" novalidate enctype="multipart/form-data">
        <?= csrf_field() ?>
        <div class="row g-4">
            <!-- Mosque Basic Information -->
            <div class="col-md-6">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>المعلومات الأساسية للمسجد</h5>
                    </div>
                    <div class="card-body">
                        
                        <div class="mb-3">
                            <label for="main_image" class="form-label">الصورة الرئيسية للمسجد</label>
                            <input type="file" class="form-control <?= isset($errors['main_image']) ? 'is-invalid' : '' ?>" 
                                   id="main_image" name="main_image" accept="image/jpeg, image/png">
                            <small class="text-muted">الحد الأقصى لحجم الملف: 2MB (الصيغ المسموحة: JPG, PNG)</small>
                            <?php if (isset($errors['main_image'])): ?>
                                <div class="invalid-feedback d-block"><?= $errors['main_image'] ?></div>
                            <?php endif; ?>
                            <div id="image-preview-container" class="mt-2 d-none">
                                <img id="image-preview" class="img-thumbnail" style="max-height: 200px;">
                                <button type="button" id="remove-image" class="btn btn-sm btn-danger mt-2">
                                    <i class="fas fa-trash"></i> إزالة الصورة
                                </button>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="mosque_name" class="form-label">اسم المسجد <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['mosque_name']) ? 'is-invalid' : '' ?>" 
                                   id="mosque_name" name="mosque_name" value="<?= $formData['mosque_name'] ?? '' ?>" required>
                            <div class="invalid-feedback"><?= $errors['mosque_name'] ?? 'يرجى إدخال اسم المسجد' ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="national_code" class="form-label">الرمز الوطني <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['national_code']) ? 'is-invalid' : '' ?>" 
                                   id="national_code" name="national_code" value="<?= $formData['national_code'] ?? '' ?>" required>
                            <div class="invalid-feedback"><?= $errors['national_code'] ?? 'يرجى إدخال الرقم الوطني' ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">العنوان <span class="text-danger">*</span></label>
                            <textarea class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" 
                                      id="address" name="address" rows="2" required><?= $formData['address'] ?? '' ?></textarea>
                            <div class="invalid-feedback"><?= $errors['address'] ?? 'يرجى إدخال عنوان المسجد' ?></div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="construction_year" class="form-label">سنة البناء <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['construction_date']) ? 'is-invalid' : '' ?>" 
                                   id="construction_year" name="construction_year" 
                                   value="<?= $formData['construction_year'] ?? '' ?>" 
                                   pattern="\d{4}" 
                                   maxlength="4"
                                   placeholder="YYYY"
                                   required>
                            <div class="invalid-feedback"><?= $errors['construction_date'] ?? 'يرجى إدخال سنة البناء (4 أرقام)' ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-12">
                                <label class="form-label">موقع GPS</label>
                                <div class="card border-0 shadow-sm">
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                    <label class="form-label">خط العرض (Latitude)</label>
                                                    <input type="text" name="latitude" class="form-control" 
                                                        placeholder="34.020882" value="<?= htmlspecialchars($formData['latitude'] ?? '') ?>"
                                                        pattern="-?\d{1,2}\.\d{1,8}">
                                            </div>
                                            <div class="col-md-6">
                                                    <label class="form-label">خط الطول (Longitude)</label>
                                                    <input type="text" name="longitude" class="form-control" 
                                                        placeholder="-6.841650" value="<?= htmlspecialchars($formData['longitude'] ?? '') ?>"
                                                        pattern="-?\d{1,3}\.\d{1,8}">
                                            </div>
                                        </div>
                                        
                                        <!-- Map Interface -->
                                        <div class="mt-3">
                                            <div id="mapContainer" style="height: 300px; display: none;" class="border rounded">
                                                <div id="map" style="height: 100%;"></div>
                                            </div>
                                            <button type="button" id="showMapBtn" class="btn btn-outline-primary btn-sm mt-2">
                                                <i class="fas fa-map-marker-alt me-2"></i>اختر الموقع على الخريطة
                                            </button>
                                            <button type="button" id="getCurrentLocationBtn" class="btn btn-outline-success btn-sm mt-2">
                                                <i class="fas fa-location-arrow me-2"></i>استخدام موقعي الحالي
                                            </button>
                                        </div>
                                        
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle me-1"></i>
                                            مثال: 34.020882, -6.841650 (بركان)
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
                
                <!-- Mosque Services -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-tasks me-2 text-primary"></i>خدمات المسجد</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="friday_prayer" class="form-label">صلاة الجمعة</label>
                                    <select class="form-select" id="friday_prayer" name="friday_prayer">
                                        <option value="نعم" <?= ($formData['friday_prayer'] ?? 'نعم') == 'نعم' ? 'selected' : '' ?>>نعم</option>
                                        <option value="لا" <?= ($formData['friday_prayer'] ?? 'نعم') == 'لا' ? 'selected' : '' ?>>لا</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="quran_memorization" class="form-label">تحفيظ القرآن الكريم</label>
                                    <select class="form-select" id="quran_memorization" name="quran_memorization">
                                        <option value="نعم" <?= ($formData['quran_memorization'] ?? 'نعم') == 'نعم' ? 'selected' : '' ?>>نعم</option>
                                        <option value="لا" <?= ($formData['quran_memorization'] ?? 'نعم') == 'لا' ? 'selected' : '' ?>>لا</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="literacy_program" class="form-label">محو الأمية</label>
                                    <select class="form-select" id="literacy_program" name="literacy_program">
                                        <option value="نعم" <?= ($formData['literacy_program'] ?? 'نعم') == 'نعم' ? 'selected' : '' ?>>نعم</option>
                                        <option value="لا" <?= ($formData['literacy_program'] ?? 'نعم') == 'لا' ? 'selected' : '' ?>>لا</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="guidance_program" class="form-label">الوعظ والإرشاد</label>
                                    <select class="form-select" id="guidance_program" name="guidance_program">
                                        <option value="نعم" <?= ($formData['guidance_program'] ?? 'نعم') == 'نعم' ? 'selected' : '' ?>>نعم</option>
                                        <option value="لا" <?= ($formData['guidance_program'] ?? 'نعم') == 'لا' ? 'selected' : '' ?>>لا</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Administrative Information -->
            <div class="col-md-6">
                <!-- Administrative Hierarchy Section -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-sitemap me-2 text-primary"></i>التقسيم الإداري</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="admin_type" class="form-label">نوع التقسيم الإداري <span class="text-danger">*</span></label>
                            <select class="form-select <?= isset($errors['admin_type']) ? 'is-invalid' : '' ?>" 
                                    id="admin_type" name="admin_type" required>
                                <option value="">-- اختر النوع --</option>
                                <option value="pashalik" <?= ($formData['admin_type'] ?? '') == 'pashalik' ? 'selected' : '' ?>>باشوية</option>
                                <option value="circle" <?= ($formData['admin_type'] ?? '') == 'circle' ? 'selected' : '' ?>>دائرة</option>
                            </select>
                            <div class="invalid-feedback"><?= $errors['admin_type'] ?? 'يرجى اختيار نوع التقسيم الإداري' ?></div>
                        </div>

                        <!-- Pashalik Section -->
                        <div id="pashalik_section" style="display: none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="pashalik" class="form-label">الباشوية <span class="text-danger">*</span></label>
                                        <select class="form-select <?= isset($errors['pashalik']) ? 'is-invalid' : '' ?>" 
                                               id="pashalik" name="pashalik" required>
                                            <option value="">-- اختر الباشوية --</option>
                                            <option value="بركان" <?= ($formData['pashalik'] ?? '') == 'بركان' ? 'selected' : '' ?>>باشوية بركان</option>
                                            <option value="سيدي سليمان شراعة" <?= ($formData['pashalik'] ?? '') == 'سيدي سليمان شراعة' ? 'selected' : '' ?>>باشوية سيدي سليمان شراعة</option>
                                            <option value="أحفير" <?= ($formData['pashalik'] ?? '') == 'أحفير' ? 'selected' : '' ?>>باشوية أحفير</option>
                                            <option value="السعيدية" <?= ($formData['pashalik'] ?? '') == 'السعيدية' ? 'selected' : '' ?>>باشوية السعيدية</option>
                                            <option value="أكليم" <?= ($formData['pashalik'] ?? '') == 'أكليم' ? 'selected' : '' ?>>باشوية أكليم</option>
                                            <option value="عين الركادة" <?= ($formData['pashalik'] ?? '') == 'عين الركادة' ? 'selected' : '' ?>>باشوية عين الركادة</option>
                                        </select>
                                        <div class="invalid-feedback"><?= $errors['pashalik'] ?? 'يرجى اختيار الباشوية' ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="pashalik_community" class="form-label">الجماعة <span class="text-danger">*</span></label>
                                        <select class="form-select <?= isset($errors['community']) ? 'is-invalid' : '' ?>" 
                                               id="pashalik_community" name="pashalik_community" required>
                                            <option value="">-- اختر الجماعة --</option>
                                        </select>
                                        <div class="invalid-feedback"><?= $errors['community'] ?? 'يرجى اختيار الجماعة' ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="administrative_attachment" class="form-label">الملحقة/المقاطعة الإدارية</label>
                                        <select class="form-select" id="administrative_attachment" name="administrative_attachment">
                                            <option value="">-- اختر الملحقة/المقاطعة --</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Circle Section -->
                        <div id="circle_section" style="display: none;">
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="circle" class="form-label">الدائرة <span class="text-danger">*</span></label>
                                        <select class="form-select <?= isset($errors['circle']) ? 'is-invalid' : '' ?>" 
                                               id="circle" name="circle" required>
                                            <option value="">-- اختر الدائرة --</option>
                                            <option value="أحفير" <?= ($formData['circle'] ?? '') == 'أحفير' ? 'selected' : '' ?>>دائرة أحفير</option>
                                            <option value="أكليم" <?= ($formData['circle'] ?? '') == 'أكليم' ? 'selected' : '' ?>>دائرة أكليم</option>
                                        </select>
                                        <div class="invalid-feedback"><?= $errors['circle'] ?? 'يرجى اختيار الدائرة' ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="leadership" class="form-label">القيادة <span class="text-danger">*</span></label>
                                        <select class="form-select <?= isset($errors['leadership']) ? 'is-invalid' : '' ?>" 
                                               id="leadership" name="leadership" required>
                                            <option value="">-- اختر القيادة --</option>
                                        </select>
                                        <div class="invalid-feedback"><?= $errors['leadership'] ?? 'يرجى اختيار القيادة' ?></div>
                                    </div>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="circle_community" class="form-label">الجماعة <span class="text-danger">*</span></label>
                                        <select class="form-select <?= isset($errors['community']) ? 'is-invalid' : '' ?>" 
                                               id="circle_community" name="circle_community" required>
                                            <option value="">-- اختر الجماعة --</option>
                                        </select>
                                        <div class="invalid-feedback"><?= $errors['community'] ?? 'يرجى اختيار الجماعة' ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Mosque Status and Funding -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-info-circle me-2 text-primary"></i>الحالة و التمويل</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="status" class="form-label">الوضعية</label>
                            <select class="form-select" id="status" name="status">
                                <option value="مفتوح" <?= ($formData['status'] ?? 'مفتوح') == 'مفتوح' ? 'selected' : '' ?>>مفتوح</option>
                                <option value="مغلق" <?= ($formData['status'] ?? 'مفتوح') == 'مغلق' ? 'selected' : '' ?>>مغلق</option>
                                <option value="مفتوح دون ترخيص" <?= ($formData['status'] ?? 'مفتوح') == 'مفتوح دون ترخيص' ? 'selected' : '' ?>>مفتوح دون ترخيص</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="funding_source" class="form-label">جهة الإنفاق</label>
                            <select class="form-select" id="funding_source" name="funding_source">
                                <option value="الأوقاف" <?= ($formData['funding_source'] ?? 'الأوقاف') == 'الأوقاف' ? 'selected' : '' ?>>الأوقاف</option>
                                <option value="الأوقاف والمحسنون" <?= ($formData['funding_source'] ?? 'الأوقاف') == 'الأوقاف والمحسنون' ? 'selected' : '' ?>>الأوقاف والمحسنون</option>
                                <option value="المحسنون" <?= ($formData['funding_source'] ?? 'الأوقاف') == 'المحسنون' ? 'selected' : '' ?>>المحسنون</option>
                            </select>
                        </div>
                    </div>
                </div>
                
                <!-- Mosque Staff Information -->
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-users me-2 text-primary"></i>طاقم المسجد</h5>
                    </div>
                    <div class="card-body">
                        <ul class="nav nav-tabs mb-3" id="staffTabs" role="tablist">
                            <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="imam-tab" data-bs-toggle="tab" data-bs-target="#imam" type="button" role="tab">الإمام</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="preacher-tab" data-bs-toggle="tab" data-bs-target="#preacher" type="button" role="tab">الخطيب</button>
                            </li>
                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="muezzin-tab" data-bs-toggle="tab" data-bs-target="#muezzin" type="button" role="tab">المؤذن</button>
                            </li>
                        </ul>
                        
                        <div class="tab-content" id="staffTabsContent">
                            <!-- Imam Tab -->
                            <div class="tab-pane fade show active" id="imam" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="imam_name" class="form-label">اسم الإمام</label>
                                            <input type="text" class="form-control" id="imam_name" name="imam_name" 
                                                   value="<?= $formData['imam_name'] ?? '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="imam_registration" class="form-label">ر.ب.ت.و</label>
                                            <input type="text" class="form-control" id="imam_registration" name="imam_registration" 
                                                   value="<?= $formData['imam_registration'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="imam_phone" class="form-label">رقم الهاتف</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" id="imam_phone" name="imam_phone" 
                                               value="<?= $formData['imam_phone'] ?? '' ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="guide_imam_id" class="form-label">الإمام المرشد</label>
                                    <select class="form-select" id="guide_imam_id" name="guide_imam_id">
                                        <option value="">-- اختر الإمام المرشد --</option>
                                        <?php
                                        $guideImams = $pdo->query("SELECT id, display_name FROM guide_imams ORDER BY display_name_normalized")->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($guideImams as $imam) {
                                            $selected = (isset($formData['guide_imam_id']) && $formData['guide_imam_id'] == $imam['id']) ? 'selected' : '';
                                            echo "<option value=\"" . $imam['id'] . "\" $selected>" . htmlspecialchars($imam['display_name']) . "</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                            </div>
                            
                            <!-- Preacher Tab -->
                            <div class="tab-pane fade" id="preacher" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="preacher_name" class="form-label">اسم الخطيب</label>
                                            <input type="text" class="form-control" id="preacher_name" name="preacher_name" 
                                                   value="<?= $formData['preacher_name'] ?? '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="preacher_registration" class="form-label">ر.ب.ت.و</label>
                                            <input type="text" class="form-control" id="preacher_registration" name="preacher_registration" 
                                                   value="<?= $formData['preacher_registration'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="preacher_phone" class="form-label">هاتف الخطيب</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" id="preacher_phone" name="preacher_phone" 
                                               value="<?= $formData['preacher_phone'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Muezzin Tab -->
                            <div class="tab-pane fade" id="muezzin" role="tabpanel">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="muezzin_name" class="form-label">المؤذن</label>
                                            <input type="text" class="form-control" id="muezzin_name" name="muezzin_name" 
                                                   value="<?= $formData['muezzin_name'] ?? '' ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="muezzin_registration" class="form-label">ر ب ت و</label>
                                            <input type="text" class="form-control" id="muezzin_registration" name="muezzin_registration" 
                                                   value="<?= $formData['muezzin_registration'] ?? '' ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label for="muezzin_phone" class="form-label">رقم الهاتف</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-phone"></i></span>
                                        <input type="tel" class="form-control" id="muezzin_phone" name="muezzin_phone" 
                                               value="<?= $formData['muezzin_phone'] ?? '' ?>">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Additional Information -->
            <div class="col-12">
                <div class="card mb-4">
                    <div class="card-header bg-light">
                        <h5 class="mb-0"><i class="fas fa-plus-circle me-2 text-primary"></i>معلومات إضافية</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="notes" class="form-label">ملاحظات</label>
                            <textarea class="form-control" id="notes" name="notes" rows="3"><?= $formData['notes'] ?? '' ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Form buttons -->
        <div class="d-flex justify-content-between mt-4">
            <button type="reset" class="btn btn-outline-secondary">
                <i class="fas fa-undo me-1"></i> إعادة تعيين
            </button>
            <div>
                <a href="mosques.php" class="btn btn-danger me-2">
                    <i class="fas fa-times me-1"></i> إلغاء
                </a>
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-save me-1"></i> حفظ البيانات
                </button>
            </div>
        </div>
    </form>
</div>

<script src="assets/js/mosque_form.js"></script>

<?php require_once 'includes/footer.php'; ?>
