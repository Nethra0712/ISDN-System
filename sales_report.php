<?php
// ============================================
// modules/reports/sales_report.php
// Monthly and Historical Sales Report
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager']);

$page_title = "Monthly Sales Report";

// Historical Monthly Summary
$history = mysqli_query($conn,
    "SELECT DATE_FORMAT(order_date, '%Y-%m') AS ym, 
            DATE_FORMAT(order_date, '%M %Y') AS month_label,
            COUNT(*) AS order_count,
            SUM(total_amount) AS revenue
     FROM orders
     GROUP BY ym ORDER BY ym DESC"
);

// Daily breakdown for current month
$current_month = date('Y-m');
$daily = mysqli_query($conn,
    "SELECT order_date, COUNT(*) AS cnt, SUM(total_amount) AS total
     FROM orders 
     WHERE DATE_FORMAT(order_date, '%Y-%m') = '$current_month'
     GROUP BY order_date ORDER BY order_date DESC"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <div><a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a></div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer me-1"></i>Print Report</button>
</div>

<div class="row g-3">
    <div class="col-xl-7">
        <div class="page-card">
            <div class="page-card-header">
                <h5><i class="bi bi-calendar3 me-2"></i>Monthly Sales History</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Month</th>
                            <th class="text-center">Total Orders</th>
                            <th class="text-end">Total Revenue</th>
                            <th class="text-end">Avg. Order Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($r = mysqli_fetch_assoc($history)): 
                            $aov = $r['order_count'] > 0 ? $r['revenue'] / $r['order_count'] : 0;
                        ?>
                        <tr>
                            <td><strong><?= $r['month_label'] ?></strong></td>
                            <td class="text-center"><?= $r['order_count'] ?></td>
                            <td class="text-end fw-bold">LKR <?= number_format($r['revenue'], 2) ?></td>
                            <td class="text-end text-muted">LKR <?= number_format($aov, 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-5">
        <div class="page-card">
            <div class="page-card-header">
                <h5><i class="bi bi-graph-up me-2"></i>Daily Breakdown (<?= date('F Y') ?>)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-sm mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th class="text-center">Orders</th>
                            <th class="text-end">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($d = mysqli_fetch_assoc($daily)): ?>
                        <tr>
                            <td><small><?= date('d M Y', strtotime($d['order_date'])) ?></small></td>
                            <td class="text-center"><?= $d['cnt'] ?></td>
                            <td class="text-end">LKR <?= number_format($d['total'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($daily) === 0): ?>
                        <tr><td colspan="3" class="text-center py-3 text-muted">No sales recorded yet this month</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
