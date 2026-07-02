<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','rdc_staff']);

$user_role = $_SESSION['role'];
$user_prov = $_SESSION['province'] ?? 'None';
$my_center_id = null;

if ($user_role === 'rdc_staff') {
    $c_stmt = mysqli_query($conn, "SELECT id FROM distribution_centers WHERE province='$user_prov' AND status='active'");
    if ($c_row = mysqli_fetch_assoc($c_stmt)) {
        $my_center_id = $c_row['id'];
    } else {
        $my_center_id = -1;
    }
}

$where_orders   = $my_center_id ? "WHERE center_id=$my_center_id" : "";
$where_payments = $my_center_id ? "WHERE invoice_id IN (SELECT id FROM invoices WHERE order_id IN (SELECT id FROM orders WHERE center_id=$my_center_id))" : "";
$where_oi       = $my_center_id ? "WHERE oi.order_id IN (SELECT id FROM orders WHERE center_id=$my_center_id)" : "";

$page_title = "Reports & Analytics";

// KPI summary
$total_revenue = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(amount_paid),0) AS t FROM payments $where_payments"))['t'];
$total_orders  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM orders $where_orders"))['t'];
$delivered     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM orders " . ($where_orders ? "$where_orders AND " : "WHERE ") . "status='delivered'"))['t'];
$total_customers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS t FROM users WHERE role='customer' AND status='active' " . ($my_center_id ? "AND province='$user_prov'" : "")))['t'];

// Monthly revenue (12 months)
$monthly = mysqli_query($conn,
    "SELECT DATE_FORMAT(payment_date,'%b %Y') AS month,
            DATE_FORMAT(payment_date,'%Y-%m') AS ym,
            SUM(amount_paid) AS total
     FROM payments
     " . ($where_payments ?: "WHERE 1=1") . " AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
     GROUP BY ym ORDER BY ym ASC"
);
$monthly_labels = []; $monthly_data = [];
while ($r = mysqli_fetch_assoc($monthly)) {
    $monthly_labels[] = $r['month'];
    $monthly_data[]   = (float)$r['total'];
}

// Orders by status (pie)
$status_data = [];
$statuses = ['pending','approved','processing','shipped','delivered','cancelled'];
foreach ($statuses as $s) {
    $cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS c FROM orders " . ($where_orders ? "$where_orders AND " : "WHERE ") . "status='$s'"))['c'];
    $status_data[$s] = (int)$cnt;
}

// Top products by revenue
$top_products = mysqli_query($conn,
    "SELECT p.name, SUM(oi.subtotal) AS revenue
     FROM order_items oi JOIN products p ON oi.product_id=p.id
     $where_oi
     GROUP BY oi.product_id ORDER BY revenue DESC LIMIT 5"
);
$tp_labels = []; $tp_data = [];
while ($r = mysqli_fetch_assoc($top_products)) {
    $tp_labels[] = $r['name'];
    $tp_data[]   = (float)$r['revenue'];
}

// Inventory summary per center
$inventory_summary = mysqli_query($conn,
    "SELECT dc.name, SUM(i.quantity_on_hand) AS total_stock
     FROM inventory i JOIN distribution_centers dc ON i.center_id=dc.id
     GROUP BY i.center_id ORDER BY total_stock DESC"
);
$inv_labels = []; $inv_data = [];
while ($r = mysqli_fetch_assoc($inventory_summary)) {
    $inv_labels[] = $r['name'];
    $inv_data[]   = (int)$r['total_stock'];
}

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<!-- Reports Hub Section -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="page-card py-3">
            <h6 class="text-muted small text-uppercase fw-bold mb-3 px-1"><i class="bi bi-journal-text me-2"></i>Detailed Reports Hub</h6>
            <div class="d-flex flex-wrap gap-2">
                <?php if ($user_role !== 'rdc_staff'): ?>
                    <a href="stock_report.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-box-seam me-1"></i> Stock Report</a>
                <?php endif; ?>
                <a href="due_payment_report.php" class="btn btn-sm btn-outline-primary"><i class="bi bi-cash-stack me-1"></i> Due Payment Report</a>
                <?php if ($user_role !== 'rdc_staff'): ?>
                    <a href="sales_report.php" class="btn btn-sm btn-outline-info text-dark"><i class="bi bi-cart-check me-1"></i> Sales Report</a>
                    <a href="revenue_report.php" class="btn btn-sm btn-outline-success"><i class="bi bi-currency-dollar me-1"></i> Revenue Report</a>
                    <a href="performance_report.php" class="btn btn-sm btn-outline-warning text-dark"><i class="bi bi-speedometer2 me-1"></i> DC Performance</a>
                <?php else: ?>
                    <a href="rdc_weekly_summary.php" class="btn btn-sm btn-outline-info text-dark"><i class="bi bi-calendar-range me-1"></i> Weekly Summary</a>
                    <a href="rdc_stock_analysis.php" class="btn btn-sm btn-outline-success"><i class="bi bi-graph-up-arrow me-1"></i> Stock Analysis</a>
                <?php endif; ?>
                <button onclick="window.print()" class="btn btn-sm btn-dark ms-auto"><i class="bi bi-printer me-1"></i> Print Overview</button>
            </div>
        </div>
    </div>
