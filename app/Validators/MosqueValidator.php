<?php

declare(strict_types=1);

namespace App\Validators;

/**
 * Server-side mosque form validation
 * (legacy validateMosqueRequiredFields(), same messages).
 */
final class MosqueValidator
{
    /**
     * @param array<string, mixed> $formData
     * @return array<string, string> field => Arabic error message
     */
    public function requiredFields(array $formData, string $adminType): array
    {
        $errors = [];
        $requiredFields = [
            'mosque_name' => 'اسم المسجد مطلوب',
            'national_code' => 'الرمز الوطني مطلوب',
            'address' => 'العنوان مطلوب',
            'construction_date' => 'سنة البناء مطلوبة',
            'admin_type' => 'نوع التقسيم الإداري مطلوب',
        ];

        // conditional required fields
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

        $lengths = [
            'mosque_name' => 255,
            'national_code' => 50,
            'community' => 100,
            'funding_source' => 100,
            'imam_name' => 100,
            'imam_registration' => 50,
            'preacher_name' => 100,
            'preacher_registration' => 50,
            'muezzin_name' => 100,
            'muezzin_registration' => 50,
            'administrative_attachment' => 100,
            'pashalik' => 100,
            'circle' => 100,
            'leadership' => 100,
        ];
        foreach ($lengths as $field => $maximum) {
            if (mb_strlen((string) ($formData[$field] ?? '')) > $maximum) {
                $errors[$field] = "القيمة طويلة جداً (الحد الأقصى {$maximum} حرفاً)";
            }
        }

        if (!empty($formData['national_code']) && !preg_match('/^\d{9}$/', (string) $formData['national_code'])) {
            $errors['national_code'] = 'الرمز الوطني يجب أن يتكون من 9 أرقام';
        }

        $this->validateEnum($errors, $formData, 'admin_type', ['pashalik', 'circle'], 'نوع التقسيم الإداري غير صالح');
        $this->validateEnum($errors, $formData, 'status', ['مفتوح', 'مغلق', 'مفتوح دون ترخيص'], 'وضعية المسجد غير صالحة');
        $this->validateEnum($errors, $formData, 'funding_source', ['الأوقاف', 'الأوقاف والمحسنون', 'المحسنون'], 'جهة الإنفاق غير صالحة');
        foreach (['friday_prayer', 'quran_memorization', 'literacy_program', 'guidance_program'] as $field) {
            $this->validateEnum($errors, $formData, $field, ['نعم', 'لا'], 'القيمة المختارة غير صالحة');
        }

        foreach (['imam_phone', 'preacher_phone', 'muezzin_phone'] as $field) {
            $phone = (string) ($formData[$field] ?? '');
            if ($phone !== '' && !preg_match('/^\d{9,15}$/', $phone)) {
                $errors[$field] = 'رقم الهاتف يجب أن يتكون من 9 إلى 15 رقماً';
            }
        }

        foreach (['latitude' => 90, 'longitude' => 180] as $field => $maximum) {
            $coordinate = $formData[$field] ?? null;
            if ($coordinate !== null
                && (!is_numeric($coordinate) || abs((float) $coordinate) > $maximum)) {
                $errors[$field] = $field === 'latitude'
                    ? 'خط العرض غير صالح؛ يجب أن يكون بين -90 و90'
                    : 'خط الطول غير صالح؛ يجب أن يكون بين -180 و180';
            }
        }

        $hasLatitude = ($formData['latitude'] ?? null) !== null;
        $hasLongitude = ($formData['longitude'] ?? null) !== null;
        if ($hasLatitude !== $hasLongitude) {
            $errors['latitude'] = 'يجب إدخال خط العرض وخط الطول معاً';
            $errors['longitude'] = 'يجب إدخال خط العرض وخط الطول معاً';
        }

        return $errors;
    }

    /** @param array<string, string> $errors @param array<string, mixed> $formData @param list<string> $allowed */
    private function validateEnum(array &$errors, array $formData, string $field, array $allowed, string $message): void
    {
        if (!in_array((string) ($formData[$field] ?? ''), $allowed, true)) {
            $errors[$field] = $message;
        }
    }
}
