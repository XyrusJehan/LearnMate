<?php
// MUST be the very first thing in the file
session_start();

// admin_approval.php
require 'db.php';
require 'approval_mailer.php';
require 'includes/theme.php';

// Check if user is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get theme for the page
$theme = getCurrentTheme();

// Handle approval/rejection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $userId = $_POST['user_id'];
    $action = $_POST['action'];
    
    try {
        // Get user details
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        
        if (!$user) {
            throw new Exception('User not found');
        }
        
        // Initialize mailer
        $mailer = new ApprovalMailer();
        
        if ($action === 'approve') {
            $role = $_POST['role']; // 'student' or 'teacher'
            
            // Update user role and verification status
            $stmt = $pdo->prepare("UPDATE users SET role = ?, is_verified = 1 WHERE id = ?");
            $stmt->execute([$role, $userId]);
            
            // Send approval email
            if (!$mailer->sendApprovalNotification($user['email'], $user['first_name'], $role)) {
                throw new Exception('Failed to send approval email');
            }
            
            $_SESSION['success'] = "User approved successfully as {$role}";
        } elseif ($action === 'reject') {
            // Delete user
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            
            // Delete ID file if exists
            if ($user['id_file_path'] && file_exists($user['id_file_path'])) {
                unlink($user['id_file_path']);
            }
            
            // Send rejection email
            if (!$mailer->sendRejectionNotification($user['email'], $user['first_name'])) {
                throw new Exception('Failed to send rejection email');
            }
            
            $_SESSION['success'] = "User rejected successfully";
        }
        
        header('Location: admin_approval.php');
        exit();
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
        header('Location: admin_approval.php');
        exit();
    }
}

// Get pending users
$stmt = $pdo->prepare("SELECT * FROM users WHERE role = 'pending' ORDER BY created_at DESC");
$stmt->execute();
$pendingUsers = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>User Approvals - LearnMate</title>
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

    /* Recent Users Section */
    .recent-users {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: var(--space-md);
      margin-bottom: var(--space-lg);
    }

    .section-title-lg {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: var(--space-md);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .section-title-lg i {
      color: var(--primary);
    }

    /* Table styles */
    .table-responsive {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: var(--space-sm) var(--space-md);
      text-align: left;
      border-bottom: 1px solid var(--border-light);
    }

    th {
      font-weight: 600;
      font-size: 14px;
      color: var(--text-medium);
    }

    /* Alert styles */
    .alert {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-md);
    }

    .alert-success {
      background-color: rgba(50, 213, 131, 0.1);
      color: var(--success);
      border: 1px solid var(--success);
    }

    .alert-danger {
      background-color: rgba(249, 112, 102, 0.1);
      color: var(--danger);
      border: 1px solid var(--danger);
    }

    .alert-info {
      background-color: rgba(54, 191, 250, 0.1);
      color: var(--secondary);
      border: 1px solid var(--secondary);
    }

    /* Form controls */
    .form-select {
      padding: var(--space-sm);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      background-color: var(--bg-white);
      color: var(--text-dark);
      font-size: 14px;
      width: 100%;
    }

    .btn {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      font-weight: 500;
      font-size: 14px;
      cursor: pointer;
      transition: var(--transition);
      border: none;
    }

    .btn-sm {
      padding: var(--space-xs) var(--space-sm);
      font-size: 13px;
    }

    .btn-success {
      background-color: var(--success);
      color: white;
    }

    .btn-danger {
      background-color: var(--danger);
      color: white;
    }

    .btn-success:hover, .btn-danger:hover {
      opacity: 0.9;
      transform: translateY(-1px);
    }

    /* Back button */
    .back-btn {
      display: inline-flex;
      align-items: center;
      gap: var(--space-sm);
      margin-bottom: var(--space-md);
      color: var(--primary);
      text-decoration: none;
      font-weight: 500;
    }

    .back-btn:hover {
      color: var(--primary-dark);
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

    /* Responsive Design */
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
    }
  </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
   <div class="app-container">
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
      <!-- Mobile Header -->
      <header class="header">
        <h1 class="header-title">User Approvals</h1>
        <div class="header-actions">
          <button class="header-btn">
            <i class="fas fa-search"></i>
          </button>
          <button class="header-btn">
            <i class="fas fa-bell"></i>
          </button>
        </div>
      </header>



      <!-- Content Section -->
      <h2 class="section-title-lg">
        <i class="fas fa-user-check"></i>
        <span>Pending User Approvals</span>
      </h2>

      <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></div>
      <?php endif; ?>
      
      <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></div>
      <?php endif; ?>
      
      <?php if (empty($pendingUsers)): ?>
        <div class="alert alert-info">No pending user approvals</div>
      <?php else: ?>
        <div class="recent-users">
          <div class="table-responsive">
            <table>
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>ID Proof</th>
                  <th>Registered</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($pendingUsers as $user): ?>
                <tr>
                  <td><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></td>
                  <td><?php echo htmlspecialchars($user['email']); ?></td>
                  <td>
                    <?php if ($user['id_file_path']): ?>
                      <a href="<?php echo htmlspecialchars($user['id_file_path']); ?>" target="_blank">View ID</a>
                    <?php else: ?>
                      No ID uploaded
                    <?php endif; ?>
                  </td>
                  <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                  <td>
                    <form method="POST" style="display: flex; gap: var(--space-sm); align-items: center;">
                      <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                      <select name="role" class="form-select form-select-sm" required>
                        <option value="student">Student</option>
                        <option value="teacher">Teacher</option>
                      </select>
                      <button type="submit" name="action" value="approve" class="btn btn-success btn-sm">Approve</button>
                      <button type="submit" name="action" value="reject" class="btn btn-danger btn-sm">Reject</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
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
      <a href="admin_approval.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_approval.php') ? ' active' : ''; ?>">
        <i class="fas fa-user-check"></i>
        <span>Approvals</span>
      </a>
    </nav>
  </div>
</body>
</html>