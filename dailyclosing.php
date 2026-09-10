<?php
// dailyclosing.php - Central Daily Counter Register Closing & Shop Expenses
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/store_data.php';

$currentUser = get_current_user_profile();

// Handle Direct POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Close Register
    if ($action === 'close_register') {
        $summary = get_daily_register_summary();
        $countedCash = max(0.0, (float)($_POST['counted_cash'] ?? 0));
        $remarks = trim($_POST['remarks'] ?? '');

        $record = [
            'closed_by'            => $currentUser['name'] ?? 'Admin',
            'opening_cash'         => $summary['opening_cash'],
            'retail_cash_sales'    => $summary['retail_cash_sales'],
            'wholesale_recovery'   => $summary['wholesale_recovery'],
            'bank_transfers'       => $summary['bank_transfers'],
            'credit_sales'         => $summary['credit_sales'],
            'total_sales'          => $summary['total_sales'],
            'total_cash_collected' => $summary['total_cash_collected'],
            'total_expenses'       => $summary['total_expenses'],
            'expected_cash'        => $summary['expected_cash'],
            'counted_cash'         => $countedCash,
            'remarks'              => !empty($remarks) ? $remarks : 'Daily counter register closed'
        ];

        $res = save_closing_record($record);
        if ($res['success']) {
            header("Location: dailyclosing.php?closed=1&report_id=" . urlencode($res['record']['id']));
            exit;
        } else {
            header("Location: dailyclosing.php?err=" . urlencode($res['message'] ?? 'Could not close register'));
            exit;
        }
    }

    // 2. Add Expense
    if ($action === 'add_expense') {
        $title    = trim($_POST['title'] ?? '');
        $amount   = max(0.0, (float)($_POST['amount'] ?? 0));
        $category = trim($_POST['category'] ?? 'Miscellaneous');
        $mode     = trim($_POST['payment_mode'] ?? 'Cash Drawer');

        $res = add_expense([
            'title'        => $title,
            'amount'       => $amount,
            'category'     => $category,
            'payment_mode' => $mode,
            'recorded_by'  => $currentUser['name'] ?? 'Counter Staff'
        ]);

        if ($res['success']) {
            header("Location: dailyclosing.php?expense_added=1");
            exit;
        } else {
            header("Location: dailyclosing.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 3. Delete Expense
    if ($action === 'delete_expense') {
        $id = trim($_POST['expense_id'] ?? '');
        if (!empty($id)) {
            delete_expense($id);
            header("Location: dailyclosing.php?expense_deleted=1");
            exit;
        }
    }

    // 4. Update Opening Cash
    if ($action === 'update_opening_cash') {
        $amount = max(0.0, (float)($_POST['opening_cash'] ?? 0));
        set_opening_cash($amount);
        header("Location: dailyclosing.php?opening_updated=1");
        exit;
    }

    // 5. Re-open Register
    if ($action === 'reopen_register') {
        reopen_daily_register();
        header("Location: dailyclosing.php?reopened=1");
        exit;
    }
}

// Fetch live summary and records
$summary = get_daily_register_summary();
$closingHistory = get_closing_history();
$todayExpenses = $summary['today_expenses'];
$isClosed = $summary['is_closed'];

$pageTitle = 'Smart Mobile - Daily Closing & Counter Register';
$activePage = 'dailyclosing';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

<style>
/* Styling for Z-Report & Clean Printing */
.receipt-box {
    background: #fff;
    border: 1px dashed #ced4da;
    border-radius: 8px;
    padding: 24px;
    font-family: 'Courier New', Courier, monospace;
    color: #212529;
}
.receipt-table th, .receipt-table td {
    padding: 6px 4px;
    font-size: 0.9rem;
}
@media print {
    body * {
        visibility: hidden !important;
    }
    #printableZReport, #printableZReport * {
        visibility: visible !important;
    }
    #printableZReport {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        margin: 0 !important;
        padding: 10px !important;
        background: #fff !important;
        box-shadow: none !important;
        border: none !important;
    }
    .modal-footer, .btn-close, .no-print {
        display: none !important;
    }
}
</style>

    <!-- Main Content Area -->
    <div id="main-content">

        <!-- Notification Alerts -->
        <?php if (isset($_GET['closed'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
                <div class="flex-grow-1">
                    <strong>Daily Register Closed Successfully!</strong> 
                    Counter accounts balanced and today's Z-Report generated.
                    <?php if (isset($_GET['report_id'])): ?>
                        <button class="btn btn-sm btn-dark ms-3" onclick="viewZReport('<?php echo htmlspecialchars($_GET['report_id']); ?>')">
                            <i class="fa-solid fa-print me-1"></i> Print Today's Z-Report
                        </button>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['reopened'])): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-unlock-keyhole fs-4 me-3 text-warning"></i>
                <div>
                    <strong>Register Re-opened!</strong> Counter register is active. You can record more sales and expenses.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['expense_added'])): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-receipt fs-4 me-3 text-info"></i>
                <div>
                    <strong>Shop Expense Added!</strong> Recorded in today's expenses and deducted from cash till.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['expense_deleted'])): ?>
            <div class="alert alert-secondary alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-trash fs-4 me-3 text-secondary"></i>
                <div>
                    <strong>Expense Removed!</strong> Cash till expected balance updated.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['opening_updated'])): ?>
            <div class="alert alert-primary alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-vault fs-4 me-3 text-primary"></i>
                <div>
                    <strong>Opening Cash Float Updated!</strong> Till balance recalculated.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['err'])): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-triangle-exclamation fs-4 me-3 text-danger"></i>
                <div>
                    <strong>Error:</strong> <?php echo htmlspecialchars($_GET['err']); ?>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Top Header Bar -->
        <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 bg-white p-3 rounded-3 shadow-sm">
            <div>
                <div class="d-flex align-items-center gap-2">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-calculator text-primary me-2"></i>Daily Register Closing & Counter Summary</h5>
                    <?php if ($isClosed): ?>
                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1"><i class="fa-solid fa-lock me-1"></i> Register Closed</span>
                    <?php else: ?>
                        <span class="badge bg-warning-subtle text-dark border border-warning px-2 py-1"><i class="fa-solid fa-clock-rotate-left me-1"></i> Register Open (Active)</span>
                    <?php endif; ?>
                </div>
                <small class="text-muted">
                    Session Date: <strong><?php echo $summary['today_formatted']; ?></strong> &bull; 
                    Shift: <strong>Main Counter Shift</strong> &bull; 
                    Counter Operator: <strong><?php echo htmlspecialchars($currentUser['name'] ?? 'Admin'); ?></strong>
                </small>
            </div>
            
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-secondary shadow-sm" data-bs-toggle="modal" data-bs-target="#editOpeningCashModal">
                    <i class="fa-solid fa-vault me-1"></i> Opening Cash: PKR <?php echo number_format($summary['opening_cash']); ?>
                </button>
                <button class="btn btn-outline-danger shadow-sm" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                    <i class="fa-solid fa-minus me-1"></i> Add Daily Expense
                </button>

                <?php if ($isClosed): ?>
                    <button class="btn btn-warning shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#reopenModal">
                        <i class="fa-solid fa-unlock me-1"></i> Re-open Register
                    </button>
                    <button class="btn btn-dark shadow-sm fw-semibold" onclick="viewLatestTodayReport()">
                        <i class="fa-solid fa-print me-1"></i> Today's Z-Report
                    </button>
                <?php else: ?>
                    <button class="btn btn-dark shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#confirmClosingModal">
                        <i class="fa-solid fa-lock me-1 text-warning"></i> Close Register Today
                    </button>
                <?php endif; ?>
            </div>
        </header>

        <!-- Metric Stat Cards (Live PHP Data) -->
        <div class="row g-3 mb-4">
            <!-- 1. Opening Cash -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-primary text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="d-flex align-items-center gap-1">
                                <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Opening Cash (Gulla)</small>
                                <a href="javascript:void(0)" class="text-white-50 hover-white" data-bs-toggle="modal" data-bs-target="#editOpeningCashModal" title="Edit Opening Float">
                                    <i class="fa-solid fa-pen ms-1" style="font-size: 0.75rem;"></i>
                                </a>
                            </div>
                            <h3 class="fw-bold mb-0 mt-1">PKR <?php echo number_format($summary['opening_cash']); ?></h3>
                            <small class="text-white-50" style="font-size: 0.75rem;">Till Float at Shift Start</small>
                        </div>
                        <i class="fa-solid fa-vault fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- 2. Today's Total Cash Collections -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-success text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Today Cash Collections</small>
                            <h3 class="fw-bold mb-0 mt-1">PKR <?php echo number_format($summary['total_cash_collected']); ?></h3>
                            <small class="text-white-50" style="font-size: 0.75rem;">Retail Cash + Khata Recoveries</small>
                        </div>
                        <i class="fa-solid fa-hand-holding-dollar fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- 3. Shop Expenses Today -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-danger text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <div class="d-flex align-items-center gap-1">
                                <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Shop Expenses Today</small>
                                <a href="javascript:void(0)" class="text-white-50 hover-white" data-bs-toggle="modal" data-bs-target="#addExpenseModal" title="Add Expense">
                                    <i class="fa-solid fa-plus ms-1" style="font-size: 0.75rem;"></i>
                                </a>
                            </div>
                            <h3 class="fw-bold mb-0 mt-1">PKR <?php echo number_format($summary['total_expenses']); ?></h3>
                            <small class="text-white-50" style="font-size: 0.75rem;"><?php echo count($todayExpenses); ?> Recorded Outflows</small>
                        </div>
                        <i class="fa-solid fa-receipt fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- 4. Expected Cash In Till -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-dark text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Expected Cash In Till</small>
                            <h3 class="fw-bold mb-0 mt-1 text-warning">PKR <?php echo number_format($summary['expected_cash']); ?></h3>
                            <small class="text-white-50" style="font-size: 0.75rem;">Opening + Cash In - Expenses</small>
                        </div>
                        <i class="fa-solid fa-calculator fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Middle Section: Breakdown & Expenses -->
        <div class="row g-3 mb-4">
            <!-- Left Side: Sales & Payment Breakdown -->
            <div class="col-lg-7">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <div class="fw-bold">
                            <i class="fa-solid fa-chart-pie me-2 text-primary"></i>Payment Modes & Revenue Breakdown
                        </div>
                        <span class="badge bg-light text-dark border">
                            Gross Total Sales: <strong>PKR <?php echo number_format($summary['total_sales']); ?></strong>
                        </span>
                    </div>
                    <div class="card-body p-0">
                        <ul class="list-group list-group-flush">
                            <!-- Retail Cash Sales -->
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                <div>
                                    <div class="d-flex align-items-center">
                                        <i class="fa-solid fa-money-bill-1 text-success me-2 fa-lg"></i>
                                        <strong>Retail Cash Sales</strong>
                                    </div>
                                    <small class="text-muted ps-4 d-block">Direct counter walk-in cash received</small>
                                </div>
                                <span class="fw-bold fs-6 text-dark">PKR <?php echo number_format($summary['retail_cash_sales']); ?></span>
                            </li>

                            <!-- Wholesale Recovery Cash -->
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                <div>
                                    <div class="d-flex align-items-center">
                                        <i class="fa-solid fa-hand-holding-dollar text-primary me-2 fa-lg"></i>
                                        <strong>Wholesale Khata Recovery (Cash)</strong>
                                    </div>
                                    <small class="text-muted ps-4 d-block">Purana udhaar recovery received in cash</small>
                                </div>
                                <span class="fw-bold fs-6 text-primary">PKR <?php echo number_format($summary['wholesale_recovery']); ?></span>
                            </li>

                            <!-- Online Bank / Wallet Transfers -->
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                <div>
                                    <div class="d-flex align-items-center">
                                        <i class="fa-solid fa-building-columns text-info me-2 fa-lg"></i>
                                        <strong>Online Bank Transfers & Wallets</strong>
                                    </div>
                                    <small class="text-muted ps-4 d-block">Meezan, HBL, EasyPaisa (Direct in Bank)</small>
                                </div>
                                <span class="fw-bold fs-6 text-info">PKR <?php echo number_format($summary['bank_transfers']); ?></span>
                            </li>

                            <!-- New Credit Sales (Udhaar Given) -->
                            <li class="list-group-item d-flex justify-content-between align-items-center py-3 px-4">
                                <div>
                                    <div class="d-flex align-items-center">
                                        <i class="fa-solid fa-clock-rotate-left text-danger me-2 fa-lg"></i>
                                        <strong>New Credit Sales Today (Udhaar Given)</strong>
                                    </div>
                                    <small class="text-muted ps-4 d-block">Uncollected wholesale bills given on credit</small>
                                </div>
                                <span class="fw-bold fs-6 text-danger">PKR <?php echo number_format($summary['credit_sales']); ?></span>
                            </li>
                        </ul>
                    </div>
                    <div class="card-footer bg-light p-3 d-flex flex-wrap justify-content-between align-items-center small">
                        <span><i class="fa-solid fa-info-circle text-primary me-1"></i> Cash to be deposited into Safe/Bank: <strong>PKR <?php echo number_format(max(0, $summary['expected_cash'] - $summary['opening_cash'])); ?></strong></span>
                        <span class="text-muted">Register Status: <strong class="<?php echo $isClosed ? 'text-success' : 'text-warning'; ?>"><?php echo $summary['closing_status']; ?></strong></span>
                    </div>
                </div>
            </div>

            <!-- Right Side: Daily Shop Expenses -->
            <div class="col-lg-5">
                <div class="card border-0 shadow-sm rounded-3 h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <span class="fw-bold">
                            <i class="fa-solid fa-wallet me-2 text-danger"></i>Daily Shop Expenses (Akhrajat)
                        </span>
                        <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#addExpenseModal">
                            <i class="fa-solid fa-plus me-1"></i> Add Expense
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 285px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Expense Details</th>
                                        <th>Category</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-center" style="width: 40px;"></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($todayExpenses)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center text-muted py-4">
                                                <i class="fa-solid fa-mug-hot fa-2x mb-2 text-secondary opacity-50 d-block"></i>
                                                No expenses recorded for today.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($todayExpenses as $exp): ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold text-dark"><?php echo htmlspecialchars($exp['title']); ?></div>
                                                    <small class="text-muted" style="font-size: 0.75rem;">
                                                        <i class="fa-regular fa-clock me-1"></i><?php echo date('h:i A', strtotime($exp['date'])); ?> &bull; <?php echo htmlspecialchars($exp['recorded_by'] ?? 'Staff'); ?>
                                                    </small>
                                                </td>
                                                <td>
                                                    <span class="badge <?php echo htmlspecialchars($exp['category_badge'] ?? 'bg-secondary'); ?>">
                                                        <?php echo htmlspecialchars($exp['category']); ?>
                                                    </span>
                                                </td>
                                                <td class="text-end fw-bold text-danger">
                                                    - PKR <?php echo number_format((float)$exp['amount']); ?>
                                                </td>
                                                <td class="text-center">
                                                    <form action="dailyclosing.php" method="POST" onsubmit="return confirm('Remove this expense entry?');" class="d-inline">
                                                        <input type="hidden" name="action" value="delete_expense">
                                                        <input type="hidden" name="expense_id" value="<?php echo htmlspecialchars($exp['id']); ?>">
                                                        <button type="submit" class="btn btn-link text-danger p-0 border-0" title="Delete Expense">
                                                            <i class="fa-solid fa-xmark"></i>
                                                        </button>
                                                    </form>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-white d-flex justify-content-between fw-bold py-3 border-top">
                        <span>Total Deducted Expenses:</span>
                        <span class="text-danger fs-6">- PKR <?php echo number_format($summary['total_expenses']); ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Closing History Table Card -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold">
                    <i class="fa-solid fa-clock-rotate-left me-2 text-primary"></i>Previous Daily Closing Records & Z-Reports
                </h6>
                <span class="badge bg-secondary"><?php echo count($closingHistory); ?> Archive Records</span>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Closing Date</th>
                                <th>Closed By</th>
                                <th class="text-end">Opening Cash</th>
                                <th class="text-end">Total Sales</th>
                                <th class="text-end">Net Cash In</th>
                                <th class="text-end">Expenses</th>
                                <th class="text-end">Counted Cash</th>
                                <th>Status / Variance</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($closingHistory)): ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-4">No previous closing records found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($closingHistory as $ch): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($ch['date_formatted'] ?? $ch['date']); ?></div>
                                            <small class="text-muted" style="font-size: 0.75rem;"><?php echo date('h:i A', strtotime($ch['closed_at'] ?? $ch['date'])); ?></small>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fa-solid fa-user-shield text-secondary me-2"></i>
                                                <span><?php echo htmlspecialchars($ch['closed_by']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end">PKR <?php echo number_format((float)($ch['opening_cash'] ?? 0)); ?></td>
                                        <td class="text-end fw-semibold">PKR <?php echo number_format((float)($ch['total_sales'] ?? 0)); ?></td>
                                        <td class="text-end text-success fw-bold">PKR <?php echo number_format((float)($ch['net_cash_collected'] ?? 0)); ?></td>
                                        <td class="text-end text-danger">- PKR <?php echo number_format((float)($ch['expenses'] ?? 0)); ?></td>
                                        <td class="text-end fw-bold text-dark">PKR <?php echo number_format((float)($ch['counted_cash'] ?? $ch['expected_cash'] ?? 0)); ?></td>
                                        <td>
                                            <span class="badge <?php echo htmlspecialchars($ch['status_badge'] ?? 'bg-success-subtle text-success'); ?>">
                                                <?php echo htmlspecialchars($ch['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-outline-dark" onclick="viewZReport('<?php echo htmlspecialchars($ch['id']); ?>')">
                                                <i class="fa-solid fa-print me-1"></i> Print Z-Report
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal 1: Add Daily Shop Expense -->
    <div class="modal fade" id="addExpenseModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-receipt me-2 text-danger"></i>Record Daily Shop Expense</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="dailyclosing.php" method="POST">
                    <input type="hidden" name="action" value="add_expense">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Expense Title / Description <span class="text-danger">*</span></label>
                                <input type="text" name="title" class="form-control" placeholder="e.g. Tea & Lunch for Staff / Cargo Freight" required autofocus>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Amount (PKR) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">PKR</span>
                                    <input type="number" name="amount" class="form-control fw-bold" placeholder="0.00" min="1" step="any" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Expense Category</label>
                                <select name="category" class="form-select">
                                    <option value="Entertainment / Food">Entertainment / Food (Chai/Lunch)</option>
                                    <option value="Utilities (Electricity/Net)">Utilities (Electricity, Net, UPS)</option>
                                    <option value="Transport / Cargo">Transport / Cargo / Rikshaw</option>
                                    <option value="Shop Maintenance">Shop Maintenance / Cleaning</option>
                                    <option value="Staff Salary / Advance">Staff Salary / Advance</option>
                                    <option value="Miscellaneous">Miscellaneous</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Payment Source</label>
                                <select name="payment_mode" class="form-select">
                                    <option value="Cash Drawer">Physical Cash Drawer (Gulla) - Deduct from Till</option>
                                    <option value="Bank Account">Bank Transfer / Online (Not from Gulla)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4 fw-bold"><i class="fa-solid fa-save me-1"></i> Save Expense</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 2: Edit Opening Cash Float -->
    <div class="modal fade" id="editOpeningCashModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-vault me-2 text-primary"></i>Opening Cash Float</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="dailyclosing.php" method="POST">
                    <input type="hidden" name="action" value="update_opening_cash">
                    <div class="modal-body">
                        <label class="form-label fw-semibold">Gullay Ka Opening Cash (PKR)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">PKR</span>
                            <input type="number" name="opening_cash" class="form-control fw-bold" value="<?php echo (float)$summary['opening_cash']; ?>" min="0" step="100" required>
                        </div>
                        <small class="text-muted d-block mt-2">Cash present in counter drawer when shop opens in the morning.</small>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-3 fw-bold"><i class="fa-solid fa-check me-1"></i> Update</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 3: Confirm & Close Register Today -->
    <div class="modal fade" id="confirmClosingModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-lock me-2 text-warning"></i>Close Counter & Register Today</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="dailyclosing.php" method="POST">
                    <input type="hidden" name="action" value="close_register">
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-12 text-center py-3 bg-light rounded-3 border">
                                <small class="text-muted d-block fw-semibold text-uppercase" style="letter-spacing: 0.5px;">Expected Physical Cash in Counter Till</small>
                                <h2 class="fw-bold text-primary mb-0 mt-1" id="modalExpectedDisplay">PKR <?php echo number_format($summary['expected_cash']); ?></h2>
                                <small class="text-muted" style="font-size: 0.8rem;">(Opening PKR <?php echo number_format($summary['opening_cash']); ?> + Collections PKR <?php echo number_format($summary['total_cash_collected']); ?> - Expenses PKR <?php echo number_format($summary['total_expenses']); ?>)</small>
                            </div>
                            
                            <div class="col-12">
                                <label class="form-label fw-semibold">Actual Cash Counted in Till (Gulla) <span class="text-danger">*</span></label>
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text">PKR</span>
                                    <input type="number" name="counted_cash" id="countedCashInput" class="form-control form-control-lg fw-bold" 
                                           placeholder="Enter physical cash counted..." 
                                           value="<?php echo (float)$summary['expected_cash']; ?>" 
                                           min="0" step="any" required oninput="calcDifference(this.value)">
                                </div>
                            </div>

                            <!-- Live Reconciliation Difference Note -->
                            <div class="col-12">
                                <div id="reconDifferenceAlert" class="alert alert-success py-2 px-3 mb-0 d-flex align-items-center">
                                    <i class="fa-solid fa-circle-check fs-5 me-2"></i>
                                    <div>
                                        <strong>Exact Match:</strong> Pori pori raqam barabar hai (No shortage/surplus).
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Closing Remarks / Discrepancy Note</label>
                                <input type="text" name="remarks" class="form-control" placeholder="e.g. All physical cash matched counter collections">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-dark px-4 fw-bold"><i class="fa-solid fa-print me-1"></i> Confirm & Close Register</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 4: Re-open Register Confirmation -->
    <div class="modal fade" id="reopenModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-sm">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-unlock text-warning me-2"></i>Re-open Register?</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="dailyclosing.php" method="POST">
                    <input type="hidden" name="action" value="reopen_register">
                    <div class="modal-body">
                        <p class="mb-0 text-muted">Aap register ko dobara open kar rhy hain taake mazeed sales ya expenses record kiye ja sakain. Continue?</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning fw-bold"><i class="fa-solid fa-unlock me-1"></i> Yes, Re-open</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal 5: Printable Z-Report Closing Summary Slip -->
    <div class="modal fade" id="zReportModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-md">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white no-print">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-invoice-dollar me-2 text-warning"></i>Daily Closing Z-Report Slip</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="printableZReport">
                    <div class="receipt-box shadow-sm">
                        <!-- Header -->
                        <div class="text-center pb-2 border-bottom border-dark border-2">
                            <h4 class="fw-bold mb-0">SMART MOBILE</h4>
                            <div class="small fw-semibold">WHOLESALE & RETAIL ERP</div>
                            <div style="font-size: 0.8rem;" class="text-muted">Hall Road Mobile Market, Lahore</div>
                            <div class="fw-bold mt-2 py-1 bg-dark text-white rounded" style="font-size: 0.85rem; letter-spacing: 1px;">
                                DAILY CLOSING Z-REPORT
                            </div>
                        </div>

                        <!-- Shift Meta -->
                        <div class="py-2 border-bottom border-secondary border-dashed" style="font-size: 0.85rem;">
                            <div class="d-flex justify-content-between">
                                <span>Report Date:</span>
                                <strong id="repDate"><?php echo $summary['today_formatted']; ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Closed At:</span>
                                <span id="repTime"><?php echo date('d M Y, h:i A'); ?></span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Closed By:</span>
                                <strong id="repUser"><?php echo htmlspecialchars($currentUser['name'] ?? 'Admin'); ?></strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Report Ref ID:</span>
                                <span id="repId">CLS-<?php echo date('Ymd'); ?></span>
                            </div>
                        </div>

                        <!-- Financial Figures -->
                        <table class="table table-borderless receipt-table mb-0 mt-2">
                            <tbody>
                                <tr>
                                    <td>Opening Cash Float (Gulla):</td>
                                    <td class="text-end fw-bold" id="repOpening">PKR 15,000</td>
                                </tr>
                                <tr>
                                    <td>Retail Counter Cash:</td>
                                    <td class="text-end fw-bold text-success" id="repRetailCash">+ PKR 65,000</td>
                                </tr>
                                <tr>
                                    <td>Wholesale Khata Recovery (Cash):</td>
                                    <td class="text-end fw-bold text-success" id="repKhataCash">+ PKR 120,000</td>
                                </tr>
                                <tr class="border-top border-secondary">
                                    <td class="fw-bold">Total Cash Inflows:</td>
                                    <td class="text-end fw-bold text-success" id="repTotalCashIn">PKR 185,000</td>
                                </tr>
                                <tr>
                                    <td>Less: Shop Expenses (Akhrajat):</td>
                                    <td class="text-end fw-bold text-danger" id="repExpenses">- PKR 3,500</td>
                                </tr>
                                <tr class="border-top border-dark border-2">
                                    <td class="fw-bold fs-6">EXPECTED CASH IN TILL:</td>
                                    <td class="text-end fw-bold fs-6 text-dark" id="repExpected">PKR 196,500</td>
                                </tr>
                                <tr class="bg-light">
                                    <td class="fw-bold fs-6">ACTUAL COUNTED CASH:</td>
                                    <td class="text-end fw-bold fs-6 text-primary" id="repCounted">PKR 196,500</td>
                                </tr>
                                <tr class="border-bottom border-dark border-2">
                                    <td class="fw-bold">Cash Variance / Difference:</td>
                                    <td class="text-end fw-bold" id="repDifference">PKR 0 (Balanced)</td>
                                </tr>
                            </tbody>
                        </table>

                        <!-- Non-Cash Sales Summary -->
                        <div class="mt-3 pt-2 border-top border-secondary border-dashed" style="font-size: 0.85rem;">
                            <div class="fw-bold mb-1 text-muted">NON-CASH / MEMO SALES SUMMARY:</div>
                            <div class="d-flex justify-content-between">
                                <span>Online Bank / Wallet Transfers:</span>
                                <span class="fw-semibold" id="repBank">PKR 35,000</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>New Credit Sales (Udhaar Given):</span>
                                <span class="fw-semibold" id="repCredit">PKR 45,000</span>
                            </div>
                            <div class="d-flex justify-content-between border-top pt-1 mt-1 fw-bold">
                                <span>Gross Total Sales Today:</span>
                                <span id="repTotalSales">PKR 185,000</span>
                            </div>
                        </div>

                        <!-- Remarks -->
                        <div class="mt-3 pt-2 border-top border-secondary border-dashed" style="font-size: 0.8rem;">
                            <div><strong>Manager Remarks:</strong> <span id="repRemarks">All counter collections verified & reconciled.</span></div>
                        </div>

                        <!-- Signatures -->
                        <div class="row text-center mt-4 pt-4 border-top border-secondary" style="font-size: 0.75rem;">
                            <div class="col-6">
                                <div class="border-top border-dark pt-1">Cashier Signature</div>
                            </div>
                            <div class="col-6">
                                <div class="border-top border-dark pt-1">Manager Signature</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-dark fw-bold" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print Z-Report</button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Live Calculations and Dynamic Z-Report -->
    <script>
    const expectedCashValue = <?php echo (float)$summary['expected_cash']; ?>;

    function calcDifference(countedVal) {
        const counted = parseFloat(countedVal) || 0;
        const diff = counted - expectedCashValue;
        const diffAlert = document.getElementById('reconDifferenceAlert');

        if (Math.abs(diff) < 0.01) {
            diffAlert.className = 'alert alert-success py-2 px-3 mb-0 d-flex align-items-center';
            diffAlert.innerHTML = '<i class="fa-solid fa-circle-check fs-5 me-2"></i><div><strong>Exact Match:</strong> Pori pori raqam barabar hai (No shortage/surplus).</div>';
        } else if (diff < 0) {
            diffAlert.className = 'alert alert-danger py-2 px-3 mb-0 d-flex align-items-center';
            diffAlert.innerHTML = '<i class="fa-solid fa-triangle-exclamation fs-5 me-2"></i><div><strong>Cash Shortage (Kami):</strong> PKR ' + Math.abs(diff).toLocaleString() + ' kam hain counter mein.</div>';
        } else {
            diffAlert.className = 'alert alert-primary py-2 px-3 mb-0 d-flex align-items-center';
            diffAlert.innerHTML = '<i class="fa-solid fa-circle-info fs-5 me-2"></i><div><strong>Cash Surplus (Izaafi):</strong> PKR ' + diff.toLocaleString() + ' izaafi raqam mojood hai.</div>';
        }
    }

    function viewZReport(reportId) {
        fetch('ajax_daily_closing.php?action=get_closing_report&id=' + encodeURIComponent(reportId))
            .then(res => res.json())
            .then(data => {
                if (data.success && data.report) {
                    const r = data.report;
                    document.getElementById('repDate').textContent = r.date_formatted || r.date;
                    document.getElementById('repTime').textContent = r.closed_at || r.date;
                    document.getElementById('repUser').textContent = r.closed_by || 'Admin';
                    document.getElementById('repId').textContent = r.id;
                    document.getElementById('repOpening').textContent = 'PKR ' + parseFloat(r.opening_cash || 0).toLocaleString();
                    document.getElementById('repRetailCash').textContent = '+ PKR ' + parseFloat(r.retail_cash_sales || 0).toLocaleString();
                    document.getElementById('repKhataCash').textContent = '+ PKR ' + parseFloat(r.wholesale_recovery || 0).toLocaleString();
                    document.getElementById('repTotalCashIn').textContent = 'PKR ' + parseFloat(r.net_cash_collected || 0).toLocaleString();
                    document.getElementById('repExpenses').textContent = '- PKR ' + parseFloat(r.expenses || 0).toLocaleString();
                    document.getElementById('repExpected').textContent = 'PKR ' + parseFloat(r.expected_cash || 0).toLocaleString();
                    document.getElementById('repCounted').textContent = 'PKR ' + parseFloat(r.counted_cash || 0).toLocaleString();
                    
                    const diff = parseFloat(r.difference || 0);
                    let diffText = 'PKR 0 (Exact Match)';
                    if (diff < 0) diffText = '- PKR ' + Math.abs(diff).toLocaleString() + ' (Shortage)';
                    else if (diff > 0) diffText = '+ PKR ' + diff.toLocaleString() + ' (Surplus)';
                    document.getElementById('repDifference').textContent = diffText;

                    document.getElementById('repBank').textContent = 'PKR ' + parseFloat(r.bank_transfers || 0).toLocaleString();
                    document.getElementById('repCredit').textContent = 'PKR ' + parseFloat(r.credit_sales || 0).toLocaleString();
                    document.getElementById('repTotalSales').textContent = 'PKR ' + parseFloat(r.total_sales || 0).toLocaleString();
                    document.getElementById('repRemarks').textContent = r.remarks || 'Normal closing';

                    const modal = new bootstrap.Modal(document.getElementById('zReportModal'));
                    modal.show();
                } else {
                    alert(data.message || 'Unable to load report');
                }
            })
            .catch(err => {
                console.error(err);
                alert('Error loading Z-Report');
            });
    }

    function viewLatestTodayReport() {
        // If report exists in closing history for today, show first report
        <?php if (!empty($closingHistory)): ?>
            viewZReport('<?php echo htmlspecialchars($closingHistory[0]['id']); ?>');
        <?php else: ?>
            const modal = new bootstrap.Modal(document.getElementById('zReportModal'));
            modal.show();
        <?php endif; ?>
    }

    // Auto-popup Z-Report if just closed
    <?php if (isset($_GET['closed']) && isset($_GET['report_id'])): ?>
        window.addEventListener('DOMContentLoaded', () => {
            viewZReport('<?php echo htmlspecialchars($_GET['report_id']); ?>');
        });
    <?php endif; ?>
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
