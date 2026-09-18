<?php
require_once __DIR__ . '/../includes/db.php';
$pdo = get_db();
if (!$pdo) {
    echo "No DB Connection\n";
    exit(1);
}

try {
    // Alter secret_pin to VARCHAR(100)
    $pdo->exec("ALTER TABLE `clients` MODIFY `secret_pin` VARCHAR(100) DEFAULT '1234'");
    echo "Successfully updated clients table column secret_pin to VARCHAR(100)\n";
} catch (Throwable $e) {
    echo "DB Error: " . $e->getMessage() . "\n";
}
