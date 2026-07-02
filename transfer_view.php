<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','rdc_staff']);

$id = (int)($_GET['id'] ?? 0);
$error = '';
$success = '';

// Fetch Transfer Header
$t_query = "
    SELECT st.*, 
           sc.name AS source_name, 
           dc.name AS dest_name, 
           u.name AS creator_name,
           a.name AS approver_name
    FROM stock_transfers st
    JOIN distribution_centers sc ON st.source_center_id = sc.id
    JOIN distribution_centers dc ON st.dest_center_id = dc.id
    JOIN users u ON st.created_by = u.id
    LEFT JOIN users a ON st.approved_by = a.id
    WHERE st.id = $id
";
$t_res = mysqli_query($conn, $t_query);
$transfer = mysqli_fetch_assoc($t_res);

if (!$transfer) {
    header("Location: transfer_list.php");
    exit();
}

$page_title = "Transfer Request: " . $transfer['transfer_number'];

// Handle Approval / Execution
// Only Admin or HO Manager can approve/execute
$can_execute = in_array($_SESSION['role'], ['admin', 'ho_manager']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_execute && $transfer['status'] === 'pending') {
    if (isset($_POST['execute'])) {
        mysqli_begin_transaction($conn);
        try {
            // Fetch Items
            $items_query = mysqli_query($conn, "SELECT sti.*, p.name as product_name FROM stock_transfer_items sti JOIN products p ON sti.product_id = p.id WHERE sti.transfer_id = $id");
            
            while ($item = mysqli_fetch_assoc($items_query)) {
                $pid = $item['product_id'];
                $qty = $item['quantity'];
                
                // Check source stock
                $check = mysqli_prepare($conn, "SELECT quantity_on_hand FROM inventory WHERE product_id=? AND center_id=? FOR UPDATE");
                mysqli_stmt_bind_param($check, "ii", $pid, $transfer['source_center_id']);
                mysqli_stmt_execute($check);
                $res = mysqli_stmt_get_result($check);
                $inv = mysqli_fetch_assoc($res);

                if (!$inv || $inv['quantity_on_hand'] < $qty) {
                    throw new Exception("Insufficient stock for " . $item['product_name'] . " at " . $transfer['source_name']);
                }

                // Deduct from source
                $deduct = mysqli_prepare($conn, "UPDATE inventory SET quantity_on_hand = quantity_on_hand - ? WHERE product_id=? AND center_id=?");
                mysqli_stmt_bind_param($deduct, "iii", $qty, $pid, $transfer['source_center_id']);
                mysqli_stmt_execute($deduct);

                // Add to destination
                $add = mysqli_prepare($conn, 
                    "INSERT INTO inventory (product_id, center_id, quantity_on_hand) VALUES (?, ?, ?) 
                     ON DUPLICATE KEY UPDATE quantity_on_hand = quantity_on_hand + ?"
                );
                mysqli_stmt_bind_param($add, "iiii", $pid, $transfer['dest_center_id'], $qty, $qty);
                mysqli_stmt_execute($add);
            }

            // Update status
            $upd = mysqli_prepare($conn, "UPDATE stock_transfers SET status='completed', approved_by=? WHERE id=?");
            mysqli_stmt_bind_param($upd, "ii", $_SESSION['user_id'], $id);
            mysqli_stmt_execute($upd);

            mysqli_commit($conn);
            $success = "Transfer executed and stock updated successfully.";
            
            // Refresh transfer data
            $t_res = mysqli_query($conn, $t_query);
            $transfer = mysqli_fetch_assoc($t_res);
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error = $e->getMessage();
        }
    } elseif (isset($_POST['reject'])) {
        $rej = mysqli_prepare($conn, "UPDATE stock_transfers SET status='rejected', approved_by=? WHERE id=?");
        mysqli_stmt_bind_param($rej, "ii", $_SESSION['user_id'], $id);
        mysqli_stmt_execute($rej);
        $success = "Transfer request rejected.";
        
        // Refresh transfer data
        $t_res = mysqli_query($conn, $t_query);
        $transfer = mysqli_fetch_assoc($t_res);
    }
}

