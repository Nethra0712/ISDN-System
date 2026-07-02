<?php
session_start();
define('BASE_URL', '../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['logistics_staff']);

$page_title = "Logistics Dashboard";

$my_id = $_SESSION['user_id'];
$assigned    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM deliveries WHERE assigned_to=$my_id AND status='out_for_delivery'"))['cnt'];
$delayed     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM deliveries WHERE assigned_to=$my_id AND status='delayed'"))['cnt'];
$delivered   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM deliveries WHERE assigned_to=$my_id AND status='delivered'"))['cnt'];
$pending_all = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM deliveries WHERE status='pending'"))['cnt'];

$my_deliveries = mysqli_query($conn,
    "SELECT d.*, o.order_number, u.name AS customer_name
     FROM deliveries d JOIN orders o ON d.order_id=o.id JOIN users u ON o.customer_id=u.id
     WHERE d.assigned_to=$my_id AND d.status != 'delivered'
     ORDER BY d.estimated_delivery ASC LIMIT 10"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="kpi-card orange"><div class="kpi-label">Ready for Shipment</div><div class="kpi-value"><?= $pending_all ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card blue"><div class="kpi-label">Out for Delivery</div><div class="kpi-value"><?= $assigned ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card red"><div class="kpi-label">Delayed</div><div class="kpi-value"><?= $delayed ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card green"><div class="kpi-label">My Deliveries</div><div class="kpi-value"><?= $delivered ?></div></div></div>
</div>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-geo-alt me-2"></i>My Active Deliveries</h5>
        <a href="<?= BASE_URL ?>modules/delivery/list.php" class="btn btn-sm btn-primary">All Deliveries</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Delivery #</th><th>Order</th><th>Customer</th><th>Est. Date</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
                <?php while ($d = mysqli_fetch_assoc($my_deliveries)): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($d['delivery_number']) ?></strong></td>
                    <td><?= htmlspecialchars($d['order_number']) ?></td>
                    <td><?= htmlspecialchars($d['customer_name']) ?></td>
                    <td><?= $d['estimated_delivery'] ?? '-' ?></td>
                    <td><span class="badge badge-<?= $d['status'] ?>"><?= ucfirst(str_replace('_',' ',$d['status'])) ?></span></td>
                    <td><a href="<?= BASE_URL ?>modules/delivery/update.php?id=<?= $d['id'] ?>" class="btn btn-sm btn-primary">Update</a></td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($my_deliveries) === 0): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No active deliveries assigned</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
