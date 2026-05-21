<?php
// includes/mosque_functions.php

function validateImageUpload($file, $maxSize) {
    $errors = [];
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "حدث خطأ أثناء تحميل الملف. الرجاء المحاولة مرة أخرى.";
        return $errors;
    }
    
    // Validate file size
    if ($file['size'] > $maxSize) {
        $errors[] = "حجم الملف كبير جداً. الحد الأقصى 2MB";
    }
    
    // Get file info
    $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowedExts = ['jpg', 'jpeg', 'png'];
    
    // Validate extension
    if (!in_array($fileExt, $allowedExts)) {
        $errors[] = "نوع الملف غير مسموح به. يرجى تحميل صورة (JPG, PNG, JPEG)";
    }
    
    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    
    $allowedMimes = ['image/jpeg', 'image/png'];
    
    if (!in_array($mime, $allowedMimes)) {
        $errors[] = "نوع الملف غير مسموح به. يرجى تحميل صورة (JPG, PNG, JPEG)";
    }
    
    // Additional check for image validity
    if (empty($errors)) {
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            $errors[] = "الملف المرفوع ليس صورة صالحة";
        }
    }
    
    return $errors;
}

function sanitizeInput($data) {
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validateConstructionYear($year) {
    if (empty($year)) return null;
    if (!preg_match('/^\d{4}$/', $year)) return null;
    $yearInt = (int)$year;
    $currentYear = (int)date('Y');
    return ($yearInt >= 1000 && $yearInt <= ($currentYear + 1)) ? $year . '-01-01' : null;
}

function validatePhone($phone) {
    if (empty($phone)) return null;
    $cleaned = preg_replace('/[^0-9]/', '', $phone);
    return !empty($cleaned) ? $cleaned : null;
}

function validateGPS($coordinate) {
    if (empty($coordinate)) return null;
    
    // Handle coordinates with minus sign at the end (if any)
    if (preg_match('/^(\d+\.\d+)-$/', $coordinate, $matches)) {
        $coordinate = '-' . $matches[1];
    }
    
    // Remove any extra spaces
    $coordinate = trim($coordinate);
    
    // Validate GPS coordinate format with high precision
    // Latitude: -90 to +90, Longitude: -180 to +180
    if (preg_match('/^-?\d{1,2}\.\d{1,16}$/', $coordinate) || 
        preg_match('/^-?\d{1,3}\.\d{1,16}$/', $coordinate)) {
        
        $floatValue = floatval($coordinate);
        
        // Additional range validation
        if (abs($floatValue) <= 180) { // Covers both lat (-90 to 90) and lng (-180 to 180)
            return $floatValue;
        }
    }
    
    return null;
}

function processMosqueFormData($postData, $existingImage = null) {
    $adminType = sanitizeInput($postData['admin_type'] ?? '');
    $community = '';
    $administrativeAttachment = '';
    
    if ($adminType == 'pashalik') {
        $community = sanitizeInput($postData['pashalik_community'] ?? '');
        $administrativeAttachment = sanitizeInput($postData['administrative_attachment'] ?? '');
    } elseif ($adminType == 'circle') {
        $community = sanitizeInput($postData['circle_community'] ?? '');
    }

    return [
        'mosque_name' => sanitizeInput($postData['mosque_name'] ?? ''),
        'address' => sanitizeInput($postData['address'] ?? ''),
        'construction_date' => validateConstructionYear($postData['construction_year'] ?? ''),
        'national_code' => sanitizeInput($postData['national_code'] ?? ''),
        'status' => sanitizeInput($postData['status'] ?? 'مفتوح'),
        'friday_prayer' => sanitizeInput($postData['friday_prayer'] ?? 'نعم'),
        'community' => $community,
        'funding_source' => sanitizeInput($postData['funding_source'] ?? 'الأوقاف'),
        'imam_name' => sanitizeInput($postData['imam_name'] ?? ''),
        'imam_registration' => sanitizeInput($postData['imam_registration'] ?? ''),
        'imam_phone' => validatePhone($postData['imam_phone'] ?? ''),
        'preacher_name' => sanitizeInput($postData['preacher_name'] ?? ''),
        'preacher_registration' => sanitizeInput($postData['preacher_registration'] ?? ''),
        'preacher_phone' => validatePhone($postData['preacher_phone'] ?? ''),
        'muezzin_name' => sanitizeInput($postData['muezzin_name'] ?? ''),
        'muezzin_registration' => sanitizeInput($postData['muezzin_registration'] ?? ''),
        'muezzin_phone' => validatePhone($postData['muezzin_phone'] ?? ''),
        'quran_memorization' => sanitizeInput($postData['quran_memorization'] ?? 'نعم'),
        'literacy_program' => sanitizeInput($postData['literacy_program'] ?? 'نعم'),
        'guidance_program' => sanitizeInput($postData['guidance_program'] ?? 'نعم'),
        'guide_imam' => sanitizeInput($postData['guide_imam'] ?? ''),
        'notes' => sanitizeInput($postData['notes'] ?? ''),
        'administrative_attachment' => $administrativeAttachment,
        'admin_type' => $adminType,
        'pashalik' => sanitizeInput($postData['pashalik'] ?? ''),
        'circle' => sanitizeInput($postData['circle'] ?? ''),
        'leadership' => sanitizeInput($postData['leadership'] ?? ''),
        'main_image' => $existingImage,
        //GPS COORDINATES
        'latitude' => validateGPS($postData['latitude'] ?? ''),
        'longitude' => validateGPS($postData['longitude'] ?? '')
    ];
}

function validateMosqueRequiredFields($formData, $adminType) {
    $errors = [];
    $requiredFields = [
        'mosque_name' => 'اسم المسجد مطلوب',
        'national_code' => 'الرمز الوطني مطلوب',
        'address' => 'العنوان مطلوب',
        'construction_date' => 'سنة البناء مطلوبة',
        'admin_type' => 'نوع التقسيم الإداري مطلوب'
    ];

    //conditional required fields
    if ($adminType == 'pashalik') {
        $requiredFields['pashalik'] = 'الباشوية مطلوبة';
        $requiredFields['community'] = 'الجماعة مطلوبة';
    } elseif ($adminType == 'circle') {
        $requiredFields['circle'] = 'الدائرة مطلوبة';
        $requiredFields['leadership'] = 'القيادة مطلوبة';
        $requiredFields['community'] = 'الجماعة مطلوبة';
    }

    foreach ($requiredFields as $field => $message) {
        if (empty($formData[$field])) {
            $errors[$field] = $message;
        }
    }

    return $errors;
}