</div>

<!-- KPI Summary -->
<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="kpi-card green"><div class="kpi-label">Total Revenue</div><div class="kpi-value" style="font-size:1.2rem;">LKR <?= number_format($total_revenue) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card blue"><div class="kpi-label">Total Orders</div><div class="kpi-value"><?= $total_orders ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card green"><div class="kpi-label">Delivered</div><div class="kpi-value"><?= $delivered ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card blue"><div class="kpi-label">Active Customers</div><div class="kpi-value"><?= $total_customers ?></div></div></div>
</div>

<div class="row g-3 mb-3">
    <!-- Monthly Revenue Bar -->
    <div class="col-xl-8">
        <div class="page-card">
            <div class="page-card-header"><h5><i class="bi bi-bar-chart me-2"></i>Monthly Revenue (Last 12 Months)</h5></div>
            <canvas id="revenueChart" height="80"></canvas>
        </div>
    </div>

    <!-- Order Status Doughnut -->
    <div class="col-xl-4">
        <div class="page-card">
            <div class="page-card-header"><h5><i class="bi bi-pie-chart me-2"></i>Orders by Status</h5></div>
            <canvas id="statusChart" height="150"></canvas>
        </div>
    </div>
</div>

<div class="row g-3">
    <!-- Top Products Bar -->
    <div class="col-xl-6">
        <div class="page-card">
            <div class="page-card-header"><h5><i class="bi bi-trophy me-2"></i>Top 5 Products by Revenue</h5></div>
            <canvas id="topProductChart" height="120"></canvas>
        </div>
    </div>

    <!-- Inventory Summary across Centers -->
    <div class="col-xl-6">
        <div class="page-card">
            <div class="page-card-header"><h5><i class="bi bi-houses me-2"></i>Inventory Summary across Centers</h5></div>
            <canvas id="inventoryChart" height="120"></canvas>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // 1. Monthly Revenue Bar Chart
    new Chart(document.getElementById('revenueChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($monthly_labels ?: ['No Data']) ?>,
            datasets: [{ label: 'Revenue (LKR)', data: <?= json_encode($monthly_data ?: [0]) ?>,
                backgroundColor: 'rgba(26,58,92,0.75)', borderColor: '#1a3a5c', borderWidth: 1, borderRadius: 4 }]
        },
        options: {
            responsive: true, plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => 'LKR ' + v.toLocaleString() } } }
        }
    });

    // 2. Order Status Doughnut
    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Pending','Approved','Processing','Shipped','Delivered','Cancelled'],
            datasets: [{ data: <?= json_encode(array_values($status_data)) ?>,
                backgroundColor: ['#ffc107','#0d6efd','#6f42c1','#0dcaf0','#198754','#dc3545'] }]
        },
        options: { responsive: true, plugins: { legend: { position: 'bottom', labels: { font: { size: 11 } } } } }
    });

    // 3. Top Products Horizontal Bar
    new Chart(document.getElementById('topProductChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($tp_labels ?: ['No Data']) ?>,
            datasets: [{ label: 'Revenue', data: <?= json_encode($tp_data ?: [0]) ?>,
                backgroundColor: 'rgba(25,135,84,0.7)', borderColor: '#198754', borderWidth: 1, borderRadius: 4 }]
        },
        options: {
            indexAxis: 'y', responsive: true, plugins: { legend: { display: false } },
            scales: { x: { ticks: { callback: v => 'LKR ' + v.toLocaleString() } } }
        }
    });

    // 4. Inventory Summary across Centers (Horizontal Bar)
    new Chart(document.getElementById('inventoryChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($inv_labels ?: ['No Centers']) ?>,
            datasets: [{ label: 'Total Stock Units', data: <?= json_encode($inv_data ?: [0]) ?>,
                backgroundColor: 'rgba(111, 66, 193, 0.7)', borderColor: '#6f42c1', borderWidth: 1, borderRadius: 4 }]
        },
        options: {
            indexAxis: 'y', responsive: true, plugins: { legend: { display: false } },
            scales: { x: { beginAtZero: true, title: { display: true, text: 'Stock Units' } } }
        }
    });
});
</script>

<?php include BASE_URL . 'includes/footer.php'; ?>
