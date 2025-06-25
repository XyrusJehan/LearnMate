<?php
session_start();
require 'db.php';
require 'includes/theme.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$studentId = $_SESSION['user_id'];

// Get group and folder IDs from URL
if (!isset($_GET['group_id']) || !isset($_GET['folder_id'])) {
    header('Location: student_group.php');
    exit();
}

$groupId = $_GET['group_id'];
$folderId = $_GET['folder_id'];

// Verify user is a member of the group
$stmt = $pdo->prepare("
    SELECT 1 FROM group_members 
    WHERE group_id = ? AND user_id = ?
");
$stmt->execute([$groupId, $studentId]);
if (!$stmt->fetch()) {
    header('Location: student_group.php');
    exit();
}

// Verify folder is shared in the group
$stmt = $pdo->prepare("
    SELECT sf.*, f.name as folder_name, u.first_name, u.last_name
    FROM shared_folders sf
    JOIN folders f ON sf.folder_id = f.id
    JOIN users u ON sf.shared_by = u.id
    WHERE sf.group_id = ? AND sf.folder_id = ?
");
$stmt->execute([$groupId, $folderId]);
$sharedFolder = $stmt->fetch();

if (!$sharedFolder) {
    header('Location: student_group.php');
    exit();
}

// Fetch flashcards in the shared folder
$stmt = $pdo->prepare("
    SELECT f.id, f.front_content, f.back_content, f.created_at
    FROM flashcards f
    WHERE f.folder_id = ?
    ORDER BY f.created_at DESC
");
$stmt->execute([$folderId]);
$flashcards = $stmt->fetchAll();

// Fetch classes for sidebar
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM classes c
    JOIN class_students cs ON c.id = cs.class_id
    WHERE cs.student_id = ?
");
$stmt->execute([$studentId]);
$classes = $stmt->fetchAll();

$theme = getCurrentTheme();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($sharedFolder['folder_name']); ?> - Shared Folder</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        :root {
            --primary: #7F56D9;
            --primary-light: #9E77ED;
            --primary-dark: #6941C6;
            --primary-extra-light: #F9F5FF;
            --secondary: #36BFFA;
            --success: #12B76A;
            --success-light: #ECFDF3;
            --warning: #F79009;
            --warning-light: #FFFAEB;
            --danger: #F04438;
            --danger-light: #FEF3F2;
            --text-dark: #101828;
            --text-medium: #475467;
            --text-light: #98A2B3;
            --text-extra-light: #D0D5DD;
            --bg-light: #F9FAFB;
            --bg-white: #FFFFFF;
            --border-light: #EAECF0;
            --border-medium: #D0D5DD;
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
            --space-2xl: 40px;
            --space-3xl: 48px;
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
            background-color: var(--primary-extra-light);
            color: var(--primary-dark);
        }

        .nav-item.active {
            background-color: var(--primary-extra-light);
            color: var(--primary-dark);
            font-weight: 600;
        }

        .nav-item i {
            width: 20px;
            text-align: center;
        }

        /* Dropdown Styles */
        .dropdown {
            position: relative;
            margin-bottom: var(--space-xs);
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
            transition: var(--transition);
        }

        .header-btn:hover {
            background-color: var(--border-light);
        }

        /* Folder Info */
        .folder-info {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            margin-bottom: var(--space-md);
            background-color: var(--bg-white);
            padding: var(--space-md);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            position: relative;
        }

        .back-btn {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            transition: var(--transition);
            font-weight: 500;
            color: var(--text-medium);
            text-decoration: none;
            background-color: var(--bg-light);
            border: 1px solid var(--border-light);
        }

        .back-btn:hover {
            background-color: var(--primary-light);
            color: white;
            border-color: var(--primary-light);
        }

        .folder-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 24px;
        }

        .folder-details h1 {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .folder-meta {
            color: var(--text-light);
            font-size: 14px;
        }

        /* Flashcards Grid */
        .flashcards-grid {
            display: grid;
            gap: var(--space-md);
        }

        .flashcard {
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            box-shadow: var(--shadow-sm);
            transition: var(--transition);
        }

        .flashcard:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        .flashcard-front {
            font-weight: 600;
            margin-bottom: var(--space-md);
            color: var(--text-dark);
        }

        .flashcard-back {
            color: var(--text-medium);
            font-size: 0.95em;
            line-height: 1.6;
        }

        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            color: var(--text-light);
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
        }

        .empty-state i {
            font-size: 48px;
            margin-bottom: var(--space-md);
            opacity: 0.5;
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

            .flashcards-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

        @media (min-width: 1024px) {
            .flashcards-grid {
                grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
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
                        <a href="join_class.php" class="dropdown-item" style="margin-top: var(--space-sm); color: var(--primary);">
                            <i class="fas fa-plus"></i> Join New Class
                        </a>
                    </div>
                </div>
                
                <a href="student_group.php" class="nav-item active">
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
                <div style="display: flex; align-items: center; gap: var(--space-md);">
                    <a href="group_details.php?id=<?php echo $groupId; ?>" class="header-btn">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <h1 class="header-title">Shared Folder</h1>
                </div>
            </header>

            <div class="folder-info">
                <div class="folder-icon">
                    <i class="fas fa-folder"></i>
                </div>
                <div class="folder-details">
                    <h1><?php echo htmlspecialchars($sharedFolder['folder_name']); ?></h1>
                    <div class="folder-meta">
                        Shared by <?php echo htmlspecialchars($sharedFolder['first_name'] . ' ' . $sharedFolder['last_name']); ?> • 
                        <?php echo count($flashcards); ?> flashcards
                    </div>
                </div>
                <a href="student_group_details.php?id=<?php echo $groupId; ?>" class="back-btn">
                    <i class="fas fa-arrow-left"></i> Back to Group
                </a>
            </div>

            <?php if (empty($flashcards)): ?>
                <div class="empty-state">
                    <i class="fas fa-folder-open"></i>
                    <p>This folder is empty</p>
                </div>
            <?php else: ?>
                <div class="flashcards-grid">
                    <?php foreach ($flashcards as $flashcard): ?>
                        <div class="flashcard">
                            <div class="flashcard-front">
                                <?php echo htmlspecialchars($flashcard['front_content']); ?>
                            </div>
                            <div class="flashcard-back">
                                <?php echo htmlspecialchars($flashcard['back_content']); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script>
        // Dropdown toggle
        function toggleDropdown(element) {
            element.classList.toggle('active');
            const menu = element.parentElement.querySelector('.dropdown-menu');
            menu.classList.toggle('show');
        }
    </script>
</body>
</html>