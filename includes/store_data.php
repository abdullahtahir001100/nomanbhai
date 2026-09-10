<?php
// includes/store_data.php
// Central State & Inventory Data Manager for Smart Mobile ERP
require_once __DIR__ . '/db.php';

/**
 * =========================================================================
 * REAL-TIME MYSQL DATABASE SYNCHRONIZATION HELPERS
 * Automatically updates MySQL if connected, otherwise gracefully falls back.
 * =========================================================================
 */

function db_sync_product(array $p): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $sku = $p['sku'] ?? '';
        if (empty($sku)) return;
        $qty = (int)($p['stock'] ?? 0);
        $status = ($qty > 5) ? 'in_stock' : (($qty > 0) ? 'low_stock' : 'out_of_stock');
        $stmt = $pdo->prepare("
            INSERT INTO `products` (`sku`, `name`, `category_name`, `cost_price`, `wholesale_price`, `retail_price`, `stock_qty`, `status`)
            VALUES (:sku, :name, :cat, :cost, :ws, :ret, :stock, :status)
            ON DUPLICATE KEY UPDATE 
                `name` = VALUES(`name`), `category_name` = VALUES(`category_name`), `cost_price` = VALUES(`cost_price`),
                `wholesale_price` = VALUES(`wholesale_price`), `retail_price` = VALUES(`retail_price`), 
                `stock_qty` = VALUES(`stock_qty`), `status` = VALUES(`status`)
        ");
        $stmt->execute([
            ':sku'    => $sku,
            ':name'   => $p['name'] ?? '',
            ':cat'    => $p['category'] ?? 'General',
            ':cost'   => (float)($p['cost_price'] ?? 0),
            ':ws'     => (float)($p['wholesale_price'] ?? 0),
            ':ret'    => (float)($p['retail_price'] ?? 0),
            ':stock'  => $qty,
            ':status' => $status
        ]);
    } catch (Throwable $e) {
        error_log("DB Sync Product Error: " . $e->getMessage());
    }
}

function db_delete_product(string $sku): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("DELETE FROM `products` WHERE `sku` = :sku");
        $stmt->execute([':sku' => $sku]);
    } catch (Throwable $e) {
        error_log("DB Delete Product Error: " . $e->getMessage());
    }
}

function db_record_stock_adjustment(string $sku, string $type, int $qty, string $remarks = ''): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $stmtP = $pdo->prepare("SELECT id, stock_qty FROM `products` WHERE `sku` = :sku");
        $stmtP->execute([':sku' => $sku]);
        $row = $stmtP->fetch();
        if ($row) {
            $pId = (int)$row['id'];
            $act = strtoupper($type) === 'IN' ? 'IN' : 'OUT';
            $stmt = $pdo->prepare("
                INSERT INTO `stock_adjustments` (`product_id`, `action_type`, `quantity`, `remarks`, `adjusted_by`)
                VALUES (:pid, :act, :qty, :remarks, 'Admin')
            ");
            $stmt->execute([
                ':pid'     => $pId,
                ':act'     => $act,
                ':qty'     => $qty,
                ':remarks' => $remarks
            ]);
            
            $newQty = ($act === 'IN') ? ((int)$row['stock_qty'] + $qty) : max(0, (int)$row['stock_qty'] - $qty);
            $newStatus = ($newQty > 5) ? 'in_stock' : (($newQty > 0) ? 'low_stock' : 'out_of_stock');
            $stmtUp = $pdo->prepare("UPDATE `products` SET `stock_qty` = :qty, `status` = :status WHERE `id` = :pid");
            $stmtUp->execute([':qty' => $newQty, ':status' => $newStatus, ':pid' => $pId]);
        }
    } catch (Throwable $e) {
        error_log("DB Stock Adjustment Error: " . $e->getMessage());
    }
}

function db_sync_client(array $c): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $bal = (float)($c['balance'] ?? 0);
        $limit = (float)($c['limit'] ?? 500000);
        $status = ($bal <= 0) ? 'clear' : (($bal > $limit && $limit > 0) ? 'overdue' : 'payment_due');
        $stmt = $pdo->prepare("
            INSERT INTO `clients` (`shop_name`, `owner_name`, `phone`, `city_area`, `credit_limit`, `current_balance`, `secret_pin`, `status`)
            VALUES (:shop_name, :owner_name, :phone, :city_area, :credit_limit, :current_balance, '1234', :status)
            ON DUPLICATE KEY UPDATE
                `owner_name` = VALUES(`owner_name`), `phone` = VALUES(`phone`), `city_area` = VALUES(`city_area`),
                `credit_limit` = VALUES(`credit_limit`), `current_balance` = VALUES(`current_balance`), `status` = VALUES(`status`)
        ");
        $stmt->execute([
            ':shop_name'       => $c['name'] ?? '',
            ':owner_name'      => $c['owner'] ?? ($c['name'] ?? ''),
            ':phone'           => $c['phone'] ?? '',
            ':city_area'       => $c['city'] ?? '',
            ':credit_limit'    => $limit,
            ':current_balance' => $bal,
            ':status'          => $status
        ]);
    } catch (Throwable $e) {
        error_log("DB Sync Client Error: " . $e->getMessage());
    }
}

function db_delete_client(string $clientName): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("DELETE FROM `clients` WHERE `shop_name` = :name");
        $stmt->execute([':name' => $clientName]);
    } catch (Throwable $e) {
        error_log("DB Delete Client Error: " . $e->getMessage());
    }
}

function db_sync_client_transaction(string $clientName, string $type, float $amount, string $description, string $ref, float $newBalance): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $stmtC = $pdo->prepare("SELECT id FROM `clients` WHERE `shop_name` = :name LIMIT 1");
        $stmtC->execute([':name' => $clientName]);
        $cId = $stmtC->fetchColumn();
        if ($cId) {
            $debit = ($type === 'debit') ? $amount : 0.0;
            $credit = ($type === 'credit') ? $amount : 0.0;
            $stmt = $pdo->prepare("
                INSERT INTO `client_ledgers` (`client_id`, `transaction_date`, `description`, `debit`, `credit`, `balance`, `reference_no`)
                VALUES (:cid, NOW(), :desc, :deb, :cred, :bal, :ref)
            ");
            $stmt->execute([
                ':cid'  => $cId,
                ':desc' => $description,
                ':deb'  => $debit,
                ':cred' => $credit,
                ':bal'  => $newBalance,
                ':ref'  => $ref
            ]);
            $pdo->prepare("UPDATE `clients` SET `current_balance` = :bal WHERE `id` = :cid")->execute([':bal' => $newBalance, ':cid' => $cId]);
        }
    } catch (Throwable $e) {
        error_log("DB Sync Client Transaction Error: " . $e->getMessage());
    }
}

