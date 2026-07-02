<?php
// ============================================
// modules/reports/due_payment_report.php
// Customer Due Payment Report
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager','rdc_staff']);

$user_role = $_SESSION['role'];
$user_prov = $_SESSION['province'] ?? 'None';
$my_center_id = null;

if ($user_role === 'rdc_staff') {
    $c_stmt = mysqli_query($conn, "SELECT id FROM distribution_centers WHERE province='$user_prov' AND status='active'");
    if ($c_row = mysqli_fetch_assoc($c_stmt)) {
        $my_center_id = $c_row['id'];
    } else {
        $my_center_id = -1;
    }
}

$page_title = "Customer Due Payment Report";

// Filters
$cust_filter = isset($_GET['customer']) ? (int)$_GET['customer'] : 0;
$status      = isset($_GET['status']) ? $_GET['status'] : 'pending_only';

$where_clauses = ["inv.status IN ('unpaid', 'partially_paid')"];
if ($cust_filter) $where_clauses[] = "inv.customer_id = $cust_filter";
if ($status === 'all') array_shift($where_clauses); // Remove unpaid filter for all report

if ($my_center_id) {
    $where_clauses[] = "inv.order_id IN (SELECT id FROM orders WHERE center_id=$my_center_id)";
}

$where = !empty($where_clauses) ? "WHERE " . implode(' AND ', $where_clauses) : "";

$due_data = mysqli_query($conn,
    "SELECT inv.*, u.name AS customer_name, u.email AS customer_email
     FROM invoices inv
     JOIN users u ON inv.customer_id = u.id
     $where
     ORDER BY inv.due_date ASC"
);

// Get customers for filter
$cust_where = $my_center_id ? "AND province='$user_prov'" : "";
$customers = mysqli_query($conn, "SELECT id, name FROM users WHERE role='customer' $cust_where ORDER BY name");

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <div><a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a></div>
    <div class="d-flex gap-2">
        <form class="row g-2" method="GET">
            <div class="col-auto">
                <select name="customer" class="form-select form-select-sm">
                    <option value="">All Customers</option>
                    <?php while ($c = mysqli_fetch_assoc($customers)): ?>
                        <option value="<?= $c['id'] ?>" <?= $cust_filter == $c['id'] ? 'selected' : '' ?>><?= htmlspecialchars($c['name']) ?></option>
                    <?php endwhile; ?>
                </select>
            </div>
            <div class="col-auto">
                <select name="status" class="form-select form-select-sm">
                    <option value="pending_only" <?= $status == 'pending_only' ? 'selected' : '' ?>>Unpaid & Partial</option>
                    <option value="all" <?= $status == 'all' ? 'selected' : '' ?>>All Invoices</option>
                </select>
            </div>
            <div class="col-auto">
                <button type="submit" class="btn btn-sm btn-primary">Filter</button>
                <a href="due_payment_report.php" class="btn btn-sm btn-outline-danger">Clear</a>
            </div>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer"></i></button>
    </div>
</div>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-cash-stack me-2"></i>Outstanding Customer Invoices</h5>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Invoice #</th>
                    <th>Customer</th>
                    <th>Date Issued</th>
                    <th>Due Date</th>
                    <th>Aging</th>
                    <th class="text-end">Total</th>
                    <th class="text-end">Balance Due</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = mysqli_fetch_assoc($due_data)): 
                    $due_dt = new DateTime($row['due_date']);
                    $now = new DateTime();
                    $diff = $now->diff($due_dt);
                    $days = $diff->days * ($diff->invert ? -1 : 1);
                    $is_overdue = ($days < 0 && $row['status'] != 'paid');
                ?>
                <tr class="<?= $is_overdue ? 'table-danger' : '' ?>">
                    <td><code><?= htmlspecialchars($row['invoice_number']) ?></code></td>
                    <td>
                        <strong><?= htmlspecialchars($row['customer_name']) ?></strong><br>
                        <small class="text-muted"><?= htmlspecialchars($row['customer_email']) ?></small>
                    </td>
                    <td><small><?= date('d M Y', strtotime($row['issued_at'])) ?></small></td>
                    <td><small><?= date('d M Y', strtotime($row['due_date'])) ?></small></td>
                    <td>
                        <?php if ($row['status'] == 'paid'): ?>
                            -
                        <?php elseif ($days < 0): ?>
                            <span class="text-danger fw-bold"><?= abs($days) ?>d Overdue</span>
                        <?php else: ?>
                            <span>Due in <?= $days ?>d</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-end">LKR <?= number_format($row['total_amount'], 2) ?></td>
                    <td class="text-end fw-bold text-danger">LKR <?= number_format($row['amount_due'], 2) ?></td>
                    <td><span class="badge badge-<?= $row['status'] ?>"><?= ucfirst($row['status']) ?></span></td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($due_data) === 0): ?>
                <tr><td colspan="8" class="text-center text-muted py-3">No outstanding payments found</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
