<?php
session_start();
define('BASE_URL', '../');
$page_title = "Access Denied";
include '../includes/header.php';
include '../includes/sidebar.php';
include '../includes/topbar.php';
?>
<div class="d-flex flex-column align-items-center justify-content-center" style="min-height:60vh;">
    <i class="bi bi-shield-lock" style="font-size:4rem;color:#dc3545;"></i>
    <h3 class="mt-3 text-danger">Access Denied</h3>
    <p class="text-muted">You don't have permission to view this page.</p>
    <a href="javascript:history.back()" class="btn btn-primary"><i class="bi bi-arrow-left me-1"></i>Go Back</a>
</div>
<?php include '../includes/footer.php'; ?>
