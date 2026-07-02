<?php
// ============================================
// modules/reports/revenue_report.php
// Detailed Revenue and Payment Tracking
// ============================================
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager']);

$page_title = "Detailed Revenue Report";

// Date Range Filtering
$start_date = $_GET['start'] ?? date('Y-m-01');
$end_date   = $_GET['end'] ?? date('Y-m-d');

$payments = mysqli_query($conn,
    "SELECT p.*, inv.invoice_number, u.name AS customer_name
     FROM payments p
     JOIN invoices inv ON p.invoice_id = inv.id
     JOIN users u ON inv.customer_id = u.id
     WHERE p.payment_date BETWEEN '$start_date' AND '$end_date'
     ORDER BY p.payment_date DESC"
);

// Method Summary
$methods = mysqli_query($conn,
    "SELECT payment_method, SUM(amount_paid) AS total, COUNT(*) AS cnt
     FROM payments
     WHERE payment_date BETWEEN '$start_date' AND '$end_date'
     GROUP BY payment_method"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="mb-3 d-flex justify-content-between align-items-center">
    <div><a href="dashboard.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a></div>
    <div class="d-flex gap-2 align-items-center">
        <form class="row g-2" method="GET">
            <div class="col-auto"><input type="date" name="start" value="<?= $start_date ?>" class="form-control form-control-sm"></div>
            <div class="col-auto"><input type="date" name="end" value="<?= $end_date ?>" class="form-control form-control-sm"></div>
            <div class="col-auto"><button type="submit" class="btn btn-sm btn-primary">Go</button></div>
        </form>
        <button onclick="window.print()" class="btn btn-sm btn-outline-dark"><i class="bi bi-printer me-1"></i>Print</button>
    </div>
</div>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="page-card">
            <div class="page-card-header">
                <h5><i class="bi bi-cash-stack me-2"></i>Payment Collection Ledger</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Invoice #</th>
                            <th>Customer</th>
                            <th>Method</th>
                            <th class="text-end">Amount Paid</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $grand_total = 0;
                        while ($p = mysqli_fetch_assoc($payments)): 
                            $grand_total += $p['amount_paid'];
                        ?>
                        <tr>
                            <td><small><?= date('d M Y', strtotime($p['payment_date'])) ?></small></td>
                            <td><code><?= htmlspecialchars($p['invoice_number']) ?></code></td>
                            <td><strong><?= htmlspecialchars($p['customer_name']) ?></strong></td>
                            <td><span class="badge bg-light text-dark border text-capitalize"><?= $p['payment_method'] ?></span></td>
                            <td class="text-end fw-bold">LKR <?= number_format($p['amount_paid'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if (mysqli_num_rows($payments) === 0): ?>
                        <tr><td colspan="5" class="text-center py-4 text-muted">No payments recorded in this period</td></tr>
                        <?php endif; ?>
                    </tbody>
                    <tfoot class="table-light">
                        <tr><td colspan="4" class="text-end fw-bold">Total Collection</td><td class="text-end h5 text-primary mb-0">LKR <?= number_format($grand_total, 2) ?></td></tr>
                    </tfoot>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="page-card">
            <div class="page-card-header">
                <h5><i class="bi bi-pie-chart me-2"></i>Revenue by Method</h5>
            </div>
            <div class="table-responsive">
                <table class="table mb-0">
                    <thead><tr><th>Method</th><th>Count</th><th class="text-end">Amount</th></tr></thead>
                    <tbody>
                        <?php while ($m = mysqli_fetch_assoc($methods)): ?>
                        <tr>
                            <td class="text-capitalize"><?= $m['payment_method'] ?></td>
                            <td><?= $m['cnt'] ?></td>
                            <td class="text-end fw-bold">LKR <?= number_format($m['total'], 2) ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
