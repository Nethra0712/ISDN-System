<?php
// ============================================
// dashboards/admin_dashboard.php
// ============================================
session_start();
define('BASE_URL', '../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin']);

$page_title = "Admin Dashboard";

// ---- KPI Queries ----
// Total users
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users WHERE role != 'admin'"))['cnt'];

// Pending customers
$pending_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users WHERE status='pending'"))['cnt'];

// Total orders
$total_orders = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders"))['cnt'];

// Revenue (paid invoices)
$revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount_paid),0) AS total FROM payments"))['total'];

// Total products
$total_products = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM products"))['cnt'];

// Low stock (inventory below reorder_level)
$low_stock = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS cnt FROM inventory i JOIN products p ON i.product_id=p.id WHERE i.quantity_on_hand <= p.reorder_level"
))['cnt'];

// Pending stock transfers
$pending_transfers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM stock_transfers WHERE status='pending'"))['cnt'];


// Recent orders
$recent_orders = mysqli_query($conn,
    "SELECT o.*, u.name AS customer_name FROM orders o JOIN users u ON o.customer_id=u.id ORDER BY o.order_date DESC LIMIT 8"
);

// Monthly sales for chart (last 6 months)
$monthly = mysqli_query($conn,
    "SELECT DATE_FORMAT(payment_date,'%b %Y') AS month, SUM(amount_paid) AS total
     FROM payments
     WHERE payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
     GROUP BY YEAR(payment_date), MONTH(payment_date)
     ORDER BY payment_date ASC"
);
$chart_labels = [];
$chart_data   = [];
while ($row = mysqli_fetch_assoc($monthly)) {
    $chart_labels[] = $row['month'];
    $chart_data[]   = (float)$row['total'];
}

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<!-- KPI Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl-2 col-md-4 col-6">
        <div class="kpi-card blue">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Total Users</div>
                    <div class="kpi-value"><?= $total_users ?></div>
                </div>
                <i class="bi bi-people kpi-icon"></i>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="kpi-card orange">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Pending Approval</div>
                    <div class="kpi-value"><?= $pending_customers ?></div>
                </div>
                <i class="bi bi-hourglass kpi-icon"></i>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="kpi-card blue">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Total Orders</div>
                    <div class="kpi-value"><?= $total_orders ?></div>
                </div>
                <i class="bi bi-cart3 kpi-icon"></i>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="kpi-card green">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Revenue (LKR)</div>
                    <div class="kpi-value" style="font-size:1.3rem;"><?= number_format($revenue) ?></div>
                </div>
                <i class="bi bi-currency-exchange kpi-icon"></i>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="kpi-card blue">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Products</div>
                    <div class="kpi-value"><?= $total_products ?></div>
                </div>
                <i class="bi bi-box-seam kpi-icon"></i>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <div class="kpi-card red">
            <div class="d-flex justify-content-between align-items-start">
                <div>
                    <div class="kpi-label">Low Stock Items</div>
                    <div class="kpi-value"><?= $low_stock ?></div>
                </div>
                <i class="bi bi-exclamation-triangle kpi-icon"></i>
            </div>
        </div>
    </div>
    <div class="col-xl-2 col-md-4 col-6">
        <a href="<?= BASE_URL ?>modules/inventory/transfer_list.php" class="text-decoration-none">
            <div class="kpi-card orange">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="kpi-label">Stock Transfers</div>
                        <div class="kpi-value"><?= $pending_transfers ?></div>
                    </div>
                    <i class="bi bi-arrow-left-right kpi-icon"></i>
                </div>
            </div>
        </a>
    </div>
</div>


<div class="row g-3">
    <!-- Monthly Revenue Chart -->
    <div class="col-xl-7">
        <div class="page-card">
            <div class="page-card-header">
                <h5><i class="bi bi-bar-chart-line me-2"></i>Monthly Revenue</h5>
                <a href="<?= BASE_URL ?>modules/reports/dashboard.php" class="btn btn-sm btn-primary">Full Report</a>
            </div>
            <canvas id="revenueChart" height="80"></canvas>
        </div>
    </div>

    <!-- Recent Orders -->
    <div class="col-xl-5">
        <div class="page-card">
            <div class="page-card-header">
                <h5><i class="bi bi-clock-history me-2"></i>Recent Orders</h5>
                <a href="<?= BASE_URL ?>modules/orders/list.php" class="btn btn-sm btn-primary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Customer</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($o = mysqli_fetch_assoc($recent_orders)): ?>
                        <tr>
                            <td><small class="fw-bold"><?= htmlspecialchars($o['order_number']) ?></small></td>
                            <td><small><?= htmlspecialchars($o['customer_name']) ?></small></td>
                            <td><small>LKR <?= number_format($o['total_amount']) ?></small></td>
                            <td>
                                <span class="badge badge-<?= $o['status'] ?> rounded-pill" style="font-size:.7rem;">
                                    <?= ucfirst($o['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($recent_orders) === 0): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No orders yet</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Monthly Revenue Chart
    const ctx = document.getElementById('revenueChart').getContext('2d');
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chart_labels ?: ['No Data']) ?>,
            datasets: [{
                label: 'Revenue (LKR)',
                data: <?= json_encode($chart_data ?: [0]) ?>,
                backgroundColor: 'rgba(26, 58, 92, 0.75)',
                borderColor: '#1a3a5c',
                borderWidth: 1,
                borderRadius: 4,
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { callback: v => 'LKR ' + v.toLocaleString() }
                }
            }
        }
    });
});
</script>

<?php include BASE_URL . 'includes/footer.php'; ?>
