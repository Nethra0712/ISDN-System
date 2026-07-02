<?php
// ============================================
// includes/auth_check.php
// Included at top of every protected page
// ============================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// If not logged in, redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "auth/login.php");
    exit();
}

// Optional: restrict by role
// Usage: require_role(['admin', 'ho_manager']);
function require_role($allowed_roles) {
    if (!in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: " . BASE_URL . "includes/unauthorized.php");
        exit();
    }
}
