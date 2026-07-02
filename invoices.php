<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','rdc_staff']);

$page_title = "Invoices";

$invoices = mysqli_query($conn,
    "SELECT i.*, u.name AS customer_name, o.order_number, 
     COALESCE((SELECT SUM(amount_paid) FROM payments WHERE invoice_id = i.id), 0) AS total_paid
     FROM invoices i
     JOIN users u ON i.customer_id=u.id JOIN orders o ON i.order_id=o.id
     ORDER BY i.issued_at DESC"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>



<div class="page-card">
    <div class="page-card-header"><h5><i class="bi bi-receipt me-2"></i>Invoices</h5></div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Invoice #</th><th>Order</th><th>Customer</th><th>Total</th><th>Paid</th><th>Due Amount</th><th>Due Date</th><th>Status</th></tr></thead>
            <tbody>
                <?php while ($inv = mysqli_fetch_assoc($invoices)): 
                    $due_amount = max(0, $inv['total_amount'] - $inv['total_paid']);
                ?>
                <tr>
                    <td><strong><?= htmlspecialchars($inv['invoice_number']) ?></strong></td>
                    <td><?= htmlspecialchars($inv['order_number']) ?></td>
                    <td><?= htmlspecialchars($inv['customer_name']) ?></td>
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
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
