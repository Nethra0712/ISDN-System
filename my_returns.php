<?php
// ============================================
// modules/returns/my_returns.php
// Customer Return Requests History
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['customer']);

$uid = $_SESSION['user_id'];
$page_title = "My Returns";

$returns = mysqli_query($conn, 
    "SELECT r.*, o.order_number 
     FROM return_requests r 
     JOIN orders o ON r.order_id = o.id 
     WHERE r.customer_id = $uid 
     ORDER BY r.created_at DESC"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-arrow-counterclockwise me-2"></i>My Return Requests</h5>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Order #</th>
                    <th>Status</th>
                    <th class="text-end">Ref. Amount</th>
                    <th>Requested On</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($r = mysqli_fetch_assoc($returns)): ?>
                <tr>
                    <td><code>#RET-<?= str_pad($r['id'], 4, '0', STR_PAD_LEFT) ?></code></td>
                    <td><strong><?= htmlspecialchars($r['order_number']) ?></strong></td>
                    <td>
                        <span class="badge badge-<?= $r['status'] ?>">
                            <?= ucfirst($r['status']) ?>
                        </span>
                    </td>
                    <td class="text-end fw-bold text-success">LKR <?= number_format($r['total_refund_amount'], 2) ?></td>
                    <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($returns) === 0): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">You have not submitted any return requests.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
