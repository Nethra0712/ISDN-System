<?php
// ============================================
// modules/orders/cart.php
// Shopping Cart UI
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['customer']);

$page_title = "My Shopping Cart";
$cart = $_SESSION['cart'] ?? [];

$items = [];
$total = 0;

if (!empty($cart)) {
    $ids = implode(',', array_keys($cart));
    $res = mysqli_query($conn, "SELECT * FROM products WHERE id IN ($ids)");
    while ($p = mysqli_fetch_assoc($res)) {
        $qty = $cart[$p['id']];
        $price = $p['unit_price'] * (1 - ($p['discount_percentage'] / 100));
        
        $promo_applied = false;
        if ($p['category'] === 'Stationery & Office Supplies') {
            $price = $price * 0.95; // 5% extra discount
            $promo_applied = true;
        }

        $bulk_promo_applied = false;
        if ($qty >= 10) {
            $price = $price * 0.95; // 5% bulk discount
            $bulk_promo_applied = true;
        }

        $subtotal = $qty * $price;
        $total += $subtotal;
        $items[] = array_merge($p, ['qty' => $qty, 'final_price' => $price, 'subtotal' => $subtotal, 'promo_applied' => $promo_applied, 'bulk_promo_applied' => $bulk_promo_applied]);
    }
}

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="row">
    <div class="col-lg-8">
        <div class="page-card">
            <div class="page-card-header">
                <h5><i class="bi bi-cart3 me-2"></i>Review Your Cart</h5>
                <?php if (!empty($items)): ?>
                <a href="cart_action.php?action=clear&id=1" class="btn btn-sm btn-outline-danger" onclick="return confirm('Clear entire cart?')">Clear Cart</a>
                <?php endif; ?>
            </div>
            
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>Product</th><th>Price</th><th>Quantity</th><th>Subtotal</th><th></th></tr></thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                        <tr>
                            <td>
                                <div class="fw-bold">
                                    <?= htmlspecialchars($item['name']) ?>
                                    <?php if ($item['promo_applied']): ?>
                                        <span class="badge bg-success ms-1" style="font-size: 0.6rem;">Promo Applied!</span>
                                    <?php endif; ?>
                                    <?php if ($item['bulk_promo_applied']): ?>
                                        <span class="badge bg-warning text-dark ms-1" style="font-size: 0.6rem;"><i class="bi bi-star-fill me-1"></i>Bulk Discount!</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= $item['sku'] ?></small>
                            </td>
                            <td>LKR <?= number_format($item['final_price'], 2) ?></td>
                            <td style="width:120px;">
                                <form action="cart_action.php" method="GET" class="d-flex">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="id" value="<?= $item['id'] ?>">
                                    <input type="number" name="qty" class="form-control form-control-sm me-1" value="<?= $item['qty'] ?>" min="1" onchange="this.form.submit()">
                                </form>
                            </td>
                            <td class="fw-bold text-primary">LKR <?= number_format($item['subtotal'], 2) ?></td>
                            <td class="text-end">
                                <a href="cart_action.php?action=remove&id=<?= $item['id'] ?>" class="text-danger"><i class="bi bi-trash"></i></a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($items)): ?>
                        <tr><td colspan="5" class="text-center py-5 text-muted">Your cart is empty. <a href="../../dashboards/customer_dashboard.php">Start shopping</a></td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="page-card">
            <div class="page-card-header"><h5>Order Summary</h5></div>
            <div class="p-3">
                <div class="d-flex justify-content-between mb-2"><span>Subtotal:</span><span>LKR <?= number_format($total, 2) ?></span></div>
                <?php
                $high_value_discount = 0;
                if ($total > 100000) {
                    $high_value_discount = $total * 0.10;
                    $total = $total - $high_value_discount;
                ?>
                <div class="d-flex justify-content-between mb-2 text-success">
                    <span><i class="bi bi-gift me-1"></i>High Value Promo (10% OFF):</span>
                    <span>- LKR <?= number_format($high_value_discount, 2) ?></span>
                </div>
                <?php } ?>
                <div class="d-flex justify-content-between mb-3"><span class="fw-bold">Total:</span><span class="fw-bold text-primary" style="font-size:1.3rem;">LKR <?= number_format($total, 2) ?></span></div>
                <hr>
                <?php if (!empty($items)): ?>
                <a href="place_order.php" class="btn btn-primary w-100 py-2"><i class="bi bi-credit-card me-2"></i>Proceed to Checkout</a>
                <?php else: ?>
                <button class="btn btn-secondary w-100 py-2" disabled>Proceed to Checkout</button>
                <?php endif; ?>
                <a href="../../dashboards/customer_dashboard.php" class="btn btn-outline-secondary w-100 mt-2">Continue Shopping</a>
            </div>
        </div>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
