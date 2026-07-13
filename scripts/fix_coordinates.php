<?php
// scripts/fix_coordinates.php - one-off data fix (already applied to the
// dev DB). Moved out of the web root; run from the CLI only:
//     docker compose exec app php scripts/fix_coordinates.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$pdo = $app->database->pdo();

// Get all mosques with coordinates
$stmt = $pdo->query("SELECT registration_number, latitude, longitude FROM mosques WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
$mosques = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($mosques as $mosque) {
    $fixed_longitude = $mosque['longitude'];

    // Check if longitude has minus sign at the end
    if (preg_match('/^(\d+\.\d+)-$/', $mosque['longitude'], $matches)) {
        $fixed_longitude = '-' . $matches[1];
        echo "Fixing mosque {$mosque['registration_number']}: {$mosque['longitude']} -> {$fixed_longitude}\n";

        // Update the database
        $updateStmt = $pdo->prepare("UPDATE mosques SET longitude = ? WHERE registration_number = ?");
        $updateStmt->execute([$fixed_longitude, $mosque['registration_number']]);
    }
}

echo "Coordinate fix completed!\n";
