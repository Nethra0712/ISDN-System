<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','rdc_staff']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: list.php"); exit(); }

$stmt = mysqli_prepare($conn,
    "SELECT po.*, dc.name AS center_name, u.name AS created_by_name, a.name AS approved_by_name
     FROM purchase_orders po JOIN distribution_centers dc ON po.center_id=dc.id
     JOIN users u ON po.created_by=u.id LEFT JOIN users a ON po.approved_by=a.id WHERE po.id=?"
);
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$po = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$po) { header("Location: list.php"); exit(); }

$items = mysqli_query($conn,
    "SELECT poi.*, p.name AS product_name, p.sku FROM purchase_order_items poi JOIN products p ON poi.product_id=p.id WHERE poi.po_id=$id"
);

$page_title = "PO " . $po['po_number'];

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="mb-3 text-end">
    <a href="list.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
</div>

<div class="row g-3">
    <div class="col-md-8">
        <div class="page-card">
            <div class="page-card-header">
                <h5><?= htmlspecialchars($po['po_number']) ?></h5>
                <span class="badge badge-<?= $po['status'] ?> fs-6"><?= ucfirst($po['status']) ?></span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>SKU</th><th>Product</th><th>Ordered</th><th>Unit Cost</th><th>Total</th></tr></thead>
                    <tbody>
                        <?php while ($item = mysqli_fetch_assoc($items)): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($item['sku']) ?></code></td>
                            <td><?= htmlspecialchars($item['product_name']) ?></td>
                            <td><?= $item['quantity_ordered'] ?></td>
                            <td>LKR <?= number_format($item['unit_cost'], 2) ?></td>
                            <td><strong>LKR <?= number_format($item['quantity_ordered'] * $item['unit_cost'], 2) ?></strong></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                    <tfoot>
                        <tr><td colspan="4" class="text-end fw-bold">Grand Total</td>
                        <td><strong class="text-primary">LKR <?= number_format($po['total_amount'], 2) ?></strong></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="page-card">
            <div class="page-card-header"><h5>PO Information</h5></div>
            <table class="table table-sm table-borderless mb-0">
                <tr><td class="text-muted fw-semibold">Center</td><td><?= htmlspecialchars($po['center_name']) ?></td></tr>
                <tr><td class="text-muted fw-semibold">Supplier</td><td><?= htmlspecialchars($po['supplier_name']) ?></td></tr>
                <tr><td class="text-muted fw-semibold">Created By</td><td><?= htmlspecialchars($po['created_by_name']) ?></td></tr>
                <tr><td class="text-muted fw-semibold">Approved By</td><td><?= $po['approved_by_name'] ?? '<em class="text-muted">Pending</em>' ?></td></tr>
                <tr><td class="text-muted fw-semibold">Exp. Delivery</td><td><?= $po['expected_delivery'] ?? '-' ?></td></tr>
                <tr><td class="text-muted fw-semibold">Notes</td><td><small><?= nl2br(htmlspecialchars($po['notes'])) ?></small></td></tr>
            </table>
        </div>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
