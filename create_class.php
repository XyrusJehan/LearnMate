<?php
// create_class.php
session_start();
require 'db.php';
require 'includes/theme.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id'])) {
    header('Location: teacher_dashboard.php');
    exit();
}

if ($_SESSION['role'] !== 'teacher') {
    header('Location: unauthorized.php');
    exit();
}

// Get theme for the page
$theme = getCurrentTheme();

$teacherId = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch teacher's classes
try {
    $stmt = $pdo->prepare("SELECT id, class_name, section FROM classes WHERE teacher_id = ? ORDER BY created_at DESC");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Error fetching classes: ' . $e->getMessage();
    $classes = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $className = trim($_POST['class_name']);
    $section = trim($_POST['section']);
    
    // Validate inputs
    if (empty($className)) {
        $error = 'Class name is required';
    } elseif (empty($section)) {
        $error = 'Section is required';
    } else {
        try {
            // Insert new class into database (removed class_code column)
            $stmt = $pdo->prepare("INSERT INTO classes (class_name, section, teacher_id) VALUES (?, ?, ?)");
            $stmt->execute([$className, $section, $teacherId]);
            
            // Get the ID of the newly created class
            $classId = $pdo->lastInsertId();
            
            // Log activity
            $stmt = $pdo->prepare("INSERT INTO activities (student_id, description, icon) VALUES (?, ?, ?)");
            $stmt->execute([$teacherId, "Created new class: $className", "chalkboard-teacher"]);
            
            // Redirect to the class details page
            header("Location: class_details.php?id=" . $classId);
            exit();
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create New Class - LearnMate</title>
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

    .form-container {
      max-width: 600px;
      margin: 0 auto;
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: var(--space-lg);
      margin-top: var(--space-md);
    }

    .form-title {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: var(--space-lg);
      color: var(--primary-dark);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .form-title i {
      color: var(--primary);
    }

    .form-group {
      margin-bottom: var(--space-lg);
    }

    .form-label {
      display: block;
      font-weight: 500;
      margin-bottom: var(--space-sm);
      color: var(--text-dark);
    }

    .form-control {
      width: 100%;
      padding: var(--space-sm) var(--space-md);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      font-size: 14px;
      transition: var(--transition);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary-light);
      box-shadow: 0 0 0 3px rgba(127, 86, 217, 0.1);
    }

    .btn {
      padding: var(--space-sm) var(--space-lg);
      border-radius: var(--radius-md);
      border: none;
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: var(--space-sm);
    }

    .btn-primary {
      background-color: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
      transform: translateY(-1px);
    }

    .btn-secondary {
      background-color: white;
      color: var(--primary);
      border: 1px solid var(--border-light);
    }

    .btn-secondary:hover {
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }

    .form-actions {
      display: flex;
      justify-content: flex-end;
      gap: var(--space-sm);
      margin-top: var(--space-xl);
    }

    .alert {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-lg);
      font-size: 14px;
    }

    .alert-success {
      background-color: #ECFDF3;
      color: #027A48;
      border: 1px solid #ABEFC6;
    }

    .alert-danger {
      background-color: #FEF3F2;
      color: #B42318;
      border: 1px solid #FECDCA;
    }

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
      .dropdown {
    position: relative;
    margin-bottom: var(--space-xs);
}

.dropdown-toggle {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: var(--space-sm) var(--space-md);
    border-radius: var(--radius-md);
    color: var(--text-medium);
    font-weight: 500;
    cursor: pointer;
    transition: var(--transition);
    background: none;
    border: none;
    text-align: left;
}

.dropdown-toggle:hover {
    background-color: rgba(159, 122, 234, 0.08);
    color: var(--primary-dark);
}

.dropdown-toggle.active {
    background-color: rgba(159, 122, 234, 0.08);
    color: var(--primary-dark);
}

.dropdown-toggle i.fa-chevron-down {
    transition: transform 0.3s ease;
    font-size: 12px;
    color: var(--text-light);
}

.dropdown-toggle.active i.fa-chevron-down {
    transform: rotate(180deg);
    color: var(--primary);
}

.dropdown-menu {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
    padding-left: var(--space-md);
    background-color: var(--bg-white);
    border-radius: 0 0 var(--radius-md) var(--radius-md);
    box-shadow: none;
}

.dropdown-menu.show {
    max-height: 500px;
    padding: var(--space-xs) 0;
    margin-top: var(--space-xs);
    border-left: 2px solid var(--border-light);
}

.dropdown-item {
    display: block;
    padding: var(--space-xs) var(--space-md);
    text-decoration: none;
    color: var(--text-medium);
    transition: var(--transition);
    font-size: 0.875rem;
}

.dropdown-item:hover {
    background-color: rgba(159, 122, 234, 0.05);
    color: var(--primary-dark);
}

.profile-initial {
    width: 24px;
    height: 24px;
    border-radius: var(--radius-full);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 0.7rem;
    font-weight: 600;
    flex-shrink: 0;
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
        
        <!-- Updated Classes Dropdown -->
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
                    <div class="dropdown-item" style="padding: var(--space-sm); color: var(--text-light);">
                        No classes yet
                    </div>
                <?php else: ?>
                    <?php foreach ($classes as $class): ?>
                        <a href="class_details.php?id=<?php echo $class['id']; ?>" class="dropdown-item">
                            <div style="display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-xs) 0;">
                                <div class="profile-initial" style="background-color: var(--primary-light);">
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
                                <div style="overflow: hidden;">
                                    <div class="class-name" style="font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </div>
                                    <div class="class-section" style="font-size: 0.75rem; color: var(--text-light);">
                                        <?php echo htmlspecialchars($class['section']); ?>
                                    </div>
                                </div>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
                <div style="margin-top: var(--space-sm); border-top: 1px solid var(--border-light); padding-top: var(--space-sm);">
                    <a href="create_class.php" class="dropdown-item" style="display: flex; align-items: center; gap: var(--space-sm); color: var(--primary); font-weight: 500;">
                        <i class="fas fa-plus" style="width: 20px; text-align: center;"></i>
                        <span>Create New Class</span>
                    </a>
                </div>
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
              <i class="fas fa-layer-group"></i>  <!-- Updated to match group details -->
              <span>Flashcard Decks</span>
          </a>
          <a href="create_quiz.php" class="nav-item">
            <i class="fas fa-question-circle"></i>
            <span>Create Quiz</span>
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


    <main class="main-content">
      <header class="header">
        <h1 class="header-title">Create New Class</h1>
        <div class="header-actions">
          <button class="header-btn">
            <i class="fas fa-arrow-left"></i>
          </button>
        </div>
      </header>

      <div class="form-container">
        <?php if ($error): ?>
          <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
          </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
          <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success; ?>
          </div>
        <?php endif; ?>
        
        <h2 class="form-title">
          <i class="fas fa-chalkboard-teacher"></i>
          <span>New Class Information</span>
        </h2>
        
        <form method="POST" action="create_class.php">
          <div class="form-group">
            <label for="class_name" class="form-label">Class Name</label>
            <input type="text" id="class_name" name="class_name" class="form-control" 
                   placeholder="Enter class name (e.g., Mathematics 101)" required 
                   value="<?php echo isset($className) ? htmlspecialchars($className) : ''; ?>">
          </div>
          
          <div class="form-group">
            <label for="section" class="form-label">Section</label>
            <input type="text" id="section" name="section" class="form-control" 
                   placeholder="Enter section (e.g., A, B, C)" required
                   value="<?php echo isset($section) ? htmlspecialchars($section) : ''; ?>">
          </div>
          
          <div class="form-actions">
            <a href="teacher_dashboard.php" class="btn btn-secondary">
              <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn btn-primary">
              <i class="fas fa-save"></i> Create Class
            </button>
          </div>
        </form>
      </div>
    </main>
  </div>

  <div class="bottom-nav-container">
    <nav class="bottom-nav">
      <a href="teacher_dashboard.php" class="nav-item-mobile">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="my_classes.php" class="nav-item-mobile active">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Classes</span>
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
        <button class="fab">
          <i class="fas fa-plus"></i>
        </button>
      </div>
      
      <div style="width: 25%;"></div>
      
      <a href="#" class="nav-item-mobile">
        <i class="fas fa-book"></i>
        <span>Decks</span>
      </a>
      <a href="#" class="nav-item-mobile">
        <i class="fas fa-chart-line"></i>
        <span>Analytics</span>
      </a>
    </nav>
  </div>
</body>
<script>
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
</script>
</html>