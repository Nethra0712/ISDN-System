<?php
// ============================================
// Customer self-registration only
// ============================================
session_start();
require_once '../config/db.php';

define('BASE_URL', '../');

$error   = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);
    $confirm  = trim($_POST['confirm_password']);
    $address  = trim($_POST['address']);
    $province = $_POST['province'] ?? 'None';

    // Basic validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Invalid email format.";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters.";
    } elseif ($password !== $confirm) {
        $error = "Passwords do not match.";
    } else {
        // Check if email already exists
        $stmt = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_store_result($stmt);

        if (mysqli_stmt_num_rows($stmt) > 0) {
            $error = "Email already registered.";
        } else {
            // Hash password securely
            $hashed = password_hash($password, PASSWORD_BCRYPT);

            // Insert as customer with 'pending' status (needs admin approval)
            $stmt2 = mysqli_prepare($conn,
                "INSERT INTO users (name, email, password, role, province, status, permanent_address) VALUES (?, ?, ?, 'customer', ?, 'pending', ?)"
            );
            mysqli_stmt_bind_param($stmt2, "sssss", $name, $email, $hashed, $province, $address);

            if (mysqli_stmt_execute($stmt2)) {
                $success = "Registration successful! Your account is pending approval. You will be notified once approved.";
            } else {
                $error = "Registration failed. Please try again.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISDN - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body { background-color: #0f1923; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Segoe UI', sans-serif; }
        .reg-card { background: #fff; border-radius: 8px; width: 100%; max-width: 480px; box-shadow: 0 20px 60px rgba(0,0,0,0.4); overflow: hidden; }
        .reg-header { background: #1a3a5c; padding: 25px 30px; color: #fff; text-align: center; }
        .reg-header h2 { font-weight: 700; letter-spacing: 2px; margin: 0; font-size: 1.5rem; }
        .reg-body { padding: 30px 35px 20px; }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #444; }
        .form-control:focus { border-color: #1a3a5c; box-shadow: 0 0 0 0.2rem rgba(26,58,92,0.15); }
        .btn-reg { background: #1a3a5c; border: none; color: #fff; width: 100%; padding: 11px; font-weight: 600; border-radius: 5px; }
        .btn-reg:hover { background: #14304e; color: #fff; }
        .reg-footer { background: #f8f9fa; padding: 15px 35px; text-align: center; font-size: 0.85rem; color: #666; border-top: 1px solid #e9ecef; }
        .reg-footer a { color: #1a3a5c; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
<div class="reg-card">
    <div class="reg-header">
        <i class="bi bi-person-plus" style="font-size:1.8rem;"></i>
        <h2>Create Account</h2>
        <p style="opacity:.75;font-size:.85rem;margin:5px 0 0;">ISDN Customer Registration</p>
    </div>
    <div class="reg-body">
        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success py-2 small"><i class="bi bi-check-circle me-1"></i><?= $success ?></div>
        <?php else: ?>
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control" placeholder="John Perera" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
            </div>
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="you@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            <div class="row">
                <div class="col-6 mb-3">
                    <label class="form-label">Password</label>
                    <input type="password" name="password" class="form-control" placeholder="Min 8 chars" required>
                </div>
                <div class="col-6 mb-3">
                    <label class="form-label">Confirm Password</label>
                    <input type="password" name="confirm_password" class="form-control" placeholder="Repeat" required>
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Province</label>
                <select name="province" class="form-select" required>
                    <option value="">Select your Province...</option>
                    <option value="North">Northern Province</option>
                    <option value="South">Southern Province</option>
                    <option value="East">Eastern Province</option>
                    <option value="West">Western Province</option>
                    <option value="Central">Central Province</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label">Permanent Address (For Deliveries)</label>
                <textarea name="address" class="form-control" rows="2" placeholder="Your delivery address..." required><?= htmlspecialchars($_POST['address'] ?? '') ?></textarea>
            </div>
            <button type="submit" class="btn btn-reg">
                <i class="bi bi-person-check me-1"></i> Register
            </button>
        </form>
        <?php endif; ?>
    </div>
    <div class="reg-footer">
        Already have an account? <a href="login.php">Sign In</a>
    </div>
</div>
</body>
</html>
