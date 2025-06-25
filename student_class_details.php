<?php
// student_class_details.php
session_start();
require 'db.php';
require 'includes/theme.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'student') {
    header('Location: index.php');
    exit();
}

// Get theme for the page
$theme = getCurrentTheme();

// Get class ID from URL
$classId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$studentId = $_SESSION['user_id'];

// Fetch class details
try {
    // Check if student is enrolled in this class
    $stmt = $pdo->prepare("
        SELECT c.*, u.first_name, u.last_name, u.email 
        FROM classes c
        JOIN users u ON c.teacher_id = u.id
        JOIN class_students cs ON cs.class_id = c.id
        WHERE c.id = ? AND cs.student_id = ?
    ");
    $stmt->execute([$classId, $studentId]);
    $class = $stmt->fetch();

    if (!$class) {
        header('Location: student_dashboard.php');
        exit();
    }

    // Get all classes the student is enrolled in (for sidebar dropdown)
    $stmt = $pdo->prepare("
        SELECT c.* 
        FROM classes c
        JOIN class_students cs ON c.id = cs.class_id
        WHERE cs.student_id = ?
        ORDER BY c.class_name
    ");
    $stmt->execute([$studentId]);
    $classes = $stmt->fetchAll();

    // Get enrolled students
    $stmt = $pdo->prepare("
        SELECT u.* 
        FROM users u
        JOIN class_students cs ON u.id = cs.student_id
        WHERE cs.class_id = ?
        ORDER BY u.last_name, u.first_name
    ");
    $stmt->execute([$classId]);
    $students = $stmt->fetchAll();

    // Get quizzes for this class
    $stmt = $pdo->prepare("
        SELECT q.* 
        FROM quizzes q
        JOIN quiz_classes qc ON q.id = qc.quiz_id
        WHERE qc.class_id = ?
        ORDER BY q.created_at DESC
    ");
    $stmt->execute([$classId]);
    $quizzes = $stmt->fetchAll();

    // Fetch announcements for this class
    $stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name FROM announcements a JOIN users u ON a.user_id = u.id JOIN announcement_classes ac ON a.id = ac.announcement_id WHERE ac.class_id = ? ORDER BY a.created_at DESC");
    $stmt->execute([$classId]);
    $announcements = $stmt->fetchAll();

    // Fetch modules for this class
    $stmt = $pdo->prepare("SELECT * FROM modules WHERE class_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$classId]);
    $modules = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Helper function to display time ago
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime('now', new DateTimeZone('Asia/Manila'));
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila'));
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
    /* Use the same styles as class_details.php, but remove theme and class code sections, and grades tab */
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
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
    body { background-color: var(--bg-light); color: var(--text-dark); line-height: 1.5; }
    .app-container { display: flex; min-height: 100vh; }
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
    @media (min-width: 768px) { .sidebar { display: flex; flex-direction: column; } .main-content { width: calc(100% - 280px); padding: var(--space-xl); } .header { display: none; } }
    .main-content { flex: 1; padding: var(--space-md); position: relative; background-color: var(--bg-light); width: 100%; }
    .header { background-color: var(--bg-white); padding: var(--space-md); position: sticky; top: 0; z-index: 10; box-shadow: var(--shadow-sm); display: flex; justify-content: space-between; align-items: center; }
    .header-title { font-weight: 600; font-size: 18px; }
    .header-actions { display: flex; gap: var(--space-sm); }
    .header-btn { width: 36px; height: 36px; border-radius: var(--radius-full); display: flex; align-items: center; justify-content: center; background-color: var(--bg-light); border: none; color: var(--text-medium); cursor: pointer; }
    .header-btn:hover { background-color: var(--primary-light); color: white; }
    .class-nav { display: flex; gap: var(--space-sm); padding: var(--space-md); background-color: var(--bg-white); border-bottom: 1px solid var(--border-light); margin-bottom: var(--space-md); overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .class-nav-item { display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm) var(--space-md); border-radius: var(--radius-md); color: var(--text-medium); cursor: pointer; transition: var(--transition); white-space: nowrap; }
    .class-nav-item:hover { background-color: var(--bg-light); color: var(--primary); }
    .class-nav-item.active { background-color: var(--primary); color: white; }
    .class-nav-item i { font-size: 16px; }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .card { 
      background-color: var(--bg-white); 
      border-radius: var(--radius-lg); 
      box-shadow: var(--shadow-sm); 
      overflow: hidden; 
      margin-bottom: var(--space-sm); 
    }
    .card-header { padding: var(--space-md); border-bottom: 1px solid var(--border-light); display: flex; justify-content: space-between; align-items: center; }
    .card-title { font-weight: 600; font-size: 16px; color: var(--text-dark); }
    .card-content { padding: var(--space-md); }
    .empty-state { text-align: center; padding: var(--space-xl); color: var(--text-light); }
    .empty-state i { font-size: 48px; margin-bottom: var(--space-md); color: var(--border-light); }
    .people-section { margin-bottom: var(--space-lg); }
    .teacher-item { display: flex; align-items: center; gap: var(--space-md); padding: var(--space-sm); background-color: var(--bg-light); border-radius: var(--radius-md); margin-bottom: var(--space-md); }
    .teacher-avatar { width: 40px; height: 40px; border-radius: var(--radius-full); background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; }
    .teacher-info { flex: 1; }
    .teacher-name { font-weight: 500; margin-bottom: 2px; }
    .teacher-email { font-size: 14px; color: var(--text-light); }
    .student-list { display: flex; flex-direction: column; gap: var(--space-sm); }
    .student-item { display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm); border-radius: var(--radius-md); transition: var(--transition); }
    .student-item:hover { background-color: var(--bg-light); }
    .student-avatar { width: 40px; height: 40px; border-radius: var(--radius-full); background-color: var(--primary); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600; font-size: 14px; }
    .student-info { flex: 1; }
    .student-name { font-weight: 500; color: var(--text-dark); }
    .student-email { font-size: 12px; color: var(--text-light); }
    .quiz-list { display: flex; flex-direction: column; gap: var(--space-sm); }
    .quiz-item { display: flex; align-items: center; gap: var(--space-md); padding: var(--space-sm); border-radius: var(--radius-md); transition: var(--transition); }
    .quiz-item:hover { background-color: var(--bg-light); }
    .quiz-icon { width: 40px; height: 40px; border-radius: var(--radius-md); background-color: #F9F5FF; color: var(--primary); display: flex; align-items: center; justify-content: center; }
    .quiz-content { flex: 1; }
    .quiz-title { font-weight: 500; margin-bottom: 2px; }
    .quiz-meta { font-size: 14px; color: var(--text-light); }

    /* Theme Preview Box Styles */
    .theme-preview-box {
      width: 100%;
      height: 200px;
      border-radius: var(--radius-md);
      position: relative;
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .theme-section {
      position: absolute;
      bottom: var(--space-md);
      left: var(--space-md);
      background-color: rgba(255, 255, 255, 0.9);
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-sm);
    }

    .section-label {
      font-weight: 500;
      color: #111;
    }

    body[data-theme='dark'] .theme-section {
      background-color: rgba(30, 30, 30, 0.96);
      color: #fff;
      border: 1px solid #333;
      box-shadow: 0 2px 8px rgba(0,0,0,0.25);
    }

    body[data-theme='dark'] .theme-section .section-label {
      color: #fff;
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
        <a href="student_dashboard.php" class="nav-item">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>
        <a href="student_flashcards/flashcard.php" class="nav-item">
          <i class="fas fa-layer-group"></i>
          <span>My flashcards</span>
        </a>
        <!-- Classes Dropdown -->
        <div class="dropdown">
          <div class="dropdown-toggle <?php echo $classId > 0 ? 'active' : ''; ?>" onclick="toggleDropdown(this)">
            <div style="display: flex; align-items: center; gap: var(--space-sm);">
              <i class="fas fa-chalkboard-teacher"></i>
              <span>Classes</span>
            </div>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="dropdown-menu <?php echo $classId > 0 ? 'show' : ''; ?>">
            <?php if (empty($classes)): ?>
              <div class="dropdown-item" style="padding: var(--space-sm);">
                No classes yet
              </div>
            <?php else: ?>
              <?php foreach ($classes as $c): ?>
                <a href="student_class_details.php?id=<?php echo $c['id']; ?>" class="dropdown-item<?php echo ($c['id'] == $classId) ? ' active' : ''; ?>">
                  <div style="display: flex; align-items: center;">
                    <div class="profile-initial">
                      <?php 
                        $words = explode(' ', $c['class_name']);
                        $initials = '';
                        foreach ($words as $word) {
                          $initials .= strtoupper(substr($word, 0, 1));
                          if (strlen($initials) >= 2) break;
                        }
                        echo $initials;
                      ?>
                    </div>
                    <div>
                      <div class="class-name"><?php echo htmlspecialchars($c['class_name']); ?></div>
                      <div class="class-section"><?php echo htmlspecialchars($c['section']); ?></div>
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
    <main class="main-content">
      <header class="header">
        <h1 class="header-title"><?php echo htmlspecialchars($class['class_name']); ?></h1>
        <div class="header-actions">
          <a href="student_dashboard.php" class="header-btn">
            <i class="fas fa-arrow-left"></i>
          </a>
        </div>
      </header>

      <?php if (isset($_SESSION['join_success']) && $_SESSION['join_success']): ?>
        <div class="alert alert-success" style="margin: var(--space-md); display: flex; align-items: center; gap: var(--space-sm);">
          <i class="fas fa-check-circle"></i>
          <span>Successfully joined <?php echo htmlspecialchars($_SESSION['joined_class_name']); ?>!</span>
        </div>
        <?php 
          // Clear the success message
          unset($_SESSION['join_success']);
          unset($_SESSION['joined_class_name']);
        ?>
      <?php endif; ?>

      <!-- Theme Preview Box - Show for all classes -->
      <div class="card" style="margin-bottom: var(--space-md);">
        <div class="card-header">
          <h3 class="card-title">Class Theme</h3>
        </div>
        <div class="card-content">
          <div class="theme-preview-box" id="themePreviewBox" style="height: 200px; background-image: url('<?php echo !empty($class['background_image']) ? htmlspecialchars($class['background_image']) : ''; ?>'); background-color: <?php echo !empty($class['background_image']) ? 'transparent' : '#7F56D9'; ?>; background-size: cover; background-position: center; background-repeat: no-repeat;">
            <div class="theme-section">
              <span class="section-label">Section: <?php echo htmlspecialchars($class['section']); ?></span>
            </div>
          </div>
        </div>
      </div>

      <div class="class-nav">
        <div class="class-nav-item active" data-tab="overview">
          <i class="fas fa-chalkboard"></i>
          <span><?php echo htmlspecialchars($class['class_name']); ?></span>
        </div>
        <div class="class-nav-item" data-tab="quizzes">
          <i class="fas fa-tasks"></i>
          <span>Quizzes</span>
        </div>
        <div class="class-nav-item" data-tab="people">
          <i class="fas fa-users"></i>
          <span>People</span>
        </div>
      </div>
      <div class="tab-content active" id="overview-tab">
        <div class="card" style="margin-bottom: var(--space-sm);">
          <div class="card-header">
            <h3 class="card-title">Announcements</h3>
          </div>
          <div class="card-content">
            <?php if (empty($announcements)): ?>
              <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <p>No announcements yet</p>
                <p class="text-muted">Your teacher will post announcements here</p>
              </div>
            <?php else: ?>
              <div class="announcement-list">
                <?php foreach ($announcements as $a): ?>
                  <div class="announcement-item" data-id="<?php echo $a['id']; ?>" style="background:#F9F5FF;padding:12px;border-radius:8px;margin-bottom:12px;">
                    <div class="announcement-header">
                      <div style="font-weight:600;">Announcement by <?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?> 
                        <span style="color:#aaa;font-weight:400;font-size:12px;">
                          (<?php echo time_elapsed_string($a['created_at']); ?>)
                        </span>
                      </div>
                    </div>
                    <div class="announcement-content" style="margin-top:4px;white-space:pre-line;"><?php echo $a['content']; ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Modules</h3>
          </div>
          <div class="card-content">
            <?php if (empty($modules)): ?>
              <div class="empty-state">
                <i class="fas fa-file-pdf"></i>
                <p>No modules uploaded yet</p>
                <p class="text-muted">Your teacher will upload learning materials here</p>
              </div>
            <?php else: ?>
              <ul style="list-style:none;padding:0;">
                <?php foreach ($modules as $mod): ?>
                  <li style="margin-bottom:10px;display:flex;align-items:center;gap:10px;">
                    <a href="view_module.php?id=<?php echo $mod['id']; ?>" style="color:var(--primary);font-weight:500;text-decoration:underline;">
                      <i class="fas fa-file-pdf"></i> <?php echo htmlspecialchars($mod['title']); ?>
                    </a>
                    <span style="color:#888;font-size:12px;">(Uploaded <?php echo time_elapsed_string($mod['uploaded_at']); ?>)</span>
                  </li>
                <?php endforeach; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="tab-content" id="quizzes-tab">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Quizzes</h3>
          </div>
          <div class="card-content">
            <?php if (empty($quizzes)): ?>
              <div class="empty-state">
                <i class="fas fa-tasks"></i>
                <p>No quizzes yet</p>
              </div>
            <?php else: ?>
              <div class="quiz-list">
                <?php foreach ($quizzes as $quiz): 
                  // Check if student has taken this quiz
                  $stmt = $pdo->prepare("SELECT * FROM quiz_results WHERE quiz_id = ? AND student_id = ?");
                  $stmt->execute([$quiz['id'], $studentId]);
                  $result = $stmt->fetch();
                ?>
                  <div class="quiz-item">
                    <div class="quiz-icon">
                      <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="quiz-content">
                      <div class="quiz-title">
                        <?php echo htmlspecialchars($quiz['title']); ?>
                      </div>
                      <div class="quiz-meta">
                        <?php echo htmlspecialchars($quiz['description']); ?><br>
                        Created: <?php echo date('M d, Y', strtotime($quiz['created_at'])); ?>
                      </div>
                    </div>
                    <div style="display: flex; align-items: center; gap: 10px;">
                      <?php if ($result): ?>
                        <span style="font-weight: 500; color: var(--primary);">
                          Score: <?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?>
                        </span>
                      <?php endif; ?>
                      <a href="take_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn" style="background-color: var(--primary); color: white; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-weight: 500; white-space: nowrap;">
                        <?php echo $result ? 'Retake Quiz' : 'Take Quiz'; ?>
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="tab-content" id="people-tab">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">People</h3>
          </div>
          <div class="card-content">
            <div class="people-section">
              <h4 class="section-title">Teacher</h4>
              <div class="teacher-item">
                <div class="teacher-avatar">
                  <?php 
                    $initials = strtoupper(substr($class['first_name'], 0, 1) . substr($class['last_name'], 0, 1));
                    echo $initials;
                  ?>
                </div>
                <div class="teacher-info">
                  <div class="teacher-name">
                    <?php echo htmlspecialchars($class['first_name'] . ' ' . $class['last_name']); ?>
                  </div>
                  <div class="teacher-email">
                    <?php echo htmlspecialchars($class['email']); ?>
                  </div>
                </div>
              </div>
            </div>
            <div class="people-section">
              <h4 class="section-title">Students (<?php echo count($students); ?>)</h4>
              <?php if (empty($students)): ?>
                <div class="empty-state">
                  <i class="fas fa-users"></i>
                  <p>No students enrolled yet</p>
                </div>
              <?php else: ?>
                <div class="student-list">
                  <?php foreach ($students as $student): ?>
                    <div class="student-item">
                      <div class="student-avatar">
                        <?php 
                          $initials = strtoupper(substr($student['first_name'], 0, 1) . substr($student['last_name'], 0, 1));
                          echo $initials;
                        ?>
                      </div>
                      <div class="student-info">
                        <div class="student-name">
                          <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        </div>
                        <div class="student-email">
                          <?php echo htmlspecialchars($student['email']); ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>
  <script>
    // Toggle dropdown functionality
    function toggleDropdown(element) {
      // If we're viewing a class (classId > 0), keep the dropdown open by default
      // Only toggle if it's not already active or if we're not viewing a class
      const isViewingClass = <?php echo $classId > 0 ? 'true' : 'false'; ?>;
      
      if (isViewingClass && !element.classList.contains('active')) {
        // If viewing a class and dropdown is not active, make it active
        element.classList.add('active');
        const menu = element.parentElement.querySelector('.dropdown-menu');
        menu.classList.add('show');
      } else {
        // Normal toggle behavior
        element.classList.toggle('active');
        const menu = element.parentElement.querySelector('.dropdown-menu');
        menu.classList.toggle('show');
      }
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

    // Initialize dropdown state when page loads
    document.addEventListener('DOMContentLoaded', function() {
      // If we're viewing a class, ensure the dropdown is open
      const isViewingClass = <?php echo $classId > 0 ? 'true' : 'false'; ?>;
      if (isViewingClass) {
        const dropdownToggle = document.querySelector('.dropdown-toggle');
        const dropdownMenu = document.querySelector('.dropdown-menu');
        if (dropdownToggle && dropdownMenu) {
          dropdownToggle.classList.add('active');
          dropdownMenu.classList.add('show');
        }
      }
    });

    document.querySelectorAll('.class-nav-item').forEach(item => {
      item.addEventListener('click', function() {
        document.querySelectorAll('.class-nav-item').forEach(navItem => {
          navItem.classList.remove('active');
        });
        this.classList.add('active');
        document.querySelectorAll('.tab-content').forEach(tab => {
          tab.classList.remove('active');
        });
        const tabId = this.getAttribute('data-tab') + '-tab';
        document.getElementById(tabId).classList.add('active');
      });
    });
  </script>
</body>
</html> 