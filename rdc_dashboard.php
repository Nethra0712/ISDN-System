<?php
session_start();
define('BASE_URL', '../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['rdc_staff']);

$page_title = "RDC Staff Dashboard";

// Determine the user's assigned center based on their province
$user_prov = $_SESSION['province'] ?? 'None';
$c_stmt = mysqli_query($conn, "SELECT id, name FROM distribution_centers WHERE province='$user_prov' AND status='active'");
$center_data = mysqli_fetch_assoc($c_stmt);
$my_center_id = $center_data ? $center_data['id'] : -1;

$total_products  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM products WHERE status='active'"))['cnt'];
$total_inventory = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(quantity_on_hand),0) AS cnt FROM inventory WHERE center_id=$my_center_id"))['cnt'];
$my_po = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM purchase_orders WHERE center_id=$my_center_id AND status IN ('draft','submitted')"))['cnt'];
$low_stock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM inventory i JOIN products p ON i.product_id=p.id WHERE i.center_id=$my_center_id AND i.quantity_on_hand <= p.reorder_level"))['cnt'];

// Low stock items for THIS center
$low_items = mysqli_query($conn,
    "SELECT p.name, p.sku, p.reorder_level, i.quantity_on_hand, dc.name AS center
     FROM inventory i JOIN products p ON i.product_id=p.id JOIN distribution_centers dc ON i.center_id=dc.id
     WHERE i.center_id=$my_center_id AND i.quantity_on_hand <= p.reorder_level ORDER BY i.quantity_on_hand ASC LIMIT 10"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="kpi-card blue"><div class="kpi-label">Active Products</div><div class="kpi-value"><?= $total_products ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card green"><div class="kpi-label">Total Stock Units</div><div class="kpi-value"><?= number_format($total_inventory) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card blue"><div class="kpi-label">My Open POs</div><div class="kpi-value"><?= $my_po ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card red"><div class="kpi-label">Low Stock Alerts</div><div class="kpi-value"><?= $low_stock ?></div></div></div>
</div>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-exclamation-triangle-fill text-warning me-2"></i>Low Stock Alerts</h5>
        <a href="<?= BASE_URL ?>modules/purchase_orders/create.php" class="btn btn-sm btn-primary">
            <i class="bi bi-plus me-1"></i>Create PO
        </a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>SKU</th><th>Product</th><th>Center</th><th>On Hand</th><th>Reorder Level</th></tr></thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($low_items)): ?>
                <tr>
                    <td><code><?= htmlspecialchars($row['sku']) ?></code></td>
                    <td><?= htmlspecialchars($row['name']) ?></td>
                    <td><?= htmlspecialchars($row['center']) ?></td>
                    <td><span class="badge bg-danger"><?= $row['quantity_on_hand'] ?></span></td>
                    <td><?= $row['reorder_level'] ?></td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($low_items) === 0): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">All stock levels are healthy</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
