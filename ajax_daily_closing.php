<?php
// ajax_daily_closing.php - Asynchronous API for Counter Closing & Expenses
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/includes/auth.php';
if (!is_logged_in()) {
    echo json_encode(['success' => false, 'message' => 'Session expired or unauthorized. Please login.']);
    exit;
}

require_once __DIR__ . '/includes/store_data.php';

$rawInput = file_get_contents('php://input');
$json = json_decode($rawInput, true);
$req = is_array($json) ? $json : array_merge($_GET, $_POST);

$action = $req['action'] ?? 'get_summary';

try {
    // 1. Get Live Register Summary
    if ($action === 'get_summary') {
        $summary = get_daily_register_summary();
        echo json_encode([
            'success' => true,
            'summary' => $summary
        ]);
        exit;
    }

    // 2. Add Expense
    if ($action === 'add_expense') {
        $title    = trim($req['title'] ?? '');
        $amount   = max(0.0, (float)($req['amount'] ?? 0));
        $category = trim($req['category'] ?? 'Miscellaneous');
        $mode     = trim($req['payment_mode'] ?? 'Cash Drawer');

        $currentUser = get_current_user_profile();
        $recordedBy = $currentUser['name'] ?? 'Counter Staff';

        $res = add_expense([
            'title'        => $title,
            'amount'       => $amount,
            'category'     => $category,
            'payment_mode' => $mode,
            'recorded_by'  => $recordedBy
        ]);

        if ($res['success']) {
            $summary = get_daily_register_summary();
            echo json_encode([
                'success'        => true,
                'expense'        => $res['expense'],
                'total_expenses' => $summary['total_expenses'],
                'expected_cash'  => $summary['expected_cash'],
                'summary'        => $summary
            ]);
        } else {
            echo json_encode($res);
        }
        exit;
    }

    // 3. Delete Expense
    if ($action === 'delete_expense') {
        $id = trim($req['id'] ?? '');
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'Expense ID required']);
            exit;
        }

        delete_expense($id);
        $summary = get_daily_register_summary();
        echo json_encode([
            'success'        => true,
            'deleted_id'     => $id,
            'total_expenses' => $summary['total_expenses'],
            'expected_cash'  => $summary['expected_cash'],
            'summary'        => $summary
        ]);
        exit;
    }

    // 4. Update Opening Cash Till Float
    if ($action === 'update_opening_cash') {
        $amount = max(0.0, (float)($req['amount'] ?? 0));
        set_opening_cash($amount);
        $summary = get_daily_register_summary();
        echo json_encode([
            'success'      => true,
            'opening_cash' => $amount,
            'expected_cash'=> $summary['expected_cash'],
            'summary'      => $summary
        ]);
        exit;
    }

    // 5. Get Specific Closing Record (for Z-Report Modal & Print)
    if ($action === 'get_closing_report') {
        $id = trim($req['id'] ?? '');
        $history = get_closing_history();
        $found = null;

        foreach ($history as $rec) {
            if (($rec['id'] ?? '') === $id) {
                $found = $rec;
                break;
            }
        }

        if ($found) {
            echo json_encode(['success' => true, 'report' => $found]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Closing report record not found']);
        }
        exit;
    }

    // 6. Reopen Register
    if ($action === 'reopen_register') {
        reopen_daily_register();
        echo json_encode(['success' => true, 'status' => 'Pending']);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action']);
} catch (\Throwable $e) {
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
}
