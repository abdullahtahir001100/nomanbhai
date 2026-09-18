<?php
// wholesale_catalog.php - Dedicated Wholesale Client B2B Rates Portal
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/store_data.php';

// Authentication Guard: Must be logged in as wholesale client or system user
if (!is_wholesale_client_logged_in() && !is_logged_in()) {
    header("Location: login.php?client=1");
    exit;
}

$client = get_current_wholesale_client();
$isSystemAdmin = is_logged_in();

// If admin is browsing wholesale catalog, provide simulated or selected client
if (!$client && $isSystemAdmin) {
    $client = [
        'id'      => 'admin_view',
        'name'    => 'Ali Electronics (Simulated Dealer View)',
        'owner'   => 'Muhammad Ali',
        'phone'   => '0300-1234567',
        'city'    => 'Hall Road, Lahore',
        'balance' => 145000,
        'limit'   => 500000
    ];
}

$storeData = get_store_metrics();
$products = $storeData['products'] ?? [];
$storeWhatsApp = defined('STORE_WHATSAPP_NUMBER') ? STORE_WHATSAPP_NUMBER : '923041612042';
$storePhoneDisplay = defined('STORE_PHONE_DISPLAY') ? STORE_PHONE_DISPLAY : '0304-1612042';

// Group products by category
$categories = [];
foreach ($products as $p) {
    $cat = $p['category'] ?? 'General';
    if (!isset($categories[$cat])) {
        $categories[$cat] = 0;
    }
    $categories[$cat]++;
}

$pageTitle = 'Smart Mobile - Wholesale Dealer Portal & Rates';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome 6 Icons -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bs-body-bg: #f8fafc;
            --primary-dark: #0f172a;
            --card-border: rgba(226, 232, 240, 0.8);
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bs-body-bg);
            color: #1e293b;
            min-height: 100vh;
        }

        /* Top Dealer Navbar */
        .dealer-navbar {
            background: #0f172a;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            position: sticky;
            top: 0;
            z-index: 1030;
        }

        /* Wholesale Product Card */
        .wholesale-card {
            background: #fff;
            border: 1px solid var(--card-border);
            border-radius: 18px;
            transition: all 0.28s cubic-bezier(0.165, 0.84, 0.44, 1);
            position: relative;
            overflow: hidden;
        }
        .wholesale-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 20px 36px rgba(15, 23, 42, 0.1);
            border-color: #cbd5e1;
        }

        /* Large Product Image Banner */
        .product-img-banner {
            height: 230px;
            background: linear-gradient(180deg, #f8fafc 0%, #f1f5f9 100%);
            border-bottom: 1px solid rgba(226, 232, 240, 0.9);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .product-large-img {
            max-height: 100%;
            max-width: 100%;
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 16px;
            transition: transform 0.35s cubic-bezier(0.165, 0.84, 0.44, 1);
            cursor: pointer;
        }
        .wholesale-card:hover .product-large-img {
            transform: scale(1.08);
        }
        .zoom-badge-btn {
            position: absolute;
            bottom: 10px;
            right: 10px;
            width: 32px;
            height: 32px;
            background: rgba(255, 255, 255, 0.88);
            backdrop-filter: blur(4px);
            border: 1px solid rgba(0, 0, 0, 0.08);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1e293b;
            font-size: 0.82rem;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            transition: all 0.2s ease;
        }
        .zoom-badge-btn:hover {
            background: #2563eb;
            color: #fff;
            transform: scale(1.1);
        }
        .text-truncate-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.8rem;
        }

        .wholesale-rate-badge {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: #fff;
            padding: 14px 16px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }
        .product-icon-wrap {
            width: 54px;
            height: 54px;
            background: #f1f5f9;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #475569;
            font-size: 1.5rem;
        }

        /* Filter Pills */
        .filter-pill {
            cursor: pointer;
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.88rem;
            font-weight: 600;
            background: #fff;
            color: #64748b;
            border: 1px solid #e2e8f0;
            transition: all 0.2s ease;
        }
        .filter-pill:hover, .filter-pill.active {
            background: #2563eb;
            color: #fff;
            border-color: #2563eb;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
        }

        /* Clean Printable Sheet Styling */
        @media print {
            body * {
                visibility: hidden !important;
            }
            #printableRateSheet, #printableRateSheet * {
                visibility: visible !important;
            }
            #printableRateSheet {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 12px !important;
                background: #fff !important;
            }
            .no-print {
                display: none !important;
            }
        }
    </style>
