<?php
// scratch/audit_system.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== SMART MOBILE ERP PRODUCTION AUDIT ===\n\n";

// 1. Config & DB Connection Check
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/store_data.php';

echo "[1] Database Connection Test:\n";
$pdo = get_db();
if ($pdo) {
    echo "  -> SUCCESS: Connected to MySQL database '" . DB_NAME . "' on " . DB_HOST . ":" . DB_PORT . "\n";
} else {
    echo "  -> WARNING: Could not connect to MySQL. Falling back to JSON flat-files.\n";
}

if ($pdo) {
    echo "\n[2] Database Table Row Counts:\n";
    $tables = ['users', 'categories', 'products', 'clients', 'client_ledgers', 'vendors', 'vendor_ledgers', 'sales', 'sale_items', 'expenses', 'daily_closings', 'stock_adjustments'];
    foreach ($tables as $tbl) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM `$tbl`");
            $cnt = $stmt->fetchColumn();
            echo "  - $tbl: $cnt rows\n";
        } catch (Exception $e) {
            echo "  - $tbl: ERROR (" . $e->getMessage() . ")\n";
        }
    }
}

echo "\n[3] JSON Data File Status:\n";
$jsonFiles = [
    'store_state.json'     => get_store_data_file_path(),
    'clients.json'         => get_clients_file_path(),
    'client_ledgers.json'  => get_client_ledgers_file_path(),
    'vendors.json'         => get_vendors_file_path(),
    'vendor_ledgers.json'  => get_vendor_ledgers_file_path(),
    'sales_history.json'   => get_sales_history_file_path(),
    'expenses.json'        => get_expenses_file_path(),
    'closing_history.json' => get_closing_history_file_path()
];
foreach ($jsonFiles as $name => $path) {
    if (file_exists($path)) {
        $size = filesize($path);
        echo "  - $name: OK (" . round($size / 1024, 2) . " KB)\n";
    } else {
        echo "  - $name: Not yet created (will be created on first write)\n";
    }
}

echo "\n[4] Auth Test (admin, staff, client with PIN 1234):\n";
$adminAuth = authenticate_user('admin', '1234');
echo "  - Admin login: " . ($adminAuth['success'] ? "PASSED" : "FAILED") . "\n";
$staffAuth = authenticate_user('staff', '1234');
echo "  - Staff login: " . ($staffAuth['success'] ? "PASSED" : "FAILED") . "\n";
$clientAuth = authenticate_wholesale_client('client', '1234');
echo "  - Wholesale Client login ('client' / '1234'): " . ($clientAuth['success'] ? "PASSED" : "FAILED") . "\n";

echo "\n[5] SQL Dump File Check:\n";
$sqlFile = __DIR__ . '/../smartmobile_erp.sql';
if (file_exists($sqlFile)) {
    echo "  -> SUCCESS: smartmobile_erp.sql exists (" . round(filesize($sqlFile)/1024, 2) . " KB)\n";
} else {
    echo "  -> ERROR: smartmobile_erp.sql not found!\n";
}

echo "\nAudit Finished.\n";
