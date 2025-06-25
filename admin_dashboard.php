<?php
// admin_dashboard.php
session_start();
require 'db.php';
require 'includes/theme.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get theme for the page
$theme = getCurrentTheme();

// Fetch statistics
$stats = [
    'total_users' => 0,
    'total_teachers' => 0,
    'total_students' => 0,
    'total_classes' => 0
];

// User Growth (last 12 months)
$userGrowthLabels = [];
$userGrowthData = [];
// Group Growth (last 12 months)
$groupGrowthLabels = [];
$groupGrowthData = [];

// Popular Courses (top 5 by enrollment)
$popularCoursesLabels = [];
$popularCoursesData = [];

try {
    // Get user counts
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
    $stmt->execute();
    $stats['total_teachers'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $stmt->execute();
    $stats['total_students'] = $stmt->fetch()['count'];
    
    // Get class count
$stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `groups`");
$stmt->execute();
$stats['total_classes'] = $stmt->fetch()['count'];
    
    // User Growth: registrations per month (last 12 months)
    $stmt = $pdo->prepare("
        SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
        FROM users
        WHERE created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 MONTH), '%Y-%m-01')
        GROUP BY month
        ORDER BY month ASC
    ");
    $stmt->execute();
    $growth = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Fill missing months
    $months = [];
    for ($i = 11; $i >= 0; $i--) {
        $months[] = date('Y-m', strtotime("-{$i} months"));
    }
    $growthMap = array_column($growth, 'count', 'month');
    foreach ($months as $m) {
        $userGrowthLabels[] = date('M Y', strtotime($m));
        $userGrowthData[] = isset($growthMap[$m]) ? (int)$growthMap[$m] : 0;
    }

    // Group Growth: groups created per month (last 12 months)
$stmt = $pdo->prepare("
    SELECT DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count
    FROM `groups`
    WHERE created_at >= DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 11 MONTH), '%Y-%m-01')
    GROUP BY month
    ORDER BY month ASC
