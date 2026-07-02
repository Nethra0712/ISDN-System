<?php
// ============================================
// modules/reports/rdc_stock_analysis.php
// Monthly Stock Movement Analysis for RDC
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

$page_title = "Monthly Stock Analysis - $center_name";

// Monthly Sales per Product
$start_month = date('Y-m-01');
$end_month   = date('Y-m-t');

$stock_analysis = mysqli_query($conn,
    "SELECT p.id, p.name, p.sku, p.reorder_level, i.quantity_on_hand,
            (SELECT SUM(quantity) FROM order_items oi JOIN orders o ON oi.order_id=o.id 
             WHERE oi.product_id=p.id AND o.center_id=$my_center_id AND o.order_date BETWEEN '$start_month' AND '$end_month') AS sold_this_month
     FROM inventory i
     JOIN products p ON i.product_id = p.id
     WHERE i.center_id = $my_center_id
     ORDER BY sold_this_month DESC"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <div><a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a></div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer me-1"></i>Print Analysis</button>
</div>

<div class="page-card mb-4 border-success">
    <div class="page-card-header bg-success text-white">
        <h5 class="mb-0"><i class="bi bi-graph-up-arrow me-2"></i>Stock Movement Analysis: <?= date('F Y') ?></h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Product Details</th>
                    <th class="text-center">Current Stock</th>
                    <th class="text-center">Sold This Month</th>
                    <th class="text-center">Inventory Turnover</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = mysqli_fetch_assoc($stock_analysis)): 
                    $sold = (int)$r['sold_this_month'];
                    $stock = (int)$r['quantity_on_hand'];
                    $low = $stock <= $r['reorder_level'];
                    
                    // Turnover logic: How fast is it moving compared to what we have?
                    $turnover = "Stable";
                    $turn_class = "secondary";
                    if ($sold > $stock && $stock > 0) { $turnover = "High Demand"; $turn_class = "danger"; }
                    elseif ($sold == 0 && $stock > 50) { $turnover = "Stagnant"; $turn_class = "warning"; }
                ?>
                <tr>
                    <td>
                        <div class="fw-bold"><?= htmlspecialchars($r['name']) ?></div>
                        <code><?= htmlspecialchars($r['sku']) ?></code>
                    </td>
                    <td class="text-center fw-bold"><?= $stock ?></td>
                    <td class="text-center text-primary fw-bold"><?= $sold ?></td>
                    <td class="text-center">
                        <span class="badge bg-<?= $turn_class ?>-subtle text-<?= $turn_class ?> border border-<?= $turn_class ?>"><?= $turnover ?></span>
                    </td>
                    <td class="text-center">
                        <?php if ($low): ?>
                            <span class="badge bg-danger">REORDER</span>
                        <?php else: ?>
                            <span class="badge bg-success">HEALTHY</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($stock_analysis) === 0): ?>
                <tr><td colspan="5" class="text-center py-4 text-muted">No inventory data available for this branch.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="row g-3">
    <div class="col-md-6">
        <div class="alert alert-warning border-0 small">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>High Demand</strong> items have monthly sales exceeding current stock. Priority Purchase Orders suggested.
        </div>
    </div>
    <div class="col-md-6">
        <div class="alert alert-secondary border-0 small">
            <i class="bi bi-info-circle me-1"></i>
            <strong>Stagnant</strong> items have zero sales this month but high inventory levels. Consider province-specific promos.
        </div>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
