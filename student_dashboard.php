<?php
// student_dashboard.php
session_start();
require 'db.php';
require 'includes/theme.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$studentId = $_SESSION['user_id'];
$classes = [];
$stats = [
    'active_classes' => 0,
    'cards_studied' => 0,
    'mastery_level' => 0
];  

try {
    // Get student's classes
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM classes c
        JOIN class_students cs ON c.id = cs.class_id
        WHERE cs.student_id = ?
    ");
    $stmt->execute([$studentId]);
    $classes = $stmt->fetchAll();
    
    // Get stats
    $stats['active_classes'] = count($classes);
    
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM card_progress 
        WHERE student_id = ? AND status = 'learned'
    ");
    $stmt->execute([$studentId]);
    $stats['cards_studied'] = $stmt->fetch()['count'];
    
    $stmt = $pdo->prepare("
        SELECT AVG(accuracy) as avg 
        FROM card_progress 
        WHERE student_id = ?
    ");
    $stmt->execute([$studentId]);
    $stats['mastery_level'] = round($stmt->fetch()['avg'] ?? 0);
    
    // Get recent activity
    $stmt = $pdo->prepare("
        SELECT a.*, c.class_name as group_name 
        FROM activities a
        LEFT JOIN classes c ON a.group_id = c.id
        WHERE a.student_id = ?
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $activities = $stmt->fetchAll();
    
    // Get upcoming quizzes
    $stmt = $pdo->prepare("
        SELECT q.*, c.class_name 
        FROM quizzes q
        JOIN quiz_classes qc ON q.id = qc.quiz_id
        JOIN classes c ON qc.class_id = c.id
        JOIN class_students cs ON cs.class_id = c.id
        WHERE cs.student_id = ?
        ORDER BY q.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$studentId]);
    $quizzes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Get theme for the page
$theme = getCurrentTheme();

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
  <title>Student Dashboard - LearnMate</title>
  
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/theme.css">

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

    /* Sidebar Styles */
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
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }

    .nav-item.active {
      background-color: #F9F5FF;
      color: var(--primary-dark);
      font-weight: 600;
    }

    .nav-item i {
      width: 20px;
      text-align: center;
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
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }
    
    .dropdown-toggle.active {
      background-color: #F9F5FF;
      color: var(--primary-dark);
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
      display: block;
      padding: var(--space-xs) 0;
      text-decoration: none;
      color: var(--text-medium);
      transition: var(--transition);
    }
    
    .dropdown-item:hover {
      color: var(--primary-dark);
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

    /* Main Content Styles */
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
      gap: var(--space-md);
      margin: var(--space-lg) auto var(--space-xl);
      grid-template-columns: repeat(3, minmax(200px, 1fr));
      max-width: 1000px;
      padding: 0 var(--space-md);
    }

    .stat-card {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-lg);
      box-shadow: var(--shadow-sm);
      text-align: center;
      transition: var(--transition);
      border: 1px solid var(--border-light);
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .stat-value {
      font-size: 32px;
      font-weight: 700;
      margin-bottom: var(--space-sm);
      color: var(--primary);
      line-height: 1;
    }

    .stat-label {
      font-size: 15px;
      color: var(--text-medium);
      font-weight: 500;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    /* Section Titles */
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

    /* Assignment Cards */
    .assignment-card {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      margin-bottom: var(--space-sm);
      box-shadow: var(--shadow-sm);
    }
    
    .assignment-title {
      font-weight: 600;
      margin-bottom: var(--space-xs);
    }
    
    .assignment-meta {
      font-size: 12px;
      color: var(--text-light);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      margin-bottom: var(--space-sm);
    }
    
    .progress-container {
      height: 6px;
      background-color: var(--border-light);
      border-radius: var(--radius-full);
      overflow: hidden;
      margin-bottom: var(--space-sm);
    }
    
    .progress-bar {
      height: 100%;
      background-color: var(--primary);
      border-radius: var(--radius-full);
      width: 65%;
    }
    
    .assignment-actions {
      display: flex;
      gap: var(--space-xs);
    }
    
    .assignment-btn {
      flex: 1;
      padding: 6px;
      border-radius: var(--radius-sm);
      border: none;
      background-color: var(--bg-light);
      color: var(--text-medium);
      font-size: 12px;
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
    }
    
    .assignment-btn.primary {
      background-color: var(--primary);
      color: white;
    }
    
    .assignment-btn.primary:hover {
      background-color: var(--primary-dark);
    }

    /* Class Cards */
    .classes-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: var(--space-sm);
      justify-items: center;
      align-items: stretch;
      margin-top: var(--space-lg);
    }

    .class-card {
      width: 100%;
      max-width: 340px;
      min-width: 260px;
      margin: 0 auto;
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
      position: relative;
      text-decoration: none;
      color: inherit;
      transition: var(--transition);
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .class-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .class-image {
      width: 100%;
      height: 180px;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
      background-size: cover;
      background-position: center;
      background-repeat: no-repeat;
      transition: transform 0.3s ease;
    }

    .class-card:hover .class-image {
      transform: scale(1.02);
    }

    .class-initials {
      font-size: 48px;
      font-weight: 600;
      color: white;
      text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
      opacity: 0.9;
    }

    .class-content {
      padding: var(--space-md);
      flex: 1;
      display: flex;
      flex-direction: column;
    }

    .class-title {
      font-weight: 600;
      font-size: 14px;
      margin-bottom: var(--space-xs);
    }

    .class-meta {
      font-size: 12px;
      color: var(--text-light);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      margin-bottom: var(--space-xs);
    }

    .class-actions {
      display: flex;
      gap: 10px;
      margin-top: 15px;
    }

    .btn-view, .btn-leave {
      flex: 1;
      padding: 8px 15px;
      border: none;
      border-radius: 6px;
      font-weight: 500;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      transition: all 0.3s ease;
    }

    .btn-view {
      background-color: var(--primary);
      color: white;
    }

    .btn-view:hover {
      background-color: var(--primary-dark);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(127, 86, 217, 0.2);
    }

    .btn-leave {
      background-color: #FEE2E2;
      color: #DC2626;
    }

    .btn-leave:hover {
      background-color: #FECACA;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(220, 38, 38, 0.1);
    }

    .btn-view i, .btn-leave i {
      font-size: 14px;
    }

    /* Add loading state styles */
    .btn-view.loading, .btn-leave.loading {
      opacity: 0.7;
      cursor: not-allowed;
      transform: none;
    }

    .btn-view.loading i, .btn-leave.loading i {
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    /* Activity Items */
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

    /* Floating Action Button */
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
    }

    .fab i {
      font-size: 24px;
    }

    /* Responsive Design */
    @media (min-width: 640px) {
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
      }
      
      .classes-grid {
        grid-template-columns: 1fr 1fr;
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
      
      .classes-grid {
        grid-template-columns: 1fr 1fr 1fr;
      }
    }

    @media (min-width: 1024px) {
      .classes-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      }
    }

    /* Add these styles to your existing CSS */
    .quick-actions {
      margin-bottom: var(--space-xl);
    }

    .actions-grid {
      display: grid;
      gap: var(--space-md);
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    }

    .action-card {
      background: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-lg);
      display: flex;
      align-items: center;
      gap: var(--space-md);
      text-decoration: none;
      color: var(--text-dark);
      transition: var(--transition);
      box-shadow: var(--shadow-sm);
    }

    .action-card:hover {
      transform: translateY(-2px);
      box-shadow: var(--shadow-md);
    }

    .action-icon {
      width: 48px;
      height: 48px;
      border-radius: var(--radius-lg);
      background: var(--primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
    }

    .action-content h3 {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 4px;
    }

    .action-content p {
      font-size: 14px;
      color: var(--text-light);
      margin: 0;
    }

    @media (max-width: 768px) {
      .actions-grid {
        grid-template-columns: 1fr;
      }

      .stats-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: var(--space-sm);
        padding: 0 var(--space-sm);
        margin: var(--space-md) auto var(--space-lg);
      }

      .stat-card {
        padding: var(--space-md);
      }

      .stat-value {
        font-size: 24px;
        margin-bottom: var(--space-xs);
      }

      .stat-label {
        font-size: 12px;
      }
    }

    @media (max-width: 480px) {
      .stats-grid {
        grid-template-columns: repeat(3, 1fr);
        gap: var(--space-xs);
      }

      .stat-card {
        padding: var(--space-sm);
      }

      .stat-value {
        font-size: 20px;
      }

      .stat-label {
        font-size: 11px;
      }
    }
  </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
  <div class="app-container">
    <!-- Sidebar - Desktop -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo">LM</div>
        <div class="app-name">LearnMate</div>
      </div>
      
      <div class="nav-section">
        <div class="section-title">Menu</div>
        <a href="student_dashboard.php" class="nav-item active">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>
        <a href="student_flashcards/flashcard.php" class="nav-item">
          <i class="fas fa-layer-group"></i>
          <span>My flashcards</span>
        </a>
        
        <!-- Classes Dropdown -->
        <div class="dropdown">
          <div class="dropdown-toggle" onclick="toggleDropdown(this)">
            <div style="display: flex; align-items: center; gap: var(--space-sm);">
              <i class="fas fa-chalkboard-teacher"></i>
              <span>Classes</span>
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
                <?php
                    // Get student count for this class
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM class_students WHERE class_id = ?");
                    $stmt->execute([$class['id']]);
                    $studentCount = $stmt->fetch()['count'];
                ?>
                <a href="student_class_details.php?id=<?php echo $class['id']; ?>" class="dropdown-item">
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
            <a href="join_class.php" class="dropdown-item" style="margin-top: var(--space-sm); color: var(--primary);">
              <i class="fas fa-plus"></i> Join New Class
            </a>
          </div>
        </div>
        
        <a href="student_group.php" class="nav-item">
          <i class="fas fa-users"></i>
          <span>Groups</span>
        </a>
      </div>
      
      <div class="nav-section">
        <div class="section-title">Study</div>
        <a href="student_flashcards/review.php" class="nav-item">
          <i class="fas fa-book-open"></i>
          <span>Review Flashcards</span>
        </a>
      </div>
      
      <div class="nav-section" style="margin-top: auto;">
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
      <!-- Mobile Header -->
      <header class="header">
        <h1 class="header-title">Student Dashboard</h1>
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
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value"><?php echo $stats['active_classes']; ?></div>
          <div class="stat-label">Active Classes</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo $stats['cards_studied']; ?></div>
          <div class="stat-label">Cards Studied</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo $stats['mastery_level']; ?>%</div>
          <div class="stat-label">Mastery Level</div>
        </div>
      </div>

      <!-- Quick Actions Section -->
      <div class="quick-actions">
        <h2 class="section-title-lg">
          <i class="fas fa-bolt"></i>
          <span>Quick Actions</span>
        </h2>
        <div class="actions-grid">
          <a href="student_flashcards/review.php" class="action-card">
            <div class="action-icon">
              <i class="fas fa-book-open"></i>
            </div>
            <div class="action-content">
              <h3>Review Flashcards</h3>
              <p>Practice with your flashcards to improve your learning</p>
            </div>
          </a>
          <a href="student_flashcards/flashcard.php" class="action-card">
            <div class="action-icon">
              <i class="fas fa-layer-group"></i>
            </div>
            <div class="action-content">
              <h3>My Flashcards</h3>
              <p>Create and manage your flashcard collections</p>
            </div>
          </a>
        </div>
      </div>

      <!-- Upcoming Quizzes Section -->
      <div class="card">
        <div class="card-header">
          <div class="card-title">
            <i class="fas fa-tasks"></i>
            <span>Upcoming Quizzes</span>
          </div>
        </div>
        <div class="card-content">
          <?php if (empty($quizzes)): ?>
            <div class="empty-state">
              <div class="assignment-title">No upcoming quizzes</div>
            </div>
          <?php else: ?>
            <?php foreach ($quizzes as $quiz): ?>
              <div class="assignment-item">
                <div class="assignment-icon">
                  <i class="fas fa-file-alt"></i>
                </div>
                <div class="assignment-content">
                  <div class="assignment-title"><?php echo htmlspecialchars($quiz['title']); ?></div>
                  <div class="assignment-meta">
                    <span class="class-name"><?php echo htmlspecialchars($quiz['class_name']); ?></span>
                    <span class="due-date">Created: <?php echo date('M d, Y', strtotime($quiz['created_at'])); ?></span>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>
      
      <!-- Classes Section -->
      <h2 class="section-title-lg">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Your Classes</span>
      </h2>
      
      <div class="classes-grid">
        <?php foreach ($classes as $class): ?>
        <?php
            // Get student count for this class
            $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM class_students WHERE class_id = ?");
            $stmt->execute([$class['id']]);
            $studentCount = $stmt->fetch()['count'];
        ?>
        <div class="class-card">
          <div class="class-image" style="background-image: url('<?php echo !empty($class['background_image']) ? htmlspecialchars($class['background_image']) : ''; ?>'); background-color: <?php echo !empty($class['background_image']) ? 'transparent' : '#7F56D9'; ?>; background-size: cover; background-position: center; background-repeat: no-repeat;">
            <?php if (empty($class['background_image'])): ?>
              <div class="class-initials">
                <?php 
                  // Get first letter of each word in class name
                  $words = explode(' ', $class['class_name']);
                  $initials = '';
                  foreach ($words as $word) {
                    $initials .= strtoupper(substr($word, 0, 1));
                    if (strlen($initials) >= 2) break;
                  }
                  echo $initials;
                ?>
              </div>
            <?php endif; ?>
          </div>
          <div class="class-content">
            <h3><?php echo htmlspecialchars($class['class_name']); ?></h3>
            <p class="class-section"><?php echo htmlspecialchars($class['section']); ?></p>
            <div class="class-stats">
                <span><i class="fas fa-users"></i> <?php echo $studentCount; ?> Students</span>
            </div>
            <div class="class-actions">
                <button onclick="window.location.href='student_class_details.php?id=<?php echo $class['id']; ?>'" class="btn-view">
                    <i class="fas fa-eye"></i> View
                </button>
                <button onclick="leaveClass(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['class_name']); ?>')" 
                        class="btn-leave" 
                        data-class-id="<?php echo $class['id']; ?>">
                    <i class="fas fa-sign-out-alt"></i> Leave
                </button>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
        
        <a href="join_class.php" class="class-card">
          <div class="class-content" style="padding: var(--space-md); display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%;">
            <i class="fas fa-plus" style="font-size: 24px; color: var(--text-light); margin-bottom: var(--space-sm);"></i>
            <h3 class="class-title" style="text-align: center;">Join New Class</h3>
          </div>
        </a>
      </div>
      
      <!-- Recent Activity Section -->
      <h2 class="section-title-lg" style="margin-top: var(--space-xl);">
        <i class="fas fa-clock"></i>
        <span>Recent Activity</span>
      </h2>
      
      <div style="background-color: var(--bg-white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--space-sm);">
        <?php if (empty($activities)): ?>
          <div class="activity-item">
            <div class="activity-details">
              <div class="activity-title">No recent activity</div>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($activities as $activity): ?>
          <div class="activity-item">
            <div class="activity-icon">
              <i class="fas fa-<?php echo htmlspecialchars($activity['icon'] ?? 'bell'); ?>"></i>
            </div>
            <div class="activity-details">
              <div class="activity-title"><?php echo htmlspecialchars($activity['description']); ?></div>
              <div class="activity-meta">
                <?php 
                  echo time_elapsed_string($activity['created_at']);
                  if ($activity['group_name']): 
                    echo ' • ' . htmlspecialchars($activity['group_name']);
                  endif; 
                ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <!-- Bottom Navigation with Fixed FAB - Mobile Only -->
  <div class="bottom-nav-container">
    <nav class="bottom-nav">
      <a href="student_dashboard.php" class="nav-item-mobile active">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="my_classes.php" class="nav-item-mobile">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Classes</span>
      </a>
      
      <!-- FAB Container -->
      <div class="fab-container">
        <button class="fab">
          <i class="fas fa-plus"></i>
        </button>
      </div>
      
      <!-- Spacer for FAB area -->
      <div style="width: 25%;"></div>
      
      <a href="study_sets.php" class="nav-item-mobile">
        <i class="fas fa-book"></i>
        <span>Study</span>
      </a>
      <a href="progress.php" class="nav-item-mobile">
        <i class="fas fa-chart-line"></i>
        <span>Progress</span>
      </a>
    </nav>
  </div>

  <script>
    // Toggle dropdown functionality
    function toggleDropdown(element) {
      element.classList.toggle('active');
      const menu = element.parentElement.querySelector('.dropdown-menu');
      menu.classList.toggle('show');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
          menu.classList.remove('show');
        });
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
          toggle.classList.remove('active');
        });
      }
    });

    // Function to handle leaving a class
    function leaveClass(classId, className) {
      if (confirm(`Are you sure you want to leave the class "${className}"? This action cannot be undone.`)) {
        const leaveBtn = document.querySelector(`.btn-leave[data-class-id="${classId}"]`);
        if (leaveBtn) {
          leaveBtn.classList.add('loading');
          leaveBtn.disabled = true;
        }

        fetch('leave_class.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            class_id: classId
          })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Show success message
            alert('Successfully left the class');
            // Refresh the page to update the UI
            window.location.reload();
          } else {
            if (leaveBtn) {
              leaveBtn.classList.remove('loading');
              leaveBtn.disabled = false;
            }
            alert('Failed to leave class: ' + data.message);
          }
        })
        .catch(error => {
          if (leaveBtn) {
            leaveBtn.classList.remove('loading');
            leaveBtn.disabled = false;
          }
          console.error('Error:', error);
          alert('An error occurred while leaving the class');
        });
      }
    }
  </script>
</body>
</html>