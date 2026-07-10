<?php
require_once '../includes/config.php';
require_once '../includes/auth.php';
checkAuth();

header('Content-Type: application/json');

$searchTerm = isset($_GET['q']) ? trim($_GET['q']) : '';
$community = isset($_GET['community']) ? trim($_GET['community']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$fridayPrayer = isset($_GET['friday_prayer']) ? trim($_GET['friday_prayer']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$start = ($page - 1) * $limit;

try {
    $baseConditions = [];
    $params = [];
    
    // Search conditions (combined with OR)
    if (!empty($searchTerm)) {
        $searchConditions = [
            "mosque_name LIKE ?",
            "imam_name LIKE ?",
            "preacher_name LIKE ?",
            "muezzin_name LIKE ?",
            "gi.display_name LIKE ?",
            "gi.display_name_normalized LIKE ?",
            "m.guide_imam LIKE ?",
            "national_code LIKE ?",
            "imam_registration LIKE ?",
            "address LIKE ?"
        ];
        $searchParam = "%$searchTerm%";
        $params = array_fill(0, count($searchConditions), $searchParam);
        $baseConditions[] = "(" . implode(" OR ", $searchConditions) . ")";
    }
    
    // Community filter (AND condition)
    if (!empty($community)) {
        $baseConditions[] = "community = ?";
        $params[] = $community;
    }
    
    // Status filter (AND condition)
    if (!empty($status)) {
        $baseConditions[] = "status = ?";
        $params[] = $status;
    }
    
    // Friday prayer filter (AND condition)
    if (!empty($fridayPrayer)) {
        $baseConditions[] = "friday_prayer = ?";
        $params[] = $fridayPrayer;
    }
    
    // Build the WHERE clause
    $whereClause = !empty($baseConditions) ? "WHERE " . implode(" AND ", $baseConditions) : "";
    
    // Count query
    $countSql = "SELECT COUNT(*) FROM mosques m LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id $whereClause";
    
    // Get total count
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->execute($params);
    $total = $stmtCount->fetchColumn();
    
    // Results query
    $sql = "SELECT m.*, COALESCE(gi.display_name, m.guide_imam) AS guide_imam 
            FROM mosques m 
            LEFT JOIN guide_imams gi ON m.guide_imam_id = gi.id 
            $whereClause 
            ORDER BY m.registration_number DESC 
            LIMIT ?, ?";
    $params[] = $start;
    $params[] = $limit;
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
        $stmt->bindValue($key + 1, $value, $paramType);
    }
    
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'data' => $results,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}