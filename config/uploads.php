<?php

declare(strict_types=1);

$basePath = dirname(__DIR__);

/*
 * Upload paths. The web root is the repository root until the final
 * docroot flip to public/; the 'dir' below is the single source of truth
 * used by the upload service, so the flip only changes this file.
 */
return [
    // Absolute filesystem directory where mosque images are stored.
    'mosques_dir' => $basePath . '/uploads/mosques',

    // URL prefix stored in the database and used in <img src> (unchanged).
    'mosques_url' => 'uploads/mosques',

    // Validation limits (same as legacy validateImageUpload()).
    'max_size' => 2 * 1024 * 1024,
    'allowed_extensions' => ['jpg', 'jpeg', 'png'],
    'allowed_mime_types' => ['image/jpeg', 'image/png'],
];
