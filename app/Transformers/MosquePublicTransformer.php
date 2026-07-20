<?php

declare(strict_types=1);

namespace App\Transformers;

use App\Core\Config;

final class MosquePublicTransformer
{
    public function __construct(private readonly Config $config)
    {
    }

    /** @param array<string, mixed> $row
     *  @return array<string, mixed>
     */
    public function transform(array $row): array
    {
        $latitude = is_numeric($row['latitude'] ?? null) ? (float) $row['latitude'] : null;
        $longitude = is_numeric($row['longitude'] ?? null) ? (float) $row['longitude'] : null;
        $hasCoordinates = $latitude !== null && $longitude !== null
            && $latitude >= -90 && $latitude <= 90
            && $longitude >= -180 && $longitude <= 180;

        return [
            'id' => (string) $row['registration_number'],
            'national_code' => $this->nullableString($row['national_code'] ?? null),
            'name' => $this->nullableString($row['mosque_name'] ?? null),
            'address' => $this->nullableString($row['address'] ?? null),
            'community' => $this->nullableString($row['community'] ?? null),
            'administrative_type' => $this->nullableString($row['admin_type'] ?? null),
            'status' => $this->nullableString($row['status'] ?? null),
            'friday_prayer' => $this->boolean($row['friday_prayer'] ?? null),
            'construction_year' => $this->constructionYear($row['construction_date'] ?? null),
            'latitude' => $latitude,
            'longitude' => $longitude,
            'has_coordinates' => $hasCoordinates,
            'image_url' => $this->imageUrl($row['main_image'] ?? null),
            'quran_memorization' => $this->boolean($row['quran_memorization'] ?? null),
            'literacy_program' => $this->boolean($row['literacy_program'] ?? null),
            'guidance_program' => $this->boolean($row['guidance_program'] ?? null),
        ];
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private function boolean(mixed $value): ?bool
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (is_bool($value)) {
            return $value;
        }

        return match (mb_strtolower(trim((string) $value), 'UTF-8')) {
            'نعم', '1', 'true', 'yes' => true,
            'لا', '0', 'false', 'no' => false,
            default => null,
        };
    }

    private function constructionYear(mixed $value): ?int
    {
        if (preg_match('/^(\d{4})/', trim((string) ($value ?? '')), $match) !== 1) {
            return null;
        }

        $year = (int) $match[1];

        return $year >= 1000 && $year <= (int) date('Y') + 1 ? $year : null;
    }

    private function imageUrl(mixed $value): string
    {
        $path = str_replace('\\', '/', trim((string) ($value ?? '')));
        $file = rtrim((string) $this->config->get('uploads.mosques_dir'), '/\\')
            . DIRECTORY_SEPARATOR . basename($path);
        if (preg_match('/^uploads\/mosques\/[A-Za-z0-9._-]+$/', $path) !== 1 || !is_file($file)) {
            $path = (string) $this->config->get('api.default_image');
        }

        return rtrim((string) $this->config->get('app.url'), '/') . '/' . ltrim($path, '/');
    }
}
