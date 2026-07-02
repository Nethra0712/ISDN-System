<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['customer']);

$cid = $_SESSION['user_id'];
$inv_id = (int)($_GET['invoice_id'] ?? 0);

if (!$inv_id) {
    header("Location: my_invoices.php");
    exit();
}

$stmt = mysqli_prepare($conn, 
    "SELECT i.*, o.order_number,
     COALESCE((SELECT SUM(amount_paid) FROM payments WHERE invoice_id = i.id), 0) AS total_paid
     FROM invoices i
     JOIN orders o ON i.order_id = o.id
     WHERE i.id = ? AND i.customer_id = ?"
);
mysqli_stmt_bind_param($stmt, "ii", $inv_id, $cid);
mysqli_stmt_execute($stmt);
$inv = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$inv || $inv['status'] === 'paid') {
    header("Location: my_invoices.php");
    exit();
}

$promo_applied = $_SESSION['promo_applied'] ?? false;
if ($promo_applied) {
    unset($_SESSION['promo_applied']);
}

$bulk_promo_applied = $_SESSION['bulk_promo_applied'] ?? false;
if ($bulk_promo_applied) {
    unset($_SESSION['bulk_promo_applied']);
}

$high_value_promo_applied = $_SESSION['high_value_promo_applied'] ?? false;
if ($high_value_promo_applied) {
    unset($_SESSION['high_value_promo_applied']);
}

$due_amount = max(0, $inv['total_amount'] - $inv['total_paid']);

// Handle form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_now'])) {
    $pay_amount = (float)$_POST['pay_amount'];
    
    if ($pay_amount <= 0 || $pay_amount > $due_amount) {
        $error = "Invalid payment amount. Please enter a valid amount up to LKR " . number_format($due_amount, 2);
    } else {
        $method = 'online';
        $ref = 'ONLINE-' . strtoupper(uniqid());
        $date = date('Y-m-d');
        
        $p_stmt = mysqli_prepare($conn,
            "INSERT INTO payments (invoice_id, amount_paid, payment_method, reference_number, payment_date, recorded_by) VALUES (?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($p_stmt, "idsssi", $inv_id, $pay_amount, $method, $ref, $date, $cid);
        mysqli_stmt_execute($p_stmt);
        
        // Update invoice status
        $new_paid = $inv['total_paid'] + $pay_amount;
        $status = ($new_paid >= $inv['total_amount']) ? 'paid' : 'partially_paid';
        
        mysqli_query($conn, "UPDATE invoices SET status='$status' WHERE id=$inv_id");
        
        // Send email invoice
        require_once BASE_URL . 'includes/invoice_service.php';
        sendInvoiceEmail($conn, $inv_id);

        header("Location: my_invoices.php?msg=payment_successful");
        exit();
    }
}

$page_title = "Payment Gateway";
include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="page-card shadow-sm border-0 mt-4">
            <div class="page-card-header bg-primary text-white rounded-top">
                <h5 class="mb-0 text-white"><i class="bi bi-shield-lock me-2"></i>Secure Payment Gateway</h5>
            </div>
            <div class="card-body p-4">
                <?php if ($error): ?>
                <div class="alert alert-danger fade show small"><?= $error ?></div>
                <?php endif; ?>
                
                <?php if ($promo_applied): ?>
                <div class="alert alert-success fade show small">
                    <i class="bi bi-stars me-1"></i><strong>Promotion Applied!</strong> You received a 5% discount on all Stationery & Office Supplies.
                </div>
                <?php endif; ?>
                
                <?php if ($bulk_promo_applied): ?>
                <div class="alert alert-success fade show small">
                    <i class="bi bi-stars me-1"></i><strong>Bulk Discount Applied!</strong> You received an extra 5% off for purchasing 10 or more of a single item.
                </div>
                <?php endif; ?>
                
                <?php if ($high_value_promo_applied): ?>
                <div class="alert alert-success fade show small">
                    <i class="bi bi-gift me-1"></i><strong>High Value Promotion Applied!</strong> You received 10% OFF your entire order for spending over LKR 100,000.
                </div>
                <?php endif; ?>
                
                <h6 class="text-muted mb-4">Invoice #<?= htmlspecialchars($inv['invoice_number']) ?> Details</h6>
                
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Order Number:</span>
                    <strong><?= htmlspecialchars($inv['order_number']) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-muted">Total Amount:</span>
                    <strong>LKR <?= number_format($inv['total_amount'], 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-3 border-bottom pb-3">
                    <span class="text-muted">Amount Already Paid:</span>
                    <strong class="text-success">LKR <?= number_format($inv['total_paid'], 2) ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-4">
                    <span class="fs-5">Current Due Balance:</span>
                    <strong class="fs-5 text-danger">LKR <?= number_format($due_amount, 2) ?></strong>
                </div>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Amount to Pay Now (LKR)</label>
                        <input type="number" step="0.01" name="pay_amount" class="form-control form-control-lg" value="<?= $due_amount ?>" max="<?= $due_amount ?>" min="1" required>
                        <div class="form-text">You can adjust the amount to make a partial payment.</div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Card Details (Mock)</label>
                        <input type="text" class="form-control mb-2" placeholder="Card Number" value="4111 1111 1111 1111" disabled>
                        <div class="row g-2">
                            <div class="col-6"><input type="text" class="form-control" placeholder="MM/YY" value="12/26" disabled></div>
                            <div class="col-6"><input type="text" class="form-control" placeholder="CVC" value="123" disabled></div>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" name="pay_now" class="btn btn-primary btn-lg"><i class="bi bi-lock-fill me-1"></i> Process Payment</button>
                        <a href="my_invoices.php" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
