<?php
// ============================================
// modules/returns/process.php
// Return Reconciliation & Processing
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','rdc_staff']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list.php"); exit(); }

// Fetch return request
$stmt = mysqli_prepare($conn, 
    "SELECT r.*, o.order_number, u.name AS customer_name, dc.name AS center_name 
     FROM return_requests r 
     JOIN orders o ON r.order_id = o.id 
     JOIN users u ON r.customer_id = u.id 
     JOIN distribution_centers dc ON r.center_id = dc.id 
     WHERE r.id=?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$ret = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$ret) { header("Location: list.php"); exit(); }

$error = '';
$success = '';

// Fetch return items
$items_res = mysqli_query($conn, 
    "SELECT ri.*, p.name AS product_name, p.sku 
     FROM return_items ri 
     JOIN products p ON ri.product_id=p.id 
     WHERE ri.return_id=$id"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $ret['status'] === 'pending') {
    $accepted_qtys = $_POST['accepted_qty'] ?? [];
    $notes = trim($_POST['manager_notes']);
    $action = $_POST['action'] ?? '';

    if ($action === 'reject') {
        mysqli_query($conn, "UPDATE return_requests SET status='rejected', notes=CONCAT(notes, '\nRejected: ', '$notes') WHERE id=$id");
        header("Location: list.php?msg=rejected");
        exit();
    }

    mysqli_begin_transaction($conn);
    try {
        $total_refund = 0;

        foreach($accepted_qtys as $ri_id => $qty) {
            $qty = (int)$qty;
            $ri_id = (int)$ri_id;

            // Fetch return item info
            $ri_stmt = mysqli_query($conn, "SELECT * FROM return_items WHERE id=$ri_id AND return_id=$id");
            $ri_data = mysqli_fetch_assoc($ri_stmt);

            if (!$ri_data || $qty > $ri_data['quantity_requested']) {
                throw new Exception("Invalid accepted quantity for item #$ri_id");
            }

            $refund = $qty * $ri_data['unit_price'];
            $total_refund += $refund;

            // Update return item
            $upd_ri = mysqli_prepare($conn, "UPDATE return_items SET quantity_accepted=?, refund_amount=? WHERE id=?");
            mysqli_stmt_bind_param($upd_ri, "idi", $qty, $refund, $ri_id);
            mysqli_stmt_execute($upd_ri);

            // Update inventory
            if ($qty > 0) {
                $upd_inv = mysqli_prepare($conn, 
                    "UPDATE inventory SET quantity_on_hand = quantity_on_hand + ? 
                     WHERE product_id = ? AND center_id = ?"
                );
                mysqli_stmt_bind_param($upd_inv, "iii", $qty, $ri_data['product_id'], $ret['center_id']);
                mysqli_stmt_execute($upd_inv);
            }
        }

        // Update return request status
        $upd_ret = mysqli_prepare($conn, "UPDATE return_requests SET status='completed', total_refund_amount=? WHERE id=?");
        mysqli_stmt_bind_param($upd_ret, "di", $total_refund, $id);
        mysqli_stmt_execute($upd_ret);

        // Credit customer wallet
        if ($total_refund > 0) {
            $upd_wallet = mysqli_prepare($conn, "UPDATE users SET wallet_balance = wallet_balance + ? WHERE id=?");
            mysqli_stmt_bind_param($upd_wallet, "di", $total_refund, $ret['customer_id']);
            mysqli_stmt_execute($upd_wallet);
        }

        mysqli_commit($conn);
        header("Location: list.php?msg=completed");
        exit();
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $error = "Processing Error: " . $e->getMessage();
    }
}

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="page-card">
            <div class="page-card-header">
                <h5><i class="bi bi-shield-check me-2"></i>Process Return Request #<?= str_pad($id, 4, '0', STR_PAD_LEFT) ?></h5>
                <span class="badge badge-<?= $ret['status'] ?>"><?= ucfirst($ret['status']) ?></span>
            </div>

            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= $error ?></div>
            <?php endif; ?>

            <form method="POST">
                <div class="table-responsive mb-4">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Requested</th>
                                <th style="width: 150px;">Accepted Qty</th>
                                <th class="text-end">Current Refund</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($item = mysqli_fetch_assoc($items_res)): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                                    <small class="text-muted"><?= $item['sku'] ?> | LKR <?= number_format($item['unit_price'], 2) ?> each</small>
                                </td>
                                <td><span class="badge bg-light text-dark"><?= $item['quantity_requested'] ?></span></td>
                                <td>
                                    <?php if ($ret['status'] === 'pending'): ?>
                                    <input type="number" name="accepted_qty[<?= $item['id'] ?>]" 
                                           class="form-control form-control-sm qty-adj" 
                                           min="0" max="<?= $item['quantity_requested'] ?>" 
                                           value="<?= $item['quantity_requested'] ?>"
                                           data-price="<?= $item['unit_price'] ?>">
                                    <?php else: ?>
                                    <strong><?= $item['quantity_accepted'] ?></strong>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end fw-bold">
                                    LKR <span class="line-refund"><?= number_format($ret['status'] === 'pending' ? ($item['quantity_requested'] * $item['unit_price']) : $item['refund_amount'], 2) ?></span>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                        <tfoot class="table-light">
                            <tr class="fw-bold fs-5">
                                <td colspan="3" class="text-end">Total Refund Amount</td>
                                <td class="text-end text-success">LKR <span id="grand-refund"><?= number_format($ret['total_refund_amount'], 2) ?></span></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-bold">Verification Notes</label>
                    <textarea name="manager_notes" class="form-control" rows="3" placeholder="Notes for the customer regarding accepted quantities..." <?= $ret['status'] !== 'pending' ? 'readonly' : '' ?>></textarea>
                </div>

                <?php if ($ret['status'] === 'pending'): ?>
                <div class="d-flex gap-2">
                    <button type="submit" name="action" value="approve" class="btn btn-primary px-4" onclick="return confirm('Complete reconciliation? Stock will be updated and customer will be credited.')">
                        <i class="bi bi-check-circle me-1"></i>Reconcile & Credit Wallet
                    </button>
                    <button type="submit" name="action" value="reject" class="btn btn-outline-danger" onclick="return confirm('Reject this return request?')">
                        Reject Request
                    </button>
                </div>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="col-lg-4">
        <div class="page-card">
            <div class="page-card-header"><h5>Request Details</h5></div>
            <table class="table table-sm table-borderless">
                <tr><td class="text-muted">Order</td><td><strong><?= htmlspecialchars($ret['order_number']) ?></strong></td></tr>
                <tr><td class="text-muted">Customer</td><td><?= htmlspecialchars($ret['customer_name']) ?></td></tr>
                <tr><td class="text-muted">Center</td><td><?= htmlspecialchars($ret['center_name']) ?></td></tr>
                <tr><td class="text-muted">Requested</td><td><?= date('d M Y', strtotime($ret['created_at'])) ?></td></tr>
            </table>
            <hr>
            <div class="small fw-bold mb-2">Customer's Return Reason:</div>
            <div class="bg-light p-2 rounded small text-muted">
                <?= nl2br(htmlspecialchars($ret['notes'])) ?>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.qty-adj').forEach(input => {
    input.addEventListener('input', function() {
        const qty = parseInt(this.value) || 0;
        const price = parseFloat(this.dataset.price);
        const refund = qty * price;
        this.closest('tr').querySelector('.line-refund').textContent = refund.toLocaleString(undefined, {minimumFractionDigits: 2});
        
        let grand = 0;
        document.querySelectorAll('.line-refund').forEach(span => {
            grand += parseFloat(span.textContent.replace(/,/g, ''));
        });
        document.getElementById('grand-refund').textContent = grand.toLocaleString(undefined, {minimumFractionDigits: 2});
    });
});
</script>

<?php include BASE_URL . 'includes/footer.php'; ?>
