<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin']);

$page_title = "Distribution Centers";
$error = '';

// Add new center
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_center'])) {
    $name    = trim($_POST['name']);
    $loc     = trim($_POST['location']);
    $mgr     = trim($_POST['manager_name']);
    $phone   = trim($_POST['contact_phone']);
    $email   = trim($_POST['contact_email']);
    $province = $_POST['province'] ?? 'None';

    if (empty($name) || empty($loc)) {
        $error = "Name and Location are required.";
    } else {
        $stmt = mysqli_prepare($conn,
            "INSERT INTO distribution_centers (name, location, province, manager_name, contact_phone, contact_email) VALUES (?,?,?,?,?,?)"
        );
        mysqli_stmt_bind_param($stmt, "ssssss", $name, $loc, $province, $mgr, $phone, $email);
        if (mysqli_stmt_execute($stmt)) { header("Location: distribution_centers.php?msg=saved"); exit(); }
        else $error = "Failed to add center.";
    }
}

// Toggle status
if (isset($_GET['toggle'])) {
    $cid = (int)$_GET['toggle'];
    mysqli_query($conn,
        "UPDATE distribution_centers SET status=IF(status='active','inactive','active') WHERE id=$cid"
    );
    header("Location: distribution_centers.php"); exit();
}

$centers = mysqli_query($conn, "SELECT * FROM distribution_centers ORDER BY name");

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show py-2 small">Center saved. <button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="page-card">
            <div class="page-card-header"><h5><i class="bi bi-building-gear me-2"></i>Distribution Centers</h5></div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Location</th><th>Province</th><th>Manager</th><th>Phone</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        <?php while ($c = mysqli_fetch_assoc($centers)): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
                            <td><?= htmlspecialchars($c['location']) ?></td>
                            <td><span class="badge bg-info text-dark"><?= $c['province'] !== 'None' ? $c['province'] : '-' ?></span></td>
                            <td><?= htmlspecialchars($c['manager_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($c['contact_phone'] ?? '-') ?></td>
                            <td><span class="badge <?= $c['status']==='active'?'bg-success':'bg-secondary' ?>"><?= ucfirst($c['status']) ?></span></td>
                            <td>
                                <a href="?toggle=<?= $c['id'] ?>" class="btn btn-sm btn-outline-secondary" onclick="return confirm('Toggle status?')">
                                    <i class="bi bi-toggle-on"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-xl-4">
        <div class="page-card">
            <div class="page-card-header"><h5><i class="bi bi-plus-circle me-2"></i>Add New Center</h5></div>
            <?php if ($error): ?><div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div><?php endif; ?>
            <form method="POST">
                <div class="mb-2"><label class="form-label fw-semibold">Center Name *</label>
                    <input type="text" name="name" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label fw-semibold">Location *</label>
                    <input type="text" name="location" class="form-control form-control-sm" required></div>
                <div class="mb-2"><label class="form-label fw-semibold">Province</label>
                    <select name="province" class="form-select form-select-sm">
                        <option value="None">None / HQ</option>
                        <option value="North">North</option>
                        <option value="South">South</option>
                        <option value="East">East</option>
                        <option value="West">West</option>
                        <option value="Central">Central</option>
                    </select>
                </div>
                <div class="mb-2"><label class="form-label fw-semibold">Manager Name</label>
                    <input type="text" name="manager_name" class="form-control form-control-sm"></div>
                <div class="mb-2"><label class="form-label fw-semibold">Contact Phone</label>
                    <input type="text" name="contact_phone" class="form-control form-control-sm"></div>
                <div class="mb-3"><label class="form-label fw-semibold">Contact Email</label>
                    <input type="email" name="contact_email" class="form-control form-control-sm"></div>
                <button type="submit" name="add_center" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-plus me-1"></i>Add Center
                </button>
            </form>
        </div>
    </div>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
