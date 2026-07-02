<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['customer']);

$page_title = "Place Order";
$error   = '';
$success = '';

// Load cart items
$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header("Location: ../../dashboards/customer_dashboard.php");
    exit();
}
// Determine the user's assigned center based on their province
$uid = $_SESSION['user_id'];
$u_stmt = mysqli_query($conn, "SELECT province, wallet_balance, permanent_address FROM users WHERE id=$uid");
$user_data = mysqli_fetch_assoc($u_stmt);
$u_prov = $user_data['province'];
$wallet = $user_data['wallet_balance'];
$p_address = $user_data['permanent_address'];

// Find the corresponding center
$c_stmt = mysqli_query($conn, "SELECT id, name FROM distribution_centers WHERE province='$u_prov' AND status='active'");
$center_data = mysqli_fetch_assoc($c_stmt);
$center_id = $center_data ? $center_data['id'] : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $route_num = (int)$_POST['route_number'];
    $notes     = trim($_POST['notes']);
    $address   = $p_address; // Use profile address
    // Items are taken from the session cart
    $valid_items = $_SESSION['cart'] ?? [];

    if (!$center_id) {
        $error = "No active Regional Distribution Center found for your province ($u_prov). Contact support.";
    } elseif ($route_num < 1 || $route_num > 5) {
        $error = "Please select a valid delivery route (1-5).";
    } elseif (empty($address)) {
        $error = "Your profile is missing a permanent address. Please update your profile before ordering.";
    } elseif (empty($valid_items)) {
        $error = "Your cart is empty. Please add items before placing an order.";
    } else {
        // Calculate total
        $total = 0;
        $line_items = [];
        $promo_applied_in_order = false;
        $bulk_promo_applied_in_order = false;

        foreach ($valid_items as $pid => $qty) {
            $stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id=?");
            mysqli_stmt_bind_param($stmt, "i", $pid);
            mysqli_stmt_execute($stmt);
            $prod = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
            if ($prod) {
                $final_price = $prod['unit_price'] * (1 - ($prod['discount_percentage'] / 100));
                
                if ($prod['category'] === 'Stationery & Office Supplies') {
                    $final_price = $final_price * 0.95;
                    $promo_applied_in_order = true;
                }

                $bulk_promo_applied = false;
                if ($qty >= 10) {
                    $final_price = $final_price * 0.95;
                    $bulk_promo_applied_in_order = true;
                }

                $subtotal = $qty * $final_price;
                $total   += $subtotal;
                $line_items[] = ['pid' => $pid, 'qty' => $qty, 'price' => $final_price];
            }
        }

        $high_value_promo_applied_in_order = false;
        if ($total > 100000) {
            $total = $total * 0.90;
            $high_value_promo_applied_in_order = true;
        }

        if ($promo_applied_in_order) {
            $_SESSION['promo_applied'] = true;
        }
        if ($bulk_promo_applied_in_order) {
            $_SESSION['bulk_promo_applied'] = true;
        }
        if ($high_value_promo_applied_in_order) {
            $_SESSION['high_value_promo_applied'] = true;
        }

        // Wallet deduction logic
        $wallet_used = min($wallet, $total);
        $final_total = $total - $wallet_used;

        mysqli_begin_transaction($conn);
        try {
            // Deduct from wallet if used
            if ($wallet_used > 0) {
                mysqli_query($conn, "UPDATE users SET wallet_balance = wallet_balance - $wallet_used WHERE id=$uid");
            }

            // Generate order number
            $order_num = 'ORD-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));

            // Insert order
            $stmt = mysqli_prepare($conn,
                "INSERT INTO orders (order_number, customer_id, center_id, total_amount, shipping_address, route_number, notes, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'pending')"
            );
            mysqli_stmt_bind_param($stmt, "siidsis", $order_num, $uid, $center_id, $final_total, $address, $route_num, $notes);
            mysqli_stmt_execute($stmt);
            $order_id = mysqli_insert_id($conn);

        // Insert order items
        foreach ($line_items as $item) {
            $ist = mysqli_prepare($conn,
                "INSERT INTO order_items (order_id, product_id, quantity, unit_price) VALUES (?,?,?,?)"
            );
            mysqli_stmt_bind_param($ist, "iiid", $order_id, $item['pid'], $item['qty'], $item['price']);
            mysqli_stmt_execute($ist);
        }

        // Immediately create an invoice for this order
        $inv_num = 'INV-' . date('Ymd') . '-' . str_pad($order_id, 4, '0', STR_PAD_LEFT);
        $tax = 0; 
        $due = date('Y-m-d', strtotime('+30 days'));
        
        // Calculate status based on wallet usage
        $inv_status = ($wallet_used >= $total) ? 'paid' : ($wallet_used > 0 ? 'partially_paid' : 'unpaid');
        
        $inv_stmt = mysqli_prepare($conn,
            "INSERT INTO invoices (invoice_number, order_id, customer_id, amount_due, tax_amount, total_amount, due_date, status)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($inv_stmt, "siidddss", $inv_num, $order_id, $uid, $final_total, $tax, $total, $due, $inv_status);
        mysqli_stmt_execute($inv_stmt);
        $invoice_id = mysqli_insert_id($conn);

        // Record the wallet deduction as a payment row for audit/reports
        if ($wallet_used > 0) {
            $p_ref = 'WALLET-' . strtoupper(substr(uniqid(), -5));
            $p_date = date('Y-m-d');
            $p_stmt = mysqli_prepare($conn, 
                "INSERT INTO payments (invoice_id, amount_paid, payment_method, reference_number, payment_date, recorded_by) 
                 VALUES (?, ?, 'online', ?, ?, ?)"
            );
            // Using 'online' for now to match ENUM if 'wallet' is missing
            mysqli_stmt_bind_param($p_stmt, "idssi", $invoice_id, $wallet_used, $p_ref, $p_date, $uid);
            mysqli_stmt_execute($p_stmt);
        }

        mysqli_commit($conn);

        // Only send email now if it was a FULL wallet payment (Status: PAID)
        // Otherwise, wait for the payment gateway to process and send the email there.
        if ($inv_status === 'paid') {
            require_once BASE_URL . 'includes/invoice_service.php';
            sendInvoiceEmail($conn, $invoice_id);
        }

        // Clear cart session
        $_SESSION['cart'] = [];

        // Redirect directly to the payment gateway
        header("Location: payment_gateway.php?invoice_id=" . $invoice_id);
        exit();
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = "Failed to place order: " . $e->getMessage();
        }
    }
}

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<?php if ($error): ?>
<div class="alert alert-danger py-2 small"><?= $error ?></div>
<?php endif; ?>

