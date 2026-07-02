<?php
// ============================================
// modules/delivery/history.php
// Delivery History & Tracking Records
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','logistics_staff']);

$page_title = "Delivery History";
$my_id = $_SESSION['user_id'];
$role  = $_SESSION['role'];

$where = ($role === 'logistics_staff') ? "WHERE d.assigned_to=$my_id" : "WHERE 1=1";

$history = mysqli_query($conn,
    "SELECT d.*, o.order_number, u.name AS customer_name, ls.name AS assigned_name
     FROM deliveries d
     JOIN orders o ON d.order_id=o.id
     JOIN users u ON o.customer_id=u.id
     LEFT JOIN users ls ON d.assigned_to=ls.id
     $where
     AND d.status IN ('delivered','delayed','out_for_delivery')
     ORDER BY d.updated_at DESC"
);


include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <div><a href="list.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back to Active</a></div>
    <button onclick="window.print()" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer me-1"></i>Print History</button>
</div>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-clock-history me-2"></i>Delivery Records & History</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-hover mb-0">
            <thead>
                <tr>
                    <th>Num #</th>
                    <th>Order</th>
                    <th>Customer</th>
                    <th>Assigned To</th>
                    <th>Actual/Completion Date</th>
                    <th>Status</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($h = mysqli_fetch_assoc($history)): ?>
                <tr>
                    <td><code><?= htmlspecialchars($h['delivery_number']) ?></code></td>
                    <td><?= htmlspecialchars($h['order_number']) ?></td>
                    <td><?= htmlspecialchars($h['customer_name']) ?></td>
                    <td><small><?= htmlspecialchars($h['assigned_name'] ?? 'N/A') ?></small></td>
                    <td><?= $h['actual_delivery'] ? date('d M Y H:i', strtotime($h['actual_delivery'])) : '-' ?></td>
                    <td><span class="badge badge-<?= $h['status'] ?>"><?= ucfirst(str_replace('_',' ',$h['status'])) ?></span></td>
                    <td><small class="text-muted"><?= htmlspecialchars($h['delivery_notes'] ?: '-') ?></small></td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($history) === 0): ?>
                <tr><td colspan="7" class="text-center text-muted py-4">No completed or historical deliveries found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
