<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list.php"); exit(); }

// Fetch product
$stmt = mysqli_prepare($conn, "SELECT * FROM products WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$p = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$p) { header("Location: list.php"); exit(); }

$page_title = "Edit Product";
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sku    = trim($_POST['sku']);
    $name   = trim($_POST['name']);
    $desc   = trim($_POST['description']);
    $cat    = trim($_POST['category']);
    $price  = (float)$_POST['unit_price'];
    $uom    = trim($_POST['unit_of_measure']);
    $reorder= (int)$_POST['reorder_level'];
    $discount = (int)($_POST['discount_percentage'] ?? 0);
    $status = $_POST['status'];

    $upd = mysqli_prepare($conn,
        "UPDATE products SET sku=?, name=?, description=?, category=?, unit_price=?, discount_percentage=?, unit_of_measure=?, reorder_level=?, status=? WHERE id=?"
    );
    mysqli_stmt_bind_param($upd, "ssssdisisi", $sku, $name, $desc, $cat, $price, $discount, $uom, $reorder, $status, $id);
    if (mysqli_stmt_execute($upd)) {
        // Ensure HO inventory (center_id 1) exists for this product
        mysqli_query($conn, "INSERT IGNORE INTO inventory (product_id, center_id, quantity_on_hand) VALUES ($id, 1, 0)");
        
        header("Location: list.php?msg=saved");
        exit();
    } else {
        $error = "Update failed. SKU may conflict.";
    }
}

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="page-card" style="max-width:650px;">
    <div class="page-card-header">
        <h5><i class="bi bi-pencil me-2"></i>Edit Product</h5>
        <a href="list.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= $error ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="form-label fw-semibold">SKU</label>
                <input type="text" name="sku" class="form-control" value="<?= htmlspecialchars($_POST['sku'] ?? $p['sku']) ?>" required>
            </div>
            <div class="col-md-8">
                <label class="form-label fw-semibold">Product Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? $p['name']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Description</label>
                <textarea name="description" class="form-control" rows="2"><?= htmlspecialchars($_POST['description'] ?? $p['description']) ?></textarea>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Category</label>
                <select name="category" class="form-select">
                    <option value="">-- Select Category --</option>
                    <?php foreach (['Food & Groceries', 'Beverages', 'Personal Care', 'Household & Cleaning Supplies', 'Health & Medical Supplies', 'Baby Products', 'Stationery & Office Supplies', 'Retail Goods'] as $cat): ?>
                    <option value="<?= $cat ?>" <?= ($p['category'] === $cat) ? 'selected' : '' ?>><?= $cat ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Unit Price (LKR)</label>
                <input type="number" step="0.01" name="unit_price" class="form-control" value="<?= $_POST['unit_price'] ?? $p['unit_price'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Unit of Measure</label>
                <select name="unit_of_measure" class="form-select">
                    <?php foreach (['unit','bag','bottle','pack','kg','liter','box'] as $u): ?>
                    <option value="<?= $u ?>" <?= ($p['unit_of_measure']) === $u ? 'selected' : '' ?>><?= ucfirst($u) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Reorder Level</label>
                <input type="number" name="reorder_level" class="form-control" value="<?= $p['reorder_level'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Discount (%)</label>
                <input type="number" name="discount_percentage" class="form-control" min="0" max="100" value="<?= $p['discount_percentage'] ?>">
            </div>
            <div class="col-md-4">
                <label class="form-label fw-semibold">Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $p['status']==='active'?'selected':'' ?>>Active</option>
                    <option value="discontinued" <?= $p['status']==='discontinued'?'selected':'' ?>>Discontinued</option>
                </select>
            </div>
            <div class="col-12"><hr>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Update Product</button>
                <a href="list.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
