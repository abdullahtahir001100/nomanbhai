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
        $alert_qty      = max(1, (int)($_POST['alert_qty'] ?? 10));
        $high_stock_qty = max($alert_qty + 1, (int)($_POST['high_stock_qty'] ?? 50));
        $cost_price     = max(0.0, (float)($_POST['cost_price'] ?? 0));
        $wholesale_price= max(0.0, (float)($_POST['wholesale_price'] ?? 0));
        $retail_price   = max(0.0, (float)($_POST['retail_price'] ?? 0));

        $file = $_FILES['product_image'] ?? null;

        $res = add_product([
            'name'            => $name,
            'sku'             => $sku,
            'category'        => $category,
            'stock'           => $stock,
            'alert_qty'       => $alert_qty,
            'high_stock_qty'  => $high_stock_qty,
            'cost_price'      => $cost_price,
            'wholesale_price' => $wholesale_price,
            'retail_price'    => $retail_price
        ], $file);

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
        $alert_qty      = max(1, (int)($_POST['alert_qty'] ?? 10));
        $high_stock_qty = max($alert_qty + 1, (int)($_POST['high_stock_qty'] ?? 50));
        $cost_price     = max(0.0, (float)($_POST['cost_price'] ?? 0));
        $wholesale_price= max(0.0, (float)($_POST['wholesale_price'] ?? 0));
        $retail_price   = max(0.0, (float)($_POST['retail_price'] ?? 0));
        $remove_image   = trim($_POST['remove_image'] ?? '0');

        $file = $_FILES['product_image'] ?? null;

        $res = edit_product($orig_sku, [
            'name'            => $name,
            'sku'             => $sku,
            'category'        => $category,
            'stock'           => $stock,
            'alert_qty'       => $alert_qty,
            'high_stock_qty'  => $high_stock_qty,
            'cost_price'      => $cost_price,
            'wholesale_price' => $wholesale_price,
            'retail_price'    => $retail_price,
            'remove_image'    => $remove_image
        ], $file);

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
                            <small class="text-dark-50 fw-semibold">Quantity &le; alert threshold (کم اسٹاک)</small>
                        </div>
                        <i class="fa-solid fa-triangle-exclamation fa-2x opacity-50 text-dark"></i>
                    </div>
                </div>
            </div>

            <!-- 4. High Stock Surplus -->
            <div class="col-sm-6 col-xl-3">
                <div class="card stat-card shadow-sm text-white p-3 h-100 border-0" style="background: linear-gradient(135deg, #1e3a8a, #2563eb);">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-white-50 text-uppercase fw-semibold" style="letter-spacing: 0.5px;">High Stock Surplus</small>
                            <h3 class="fw-bold mb-0 mt-1"><?php echo number_format($metrics['high_stock_count'] ?? 0); ?> Items</h3>
                            <small class="text-white-50 fw-semibold">Quantity &ge; 50 units (وافر اسٹاک)</small>
                        </div>
                        <i class="fa-solid fa-cubes-stacked fa-2x opacity-50"></i>
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
                            <option value="">All Stock Levels (<?php echo count($products); ?> items)</option>
                            <option value="high_stock">🔥 High Stock (&ge; 50 pcs)</option>
                            <option value="in_stock">✅ In Stock (Normal)</option>
                            <option value="low_stock">⚠️ Low Stock (Alert)</option>
                            <option value="out_stock">❌ Out of Stock (0 pcs)</option>
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
                                <th style="width: 60px;" class="text-center">Photo</th>
                                <th>Item & SKU</th>
                                <th>Category</th>
                                <th>Cost Price</th>
                                <th>Wholesale Rate</th>
                                <th>Retail Rate</th>
                                <th>In Stock</th>
                                <th>Stock Status</th>
                                <th class="text-center" style="width: 170px;">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-box-open fa-3x mb-3 text-secondary opacity-50"></i>
                                        <p class="mb-2 fs-6">No products found in the inventory.</p>
                                        <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addProductModal">
                                            <i class="fa-solid fa-plus me-1"></i> Add Your First Item
                                        </button>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $sku => $p): 
                                    $stock   = (int)($p['stock'] ?? 0);
                                    $cost    = (float)($p['cost_price'] ?? 0);
                                    $ws      = (float)($p['wholesale_price'] ?? 0);
                                    $ret     = (float)($p['retail_price'] ?? 0);
                                    $alert   = isset($p['alert_qty']) ? (int)$p['alert_qty'] : 10;
                                    $high    = isset($p['high_stock_qty']) ? (int)$p['high_stock_qty'] : 50;
                                    $badge   = !empty($p['category_badge']) ? $p['category_badge'] : get_category_badge($p['category'] ?? '');
                                    $imgUrl  = !empty($p['image']) ? $p['image'] : '';
                                    
                                    if ($stock <= 0) {
                                        $statusClass  = 'out_stock';
                                        $statusBadge  = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1"><i class="fa-solid fa-circle-xmark me-1"></i>Out of Stock</span>';
                                        $stockDisplay = '<span class="fw-bold text-danger">0 Pcs</span>';
                                    } elseif ($stock <= $alert) {
                                        $statusClass  = 'low_stock';
                                        $statusBadge  = '<span class="badge bg-warning text-dark border border-warning shadow-xs px-2 py-1 fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Low Stock (' . $stock . ')</span>';
                                        $stockDisplay = '<span class="fw-bold text-danger">' . $stock . ' Pcs</span>';
                                    } elseif ($stock >= $high) {
                                        $statusClass  = 'high_stock';
                                        $statusBadge  = '<span class="badge bg-primary-subtle text-primary border border-primary-subtle shadow-xs px-2 py-1 fw-bold"><i class="fa-solid fa-boxes-stacked me-1"></i>High Stock (' . $stock . ')</span>';
                                        $stockDisplay = '<span class="fw-bold text-primary">' . $stock . ' Pcs</span>';
                                    } else {
                                        $statusClass  = 'in_stock';
                                        $statusBadge  = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i>In Stock</span>';
                                        $stockDisplay = '<span class="fw-bold text-dark">' . $stock . ' Pcs</span>';
                                    }
                                ?>
                                    <tr class="product-row" 
                                        data-sku="<?php echo htmlspecialchars($sku); ?>"
                                        data-name="<?php echo htmlspecialchars(strtolower($p['name'])); ?>"
                                        data-category="<?php echo htmlspecialchars($p['category']); ?>"
                                        data-status="<?php echo $statusClass; ?>"
                                        data-image="<?php echo htmlspecialchars($imgUrl); ?>"
                                        data-alert="<?php echo $alert; ?>"
                                        data-high="<?php echo $high; ?>">
                                        <td class="text-center" style="width: 60px;">
                                            <?php if (!empty($imgUrl) && file_exists(__DIR__ . '/' . $imgUrl)): ?>
                                                <a href="javascript:void(0)" onclick="viewImageModal('<?php echo htmlspecialchars($imgUrl); ?>', '<?php echo htmlspecialchars(addslashes($p['name'])); ?>')">
                                                    <img src="<?php echo htmlspecialchars($imgUrl); ?>" 
                                                         alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                                         class="rounded-3 border shadow-xs object-fit-cover cursor-pointer" 
                                                         style="width: 46px; height: 46px; min-width: 46px; transition: transform 0.2s;"
                                                         onmouseover="this.style.transform='scale(1.1)'"
                                                         onmouseout="this.style.transform='scale(1)'"
                                                         title="Click to view full photo">
                                                </a>
                                            <?php else: ?>
                                                <div class="rounded-3 bg-light border d-inline-flex align-items-center justify-content-center text-muted shadow-xs" 
                                                     style="width: 46px; height: 46px; min-width: 46px;" 
                                                     title="No image attached">
                                                    <i class="fa-regular fa-image fs-4 opacity-50"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
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
                                                        data-alert="<?php echo $alert; ?>"
                                                        data-high="<?php echo $high; ?>"
                                                        data-image="<?php echo htmlspecialchars($imgUrl); ?>"
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
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle me-1"><?php echo $metrics['high_stock_count'] ?? 0; ?> High Stock</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle me-1"><?php echo $metrics['in_stock_count'] ?? 0; ?> Normal</span>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle me-1"><?php echo $metrics['low_stock_count']; ?> Low Stock</span>
                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle"><?php echo $metrics['out_of_stock_count']; ?> Empty</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Add New Item -->
    <div class="modal fade" id="addProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-primary text-white py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-plus-circle me-2"></i>Add New Product to Inventory</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="inventory.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_product">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Product Photo / Image Upload Box -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark d-flex align-items-center">
                                    <i class="fa-solid fa-camera text-primary me-2"></i>Product Photo / Image (پروڈکٹ کی تصویر لگائیں)
                                </label>
                                <div class="border border-2 border-dashed rounded-3 p-3 text-center bg-light position-relative" style="border-color: #cbd5e1 !important;">
                                    <div id="addImgPlaceholder">
                                        <i class="fa-regular fa-image fs-1 text-muted mb-2 d-block opacity-75"></i>
                                        <p class="small text-muted mb-2">Upload product photo (JPG, PNG, WEBP, max 5MB)</p>
                                        <input type="file" name="product_image" id="addProdImage" class="form-control form-control-sm mx-auto" style="max-width: 320px;" accept="image/*" onchange="previewUploadImage(this, 'addImgPreview', 'addImgPlaceholder', 'addImgPreviewWrapper')">
                                    </div>
                                    <div id="addImgPreviewWrapper" class="d-none text-center">
                                        <img id="addImgPreview" src="" alt="Selected Product Preview" class="rounded-3 border shadow-sm object-fit-cover mb-2" style="max-height: 140px; max-width: 220px;">
                                        <div>
                                            <button type="button" class="btn btn-outline-danger btn-sm py-1 px-3" onclick="removeUploadPreview('addProdImage', 'addImgPreview', 'addImgPlaceholder', 'addImgPreviewWrapper')">
                                                <i class="fa-solid fa-trash me-1"></i> Remove Photo
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

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
                                <label class="form-label fw-semibold">Initial Quantity (Stock IN) <span class="text-danger">*</span></label>
                                <input type="number" name="stock" class="form-control" min="0" value="10" placeholder="0" required>
                            </div>

                            <!-- Stock Alert Level Thresholds -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-warning-emphasis">
                                    <i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>Low Stock Alert Trigger (&le; Qty)
                                </label>
                                <input type="number" name="alert_qty" class="form-control border-warning" min="1" value="10" placeholder="10" required>
                                <small class="text-muted" style="font-size: 0.74rem;">Jab stock is se kam ya barabar hoga to <strong>Low Stock</strong> badge aayega</small>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-primary">
                                    <i class="fa-solid fa-boxes-stacked me-1 text-primary"></i>High Stock Level Trigger (&ge; Qty)
                                </label>
                                <input type="number" name="high_stock_qty" class="form-control border-primary" min="2" value="50" placeholder="50" required>
                                <small class="text-muted" style="font-size: 0.74rem;">Jab stock is se zyada ya barabar hoga to <strong>High Stock</strong> badge aayega</small>
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
                        <button type="submit" class="btn btn-primary px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-save me-1"></i> Save & Add to Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Edit Product & Rates -->
    <div class="modal fade" id="editProductModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white py-3">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2 text-warning"></i>Edit Product & Rates</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form action="inventory.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="edit_product">
                    <input type="hidden" name="orig_sku" id="editOrigSku">
                    <div class="modal-body p-4">
                        <div class="row g-3">
                            <!-- Product Photo / Image Section in Edit -->
                            <div class="col-12">
                                <label class="form-label fw-semibold text-dark d-flex align-items-center">
                                    <i class="fa-solid fa-camera text-primary me-2"></i>Product Photo / Image (تصویر تبدیل کریں یا ہٹائیں)
                                </label>
                                <div class="border border-2 border-dashed rounded-3 p-3 text-center bg-light position-relative" style="border-color: #cbd5e1 !important;">
                                    <div id="editImgCurrentWrap" class="mb-2 d-none">
                                        <img id="editImgCurrent" src="" alt="Current Product Image" class="rounded-3 border shadow-sm object-fit-cover mb-2" style="max-height: 120px; max-width: 180px;">
                                        <div>
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="remove_image" id="editRemoveImage" value="1">
                                                <label class="form-check-label small text-danger fw-semibold" for="editRemoveImage">
                                                    <i class="fa-solid fa-trash me-1"></i> Remove Current Photo
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                    <div id="editImgNewWrap">
                                        <small class="text-muted d-block mb-1">Select a new image to replace (optional):</small>
                                        <input type="file" name="product_image" id="editProdImage" class="form-control form-control-sm mx-auto" style="max-width: 320px;" accept="image/*" onchange="previewUploadImage(this, 'editImgNewPreview', null, 'editImgNewPreviewWrap')">
                                        <div id="editImgNewPreviewWrap" class="d-none mt-2">
                                            <span class="badge bg-success mb-1">New Image Selected</span><br>
                                            <img id="editImgNewPreview" src="" alt="New Preview" class="rounded-3 border shadow-sm object-fit-cover" style="max-height: 100px; max-width: 160px;">
                                        </div>
                                    </div>
                                </div>
                            </div>

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
                                <label class="form-label fw-semibold">Current In-Stock Quantity <span class="text-danger">*</span></label>
                                <input type="number" name="stock" id="editStock" class="form-control" min="0" required>
                            </div>

                            <!-- Stock Thresholds in Edit -->
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-warning-emphasis">
                                    <i class="fa-solid fa-triangle-exclamation me-1 text-warning"></i>Low Stock Alert Trigger (&le; Qty)
                                </label>
                                <input type="number" name="alert_qty" id="editAlertQty" class="form-control border-warning" min="1" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold text-primary">
                                    <i class="fa-solid fa-boxes-stacked me-1 text-primary"></i>High Stock Level Trigger (&ge; Qty)
                                </label>
                                <input type="number" name="high_stock_qty" id="editHighStockQty" class="form-control border-primary" min="2" required>
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
                        <button type="submit" class="btn btn-success px-4 fw-bold shadow-sm">
                            <i class="fa-solid fa-check me-1"></i> Update Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal: Image Preview Lightbox -->
    <div class="modal fade" id="imagePreviewModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white py-2 px-3">
                    <h6 class="modal-title fw-bold mb-0 text-truncate" id="imagePreviewTitle">Product Photo</h6>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0 bg-black text-center d-flex align-items-center justify-content-center" style="min-height: 300px; max-height: 80vh;">
                    <img id="imagePreviewImg" src="" alt="Full Product Photo" class="img-fluid object-fit-contain" style="max-height: 75vh; width: 100%;">
                </div>
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
                const alert     = this.getAttribute('data-alert') || '10';
                const high      = this.getAttribute('data-high') || '50';
                const image     = this.getAttribute('data-image') || '';

                document.getElementById('editOrigSku').value = sku;
                document.getElementById('editSku').value = sku;
                document.getElementById('editName').value = name;
                document.getElementById('editStock').value = stock;
                document.getElementById('editCost').value = cost;
                document.getElementById('editWholesale').value = wholesale;
                document.getElementById('editRetail').value = retail;
                document.getElementById('editAlertQty').value = alert;
                document.getElementById('editHighStockQty').value = high;

                // Reset file input & new preview
                const editFileInput = document.getElementById('editProdImage');
                if (editFileInput) editFileInput.value = '';
                const newPreviewWrap = document.getElementById('editImgNewPreviewWrap');
                if (newPreviewWrap) newPreviewWrap.classList.add('d-none');
                const removeCheck = document.getElementById('editRemoveImage');
                if (removeCheck) removeCheck.checked = false;

                // Current image preview
                const currentWrap = document.getElementById('editImgCurrentWrap');
                const currentImg = document.getElementById('editImgCurrent');
                if (image && image.trim() !== '') {
                    currentImg.src = image;
                    currentWrap.classList.remove('d-none');
                } else {
                    currentImg.src = '';
                    currentWrap.classList.add('d-none');
                }

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
            const query    = (searchInput.value || '').toLowerCase().trim();
            const catVal   = (catFilter.value || '').toLowerCase().trim();
            const stockVal = (stockFilter.value || '').trim();

            let visibleCount = 0;

            rows.forEach(row => {
                const sku      = (row.getAttribute('data-sku') || '').toLowerCase();
                const name     = (row.getAttribute('data-name') || '').toLowerCase();
                const category = (row.getAttribute('data-category') || '').toLowerCase();
                const status   = (row.getAttribute('data-status') || '');

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

    // Real-time File Upload Image Preview Helper
    function previewUploadImage(input, previewImgId, placeholderId, wrapperId) {
        if (input.files && input.files[0]) {
            const file = input.files[0];
            const reader = new FileReader();
            reader.onload = function(e) {
                const previewImg = document.getElementById(previewImgId);
                if (previewImg) previewImg.src = e.target.result;
                if (placeholderId) {
                    const placeholder = document.getElementById(placeholderId);
                    if (placeholder) placeholder.classList.add('d-none');
                }
                if (wrapperId) {
                    const wrapper = document.getElementById(wrapperId);
                    if (wrapper) wrapper.classList.remove('d-none');
                }
            };
            reader.readAsDataURL(file);
        }
    }

    // Remove Upload Preview Helper
    function removeUploadPreview(inputId, previewImgId, placeholderId, wrapperId) {
        const input = document.getElementById(inputId);
        if (input) input.value = '';
        const previewImg = document.getElementById(previewImgId);
        if (previewImg) previewImg.src = '';
        if (wrapperId) {
            const wrapper = document.getElementById(wrapperId);
            if (wrapper) wrapper.classList.add('d-none');
        }
        if (placeholderId) {
            const placeholder = document.getElementById(placeholderId);
            if (placeholder) placeholder.classList.remove('d-none');
        }
    }

    // Lightbox Modal for Full Image View
    function viewImageModal(src, title) {
        document.getElementById('imagePreviewTitle').textContent = title || 'Product Photo';
        document.getElementById('imagePreviewImg').src = src;
        const modalEl = document.getElementById('imagePreviewModal');
        const modal = bootstrap.Modal.getInstance(modalEl) || new bootstrap.Modal(modalEl);
        modal.show();
    }

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
