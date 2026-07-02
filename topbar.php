<?php
// ============================================
// includes/topbar.php
// Top navigation bar (included after sidebar)
// ============================================
?>
<!-- Topbar -->
<div id="topbar">
    <span class="topbar-title">
        <i class="bi bi-chevron-right me-1" style="font-size:.75rem;"></i>
        <?= $page_title ?? 'Dashboard' ?>
    </span>
    <div class="d-flex align-items-center gap-3">
        <span class="role-badge"><?= str_replace('_', ' ', $_SESSION['role']) ?></span>
        <div class="d-flex align-items-center gap-2">
            <div class="user-badge"><?= strtoupper(substr($_SESSION['user_name'], 0, 1)) ?></div>
            <span style="font-size:.875rem;font-weight:600;color:#333;"><?= htmlspecialchars($_SESSION['user_name']) ?></span>
        </div>
        <a href="<?= BASE_URL ?>auth/logout.php" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-box-arrow-right"></i>
        </a>
    </div>
</div>
<!-- Content Wrapper -->
<div id="content">
