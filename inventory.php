<?php
require_once __DIR__ . '/includes/auth.php';
require_login();
require_once __DIR__ . '/includes/store_data.php';

// Handle Stock Adjustment form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Stock Adjust (IN / OUT)
    if ($action === 'stock_adjust') {
        $sku  = trim($_POST['sku'] ?? '');
        $type = trim($_POST['type'] ?? 'in');
        $qty  = max(1, (int)($_POST['quantity'] ?? 1));
        $note = trim($_POST['note'] ?? '');
        if (!empty($sku)) {
            $res = record_stock_adjustment($sku, $type, $qty, $note);
            if ($res['success']) {
                header("Location: inventory.php?adjusted=1&sku=" . urlencode($sku) . "&type=" . urlencode($type) . "&qty=" . $qty);
                exit;
            } else {
                header("Location: inventory.php?err=" . urlencode($res['message']));
                exit;
            }
        }
    }

    // 2. Add New Product
    if ($action === 'add_product') {
        $name           = trim($_POST['name'] ?? '');
        $sku            = trim($_POST['sku'] ?? '');
        $category       = trim($_POST['category'] ?? 'General');
        $stock          = max(0, (int)($_POST['stock'] ?? 0));
        $cost_price     = max(0.0, (float)($_POST['cost_price'] ?? 0));
        $wholesale_price= max(0.0, (float)($_POST['wholesale_price'] ?? 0));
        $retail_price   = max(0.0, (float)($_POST['retail_price'] ?? 0));

        $res = add_product([
            'name'            => $name,
            'sku'             => $sku,
            'category'        => $category,
            'stock'           => $stock,
            'cost_price'      => $cost_price,
            'wholesale_price' => $wholesale_price,
            'retail_price'    => $retail_price
        ]);

        if ($res['success']) {
            header("Location: inventory.php?added=1&sku=" . urlencode($res['product']['sku']));
            exit;
        } else {
            header("Location: inventory.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 3. Edit Product
    if ($action === 'edit_product') {
        $orig_sku       = trim($_POST['orig_sku'] ?? '');
        $name           = trim($_POST['name'] ?? '');
        $sku            = trim($_POST['sku'] ?? $orig_sku);
        $category       = trim($_POST['category'] ?? 'General');
        $stock          = max(0, (int)($_POST['stock'] ?? 0));
        $cost_price     = max(0.0, (float)($_POST['cost_price'] ?? 0));
        $wholesale_price= max(0.0, (float)($_POST['wholesale_price'] ?? 0));
        $retail_price   = max(0.0, (float)($_POST['retail_price'] ?? 0));

        $res = edit_product($orig_sku, [
            'name'            => $name,
            'sku'             => $sku,
            'category'        => $category,
            'stock'           => $stock,
            'cost_price'      => $cost_price,
            'wholesale_price' => $wholesale_price,
            'retail_price'    => $retail_price
        ]);

        if ($res['success']) {
            header("Location: inventory.php?edited=1&sku=" . urlencode($sku));
            exit;
        } else {
            header("Location: inventory.php?err=" . urlencode($res['message']));
            exit;
        }
    }

    // 4. Delete Product (POST)
    if ($action === 'delete_product') {
        $sku = trim($_POST['sku'] ?? '');
        if (!empty($sku)) {
            $res = delete_product($sku);
            if ($res['success']) {
                header("Location: inventory.php?deleted=1&sku=" . urlencode($sku));
                exit;
            } else {
                header("Location: inventory.php?err=" . urlencode($res['message']));
                exit;
            }
        }
    }
}

// 5. Delete Product (GET fallback)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'delete_product') {
    $sku = trim($_GET['sku'] ?? '');
    if (!empty($sku)) {
        $res = delete_product($sku);
        if ($res['success']) {
            header("Location: inventory.php?deleted=1&sku=" . urlencode($sku));
            exit;
        } else {
            header("Location: inventory.php?err=" . urlencode($res['message']));
            exit;
        }
    }
}

$pageTitle = 'Smart Mobile - Inventory & Stock Management';
$activePage = 'inventory';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';

$storeData = get_store_metrics();
$products  = $storeData['products'] ?? [];
$metrics   = calculate_inventory_metrics($products);

// Format total valuation display
$totalStockValue = $metrics['total_stock_value'];
if ($totalStockValue >= 1000000) {
    $formattedStockValue = 'PKR ' . number_format($totalStockValue / 1000000, 2) . 'M';
} else {
    $formattedStockValue = 'PKR ' . number_format($totalStockValue);
}

// Get unique categories for dropdown filter
$categories = [];
foreach ($products as $p) {
    if (!empty($p['category']) && !in_array($p['category'], $categories)) {
        $categories[] = $p['category'];
    }
}
sort($categories);
?>

    <!-- Main Content Area -->
    <div id="main-content">

        <!-- Notification Alerts -->
        <?php if (isset($_GET['added'])): ?>
            <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-circle-check fs-4 me-3 text-success"></i>
                <div>
                    <strong>New Item Added!</strong> Product <code><?php echo htmlspecialchars($_GET['sku'] ?? ''); ?></code> has been successfully added to catalog & stock updated.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['edited'])): ?>
            <div class="alert alert-info alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-pen-to-square fs-4 me-3 text-info"></i>
                <div>
                    <strong>Item Updated!</strong> Product specifications, rates and stock have been successfully updated.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['deleted'])): ?>
            <div class="alert alert-warning alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-trash-can fs-4 me-3 text-danger"></i>
                <div>
                    <strong>Item Removed!</strong> Product has been deleted from your inventory.
                </div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['adjusted'])): ?>
            <div class="alert alert-primary alert-dismissible fade show border-0 shadow-sm d-flex align-items-center mb-3" role="alert">
                <i class="fa-solid fa-right-left fs-4 me-3 text-primary"></i>
                <div>
                    <strong>Stock Adjustment Recorded!</strong> 
                    <?php echo (isset($_GET['type']) && $_GET['type'] === 'in') ? 'Stock IN (+'.(int)$_GET['qty'].')' : 'Stock OUT (-'.(int)$_GET['qty'].')'; ?>
                    applied for <code><?php echo htmlspecialchars($_GET['sku'] ?? ''); ?></code>.
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

        <!-- Top Header Bar -->
        <header class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 bg-white p-3 rounded-3 shadow-sm">
            <div>
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-warehouse text-primary me-2"></i>Inventory & Stock Control</h5>
                <small class="text-muted">Manage real-time products, retail/wholesale pricing, and stock entries</small>
            </div>
            
            <div class="d-flex gap-2">
                <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#stockAdjustModal">
                    <i class="fa-solid fa-right-left me-1 text-primary"></i> Stock IN / OUT
                </button>
                <button class="btn btn-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                    <i class="fa-solid fa-plus me-1"></i> Add New Item
                </button>
            </div>
        </header>

        <!-- Metric Stat Cards (100% Dynamic PHP) -->
        <div class="row g-3 mb-4">
            <!-- 1. Total Items Listed -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-primary text-white p-3 h-100 border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Total Items Listed</small>
                            <h3 class="fw-bold mb-0 mt-1"><?php echo number_format($metrics['total_items']); ?> Items</h3>
                            <small class="text-white-50"><?php echo number_format($metrics['total_units']); ?> total units in stock</small>
                        </div>
                        <i class="fa-solid fa-boxes-stacked fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- 2. Total Stock Value -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-success text-white p-3 h-100 border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Total Stock Value</small>
                            <h3 class="fw-bold mb-0 mt-1" title="Exact: PKR <?php echo number_format($totalStockValue); ?>"><?php echo $formattedStockValue; ?></h3>
                            <small class="text-white-50">PKR <?php echo number_format($totalStockValue); ?></small>
                        </div>
                        <i class="fa-solid fa-wallet fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- 3. Low Stock Alert -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-warning text-dark p-3 h-100 border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-dark-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Low Stock Alert</small>
                            <h3 class="fw-bold mb-0 mt-1"><?php echo number_format($metrics['low_stock_count']); ?> Items</h3>
                            <small class="text-muted fw-semibold">Quantity &le; 10 units</small>
                        </div>
                        <i class="fa-solid fa-triangle-exclamation fa-2x opacity-50 text-dark"></i>
                    </div>
                </div>
            </div>

            <!-- 4. Out of Stock -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm bg-danger text-white p-3 h-100 border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">Out of Stock</small>
                            <h3 class="fw-bold mb-0 mt-1"><?php echo number_format($metrics['out_of_stock_count']); ?> Items</h3>
                            <small class="text-white-50">Zero stock / Urgent reorder</small>
                        </div>
                        <i class="fa-solid fa-circle-xmark fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Inventory List Table Card -->
        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-header bg-white py-3">
                <div class="row g-2 align-items-center">
                    <!-- Search Input -->
                    <div class="col-md-4">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="inventorySearch" class="form-control bg-light border-start-0" placeholder="Search SKU, item name, or category...">
                        </div>
                    </div>

                    <!-- Category Filter -->
                    <div class="col-md-3">
                        <select id="categoryFilter" class="form-select bg-light">
                            <option value="">All Categories (<?php echo count($products); ?> items)</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Stock Status Filter -->
                    <div class="col-md-3">
                        <select id="stockStatusFilter" class="form-select bg-light">
                            <option value="">Filter by Stock Status</option>
                            <option value="in_stock">In Stock (&gt; 10)</option>
                            <option value="low_stock">Low Stock (1 - 10)</option>
                            <option value="out_stock">Out of Stock (0)</option>
                        </select>
                    </div>

                    <!-- Actions -->
                    <div class="col-md-2 text-end">
                        <button class="btn btn-outline-secondary w-100" onclick="window.print()"><i class="fa-solid fa-print me-1"></i> Print / PDF</button>
                    </div>
                </div>
            </div>
            
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="inventoryTable">
                        <thead class="table-light text-secondary small text-uppercase">
                            <tr>
                                <th>Item & SKU</th>
                                <th>Category</th>
                                <th>Cost Price</th>
                                <th>Wholesale Rate</th>
                                <th>Retail Rate</th>
                                <th>In Stock</th>
                                <th>Status</th>
                                <th class="text-center" style="width: 170px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-box-open fa-3x mb-3 text-secondary opacity-50"></i>
                                        <p class="mb-2 fs-6">No products found in the inventory.</p>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                            <i class="fa-solid fa-plus me-1"></i> Add Your First Item
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $sku => $p): 
                                    $stock = (int)($p['stock'] ?? 0);
                                    $cost  = (float)($p['cost_price'] ?? 0);
                                    $ws    = (float)($p['wholesale_price'] ?? 0);
                                    $ret   = (float)($p['retail_price'] ?? 0);
                                    $badge = !empty($p['category_badge']) ? $p['category_badge'] : get_category_badge($p['category'] ?? '');
                                    
                                    if ($stock <= 0) {
                                        $statusClass = 'out_stock';
                                        $statusBadge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="fa-solid fa-circle-xmark me-1"></i>Out of Stock</span>';
                                        $stockDisplay = '<span class="fw-bold text-danger">0 Pcs</span>';
                                    } elseif ($stock <= 10) {
                                        $statusClass = 'low_stock';
                                        $statusBadge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low Stock</span>';
                                        $stockDisplay = '<span class="fw-bold text-danger">' . $stock . ' Pcs</span>';
                                    } else {
                                        $statusClass = 'in_stock';
                                        $statusBadge = '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="fa-solid fa-circle-check me-1"></i>In Stock</span>';
                                        $stockDisplay = '<span class="fw-bold text-dark">' . $stock . ' Pcs</span>';
                                    }
                                ?>
                                    <tr class="product-row" 
                                        data-sku="<?php echo htmlspecialchars($sku); ?>"
                                        data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>"
                                        data-category="<?php echo htmlspecialchars($p['category']); ?>"
                                        data-status="<?php echo $statusClass; ?>">
                                        <td>
                                            <div class="fw-bold text-dark"><?php echo htmlspecialchars($p['name']); ?></div>
                                            <small class="text-muted"><i class="fa-solid fa-barcode me-1"></i>SKU: <strong><?php echo htmlspecialchars($sku); ?></strong></small>
                                        </td>
                                        <td>
                                            <span class="badge <?php echo $badge; ?>"><?php echo htmlspecialchars($p['category'] ?? 'General'); ?></span>
                                        </td>
                                        <td>
                                            <span class="text-secondary fw-semibold">PKR <?php echo number_format($cost); ?></span>
                                        </td>
                                        <td>
                                            <span class="fw-bold text-success">PKR <?php echo number_format($ws); ?></span>
                                        </td>
                                        <td>
                                            <span class="fw-semibold text-primary">PKR <?php echo number_format($ret); ?></span>
                                        </td>
                                        <td>
                                            <?php echo $stockDisplay; ?>
                                        </td>
                                        <td>
                                            <?php echo $statusBadge; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <!-- Cart / Sell in POS -->
                                                <a href="pos.php?add_sku=<?php echo urlencode($sku); ?>" 
                                                   class="btn btn-outline-primary" 
                                                   title="Add to Cart / Sell in POS">
                                                    <i class="fa-solid fa-cart-plus"></i>
                                                </a>
                                                
                                                <!-- Edit Item Button -->
                                                <button type="button" 
                                                        class="btn btn-outline-secondary edit-product-btn" 
                                                        data-sku="<?php echo htmlspecialchars($sku); ?>"
                                                        data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                                        data-category="<?php echo htmlspecialchars($p['category'] ?? ''); ?>"
                                                        data-stock="<?php echo $stock; ?>"
                                                        data-cost="<?php echo $cost; ?>"
                                                        data-wholesale="<?php echo $ws; ?>"
                                                        data-retail="<?php echo $ret; ?>"
                                                        title="Edit Item & Pricing">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </button>

                                                <!-- Delete Item Button -->
                                                <button type="button" 
                                                        class="btn btn-outline-danger delete-product-btn" 
                                                        data-sku="<?php echo htmlspecialchars($sku); ?>"
                                                        data-name="<?php echo htmlspecialchars($p['name']); ?>"
                                                        title="Delete Item">
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
                <small class="text-muted" id="tableSummary">Showing <?php echo count($products); ?> registered products in catalog</small>
                <div class="small text-muted">
                    <span class="badge bg-success-subtle text-success border border-success-subtle me-1"><?php echo $metrics['total_items'] - $metrics['low_stock_count'] - $metrics['out_of_stock_count']; ?> Normal</span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle me-1"><?php echo $metrics['low_stock_count']; ?> Low</span>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?php echo $metrics['out_of_stock_count']; ?> Empty</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Add New Item -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Add New Product to Inventory</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="inventory.php" method="POST">
                    <input type="hidden" name="action" value="add_product">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" class="form-control" placeholder="e.g. Vivo V20 AMOLED Display Panel" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">SKU / Item Code</label>
                                <input type="text" name="sku" class="form-control" placeholder="e.g. DISP-VIV-V20 (Auto if blank)">
                                <small class="text-muted">Leave empty to auto-generate</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select name="category" class="form-select" required>
                                    <option value="Panels">Panels / Displays / Touch</option>
                                    <option value="Batteries">Batteries</option>
                                    <option value="Chargers">Chargers & Adapters</option>
                                    <option value="Accessories">Accessories & Audio</option>
                                    <option value="Tools">Repair Tools & Supplies</option>
                                    <option value="Motherboards">Motherboards & ICs</option>
                                    <option value="General">Other / General</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Initial Quantity (Stock IN)</label>
                                <input type="number" name="stock" class="form-control" min="0" value="10" placeholder="0">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Cost Price (Purchase PKR)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">PKR</span>
                                    <input type="number" name="cost_price" class="form-control" step="0.01" placeholder="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Wholesale Rate (PKR) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-success fw-bold">PKR</span>
                                    <input type="number" name="wholesale_price" class="form-control fw-bold text-success" step="0.01" placeholder="0" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Retail Rate (PKR) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-primary fw-bold">PKR</span>
                                    <input type="number" name="retail_price" class="form-control fw-bold text-primary" step="0.01" placeholder="0" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-save me-1"></i> Save & Add to Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Edit Product & Rates -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-pen-to-square me-2 text-info"></i>Edit Product & Pricing Rates</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="inventory.php" method="POST">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="orig_sku" id="editOrigSku">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label fw-semibold">Item Name <span class="text-danger">*</span></label>
                                <input type="text" name="name" id="editName" class="form-control" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">SKU / Item Code <span class="text-danger">*</span></label>
                                <input type="text" name="sku" id="editSku" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Category <span class="text-danger">*</span></label>
                                <select name="category" id="editCategory" class="form-select" required>
                                    <option value="Panels">Panels / Displays / Touch</option>
                                    <option value="Batteries">Batteries</option>
                                    <option value="Chargers">Chargers & Adapters</option>
                                    <option value="Accessories">Accessories & Audio</option>
                                    <option value="Tools">Repair Tools & Supplies</option>
                                    <option value="Motherboards">Motherboards & ICs</option>
                                    <option value="General">Other / General</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Current In-Stock Quantity</label>
                                <input type="number" name="stock" id="editStock" class="form-control" min="0" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Cost Price (PKR)</label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light">PKR</span>
                                    <input type="number" name="cost_price" id="editCost" class="form-control" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Wholesale Rate (PKR) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-success fw-bold">PKR</span>
                                    <input type="number" name="wholesale_price" id="editWholesale" class="form-control fw-bold text-success" step="0.01" required>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label fw-semibold">Retail Rate (PKR) <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light text-primary fw-bold">PKR</span>
                                    <input type="number" name="retail_price" id="editRetail" class="form-control fw-bold text-primary" step="0.01" required>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info text-white px-4 fw-bold"><i class="fa-solid fa-check me-1"></i> Update Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Delete Confirmation -->
    <div class="modal fade" id="deleteProductModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-trash-can me-2"></i>Confirm Delete Product</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="inventory.php" method="POST">
                    <input type="hidden" name="action" value="delete_product">
                    <input type="hidden" name="sku" id="deleteSku">
                    <div class="modal-body p-4 text-center">
                        <i class="fa-solid fa-triangle-exclamation text-danger fa-3x mb-3"></i>
                        <h5 class="fw-bold mb-2">Delete <span id="deleteProductName" class="text-danger"></span>?</h5>
                        <p class="text-muted mb-0">Are you sure you want to remove this product from the inventory? This will remove its pricing and stock records.</p>
                    </div>
                    <div class="modal-footer bg-light justify-content-center">
                        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger px-4 fw-bold"><i class="fa-solid fa-trash me-1"></i> Yes, Delete Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Stock IN / OUT Adjustment -->
    <div class="modal fade" id="stockAdjustModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-light">
                    <h5 class="modal-title fw-bold text-dark"><i class="fa-solid fa-right-left me-2 text-primary"></i>Stock Adjustment (IN / OUT)</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form action="inventory.php" method="POST">
                    <input type="hidden" name="action" value="stock_adjust">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label fw-semibold">Select Product <span class="text-danger">*</span></label>
                                <select name="sku" id="adjustSkuSelect" class="form-select" required>
                                    <?php foreach ($products as $skuCode => $p): ?>
                                        <option value="<?php echo htmlspecialchars($skuCode); ?>">
                                            <?php echo htmlspecialchars($p['name']); ?> — Current Stock: <?php echo $p['stock']; ?> pcs
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Action Type <span class="text-danger">*</span></label>
                                <select name="type" id="adjustTypeSelect" class="form-select" required>
                                    <option value="in" class="text-success fw-bold">+ Stock IN (New Purchase / Arrival)</option>
                                    <option value="out" class="text-danger fw-bold">- Stock OUT (Return / Damaged / Sale)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="quantity" class="form-control" min="1" value="10" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Note / Remarks (Optional)</label>
                                <input type="text" name="note" class="form-control" placeholder="e.g. Received shipment from supplier / Returned item">
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer bg-light">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-check me-1"></i> Apply Stock Adjustment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Client-side Interactive Filter & Modal Population Scripts -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Edit Button Handler
        const editButtons = document.querySelectorAll('.edit-product-btn');
        const editModal = new bootstrap.Modal(document.getElementById('editProductModal'));
        
        editButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const sku       = this.getAttribute('data-sku');
                const name      = this.getAttribute('data-name');
                const category  = this.getAttribute('data-category');
                const stock     = this.getAttribute('data-stock');
                const cost      = this.getAttribute('data-cost');
                const wholesale = this.getAttribute('data-wholesale');
                const retail    = this.getAttribute('data-retail');

                document.getElementById('editOrigSku').value = sku;
                document.getElementById('editSku').value = sku;
                document.getElementById('editName').value = name;
                document.getElementById('editStock').value = stock;
                document.getElementById('editCost').value = cost;
                document.getElementById('editWholesale').value = wholesale;
                document.getElementById('editRetail').value = retail;

                // Set category dropdown if exists, else select General
                const catSelect = document.getElementById('editCategory');
                let found = false;
                for (let i = 0; i < catSelect.options.length; i++) {
                    if (catSelect.options[i].value.toLowerCase() === category.toLowerCase()) {
                        catSelect.selectedIndex = i;
                        found = true;
                        break;
                    }
                }
                if (!found) {
                    catSelect.value = 'General';
                }

                editModal.show();
            });
        });

        // Delete Button Handler
        const deleteButtons = document.querySelectorAll('.delete-product-btn');
        const deleteModal = new bootstrap.Modal(document.getElementById('deleteProductModal'));

        deleteButtons.forEach(btn => {
            btn.addEventListener('click', function() {
                const sku = this.getAttribute('data-sku');
                const name = this.getAttribute('data-name');

                document.getElementById('deleteSku').value = sku;
                document.getElementById('deleteProductName').textContent = name + ' (' + sku + ')';

                deleteModal.show();
            });
        });

        // Search & Filter Logic
        const searchInput = document.getElementById('inventorySearch');
        const catFilter   = document.getElementById('categoryFilter');
        const stockFilter = document.getElementById('stockStatusFilter');
        const rows        = document.querySelectorAll('.product-row');
        const summaryText = document.getElementById('tableSummary');

        function filterTable() {
            const query = (searchInput.value || '').toLowerCase().trim();
            const catVal = (catFilter.value || '').toLowerCase().trim();
            const stockVal = (stockFilter.value || '').trim();

            let visibleCount = 0;

            rows.forEach(row => {
                const sku = (row.getAttribute('data-sku') || '').toLowerCase();
                const name = (row.getAttribute('data-name') || '').toLowerCase();
                const category = (row.getAttribute('data-category') || '').toLowerCase();
                const status = (row.getAttribute('data-status') || '');

                const matchesQuery = !query || sku.includes(query) || name.includes(query) || category.includes(query);
                const matchesCat   = !catVal || category === catVal;
                const matchesStock = !stockVal || status === stockVal;

                if (matchesQuery && matchesCat && matchesStock) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });

            if (summaryText) {
                summaryText.textContent = 'Showing ' + visibleCount + ' of ' + rows.length + ' registered items in catalog';
            }
        }

        if (searchInput) searchInput.addEventListener('input', filterTable);
        if (catFilter) catFilter.addEventListener('change', filterTable);
        if (stockFilter) stockFilter.addEventListener('change', filterTable);
    });

    // Helper to open stock adjust modal directly for a specific item
    function prepareStockModal(sku, type) {
        const select = document.getElementById('adjustSkuSelect');
        if (select) select.value = sku;
        const typeSelect = document.getElementById('adjustTypeSelect');
        if (typeSelect) typeSelect.value = type;
        const modal = new bootstrap.Modal(document.getElementById('stockAdjustModal'));
        modal.show();
    }
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
