<?php
// class_details.php
session_start();
require 'db.php';
require 'includes/theme.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

// Get theme for the page
$theme = getCurrentTheme();

// Get class ID from URL
$classId = isset($_GET['id']) ? $_GET['id'] : null;
if (!$classId) {
    header('Location: teacher_dashboard.php');
    exit();
}

// Fetch class details
$class = [];
$students = [];
$assignments = [];
$recentActivities = [];

try {
    // Get class information
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
    $stmt->execute([$classId, $_SESSION['user_id']]);
    $class = $stmt->fetch();

    if (!$class) {
        header('Location: teacher_dashboard.php');
        exit();
    }

    // Get students in this class
    $stmt = $pdo->prepare("
        SELECT u.id, u.username, u.email, cs.joined_at 
        FROM class_students cs
        JOIN users u ON cs.student_id = u.id
        WHERE cs.class_id = ?
        ORDER BY u.username
    ");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();

    // Get assignments for this class
    $stmt = $pdo->prepare("
        SELECT * FROM assignments 
        WHERE class_id = ?
        ORDER BY due_date DESC
    ");
    $stmt->execute([$classId]);
    $assignments = $stmt->fetchAll();

    // Get recent activities for this class
    $stmt = $pdo->prepare("
        SELECT a.*, u.username as student_name 
        FROM activities a
        JOIN users u ON a.student_id = u.id
        WHERE a.group_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$classId]);
    $recentActivities = $stmt->fetchAll();

    // Get performance statistics
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_students,
               (SELECT COUNT(*) FROM assignments WHERE class_id = ?) as total_assignments,
               (SELECT AVG(score) FROM assignment_submissions WHERE assignment_id IN 
                   (SELECT id FROM assignments WHERE class_id = ?)) as avg_score
        FROM class_students
        WHERE class_id = ?
    ");
    $stmt->execute([$classId, $classId, $classId]);
    $stats = $stmt->fetch();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Helper function to display time ago
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );
    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($class['class_name']); ?> - LearnMate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/theme.css">
  <style>
    :root {
      --primary: #7F56D9;
      --primary-light: #9E77ED;
      --primary-dark: #6941C6;
      --primary-darker: #53389E;
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
      --shadow-sm: 0 1px 3px rgba(16, 24, 40, 0.1), 0 1px 2px rgba(16, 24, 40, 0.06);
      --shadow-md: 0 4px 6px -1px rgba(16, 24, 40, 0.1), 0 2px 4px -1px rgba(16, 24, 40, 0.06);
      --shadow-lg: 0 10px 15px -3px rgba(16, 24, 40, 0.1), 0 4px 6px -2px rgba(16, 24, 40, 0.05);
      --shadow-xl: 0 20px 25px -5px rgba(16, 24, 40, 0.1), 0 10px 10px -5px rgba(16, 24, 40, 0.04);
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
      font-family: 'Inter', sans-serif;
    }

    body {
      background-color: var(--bg-light);
      color: var(--text-dark);
      line-height: 1.5;
    }

    .app-container {
      display: flex;
      min-height: 100vh;
    }

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
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
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
      background-color: var(--primary-darker);
      color: white;
    }

    .nav-item.active {
      background-color: var(--primary-dark);
      color: white;
      font-weight: 600;
    }

    .nav-item i {
      width: 20px;
      text-align: center;
      color: inherit;
    }

    /* Dropdown styles */
    .dropdown {
      position: relative;
    }
    
    .dropdown-toggle {
      display: flex;
      align-items: center;
      justify-content: space-between;
      width: 100%;
      cursor: pointer;
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-xs);
      color: var(--text-medium);
      font-weight: 500;
      transition: var(--transition);
    }
    
    .dropdown-toggle:hover {
      background-color: var(--primary-darker);
      color: white;
    }
    
    .dropdown-toggle.active {
      background-color: var(--primary-dark);
      color: white;
      font-weight: 600;
    }
    
    .dropdown-toggle i.fa-chevron-down {
      transition: transform 0.3s ease;
      font-size: 12px;
    }
    
    .dropdown-toggle.active i.fa-chevron-down {
      transform: rotate(180deg);
    }
    
    .dropdown-menu {
      max-height: 0;
      overflow: hidden;
      transition: max-height 0.3s ease;
      padding-left: var(--space-md);
    }
    
    .dropdown-menu.show {
      max-height: 500px;
    }
    
    .dropdown-item {
      display: flex;
      align-items: center;
      padding: var(--space-xs) var(--space-sm);
      text-decoration: none;
      color: var(--text-medium);
      transition: var(--transition);
      border-radius: var(--radius-sm);
      margin-bottom: 2px;
    }
    
    .dropdown-item:hover {
      background-color: rgba(0, 0, 0, 0.05);
      color: var(--primary-dark);
    }
    
    .dropdown-item .profile-initial {
      transition: var(--transition);
    }
    
    .dropdown-item:hover .profile-initial {
      background-color: var(--primary-dark);
    }
    
    .class-name {
      font-weight: 600;
      font-size: 14px;
      color: var(--text-dark);
    }
    
    .class-section {
      font-size: 12px;
      color: var(--text-light);
      margin-top: 2px;
    }

    /* Profile Initial Styles */
    .profile-initial {
      width: 24px;
      height: 24px;
      border-radius: var(--radius-full);
      background-color: var(--primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 10px;
      font-weight: 600;
      margin-right: var(--space-sm);
      flex-shrink: 0;
    }

    .teacher-tools-card {
      background-color: #F9F5FF;
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
      text-decoration: none;
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
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }

    .main-content {
      flex: 1;
      padding: var(--space-md);
      position: relative;
      background-color: var(--bg-light);
      width: 100%;
    }

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

    .section-title-lg {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: var(--space-md);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .section-title-lg i {
      color: var(--primary);
    }

    /* Class Header */
    .class-header {
      display: flex;
      align-items: center;
      gap: var(--space-md);
      margin-bottom: var(--space-lg);
    }

    .class-header-image {
      width: 80px;
      height: 80px;
      border-radius: var(--radius-lg);
      background-color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 32px;
      font-weight: 600;
    }

    .class-header-info {
      flex: 1;
    }

    .class-header-title {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: var(--space-xs);
    }

    .class-header-meta {
      display: flex;
      gap: var(--space-md);
      color: var(--text-light);
      font-size: 14px;
    }

    /* Tabs */
    .tabs {
      display: flex;
      border-bottom: 1px solid var(--border-light);
      margin-bottom: var(--space-lg);
    }

    .tab {
      padding: var(--space-sm) var(--space-md);
      cursor: pointer;
      font-weight: 500;
      color: var(--text-medium);
      border-bottom: 2px solid transparent;
      transition: var(--transition);
    }

    .tab.active {
      color: var(--primary);
      border-bottom-color: var(--primary);
    }

    .tab:hover:not(.active) {
      color: var(--primary-dark);
    }

    /* Table styles */
    .table-container {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }

    .table {
      width: 100%;
      border-collapse: collapse;
    }

    .table th {
      text-align: left;
      padding: var(--space-sm) var(--space-md);
      background-color: var(--bg-light);
      font-weight: 600;
      font-size: 12px;
      color: var(--text-light);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .table td {
      padding: var(--space-sm) var(--space-md);
      border-bottom: 1px solid var(--border-light);
      vertical-align: middle;
    }

    .table tr:last-child td {
      border-bottom: none;
    }

    .table tr:hover td {
      background-color: rgba(0, 0, 0, 0.02);
    }

    .student-avatar {
      width: 32px;
      height: 32px;
      border-radius: var(--radius-full);
      background-color: var(--primary-light);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 12px;
      font-weight: 600;
    }

    .badge {
      display: inline-block;
      padding: 2px 8px;
      border-radius: var(--radius-full);
      font-size: 12px;
      font-weight: 500;
    }

    .badge-primary {
      background-color: #F9F5FF;
      color: var(--primary);
    }

    .badge-success {
      background-color: #ECFDF3;
      color: #027A48;
    }

    .badge-warning {
      background-color: #FFFAEB;
      color: #B54708;
    }

    .btn {
      padding: 6px 12px;
      border-radius: var(--radius-sm);
      font-size: 12px;
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
      border: none;
    }

    .btn-primary {
      background-color: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
    }

    .btn-outline {
      background-color: transparent;
      border: 1px solid var(--border-light);
      color: var(--text-medium);
    }

    .btn-outline:hover {
      background-color: var(--bg-light);
    }

    /* Activity item */
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
      background-color: #F9F5FF;
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

    /* Bottom navigation for mobile */
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
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      box-shadow: var(--shadow-lg);
      border: none;
      cursor: pointer;
      z-index: 2;
      text-decoration: none;
    }

    .fab i {
      font-size: 24px;
    }

    /* Responsive styles */
    @media (min-width: 640px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (min-width: 768px) {
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
      .class-header-image {
        width: 100px;
        height: 100px;
        font-size: 40px;
      }
    }
  </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
  <div class="app-container">
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo">LM</div>
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
      
      <div class="teacher-tools-card">
        <div class="card-title">Teacher Tools</div>
        <a href="create_assignment.php?class_id=<?php echo $classId; ?>" class="action-btn">
          <i class="fas fa-plus"></i>
          <span>Create Assignment</span>
        </a>
        <button class="action-btn secondary">
          <i class="fas fa-share-alt"></i>
          <span>Invite Students</span>
        </button>
      </div>
    </aside>

    <main class="main-content">
      <header class="header">
        <h1 class="header-title"><?php echo htmlspecialchars($class['class_name']); ?></h1>
        <div class="header-actions">
          <button class="header-btn" onclick="window.history.back()">
            <i class="fas fa-arrow-left"></i>
          </button>
          <button class="header-btn">
            <i class="fas fa-ellipsis-v"></i>
          </button>
        </div>
      </header>

      <div class="class-header">
        <div class="class-header-image">
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
        <div class="class-header-info">
          <h1 class="class-header-title"><?php echo htmlspecialchars($class['class_name']); ?></h1>
          <div class="class-header-meta">
            <span><i class="fas fa-users"></i> <?php echo count($students); ?> students</span>
            <span><i class="fas fa-book"></i> <?php echo count($assignments); ?> assignments</span>
            <span><i class="fas fa-chart-line"></i> <?php echo round($stats['avg_score'] ?? 0); ?>% avg score</span>
          </div>
        </div>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value"><?php echo count($students); ?></div>
          <div class="stat-label">Students</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo count($assignments); ?></div>
          <div class="stat-label">Assignments</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo round($stats['avg_score'] ?? 0); ?>%</div>
          <div class="stat-label">Avg. Score</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo count($recentActivities); ?></div>
          <div class="stat-label">Recent Activities</div>
        </div>
      </div>

      <div class="tabs">
        <div class="tab active" onclick="showTab('students')">Students</div>
        <div class="tab" onclick="showTab('assignments')">Assignments</div>
        <div class="tab" onclick="showTab('activity')">Activity</div>
      </div>

      <!-- Students Tab -->
      <div id="students-tab" class="tab-content">
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>Student</th>
                <th>Email</th>
                <th>Joined</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($students as $student): ?>
              <tr>
                <td>
                  <div style="display: flex; align-items: center; gap: var(--space-sm);">
                    <div class="student-avatar">
                      <?php echo strtoupper(substr($student['username'], 0, 1)); ?>
                    </div>
                    <span><?php echo htmlspecialchars($student['username']); ?></span>
                  </div>
                </td>
                <td><?php echo htmlspecialchars($student['email']); ?></td>
                <td><?php echo date('M d, Y', strtotime($student['joined_at'])); ?></td>
                <td>
                  <button class="btn btn-outline">View</button>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Assignments Tab -->
      <div id="assignments-tab" class="tab-content" style="display: none;">
        <div class="table-container">
          <table class="table">
            <thead>
              <tr>
                <th>Assignment</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Avg. Score</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($assignments as $assignment): ?>
              <tr>
                <td><?php echo htmlspecialchars($assignment['title']); ?></td>
                <td><?php echo date('M d, Y', strtotime($assignment['due_date'])); ?></td>
                <td>
                  <span class="badge badge-primary">Active</span>
                </td>
                <td>75%</td>
                <td>
                  <button class="btn btn-primary">Grade</button>
                  <button class="btn btn-outline">View</button>
                </td>
              </tr>
              <?php endforeach; ?>
              <?php if (empty($assignments)): ?>
                <tr>
                  <td colspan="5" style="text-align: center; padding: var(--space-lg); color: var(--text-light);">
                    No assignments yet. <a href="create_assignment.php?class_id=<?php echo $classId; ?>" style="color: var(--primary);">Create one now</a>
                  </td>
                </tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Activity Tab -->
      <div id="activity-tab" class="tab-content" style="display: none;">
        <div style="background-color: var(--bg-white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--space-sm);">
          <?php if (empty($recentActivities)): ?>
            <div class="activity-item">
              <div class="activity-details">
                <div class="activity-title">No recent activity</div>
              </div>
            </div>
          <?php else: ?>
            <?php foreach ($recentActivities as $activity): ?>
            <div class="activity-item">
              <div class="activity-icon">
                <i class="fas fa-<?php echo htmlspecialchars($activity['icon'] ?? 'bell'); ?>"></i>
              </div>
              <div class="activity-details">
                <div class="activity-title">
                  <strong><?php echo htmlspecialchars($activity['student_name']); ?></strong> 
                  <?php echo htmlspecialchars($activity['description']); ?>
                </div>
                <div class="activity-meta">
                  <?php echo time_elapsed_string($activity['created_at']); ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
    </main>
  </div>

  <div class="bottom-nav-container">
    <nav class="bottom-nav">
      <a href="teacher_dashboard.php" class="nav-item-mobile">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="class_details.php?id=<?php echo $classId; ?>" class="nav-item-mobile active">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Class</span>
      </a>
      <a href="settings.php" class="nav-item-mobile">
        <i class="fas fa-cog"></i>
        <span>Settings</span>
      </a>
      <a href="logout.php" class="nav-item-mobile">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
      
      <div class="fab-container">
        <a href="create_assignment.php?class_id=<?php echo $classId; ?>" class="fab">
          <i class="fas fa-plus"></i>
        </a>
      </div>
      
      <div style="width: 25%;"></div>
      
      <a href="demo02_v10/teacher_flashcard.php" class="nav-item-mobile">
        <i class="fas fa-book"></i>
        <span>Decks</span>
      </a>
    </nav>
  </div>

  <script>
    function toggleDropdown(element) {
      element.classList.toggle('active');
      const menu = element.parentElement.querySelector('.dropdown-menu');
      menu.classList.toggle('show');
    }

    function showTab(tabName) {
      // Hide all tab contents
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.style.display = 'none';
      });
      
      // Remove active class from all tabs
      document.querySelectorAll('.tab').forEach(tab => {
        tab.classList.remove('active');
      });
      
      // Show selected tab content
      document.getElementById(tabName + '-tab').style.display = 'block';
      
      // Add active class to selected tab
      event.currentTarget.classList.add('active');
    }
  </script>
</body>
</html>