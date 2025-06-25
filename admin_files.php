<?php
session_start();
require 'db.php';
require 'includes/theme.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$theme = getCurrentTheme();

// Handle file deletion
$deleteMsg = '';
if (isset($_GET['delete'])) {
    $fileId = intval($_GET['delete']);
    // Fetch file info
    $stmt = $pdo->prepare("SELECT file_url FROM user_files WHERE id = ?");
    $stmt->execute([$fileId]);
    $file = $stmt->fetch();
    if ($file) {
        // Delete file from disk
        if (file_exists($file['file_url'])) {
            unlink($file['file_url']);
        }
        // Delete from DB
        $stmt = $pdo->prepare("DELETE FROM user_files WHERE id = ?");
        $stmt->execute([$fileId]);
        $deleteMsg = '<div class="alert alert-success">File deleted successfully.</div>';
    } else {
        $deleteMsg = '<div class="alert alert-error">File not found.</div>';
    }
}

// Auto-scan and import files from folders if ?scan=1 is present
$scanMsg = '';
if (isset($_GET['scan']) && $_GET['scan'] == '1') {
    $folders = [
        'student_flashcard/uploads',
        'demo0/uploads'
    ];
    $added = 0;
    foreach ($folders as $folder) {
        if (is_dir($folder)) {
            foreach (glob($folder . '/*.pdf') as $filePath) {
                $fileName = basename($filePath);
                // Check if already in DB
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM user_files WHERE file_url = ?");
                $stmt->execute([$filePath]);
                if ($stmt->fetchColumn() == 0) {
                    // Default to user_id=1 if not known
                    $stmt = $pdo->prepare("INSERT INTO user_files (user_id, file_name, file_url) VALUES (?, ?, ?)");
                    $stmt->execute([1, $fileName, $filePath]);
                    $added++;
                }
            }
        }
    }
    $scanMsg = '<div class="alert alert-success">Scan complete. ' . $added . ' new file(s) added.</div>';
}

