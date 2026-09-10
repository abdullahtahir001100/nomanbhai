<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/store_data.php';

$storeData = get_store_metrics();
$products  = $storeData['products'] ?? [];
$clients   = get_clients_list();

// Check if an item was sent to cart from inventory.php
$addSku = trim($_GET['add_sku'] ?? '');

// Check if a wholesale client was selected from clients.php
$preselectedClient = trim($_GET['client_id'] ?? '');

$pageTitle = 'Smart Mobile - POS / Counter Sale';
$activePage = 'pos';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

// Unique categories for POS filter tabs
$posCategories = [];
foreach ($products as $p) {
    if (!empty($p['category']) && !in_array($p['category'], $posCategories)) {
        $posCategories[] = $p['category'];
    }
}
sort($posCategories);
?>

<style>
/* POS Custom Enhancements */
.pos-item-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    background: #fff;
    transition: all 0.2s ease-in-out;
    cursor: pointer;
}
.pos-item-card:hover {
    border-color: #0d6efd;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.12) !important;
    transform: translateY(-2px);
}
.pos-item-card.border-primary {
    border-color: #0d6efd !important;
    background-color: #f8fbff;
}
.cart-items-list::-webkit-scrollbar {
    width: 6px;
}
.cart-items-list::-webkit-scrollbar-thumb {
    background-color: #dee2e6;
    border-radius: 4px;
}
.cursor-pointer {
    cursor: pointer;
}
.quick-cash-btn {
    font-size: 11px;
    padding: 2px 8px;
    border-radius: 6px;
}
/* 80mm Thermal Receipt Styling */
.thermal-receipt {
    width: 100%;
    max-width: 320px;
    margin: 0 auto;
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    color: #000;
    line-height: 1.35;
}
.thermal-receipt .dashed-line {
    border-top: 1px dashed #444;
    margin: 6px 0;
}
.thermal-receipt table {
    width: 100%;
}
.thermal-receipt th, .thermal-receipt td {
    padding: 2px 0;
}
@media print {
    body * {
        visibility: hidden !important;
    }
    #thermalReceiptArea, #thermalReceiptArea * {
        visibility: visible !important;
    }
    #thermalReceiptArea {
        position: absolute !important;
        left: 0 !important;
        top: 0 !important;
        width: 80mm !important;
        margin: 0 !important;
        padding: 0 !important;
    }
    .modal-footer, .btn-close {
        display: none !important;
    }
}
</style>

    <!-- Main Content Area -->
    <div id="main-content">
        <!-- Top Toolbar Banner -->
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3 bg-white p-3 rounded-3 shadow-sm">
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-cash-register text-primary me-2"></i>POS & Counter Billing</h5>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill">Terminal #1</span>
                <span class="badge bg-success-subtle text-success border border-success-subtle rounded-pill">Live Mode</span>
            </div>
            
            <div class="d-flex gap-2">
                <!-- Quick Add New Item for Sale -->
                <button class="btn btn-sm btn-outline-success" data-bs-toggle="modal" data-bs-target="#quickAddItemModal" title="Add a brand new item directly to catalog & sale">
                    <i class="fa-solid fa-plus-circle me-1"></i> Quick Item
                </button>
                <!-- Custom Service / Misc Fee Item -->
                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#customItemModal" title="Add repair fee or custom item to cart">
                    <i class="fa-solid fa-screwdriver-wrench me-1"></i> Custom / Service
                </button>
                <!-- Held Bills -->
                <button class="btn btn-sm btn-outline-secondary position-relative" data-bs-toggle="modal" data-bs-target="#heldOrdersModal" id="heldOrdersBtn">
                    <i class="fa-solid fa-pause me-1"></i> Held Bills
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" id="heldCountBadge">0</span>
                </button>
                <!-- Recent Sales History -->
                <button class="btn btn-sm btn-outline-dark" data-bs-toggle="modal" data-bs-target="#recentSalesModal" onclick="loadRecentSales()">
                    <i class="fa-solid fa-clock-rotate-left me-1"></i> Recent Sales
                </button>
            </div>
        </div>

        <div class="row g-3">
            
            <!-- Left Side: Product Selection Grid -->
            <div class="col-lg-7 col-xl-8">
                <!-- Search & Customer Selection Header -->
                <div class="bg-white p-3 rounded-3 shadow-sm mb-3">
                    <div class="row g-2 align-items-center">
                        <!-- Barcode Scanner / Text Search Input -->
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-barcode text-muted"></i></span>
                                <input type="text" id="posSearch" class="form-control bg-light border-start-0" placeholder="Scan barcode or type name (Enter to add)..." autofocus autocomplete="off">
                            </div>
                        </div>

                        <!-- Customer Selection (Walk-in vs Wholesale) -->
                        <div class="col-md-5">
                            <div class="input-group">
                                <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user-tag text-muted"></i></span>
                                <select class="form-select bg-light border-start-0" id="customerType">
                                    <option value="walkin" data-type="retail" data-phone="" data-balance="0">Customer: Walk-in Retail (Retail Rates)</option>
                                    <optgroup label="Registered Wholesale Clients (Wholesale Rates)">
                                        <?php foreach ($clients as $cid => $client): ?>
                                            <option value="<?php echo htmlspecialchars($cid); ?>" 
                                                    data-type="wholesale" 
                                                    data-phone="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>"
                                                    data-balance="<?php echo (float)($client['balance'] ?? 0); ?>"
                                                    data-limit="<?php echo (float)($client['limit'] ?? 0); ?>"
                                                    <?php echo ($preselectedClient === $cid) ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($client['name']); ?> (Bal: PKR <?php echo number_format($client['balance'] ?? 0); ?>)
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                </select>
                            </div>
                        </div>

                        <!-- Add New Client Button -->
                        <div class="col-md-2">
                            <button class="btn btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#newCustomerModal" title="Quick add new wholesale or retail customer">
                                <i class="fa-solid fa-user-plus"></i> New
                            </button>
                        </div>
                    </div>

                    <!-- Client Balance Indicator Alert (if Wholesale) -->
                    <div id="wholesaleClientInfo" class="alert alert-info py-2 px-3 mt-2 mb-0 d-none d-flex justify-content-between align-items-center small">
                        <div>
                            <i class="fa-solid fa-building me-1"></i> <strong id="selectedClientName">Ali Electronics</strong> 
                            <span class="text-muted ms-2"><i class="fa-solid fa-phone me-1"></i><span id="selectedClientPhone"></span></span>
                        </div>
                        <div>
                            <span>Current Khata Balance:</span> 
                            <strong class="text-danger ms-1" id="selectedClientBalance">PKR 0</strong>
                            <span class="badge bg-success ms-2">Wholesale Pricing Active</span>
                        </div>
                    </div>

                    <!-- Fast Category Tabs -->
                    <div class="d-flex gap-2 mt-3 overflow-auto pb-1" id="categoryTabs">
                        <button class="btn btn-sm btn-primary px-3 rounded-pill filter-cat active" data-cat="">All Items (<?php echo count($products); ?>)</button>
                        <?php foreach ($posCategories as $c): ?>
                            <button class="btn btn-sm btn-outline-secondary px-3 rounded-pill filter-cat" data-cat="<?php echo htmlspecialchars(strtolower($c)); ?>">
                                <?php echo htmlspecialchars($c); ?>
                            </button>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Products Grid (Dynamic from store_data) -->
                <div class="row g-3 overflow-auto" id="posProductsGrid" style="max-height: calc(100vh - 225px);">
                    <?php if (empty($products)): ?>
                        <div class="col-12 text-center py-5 text-muted">
                            <i class="fa-solid fa-boxes-packing fa-3x mb-3 text-secondary opacity-50"></i>
                            <p>No products available in inventory.</p>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#quickAddItemModal">
                                <i class="fa-solid fa-plus me-1"></i> Add Your First Item
                            </button>
                        </div>
                    <?php else: ?>
                        <?php foreach ($products as $sku => $p): 
                            $badge = !empty($p['category_badge']) ? $p['category_badge'] : get_category_badge($p['category'] ?? '');
                            $stock = (int)($p['stock'] ?? 0);
                            $ret   = (float)($p['retail_price'] ?? 0);
                            $ws    = (float)($p['wholesale_price'] ?? 0);
                        ?>
                            <div class="col-md-4 col-sm-6 pos-card-wrapper" 
                                 data-sku="<?php echo htmlspecialchars($sku); ?>"
                                 data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>"
                                 data-cat="<?php echo htmlspecialchars(strtolower($p['category'] ?? '')); ?>">
                                <div class="pos-item-card p-3 shadow-sm h-100 d-flex flex-column justify-content-between <?php echo ($addSku === $sku) ? 'border border-2 border-primary' : ''; ?>"
                                     onclick="addToCart('<?php echo htmlspecialchars($sku); ?>')">
                                    <div>
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($p['category'] ?? 'General'); ?></span>
                                            <?php if ($stock <= 0): ?>
                                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-circle-xmark me-1"></i>Out of Stock</span>
                                            <?php elseif ($stock <= 10): ?>
                                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fa-solid fa-triangle-exclamation me-1"></i><?php echo $stock; ?> in stock</span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i><?php echo $stock; ?> in stock</span>
                                            <?php endif; ?>
                                        </div>
                                        <h6 class="fw-bold mb-1 text-truncate" title="<?php echo htmlspecialchars($p['name']); ?>"><?php echo htmlspecialchars($p['name']); ?></h6>
                                        <small class="text-muted d-block mb-2"><i class="fa-solid fa-barcode me-1"></i>SKU: <?php echo htmlspecialchars($sku); ?></small>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                                        <div>
                                            <span class="fw-bold text-primary item-price-display" 
                                                  data-retail="<?php echo $ret; ?>" 
                                                  data-wholesale="<?php echo $ws; ?>">
                                                PKR <?php echo number_format($ret); ?>
                                            </span>
                                            <small class="text-muted d-block" style="font-size: 11px;">WS: PKR <?php echo number_format($ws); ?></small>
                                        </div>
                                        <button class="btn btn-sm btn-primary rounded-circle add-to-cart-btn" 
                                                data-sku="<?php echo htmlspecialchars($sku); ?>" 
                                                title="Add to cart"
                                                onclick="event.stopPropagation(); addToCart('<?php echo htmlspecialchars($sku); ?>');"
                                                <?php echo ($stock <= 0) ? 'disabled' : ''; ?>>
                                            <i class="fa-solid fa-plus"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Side: Order Cart & Billing Panel -->
            <div class="col-lg-5 col-xl-4">
                <div class="cart-container p-3 bg-white rounded-3 shadow-sm d-flex flex-column" style="min-height: calc(100vh - 120px);">
                    <!-- Cart Header -->
                    <div class="d-flex justify-content-between align-items-center border-bottom pb-2 mb-2">
                        <div class="d-flex align-items-center gap-2">
                            <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-cart-shopping me-2 text-primary"></i>Order Cart</h6>
                            <span class="badge bg-primary rounded-pill" id="cartCountBadge">0</span>
                        </div>
                        <div class="d-flex gap-2 align-items-center">
                            <span class="badge bg-danger cursor-pointer" id="clearCartBtn" title="Clear all items in cart">
                                <i class="fa-solid fa-trash me-1"></i> Clear
                            </span>
                        </div>
                    </div>

                    <!-- Cart Itemized List -->
                    <div class="cart-items-list mb-3 pe-1 flex-grow-1" id="cartItemsList" style="max-height: 290px; overflow-y: auto;">
                        <!-- Rendered dynamically via JavaScript -->
                    </div>

                    <!-- Payment Summary & Calculation Panel -->
                    <div class="border-top pt-2">
                        <!-- Subtotal -->
                        <div class="d-flex justify-content-between mb-1 small">
                            <span class="text-muted">Gross Subtotal:</span>
                            <span class="fw-semibold text-dark" id="cartSubtotalText">PKR 0</span>
                        </div>

                        <!-- Discount -->
                        <div class="d-flex justify-content-between align-items-center mb-2 small">
                            <span class="text-muted">Discount (PKR):</span>
                            <div class="input-group input-group-sm" style="width: 130px;">
                                <span class="input-group-text bg-light py-0">PKR</span>
                                <input type="number" id="cartDiscount" class="form-control form-control-sm text-end fw-semibold py-0" style="height: 28px;" value="0" min="0" step="10">
                            </div>
                        </div>

                        <!-- Net Total Payable (Highlight Banner) -->
                        <div class="d-flex justify-content-between align-items-center mb-3 fs-5 fw-bold border border-primary-subtle py-2 px-3 rounded bg-primary-subtle text-primary">
                            <span>Net Payable:</span>
                            <span id="cartPayableText">PKR 0</span>
                        </div>

                        <!-- Payment Method Toggle -->
                        <label class="form-label small fw-semibold text-muted mb-1 text-uppercase" style="letter-spacing: 0.5px;">Select Payment Mode:</label>
                        <div class="row g-2 mb-2">
                            <!-- 1. Cash -->
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="paymentMethod" id="cashRadio" value="cash" checked>
                                <label class="btn btn-outline-success w-100 btn-sm py-2" for="cashRadio">
                                    <i class="fa-solid fa-money-bill-wave d-block mb-1 fs-6"></i> Cash
                                </label>
                            </div>
                            <!-- 2. Bank / Online -->
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="paymentMethod" id="bankRadio" value="bank">
                                <label class="btn btn-outline-primary w-100 btn-sm py-2" for="bankRadio">
                                    <i class="fa-solid fa-building-columns d-block mb-1 fs-6"></i> Online
                                </label>
                            </div>
                            <!-- 3. Udhaar / Credit -->
                            <div class="col-4">
                                <input type="radio" class="btn-check" name="paymentMethod" id="creditRadio" value="credit">
                                <label class="btn btn-outline-warning w-100 btn-sm py-2 text-dark" for="creditRadio">
                                    <i class="fa-solid fa-clock-rotate-left d-block mb-1 fs-6"></i> Udhaar
                                </label>
                            </div>
                        </div>

                        <!-- Dynamic Payment Details Box -->
                        <div class="bg-light p-2 rounded-3 mb-3 border">
                            <!-- Cash Details Form -->
                            <div id="cashDetailsArea">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <label class="small fw-semibold mb-0">Cash Tendered:</label>
                                    <div class="d-flex gap-1">
                                        <button type="button" class="btn btn-xs btn-outline-secondary quick-cash-btn" onclick="setExactCash()">Exact</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary quick-cash-btn" onclick="addCashAmount(500)">+500</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary quick-cash-btn" onclick="addCashAmount(1000)">+1k</button>
                                        <button type="button" class="btn btn-xs btn-outline-secondary quick-cash-btn" onclick="addCashAmount(5000)">+5k</button>
                                    </div>
                                </div>
                                <div class="input-group input-group-sm mb-2">
                                    <span class="input-group-text bg-white">PKR</span>
                                    <input type="number" id="cashTendered" class="form-control form-control-sm text-end fw-bold fs-6" placeholder="0">
                                </div>
                                <div class="d-flex justify-content-between align-items-center p-2 rounded" id="changeReturnBox" style="background-color: #e8f5e9;">
                                    <span class="small fw-semibold text-success">Change to Return:</span>
                                    <span class="fw-bold text-success" id="changeReturnText">PKR 0</span>
                                </div>
                            </div>

                            <!-- Bank Details Form -->
                            <div id="bankDetailsArea" class="d-none">
                                <div class="row g-2">
                                    <div class="col-6">
                                        <label class="small fw-semibold mb-1">Payment Channel:</label>
                                        <select id="bankAccountSelect" class="form-select form-select-sm">
                                            <option value="JazzCash">JazzCash (0300-1234567)</option>
                                            <option value="EasyPaisa">EasyPaisa (0321-9876543)</option>
                                            <option value="Meezan Bank">Meezan Bank (PK88MEZN...)</option>
                                            <option value="HBL Bank">HBL Mobile (PK22HABB...)</option>
                                            <option value="Raast">Raast Instant Pay</option>
                                        </select>
                                    </div>
                                    <div class="col-6">
                                        <label class="small fw-semibold mb-1">Trx ID / Ref #:</label>
                                        <input type="text" id="bankTrxRef" class="form-control form-control-sm" placeholder="e.g. TRX-982341">
                                    </div>
                                </div>
                            </div>

                            <!-- Udhaar / Credit Details Form -->
                            <div id="creditDetailsArea" class="d-none">
                                <div class="small">
                                    <div class="d-flex justify-content-between">
                                        <span class="text-muted">Client Khata:</span>
                                        <strong id="creditClientNameDisplay">Walk-in Retail</strong>
                                    </div>
                                    <div class="text-muted mt-1" style="font-size: 11px;">
                                        <i class="fa-solid fa-circle-info text-primary me-1"></i> Bill amount will be automatically recorded in client's ledger account.
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons: Hold Bill & Complete Sale -->
                        <div class="row g-2">
                            <div class="col-5">
                                <button type="button" class="btn btn-outline-secondary w-100 py-2 fw-semibold" id="holdBillBtn">
                                    <i class="fa-solid fa-pause me-1"></i> Hold Bill
                                </button>
                            </div>
                            <div class="col-7">
                                <button type="button" class="btn btn-success w-100 py-2 fw-bold shadow-sm" id="completeSaleBtn">
                                    <i class="fa-solid fa-check-double me-1"></i> Pay & Print
                                </button>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </div>

    <!-- Modal: Quick Add New Item for Sale -->
    <div class="modal fade" id="quickAddItemModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-plus-circle text-success me-2"></i>Quick Add Item for Sale</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="quickAddItemForm">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                                <input type="text" id="quickItemName" class="form-control" placeholder="e.g. Oppo Reno 6 Charging Flex" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category</label>
                                <select id="quickItemCategory" class="form-select">
                                    <option value="Panels">Panels / Displays</option>
                                    <option value="Batteries">Batteries</option>
                                    <option value="Chargers">Chargers & Adapters</option>
                                    <option value="Accessories" selected>Accessories & Audio</option>
                                    <option value="Tools">Tools & Supplies</option>
                                    <option value="General">Other / General</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">SKU / Code</label>
                                <input type="text" id="quickItemSku" class="form-control" placeholder="Auto-generated if blank">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Stock Qty</label>
                                <input type="number" id="quickItemStock" class="form-control" value="10" min="1" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Wholesale (PKR)</label>
                                <input type="number" id="quickItemWs" class="form-control fw-bold text-success" placeholder="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Retail (PKR)</label>
                                <input type="number" id="quickItemRet" class="form-control fw-bold text-primary" placeholder="0" required>
                            </div>
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="quickItemAutoCart" checked>
                                    <label class="form-check-label fw-semibold" for="quickItemAutoCart">Immediately add to current sale cart</label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success px-4 fw-bold"><i class="fa-solid fa-save me-1"></i> Save & Add to Sale</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Custom Item / Repair Fee -->
    <div class="modal fade" id="customItemModal" tabindex="-1">
        <div class="modal-dialog modal-sm modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h6 class="modal-title fw-bold"><i class="fa-solid fa-screwdriver-wrench text-primary me-2"></i>Custom Item / Service</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="customItemForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold small">Description / Service</label>
                            <input type="text" id="customDesc" class="form-control form-control-sm" placeholder="e.g. Screen Replacement Labor / Software Flash" required>
                        </div>
                        <div class="row g-2 mb-3">
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Price (PKR)</label>
                                <input type="number" id="customPrice" class="form-control form-control-sm" placeholder="1000" min="1" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label fw-semibold small">Quantity</label>
                                <input type="number" id="customQty" class="form-control form-control-sm" value="1" min="1" required>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-sm btn-primary fw-bold">Add to Order</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Fast Add New Wholesale/Retail Customer -->
    <div class="modal fade" id="newCustomerModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-user-plus text-primary me-2"></i>Quick Add Customer / Client</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="quickAddClientForm">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Full Name / Shop Name <span class="text-danger">*</span></label>
                                <input type="text" id="newClientName" class="form-control" placeholder="e.g. Bilal Mobile Plaza" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Contact Phone Number</label>
                                <input type="text" id="newClientPhone" class="form-control" placeholder="0300-1234567">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Customer Account Type</label>
                                <select id="newClientType" class="form-select">
                                    <option value="wholesale" selected>Wholesale Client (Wholesale Rates)</option>
                                    <option value="retail">Retail Customer (Retail Rates)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Market / City</label>
                                <input type="text" id="newClientCity" class="form-control" placeholder="e.g. Hafeez Center, Lahore">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Credit Limit (PKR)</label>
                                <input type="number" id="newClientLimit" class="form-control" value="200000">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-save me-1"></i> Save & Select</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Held Orders Management -->
    <div class="modal fade" id="heldOrdersModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-layer-group text-primary me-2"></i>Parked / Held Bills</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3">
                    <div id="heldOrdersList" style="max-height: 350px; overflow-y: auto;">
                        <!-- Rendered via JS -->
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Recent Sales History -->
    <div class="modal fade" id="recentSalesModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-clock-rotate-left text-dark me-2"></i>Today's Completed Sales</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light small text-uppercase">
                                <tr>
                                    <th>Invoice #</th>
                                    <th>Time</th>
                                    <th>Customer</th>
                                    <th>Mode</th>
                                    <th>Items</th>
                                    <th>Total Paid</th>
                                    <th class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="recentSalesTableBody">
                                <tr><td colspan="7" class="text-center py-4 text-muted">Loading sales history...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Professional 80mm Thermal Receipt Simulation -->
    <div class="modal fade" id="receiptModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-success text-white py-2">
                    <h6 class="modal-title fw-bold mb-0"><i class="fa-solid fa-circle-check me-2"></i>Sale Finalized!</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" onclick="onReceiptModalClosed()"></button>
                </div>
                <div class="modal-body p-3 bg-white" id="thermalReceiptArea">
                    <div class="thermal-receipt text-center" id="thermalReceiptContent">
                        <!-- Store Header -->
                        <h5 class="fw-bold mb-0 text-uppercase tracking-wider">SMART MOBILE</h5>
                        <p class="mb-0 text-muted" style="font-size: 11px;">Wholesale & Retail Mobile Center</p>
                        <p class="mb-1 text-muted" style="font-size: 10px;">Hall Road Market, Lahore | Ph: 0300-1234567</p>
                        
                        <div class="dashed-line"></div>
                        
                        <!-- Meta info -->
                        <div class="d-flex justify-content-between text-start small">
                            <span id="rcptInvoiceNo">INV-2026-0001</span>
                            <span id="rcptTimestamp">2026-09-08 15:30</span>
                        </div>
                        <div class="d-flex justify-content-between text-start small">
                            <span>Cashier: Admin (Terminal 1)</span>
                            <span id="rcptCustomerType" class="badge bg-light text-dark border">Retail</span>
                        </div>
                        <div class="text-start small fw-semibold mt-1">
                            Cust: <span id="rcptCustomerName">Walk-in Retail Customer</span>
                            <span id="rcptCustomerPhone" class="text-muted ms-1"></span>
                        </div>

                        <div class="dashed-line"></div>

                        <!-- Itemized Items Table -->
                        <table class="text-start small mb-1">
                            <thead>
                                <tr style="border-bottom: 1px dashed #666;">
                                    <th style="width: 50%;">Item</th>
                                    <th class="text-center" style="width: 15%;">Qty</th>
                                    <th class="text-end" style="width: 35%;">Total</th>
                                </tr>
                            </thead>
                            <tbody id="rcptItemsTableBody">
                                <!-- Rendered dynamically -->
                            </tbody>
                        </table>

                        <div class="dashed-line"></div>

                        <!-- Bill Calculations -->
                        <div class="d-flex justify-content-between text-start small">
                            <span>Subtotal:</span>
                            <span id="rcptSubtotal">PKR 0</span>
                        </div>
                        <div class="d-flex justify-content-between text-start small">
                            <span>Discount:</span>
                            <span id="rcptDiscount">PKR 0</span>
                        </div>
                        <div class="d-flex justify-content-between text-start fw-bold fs-6 mt-1 pt-1 border-top border-dark">
                            <span>NET PAYABLE:</span>
                            <span id="rcptNetPayable">PKR 0</span>
                        </div>

                        <div class="dashed-line"></div>

                        <!-- Payment Method Breakdown -->
                        <div class="text-start small bg-light p-2 rounded">
                            <div class="d-flex justify-content-between">
                                <span>Payment Method:</span>
                                <strong class="text-uppercase" id="rcptPaymentMethod">CASH</strong>
                            </div>
                            <div class="d-flex justify-content-between" id="rcptTenderedRow">
                                <span>Amount Tendered:</span>
                                <span id="rcptAmountTendered">PKR 0</span>
                            </div>
                            <div class="d-flex justify-content-between fw-bold text-success" id="rcptChangeRow">
                                <span>Change Returned:</span>
                                <span id="rcptChangeReturned">PKR 0</span>
                            </div>
                            <div class="d-flex justify-content-between" id="rcptBankRefRow" style="display: none;">
                                <span>Bank / Trx Ref:</span>
                                <span id="rcptBankRef">-</span>
                            </div>
                        </div>

                        <div class="dashed-line"></div>

                        <!-- Footer notes -->
                        <div class="text-center mt-2" style="font-size: 10px; color: #555;">
                            <p class="mb-1">Thank you for your visit to Smart Mobile!</p>
                            <p class="mb-1">Display panels checking warranty: 3 days with seal intact.</p>
                            <p class="mb-0">Software & POS powered by Smart Mobile ERP</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-light p-2 d-flex justify-content-between">
                    <button type="button" class="btn btn-outline-dark btn-sm" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> Print Receipt
                    </button>
                    <button type="button" class="btn btn-primary btn-sm fw-bold px-3" data-bs-dismiss="modal" onclick="onReceiptModalClosed()">
                        <i class="fa-solid fa-plus me-1"></i> Next Sale
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Central Interactive Cart & POS Engine -->
    <script>
        // State
        let storeProducts = <?php echo json_encode($products); ?>;
        const autoAddSku  = <?php echo json_encode($addSku); ?>;
        let cart          = [];
        let heldOrders    = JSON.parse(localStorage.getItem('sm_pos_held_orders') || '[]');
        let pricingMode   = 'retail'; // 'retail' or 'wholesale'

        // DOM elements
        const posSearch       = document.getElementById('posSearch');
        const customerSelect  = document.getElementById('customerType');
        const cartItemsList   = document.getElementById('cartItemsList');
        const cartCountBadge  = document.getElementById('cartCountBadge');
        const cartSubtotalEl  = document.getElementById('cartSubtotalText');
        const cartDiscountEl  = document.getElementById('cartDiscount');
        const cartPayableEl   = document.getElementById('cartPayableText');
        const cashRadio       = document.getElementById('cashRadio');
        const bankRadio       = document.getElementById('bankRadio');
        const creditRadio     = document.getElementById('creditRadio');
        const cashDetailsArea = document.getElementById('cashDetailsArea');
        const bankDetailsArea = document.getElementById('bankDetailsArea');
        const creditDetailsArea = document.getElementById('creditDetailsArea');
        const cashTendered    = document.getElementById('cashTendered');
        const changeReturnText = document.getElementById('changeReturnText');
        const heldCountBadge  = document.getElementById('heldCountBadge');

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            updateHeldBadge();
            handleCustomerChange();

            // Auto-add requested item from inventory if passed
            if (autoAddSku && storeProducts[autoAddSku]) {
                addToCart(autoAddSku);
            } else {
                // Default first in-stock product if available
                const keys = Object.keys(storeProducts);
                for (let k of keys) {
                    if (storeProducts[k].stock > 0) {
                        addToCart(k);
                        break;
                    }
                }
            }

            // Customer change listener
            customerSelect.addEventListener('change', handleCustomerChange);

            // Discount listener
            cartDiscountEl.addEventListener('input', renderCart);

            // Cash tendered listener
            cashTendered.addEventListener('input', calculateCashChange);

            // Payment radio change
            document.querySelectorAll('input[name="paymentMethod"]').forEach(r => {
                r.addEventListener('change', handlePaymentMethodChange);
            });

            // Clear Cart Button
            document.getElementById('clearCartBtn').addEventListener('click', function() {
                if (cart.length > 0 && confirm('Are you sure you want to clear the active cart?')) {
                    cart = [];
                    renderCart();
                }
            });

            // Hold Bill Button
            document.getElementById('holdBillBtn').addEventListener('click', holdCurrentBill);

            // Complete Sale Button
            document.getElementById('completeSaleBtn').addEventListener('click', completeSale);

            // Barcode Search Input Keyup & Enter
            posSearch.addEventListener('input', filterProductCards);
            posSearch.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    addFirstFilteredProduct();
                }
            });

            // Fast Category Tabs
            document.querySelectorAll('.filter-cat').forEach(tab => {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.filter-cat').forEach(t => {
                        t.classList.remove('btn-primary', 'active');
                        t.classList.add('btn-outline-secondary');
                    });
                    this.classList.remove('btn-outline-secondary');
                    this.classList.add('btn-primary', 'active');
                    filterProductCards();
                });
            });

            // Quick Add Item Form
            document.getElementById('quickAddItemForm').addEventListener('submit', handleQuickAddItem);

            // Quick Add Customer Form
            document.getElementById('quickAddClientForm').addEventListener('submit', handleQuickAddClient);

            // Custom Item Form
            document.getElementById('customItemForm').addEventListener('submit', handleAddCustomItem);
        });

        function getUnitPrice(item) {
            if (item.isCustom) return item.price;
            const p = storeProducts[item.sku];
            if (!p) return 0;
            return (pricingMode === 'wholesale') ? (parseFloat(p.wholesale_price) || 0) : (parseFloat(p.retail_price) || 0);
        }

        function handleCustomerChange() {
            const opt = customerSelect.options[customerSelect.selectedIndex];
            const type = opt.getAttribute('data-type') || 'retail';
            pricingMode = type;

            const clientInfoBox = document.getElementById('wholesaleClientInfo');
            if (type === 'wholesale') {
                clientInfoBox.classList.remove('d-none');
                document.getElementById('selectedClientName').textContent = opt.text.split('(')[0].trim();
                document.getElementById('selectedClientPhone').textContent = opt.getAttribute('data-phone') || 'No Phone';
                const bal = parseFloat(opt.getAttribute('data-balance')) || 0;
                document.getElementById('selectedClientBalance').textContent = 'PKR ' + bal.toLocaleString();
                document.getElementById('creditClientNameDisplay').textContent = opt.text.split('(')[0].trim();
            } else {
                clientInfoBox.classList.add('d-none');
                document.getElementById('creditClientNameDisplay').textContent = 'Walk-in Retail (Khata not recommended)';
            }

            // Update catalog cards price display
            document.querySelectorAll('.item-price-display').forEach(el => {
                const ret = parseFloat(el.getAttribute('data-retail')) || 0;
                const ws  = parseFloat(el.getAttribute('data-wholesale')) || 0;
                el.textContent = 'PKR ' + ((pricingMode === 'wholesale') ? ws : ret).toLocaleString();
            });

            renderCart();
        }

        function handlePaymentMethodChange() {
            cashDetailsArea.classList.add('d-none');
            bankDetailsArea.classList.add('d-none');
            creditDetailsArea.classList.add('d-none');

            if (cashRadio.checked) {
                cashDetailsArea.classList.remove('d-none');
                calculateCashChange();
            } else if (bankRadio.checked) {
                bankDetailsArea.classList.remove('d-none');
            } else if (creditRadio.checked) {
                creditDetailsArea.classList.remove('d-none');
            }
        }

        function calculateCartTotals() {
            let subtotal = 0;
            let totalQty = 0;

            cart.forEach(item => {
                const unitPrice = getUnitPrice(item);
                subtotal += (unitPrice * item.qty);
                totalQty += item.qty;
            });

            const discount = Math.max(0, parseFloat(cartDiscountEl.value) || 0);
            const payable  = Math.max(0, subtotal - discount);

            return { subtotal, discount, payable, totalQty };
        }

        function renderCart() {
            const totals = calculateCartTotals();

            cartCountBadge.textContent = totals.totalQty.toString();
            cartSubtotalEl.textContent = 'PKR ' + totals.subtotal.toLocaleString();
            cartPayableEl.textContent  = 'PKR ' + totals.payable.toLocaleString();

            if (cart.length === 0) {
                cartItemsList.innerHTML = `
                    <div class="text-center py-5 text-muted">
                        <i class="fa-solid fa-cart-shopping fa-3x mb-2 text-secondary opacity-50"></i>
                        <p class="small mb-0">Cart is empty.<br>Click any product on the left or scan barcode to add.</p>
                    </div>
                `;
                calculateCashChange();
                return;
            }

            let html = '';
            cart.forEach((item, idx) => {
                let name = item.name;
                let sku = item.sku || 'CUSTOM';
                let maxStock = 999;
                
                if (!item.isCustom && storeProducts[item.sku]) {
                    name = storeProducts[item.sku].name;
                    maxStock = storeProducts[item.sku].stock;
                }

                const unitPrice = getUnitPrice(item);
                const lineTotal = unitPrice * item.qty;

                html += `
                    <div class="card mb-2 border-0 bg-light p-2 rounded-3 shadow-none">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="me-2 text-truncate" style="max-width: 190px;">
                                <h6 class="fw-semibold mb-0 fs-6 text-truncate" title="${name}">${name}</h6>
                                <small class="text-muted">PKR ${unitPrice.toLocaleString()} &times; ${item.qty}</small>
                            </div>
                            <span class="fw-bold text-dark">PKR ${lineTotal.toLocaleString()}</span>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 pt-1 border-top">
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary px-2" onclick="changeQty(${idx}, -1)">-</button>
                                <button type="button" class="btn btn-white bg-white px-3 fw-bold border-top border-bottom border-secondary" disabled>${item.qty}</button>
                                <button type="button" class="btn btn-outline-secondary px-2" onclick="changeQty(${idx}, 1)">+</button>
                            </div>
                            <button type="button" class="btn btn-sm text-danger p-0 border-0 bg-transparent" onclick="removeFromCart(${idx})">
                                <i class="fa-solid fa-trash-can me-1"></i> Remove
                            </button>
                        </div>
                    </div>
                `;
            });

            cartItemsList.innerHTML = html;
            calculateCashChange();
        }

        function addToCart(sku) {
            const p = storeProducts[sku];
            if (!p) return;
            if (p.stock <= 0) {
                alert('Item ' + p.name + ' is out of stock!');
                return;
            }

            const existing = cart.find(i => !i.isCustom && i.sku === sku);
            if (existing) {
                if (existing.qty < p.stock) {
                    existing.qty += 1;
                } else {
                    alert('Cannot exceed current in-stock quantity (' + p.stock + ' pcs).');
                    return;
                }
            } else {
                cart.push({
                    sku: sku,
                    name: p.name,
                    qty: 1,
                    isCustom: false
                });
            }
            renderCart();
        }

        function changeQty(index, delta) {
            const item = cart[index];
            if (!item) return;

            const newQty = item.qty + delta;
            if (newQty <= 0) {
                removeFromCart(index);
                return;
            }

            if (!item.isCustom && storeProducts[item.sku]) {
                const stock = storeProducts[item.sku].stock;
                if (newQty > stock) {
                    alert('Only ' + stock + ' pcs available in inventory.');
                    return;
                }
            }

            item.qty = newQty;
            renderCart();
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            renderCart();
        }

        // Cash Calculation
        function calculateCashChange() {
            const totals = calculateCartTotals();
            const payable = totals.payable;
            const tendered = parseFloat(cashTendered.value) || 0;
            const changeBox = document.getElementById('changeReturnBox');

            if (tendered <= 0) {
                changeReturnText.textContent = 'PKR 0';
                changeReturnText.className = 'fw-bold text-muted';
                changeBox.style.backgroundColor = '#f8f9fa';
                return;
            }

            const change = tendered - payable;
            if (change >= 0) {
                changeReturnText.textContent = 'PKR ' + change.toLocaleString();
                changeReturnText.className = 'fw-bold text-success';
                changeBox.style.backgroundColor = '#e8f5e9';
            } else {
                changeReturnText.textContent = 'Short: PKR ' + Math.abs(change).toLocaleString();
                changeReturnText.className = 'fw-bold text-danger';
                changeBox.style.backgroundColor = '#ffebee';
            }
        }

        function setExactCash() {
            const totals = calculateCartTotals();
            cashTendered.value = totals.payable;
            calculateCashChange();
        }

        function addCashAmount(amount) {
            const cur = parseFloat(cashTendered.value) || 0;
            cashTendered.value = cur + amount;
            calculateCashChange();
        }

        // Filter Product Cards
        function filterProductCards() {
            const query = (posSearch.value || '').toLowerCase().trim();
            const activeTab = document.querySelector('.filter-cat.active');
            const targetCat = activeTab ? (activeTab.getAttribute('data-cat') || '').toLowerCase().trim() : '';

            document.querySelectorAll('.pos-card-wrapper').forEach(card => {
                const sku  = (card.getAttribute('data-sku') || '').toLowerCase();
                const name = (card.getAttribute('data-name') || '').toLowerCase();
                const cat  = (card.getAttribute('data-cat') || '').toLowerCase();

                const matchQuery = !query || sku.includes(query) || name.includes(query);
                const matchCat   = !targetCat || cat === targetCat;

                if (matchQuery && matchCat) {
                    card.style.display = '';
                } else {
                    card.style.display = 'none';
                }
            });
        }

        function addFirstFilteredProduct() {
            const visible = Array.from(document.querySelectorAll('.pos-card-wrapper')).find(c => c.style.display !== 'none');
            if (visible) {
                const sku = visible.getAttribute('data-sku');
                addToCart(sku);
                posSearch.value = '';
                filterProductCards();
            }
        }

        // Hold Bill
        function holdCurrentBill() {
            if (cart.length === 0) {
                alert('Cart is empty. Nothing to hold.');
                return;
            }

            const opt = customerSelect.options[customerSelect.selectedIndex];
            const heldItem = {
                id: Date.now(),
                timestamp: new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
                customerText: opt.text,
                customerVal: customerSelect.value,
                discount: cartDiscountEl.value,
                cart: JSON.parse(JSON.stringify(cart)),
                totals: calculateCartTotals()
            };

            heldOrders.unshift(heldItem);
            localStorage.setItem('sm_pos_held_orders', JSON.stringify(heldOrders));
            updateHeldBadge();

            cart = [];
            cartDiscountEl.value = '0';
            renderCart();
            alert('Order held successfully! You can attend next customer.');
        }

        function updateHeldBadge() {
            if (heldCountBadge) {
                heldCountBadge.textContent = heldOrders.length.toString();
            }
            renderHeldOrdersList();
        }

        function renderHeldOrdersList() {
            const container = document.getElementById('heldOrdersList');
            if (!container) return;

            if (heldOrders.length === 0) {
                container.innerHTML = '<div class="text-center py-4 text-muted small">No orders currently held.</div>';
                return;
            }

            let html = '';
            heldOrders.forEach((h, idx) => {
                html += `
                    <div class="card p-3 mb-2 border rounded-3 bg-light d-flex flex-row justify-content-between align-items-center">
                        <div>
                            <h6 class="fw-bold mb-1">${h.customerText.split('(')[0]}</h6>
                            <small class="text-muted">${h.timestamp} &bull; ${h.cart.length} items &bull; <strong>PKR ${h.totals.payable.toLocaleString()}</strong></small>
                        </div>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-primary" onclick="resumeHeldOrder(${idx})"><i class="fa-solid fa-play me-1"></i> Resume</button>
                            <button class="btn btn-outline-danger" onclick="discardHeldOrder(${idx})"><i class="fa-solid fa-trash"></i></button>
                        </div>
                    </div>
                `;
            });
            container.innerHTML = html;
        }

        function resumeHeldOrder(idx) {
            const held = heldOrders[idx];
            if (!held) return;

            if (cart.length > 0 && !confirm('Active cart has items. Replace with held order?')) {
                return;
            }

            cart = held.cart;
            cartDiscountEl.value = held.discount || '0';
            customerSelect.value = held.customerVal;
            handleCustomerChange();

            heldOrders.splice(idx, 1);
            localStorage.setItem('sm_pos_held_orders', JSON.stringify(heldOrders));
            updateHeldBadge();

            const modal = bootstrap.Modal.getInstance(document.getElementById('heldOrdersModal'));
            if (modal) modal.hide();

            renderCart();
        }

        function discardHeldOrder(idx) {
            if (confirm('Discard this held order?')) {
                heldOrders.splice(idx, 1);
                localStorage.setItem('sm_pos_held_orders', JSON.stringify(heldOrders));
                updateHeldBadge();
            }
        }

        // Complete Sale / Checkout
        async function completeSale() {
            if (cart.length === 0) {
                alert('Cannot complete checkout: Cart is empty.');
                return;
            }

            const totals = calculateCartTotals();
            const opt = customerSelect.options[customerSelect.selectedIndex];
            const custType = opt.getAttribute('data-type') || 'retail';
            const clientId = (custType === 'wholesale') ? customerSelect.value : '';
            const custName = opt.text.split('(')[0].trim();
            const custPhone = opt.getAttribute('data-phone') || '';

            let paymentMethod = 'cash';
            let amountPaid = totals.payable;
            let changeReturned = 0;
            let bankAccount = '';
            let trxRef = '';

            if (cashRadio.checked) {
                paymentMethod = 'cash';
                const tendered = parseFloat(cashTendered.value) || 0;
                if (tendered < totals.payable && totals.payable > 0) {
                    if (!confirm('Cash entered (PKR ' + tendered + ') is less than net payable (PKR ' + totals.payable + '). Proceed with short amount?')) {
                        return;
                    }
                }
                amountPaid = tendered > 0 ? tendered : totals.payable;
                changeReturned = Math.max(0, amountPaid - totals.payable);
            } else if (bankRadio.checked) {
                paymentMethod = 'bank';
                bankAccount = document.getElementById('bankAccountSelect').value;
                trxRef = document.getElementById('bankTrxRef').value.trim();
                amountPaid = totals.payable;
            } else if (creditRadio.checked) {
                paymentMethod = 'credit';
                if (custType !== 'wholesale') {
                    alert('Udhaar / Credit sales are only allowed for registered Wholesale Clients. Please select a client or register one first.');
                    return;
                }
                amountPaid = 0;
            }

            // Build payload
            const payloadItems = cart.map(item => {
                const unitPrice = getUnitPrice(item);
                return {
                    sku: item.sku || 'CUSTOM',
                    name: item.name,
                    qty: item.qty,
                    unit_price: unitPrice,
                    line_total: unitPrice * item.qty
                };
            });

            const salePayload = {
                action: 'checkout',
                customer_name: custName,
                customer_phone: custPhone,
                customer_type: custType,
                client_id: clientId,
                payment_method: paymentMethod,
                amount_paid: amountPaid,
                change_returned: changeReturned,
                bank_account: bankAccount,
                transaction_ref: trxRef,
                subtotal: totals.subtotal,
                discount: totals.discount,
                total_payable: totals.payable,
                items: payloadItems
            };

            const btn = document.getElementById('completeSaleBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin me-1"></i> Processing...';

            try {
                const response = await fetch('ajax_pos_sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(salePayload)
                });
                const res = await response.json();

                if (res.success) {
                    // Update in-memory products stock
                    if (res.products) {
                        storeProducts = res.products;
                        updateProductGridStockBadges();
                    }

                    // Display Receipt Modal with actual invoice
                    showReceiptModal(res.invoice);
                } else {
                    alert('Sale Failed: ' + (res.message || 'Unknown error.'));
                }
            } catch (err) {
                alert('Connection Error: Could not finalize sale.');
                console.error(err);
            } finally {
                btn.disabled = false;
                btn.innerHTML = '<i class="fa-solid fa-check-double me-1"></i> Pay & Print';
            }
        }

        function showReceiptModal(invoice) {
            document.getElementById('rcptInvoiceNo').textContent = invoice.invoice_no;
            document.getElementById('rcptTimestamp').textContent = invoice.timestamp;
            document.getElementById('rcptCustomerName').textContent = invoice.customer_name;
            document.getElementById('rcptCustomerPhone').textContent = invoice.customer_phone ? '(' + invoice.customer_phone + ')' : '';
            document.getElementById('rcptCustomerType').textContent = (invoice.customer_type === 'wholesale') ? 'WHOLESALE' : 'RETAIL';

            // Items
            let itemsHtml = '';
            (invoice.items || []).forEach(it => {
                itemsHtml += `
                    <tr>
                        <td>${it.name}</td>
                        <td class="text-center">${it.qty}</td>
                        <td class="text-end">PKR ${Number(it.line_total).toLocaleString()}</td>
                    </tr>
                `;
            });
            document.getElementById('rcptItemsTableBody').innerHTML = itemsHtml;

            // Calculations
            document.getElementById('rcptSubtotal').textContent = 'PKR ' + Number(invoice.subtotal).toLocaleString();
            document.getElementById('rcptDiscount').textContent = 'PKR ' + Number(invoice.discount).toLocaleString();
            document.getElementById('rcptNetPayable').textContent = 'PKR ' + Number(invoice.total_payable).toLocaleString();

            // Payment
            const method = invoice.payment_method;
            const methodEl = document.getElementById('rcptPaymentMethod');
            const tenderedRow = document.getElementById('rcptTenderedRow');
            const changeRow = document.getElementById('rcptChangeRow');
            const bankRow = document.getElementById('rcptBankRefRow');

            if (method === 'cash') {
                methodEl.textContent = 'CASH';
                methodEl.className = 'text-uppercase text-success fw-bold';
                tenderedRow.style.display = 'flex';
                changeRow.style.display = 'flex';
                bankRow.style.display = 'none';
                document.getElementById('rcptAmountTendered').textContent = 'PKR ' + Number(invoice.amount_paid).toLocaleString();
                document.getElementById('rcptChangeReturned').textContent = 'PKR ' + Number(invoice.change_returned).toLocaleString();
            } else if (method === 'bank') {
                methodEl.textContent = 'ONLINE / BANK (' + (invoice.bank_account || 'Raast') + ')';
                methodEl.className = 'text-uppercase text-primary fw-bold';
                tenderedRow.style.display = 'none';
                changeRow.style.display = 'none';
                bankRow.style.display = 'flex';
                document.getElementById('rcptBankRef').textContent = invoice.transaction_ref || 'TRX-CONFIRMED';
            } else if (method === 'credit') {
                methodEl.textContent = 'UDHAAR / KHATA (CREDIT)';
                methodEl.className = 'text-uppercase text-danger fw-bold';
                tenderedRow.style.display = 'none';
                changeRow.style.display = 'none';
                bankRow.style.display = 'none';
            }

            const modal = new bootstrap.Modal(document.getElementById('receiptModal'));
            modal.show();
        }

        function onReceiptModalClosed() {
            // Reset cart for next customer
            cart = [];
            cartDiscountEl.value = '0';
            cashTendered.value = '';
            document.getElementById('bankTrxRef').value = '';
            renderCart();
        }

        function updateProductGridStockBadges() {
            document.querySelectorAll('.pos-card-wrapper').forEach(card => {
                const sku = card.getAttribute('data-sku');
                if (storeProducts[sku]) {
                    const stock = storeProducts[sku].stock;
                    const cardInner = card.querySelector('.pos-item-card');
                    const badgeContainer = cardInner.querySelector('.d-flex.justify-content-between.align-items-start.mb-2');
                    const addBtn = cardInner.querySelector('.add-to-cart-btn');

                    let badgeHtml = '';
                    const catBadge = storeProducts[sku].category_badge || 'bg-secondary';
                    const catName = storeProducts[sku].category || 'General';

                    if (stock <= 0) {
                        badgeHtml = `<span class="badge ${catBadge}">${catName}</span><span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-circle-xmark me-1"></i>Out of Stock</span>`;
                        if (addBtn) addBtn.disabled = true;
                    } else if (stock <= 10) {
                        badgeHtml = `<span class="badge ${catBadge}">${catName}</span><span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fa-solid fa-triangle-exclamation me-1"></i>${stock} in stock</span>`;
                        if (addBtn) addBtn.disabled = false;
                    } else {
                        badgeHtml = `<span class="badge ${catBadge}">${catName}</span><span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i>${stock} in stock</span>`;
                        if (addBtn) addBtn.disabled = false;
                    }
                    badgeContainer.innerHTML = badgeHtml;
                }
            });
        }

        // Quick Add Item
        async function handleQuickAddItem(e) {
            e.preventDefault();
            const name     = document.getElementById('quickItemName').value.trim();
            const sku      = document.getElementById('quickItemSku').value.trim();
            const category = document.getElementById('quickItemCategory').value;
            const stock    = parseInt(document.getElementById('quickItemStock').value) || 1;
            const ws       = parseFloat(document.getElementById('quickItemWs').value) || 0;
            const ret      = parseFloat(document.getElementById('quickItemRet').value) || 0;
            const autoCart = document.getElementById('quickItemAutoCart').checked;

            try {
                const res = await fetch('ajax_pos_sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'quick_add_product',
                        name: name,
                        sku: sku,
                        category: category,
                        stock: stock,
                        wholesale_price: ws,
                        retail_price: ret
                    })
                });
                const data = await res.json();
                if (data.success) {
                    const newProd = data.product;
                    storeProducts[newProd.sku] = newProd;

                    // Close modal & reset form
                    bootstrap.Modal.getInstance(document.getElementById('quickAddItemModal')).hide();
                    document.getElementById('quickAddItemForm').reset();

                    // Prepend card to POS grid
                    appendProductCardToGrid(newProd);

                    if (autoCart) {
                        addToCart(newProd.sku);
                    }
                    alert('Item ' + newProd.name + ' created and available in POS!');
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                alert('Failed to add product.');
            }
        }

        function appendProductCardToGrid(p) {
            const grid = document.getElementById('posProductsGrid');
            const col = document.createElement('div');
            col.className = 'col-md-4 col-sm-6 pos-card-wrapper';
            col.setAttribute('data-sku', p.sku);
            col.setAttribute('data-name', p.name.toLowerCase());
            col.setAttribute('data-cat', (p.category || '').toLowerCase());

            const price = (pricingMode === 'wholesale') ? p.wholesale_price : p.retail_price;

            col.innerHTML = `
                <div class="pos-item-card p-3 shadow-sm h-100 d-flex flex-column justify-content-between" onclick="addToCart('${p.sku}')">
                    <div>
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <span class="badge ${p.category_badge || 'bg-secondary'}">${p.category}</span>
                            <span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i>${p.stock} in stock</span>
                        </div>
                        <h6 class="fw-bold mb-1 text-truncate" title="${p.name}">${p.name}</h6>
                        <small class="text-muted d-block mb-2"><i class="fa-solid fa-barcode me-1"></i>SKU: ${p.sku}</small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mt-2 pt-2 border-top">
                        <div>
                            <span class="fw-bold text-primary item-price-display" data-retail="${p.retail_price}" data-wholesale="${p.wholesale_price}">
                                PKR ${Number(price).toLocaleString()}
                            </span>
                            <small class="text-muted d-block" style="font-size: 11px;">WS: PKR ${Number(p.wholesale_price).toLocaleString()}</small>
                        </div>
                        <button class="btn btn-sm btn-primary rounded-circle add-to-cart-btn" data-sku="${p.sku}" onclick="event.stopPropagation(); addToCart('${p.sku}');">
                            <i class="fa-solid fa-plus"></i>
                        </button>
                    </div>
                </div>
            `;
            grid.prepend(col);
        }

        // Custom Service Item
        function handleAddCustomItem(e) {
            e.preventDefault();
            const desc  = document.getElementById('customDesc').value.trim();
            const price = parseFloat(document.getElementById('customPrice').value) || 0;
            const qty   = parseInt(document.getElementById('customQty').value) || 1;

            cart.push({
                sku: 'CUSTOM-' + Date.now(),
                name: desc,
                price: price,
                qty: qty,
                isCustom: true
            });

            bootstrap.Modal.getInstance(document.getElementById('customItemModal')).hide();
            document.getElementById('customItemForm').reset();
            renderCart();
        }

        // Quick Add Client
        async function handleQuickAddClient(e) {
            e.preventDefault();
            const name  = document.getElementById('newClientName').value.trim();
            const phone = document.getElementById('newClientPhone').value.trim();
            const type  = document.getElementById('newClientType').value;
            const city  = document.getElementById('newClientCity').value.trim();
            const limit = parseFloat(document.getElementById('newClientLimit').value) || 100000;

            try {
                const res = await fetch('ajax_pos_sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'quick_add_client',
                        name: name,
                        phone: phone,
                        type: type,
                        city: city,
                        limit: limit
                    })
                });
                const data = await res.json();
                if (data.success) {
                    const c = data.client;
                    const opt = document.createElement('option');
                    opt.value = c.id;
                    opt.setAttribute('data-type', c.type);
                    opt.setAttribute('data-phone', c.phone);
                    opt.setAttribute('data-balance', c.balance);
                    opt.setAttribute('data-limit', c.limit);
                    opt.text = c.name + ' (Bal: PKR ' + Number(c.balance).toLocaleString() + ')';
                    opt.selected = true;

                    customerSelect.appendChild(opt);
                    handleCustomerChange();

                    bootstrap.Modal.getInstance(document.getElementById('newCustomerModal')).hide();
                    document.getElementById('quickAddClientForm').reset();
                    alert('Customer ' + c.name + ' added and selected!');
                } else {
                    alert('Error: ' + data.message);
                }
            } catch (err) {
                alert('Failed to add client.');
            }
        }

        // Recent Sales
        async function loadRecentSales() {
            const tbody = document.getElementById('recentSalesTableBody');
            tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading sales history...</td></tr>';

            try {
                const res = await fetch('ajax_pos_sale.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'get_recent_sales' })
                });
                const data = await res.json();

                if (!data.sales || data.sales.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-muted">No sales recorded yet today.</td></tr>';
                    return;
                }

                let html = '';
                data.sales.forEach(s => {
                    let badgeClass = 'bg-success-subtle text-success border border-success-subtle';
                    if (s.payment_method === 'bank') badgeClass = 'bg-primary-subtle text-primary border border-primary-subtle';
                    if (s.payment_method === 'credit') badgeClass = 'bg-danger-subtle text-danger border border-danger-subtle';

                    const itemCount = (s.items || []).reduce((acc, it) => acc + (it.qty || 1), 0);

                    html += `
                        <tr>
                            <td class="fw-bold"><code>${s.invoice_no}</code></td>
                            <td><small class="text-muted">${(s.timestamp || '').split(' ')[1] || s.timestamp}</small></td>
                            <td>
                                <div class="fw-semibold text-truncate" style="max-width: 140px;">${s.customer_name}</div>
                            </td>
                            <td><span class="badge ${badgeClass} text-uppercase">${s.payment_method}</span></td>
                            <td>${itemCount} pcs</td>
                            <td class="fw-bold text-dark">PKR ${Number(s.total_payable).toLocaleString()}</td>
                            <td class="text-center">
                                <button class="btn btn-xs btn-outline-secondary" onclick='showReceiptModal(${JSON.stringify(s)})' title="View & Print Slip">
                                    <i class="fa-solid fa-receipt"></i> Slip
                                </button>
                            </td>
                        </tr>
                    `;
                });
                tbody.innerHTML = html;
            } catch (err) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center py-4 text-danger">Failed to fetch sales history.</td></tr>';
            }
        }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
