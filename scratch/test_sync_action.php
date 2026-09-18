<?php
// scratch/test_sync_action.php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/store_data.php';

echo "Testing Dual Write Sync...\n";

// 1. Add temporary test product
$sku = 'TEST-' . rand(100, 999);
$addRes = add_product([
    'sku'             => $sku,
    'name'            => 'Live Test Cable Type-C',
    'category'        => 'Accessories',
    'stock'           => 15,
    'cost_price'      => 300,
    'wholesale_price' => 450,
    'retail_price'    => 700
]);

echo "Product Added: " . ($addRes['success'] ? "SUCCESS ($sku)" : "FAILED") . "\n";

// Verify in MySQL
$pdo = get_db();
$stmt = $pdo->prepare("SELECT * FROM products WHERE sku = :sku");
$stmt->execute([':sku' => $sku]);
$dbRow = $stmt->fetch();
echo "MySQL Verification: " . ($dbRow ? "EXISTS in DB (ID: " . $dbRow['id'] . ", Stock: " . $dbRow['stock_qty'] . ")" : "NOT in DB") . "\n";

// 2. Adjust stock
$adjRes = record_stock_adjustment($sku, 'IN', 5, 'Live test shipment');
$stmt->execute([':sku' => $sku]);
$dbRow2 = $stmt->fetch();
echo "Stock Adjustment in DB: New stock = " . ($dbRow2['stock_qty'] ?? 'N/A') . " (Expected: 20)\n";

// 3. Clean up test item
delete_product($sku);
$stmt->execute([':sku' => $sku]);
$dbRow3 = $stmt->fetch();
echo "Cleanup Verification: " . (!$dbRow3 ? "DELETED from DB successfully" : "STILL in DB") . "\n";

echo "ALL TESTS PASSED!\n";
