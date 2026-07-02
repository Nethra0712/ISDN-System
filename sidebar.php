<?php
// ============================================
// includes/sidebar.php
// Dynamic sidebar based on user role
// ============================================

$role = $_SESSION['role'];
$current = basename($_SERVER['PHP_SELF']);

// Helper to mark active links
function is_active($file) {
    return basename($_SERVER['PHP_SELF']) === $file ? 'active' : '';
}
function is_active_path($path) {
    return strpos($_SERVER['PHP_SELF'], $path) !== false ? 'active' : '';
}
?>
<nav id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="d-flex align-items-center gap-2 mb-1">
            <i class="bi bi-building text-white" style="font-size:1.4rem;"></i>
            <h5>ISDN</h5>
        </div>
        <small>Distribution Management</small>
    </div>

    <!-- Navigation -->
    <ul class="nav flex-column mt-2 pb-4">

        <!-- DASHBOARD -->
        <div class="sidebar-section">Main</div>

        <?php if ($role === 'admin'): ?>
        <li class="nav-item">
            <a class="nav-link <?= is_active('admin_dashboard.php') ?>" href="<?= BASE_URL ?>dashboards/admin_dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <?php elseif ($role === 'ho_manager'): ?>
        <li class="nav-item">
            <a class="nav-link <?= is_active('ho_dashboard.php') ?>" href="<?= BASE_URL ?>dashboards/ho_dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <?php elseif ($role === 'rdc_staff'): ?>
        <li class="nav-item">
            <a class="nav-link <?= is_active('rdc_dashboard.php') ?>" href="<?= BASE_URL ?>dashboards/rdc_dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <?php elseif ($role === 'logistics_staff'): ?>
        <li class="nav-item">
            <a class="nav-link <?= is_active('logistics_dashboard.php') ?>" href="<?= BASE_URL ?>dashboards/logistics_dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
        </li>
        <?php elseif ($role === 'customer'): ?>
        <li class="nav-item">
            <a class="nav-link <?= is_active('customer_dashboard.php') ?>" href="<?= BASE_URL ?>dashboards/customer_dashboard.php">
                <i class="bi bi-speedometer2"></i> My Dashboard
            </a>
        </li>
        <?php endif; ?>

        <!-- ADMIN MENU -->
        <?php if ($role === 'admin'): ?>
        <div class="sidebar-section">Administration</div>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('modules/admin/users') ?>" href="<?= BASE_URL ?>modules/admin/users.php">
                <i class="bi bi-people"></i> User Management
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('modules/admin/assign_logistics') ?>" href="<?= BASE_URL ?>modules/admin/assign_logistics.php">
                <i class="bi bi-truck-front"></i> Assign Logistics
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('distribution_centers') ?>" href="<?= BASE_URL ?>modules/admin/distribution_centers.php">
                <i class="bi bi-building-gear"></i> Distribution Centers
            </a>
        </li>
        <?php endif; ?>

        <!-- PRODUCTS & INVENTORY -->
        <?php if (in_array($role, ['admin','ho_manager','rdc_staff'])): ?>
        <div class="sidebar-section">Catalog</div>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('products') ?>" href="<?= BASE_URL ?>modules/products/list.php">
                <i class="bi bi-box-seam"></i> Products
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('inventory') ?>" href="<?= BASE_URL ?>modules/inventory/list.php">
                <i class="bi bi-archive"></i> Inventory
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('transfer') ?>" href="<?= BASE_URL ?>modules/inventory/transfer.php">
                <i class="bi bi-arrow-left-right"></i> Stock Transfer
            </a>
        </li>
        <?php endif; ?>

        <!-- ORDERS -->
        <?php if (in_array($role, ['admin','ho_manager','rdc_staff'])): ?>
        <div class="sidebar-section">Sales</div>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('orders') ?>" href="<?= BASE_URL ?>modules/orders/list.php">
                <i class="bi bi-cart3"></i> Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('invoices') ?>" href="<?= BASE_URL ?>modules/orders/invoices.php">
                <i class="bi bi-receipt"></i> Invoices
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('modules/returns/list.php') ?>" href="<?= BASE_URL ?>modules/returns/list.php">
                <i class="bi bi-arrow-counterclockwise"></i> Product Returns
            </a>
        </li>
        <?php endif; ?>

        <!-- CUSTOMER MENU -->
        <?php if ($role === 'customer'): ?>
        <div class="sidebar-section">My Account</div>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('dashboards/customer_dashboard.php') ?>" href="<?= BASE_URL ?>dashboards/customer_dashboard.php">
                <i class="bi bi-shop"></i> Product Catalog
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('modules/orders/cart.php') ?>" href="<?= BASE_URL ?>modules/orders/cart.php">
                <i class="bi bi-cart3"></i> My Shopping Cart
                <?php if (!empty($_SESSION['cart'])): ?>
                <span class="badge bg-danger rounded-pill ms-auto"><?= array_sum($_SESSION['cart']) ?></span>
                <?php endif; ?>
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('my_orders') ?>" href="<?= BASE_URL ?>modules/orders/my_orders.php">
                <i class="bi bi-list-check"></i> My Orders
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('my_invoices') ?>" href="<?= BASE_URL ?>modules/orders/my_invoices.php">
                <i class="bi bi-receipt"></i> My Invoices
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('modules/returns/my_returns.php') ?>" href="<?= BASE_URL ?>modules/returns/my_returns.php">
                <i class="bi bi-arrow-counterclockwise"></i> My Returns
            </a>
        </li>
        <?php endif; ?>

        <!-- PURCHASE ORDERS -->
        <?php if (in_array($role, ['admin','ho_manager','rdc_staff'])): ?>
        <div class="sidebar-section">Procurement</div>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('purchase_orders') ?>" href="<?= BASE_URL ?>modules/purchase_orders/list.php">
                <i class="bi bi-truck"></i> Purchase Orders
            </a>
        </li>
        <?php endif; ?>

        <!-- DELIVERY -->
        <?php if (in_array($role, ['admin','ho_manager','logistics_staff'])): ?>
        <div class="sidebar-section">Logistics</div>
        <li class="nav-item">
            <?php if ($role === 'logistics_staff'): ?>
            <a class="nav-link <?= is_active_path('delivery/logistics_view.php') ?>" href="<?= BASE_URL ?>modules/delivery/logistics_view.php">
                <i class="bi bi-truck"></i> Active Deliveries
            </a>
            <?php else: ?>
            <a class="nav-link <?= is_active_path('delivery/list.php') ?>" href="<?= BASE_URL ?>modules/delivery/list.php">
                <i class="bi bi-geo-alt"></i> Active Deliveries
            </a>
            <?php endif; ?>
        </li>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('delivery/history.php') ?>" href="<?= BASE_URL ?>modules/delivery/history.php">
                <i class="bi bi-clock-history"></i> Delivery History
            </a>
        </li>
        <?php endif; ?>

        <!-- REPORTS -->
        <?php if (in_array($role, ['admin','ho_manager','rdc_staff'])): ?>
        <div class="sidebar-section">Analytics</div>
        <li class="nav-item">
            <a class="nav-link <?= is_active_path('reports') ?>" href="<?= BASE_URL ?>modules/reports/dashboard.php">
                <i class="bi bi-bar-chart-line"></i> Reports
            </a>
        </li>
        <?php endif; ?>

        <!-- LOGOUT -->
        <div class="sidebar-section">Account</div>
        <li class="nav-item">
            <a class="nav-link" href="<?= BASE_URL ?>auth/logout.php">
                <i class="bi bi-box-arrow-left"></i> Logout
            </a>
        </li>
    </ul>
</nav>
