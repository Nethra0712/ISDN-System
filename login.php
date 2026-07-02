<?php
// ============================================
// auth/login.php
// ============================================
session_start();
require_once '../config/db.php';

define('BASE_URL', '../');

// If already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: ../index.php");
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']);
    $password = trim($_POST['password']);

    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password.";
    } else {
        // Prepared statement to prevent SQL injection
        $stmt = mysqli_prepare($conn, "SELECT id, name, email, password, role, province, status FROM users WHERE email = ?");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user   = mysqli_fetch_assoc($result);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'inactive') {
                $error = "Your account has been deactivated. Contact admin.";
            } elseif ($user['status'] === 'pending') {
                $error = "Your account is pending approval. Please wait.";
            } else {
                // Set session variables
                $_SESSION['user_id']   = $user['id'];
                $_SESSION['user_name'] = $user['name'];
                $_SESSION['email']     = $user['email'];
                $_SESSION['role']      = $user['role'];
                $_SESSION['province']  = $user['province'];

                // Role-based redirect
                switch ($user['role']) {
                    case 'admin':           header("Location: ../dashboards/admin_dashboard.php"); break;
                    case 'ho_manager':      header("Location: ../dashboards/ho_dashboard.php"); break;
                    case 'rdc_staff':       header("Location: ../dashboards/rdc_dashboard.php"); break;
                    case 'logistics_staff': header("Location: ../dashboards/logistics_dashboard.php"); break;
                    case 'customer':        header("Location: ../dashboards/customer_dashboard.php"); break;
                    default:                header("Location: ../index.php");
                }
                exit();
            }
        } else {
            $error = "Invalid email or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ISDN - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        body {
            background-color: #0f1923;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', sans-serif;
        }
        .login-card {
            background: #fff;
            border-radius: 8px;
            width: 100%;
            max-width: 430px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.4);
            overflow: hidden;
        }
        .login-header {
            background: #1a3a5c;
            padding: 30px;
            text-align: center;
            color: #fff;
        }
        .login-header h2 {
            font-weight: 700;
            letter-spacing: 2px;
            margin: 0;
            font-size: 1.6rem;
        }
        .login-header p {
            margin: 5px 0 0;
            opacity: 0.75;
            font-size: 0.85rem;
        }
        .login-body {
            padding: 35px 35px 25px;
        }
        .form-label { font-weight: 600; font-size: 0.85rem; color: #444; }
        .form-control:focus { border-color: #1a3a5c; box-shadow: 0 0 0 0.2rem rgba(26,58,92,0.15); }
        .btn-login {
            background: #1a3a5c;
            border: none;
            color: #fff;
            width: 100%;
            padding: 11px;
            font-weight: 600;
            letter-spacing: 0.5px;
            border-radius: 5px;
        }
        .btn-login:hover { background: #14304e; color: #fff; }
        .login-footer {
            background: #f8f9fa;
            padding: 15px 35px;
            text-align: center;
            font-size: 0.85rem;
            color: #666;
            border-top: 1px solid #e9ecef;
        }
        .login-footer a { color: #1a3a5c; font-weight: 600; text-decoration: none; }
    </style>
</head>
<body>
<div class="login-card">
    <div class="login-header">
        <i class="bi bi-building" style="font-size:2rem;"></i>
        <h2>ISDN</h2>
        <p>Distribution Management System</p>
    </div>
    <div class="login-body">
        <h6 class="mb-4 text-muted">Sign in to your account</h6>

        <?php if ($error): ?>
            <div class="alert alert-danger py-2 small"><i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="mb-3">
                <label class="form-label">Email Address</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-envelope text-muted"></i></span>
                    <input type="email" name="email" class="form-control" placeholder="you@company.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
            </div>
            <div class="mb-4">
                <label class="form-label">Password</label>
                <div class="input-group">
                    <span class="input-group-text bg-light"><i class="bi bi-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                </div>
            </div>
            <button type="submit" class="btn btn-login">
                <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
            </button>
        </form>
    </div>
    <div class="login-footer">
        New customer? <a href="register.php">Create an account</a>
    </div>
</div>
</body>
</html>
