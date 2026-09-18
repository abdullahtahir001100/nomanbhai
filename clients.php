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
            'name'     => trim($_POST['name'] ?? ''),
            'owner'    => trim($_POST['owner'] ?? ''),
            'phone'    => trim($_POST['phone'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'city'     => trim($_POST['city'] ?? ''),
            'limit'    => max(0.0, (float)($_POST['limit'] ?? 500000)),
            'balance'  => max(0.0, (float)($_POST['balance'] ?? 0))
        ]);

        if ($res['success']) {
            header("Location: clients.php?client_added=" . urlencode($res['client']['name']));
            exit;
        } else {
            header("Location: clients.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 1.5 Edit Client
    if ($action === 'edit_client') {
        $clientId = trim($_POST['client_id'] ?? '');
        $res = edit_client($clientId, [
            'name'     => trim($_POST['name'] ?? ''),
            'owner'    => trim($_POST['owner'] ?? ''),
            'phone'    => trim($_POST['phone'] ?? ''),
            'password' => trim($_POST['password'] ?? ''),
            'city'     => trim($_POST['city'] ?? ''),
            'limit'    => max(0.0, (float)($_POST['limit'] ?? 500000))
        ]);

        if ($res['success']) {
            header("Location: clients.php?client_updated=" . urlencode($res['client']['name']));
            exit;
        } else {
            header("Location: clients.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 1.8 Create / Setup Client Login from Action Column
    if ($action === 'setup_client_login') {
        $clientId = trim($_POST['client_id'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $password = trim($_POST['password'] ?? '');

        if (empty($phone)) {
            header("Location: clients.php?err=" . urlencode("Client Mobile Number is required for Login Username"));
            exit;
        }
        if (empty($password)) {
            $password = '1234';
        }

        $res = edit_client($clientId, [
            'phone'    => $phone,
            'password' => $password
        ]);

        if ($res['success']) {
            header("Location: clients.php?login_created=1&client_name=" . urlencode($res['client']['name']) . "&phone=" . urlencode($phone) . "&client_id=" . urlencode($clientId));
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

        <?php if (isset($_GET['client_updated'])): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-circle-check fs-4 me-3 text-info"></i>
                <div>
                    <strong>Client Updated!</strong> Details and credentials for <strong><?php echo htmlspecialchars($_GET['client_updated']); ?></strong> have been saved.
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

        <?php if (isset($_GET['login_created'])): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert" style="background-color: #fff9e6; border-left: 5px solid #f59e0b !important;">
                <i class="fa-solid fa-key fs-3 me-3 text-warning"></i>
                <div class="flex-grow-1">
                    <strong class="text-dark">Wholesale Client Login Created & Active!</strong> Login credentials for <strong><?php echo htmlspecialchars($_GET['client_name'] ?? ''); ?></strong> are saved.<br>
                    <small class="text-muted">Username (Mobile): <strong class="text-primary font-monospace"><?php echo htmlspecialchars($_GET['phone'] ?? ''); ?></strong> &bull; Yeh client login kar ke apna <strong>Khaata (Ledger)</strong> aur <strong>Wholesale Rates</strong> dekh sakta hai.</small>
                </div>
                <a href="login.php?client=1&autoclient=<?php echo urlencode($_GET['client_id'] ?? ''); ?>" target="_blank" class="btn btn-sm btn-warning text-dark fw-bold ms-3 me-2">
                    <i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Test Client Screen Now
                </a>
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
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-users-gear text-primary me-2"></i>Wholesale Clients & Khata Ledger</h5>
                <small class="text-muted">Manage wholesale clients, credit limits, and client ledger statements</small>
            </div>
            
            <div class="d-flex flex-wrap gap-2">
                <a href="ledgers.php" class="btn text-white shadow-sm" style="background-color: #6f42c1; border-color: #6f42c1;">
                    <i class="fa-solid fa-book-bookmark me-1"></i> All Khatas & Vendors
                </a>
                <button class="btn btn-outline-success shadow-sm" data-bs-toggle="modal" data-bs-target="#receivePaymentModal">
                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Receive Payment
                </button>
                <button class="btn btn-primary shadow-sm fw-semibold" data-bs-toggle="modal" data-bs-target="#addClientModal">
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
                                <th>Client Details</th>
                                <th>Login Mobile (Username)</th>
                                <th>Portal Password</th>
                                <th>City / Area</th>
                                <th>Credit Limit</th>
                                <th>Khata Balance (Udhaar)</th>
                                <th>Status</th>
                                <th class="text-center" style="min-width: 290px;">Actions (لاگ ان / کھاتہ)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($clients)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
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
                                    $limit = (float)($client['limit'] ?? 500000);
                                    $init  = get_initials($client['name']);
                                    $phone = $client['phone'] ?? '';
                                    $pass  = $client['password'] ?? ($client['secret_pin'] ?? '1234');
                                    $code  = $client['client_code'] ?? strtoupper($cid);

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
                                        data-code="<?php echo htmlspecialchars($code); ?>"
                                        data-name="<?php echo htmlspecialchars(strtolower($client['name'])); ?>"
                                        data-owner="<?php echo htmlspecialchars(strtolower($client['owner'] ?? '')); ?>"
                                        data-phone="<?php echo htmlspecialchars($phone); ?>"
                                        data-password="<?php echo htmlspecialchars($pass); ?>"
                                        data-city="<?php echo htmlspecialchars(strtolower($client['city'] ?? '')); ?>"
                                        data-status="<?php echo $statusClass; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <div class="avatar-circle me-3"><?php echo $init; ?></div>
                                                <div>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span class="fw-bold text-dark"><?php echo htmlspecialchars($client['name']); ?></span>
                                                        <span class="badge bg-secondary-subtle text-secondary border py-0 px-1 font-monospace" style="font-size:0.72rem;"><?php echo htmlspecialchars($code); ?></span>
                                                    </div>
                                                    <small class="text-muted">Proprietor: <?php echo htmlspecialchars($client['owner'] ?? $client['name']); ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <?php if (!empty($phone)): ?>
                                                <div class="d-flex align-items-center gap-1">
                                                    <span class="font-monospace fw-bold text-primary" style="font-size:0.9rem;"><?php echo htmlspecialchars($phone); ?></span>
                                                    <button type="button" class="btn btn-link btn-sm text-secondary p-0 ms-1" onclick="copyText('<?php echo htmlspecialchars($phone); ?>', this)" title="Copy Mobile Number">
                                                        <i class="fa-regular fa-copy"></i>
                                                    </button>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-muted small">No Phone</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-1">
                                                <span class="badge bg-light text-dark border font-monospace px-2 py-1 pass-masked-display" id="passMask_<?php echo htmlspecialchars($cid); ?>">••••••</span>
                                                <span class="badge bg-warning-subtle text-dark border font-monospace px-2 py-1 pass-plain-display d-none" id="passPlain_<?php echo htmlspecialchars($cid); ?>"><?php echo htmlspecialchars($pass); ?></span>
                                                <button type="button" class="btn btn-link btn-sm text-secondary p-0 ms-1" onclick="togglePassRow('<?php echo htmlspecialchars($cid); ?>', this)" title="Show/Hide Password">
                                                    <i class="fa-regular fa-eye"></i>
                                                </button>
                                                <button type="button" class="btn btn-link btn-sm text-secondary p-0 ms-1" onclick="copyText('<?php echo htmlspecialchars($pass); ?>', this)" title="Copy Password">
                                                    <i class="fa-regular fa-copy"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td>
                                            <i class="fa-solid fa-location-dot text-muted me-1"></i> <?php echo htmlspecialchars($client['city'] ?? 'Local'); ?>
                                        </td>
                                        <td>PKR <?php echo number_format($limit); ?></td>
                                        <td><?php echo $balDisplay; ?></td>
                                        <td><?php echo $statusBadge; ?></td>
                                        <td class="text-center text-nowrap">
                                            <div class="d-inline-flex align-items-center gap-1">
                                                <!-- 1. Create / Setup Login Button -->
                                                <button type="button" 
                                                        class="btn btn-warning btn-sm text-dark fw-bold setup-client-login-btn shadow-sm px-2"
                                                        data-id="<?php echo htmlspecialchars($cid); ?>"
                                                        data-code="<?php echo htmlspecialchars($code); ?>"
                                                        data-name="<?php echo htmlspecialchars($client['name']); ?>"
                                                        data-owner="<?php echo htmlspecialchars($client['owner'] ?? ''); ?>"
                                                        data-phone="<?php echo htmlspecialchars($phone); ?>"
                                                        data-password="<?php echo htmlspecialchars($pass); ?>"
                                                        data-city="<?php echo htmlspecialchars($client['city'] ?? ''); ?>"
                                                        title="Create / Setup Wholesale Login for <?php echo htmlspecialchars($client['name']); ?>">
                                                    <i class="fa-solid fa-key me-1"></i> Create Login
                                                </button>

                                                <!-- 2. View Ledger Modal -->
                                                <button type="button" 
                                                        class="btn btn-outline-secondary btn-sm view-ledger-btn shadow-sm px-2" 
                                                        data-id="<?php echo htmlspecialchars($cid); ?>"
                                                        data-name="<?php echo htmlspecialchars($client['name']); ?>"
                                                        title="View Statement & Khata Ledger">
                                                    <i class="fa-solid fa-book me-1"></i> Ledger
                                                </button>

                                                <!-- 3. Receive Cash Payment -->
                                                <button type="button" 
                                                        class="btn btn-outline-success btn-sm pay-client-btn shadow-sm px-2" 
                                                        data-id="<?php echo htmlspecialchars($cid); ?>"
                                                        data-name="<?php echo htmlspecialchars($client['name']); ?>"
                                                        data-balance="<?php echo $bal; ?>"
                                                        title="Receive Payment from Client">
                                                    <i class="fa-solid fa-hand-holding-dollar me-1"></i> Pay
                                                </button>

                                                <!-- 4. More Options Dropdown -->
                                                <div class="dropdown d-inline-block">
                                                    <button class="btn btn-outline-dark btn-sm shadow-sm dropdown-toggle px-2" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="More Options">
                                                        <i class="fa-solid fa-ellipsis-vertical"></i>
                                                    </button>
                                                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 rounded-3">
                                                        <li>
                                                            <a class="dropdown-item py-2 edit-client-btn" href="javascript:void(0)"
                                                               data-id="<?php echo htmlspecialchars($cid); ?>"
                                                               data-code="<?php echo htmlspecialchars($code); ?>"
                                                               data-name="<?php echo htmlspecialchars($client['name']); ?>"
                                                               data-owner="<?php echo htmlspecialchars($client['owner'] ?? ''); ?>"
                                                               data-phone="<?php echo htmlspecialchars($phone); ?>"
                                                               data-password="<?php echo htmlspecialchars($pass); ?>"
                                                               data-city="<?php echo htmlspecialchars($client['city'] ?? ''); ?>"
                                                               data-limit="<?php echo $limit; ?>">
                                                                <i class="fa-solid fa-pen-to-square text-primary me-2"></i> Edit Client Profile
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item py-2" href="pos.php?client_id=<?php echo urlencode($cid); ?>">
                                                                <i class="fa-solid fa-cash-register text-info me-2"></i> POS Counter Sale
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <a class="dropdown-item py-2" href="login.php?client=1&autoclient=<?php echo urlencode($cid); ?>" target="_blank">
                                                                <i class="fa-solid fa-arrow-up-right-from-square text-warning me-2"></i> Open Client Wholesale Portal
                                                            </a>
                                                        </li>
                                                        <li><hr class="dropdown-divider"></li>
                                                        <li>
                                                            <a class="dropdown-item py-2 text-danger delete-client-btn" href="javascript:void(0)"
                                                               data-id="<?php echo htmlspecialchars($cid); ?>"
                                                               data-name="<?php echo htmlspecialchars($client['name']); ?>">
                                                                <i class="fa-solid fa-trash me-2"></i> Delete Client Record
                                                            </a>
                                                        </li>
                                                    </ul>
                                                </div>
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

    <!-- Modal: Setup / Create Client Login for Specific Table Row Client -->
    <div class="modal fade" id="setupClientLoginModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-warning text-dark py-3">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">
                            <i class="fa-solid fa-key me-2"></i>Create Client Login / پورٹل لاگ ان بنائیں
                        </h5>
                        <small class="text-dark-50 fw-semibold" id="setupLoginClientSubtitle">کلائنٹ کا موبائل نمبر اور پاسورڈ سیٹ کریں</small>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="clients.php" method="POST" id="setupClientLoginForm">
                    <input type="hidden" name="action" value="setup_client_login">
                    <input type="hidden" name="client_id" id="setupLoginClientId">
                    
                    <div class="modal-body p-4">
                        <!-- Client Overview Box -->
                        <div class="d-flex align-items-center justify-content-between p-3 bg-light rounded-3 mb-3 border">
                            <div>
                                <span class="badge bg-primary px-2 py-1 mb-1 font-monospace" id="setupLoginClientCode">WC-1001</span>
                                <h5 class="fw-bold text-dark mb-0" id="setupLoginClientName">Client Shop Name</h5>
                            </div>
                            <span class="badge bg-warning text-dark fw-bold">Wholesale Rates Access</span>
                        </div>

                        <div class="alert alert-warning py-2 px-3 small mb-3 rounded-3">
                            <i class="fa-solid fa-circle-info me-1 text-warning"></i>
                            Username ki jagah client ka <strong>Mobile Number</strong> use hoga aur <strong>Password</strong> jo aap yahan rakhenge. Login karne par client ko apna <strong>Khaata (Ledger)</strong> aur <strong>Wholesale Rates</strong> show honge.
                        </div>

                        <!-- Username: Mobile Number -->
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">
                                Client Mobile Number (Login Username) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-primary"><i class="fa-solid fa-mobile-screen text-primary fs-5"></i></span>
                                <input type="text" name="phone" id="setupLoginPhone" class="form-control border-primary fw-bold font-monospace" placeholder="0300-1234567" required>
                            </div>
                            <small class="text-primary fw-semibold" style="font-size: 0.74rem;">Yeh mobile number client ka login username hoga</small>
                        </div>

                        <!-- Password: Jo admin rakhe -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-bold text-dark mb-0">Client Password (جو آپ رکھیں) <span class="text-danger">*</span></label>
                                <button type="button" class="btn btn-link btn-sm text-primary p-0 text-decoration-none fw-semibold" onclick="generatePassword('setupLoginPassword')" style="font-size: 0.76rem;">
                                    <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Auto Generate PIN
                                </button>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-white border-warning"><i class="fa-solid fa-lock text-warning fs-5"></i></span>
                                <input type="text" name="password" id="setupLoginPassword" class="form-control border-warning font-monospace fw-bold" placeholder="Password rakhein (e.g. 1234)" value="1234" required>
                                <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('setupLoginPassword', this)">
                                    <i class="fa-regular fa-eye"></i>
                                </button>
                            </div>
                            <small class="text-muted" style="font-size: 0.74rem;">Client is password se login karega</small>
                        </div>
                    </div>

                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning px-4 fw-bold shadow-sm text-dark">
                            <i class="fa-solid fa-key me-1"></i> Save & Activate Login
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Add New Wholesale Client -->
    <div class="modal fade" id="addClientModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-primary text-white py-3">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">
                            <i class="fa-solid fa-user-plus me-2"></i>Add New Wholesale Client
                        </h5>
                        <small class="text-white-50">نیا ہول سیل کلائنٹ رجسٹر کریں</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="clients.php" method="POST" id="addClientForm">
                    <input type="hidden" name="action" value="add_client">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Shop / Business Name <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-shop text-muted"></i></span>
                                    <input type="text" name="name" class="form-control" placeholder="e.g. Lahore Electronics" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Proprietor / Owner Name</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-user text-muted"></i></span>
                                    <input type="text" name="owner" class="form-control" placeholder="e.g. Muhammad Ahmad">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Phone / Mobile Number <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-phone text-muted"></i></span>
                                    <input type="text" name="phone" class="form-control" placeholder="e.g. 0300-1234567" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">City / Market Location</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light"><i class="fa-solid fa-location-dot text-muted"></i></span>
                                    <input type="text" name="city" class="form-control" placeholder="e.g. Hall Road, Lahore">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Credit Limit (PKR)</label>
                                <input type="number" name="limit" class="form-control" value="500000" step="5000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Opening Udhaar (PKR)</label>
                                <input type="number" name="balance" class="form-control" placeholder="0" step="10">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-user-plus me-1"></i> Save Client Record
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Edit Wholesale Client & Credentials -->
    <div class="modal fade" id="editClientModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white py-3">
                    <div>
                        <h5 class="modal-title fw-bold mb-0">
                            <i class="fa-solid fa-user-pen me-2 text-warning"></i>Edit Wholesale Client & Login
                        </h5>
                        <small class="text-white-50">کلائنٹ کی معلومات یا لاگ ان پاسورڈ تبدیل کریں</small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="clients.php" method="POST" id="editClientForm">
                    <input type="hidden" name="action" value="edit_client">
                    <input type="hidden" name="client_id" id="editClientId">
                    <div class="modal-body p-4">
                        
                        <!-- Client Code Identifier -->
                        <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3 mb-3 border">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-primary fs-6 px-3 py-2" id="editClientCodeBadge">WC-1001</span>
                                <span class="fw-bold text-dark fs-6" id="editClientNameHeading">Client Name</span>
                            </div>
                            <small class="text-muted">Unique Account Record</small>
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Shop / Business Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="editClientName" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Proprietor / Owner Name</label>
                                <input type="text" name="owner" id="editClientOwner" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">City / Market Location</label>
                                <input type="text" name="city" id="editClientCity" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Credit Limit (PKR)</label>
                                <input type="number" name="limit" id="editClientLimit" class="form-control" step="5000">
                            </div>
                        </div>

                        <!-- Login Credentials Box -->
                        <div class="p-3 bg-warning bg-opacity-10 border border-warning border-opacity-50 rounded-3">
                            <h6 class="fw-bold text-dark mb-2 d-flex align-items-center">
                                <i class="fa-solid fa-key text-warning me-2"></i>Update Login Credentials (پورٹل لاگ ان معلومات)
                            </h6>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold text-dark">Login Mobile Number <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="fa-solid fa-phone text-primary"></i></span>
                                        <input type="text" name="phone" id="editClientPhone" class="form-control" required>
                                    </div>
                                    <small class="text-muted" style="font-size:0.74rem;">Yeh mobile number login username ke tor par use hoga</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <label class="form-label fw-semibold text-dark">Portal Password <span class="text-danger">*</span></label>
                                        <button type="button" class="btn btn-link btn-sm text-primary p-0 text-decoration-none" onclick="generatePassword('editClientPass')" style="font-size: 0.76rem;">
                                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i>Generate New
                                        </button>
                                    </div>
                                    <div class="input-group">
                                        <span class="input-group-text bg-white"><i class="fa-solid fa-lock text-warning"></i></span>
                                        <input type="text" name="password" id="editClientPass" class="form-control font-monospace fw-bold" required>
                                        <button class="btn btn-outline-secondary" type="button" onclick="togglePassVisibility('editClientPass', this)">
                                            <i class="fa-regular fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted" style="font-size:0.74rem;">Client ka naya password set karein</small>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-1"></i> Update Client Details
                        </button>
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
        const editModalEl = document.getElementById('editClientModal');
        const editModal   = editModalEl ? new bootstrap.Modal(editModalEl) : null;
        const setupLoginModalEl = document.getElementById('setupClientLoginModal');
        const setupLoginModal   = setupLoginModalEl ? new bootstrap.Modal(setupLoginModalEl) : null;

        // Setup / Create Client Login Button Click (From Action Column)
        document.querySelectorAll('.setup-client-login-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const clientId    = this.getAttribute('data-id');
                const clientCode  = this.getAttribute('data-code') || 'WC';
                const clientName  = this.getAttribute('data-name') || '';
                const clientPhone = this.getAttribute('data-phone') || '';
                const clientPass  = this.getAttribute('data-password') || '1234';

                document.getElementById('setupLoginClientId').value = clientId;
                document.getElementById('setupLoginClientCode').textContent = clientCode;
                document.getElementById('setupLoginClientName').textContent = clientName;
                document.getElementById('setupLoginClientSubtitle').textContent = 'Client: ' + clientName + ' (' + clientCode + ')';
                document.getElementById('setupLoginPhone').value = clientPhone;
                document.getElementById('setupLoginPassword').value = clientPass;

                if (setupLoginModal) {
                    setupLoginModal.show();
                    setTimeout(() => {
                        const pInput = document.getElementById('setupLoginPhone');
                        if (pInput && !pInput.value) {
                            pInput.focus();
                        } else {
                            document.getElementById('setupLoginPassword').focus();
                        }
                    }, 350);
                }
            });
        });

        // Edit Client Button Click
        document.querySelectorAll('.edit-client-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const clientId = this.getAttribute('data-id');
                const clientCode = this.getAttribute('data-code') || 'WC';
                const clientName = this.getAttribute('data-name');
                const clientOwner = this.getAttribute('data-owner') || '';
                const clientPhone = this.getAttribute('data-phone') || '';
                const clientPass = this.getAttribute('data-password') || '1234';
                const clientCity = this.getAttribute('data-city') || '';
                const clientLimit = this.getAttribute('data-limit') || '500000';

                document.getElementById('editClientId').value = clientId;
                document.getElementById('editClientCodeBadge').textContent = clientCode;
                document.getElementById('editClientNameHeading').textContent = clientName;
                document.getElementById('editClientName').value = clientName;
                document.getElementById('editClientOwner').value = clientOwner;
                document.getElementById('editClientPhone').value = clientPhone;
                document.getElementById('editClientPass').value = clientPass;
                document.getElementById('editClientCity').value = clientCity;
                document.getElementById('editClientLimit').value = clientLimit;

                if (editModal) editModal.show();
            });
        });

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

    // Toggle Password in Table Rows
    function togglePassRow(clientId, btn) {
        const masked = document.getElementById('passMask_' + clientId);
        const plain  = document.getElementById('passPlain_' + clientId);
        const icon   = btn.querySelector('i');

        if (masked && plain) {
            if (masked.classList.contains('d-none')) {
                masked.classList.remove('d-none');
                plain.classList.add('d-none');
                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            } else {
                masked.classList.add('d-none');
                plain.classList.remove('d-none');
                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            }
        }
    }

    // Toggle Password Input Visibility in Modals
    function togglePassVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon  = btn.querySelector('i');
        if (input) {
            if (input.type === 'password') {
                input.type = 'text';
                if (icon) {
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                }
            } else {
                input.type = 'password';
                if (icon) {
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            }
        }
    }

    // Auto Generate 4-digit PIN / password
    function generatePassword(targetInputId) {
        const pin = Math.floor(1000 + Math.random() * 9000);
        const input = document.getElementById(targetInputId);
        if (input) {
            input.value = pin;
            input.focus();
        }
    }

    // 1-Click Copy Text Helper
    function copyText(text, btn) {
        if (!text) return;
        navigator.clipboard.writeText(text).then(() => {
            if (btn) {
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
                setTimeout(() => {
                    btn.innerHTML = origHtml;
                }, 1500);
            }
        }).catch(err => {
            // Fallback for non-https
            const temp = document.createElement('textarea');
            temp.value = text;
            document.body.appendChild(temp);
            temp.select();
            document.execCommand('copy');
            document.body.removeChild(temp);
            if (btn) {
                const origHtml = btn.innerHTML;
                btn.innerHTML = '<i class="fa-solid fa-check text-success"></i>';
                setTimeout(() => {
                    btn.innerHTML = origHtml;
                }, 1500);
            }
        });
    }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
