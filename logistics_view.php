<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['logistics_staff']);

$page_title = "My Deliveries";
$uid = $_SESSION['user_id'];

// Handle status update by logistics staff
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery'])) {
    $did    = (int)$_POST['delivery_id'];
    $status = $_POST['status'];
    $notes  = trim($_POST['notes']);
    $actual = ($status === 'delivered') ? date('Y-m-d H:i:s') : null;

    $stmt = mysqli_prepare($conn, "UPDATE deliveries SET status=?, delivery_notes=?, actual_delivery=? WHERE id=? AND assigned_to=?");
    mysqli_stmt_bind_param($stmt, "ssiii", $status, $notes, $actual, $did, $uid);
    mysqli_stmt_execute($stmt);
    
    // Update order status if delivered
    if ($status === 'delivered') {
        $ord_res = mysqli_query($conn, "SELECT order_id FROM deliveries WHERE id=$did");
        $ord_row = mysqli_fetch_assoc($ord_res);
        $oid = $ord_row['order_id'];
        mysqli_query($conn, "UPDATE orders SET status='delivered' WHERE id=$oid");
    }

    header("Location: logistics_view.php?msg=updated");
    exit();
}

// Fetch active assignments for this staff member
$deliveries = mysqli_query($conn,
    "SELECT d.*, o.order_number, o.route_number as order_route, u.name AS customer_name, u.permanent_address
     FROM deliveries d
     JOIN orders o ON d.order_id=o.id
     JOIN users u ON o.customer_id=u.id
     WHERE d.assigned_to = $uid AND d.status NOT IN ('delivered', 'delayed')
     ORDER BY d.created_at DESC"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-truck me-2"></i>My Active Assignments</h5>
    </div>

    <?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success alert-dismissible fade show py-2 small">Delivery status updated. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Delivery #</th>
                    <th>Order #</th>
                    <th>Customer & Address</th>
                    <th>Route</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($d = mysqli_fetch_assoc($deliveries)): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($d['delivery_number']) ?></strong></td>
                    <td><?= htmlspecialchars($d['order_number']) ?></td>
                    <td>
                        <strong><?= htmlspecialchars($d['customer_name']) ?></strong><br>
                        <small class="text-muted"><i class="bi bi-geo-alt"></i> <?= nl2br(htmlspecialchars($d['permanent_address'])) ?></small>
                    </td>
                    <td><span class="badge bg-info text-dark">Route <?= $d['order_route'] ?></span></td>
                    <td><code><?= htmlspecialchars($d['vehicle_number'] ?? 'N/A') ?></code></td>
                    <td><span class="badge badge-<?= $d['status'] ?>"><?= ucfirst(str_replace('_',' ',$d['status'])) ?></span></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#updateModal"
                            data-did="<?= $d['id'] ?>" data-dnum="<?= htmlspecialchars($d['delivery_number']) ?>"
                            data-status="<?= $d['status'] ?>">
                            Update Status
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($deliveries) === 0): ?>
                <tr><td colspan="7" class="text-center py-5 text-muted"><i class="bi bi-check2-circle d-block mb-2" style="font-size:2rem;"></i>No pending deliveries assigned to you.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Update Status Modal -->
<div class="modal fade" id="updateModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Update Delivery Status</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="delivery_id" id="modal_did">
                    <p class="small text-muted mb-3">Delivery: <strong id="modal_dnum"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Status</label>
                        <select name="status" id="modal_status" class="form-select form-select-sm" required>
                            <option value="pending">Pending</option>
                            <option value="out_for_delivery">Out for Delivery</option>
                            <option value="delivered">Delivered</option>
                            <option value="delayed">Delayed</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold small">Delivery Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="update_delivery" class="btn btn-sm btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
let locationInterval = null;
let currentTrackingId = null;

function updateLocation(did, lat, lng) {
    fetch('api_update_location.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ delivery_id: did, lat: lat, lng: lng })
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success) console.error('Tracking Error:', data.message);
    })
    .catch(err => console.error('AJAX Error:', err));
}

function startTracking(did) {
    if (locationInterval) clearInterval(locationInterval);
    currentTrackingId = did;
    
    locationInterval = setInterval(() => {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    updateLocation(did, position.coords.latitude, position.coords.longitude);
                },
                (error) => {
                    console.error('Geolocation Error:', error.message);
                },
                { enableHighAccuracy: true }
            );
        }
    }, 5000);
}

function stopTracking() {
    if (locationInterval) {
        clearInterval(locationInterval);
        locationInterval = null;
    }
}

document.getElementById('updateModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('modal_did').value = btn.dataset.did;
    document.getElementById('modal_dnum').textContent = btn.dataset.dnum;
    document.getElementById('modal_status').value = btn.dataset.status;
});

// Auto-start tracking on page load if any delivery is "out_for_delivery"
window.addEventListener('load', () => {
    const outForDeliveryItems = document.querySelectorAll('span.badge-out_for_delivery');
    if (outForDeliveryItems.length > 0) {
        // Find the first ID (usually there's only one active in-transit delivery per staff)
        const firstRow = outForDeliveryItems[0].closest('tr');
        const updateBtn = firstRow.querySelector('button[data-bs-target="#updateModal"]');
        if (updateBtn) startTracking(updateBtn.dataset.did);
    }
});
</script>

<?php include BASE_URL . 'includes/footer.php'; ?>
