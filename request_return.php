<?php
// ============================================
// modules/orders/request_return.php
// Customer Return Request Form
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['customer']);

$order_id = (int)($_GET['id'] ?? 0);
if (!$order_id) { header("Location: my_orders.php"); exit(); }

// Fetch order and verify ownership
$stmt = mysqli_prepare($conn, "SELECT * FROM orders WHERE id=? AND customer_id=? AND status='delivered'");
$uid = $_SESSION['user_id'];
mysqli_stmt_bind_param($stmt, "ii", $order_id, $uid);
mysqli_stmt_execute($stmt);
$order = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));

if (!$order) {
    header("Location: my_orders.php");
    exit();
}

$error = '';
$success = '';

// Fetch order items
$items_res = mysqli_query($conn, 
    "SELECT oi.*, p.name AS product_name, p.sku 
     FROM order_items oi 
     JOIN products p ON oi.product_id=p.id 
     WHERE oi.order_id=$order_id"
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = trim($_POST['notes']);
    $ret_items = $_POST['returns'] ?? [];
    
    // Check if at least one item is selected
    $has_selection = false;
    foreach($ret_items as $pid => $qty) {
        if ($qty > 0) $has_selection = true;
    }

    if (!$has_selection) {
        $error = "Please specify the quantity for at least one item to return.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            // Create return request
            $ist = mysqli_prepare($conn, 
                "INSERT INTO return_requests (order_id, customer_id, center_id, notes) 
                 VALUES (?, ?, ?, ?)"
            );
            mysqli_stmt_bind_param($ist, "iiis", $order_id, $uid, $order['center_id'], $notes);
            mysqli_stmt_execute($ist);
            $return_id = mysqli_insert_id($conn);

            // Insert return items
            foreach($ret_items as $pid => $qty) {
                $qty = (int)$qty;
                if ($qty <= 0) continue;

                // Validate requested quantity against original order
                $chk = mysqli_query($conn, "SELECT quantity, unit_price FROM order_items WHERE order_id=$order_id AND product_id=$pid");
                $orig = mysqli_fetch_assoc($chk);
                
                if (!$orig || $qty > $orig['quantity']) {
                    throw new Exception("Invalid quantity for product ID $pid");
                }

                $ri = mysqli_prepare($conn, 
                    "INSERT INTO return_items (return_id, product_id, quantity_requested, unit_price) 
                     VALUES (?, ?, ?, ?)"
                );
                mysqli_stmt_bind_param($ri, "iiid", $return_id, $pid, $qty, $orig['unit_price']);
                mysqli_stmt_execute($ri);
            }

            mysqli_commit($conn);
            header("Location: view.php?id=$order_id&msg=return_requested");
            exit();
        } catch (Exception $e) {
            // Check if connection is still alive before rollback
            if ($conn && mysqli_ping($conn)) {
                mysqli_rollback($conn);
            }
            $error = "Error: " . $e->getMessage();
        }
    }
}

$page_title = "Request Return - " . $order['order_number'];
include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="page-card" style="max-width: 800px;">
    <div class="page-card-header">
        <h5><i class="bi bi-arrow-counterclockwise me-2"></i>Request Return for <?= $order['order_number'] ?></h5>
        <a href="view.php?id=<?= $order_id ?>" class="btn btn-sm btn-outline-secondary">Cancel</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= $error ?></div>
    <?php endif; ?>

    <p class="text-muted small mb-4">Select the items and quantities you wish to return. Our RDC Manager will review your request once submitted.</p>

    <form method="POST">
        <div class="table-responsive mb-4">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Ordered Qty</th>
                        <th style="width: 150px;">Return Qty</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($item = mysqli_fetch_assoc($items_res)): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($item['product_name']) ?></strong><br>
                            <small class="text-muted"><?= $item['sku'] ?></small>
                        </td>
                        <td><?= $item['quantity'] ?></td>
                        <td>
                            <input type="number" name="returns[<?= $item['product_id'] ?>]" 
                                   class="form-control form-control-sm" 
                                   min="0" max="<?= $item['quantity'] ?>" value="0">
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Reason for Return / Notes</label>
            <textarea name="notes" class="form-control" rows="3" placeholder="Please provide a brief reason for the return..." required></textarea>
        </div>

        <div class="alert alert-info py-2 small border-0">
            <i class="bi bi-info-circle me-2"></i>Upon approval, the refund amount will be credited to your <strong>ISDN Wallet</strong> and automatically applied to your next order.
        </div>

        <button type="submit" class="btn btn-primary px-4"><i class="bi bi-send me-1"></i>Submit Return Request</button>
    </form>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
