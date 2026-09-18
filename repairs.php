<?php
// repairs.php - Mobile Repairing & Lab Desk Management with WhatsApp Alerts
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/repairs_data.php';
require_once __DIR__ . '/includes/config.php';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 1. Add new repair job
    if ($action === 'add_repair') {
        $res = add_repair_job($_POST);
        if ($res['success']) {
            $token = $res['job']['token_no'];
            // If user checked "Send WhatsApp immediately"
            $sendWa = isset($_POST['send_whatsapp_now']) && $_POST['send_whatsapp_now'] == '1';
            if ($sendWa) {
                $waUrl = generate_repair_whatsapp_url($res['job'], 'token_receipt');
                header("Location: repairs.php?added=1&token=" . urlencode($token) . "&wa=" . urlencode($waUrl));
                exit;
            }
            header("Location: repairs.php?added=1&token=" . urlencode($token));
            exit;
        } else {
            header("Location: repairs.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 2. Quick status update
    if ($action === 'update_status') {
        $token = trim($_POST['token_no'] ?? '');
        $newStatus = trim($_POST['status'] ?? '');
        $notes = trim($_POST['technician_notes'] ?? '');

        $res = update_repair_status($token, $newStatus, $notes);
        if ($res['success']) {
            $sendReadyWa = ($newStatus === 'ready' && isset($_POST['send_ready_whatsapp']) && $_POST['send_ready_whatsapp'] == '1');
            if ($sendReadyWa && isset($res['job'])) {
                $waUrl = generate_repair_whatsapp_url($res['job'], 'ready_alert');
                header("Location: repairs.php?status_updated=1&token=" . urlencode($token) . "&wa=" . urlencode($waUrl));
                exit;
            }
            header("Location: repairs.php?status_updated=1&token=" . urlencode($token));
            exit;
        } else {
            header("Location: repairs.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 3. Edit full repair job details
    if ($action === 'edit_repair') {
        $origToken = trim($_POST['orig_token_no'] ?? '');
        $res = update_repair_job($origToken, $_POST);
        if ($res['success']) {
            header("Location: repairs.php?edited=1&token=" . urlencode($origToken));
            exit;
        } else {
            header("Location: repairs.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 4. Delete repair job
    if ($action === 'delete_repair') {
        $token = trim($_POST['token_no'] ?? '');
        $res = delete_repair_job($token);
        if ($res['success']) {
            header("Location: repairs.php?deleted=1&token=" . urlencode($token));
            exit;
        } else {
            header("Location: repairs.php?err=" . urlencode($res['message']));
            exit;
        }
    }
}

$pageTitle = 'Smart Mobile - Mobile Repairs & Lab Desk';
$activePage = 'repairs';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$repairs = get_repairs_list();
$metrics = calculate_repairs_metrics($repairs);
$storePhoneDisplay = defined('STORE_PHONE_DISPLAY') ? STORE_PHONE_DISPLAY : '0304-1612042';
$storeWhatsApp = defined('STORE_WHATSAPP_NUMBER') ? STORE_WHATSAPP_NUMBER : '923041612042';
?>

<style>
/* Repairs Screen Custom Styles */
.repair-row:hover {
    background-color: #f8fafc;
}
.cursor-pointer { cursor: pointer; }
.status-badge-received {
    background-color: #fef3c7;
    color: #92400e;
    border: 1px solid #fde68a;
}
.status-badge-in_progress {
    background-color: #e0f2fe;
    color: #0369a1;
    border: 1px solid #bae6fd;
}
.status-badge-ready {
    background-color: #dcfce7;
    color: #15803d;
    border: 1px solid #bbf7d0;
}
.status-badge-delivered {
    background-color: #f1f5f9;
    color: #475569;
    border: 1px solid #e2e8f0;
}
.status-badge-cancelled {
    background-color: #fee2e2;
    color: #b91c1c;
    border: 1px solid #fecaca;
}
/* Thermal Slip Print Styling */
.thermal-slip {
    width: 100%;
    max-width: 320px;
    margin: 0 auto;
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    color: #000;
    line-height: 1.35;
}
.thermal-slip .dashed-line {
    border-top: 1px dashed #444;
    margin: 6px 0;
}
@media print {
    body * { visibility: hidden !important; }
    #thermalSlipArea, #thermalSlipArea * { visibility: visible !important; }
    #thermalSlipArea {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .modal-footer, .btn-close { display: none !important; }
}
</style>

    <!-- Main Content Area -->
    <div id="main-content">

        <!-- Notification Alerts -->
        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
                <div class="flex-grow-1">
                    <strong>New Repair Job Created!</strong> Token <code>#<?php echo htmlspecialchars($_GET['token'] ?? ''); ?></code> has been registered in the repair lab.
                    <?php if (isset($_GET['wa']) && !empty($_GET['wa'])): ?>
                        <div class="mt-2">
                            <a href="<?php echo htmlspecialchars($_GET['wa']); ?>" target="_blank" class="btn btn-sm btn-success fw-bold">
                                <i class="fa-brands fa-whatsapp me-1"></i> Open WhatsApp & Send Token Slip to Customer
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['status_updated'])): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-bell fs-4 me-3 text-info"></i>
                <div class="flex-grow-1">
                    <strong>Repair Status Updated!</strong> Token <code>#<?php echo htmlspecialchars($_GET['token'] ?? ''); ?></code> status has been changed.
                    <?php if (isset($_GET['wa']) && !empty($_GET['wa'])): ?>
                        <div class="mt-2">
                            <a href="<?php echo htmlspecialchars($_GET['wa']); ?>" target="_blank" class="btn btn-sm btn-success fw-bold">
                                <i class="fa-brands fa-whatsapp me-1"></i> Send 'Ready for Pickup' Alert to Customer Now
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['edited'])): ?>
            <div class="alert alert-primary alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-pen-to-square fs-4 me-3 text-primary"></i>
                <div>
                    <strong>Job Details Updated!</strong> Token <code>#<?php echo htmlspecialchars($_GET['token'] ?? ''); ?></code> details and billing updated.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-trash fs-4 me-3 text-warning"></i>
                <div>
                    <strong>Repair Job Removed!</strong> Token <code>#<?php echo htmlspecialchars($_GET['token'] ?? ''); ?></code> has been deleted.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['err'])): ?>
            <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-triangle-exclamation fs-4 me-3 text-danger"></i>
                <div>
                    <strong>Action Failed:</strong> <?php echo htmlspecialchars($_GET['err']); ?>
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Top Title & Quick Actions Header -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
            <div>
                <h4 class="fw-bold mb-1"><i class="fa-solid fa-screwdriver-wrench text-warning me-2"></i>Mobile Repairs & Lab Desk</h4>
                <p class="text-muted mb-0 small">
                    Customer device intake, job tokens, repair status updates & 1-click WhatsApp customer notifications.
                </p>
            </div>
            <div class="d-flex gap-2">
                <a href="track.php" target="_blank" class="btn btn-outline-primary btn-sm px-3 shadow-xs">
                    <i class="fa-solid fa-satellite-dish me-1"></i> Customer Live Tracking Screen &rarr;
                </a>
                <button type="button" class="btn btn-primary btn-sm px-3 shadow-sm fw-bold" data-bs-toggle="modal" data-bs-target="#addRepairModal">
                    <i class="fa-solid fa-plus-circle me-1"></i> New Repair Intake
                </button>
            </div>
        </div>

        <!-- Metric Stat Cards -->
        <div class="row g-3 mb-4">
            <!-- 1. Active Repairs -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 h-100 overflow-hidden">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Active Repairs</small>
                            <h3 class="fw-bold mb-0 text-dark"><?php echo $metrics['active_jobs']; ?> Devices</h3>
                            <small class="text-muted">Total logged: <?php echo $metrics['total']; ?></small>
                        </div>
                        <div class="bg-primary-subtle text-primary p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa-solid fa-mobile-screen fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. In Lab / Progress -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 h-100 overflow-hidden">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted text-uppercase fw-semibold" style="letter-spacing: 0.5px;">In Lab / In Progress</small>
                            <h3 class="fw-bold mb-0 text-info"><?php echo $metrics['in_progress_count']; ?> Devices</h3>
                            <small class="text-warning fw-semibold"><?php echo $metrics['received_count']; ?> Waiting in Queue</small>
                        </div>
                        <div class="bg-info-subtle text-info p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa-solid fa-gears fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 3. Ready for Delivery -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 h-100 overflow-hidden" style="background: linear-gradient(135deg, #059669 0%, #10b981 100%);">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between text-white">
                        <div>
                            <small class="text-white-50 text-uppercase fw-bold" style="letter-spacing: 0.5px;">Ready For Pickup</small>
                            <h3 class="fw-bold mb-0 text-white"><?php echo $metrics['ready_count']; ?> Ready</h3>
                            <small class="text-white opacity-75">Customer ko WhatsApp bhejein</small>
                        </div>
                        <div class="bg-white bg-opacity-25 text-white p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa-solid fa-circle-check fs-3"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 4. Pending Receivable Balance -->
            <div class="col-sm-6 col-xl-3">
                <div class="card border-0 shadow-sm rounded-3 h-100 overflow-hidden">
                    <div class="card-body p-3 d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Repair Receivable</small>
                            <h3 class="fw-bold mb-0 text-danger">PKR <?php echo number_format($metrics['pending_receivable']); ?></h3>
                            <small class="text-muted"><?php echo $metrics['delivered_count']; ?> Delivered closed</small>
                        </div>
                        <div class="bg-danger-subtle text-danger p-3 rounded-circle d-flex align-items-center justify-content-center" style="width: 52px; height: 52px;">
                            <i class="fa-solid fa-hand-holding-dollar fs-4"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Search Toolbar Card -->
        <div class="card border-0 shadow-sm rounded-3 mb-3 bg-white">
            <div class="card-body p-3">
                <div class="row g-2 align-items-center">
                    <div class="col-md-5">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="repairSearchInput" class="form-control bg-light border-start-0" placeholder="Search Token #, Customer Name, Phone, Device Model..." onkeyup="filterRepairsTable()">
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-filter text-muted"></i></span>
                            <select id="statusFilterSelect" class="form-select bg-light border-start-0" onchange="filterRepairsTable()">
                                <option value="all">Filter: All Statuses (<?php echo count($repairs); ?>)</option>
                                <option value="active">Active Only (Received / In Lab / Ready)</option>
                                <option value="received">🟡 Received Only (<?php echo $metrics['received_count']; ?>)</option>
                                <option value="in_progress">🔵 In Progress / Lab (<?php echo $metrics['in_progress_count']; ?>)</option>
                                <option value="ready">🟢 Ready for Pickup (<?php echo $metrics['ready_count']; ?>)</option>
                                <option value="delivered">⚪ Delivered (<?php echo $metrics['delivered_count']; ?>)</option>
                                <option value="cancelled">🔴 Cancelled (<?php echo $metrics['cancelled_count']; ?>)</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-3 text-md-end">
                        <span class="badge bg-light text-secondary border px-3 py-2" id="filteredCountBadge">
                            Showing <?php echo count($repairs); ?> jobs
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Repairs Management Table -->
        <div class="card border-0 shadow-sm rounded-3 overflow-hidden bg-white">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="repairsTable">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 110px;">Token #</th>
                            <th>Customer Info</th>
                            <th>Device & Problem</th>
                            <th>Bill & Advance</th>
                            <th>Status</th>
                            <th>Intake Date</th>
                            <th class="text-center" style="width: 180px;">WhatsApp & Actions</th>
                        </tr>
                    </thead>
                    <tbody id="repairsTableBody">
                        <?php if (empty($repairs)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-screwdriver-wrench fa-3x mb-3 text-secondary opacity-50"></i>
                                    <p class="mb-2 fs-6">Koi mobile repair job record mojood nahi hai.</p>
                                    <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addRepairModal">
                                        <i class="fa-solid fa-plus-circle me-1"></i> Naya Repair Phone Add Karein
                                    </button>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($repairs as $token => $r): 
                                $status = $r['status'] ?? 'received';
                                $cost = (float)($r['estimated_cost'] ?? 0);
                                $adv = (float)($r['advance_paid'] ?? 0);
                                $due = max(0, $cost - $adv);

                                // WhatsApp Links
                                $waSlipUrl  = generate_repair_whatsapp_url($r, 'token_receipt');
                                $waReadyUrl = generate_repair_whatsapp_url($r, 'ready_alert');
                                $waChatUrl  = generate_repair_whatsapp_url($r, 'status_update');

                                // Status badge class
                                $badgeClass = 'status-badge-' . $status;
                                $statusNames = [
                                    'received'    => 'Received',
                                    'in_progress' => 'In Progress',
                                    'ready'       => 'Ready for Pickup',
                                    'delivered'   => 'Delivered',
                                    'cancelled'   => 'Cancelled'
                                ];
                                $statusLabel = $statusNames[$status] ?? ucfirst($status);
                            ?>
                                <tr class="repair-row" 
                                    data-token="<?php echo htmlspecialchars(strtolower($token)); ?>"
                                    data-customer="<?php echo htmlspecialchars(strtolower($r['customer_name'] . ' ' . $r['customer_phone'])); ?>"
                                    data-device="<?php echo htmlspecialchars(strtolower($r['device_model'] . ' ' . $r['fault_issue'])); ?>"
                                    data-status="<?php echo htmlspecialchars($status); ?>">
                                    <td>
                                        <span class="badge bg-primary text-white fw-bold font-monospace px-2 py-1">
                                            #<?php echo htmlspecialchars($token); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($r['customer_name']); ?></div>
                                        <small class="text-muted">
                                            <i class="fa-solid fa-phone me-1 text-primary"></i><?php echo htmlspecialchars($r['customer_phone']); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold text-dark">
                                            <i class="fa-solid fa-mobile-screen me-1 text-secondary"></i><?php echo htmlspecialchars($r['device_model']); ?>
                                        </div>
                                        <small class="text-muted text-truncate d-inline-block" style="max-width: 200px;" title="<?php echo htmlspecialchars($r['fault_issue']); ?>">
                                            Fault: <?php echo htmlspecialchars($r['fault_issue']); ?>
                                        </small>
                                        <?php if (!empty($r['pattern_lock'])): ?>
                                            <div class="small text-secondary" style="font-size: 0.72rem;">
                                                <i class="fa-solid fa-lock me-1 text-warning"></i>PIN/Pattern: <code><?php echo htmlspecialchars($r['pattern_lock']); ?></code>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark">PKR <?php echo number_format($cost); ?></div>
                                        <?php if ($adv > 0): ?>
                                            <small class="text-success d-block">Adv: PKR <?php echo number_format($adv); ?></small>
                                        <?php endif; ?>
                                        <small class="<?php echo ($due > 0) ? 'text-danger fw-bold' : 'text-success'; ?>">
                                            Due: PKR <?php echo number_format($due); ?>
                                        </small>
                                    </td>
                                    <td>
                                        <!-- Quick Status Changer Dropdown -->
                                        <div class="dropdown">
                                            <button class="btn btn-sm <?php echo $badgeClass; ?> dropdown-toggle fw-bold px-2 py-1" type="button" data-bs-toggle="dropdown" style="font-size: 0.78rem;">
                                                <?php echo $statusLabel; ?>
                                            </button>
                                            <ul class="dropdown-menu shadow-sm border-0" style="font-size: 0.84rem;">
                                                <li><h6 class="dropdown-header">Change Status:</h6></li>
                                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeStatus('<?php echo $token; ?>', 'received')">🟡 Received</a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeStatus('<?php echo $token; ?>', 'in_progress')">🔵 In Progress / Lab</a></li>
                                                <li><a class="dropdown-item text-success fw-bold" href="javascript:void(0)" onclick="quickChangeStatus('<?php echo $token; ?>', 'ready')">🟢 Ready for Pickup (Notify Customer)</a></li>
                                                <li><a class="dropdown-item" href="javascript:void(0)" onclick="quickChangeStatus('<?php echo $token; ?>', 'delivered')">⚪ Delivered / Handover</a></li>
                                                <li><hr class="dropdown-divider"></li>
                                                <li><a class="dropdown-item text-danger" href="javascript:void(0)" onclick="quickChangeStatus('<?php echo $token; ?>', 'cancelled')">🔴 Cancelled</a></li>
                                            </ul>
                                        </div>
                                    </td>
                                    <td>
                                        <small class="text-muted d-block"><?php echo date('d M Y', strtotime($r['created_at'])); ?></small>
                                        <small class="text-muted" style="font-size: 0.72rem;"><?php echo date('h:i A', strtotime($r['created_at'])); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <div class="btn-group btn-group-sm" role="group">
                                            <!-- WhatsApp Menu Button -->
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-success dropdown-toggle" data-bs-toggle="dropdown" title="WhatsApp Customer Alerts">
                                                    <i class="fa-brands fa-whatsapp"></i>
                                                </button>
                                                <ul class="dropdown-menu shadow-sm border-0 dropdown-menu-end" style="font-size: 0.84rem;">
                                                    <li><h6 class="dropdown-header"><i class="fa-brands fa-whatsapp text-success me-1"></i> Send Customer Alert:</h6></li>
                                                    <li>
                                                        <a class="dropdown-item" href="<?php echo htmlspecialchars($waSlipUrl); ?>" target="_blank">
                                                            <i class="fa-solid fa-receipt text-primary me-2"></i> Send Token Slip &amp; Live Tracking Link
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item text-success fw-bold" href="<?php echo htmlspecialchars($waReadyUrl); ?>" target="_blank">
                                                            <i class="fa-solid fa-bell text-success me-2"></i> Send 'Mobile Ready for Pickup' Alert
                                                        </a>
                                                    </li>
                                                    <li>
                                                        <a class="dropdown-item" href="<?php echo htmlspecialchars($waChatUrl); ?>" target="_blank">
                                                            <i class="fa-solid fa-comment-dots text-secondary me-2"></i> Send Current Status Update
                                                        </a>
                                                    </li>
                                                </ul>
                                            </div>

                                            <!-- Print Token Slip Button -->
                                            <button type="button" class="btn btn-outline-secondary" onclick="openPrintSlipModal(<?php echo htmlspecialchars(json_encode($r)); ?>)" title="Print 80mm Token Slip">
                                                <i class="fa-solid fa-print"></i>
                                            </button>

                                            <!-- Live Track View Link -->
                                            <a href="track.php?token=<?php echo urlencode($token); ?>" target="_blank" class="btn btn-outline-primary" title="Open Live Status View">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i>
                                            </a>

                                            <!-- Edit Job Details Button -->
                                            <button type="button" class="btn btn-outline-dark" onclick="openEditJobModal(<?php echo htmlspecialchars(json_encode($r)); ?>)" title="Edit Details">
                                                <i class="fa-solid fa-pen-to-square"></i>
                                            </button>

                                            <!-- Delete Job Button -->
                                            <button type="button" class="btn btn-outline-danger" onclick="confirmDeleteJob('<?php echo $token; ?>')" title="Delete Record">
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

            <!-- Footer summary -->
            <div class="card-footer bg-white d-flex justify-content-between align-items-center py-3">
                <small class="text-muted" id="footerSummary">Total <?php echo count($repairs); ?> devices registered in lab</small>
                <div class="small">
                    <span class="badge bg-warning-subtle text-dark border border-warning-subtle me-1"><?php echo $metrics['received_count']; ?> Received</span>
                    <span class="badge bg-info-subtle text-info border border-info-subtle me-1"><?php echo $metrics['in_progress_count']; ?> In Progress</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle me-1"><?php echo $metrics['ready_count']; ?> Ready</span>
                    <span class="badge bg-secondary-subtle text-secondary border"><?php echo $metrics['delivered_count']; ?> Delivered</span>
                </div>
            </div>
        </div>

    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 1: ADD NEW REPAIR JOB -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="addRepairModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-screwdriver-wrench me-2"></i>New Mobile Repair Job Intake</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="repairs.php" method="POST">
                    <input type="hidden" name="action" value="add_repair">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Customer Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" class="form-control" placeholder="e.g. Muhammad Usman" required autofocus>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Customer Mobile Number <span class="text-danger">*</span></label>
                                <input type="text" name="customer_phone" class="form-control" placeholder="0300-1234567" required>
                                <small class="text-muted">WhatsApp alert isi number par bheja jaye ga</small>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Device Model / Phone Name <span class="text-danger">*</span></label>
                                <input type="text" name="device_model" class="form-control" placeholder="e.g. Samsung A32 ya iPhone 11" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Pattern Lock / Screen PIN (Optional)</label>
                                <input type="text" name="pattern_lock" class="form-control" placeholder="e.g. 1234 ya L-Shape Pattern">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Reported Fault / Problem Details <span class="text-danger">*</span></label>
                                <textarea name="fault_issue" class="form-control" rows="2" placeholder="e.g. Display glass cracked, charging port loose, battery draining fast" required></textarea>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Estimated Repair Bill (PKR) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold text-primary">PKR</span>
                                    <input type="number" name="estimated_cost" class="form-control fw-bold text-primary" placeholder="3500" min="0" step="1" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Advance Payment Received (PKR)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light fw-bold text-success">PKR</span>
                                    <input type="number" name="advance_paid" class="form-control fw-bold text-success" placeholder="0" min="0" step="1" value="0">
                                </div>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Technician / Lab Notes (Internal)</label>
                                <input type="text" name="technician_notes" class="form-control" placeholder="e.g. Needs original AMOLED panel, check mic after fixing">
                            </div>

                            <div class="col-12">
                                <div class="p-3 bg-light rounded-3 border d-flex align-items-center justify-content-between">
                                    <div>
                                        <strong class="d-block text-dark"><i class="fa-brands fa-whatsapp text-success me-1"></i> Send WhatsApp Token Slip to Customer</strong>
                                        <small class="text-muted">Job save hote hi direct WhatsApp par slip aur tracking link open ho jayega</small>
                                    </div>
                                    <div class="form-check form-switch fs-4 mb-0">
                                        <input class="form-check-input" type="checkbox" name="send_whatsapp_now" value="1" id="sendWaCheck" checked>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold">
                            <i class="fa-solid fa-save me-1"></i> Create Repair Token
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 2: EDIT REPAIR JOB DETAILS -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="editRepairModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Repair Job Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="repairs.php" method="POST">
                    <input type="hidden" name="action" value="edit_repair">
                    <input type="hidden" name="orig_token_no" id="editOrigToken">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Customer Name <span class="text-danger">*</span></label>
                                <input type="text" name="customer_name" id="editCustomerName" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Customer Mobile <span class="text-danger">*</span></label>
                                <input type="text" name="customer_phone" id="editCustomerPhone" class="form-control" required>
                            </div>

                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Device Model <span class="text-danger">*</span></label>
                                <input type="text" name="device_model" id="editDeviceModel" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Pattern Lock / Screen PIN</label>
                                <input type="text" name="pattern_lock" id="editPatternLock" class="form-control">
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Fault / Problem Details <span class="text-danger">*</span></label>
                                <textarea name="fault_issue" id="editFaultIssue" class="form-control" rows="2" required></textarea>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Estimated Bill (PKR)</label>
                                <input type="number" name="estimated_cost" id="editEstCost" class="form-control fw-bold text-primary" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Advance Paid (PKR)</label>
                                <input type="number" name="advance_paid" id="editAdvPaid" class="form-control fw-bold text-success" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Current Status</label>
                                <select name="status" id="editStatus" class="form-select">
                                    <option value="received">🟡 Received in Lab</option>
                                    <option value="in_progress">🔵 In Progress / Repairing</option>
                                    <option value="ready">🟢 Ready for Pickup</option>
                                    <option value="delivered">⚪ Delivered to Customer</option>
                                    <option value="cancelled">🔴 Cancelled</option>
                                </select>
                            </div>

                            <div class="col-12">
                                <label class="form-label fw-semibold">Technician / Lab Notes</label>
                                <input type="text" name="technician_notes" id="editTechNotes" class="form-control">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="fa-solid fa-check me-1"></i> Update Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 3: QUICK STATUS UPDATE CONFIRMATION -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="statusModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold" id="statusModalTitle">Change Repair Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="repairs.php" method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="token_no" id="quickStatusToken">
                    <input type="hidden" name="status" id="quickStatusVal">

                    <div class="modal-body p-4 text-center">
                        <div class="mb-3">
                            <i class="fa-solid fa-bell fa-3x text-warning mb-3"></i>
                            <h5 class="fw-bold" id="statusModalConfirmText">Status tabdeel karna chahte hain?</h5>
                            <p class="text-muted small mb-0">Token <strong id="statusModalTokenLabel">#REP-1001</strong></p>
                        </div>

                        <div id="readyNotifyWrapper" class="p-3 bg-success bg-opacity-10 border border-success border-opacity-25 rounded-3 text-start d-none mt-3">
                            <div class="form-check form-switch mb-0">
                                <input class="form-check-input" type="checkbox" name="send_ready_whatsapp" value="1" id="sendReadyCheck" checked>
                                <label class="form-check-label fw-bold text-success" for="sendReadyCheck">
                                    <i class="fa-brands fa-whatsapp me-1"></i> Customer ko WhatsApp "Ready for Pickup" message bhejein
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4">
                            <i class="fa-solid fa-check me-1"></i> Confirm Update
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ========================================================================= -->
    <!-- MODAL 4: 80MM THERMAL TOKEN PRINT SLIP -->
    <!-- ========================================================================= -->
    <div class="modal fade" id="printSlipModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-print me-2 text-warning"></i>Print Repair Token Slip</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white" id="thermalSlipArea">
                    <div class="thermal-slip text-center" id="thermalSlipContent">
                        <h4 class="fw-bold mb-0 text-uppercase">SMART MOBILE</h4>
                        <p class="mb-0 text-muted" style="font-size: 11px;">Mobile Repairing Lab &amp; Service Desk</p>
                        <p class="mb-1 text-muted" style="font-size: 10px;">Milad Chowk Main Bazar Miani | Ph: <?php echo $storePhoneDisplay; ?></p>
                        
                        <div class="dashed-line"></div>
                        <h5 class="fw-bold my-1" id="slipTokenNo">#REP-1001</h5>
                        <p class="mb-1" style="font-size: 11px;">REPAIR INTAKE TOKEN SLIP</p>
                        <div class="dashed-line"></div>

                        <div class="text-start" style="font-size: 11px;">
                            <div class="d-flex justify-content-between mb-1">
                                <span>Date &amp; Time:</span>
                                <strong id="slipDate">2026-09-12 15:30</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Customer:</span>
                                <strong id="slipCustName">Muhammad Usman</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Phone:</span>
                                <strong id="slipCustPhone">0300-1234567</strong>
                            </div>
                            <div class="dashed-line"></div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Device Model:</span>
                                <strong id="slipDevice">Vivo V20</strong>
                            </div>
                            <div class="mb-1">
                                <span>Fault / Problem:</span><br>
                                <strong id="slipFault">Display Glass Cracked</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1" id="slipLockRow">
                                <span>PIN / Lock:</span>
                                <strong id="slipLock">1234</strong>
                            </div>
                            <div class="dashed-line"></div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Est. Repair Bill:</span>
                                <strong id="slipEstCost">PKR 3,500</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1">
                                <span>Advance Paid:</span>
                                <strong id="slipAdvPaid">PKR 1,000</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-1 fs-6">
                                <span>Remaining Due:</span>
                                <strong id="slipDue">PKR 2,500</strong>
                            </div>
                            <div class="dashed-line"></div>
                            <div class="text-center my-2" style="font-size: 10px;">
                                <strong>Track Live Online:</strong><br>
                                <span id="slipTrackUrl" class="font-monospace">track.php</span>
                            </div>
                            <div class="text-center text-muted" style="font-size: 9px;">
                                * Barah-e-karam mobile receive karte waqt yeh slip sath layein.<br>
                                30 din baad dukan zimmedar nahi hogi.
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-dark fw-bold" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> Print 80mm Receipt
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Deletion -->
    <form id="deleteJobForm" action="repairs.php" method="POST" class="d-none">
        <input type="hidden" name="action" value="delete_repair">
        <input type="hidden" name="token_no" id="deleteJobToken">
    </form>

    <!-- JavaScript Handlers -->
    <script>
    // Live Search and Status Filter
    function filterRepairsTable() {
        const query = document.getElementById('repairSearchInput').value.toLowerCase().trim();
        const statusFilter = document.getElementById('statusFilterSelect').value;
        const rows = document.querySelectorAll('.repair-row');
        let visibleCount = 0;

        rows.forEach(row => {
            const token = row.getAttribute('data-token');
            const customer = row.getAttribute('data-customer');
            const device = row.getAttribute('data-device');
            const status = row.getAttribute('data-status');

            const matchesQuery = !query || token.includes(query) || customer.includes(query) || device.includes(query);
            let matchesStatus = true;
            if (statusFilter === 'active') {
                matchesStatus = (status === 'received' || status === 'in_progress' || status === 'ready');
            } else if (statusFilter !== 'all') {
                matchesStatus = (status === statusFilter);
            }

            if (matchesQuery && matchesStatus) {
                row.style.display = '';
                visibleCount++;
            } else {
                row.style.display = 'none';
            }
        });

        document.getElementById('filteredCountBadge').textContent = 'Showing ' + visibleCount + ' jobs';
    }

    // Quick Status Modal trigger
    function quickChangeStatus(token, newStatus) {
        document.getElementById('quickStatusToken').value = token;
        document.getElementById('quickStatusVal').value = newStatus;
        document.getElementById('statusModalTokenLabel').textContent = '#' + token;

        const notifyWrap = document.getElementById('readyNotifyWrapper');
        if (newStatus === 'ready') {
            notifyWrap.classList.remove('d-none');
            document.getElementById('statusModalConfirmText').textContent = "Mark as READY FOR PICKUP?";
        } else {
            notifyWrap.classList.add('d-none');
            document.getElementById('statusModalConfirmText').textContent = "Change status to " + newStatus.toUpperCase() + "?";
        }

        const modal = new bootstrap.Modal(document.getElementById('statusModal'));
        modal.show();
    }

    // Open Edit Modal with prefilled values
    function openEditJobModal(job) {
        document.getElementById('editOrigToken').value = job.token_no;
        document.getElementById('editCustomerName').value = job.customer_name;
        document.getElementById('editCustomerPhone').value = job.customer_phone;
        document.getElementById('editDeviceModel').value = job.device_model;
        document.getElementById('editPatternLock').value = job.pattern_lock || '';
        document.getElementById('editFaultIssue').value = job.fault_issue;
        document.getElementById('editEstCost').value = job.estimated_cost;
        document.getElementById('editAdvPaid').value = job.advance_paid;
        document.getElementById('editStatus').value = job.status;
        document.getElementById('editTechNotes').value = job.technician_notes || '';

        const modal = new bootstrap.Modal(document.getElementById('editRepairModal'));
        modal.show();
    }

    // Open Thermal Print Slip Modal
    function openPrintSlipModal(job) {
        document.getElementById('slipTokenNo').textContent = '#' + job.token_no;
        document.getElementById('slipDate').textContent = job.created_at;
        document.getElementById('slipCustName').textContent = job.customer_name;
        document.getElementById('slipCustPhone').textContent = job.customer_phone;
        document.getElementById('slipDevice').textContent = job.device_model;
        document.getElementById('slipFault').textContent = job.fault_issue;

        if (job.pattern_lock) {
            document.getElementById('slipLockRow').classList.remove('d-none');
            document.getElementById('slipLock').textContent = job.pattern_lock;
        } else {
            document.getElementById('slipLockRow').classList.add('d-none');
        }

        const cost = Number(job.estimated_cost || 0);
        const adv = Number(job.advance_paid || 0);
        const due = Math.max(0, cost - adv);

        document.getElementById('slipEstCost').textContent = 'PKR ' + cost.toLocaleString();
        document.getElementById('slipAdvPaid').textContent = 'PKR ' + adv.toLocaleString();
        document.getElementById('slipDue').textContent = 'PKR ' + due.toLocaleString();

        const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/'));
        const trackUrl = window.location.origin + basePath + '/track.php?token=' + encodeURIComponent(job.token_no);
        document.getElementById('slipTrackUrl').textContent = trackUrl;

        const modal = new bootstrap.Modal(document.getElementById('printSlipModal'));
        modal.show();
    }

    // Confirm Delete
    function confirmDeleteJob(token) {
        if (confirm("Kya aap waqai Token #" + token + " ko delete karna chahte hain?")) {
            document.getElementById('deleteJobToken').value = token;
            document.getElementById('deleteJobForm').submit();
        }
    }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
