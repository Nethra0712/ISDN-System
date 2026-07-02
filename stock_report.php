<?php
// ============================================
// modules/reports/stock_report.php
// Comprehensive Stock Report
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager']);

$page_title = "Stock Report";

// Filters
$center_filter = isset($_GET['center']) ? (int)$_GET['center'] : 0;
$cat_filter    = isset($_GET['category']) ? $_GET['category'] : '';

$where_clauses = [];
if ($center_filter) $where_clauses[] = "i.center_id = $center_filter";
if ($cat_filter)    $where_clauses[] = "p.category = '" . mysqli_real_escape_string($conn, $cat_filter) . "'";
$where = !empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "";

$stock_data = mysqli_query($conn,
    "SELECT i.quantity_on_hand, p.name AS product_name, p.sku, p.category, p.reorder_level, dc.name AS center_name
     FROM inventory i
     JOIN products p ON i.product_id = p.id
     JOIN distribution_centers dc ON i.center_id = dc.id
     $where
     ORDER BY dc.name, p.name"
);

// Get filters data
$centers  = mysqli_query($conn, "SELECT id, name FROM distribution_centers WHERE status='active'");
$categories = mysqli_query($conn, "SELECT DISTINCT category FROM products WHERE category != ''");

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <div><a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a></div>
    <div class="d-flex gap-2">
        <form class="row g-2" method="GET">
            <div class="col-auto">
                <select name="center" class="form-select form-select-sm">
                    <option value="">All Centers</option>
                    <?php while ($c = mysqli_fetch_assoc($centers)): ?>
                        <option value="<?= $c['id'] ?>" <?= $center_filter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="category" class="form-select form-select-sm">
                    <option value="">All Categories</option>
                    <?php while ($cat = mysqli_fetch_assoc($categories)): ?>
                        <option value="<?= htmlspecialchars($cat['category']) ?>" <?= $cat_filter == $cat['category'] ? 'selected' : '' ?>><?= htmlspecialchars($cat['category']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="stock_report.php" class="btn btn-sm btn-outline-danger">Clear</a>
            </div>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i></button>
    </div>
</div>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-box-seam me-2"></i>Global Inventory Levels</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Center</th>
                    <th>Product</th>
                    <th>SKU</th>
                    <th>Category</th>
                    <th class="text-end">Quantity</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($stock_data)): ?>
                <tr>
                    <td><?= htmlspecialchars($row['center_name']) ?></td>
                    <td><strong><?= htmlspecialchars($row['product_name']) ?></strong></td>
                    <td><code><?= htmlspecialchars($row['sku']) ?></code></td>
                    <td><small class="text-muted"><?= htmlspecialchars($row['category']) ?></small></td>
                    <td class="text-end fw-bold"><?= number_format($row['quantity_on_hand']) ?></td>
                    <td>
                        <?php if ($row['quantity_on_hand'] <= 0): ?>
                            <span class="badge bg-danger">Out of Stock</span>
                        <?php elseif ($row['quantity_on_hand'] <= $row['reorder_level']): ?>
                            <span class="badge bg-warning text-dark">Low Stock</span>
                        <?php else: ?>
                            <span class="badge bg-success">Healthy</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($stock_data) === 0): ?>
                <tr><td colspan="6" class="text-center text-muted py-3">No inventory data found matching filters</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
