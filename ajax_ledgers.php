<?php
// ajax_ledgers.php - Central API for Client & Vendor Khata Ledgers
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/auth.php';
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Session expired or not logged in. Please refresh and login.']);
    exit;
}
require_once __DIR__ . '/includes/store_data.php';

$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true);
$req = is_array($json) ? $json : array_merge($_GET, $_POST);

$action = $req['action'] ?? 'get_statement';

try {
    // 1. Get Statement (Client or Vendor)
    if ($action === 'get_statement') {
        $type = strtolower(trim($req['type'] ?? 'client'));
        $id   = trim($req['id'] ?? '');

        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Account ID is required']);
            exit;
        }

        if ($type === 'vendor') {
            $vendors = get_vendors_list();
            if (!isset($vendors[$id])) {
                echo json_encode(['success' => false, 'message' => 'Vendor not found']);
                exit;
            }
            $account = $vendors[$id];
            $ledger  = get_vendor_ledger($id);

            $totalDebit = 0;
            $totalCredit = 0;
            foreach ($ledger as $tx) {
                $totalDebit  += (float)($tx['debit'] ?? 0);
                $totalCredit += (float)($tx['credit'] ?? 0);
            }

            echo json_encode([
                'success'      => true,
                'type'         => 'vendor',
                'account'      => $account,
                'ledger'       => $ledger,
                'total_debit'  => $totalDebit,   // Payments made to vendor
                'total_credit' => $totalCredit,  // Supplies / Invoices from vendor
                'balance'      => (float)($account['balance'] ?? 0) // Payable to vendor
            ]);
            exit;
        } else {
            // Client
            $clients = get_clients_list();
            if (!isset($clients[$id])) {
                echo json_encode(['success' => false, 'message' => 'Client not found']);
                exit;
            }
            $account = $clients[$id];
            $ledger  = get_client_ledger($id);

            $totalDebit = 0;
            $totalCredit = 0;
            foreach ($ledger as $tx) {
                $totalDebit  += (float)($tx['debit'] ?? 0);
                $totalCredit += (float)($tx['credit'] ?? 0);
            }

            echo json_encode([
                'success'      => true,
                'type'         => 'client',
                'account'      => $account,
                'ledger'       => $ledger,
                'total_debit'  => $totalDebit,   // Purchases made by client
                'total_credit' => $totalCredit,  // Payments received from client
                'balance'      => (float)($account['balance'] ?? 0) // Receivable from client
            ]);
            exit;
        }
    }

    // 2. Record Transaction Entry
    if ($action === 'record_transaction' || $action === 'record_tx') {
        $idVal     = trim($req['id'] ?? $req['account_id'] ?? '');
        $type      = strtolower(trim($req['type'] ?? ''));
        if (strpos($idVal, ':') !== false) {
            $parts = explode(':', $idVal, 2);
            $type = $type ?: strtolower($parts[0]);
            $id = $parts[1];
        } else {
            $id = $idVal;
            $type = $type ?: 'client';
        }

        $rawTxType = strtolower(trim($req['tx_type'] ?? 'payment'));
        $isPayment = in_array($rawTxType, ['payment', 'paid', 'pay', 'cash_paid', 'debit_vendor', 'credit_client'], true);
        $amount    = max(0.0, (float)($req['amount'] ?? 0));
        $desc      = trim($req['description'] ?? $req['desc'] ?? '');
        $ref       = trim($req['ref'] ?? '');

        if (empty($id) || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Account and Amount (> 0) are required']);
            exit;
        }

        if ($type === 'vendor') {
            // In vendor khata:
            // Payment paid to vendor -> debit (reduces payable balance)
            // Purchase / bill from vendor -> credit (increases payable balance)
            $actualType = $isPayment ? 'debit' : 'credit';
            if (empty($desc)) {
                $desc = $isPayment ? 'Payment Paid to Supplier' : 'Goods / Stock Received from Supplier';
            }
            $res = record_vendor_transaction($id, $actualType, $amount, $desc, $ref);
            echo json_encode($res);
            exit;
        } else {
            // In client khata:
            // Payment received from client -> credit (reduces receivable balance)
            // Wholesale sale / invoice to client -> debit (increases receivable balance)
            $actualType = $isPayment ? 'credit' : 'debit';
            if (empty($desc)) {
                $desc = $isPayment ? 'Payment Received from Client' : 'Wholesale Sale / Invoice';
            }
            $res = record_client_transaction($id, $actualType, $amount, $desc, $ref);
            echo json_encode($res);
            exit;
        }
    }

    // 3. Add Vendor
    if ($action === 'add_vendor') {
        $res = add_vendor([
            'name'     => trim($req['name'] ?? ''),
            'contact'  => trim($req['contact'] ?? ''),
            'phone'    => trim($req['phone'] ?? ''),
            'city'     => trim($req['city'] ?? ''),
            'category' => trim($req['category'] ?? 'Mobile Parts & Accessories'),
            'balance'  => max(0.0, (float)($req['balance'] ?? 0)),
            'limit'    => max(0.0, (float)($req['limit'] ?? 500000))
        ]);
        echo json_encode($res);
        exit;
    }

    // 4. Add Client
    if ($action === 'add_client') {
        $res = add_client([
            'name'    => trim($req['name'] ?? ''),
            'owner'   => trim($req['owner'] ?? ''),
            'phone'   => trim($req['phone'] ?? ''),
            'city'    => trim($req['city'] ?? ''),
            'type'    => trim($req['type'] ?? 'wholesale'),
            'balance' => max(0.0, (float)($req['balance'] ?? 0)),
            'limit'   => max(0.0, (float)($req['limit'] ?? 200000))
        ]);
        echo json_encode($res);
        exit;
    }

    // 5. Delete Account
    if ($action === 'delete_account') {
        $type = strtolower(trim($req['type'] ?? 'client'));
        $id   = trim($req['id'] ?? '');

        if ($type === 'vendor') {
            $res = delete_vendor($id);
        } else {
            $res = delete_client($id);
        }
        echo json_encode($res);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