");
    $stmt->execute();
    $groupGrowth = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $groupGrowthMap = array_column($groupGrowth, 'count', 'month');
    foreach ($months as $m) {
        $groupGrowthLabels[] = date('M Y', strtotime($m));
        $groupGrowthData[] = isset($groupGrowthMap[$m]) ? (int)$groupGrowthMap[$m] : 0;
    }

    // Popular Courses: top 5 by student count
    $stmt = $pdo->prepare("
        SELECT c.class_name, COUNT(cs.student_id) as student_count
        FROM classes c
        LEFT JOIN class_students cs ON c.id = cs.class_id
        GROUP BY c.id
        ORDER BY student_count DESC, c.class_name ASC
        LIMIT 5
    ");
    $stmt->execute();
    $popular = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($popular as $row) {
        $popularCoursesLabels[] = $row['class_name'];
        $popularCoursesData[] = (int)$row['student_count'];
    }
    
    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name 
        FROM admin_activity_log a
        JOIN users u ON a.admin_id = u.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $activities = $stmt->fetchAll();
    
    // Get recent users
    $stmt = $pdo->prepare("
        SELECT * FROM users
        ORDER BY created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentUsers = $stmt->fetchAll();
    
    // Get recently added classes
    $stmt = $pdo->prepare("SELECT c.class_name, c.section, c.created_at, u.first_name, u.last_name FROM classes c LEFT JOIN users u ON c.teacher_id = u.id ORDER BY c.created_at DESC LIMIT 5");
    $stmt->execute();
    $recentClasses = $stmt->fetchAll();
    // Get recently added groups
    $stmt = $pdo->prepare("SELECT g.name, g.created_at, u.first_name, u.last_name FROM `groups` g LEFT JOIN users u ON g.created_by = u.id ORDER BY g.created_at DESC LIMIT 5");
    $stmt->execute();
    $recentGroups = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Admin Dashboard - LearnMate</title>
  <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
  />
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
  />
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
  </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
  <div class="app-container">
    <?php include 'includes/admin_sidebar.php'; ?>

    <main class="main-content">
      <!-- Mobile Header -->
      <header class="header">
        <h1 class="header-title">Admin Dashboard</h1>
        <div class="header-actions">
          <button class="header-btn">
            <i class="fas fa-search"></i>
          </button>
          <button class="header-btn">
            <i class="fas fa-bell"></i>
          </button>
        </div>
      </header>

      <!-- Stats Section -->
      <h2 class="section-title-lg">
        <i class="fas fa-tachometer-alt"></i>
        <span>System Overview</span>
      </h2>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value"><?php echo $stats['total_users']; ?></div>
          <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo $stats['total_teachers']; ?></div>
          <div class="stat-label">Teachers</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo $stats['total_students']; ?></div>
          <div class="stat-label">Students</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo $stats['total_classes']; ?></div>
          <div class="stat-label">Active Classes</div>
        </div>
      </div>

      <!-- Charts Section -->
      <div class="recent-container" style="margin-bottom: 32px;">
        <div class="recent-users" style="min-width: 0;">
          <h2 class="section-title-lg"><i class="fas fa-chart-line"></i> <span>User Growth</span></h2>
          <canvas id="userGrowthChart" height="180"></canvas>
        </div>
        <div class="recent-users" style="min-width: 0;">
          <h2 class="section-title-lg"><i class="fas fa-users"></i> <span>Group Growth</span></h2>
          <canvas id="groupGrowthChart" height="180"></canvas>
        </div>
      </div>

      <div class="recent-container">
        <div style="display: flex; gap: 20px; margin-bottom: 20px;">
          <div class="recent-users" style="flex: 1;">
            <h2 class="section-title-lg"><i class="fas fa-chalkboard"></i> <span>Recently Added Classes</span></h2>
            <?php if (empty($recentClasses)): ?>
              <div class="activity-item"><div class="activity-details"><div class="activity-title">No recent classes</div></div></div>
            <?php else: foreach ($recentClasses as $class): ?>
              <div class="activity-item">
                <div class="activity-icon"><i class="fas fa-chalkboard"></i></div>
                <div class="activity-details">
                  <div class="activity-title"><?php echo htmlspecialchars($class['class_name']); ?><?php if (!empty($class['section'])): ?> (<?php echo htmlspecialchars($class['section']); ?>)<?php endif; ?></div>
                  <div class="activity-meta">Created by <?php echo htmlspecialchars($class['first_name'] . ' ' . $class['last_name']); ?> • <?php echo date('M j, Y', strtotime($class['created_at'])); ?></div>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
          <div class="recent-users" style="flex: 1;">
            <h2 class="section-title-lg"><i class="fas fa-users"></i> <span>Recently Added Groups</span></h2>
            <?php if (empty($recentGroups)): ?>
              <div class="activity-item"><div class="activity-details"><div class="activity-title">No recent groups</div></div></div>
            <?php else: foreach ($recentGroups as $group): ?>
              <div class="activity-item">
                <div class="activity-icon"><i class="fas fa-users"></i></div>
                <div class="activity-details">
                  <div class="activity-title"><?php echo htmlspecialchars($group['name']); ?><?php if (!empty($group['category'])): ?> (<?php echo htmlspecialchars($group['category']); ?>)<?php endif; ?></div>
                  <div class="activity-meta">Created by <?php echo htmlspecialchars($group['first_name'] . ' ' . $group['last_name']); ?> • <?php echo date('M j, Y', strtotime($group['created_at'])); ?></div>
                </div>
              </div>
            <?php endforeach; endif; ?>
          </div>
        </div>
        <div class="recent-users">
          <h2 class="section-title-lg"><i class="fas fa-user"></i> <span>Recent Users</span></h2>
          <?php if (empty($recentUsers)): ?>
            <div class="activity-item"><div class="activity-details"><div class="activity-title">No recent users</div></div></div>
          <?php else: foreach ($recentUsers as $user): ?>
            <div class="activity-item">
              <div class="activity-icon"><i class="fas fa-user"></i></div>
              <div class="activity-details">
                <div class="activity-title"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></div>
                <div class="activity-meta"><?php echo htmlspecialchars($user['email']); ?> • <?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </main>
  </div>

  <!-- Bottom Navigation with Fixed FAB - Mobile Only -->
  <div class="bottom-nav-container">
    <nav class="bottom-nav">
      <a href="admin_dashboard.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php') ? ' active' : ''; ?>">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="admin_users.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_users.php') ? ' active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>Users</span>
      </a>
      <a href="logout.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'logout.php') ? ' active' : ''; ?>">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
      <a href="admin_classes.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_classes.php') ? ' active' : ''; ?>">
        <i class="fas fa-chalkboard"></i>
        <span>Classes</span>
      </a>
      <a href="admin_group.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_group.php') ? ' active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>Groups</span>
      </a>
      <a href="admin_logs.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_logs.php') ? ' active' : ''; ?>">
        <i class="fas fa-clipboard-list"></i>
        <span>Logs</span>
      </a>
    </nav>
  </div>

  <!-- Create User Modal -->
  <div
    id="createUserModal"
    style="display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.3); z-index: 1000; align-items: center; justify-content: center;"
  >
    <div
      style="
        background: #fff;
        padding: 32px;
        border-radius: 12px;
        min-width: 320px;
        max-width: 90vw;
        position: relative;
      "
    >
      <h2 style="margin-bottom: 16px;">Create New User</h2>
      <form method="POST" id="createUserForm">
        <div style="margin-bottom: 12px;">
          <input
            type="text"
            name="new_first_name"
            placeholder="First Name"
            required
            style="width: 100%; padding: 8px"
          />
        </div>
        <div style="margin-bottom: 12px;">
          <input
            type="text"
            name="new_last_name"
            placeholder="Last Name"
            required
            style="width: 100%; padding: 8px"
          />
        </div>
        <div style="margin-bottom: 12px;">
          <input
            type="email"
            name="new_email"
            placeholder="Email"
            required
            style="width: 100%; padding: 8px"
          />
        </div>
        <div style="margin-bottom: 12px;">
          <input
            type="password"
            name="new_password"
            placeholder="Password"
            required
            style="width: 100%; padding: 8px"
          />
        </div>
        <div style="margin-bottom: 12px;">
          <select name="new_role" required style="width: 100%; padding: 8px">
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div style="display: flex; gap: 8px">
          <button type="submit" class="action-btn" style="flex: 1">Create</button>
          <button
            type="button"
            id="closeCreateUserModal"
            class="action-btn secondary"
            style="flex: 1"
          >
            Cancel
          </button>
        </div>
      </form>
      <div id="createUserMsg" style="margin-top: 12px"></div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <script>
    // User Growth Data
    const userGrowthLabels = <?php echo json_encode($userGrowthLabels); ?>;
    const userGrowthData = <?php echo json_encode($userGrowthData); ?>;
    // Group Growth Data
    const groupGrowthLabels = <?php echo json_encode($groupGrowthLabels); ?>;
    const groupGrowthData = <?php echo json_encode($groupGrowthData); ?>;

    // User Growth Chart
    new Chart(document.getElementById("userGrowthChart").getContext("2d"), {
      type: "line",
      data: {
        labels: userGrowthLabels,
        datasets: [
          {
            label: "User Registrations",
            data: userGrowthData,
            fill: true,
            backgroundColor: "rgba(127,86,217,0.1)",
            borderColor: "#7F56D9",
            tension: 0.3,
            pointRadius: 3,
            pointBackgroundColor: "#7F56D9",
          },
        ],
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1 } },
        },
      },
    });

    // Group Growth Chart
    new Chart(document.getElementById("groupGrowthChart").getContext("2d"), {
      type: "bar",
      data: {
        labels: groupGrowthLabels,
        datasets: [
          {
            label: "Groups Created",
            data: groupGrowthData,
            backgroundColor: "#36BFFA",
            borderRadius: 8,
          },
        ],
      },
      options: {
        responsive: true,
        plugins: { legend: { display: false } },
        scales: {
          y: { beginAtZero: true, ticks: { stepSize: 1 } },
        },
      },
    });

    document.getElementById("openCreateUserModal").onclick = function () {
      document.getElementById("createUserModal").style.display = "flex";
    };
    document.getElementById("closeCreateUserModal").onclick = function () {
      document.getElementById("createUserModal").style.display = "none";
      document.getElementById("createUserMsg").innerHTML = "";
    };
    document.getElementById("createUserForm").onsubmit = async function (e) {
      e.preventDefault();
      const form = e.target;
      const data = new FormData(form);
      const res = await fetch("admin_dashboard.php", { method: "POST", body: data });
      const text = await res.text();
      const msg = text.match(/<div id="createUserMsg">([\s\S]*?)<\/div>/);
      document.getElementById("createUserMsg").innerHTML = msg ? msg[1] : "User created!";
      if (msg && msg[1].includes("success")) setTimeout(() => location.reload(), 1200);
    };
  </script>
</body>
</html>


