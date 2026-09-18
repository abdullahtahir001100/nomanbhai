<?php
// scratch/execute_fresh_clean_slate.php
require_once __DIR__ . '/../includes/db.php';

echo "=== SMART MOBILE ERP: COMPLETE CLEAN SLATE RESET ===\n\n";

$pdo = get_db();
if (!$pdo) {
    die("ERROR: Database connection failed. Aborting.\n");
}

// 1. Disable Foreign Key Checks and Truncate All Data Tables
echo "[1] Truncating all business data tables in MySQL...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

$tablesToTruncate = [
    'stock_adjustments',
    'sale_items',
    'sales',
    'client_ledgers',
    'clients',
    'vendor_ledgers',
    'vendors',
    'expenses',
    'daily_closings',
    'repairs',
    'products'
];

foreach ($tablesToTruncate as $t) {
    try {
        $pdo->exec("TRUNCATE TABLE `$t`;");
        echo "  - Truncated table: $t\n";
    } catch (Throwable $e) {
        echo "  ! Warning on $t: " . $e->getMessage() . "\n";
    }
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "  -> Business tables cleared successfully.\n\n";

// 2. Clean and ensure system users in `users` table
echo "[2] Ensuring clean system logins in users table...\n";
// Clear any test users
$pdo->exec("DELETE FROM `users` WHERE `username` NOT IN ('admin', 'cashier', 'staff');");

$pdo->exec("
INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `phone`)
VALUES 
('admin', '1234', 'Administrator', 'admin', '0300-1122334'),
('cashier', '1234', 'Muhammad Rizwan', 'cashier', '0321-4455667'),
('staff', '1234', 'Counter Operator', 'cashier', '0321-4455667')
ON DUPLICATE KEY UPDATE 
    `password` = VALUES(`password`),
    `full_name` = VALUES(`full_name`),
    `role` = VALUES(`role`);
");
echo "  -> Preserved Logins: admin (password: 1234), staff (password: 1234)\n\n";

// 3. Ensure clean categories
echo "[3] Resetting product categories...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");
$pdo->exec("DELETE FROM `categories`;");
$pdo->exec("ALTER TABLE `categories` AUTO_INCREMENT = 1;");
$pdo->exec("
INSERT INTO `categories` (`id`, `name`, `description`)
VALUES 
(1, 'Panels', 'Mobile OLED, IPS Displays & Touch Screens'),
(2, 'Batteries', 'Original & High-Capacity Mobile Batteries'),
(3, 'Chargers', 'Fast Chargers, Adapters & Power Bricks'),
(4, 'Accessories', 'Earbuds, Data Cables, Covers & Protectors');
");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "  -> Standard clean categories created.\n\n";

// 4. Reset JSON Flat Storage
echo "[4] Resetting data/ directory JSON cache files...\n";
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

$cleanState = [
    'today_sales'          => 0,
    'stock_in_today'       => 0,
    'stock_out_today'      => 0,
    'daily_closing_status' => 'Pending',
    'last_updated'         => date('Y-m-d H:i:s'),
    'products'             => new stdClass()
];

file_put_contents($dataDir . '/store_state.json', json_encode($cleanState, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
file_put_contents($dataDir . '/clients.json', '{}');
file_put_contents($dataDir . '/client_ledgers.json', '{}');
file_put_contents($dataDir . '/repairs.json', '{}');
file_put_contents($dataDir . '/sales_history.json', '[]');
file_put_contents($dataDir . '/expenses.json', '[]');
file_put_contents($dataDir . '/closing_history.json', '[]');
file_put_contents($dataDir . '/vendors.json', '{}');
file_put_contents($dataDir . '/vendor_ledgers.json', '{}');

echo "  - Reset store_state.json, clients.json, repairs.json, client_ledgers.json, sales_history.json\n\n";

// 5. Clean test uploads
echo "[5] Cleaning test inventory images in uploads/inventory...\n";
$invUploadDir = __DIR__ . '/../uploads/inventory';
if (is_dir($invUploadDir)) {
    $files = glob($invUploadDir . '/*');
    foreach ($files as $file) {
        if (is_file($file)) {
            unlink($file);
            echo "  - Removed test image: " . basename($file) . "\n";
        }
    }
}

echo "\n=======================================================\n";
echo "SUCCESS: The software is now completely clean & fresh!\n";
echo "Only the login credentials (admin / 1234) have been kept.\n";
echo "=======================================================\n";
