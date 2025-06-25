<?php
session_start();
require 'db.php'; // This should provide $pdo connection
require 'includes/theme.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

// Get user's theme preference
$theme = getCurrentTheme();

// Handle theme change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_theme'])) {
    $new_theme = $_POST['theme'] ?? 'light';
    if (setUserTheme($pdo, $_SESSION['user_id'], $new_theme)) {
        $theme = $new_theme;
        $_SESSION['theme'] = $new_theme;
    }
}

$host = 'switchyard.proxy.rlwy.net';
$dbname = 'railway';
$username = 'root';
$password = 'mfwZMSewsBKfBJQOdeOmyqMZoRGwewMI'; // From MYSQL_ROOT_PASSWORD
$port = 47909;

$mysqli = new mysqli($host, $username, $password, $dbname, $port);



$users = [];

try {
    $query = "
        SELECT 
            u.id,
            u.first_name,
            u.last_name,
            u.email,
            u.role,
            u.created_at,
            COUNT(DISTINCT f.id) as total_flashcards,
            GROUP_CONCAT(
                CONCAT(
                    f.front_content, '|',
                    f.back_content, '|',
                    f.created_at
                ) ORDER BY f.created_at DESC
            ) as flashcard_data
        FROM users u
        LEFT JOIN folders fo ON u.id = fo.user_id
        LEFT JOIN flashcards f ON fo.id = f.folder_id
        WHERE u.role != 'admin'
        GROUP BY u.id, u.first_name, u.last_name, u.email, u.role, u.created_at
        ORDER BY u.role, u.first_name, u.last_name
    ";

    // Use the PDO connection from db.php
    $stmt = $pdo->prepare($query);
    
    if (!$stmt) {
        throw new Exception("Query preparation failed: " . $pdo->errorInfo()[2]);
    }

    if (!$stmt->execute()) {
        throw new Exception("Query execution failed: " . $stmt->errorInfo()[2]);
    }

    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Logs - LearnMate</title>
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
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }

        body {
            background-color: var(--bg-light);
            color: var(--text-dark);
            line-height: 1.6;
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

        .logo-text {
            font-weight: 600;
            font-size: 18px;
            color: white;
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

        .content-section {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            box-shadow: var(--shadow-sm);
            margin-bottom: var(--space-lg);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-md);
            border-bottom: 1px solid var(--border-light);
        }

        .section-title-lg {
            font-size: 20px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            color: var(--primary);
        }

        .activity-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
        }

        .activity-item {
            background: var(--bg-white);
            border-left: 4px solid var(--primary);
            padding: var(--space-lg);
            border-radius: var(--radius-lg);
            transition: var(--transition);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-md);
            box-shadow: var(--shadow-sm);
        }

        .activity-item:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }

        .activity-content {
            flex: 1;
        }

        .activity-description h4 {
            font-size: 1.1rem;
            color: var(--text-dark);
            margin-bottom: var(--space-sm);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .user-role {
            background-color: rgba(127, 86, 217, 0.1);
            color: var(--primary);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        .activity-meta {
            display: flex;
            flex-wrap: wrap;
            gap: var(--space-md);
            color: var(--text-medium);
            font-size: 0.9rem;
        }

        .activity-type {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            background-color: var(--bg-light);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            transition: var(--transition);
        }

        .activity-type:hover {
            background-color: rgba(127, 86, 217, 0.1);
            color: var(--primary);
        }

        .activity-type i {
            font-size: 0.9rem;
        }

        .activity-date {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            color: var(--text-light);
        }

        /* Enhanced Section Styles */
        .role-section {
            margin-bottom: var(--space-xl);
            animation: fadeIn 0.5s ease-out;
        }

        .role-section h3 {
            color: var(--primary);
            margin-bottom: var(--space-lg);
            padding-bottom: var(--space-sm);
            border-bottom: 2px solid var(--primary-light);
            font-size: 1.2rem;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .role-section h3::before {
            content: '';
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: var(--primary);
            border-radius: var(--radius-full);
        }

        /* No Activities State */
        .no-activities {
            text-align: center;
            padding: var(--space-xl);
            background: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-top: var(--space-lg);
        }

        .no-activities p {
            color: var(--text-medium);
            font-size: 1.1rem;
            margin-bottom: var(--space-sm);
        }

        .no-activities i {
            font-size: 3rem;
            color: var(--text-light);
            margin-bottom: var(--space-md);
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Dark Mode Enhancements */
        [data-theme="dark"] .activity-item {
            background: var(--bg-white);
            box-shadow: var(--shadow-md);
        }

        [data-theme="dark"] .activity-type {
            background-color: rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] .activity-type:hover {
            background-color: rgba(127, 86, 217, 0.2);
        }

        /* Responsive Improvements */
        @media (max-width: 768px) {
            .activity-meta {
                flex-direction: column;
                gap: var(--space-sm);
            }

            .activity-item {
                padding: var(--space-md);
            }

            .activity-description h4 {
                font-size: 1rem;
            }
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

        .notification {
            display: none;
            position: fixed;
            top: 20px;
            right: 20px;
            background-color: var(--success);
            color: white;
            padding: var(--space-md) var(--space-lg);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        @keyframes slideOut {
            from {
                transform: translateX(0);
                opacity: 1;
            }
            to {
                transform: translateX(100%);
                opacity: 0;
            }
        }

        .theme-toggle-container {
            display: flex;
            align-items: center;
            gap: var(--space-md);
            margin-bottom: var(--space-md);
            padding: var(--space-sm) var(--space-md);
        }

        .theme-label {
            font-weight: 500;
            color: var(--text-dark);
        }

        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 34px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: .4s;
            border-radius: 34px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 26px;
            width: 26px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--primary);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(26px);
        }

        .flashcards-section {
            margin-top: var(--space-md);
            padding-top: var(--space-md);
            border-top: 1px solid var(--border-light);
        }

        .flashcards-section h5 {
            color: var(--text-medium);
            font-size: 0.9rem;
            margin-bottom: var(--space-sm);
        }

        .flashcard-item {
            background: var(--bg-light);
            border-radius: var(--radius-md);
            padding: var(--space-md);
            margin-bottom: var(--space-sm);
        }

        .flashcard-content {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }

        .flashcard-front, .flashcard-back {
            font-size: 0.9rem;
            color: var(--text-dark);
        }

        .flashcard-date {
            font-size: 0.8rem;
            color: var(--text-light);
            margin-top: var(--space-xs);
        }

        .flashcard-front strong, .flashcard-back strong {
            color: var(--primary);
            margin-right: var(--space-xs);
        }

        /* Table Styles */
        .table-responsive {
            overflow-x: auto;
            margin: 0 -1rem;
            padding: 0 1rem;
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            overflow: hidden;
            box-shadow: var(--shadow-sm);
        }

        .users-table th,
        .users-table td {
            padding: var(--space-md);
            text-align: left;
            border-bottom: 1px solid var(--border-light);
        }

        .users-table th {
            background-color: var(--bg-light);
            font-weight: 600;
            color: var(--text-dark);
            white-space: nowrap;
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .users-table tr:hover {
            background-color: var(--bg-light);
        }

        .flashcard-preview {
            background-color: var(--bg-light);
            padding: var(--space-sm);
            border-radius: var(--radius-sm);
            margin-bottom: var(--space-xs);
            font-size: 0.9rem;
        }

        .flashcard-preview:last-child {
            margin-bottom: 0;
        }

        .flashcard-front,
        .flashcard-back {
            margin-bottom: var(--space-xs);
        }

        .flashcard-date {
            font-size: 0.8rem;
            color: var(--text-light);
        }

        .user-role {
            background-color: rgba(127, 86, 217, 0.1);
            color: var(--primary);
            padding: var(--space-xs) var(--space-sm);
            border-radius: var(--radius-sm);
            font-size: 0.75rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            display: inline-block;
        }

        @media (max-width: 768px) {
            .users-table {
                font-size: 0.9rem;
            }
            
            .users-table th,
            .users-table td {
                padding: var(--space-sm);
            }
            
            .flashcard-preview {
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
    <!-- Add notification div for theme changes -->
    <div id="notification" class="notification"></div>

    <div class="app-container">
        <!-- Sidebar - Desktop -->
        <?php include 'includes/admin_sidebar.php'; ?>

        <!-- Main Content Area -->
        <main class="main-content">
            <!-- Mobile Header -->
            <header class="header">
                <h1 class="header-title">Activity Logs</h1>
                <div class="header-actions">
                    <button class="header-btn">
                        <i class="fas fa-search"></i>
                    </button>
                    <button class="header-btn">
                        <i class="fas fa-bell"></i>
                    </button>
                </div>
            </header>

            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title-lg">
                        <i class="fas fa-users"></i>
                        <span>System Users</span>
                    </h2>
                </div>

                <?php if (!empty($users)): ?>
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Role</th>
                                    <th>Email</th>
                                    <th>Total Flashcards</th>
                                    <th>Joined Date</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                                        </td>
                                        <td>
                                            <span class="user-role">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo $user['total_flashcards']; ?></td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-activities">
                        <i class="fas fa-users-slash"></i>
                        <p>No users found in the system.</p>
                        <?php if (isset($error_message)): ?>
                            <p style="color: var(--danger); margin-top: var(--space-sm);">Error: <?php echo htmlspecialchars($error_message); ?></p>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>
        </main>

        <!-- Bottom Navigation - Mobile -->
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
                <a href="admin_classes.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_classes.php') ? ' active' : ''; ?>">
                    <i class="fas fa-chalkboard"></i>
                    <span>Classes</span>
                </a>
                <a href="admin_group.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_group.php') ? ' active' : ''; ?>">
                    <i class="fas fa-users"></i>
                    <span>Group</span>
                </a>
                <a href="admin_logs.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_logs.php') ? ' active' : ''; ?>">
                    <i class="fas fa-clipboard-list"></i>
                    <span>Logs</span>
                </a>
            </nav>
        </div>
    </div>

    <!-- Add theme toggle script -->
    <script>
        function showNotification(message) {
            const notification = document.getElementById('notification');
            notification.textContent = message;
            notification.style.display = 'block';
            
            setTimeout(() => {
                notification.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => {
                    notification.style.display = 'none';
                    notification.style.animation = 'slideIn 0.3s ease-out';
                }, 300);
            }, 1000);
        }

        document.getElementById('themeToggle').addEventListener('change', function(e) {
            const theme = this.checked ? 'dark' : 'light';
            const formData = new FormData();
            formData.append('theme', theme);
            formData.append('change_theme', '1');
            
            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => response.text())
            .then(html => {
                if (html.includes('Theme updated successfully')) {
                    showNotification('Theme updated successfully!');
                    document.body.setAttribute('data-theme', theme);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                this.checked = !this.checked;
            });
        });
    </script>
</body>
</html>