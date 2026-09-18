<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = get_db();
if (!$pdo) {
    echo "No DB connection\n";
    exit;
}
$tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
foreach ($tables as $t) {
    echo "=== Table: $t ===\n";
    $stmt = $pdo->query("SELECT * FROM `$t` LIMIT 20");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "Count: " . count($rows) . "\n";
    print_r($rows);
    echo "\n";
}
