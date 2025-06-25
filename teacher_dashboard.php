<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require 'db.php';
require 'includes/theme.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

// Get theme for the page
$theme = getCurrentTheme();

// Get teacher ID
$teacherId = $_SESSION['user_id'];

// Fetch teacher's classes
$classes = [];
try {
    $stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ?");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching classes: " . $e->getMessage());
}

// Fetch recent activities
$activities = [];
try {
    $stmt = $pdo->prepare("SELECT a.*, c.class_name 
                          FROM activities a 
                          LEFT JOIN classes c ON a.group_id = c.id 
                          WHERE a.student_id = ? 
                          ORDER BY a.created_at DESC 
                          LIMIT 5");
    $stmt->execute([$teacherId]);
    $activities = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching activities: " . $e->getMessage());
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
    <title>Teacher Dashboard - LearnMate</title>
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
            background-color: var(--border-light);
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
            background-color: var(--primary);
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

        .btn-manage,
        .btn-delete {
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

        .btn-manage {
            background-color: var(--primary);
            color: white;
        }

        .btn-manage:hover {
            background-color: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(127, 86, 217, 0.2);
        }

        .btn-delete {
            background-color: #FEE2E2;
            color: #DC2626;
        }

        .btn-delete:hover {
            background-color: #FECACA;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.1);
        }

        .btn-manage i,
        .btn-delete i {
            font-size: 14px;
        }

        .btn-manage.loading,
        .btn-delete.loading {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

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

        .toast-notification {
            position: fixed;
            bottom: 20px;
            left: 50%;
            transform: translateX(-50%);
            padding: 12px 24px;
            border-radius: var(--radius-md);
            background-color: var(--text-dark);
            color: white;
            box-shadow: var(--shadow-lg);
            opacity: 0;
            transition: opacity 0.3s ease, transform 0.3s ease;
            z-index: 1000;
            max-width: 90%;
            text-align: center;
            pointer-events: none;
        }

        .toast-notification.show {
            opacity: 1;
            transform: translateX(-50%) translateY(-10px);
        }

        .toast-notification.success {
            background-color: var(--success);
        }

        .toast-notification.error {
            background-color: var(--danger);
        }

        .toast-notification.warning {
            background-color: var(--warning);
            color: var(--text-dark);
        }

        @media (min-width: 640px) {
            .classes-grid {
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

            .classes-grid {
                grid-template-columns: 1fr 1fr 1fr;
            }
        }

        @media (min-width: 1024px) {
            .classes-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
                <a href="teacher_dashboard.php" class="nav-item active">
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
                <h1 class="header-title">Teacher Dashboard</h1>
                <div class="header-actions">
                    <button class="header-btn" onclick="window.history.back()">
                        <i class="fas fa-arrow-left"></i>
                    </button>
                    <button class="header-btn">
                        <i class="fas fa-bell"></i>
                    </button>
                </div>
            </header>

            <h2 class="section-title-lg">
                <i class="fas fa-chalkboard-teacher"></i>
                <span>Your Classes</span>
            </h2>

            <div class="classes-grid">
                <?php foreach ($classes as $class): ?>
                    <?php
                    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM class_students WHERE class_id = ?");
                    $stmt->execute([$class['id']]);
                    $studentCount = $stmt->fetch()['count'];
                    ?>
                    <div class="class-card">
                        <div class="class-image">
                            <div class="class-initials">
                                <?php
                                $words = explode(' ', $class['class_name']);
                                $initials = '';
                                foreach ($words as $word) {
                                    $initials .= strtoupper(substr($word, 0, 1));
                                }
                                echo $initials;
                                ?>
                            </div>
                        </div>
                        <div class="class-content">
                            <h3 class="class-title"><?php echo htmlspecialchars($class['class_name']); ?></h3>
                            <p class="class-section"><?php echo htmlspecialchars($class['section']); ?></p>
                            <div class="class-meta">
                                <span><i class="fas fa-users"></i> <?php echo $studentCount; ?> Students</span>
                            </div>
                            <div class="class-actions">
                                <button onclick="window.location.href='class_details.php?id=<?php echo $class['id']; ?>'" 
                                    class="btn-manage">
                                    <i class="fas fa-cog"></i> Manage
                                </button>
                                <button onclick="deleteClass(<?php echo $class['id']; ?>, '<?php echo htmlspecialchars($class['class_name']); ?>')" 
                                    class="btn-delete" 
                                    data-class-id="<?php echo $class['id']; ?>">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <a href="create_class.php" class="class-card">
                    <div class="class-content" style="padding: var(--space-md); display: flex; flex-direction: column; align-items: center; justify-content: center; height: 100%;">
                        <i class="fas fa-plus" style="font-size: 24px; color: var(--text-light); margin-bottom: var(--space-sm);"></i>
                        <h3 class="class-title" style="text-align: center;">Create New Class</h3>
                    </div>
                </a>
            </div>

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
                                    if (isset($activity['class_name']) && $activity['class_name']):
                                        echo ' • ' . htmlspecialchars($activity['class_name']);
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

    <div class="bottom-nav-container">
        <nav class="bottom-nav">
            <a href="teacher_dashboard.php" class="nav-item-mobile active">
                <i class="fas fa-home"></i>
                <span>Home</span>
            </a>
            <a href="my_classes.php" class="nav-item-mobile">
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
                <a href="create_class.php" class="fab">
                    <i class="fas fa-plus"></i>
                </a>
            </div>

            <div style="width: 25%;"></div>

            <a href="demo02_v10/flashcard.php" class="nav-item-mobile">
                <i class="fas fa-layer-group"></i>
                <span>Decks</span>
            </a>
        </nav>
    </div>

    <script>
        // Toast notification function
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast-notification ${type}`;
            toast.textContent = message;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.classList.add('show');
                setTimeout(() => {
                    toast.classList.remove('show');
                    setTimeout(() => {
                        toast.remove();
                    }, 300);
                }, 3000);
            }, 100);
        }

        // Delete class function
        function deleteClass(classId, className) {
            if (!confirm(`Are you sure you want to permanently delete "${className}"? All class data will be removed.`)) {
                return;
            }

            const deleteBtn = document.querySelector(`.btn-delete[data-class-id="${classId}"]`);
            const originalBtnHTML = deleteBtn?.innerHTML;

            if (deleteBtn) {
                deleteBtn.classList.add('loading');
                deleteBtn.disabled = true;
                deleteBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Deleting...';
            }

            fetch('delete_class.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    class_id: classId
                })
            })
                .then(response => {
                    if (!response.ok) {
                        return response.json().then(err => {
                            throw new Error(err.message || 'Failed to delete class');
                        });
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        showToast('Class deleted successfully!', 'success');
                        // Remove the class card from the DOM
                        const classCard = document.querySelector(`.btn-delete[data-class-id="${classId}"]`)?.closest('.class-card');
                        if (classCard) {
                            classCard.style.opacity = '0';
                            setTimeout(() => {
                                classCard.remove();
                                // If no classes left, show message
                                if (document.querySelectorAll('.class-card').length <= 1) {
                                    const classesGrid = document.querySelector('.classes-grid');
                                    if (classesGrid) {
                                        classesGrid.innerHTML += `
                                            <div class="no-classes-message" style="grid-column: 1/-1; text-align: center; padding: 2rem;">
                                                <i class="fas fa-chalkboard-teacher" style="font-size: 3rem; color: var(--text-light); margin-bottom: 1rem;"></i>
                                                <h3>No classes found</h3>
                                                <p>Create your first class to get started</p>
                                                <a href="create_class.php" class="action-btn" style="margin-top: 1rem;">
                                                    <i class="fas fa-plus"></i> Create Class
                                                </a>
                                            </div>
                                        `;
                                    }
                                }
                            }, 300);
                        } else {
                            window.location.reload();
                        }
                    } else {
                        throw new Error(data.message || 'Failed to delete class');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast(error.message, 'error');
                    if (deleteBtn) {
                        deleteBtn.classList.remove('loading');
                        deleteBtn.disabled = false;
                        deleteBtn.innerHTML = originalBtnHTML;
                    }
                });
        }

        // Toggle dropdown menu
        function toggleDropdown(element) {
            element.classList.toggle('active');
            const menu = element.nextElementSibling;
            menu.classList.toggle('show');
        }
    </script>
</body>
</html>