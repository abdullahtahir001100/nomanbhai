<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = get_db();
if (!$pdo) {
    echo "No DB\n";
    exit;
}
$stmt = $pdo->query("DESCRIBE clients");
echo "=== SCHEMA ===\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));

$stmt2 = $pdo->query("SELECT * FROM clients");
echo "=== ROWS ===\n";
print_r($stmt2->fetchAll(PDO::FETCH_ASSOC));
