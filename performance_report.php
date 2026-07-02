<?php
// ============================================
// modules/reports/performance_report.php
// DC Performance Comparative Report
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager']);

$page_title = "DC Performance Report";

// Performance Metrics per Center
$performance = mysqli_query($conn,
    "SELECT dc.id, dc.name, dc.province,
            (SELECT COUNT(*) FROM orders WHERE center_id = dc.id) AS total_orders,
            (SELECT COUNT(*) FROM orders WHERE center_id = dc.id AND status='delivered') AS fulfilled,
            (SELECT SUM(total_amount) FROM orders WHERE center_id = dc.id) AS total_revenue
     FROM distribution_centers dc
     ORDER BY total_revenue DESC"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <div><a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a></div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer me-1"></i>Print Report</button>
</div>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-speedometer2 me-2"></i>Distribution Center Performance Leaderboard</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Center Name</th>
                    <th>Province</th>
                    <th class="text-center">Total Orders</th>
                    <th class="text-center">Fulfilled</th>
                    <th class="text-center">Fulfillment rate</th>
                    <th class="text-end">Total Revenue</th>
                    <th class="text-end">Avg. Order Value</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = mysqli_fetch_assoc($performance)): 
                    $rate = $r['total_orders'] > 0 ? ($r['fulfilled'] / $r['total_orders']) * 100 : 0;
                    $aov  = $r['total_orders'] > 0 ? $r['total_revenue'] / $r['total_orders'] : 0;
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
                    <td><span class="badge bg-info text-dark"><?= $r['province'] ?></span></td>
                    <td class="text-center"><?= number_format($r['total_orders']) ?></td>
                    <td class="text-center"><?= number_format($r['fulfilled']) ?></td>
                    <td class="text-center">
                        <div class="progress" style="height: 6px; width: 80px; margin: 0 auto 4px;">
                            <div class="progress-bar bg-success" style="width: <?= $rate ?>%"></div>
                        </div>
                        <small><?= number_format($rate, 1) ?>%</small>
                    </td>
                    <td class="text-end fw-bold text-primary">LKR <?= number_format($r['total_revenue'], 2) ?></td>
                    <td class="text-end text-muted">LKR <?= number_format($aov, 2) ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="alert alert-info py-2 small border-0">
    <i class="bi bi-info-circle me-1"></i> 
    Performance is calculated based on lifetime data. Revenue represents the sum of all order totals assigned to the center.
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
