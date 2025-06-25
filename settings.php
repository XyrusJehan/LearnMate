<?php
// settings.php
session_start();
// Include database connection file located in the root directory
require 'db.php';
require 'includes/theme.php';

// Create reports table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS reports (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        message TEXT NOT NULL,
        status ENUM('pending', 'in_progress', 'resolved') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )");
} catch (PDOException $e) {
    // Table might already exist, continue
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php'); // Redirect to login page if not logged in
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$error = '';
$success = '';
$password_success = '';
$theme_success = '';
$user = null;

// Get user information
try {
    $stmt = $pdo->prepare("SELECT first_name, last_name, email FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        throw new Exception("User not found.");
    }
    
    // Get theme preference
    $user['theme_preference'] = getUserTheme($pdo, $userId);
    
    // Handle form submissions
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['change_password'])) {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $stored_password = $stmt->fetch()['password'];

            if (password_verify($current_password, $stored_password)) {
                if ($new_password === $confirm_password) {
                    if (strlen($new_password) >= 8) {
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                        $stmt->execute([$hashed_password, $userId]);
                        $password_success = "Password changed successfully!";
                    } else {
                        $error = "New password must be at least 8 characters long.";
                    }
                } else {
                    $error = "New passwords do not match.";
                }
            } else {
                $error = "Current password is incorrect.";
            }
        } elseif (isset($_POST['change_theme'])) {
            $theme = $_POST['theme'] ?? 'light';
            if (setUserTheme($pdo, $userId, $theme)) {
                $user['theme_preference'] = $theme;
                $theme_success = "Theme updated successfully!";
            } else {
                $error = "Failed to update theme.";
            }
        } elseif (isset($_POST['change_name'])) {
            $first_name = trim($_POST['first_name'] ?? '');
            $last_name = trim($_POST['last_name'] ?? '');

            if (empty($first_name) || empty($last_name)) {
                $error = "First name and last name are required.";
            } else {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET first_name = ?, last_name = ? WHERE id = ?");
                    if ($stmt->execute([$first_name, $last_name, $userId])) {
                        $_SESSION['first_name'] = $first_name;
                        $_SESSION['last_name'] = $last_name;
                        $user['first_name'] = $first_name;
                        $user['last_name'] = $last_name;
                        $success = "Name updated successfully!";
                    } else {
                        $error = "Failed to update name.";
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        } elseif (isset($_POST['submit_report'])) {
            $message = trim($_POST['report_message'] ?? '');
            
            if (empty($message)) {
                $error = "Please enter your report message.";
            } else {
                try {
                    $stmt = $pdo->prepare("INSERT INTO reports (user_id, message) VALUES (?, ?)");
                    if ($stmt->execute([$userId, $message])) {
                        $success = "Report submitted successfully!";
                    } else {
                        $error = "Failed to submit report.";
                    }
                } catch (PDOException $e) {
                    $error = "Database error: " . $e->getMessage();
                }
            }
        }
    }

    // Get classes based on user role
    if ($userRole === 'teacher') {
        $stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ?");
        $stmt->execute([$userId]);
    } else {
        $stmt = $pdo->prepare("
            SELECT c.* 
            FROM classes c
            JOIN class_students cs ON c.id = cs.class_id
            WHERE cs.student_id = ?
        ");
        $stmt->execute([$userId]);
    }
    $classes = $stmt->fetchAll();
    
    // Get user's reports if they are a student or teacher
    if ($userRole === 'student' || $userRole === 'teacher') {
        $stmt = $pdo->prepare("SELECT * FROM reports WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        $userReports = $stmt->fetchAll();
    }
    
    // Get all reports if user is admin
    if ($userRole === 'admin') {
        $stmt = $pdo->prepare("
            SELECT r.*, u.first_name, u.last_name, u.email 
            FROM reports r 
            JOIN users u ON r.user_id = u.id 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute();
        $allReports = $stmt->fetchAll();
    }
    
} catch (Exception $e) {
    $error = $e->getMessage();
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
  <title>Settings - LearnMate</title>
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

    [data-theme="dark"] {
      --text-dark: #FFFFFF;
      --text-medium: #E0E0E0;
      --text-light: #CCCCCC;
      --bg-light: #1A1A1A;
      --bg-white: #2A2A2A;
      --border-light: #333333;
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
      transition: background-color var(--transition), color var(--transition);
    }

    body[data-theme="dark"] {
      background-color: var(--bg-light);
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

    body[data-theme="dark"] .sidebar {
      background-color: var(--bg-white);
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

    body[data-theme="dark"] .app-name {
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

    body[data-theme="dark"] .nav-item:hover {
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }

    .nav-item.active {
      background-color: #F9F5FF;
      color: var(--primary-dark);
      font-weight: 600;
    }

    body[data-theme="dark"] .nav-item.active {
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }

    .nav-item i {
      width: 20px;
      text-align: center;
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
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }
    
    body[data-theme="dark"] .dropdown-toggle:hover {
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }
    
    .dropdown-toggle.active {
      background-color: #F9F5FF;
      color: var(--primary-dark);
      font-weight: 600;
    }
    
    body[data-theme="dark"] .dropdown-toggle.active {
      background-color: #F9F5FF;
      color: var(--primary-dark);
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
      background-color: var(--bg-white);
    }
    
    body[data-theme="dark"] .dropdown-menu {
      background-color: var(--bg-white);
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
    
    body[data-theme="dark"] .dropdown-item:hover {
      color: var(--primary-dark);
    }
    
    .class-name {
      font-weight: 600;
      font-size: 14px;
      color: var(--text-dark);
    }
    
    body[data-theme="dark"] .class-name {
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

    body[data-theme="dark"] .main-content {
      background-color: var(--bg-light);
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

    body[data-theme="dark"] .header {
      background-color: var(--bg-white);
    }

    .header-title {
      font-weight: 600;
      font-size: 18px;
      color: var(--text-dark);
    }

    body[data-theme="dark"] .header-title {
      color: var(--text-dark);
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

    body[data-theme="dark"] .header-btn {
      background-color: var(--bg-light);
    }

    .settings-card {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      box-shadow: var(--shadow-sm);
      margin-bottom: var(--space-lg);
    }

    body[data-theme="dark"] .settings-card {
      background-color: var(--bg-white);
    }

    .settings-title {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: var(--space-md);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      color: var(--text-dark);
    }

    body[data-theme="dark"] .settings-title {
      color: var(--text-dark);
    }

    .form-group {
      margin-bottom: var(--space-md);
    }

    .form-label {
      font-size: 14px;
      font-weight: 500;
      color: var(--text-medium);
      margin-bottom: var(--space-xs);
      display: block;
    }

    .form-control {
      width: 100%;
      padding: var(--space-sm);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      font-size: 14px;
      color: var(--text-dark);
      background-color: var(--bg-white);
      transition: var(--transition);
    }

    body[data-theme="dark"] .form-control {
      color: var(--text-dark);
      background-color: var(--bg-white);
    }

    .form-control:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(127, 86, 217, 0.2);
    }

    .form-help-text {
      font-size: 12px;
      color: var(--text-light);
      margin-top: var(--space-xs);
    }

    .btn {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      border: none;
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
    }

    .btn-primary {
      background-color: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
    }

    .alert {
      padding: var(--space-sm);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-md);
      font-size: 14px;
    }

    .alert-success {
      background-color: #E9FCEB;
      color: var(--success);
    }

    .alert-danger {
      background-color: #FEE4E2;
      color: var(--danger);
    }

    .theme-options {
      display: flex;
      gap: var(--space-md);
    }

    .theme-option {
      display: flex;
      align-items: center;
      gap: var(--space-xs);
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

    body[data-theme="dark"] .bottom-nav {
      background-color: var(--bg-white);
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

    /* Replace modal styles with notification styles */
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

    body[data-theme="dark"] .notification {
      background-color: var(--success);
    }

    /* Add toggle switch styles */
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

    .theme-toggle-container {
      display: flex;
      align-items: center;
      gap: var(--space-md);
      margin-bottom: var(--space-md);
    }

    .theme-label {
      font-weight: 500;
      color: var(--text-dark);
    }

    body[data-theme="dark"] .theme-label {
      color: var(--text-dark);
    }
    .password-container {
    position: relative;
}

  .password-container input {
      padding-right: 2.5rem;
  }

  .toggle-password {
      position: absolute;
      right: 0.75rem;
      top: 50%;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      padding: 0.3125rem;
      color: var(--text-light);
      display: flex;
      align-items: center;
      justify-content: center;
      width: 1.875rem;
      height: 1.875rem;
  }

  .eye-icon {
      width: 1.25rem;
      height: 1.25rem;
      fill: none;
      stroke: currentColor;
      stroke-width: 2;
      stroke-linecap: round;
      stroke-linejoin: round;
      transition: all 0.3s ease;
      position: absolute;
  }

  .eye-open {
      display: none;
      opacity: 0;
  }

  .eye-closed {
      display: block;
      opacity: 1;
  }

  .password-visible .eye-closed {
      display: none;
      opacity: 0;
  }

  .password-visible .eye-open {
      display: block;
      opacity: 1;
  }

  .toggle-password:hover .eye-icon {
      color: var(--primary);
  }

  .card {
    background-color: var(--bg-white);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-sm);
    padding: var(--space-md);
    margin-bottom: var(--space-lg);
  }

  .reports-section {
    margin-top: var(--space-xl);
  }

  .report-form {
    margin-bottom: var(--space-lg);
  }

  .report-form textarea {
    width: 100%;
    min-height: 120px;
    padding: var(--space-md);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    background-color: var(--bg-light);
    color: var(--text-dark);
    font-size: 15px;
    resize: vertical;
    margin-bottom: var(--space-md);
  }

  .report-form textarea:focus {
    outline: none;
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--primary-light);
  }

  .reports-list {
    display: flex;
    flex-direction: column;
    gap: var(--space-md);
  }

  .report-item {
    background-color: var(--bg-white);
    border: 1px solid var(--border-light);
    border-radius: var(--radius-md);
    padding: var(--space-md);
  }

  .report-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: var(--space-sm);
  }

  .report-user {
    font-weight: 600;
    color: var(--text-dark);
  }

  .report-date {
    color: var(--text-light);
    font-size: 14px;
  }

  .report-message {
    color: var(--text-medium);
    margin-bottom: var(--space-sm);
  }

  .report-status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: var(--radius-full);
    font-size: 12px;
    font-weight: 600;
  }

  .status-pending {
    background-color: var(--warning);
    color: white;
  }

  .status-in_progress {
    background-color: var(--secondary);
    color: white;
  }

  .status-resolved {
    background-color: var(--success);
    color: white;
  }

  .admin-actions {
    display: flex;
    gap: var(--space-sm);
    margin-top: var(--space-sm);
  }

  .admin-actions button {
    padding: 6px 12px;
    border-radius: var(--radius-md);
    border: none;
    font-size: 14px;
    cursor: pointer;
    transition: var(--transition);
  }

  .btn-in-progress {
    background-color: var(--secondary);
    color: white;
  }

  .btn-resolve {
    background-color: var(--success);
    color: white;
  }

  .btn-delete {
    background-color: var(--danger);
    color: white;
  }

  @media (min-width: 768px) {
    .sidebar {
      display: flex;
      flex-direction: column;
    }
  }
  </style>
</head>
<body data-theme="<?php echo htmlspecialchars($user['theme_preference']); ?>">
  <!-- Replace modal with notification -->
  <div id="notification" class="notification"></div>

  <div class="app-container">
    <?php if ($userRole === 'admin'): ?>
      <?php include 'includes/admin_sidebar.php'; ?>
    <?php elseif ($userRole === 'teacher'): ?>
      <!-- Sidebar - Desktop for Teacher -->
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
          <a href="settings.php" class="nav-item active">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
          </a>
          <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </div>
      </aside>
    <?php else: ?>
      <!-- Sidebar - Desktop for Student -->
      <aside class="sidebar">
        <div class="sidebar-header">
          <div class="logo">LM</div>
          <div class="app-name">LearnMate</div>
        </div>
        <div class="nav-section">
          <div class="section-title">Menu</div>
          <a href="student_dashboard.php" class="nav-item<?php echo (basename($_SERVER['PHP_SELF']) == 'student_dashboard.php') ? ' active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
          </a>
          <a href="student_flashcards/flashcard.php" class="nav-item<?php echo (basename($_SERVER['PHP_SELF']) == 'flashcard.php') ? ' active' : ''; ?>">
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
          <a href="student_group.php" class="nav-item<?php echo (basename($_SERVER['PHP_SELF']) == 'student_group.php') ? ' active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>Groups</span>
          </a>
        </div>
        
        <div class="nav-section">
          <div class="section-title">Study</div>
          <a href="student_flashcards/review.php" class="nav-item<?php echo (basename($_SERVER['PHP_SELF']) == 'review.php') ? ' active' : ''; ?>">
            <i class="fas fa-book-open"></i>
            <span>Review Flashcards</span>
          </a>
        </div>
        
        <div class="nav-section" style="margin-top: auto;">
          <div class="section-title">Settings</div>
          <a href="settings.php" class="nav-item active">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
          </a>
          <a href="logout.php" class="nav-item">
            <i class="fas fa-sign-out-alt"></i>
            <span>Logout</span>
          </a>
        </div>
      </aside>
    <?php endif; ?>

    <!-- Main Content Area -->
    <main class="main-content">
      <!-- Mobile Header -->
      <header class="header">
        <h1 class="header-title">Settings</h1>
        <div class="header-actions">
          <button class="header-btn">
            <i class="fas fa-search"></i>
          </button>
          <button class="header-btn">
            <i class="fas fa-bell"></i>
          </button>
        </div>
      </header>

      <!-- Profile Information -->
      <div class="settings-card">
        <h2 class="settings-title">
            <i class="fas fa-user"></i>
            Profile Information
        </h2>
        <form method="POST" action="settings.php">
            <div class="form-group">
                <label for="first_name" class="form-label">First Name</label>
                <input type="text" id="first_name" name="first_name" class="form-control" 
                       value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
            </div>
            <div class="form-group">
                <label for="last_name" class="form-label">Last Name</label>
                <input type="text" id="last_name" name="last_name" class="form-control" 
                       value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email</label>
                <input type="email" class="form-control" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                <div class="form-help-text">Email cannot be changed</div>
            </div>
            <button type="submit" name="change_name" class="btn btn-primary">Update Profile</button>
        </form>
      </div>

<!-- Change Password -->
<div class="settings-card">
    <h2 class="settings-title">
        <i class="fas fa-lock"></i>
        Change Password
    </h2>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($password_success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($password_success); ?></div>
    <?php endif; ?>
    <form method="POST" action="settings.php">
        <div class="form-group">
            <label for="current_password" class="form-label">Current Password</label>
            <div class="password-container">
                <input type="password" id="current_password" name="current_password" class="form-control" required>
                <button type="button" class="toggle-password" aria-label="Show password">
                    <svg class="eye-icon eye-closed" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                    <svg class="eye-icon eye-open" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
        </div>
        <div class="form-group">
            <label for="new_password" class="form-label">New Password</label>
            <div class="password-container">
                <input type="password" id="new_password" name="new_password" class="form-control" 
                       required minlength="8"
                       pattern="^(?=.*[A-Z])(?=.*[0-9])(?=.*[!@#$%^&*]).{8,}$"
                       title="Must contain at least 8 characters with uppercase, number and special character">
                <button type="button" class="toggle-password" aria-label="Show password">
                    <svg class="eye-icon eye-closed" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                    <svg class="eye-icon eye-open" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
            <div class="form-help-text">Password must be at least 8 characters long with uppercase, number and special character</div>
        </div>
        <div class="form-group">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <div class="password-container">
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                <button type="button" class="toggle-password" aria-label="Show password">
                    <svg class="eye-icon eye-closed" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                        <line x1="1" y1="1" x2="23" y2="23"></line>
                    </svg>
                    <svg class="eye-icon eye-open" viewBox="0 0 24 24" width="20" height="20">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                    </svg>
                </button>
            </div>
        </div>
        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
    </form>
</div>

      <!-- Theme Settings -->
      <div class="settings-card">
        <h2 class="settings-title">
          <i class="fas fa-palette"></i>
          Theme Settings
        </h2>
        <div class="theme-toggle-container">
          <span class="theme-label">Light</span>
          <label class="toggle-switch">
            <input type="checkbox" id="themeToggle" <?php echo $user['theme_preference'] === 'dark' ? 'checked' : ''; ?>>
            <span class="toggle-slider"></span>
          </label>
          <span class="theme-label">Dark</span>
        </div>
      </div>

      <!-- Archive Section: Single Archived Button (no dropdown) -->
      <a href="archived.php" class="btn btn-primary" style="margin-bottom: 18px; width: 100%; max-width: 350px; display: block; font-size: 1.1rem; text-align: center; text-decoration: none;">
        <i class="fas fa-archive"></i> Archived
      </a>


      <?php if ($userRole === 'student' || $userRole === 'teacher'): ?>
      <div class="card reports-section">
        <h2 class="section-title-lg">
          <i class="fas fa-flag"></i>
          <span>Report a Problem</span>
        </h2>
        
        <form method="POST" class="report-form">
          <textarea name="report_message" placeholder="Describe the problem you're experiencing..." required></textarea>
          <button type="submit" name="submit_report" class="btn btn-primary">
            <i class="fas fa-paper-plane"></i> Submit Report
          </button>
        </form>

        <?php if (!empty($userReports)): ?>
        <h3 class="section-title-lg" style="margin-top: var(--space-xl);">
          <i class="fas fa-history"></i>
          <span>Your Reports</span>
        </h3>
        
        <div class="reports-list">
          <?php foreach ($userReports as $report): ?>
          <div class="report-item">
            <div class="report-header">
              <div class="report-date"><?php echo time_elapsed_string($report['created_at']); ?></div>
              <span class="report-status status-<?php echo $report['status']; ?>">
                <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
              </span>
            </div>
            <div class="report-message"><?php echo htmlspecialchars($report['message']); ?></div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <?php if ($userRole === 'admin'): ?>
      <div class="card reports-section">
        <h2 class="section-title-lg">
          <i class="fas fa-flag"></i>
          <span>User Reports</span>
        </h2>
        
        <?php if (!empty($allReports)): ?>
        <div class="reports-list">
          <?php foreach ($allReports as $report): ?>
          <div class="report-item">
            <div class="report-header">
              <div class="report-user">
                <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                <span style="color: var(--text-light); font-size: 14px;">(<?php echo htmlspecialchars($report['email']); ?>)</span>
              </div>
              <div class="report-date"><?php echo time_elapsed_string($report['created_at']); ?></div>
            </div>
            <div class="report-message"><?php echo htmlspecialchars($report['message']); ?></div>
            <div class="admin-actions">
              <?php if ($report['status'] === 'pending'): ?>
              <button class="btn-in-progress" onclick="updateReportStatus(<?php echo $report['id']; ?>, 'in_progress')">
                <i class="fas fa-clock"></i> Mark In Progress
              </button>
              <?php endif; ?>
              <?php if ($report['status'] !== 'resolved'): ?>
              <button class="btn-resolve" onclick="updateReportStatus(<?php echo $report['id']; ?>, 'resolved')">
                <i class="fas fa-check"></i> Mark Resolved
              </button>
              <?php endif; ?>
              <button class="btn-delete" onclick="deleteReport(<?php echo $report['id']; ?>)">
                <i class="fas fa-trash"></i> Delete
              </button>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p style="color: var(--text-medium);">No reports have been submitted yet.</p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </main>
  </div>

  <!-- Bottom Navigation with Fixed FAB - Mobile Only -->
  <div class="bottom-nav-container">
    <nav class="bottom-nav">
      <a href="student_dashboard.php" class="nav-item-mobile">
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
    // Password Toggle Functionality
document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', function() {
        const input = this.parentElement.querySelector('input');
        const isPassword = input.type === 'password';
        input.type = isPassword ? 'text' : 'password';
        this.classList.toggle('password-visible');
        this.setAttribute('aria-label', isPassword ? 'Hide password' : 'Show password');
    });
});
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

    // Replace modal functions with notification functions
    function showNotification(message) {
      const notification = document.getElementById('notification');
      notification.textContent = message;
      notification.style.display = 'block';
      
      // Hide notification after 1 second
      setTimeout(() => {
        notification.style.animation = 'slideOut 0.3s ease-out';
        setTimeout(() => {
          notification.style.display = 'none';
          notification.style.animation = 'slideIn 0.3s ease-out';
        }, 300);
      }, 1000);
    }

    // Handle theme toggle
    document.getElementById('themeToggle').addEventListener('change', function(e) {
      const theme = this.checked ? 'dark' : 'light';
      const formData = new FormData();
      formData.append('theme', theme);
      formData.append('change_theme', '1');
      
      fetch('settings.php', {
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
        // Revert toggle if there's an error
        this.checked = !this.checked;
      });
    });

    // Function to update report status
    async function updateReportStatus(reportId, status) {
      try {
        const response = await fetch('update_report.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            report_id: reportId,
            status: status
          })
        });
        
        if (response.ok) {
          location.reload();
        } else {
          alert('Failed to update report status');
        }
      } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while updating the report');
      }
    }

    // Function to delete report
    async function deleteReport(reportId) {
      if (!confirm('Are you sure you want to delete this report?')) {
        return;
      }

      try {
        const response = await fetch('delete_report.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            report_id: reportId
          })
        });
        
        if (response.ok) {
          location.reload();
        } else {
          alert('Failed to delete report');
        }
      } catch (error) {
        console.error('Error:', error);
        alert('An error occurred while deleting the report');
      }
    }
  </script>
</body>
</html>