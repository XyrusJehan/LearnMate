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
$classId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$teacherId = $_SESSION['user_id'];

// Fetch class details
try {
    // Get class information
    $stmt = $pdo->prepare("
        SELECT c.*, u.first_name, u.last_name, u.email 
        FROM classes c
        JOIN users u ON c.teacher_id = u.id
        WHERE c.id = ? AND c.teacher_id = ?
    ");
    $stmt->execute([$classId, $teacherId]);
    $class = $stmt->fetch();

    if (!$class) {
        header('Location: teacher_dashboard.php');
        exit();
    }

    // Generate class code if it doesn't exist
    if (empty($class['class_code'])) {
        $classCode = strtoupper(substr(md5(uniqid()), 0, 6));
        $stmt = $pdo->prepare("UPDATE classes SET class_code = ? WHERE id = ?");
        $stmt->execute([$classCode, $classId]);
        $class['class_code'] = $classCode;
    }

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

    // Get class activities
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name
        FROM activities a
        JOIN users u ON a.student_id = u.id
        WHERE a.group_id = ?
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute([$classId]);
    $activities = $stmt->fetchAll();

    // Fetch teacher's classes for sidebar dropdown
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ?");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll();

    // Restore currentClassId for dropdown default
    $currentClassId = $classId;

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Add this after the existing database queries
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_background']) && isset($_POST['temp_image'])) {
        // Handle saving the temporary image
        $tempImage = $_POST['temp_image'];
        $uploadDir = 'uploads/class_backgrounds/';
        
        // Create directory if it doesn't exist
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        // Generate unique filename
        $fileName = uniqid() . '.jpg';
        $targetPath = $uploadDir . $fileName;
        
        // Convert base64 to image and save
        $imageData = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $tempImage));
        if (file_put_contents($targetPath, $imageData)) {
            // Update database with new background image path
            $stmt = $pdo->prepare("UPDATE classes SET background_image = ? WHERE id = ?");
            $stmt->execute([$targetPath, $classId]);
            
            // Redirect to refresh the page
            header("Location: class_details.php?id=" . $classId);
            exit();
        }
    }
}

// Fetch all classes for the teacher
$allClasses = [];
if ($_SESSION['role'] === 'teacher') {
    $stmt = $pdo->prepare("SELECT id, class_name, section FROM classes WHERE teacher_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $allClasses = $stmt->fetchAll();
}
// Fetch students for selected classes (AJAX in real app, here we fetch all for demo)
$allStudents = [];
if ($_SESSION['role'] === 'teacher') {
    $stmt = $pdo->query("SELECT u.id, u.first_name, u.last_name, c.class_name FROM users u JOIN class_students cs ON u.id = cs.student_id JOIN classes c ON cs.class_id = c.id");
    $allStudents = $stmt->fetchAll();
}
// Handle new announcement submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_announcement'])) {
    $announcement_content = trim($_POST['announcement_content'] ?? '');
    $selected_classes = $_POST['announcement_classes'] ?? [];
    $selected_students = $_POST['announcement_students'] ?? [];
    if (!empty($announcement_content) && !empty($selected_classes)) {
        // Insert announcement (no class_id)
        $stmt = $pdo->prepare("INSERT INTO announcements (user_id, content) VALUES (?, ?)");
        $stmt->execute([$_SESSION['user_id'], $announcement_content]);
        $announcement_id = $pdo->lastInsertId();
        // Insert into announcement_classes for each class
        $stmt = $pdo->prepare("INSERT INTO announcement_classes (announcement_id, class_id) VALUES (?, ?)");
        foreach ($selected_classes as $cid) {
            $stmt->execute([$announcement_id, $cid]);
        }
        // Insert into announcement_students if not all students
        if (!empty($selected_students)) {
            $stmt = $pdo->prepare("INSERT INTO announcement_students (announcement_id, student_id) VALUES (?, ?)");
            foreach ($selected_students as $sid) {
                $stmt->execute([$announcement_id, $sid]);
            }
        }
        header("Location: class_details.php?id=" . $classId);
        exit();
    }
}
// Fetch announcements for this class
$stmt = $pdo->prepare("SELECT a.*, u.first_name, u.last_name FROM announcements a JOIN users u ON a.user_id = u.id JOIN announcement_classes ac ON a.id = ac.announcement_id WHERE ac.class_id = ? ORDER BY a.created_at DESC");
$stmt->execute([$classId]);
$announcements = $stmt->fetchAll();

