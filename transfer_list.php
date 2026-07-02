<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','rdc_staff']);

$page_title = "Stock Transfer Requests";

$user_role = $_SESSION['role'];
$user_prov = $_SESSION['province'] ?? 'None';
$my_center_id = null;

if ($user_role === 'rdc_staff') {
    $c_stmt = mysqli_query($conn, "SELECT id FROM distribution_centers WHERE province='$user_prov' AND status='active'");
    if ($c_row = mysqli_fetch_assoc($c_stmt)) {
        $my_center_id = $c_row['id'];
    }
}

$where = "";
if ($my_center_id) {
    $where = "WHERE st.source_center_id = $my_center_id OR st.dest_center_id = $my_center_id";
}

$query = "
    SELECT st.*, 
           sc.name AS source_name, 
           dc.name AS dest_name, 
           u.name AS creator_name,
           (SELECT COUNT(*) FROM stock_transfer_items sti WHERE sti.transfer_id = st.id) as item_count
    FROM stock_transfers st
    JOIN distribution_centers sc ON st.source_center_id = sc.id
    JOIN distribution_centers dc ON st.dest_center_id = dc.id
    JOIN users u ON st.created_by = u.id
    $where
    ORDER BY st.created_at DESC
";
$transfers = mysqli_query($conn, $query);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="page-card">
    <div class="page-card-header d-flex justify-content-between align-items-center">
        <h5><i class="bi bi-arrow-left-right me-2"></i>Stock Transfer History</h5>
        <a href="transfer.php" class="btn btn-sm btn-primary"><i class="bi bi-plus-circle me-1"></i>New Request</a>
    </div>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Ref #</th>
                    <th>Date</th>
                    <th>Source</th>
                    <th>Destination</th>
                    <th>Items</th>
                    <th>Created By</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($t = mysqli_fetch_assoc($transfers)): ?>
                <tr>
                    <td><code><?= $t['transfer_number'] ?></code></td>
                    <td><small><?= date('M d, Y', strtotime($t['created_at'])) ?></small></td>
                    <td><?= htmlspecialchars($t['source_name']) ?></td>
                    <td><?= htmlspecialchars($t['dest_name']) ?></td>
                    <td><span class="badge bg-secondary"><?= $t['item_count'] ?> products</span></td>
                    <td><small><?= htmlspecialchars($t['creator_name']) ?></small></td>
                    <td>
                        <?php 
                        $status_class = 'bg-warning';
                        if ($t['status'] == 'completed') $status_class = 'bg-success';
                        if ($t['status'] == 'rejected' || $t['status'] == 'cancelled') $status_class = 'bg-danger';
                        ?>
                        <span class="badge <?= $status_class ?>"><?= ucfirst($t['status']) ?></span>
                    </td>
                    <td>
                        <a href="transfer_view.php?id=<?= $t['id'] ?>" class="btn btn-sm btn-outline-primary py-0">Details</a>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($transfers) == 0): ?>
                <tr>
                    <td colspan="8" class="text-center py-4 text-muted">No stock transfer requests found.</td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
