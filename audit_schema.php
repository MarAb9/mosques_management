<?php
require_once 'includes/config.php';

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
