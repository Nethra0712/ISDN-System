<?php
// ============================================
// modules/returns/list.php
// RDC Manager Return Requests Management
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','rdc_staff']);

$page_title = "Product Returns Management";
$role = $_SESSION['role'];
$uid  = $_SESSION['user_id'];

// Determine RDC context
$center_id = null;
if ($role === 'rdc_staff') {
    $u_res = mysqli_query($conn, "SELECT province FROM users WHERE id=$uid");
    $u_prov = mysqli_fetch_assoc($u_res)['province'];
    $c_res = mysqli_query($conn, "SELECT id FROM distribution_centers WHERE province='$u_prov'");
    $center_id = mysqli_fetch_assoc($c_res)['id'] ?? null;
}

$where = "WHERE 1=1";
if ($center_id) $where .= " AND r.center_id = $center_id";

$query = "SELECT r.*, o.order_number, u.name AS customer_name, dc.name AS center_name 
          FROM return_requests r 
          JOIN orders o ON r.order_id = o.id 
          JOIN users u ON r.customer_id = u.id 
          JOIN distribution_centers dc ON r.center_id = dc.id 
          $where 
          ORDER BY r.created_at DESC";

$returns = mysqli_query($conn, $query);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-arrow-counterclockwise me-2"></i>Product Return Requests</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Order #</th>
                    <th>Customer</th>
                    <th>Center</th>
                    <th>Status</th>
                    <th class="text-end">Ref. Amount</th>
                    <th>Requested On</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = mysqli_fetch_assoc($returns)): ?>
                <tr>
                    <td><code>#RET-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></code></td>
                    <td><strong><?= htmlspecialchars($r['order_number']) ?></strong></td>
                    <td><?= htmlspecialchars($r['customer_name']) ?></td>
                    <td><?= htmlspecialchars($r['center_name']) ?></td>
                    <td>
                        <span class="badge badge-<?= $r['status'] ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td class="text-end fw-bold">LKR <?= number_format($r['total_refund_amount'], 2) ?></td>
                    <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                    <td>
                        <a href="process.php?id=<?= $r['id'] ?>" class="btn btn-sm <?= $r['status'] === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                            <i class="bi <?= $r['status'] === 'pending' ? 'bi-pencil' : 'bi-eye' ?> me-1"></i>
                            <?= $r['status'] === 'pending' ? 'Process' : 'View' ?>
                        </a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($returns) === 0): ?>
                <tr><td colspan="8" class="text-center text-muted py-4">No return requests found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