</head>
<body>

    <!-- Top Dealer Navigation Bar -->
    <nav class="dealer-navbar py-3 px-3 px-lg-4 text-white">
        <div class="container-fluid d-flex flex-wrap justify-content-between align-items-center gap-3">
            
            <!-- Brand Info -->
            <div class="d-flex align-items-center gap-3">
                <div class="bg-primary p-2 rounded-3 text-white">
                    <i class="fa-solid fa-mobile-screen-button fs-4"></i>
                </div>
                <div>
                    <div class="d-flex align-items-center gap-2">
                        <h5 class="fw-bold mb-0 text-white">Smart Mobile</h5>
                        <span class="badge bg-warning text-dark fw-bold px-2 py-1" style="font-size: 0.72rem;">
                            <i class="fa-solid fa-boxes-stacked me-1"></i> WHOLESALE B2B DEALER
                        </span>
                    </div>
                    <small class="text-white-50" style="font-size: 0.8rem;">Milad Chowk Main Bazar Miani</small>
                </div>
            </div>

            <!-- Client Identity Pill & Actions -->
            <div class="d-flex flex-wrap align-items-center gap-3">
                
                <!-- Client Details Card -->
                <div class="bg-dark bg-opacity-50 px-3 py-2 rounded-3 border border-secondary border-opacity-50 d-flex align-items-center gap-3">
                    <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 38px; height: 38px; font-size: 0.9rem;">
                        <?php echo strtoupper(substr($client['name'] ?? 'CL', 0, 2)); ?>
                    </div>
                    <div>
                        <div class="fw-bold text-white mb-0" style="font-size: 0.92rem;">
                            <?php echo htmlspecialchars($client['name']); ?>
                        </div>
                        <small class="text-white-50" style="font-size: 0.76rem;">
                            <i class="fa-solid fa-location-dot me-1 text-danger"></i><?php echo htmlspecialchars($client['city']); ?> 
                            &bull; Khata Udhaar: <strong class="text-warning">PKR <?php echo number_format((float)($client['balance'] ?? 0)); ?></strong>
                        </small>
                    </div>
                </div>

                <!-- Action Buttons -->
                <button class="btn btn-warning btn-sm px-3 shadow-sm fw-bold" onclick="openMyLedgerModal()">
                    <i class="fa-solid fa-book-bookmark me-1"></i> Mera Khata (Ledger)
                </button>

                <button class="btn btn-outline-light btn-sm px-3 shadow-sm" onclick="openPrintModal()">
                    <i class="fa-solid fa-print me-1"></i> Print Rate List
                </button>

                <a href="https://wa.me/<?php echo $storeWhatsApp; ?>?text=Assalam-o-Alaikum%20Smart%20Mobile,%20I%20am%20<?php echo urlencode($client['name']); ?>.%20I%20want%20to%20place%20a%20wholesale%20order." target="_blank" class="btn btn-success btn-sm px-3 shadow-sm fw-semibold">
                    <i class="fa-brands fa-whatsapp me-1"></i> WhatsApp Order
                </a>

                <?php if ($isSystemAdmin): ?>
                    <a href="index.php" class="btn btn-outline-info btn-sm px-3">
                        <i class="fa-solid fa-gauge me-1"></i> Back to ERP
                    </a>
                <?php endif; ?>

                <a href="logout.php" class="btn btn-outline-danger btn-sm px-3" title="Exit / Logout">
                    <i class="fa-solid fa-right-from-bracket me-1"></i> Logout
                </a>
            </div>

        </div>
    </nav>

    <!-- Main Container -->
    <div class="container-fluid py-4 px-3 px-lg-4">

        <!-- Client Khata & Udhaar Summary Dashboard Card (اپنا کھاتہ اور ادھار سمری) -->
        <div class="card border-0 shadow-sm rounded-4 mb-4 overflow-hidden text-white" style="background: linear-gradient(135deg, #0f172a, #1e293b); border-left: 6px solid #f59e0b !important;">
            <div class="card-body p-4">
                <div class="row align-items-center g-3">
                    <div class="col-lg-7">
                        <div class="d-flex align-items-center gap-3 mb-2">
                            <div class="rounded-circle bg-warning bg-opacity-25 text-warning d-flex align-items-center justify-content-center fw-bold fs-3" style="width: 56px; height: 56px;">
                                <i class="fa-solid fa-book-bookmark"></i>
                            </div>
                            <div>
                                <div class="d-flex align-items-center gap-2">
                                    <h4 class="fw-bold text-white mb-0"><?php echo htmlspecialchars($client['name']); ?></h4>
                                    <span class="badge bg-warning text-dark fw-bold"><?php echo htmlspecialchars($client['client_code'] ?? 'WC'); ?></span>
                                </div>
                                <div class="text-white-50 small mt-1">
                                    <span class="me-2"><i class="fa-solid fa-user me-1 text-info"></i> Proprietor: <strong><?php echo htmlspecialchars($client['owner'] ?? $client['name']); ?></strong></span>
                                    <span class="me-2"><i class="fa-solid fa-phone me-1 text-success"></i> <strong><?php echo htmlspecialchars($client['phone'] ?? ''); ?></strong></span>
                                    <span><i class="fa-solid fa-location-dot me-1 text-danger"></i> <?php echo htmlspecialchars($client['city'] ?? 'Local'); ?></span>
                                </div>
                            </div>
                        </div>
                        <p class="text-white-50 small mb-0 mt-3">
                            <i class="fa-solid fa-circle-check text-success me-1"></i> Khush-Amdeed! Yahan aap apna <strong>Khata (Udhaar & Payments)</strong> live check kar sakte hain aur neechy tamam products ke <strong>Wholesale Rates</strong> dekh kar order de sakte hain.
                        </p>
                    </div>

                    <div class="col-lg-5">
                        <div class="p-3 rounded-4 bg-white bg-opacity-10 border border-white border-opacity-10 d-flex flex-wrap justify-content-between align-items-center gap-3">
                            <div>
                                <small class="text-warning text-uppercase fw-bold d-block" style="font-size: 0.76rem; letter-spacing: 0.5px;">Wajib-ul-Ada Udhaar (Current Balance)</small>
                                <h2 class="fw-bold text-warning mb-0 mt-1">
                                    PKR <?php echo number_format((float)($client['balance'] ?? 0)); ?>
                                </h2>
                                <small class="text-white-50" style="font-size: 0.74rem;">Credit Limit: PKR <?php echo number_format((float)($client['limit'] ?? 500000)); ?></small>
                            </div>
                            <div>
                                <button type="button" class="btn btn-warning fw-bold px-3 py-2 shadow-sm text-dark" onclick="openMyLedgerModal()">
                                    <i class="fa-solid fa-file-invoice-dollar me-1"></i> Mera Khata (Ledger)
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Welcome Banner & Live Search -->
        <div class="card border-0 shadow-sm rounded-4 p-4 mb-4 bg-white">
            <div class="row align-items-center g-3">
                <div class="col-lg-6">
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-success-subtle text-success border border-success px-2 py-1">
                            <i class="fa-solid fa-circle-check me-1"></i> Verified Wholesale Pricing Active
                        </span>
                        <span class="text-muted small">&bull; Updated <?php echo date('d M Y'); ?></span>
                    </div>
                    <h3 class="fw-bold mb-1">Wholesale Products & Spare Parts Catalog</h3>
                    <p class="text-muted mb-0 small">
                        All rates shown below are <strong>exclusive dealer wholesale rates</strong> for shopkeepers and distributors.
                    </p>
                </div>

                <!-- Search Input -->
                <div class="col-lg-6">
                    <div class="input-group input-group-lg shadow-sm rounded-3">
                        <span class="input-group-text bg-white border-end-0 text-muted">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </span>
                        <input type="text" id="catalogSearch" class="form-control border-start-0" 
                               placeholder="Search item name, display panel, battery, charger, SKU..." 
                               onkeyup="filterCatalog()">
                        <button class="btn btn-primary px-4 fw-semibold" type="button" onclick="document.getElementById('catalogSearch').focus()">
                            Search
                        </button>
                    </div>
                </div>
            </div>

            <!-- Filter Pills Bar -->
            <div class="d-flex flex-wrap align-items-center gap-2 mt-4 pt-3 border-top">
                <span class="fw-semibold small text-muted me-2"><i class="fa-solid fa-filter me-1"></i> Category Filter:</span>
                <span class="filter-pill active" data-category="all" onclick="setCategoryFilter('all', this)">
                    All Products (<?php echo count($products); ?>)
                </span>
                <?php foreach ($categories as $catName => $catCount): ?>
                    <span class="filter-pill" data-category="<?php echo htmlspecialchars(strtolower($catName)); ?>" onclick="setCategoryFilter('<?php echo htmlspecialchars(strtolower($catName)); ?>', this)">
                        <?php echo htmlspecialchars($catName); ?> (<?php echo $catCount; ?>)
                    </span>
                <?php endforeach; ?>

                <div class="ms-auto d-flex gap-2">
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="toggleViewMode('grid')" id="gridBtn">
                        <i class="fa-solid fa-grip me-1"></i> Grid
                    </button>
                    <button class="btn btn-sm btn-outline-secondary rounded-pill px-3" onclick="toggleViewMode('table')" id="tableBtn">
                        <i class="fa-solid fa-list me-1"></i> Rate Sheet Table
                    </button>
                </div>
            </div>
        </div>

        <!-- 1. GRID VIEW OF PRODUCTS -->
        <div id="gridViewWrapper" class="row g-3 g-xl-4">
            <?php foreach ($products as $sku => $p): 
                $stock = (int)($p['stock'] ?? 0);
                $wholesalePrice = (float)($p['wholesale_price'] ?? 0);
                $retailPrice = (float)($p['retail_price'] ?? 0);
                $margin = max(0, $retailPrice - $wholesalePrice);
                $marginPct = ($retailPrice > 0) ? round(($margin / $retailPrice) * 100) : 0;
                $cat = $p['category'] ?? 'General';
                $catLower = strtolower($cat);
                $alertQty = isset($p['alert_qty']) ? (int)$p['alert_qty'] : 10;
                $highStockQty = isset($p['high_stock_qty']) ? (int)$p['high_stock_qty'] : 50;

                // Category Icon
                $icon = 'fa-boxes-stacked';
                if (stripos($cat, 'Panel') !== false || stripos($cat, 'Display') !== false) $icon = 'fa-mobile-screen';
                elseif (stripos($cat, 'Battery') !== false) $icon = 'fa-battery-full';
                elseif (stripos($cat, 'Charger') !== false) $icon = 'fa-bolt';
                elseif (stripos($cat, 'Access') !== false) $icon = 'fa-headphones';
            ?>
                <div class="col-sm-6 col-lg-4 col-xl-4 product-card-col" 
                     data-name="<?php echo htmlspecialchars(strtolower($p['name'] . ' ' . $sku)); ?>" 
                     data-category="<?php echo htmlspecialchars($catLower); ?>">
                    <div class="wholesale-card h-100 d-flex flex-column justify-content-between">
                        
                        <!-- Top Large Product Image Banner -->
                        <div class="product-img-banner">
                            <?php if (!empty($p['image']) && file_exists(__DIR__ . '/' . $p['image'])): ?>
                                <img src="<?php echo htmlspecialchars($p['image']); ?>" 
                                     alt="<?php echo htmlspecialchars($p['name']); ?>" 
                                     class="product-large-img"
                                     onclick="viewCatalogImage('<?php echo htmlspecialchars($p['image']); ?>', '<?php echo htmlspecialchars(addslashes($p['name'])); ?>', 'PKR <?php echo number_format($wholesalePrice); ?>', '<?php echo htmlspecialchars($sku); ?>')"
                                     title="Click to zoom / view photo">
                                <button type="button" class="zoom-badge-btn" 
                                        onclick="viewCatalogImage('<?php echo htmlspecialchars($p['image']); ?>', '<?php echo htmlspecialchars(addslashes($p['name'])); ?>', 'PKR <?php echo number_format($wholesalePrice); ?>', '<?php echo htmlspecialchars($sku); ?>')"
                                        title="Enlarge Photo">
                                    <i class="fa-solid fa-expand"></i>
                                </button>
                            <?php else: ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="fa-solid <?php echo $icon; ?> fa-4x text-secondary opacity-25 mb-2"></i>
                                    <div class="small fw-semibold text-muted opacity-75">No Photo Attached</div>
                                </div>
                            <?php endif; ?>

                            <!-- Top-Left Floating Badge: Category -->
                            <div class="position-absolute top-0 start-0 m-3">
                                <span class="badge <?php echo htmlspecialchars($p['category_badge'] ?? 'bg-secondary'); ?> shadow-sm px-2 py-1">
                                    <?php echo htmlspecialchars($cat); ?>
                                </span>
                            </div>

                            <!-- Top-Right Floating Badge: Dynamic Stock -->
                            <div class="position-absolute top-0 end-0 m-3">
                                <?php if ($stock <= 0): ?>
                                    <span class="badge bg-danger text-white shadow-sm" style="font-size: 0.74rem;">
                                        <i class="fa-solid fa-circle-xmark me-1"></i> Out of Stock
                                    </span>
                                <?php elseif ($stock <= $alertQty): ?>
                                    <span class="badge bg-warning text-dark border border-warning shadow-sm fw-bold" style="font-size: 0.74rem;">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Low Stock (<?php echo $stock; ?>)
                                    </span>
                                <?php elseif ($stock >= $highStockQty): ?>
                                    <span class="badge bg-primary text-white shadow-sm fw-bold" style="font-size: 0.74rem;">
                                        <i class="fa-solid fa-boxes-stacked me-1"></i> High Stock (<?php echo $stock; ?>)
                                    </span>
                                <?php else: ?>
                                    <span class="badge bg-success text-white shadow-sm" style="font-size: 0.74rem;">
                                        <i class="fa-solid fa-circle-check me-1"></i> In Stock (<?php echo $stock; ?>)
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Card Body: Details & Pricing -->
                        <div class="p-3 p-md-4 d-flex flex-column justify-content-between flex-grow-1">
                            <div>
                                <!-- Product Title & SKU -->
                                <h5 class="fw-bold text-dark mb-1 text-truncate-2" title="<?php echo htmlspecialchars($p['name']); ?>">
                                    <?php echo htmlspecialchars($p['name']); ?>
                                </h5>
                                <div class="text-muted small mb-3">
                                    SKU / Code: <code class="text-primary fw-bold"><?php echo htmlspecialchars($sku); ?></code>
                                </div>
                            </div>

                            <!-- Pricing Section -->
                            <div class="mt-2">
                                <!-- Reference Retail Price & Margin -->
                                <div class="d-flex justify-content-between align-items-center mb-2 small">
                                    <div>
                                        <span class="text-muted">Market Retail:</span>
                                        <span class="text-decoration-line-through text-secondary fw-semibold">PKR <?php echo number_format($retailPrice); ?></span>
                                    </div>
                                    <span class="badge bg-primary-subtle text-primary fw-semibold">
                                        Profit: +PKR <?php echo number_format($margin); ?> (<?php echo $marginPct; ?>%)
                                    </span>
                                </div>

                                <!-- Wholesale Price Box -->
                                <div class="wholesale-rate-badge d-flex justify-content-between align-items-center mb-3">
                                    <div>
                                        <small class="text-white-50 text-uppercase fw-bold" style="letter-spacing: 0.5px; font-size: 0.72rem;">
                                            DEALER WHOLESALE RATE
                                        </small>
                                        <h3 class="fw-bold mb-0 text-white">
                                            PKR <?php echo number_format($wholesalePrice); ?>
                                        </h3>
                                    </div>
                                    <div class="text-end text-white-50 small">
                                        <i class="fa-solid fa-tag fa-2x text-white opacity-50"></i>
                                    </div>
                                </div>

                                <!-- Order Action Buttons -->
                                <div class="d-flex gap-2">
                                    <a href="https://wa.me/<?php echo $storeWhatsApp; ?>?text=Assalam-o-Alaikum,%20I%20want%20to%20order%20Item:%20<?php echo urlencode($p['name']); ?>%20(SKU:%20<?php echo $sku; ?>)%20at%20Wholesale%20Rate:%20PKR%20<?php echo number_format($wholesalePrice); ?>.%20Please%20confirm%20quantity." target="_blank" class="btn btn-outline-success w-100 fw-semibold btn-sm py-2">
                                        <i class="fa-brands fa-whatsapp me-1"></i> Inquire Item
                                    </a>
                                    <button class="btn btn-outline-primary btn-sm px-3" onclick="quickInquiryPrompt('<?php echo htmlspecialchars($p['name']); ?>', '<?php echo $sku; ?>', <?php echo $wholesalePrice; ?>)" title="Book Wholesale Quantity">
                                        <i class="fa-solid fa-cart-plus"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- 2. TABLE VIEW OF PRODUCTS (Alternate clean Hall Road rate sheet) -->
        <div id="tableViewWrapper" class="card border-0 shadow-sm rounded-4 overflow-hidden d-none mb-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <span class="fw-bold"><i class="fa-solid fa-table-list me-2 text-primary"></i>Wholesale Price Sheet Table</span>
                <button class="btn btn-sm btn-outline-dark" onclick="window.print()">
                    <i class="fa-solid fa-print me-1"></i> Print Sheet
                </button>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 65px;" class="text-center">Photo</th>
                            <th>SKU Code</th>
                            <th>Item Name & Description</th>
                            <th>Category</th>
                            <th class="text-center">Stock Availability</th>
                            <th class="text-end">Retail Price</th>
                            <th class="text-end text-success fw-bold">Wholesale Rate</th>
                            <th class="text-end text-primary">Shopkeeper Margin</th>
                            <th class="text-center">Order Action</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyRows">
                        <?php foreach ($products as $sku => $p): 
                            $stock = (int)($p['stock'] ?? 0);
                            $wholesalePrice = (float)($p['wholesale_price'] ?? 0);
                            $retailPrice = (float)($p['retail_price'] ?? 0);
                            $margin = max(0, $retailPrice - $wholesalePrice);
                            $cat = $p['category'] ?? 'General';
                            $alertQty = isset($p['alert_qty']) ? (int)$p['alert_qty'] : 10;
                            $highStockQty = isset($p['high_stock_qty']) ? (int)$p['high_stock_qty'] : 50;
                        ?>
                            <tr class="product-table-row" data-name="<?php echo htmlspecialchars(strtolower($p['name'] . ' ' . $sku)); ?>" data-category="<?php echo htmlspecialchars(strtolower($cat)); ?>">
                                <td class="text-center">
                                    <?php if (!empty($p['image']) && file_exists(__DIR__ . '/' . $p['image'])): ?>
                                        <img src="<?php echo htmlspecialchars($p['image']); ?>" 
                                             alt="photo" 
                                             class="rounded-3 border shadow-xs object-fit-cover cursor-pointer" 
                                             style="width: 48px; height: 48px; min-width: 48px;"
                                             onclick="viewCatalogImage('<?php echo htmlspecialchars($p['image']); ?>', '<?php echo htmlspecialchars(addslashes($p['name'])); ?>', 'PKR <?php echo number_format($wholesalePrice); ?>', '<?php echo htmlspecialchars($sku); ?>')"
                                             title="Click to view full photo">
                                    <?php else: ?>
                                        <div class="rounded-3 bg-light border d-inline-flex align-items-center justify-content-center text-muted" style="width: 48px; height: 48px; min-width: 48px;">
                                            <i class="fa-regular fa-image opacity-50"></i>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td><code><?php echo htmlspecialchars($sku); ?></code></td>
                                <td class="fw-bold text-dark"><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><span class="badge <?php echo htmlspecialchars($p['category_badge'] ?? 'bg-secondary'); ?>"><?php echo htmlspecialchars($cat); ?></span></td>
                                <td class="text-center">
                                    <?php if ($stock <= 0): ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle">Out of Stock</span>
                                    <?php elseif ($stock <= $alertQty): ?>
                                        <span class="badge bg-warning text-dark border border-warning shadow-xs fw-bold">Low: <?php echo $stock; ?> left</span>
                                    <?php elseif ($stock >= $highStockQty): ?>
                                        <span class="badge bg-primary text-white shadow-xs fw-bold">High: <?php echo $stock; ?> pcs</span>
                                    <?php else: ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle"><?php echo $stock; ?> Available</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-muted text-decoration-line-through">PKR <?php echo number_format($retailPrice); ?></td>
                                <td class="text-end fw-bold text-success fs-6">PKR <?php echo number_format($wholesalePrice); ?></td>
                                <td class="text-end text-primary fw-semibold">+PKR <?php echo number_format($margin); ?></td>
                                <td class="text-center">
                                    <a href="https://wa.me/<?php echo $storeWhatsApp; ?>?text=Assalam-o-Alaikum,%20I%20want%20to%20order%20Item:%20<?php echo urlencode($p['name']); ?>%20at%20Wholesale%20Rate:%20PKR%20<?php echo number_format($wholesalePrice); ?>." target="_blank" class="btn btn-sm btn-success">
                                        <i class="fa-brands fa-whatsapp me-1"></i> Order
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Empty Results Message -->
        <div id="noResultsAlert" class="alert alert-warning text-center py-5 d-none rounded-4">
            <i class="fa-solid fa-box-open fa-3x mb-3 text-warning opacity-75"></i>
            <h5>Koi item nahi mila (No matching wholesale item found)</h5>
            <p class="text-muted mb-0 small">Dusri spelling ya category check karein.</p>
        </div>

    </div>

    <!-- Modal: Printable Wholesale Rate Sheet -->
    <div class="modal fade" id="printModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4">
                <div class="modal-header bg-dark text-white no-print">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-file-lines me-2 text-warning"></i>Print Wholesale Rate Sheet</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4" id="printableRateSheet">
                    <!-- Letterhead -->
                    <div class="text-center border-bottom pb-3 mb-3">
                        <h3 class="fw-bold mb-0">SMART MOBILE WHOLESALE</h3>
                        <div class="fw-semibold text-secondary">Verified Dealer Price Catalog & Rate Sheet</div>
                        <div class="small text-muted">Milad Chowk Main Bazar Miani &bull; WhatsApp: <?php echo $storePhoneDisplay; ?></div>
                    </div>

                    <!-- Client & Date Meta -->
                    <div class="row g-2 mb-3 small bg-light p-2 rounded">
                        <div class="col-6">
                            <strong>Client / Dealer:</strong> <?php echo htmlspecialchars($client['name']); ?> (<?php echo htmlspecialchars($client['city']); ?>)
                        </div>
                        <div class="col-6 text-end">
                            <strong>Effective Date:</strong> <?php echo date('d M Y'); ?>
                        </div>
                    </div>

                    <!-- Rates Table -->
                    <table class="table table-bordered table-sm align-middle mb-0" style="font-size: 0.85rem;">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 50px;" class="text-center">#</th>
                                <th>SKU</th>
                                <th>Item Description</th>
                                <th>Category</th>
                                <th class="text-center">Stock</th>
                                <th class="text-end">Wholesale Rate (PKR)</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $sr = 1; foreach ($products as $sku => $p): ?>
                                <tr>
                                    <td class="text-center"><?php echo $sr++; ?></td>
                                    <td><code><?php echo htmlspecialchars($sku); ?></code></td>
                                    <td class="fw-bold"><?php echo htmlspecialchars($p['name']); ?></td>
                                    <td><?php echo htmlspecialchars($p['category']); ?></td>
                                    <td class="text-center"><?php echo (int)$p['stock'] > 0 ? (int)$p['stock'] . ' pcs' : 'Out'; ?></td>
                                    <td class="text-end fw-bold text-success">PKR <?php echo number_format((float)$p['wholesale_price']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>

                    <div class="mt-4 pt-2 border-top text-center small text-muted">
                        * Rates are subject to market changes. For bulk carton lots, special discounts apply.
                    </div>
                </div>
                <div class="modal-footer no-print">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-dark fw-bold" onclick="window.print()">
                        <i class="fa-solid fa-print me-1"></i> Print Price List
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal: Client's Own Khata Ledger Statement -->
    <div class="modal fade" id="myLedgerModal" tabindex="-1">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white py-3">
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white">
                            <i class="fa-solid fa-book-bookmark text-warning me-2"></i><?php echo htmlspecialchars($client['name']); ?> — Mera Khata Statement
                        </h5>
                        <small class="text-white-50">Mobile: <?php echo htmlspecialchars($client['phone'] ?? ''); ?> &bull; City: <?php echo htmlspecialchars($client['city'] ?? ''); ?></small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <!-- Summary Ribbon -->
                    <div class="p-3 bg-light border-bottom">
                        <div class="row g-2 text-center">
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border">
                                    <small class="text-muted d-block">Total Purchase (Debit)</small>
                                    <strong class="text-dark fs-6" id="myLedgerTotalDebit">PKR 0</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border">
                                    <small class="text-muted d-block">Total Paid (Credit)</small>
                                    <strong class="text-success fs-6" id="myLedgerTotalCredit">PKR 0</strong>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="p-2 bg-white rounded border">
                                    <small class="text-muted d-block">Remaining Balance (Udhaar)</small>
                                    <strong class="text-danger fs-6" id="myLedgerCurrentBal">PKR <?php echo number_format((float)($client['balance'] ?? 0)); ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Ledger Table -->
                    <div class="table-responsive" style="max-height: 380px; overflow-y: auto;">
                        <table class="table table-sm table-striped align-middle mb-0">
                            <thead class="table-dark small text-uppercase">
                                <tr>
                                    <th style="width: 20%;">Date</th>
                                    <th style="width: 45%;">Description / Reference</th>
                                    <th class="text-end" style="width: 17%;">Purchase (Debit)</th>
                                    <th class="text-end" style="width: 18%;">Payment (Credit)</th>
                                </tr>
                            </thead>
                            <tbody id="myLedgerTableBody">
                                <tr><td colspan="4" class="text-center py-4 text-muted"><i class="fa-solid fa-spinner fa-spin me-2"></i>Loading your ledger history...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer bg-light justify-content-between">
                    <small class="text-muted"><i class="fa-solid fa-shield-halved me-1 text-success"></i>Verified Official B2B Ledger Record</small>
                    <button type="button" class="btn btn-secondary btn-sm px-3" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    let activeCategory = 'all';

    async function openMyLedgerModal() {
        const modal = new bootstrap.Modal(document.getElementById('myLedgerModal'));
        modal.show();

        const clientId = '<?php echo htmlspecialchars($client['id'] ?? ''); ?>';
        if (!clientId) return;

        try {
            const res = await fetch('ajax_client_ledger.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_ledger', client_id: clientId })
            });
            const data = await res.json();
            if (data.success) {
                document.getElementById('myLedgerTotalDebit').textContent = 'PKR ' + Number(data.total_debit || 0).toLocaleString();
                document.getElementById('myLedgerTotalCredit').textContent = 'PKR ' + Number(data.total_credit || 0).toLocaleString();
                document.getElementById('myLedgerCurrentBal').textContent = 'PKR ' + Number(data.balance || 0).toLocaleString();

                if (!data.ledger || data.ledger.length === 0) {
                    document.getElementById('myLedgerTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">Aapke khata mein abhi koi transaction record nahi hai.</td></tr>';
                    return;
                }

                let html = '';
                data.ledger.forEach(tx => {
                    const deb = (tx.debit > 0) ? 'PKR ' + Number(tx.debit).toLocaleString() : '-';
                    const cred = (tx.credit > 0) ? '<span class="text-success fw-bold">PKR ' + Number(tx.credit).toLocaleString() + '</span>' : '-';
                    html += `
                        <tr>
                            <td><small class="text-muted">${(tx.date || '').split(' ')[0]}</small></td>
                            <td>
                                <div>${tx.description}</div>
                                ${tx.ref ? '<small class="text-muted">Ref: <code>' + tx.ref + '</code></small>' : ''}
                            </td>
                            <td class="text-end fw-semibold">${deb}</td>
                            <td class="text-end">${cred}</td>
                        </tr>
                    `;
                });
                document.getElementById('myLedgerTableBody').innerHTML = html;
            } else {
                document.getElementById('myLedgerTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">' + (data.message || 'Error') + '</td></tr>';
            }
        } catch(e) {
            document.getElementById('myLedgerTableBody').innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">Khata load karne mein masla pesh aya.</td></tr>';
        }
    }

    function setCategoryFilter(cat, elem) {
        activeCategory = cat;
        document.querySelectorAll('.filter-pill').forEach(el => el.classList.remove('active'));
        elem.classList.add('active');
        filterCatalog();
    }

    function filterCatalog() {
        const query = document.getElementById('catalogSearch').value.toLowerCase().trim();
        const gridItems = document.querySelectorAll('.product-card-col');
        const tableRows = document.querySelectorAll('.product-table-row');
        let visibleCount = 0;

        gridItems.forEach(item => {
            const name = item.getAttribute('data-name');
            const cat = item.getAttribute('data-category');

            const matchesQuery = !query || name.includes(query);
            const matchesCat = (activeCategory === 'all') || (cat === activeCategory);

            if (matchesQuery && matchesCat) {
                item.style.display = 'block';
                visibleCount++;
            } else {
                item.style.display = 'none';
            }
        });

        tableRows.forEach(row => {
            const name = row.getAttribute('data-name');
            const cat = row.getAttribute('data-category');

            const matchesQuery = !query || name.includes(query);
            const matchesCat = (activeCategory === 'all') || (cat === activeCategory);

            if (matchesQuery && matchesCat) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });

        const noResults = document.getElementById('noResultsAlert');
        if (visibleCount === 0) {
            noResults.classList.remove('d-none');
        } else {
            noResults.classList.add('d-none');
        }
    }

    function toggleViewMode(mode) {
        const grid = document.getElementById('gridViewWrapper');
        const table = document.getElementById('tableViewWrapper');
        const gridBtn = document.getElementById('gridBtn');
        const tableBtn = document.getElementById('tableBtn');

        if (mode === 'table') {
            grid.classList.add('d-none');
            table.classList.remove('d-none');
            tableBtn.className = 'btn btn-sm btn-primary rounded-pill px-3';
            gridBtn.className = 'btn btn-sm btn-outline-secondary rounded-pill px-3';
        } else {
            table.classList.add('d-none');
            grid.classList.remove('d-none');
            gridBtn.className = 'btn btn-sm btn-primary rounded-pill px-3';
            tableBtn.className = 'btn btn-sm btn-outline-secondary rounded-pill px-3';
        }
    }

    function openPrintModal() {
        const modal = new bootstrap.Modal(document.getElementById('printModal'));
        modal.show();
    }

    function quickInquiryPrompt(name, sku, rate) {
        const qty = prompt("Wholesale Order for " + name + " (Rate: PKR " + rate.toLocaleString() + ")\nKitni quantity required hai?", "10");
        if (qty && parseInt(qty) > 0) {
            const total = parseInt(qty) * rate;
            const text = "Assalam-o-Alaikum, I want to book " + qty + " pcs of " + name + " (SKU: " + sku + ") at Wholesale Rate PKR " + rate.toLocaleString() + " each. Total: PKR " + total.toLocaleString() + ". Please confirm availability.";
            window.open("https://wa.me/<?php echo $storeWhatsApp; ?>?text=" + encodeURIComponent(text), "_blank");
        }
    }

    function viewCatalogImage(imgSrc, title, rate, sku) {
        document.getElementById('catModalImg').src = imgSrc;
        document.getElementById('catModalTitle').textContent = title || 'Product Photo';
        document.getElementById('catModalSubtitle').textContent = sku ? 'SKU: ' + sku : '';
        document.getElementById('catModalRate').textContent = rate || '';
        const modal = new bootstrap.Modal(document.getElementById('catalogImageModal'));
        modal.show();
    }
    </script>

    <!-- Modal: High-Res Catalog Image Lightbox -->
    <div class="modal fade" id="catalogImageModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white py-3">
                    <div>
                        <h5 class="modal-title fw-bold mb-0 text-white" id="catModalTitle">Product Photo</h5>
                        <small class="text-white-50" id="catModalSubtitle"></small>
                    </div>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0 text-center bg-black bg-opacity-10 position-relative">
                    <img id="catModalImg" src="" alt="Enlarged Product Photo" class="img-fluid object-fit-contain p-3" style="max-height: 75vh; width: 100%;">
                </div>
                <div class="modal-footer bg-light d-flex justify-content-between">
                    <div>
                        <span class="text-muted small">Dealer Wholesale Rate:</span>
                        <strong class="text-success fs-5 ms-1" id="catModalRate"></strong>
                    </div>
                    <button type="button" class="btn btn-secondary btn-sm px-4" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
