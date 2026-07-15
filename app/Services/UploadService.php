<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Config;

/**
 * Mosque image upload handling.
 *
 * Validation rules, error messages, generated file names, and the stored
 * relative path ("uploads/mosques/<name>") are identical to the legacy
 * add/edit pages. Deletion is additionally constrained to the uploads
 * directory (path-traversal protection).
 */
final class UploadService
{
    public function __construct(private readonly Config $config)
    {
    }

    /**
     * Validate an uploaded image (legacy validateImageUpload()).
     *
     * @param array<string, mixed> $file one $_FILES entry
     * @return list<string> error messages (empty when valid)
     */
    public function validateImage(array $file): array
    {
        $errors = [];
        $maxSize = (int) $this->config->get('uploads.max_size');

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'حدث خطأ أثناء تحميل الملف. الرجاء المحاولة مرة أخرى.';

            return $errors;
        }

        if ($file['size'] > $maxSize) {
            $errors[] = 'حجم الملف كبير جداً. الحد الأقصى 2MB';
        }

        $fileExt = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        $allowedExts = (array) $this->config->get('uploads.allowed_extensions');

        if (!in_array($fileExt, $allowedExts, true)) {
            $errors[] = 'نوع الملف غير مسموح به. يرجى تحميل صورة (JPG, PNG, JPEG)';
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, (string) $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = (array) $this->config->get('uploads.allowed_mime_types');

        if (!in_array($mime, $allowedMimes, true)) {
            $errors[] = 'نوع الملف غير مسموح به. يرجى تحميل صورة (JPG, PNG, JPEG)';
        }

        if (empty($errors)) {
            $imageInfo = getimagesize((string) $file['tmp_name']);
            if (!$imageInfo) {
                $errors[] = 'الملف المرفوع ليس صورة صالحة';
            } elseif (((int) $imageInfo[0] * (int) $imageInfo[1]) > (int) $this->config->get('uploads.max_pixels', 25_000_000)) {
                $errors[] = 'أبعاد الصورة كبيرة جداً';
            }
        }

        return $errors;
    }

    /**
     * Move a validated upload into the mosques folder.
     *
     * @param array<string, mixed> $file
     * @return string|null the relative path to store in the DB, or null on failure
     */
    public function storeMosqueImage(array $file): ?string
    {
        $dir = (string) $this->config->get('uploads.mosques_dir');

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $contents = file_get_contents((string) $file['tmp_name']);
        $image = $contents === false ? false : @imagecreatefromstring($contents);
        if ($image === false) {
            return null;
        }

        $mime = (string) (getimagesize((string) $file['tmp_name'])['mime'] ?? '');
        $extension = $mime === 'image/png' ? 'png' : 'jpg';
        $filename = 'mosque_' . bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $dir . '/' . $filename;

        if ($extension === 'png') {
            imagealphablending($image, false);
            imagesavealpha($image, true);
            $stored = imagepng($image, $destination, 6);
        } else {
            $stored = imagejpeg($image, $destination, 90);
        }
        imagedestroy($image);

        if (!$stored) {
            return null;
        }

        return $this->config->get('uploads.mosques_url') . '/' . $filename;
    }

    /**
     * Delete a previously stored image by its DB-stored relative path.
     * Silently ignores paths outside the uploads directory.
     */
    public function deleteMosqueImage(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') {
            return;
        }

        $dir = (string) $this->config->get('uploads.mosques_dir');
        $file = $dir . '/' . basename($relativePath);

        $realDir = realpath($dir);
        $realFile = realpath($file);

        if ($realDir !== false && $realFile !== false
            && str_starts_with($realFile, $realDir)
            && is_file($realFile)
        ) {
            unlink($realFile);
        }
    }
}
