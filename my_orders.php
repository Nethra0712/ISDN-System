<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['customer']);

$page_title = "My Orders";
$cid = $_SESSION['user_id'];

$orders = mysqli_query($conn,
    "SELECT * FROM orders WHERE customer_id=$cid ORDER BY order_date DESC"
);

// Handle Deletion
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $oid = (int)$_GET['id'];
    // Verify ownership and status
    $check = mysqli_query($conn, "SELECT status FROM orders WHERE id=$oid AND customer_id=$cid");
    if ($row = mysqli_fetch_assoc($check)) {
        if ($row['status'] === 'pending') {
            mysqli_begin_transaction($conn);
            try {
                // Check for any payments made (Wallet or Online mock)
                // We sum up wallet payments and online payments to refund to wallet
                $pay_stmt = mysqli_query($conn, 
                    "SELECT SUM(amount_paid) AS total_to_refund 
                     FROM payments p 
                     JOIN invoices i ON p.invoice_id = i.id 
                     WHERE i.order_id = $oid"
                );
                $pay_data = mysqli_fetch_assoc($pay_stmt);
                $refund_val = (float)($pay_data['total_to_refund'] ?? 0);

                if ($refund_val > 0) {
                    // Credit the customer's wallet
                    mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance + $refund_val WHERE id=$cid");
                }

                // Delete order items
                mysqli_query($conn, "DELETE FROM order_items WHERE order_id=$oid");
                // Delete order (CASCADE handles deliveries/invoices/payments in DB)
                mysqli_query($conn, "DELETE FROM orders WHERE id=$oid");
                
                mysqli_commit($conn);
                header("Location: my_orders.php?msg=deleted" . ($refund_val > 0 ? "_refunded" : ""));
                exit();
            } catch (Exception $e) {
                mysqli_rollback($conn);
                header("Location: my_orders.php?msg=error");
                exit();
            }
        }
    }
}

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-list-check me-2"></i>My Order History</h5>
        <?php if (isset($_GET['msg'])): ?>
            <?php if ($_GET['msg'] === 'deleted'): ?>
                <div class="alert alert-success py-1 px-3 small mb-0 me-3">Order deleted successfully.</div>
            <?php elseif ($_GET['msg'] === 'deleted_refunded'): ?>
                <div class="alert alert-success py-1 px-3 small mb-0 me-3">Order deleted. <strong>Refund credited to your wallet!</strong></div>
            <?php elseif ($_GET['msg'] === 'error'): ?>
                <div class="alert alert-danger py-1 px-3 small mb-0 me-3">An error occurred while deleting the order.</div>
            <?php endif; ?>
        <?php endif; ?>
        <a href="place_order.php" class="btn btn-sm btn-primary"><i class="bi bi-plus me-1"></i>New Order</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Order #</th><th>Date</th><th>Amount</th><th>Status</th><th>Est. Delivery</th><th></th></tr></thead>
            <tbody>
                <?php while ($o = mysqli_fetch_assoc($orders)): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
                    <td><?= date('d M Y', strtotime($o['order_date'])) ?></td>
                    <td>LKR <?= number_format($o['total_amount'], 2) ?></td>
                    <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td>
                        <?php if ($o['estimated_delivery']): ?>
                            <small class="fw-bold text-success"><?= date('d M Y', strtotime($o['estimated_delivery'])) ?></small>
                        <?php else: ?>
                            <small class="text-muted">TBD</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">
                        <a href="view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                        <?php if ($o['status'] === 'pending'): ?>
                            <a href="?action=delete&id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-danger ms-1" onclick="return confirm('Are you sure you want to delete this pending order?')">
                                <i class="bi bi-trash"></i>
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($orders) === 0): ?>
                <tr><td colspan="5" class="text-center text-muted py-4">No orders yet.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
