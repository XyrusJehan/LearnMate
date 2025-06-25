<?php
// Get current page for active state
$current_page = basename($_SERVER['PHP_SELF']);

// Get count of pending reports
$stmt = $pdo->query("SELECT COUNT(*) FROM reports WHERE status = 'pending'");
$pendingCount = $stmt->fetchColumn();
?>
<aside class="sidebar">
    <div class="sidebar-header">
        <div class="logo">
            <span class="logo-text">LM</span>
        </div>
        <div class="app-name">LearnMate</div>
    </div>
    
    <div class="nav-section">
        <div class="section-title">Menu</div>
        <a href="admin_dashboard.php" class="nav-item<?php echo ($current_page == 'admin_dashboard.php') ? ' active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="admin_users.php" class="nav-item<?php echo ($current_page == 'admin_users.php') ? ' active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>User Management</span>
        </a>
        <a href="admin_classes.php" class="nav-item<?php echo ($current_page == 'admin_classes.php') ? ' active' : ''; ?>">
            <i class="fas fa-chalkboard"></i>
            <span>Class Management</span>
        </a>
        <a href="admin_group.php" class="nav-item<?php echo ($current_page == 'admin_group.php') ? ' active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Group Management</span>
        </a>
        <a href="admin_analytics.php" class="nav-item<?php echo ($current_page == 'admin_analytics.php') ? ' active' : ''; ?>">
            <i class="fas fa-chart-line"></i>
            <span>System Analytics</span>
        </a>
        <a href="admin_feedback.php" class="nav-item<?php echo ($current_page == 'admin_feedback.php') ? ' active' : ''; ?>">
            <i class="fas fa-comment-alt"></i>
            <span>User Feedback</span>
            <?php if ($pendingCount > 0): ?>
            <span class="badge" style="background-color: var(--warning); margin-left: auto;"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
        </a>
    </div>
    
  <div class="nav-section">
    <div class="section-title">System</div>
    <a href="admin_approval.php" class="nav-item<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_approval.php') ? ' active' : ''; ?>">
      <i class="fas fa-user-check"></i>
      <span>User Approvals</span>
    </a>
        <a href="admin_logs.php" class="nav-item<?php echo ($current_page == 'admin_logs.php') ? ' active' : ''; ?>">
            <i class="fas fa-clipboard-list"></i>
            <span>Activity Logs</span>
        </a>
    </div>

    <div class="nav-section" style="margin-top: auto;">
        <a href="settings.php" class="nav-item">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
        </a>
        <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
        </a>
    </div>
</aside> 