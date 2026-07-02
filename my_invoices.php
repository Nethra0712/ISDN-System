<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['customer']);

$page_title = "My Invoices";
$cid = $_SESSION['user_id'];

$invoices = mysqli_query($conn,
    "SELECT i.*, o.order_number, 
     COALESCE((SELECT SUM(amount_paid) FROM payments WHERE invoice_id = i.id), 0) AS total_paid
     FROM invoices i
     JOIN orders o ON i.order_id = o.id
     WHERE i.customer_id = $cid
     ORDER BY i.issued_at DESC"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'payment_successful'): ?>
<div class="alert alert-success alert-dismissible fade show py-2 small">Payment was processed successfully. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-receipt me-2"></i>My Invoices</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Order</th>
                    <th>Date Issued</th>
                    <th>Total Amout</th>
                    <th>Amount Paid</th>
                    <th>Due Amount</th>
                    <th>Due Date</th>
                    <th>Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($inv = mysqli_fetch_assoc($invoices)): 
                    $due_amount = max(0, $inv['total_amount'] - $inv['total_paid']);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                    <td><?= htmlspecialchars($inv['order_number']) ?></td>
                    <td><?= date('d M Y', strtotime($inv['issued_at'])) ?></td>
                    <td><strong>LKR <?= number_format($inv['total_amount'], 2) ?></strong></td>
                    <td class="text-success">LKR <?= number_format($inv['total_paid'], 2) ?></td>
                    <td class="text-danger fw-bold">LKR <?= number_format($due_amount, 2) ?></td>
                    <td><?= $inv['due_date'] ?? '-' ?></td>
                    <td>
                        <?php
                        $badge = match($inv['status']) {
                            'paid' => 'bg-success',
                            'partially_paid' => 'bg-warning text-dark',
                            'overdue' => 'bg-danger',
                            default => 'bg-secondary'
                        };
                        ?>
                        <span class="badge <?= $badge ?>"><?= ucfirst(str_replace('_',' ',$inv['status'])) ?></span>
                    </td>
                    <td>
                        <?php if ($due_amount > 0): ?>
                        <a href="payment_gateway.php?invoice_id=<?= $inv['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="bi bi-credit-card me-1"></i> Pay Now
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($invoices) === 0): ?>
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">No invoices found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
