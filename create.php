<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['rdc_staff','admin']);

$page_title = "Create Purchase Order";
$error = '';

$products = mysqli_query($conn, 
    "SELECT p.*, (i.quantity_on_hand - i.quantity_reserved) AS ho_stock 
     FROM products p 
     INNER JOIN inventory i ON p.id = i.product_id AND i.center_id = 1 
     WHERE p.status='active' 
     ORDER BY p.name"
);
$centers  = mysqli_query($conn, "SELECT * FROM distribution_centers WHERE status='active'");
$categories_res = mysqli_query($conn, "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND status='active' ORDER BY category");
$categories = [];
while($cat = mysqli_fetch_assoc($categories_res)) $categories[] = $cat['category'];

// Determine User's Center
$user_role = $_SESSION['role'];
$user_prov = $_SESSION['province'] ?? 'None';
$my_center_id = null;

if ($user_role === 'rdc_staff') {
    $c_stmt = mysqli_query($conn, "SELECT id FROM distribution_centers WHERE province='$user_prov' AND status='active'");
    if ($c_row = mysqli_fetch_assoc($c_stmt)) {
        $my_center_id = $c_row['id'];
    }
}

$hq_stmt = mysqli_query($conn, "SELECT id, name FROM distribution_centers WHERE province='None' LIMIT 1");
$hq_data = mysqli_fetch_assoc($hq_stmt);
$hq_name = $hq_data ? $hq_data['name'] : 'HQ Warehouse';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $center_id   = $my_center_id ? $my_center_id : (int)$_POST['center_id'];
    $supplier    = $my_center_id ? $hq_name : trim($_POST['supplier_name']);
    $expected    = trim($_POST['expected_delivery']);
    $notes       = trim($_POST['notes']);
    $save_draft  = isset($_POST['save_draft']);
    $items       = $_POST['items'] ?? [];

    $valid_items = [];
    foreach ($items as $pid => $row) {
        $qty  = (int)($row['qty'] ?? 0);
        $cost = (float)($row['cost'] ?? 0);
        if ($qty > 0 && $cost > 0) {
            $valid_items[(int)$pid] = ['qty' => $qty, 'cost' => $cost];
        }
    }

    if (empty($supplier) || !$center_id) {
        $error = "Supplier and Center are required.";
    } elseif (empty($valid_items)) {
        $error = "Add at least one product item.";
    } else {
        $total  = 0;
        foreach ($valid_items as $item) $total += $item['qty'] * $item['cost'];

        $po_num = 'PO-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -5));
        $status = $save_draft ? 'draft' : 'submitted';

        $stmt = mysqli_prepare($conn,
            "INSERT INTO purchase_orders (po_number, center_id, created_by, supplier_name, status, total_amount, expected_delivery, notes)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($stmt, "siissdss", $po_num, $center_id, $_SESSION['user_id'], $supplier, $status, $total, $expected, $notes);

        if (mysqli_stmt_execute($stmt)) {
            $po_id = mysqli_insert_id($conn);
            foreach ($valid_items as $pid => $item) {
                $is = mysqli_prepare($conn,
                    "INSERT INTO purchase_order_items (po_id, product_id, quantity_ordered, unit_cost) VALUES (?,?,?,?)"
                );
                mysqli_stmt_bind_param($is, "iiid", $po_id, $pid, $item['qty'], $item['cost']);
                mysqli_stmt_execute($is);
            }
            header("Location: list.php?msg=created");
            exit();
        } else {
            $error = "Failed to create PO.";
        }
    }
}

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<?php if ($error): ?>
<div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST">
<div class="row g-3">
    <div class="col-xl-8">
        <div class="page-card">
            <div class="page-card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-plus-circle me-2"></i>Purchase Order Items</h5>
                <div class="d-flex gap-2">
                    <input type="text" id="productSearch" class="form-control form-control-sm" placeholder="Search products..." style="width: 200px;">
                    <select id="categoryFilter" class="form-select form-select-sm" style="width: 150px;">
                        <option value="">All Categories</option>
                        <?php foreach($categories as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table mb-0" id="poTable">
                    <thead><tr><th>Product</th><th>HO Stock</th><th>Cost (After Disc.)</th><th>Quantity</th><th>Subtotal</th></tr></thead>
                    <tbody>
                        <?php while ($p = mysqli_fetch_assoc($products)): ?>
                        <tr class="product-row" 
                            data-name="<?= strtolower(htmlspecialchars($p['name'])) ?>" 
                            data-sku="<?= strtolower(htmlspecialchars($p['sku'])) ?>" 
                            data-category="<?= htmlspecialchars($p['category']) ?>">
                            <td>
                                <div class="fw-semibold"><?= htmlspecialchars($p['name']) ?></div>
                                <small class="text-muted"><?= $p['sku'] ?></small>
                            </td>
                            <td>
                                <span class="badge bg-light text-dark"><?= (int)($p['ho_stock'] ?? 0) ?></span>
                            </td>
                            <td style="width:150px;">
                                <?php 
                                    $orig_price = (float)$p['unit_price'];
                                    $disc = (int)$p['discount_percentage'];
                                    $final_cost = $orig_price * (1 - ($disc / 100));
                                ?>
                                <input type="number" step="0.01" name="items[<?= $p['id'] ?>][cost]"
                                       class="form-control form-control-sm cost-input bg-light" min="0" value="<?= $final_cost ?>" readonly>
                                <?php if ($disc > 0): ?>
                                    <small class="text-danger fw-bold">-<?= $disc ?>% Applied</small>
                                <?php endif; ?>
                            </td>
                            <td style="width:120px;">
                                <input type="number" name="items[<?= $p['id'] ?>][qty]"
                                       class="form-control form-control-sm qty-input" min="0" value="0">
                            </td>
                            <td><span class="subtotal">LKR 0.00</span></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="page-card">
            <div class="page-card-header"><h5>PO Details</h5></div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Center <span class="text-danger">*</span></label>
                <?php if ($my_center_id): ?>
                    <?php 
                        $my_c_name = 'Unknown';
                        mysqli_data_seek($centers, 0);
                        while($c = mysqli_fetch_assoc($centers)) {
                            if($c['id'] == $my_center_id) $my_c_name = $c['name'];
                        }
                    ?>
                    <div class="form-control form-control-sm bg-light text-muted">
                        <?= htmlspecialchars($my_c_name) ?> (Assigned)
                    </div>
                    <input type="hidden" name="center_id" value="<?= $my_center_id ?>">
                <?php else: ?>
                    <select name="center_id" class="form-select form-select-sm" required>
                        <option value="">-- Select --</option>
                        <?php 
                        mysqli_data_seek($centers, 0);
                        while ($c = mysqli_fetch_assoc($centers)): 
                        ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                <?php endif; ?>
            </div>
            
            <?php if ($my_center_id): ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Supplier Name</label>
                <div class="form-control form-control-sm bg-light text-muted"><?= htmlspecialchars($hq_name) ?> (Auto-assigned)</div>
                <input type="hidden" name="supplier_name" value="<?= htmlspecialchars($hq_name) ?>">
            </div>
            <?php else: ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Supplier Name <span class="text-danger">*</span></label>
                <input type="text" name="supplier_name" class="form-control form-control-sm" required value="<?= htmlspecialchars($_POST['supplier_name'] ?? '') ?>">
            </div>
            <?php endif; ?>
            <div class="mb-3">
                <label class="form-label fw-semibold">Expected Delivery</label>
                <input type="date" name="expected_delivery" class="form-control form-control-sm" value="<?= $_POST['expected_delivery'] ?? '' ?>">
            </div>
            <div class="mb-3">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
            </div>
            <hr>
            <div class="d-flex justify-content-between fw-bold mb-3">
                <span>Total:</span>
                <span id="po_total">LKR 0.00</span>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" name="submit_po" class="btn btn-primary"><i class="bi bi-send me-1"></i>Submit for Approval</button>
                <button type="submit" name="save_draft" class="btn btn-outline-secondary"><i class="bi bi-save me-1"></i>Save as Draft</button>
            </div>
        </div>
    </div>
</div>
</form>

<script>
document.querySelectorAll('.qty-input, .cost-input').forEach(i => i.addEventListener('input', calcTotal));

function calcTotal() {
    let grand = 0;
    document.querySelectorAll('#poTable tbody tr').forEach(row => {
        const qty  = parseFloat(row.querySelector('.qty-input').value) || 0;
        const cost = parseFloat(row.querySelector('.cost-input').value) || 0;
        const sub  = qty * cost;
        row.querySelector('.subtotal').textContent = 'LKR ' + sub.toLocaleString('en-LK', {minimumFractionDigits:2});
        grand += sub;
    });
    document.getElementById('po_total').textContent = 'LKR ' + grand.toLocaleString('en-LK', {minimumFractionDigits:2});
}

// Filtering Logic
const searchInput = document.getElementById('productSearch');
const categoryFilter = document.getElementById('categoryFilter');
const rows = document.querySelectorAll('.product-row');

function filterProducts() {
    const searchTerm = searchInput.value.toLowerCase();
    const selectedCat = categoryFilter.value;

    rows.forEach(row => {
        const name = row.getAttribute('data-name');
        const sku = row.getAttribute('data-sku');
        const category = row.getAttribute('data-category');

        const matchesSearch = name.includes(searchTerm) || sku.includes(searchTerm);
        const matchesCategory = selectedCat === "" || category === selectedCat;

        if (matchesSearch && matchesCategory) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

searchInput.addEventListener('input', filterProducts);
categoryFilter.addEventListener('change', filterProducts);
</script>

<?php include BASE_URL . 'includes/footer.php'; ?>
