<?php
// includes/sidebar.php
if (!isset($activePage)) {
    $activePage = 'dashboard';
}
?>
<!-- Sidebar Navigation -->
<nav id="sidebar">
    <div>
        <div class="p-3 text-center border-bottom border-secondary border-opacity-25">
            <a href="index.php" class="text-decoration-none">
                <h4 class="fw-bold text-white mb-0 d-flex align-items-center justify-content-center">
                    <i class="fa-solid fa-mobile-screen-button text-primary me-2"></i>Smart Mobile
                </h4>
            </a>
            <small class="text-slate-400 text-muted" style="font-size: 0.78rem; letter-spacing: 0.5px;">WHOLESALE & RETAIL ERP</small>
        </div>
        
        <ul class="nav nav-pills flex-column mt-3">
            <li class="nav-item">
                <a href="index.php" class="nav-link <?php echo ($activePage === 'dashboard') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-pie me-2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a href="inventory.php" class="nav-link <?php echo ($activePage === 'inventory') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-boxes-stacked me-2"></i> Inventory / Stock
                </a>
            </li>
            <li class="nav-item">
                <a href="pos.php" class="nav-link <?php echo ($activePage === 'pos') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-cash-register me-2"></i> POS / Counter Sale
                </a>
            </li>
            <li class="nav-item">
                <a href="clients.php" class="nav-link <?php echo ($activePage === 'clients') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users me-2"></i> Wholesale Clients
                </a>
            </li>
            <li class="nav-item">
                <a href="ledgers.php" class="nav-link <?php echo ($activePage === 'ledgers') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-book-bookmark me-2"></i> Khata Ledgers
                </a>
            </li>
            <li class="nav-item">
                <a href="dailyclosing.php" class="nav-link <?php echo ($activePage === 'dailyclosing') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-file-invoice-dollar me-2"></i> Daily Closing
                </a>
            </li>
            <li class="nav-item">
                <a href="popup.php" class="nav-link <?php echo ($activePage === 'wholesale_portal') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-user-shield me-2"></i> Wholesale Portal
                </a>
            </li>
            <li class="nav-item">
                <a href="wholesale_catalog.php" class="nav-link <?php echo ($activePage === 'wholesale_catalog') ? 'active' : ''; ?>" target="_blank">
                    <i class="fa-solid fa-tags me-2 text-warning"></i> Dealer Rates View
                </a>
            </li>
        </ul>
    </div>

    <!-- Sidebar Bottom User & Status -->
    <div class="p-3 border-top border-secondary border-opacity-25 m-2 bg-dark bg-opacity-50 rounded-3">
        <div class="d-flex align-items-center justify-content-between mb-2 pb-2 border-bottom border-secondary border-opacity-25">
            <div class="d-flex align-items-center overflow-hidden">
                <div class="avatar-circle me-2 flex-shrink-0" style="width: 34px; height: 34px; font-size: 0.8rem; background-color: #2563eb; color: #fff;">
                    <?php echo htmlspecialchars($currentUser['avatar'] ?? 'SM'); ?>
                </div>
                <div class="text-truncate">
                    <small class="text-white fw-bold d-block text-truncate" style="font-size: 0.82rem;"><?php echo htmlspecialchars($currentUser['name'] ?? 'Admin User'); ?></small>
                    <span class="badge <?php echo htmlspecialchars($currentUser['badge_color'] ?? 'bg-primary'); ?> p-0 px-1" style="font-size: 0.68rem;"><?php echo htmlspecialchars($currentUser['role'] ?? 'Staff'); ?></span>
                </div>
            </div>
            <a href="logout.php" class="btn btn-outline-danger btn-sm p-1 px-2 ms-2" title="Sign Out / Lock Portal">
                <i class="fa-solid fa-right-from-bracket"></i>
            </a>
        </div>
        <div class="d-flex align-items-center">
            <span class="badge bg-success p-1 rounded-circle me-2" style="width: 9px; height: 9px;"> </span>
            <div>
                <small class="text-white-50 d-block" style="font-size: 0.74rem;">Terminal Online</small>
                <small class="text-muted" style="font-size: 0.70rem;">Auth Protected Session</small>
            </div>
        </div>
    </div>
</nav>