// Helper function to display time ago
function time_elapsed_string($datetime, $full = false) {
    $now = new DateTime('now', new DateTimeZone('Asia/Manila')); // Set to Philippine timezone
    $ago = new DateTime($datetime, new DateTimeZone('Asia/Manila')); // Set to Philippine timezone
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

// Fetch modules for this class
$stmt = $pdo->prepare("SELECT * FROM modules WHERE class_id = ? ORDER BY uploaded_at DESC");
$stmt->execute([$classId]);
$modules = $stmt->fetchAll();
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
      display: block;
      padding: var(--space-xs) 0;
      text-decoration: none;
      color: var(--text-medium);
      transition: var(--transition);
    }
    
    .dropdown-item:hover {
      color: var(--primary-dark);
    }
    
    .dropdown-item.active {
      color: var(--primary);
      font-weight: 500;
      background-color: var(--bg-light);
      border-radius: var(--radius-sm);
      padding: var(--space-xs) var(--space-sm);
      margin: 0 calc(-1 * var(--space-sm));
    }
    
    .dropdown-item.active .class-name {
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

    .header-btn:hover {
      background-color: var(--primary-light);
      color: white;
    }

    .class-header {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-lg);
      margin-bottom: var(--space-lg);
      box-shadow: var(--shadow-sm);
    }

    .class-title {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: var(--space-xs);
      color: var(--text-dark);
    }

    .class-meta {
      display: flex;
      gap: var(--space-lg);
      color: var(--text-medium);
      font-size: 14px;
    }

    .meta-item {
      display: flex;
      align-items: center;
      gap: var(--space-xs);
    }

    .content-grid {
      display: grid;
      gap: var(--space-md);
      grid-template-columns: 2fr 1fr;
    }

    .card {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      overflow: hidden;
    }

    .card-header {
      padding: var(--space-md);
      border-bottom: 1px solid var(--border-light);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .card-title {
      font-weight: 600;
      font-size: 16px;
      color: var(--text-dark);
    }

    .card-content {
      padding: var(--space-md);
    }

    .student-list {
      display: flex;
      flex-direction: column;
      gap: var(--space-sm);
    }

    .student-item {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      padding: var(--space-sm);
      border-radius: var(--radius-md);
      transition: var(--transition);
    }

    .student-item:hover {
      background-color: var(--bg-light);
    }

    .student-avatar {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-full);
      background-color: var(--primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 14px;
    }

    .student-info {
      flex: 1;
    }

    .student-name {
      font-weight: 500;
      color: var(--text-dark);
    }

    .student-email {
      font-size: 12px;
      color: var(--text-light);
    }

    .activity-item {
      display: flex;
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

    .activity-content {
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

    .btn {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      border: none;
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: var(--space-sm);
      text-decoration: none;
    }

    .btn-primary {
      background-color: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
    }

    .btn-secondary {
      background-color: var(--bg-light);
      color: var(--text-medium);
    }

    .btn-secondary:hover {
      background-color: var(--border-light);
    }

    .empty-state {
      text-align: center;
      padding: var(--space-xl);
      color: var(--text-light);
    }

    .empty-state i {
      font-size: 48px;
      margin-bottom: var(--space-md);
      color: var(--border-light);
    }

    @media (max-width: 768px) {
      .content-grid {
        grid-template-columns: 1fr;
      }
    }

    @media (min-width: 768px) {
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

    /* Add these styles for the bottom navigation */
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
    }

    .fab i {
      font-size: 24px;
    }

    /* Action Menu Styles */
    .action-menu {
      position: fixed;
      bottom: 80px;
      left: 50%;
      transform: translateX(-50%);
      background: white;
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      padding: var(--space-sm);
      display: none;
      z-index: 30;
      min-width: 200px;
    }

    .action-menu.show {
      display: block;
    }

    .action-menu-item {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      padding: var(--space-sm) var(--space-md);
      color: var(--text-dark);
      text-decoration: none;
      border-radius: var(--radius-md);
      transition: var(--transition);
    }

    .action-menu-item:hover {
      background-color: var(--bg-light);
    }

    .action-menu-item i {
      width: 20px;
      color: var(--primary);
    }

    @media (min-width: 768px) {
      .bottom-nav-container {
        display: none;
      }
    }

    .class-code-container {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: var(--space-md);
      padding: var(--space-md);
      background-color: var(--bg-light);
      border-radius: var(--radius-md);
    }

    .class-code {
      display: flex;
      flex-direction: column;
      gap: var(--space-xs);
    }

    .class-code span {
      font-size: 14px;
      color: var(--text-light);
    }

    .class-code strong {
      font-size: 24px;
      font-family: monospace;
      letter-spacing: 2px;
      color: var(--text-dark);
    }

    @media (max-width: 768px) {
      .class-code-container {
        flex-direction: column;
        align-items: stretch;
        text-align: center;
      }
    }

    /* Add these styles for the navigation and tabs */
    .class-nav {
      display: flex;
      gap: var(--space-sm);
      padding: var(--space-md);
      background-color: var(--bg-white);
      border-bottom: 1px solid var(--border-light);
      margin-bottom: var(--space-md);
      overflow-x: auto;
      -webkit-overflow-scrolling: touch;
    }

    .class-nav-item {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      color: var(--text-medium);
      cursor: pointer;
      transition: var(--transition);
      white-space: nowrap;
    }

    .class-nav-item:hover {
      background-color: var(--bg-light);
      color: var(--primary);
    }

    .class-nav-item.active {
      background-color: var(--primary);
      color: white;
    }

    .class-nav-item i {
      font-size: 16px;
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
    }

    .people-section {
      margin-bottom: var(--space-lg);
    }

    .section-title {
      font-size: 14px;
      font-weight: 600;
      color: var(--text-light);
      margin-bottom: var(--space-sm);
      text-transform: uppercase;
      letter-spacing: 0.05em;
    }

    .teacher-item {
      display: flex;
      align-items: center;
      gap: var(--space-md);
      padding: var(--space-sm);
      background-color: var(--bg-light);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-md);
    }

    .teacher-avatar {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-full);
      background-color: var(--primary);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
    }

    .teacher-info {
      flex: 1;
    }

    .teacher-name {
      font-weight: 500;
      margin-bottom: 2px;
    }

    .teacher-email {
      font-size: 14px;
      color: var(--text-light);
    }

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

    .theme-actions {
      display: flex;
      gap: var(--space-sm);
    }

    .for-dropdown-container {
      position: relative;
      display: inline-block;
      width: 100%;
    }
    .for-dropdown-btn {
      min-width: 220px;
      text-align: left;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 8px;
      padding: 12px 16px;
      border-radius: 12px;
      border: 1.5px solid var(--border-light);
      background: var(--bg-white);
      box-shadow: 0 2px 8px rgba(16,24,40,0.04);
      font-size: 15px;
      cursor: pointer;
      transition: border 0.2s, box-shadow 0.2s, background 0.2s;
    }
    .for-dropdown-btn:hover, .for-dropdown-btn:focus {
      border: 1.5px solid var(--primary);
      background: #f4f3ff;
      box-shadow: 0 4px 16px rgba(127,86,217,0.08);
      outline: none;
    }
    .for-dropdown-icon {
      font-size: 18px;
      margin-left: 8px;
      color: var(--primary-dark);
      transition: transform 0.2s;
    }
    .for-dropdown-btn[aria-expanded="true"] .for-dropdown-icon {
      transform: rotate(180deg);
    }
    .for-dropdown-list {
      opacity: 0;
      pointer-events: none;
      position: absolute;
      left: 0;
      top: 110%;
      z-index: 9999;
      background: var(--bg-white);
      border: 1px solid var(--border-light);
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(16,24,40,0.12);
      min-width: 240px;
      max-height: 260px;
      overflow-y: auto;
      padding: 8px 0;
      transition: opacity 0.2s, transform 0.2s;
      transform: translateY(8px);
      display: block;
    }
    .for-dropdown-list.show {
      opacity: 1;
      pointer-events: auto;
      transform: translateY(0);
    }
    .for-dropdown-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 20px;
      cursor: pointer;
      font-size: 15px;
      transition: background 0.15s;
      user-select: none;
    }
    .for-dropdown-item.selected {
      background: #f4f3ff;
    }
    .for-dropdown-item:hover {
      background: #f4f3ff;
    }
    .for-dropdown-checkbox {
      accent-color: var(--primary);
      width: 16px;
      height: 16px;
      margin-right: 4px;
    }
    .for-dropdown-checkmark {
      color: var(--primary);
      font-size: 16px;
      margin-left: auto;
      display: none;
    }
    .for-dropdown-item.selected .for-dropdown-checkmark {
      display: inline;
    }
    .for-dropdown-badge {
      background: var(--primary-light);
      color: #fff;
      border-radius: 8px;
      padding: 2px 10px;
      font-size: 13px;
      display: inline-block;
      margin-bottom: 2px;
      margin-right: 4px;
    }
    body[data-theme='dark'] .for-dropdown-btn {
      background: #23232b;
      border: 1.5px solid #444;
      color: #fff;
    }
    body[data-theme='dark'] .for-dropdown-btn:hover, body[data-theme='dark'] .for-dropdown-btn:focus {
      background: #2d2d3a;
      border: 1.5px solid #9E77ED;
    }
    body[data-theme='dark'] .for-dropdown-list {
      background: #23232b;
      border: 1px solid #444;
      color: #fff;
    }
    body[data-theme='dark'] .for-dropdown-item.selected, body[data-theme='dark'] .for-dropdown-item:hover {
      background: #2d2d3a;
    }
    body[data-theme='dark'] .for-dropdown-badge {
      background: #7F56D9;
      color: #fff;
    }

    /* Add these styles in the <style> section */
    .announcement-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
    }
    
    .announcement-actions {
      position: relative;
    }
    
    .kebab-menu {
      background: none;
      border: none;
      color: var(--text-light);
      cursor: pointer;
      padding: 4px;
      border-radius: 4px;
      transition: background-color 0.2s;
    }
    
    .kebab-menu:hover {
      background-color: rgba(0, 0, 0, 0.05);
      color: var(--text-dark);
    }
    
    .kebab-dropdown {
      position: absolute;
      right: 0;
      top: 100%;
      background: white;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
      padding: 4px 0;
      min-width: 120px;
      display: none;
      z-index: 1000;
    }
    
    .kebab-dropdown.show {
      display: block;
    }
    
    .kebab-item {
      display: flex;
      align-items: center;
      gap: 8px;
      padding: 8px 16px;
      color: var(--text-dark);
      text-decoration: none;
      transition: background-color 0.2s;
      cursor: pointer;
    }
    
    .kebab-item:hover {
      background-color: var(--bg-light);
    }
    
    .kebab-item.delete {
      color: var(--danger);
    }
    
    .kebab-item i {
      width: 16px;
      text-align: center;
    }

    /* Update the announcement list section */
    .announcement-list {
      <?php foreach ($announcements as $a): ?>
        <div class="announcement-item" data-id="<?php echo $a['id']; ?>" style="background:#F9F5FF;padding:12px;border-radius:8px;margin-bottom:12px;">
          <div class="announcement-header">
            <div style="font-weight:600;">Announcement by <?php echo htmlspecialchars($a['first_name'] . ' ' . $a['last_name']); ?> 
              <span style="color:#aaa;font-weight:400;font-size:12px;">
                (<?php echo time_elapsed_string($a['created_at']); ?>)
              </span>
            </div>
            <?php if ($_SESSION['user_id'] == $a['user_id']): ?>
            <div class="announcement-actions">
              <button class="kebab-menu" onclick="toggleKebabMenu(this)">
                <i class="fas fa-ellipsis-v"></i>
              </button>
              <div class="kebab-dropdown">
                <div class="kebab-item" onclick="editAnnouncement(<?php echo $a['id']; ?>)">
                  <i class="fas fa-edit"></i>
                  <span>Edit</span>
                </div>
                <div class="kebab-item delete" onclick="deleteAnnouncement(<?php echo $a['id']; ?>)">
                  <i class="fas fa-trash"></i>
                  <span>Delete</span>
                </div>
              </div>
            </div>
            <?php endif; ?>
          </div>
          <div class="announcement-content" style="margin-top:4px;white-space:pre-line;"><?php echo $a['content']; ?></div>
        </div>
      <?php endforeach; ?>
    </div>

    /* Add these styles in the <style> section */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      align-items: center;
      justify-content: center;
    }
    
    .modal.show {
      display: flex;
    }
    
    .modal-content {
      background: white;
      padding: 24px;
      border-radius: 12px;
      width: 90%;
      max-width: 600px;
      position: relative;
    }
    
    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 16px;
    }
    
    .modal-title {
      font-size: 1.25rem;
      font-weight: 600;
      color: var(--text-dark);
    }
    
    .close-modal {
      background: none;
      border: none;
      font-size: 1.5rem;
      color: var(--text-light);
      cursor: pointer;
      padding: 4px;
    }
    
    .close-modal:hover {
      color: var(--text-dark);
    }
    
    .modal-body {
      margin-bottom: 24px;
    }
    
    .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: 12px;
    }
    
    .btn-secondary {
      background: var(--bg-light);
      color: var(--text-dark);
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .btn-secondary:hover {
      background: #e0e0e0;
    }
    
    .btn-danger {
      background: var(--danger);
      color: white;
      border: none;
      padding: 8px 16px;
      border-radius: 6px;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .btn-danger:hover {
      background: #dc3545;
    }

    body[data-theme='dark'] .theme-section {
      background-color: rgba(30, 30, 30, 0.96);
      color: #fff;
      border: 1px solid #333;
      box-shadow: 0 2px 8px rgba(0,0,0,0.25);
    }

    body[data-theme='dark'] .theme-section .section-label {
      color: #111;
    }

    .quiz-item {
      padding: 16px;
      border-bottom: 1px solid var(--border-light);
    }

    .quiz-item:last-child {
      border-bottom: none;
    }

    .quiz-header h4 {
      margin-bottom: 8px;
      color: var(--text-dark);
    }

    .quiz-meta {
      display: flex;
      flex-direction: column;
      gap: 4px;
      font-size: 14px;
      color: var(--text-light);
      margin-bottom: 12px;
    }

    .quiz-actions {
      display: flex;
      gap: 8px;
    }

    .btn-danger {
      background-color: var(--danger);
      color: white;
    }

    .btn-danger:hover {
      background-color: #dc3545;
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
        
        <!-- Classes Dropdown -->
        <div class="dropdown">
          <div class="dropdown-toggle <?php echo $classId > 0 ? 'active' : ''; ?>" onclick="toggleDropdown(this)">
            <div style="display: flex; align-items: center; gap: var(--space-sm);">
              <i class="fas fa-chalkboard-teacher"></i>
              <span>My Classes</span>
            </div>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="dropdown-menu <?php echo $classId > 0 ? 'show' : ''; ?>">
            <?php if (empty($classes)): ?>
              <div class="dropdown-item" style="padding: var(--space-sm);">
                No classes yet
              </div>
            <?php else: ?>
              <?php foreach ($classes as $classItem): ?>
                <a href="class_details.php?id=<?php echo $classItem['id']; ?>" class="dropdown-item <?php echo $classItem['id'] == $classId ? 'active' : ''; ?>">
                  <div style="display: flex; align-items: center;">
                    <div class="profile-initial">
                      <?php 
                        // Get first letter of each word in class name
                        $words = explode(' ', $classItem['class_name']);
                        $initials = '';
                        foreach ($words as $word) {
                          $initials .= strtoupper(substr($word, 0, 1));
                          if (strlen($initials) >= 2) break;
                        }
                        echo $initials;
                      ?>
                    </div>
                    <div>
                      <div class="class-name"><?php echo htmlspecialchars($classItem['class_name']); ?></div>
                      <div class="class-section"><?php echo htmlspecialchars($classItem['section']); ?></div>
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
        <div class="section-title">Content</div>
        <a href="demo02_v10/teacher_flashcard.php" class="nav-item">
          <i class="fas fa-layer-group"></i>
          <span>Flashcard Decks</span>
        </a>
        <a href="create_quiz.php" class="nav-item">
          <i class="fas fa-question-circle"></i>
          <span>Create Quiz</span>
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

    <main class="main-content">
      <header class="header">
        <h1 class="header-title"><?php echo htmlspecialchars($class['class_name']); ?></h1>
        <div class="header-actions">
          <a href="teacher_dashboard.php" class="header-btn">
            <i class="fas fa-arrow-left"></i>
          </a>
          <button class="header-btn">
            <i class="fas fa-ellipsis-v"></i>
          </button>
        </div>
      </header>

      <div class="class-nav">
        <div class="class-nav-item active" data-tab="overview">
          <i class="fas fa-chalkboard"></i>
          <span><?php echo htmlspecialchars($class['class_name']); ?></span>
        </div>
        <div class="class-nav-item" data-tab="quizzes">
          <i class="fas fa-question-circle"></i>
          <span>Quizzes</span>
        </div>
        <div class="class-nav-item" data-tab="people">
          <i class="fas fa-users"></i>
          <span>People</span>
        </div>
        <div class="class-nav-item" data-tab="grades">
          <i class="fas fa-chart-bar"></i>
          <span>Grades</span>
        </div>
      </div>

      <div class="tab-content active" id="overview-tab">
        <!-- Theme Preview Box - Show for all classes -->
        <div class="card" style="margin-bottom: var(--space-md);">
          <div class="card-header">
            <h3 class="card-title">Class Theme</h3>
            <div class="theme-actions">
              <form id="backgroundUploadForm" method="POST" enctype="multipart/form-data" style="display: inline;">
                <label for="bgImageUpload" class="btn btn-secondary">
                  <i class="fas fa-image"></i>
                  Upload Background
                </label>
                <input type="file" id="bgImageUpload" name="background_image" accept="image/*" style="display: none;" onchange="handleBackgroundUpload(event)">
                <button type="button" id="saveBackgroundBtn" class="btn btn-primary" style="display: none;" onclick="saveBackground()">
                  <i class="fas fa-save"></i>
                  Save Background
                </button>
                <button type="button" id="cancelBackgroundBtn" class="btn btn-secondary" style="display: none;" onclick="cancelBackground()">
                  <i class="fas fa-times"></i>
                  Cancel
                </button>
                <input type="hidden" id="tempImage" name="temp_image">
              </form>
            </div>
          </div>
          <div class="card-content">
            <div class="theme-preview-box" id="themePreviewBox" style="background-image: url('<?php echo !empty($class['background_image']) ? htmlspecialchars($class['background_image']) : ''; ?>'); background-color: <?php echo !empty($class['background_image']) ? 'transparent' : '#7F56D9'; ?>; background-size: cover; background-position: center; background-repeat: no-repeat;">
              <div class="theme-section">
                <span class="section-label">Section: <?php echo htmlspecialchars($class['section']); ?></span>
              </div>
            </div>
          </div>
        </div>

        <div class="card" style="margin-bottom: var(--space-lg);">
          <div class="card-header">
            <h3 class="card-title">Class Code</h3>
          </div>
          <div class="card-content">
            <div class="class-code-container">
              <div class="class-code">
                <span>Share this code with your students:</span>
                <strong><?php echo htmlspecialchars($class['class_code']); ?></strong>
              </div>
              <button class="btn btn-secondary" onclick="copyClassCode()">
                <i class="fas fa-copy"></i>
                Copy
              </button>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Announcements</h3>
            <?php if ($_SESSION['role'] === 'teacher'): ?>
              <button class="btn btn-primary" onclick="document.getElementById('announcementForm').style.display='block'">
                <i class="fas fa-plus"></i>
                New Announcement
              </button>
            <?php endif; ?>
          </div>
          <div class="card-content">
            <!-- Announcement Form (hidden by default) -->
            <?php if ($_SESSION['role'] === 'teacher'): ?>
            <form id="announcementForm" method="POST" style="display:none; margin-bottom: 16px; position:relative;">
              <div style="margin-bottom:8px;">
                <label style="font-weight:500;">For</label><br>
                <div class="for-dropdown-container">
                  <button type="button" id="classDropdownBtn" class="for-dropdown-btn" aria-haspopup="listbox" aria-expanded="false" onclick="toggleClassDropdown()">
                    <span id="selectedClassesLabel"></span>
                    <i class="fas fa-chevron-down for-dropdown-icon"></i>
                  </button>
                  <div id="classDropdown" class="for-dropdown-list" role="listbox" aria-multiselectable="true" tabindex="-1">
                    <?php if (empty($allClasses)): ?>
                      <div class="for-dropdown-item" style="color:#888;">No classes found. <a href='create_class.php'>Create a class</a>.</div>
                    <?php else: ?>
                      <?php foreach ($allClasses as $c): ?>
                        <div class="for-dropdown-item" data-class-id="<?php echo $c['id']; ?>" tabindex="0">
                          <input type="checkbox" name="announcement_classes[]" value="<?php echo $c['id']; ?>" class="for-dropdown-checkbox classCheckbox" <?php echo $c['id'] == $currentClassId ? 'checked' : ''; ?> onchange="updateSelectedClassesLabel()" id="class_cb_<?php echo $c['id']; ?>">
                          <label for="class_cb_<?php echo $c['id']; ?>" style="margin:0;cursor:pointer;"><b><?php echo htmlspecialchars($c['class_name']); ?></b> <span style="color:#888;">(<?php echo htmlspecialchars($c['section']); ?>)</span></label>
                          <span class="for-dropdown-checkmark"><i class="fas fa-check"></i></span>
                        </div>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </div>
                </div>
              </div>
              <div style="margin-bottom:8px;">
                <div id="editor" style="background:#f9f9f9;border-radius:8px;padding:8px;min-height:80px;"></div>
                <input type="hidden" name="announcement_content" id="announcement_content">
              </div>
              <div style="display:flex;gap:8px;">
                <button type="submit" name="new_announcement" class="btn btn-primary" onclick="return submitAnnouncement()">Post</button>
                <button type="button" class="btn btn-secondary" onclick="document.getElementById('announcementForm').style.display='none'">Cancel</button>
              </div>
            </form>
            <script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
            <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
            <script>
              var quill = new Quill('#editor', { theme: 'snow', modules: { toolbar: [['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['clean']] } });
              function submitAnnouncement() {
                document.getElementById('announcement_content').value = quill.root.innerHTML;
                return true;
              }
              function toggleClassDropdown() {
                var el = document.getElementById('classDropdown');
                var btn = document.getElementById('classDropdownBtn');
                var expanded = btn.getAttribute('aria-expanded') === 'true';
                el.classList.toggle('show', !expanded);
                btn.setAttribute('aria-expanded', !expanded);
                if (!expanded) {
                  el.focus();
                }
              }
              function updateSelectedClassesLabel() {
                var checkboxes = document.querySelectorAll('.classCheckbox:checked');
                var labels = [];
                checkboxes.forEach(function(checkbox) {
                  var label = checkbox.nextElementSibling.querySelector('b').textContent;
                  var section = checkbox.nextElementSibling.querySelector('span').textContent;
                  labels.push('<span class="for-dropdown-badge">' + label + ' ' + section + '</span>');
                });
                document.getElementById('selectedClassesLabel').innerHTML = labels.length ? labels.join(' ') : '<span style="color:#aaa;">Select class...</span>';
                // Highlight selected in dropdown
                document.querySelectorAll('.for-dropdown-item').forEach(function(item) {
                  var cb = item.querySelector('input[type=checkbox]');
                  if (cb && cb.checked) item.classList.add('selected');
                  else item.classList.remove('selected');
                });
              }
              document.addEventListener('DOMContentLoaded', function() {
                updateSelectedClassesLabel();
                // Close dropdown on outside click
                document.addEventListener('click', function(event) {
                  var dropdown = document.getElementById('classDropdown');
                  var btn = document.getElementById('classDropdownBtn');
                  if (dropdown && btn && !dropdown.contains(event.target) && !btn.contains(event.target)) {
                    dropdown.classList.remove('show');
                    btn.setAttribute('aria-expanded', 'false');
                  }
                });
                // Close dropdown on ESC
                document.getElementById('classDropdown').addEventListener('keydown', function(e) {
                  if (e.key === 'Escape') {
                    this.classList.remove('show');
                    document.getElementById('classDropdownBtn').setAttribute('aria-expanded', 'false');
                    document.getElementById('classDropdownBtn').focus();
                  }
                });
                // Keyboard navigation
                document.querySelectorAll('.for-dropdown-item').forEach(function(item) {
                  item.addEventListener('keydown', function(e) {
                    if (e.key === 'ArrowDown') {
                      e.preventDefault();
                      var next = item.nextElementSibling;
                      if (next) next.focus();
                    } else if (e.key === 'ArrowUp') {
                      e.preventDefault();
                      var prev = item.previousElementSibling;
                      if (prev) prev.focus();
                    } else if (e.key === ' ' || e.key === 'Enter') {
                      e.preventDefault();
                      var cb = item.querySelector('input[type=checkbox]');
                      if (cb) { cb.checked = !cb.checked; updateSelectedClassesLabel(); }
                    }
                  });
                });
              });
            </script>
            <?php endif; ?>
            <!-- Announcements List -->
            <?php if (empty($announcements)): ?>
              <div class="empty-state">
                <i class="fas fa-bullhorn"></i>
                <p>No announcements yet</p>
                <p class="text-muted">Create an announcement to share with your class</p>
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
                      <?php if ($_SESSION['user_id'] == $a['user_id']): ?>
                      <div class="announcement-actions">
                        <button class="kebab-menu" onclick="toggleKebabMenu(this)">
                          <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="kebab-dropdown">
                          <div class="kebab-item" onclick="editAnnouncement(<?php echo $a['id']; ?>)">
                            <i class="fas fa-edit"></i>
                            <span>Edit</span>
                          </div>
                          <div class="kebab-item delete" onclick="deleteAnnouncement(<?php echo $a['id']; ?>)">
                            <i class="fas fa-trash"></i>
                            <span>Delete</span>
                          </div>
                        </div>
                      </div>
                      <?php endif; ?>
                    </div>
                    <div class="announcement-content" style="margin-top:4px;white-space:pre-line;"><?php echo $a['content']; ?></div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>

        <div class="card" style="margin-top: var(--space-md);">
          <div class="card-header">
            <h3 class="card-title">Modules</h3>
            <?php if ($_SESSION['role'] === 'teacher'): ?>
              <form id="moduleUploadForm" method="POST" action="upload_module.php" enctype="multipart/form-data" style="display:inline;">
                <input type="file" name="module_file" accept="application/pdf" required style="display:none;" id="moduleFileInput" onchange="document.getElementById('moduleUploadForm').submit();">
                <label for="moduleFileInput" class="btn btn-primary" style="cursor:pointer;">
                  <i class="fas fa-upload"></i> Upload Module
                </label>
                <input type="hidden" name="class_id" value="<?php echo $classId; ?>">
                <input type="text" name="title" placeholder="Module Title" required style="margin-left:8px;padding:6px 10px;border-radius:6px;border:1px solid #ccc;">
              </form>
            <?php endif; ?>
          </div>
          <div class="card-content">
            <?php if (empty($modules)): ?>
              <div class="empty-state">
                <i class="fas fa-file-pdf"></i>
                <p>No modules uploaded yet</p>
                <p class="text-muted">Upload learning materials for your students</p>
              </div>
            <?php else: ?>
              <ul style="list-style:none;padding:0;">
                <?php foreach ($modules as $mod): ?>
                  <li style="margin-bottom:10px;display:flex;align-items:center;gap:10px;">
                    <button class="btn btn-danger btn-delete-module" title="Delete Module" data-module-id="<?php echo $mod['id']; ?>" data-file-path="<?php echo htmlspecialchars($mod['file_path']); ?>" style="padding:6px 10px;border-radius:6px;">
                      <i class="fas fa-trash"></i>
                    </button>
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
            <?php
            // Fetch quizzes for this class
            $stmt = $pdo->prepare("
                SELECT q.* 
                FROM quizzes q
                JOIN quiz_classes qc ON q.id = qc.quiz_id
                WHERE qc.class_id = ?
                ORDER BY q.created_at DESC
            ");
            $stmt->execute([$classId]);
            $quizzes = $stmt->fetchAll();
            
            if (empty($quizzes)): ?>
                <div class="empty-state">
                    <i class="fas fa-question-circle"></i>
                    <p>No quizzes yet</p>
                    <p class="text-muted">Create a quiz to assess your students</p>
                </div>
            <?php else: ?>
                <div class="quiz-list">
                    <?php foreach ($quizzes as $quiz): ?>
                        <div class="quiz-item" data-id="<?php echo $quiz['id']; ?>">
                            <div class="quiz-header">
                                <h4>
                                    <a href="quiz_details.php?id=<?php echo $quiz['id']; ?>">
                                        <?php echo htmlspecialchars($quiz['title']); ?>
                                    </a>
                                </h4>
                                <div class="quiz-meta">
                                    <span>Created <?php echo time_elapsed_string($quiz['created_at']); ?></span>
                                    <span><?php echo $quiz['description'] ? htmlspecialchars($quiz['description']) : 'No description'; ?></span>
                                </div>
                            </div>
                            <div class="quiz-actions">
                                <a href="edit_quiz.php?id=<?php echo $quiz['id']; ?>" class="btn btn-secondary">
                                    <i class="fas fa-edit"></i>
                                    Edit
                                </a>
                                <button class="btn btn-danger" onclick="deleteQuiz(<?php echo $quiz['id']; ?>, '<?php echo htmlspecialchars(addslashes($quiz['title'])); ?>')">
                                    <i class="fas fa-trash"></i>
                                    Delete
                                </button>
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
            <div class="class-code-container">
              <div class="class-code">
                <span>Class Code:</span>
                <strong><?php echo htmlspecialchars($class['class_code']); ?></strong>
              </div>
              <button class="btn btn-secondary" onclick="copyClassCode()">
                <i class="fas fa-copy"></i>
                Copy
              </button>
            </div>
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
                  <p class="text-muted">Share the class code with your students to let them join</p>
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
                      <?php if ($_SESSION['role'] === 'teacher'): ?>
                        <button class="btn btn-icon btn-remove-student" title="Remove Student" data-student-id="<?php echo $student['id']; ?>" style="background:none;border:none;color:var(--danger);font-size:20px;cursor:pointer;">
                          <i class="fas fa-user-minus"></i>
                        </button>
                      <?php endif; ?>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>

      <div class="tab-content" id="grades-tab">
        <div class="card">
          <div class="card-header">
            <h3 class="card-title">Grades</h3>
          </div>
          <div class="card-content">
            <?php
            // Fetch all quizzes for this class with total points
            $stmt = $pdo->prepare("
              SELECT q.id, q.title, SUM(qq.points) as total_points
              FROM quizzes q
              JOIN quiz_questions qq ON q.id = qq.quiz_id
              JOIN quiz_classes qc ON q.id = qc.quiz_id
              WHERE qc.class_id = ?
              GROUP BY q.id
              ORDER BY q.created_at DESC
            ");
            $stmt->execute([$classId]);
            $quizzes = $stmt->fetchAll();

            // Fetch all students in this class
            $stmt = $pdo->prepare("
              SELECT u.id, u.first_name, u.last_name 
              FROM users u
              JOIN class_students cs ON u.id = cs.student_id
              WHERE cs.class_id = ?
              ORDER BY u.last_name, u.first_name
            ");
            $stmt->execute([$classId]);
            $students = $stmt->fetchAll();

            if (empty($quizzes) || empty($students)): ?>
              <div class="empty-state">
                <i class="fas fa-chart-bar"></i>
                <p>No grades available yet</p>
                <p class="text-muted">
                  <?php 
                    if (empty($quizzes)) {
                      echo "Create quizzes to start collecting grades";
                    } else {
                      echo "Students need to complete quizzes to see grades";
                    }
                  ?>
                </p>
              </div>
            <?php else: ?>
              <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse;">
                  <thead>
                    <tr>
                      <th style="text-align: left; padding: 12px; border-bottom: 1px solid var(--border-light);">Student</th>
                      <?php foreach ($quizzes as $quiz): ?>
                        <th style="text-align: center; padding: 12px; border-bottom: 1px solid var(--border-light);" title="<?php echo htmlspecialchars($quiz['total_points'] . ' total points'); ?>">
                          <?php echo htmlspecialchars($quiz['title']); ?>
                          <div style="font-size: 12px; font-weight: normal; color: var(--text-light);">
                            (<?php echo (int)$quiz['total_points']; ?> pts)
                          </div>
                        </th>
                      <?php endforeach; ?>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($students as $student): ?>
                      <tr>
                        <td style="padding: 12px; border-bottom: 1px solid var(--border-light);">
                          <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?>
                        </td>
                        <?php foreach ($quizzes as $quiz): 
                          // Get student's result for this quiz
                          $stmt = $pdo->prepare("
                            SELECT qr.score, qr.total_questions 
                            FROM quiz_results qr
                            WHERE qr.quiz_id = ? AND qr.student_id = ?
                            LIMIT 1
                          ");
                          $stmt->execute([$quiz['id'], $student['id']]);
                          $result = $stmt->fetch();
                          
                          if ($result) {
                            $percentage = round(($result['score'] / $quiz['total_points']) * 100);
                          }
                        ?>
                          <td style="text-align: center; padding: 12px; border-bottom: 1px solid var(--border-light);">
                            <?php if ($result): ?>
                              <div style="margin-bottom: 4px; font-weight: 500; color: <?php 
                                if ($percentage >= 80) echo 'var(--success)';
                                elseif ($percentage >= 50) echo 'var(--warning)';
                                else echo 'var(--danger)';
                              ?>;">
                                <?php echo $percentage; ?>%
                              </div>
                              <div style="font-size: 12px; color: var(--text-medium);">
                                <?php echo $result['score']; ?>/<?php echo $quiz['total_points']; ?>
                              </div>
                            <?php else: ?>
                              <span style="color: var(--text-light);">-</span>
                            <?php endif; ?>
                          </td>
                        <?php endforeach; ?>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php endif; ?>
          </div>
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
        <button class="fab" onclick="showActionMenu()">
          <i class="fas fa-plus"></i>
        </button>
      </div>
      
      <div style="width: 25%;"></div>
      
      <a href="demo02_v10/teacher_flashcard.php" class="nav-item-mobile">
        <i class="fas fa-layer-group"></i>
        <span>Decks</span>
      </a>
    </nav>
  </div>

  <div class="action-menu" id="actionMenu">
    <a href="#" class="action-menu-item" onclick="addStudents()">
      <i class="fas fa-user-plus"></i>
      Add Students
    </a>
    <a href="#" class="action-menu-item" onclick="createAssignment()">
      <i class="fas fa-plus"></i>
      Create Assignment
    </a>
  </div>

  <script>
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

    function showActionMenu() {
      const menu = document.getElementById('actionMenu');
      menu.classList.toggle('show');
    }

    function addStudents() {
      // Implement add students functionality
      window.location.href = 'add_students.php?class_id=<?php echo $classId; ?>';
    }

    function createAssignment() {
      // Implement create assignment functionality
      window.location.href = 'create_assignment.php?class_id=<?php echo $classId; ?>';
    }

    // Close action menu when clicking outside
    document.addEventListener('click', function(event) {
      const menu = document.getElementById('actionMenu');
      const fab = document.querySelector('.fab');
      if (menu && !menu.contains(event.target) && fab && !fab.contains(event.target)) {
        menu.classList.remove('show');
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

    function copyClassCode() {
      const classCode = '<?php echo $class['class_code']; ?>';
      navigator.clipboard.writeText(classCode).then(() => {
        // Show a temporary success message
        const btn = document.querySelector('.class-code-container .btn');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> Copied!';
        btn.classList.add('btn-success');
        setTimeout(() => {
          btn.innerHTML = originalText;
          btn.classList.remove('btn-success');
        }, 2000);
      }).catch(err => {
        console.error('Failed to copy text: ', err);
      });
    }

    let currentPreviewImage = null;
    let originalBackgroundImage = null;

    function handleBackgroundUpload(event) {
      const file = event.target.files[0];
      if (file) {
        // Store original background if not already stored
        if (!originalBackgroundImage) {
          const themePreviewBox = document.getElementById('themePreviewBox');
          originalBackgroundImage = {
            image: themePreviewBox.style.backgroundImage,
            color: themePreviewBox.style.backgroundColor
          };
        }

        // Show preview
        const reader = new FileReader();
        reader.onload = function(e) {
          const themePreviewBox = document.getElementById('themePreviewBox');
          themePreviewBox.style.backgroundImage = `url(${e.target.result})`;
          themePreviewBox.style.backgroundColor = 'transparent';
          currentPreviewImage = e.target.result;
          
          // Show save and cancel buttons
          document.getElementById('saveBackgroundBtn').style.display = 'inline-flex';
          document.getElementById('cancelBackgroundBtn').style.display = 'inline-flex';
        };
        reader.readAsDataURL(file);
      }
    }

    function cancelBackground() {
      // Revert to original background
      const themePreviewBox = document.getElementById('themePreviewBox');
      themePreviewBox.style.backgroundImage = originalBackgroundImage.image;
      themePreviewBox.style.backgroundColor = originalBackgroundImage.color;
      currentPreviewImage = null;
      
      // Hide save and cancel buttons
      document.getElementById('saveBackgroundBtn').style.display = 'none';
      document.getElementById('cancelBackgroundBtn').style.display = 'none';
      
      // Clear the file input
      document.getElementById('bgImageUpload').value = '';
    }

    function saveBackground() {
      if (currentPreviewImage) {
        // Set the temporary image value
        document.getElementById('tempImage').value = currentPreviewImage;
        
        // Create and submit a form to save the background
        const form = document.getElementById('backgroundUploadForm');
        const saveInput = document.createElement('input');
        saveInput.type = 'hidden';
        saveInput.name = 'save_background';
        saveInput.value = '1';
        form.appendChild(saveInput);
        
        form.submit();
      }
    }

    document.querySelectorAll('.class-nav-item').forEach(item => {
      item.addEventListener('click', function() {
        // Remove active class from all nav items
        document.querySelectorAll('.class-nav-item').forEach(navItem => {
          navItem.classList.remove('active');
        });
        
        // Add active class to clicked nav item
        this.classList.add('active');
        
        // Hide all tab contents
        document.querySelectorAll('.tab-content').forEach(tab => {
          tab.classList.remove('active');
        });
        
        // Show selected tab content
        const tabId = this.getAttribute('data-tab') + '-tab';
        document.getElementById(tabId).classList.add('active');
      });
    });

    let currentEditId = null;
    
    function toggleKebabMenu(button) {
      document.querySelectorAll('.kebab-dropdown.show').forEach(menu => {
        if (menu !== button.nextElementSibling) {
          menu.classList.remove('show');
        }
      });
      const dropdown = button.nextElementSibling;
      dropdown.classList.toggle('show');
      document.addEventListener('click', function closeMenu(e) {
        if (!button.contains(e.target) && !dropdown.contains(e.target)) {
          dropdown.classList.remove('show');
          document.removeEventListener('click', closeMenu);
        }
      });
    }
    
    function editAnnouncement(id) {
      // Cancel any other edit in progress
      if (currentEditId !== null) cancelInlineEdit();
      currentEditId = id;
      const announcement = document.querySelector(`.announcement-item[data-id="${id}"]`);
      const contentDiv = announcement.querySelector('.announcement-content');
      originalContent = contentDiv.innerHTML;

      // Replace content with Quill editor
      contentDiv.innerHTML = `<div id="quill-edit-${id}" style="background:#f9f9f9;border-radius:8px;padding:8px;min-height:80px;"></div>
        <div style='margin-top:8px;display:flex;gap:8px;'>
          <button class='btn btn-primary' onclick='saveInlineEdit(${id})'>Save</button>
          <button class='btn btn-secondary' onclick='cancelInlineEdit()'>Cancel</button>
        </div>`;

      quillEdit = new Quill(`#quill-edit-${id}`, { theme: 'snow', modules: { toolbar: [['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['clean']] } });
      quillEdit.root.innerHTML = originalContent;
    }

    function saveInlineEdit(id) {
      if (!quillEdit) return;
      const content = quillEdit.root.innerHTML;
      fetch('update_announcement.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id: id, content: content })
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          const announcement = document.querySelector(`.announcement-item[data-id="${id}"]`);
          const contentDiv = announcement.querySelector('.announcement-content');
          contentDiv.innerHTML = content;
          currentEditId = null;
          quillEdit = null;
        } else {
          alert('Failed to update announcement: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while updating the announcement');
      });
    }

    function cancelInlineEdit() {
      if (currentEditId === null) return;
      const announcement = document.querySelector(`.announcement-item[data-id="${currentEditId}"]`);
      const contentDiv = announcement.querySelector('.announcement-content');
      contentDiv.innerHTML = originalContent;
      currentEditId = null;
      quillEdit = null;
    }
    
    function deleteAnnouncement(id) {
      if (confirm('Are you sure you want to delete this announcement?')) {
        fetch('delete_announcement.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const announcement = document.querySelector(`.announcement-item[data-id="${id}"]`);
            if (announcement) announcement.remove();
          } else {
            alert('Failed to delete announcement: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('An error occurred while deleting the announcement');
        });
      }
    }

    document.querySelectorAll('.btn-delete-module').forEach(btn => {
      btn.addEventListener('click', function() {
        const moduleId = this.getAttribute('data-module-id');
        const filePath = this.getAttribute('data-file-path');
        if (confirm('Are you sure you want to delete this module?')) {
          fetch('delete_module.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: moduleId, file_path: filePath })
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              this.closest('li').remove();
            } else {
              alert('Failed to delete module: ' + data.message);
            }
          })
          .catch(err => {
            alert('An error occurred while deleting the module.');
          });
        }
      });
    });

    document.querySelectorAll('.btn-remove-student').forEach(btn => {
      btn.addEventListener('click', function() {
        const studentId = this.getAttribute('data-student-id');
        const studentName = this.closest('.student-item').querySelector('.student-name').textContent;
        
        if (confirm(`Are you sure you want to remove ${studentName} from this class?`)) {
          fetch('remove_student.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ class_id: <?php echo $classId; ?>, student_id: studentId })
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              // Remove the student item from the DOM
              this.closest('.student-item').remove();
              
              // Update the student count
              const studentCount = document.querySelectorAll('.student-item').length;
              const sectionTitle = document.querySelector('.people-section .section-title');
              if (sectionTitle) {
                sectionTitle.textContent = `Students (${studentCount})`;
              }
              
              // If no students left, show empty state
              if (studentCount === 0) {
                const studentList = document.querySelector('.student-list');
                if (studentList) {
                  studentList.innerHTML = `
                    <div class="empty-state">
                      <i class="fas fa-users"></i>
                      <p>No students enrolled yet</p>
                      <p class="text-muted">Share the class code with your students to let them join</p>
                    </div>
                  `;
                }
              }
              
              // Show success message
              alert('Student removed successfully');
            } else {
              alert('Failed to remove student: ' + (data.message || 'Unknown error'));
            }
          })
          .catch(err => {
            console.error('Error:', err);
            alert('An error occurred while removing the student.');
          });
        }
      });
    });

    function deleteQuiz(quizId, quizTitle) {
        if (confirm('Are you sure you want to delete the quiz "' + quizTitle + '"? This action cannot be undone.')) {
            fetch('delete_quiz.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ id: quizId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Remove the quiz item from the DOM
                    document.querySelector(`.quiz-item[data-id="${quizId}"]`).remove();
                    
                    // Show success message
                    alert('Quiz deleted successfully');
                    
                    // If no quizzes left, show empty state
                    if (document.querySelectorAll('.quiz-item').length === 0) {
                        document.querySelector('.quiz-list').innerHTML = `
                            <div class="empty-state">
                                <i class="fas fa-question-circle"></i>
                                <p>No quizzes yet</p>
                                <p class="text-muted">Create a quiz to assess your students</p>
                            </div>
                        `;
                    }
                } else {
                    alert('Failed to delete quiz: ' + (data.message || 'Unknown error'));
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred while deleting the quiz');
            });
        }
    }

    function exportGrades() {
        // In a real implementation, this would generate a CSV or Excel file
        // For now, we'll just show an alert
        alert('Grade export functionality would generate a CSV file with all student grades in this class.');
        
        // Example of what a real implementation might look like:
        /*
        fetch('export_grades.php?class_id=<?php echo $classId; ?>')
          .then(response => response.blob())
          .then(blob => {
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = 'grades_class_<?php echo $classId; ?>_<?php echo date('Y-m-d'); ?>.csv';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
          });
        */
    }
  </script>
</body>
</html>