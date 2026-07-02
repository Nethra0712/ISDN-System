<?php
session_start();
define('BASE_URL', '../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['ho_manager']);

$page_title = "HO Manager Dashboard";

$pending_orders  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE status='pending'"))['cnt'];
$pending_po      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM purchase_orders WHERE status='submitted'"))['cnt'];
$pending_users   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM users WHERE status='pending'"))['cnt'];
$total_deliveries= mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM deliveries WHERE status='in_transit'"))['cnt'];
$pending_transfers = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM stock_transfers WHERE status='pending'"))['cnt'];


$recent_po = mysqli_query($conn,
    "SELECT po.*, dc.name AS center_name, u.name AS created_by_name
     FROM purchase_orders po
     JOIN distribution_centers dc ON po.center_id=dc.id
     JOIN users u ON po.created_by=u.id
     ORDER BY po.created_at DESC LIMIT 8"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6">
        <div class="kpi-card orange">
            <div class="kpi-label">Pending Orders</div>
            <div class="kpi-value"><?= $pending_orders ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card blue">
            <div class="kpi-label">POs to Approve</div>
            <div class="kpi-value"><?= $pending_po ?></div>
        </div>
    </div>
    <div class="col-md-3 col-6">
        <div class="kpi-card orange">
            <div class="kpi-label">Pending Accounts</div>
            <div class="kpi-value"><?= $pending_users ?></div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <div class="kpi-card blue">
            <div class="kpi-label">Active Deliveries</div>
            <div class="kpi-value"><?= $total_deliveries ?></div>
        </div>
    </div>
    <div class="col-md-2 col-6">
        <a href="<?= BASE_URL ?>modules/inventory/transfer_list.php" class="text-decoration-none">
            <div class="kpi-card orange">
                <div class="kpi-label">Stock Transfers</div>
                <div class="kpi-value"><?= $pending_transfers ?></div>
            </div>
        </a>
    </div>
</div>


<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-truck me-2"></i>Recent Purchase Orders</h5>
        <a href="<?= BASE_URL ?>modules/purchase_orders/list.php" class="btn btn-sm btn-primary">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>PO Number</th><th>Center</th><th>Supplier</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php while ($po = mysqli_fetch_assoc($recent_po)): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($po['po_number']) ?></strong></td>
                    <td><?= htmlspecialchars($po['center_name']) ?></td>
                    <td><?= htmlspecialchars($po['supplier_name']) ?></td>
                    <td>LKR <?= number_format($po['total_amount']) ?></td>
                    <td><span class="badge badge-<?= $po['status'] ?>"><?= ucfirst($po['status']) ?></span></td>
                    <td>
                        <a href="<?= BASE_URL ?>modules/purchase_orders/view.php?id=<?= $po['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
