<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

try {
    $sql = "SELECT registration_number, mosque_name, address, imam_name, status, friday_prayer, latitude, longitude 
            FROM mosques 
            WHERE latitude IS NOT NULL AND longitude IS NOT NULL";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $mosques = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($mosques);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load mosque data']);
}