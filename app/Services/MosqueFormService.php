<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\GuideImamRepository;

/**
 * Mosque form-data shaping (legacy processMosqueFormData()).
 *
 * The returned array keeps the exact legacy key order — the insert/update
 * repository methods map these keys to the same column order the legacy
 * SQL used.
 */
final class MosqueFormService
{
    public function __construct(private readonly GuideImamRepository $guideImams)
    {
    }

    /**
     * @param array<string, mixed> $postData
     * @return array<string, mixed>
     */
    public function processFormData(array $postData, ?string $existingImage = null): array
    {
        $adminType = $this->sanitizeInput($postData['admin_type'] ?? '');
        $community = '';
        $administrativeAttachment = '';

        if ($adminType == 'pashalik') {
            $community = $this->sanitizeInput($postData['pashalik_community'] ?? '');
            $administrativeAttachment = $this->sanitizeInput($postData['administrative_attachment'] ?? '');
        } elseif ($adminType == 'circle') {
            $community = $this->sanitizeInput($postData['circle_community'] ?? '');
        }

        $guideImamId = !empty($postData['guide_imam_id']) ? (int) $postData['guide_imam_id'] : null;
        $guideImamName = $guideImamId ? $this->guideImams->findDisplayName($guideImamId) : '';

        return [
            'mosque_name' => $this->sanitizeInput($postData['mosque_name'] ?? ''),
            'address' => $this->sanitizeInput($postData['address'] ?? ''),
            'construction_date' => $this->validateConstructionYear((string) ($postData['construction_year'] ?? '')),
            'national_code' => $this->sanitizeInput($postData['national_code'] ?? ''),
            'status' => $this->sanitizeInput($postData['status'] ?? 'مفتوح'),
            'friday_prayer' => $this->sanitizeInput($postData['friday_prayer'] ?? 'نعم'),
            'community' => $community,
            'funding_source' => $this->sanitizeInput($postData['funding_source'] ?? 'الأوقاف'),
            'imam_name' => $this->sanitizeInput($postData['imam_name'] ?? ''),
            'imam_registration' => $this->sanitizeInput($postData['imam_registration'] ?? ''),
            'imam_phone' => $this->validatePhone((string) ($postData['imam_phone'] ?? '')),
            'preacher_name' => $this->sanitizeInput($postData['preacher_name'] ?? ''),
            'preacher_registration' => $this->sanitizeInput($postData['preacher_registration'] ?? ''),
            'preacher_phone' => $this->validatePhone((string) ($postData['preacher_phone'] ?? '')),
            'muezzin_name' => $this->sanitizeInput($postData['muezzin_name'] ?? ''),
            'muezzin_registration' => $this->sanitizeInput($postData['muezzin_registration'] ?? ''),
            'muezzin_phone' => $this->validatePhone((string) ($postData['muezzin_phone'] ?? '')),
            'quran_memorization' => $this->sanitizeInput($postData['quran_memorization'] ?? 'نعم'),
            'literacy_program' => $this->sanitizeInput($postData['literacy_program'] ?? 'نعم'),
            'guidance_program' => $this->sanitizeInput($postData['guidance_program'] ?? 'نعم'),
            'guide_imam' => $guideImamName,
            'notes' => $this->sanitizeInput($postData['notes'] ?? ''),
            'administrative_attachment' => $administrativeAttachment,
            'admin_type' => $adminType,
            'pashalik' => $this->sanitizeInput($postData['pashalik'] ?? ''),
            'circle' => $this->sanitizeInput($postData['circle'] ?? ''),
            'leadership' => $this->sanitizeInput($postData['leadership'] ?? ''),
            'main_image' => $existingImage,
            // GPS COORDINATES
            'latitude' => $this->validateGPS((string) ($postData['latitude'] ?? '')),
            'longitude' => $this->validateGPS((string) ($postData['longitude'] ?? '')),
            'guide_imam_id' => $guideImamId,
        ];
    }

    /** Legacy sanitizeInput(): trim + htmlspecialchars at input time. */
    private function sanitizeInput(mixed $data): string
    {
        return htmlspecialchars(trim((string) $data), ENT_QUOTES, 'UTF-8');
    }

    /** Legacy validateConstructionYear(): YYYY -> "YYYY-01-01" or null. */
    private function validateConstructionYear(string $year): ?string
    {
        if (empty($year)) {
            return null;
        }
        if (!preg_match('/^\d{4}$/', $year)) {
            return null;
        }
        $yearInt = (int) $year;
        $currentYear = (int) date('Y');

        return ($yearInt >= 1000 && $yearInt <= ($currentYear + 1)) ? $year . '-01-01' : null;
    }

    /** Legacy validatePhone(): digits only, or null. */
    private function validatePhone(string $phone): ?string
    {
        if (empty($phone)) {
            return null;
        }
        $cleaned = preg_replace('/[^0-9]/', '', $phone);

        return !empty($cleaned) ? $cleaned : null;
    }

    /** Legacy validateGPS(): normalized float within range, or null. */
    private function validateGPS(string $coordinate): ?float
    {
        if (empty($coordinate)) {
            return null;
        }

        // Handle coordinates with minus sign at the end (if any)
        if (preg_match('/^(\d+\.\d+)-$/', $coordinate, $matches)) {
            $coordinate = '-' . $matches[1];
        }

        $coordinate = trim($coordinate);

        // Latitude: -90 to +90, Longitude: -180 to +180
        if (preg_match('/^-?\d{1,2}\.\d{1,16}$/', $coordinate)
            || preg_match('/^-?\d{1,3}\.\d{1,16}$/', $coordinate)
        ) {
            $floatValue = floatval($coordinate);

            if (abs($floatValue) <= 180) {
                return $floatValue;
            }
        }

        return null;
    }
}
