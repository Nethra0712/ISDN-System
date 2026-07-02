<?php
// ============================================
// modules/reports/rdc_weekly_summary.php
// Individual Weekly Performance Summary for RDC
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['rdc_staff','admin','ho_manager']);

$user_role = $_SESSION['role'];
$user_prov = $_SESSION['province'] ?? 'None';
$my_center_id = null;
$center_name = "System";

if ($user_role === 'rdc_staff') {
    $c_stmt = mysqli_query($conn, "SELECT id, name FROM distribution_centers WHERE province='$user_prov' AND status='active'");
    if ($c_row = mysqli_fetch_assoc($c_stmt)) {
        $my_center_id = $c_row['id'];
        $center_name = $c_row['name'];
    } else {
        $my_center_id = -1;
    }
}

$page_title = "RDC Weekly Summary - $center_name";

// Date Range (Current Week)
$start_of_week = date('Y-m-d', strtotime('monday this week'));
$end_of_week   = date('Y-m-d', strtotime('sunday this week'));

$daily_summary = mysqli_query($conn,
    "SELECT order_date, COUNT(*) AS order_count, SUM(total_amount) AS revenue
     FROM orders
     WHERE center_id = $my_center_id AND order_date BETWEEN '$start_of_week' AND '$end_of_week'
     GROUP BY order_date ORDER BY order_date ASC"
);

$totals = mysqli_fetch_assoc(mysqli_query($conn,
    "SELECT COUNT(*) AS total_orders, SUM(total_amount) AS total_revenue
     FROM orders
     WHERE center_id = $my_center_id AND order_date BETWEEN '$start_of_week' AND '$end_of_week'"
));

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <div><a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a></div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer me-1"></i>Print Weekly Report</button>
</div>

<div class="page-card mb-4 border-primary">
    <div class="page-card-header bg-primary text-white">
        <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Weekly Performance Summary: <?= date('d M', strtotime($start_of_week)) ?> - <?= date('d M Y', strtotime($end_of_week)) ?></h5>
    </div>
    <div class="row g-0 text-center border-bottom">
        <div class="col-6 py-4 border-end">
            <div class="text-muted small text-uppercase fw-bold">Total Orders Created</div>
            <div class="h2 mb-0"><?= number_format($totals['total_orders']) ?></div>
        </div>
        <div class="col-6 py-4">
            <div class="text-muted small text-uppercase fw-bold">Total Sales Value</div>
            <div class="h2 mb-0 text-primary">LKR <?= number_format($totals['total_revenue'], 2) ?></div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Day / Date</th>
                    <th class="text-center">Orders</th>
                    <th class="text-end">Revenue Generated</th>
                    <th class="text-end">Avg. Value</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
                $data_days = [];
                while ($r = mysqli_fetch_assoc($daily_summary)) {
                    $data_days[date('l', strtotime($r['order_date']))] = $r;
                }
                
                foreach ($days as $day): 
                    $curr_dt = date('Y-m-d', strtotime("$day this week"));
                    $row = $data_days[$day] ?? ['order_count'=>0, 'revenue'=>0];
                    $aov = $row['order_count'] > 0 ? $row['revenue'] / $row['order_count'] : 0;
                ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= $day ?></div>
                        <small class="text-muted"><?= date('d M Y', strtotime($curr_dt)) ?></small>
                    </td>
                    <td class="text-center"><?= $row['order_count'] ?></td>
                    <td class="text-end fw-semibold">LKR <?= number_format($row['revenue'], 2) ?></td>
                    <td class="text-end text-muted">LKR <?= number_format($aov, 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info py-2 small border-0">
    <i class="bi bi-info-circle me-1"></i> This report summarizes all orders assigned to <strong><?= htmlspecialchars($center_name) ?></strong> for the current active week.
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