// Fetch all files with user info
$files = $pdo->query("SELECT uf.id, uf.file_name, uf.file_url, uf.uploaded_at, u.first_name, u.last_name FROM user_files uf JOIN users u ON uf.user_id = u.id ORDER BY uf.uploaded_at DESC")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>File Management - LearnMate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/theme.css">
  <style>
    <link rel="stylesheet" href="css/theme.css" />
  <style>
    :root {
      --primary: #7F56D9;
      --primary-light: #9E77ED;
      --primary-dark: #6941C6;
      --secondary: #36BFFA;
      --success: #32D583;
      --warning: #FDB022;
      --danger: #F97066;
      --text-dark: #101828;
      --text-medium: #475467;
      --text-light: #98A2B3;
      --bg-light: #F9FAFB;
      --bg-white: #FFFFFF;
      --border-light: #EAECF0;
      --shadow-xs: 0 1px 2px rgba(16, 24, 40, 0.05);
      --shadow-sm: 0 1px 3px rgba(16, 24, 40, 0.1),
        0 1px 2px rgba(16, 24, 40, 0.06);
      --shadow-md: 0 4px 6px -1px rgba(16, 24, 40, 0.1),
        0 2px 4px -1px rgba(16, 24, 40, 0.06);
      --shadow-lg: 0 10px 15px -3px rgba(16, 24, 40, 0.1),
        0 4px 6px -2px rgba(16, 24, 40, 0.05);
      --shadow-xl: 0 20px 25px -5px rgba(16, 24, 40, 0.1),
        0 10px 10px -5px rgba(16, 24, 40, 0.04);
      --radius-sm: 6px;
      --radius-md: 8px;
      --radius-lg: 12px;
      --radius-xl: 16px;
      --radius-full: 9999px;
      --space-xs: 4px;
      --space-sm: 8px;
      --space-md: 16px;
      --space-lg: 24px;
      --space-xl: 32px;
      --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: "Inter", sans-serif;
    }

    body {
      background-color: var(--bg-light);
      color: var(--text-dark);
      line-height: 1.5;
    }

    /* App Container - Mobile First */
    .app-container {
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar - Desktop */
    .sidebar {
      display: none;
      width: 280px;
      min-width: 280px;
      height: 100vh;
      background-color: var(--bg-white);
      border-right: 1px solid var(--border-light);
      padding: var(--space-xl);
      position: sticky;
      top: 0;
      overflow-y: auto;
      z-index: 10;
    }

    .sidebar-header {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      margin-bottom: var(--space-xl);
      padding-bottom: var(--space-md);
      border-bottom: 1px solid var(--border-light);
    }

    .logo {
      width: 32px;
      height: 32px;
      background: linear-gradient(
        135deg,
        var(--primary) 0%,
        var(--primary-light) 100%
      );
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 600;
    }

    .app-name {
      font-weight: 600;
      font-size: 18px;
      color: var(--text-dark);
    }

    .nav-section {
      margin-bottom: var(--space-xl);
    }

    .section-title {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-light);
      margin-bottom: var(--space-sm);
      font-weight: 600;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-xs);
      text-decoration: none;
      color: var(--text-medium);
      font-weight: 500;
      transition: var(--transition);
    }

    .nav-item:hover {
      background-color: #f9f5ff;
      color: var(--primary-dark);
    }

    .nav-item.active {
      background-color: #f9f5ff;
      color: var(--primary-dark);
      font-weight: 600;
    }

    .nav-item i {
      width: 20px;
      text-align: center;
    }

    .admin-tools-card {
      background-color: #f0f9ff;
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      margin-top: var(--space-xl);
    }

    .card-title {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: var(--space-md);
      color: var(--primary-dark);
    }

    .action-btn {
      width: 100%;
      padding: var(--space-sm);
      border-radius: var(--radius-md);
      border: none;
      background-color: var(--primary);
      color: white;
      font-weight: 500;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: var(--space-sm);
      cursor: pointer;
      transition: var(--transition);
      margin-bottom: var(--space-sm);
    }

    .action-btn:hover {
      background-color: var(--primary-dark);
      transform: translateY(-1px);
    }

    .action-btn.secondary {
      background-color: white;
      color: var(--primary);
      border: 1px solid var(--border-light);
    }

    .action-btn.secondary:hover {
      background-color: #f9f5ff;
      color: var(--primary-dark);
    }

    /* Main Content */
    .main-content {
      flex: 1;
      padding: var(--space-md);
      position: relative;
      background-color: var(--bg-light);
      width: 100%;
    }

    /* Header - Mobile */
    .header {
      background-color: var(--bg-white);
      padding: var(--space-md);
      position: sticky;
      top: 0;
      z-index: 10;
      box-shadow: var(--shadow-sm);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header-title {
      font-weight: 600;
      font-size: 18px;
    }

    .header-actions {
      display: flex;
      gap: var(--space-sm);
    }

    .header-btn {
      width: 36px;
      height: 36px;
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--bg-light);
      border: none;
      color: var(--text-medium);
      cursor: pointer;
    }

    /* Stats Section */
    .stats-grid {
      display: grid;
      gap: var(--space-sm);
      margin-bottom: var(--space-lg);
    }

    .stat-card {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      box-shadow: var(--shadow-sm);
    }

    .stat-value {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: var(--space-xs);
      color: var(--primary);
    }

    .stat-label {
      font-size: 12px;
      color: var(--text-light);
    }

    /* Recent Users Section */
    .recent-users {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: var(--space-md);
      margin-bottom: var(--space-lg);
    }

    .user-item {
      display: flex;
      align-items: center;
      padding: var(--space-sm) 0;
      border-bottom: 1px solid var(--border-light);
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #f9f5ff;
      display: flex;
      align-items: center;
      justify-content: center;
      margin-right: var(--space-md);
      color: var(--primary);
      font-weight: 600;
    }

    .user-details {
      flex: 1;
    }

    .user-name {
      font-weight: 500;
      margin-bottom: 2px;
    }

    .user-role {
      font-size: 12px;
      color: var(--text-light);
      display: flex;
      align-items: center;
    }

    .user-role .badge {
      margin-left: var(--space-sm);
    }

    .badge {
      display: inline-block;
      padding: 2px 6px;
      border-radius: var(--radius-full);
      font-size: 10px;
      font-weight: 600;
      background-color: var(--primary-light);
      color: white;
    }

    .badge.student {
      background-color: var(--secondary);
    }

    .badge.teacher {
      background-color: var(--success);
    }

    .badge.admin {
      background-color: var(--danger);
    }

    /* Recent Activity */
    .activity-item {
      display: flex;
      align-items: flex-start;
      gap: var(--space-md);
      padding: var(--space-sm) 0;
      border-bottom: 1px solid var(--border-light);
    }

    .activity-icon {
      width: 32px;
      height: 32px;
      border-radius: var(--radius-full);
      background-color: #f9f5ff;
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .activity-details {
      flex: 1;
    }

    .activity-title {
      font-weight: 500;
      margin-bottom: 2px;
    }

    .activity-meta {
      font-size: 12px;
      color: var(--text-light);
    }

    /* Bottom Navigation - Mobile */
    .bottom-nav-container {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 20;
    }

    .bottom-nav {
      background-color: var(--bg-white);
      display: flex;
      justify-content: space-around;
      align-items: center;
      padding: var(--space-sm) 0;
      box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
      position: relative;
      height: 60px;
    }

    .nav-item-mobile {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-decoration: none;
      color: var(--text-light);
      font-size: 10px;
      gap: 4px;
      z-index: 1;
      width: 25%;
    }

    .nav-item-mobile i {
      font-size: 20px;
    }

    .nav-item-mobile.active {
      color: var(--primary);
    }

    /* Floating Action Button integrated with nav */
    .fab-container {
      position: absolute;
      left: 50%;
      top: -20px;
      transform: translateX(-50%);
      width: 56px;
      height: 56px;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .fab {
      width: 56px;
      height: 56px;
      background: linear-gradient(
        135deg,
        var(--primary) 0%,
        var(--primary-light) 100%
      );
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      box-shadow: var(--shadow-lg);
      border: none;
      cursor: pointer;
      z-index: 2;
    }

    .fab i {
      font-size: 24px;
    }

    /* Responsive Design */
    @media (min-width: 640px) {
      /* Tablet styles */
      .main-content {
        padding: var(--space-lg);
      }

      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (min-width: 768px) {
      /* Larger tablet styles */
      body {
        padding-bottom: 0;
      }

      .bottom-nav-container {
        display: none;
      }

      .sidebar {
        display: flex;
        flex-direction: column;
      }

      .main-content {
        width: calc(100% - 280px);
        padding: var(--space-xl);
      }

      .header {
        display: none;
      }

      .stats-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    @media (min-width: 1024px) {
      /* Desktop styles */
      .recent-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-lg);
      }
    }

    .main-content { flex: 1; padding: var(--space-md); position: relative; background-color: var(--bg-light); width: 100%; }
    .header { background-color: var(--bg-white); padding: var(--space-md); position: sticky; top: 0; z-index: 10; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; }
    .header-title { font-weight: 600; font-size: 18px; }
    .recent-container { margin-bottom: 32px; display: grid; grid-template-columns: 1fr 1fr; gap: var(--space-lg); }
    .recent-users { background-color: var(--bg-white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--space-md); margin-bottom: var(--space-lg); }
    .section-title-lg { font-size: 20px; font-weight: 600; margin-bottom: var(--space-md); display: flex; align-items: center; gap: var(--space-sm); }
    .filter-form { display: flex; gap: var(--space-md); margin-bottom: 0; }
    .filter-select { padding: var(--space-sm) var(--space-md); border-radius: var(--radius-md); border: 1px solid var(--border-light); font-size: 14px; background-color: white; }
    .upload-form label { font-weight: 500; margin-bottom: var(--space-xs); display: block; }
    .upload-form input, .upload-form select { width: 100%; margin-bottom: var(--space-md); padding: var(--space-sm) var(--space-md); border-radius: var(--radius-md); border: 1px solid var(--border-light); font-size: 14px; }
    .upload-form button { background: var(--primary); color: #fff; border: none; border-radius: var(--radius-md); padding: var(--space-sm) var(--space-md); font-weight: 500; cursor: pointer; }
    .files-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); }
    .files-table th, .files-table td { padding: var(--space-md); border-bottom: 1px solid var(--border-light); text-align: left; font-size: 14px; }
    .files-table th { background: #f9f5ff; color: var(--primary-dark); font-weight: 600; }
    .files-table tr:last-child td { border-bottom: none; }
    .action-btn { padding: 6px 12px; border-radius: var(--radius-sm); border: none; font-weight: 500; font-size: 13px; cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: var(--space-xs); }
    .action-btn.view { background: var(--primary-light); color: #fff; }
    .action-btn.delete { background: #FEF3F2; color: var(--danger); }
    .action-btn.delete:hover { background: #FEE4E2; }
    .alert { padding: var(--space-sm) var(--space-md); border-radius: var(--radius-md); margin-bottom: var(--space-lg); font-size: 14px; display: flex; align-items: center; gap: var(--space-sm); }
    .alert-success { background-color: #ECFDF3; color: #027A48; border: 1px solid #ABEFC6; }
    .alert-error { background-color: #FEF3F2; color: #B42318; border: 1px solid #FDA29B; }
    @media (min-width: 640px) { .main-content { padding: var(--space-lg); } .recent-container { grid-template-columns: 1fr 1fr; } }
    @media (min-width: 768px) { .main-content { width: calc(100% - 280px); padding: var(--space-xl); } .recent-container { grid-template-columns: 1fr 1fr; } }
    @media (min-width: 1024px) { .recent-container { grid-template-columns: 1fr 1fr; gap: var(--space-lg); } }
  </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
  <div class="app-container">
    <!-- Sidebar - Desktop -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo">A</div>
        <div class="app-name">LearnMate</div>
      </div>
      <div class="nav-section">
        <div class="section-title">Menu</div>
        <a href="teacher_dashboard.php" class="nav-item">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>

        <div class="dropdown">
          <div class="dropdown-toggle" onclick="toggleDropdown(this)">
            <div style="display: flex; align-items: center; gap: var(--space-sm);">
              <i class="fas fa-chalkboard-teacher"></i>
              <span>My Classes</span>
            </div>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="dropdown-menu">
            <?php if (empty($classes)): ?>
              <div class="dropdown-item" style="padding: var(--space-sm);">
                No classes yet
              </div>
            <?php else: ?>
              <?php foreach ($classes as $class): ?>
                <a href="class_details.php?id=<?php echo $class['id']; ?>" class="dropdown-item">
                  <div style="display: flex; align-items: center;">
                    <div class="profile-initial">
                      <?php 
                        $words = explode(' ', $class['class_name']);
                        $initials = '';
                        foreach ($words as $word) {
                          $initials .= strtoupper(substr($word, 0, 1));
                          if (strlen($initials) >= 2) break;
                        }
                        echo $initials;
                      ?>
                    </div>
                    <div>
                      <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                      <div class="class-section"><?php echo htmlspecialchars($class['section']); ?></div>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
            <a href="create_class.php" class="dropdown-item" style="margin-top: var(--space-sm); color: var(--primary);">
              <i class="fas fa-plus"></i> Create New Class
            </a>
          </div>
        </div>
        
        <a href="teacher_group.php" class="nav-item">
          <i class="fas fa-users"></i>
          <span>Groups</span>
        </a>
      </div>
      
      <div class="nav-section">
        <div class="section-title">Settings</div>
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
    <!-- Main Content Area -->
    <main class="main-content">
      <header class="header">
        <h1 class="header-title"><i class="fas fa-file-alt"></i> File Management</h1>
      </header>
      <div class="recent-users">
        <h2 class="section-title-lg"><i class="fas fa-folder-open"></i> Uploaded Files</h2>
        <a href="admin_files.php?scan=1" class="action-btn view" style="margin-bottom:16px;"><i class="fas fa-sync"></i> Scan for New Files</a>
        <?php echo $scanMsg; ?>
        <?php echo $deleteMsg; ?>
        <table class="files-table">
          <thead>
            <tr>
              <th>File Name</th>
              <th>User</th>
              <th>Uploaded</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($files)): ?>
              <tr><td colspan="4" style="text-align:center; color:var(--text-light);">No files found.</td></tr>
            <?php else: foreach ($files as $file): ?>
              <tr>
                <td><?php echo htmlspecialchars($file['file_name']); ?></td>
                <td><?php echo htmlspecialchars($file['first_name'] . ' ' . $file['last_name']); ?></td>
                <td><?php echo htmlspecialchars($file['uploaded_at']); ?></td>
                <td>
                  <a href="<?php echo htmlspecialchars($file['file_url']); ?>" target="_blank" class="action-btn view"><i class="fas fa-eye"></i> View</a>
                  <a href="admin_files.php?delete=<?php echo $file['id']; ?>" class="action-btn delete" onclick="return confirm('Delete this file?');"><i class="fas fa-trash-alt"></i> Delete</a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html> 