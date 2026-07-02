<?php
session_start();
define('BASE_URL', '../../');
require_once BASE_URL . 'config/db.php';
require_once BASE_URL . 'includes/auth_check.php';
require_role(['admin','ho_manager']);

$page_title = "User Management";

// Approve/Activate/Deactivate
if (isset($_GET['action']) && isset($_GET['uid'])) {
    $uid    = (int)$_GET['uid'];
    $action = $_GET['action'];
    if ($action === 'activate') {
        $stmt = mysqli_prepare($conn, "UPDATE users SET status='active' WHERE id=?");
    } elseif ($action === 'deactivate') {
        $stmt = mysqli_prepare($conn, "UPDATE users SET status='inactive' WHERE id=?");
    } elseif ($action === 'approve') {
        $stmt = mysqli_prepare($conn, "UPDATE users SET status='active' WHERE id=? AND role='customer'");
    } elseif ($action === 'delete' && $_SESSION['role'] === 'admin') {
        // Prevent deleting self
        if ($uid !== $_SESSION['user_id']) {
            $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id=?");
        }
    }
    if (isset($stmt)) {
        mysqli_stmt_bind_param($stmt, "i", $uid);
        mysqli_stmt_execute($stmt);
    }
    header("Location: users.php?msg=updated");
    exit();
}

// Create staff user
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $name   = trim($_POST['name']);
    $email  = trim($_POST['email']);
    $pass   = trim($_POST['password']);
    $role   = $_POST['role'];
    $province = $_POST['province'] ?? 'None';
    $allowed_roles = ['ho_manager','rdc_staff','logistics_staff'];

    if (!in_array($role, $allowed_roles) || empty($name) || empty($email) || empty($pass)) {
        $error = "All fields required and role must be staff.";
    } else {
        $hashed = password_hash($pass, PASSWORD_BCRYPT);
        $stmt   = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, province, status) VALUES (?,?,?,?,?, 'active')");
        mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $hashed, $role, $province);
        if (!mysqli_stmt_execute($stmt)) $error = "Email may already exist.";
        else { header("Location: users.php?msg=created"); exit(); }
    }
}

$filter = isset($_GET['role']) ? $_GET['role'] : '';
$where  = $filter ? "WHERE role='" . mysqli_real_escape_string($conn, $filter) . "'" : "";
$users  = mysqli_query($conn, "SELECT * FROM users $where ORDER BY created_at DESC");

include BASE_URL . 'includes/header.php';
include BASE_URL . 'includes/sidebar.php';
include BASE_URL . 'includes/topbar.php';
?>

<?php if (isset($_GET['msg'])): ?>
<div class="alert alert-success alert-dismissible fade show py-2 small">
    <?= $_GET['msg'] === 'created' ? 'Staff user created.' : 'User status updated.' ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
<?php endif; ?>

<div class="row g-3">
    <div class="col-xl-8">
        <div class="page-card">
            <div class="page-card-header">
                <h5><i class="bi bi-people me-2"></i>System Users</h5>
            </div>

            <!-- Role Filters -->
            <div class="mb-3">
                <?php foreach (['' => 'All', 'admin' => 'Admin', 'ho_manager' => 'HO Manager', 'rdc_staff' => 'RDC Staff', 'logistics_staff' => 'Logistics', 'customer' => 'Customers'] as $val => $label): ?>
                <a href="?role=<?= $val ?>" class="btn btn-sm me-1 <?= $filter === $val ? 'btn-primary' : 'btn-outline-secondary' ?>"><?= $label ?></a>
                <?php endforeach; ?>
            </div>

            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Province</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php while ($u = mysqli_fetch_assoc($users)): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['name']) ?></td>
                            <td><small><?= htmlspecialchars($u['email']) ?></small></td>
                            <td>
                                <span class="badge bg-secondary text-capitalize"><?= str_replace('_',' ',$u['role']) ?></span>
                            </td>
                            <td><span class="badge bg-info mt-1 text-dark"><?= $u['province'] !== 'None' ? $u['province'] : '-' ?></span></td>
                            <td>
                                <?php if ($u['status'] === 'active'): ?>
                                <span class="badge bg-success">Active</span>
                                <?php elseif ($u['status'] === 'pending'): ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                                <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= date('d M Y', strtotime($u['created_at'])) ?></small></td>
                            <td class="d-flex gap-1">
                                <?php if ($u['status'] === 'pending'): ?>
                                <a href="?action=approve&uid=<?= $u['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Approve this user?')"><i class="bi bi-check-lg"></i></a>
                                <?php elseif ($u['status'] === 'active' && $u['role'] !== 'admin'): ?>
                                <a href="?action=deactivate&uid=<?= $u['id'] ?>" class="btn btn-sm btn-outline-warning" onclick="return confirm('Deactivate?')"><i class="bi bi-pause-circle"></i></a>
                                <?php elseif ($u['status'] === 'inactive'): ?>
                                <a href="?action=activate&uid=<?= $u['id'] ?>" class="btn btn-sm btn-outline-success" onclick="return confirm('Activate?')"><i class="bi bi-play-circle"></i></a>
                                <?php endif; ?>
                                
                                <a href="edit_user.php?id=<?= $u['id'] ?>" class="btn btn-sm btn-outline-primary"><i class="bi bi-pencil"></i></a>
                                
                                <?php if ($_SESSION['role'] === 'admin' && $u['id'] !== $_SESSION['user_id']): ?>
                                <a href="?action=delete&uid=<?= $u['id'] ?>" class="btn btn-sm btn-outline-danger" onclick="return confirm('Are you sure you want to PERMANENTLY delete this user?')">
                                    <i class="bi bi-trash"></i>
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Create Staff User -->
    <?php if ($_SESSION['role'] === 'admin'): ?>
    <div class="col-xl-4">
        <div class="page-card">
            <div class="page-card-header"><h5><i class="bi bi-person-plus me-2"></i>Create Staff User</h5></div>
            <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Full Name</label>
                    <input type="text" name="name" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Email</label>
                    <input type="email" name="email" class="form-control form-control-sm" required>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Role</label>
                    <select name="role" class="form-select form-select-sm" required>
                        <option value="ho_manager">HO Manager</option>
                        <option value="rdc_staff">RDC Staff</option>
                        <option value="logistics_staff">Logistics Staff</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Province</label>
                    <select name="province" class="form-select form-select-sm">
                        <option value="None">None / N/A</option>
                        <option value="North">North</option>
                        <option value="South">South</option>
                        <option value="East">East</option>
                        <option value="West">West</option>
                        <option value="Central">Central</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label fw-semibold">Password</label>
                    <input type="password" name="password" class="form-control form-control-sm" minlength="8" required>
                </div>
                <button type="submit" name="create_user" class="btn btn-primary btn-sm w-100">
                    <i class="bi bi-person-plus me-1"></i>Create User
                </button>
            </form>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include BASE_URL . 'includes/footer.php'; ?>
