<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','logistics_staff']);

$id = (int)($_GET['id'] ?? 0);
$back_url = ($_SESSION['role'] === 'logistics_staff') ? 'logistics_view.php' : 'list.php';
if (!$id) { header("Location: $back_url"); exit(); }

$stmt = mysqli_prepare($conn,
    "SELECT d.*, o.order_number, u.name AS customer_name
     FROM deliveries d JOIN orders o ON d.order_id=o.id JOIN users u ON o.customer_id=u.id WHERE d.id=?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$d = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$d) { header("Location: $back_url"); exit(); }

// Logistics staff can only update their own assigned deliveries
if ($_SESSION['role'] === 'logistics_staff' && $d['assigned_to'] != $_SESSION['user_id']) {
    header("Location: $back_url"); exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $status  = $_POST['status'];
    $notes   = trim($_POST['delivery_notes']);
    $actual  = $status === 'delivered' ? date('Y-m-d H:i:s') : null;

    $stmt2 = mysqli_prepare($conn,
        "UPDATE deliveries SET status=?, delivery_notes=?, actual_delivery=? WHERE id=?"
    );
    mysqli_stmt_bind_param($stmt2, "sssi", $status, $notes, $actual, $id);
    mysqli_stmt_execute($stmt2);

    // If delivered, update order status too
    if ($status === 'delivered') {
        mysqli_query($conn, "UPDATE orders SET status='delivered' WHERE id=" . $d['order_id']);
    }

    header("Location: $back_url?msg=updated");
    exit();
}

$page_title = "Update Delivery";

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="page-card" style="max-width:500px;">
    <div class="page-card-header">
        <h5><i class="bi bi-geo-alt me-2"></i>Update Delivery Status</h5>
        <a href="<?= $back_url ?>" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <table class="table table-sm table-borderless mb-3">
        <tr><td class="text-muted fw-semibold" style="width:40%">Delivery #</td><td><?= htmlspecialchars($d['delivery_number']) ?></td></tr>
        <tr><td class="text-muted fw-semibold">Order</td><td><?= htmlspecialchars($d['order_number']) ?></td></tr>
        <tr><td class="text-muted fw-semibold">Customer</td><td><?= htmlspecialchars($d['customer_name']) ?></td></tr>
        <tr><td class="text-muted fw-semibold">Driver</td><td><?= htmlspecialchars($d['driver_name'] ?? '-') ?></td></tr>
        <tr><td class="text-muted fw-semibold">Current Status</td><td><span class="badge badge-<?= $d['status'] ?>"><?= ucfirst(str_replace('_',' ',$d['status'])) ?></span></td></tr>
    </table>

    <form method="POST">
        <div class="mb-3">
            <label class="form-label fw-semibold">Update Status</label>
            <select name="status" class="form-select">
                <?php foreach (['pending','out_for_delivery','delivered','delayed'] as $s): ?>
                <option value="<?= $s ?>" <?= $d['status'] === $s ? 'selected' : '' ?>><?= ucfirst(str_replace('_',' ',$s)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label fw-semibold">Delivery Notes</label>
            <textarea name="delivery_notes" class="form-control" rows="3"><?= htmlspecialchars($d['delivery_notes']) ?></textarea>
        </div>
        <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update</button>
        <a href="<?= $back_url ?>" class="btn btn-outline-secondary ms-2">Cancel</a>
    </form>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
