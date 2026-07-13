<?php
// scripts/audit_schema.php — dev utility: print the live DB schema.
// Run: docker compose exec app php scripts/audit_schema.php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

$app = require dirname(__DIR__) . '/bootstrap/app.php';
$pdo = $app->database->pdo();

// List tables
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
echo "Tables:\n";
print_r($tables);

// Describe mosques table
$columns = $pdo->query("DESCRIBE mosques")->fetchAll(PDO::FETCH_ASSOC);
echo "\nMosques columns:\n";
foreach ($columns as $col) {
    echo "{$col['Field']} - {$col['Type']}\n";
}

// Describe other tables if any
foreach ($tables as $table) {
    if ($table != 'mosques') {
        $cols = $pdo->query("DESCRIBE `$table`")->fetchAll(PDO::FETCH_ASSOC);
        echo "\nTable `$table` columns:\n";
        foreach ($cols as $col) {
            echo "{$col['Field']} - {$col['Type']}\n";
        }
    }
}
