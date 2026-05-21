<?php
// fix_coordinates.php - Run this once to fix existing data
require_once 'includes/config.php';

// Get all mosques with coordinates
$stmt = $pdo->query("SELECT registration_number, latitude, longitude FROM mosques WHERE latitude IS NOT NULL AND longitude IS NOT NULL");
$mosques = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($mosques as $mosque) {
    $fixed_longitude = $mosque['longitude'];
    
    // Check if longitude has minus sign at the end
    if (preg_match('/^(\d+\.\d+)-$/', $mosque['longitude'], $matches)) {
        $fixed_longitude = '-' . $matches[1];
        echo "Fixing mosque {$mosque['registration_number']}: {$mosque['longitude']} -> {$fixed_longitude}<br>";
        
        // Update the database
        $updateStmt = $pdo->prepare("UPDATE mosques SET longitude = ? WHERE registration_number = ?");
        $updateStmt->execute([$fixed_longitude, $mosque['registration_number']]);
    }
}

echo "Coordinate fix completed!";
?>