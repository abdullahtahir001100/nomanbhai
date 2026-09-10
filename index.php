<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/store_data.php';

// Direct fallback POST handling for stock adjustment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'stock_adjust') {
    $sku = trim($_POST['sku'] ?? '');
    $type = trim($_POST['type'] ?? 'in');
    $qty = max(1, (int)($_POST['quantity'] ?? 1));
    $note = trim($_POST['note'] ?? '');
    if (!empty($sku)) {
        record_stock_adjustment($sku, $type, $qty, $note);
        header("Location: index.php?stock_updated=1");
        exit;
    }
}

$pageTitle = 'Smart Mobile - Dashboard & Wholesale Portal';
$activePage = 'dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Load dynamic store metrics from central store data
$storeData            = get_store_metrics();
$todaySales           = $storeData['today_sales'] ?? 0;
$stockInToday         = $storeData['stock_in_today'] ?? 0;
$stockOutToday        = $storeData['stock_out_today'] ?? 0;
$closingStatus        = $storeData['daily_closing_status'] ?? 'Pending';
$isClosed             = (strtolower($closingStatus) === 'closed');
$products             = $storeData['products'] ?? [];
?>

    <!-- Main Content Area -->
    <div id="main-content">
        <!-- Top Header Bar -->
        <header class="d-flex justify-content-between align-items-center mb-4 bg-white p-3 rounded-3 shadow-sm">
            <div>
                <h5 class="mb-0 fw-bold">Overview & Catalog</h5>
                <small class="text-muted">Welcome back, <?php echo htmlspecialchars($currentUser['name'] ?? 'Admin'); ?></small>
            </div>
            
            <!-- Dynamic Role Toggle Simulation -->
            <div class="d-flex align-items-center gap-3">
                <div class="bg-light p-2 rounded-3 border">
                    <label class="form-check-label me-2 fw-semibold small" for="roleSwitch">View Mode:</label>
                    <div class="form-check form-switch d-inline-block align-middle">
                        <input class="form-check-input cursor-pointer" type="checkbox" id="roleSwitch" onchange="handleViewModeSwitch(this)">
                        <span id="roleLabel" class="badge bg-secondary ms-1">Retail View</span>
                    </div>
                </div>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-user me-1"></i> <?php echo htmlspecialchars($currentUser['name'] ?? 'Admin Account'); ?>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0">
                        <li><a class="dropdown-item" href="dailyclosing.php"><i class="fa-solid fa-file-invoice-dollar me-2 text-muted"></i>Daily Closing</a></li>
                        <li><a class="dropdown-item" href="popup.php"><i class="fa-solid fa-user-shield me-2 text-muted"></i>Wholesale Portal</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item text-danger" href="logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Sign Out / Logout</a></li>
                    </ul>
                </div>
            </div>
        </header>

        <!-- Live Stock Notification Alert Box -->
        <div id="stockNotificationBox" class="d-none alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 rounded-3 p-3 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check fs-5 me-2 text-success"></i>
            <div id="stockNotificationMsg"><strong>Stock Updated!</strong> Inventory counts have been updated successfully.</div>
            <button type="button" class="btn-close" onclick="document.getElementById('stockNotificationBox').classList.add('d-none')"></button>
        </div>

        <?php if (isset($_GET['stock_updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show d-flex align-items-center mb-4 rounded-3 p-3 shadow-sm" role="alert">
                <i class="fa-solid fa-circle-check fs-5 me-2 text-success"></i>
                <div><strong>Stock Updated!</strong> Stock entry recorded and metrics updated live.</div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Dynamic Metric Stat Cards (Live PHP Data) -->
        <div class="row g-3 mb-4">
            <!-- 1. Today's Sales -->
            <div class="col-md-3">
                <a href="pos.php" class="text-decoration-none">
                    <div class="card stat-card shadow-sm bg-primary text-white p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-white-50">Today's Sales</small>
                                <h3 class="fw-bold mb-0">PKR <span id="statTodaySales"><?php echo number_format($todaySales); ?></span></h3>
                            </div>
                            <i class="fa-solid fa-money-bill-wave fa-2x opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>

            <!-- 2. Stock IN Today -->
            <div class="col-md-3">
                <a href="inventory.php" class="text-decoration-none">
                    <div class="card stat-card shadow-sm bg-success text-white p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-white-50">Stock IN Today</small>
                                <h3 class="fw-bold mb-0"><span id="statStockIn"><?php echo number_format($stockInToday); ?></span> Units</h3>
                            </div>
                            <i class="fa-solid fa-arrow-down-left fa-2x opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>

            <!-- 3. Stock OUT Today -->
            <div class="col-md-3">
                <a href="inventory.php" class="text-decoration-none">
                    <div class="card stat-card shadow-sm bg-warning text-dark p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-dark-50">Stock OUT Today</small>
                                <h3 class="fw-bold mb-0"><span id="statStockOut"><?php echo number_format($stockOutToday); ?></span> Units</h3>
                            </div>
                            <i class="fa-solid fa-arrow-up-right fa-2x opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>

            <!-- 4. Daily Closing Status -->
            <div class="col-md-3">
                <a href="dailyclosing.php" class="text-decoration-none">
                    <div class="card stat-card shadow-sm bg-dark text-white p-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <small class="text-white-50">Daily Closing Status</small>
                                <h3 class="fw-bold mb-0" id="statClosingStatusWrapper">
                                    <span id="statClosingStatus" class="<?php echo $isClosed ? 'text-success' : 'text-warning'; ?>">
                                        <?php echo htmlspecialchars($closingStatus); ?>
                                    </span>
                                </h3>
                            </div>
                            <i id="statClosingIcon" class="fa-solid <?php echo $isClosed ? 'fa-lock text-success' : 'fa-lock-open text-warning'; ?> fa-2x opacity-50"></i>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <!-- Product Inventory & Pricing Table (Action Column Removed) -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="mb-0 fw-bold"><i class="fa-solid fa-list me-2 text-primary"></i>Stock & Pricing Catalog</h6>
                <div class="d-flex gap-2">
                    <input type="text" id="catalogSearchInput" onkeyup="filterCatalogTable()" class="form-control form-control-sm" placeholder="Search product, panel, battery...">
                    <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#quickStockModal">
                        <i class="fa-solid fa-right-left me-1"></i> Stock IN / OUT
                    </button>
                    <a href="inventory.php" class="btn btn-sm btn-primary"><i class="fa-solid fa-boxes-stacked me-1"></i>Manage Stock</a>
                </div>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="catalogTable">
                        <thead class="table-light">
                            <tr>
                                <th>Item Name</th>
                                <th>Category</th>
                                <th>In Stock</th>
                                <th>Price</th>
                            </tr>
                        </thead>
                        <tbody id="catalogTableBody">
                            <?php foreach ($products as $sku => $prod): ?>
                            <tr data-sku="<?php echo htmlspecialchars($sku); ?>">
                                <td>
                                    <div class="fw-bold product-name"><?php echo htmlspecialchars($prod['name']); ?></div>
                                    <small class="text-muted">SKU: <?php echo htmlspecialchars($prod['sku']); ?></small>
                                </td>
                                <td>
                                    <span class="badge <?php echo htmlspecialchars($prod['category_badge'] ?? 'bg-secondary'); ?>">
                                        <?php echo htmlspecialchars($prod['category']); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="stock-qty-badge fw-bold <?php echo ($prod['stock'] > 10) ? 'text-success' : (($prod['stock'] > 0) ? 'text-danger' : 'text-muted'); ?>">
                                        <?php 
                                        if ($prod['stock'] > 10) {
                                            echo "{$prod['stock']} Pcs";
                                        } elseif ($prod['stock'] > 0) {
                                            echo "{$prod['stock']} Pcs (Low)";
                                        } else {
                                            echo '<span class="badge bg-danger">Out of Stock</span>';
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <!-- Price switches dynamically based on Wholesale login status -->
                                    <div class="price-retail">PKR <?php echo number_format($prod['retail_price']); ?></div>
                                    <div class="price-wholesale d-none">
                                        <span class="fw-bold text-success">PKR <?php echo number_format($prod['wholesale_price']); ?></span>
                                        <span class="retail-price ms-1">PKR <?php echo number_format($prod['retail_price']); ?></span>
                                        <span class="wholesale-badge ms-1">Wholesale Rate</span>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>

    <!-- Modal: Wholesale Client Login Popup -->
    <div class="modal fade" id="wholesaleLoginModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-labelledby="wholesaleLoginModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white rounded-top-4 py-3 border-0">
                    <div class="d-flex align-items-center">
                        <div class="rounded-3 bg-warning bg-opacity-25 p-2 me-2 text-warning">
                            <i class="fa-solid fa-user-shield fs-5"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold mb-0 text-white" id="wholesaleLoginModalLabel">Wholesale Client Login</h6>
                            <small class="text-white-50" style="font-size: 0.75rem;">Enter client credentials to unlock dealer prices</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="onWholesaleModalClose()"></button>
                </div>
                
                <div class="modal-body p-4 bg-white">
                    <div class="alert alert-info d-flex align-items-center p-2 mb-3 rounded-3" style="font-size: 0.82rem;">
                        <i class="fa-solid fa-circle-info me-2 text-primary fs-6"></i>
                        <div>ہول سیل ریٹس اور ڈسکاؤنٹ پرائسز دیکھنے کے لیے کلائنٹ لاگ ان کریں۔</div>
                    </div>

                    <div id="modalAlertBox" class="d-none alert alert-danger p-2 mb-3 rounded-3" style="font-size: 0.82rem;">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> <span id="modalAlertMsg"></span>
                    </div>
                    
                    <form id="wholesaleClientLoginForm" onsubmit="submitWholesaleLogin(event)">
                        <!-- Client Phone / ID -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary">Client Phone / Wholesale ID</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-phone text-muted"></i></span>
                                <input type="text" id="modalClientPhone" class="form-control border-start-0" placeholder="e.g. 0300-1234567" required value="0300-1234567">
                            </div>
                        </div>
                        
                        <!-- Client PIN / Password -->
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label fw-semibold small text-secondary">Wholesale Secret PIN / Password</label>
                                <span class="text-secondary small" style="cursor: pointer; font-size: 0.75rem;" onclick="toggleModalPassword()">
                                    <span id="modalPinToggleText"><i class="fa-regular fa-eye me-1"></i>Show</span>
                                </span>
                            </div>
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-key text-muted"></i></span>
                                <input type="password" id="modalClientPin" class="form-control border-start-0 border-end-0" placeholder="Enter PIN (e.g. 1234)" required value="1234">
                                <span class="input-group-text bg-light cursor-pointer" onclick="toggleModalPassword()" style="cursor: pointer;">
                                    <i class="fa-regular fa-eye text-muted" id="modalEyeIcon"></i>
                                </span>
                            </div>
                        </div>

                        <!-- 1-Click Fill Helper -->
                        <div class="p-2 mb-3 rounded-2 bg-light border text-center">
                            <small class="text-muted d-block mb-1" style="font-size: 0.75rem;">
                                <i class="fa-solid fa-bolt text-warning me-1"></i> <strong>Quick Wholesale Account:</strong>
                            </small>
                            <button type="button" class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size: 0.75rem;" onclick="fillModalClient('0300-1234567', '1234')">
                                <i class="fa-solid fa-shop me-1"></i> Ali Electronics (PIN: 1234)
                            </button>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light border w-50 py-2 fw-semibold" data-bs-dismiss="modal" onclick="onWholesaleModalClose()">
                                Cancel
                            </button>
                            <button type="submit" id="unlockBtn" class="btn btn-success w-50 py-2 fw-bold shadow-sm">
                                <i class="fa-solid fa-unlock me-1"></i> Unlock Rates
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="modal-footer bg-light justify-content-between py-2 px-3 border-top">
                    <small class="text-muted" style="font-size: 0.75rem;">Smart Mobile Wholesale Security</small>
                    <a href="clients.php" class="text-primary small text-decoration-none fw-semibold" style="font-size: 0.75rem;">Client Directory &rarr;</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Quick Stock Adjustment (IN / OUT) -->
    <div class="modal fade" id="quickStockModal" tabindex="-1" aria-labelledby="quickStockModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-success text-white py-3 border-0">
                    <div class="d-flex align-items-center">
                        <div class="rounded-3 bg-white bg-opacity-25 p-2 me-2 text-white">
                            <i class="fa-solid fa-right-left fs-5"></i>
                        </div>
                        <div>
                            <h6 class="modal-title fw-bold mb-0 text-white" id="quickStockModalLabel">Stock Adjustment (IN / OUT)</h6>
                            <small class="text-white-50" style="font-size: 0.75rem;">Record new inventory arrival or stock reduction</small>
                        </div>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4 bg-white">
                    <form id="quickStockForm" onsubmit="submitQuickStockAdjust(event)">
                        <!-- Select Item -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary">Select Product Item</label>
                            <select id="quickStockSku" class="form-select" required>
                                <?php foreach ($products as $s => $p): ?>
                                    <option value="<?php echo htmlspecialchars($s); ?>">
                                        <?php echo htmlspecialchars($p['name']); ?> (Current Stock: <?php echo $p['stock']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Action Type (IN or OUT) -->
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-secondary">Action Type</label>
                                <select id="quickStockType" class="form-select fw-semibold" required>
                                    <option value="in" class="text-success font-monospace">+ Stock IN (New Arrival)</option>
                                    <option value="out" class="text-danger font-monospace">- Stock OUT (Damaged/Return)</option>
                                </select>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small text-secondary">Quantity (Units)</label>
                                <input type="number" id="quickStockQty" class="form-control" min="1" value="10" required>
                            </div>
                        </div>

                        <!-- Note -->
                        <div class="mb-3">
                            <label class="form-label fw-semibold small text-secondary">Note / Remarks (Optional)</label>
                            <input type="text" id="quickStockNote" class="form-control" placeholder="e.g. Shipment received from Lahore Hall Road">
                        </div>

                        <div class="d-flex gap-2">
                            <button type="button" class="btn btn-light border w-50 py-2 fw-semibold" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" id="saveQuickStockBtn" class="btn btn-success w-50 py-2 fw-bold shadow-sm">
                                <i class="fa-solid fa-check me-1"></i> Update Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Role Switch Simulation & AJAX Modal JS -->
    <script>
        let wholesaleModalInstance = null;

        document.addEventListener('DOMContentLoaded', function() {
            const modalEl = document.getElementById('wholesaleLoginModal');
            wholesaleModalInstance = new bootstrap.Modal(modalEl);
        });

        // Whenever the user flips the switch:
        // Even if previously unlocked, every time they switch to wholesale, they MUST log in again!
        function handleViewModeSwitch(checkbox) {
            if (checkbox.checked) {
                // Keep the checkbox unchecked until the AJAX request successfully validates credentials
                checkbox.checked = false;

                // Clear previous alerts and reset PIN field
                const alertBox = document.getElementById('modalAlertBox');
                if (alertBox) alertBox.classList.add('d-none');
                
                const pinInput = document.getElementById('modalClientPin');
                if (pinInput) pinInput.value = '';

                // Show the modal
                if (!wholesaleModalInstance) {
                    wholesaleModalInstance = new bootstrap.Modal(document.getElementById('wholesaleLoginModal'));
                }
                wholesaleModalInstance.show();

                // Auto-focus PIN field
                setTimeout(() => {
                    if (pinInput) pinInput.focus();
                }, 400);
            } else {
                // When toggled OFF, immediately lock and return to retail view
                applyWholesaleView(false);
            }
        }

        function onWholesaleModalClose() {
            const checkbox = document.getElementById('roleSwitch');
            checkbox.checked = false;
            applyWholesaleView(false);
        }

        // AJAX verification function
        function submitWholesaleLogin(e) {
            e.preventDefault();
            const phoneInput = document.getElementById('modalClientPhone');
            const pinInput   = document.getElementById('modalClientPin');
            const alertBox   = document.getElementById('modalAlertBox');
            const alertMsg   = document.getElementById('modalAlertMsg');
            const unlockBtn  = document.getElementById('unlockBtn');

            const phone = phoneInput.value.trim();
            const pin   = pinInput.value.trim();

            if (!phone || !pin) {
                alertBox.classList.remove('d-none');
                alertMsg.textContent = "Phone number aur PIN dono darj karein.";
                return;
            }

            // Button loading spinner
            const originalBtnHtml = unlockBtn.innerHTML;
            unlockBtn.disabled = true;
            unlockBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-1"></i> Verifying...';
            alertBox.classList.add('d-none');

            // Send AJAX Request to ajax_wholesale_auth.php
            const formData = new FormData();
            formData.append('phone', phone);
            formData.append('pin', pin);

            fetch('ajax_wholesale_auth.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error('Server returned status: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                unlockBtn.disabled = false;
                unlockBtn.innerHTML = originalBtnHtml;

                if (data.success) {
                    // Hide Modal
                    if (wholesaleModalInstance) {
                        wholesaleModalInstance.hide();
                    }

                    // Turn Switch ON
                    const checkbox = document.getElementById('roleSwitch');
                    checkbox.checked = true;

                    // Apply Wholesale Pricing & Label
                    applyWholesaleView(true, data.client_name);

                    // Reset PIN field so next time it is re-prompted
                    pinInput.value = '';
                } else {
                    alertBox.classList.remove('d-none');
                    alertMsg.textContent = data.message || "Ghalat PIN! Barah-e-karam 1234 use karein.";
                    pinInput.focus();
                }
            })
            .catch(error => {
                console.error('AJAX Error:', error);
                unlockBtn.disabled = false;
                unlockBtn.innerHTML = originalBtnHtml;
                alertBox.classList.remove('d-none');
                alertMsg.textContent = "AJAX error: Server se rabta nahi ho saka. Dobara koshish karein.";
            });
        }

        function applyWholesaleView(enableWholesale, clientName = "Ali Electronics") {
            const roleLabel = document.getElementById('roleLabel');
            const retailPrices = document.querySelectorAll('.price-retail');
            const wholesalePrices = document.querySelectorAll('.price-wholesale');

            if (enableWholesale) {
                roleLabel.innerHTML = `<i class="fa-solid fa-lock-open text-warning me-1"></i> Wholesale View (${clientName})`;
                roleLabel.className = "badge bg-success ms-1 shadow-sm";
                
                retailPrices.forEach(el => el.classList.add('d-none'));
                wholesalePrices.forEach(el => el.classList.remove('d-none'));
            } else {
                roleLabel.textContent = "Retail View";
                roleLabel.className = "badge bg-secondary ms-1";
                
                retailPrices.forEach(el => el.classList.remove('d-none'));
                wholesalePrices.forEach(el => el.classList.add('d-none'));
            }
        }

        function fillModalClient(phone, pin) {
            document.getElementById('modalClientPhone').value = phone;
            const pinInput = document.getElementById('modalClientPin');
            pinInput.value = pin;
            pinInput.focus();
        }

        function toggleModalPassword() {
            const pinInput = document.getElementById('modalClientPin');
            const eyeIcon = document.getElementById('modalEyeIcon');
            const toggleText = document.getElementById('modalPinToggleText');
            
            if (pinInput.type === 'password') {
                pinInput.type = 'text';
                eyeIcon.classList.remove('fa-eye');
                eyeIcon.classList.add('fa-eye-slash');
                toggleText.innerHTML = '<i class="fa-regular fa-eye-slash me-1"></i>Hide';
            } else {
                pinInput.type = 'password';
                eyeIcon.classList.remove('fa-eye-slash');
                eyeIcon.classList.add('fa-eye');
                toggleText.innerHTML = '<i class="fa-regular fa-eye me-1"></i>Show';
            }
        }

        // Live Table Search Filter
        function filterCatalogTable() {
            const input = document.getElementById('catalogSearchInput');
            const filter = input.value.toLowerCase();
            const rows = document.querySelectorAll('#catalogTableBody tr');

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(filter) ? '' : 'none';
            });
        }

        // Live AJAX Stock IN / OUT Adjustment
        function submitQuickStockAdjust(e) {
            e.preventDefault();
            const sku = document.getElementById('quickStockSku').value;
            const type = document.getElementById('quickStockType').value;
            const qty = parseInt(document.getElementById('quickStockQty').value, 10);
            const note = document.getElementById('quickStockNote').value;
            const saveBtn = document.getElementById('saveQuickStockBtn');

            if (!sku || qty <= 0) return;

            const originalBtnHtml = saveBtn.innerHTML;
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin me-1"></i> Updating...';

            const formData = new FormData();
            formData.append('action', 'adjust_stock');
            formData.append('sku', sku);
            formData.append('type', type);
            formData.append('quantity', qty);
            formData.append('note', note);

            fetch('ajax_stock_action.php', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalBtnHtml;

                if (data.success) {
                    // Close Modal
                    const modalEl = document.getElementById('quickStockModal');
                    const modal = bootstrap.Modal.getInstance(modalEl);
                    if (modal) modal.hide();

                    // Update Metric Stat Cards Live!
                    const statIn = document.getElementById('statStockIn');
                    const statOut = document.getElementById('statStockOut');
                    if (statIn && data.stock_in_today !== undefined) {
                        statIn.textContent = new Intl.NumberFormat().format(data.stock_in_today);
                    }
                    if (statOut && data.stock_out_today !== undefined) {
                        statOut.textContent = new Intl.NumberFormat().format(data.stock_out_today);
                    }

                    // Update Table Row Stock Count
                    const targetRow = document.querySelector(`tr[data-sku="${sku}"]`);
                    if (targetRow) {
                        const stockCell = targetRow.querySelector('.stock-qty-badge');
                        if (stockCell) {
                            if (data.new_stock > 10) {
                                stockCell.className = "stock-qty-badge fw-bold text-success";
                                stockCell.textContent = `${data.new_stock} Pcs`;
                            } else if (data.new_stock > 0) {
                                stockCell.className = "stock-qty-badge fw-bold text-danger";
                                stockCell.textContent = `${data.new_stock} Pcs (Low)`;
                            } else {
                                stockCell.className = "stock-qty-badge";
                                stockCell.innerHTML = '<span class="badge bg-danger">Out of Stock</span>';
                            }
                        }
                    }

                    // Show Notification Alert
                    const notifBox = document.getElementById('stockNotificationBox');
                    const notifMsg = document.getElementById('stockNotificationMsg');
                    if (notifBox && notifMsg) {
                        notifMsg.innerHTML = `<strong>Kamyabi!</strong> ${data.message}`;
                        notifBox.classList.remove('d-none');
                        setTimeout(() => notifBox.classList.add('d-none'), 5000);
                    }
                } else {
                    alert(data.message || 'Error updating stock');
                }
            })
            .catch(err => {
                console.error(err);
                saveBtn.disabled = false;
                saveBtn.innerHTML = originalBtnHtml;
                alert('Server se rabta nahi ho saka.');
            });
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
