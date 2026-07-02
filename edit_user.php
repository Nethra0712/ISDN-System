<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin']);

$id = (int)($_GET['id'] ?? 0);
if (!$id) { header("Location: users.php"); exit(); }

// Fetch user
$stmt = mysqli_prepare($conn, "SELECT * FROM users WHERE id=?");
mysqli_stmt_bind_param($stmt, "i", $id);
mysqli_stmt_execute($stmt);
$u = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
if (!$u) { header("Location: users.php"); exit(); }

$page_title = "Edit User: " . $u['name'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $role     = $_POST['role'];
    $province = $_POST['province'] ?? 'None';
    $status   = $_POST['status'];
    $new_pass = trim($_POST['new_password']);

    if (empty($name) || empty($email)) {
        $error = "Name and Email are required.";
    } elseif ($role === 'rdc_staff' && $province === 'None') {
        $error = "RDC Staff must be assigned a province.";
    } else {
        // Update user
        if (!empty($new_pass)) {
            $hashed = password_hash($new_pass, PASSWORD_BCRYPT);
            $upd = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, role=?, province=?, status=?, password=? WHERE id=?");
            mysqli_stmt_bind_param($upd, "ssssssi", $name, $email, $role, $province, $status, $hashed, $id);
        } else {
            $upd = mysqli_prepare($conn, "UPDATE users SET name=?, email=?, role=?, province=?, status=? WHERE id=?");
            mysqli_stmt_bind_param($upd, "sssssi", $name, $email, $role, $province, $status, $id);
        }

        if (mysqli_stmt_execute($upd)) {
            header("Location: users.php?msg=updated");
            exit();
        } else {
            $error = "Update failed. Email may already be in use.";
        }
    }
}

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<div class="page-card" style="max-width:600px;">
    <div class="page-card-header">
        <h5><i class="bi bi-pencil-square me-2"></i>Edit User Details</h5>
        <a href="users.php" class="btn btn-sm btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i>Back</a>
    </div>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="row g-3">
            <div class="col-12">
                <label class="form-label fw-semibold">Full Name</label>
                <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($_POST['name'] ?? $u['name']) ?>" required>
            </div>
            <div class="col-12">
                <label class="form-label fw-semibold">Email Address</label>
                <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($_POST['email'] ?? $u['email']) ?>" required>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Role</label>
                <select name="role" class="form-select">
                    <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>Admin</option>
                    <option value="ho_manager" <?= $u['role']==='ho_manager'?'selected':'' ?>>HO Manager</option>
                    <option value="rdc_staff" <?= $u['role']==='rdc_staff'?'selected':'' ?>>RDC Staff</option>
                    <option value="logistics_staff" <?= $u['role']==='logistics_staff'?'selected':'' ?>>Logistics Staff</option>
                    <option value="customer" <?= $u['role']==='customer'?'selected':'' ?>>Customer</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Province (RDC Only)</label>
                <select name="province" class="form-select">
                    <option value="None" <?= $u['province']==='None'?'selected':'' ?>>None / N/A</option>
                    <option value="North" <?= $u['province']==='North'?'selected':'' ?>>North</option>
                    <option value="South" <?= $u['province']==='South'?'selected':'' ?>>South</option>
                    <option value="East" <?= $u['province']==='East'?'selected':'' ?>>East</option>
                    <option value="West" <?= $u['province']==='West'?'selected':'' ?>>West</option>
                    <option value="Central" <?= $u['province']==='Central'?'selected':'' ?>>Central</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">Account Status</label>
                <select name="status" class="form-select">
                    <option value="active" <?= $u['status']==='active'?'selected':'' ?>>Active</option>
                    <option value="inactive" <?= $u['status']==='inactive'?'selected':'' ?>>Inactive</option>
                    <option value="pending" <?= $u['status']==='pending'?'selected':'' ?>>Pending Approval</option>
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label fw-semibold">New Password (Optional)</label>
                <input type="password" name="new_password" class="form-control" placeholder="Leave blank to keep current">
            </div>
            <div class="col-12"><hr>
                <button type="submit" class="btn btn-primary"><i class="bi bi-save me-1"></i>Save Changes</button>
                <a href="users.php" class="btn btn-outline-secondary ms-2">Cancel</a>
            </div>
        </div>
    </form>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
