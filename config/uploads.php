<?php

declare(strict_types=1);

use App\Core\Config;

$basePath = dirname(__DIR__);
$publicPath = Config::env('PUBLIC_PATH');

if ($publicPath === '') {
    $serverDocumentRoot = (string) ($_SERVER['DOCUMENT_ROOT'] ?? '');
    $publicPath = $serverDocumentRoot !== '' ? $serverDocumentRoot : $basePath . '/public';
}

$publicPath = rtrim(str_replace('\\', '/', $publicPath), '/');

/*
 * Upload paths. Only public/ is web-accessible; uploaded mosque images live
 * below that document root while application code remains private.
 */
return [
    // Absolute filesystem directory where mosque images are stored.
    'mosques_dir' => $publicPath . '/uploads/mosques',

    // URL prefix stored in the database and used in <img src> (unchanged).
    'mosques_url' => 'uploads/mosques',

    // Validation limits (same as legacy validateImageUpload()).
    'max_size' => 2 * 1024 * 1024,
    'max_pixels' => 25_000_000,
    'allowed_extensions' => ['jpg', 'jpeg', 'png'],
    'allowed_mime_types' => ['image/jpeg', 'image/png'],
    'imports' => [
        'max_size' => 5 * 1024 * 1024,
        'max_uncompressed_size' => 50 * 1024 * 1024,
        'max_rows' => 5000,
        'allowed_extensions' => ['xlsx', 'xls'],
        'allowed_mime_types' => [
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-excel',
            'application/zip',
            'application/x-ole-storage',
            'application/octet-stream',
        ],
    ],
];
