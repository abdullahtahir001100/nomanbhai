<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$pageTitle = 'Smart Mobile - Wholesale Portal & Rates Unlock';
$activePage = 'wholesale_portal';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/includes/sidebar.php';
?>

    <!-- Main Content Area -->
    <div id="main-content">
        
        <!-- Header Banner with Wholesale Lock/Unlock Button -->
        <div class="card bg-primary text-white p-4 mb-4 rounded-3 shadow-sm border-0">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
                <div>
                    <h4 class="mb-1 fw-bold"><i class="fa-solid fa-mobile-screen-button me-2"></i>Smart Mobile Store</h4>
                    <p class="mb-0 text-white-50">Retail & Wholesale Verified Product Catalog</p>
                </div>
                <!-- Check / Toggle Button -->
                <div class="d-flex gap-2">
                    <a href="pos.php" class="btn btn-outline-light fw-bold">
                        <i class="fa-solid fa-cash-register me-1"></i> Open POS
                    </a>
                    <button id="wholesaleToggleBtn" class="btn btn-warning fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#wholesaleLoginModal">
                        <i class="fa-solid fa-lock me-1"></i> Unlock Wholesale Rates
                    </button>
                </div>
            </div>
        </div>

        <!-- Product Grid -->
        <div class="row g-4 mb-4">
            
            <!-- Product Item 1 -->
            <div class="col-md-6 col-lg-4">
                <div class="card product-card shadow-sm h-100 p-3 bg-white">
                    <div class="text-center py-4 bg-light rounded-3 mb-3">
                        <i class="fa-solid fa-mobile-button fa-4x text-secondary opacity-75"></i>
                    </div>
                    <span class="badge bg-secondary mb-2 align-self-start">Panels / Displays</span>
                    <h5 class="fw-bold mb-1">OLED Display Screen - A51</h5>
                    <p class="text-muted small mb-3">Compatible with Samsung Galaxy A51</p>
                    
                    <div class="mb-3">
                        <span class="text-muted d-block small">Retail Price:</span>
                        <span class="fs-5 fw-bold text-dark">PKR 4,500</span>
                    </div>

                    <!-- Wholesale Rate Box -->
                    <div class="wholesale-badge p-3 rounded-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="fw-bold"><i class="fa-solid fa-boxes-packing me-1"></i> Wholesale Price:</small>
                            <span class="wholesale-price fw-bold text-success fs-5 blur-price">PKR 3,200</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="pos.php" class="btn btn-outline-primary w-100 btn-sm"><i class="fa-solid fa-cart-plus me-1"></i> Sell via POS</a>
                    </div>
                </div>
            </div>

            <!-- Product Item 2 -->
            <div class="col-md-6 col-lg-4">
                <div class="card product-card shadow-sm h-100 p-3 bg-white">
                    <div class="text-center py-4 bg-light rounded-3 mb-3">
                        <i class="fa-solid fa-battery-full fa-4x text-secondary opacity-75"></i>
                    </div>
                    <span class="badge bg-info text-dark mb-2 align-self-start">Batteries</span>
                    <h5 class="fw-bold mb-1">Original Battery - iPhone 11</h5>
                    <p class="text-muted small mb-3">High Capacity 3110 mAh</p>
                    
                    <div class="mb-3">
                        <span class="text-muted d-block small">Retail Price:</span>
                        <span class="fs-5 fw-bold text-dark">PKR 3,800</span>
                    </div>

                    <!-- Wholesale Rate Box -->
                    <div class="wholesale-badge p-3 rounded-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="fw-bold"><i class="fa-solid fa-boxes-packing me-1"></i> Wholesale Price:</small>
                            <span class="wholesale-price fw-bold text-success fs-5 blur-price">PKR 2,600</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="pos.php" class="btn btn-outline-primary w-100 btn-sm"><i class="fa-solid fa-cart-plus me-1"></i> Sell via POS</a>
                    </div>
                </div>
            </div>

            <!-- Product Item 3 -->
            <div class="col-md-6 col-lg-4">
                <div class="card product-card shadow-sm h-100 p-3 bg-white">
                    <div class="text-center py-4 bg-light rounded-3 mb-3">
                        <i class="fa-solid fa-bolt fa-4x text-secondary opacity-75"></i>
                    </div>
                    <span class="badge bg-warning text-dark mb-2 align-self-start">Accessories</span>
                    <h5 class="fw-bold mb-1">Type-C Fast Charging Cable 65W</h5>
                    <p class="text-muted small mb-3">Braided 1.2m Super Fast Sync</p>
                    
                    <div class="mb-3">
                        <span class="text-muted d-block small">Retail Price:</span>
                        <span class="fs-5 fw-bold text-dark">PKR 850</span>
                    </div>

                    <!-- Wholesale Rate Box -->
                    <div class="wholesale-badge p-3 rounded-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="fw-bold"><i class="fa-solid fa-boxes-packing me-1"></i> Wholesale Price:</small>
                            <span class="wholesale-price fw-bold text-success fs-5 blur-price">PKR 520</span>
                        </div>
                    </div>
                    <div class="mt-3">
                        <a href="pos.php" class="btn btn-outline-primary w-100 btn-sm"><i class="fa-solid fa-cart-plus me-1"></i> Sell via POS</a>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Modal: Wholesale Client Login Popup -->
    <div class="modal fade" id="wholesaleLoginModal" tabindex="-1" data-bs-backdrop="static">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
                <div class="modal-header bg-dark text-white rounded-top-4 py-3">
                    <h5 class="modal-title fw-bold">
                        <i class="fa-solid fa-user-shield text-warning me-2"></i>Wholesale Client Verification
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-4">
                    <p class="text-muted small mb-4">ہول سیل ریٹس دیکھنے کے لیے اپنے کلائنٹ اکاؤنٹ کا فون نمبر اور 4 ہندسوں کا سیکیورٹی پن درج کریں۔</p>
                    
                    <form id="wholesaleLoginForm">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Client Phone Number</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-phone text-muted"></i></span>
                                <input type="text" id="clientPhone" class="form-control" placeholder="0300-1234567" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Wholesale Secret PIN / Password</label>
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="fa-solid fa-key text-muted"></i></span>
                                <input type="password" id="clientPin" class="form-control" placeholder="****" maxlength="6" required>
                            </div>
                        </div>

                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="rememberClient">
                            <label class="form-check-label small text-muted" for="rememberClient">
                                اس ڈیوائس پر لاگ ان رکھیں (Remember wholesale session)
                            </label>
                        </div>

                        <button type="submit" class="btn btn-warning w-100 fw-bold py-2 shadow-sm">
                            <i class="fa-solid fa-unlock me-1"></i> Verify & Show Rates
                        </button>
                    </form>
                </div>
                <div class="modal-footer bg-light justify-content-center py-2">
                    <small class="text-muted">نیا دکاندار اکاؤنٹ بنوانے کے لیے <a href="clients.php" class="text-decoration-none fw-semibold">کلائنٹس لسٹ</a> چیک کریں۔</small>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Logic to Handle Locking / Unlocking -->
    <script>
        document.getElementById('wholesaleLoginForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Modal Hide
            const modalEl = document.getElementById('wholesaleLoginModal');
            const modal = bootstrap.Modal.getInstance(modalEl);
            if (modal) {
                modal.hide();
            }

            // Unlock Prices (Remove Blur effect)
            const prices = document.querySelectorAll('.wholesale-price');
            prices.forEach(price => {
                price.classList.remove('blur-price');
            });

            // Update Top Button Status
            const toggleBtn = document.getElementById('wholesaleToggleBtn');
            toggleBtn.className = "btn btn-success fw-bold shadow-sm";
            toggleBtn.innerHTML = '<i class="fa-solid fa-circle-check me-1"></i> Wholesale Client Verified';
            toggleBtn.removeAttribute('data-bs-toggle');
        });
    </script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
