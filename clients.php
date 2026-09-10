<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/store_data.php';

// Handle Form Submissions (Fallback & Direct POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Add Client
    if ($action === 'add_client') {
        $res = add_client([
            'name'    => trim($_POST['name'] ?? ''),
            'owner'   => trim($_POST['owner'] ?? ''),
            'phone'   => trim($_POST['phone'] ?? ''),
            'city'    => trim($_POST['city'] ?? ''),
            'limit'   => max(0.0, (float)($_POST['limit'] ?? 100000)),
            'balance' => max(0.0, (float)($_POST['balance'] ?? 0))
        ]);

        if ($res['success']) {
            header("Location: clients.php?client_added=" . urlencode($res['client']['name']));
            exit;
        } else {
            header("Location: clients.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 2. Receive Payment
    if ($action === 'receive_payment') {
        $clientId = trim($_POST['client_id'] ?? '');
        $amount   = max(0.0, (float)($_POST['amount'] ?? 0));
        $mode     = trim($_POST['mode'] ?? 'Cash');
        $remarks  = trim($_POST['remarks'] ?? '');
        $ref      = trim($_POST['ref'] ?? '');
        $desc     = "Payment Received ({$mode})" . (!empty($remarks) ? ": {$remarks}" : '');

        if (!empty($clientId) && $amount > 0) {
            $res = record_client_transaction($clientId, 'credit', $amount, $desc, $ref);
            if ($res['success']) {
                header("Location: clients.php?payment_received=1&amount={$amount}&client_id=" . urlencode($clientId));
                exit;
            } else {
                header("Location: clients.php?err=" . urlencode($res['message']));
                exit;
            }
        }
    }

    // 3. Delete Client
    if ($action === 'delete_client') {
        $clientId = trim($_POST['client_id'] ?? '');
        if (!empty($clientId)) {
            $res = delete_client($clientId);
            header("Location: clients.php?client_deleted=1");
            exit;
        }
    }
}

$clients = get_clients_list();

// Calculate Dynamic Client Metrics
$totalClients = count($clients);
$totalReceivables = 0.0;
$overdueCount = 0;
foreach ($clients as $c) {
    $bal = (float)($c['balance'] ?? 0);
    $totalReceivables += $bal;
    $limit = (float)($c['limit'] ?? 0);
    if ($bal > 0 && ($limit > 0 && $bal >= ($limit * 0.7))) {
        $overdueCount++;
    }
}
$totalReceived = get_total_payments_received();

$pageTitle = 'Smart Mobile - Wholesale Clients & Ledger';
$activePage = 'clients';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Helper for initials
function get_initials(string $name): string {
    $words = explode(' ', trim($name));
    $initials = '';
    foreach ($words as $w) {
        if (!empty($w)) {
            $initials .= strtoupper($w[0]);
            if (strlen($initials) >= 2) break;
        }
    }
    return !empty($initials) ? $initials : 'SM';
}
?>

<style>
.avatar-circle {
    width: 42px;
    height: 42px;
    background: linear-gradient(135deg, #0d6efd, #0dcaf0);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 14px;
    flex-shrink: 0;
}
@media print {
    body * {
        visibility: hidden !important;
    }
    #printableLedgerContent, #printableLedgerContent * {
        visibility: visible !important;
    }
    #printableLedgerContent {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 100% !important;
        background: #fff !important;
    }
    .modal-footer, .btn-close {
        display: none !important;
    }
}
</style>

    <!-- Main Content Area -->
    <div id="main-content">

        <!-- Notification Alerts -->
        <?php if (isset($_GET['client_added'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
                <div>
                    <strong>Client Registered!</strong> Wholesale client <strong><?php echo htmlspecialchars($_GET['client_added']); ?></strong> has been successfully added.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['payment_received'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-hand-holding-dollar fs-4 me-3 text-success"></i>
                <div>
                    <strong>Payment Recorded!</strong> Amount of <strong>PKR <?php echo number_format((float)$_GET['amount']); ?></strong> has been credited to the client's Khata ledger.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['client_deleted'])): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-trash-can fs-4 me-3 text-danger"></i>
                <div>
                    <strong>Client Removed!</strong> Client account and ledger have been deleted.
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
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-book-bookmark text-primary me-2"></i>Wholesale Clients & Khata Ledger</h5>
                <small class="text-muted">Manage credit limits, outstanding balances, and client ledger statements</small>
            </div>
            
            <div class="d-flex gap-2">
                <a href="ledgers.php" class="btn text-white shadow-sm" style="background-color: #6f42c1; border-color: #6f42c1;">
                    <i class="fa-solid fa-book-bookmark me-1"></i> All Khatas & Vendors
                </a>
                <button class="btn btn-outline-success shadow-sm" data-bs-toggle="modal" data-bs-target="#receivePaymentModal">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receive Payment
                </button>
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addClientModal">
                    <i class="fa-solid fa-user-plus me-1"></i> Add New Client
                </button>
            </div>
        </header>

        <!-- Dynamic Metric Stat Cards -->
        <div class="row g-3 mb-4">
            <!-- 1. Total Active Clients -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-primary text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Total Active Clients</small>
                            <h3 class="fw-bold mb-0 mt-1"><?php echo $totalClients; ?> Shopkeepers</h3>
                            <small class="text-white-50">Wholesale Accounts</small>
                        </div>
                        <i class="fa-solid fa-users-gear fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- 2. Total Receivables (Udhaar) -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-danger text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Total Receivables (Udhaar)</small>
                            <h3 class="fw-bold mb-0 mt-1">PKR <?php echo number_format($totalReceivables); ?></h3>
                            <small class="text-white-50">Pending Market Khata</small>
                        </div>
                        <i class="fa-solid fa-arrow-trend-up fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- 3. Received Payments -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-success text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Payments Received</small>
                            <h3 class="fw-bold mb-0 mt-1">PKR <?php echo number_format($totalReceived); ?></h3>
                            <small class="text-white-50">Settled Khata Amount</small>
                        </div>
                        <i class="fa-solid fa-circle-check fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- 4. Overdue / High Balance Alerts -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-dark text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Payment Due Accounts</small>
                            <h3 class="fw-bold mb-0 mt-1"><?php echo $overdueCount; ?> Clients</h3>
                            <small class="text-white-50">&ge; 70% of Credit Limit</small>
                        </div>
                        <i class="fa-solid fa-bell fa-2x opacity-50 text-warning"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Wholesale Clients List Table Card -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="clientSearch" class="form-control bg-light border-start-0" placeholder="Search by shop name, proprietor, or phone...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select id="accountFilter" class="form-select bg-light">
                            <option value="">All Accounts (<?php echo $totalClients; ?>)</option>
                            <option value="pending">With Pending Udhaar (Balance &gt; 0)</option>
                            <option value="clear">Clear Accounts (0 Balance)</option>
                            <option value="overdue">High Balance / Payment Due</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-end">
                        <button class="btn btn-outline-secondary w-100" onclick="window.print()">
                            <i class="fa-solid fa-file-invoice me-1"></i> Print Directory
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="clientsTable">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th>Client / Shop Details</th>
                                <th>Contact Number</th>
                                <th>City / Area</th>
                                <th>Credit Limit</th>
                                <th>Current Balance (Udhaar)</th>
                                <th>Status</th>
                                <th class="text-center" style="width: 210px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clients)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-users-slash fa-3x mb-3 text-secondary opacity-50"></i>
                                        <p class="mb-2 fs-6">No wholesale clients registered yet.</p>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addClientModal">
                                            <i class="fa-solid fa-user-plus me-1"></i> Register Your First Client
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($clients as $cid => $client): 
                                    $bal   = (float)($client['balance'] ?? 0);
                                    $limit = (float)($client['limit'] ?? 100000);
                                    $init  = get_initials($client['name']);

                                    // Determine status tag
                                    if ($bal <= 0) {
                                        $statusClass = 'clear';
                                        $statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i>Clear</span>';
                                        $balDisplay  = '<span class="fw-bold text-success">PKR 0</span>';
                                    } elseif ($limit > 0 && $bal > $limit) {
                                        $statusClass = 'overdue';
                                        $statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-circle-exclamation me-1"></i>Limit Exceeded</span>';
                                        $balDisplay  = '<span class="fw-bold text-danger">PKR ' . number_format($bal) . '</span>';
                                    } elseif ($limit > 0 && $bal >= ($limit * 0.7)) {
                                        $statusClass = 'overdue';
                                        $statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-clock-rotate-left me-1"></i>Payment Due</span>';
                                        $balDisplay  = '<span class="fw-bold text-danger">PKR ' . number_format($bal) . '</span>';
                                    } else {
                                        $statusClass = 'pending';
                                        $statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fa-solid fa-arrows-rotate me-1"></i>Active</span>';
                                        $balDisplay  = '<span class="fw-bold text-dark">PKR ' . number_format($bal) . '</span>';
                                    }
                                ?>
                                    <tr class="client-row"
                                        data-id="<?php echo htmlspecialchars($cid); ?>"
                                        data-name="<?php echo htmlspecialchars(strtolower($client['name'])); ?>"
                                        data-owner="<?php echo htmlspecialchars(strtolower($client['owner'] ?? '')); ?>"
                                        data-phone="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>"
                                        data-city="<?php echo htmlspecialchars(strtolower($client['city'] ?? '')); ?>"
                                        data-status="<?php echo $statusClass; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-3"><?php echo $init; ?></div>
                                                <div>
                                                    <div class="fw-bold text-dark"><?php echo htmlspecialchars($client['name']); ?></div>
                                                    <small class="text-muted">Proprietor: <?php echo htmlspecialchars($client['owner'] ?? $client['name']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($client['phone'])): ?>
                                                <a href="tel:<?php echo htmlspecialchars($client['phone']); ?>" class="text-decoration-none text-dark">
                                                    <i class="fa-solid fa-phone text-muted me-1"></i> <?php echo htmlspecialchars($client['phone']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <i class="fa-solid fa-location-dot text-muted me-1"></i> <?php echo htmlspecialchars($client['city'] ?? 'Local'); ?>
                                        </td>
                                        <td>PKR <?php echo number_format($limit); ?></td>
                                        <td><?php echo $balDisplay; ?></td>
                                        <td><?php echo $statusBadge; ?></td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <!-- View Ledger Modal -->
                                                <button class="btn btn-outline-primary view-ledger-btn" 
                                                        data-id="<?php echo htmlspecialchars($cid); ?>"
                                                        data-name="<?php echo htmlspecialchars($client['name']); ?>"
                                                        title="View Statement & Khata Ledger">
                                                    <i class="fa-solid fa-book me-1"></i> Ledger
                                                </button>
                                                <!-- Receive Cash Payment -->
                                                <button class="btn btn-outline-success pay-client-btn" 
                                                        data-id="<?php echo htmlspecialchars($cid); ?>"
                                                        data-name="<?php echo htmlspecialchars($client['name']); ?>"
                                                        data-balance="<?php echo $bal; ?>"
                                                        title="Receive Payment from Client">
                                                    <i class="fa-solid fa-hand-holding-dollar"></i> Pay
                                                </button>
                                                <!-- Start Counter Sale in POS -->
                                                <a href="pos.php?client_id=<?php echo urlencode($cid); ?>" 
                                                   class="btn btn-outline-secondary" 
                                                   title="Start Counter Sale with Wholesale Pricing">
                                                    <i class="fa-solid fa-cash-register"></i>
                                                </a>
                                                <!-- Delete Client -->
                                                <button class="btn btn-outline-danger delete-client-btn"
                                                        data-id="<?php echo htmlspecialchars($cid); ?>"
                                                        data-name="<?php echo htmlspecialchars($client['name']); ?>"
                                                        title="Delete Client">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table Footer -->
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                <small class="text-muted" id="clientsTableSummary">Showing <?php echo $totalClients; ?> registered wholesale clients</small>
                <div class="small">
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle me-1"><?php echo $overdueCount; ?> Due</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle"><?php echo $totalClients - $overdueCount; ?> Regular</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Add New Wholesale Client -->
    <div class="modal fade" id="addClientModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-user-plus me-2 text-primary"></i>Register Wholesale Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="clients.php" method="POST">
                    <input type="hidden" name="action" value="add_client">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Shop / Business Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Lahore Electronics" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Proprietor / Owner Name</label>
                                <input type="text" name="owner" class="form-control" placeholder="e.g. Muhammad Ahmad">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contact Phone Number</label>
                                <input type="text" name="phone" class="form-control" placeholder="0300-1234567">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">City / Market Location</label>
                                <input type="text" name="city" class="form-control" placeholder="e.g. Hall Road, Lahore">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Credit Limit (Udhaar Limit PKR)</label>
                                <input type="number" name="limit" class="form-control" value="500000" step="1000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Opening Balance (Initial Udhaar)</label>
                                <input type="number" name="balance" class="form-control" placeholder="0" step="10">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-save me-1"></i> Save Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Receive Payment (Cash / Bank Entry) -->
    <div class="modal fade" id="receivePaymentModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-hand-holding-dollar me-2 text-success"></i>Receive Payment (Khata Entry)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="clients.php" method="POST">
                    <input type="hidden" name="action" value="receive_payment">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Select Wholesale Client <span class="text-danger">*</span></label>
                                <select name="client_id" id="receiveClientIdSelect" class="form-select" required>
                                    <?php foreach ($clients as $cid => $c): ?>
                                        <option value="<?php echo htmlspecialchars($cid); ?>">
                                            <?php echo htmlspecialchars($c['name']); ?> (Due: PKR <?php echo number_format($c['balance'] ?? 0); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Amount Received (PKR) <span class="text-danger">*</span></label>
                                <input type="number" name="amount" id="receiveAmountInput" class="form-control fw-bold text-success" placeholder="0.00" min="1" step="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Payment Mode</label>
                                <select name="mode" class="form-select">
                                    <option value="Cash" selected>Cash Payment</option>
                                    <option value="Bank Transfer">Bank Transfer / Online</option>
                                    <option value="EasyPaisa">EasyPaisa</option>
                                    <option value="JazzCash">JazzCash</option>
                                    <option value="Cheque">Bank Cheque</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Slip # / Remarks (Optional)</label>
                                <input type="text" name="remarks" class="form-control" placeholder="e.g. Received at shop by Kashif / HBL Trx #">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold"><i class="fa-solid fa-check me-1"></i> Save Transaction</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: View Detailed Client Ledger (Statement) -->
    <div class="modal fade" id="ledgerViewModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-dark" id="ledgerModalTitle">Client Transaction Statement</h5>
                        <small class="text-muted" id="ledgerModalSubtitle">Proprietor Details</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-0" id="printableLedgerContent">
                    <!-- Client Ledger Summary Cards inside modal -->
                    <div class="p-3 bg-light border-bottom">
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border shadow-none">
                                    <small class="text-muted d-block">Total Debited (Bought)</small>
                                    <strong class="text-dark fs-6" id="ledgerTotalDebit">PKR 0</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border shadow-none">
                                    <small class="text-muted d-block">Total Credited (Paid)</small>
                                    <strong class="text-success fs-6" id="ledgerTotalCredit">PKR 0</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border shadow-none">
                                    <small class="text-muted d-block">Current Due Udhaar</small>
                                    <strong class="text-danger fs-6" id="ledgerCurrentBalance">PKR 0</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ledger Transactions Table -->
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="table-dark small text-uppercase">
                                <tr>
                                    <th style="width: 18%;">Date</th>
                                    <th style="width: 42%;">Description / Reference</th>
                                    <th class="text-end" style="width: 14%;">Debit (Bought)</th>
                                    <th class="text-end" style="width: 14%;">Credit (Paid)</th>
                                    <th class="text-end" style="width: 12%;">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="ledgerTableBody">
                                <tr><td colspan="5" class="text-center py-4 text-muted">Loading ledger entries...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                            <i class="fa-solid fa-print me-1"></i> Print Statement
                        </button>
                        <button class="btn btn-success btn-sm ms-2" id="ledgerReceivePayBtn">
                            <i class="fa-solid fa-hand-holding-dollar me-1"></i> Pay This Client
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Delete Client Confirmation -->
    <div class="modal fade" id="deleteClientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-trash-can me-2"></i>Delete Wholesale Client</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="clients.php" method="POST">
                    <input type="hidden" name="action" value="delete_client">
                    <input type="hidden" name="client_id" id="deleteClientId">
                    <div class="modal-body p-4 text-center">
                        <i class="fa-solid fa-triangle-exclamation text-danger fa-3x mb-3"></i>
                        <h5 class="fw-bold mb-2">Delete <span id="deleteClientName" class="text-danger"></span>?</h5>
                        <p class="text-muted mb-0">Are you sure you want to remove this client from your directory? Their ledger transactions will also be purged.</p>
                    </div>
                    <div class="modal-footer bg-light justify-content-center">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4 fw-bold"><i class="fa-solid fa-trash me-1"></i> Yes, Delete Client</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Client-side Interactive Search, Filter & Ledger Population Scripts -->
    <script>
    let activeClientIdForLedger = '';

    document.addEventListener('DOMContentLoaded', function() {
        const ledgerModal = new bootstrap.Modal(document.getElementById('ledgerViewModal'));
        const payModal    = new bootstrap.Modal(document.getElementById('receivePaymentModal'));
        const delModal    = new bootstrap.Modal(document.getElementById('deleteClientModal'));

        // View Ledger Button Click
        document.querySelectorAll('.view-ledger-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const clientId = this.getAttribute('data-id');
                const clientName = this.getAttribute('data-name');
                openClientLedger(clientId, clientName);
            });
        });

        // Pay Button Click
        document.querySelectorAll('.pay-client-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const clientId = this.getAttribute('data-id');
                const select = document.getElementById('receiveClientIdSelect');
                if (select) select.value = clientId;
                payModal.show();
            });
        });

        // Delete Button Click
        document.querySelectorAll('.delete-client-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const clientId = this.getAttribute('data-id');
                const clientName = this.getAttribute('data-name');
                document.getElementById('deleteClientId').value = clientId;
                document.getElementById('deleteClientName').textContent = clientName;
                delModal.show();
            });
        });

        // Ledger modal "Pay This Client" button
        document.getElementById('ledgerReceivePayBtn').addEventListener('click', function() {
            ledgerModal.hide();
            if (activeClientIdForLedger) {
                document.getElementById('receiveClientIdSelect').value = activeClientIdForLedger;
            }
            payModal.show();
        });

        // Search & Filter Logic
        const searchInput = document.getElementById('clientSearch');
        const accountFilter = document.getElementById('accountFilter');
        const rows = document.querySelectorAll('.client-row');
        const summaryText = document.getElementById('clientsTableSummary');

        function filterClients() {
            const query = (searchInput.value || '').toLowerCase().trim();
            const filterVal = (accountFilter.value || '').trim();

            let count = 0;
            rows.forEach(row => {
                const name  = (row.getAttribute('data-name') || '');
                const owner = (row.getAttribute('data-owner') || '');
                const phone = (row.getAttribute('data-phone') || '').toLowerCase();
                const city  = (row.getAttribute('data-city') || '');
                const status = (row.getAttribute('data-status') || '');

                const matchQuery = !query || name.includes(query) || owner.includes(query) || phone.includes(query) || city.includes(query);
                let matchStatus = true;

                if (filterVal === 'clear') {
                    matchStatus = (status === 'clear');
                } else if (filterVal === 'pending') {
                    matchStatus = (status === 'pending' || status === 'overdue');
                } else if (filterVal === 'overdue') {
                    matchStatus = (status === 'overdue');
                }

                if (matchQuery && matchStatus) {
                    row.style.display = '';
                    count++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (summaryText) {
                summaryText.textContent = 'Showing ' + count + ' of ' + rows.length + ' registered wholesale clients';
            }
        }

        if (searchInput) searchInput.addEventListener('input', filterClients);
        if (accountFilter) accountFilter.addEventListener('change', filterClients);
    });

    // Fetch and render client ledger statement
    async function openClientLedger(clientId, clientName) {
        activeClientIdForLedger = clientId;
        const modalEl = document.getElementById('ledgerViewModal');
        const ledgerModal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);

        document.getElementById('ledgerModalTitle').textContent = clientName + ' — Statement';
        document.getElementById('ledgerModalSubtitle').textContent = 'Loading account statement...';
        document.getElementById('ledgerTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading ledger entries...</td></tr>';
        
        ledgerModal.show();

        try {
            const res = await fetch('ajax_client_ledger.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_ledger', client_id: clientId })
            });
            const data = await res.json();

            if (data.success) {
                const c = data.client;
                document.getElementById('ledgerModalSubtitle').textContent = 'Proprietor: ' + (c.owner || c.name) + ' | ' + (c.city || 'Local') + ' | Phone: ' + (c.phone || 'No Phone');
                document.getElementById('ledgerTotalDebit').textContent = 'PKR ' + Number(data.total_debit).toLocaleString();
                document.getElementById('ledgerTotalCredit').textContent = 'PKR ' + Number(data.total_credit).toLocaleString();
                document.getElementById('ledgerCurrentBalance').textContent = 'PKR ' + Number(data.balance).toLocaleString();

                if (!data.ledger || data.ledger.length === 0) {
                    document.getElementById('ledgerTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No transactions recorded in this ledger yet.</td></tr>';
                    return;
                }

                let html = '';
                data.ledger.forEach(tx => {
                    const debitText = (tx.debit > 0) ? 'PKR ' + Number(tx.debit).toLocaleString() : '-';
                    const creditText = (tx.credit > 0) ? '<span class="text-success fw-bold">PKR ' + Number(tx.credit).toLocaleString() + '</span>' : '-';
                    const balText = 'PKR ' + Number(tx.balance).toLocaleString();

                    html += `
                        <tr>
                            <td><small class="text-muted">${(tx.date || '').split(' ')[0]}</small></td>
                            <td>
                                <div>${tx.description}</div>
                                ${tx.ref ? '<small class="text-muted">Ref: <code>' + tx.ref + '</code></small>' : ''}
                            </td>
                            <td class="text-end fw-semibold">${debitText}</td>
                            <td class="text-end">${creditText}</td>
                            <td class="text-end fw-bold text-danger">${balText}</td>
                        </tr>
                    `;
                });
                document.getElementById('ledgerTableBody').innerHTML = html;
            } else {
                document.getElementById('ledgerTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Failed to load statement: ' + (data.message || 'Error') + '</td></tr>';
            }
        } catch (err) {
            document.getElementById('ledgerTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Network Error: Could not connect to ledger service.</td></tr>';
        }
    }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
