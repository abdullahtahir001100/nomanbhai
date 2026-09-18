<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = get_db();
$stmt = $pdo->query("DESCRIBE client_ledgers");
echo "=== CLIENT LEDGERS SCHEMA ===\n";
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
