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

        return $errors;
    }
}
