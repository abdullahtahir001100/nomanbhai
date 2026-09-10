<?php
// ajax_client_ledger.php - Asynchronous Wholesale Client & Khata Ledger API
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/store_data.php';

$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true);
$req = is_array($json) ? $json : array_merge($_GET, $_POST);

$action = $req['action'] ?? 'get_ledger';

try {
    // 1. Get Client Ledger Statement
    if ($action === 'get_ledger') {
        $clientId = trim($req['client_id'] ?? '');
        if (empty($clientId)) {
            echo json_encode(['success' => false, 'message' => 'Client ID is required']);
            exit;
        }

        $clients = get_clients_list();
        if (!isset($clients[$clientId])) {
            echo json_encode(['success' => false, 'message' => 'Client not found']);
            exit;
        }

        $client = $clients[$clientId];
        $ledger = get_client_ledger($clientId);

        $totalDebit = 0;
        $totalCredit = 0;
        foreach ($ledger as $tx) {
            $totalDebit  += (float)($tx['debit'] ?? 0);
            $totalCredit += (float)($tx['credit'] ?? 0);
        }

        echo json_encode([
            'success'      => true,
            'client'       => $client,
            'ledger'       => $ledger,
            'total_debit'  => $totalDebit,
            'total_credit' => $totalCredit,
            'balance'      => (float)($client['balance'] ?? 0)
        ]);
        exit;
    }

    // 2. Receive Payment (Khata Entry)
    if ($action === 'receive_payment') {
        $clientId = trim($req['client_id'] ?? '');
        $amount   = max(0.0, (float)($req['amount'] ?? 0));
        $mode     = trim($req['mode'] ?? 'Cash');
        $remarks  = trim($req['remarks'] ?? '');
        $ref      = trim($req['ref'] ?? ('RCPT-' . rand(100, 999)));

        if (empty($clientId) || $amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Valid Client and Amount (> 0) are required']);
            exit;
        }

        $desc = "Payment Received ({$mode})" . (!empty($remarks) ? ": {$remarks}" : '');
        $result = record_client_transaction($clientId, 'credit', $amount, $desc, $ref);

        if ($result['success']) {
            $updatedClients = get_clients_list();
            $updatedLedger  = get_client_ledger($clientId);
            echo json_encode([
                'success'     => true,
                'message'     => 'Payment recorded successfully',
                'new_balance' => $result['new_balance'],
                'client'      => $updatedClients[$clientId] ?? null,
                'ledger'      => $updatedLedger
            ]);
            exit;
        } else {
            echo json_encode($result);
            exit;
        }
    }

    // 3. Add Client
    if ($action === 'add_client') {
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

    // 4. Edit Client
    if ($action === 'edit_client') {
        $clientId = trim($req['client_id'] ?? '');
        $res = edit_client($clientId, $req);
        echo json_encode($res);
        exit;
    }

    // 5. Delete Client
    if ($action === 'delete_client') {
        $clientId = trim($req['client_id'] ?? '');
        $res = delete_client($clientId);
        echo json_encode($res);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Invalid action']);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
