<?php
// ajax_pos_sale.php - Asynchronous POS Transaction & Quick Entry Processor
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/store_data.php';

// Accept both JSON payload and standard POST
$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true);

$req = is_array($json) ? $json : $_POST;
$action = $req['action'] ?? 'checkout';

try {
    if ($action === 'checkout') {
        $items = $req['items'] ?? [];
        if (empty($items)) {
            echo json_encode(['success' => false, 'message' => 'Cart is empty. Please add items to sell.']);
            exit;
        }

        $saleData = [
            'invoice_no'      => $req['invoice_no'] ?? ('INV-' . date('Ymd') . '-' . rand(1000, 9999)),
            'customer_name'   => trim($req['customer_name'] ?? 'Walk-in Retail Customer'),
            'customer_phone'  => trim($req['customer_phone'] ?? ''),
            'customer_type'   => trim($req['customer_type'] ?? 'retail'),
            'client_id'       => trim($req['client_id'] ?? ''),
            'payment_method'  => trim($req['payment_method'] ?? 'cash'),
            'amount_paid'     => (float)($req['amount_paid'] ?? 0),
            'change_returned' => max(0.0, (float)($req['change_returned'] ?? 0)),
            'bank_account'    => trim($req['bank_account'] ?? ''),
            'transaction_ref' => trim($req['transaction_ref'] ?? ''),
            'subtotal'        => (float)($req['subtotal'] ?? 0),
            'discount'        => (float)($req['discount'] ?? 0),
            'total_payable'   => (float)($req['total_payable'] ?? 0),
            'items'           => $items,
            'notes'           => trim($req['notes'] ?? '')
        ];

        $result = process_pos_sale($saleData);
        echo json_encode($result);
        exit;
    }

    if ($action === 'quick_add_product') {
        $name           = trim($req['name'] ?? '');
        $sku            = trim($req['sku'] ?? '');
        $category       = trim($req['category'] ?? 'General');
        $stock          = max(1, (int)($req['stock'] ?? 1));
        $costPrice      = max(0.0, (float)($req['cost_price'] ?? 0));
        $wholesalePrice = max(0.0, (float)($req['wholesale_price'] ?? 0));
        $retailPrice    = max(0.0, (float)($req['retail_price'] ?? 0));

        $res = add_product([
            'name'            => $name,
            'sku'             => $sku,
            'category'        => $category,
            'stock'           => $stock,
            'cost_price'      => $costPrice,
            'wholesale_price' => $wholesalePrice,
            'retail_price'    => $retailPrice
        ]);

        echo json_encode($res);
        exit;
    }

    if ($action === 'quick_add_client') {
        $name    = trim($req['name'] ?? '');
        $owner   = trim($req['owner'] ?? $name);
        $phone   = trim($req['phone'] ?? '');
        $city    = trim($req['city'] ?? '');
        $type    = trim($req['type'] ?? 'wholesale');
        $limit   = max(0.0, (float)($req['limit'] ?? 100000));
        $balance = max(0.0, (float)($req['balance'] ?? 0));

        $res = add_client([
            'name'    => $name,
            'owner'   => $owner,
            'phone'   => $phone,
            'city'    => $city,
            'type'    => $type,
            'limit'   => $limit,
            'balance' => $balance
        ]);

        echo json_encode($res);
        exit;
    }

    if ($action === 'get_recent_sales') {
        $history = get_sales_history(20);
        echo json_encode(['success' => true, 'sales' => $history]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action specified.']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
