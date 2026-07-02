<?php
session_start();
define('BASE_URL', '../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['customer']);

$page_title = "My Dashboard";
$cid = $_SESSION['user_id'];

$total_orders    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE customer_id=$cid"))['cnt'];
$pending_orders  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE customer_id=$cid AND status='pending'"))['cnt'];
$delivered_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM orders WHERE customer_id=$cid AND status='delivered'"))['cnt'];
$total_spent     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COALESCE(SUM(p.amount_paid),0) AS total FROM payments p JOIN invoices i ON p.invoice_id=i.id WHERE i.customer_id=$cid"))['total'];

// Find the customer's corresponding center and wallet
$u_stmt = mysqli_query($conn, "SELECT province, wallet_balance FROM users WHERE id=$cid");
$user_data = mysqli_fetch_assoc($u_stmt);
$u_prov = $user_data['province'];
$c_res = mysqli_query($conn, "SELECT id, name FROM distribution_centers WHERE province='$u_prov' AND status='active'");
$center = mysqli_fetch_assoc($c_res);
$center_id = $center ? $center['id'] : null;

// Load products available in their center
$products = [];
if ($center_id) {
    $products = mysqli_query($conn, 
        "SELECT p.*, i.quantity_on_hand 
         FROM products p 
         JOIN inventory i ON p.id = i.product_id 
         WHERE i.center_id = $center_id AND i.quantity_on_hand > 0 AND p.status='active'
         ORDER BY p.name"
    );
}

$my_orders = mysqli_query($conn,
    "SELECT * FROM orders WHERE customer_id=$cid ORDER BY order_date DESC LIMIT 5"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="row g-3 mb-4">
    <div class="col-md-3 col-6"><div class="kpi-card blue"><div class="kpi-label">Total Orders</div><div class="kpi-value"><?= $total_orders ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card orange"><div class="kpi-label">Pending</div><div class="kpi-value"><?= $pending_orders ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card green"><div class="kpi-label">Delivered</div><div class="kpi-value"><?= $delivered_count ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card blue"><div class="kpi-label">ISDN Wallet</div><div class="kpi-value" style="font-size:1.1rem;">LKR <?= number_format($user_data['wallet_balance'], 2) ?></div></div></div>
    <div class="col-md-3 col-6"><div class="kpi-card blue"><div class="kpi-label">Total Spent</div><div class="kpi-value" style="font-size:1.1rem;">LKR <?= number_format($total_spent) ?></div></div></div>
</div>

<div class="mb-4 d-flex flex-wrap gap-2">
    <span class="badge rounded-pill bg-primary px-3 py-2 shadow-sm"><i class="bi bi-tag-fill me-1"></i>Promotion: 5% OFF on all Stationery & Office Supplies!</span>
    <span class="badge rounded-pill bg-success px-3 py-2 shadow-sm"><i class="bi bi-gift me-1"></i>High Value Promo: 10% OFF on orders over LKR 100,000!</span>
    <span class="badge rounded-pill bg-warning text-dark px-3 py-2 shadow-sm"><i class="bi bi-star-fill me-1"></i>Bulk Discount: Buy 10+ items, Get 5% OFF</span>
</div>

<?php if (isset($_GET['msg']) && $_GET['msg'] === 'added'): ?>
<div class="alert alert-success py-2 small mb-4">Item added to your cart! <a href="<?= BASE_URL ?>modules/orders/cart.php" class="fw-bold">View Cart</a></div>
<?php endif; ?>

<div class="section-header d-flex justify-content-between align-items-center mb-3">
    <div>
        <h5><i class="bi bi-shop me-2"></i>Product Catalog (<?= $center['name'] ?? 'Local Branch' ?>)</h5>
        <p class="text-muted small mb-0">Browsing items available for immediate shipment from your assigned center.</p>
    </div>
    <div class="d-flex gap-2">
        <input type="text" id="customerSearch" class="form-control form-control-sm" placeholder="Search products..." style="width: 200px;">
        <select id="customerCategory" class="form-select form-select-sm" style="width: 150px;">
            <option value="">All Categories</option>
            <?php 
                $cat_q = "SELECT DISTINCT p.category FROM products p 
                          JOIN inventory i ON p.id = i.product_id 
                          WHERE i.center_id = ? AND i.quantity_on_hand > 0 AND p.status='active' AND p.category IS NOT NULL 
                          ORDER BY p.category";
                $cat_stmt = mysqli_prepare($conn, $cat_q);
                mysqli_stmt_bind_param($cat_stmt, "i", $center_id);
                mysqli_stmt_execute($cat_stmt);
                $cat_res = mysqli_stmt_get_result($cat_stmt);
                while($c = mysqli_fetch_assoc($cat_res)): 
            ?>
            <option value="<?= htmlspecialchars($c['category']) ?>"><?= htmlspecialchars($c['category']) ?></option>
            <?php endwhile; ?>
        </select>
    </div>
</div>

<div class="row g-3 mb-5">
    <?php if ($center_id && mysqli_num_rows($products) > 0): ?>
        <?php while($p = mysqli_fetch_assoc($products)): ?>
            <?php 
                $original_price = $p['unit_price'];
                $discount = $p['discount_percentage'];
                $sale_price = $original_price * (1 - ($discount / 100));
            ?>
            <div class="col-xl-3 col-md-4 col-sm-6 product-card-col" 
                 data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>" 
                 data-category="<?= htmlspecialchars($p['category']) ?>">
                <div class="card h-100 border-0 shadow-sm product-card position-relative overflow-hidden">
                    <?php if ($discount > 0): ?>
                        <div class="badge bg-danger position-absolute top-0 end-0 m-2 px-2 py-1" style="z-index: 1;">-<?= $discount ?>% OFF</div>
                    <?php endif; ?>
                    <div class="card-body">
                        <small class="text-muted d-block mb-1"><?= htmlspecialchars($p['category']) ?></small>
                        <h6 class="card-title fw-bold mb-1"><?= htmlspecialchars($p['name']) ?></h6>
                        <small class="text-muted d-block mb-3">SKU: <?= htmlspecialchars($p['sku']) ?></small>
                        
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="fs-5 fw-bold text-primary">LKR <?= number_format($sale_price, 2) ?></span>
                            <?php if ($discount > 0): ?>
                                <small class="text-muted text-decoration-line-through">LKR <?= number_format($original_price, 2) ?></small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="badge bg-light text-success border small">In Stock: <?= $p['quantity_on_hand'] ?></span>
                            <a href="<?= BASE_URL ?>modules/orders/cart_action.php?action=add&id=<?= $p['id'] ?>" class="btn btn-sm btn-primary px-3">
                                <i class="bi bi-cart-plus me-1"></i>Add
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="col-12 text-center py-5">
            <i class="bi bi-inbox text-muted display-4 mb-3"></i>
            <p class="text-muted">No products are currently in stock at your local center.</p>
        </div>
    <?php endif; ?>
    <div id="noResultsMsg" class="col-12 text-center py-5 d-none">
        <i class="bi bi-search text-muted display-4 mb-3"></i>
        <p class="text-muted">No products match your search or filter.</p>
    </div>
</div>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-clock-history me-2"></i>Recent Orders</h5>
        <a href="<?= BASE_URL ?>modules/orders/my_orders.php" class="btn btn-sm btn-outline-primary">View All History</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Order #</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
                <?php while ($o = mysqli_fetch_assoc($my_orders)): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($o['order_number']) ?></strong></td>
                    <td><?= date('d M Y', strtotime($o['order_date'])) ?></td>
                    <td>LKR <?= number_format($o['total_amount']) ?></td>
                    <td><span class="badge badge-<?= $o['status'] ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td><a href="<?= BASE_URL ?>modules/orders/view.php?id=<?= $o['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($my_orders) === 0): ?>
                <tr><td colspan="5" class="text-center text-muted py-3">No orders yet. <a href="<?= BASE_URL ?>modules/orders/place_order.php">Place your first order</a></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const customerSearch = document.getElementById('customerSearch');
const customerCategory = document.getElementById('customerCategory');
const productCards = document.querySelectorAll('.product-card-col');
const noResultsMsg = document.getElementById('noResultsMsg');

function filterItems() {
    const searchTerm = customerSearch.value.toLowerCase();
    const selectedCat = customerCategory.value;
    let visibleCount = 0;

    productCards.forEach(card => {
        const name = card.getAttribute('data-name');
        const category = card.getAttribute('data-category');

        const matchesSearch = name.includes(searchTerm);
        const matchesCategory = selectedCat === "" || category === selectedCat;

        if (matchesSearch && matchesCategory) {
            card.classList.remove('d-none');
            visibleCount++;
        } else {
            card.classList.add('d-none');
        }
    });

    if (visibleCount === 0 && productCards.length > 0) {
        noResultsMsg.classList.remove('d-none');
    } else {
        noResultsMsg.classList.add('d-none');
    }
}

customerSearch?.addEventListener('input', filterItems);
customerCategory?.addEventListener('change', filterItems);
</script>

<?php include BASE_URL . 'includes/footer.php'; ?>
