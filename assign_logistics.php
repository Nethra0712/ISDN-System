<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin']);

$page_title = "Assign Logistics Staff";

// Handle Assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['assign_logistics'])) {
    $uid       = (int)$_POST['user_id'];
    $route     = (int)$_POST['route_number'];
    $plate     = trim($_POST['plate_number']);

    $stmt = mysqli_prepare($conn, "UPDATE users SET route_number=?, vehicle_plate_number=? WHERE id=?");
    mysqli_stmt_bind_param($stmt, "isi", $route, $plate, $uid);
    if (mysqli_stmt_execute($stmt)) {
        header("Location: assign_logistics.php?msg=success");
    } else {
        header("Location: assign_logistics.php?msg=error");
    }
    exit();
}

// Fetch all logistics staff
$staff_res = mysqli_query($conn, 
    "SELECT u.*, dc.name AS center_name 
     FROM users u 
     LEFT JOIN distribution_centers dc ON u.province = dc.province 
     WHERE u.role='logistics_staff' 
     ORDER BY u.province, u.name"
);

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="page-card">
    <div class="page-card-header">
        <h5><i class="bi bi-truck-front me-2"></i>Logistics Staff Assignment</h5>
    </div>
    
    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'success'): ?>
            <div class="alert alert-success alert-dismissible fade show py-2 small">Staff profile updated successfully! <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php else: ?>
            <div class="alert alert-danger alert-dismissible fade show py-2 small">Error updating staff profile. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead>
                <tr>
                    <th>Staff Name</th>
                    <th>Email</th>
                    <th>Region (Province)</th>
                    <th>Assigned Route</th>
                    <th>Vehicle Plate</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($s = mysqli_fetch_assoc($staff_res)): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                    <td><small class="text-muted"><?= htmlspecialchars($s['email']) ?></small></td>
                    <td><span class="badge bg-light text-dark border"><?= $s['province'] ?></span></td>
                    <td>
                        <?php if ($s['route_number']): ?>
                            <span class="badge bg-primary">Route <?= $s['route_number'] ?></span>
                        <?php else: ?>
                            <span class="text-danger small"><em>Not Set</em></span>
                        <?php endif; ?>
                    </td>
                    <td><code><?= htmlspecialchars($s['vehicle_plate_number'] ?? 'N/A') ?></code></td>
                    <td class="text-end">
                        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#assignModal" 
                            data-id="<?= $s['id'] ?>" data-name="<?= htmlspecialchars($s['name']) ?>" 
                            data-route="<?= $s['route_number'] ?>" data-plate="<?= htmlspecialchars($s['vehicle_plate_number'] ?? '') ?>">
                            <i class="bi bi-pencil-square"></i> Assign
                        </button>
                    </td>
                </tr>
                <?php endwhile; ?>
                <?php if (mysqli_num_rows($staff_res) === 0): ?>
                    <tr><td colspan="6" class="text-center py-4 text-muted">No logistics staff found in the system.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Assignment Modal -->
<div class="modal fade" id="assignModal" tabindex="-1">
    <div class="modal-dialog modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Configure Logistics Profile</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="user_id" id="modal_uid">
                    <p class="small text-muted mb-3">Staff: <strong id="modal_uname"></strong></p>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold small">Assigned Delivery Route</label>
                        <select name="route_number" id="modal_route" class="form-select form-select-sm" required>
                            <option value="">-- Select Route --</option>
                            <option value="1">Route 1 (Main City)</option>
                            <option value="2">Route 2 (Suburban North)</option>
                            <option value="3">Route 3 (Suburban East)</option>
                            <option value="4">Route 4 (Suburban West)</option>
                            <option value="5">Route 5 (Rural / Outskirts)</option>
                        </select>
                    </div>

                    <div class="mb-0">
                        <label class="form-label fw-bold small">Vehicle Plate Number</label>
                        <input type="text" name="plate_number" id="modal_plate" class="form-control form-control-sm" placeholder="WP ABC-1234" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="assign_logistics" class="btn btn-sm btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.getElementById('assignModal').addEventListener('show.bs.modal', function(e) {
    const btn = e.relatedTarget;
    document.getElementById('modal_uid').value = btn.dataset.id;
    document.getElementById('modal_uname').textContent = btn.dataset.name;
    document.getElementById('modal_route').value = btn.dataset.route || '';
    document.getElementById('modal_plate').value = btn.dataset.plate || '';
});
</script>

<?php include BASE_URL . 'includes/footer.php'; ?>
