<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/store_data.php';

// Handle Form Submissions (Fallback & Direct POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Create Ledger Account (Client or Vendor)
    if ($action === 'create_ledger') {
        $type = strtolower(trim($_POST['account_type'] ?? 'client'));
        $name = trim($_POST['name'] ?? '');

        if ($type === 'vendor') {
            $res = add_vendor([
                'name'     => $name,
                'contact'  => trim($_POST['contact'] ?? $name),
                'phone'    => trim($_POST['phone'] ?? ''),
                'city'     => trim($_POST['city'] ?? ''),
                'category' => trim($_POST['category'] ?? 'Mobile Parts & Accessories'),
                'balance'  => max(0.0, (float)($_POST['balance'] ?? 0)),
                'limit'    => max(0.0, (float)($_POST['limit'] ?? 500000))
            ]);
            if ($res['success']) {
                header("Location: ledgers.php?added_vendor=" . urlencode($res['vendor']['name']));
                exit;
            } else {
                header("Location: ledgers.php?err=" . urlencode($res['message']));
                exit;
            }
        } else {
            $res = add_client([
                'name'    => $name,
                'owner'   => trim($_POST['contact'] ?? $name),
                'phone'   => trim($_POST['phone'] ?? ''),
                'city'    => trim($_POST['city'] ?? ''),
                'type'    => 'wholesale',
                'balance' => max(0.0, (float)($_POST['balance'] ?? 0)),
                'limit'   => max(0.0, (float)($_POST['limit'] ?? 200000))
            ]);
            if ($res['success']) {
                header("Location: ledgers.php?added_client=" . urlencode($res['client']['name']));
                exit;
            } else {
                header("Location: ledgers.php?err=" . urlencode($res['message']));
                exit;
            }
        }
    }

    // 2. Direct Add Vendor
    if ($action === 'add_vendor') {
        $res = add_vendor([
            'name'     => trim($_POST['name'] ?? ''),
            'contact'  => trim($_POST['contact'] ?? ''),
            'phone'    => trim($_POST['phone'] ?? ''),
            'city'     => trim($_POST['city'] ?? ''),
            'category' => trim($_POST['category'] ?? 'Mobile Parts & Accessories'),
            'balance'  => max(0.0, (float)($_POST['balance'] ?? 0)),
            'limit'    => max(0.0, (float)($_POST['limit'] ?? 500000))
        ]);
        if ($res['success']) {
            header("Location: ledgers.php?added_vendor=" . urlencode($res['vendor']['name']));
            exit;
        } else {
            header("Location: ledgers.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 3. Record Transaction
    if ($action === 'record_tx') {
        $accountVal = trim($_POST['account_id'] ?? '');
        $parts = explode(':', $accountVal);
        $type = $parts[0] ?? 'client';
        $id   = $parts[1] ?? '';
        $txType = trim($_POST['tx_type'] ?? 'payment');
        $amount = max(0.0, (float)($_POST['amount'] ?? 0));
        $desc = trim($_POST['description'] ?? '');
        $ref  = trim($_POST['ref'] ?? '');

        if ($type === 'vendor') {
            $actualType = ($txType === 'payment') ? 'debit' : 'credit';
            $res = record_vendor_transaction($id, $actualType, $amount, $desc, $ref);
        } else {
            $actualType = ($txType === 'payment') ? 'credit' : 'debit';
            $res = record_client_transaction($id, $actualType, $amount, $desc, $ref);
        }

        if ($res['success']) {
            header("Location: ledgers.php?tx_recorded=1&amount={$amount}");
            exit;
        } else {
            header("Location: ledgers.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 4. Delete Account
    if ($action === 'delete_account') {
        $type = strtolower(trim($_POST['type'] ?? 'client'));
        $id   = trim($_POST['id'] ?? '');
        if ($type === 'vendor') {
            delete_vendor($id);
        } else {
            delete_client($id);
        }
        header("Location: ledgers.php?deleted=1");
        exit;
    }
}

$clients = get_clients_list();
$vendors = get_vendors_list();

// Calculate Totals
$totalReceivables = 0.0;
foreach ($clients as $c) {
    $totalReceivables += (float)($c['balance'] ?? 0);
}

$totalPayables = 0.0;
foreach ($vendors as $v) {
    $totalPayables += (float)($v['balance'] ?? 0);
}

$netMarketPosition = $totalReceivables - $totalPayables;
$totalAccounts = count($clients) + count($vendors);

$pageTitle = 'Smart Mobile - Khata Ledgers & Accounts';
$activePage = 'ledgers';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Helper for initials avatar
function get_account_initials(string $name): string {
    $words = explode(' ', trim($name));
    $res = '';
    foreach ($words as $w) {
        if (!empty($w)) {
            $res .= strtoupper($w[0]);
            if (strlen($res) >= 2) break;
        }
    }
    return !empty($res) ? $res : 'AC';
}
?>

<style>
.avatar-client {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #0d6efd, #0dcaf0);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 13px;
    flex-shrink: 0;
}
.avatar-vendor {
    width: 40px;
    height: 40px;
    background: linear-gradient(135deg, #6f42c1, #d63384);
    color: #fff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    font-size: 13px;
    flex-shrink: 0;
}
@media print {
    body * {
        visibility: hidden !important;
    }
    #printableStatementArea, #printableStatementArea * {
        visibility: visible !important;
    }
    #printableStatementArea {
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
        <?php if (isset($_GET['added_client'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
                <div>
                    <strong>Wholesale Client Added!</strong> Khata ledger for <strong><?php echo htmlspecialchars($_GET['added_client']); ?></strong> has been created.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['added_vendor'])): ?>
            <div class="alert alert-primary alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-circle-check fs-4 me-3 text-primary"></i>
                <div>
                    <strong>Vendor / Supplier Added!</strong> Supplier Khata ledger for <strong><?php echo htmlspecialchars($_GET['added_vendor']); ?></strong> has been registered.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['tx_recorded'])): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-file-invoice-dollar fs-4 me-3 text-info"></i>
                <div>
                    <strong>Transaction Saved!</strong> Amount of <strong>PKR <?php echo number_format((float)$_GET['amount']); ?></strong> has been updated in the ledger statement.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-trash-can fs-4 me-3 text-danger"></i>
                <div>
                    <strong>Account Removed!</strong> Ledger and transactions have been removed from the system.
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
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-book-bookmark text-primary me-2"></i>Khata Ledgers & Accounts Directory</h5>
                <small class="text-muted">Manage Wholesale Clients (Market Receivables) and Supplier Vendors (Payables) Statements</small>
            </div>
            
            <div class="d-flex gap-2 flex-wrap">
                <!-- Create New Ledger Modal Button -->
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#createLedgerModal">
                    <i class="fa-solid fa-plus-circle me-1"></i> Create New Ledger
                </button>
                <!-- Quick Add Vendor Button -->
                <button class="btn btn-outline-purple shadow-sm btn-dark" data-bs-toggle="modal" data-bs-target="#quickAddVendorModal" style="background-color: #6f42c1; border-color: #6f42c1;">
                    <i class="fa-solid fa-truck-field me-1"></i> + Add Vendor
                </button>
                <!-- Quick Add Client Button -->
                <button class="btn btn-outline-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#quickAddClientModal">
                    <i class="fa-solid fa-user-plus me-1"></i> + Add Client
                </button>
                <!-- Record Entry -->
                <button class="btn btn-outline-success shadow-sm" data-bs-toggle="modal" data-bs-target="#recordTxModal">
                    <i class="fa-solid fa-receipt me-1"></i> New Entry
                </button>
            </div>
        </header>

        <!-- Metric Stat Cards (Live PHP Data) -->
        <div class="row g-3 mb-4">
            <!-- 1. Total Receivables (Clients Lena Hai) -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-danger text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Receivables (Lena Hai)</small>
                            <h3 class="fw-bold mb-0 mt-1">PKR <?php echo number_format($totalReceivables); ?></h3>
                            <small class="text-white-50">Wholesale Clients Udhaar</small>
                        </div>
                        <i class="fa-solid fa-hand-holding-dollar fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- 2. Total Payables (Vendors Dena Hai) -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm text-white p-3 border-0 h-100" style="background-color: #6f42c1;">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Payables (Dena Hai)</small>
                            <h3 class="fw-bold mb-0 mt-1">PKR <?php echo number_format($totalPayables); ?></h3>
                            <small class="text-white-50">Supplier / Vendor Khata</small>
                        </div>
                        <i class="fa-solid fa-truck-moving fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- 3. Net Market Position -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm <?php echo ($netMarketPosition >= 0) ? 'bg-success' : 'bg-warning text-dark'; ?> text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="<?php echo ($netMarketPosition >= 0) ? 'text-white-50' : 'text-dark-50'; ?> text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Net Market Balance</small>
                            <h3 class="fw-bold mb-0 mt-1">PKR <?php echo number_format(abs($netMarketPosition)); ?></h3>
                            <small class="<?php echo ($netMarketPosition >= 0) ? 'text-white-50' : 'text-muted'; ?>">
                                <?php echo ($netMarketPosition >= 0) ? 'Net Market Surplus (+)' : 'Net Liability (-)'; ?>
                            </small>
                        </div>
                        <i class="fa-solid fa-scale-balanced fa-2x opacity-50 <?php echo ($netMarketPosition >= 0) ? '' : 'text-dark'; ?>"></i>
                    </div>
                </div>
            </div>

            <!-- 4. Total Registered Ledgers -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-dark text-white p-3 border-0 h-100">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Total Active Khatas</small>
                            <h3 class="fw-bold mb-0 mt-1"><?php echo $totalAccounts; ?> Accounts</h3>
                            <small class="text-white-50"><?php echo count($clients); ?> Clients &bull; <?php echo count($vendors); ?> Vendors</small>
                        </div>
                        <i class="fa-solid fa-book-open fa-2x opacity-50 text-primary"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs & Account Directory Card -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3">
                <!-- Navigation Tabs -->
                <ul class="nav nav-pills mb-3 border-bottom pb-2" id="ledgerTypeTabs">
                    <li class="nav-item">
                        <button class="nav-link active rounded-pill px-4 tab-filter" data-type="all">
                            <i class="fa-solid fa-list me-1"></i> All Ledgers (<?php echo $totalAccounts; ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link rounded-pill px-4 tab-filter" data-type="client">
                            <i class="fa-solid fa-users me-1"></i> Wholesale Clients (<?php echo count($clients); ?>)
                        </button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link rounded-pill px-4 tab-filter" data-type="vendor">
                            <i class="fa-solid fa-truck-field me-1"></i> Suppliers & Vendors (<?php echo count($vendors); ?>)
                        </button>
                    </li>
                </ul>

                <!-- Filter & Search Bar -->
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="ledgerSearch" class="form-control bg-light border-start-0" placeholder="Search account, vendor, client, phone, or market...">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select id="balanceStatusFilter" class="form-select bg-light">
                            <option value="">All Balances</option>
                            <option value="pending">With Pending Balance (Khata &gt; 0)</option>
                            <option value="clear">Clear Accounts (0 Balance)</option>
                        </select>
                    </div>
                    <div class="col-md-3 text-end">
                        <button class="btn btn-outline-secondary w-100" onclick="window.print()">
                            <i class="fa-solid fa-print me-1"></i> Print Directory
                        </button>
                    </div>
                </div>
            </div>

            <!-- Table of All Accounts -->
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="ledgersTable">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th>Account & Type</th>
                                <th>Contact Person</th>
                                <th>Phone Number</th>
                                <th>Market / City</th>
                                <th>Balance Status</th>
                                <th>Outstanding Amount</th>
                                <th class="text-center" style="width: 220px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <!-- Loop Over Clients -->
                            <?php foreach ($clients as $cid => $c): 
                                $bal = (float)($c['balance'] ?? 0);
                                $init = get_account_initials($c['name']);
                                $statusKey = ($bal > 0) ? 'pending' : 'clear';
                            ?>
                                <tr class="ledger-row"
                                    data-type="client"
                                    data-id="<?php echo htmlspecialchars($cid); ?>"
                                    data-name="<?php echo htmlspecialchars(strtolower($c['name'])); ?>"
                                    data-contact="<?php echo htmlspecialchars(strtolower($c['owner'] ?? '')); ?>"
                                    data-phone="<?php echo htmlspecialchars($c['phone'] ?? ''); ?>"
                                    data-city="<?php echo htmlspecialchars(strtolower($c['city'] ?? '')); ?>"
                                    data-status="<?php echo $statusKey; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-client me-3"><?php echo $init; ?></div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($c['name']); ?></div>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle" style="font-size: 11px;">
                                                    <i class="fa-solid fa-user me-1"></i> Wholesale Client
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($c['owner'] ?? $c['name']); ?></td>
                                    <td>
                                        <?php if (!empty($c['phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($c['phone']); ?>" class="text-decoration-none text-dark">
                                                <i class="fa-solid fa-phone text-muted me-1"></i> <?php echo htmlspecialchars($c['phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><i class="fa-solid fa-location-dot text-muted me-1"></i> <?php echo htmlspecialchars($c['city'] ?? 'Local'); ?></td>
                                    <td>
                                        <?php if ($bal <= 0): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i>Clear (0)</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-arrow-trend-up me-1"></i>To Receive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($bal <= 0): ?>
                                            <span class="fw-bold text-success">PKR 0</span>
                                        <?php else: ?>
                                            <span class="fw-bold text-danger">PKR <?php echo number_format($bal); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- View Statement -->
                                            <button class="btn btn-outline-primary view-statement-btn"
                                                    data-type="client"
                                                    data-id="<?php echo htmlspecialchars($cid); ?>"
                                                    data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                    title="View Full Ledger Statement">
                                                <i class="fa-solid fa-book-open me-1"></i> Statement
                                            </button>
                                            <!-- Add Entry -->
                                            <button class="btn btn-outline-success record-tx-btn"
                                                    data-type="client"
                                                    data-id="<?php echo htmlspecialchars($cid); ?>"
                                                    data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                    title="Record Payment or Invoice">
                                                <i class="fa-solid fa-plus-minus"></i> Entry
                                            </button>
                                            <!-- Delete -->
                                            <button class="btn btn-outline-danger delete-account-btn"
                                                    data-type="client"
                                                    data-id="<?php echo htmlspecialchars($cid); ?>"
                                                    data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                                    title="Delete Account">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <!-- Loop Over Vendors -->
                            <?php foreach ($vendors as $vid => $v): 
                                $bal = (float)($v['balance'] ?? 0);
                                $init = get_account_initials($v['name']);
                                $statusKey = ($bal > 0) ? 'pending' : 'clear';
                            ?>
                                <tr class="ledger-row"
                                    data-type="vendor"
                                    data-id="<?php echo htmlspecialchars($vid); ?>"
                                    data-name="<?php echo htmlspecialchars(strtolower($v['name'])); ?>"
                                    data-contact="<?php echo htmlspecialchars(strtolower($v['contact'] ?? '')); ?>"
                                    data-phone="<?php echo htmlspecialchars($v['phone'] ?? ''); ?>"
                                    data-city="<?php echo htmlspecialchars(strtolower($v['city'] ?? '')); ?>"
                                    data-status="<?php echo $statusKey; ?>">
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-vendor me-3"><?php echo $init; ?></div>
                                            <div>
                                                <div class="fw-bold text-dark"><?php echo htmlspecialchars($v['name']); ?></div>
                                                <span class="badge text-white border" style="background-color: #6f42c1; font-size: 11px;">
                                                    <i class="fa-solid fa-truck-field me-1"></i> Supplier / Vendor
                                                </span>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($v['contact'] ?? $v['name']); ?></td>
                                    <td>
                                        <?php if (!empty($v['phone'])): ?>
                                            <a href="tel:<?php echo htmlspecialchars($v['phone']); ?>" class="text-decoration-none text-dark">
                                                <i class="fa-solid fa-phone text-muted me-1"></i> <?php echo htmlspecialchars($v['phone']); ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><i class="fa-solid fa-location-dot text-muted me-1"></i> <?php echo htmlspecialchars($v['city'] ?? 'Local'); ?></td>
                                    <td>
                                        <?php if ($bal <= 0): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i>Clear (0)</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-dark border border-warning-subtle"><i class="fa-solid fa-arrow-trend-down me-1"></i>To Pay</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($bal <= 0): ?>
                                            <span class="fw-bold text-success">PKR 0</span>
                                        <?php else: ?>
                                            <span class="fw-bold text-purple" style="color: #6f42c1;">PKR <?php echo number_format($bal); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- View Statement -->
                                            <button class="btn btn-outline-primary view-statement-btn"
                                                    data-type="vendor"
                                                    data-id="<?php echo htmlspecialchars($vid); ?>"
                                                    data-name="<?php echo htmlspecialchars($v['name']); ?>"
                                                    title="View Supplier Ledger Statement">
                                                <i class="fa-solid fa-book-open me-1"></i> Statement
                                            </button>
                                            <!-- Add Entry -->
                                            <button class="btn btn-outline-success record-tx-btn"
                                                    data-type="vendor"
                                                    data-id="<?php echo htmlspecialchars($vid); ?>"
                                                    data-name="<?php echo htmlspecialchars($v['name']); ?>"
                                                    title="Record Supplier Payment or Purchase">
                                                <i class="fa-solid fa-plus-minus"></i> Entry
                                            </button>
                                            <!-- Delete -->
                                            <button class="btn btn-outline-danger delete-account-btn"
                                                    data-type="vendor"
                                                    data-id="<?php echo htmlspecialchars($vid); ?>"
                                                    data-name="<?php echo htmlspecialchars($v['name']); ?>"
                                                    title="Delete Supplier">
                                                <i class="fa-solid fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Table Footer -->
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                <small class="text-muted" id="ledgersTableSummary">Showing <?php echo $totalAccounts; ?> accounts in Khata directory</small>
                <div>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1"><?php echo count($clients); ?> Clients</span>
                    <span class="badge text-white" style="background-color: #6f42c1;"><?php echo count($vendors); ?> Vendors</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Create New Ledger (Client or Vendor Account) -->
    <div class="modal fade" id="createLedgerModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-book-bookmark text-primary me-2"></i>Create New Khata Ledger Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="ledgers.php" method="POST">
                    <input type="hidden" name="action" value="create_ledger">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Account Type Selector -->
                            <div class="col-12">
                                <label class="form-label fw-bold">Select Account Category <span class="text-danger">*</span></label>
                                <div class="row g-2">
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="account_type" id="typeClient" value="client" checked>
                                        <label class="btn btn-outline-primary w-100 py-3 text-start" for="typeClient">
                                            <div class="fw-bold"><i class="fa-solid fa-user me-2"></i>Wholesale Client (Customer)</div>
                                            <small class="text-muted">Shopkeeper / Wholesaler (Receivables - Lena Hai)</small>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="radio" class="btn-check" name="account_type" id="typeVendor" value="vendor">
                                        <label class="btn btn-outline-purple w-100 py-3 text-start btn-outline-secondary" for="typeVendor">
                                            <div class="fw-bold"><i class="fa-solid fa-truck-field me-2"></i>Supplier / Vendor (Supplier)</div>
                                            <small class="text-muted">Importer / Goods Supplier (Payables - Dena Hai)</small>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Name & Contact -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Business / Shop / Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Shenzhen Display Importers / Malik Electronics" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Proprietor / Representative Name</label>
                                <input type="text" name="contact" class="form-control" placeholder="e.g. Asif Mehmood">
                            </div>

                            <!-- Phone & Location -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contact Phone / WhatsApp</label>
                                <input type="text" name="phone" class="form-control" placeholder="0300-1234567">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">City / Market Location</label>
                                <input type="text" name="city" class="form-control" placeholder="e.g. Hall Road, Lahore">
                            </div>

                            <!-- Category / Products -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Product Specialty / Category</label>
                                <input type="text" name="category" class="form-control" placeholder="e.g. Panels, Batteries, Fast Chargers, Repair Tools">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Credit Limit (PKR)</label>
                                <input type="number" name="limit" class="form-control" value="500000" step="1000">
                            </div>

                            <!-- Initial Opening Balance -->
                            <div class="col-12">
                                <label class="form-label fw-semibold">Initial Opening Balance (PKR)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">PKR</span>
                                    <input type="number" name="balance" class="form-control" placeholder="0.00" step="10">
                                </div>
                                <small class="text-muted">Purana pending udhaar (agar koi pehly se baaqi hisaab ho).</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-save me-1"></i> Create Ledger Account</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Quick Add Vendor Specifically -->
    <div class="modal fade" id="quickAddVendorModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header text-white" style="background-color: #6f42c1;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-truck-field me-2"></i>Register New Supplier / Vendor</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="ledgers.php" method="POST">
                    <input type="hidden" name="action" value="add_vendor">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Supplier / Company Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Master Panel Traders" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contact Person</label>
                                <input type="text" name="contact" class="form-control" placeholder="e.g. Mian Hafeez">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone Number</label>
                                <input type="text" name="phone" class="form-control" placeholder="0300-0000000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">City / Market</label>
                                <input type="text" name="city" class="form-control" placeholder="e.g. Hall Road, Lahore">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Supplies Category</label>
                                <input type="text" name="category" class="form-control" placeholder="Panels, ICs, Cables">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Opening Payable Balance (Hum ne dena hai PKR)</label>
                                <input type="number" name="balance" class="form-control" placeholder="0" step="10">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white px-4 fw-bold" style="background-color: #6f42c1;"><i class="fa-solid fa-save me-1"></i> Save Vendor</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Quick Add Client Specifically -->
    <div class="modal fade" id="quickAddClientModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2"></i>Register New Wholesale Client</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="ledgers.php" method="POST">
                    <input type="hidden" name="action" value="create_ledger">
                    <input type="hidden" name="account_type" value="client">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Shop / Business Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Usman Mobile Shop" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Owner Name</label>
                                <input type="text" name="contact" class="form-control" placeholder="e.g. Usman Ghani">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone Number</label>
                                <input type="text" name="phone" class="form-control" placeholder="0321-0000000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">City / Location</label>
                                <input type="text" name="city" class="form-control" placeholder="e.g. Saddar, Karachi">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Opening Udhaar Balance (PKR)</label>
                                <input type="number" name="balance" class="form-control" placeholder="0" step="10">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-save me-1"></i> Save Client Khata</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: View Statement & Detailed Ledger -->
    <div class="modal fade" id="ledgerStatementModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <div>
                        <div class="d-flex align-items-center gap-2">
                            <h5 class="modal-title fw-bold mb-0 text-dark" id="stmtAccountName">Account Statement</h5>
                            <span class="badge" id="stmtAccountBadge">Wholesale Client</span>
                        </div>
                        <small class="text-muted" id="stmtAccountSubtitle">Contact & City</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body p-0" id="printableStatementArea">
                    <!-- Statement Summary Ribbon inside modal -->
                    <div class="p-3 bg-light border-bottom">
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border">
                                    <small class="text-muted d-block" id="stmtLabelDebit">Total Debit (Purchases)</small>
                                    <strong class="text-dark fs-6" id="stmtTotalDebit">PKR 0</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border">
                                    <small class="text-muted d-block" id="stmtLabelCredit">Total Credit (Payments)</small>
                                    <strong class="text-success fs-6" id="stmtTotalCredit">PKR 0</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border">
                                    <small class="text-muted d-block" id="stmtLabelBalance">Current Net Balance</small>
                                    <strong class="fs-6" id="stmtNetBalance">PKR 0</strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Statement Table -->
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="table-dark small text-uppercase">
                                <tr>
                                    <th style="width: 18%;">Date</th>
                                    <th style="width: 42%;">Description / Reference</th>
                                    <th class="text-end" style="width: 14%;">Debit</th>
                                    <th class="text-end" style="width: 14%;">Credit</th>
                                    <th class="text-end" style="width: 12%;">Balance</th>
                                </tr>
                            </thead>
                            <tbody id="stmtTableBody">
                                <tr><td colspan="5" class="text-center py-4 text-muted">Loading statement...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="modal-footer justify-content-between bg-light">
                    <div>
                        <button class="btn btn-outline-secondary btn-sm" onclick="window.print()">
                            <i class="fa-solid fa-print me-1"></i> Print Statement
                        </button>
                        <button class="btn btn-success btn-sm ms-2" id="stmtAddEntryShortcutBtn">
                            <i class="fa-solid fa-plus-minus me-1"></i> Record Entry Here
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Record Transaction (Payment / Invoice / Khata Entry) -->
    <div class="modal fade" id="recordTxModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-receipt me-2 text-success"></i>Record Khata Transaction Entry</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="ledgers.php" method="POST">
                    <input type="hidden" name="action" value="record_tx">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Select Account (Client or Vendor) <span class="text-danger">*</span></label>
                                <select name="account_id" id="recordTxAccountSelect" class="form-select" required>
                                    <optgroup label="Wholesale Clients (Receivables)">
                                        <?php foreach ($clients as $cid => $c): ?>
                                            <option value="client:<?php echo htmlspecialchars($cid); ?>">
                                                Client: <?php echo htmlspecialchars($c['name']); ?> (Due: PKR <?php echo number_format($c['balance'] ?? 0); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <optgroup label="Suppliers & Vendors (Payables)">
                                        <?php foreach ($vendors as $vid => $v): ?>
                                            <option value="vendor:<?php echo htmlspecialchars($vid); ?>">
                                                Vendor: <?php echo htmlspecialchars($v['name']); ?> (Payable: PKR <?php echo number_format($v['balance'] ?? 0); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Transaction Type</label>
                                <select name="tx_type" id="recordTxTypeSelect" class="form-select" required>
                                    <option value="payment" selected>Payment (Cash / Bank Entry)</option>
                                    <option value="invoice">Invoice / Goods (Maal Khareeda / Diya)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Amount (PKR) <span class="text-danger">*</span></label>
                                <input type="number" name="amount" class="form-control fw-bold text-success" placeholder="0.00" min="1" step="1" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Description / Particulars</label>
                                <input type="text" name="description" class="form-control" placeholder="e.g. Cash payment received / Shipment delivery invoice">
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Slip # / Cheque # / Ref ID (Optional)</label>
                                <input type="text" name="ref" class="form-control" placeholder="e.g. RCPT-1029 / HBL-9812">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold"><i class="fa-solid fa-check me-1"></i> Save Entry</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Delete Account Confirmation -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-trash-can me-2"></i>Delete Khata Account</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="ledgers.php" method="POST">
                    <input type="hidden" name="action" value="delete_account">
                    <input type="hidden" name="type" id="delAccountType">
                    <input type="hidden" name="id" id="delAccountId">
                    <div class="modal-body p-4 text-center">
                        <i class="fa-solid fa-triangle-exclamation text-danger fa-3x mb-3"></i>
                        <h5 class="fw-bold mb-2">Delete <span id="delAccountName" class="text-danger"></span>?</h5>
                        <p class="text-muted mb-0">Are you sure you want to remove this ledger? All past statement transactions for this account will also be removed.</p>
                    </div>
                    <div class="modal-footer bg-light justify-content-center">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4 fw-bold"><i class="fa-solid fa-trash me-1"></i> Yes, Delete Ledger</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Interactive Client & Vendor Ledger JS Engine -->
    <script>
    let activeStatementTarget = { type: 'client', id: '' };

    document.addEventListener('DOMContentLoaded', function() {
        const stmtModal = new bootstrap.Modal(document.getElementById('ledgerStatementModal'));
        const txModal   = new bootstrap.Modal(document.getElementById('recordTxModal'));
        const delModal  = new bootstrap.Modal(document.getElementById('deleteAccountModal'));

        // View Statement Button Click
        document.querySelectorAll('.view-statement-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                const id   = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');
                openAccountStatement(type, id, name);
            });
        });

        // Record Entry Button Click
        document.querySelectorAll('.record-tx-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                const id   = this.getAttribute('data-id');
                const select = document.getElementById('recordTxAccountSelect');
                if (select) {
                    select.value = type + ':' + id;
                }
                txModal.show();
            });
        });

        // Delete Button Click
        document.querySelectorAll('.delete-account-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const type = this.getAttribute('data-type');
                const id   = this.getAttribute('data-id');
                const name = this.getAttribute('data-name');

                document.getElementById('delAccountType').value = type;
                document.getElementById('delAccountId').value = id;
                document.getElementById('delAccountName').textContent = name;
                delModal.show();
            });
        });

        // Statement Modal shortcut to add entry
        document.getElementById('stmtAddEntryShortcutBtn').addEventListener('click', function() {
            stmtModal.hide();
            if (activeStatementTarget.id) {
                document.getElementById('recordTxAccountSelect').value = activeStatementTarget.type + ':' + activeStatementTarget.id;
            }
            txModal.show();
        });

        // Tab Filter Logic (All vs Client vs Vendor)
        document.querySelectorAll('.tab-filter').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.tab-filter').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                filterLedgersTable();
            });
        });

        // Search & Status Filter
        const searchInput = document.getElementById('ledgerSearch');
        const statusFilter = document.getElementById('balanceStatusFilter');

        searchInput.addEventListener('input', filterLedgersTable);
        statusFilter.addEventListener('change', filterLedgersTable);
    });

    function filterLedgersTable() {
        const query = (document.getElementById('ledgerSearch').value || '').toLowerCase().trim();
        const statusVal = (document.getElementById('balanceStatusFilter').value || '').trim();
        const activeTab = document.querySelector('.tab-filter.active');
        const targetType = activeTab ? activeTab.getAttribute('data-type') : 'all';

        const rows = document.querySelectorAll('.ledger-row');
        let count = 0;

        rows.forEach(row => {
            const type    = row.getAttribute('data-type');
            const name    = (row.getAttribute('data-name') || '');
            const contact = (row.getAttribute('data-contact') || '');
            const phone   = (row.getAttribute('data-phone') || '').toLowerCase();
            const city    = (row.getAttribute('data-city') || '');
            const status  = row.getAttribute('data-status');

            const matchType   = (targetType === 'all' || type === targetType);
            const matchQuery  = !query || name.includes(query) || contact.includes(query) || phone.includes(query) || city.includes(query);
            const matchStatus = !statusVal || (status === statusVal);

            if (matchType && matchQuery && matchStatus) {
                row.style.display = '';
                count++;
            } else {
                row.style.display = 'none';
            }
        });

        const summary = document.getElementById('ledgersTableSummary');
        if (summary) {
            summary.textContent = 'Showing ' + count + ' of ' + rows.length + ' accounts in Khata directory';
        }
    }

    async function openAccountStatement(type, id, name) {
        activeStatementTarget = { type: type, id: id };
        const modalEl = document.getElementById('ledgerStatementModal');
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);

        const badge = document.getElementById('stmtAccountBadge');
        if (type === 'vendor') {
            badge.textContent = 'Supplier / Vendor';
            badge.style.backgroundColor = '#6f42c1';
            badge.className = 'badge text-white';
            document.getElementById('stmtLabelDebit').textContent = 'Total Payments Paid';
            document.getElementById('stmtLabelCredit').textContent = 'Total Supplies / Invoices';
            document.getElementById('stmtLabelBalance').textContent = 'Net Payable to Vendor';
        } else {
            badge.textContent = 'Wholesale Client';
            badge.style.backgroundColor = '#0d6efd';
            badge.className = 'badge text-white';
            document.getElementById('stmtLabelDebit').textContent = 'Total Purchases (Bought)';
            document.getElementById('stmtLabelCredit').textContent = 'Total Payments Received';
            document.getElementById('stmtLabelBalance').textContent = 'Net Due Udhaar (Receivable)';
        }

        document.getElementById('stmtAccountName').textContent = name;
        document.getElementById('stmtAccountSubtitle').textContent = 'Loading account statement...';
        document.getElementById('stmtTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading statement transactions...</td></tr>';

        modal.show();

        try {
            const res = await fetch('ajax_ledgers.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_statement', type: type, id: id })
            });
            const data = await res.json();

            if (data.success) {
                const acc = data.account;
                const contact = acc.contact || acc.owner || acc.name;
                document.getElementById('stmtAccountSubtitle').textContent = 'Contact: ' + contact + ' &bull; ' + (acc.city || 'Local') + ' &bull; Phone: ' + (acc.phone || 'No Phone');

                document.getElementById('stmtTotalDebit').textContent = 'PKR ' + Number(data.total_debit).toLocaleString();
                document.getElementById('stmtTotalCredit').textContent = 'PKR ' + Number(data.total_credit).toLocaleString();
                
                const balEl = document.getElementById('stmtNetBalance');
                balEl.textContent = 'PKR ' + Number(data.balance).toLocaleString();
                balEl.className = (data.balance > 0) ? ((type === 'vendor') ? 'fs-6 fw-bold text-purple' : 'fs-6 fw-bold text-danger') : 'fs-6 fw-bold text-success';
                if (type === 'vendor' && data.balance > 0) {
                    balEl.style.color = '#6f42c1';
                }

                if (!data.ledger || data.ledger.length === 0) {
                    document.getElementById('stmtTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-muted">No transactions recorded in this ledger yet.</td></tr>';
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
                            <td class="text-end fw-bold">${balText}</td>
                        </tr>
                    `;
                });
                document.getElementById('stmtTableBody').innerHTML = html;
            } else {
                document.getElementById('stmtTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Failed to load statement: ' + (data.message || 'Error') + '</td></tr>';
            }
        } catch (err) {
            document.getElementById('stmtTableBody').innerHTML = '<tr><td colspan="5" class="text-center py-4 text-danger">Network Error: Could not connect to ledger service.</td></tr>';
        }
    }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
