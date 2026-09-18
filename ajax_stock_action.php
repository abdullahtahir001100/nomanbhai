<?php
// ajax_stock_action.php - Central Stock & Sales AJAX Processor
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/store_data.php';

// Access protection
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'POST request expected']);
    exit;
}

$action = trim($_POST['action'] ?? '');

if ($action === 'adjust_stock') {
    $sku  = trim($_POST['sku'] ?? '');
    $type = trim($_POST['type'] ?? 'in');
    $qty  = max(1, (int)($_POST['quantity'] ?? 1));
    $note = trim($_POST['note'] ?? '');

    if (empty($sku)) {
        echo json_encode(['success' => false, 'message' => 'Item select karein.']);
        exit;
    }

    $result = record_stock_adjustment($sku, $type, $qty, $note);
    if ($result['success']) {
        $metrics = get_store_metrics();
        echo json_encode([
            'success'              => true,
            'message'              => ($type === 'in' ? "Stock IN: " : "Stock OUT: ") . "{$qty} units updated successfully.",
            'sku'                  => $sku,
            'new_stock'            => $result['new_stock'],
            'stock_in_today'       => $metrics['stock_in_today'],
            'stock_out_today'      => $metrics['stock_out_today'],
            'today_sales'          => $metrics['today_sales'],
            'daily_closing_status' => $metrics['daily_closing_status']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => $result['message'] ?? 'Error updating stock']);
    }
    exit;
}

if ($action === 'record_sale') {
    $amount = (float)($_POST['amount'] ?? 0);
    $qty    = max(1, (int)($_POST['quantity'] ?? 1));
    $sku    = !empty($_POST['sku']) ? trim($_POST['sku']) : null;

    if ($amount <= 0) {
        echo json_encode(['success' => false, 'message' => 'Invalid sale amount']);
        exit;
    }

    $res = record_sale_transaction($amount, $qty, $sku);
    $metrics = get_store_metrics();
    echo json_encode([
        'success'              => true,
        'message'              => "Sale recorded successfully!",
        'today_sales'          => $metrics['today_sales'],
        'stock_out_today'      => $metrics['stock_out_today'],
        'daily_closing_status' => $metrics['daily_closing_status']
    ]);
    exit;
}

if ($action === 'update_closing') {
    $status = trim($_POST['status'] ?? 'Pending');
    update_daily_closing_status($status);
    $metrics = get_store_metrics();
    echo json_encode([
        'success'              => true,
        'message'              => "Closing status updated to {$status}",
        'daily_closing_status' => $metrics['daily_closing_status']
    ]);
    exit;
}

echo json_encode(['success' => false, 'message' => 'Unknown action']);