function db_sync_vendor(array $v): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $id = $v['id'] ?? ('ven_' . uniqid());
        $stmt = $pdo->prepare("
            INSERT INTO `vendors` (`id`, `name`, `contact_person`, `phone`, `city`, `category`, `balance`, `credit_limit`)
            VALUES (:id, :name, :contact, :phone, :city, :cat, :bal, :limit)
            ON DUPLICATE KEY UPDATE
                `name` = VALUES(`name`), `contact_person` = VALUES(`contact_person`), `phone` = VALUES(`phone`),
                `city` = VALUES(`city`), `category` = VALUES(`category`), `balance` = VALUES(`balance`),
                `credit_limit` = VALUES(`credit_limit`)
        ");
        $stmt->execute([
            ':id'      => $id,
            ':name'    => $v['name'] ?? '',
            ':contact' => $v['contact'] ?? '',
            ':phone'   => $v['phone'] ?? '',
            ':city'    => $v['city'] ?? '',
            ':cat'     => $v['category'] ?? 'Mobile Parts',
            ':bal'     => (float)($v['balance'] ?? 0),
            ':limit'   => (float)($v['limit'] ?? 500000)
        ]);
    } catch (Throwable $e) {
        error_log("DB Sync Vendor Error: " . $e->getMessage());
    }
}

function db_delete_vendor(string $vendorId): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $pdo->prepare("DELETE FROM `vendor_ledgers` WHERE `vendor_id` = :id")->execute([':id' => $vendorId]);
        $pdo->prepare("DELETE FROM `vendors` WHERE `id` = :id")->execute([':id' => $vendorId]);
    } catch (Throwable $e) {
        error_log("DB Delete Vendor Error: " . $e->getMessage());
    }
}

function db_sync_vendor_transaction(string $vendorId, string $type, float $amount, string $description, string $ref, float $newBalance): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $txId = 'vtx_' . uniqid();
        $debit = ($type === 'debit') ? $amount : 0.0;
        $credit = ($type === 'credit') ? $amount : 0.0;
        $stmt = $pdo->prepare("
            INSERT INTO `vendor_ledgers` (`id`, `vendor_id`, `transaction_date`, `description`, `debit`, `credit`, `balance`, `reference_no`)
            VALUES (:id, :vid, NOW(), :desc, :deb, :cred, :bal, :ref)
        ");
        $stmt->execute([
            ':id'   => $txId,
            ':vid'  => $vendorId,
            ':desc' => $description,
            ':deb'  => $debit,
            ':cred' => $credit,
            ':bal'  => $newBalance,
            ':ref'  => $ref
        ]);
        $pdo->prepare("UPDATE `vendors` SET `balance` = :bal WHERE `id` = :vid")->execute([':bal' => $newBalance, ':vid' => $vendorId]);
    } catch (Throwable $e) {
        error_log("DB Sync Vendor Transaction Error: " . $e->getMessage());
    }
}

function db_sync_sale(array $sale): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $inv = $sale['invoice_no'] ?? ('INV-' . uniqid());
        
        $clientId = null;
        if (!empty($sale['client_id'])) {
            $stmtC = $pdo->prepare("SELECT id FROM `clients` WHERE `shop_name` = :name OR `id` = :id LIMIT 1");
            $stmtC->execute([':name' => $sale['customer_name'] ?? '', ':id' => $sale['client_id']]);
            $clientId = $stmtC->fetchColumn() ?: null;
        }

        $stmtSale = $pdo->prepare("
            INSERT INTO `sales` (`invoice_no`, `customer_type`, `client_id`, `subtotal`, `discount`, `total_payable`, `payment_method`, `cashier_name`, `sale_date`)
            VALUES (:invoice_no, :customer_type, :client_id, :subtotal, :discount, :total_payable, :payment_method, :cashier, :sale_date)
            ON DUPLICATE KEY UPDATE `total_payable` = VALUES(`total_payable`)
        ");
        $stmtSale->execute([
            ':invoice_no'     => $inv,
            ':customer_type'  => $sale['customer_type'] ?? 'retail',
            ':client_id'      => $clientId,
            ':subtotal'       => (float)($sale['subtotal'] ?? 0),
            ':discount'       => (float)($sale['discount'] ?? 0),
            ':total_payable'  => (float)($sale['total_payable'] ?? 0),
            ':payment_method' => $sale['payment_method'] ?? 'cash',
            ':cashier'        => $_SESSION['user_name'] ?? 'Admin',
            ':sale_date'      => $sale['timestamp'] ?? date('Y-m-d H:i:s')
        ]);

        $saleId = $pdo->lastInsertId();
        if (!$saleId) {
            $stmtG = $pdo->prepare("SELECT id FROM sales WHERE invoice_no = :inv");
            $stmtG->execute([':inv' => $inv]);
            $saleId = $stmtG->fetchColumn();
        }

        if ($saleId && !empty($sale['items'])) {
            $pdo->prepare("DELETE FROM sale_items WHERE sale_id = :sid")->execute([':sid' => $saleId]);
            $stmtItem = $pdo->prepare("
                INSERT INTO `sale_items` (`sale_id`, `product_id`, `product_name`, `unit_price`, `quantity`, `total_price`)
                VALUES (:sale_id, :product_id, :product_name, :unit_price, :quantity, :total_price)
            ");
            foreach ($sale['items'] as $it) {
                $sku = $it['sku'] ?? '';
                $pId = null;
                if (!empty($sku)) {
                    $stmtP = $pdo->prepare("SELECT id FROM products WHERE sku = :sku");
                    $stmtP->execute([':sku' => $sku]);
                    $pId = $stmtP->fetchColumn() ?: null;
                }
                $stmtItem->execute([
                    ':sale_id'      => $saleId,
                    ':product_id'   => $pId,
                    ':product_name' => $it['name'] ?? 'Item',
                    ':unit_price'   => (float)($it['unit_price'] ?? 0),
                    ':quantity'     => (int)($it['qty'] ?? 1),
                    ':total_price'  => (float)($it['line_total'] ?? (($it['unit_price'] ?? 0) * ($it['qty'] ?? 1)))
                ]);

                if ($pId) {
                    $qtySold = (int)($it['qty'] ?? 1);
                    $pdo->prepare("UPDATE products SET stock_qty = GREATEST(0, stock_qty - :qty) WHERE id = :id")->execute([':qty' => $qtySold, ':id' => $pId]);
                }
            }
        }
    } catch (Throwable $e) {
        error_log("DB Sync Sale Error: " . $e->getMessage());
    }
}

function db_sync_expense(array $exp): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("
            INSERT INTO `expenses` (`expense_date`, `title`, `category`, `amount`, `recorded_by`)
            VALUES (:exp_date, :title, :category, :amount, :recorded_by)
        ");
        $stmt->execute([
            ':exp_date'    => $exp['date'] ?? date('Y-m-d'),
            ':title'       => $exp['title'] ?? '',
            ':category'    => $exp['category'] ?? 'General',
            ':amount'      => (float)($exp['amount'] ?? 0),
            ':recorded_by' => $exp['recorded_by'] ?? ($_SESSION['user_name'] ?? 'Admin')
        ]);
    } catch (Throwable $e) {
        error_log("DB Sync Expense Error: " . $e->getMessage());
    }
}

