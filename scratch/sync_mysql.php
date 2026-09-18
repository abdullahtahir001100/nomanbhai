<?php
// scratch/sync_mysql.php - Database synchronization & SQL generator
require_once 'c:/xampp/htdocs/smartmobile-project/includes/db.php';
require_once 'c:/xampp/htdocs/smartmobile-project/includes/store_data.php';

$pdo = get_db();
if (!$pdo) {
    die("ERROR: Cannot connect to MySQL.\n");
}

echo "1. Connected to MySQL successfully.\n";

// A. Update users table schema if needed
$pdo->exec("ALTER TABLE `users` MODIFY COLUMN `role` VARCHAR(50) DEFAULT 'admin';");

// Create vendors and vendor_ledgers tables if not exist
$pdo->exec("
CREATE TABLE IF NOT EXISTS `vendors` (
  `id` varchar(50) NOT NULL PRIMARY KEY,
  `name` varchar(150) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `phone` varchar(25) DEFAULT NULL,
  `city` varchar(150) DEFAULT NULL,
  `category` varchar(150) DEFAULT NULL,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `credit_limit` decimal(12,2) NOT NULL DEFAULT 500000.00,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `vendor_ledgers` (
  `id` varchar(50) NOT NULL PRIMARY KEY,
  `vendor_id` varchar(50) NOT NULL,
  `transaction_date` datetime NOT NULL,
  `description` varchar(255) NOT NULL,
  `debit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `credit` decimal(12,2) NOT NULL DEFAULT 0.00,
  `balance` decimal(12,2) NOT NULL DEFAULT 0.00,
  `reference_no` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");
echo "2. Vendors and vendor_ledgers tables verified.\n";

// B. Sync users: admin, staff, client (all password 1234)
$pdo->exec("
INSERT INTO `users` (`username`, `password`, `full_name`, `role`, `phone`)
VALUES 
('admin', '1234', 'Administrator', 'admin', '0300-1122334'),
('staff', '1234', 'Counter Operator', 'cashier', '0321-4455667'),
('client', '1234', 'Wholesale Client (Ali Electronics)', 'client', '0300-1234567')
ON DUPLICATE KEY UPDATE `password` = VALUES(`password`), `full_name` = VALUES(`full_name`), `role` = VALUES(`role`);
");
echo "3. Users synchronized (admin / 1234, staff / 1234, client / 1234).\n";

// C. Sync products
$storeData = get_store_metrics();
$products = $storeData['products'] ?? [];
$stmtProd = $pdo->prepare("
INSERT INTO `products` (`sku`, `name`, `category_name`, `cost_price`, `wholesale_price`, `retail_price`, `stock_qty`, `status`)
VALUES (:sku, :name, :category_name, :cost_price, :wholesale_price, :retail_price, :stock_qty, :status)
ON DUPLICATE KEY UPDATE 
`name` = VALUES(`name`), `category_name` = VALUES(`category_name`), `cost_price` = VALUES(`cost_price`),
`wholesale_price` = VALUES(`wholesale_price`), `retail_price` = VALUES(`retail_price`), `stock_qty` = VALUES(`stock_qty`), `status` = VALUES(`status`);
");

foreach ($products as $sku => $p) {
    $qty = (int)($p['stock'] ?? 0);
    $status = ($qty > 5) ? 'in_stock' : (($qty > 0) ? 'low_stock' : 'out_of_stock');
    $stmtProd->execute([
        ':sku'             => $sku,
        ':name'            => $p['name'],
        ':category_name'   => $p['category'] ?? 'General',
        ':cost_price'      => (float)($p['cost_price'] ?? 0),
        ':wholesale_price' => (float)($p['wholesale_price'] ?? 0),
        ':retail_price'    => (float)($p['retail_price'] ?? 0),
        ':stock_qty'       => $qty,
        ':status'          => $status
    ]);
}
echo "4. Synced " . count($products) . " products in MySQL.\n";

// D. Sync clients
$clients = get_clients_list();
$stmtClient = $pdo->prepare("
INSERT INTO `clients` (`shop_name`, `owner_name`, `phone`, `city_area`, `credit_limit`, `current_balance`, `secret_pin`, `status`)
VALUES (:shop_name, :owner_name, :phone, :city_area, :credit_limit, :current_balance, '1234', :status)
ON DUPLICATE KEY UPDATE 
`owner_name` = VALUES(`owner_name`), `city_area` = VALUES(`city_area`), `credit_limit` = VALUES(`credit_limit`), `current_balance` = VALUES(`current_balance`);
");

foreach ($clients as $cid => $c) {
    $bal = (float)($c['balance'] ?? 0);
    $limit = (float)($c['limit'] ?? 0);
    $status = ($bal <= 0) ? 'clear' : (($bal > $limit && $limit > 0) ? 'overdue' : 'payment_due');
    $stmtClient->execute([
        ':shop_name'       => $c['name'],
        ':owner_name'      => $c['owner'] ?? $c['name'],
        ':phone'           => $c['phone'] ?? '',
        ':city_area'       => $c['city'] ?? '',
        ':credit_limit'    => $limit,
        ':current_balance' => $bal,
        ':status'          => $status
    ]);
}
echo "5. Synced " . count($clients) . " clients in MySQL.\n";

// E. Sync Vendors
$vendors = get_vendors_list();
$stmtVen = $pdo->prepare("
INSERT INTO `vendors` (`id`, `name`, `contact_person`, `phone`, `city`, `category`, `balance`, `credit_limit`)
VALUES (:id, :name, :contact_person, :phone, :city, :category, :balance, :credit_limit)
ON DUPLICATE KEY UPDATE 
`name` = VALUES(`name`), `balance` = VALUES(`balance`), `phone` = VALUES(`phone`);
");
foreach ($vendors as $vid => $v) {
    $stmtVen->execute([
        ':id'             => $vid,
        ':name'           => $v['name'],
        ':contact_person' => $v['contact'] ?? $v['name'],
        ':phone'          => $v['phone'] ?? '',
        ':city'           => $v['city'] ?? '',
        ':category'       => $v['category'] ?? 'Mobile Parts',
        ':balance'        => (float)($v['balance'] ?? 0),
        ':credit_limit'   => (float)($v['limit'] ?? 500000)
    ]);
}
echo "6. Synced " . count($vendors) . " vendors in MySQL.\n";

// F. Sync Vendor Ledgers
$vLedgersFile = get_vendor_ledgers_file_path();
if (file_exists($vLedgersFile)) {
    $vLedgers = json_decode(file_get_contents($vLedgersFile), true) ?: [];
    $stmtVtx = $pdo->prepare("
    INSERT INTO `vendor_ledgers` (`id`, `vendor_id`, `transaction_date`, `description`, `debit`, `credit`, `balance`, `reference_no`)
    VALUES (:id, :vendor_id, :transaction_date, :description, :debit, :credit, :balance, :reference_no)
    ON DUPLICATE KEY UPDATE `balance` = VALUES(`balance`);
    ");
    $vCount = 0;
    foreach ($vLedgers as $vid => $txs) {
        foreach ($txs as $tx) {
            $stmtVtx->execute([
                ':id'               => $tx['id'] ?? 'vtx_' . uniqid(),
                ':vendor_id'        => $vid,
                ':transaction_date' => $tx['date'] ?? date('Y-m-d H:i:s'),
                ':description'      => $tx['description'] ?? '',
                ':debit'            => (float)($tx['debit'] ?? 0),
                ':credit'           => (float)($tx['credit'] ?? 0),
                ':balance'          => (float)($tx['balance'] ?? 0),
                ':reference_no'     => $tx['ref'] ?? ''
            ]);
            $vCount++;
        }
    }
    echo "7. Synced $vCount vendor transactions in MySQL.\n";
}

// G. Alter sales and sale_items schema if needed
$pdo->exec("
ALTER TABLE `sales` MODIFY COLUMN `payment_method` VARCHAR(50) DEFAULT 'cash';
ALTER TABLE `sales` MODIFY COLUMN `customer_type` VARCHAR(50) DEFAULT 'retail';
");

// H. Sync Sales History and Sale Items
$salesFile = get_sales_history_file_path();
if (file_exists($salesFile)) {
    $sales = json_decode(file_get_contents($salesFile), true) ?: [];
    $stmtSale = $pdo->prepare("
    INSERT INTO `sales` (`invoice_no`, `customer_type`, `subtotal`, `discount`, `total_payable`, `payment_method`, `cashier_name`, `sale_date`)
    VALUES (:invoice_no, :customer_type, :subtotal, :discount, :total_payable, :payment_method, 'Admin', :sale_date)
    ON DUPLICATE KEY UPDATE `total_payable` = VALUES(`total_payable`);
    ");

    $stmtItem = $pdo->prepare("
    INSERT INTO `sale_items` (`sale_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `total_price`)
    VALUES (:sale_id, :product_id, :product_name, :unit_price, :quantity, :total_price)
    ");

    $salesCount = 0;
    foreach ($sales as $s) {
        $inv = $s['invoice_no'] ?? ('INV-' . uniqid());
        $stmtSale->execute([
            ':invoice_no'     => $inv,
            ':customer_type'  => $s['customer_type'] ?? 'retail',
            ':subtotal'       => (float)($s['subtotal'] ?? 0),
            ':discount'       => (float)($s['discount'] ?? 0),
            ':total_payable'  => (float)($s['total_payable'] ?? 0),
            ':payment_method' => $s['payment_method'] ?? 'cash',
            ':sale_date'      => $s['timestamp'] ?? date('Y-m-d H:i:s')
        ]);

        // Get sale ID
        $saleId = $pdo->lastInsertId();
        if (!$saleId) {
            $stmtGetId = $pdo->prepare("SELECT id FROM sales WHERE invoice_no = :inv");
            $stmtGetId->execute([':inv' => $inv]);
            $saleId = $stmtGetId->fetchColumn();
        }

        if ($saleId && !empty($s['items'])) {
            // Delete old items for this sale to avoid duplicates
            $pdo->prepare("DELETE FROM sale_items WHERE sale_id = :sid")->execute([':sid' => $saleId]);
            foreach ($s['items'] as $item) {
                $pSku = $item['sku'] ?? '';
                // find product_id if exists
                $pId = null;
                if (!empty($pSku)) {
                    $stmtP = $pdo->prepare("SELECT id FROM products WHERE sku = :sku");
                    $stmtP->execute([':sku' => $pSku]);
                    $pId = $stmtP->fetchColumn() ?: null;
                }

                $stmtItem->execute([
                    ':sale_id'      => $saleId,
                    ':product_id'   => $pId,
                    ':product_name' => $item['name'] ?? 'Item',
                    ':unit_price'   => (float)($item['unit_price'] ?? 0),
                    ':quantity'     => (int)($item['qty'] ?? 1),
                    ':total_price'  => (float)($item['line_total'] ?? (($item['unit_price'] ?? 0) * ($item['qty'] ?? 1)))
                ]);
            }
        }
        $salesCount++;
    }
    echo "8. Synced $salesCount sales and sale items in MySQL.\n";
}

echo "SUCCESS: MySQL database smartmobile_erp is 100% updated and synchronized!\n";

