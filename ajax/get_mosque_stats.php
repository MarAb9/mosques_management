<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';
checkAuth();

header('Content-Type: application/json');

try {
    // Get status statistics
    $statusStmt = $pdo->query("SELECT status, COUNT(*) as count FROM mosques GROUP BY status");
    $statusStats = $statusStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get Friday prayer statistics
    $fridayStmt = $pdo->query("SELECT friday_prayer, COUNT(*) as count FROM mosques GROUP BY friday_prayer");
    $fridayStats = $fridayStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get community statistics
    $communityStmt = $pdo->query("
        SELECT community, COUNT(*) as count 
        FROM mosques 
        WHERE community IS NOT NULL AND community != ''
        GROUP BY community
        ORDER BY count DESC
    ");
    $communityStats = $communityStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'statusStats' => $statusStats,
        'fridayStats' => $fridayStats,
        'communityStats' => $communityStats
    ]);
    
} catch (PDOException $e) {
    error_log("Mosque stats error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Failed to load statistics'
    ]);
}