function db_delete_expense(string $idOrTitle): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        if (is_numeric($idOrTitle)) {
            $pdo->prepare("DELETE FROM `expenses` WHERE `id` = :id")->execute([':id' => (int)$idOrTitle]);
        } else {
            $pdo->prepare("DELETE FROM `expenses` WHERE `title` = :title LIMIT 1")->execute([':title' => $idOrTitle]);
        }
    } catch (Throwable $e) {
        error_log("DB Delete Expense Error: " . $e->getMessage());
    }
}

function db_sync_daily_closing(array $c): void {
    if (!is_db_connected()) return;
    try {
        $pdo = get_db();
        $stmt = $pdo->prepare("
            INSERT INTO `daily_closings` (
                `closing_date`, `closed_by`, `opening_cash`, `total_retail_sales`, 
                `total_wholesale_recovery`, `total_bank_transfer`, `total_credit_sales`, 
                `total_expenses`, `expected_cash`, `actual_cash`, `discrepancy_note`, `status`
            ) VALUES (
                :c_date, :c_by, :op_cash, :ret_sales, :ws_rec, :bank, :credit, :exp, :exp_cash, :act_cash, :note, :status
            )
            ON DUPLICATE KEY UPDATE
                `actual_cash` = VALUES(`actual_cash`), `expected_cash` = VALUES(`expected_cash`), 
                `discrepancy_note` = VALUES(`discrepancy_note`), `status` = VALUES(`status`)
        ");
        $stmt->execute([
            ':c_date'    => $c['date'] ?? ($c['closing_date'] ?? date('Y-m-d')),
            ':c_by'      => $c['closed_by'] ?? ($_SESSION['user_name'] ?? 'Admin'),
            ':op_cash'   => (float)($c['opening_cash'] ?? 0),
            ':ret_sales' => (float)($c['retail_cash_sales'] ?? 0),
            ':ws_rec'    => (float)($c['wholesale_recovery'] ?? 0),
            ':bank'      => (float)($c['bank_transfers'] ?? 0),
            ':credit'    => (float)($c['credit_sales'] ?? 0),
            ':exp'       => (float)($c['expenses'] ?? ($c['total_expenses'] ?? 0)),
            ':exp_cash'  => (float)($c['expected_cash'] ?? 0),
            ':act_cash'  => (float)($c['counted_cash'] ?? ($c['actual_cash'] ?? 0)),
            ':note'      => $c['remarks'] ?? ($c['discrepancy_note'] ?? ''),
            ':status'    => (strpos(strtolower($c['status'] ?? ''), 'shortage') !== false || strpos(strtolower($c['status'] ?? ''), 'surplus') !== false) ? 'difference' : 'balanced'
        ]);
    } catch (Throwable $e) {
        error_log("DB Sync Daily Closing Error: " . $e->getMessage());
    }
}

function get_store_data_file_path(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/store_state.json';
}

function get_category_badge(string $category): string {
    $c = strtolower(trim($category));
    if (strpos($c, 'panel') !== false || strpos($c, 'display') !== false || strpos($c, 'touch') !== false) {
        return 'bg-secondary';
    } elseif (strpos($c, 'batter') !== false) {
        return 'bg-info text-dark';
    } elseif (strpos($c, 'charg') !== false || strpos($c, 'adapter') !== false) {
        return 'bg-primary';
    } elseif (strpos($c, 'cable') !== false || strpos($c, 'wire') !== false) {
        return 'bg-primary text-white';
    } elseif (strpos($c, 'accessor') !== false || strpos($c, 'earbud') !== false || strpos($c, 'headphone') !== false) {
        return 'bg-warning text-dark';
    } elseif (strpos($c, 'tool') !== false || strpos($c, 'machine') !== false) {
        return 'bg-dark';
    }
    return 'bg-secondary';
}

function get_default_store_state(): array {
    return [
        'today_sales'          => 0,
        'stock_in_today'       => 0,
        'stock_out_today'      => 0,
        'daily_closing_status' => 'Pending', // 'Pending' or 'Closed'
        'last_updated'         => date('Y-m-d H:i:s'),
        'products'             => []
    ];
}

function get_store_metrics(): array {
    $filePath = get_store_data_file_path();
    if (!file_exists($filePath)) {
        $defaultData = get_default_store_state();
        file_put_contents($filePath, json_encode($defaultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultData;
    }

    $raw = file_get_contents($filePath);
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['products'])) {
        $defaultData = get_default_store_state();
        file_put_contents($filePath, json_encode($defaultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultData;
    }

    // Ensure all products have cost_price and category_badge
    $dirty = false;
    foreach ($data['products'] as $sku => &$prod) {
        if (!isset($prod['cost_price']) || $prod['cost_price'] === null) {
            $ws = (float)($prod['wholesale_price'] ?? 0);
            $ret = (float)($prod['retail_price'] ?? 0);
            $prod['cost_price'] = $ws > 0 ? round($ws * 0.8) : round($ret * 0.65);
            $dirty = true;
        }
        if (empty($prod['category_badge'])) {
            $prod['category_badge'] = get_category_badge($prod['category'] ?? '');
            $dirty = true;
        }
    }
    unset($prod);

    if ($dirty) {
        save_store_metrics($data);
    }

    return $data;
}

function save_store_metrics(array $data): bool {
    $filePath = get_store_data_file_path();
    $data['last_updated'] = date('Y-m-d H:i:s');
    return (bool) file_put_contents($filePath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Calculate dynamic inventory summary metrics
 */
function calculate_inventory_metrics(array $products): array {
    $totalItems = count($products);
    $totalStockValue = 0;
    $lowStockCount = 0;
    $outOfStockCount = 0;
    $totalUnits = 0;

    foreach ($products as $p) {
        $stock = (int)($p['stock'] ?? 0);
        $totalUnits += $stock;
        
        // Use cost_price for inventory valuation, fallback to wholesale_price or retail_price
        $cost = (float)($p['cost_price'] ?? 0);
        if ($cost <= 0) {
            $cost = (float)($p['wholesale_price'] ?? 0);
        }
        if ($cost <= 0) {
            $cost = (float)($p['retail_price'] ?? 0);
        }

        $totalStockValue += ($stock * $cost);

        if ($stock <= 0) {
            $outOfStockCount++;
        } elseif ($stock <= 10) {
            $lowStockCount++;
        }
    }

    return [
        'total_items'       => $totalItems,
        'total_stock_value' => $totalStockValue,
        'low_stock_count'   => $lowStockCount,
        'out_of_stock_count'=> $outOfStockCount,
        'total_units'       => $totalUnits
    ];
}

/**
 * Add a new product to inventory
 */
function add_product(array $input): array {
    $data = get_store_metrics();

    $name = trim($input['name'] ?? '');
    $sku  = strtoupper(trim($input['sku'] ?? ''));
    if (empty($name)) {
        return ['success' => false, 'message' => 'Item name is required'];
    }

    if (empty($sku)) {
        $sku = 'PRD-' . strtoupper(substr(preg_replace('/[^A-Za-z0-9]/', '', $name), 0, 4)) . '-' . rand(100, 999);
    }

    if (isset($data['products'][$sku])) {
        return ['success' => false, 'message' => "An item with SKU '{$sku}' already exists"];
    }

    $category       = trim($input['category'] ?? 'General');
    $stock          = max(0, (int)($input['stock'] ?? 0));
    $costPrice      = max(0.0, (float)($input['cost_price'] ?? 0));
    $wholesalePrice = max(0.0, (float)($input['wholesale_price'] ?? 0));
    $retailPrice    = max(0.0, (float)($input['retail_price'] ?? 0));

    $data['products'][$sku] = [
        'sku'             => $sku,
        'name'            => $name,
        'category'        => $category,
        'category_badge'  => get_category_badge($category),
        'stock'           => $stock,
        'cost_price'      => $costPrice,
        'wholesale_price' => $wholesalePrice,
        'retail_price'    => $retailPrice
    ];

    if ($stock > 0) {
        $data['stock_in_today'] = ($data['stock_in_today'] ?? 0) + $stock;
    }

    save_store_metrics($data);
    db_sync_product($data['products'][$sku]);

    return [
        'success' => true,
        'message' => 'Product successfully added',
        'product' => $data['products'][$sku]
    ];
}

/**
 * Edit an existing product in inventory
 */
function edit_product(string $origSku, array $input): array {
    $data = get_store_metrics();

    if (!isset($data['products'][$origSku])) {
        return ['success' => false, 'message' => 'Product not found'];
    }

    $newSku = strtoupper(trim($input['sku'] ?? $origSku));
    if (empty($newSku)) {
        $newSku = $origSku;
    }

    // Check if new SKU is already taken by another product
    if ($newSku !== $origSku && isset($data['products'][$newSku])) {
        return ['success' => false, 'message' => "SKU '{$newSku}' is already used by another item"];
    }

    $name           = trim($input['name'] ?? $data['products'][$origSku]['name']);
    $category       = trim($input['category'] ?? $data['products'][$origSku]['category']);
    $stock          = max(0, (int)($input['stock'] ?? $data['products'][$origSku]['stock']));
    $costPrice      = max(0.0, (float)($input['cost_price'] ?? $data['products'][$origSku]['cost_price']));
    $wholesalePrice = max(0.0, (float)($input['wholesale_price'] ?? $data['products'][$origSku]['wholesale_price']));
    $retailPrice    = max(0.0, (float)($input['retail_price'] ?? $data['products'][$origSku]['retail_price']));

    $updatedProduct = [
        'sku'             => $newSku,
        'name'            => $name,
        'category'        => $category,
        'category_badge'  => get_category_badge($category),
        'stock'           => $stock,
        'cost_price'      => $costPrice,
        'wholesale_price' => $wholesalePrice,
        'retail_price'    => $retailPrice
    ];

    if ($newSku !== $origSku) {
        unset($data['products'][$origSku]);
        db_delete_product($origSku);
    }
    $data['products'][$newSku] = $updatedProduct;

    save_store_metrics($data);
    db_sync_product($updatedProduct);

    return [
        'success' => true,
        'message' => 'Product successfully updated',
        'product' => $updatedProduct
    ];
}

/**
 * Delete a product from inventory
 */
function delete_product(string $sku): array {
    $data = get_store_metrics();

    if (!isset($data['products'][$sku])) {
        return ['success' => false, 'message' => 'Product not found'];
    }

    $deletedName = $data['products'][$sku]['name'] ?? $sku;
    unset($data['products'][$sku]);

    save_store_metrics($data);
    db_delete_product($sku);

    return [
        'success' => true,
        'message' => "Product '{$deletedName}' removed from inventory"
    ];
}

/**
 * Record stock adjustment (IN or OUT)
 */
function record_stock_adjustment(string $sku, string $type, int $qty, string $note = ''): array {
    $data = get_store_metrics();
    $qty = max(1, $qty);

    if (isset($data['products'][$sku])) {
        if (strtolower($type) === 'in') {
            $data['products'][$sku]['stock'] += $qty;
            $data['stock_in_today'] = ($data['stock_in_today'] ?? 0) + $qty;
        } else {
            $data['products'][$sku]['stock'] = max(0, $data['products'][$sku]['stock'] - $qty);
            $data['stock_out_today'] = ($data['stock_out_today'] ?? 0) + $qty;
        }
        save_store_metrics($data);
        db_record_stock_adjustment($sku, $type, $qty, $note);
        return [
            'success' => true,
            'new_stock' => $data['products'][$sku]['stock'],
            'stock_in_today' => $data['stock_in_today'],
            'stock_out_today' => $data['stock_out_today'],
            'product_name' => $data['products'][$sku]['name']
        ];
    }

    return ['success' => false, 'message' => 'Product not found'];
}

/**
 * Record a sale transaction
 */
function record_sale_transaction(float $amount, int $itemsQty = 1, ?string $sku = null): array {
    $data = get_store_metrics();
    $data['today_sales'] = ($data['today_sales'] ?? 0) + $amount;
    $data['stock_out_today'] = ($data['stock_out_today'] ?? 0) + $itemsQty;

    if ($sku && isset($data['products'][$sku])) {
        $data['products'][$sku]['stock'] = max(0, $data['products'][$sku]['stock'] - $itemsQty);
    }

    save_store_metrics($data);
    return [
        'success' => true,
        'today_sales' => $data['today_sales'],
        'stock_out_today' => $data['stock_out_today']
    ];
}

/**
 * Update daily closing status
 */
function update_daily_closing_status(string $status): bool {
    $data = get_store_metrics();
    $data['daily_closing_status'] = (strtolower($status) === 'closed') ? 'Closed' : 'Pending';
    return save_store_metrics($data);
}

/**
 * Clients Data File Path
 */
function get_clients_file_path(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/clients.json';
}

function get_default_clients(): array {
    return [];
}

function get_clients_list(): array {
    $file = get_clients_file_path();
    if (!file_exists($file)) {
        $defaults = get_default_clients();
        file_put_contents($file, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaults;
    }
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $defaults = get_default_clients();
        file_put_contents($file, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaults;
    }
    return $data;
}

function add_client(array $clientData): array {
    $clients = get_clients_list();
    $name = trim($clientData['name'] ?? '');
    if (empty($name)) {
        return ['success' => false, 'message' => 'Client / Shop name is required'];
    }
    $id = 'cli_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', substr($name, 0, 8))) . '_' . rand(100, 999);
    $balance = max(0.0, (float)($clientData['balance'] ?? 0));
    $limit   = max(0.0, (float)($clientData['limit'] ?? 100000));

    $clients[$id] = [
        'id'       => $id,
        'name'     => $name,
        'owner'    => trim($clientData['owner'] ?? $name),
        'phone'    => trim($clientData['phone'] ?? ''),
        'city'     => trim($clientData['city'] ?? 'Local Market'),
        'type'     => trim($clientData['type'] ?? 'wholesale'),
        'balance'  => $balance,
        'limit'    => $limit
    ];
    file_put_contents(get_clients_file_path(), json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_sync_client($clients[$id]);

    // If opening balance > 0, record in ledger
    if ($balance > 0) {
        record_client_transaction($id, 'debit', $balance, 'Opening Balance (Initial Udhaar)', 'OPN');
    }

    return ['success' => true, 'client' => $clients[$id]];
}

function edit_client(string $clientId, array $clientData): array {
    $clients = get_clients_list();
    if (!isset($clients[$clientId])) {
        return ['success' => false, 'message' => 'Client not found'];
    }

    $name = trim($clientData['name'] ?? $clients[$clientId]['name']);
    if (empty($name)) {
        return ['success' => false, 'message' => 'Client / Shop name is required'];
    }

    $clients[$clientId]['name']  = $name;
    $clients[$clientId]['owner'] = trim($clientData['owner'] ?? $clients[$clientId]['owner']);
    $clients[$clientId]['phone'] = trim($clientData['phone'] ?? $clients[$clientId]['phone']);
    $clients[$clientId]['city']  = trim($clientData['city'] ?? $clients[$clientId]['city']);
    $clients[$clientId]['type']  = trim($clientData['type'] ?? $clients[$clientId]['type']);
    if (isset($clientData['limit'])) {
        $clients[$clientId]['limit'] = max(0.0, (float)$clientData['limit']);
    }

    file_put_contents(get_clients_file_path(), json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_sync_client($clients[$clientId]);
    return ['success' => true, 'client' => $clients[$clientId]];
}

function delete_client(string $clientId): array {
    $clients = get_clients_list();
    if (!isset($clients[$clientId])) {
        return ['success' => false, 'message' => 'Client not found'];
    }
    $name = $clients[$clientId]['name'];
    unset($clients[$clientId]);
    file_put_contents(get_clients_file_path(), json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_delete_client($name);

    // Also remove ledger
    $file = get_client_ledgers_file_path();
    if (file_exists($file)) {
        $all = json_decode(file_get_contents($file), true);
        if (is_array($all) && isset($all[$clientId])) {
            unset($all[$clientId]);
            file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    return ['success' => true, 'message' => "Client '{$name}' deleted successfully"];
}

function update_client_balance(string $clientId, float $deltaAmount): bool {
    $clients = get_clients_list();
    if (isset($clients[$clientId])) {
        $clients[$clientId]['balance'] = max(0.0, $clients[$clientId]['balance'] + $deltaAmount);
        file_put_contents(get_clients_file_path(), json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return true;
    }
    return false;
}

/**
 * Client Ledger System
 */
function get_client_ledgers_file_path(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/client_ledgers.json';
}

function get_default_ledgers(): array {
    return [];
}

function get_client_ledger(string $clientId): array {
    $file = get_client_ledgers_file_path();
    $allLedgers = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $allLedgers = $decoded;
    } else {
        $allLedgers = get_default_ledgers();
        file_put_contents($file, json_encode($allLedgers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    if (isset($allLedgers[$clientId])) {
        return $allLedgers[$clientId];
    }

    // If client exists but no ledger yet, create opening balance if balance > 0
    $clients = get_clients_list();
    if (isset($clients[$clientId])) {
        $bal = (float)($clients[$clientId]['balance'] ?? 0);
        $initLedger = [];
        if ($bal > 0) {
            $initLedger[] = [
                'id'          => 'tx_init_' . $clientId,
                'date'        => date('Y-m-d H:i:s'),
                'description' => 'Opening Balance (Initial Udhaar)',
                'debit'       => $bal,
                'credit'      => 0,
                'balance'     => $bal,
                'ref'         => 'OPN'
            ];
            $allLedgers[$clientId] = $initLedger;
            file_put_contents($file, json_encode($allLedgers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        return $initLedger;
    }

    return [];
}

function record_client_transaction(string $clientId, string $type, float $amount, string $description, string $ref = ''): array {
    $clients = get_clients_list();
    if (!isset($clients[$clientId])) {
        return ['success' => false, 'message' => 'Client not found'];
    }

    $file = get_client_ledgers_file_path();
    $allLedgers = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $allLedgers = $decoded;
    } else {
        $allLedgers = get_default_ledgers();
    }

    if (!isset($allLedgers[$clientId])) {
        $allLedgers[$clientId] = [];
    }

    $curBalance = (float)($clients[$clientId]['balance'] ?? 0);
    $debit = 0.0;
    $credit = 0.0;

    if (strtolower($type) === 'debit') {
        // Maal khareeda (Udhaar barha)
        $debit = $amount;
        $newBalance = $curBalance + $amount;
    } else {
        // Payment mili (Udhaar kam hua)
        $credit = $amount;
        $newBalance = max(0.0, $curBalance - $amount);
    }

    $entry = [
        'id'          => 'tx_' . uniqid(),
        'date'        => date('Y-m-d H:i:s'),
        'description' => $description,
        'debit'       => $debit,
        'credit'      => $credit,
        'balance'     => $newBalance,
        'ref'         => $ref
    ];

    $allLedgers[$clientId][] = $entry;
    file_put_contents($file, json_encode($allLedgers, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Update client's balance
    $clients[$clientId]['balance'] = $newBalance;
    file_put_contents(get_clients_file_path(), json_encode($clients, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_sync_client_transaction($clients[$clientId]['name'], $type, $amount, $description, $ref, $newBalance);

    return [
        'success'     => true,
        'new_balance' => $newBalance,
        'entry'       => $entry
    ];
}

/**
 * Calculate total payments received across ledgers
 */
function get_total_payments_received(): float {
    $file = get_client_ledgers_file_path();
    if (!file_exists($file)) return 110000.0;
    $allLedgers = json_decode(file_get_contents($file), true);
    if (!is_array($allLedgers)) return 110000.0;

    $total = 0.0;
    foreach ($allLedgers as $entries) {
        if (is_array($entries)) {
            foreach ($entries as $e) {
                $total += (float)($e['credit'] ?? 0);
            }
        }
    }
    return $total;
}

/**
 * Sales History file path
 */
function get_sales_history_file_path(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/sales_history.json';
}

function get_sales_history(int $limit = 50): array {
    $file = get_sales_history_file_path();
    if (!file_exists($file)) {
        return [];
    }
    $data = json_decode(file_get_contents($file), true);
    if (!is_array($data)) return [];
    return array_slice($data, 0, $limit);
}

/**
 * Complete POS Sale Processing
 */
function process_pos_sale(array $sale): array {
    $data = get_store_metrics();
    $items = $sale['items'] ?? [];
    if (empty($items)) {
        return ['success' => false, 'message' => 'Cannot checkout: Cart has no items.'];
    }

    $totalPayable = max(0.0, (float)($sale['total_payable'] ?? 0));
    $totalSoldQty = 0;

    // Deduct stock
    foreach ($items as $item) {
        $sku = $item['sku'] ?? '';
        $qty = max(1, (int)($item['qty'] ?? 1));
        $totalSoldQty += $qty;

        if (!empty($sku) && isset($data['products'][$sku])) {
            $data['products'][$sku]['stock'] = max(0, $data['products'][$sku]['stock'] - $qty);
        }
    }

    // Update today's sales and stock out
    $data['today_sales']     = ($data['today_sales'] ?? 0) + $totalPayable;
    $data['stock_out_today']  = ($data['stock_out_today'] ?? 0) + $totalSoldQty;
    save_store_metrics($data);

    // If payment method is credit (Udhaar) and client is specified
    $clientId = $sale['client_id'] ?? '';
    $invoiceNo = !empty($sale['invoice_no']) ? $sale['invoice_no'] : ('INV-' . date('Ymd') . '-' . rand(1000, 9999));

    if (($sale['payment_method'] ?? '') === 'credit' && !empty($clientId)) {
        record_client_transaction($clientId, 'debit', $totalPayable, "POS Counter Sale (Invoice #{$invoiceNo})", $invoiceNo);
    }

    // Prepare Invoice Record
    $invoiceNo = !empty($sale['invoice_no']) ? $sale['invoice_no'] : ('INV-' . date('Ymd') . '-' . rand(1000, 9999));
    $invoice = [
        'invoice_no'       => $invoiceNo,
        'timestamp'        => date('Y-m-d H:i:s'),
        'customer_name'    => trim($sale['customer_name'] ?? 'Walk-in Retail Customer'),
        'customer_phone'   => trim($sale['customer_phone'] ?? ''),
        'customer_type'    => trim($sale['customer_type'] ?? 'retail'),
        'payment_method'   => trim($sale['payment_method'] ?? 'cash'),
        'amount_paid'      => (float)($sale['amount_paid'] ?? $totalPayable),
        'change_returned'  => max(0.0, (float)($sale['change_returned'] ?? 0)),
        'bank_account'     => trim($sale['bank_account'] ?? ''),
        'transaction_ref'  => trim($sale['transaction_ref'] ?? ''),
        'subtotal'         => (float)($sale['subtotal'] ?? $totalPayable),
        'discount'         => (float)($sale['discount'] ?? 0),
        'total_payable'    => $totalPayable,
        'items'            => $items,
        'notes'            => trim($sale['notes'] ?? '')
    ];

    // Append to sales history
    $historyFile = get_sales_history_file_path();
    $history = [];
    if (file_exists($historyFile)) {
        $raw = file_get_contents($historyFile);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            $history = $decoded;
        }
    }
    array_unshift($history, $invoice);
    if (count($history) > 300) {
        $history = array_slice($history, 0, 300);
    }
    file_put_contents($historyFile, json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_sync_sale($invoice);

    return [
        'success'         => true,
        'message'         => 'Sale successfully completed and stock updated',
        'invoice'         => $invoice,
        'today_sales'     => $data['today_sales'],
        'stock_out_today' => $data['stock_out_today'],
        'products'        => $data['products']
    ];
}

/**
 * ========================================================
 * VENDOR / SUPPLIER MANAGEMENT & LEDGERS
 * ========================================================
 */

function get_vendors_file_path(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/vendors.json';
}

function get_default_vendors(): array {
    return [];
}

function get_vendors_list(): array {
    $file = get_vendors_file_path();
    if (!file_exists($file)) {
        $defaults = get_default_vendors();
        file_put_contents($file, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaults;
    }
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        $defaults = get_default_vendors();
        file_put_contents($file, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaults;
    }
    return $data;
}

function add_vendor(array $vendorData): array {
    $vendors = get_vendors_list();
    $name = trim($vendorData['name'] ?? '');
    if (empty($name)) {
        return ['success' => false, 'message' => 'Vendor / Supplier name is required'];
    }

    $id = 'ven_' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', substr($name, 0, 8))) . '_' . rand(100, 999);
    $balance = max(0.0, (float)($vendorData['balance'] ?? 0));
    $limit   = max(0.0, (float)($vendorData['limit'] ?? 500000));

    $vendors[$id] = [
        'id'       => $id,
        'name'     => $name,
        'contact'  => trim($vendorData['contact'] ?? $name),
        'phone'    => trim($vendorData['phone'] ?? ''),
        'city'     => trim($vendorData['city'] ?? 'Local Market'),
        'category' => trim($vendorData['category'] ?? 'Mobile Parts & Accessories'),
        'balance'  => $balance,
        'limit'    => $limit
    ];
    file_put_contents(get_vendors_file_path(), json_encode($vendors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_sync_vendor($vendors[$id]);

    // If opening payable balance > 0, record in vendor ledger
    if ($balance > 0) {
        record_vendor_transaction($id, 'credit', $balance, 'Opening Balance (Initial Supplier Payable)', 'OPN');
    }

    return ['success' => true, 'vendor' => $vendors[$id]];
}

function edit_vendor(string $vendorId, array $vendorData): array {
    $vendors = get_vendors_list();
    if (!isset($vendors[$vendorId])) {
        return ['success' => false, 'message' => 'Vendor not found'];
    }

    $name = trim($vendorData['name'] ?? $vendors[$vendorId]['name']);
    if (empty($name)) {
        return ['success' => false, 'message' => 'Vendor / Supplier name is required'];
    }

    $vendors[$vendorId]['name']     = $name;
    $vendors[$vendorId]['contact']  = trim($vendorData['contact'] ?? $vendors[$vendorId]['contact']);
    $vendors[$vendorId]['phone']    = trim($vendorData['phone'] ?? $vendors[$vendorId]['phone']);
    $vendors[$vendorId]['city']     = trim($vendorData['city'] ?? $vendors[$vendorId]['city']);
    $vendors[$vendorId]['category'] = trim($vendorData['category'] ?? $vendors[$vendorId]['category']);
    if (isset($vendorData['limit'])) {
        $vendors[$vendorId]['limit'] = max(0.0, (float)$vendorData['limit']);
    }

    file_put_contents(get_vendors_file_path(), json_encode($vendors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_sync_vendor($vendors[$vendorId]);
    return ['success' => true, 'vendor' => $vendors[$vendorId]];
}

function delete_vendor(string $vendorId): array {
    $vendors = get_vendors_list();
    if (!isset($vendors[$vendorId])) {
        return ['success' => false, 'message' => 'Vendor not found'];
    }

    $name = $vendors[$vendorId]['name'];
    unset($vendors[$vendorId]);
    file_put_contents(get_vendors_file_path(), json_encode($vendors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_delete_vendor($vendorId);

    // Also remove vendor ledger
    $file = get_vendor_ledgers_file_path();
    if (file_exists($file)) {
        $all = json_decode(file_get_contents($file), true);
        if (is_array($all) && isset($all[$vendorId])) {
            unset($all[$vendorId]);
            file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    return ['success' => true, 'message' => "Vendor '{$name}' deleted successfully"];
}

function get_vendor_ledgers_file_path(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/vendor_ledgers.json';
}

function get_default_vendor_ledgers(): array {
    return [];
}

function get_vendor_ledger(string $vendorId): array {
    $file = get_vendor_ledgers_file_path();
    $all = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $all = $decoded;
    } else {
        $all = get_default_vendor_ledgers();
        file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    if (isset($all[$vendorId])) {
        return $all[$vendorId];
    }

    $vendors = get_vendors_list();
    if (isset($vendors[$vendorId])) {
        $bal = (float)($vendors[$vendorId]['balance'] ?? 0);
        $init = [];
        if ($bal > 0) {
            $init[] = [
                'id'          => 'vtx_init_' . $vendorId,
                'date'        => date('Y-m-d H:i:s'),
                'description' => 'Opening Balance (Initial Supplier Payable)',
                'debit'       => 0,
                'credit'      => $bal,
                'balance'     => $bal,
                'ref'         => 'OPN'
            ];
            $all[$vendorId] = $init;
            file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
        return $init;
    }

    return [];
}

function record_vendor_transaction(string $vendorId, string $type, float $amount, string $description, string $ref = ''): array {
    $vendors = get_vendors_list();
    if (!isset($vendors[$vendorId])) {
        return ['success' => false, 'message' => 'Vendor not found'];
    }

    $file = get_vendor_ledgers_file_path();
    $all = [];
    if (file_exists($file)) {
        $raw = file_get_contents($file);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $all = $decoded;
    } else {
        $all = get_default_vendor_ledgers();
    }

    if (!isset($all[$vendorId])) {
        $all[$vendorId] = [];
    }

    $curBalance = (float)($vendors[$vendorId]['balance'] ?? 0);
    $debit = 0.0;
    $credit = 0.0;

    if (strtolower($type) === 'credit') {
        // Maal Aaya (Hum ne paise dene hain - Payable barha)
        $credit = $amount;
        $newBalance = $curBalance + $amount;
    } else {
        // Hum ne payment di (Payable kam hua)
        $debit = $amount;
        $newBalance = max(0.0, $curBalance - $amount);
    }

    $entry = [
        'id'          => 'vtx_' . uniqid(),
        'date'        => date('Y-m-d H:i:s'),
        'description' => $description,
        'debit'       => $debit,
        'credit'      => $credit,
        'balance'     => $newBalance,
        'ref'         => $ref
    ];

    $all[$vendorId][] = $entry;
    file_put_contents($file, json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    // Update vendor balance
    $vendors[$vendorId]['balance'] = $newBalance;
    file_put_contents(get_vendors_file_path(), json_encode($vendors, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_sync_vendor_transaction($vendorId, $type, $amount, $description, $ref, $newBalance);

    return [
        'success'     => true,
        'new_balance' => $newBalance,
        'entry'       => $entry
    ];
}

function get_total_vendor_payables(): float {
    $vendors = get_vendors_list();
    $total = 0.0;
    foreach ($vendors as $v) {
        $total += (float)($v['balance'] ?? 0);
    }
    return $total;
}

/**
 * =========================================================================
 * DAILY CLOSING, SHOP EXPENSES & COUNTER REGISTER SYSTEM
 * =========================================================================
 */

function get_expenses_file_path(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/expenses.json';
}

function get_default_expenses(): array {
    return [];
}

function get_expenses_list(): array {
    $file = get_expenses_file_path();
    if (!file_exists($file)) {
        $defaults = get_default_expenses();
        file_put_contents($file, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaults;
    }
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];
    return $data;
}

function add_expense(array $data): array {
    $title = trim($data['title'] ?? '');
    $amount = max(0.0, (float)($data['amount'] ?? 0));
    $category = trim($data['category'] ?? 'Miscellaneous');
    $mode = trim($data['payment_mode'] ?? 'Cash Drawer');
    $recordedBy = trim($data['recorded_by'] ?? 'Admin');

    if (empty($title) || $amount <= 0) {
        return ['success' => false, 'message' => 'Expense Title and Valid Amount are required.'];
    }

    $badge = 'bg-secondary';
    if (stripos($category, 'Food') !== false || stripos($category, 'Entertainment') !== false) {
        $badge = 'bg-secondary';
    } elseif (stripos($category, 'Utilities') !== false || stripos($category, 'Electricity') !== false) {
        $badge = 'bg-warning text-dark';
    } elseif (stripos($category, 'Transport') !== false || stripos($category, 'Cargo') !== false) {
        $badge = 'bg-info text-dark';
    } elseif (stripos($category, 'Maintenance') !== false) {
        $badge = 'bg-primary';
    } elseif (stripos($category, 'Salary') !== false || stripos($category, 'Advance') !== false) {
        $badge = 'bg-success';
    }

    $entry = [
        'id'             => 'exp_' . uniqid(),
        'date'           => date('Y-m-d H:i:s'),
        'title'          => $title,
        'category'       => $category,
        'category_badge' => $badge,
        'amount'         => $amount,
        'payment_mode'   => $mode,
        'recorded_by'    => $recordedBy
    ];

    $all = get_expenses_list();
    array_unshift($all, $entry);
    file_put_contents(get_expenses_file_path(), json_encode($all, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_sync_expense($entry);

    return ['success' => true, 'expense' => $entry];
}

function delete_expense(string $id): bool {
    $all = get_expenses_list();
    $filtered = [];
    foreach ($all as $item) {
        if (($item['id'] ?? '') !== $id) {
            $filtered[] = $item;
        }
    }
    file_put_contents(get_expenses_file_path(), json_encode($filtered, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_delete_expense($id);
    return true;
}

function get_today_expenses(): array {
    $all = get_expenses_list();
    $today = date('Y-m-d');
    $todayList = [];
    foreach ($all as $item) {
        $itemDate = substr($item['date'] ?? '', 0, 10);
        if ($itemDate === $today) {
            $todayList[] = $item;
        }
    }
    return $todayList;
}

function get_opening_cash(): float {
    $metrics = get_store_metrics();
    return (float)($metrics['opening_cash'] ?? 15000.0);
}

function set_opening_cash(float $amount): bool {
    $metrics = get_store_metrics();
    $metrics['opening_cash'] = max(0.0, $amount);
    return save_store_metrics($metrics);
}

function get_closing_history_file_path(): string {
    $dir = __DIR__ . '/../data';
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    return $dir . '/closing_history.json';
}

function get_default_closing_history(): array {
    return [];
}

function get_closing_history(): array {
    $file = get_closing_history_file_path();
    if (!file_exists($file)) {
        $defaults = get_default_closing_history();
        file_put_contents($file, json_encode($defaults, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaults;
    }
    $raw = file_get_contents($file);
    $data = json_decode($raw, true);
    if (!is_array($data)) return [];
    return $data;
}

function save_closing_record(array $record): array {
    $history = get_closing_history();
    $id = 'cls_' . date('Ymd_His');
    $counted = max(0.0, (float)($record['counted_cash'] ?? 0));
    $expected = max(0.0, (float)($record['expected_cash'] ?? 0));
    $diff = $counted - $expected;

    $status = 'Balanced & Closed';
    $badge = 'bg-success-subtle text-success border border-success-subtle';
    if ($diff < 0) {
        $status = 'Shortage: PKR ' . number_format(abs($diff));
        $badge = 'bg-danger-subtle text-danger border border-danger-subtle';
    } elseif ($diff > 0) {
        $status = 'Surplus: PKR ' . number_format($diff);
        $badge = 'bg-primary-subtle text-primary border border-primary-subtle';
    }

    $entry = [
        'id'                 => $id,
        'date'               => date('Y-m-d'),
        'date_formatted'     => date('d M Y'),
        'closed_at'          => date('Y-m-d H:i:s'),
        'closed_by'          => $record['closed_by'] ?? 'Admin',
        'opening_cash'       => (float)($record['opening_cash'] ?? 0),
        'retail_cash_sales'  => (float)($record['retail_cash_sales'] ?? 0),
        'wholesale_recovery' => (float)($record['wholesale_recovery'] ?? 0),
        'bank_transfers'     => (float)($record['bank_transfers'] ?? 0),
        'credit_sales'       => (float)($record['credit_sales'] ?? 0),
        'total_sales'        => (float)($record['total_sales'] ?? 0),
        'net_cash_collected' => (float)($record['total_cash_collected'] ?? 0),
        'expenses'           => (float)($record['total_expenses'] ?? 0),
        'expected_cash'      => $expected,
        'counted_cash'       => $counted,
        'difference'         => $diff,
        'status'             => $status,
        'status_badge'       => $badge,
        'remarks'            => trim($record['remarks'] ?? 'Normal closing')
    ];

    array_unshift($history, $entry);
    file_put_contents(get_closing_history_file_path(), json_encode($history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    db_sync_daily_closing($entry);

    // Update store state daily closing status to Closed
    update_daily_closing_status('Closed');

    return ['success' => true, 'record' => $entry];
}

function reopen_daily_register(): bool {
    return update_daily_closing_status('Pending');
}

function get_daily_register_summary(): array {
    $todayDate = date('Y-m-d');
    $storeMetrics = get_store_metrics();
    $openingCash = get_opening_cash();
    $closingStatus = $storeMetrics['daily_closing_status'] ?? 'Pending';
    $isClosed = (strtolower($closingStatus) === 'closed');

    // 1. Calculate sales from sales_history.json
    $salesFile = get_sales_history_file_path();
    $sales = [];
    if (file_exists($salesFile)) {
        $raw = file_get_contents($salesFile);
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) $sales = $decoded;
    }

    $retailCashSales = 0.0;
    $wholesaleCashSales = 0.0;
    $bankTransfers = 0.0;
    $creditSales = 0.0;
    $salesHistoryTotal = 0.0;

    foreach ($sales as $sale) {
        $saleDate = substr($sale['timestamp'] ?? '', 0, 10);
        if ($saleDate === $todayDate) {
            $pm = strtolower($sale['payment_method'] ?? 'cash');
            $custType = strtolower($sale['customer_type'] ?? 'retail');
            $amountPaid = (float)($sale['amount_paid'] ?? 0);
            $totalPayable = (float)($sale['total_payable'] ?? 0);
            $salesHistoryTotal += $totalPayable;

            if ($pm === 'cash') {
                if ($custType === 'wholesale') {
                    $wholesaleCashSales += $amountPaid;
                } else {
                    $retailCashSales += $amountPaid;
                }
            } elseif (in_array($pm, ['bank', 'easypaisa', 'jazzcash'], true)) {
                $bankTransfers += $amountPaid;
            } elseif ($pm === 'credit') {
                $creditSales += $totalPayable;
            }
        }
    }

    // 2. Calculate wholesale recoveries from client_ledgers.json
    $ledgersFile = get_client_ledgers_file_path();
    $wholesaleRecoveries = $wholesaleCashSales;
    if (file_exists($ledgersFile)) {
        $rawLedgers = file_get_contents($ledgersFile);
        $allLedgers = json_decode($rawLedgers, true);
        if (is_array($allLedgers)) {
            foreach ($allLedgers as $clientId => $txList) {
                if (is_array($txList)) {
                    foreach ($txList as $tx) {
                        $txDate = substr($tx['date'] ?? '', 0, 10);
                        if ($txDate === $todayDate && (float)($tx['credit'] ?? 0) > 0) {
                            $desc = strtolower($tx['description'] ?? '');
                            if (strpos($desc, 'bank') === false && strpos($desc, 'online') === false && strpos($desc, 'easypaisa') === false && strpos($desc, 'jazzcash') === false) {
                                $wholesaleRecoveries += (float)$tx['credit'];
                            } else {
                                $bankTransfers += (float)$tx['credit'];
                            }
                        }
                    }
                }
            }
        }
    }

    $effectiveTotalSales = $salesHistoryTotal + $creditSales;
    $totalCashCollected = $retailCashSales + $wholesaleRecoveries;

    // 3. Daily Expenses for today
    $todayExpenses = get_today_expenses();
    $totalExpenses = 0.0;
    foreach ($todayExpenses as $exp) {
        $totalExpenses += (float)($exp['amount'] ?? 0);
    }

    // 4. Expected Physical Cash in Counter Till
    $expectedCash = $openingCash + $totalCashCollected - $totalExpenses;

    return [
        'today_date'           => $todayDate,
        'today_formatted'      => date('d M Y'),
        'opening_cash'         => $openingCash,
        'retail_cash_sales'    => $retailCashSales,
        'wholesale_recovery'   => $wholesaleRecoveries,
        'bank_transfers'       => $bankTransfers,
        'credit_sales'         => $creditSales,
        'total_sales'          => $effectiveTotalSales,
        'total_cash_collected' => $totalCashCollected,
        'today_expenses'       => $todayExpenses,
        'total_expenses'       => $totalExpenses,
        'expected_cash'        => $expectedCash,
        'closing_status'       => $closingStatus,
        'is_closed'            => $isClosed
    ];
}