<div class="mb-4">
    <a href="cart.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Cart</a>
</div>

<form method="POST" id="checkoutForm">
<div class="row g-3">
    <div class="col-xl-8">
        <div class="page-card">
            <div class="page-card-header">
                <h5><i class="bi bi-list-check me-2"></i>Order Summary</h5>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Product</th><th>Quantity</th><th class="text-end">Subtotal</th></tr></thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        $ids = implode(',', array_keys($cart));
                        $res = mysqli_query($conn, "SELECT * FROM products WHERE id IN ($ids)");
                        while ($p = mysqli_fetch_assoc($res)): 
                            $qty = $cart[$p['id']];
                            $price = $p['unit_price'] * (1 - ($p['discount_percentage'] / 100));
                            
                            $promo_applied = false;
                            if ($p['category'] === 'Stationery & Office Supplies') {
                                $price = $price * 0.95;
                                $promo_applied = true;
                            }

                            $bulk_promo_applied = false;
                            if ($qty >= 10) {
                                $price = $price * 0.95;
                                $bulk_promo_applied = true;
                            }

                            $sub = $qty * $price;
                            $grand_total += $sub;
                        ?>
                        <tr>
                            <td>
                                <strong>
                                    <?= htmlspecialchars($p['name']) ?>
                                    <?php if ($promo_applied): ?>
                                        <span class="badge bg-success ms-1" style="font-size: 0.6rem;">Promo Applied!</span>
                                    <?php endif; ?>
                                    <?php if ($bulk_promo_applied): ?>
                                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.6rem;"><i class="bi bi-star-fill me-1"></i>Bulk Discount!</span>
                                    <?php endif; ?>
                                </strong><br>
                                <small class="text-muted">LKR <?= number_format($price, 2) ?> each</small>
                            </td>
                            <td><?= $qty ?></td>
                            <td class="text-end">LKR <?= number_format($sub, 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr class="fw-bold">
                            <td colspan="2" class="text-end">Cart Subtotal:</td>
                            <td class="text-end">LKR <?= number_format($grand_total, 2) ?></td>
                        </tr>
                        <?php
                        $high_value_discount = 0;
                        if ($grand_total > 100000) {
                            $high_value_discount = $grand_total * 0.10;
                            $grand_total -= $high_value_discount;
                        ?>
                        <tr class="text-success small">
                            <td colspan="2" class="text-end font-weight-normal"><i class="bi bi-gift me-1"></i>High Value Promo (10% OFF):</td>
                            <td class="text-end">- LKR <?= number_format($high_value_discount, 2) ?></td>
                        </tr>
                        <?php } ?>
                        <?php if ($wallet > 0): ?>
                        <tr class="text-success small">
                            <td colspan="2" class="text-end font-weight-normal">Wallet Credit Applied:</td>
                            <td class="text-end">- LKR <?= number_format(min($wallet, $grand_total), 2) ?></td>
                        </tr>
                        <tr class="fw-bold bg-light">
                            <td colspan="2" class="text-end">Payable Total:</td>
                            <td class="text-end text-primary">LKR <?= number_format(max(0, $grand_total - $wallet), 2) ?></td>
                        </tr>
                        <?php else: ?>
                        <tr class="fw-bold">
                            <td colspan="2" class="text-end">Order Total:</td>
                            <td class="text-end text-primary">LKR <?= number_format($grand_total, 2) ?></td>
                        </tr>
                        <?php endif; ?>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="page-card card shadow-sm">
            <div class="page-card-header bg-primary text-white"><h5><i class="bi bi-truck me-2"></i>Shipping & Notes</h5></div>
            <div class="p-3">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Distribution Center</label>
                    <div class="bg-light p-2 border rounded small"><?= htmlspecialchars($center_data['name']) ?></div>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Delivery Address (From Profile)</label>
                    <div class="bg-light p-2 border rounded small"><?= nl2br(htmlspecialchars($p_address)) ?></div>
                    <input type="hidden" name="shipping_address" value="<?= htmlspecialchars($p_address) ?>">
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold text-primary">Select Delivery Route <span class="text-danger">*</span></label>
                    <select name="route_number" class="form-select form-select-sm" required>
                        <option value="">-- Choose Route --</option>
                        <option value="1">Route 1 (Main City)</option>
                        <option value="2">Route 2 (Suburban North)</option>
                        <option value="3">Route 3 (Suburban East)</option>
                        <option value="4">Route 4 (Suburban West)</option>
                        <option value="5">Route 5 (Rural / Outskirts)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Special Notes</label>
                    <textarea name="notes" class="form-control form-control-sm" rows="2" placeholder="Any delivery instructions?"></textarea>
                </div>
                <hr>
                <div class="alert alert-info py-2 small border-0">
                    <i class="bi bi-info-circle me-2"></i>Your order will be routed to the <strong><?= $u_prov ?></strong> region.
                </div>
                <button type="submit" class="btn btn-primary w-100 py-2 fw-bold"><i class="bi bi-check2-circle me-1"></i>Confirm & Place Order</button>
            </div>
        </div>
    </div>
</div>
</form>


<?php include BASE_URL . 'includes/footer.php'; ?>
