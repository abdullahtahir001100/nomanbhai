<?php
// scratch/execute_clean_reset.php - Production Clean Slate Reset
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/store_data.php';

echo "=== SMART MOBILE ERP: PRODUCTION CLEAN SLATE RESET ===\n\n";

$pdo = get_db();
if (!$pdo) {
    die("ERROR: Database connection failed. Aborting.\n");
}

// 1. Disable Foreign Key Checks and Truncate Data Tables
echo "[1] Truncating database tables (Products, Vendors, Clients, Sales, Expenses, Closings)...\n";
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
    'products'
];

foreach ($tablesToTruncate as $t) {
    $pdo->exec("TRUNCATE TABLE `$t`;");
    echo "  - Truncated table: $t\n";
}

$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");
echo "  -> Database tables cleared successfully.\n\n";

// 2. Ensure Users table has clean system users (admin, staff, client)
echo "[2] Ensuring system login accounts exist in users table...\n";
$pdo->exec("
INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `phone`)
VALUES 
('admin', '1234', 'Administrator', 'admin', '0300-1122334'),
('staff', '1234', 'Counter Operator', 'cashier', '0321-4455667'),
('client', '1234', 'Wholesale Client', 'client', '0300-1234567')
ON DUPLICATE KEY UPDATE 
    `password` = VALUES(`password`),
    `full_name` = VALUES(`full_name`),
    `role` = VALUES(`role`);
");
echo "  -> Verified users: admin (1234), staff (1234), client (1234).\n\n";

// 3. Ensure Categories exist for item categorization
echo "[3] Ensuring standard product categories exist...\n";
$pdo->exec("
INSERT INTO `categories` (`name`, `description`)
VALUES 
('Panels', 'Mobile OLED, IPS Displays & Touch Screens'),
('Batteries', 'Original & High-Capacity Mobile Batteries'),
('Chargers', 'Fast Chargers, Adapters & Power Bricks'),
('Accessories', 'Earbuds, Data Cables, Covers & Protectors')
ON DUPLICATE KEY UPDATE `name` = VALUES(`name`);
");
echo "  -> Standard categories verified.\n\n";

// 4. Clean all JSON data files
echo "[4] Resetting JSON flat files to clean production states...\n";
$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0777, true);
}

// store_state.json
$emptyStoreState = [
    'today_sales'          => 0,
    'stock_in_today'       => 0,
    'stock_out_today'      => 0,
    'daily_closing_status' => 'Pending',
    'last_updated'         => date('Y-m-d H:i:s'),
    'products'             => new stdClass() // outputs {} in JSON
];
file_put_contents($dataDir . '/store_state.json', json_encode($emptyStoreState, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "  - Reset store_state.json (0 products, 0 sales)\n";

file_put_contents($dataDir . '/clients.json', '{}');
echo "  - Reset clients.json ({})\n";

file_put_contents($dataDir . '/client_ledgers.json', '{}');
echo "  - Reset client_ledgers.json ({})\n";

file_put_contents($dataDir . '/vendors.json', '{}');
echo "  - Reset vendors.json ({})\n";

file_put_contents($dataDir . '/vendor_ledgers.json', '{}');
echo "  - Reset vendor_ledgers.json ({})\n";

file_put_contents($dataDir . '/sales_history.json', '[]');
echo "  - Reset sales_history.json ([])\n";

file_put_contents($dataDir . '/expenses.json', '[]');
echo "  - Reset expenses.json ([])\n";

file_put_contents($dataDir . '/closing_history.json', '[]');
echo "  - Reset closing_history.json ([])\n";

echo "\nSUCCESS: Clean slate reset completed!\n";