// Fetch Items for Display
$items = mysqli_query($conn, "
    SELECT sti.*, p.name, p.sku, p.unit_of_measure 
    FROM stock_transfer_items sti 
    JOIN products p ON sti.product_id = p.id 
    WHERE sti.transfer_id = $id
");

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="page-card">
            <div class="page-card-header d-flex justify-content-between align-items-center">
                <h5><i class="bi bi-box-seam me-2"></i>Transfer Items</h5>
                <span class="badge <?= $transfer['status'] == 'completed' ? 'bg-success' : ($transfer['status'] == 'pending' ? 'bg-warning' : 'bg-danger') ?>">
                    <?= strtoupper($transfer['status']) ?>
                </span>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th class="text-end">Quantity</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($item = mysqli_fetch_assoc($items)): ?>
                        <tr>
                            <td><?= htmlspecialchars($item['name']) ?></td>
                            <td><code><?= htmlspecialchars($item['sku']) ?></code></td>
                            <td class="text-end fw-bold"><?= $item['quantity'] ?> <small class="text-muted"><?= $item['unit_of_measure'] ?></small></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="page-card mb-3">
            <div class="page-card-header"><h5>Transfer Details</h5></div>
            <div class="page-card-body p-3">
                <div class="mb-2">
                    <label class="small text-muted d-block">Reference #</label>
                    <strong><?= $transfer['transfer_number'] ?></strong>
                </div>
                <div class="mb-2">
                    <label class="small text-muted d-block">Source Center</label>
                    <i class="bi bi-geo-alt me-1 text-danger"></i><?= htmlspecialchars($transfer['source_name']) ?>
                </div>
                <div class="mb-2">
                    <label class="small text-muted d-block">Destination Center</label>
                    <i class="bi bi-geo-alt me-1 text-success"></i><?= htmlspecialchars($transfer['dest_name']) ?>
                </div>
                <hr class="my-2">
                <div class="mb-2">
                    <label class="small text-muted d-block">Created By</label>
                    <?= htmlspecialchars($transfer['creator_name']) ?> <small class="text-muted">on <?= date('M d, Y H:i', strtotime($transfer['created_at'])) ?></small>
                </div>
                <?php if ($transfer['approved_by']): ?>
                <div class="mb-2">
                    <label class="small text-muted d-block"><?= $transfer['status'] == 'completed' ? 'Approved & Executed By' : 'Rejected By' ?></label>
                    <?= htmlspecialchars($transfer['approver_name']) ?>
                </div>
                <?php endif; ?>
                <?php if ($transfer['notes']): ?>
                <div class="mb-2">
                    <label class="small text-muted d-block">Notes</label>
                    <p class="small mb-0"><?= nl2br(htmlspecialchars($transfer['notes'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($success): ?>
            <div class="alert alert-success py-2 small"><i class="bi bi-check-circle-fill me-1"></i><?= $success ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-triangle-fill me-1"></i><?= $error ?></div>
        <?php endif; ?>

        <?php if ($can_execute && $transfer['status'] === 'pending'): ?>
        <div class="page-card">
            <div class="page-card-header"><h5>Actions</h5></div>
            <div class="page-card-body p-3">
                <form method="POST">
                    <div class="d-grid gap-2">
                        <button type="submit" name="execute" class="btn btn-success" onclick="return confirm('Execute this transfer? This will update stock levels.')">
                            <i class="bi bi-check-all me-1"></i> Approve & Execute
                        </button>
                        <button type="submit" name="reject" class="btn btn-outline-danger" onclick="return confirm('Reject this request?')">
                            <i class="bi bi-x-circle me-1"></i> Reject Request
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="mt-3">
            <a href="transfer_list.php" class="btn btn-sm btn-outline-secondary w-100"><i class="bi bi-arrow-left me-1"></i> Back to List</a>
        </div>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
