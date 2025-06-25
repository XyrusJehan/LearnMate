<?php
session_start();
require 'db.php';
require 'includes/theme.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

$teacherId = $_SESSION['user_id'];

// Get theme for the page
$theme = getCurrentTheme();

// Fetch teacher's classes
$stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ?");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['group-name'])) {
    $groupName = trim($_POST['group-name']);
    $privacy = $_POST['group-privacy'] ?? 'public';
    $description = trim($_POST['group-description'] ?? '');
    $passcode = ($privacy === 'private') ? trim($_POST['group-passcode'] ?? '') : null;
    
    // Validate inputs
    if (empty($groupName)) {
        $error = "Group name is required";
    } elseif (strlen($groupName) > 100) {
        $error = "Group name must be less than 100 characters";
    } elseif ($privacy === 'private' && (empty($passcode) || strlen($passcode) < 4)) {
        $error = "Passcode must be at least 4 characters for private groups";
    } else {
        // Handle file upload
        $imageUrl = null;
        if (isset($_FILES['group-image']) && $_FILES['group-image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/groups/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }
            
            // Validate image
            $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
            $fileType = mime_content_type($_FILES['group-image']['tmp_name']);
            $maxSize = 2 * 1024 * 1024; // 2MB
            
            if (array_key_exists($fileType, $allowedTypes) && $_FILES['group-image']['size'] <= $maxSize) {
                $extension = $allowedTypes[$fileType];
                $fileName = uniqid('group_') . '.' . $extension;
                $targetPath = $uploadDir . $fileName;
                
                if (move_uploaded_file($_FILES['group-image']['tmp_name'], $targetPath)) {
                    $imageUrl = $targetPath;
                } else {
                    $error = "Failed to upload image";
                }
            } else {
                $error = "Invalid file type or size (max 2MB JPG/PNG only)";
            }
        }
        
        if (!isset($error)) {
            // Insert into database
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO groups (name, description, image_url, privacy, passcode, created_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $groupName, 
                    $description, 
                    $imageUrl, 
                    $privacy, 
                    $passcode ? password_hash($passcode, PASSWORD_DEFAULT) : null,
                    $teacherId
                ]);
                $groupId = $pdo->lastInsertId();
                
                // Add creator as admin member
                $stmt = $pdo->prepare("
                    INSERT INTO group_members (group_id, user_id, joined_at, is_admin)
                    VALUES (?, ?, NOW(), 1)
                ");
                $stmt->execute([$groupId, $teacherId]);
                
                $pdo->commit();
                header('Location: teacher_group.php?created=' . $groupId);
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Failed to create group: " . $e->getMessage();
            }
        }
    }
}

// Fetch groups the teacher is in (with admin status)
    $stmt = $pdo->prepare("
        SELECT g.*, 
              (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count, 
              MAX(CASE WHEN gm.user_id = ? AND gm.is_admin = 1 THEN 1 ELSE 0 END) as is_admin
        FROM groups g
        JOIN group_members gm ON g.id = gm.group_id
        WHERE gm.user_id = ? AND g.is_archived = 0
        GROUP BY g.id
        ORDER BY g.created_at DESC
    ");
$stmt->execute([$teacherId, $teacherId]);
$userGroups = $stmt->fetchAll();

// Fetch public groups (excluding ones teacher is already in)
    $stmt = $pdo->prepare("
        SELECT g.*, 
              (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count,
              MAX(CASE WHEN gm.user_id = ? AND gm.is_admin = 1 THEN 1 ELSE 0 END) as is_admin
        FROM groups g
        LEFT JOIN group_members gm ON g.id = gm.group_id
        WHERE g.id NOT IN (
            SELECT gm.group_id 
            FROM group_members gm 
            WHERE gm.user_id = ?
        ) AND g.is_archived = 0
        GROUP BY g.id
        ORDER BY g.created_at DESC
        LIMIT 6
    ");
$stmt->execute([$teacherId, $teacherId]);
$publicGroups = $stmt->fetchAll();

// Get newly created group details if redirected after creation
$newGroup = null;
if (isset($_GET['created'])) {
    $stmt = $pdo->prepare("
        SELECT g.*, 
              (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count, 
              1 as is_admin
        FROM groups g
        WHERE g.id = ?
    ");
    $stmt->execute([$_GET['created']]);
    $newGroup = $stmt->fetch();
}

// Get left group details if redirected after leaving
$leftGroup = null;
if (isset($_GET['left'])) {
    $stmt = $pdo->prepare("
        SELECT g.*, COUNT(gm.user_id) as member_count
        FROM groups g
        LEFT JOIN group_members gm ON g.id = gm.group_id
        WHERE g.id = ?
        GROUP BY g.id
    ");
    $stmt->execute([$_GET['left']]);
    $leftGroup = $stmt->fetch();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Study Groups - LearnMate</title>
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

    /* App Container */
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

    .profile-initial {
      width: 32px;
      height: 32px;
      background-color: var(--primary-light);
      color: white;
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 14px;
      margin-right: var(--space-sm);
    }

    .class-name {
      font-weight: 500;
      font-size: 14px;
    }

    .class-section {
      font-size: 12px;
      color: var(--text-light);
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

    /* Groups Section */
    .section-title-lg {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: var(--space-md);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .section-title-lg .btn {
      margin-left: auto;
    }

    /* Enhanced Group Card Styles */
    .groups-grid {
      display: grid;
      gap: var(--space-md);
      margin-top: var(--space-lg);
    }

    .group-card {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      overflow: visible;
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
      position: relative;
      display: flex;
      flex-direction: column;
      height: 100%;
      border: 1px solid var(--border-light);
    }

    .group-card:hover {
      transform: translateY(-4px);
      box-shadow: var(--shadow-md);
      border-color: var(--primary-light);
    }
    
    .group-card-link {
      text-decoration: none;
      color: inherit;
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .admin-badge {
      background-color: var(--primary);
      color: white;
      padding: 4px;
      border-radius: var(--radius-sm);
      font-size: 11px;
      font-weight: 600;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: var(--shadow-xs);
      white-space: nowrap;
      margin-left: 4px;
      width: 24px;
      height: 24px;
    }

    .admin-badge i {
      font-size: 12px;
    }

    .group-image-container {
      position: relative;
      width: 100%;
      height: 0;
      padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
      overflow: hidden;
      background-color: #F9F5FF;
      border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    }

    .group-image {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: center;
      transition: transform 0.3s ease;
    }

    .group-card:hover .group-image {
      transform: scale(1.05);
    }

    .group-image-placeholder {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 32px;
      border-radius: var(--radius-lg) var(--radius-lg) 0 0;
    }

    .group-content {
      padding: var(--space-md);
      flex-grow: 1;
      display: flex;
      flex-direction: column;
      position: relative;
    }

    .group-title {
      font-weight: 600;
      font-size: 16px;
      color: var(--text-dark);
      display: -webkit-box;
      -webkit-line-clamp: 1;
      -webkit-box-orient: vertical;
      overflow: hidden;
      margin: 0;
      flex: 1;
      margin-right: 4px;
    }

    .group-meta {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: var(--space-xs);
      position: relative;
    }

    .group-actions {
      position: absolute;
      top: 12px;
      right: 12px;
      z-index: 2;
      background-color: var(--bg-white);
      border-radius: var(--radius-sm);
      padding: 2px;
      box-shadow: var(--shadow-sm);
    }

    .group-menu-btn {
      background: none;
      border: none;
      color: var(--text-medium);
      cursor: pointer;
      padding: 4px;
      border-radius: var(--radius-sm);
      transition: var(--transition);
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 16px;
    }

    .group-menu-btn:hover {
      background-color: var(--bg-light);
      color: var(--text-dark);
    }

    .group-menu-dropdown {
      position: absolute;
      right: 0;
      top: 100%;
      background-color: var(--bg-white);
      border-radius: var(--radius-md);
      box-shadow: var(--shadow-lg);
      min-width: 160px;
      z-index: 100;
      display: none;
      margin-top: 4px;
      border: 1px solid var(--border-light);
    }

    .group-menu-dropdown.show {
      display: block;
    }

    .group-menu-item {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      padding: var(--space-sm) var(--space-md);
      color: var(--text-dark);
      text-decoration: none;
      transition: var(--transition);
      cursor: pointer;
      white-space: nowrap;
    }

    .group-menu-item:hover {
      background-color: var(--bg-light);
    }

    .group-menu-item.delete {
      color: var(--danger);
    }

    .group-menu-item.delete:hover {
      background-color: #FEF3F2;
    }

    .group-menu-item.archive {
      color: var(--primary);
    }

    .group-menu-item.archive:hover {
      background-color: #F9F5FF;
    }

    .group-members {
      font-size: 13px;
      color: var(--text-medium);
      display: flex;
      align-items: center;
      gap: var(--space-xs);
      background-color: var(--bg-light);
      padding: 4px 10px;
      border-radius: var(--radius-sm);
    }

    .group-members i {
      color: var(--primary);
      font-size: 12px;
    }

    .group-category {
      font-size: 12px;
      color: var(--primary-dark);
      background-color: #F9F5FF;
      padding: 4px 10px;
      border-radius: var(--radius-sm);
      font-weight: 500;
    }

    .group-description {
      font-size: 14px;
      color: var(--text-medium);
      margin-top: var(--space-sm);
      display: -webkit-box;
      -webkit-line-clamp: 2;
      -webkit-box-orient: vertical;
      overflow: hidden;
      line-height: 1.4;
    }

    .group-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-top: auto;
      padding-top: var(--space-sm);
      border-top: 1px dashed var(--border-light);
      font-size: 12px;
      color: var(--text-light);
    }

    .group-privacy {
      display: flex;
      align-items: center;
      gap: 4px;
    }

    .group-privacy i {
      font-size: 12px;
    }

    .public-privacy {
      color: var(--success);
    }

    .private-privacy {
      color: var(--warning);
    }

    /* Create Group Modal */
    .modal {
      display: none;
      position: fixed;
      z-index: 100;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.4);
    }
    
    .modal-content {
      background-color: var(--bg-white);
      margin: 10% auto;
      padding: var(--space-lg);
      border-radius: var(--radius-lg);
      width: 90%;
      max-width: 500px;
      box-shadow: var(--shadow-xl);
      position: relative;
    }
    
    .close-modal {
      position: absolute;
      top: 16px;
      right: 16px;
      color: var(--text-light);
      font-size: 24px;
      font-weight: bold;
      cursor: pointer;
    }
    
    .close-modal:hover {
      color: var(--text-dark);
    }

    /* Form Styles */
    .form-title {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: var(--space-lg);
      color: var(--primary-dark);
      text-align: center;
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

    .form-label.required:after {
      content: " *";
      color: var(--danger);
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
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(127, 86, 217, 0.1);
    }

    .form-textarea {
      min-height: 100px;
      resize: vertical;
    }

    .image-upload {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: var(--space-md);
      padding: var(--space-xl) 0;
      border: 2px dashed var(--border-light);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-lg);
      cursor: pointer;
      transition: var(--transition);
      position: relative;
      overflow: hidden;
    }

    .image-upload:hover {
      border-color: var(--primary-light);
      background-color: #F9F5FF;
    }

    .image-upload i {
      font-size: 32px;
      color: var(--primary);
    }

    .image-upload-text {
      font-size: 14px;
      color: var(--text-medium);
      text-align: center;
    }

    .image-upload-text span {
      color: var(--primary);
      font-weight: 500;
    }

    .btn {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      border: none;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      font-size: 14px;
      display: inline-flex;
      align-items: center;
      gap: var(--space-sm);
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

    .btn-submit {
      width: 100%;
      padding: var(--space-md);
      border-radius: var(--radius-md);
      border: none;
      background-color: var(--primary);
      color: white;
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
      font-size: 16px;
    }

    .btn-submit:hover {
      background-color: var(--primary-dark);
      transform: translateY(-1px);
    }

    .btn-submit:disabled {
      background-color: var(--text-light);
      cursor: not-allowed;
    }

    .radio-group {
      display: flex;
      gap: var(--space-md);
      flex-wrap: wrap;
    }

    .radio-option {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .radio-option input {
      margin: 0;
    }

    /* Error Message */
    .alert {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-lg);
      font-size: 14px;
    }

    .alert-danger {
      background-color: #FEF3F2;
      color: var(--danger);
      border: 1px solid #FECDCA;
    }

    /* Bottom Navigation with Fixed FAB - Mobile Only */
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
      transition: var(--transition);
    }

    .fab:hover {
      transform: scale(1.05);
    }

    .fab i {
      font-size: 24px;
    }

    /* Responsive Design */
    @media (min-width: 640px) {
      /* Tablet styles */
      .main-content {
        padding: var(--space-lg);
      }
      
      .groups-grid {
        grid-template-columns: 1fr 1fr;
      }
    }

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
      
      .groups-grid {
        grid-template-columns: 1fr 1fr 1fr;
      }

      .group-image-container,
      .group-image-placeholder {
        height: 160px;
      }
    }

    @media (min-width: 1024px) {
      /* Desktop styles */
      .groups-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
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
        <a href="teacher_dashboard.php" class="nav-item">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>
        
        <!-- Classes Dropdown -->
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
        
        <a href="teacher_group.php" class="nav-item active">
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

    <!-- Main Content Area -->
    <main class="main-content">
      <!-- Mobile Header -->
      <header class="header">
        <h1 class="header-title">Study Groups</h1>
        <div class="header-actions">
          <button class="header-btn" id="searchBtn">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </header>

      <!-- Create Group Modal -->
      <div class="modal" id="createGroupModal">
        <div class="modal-content">
          <span class="close-modal">&times;</span>
          <h2 class="form-title">Create Your Study Group</h2>
          
          <?php if (isset($error)): ?>
            <div class="alert alert-danger">
              <?php echo htmlspecialchars($error); ?>
            </div>
          <?php endif; ?>
          
          <form action="teacher_group.php" method="POST" enctype="multipart/form-data" id="groupForm">
            <div class="form-group">
              <label for="group-name" class="form-label required">Group Name</label>
              <input type="text" id="group-name" name="group-name" class="form-control" 
                     placeholder="Enter group name" required maxlength="100">
            </div>
            
            <div class="form-group">
              <label for="group-description" class="form-label">Description</label>
              <textarea id="group-description" name="group-description" class="form-control form-textarea" 
                        placeholder="What's this group about? (Optional)" maxlength="500"></textarea>
            </div>
            
            <div class="form-group">
              <label class="form-label">Group Image</label>
              <label for="group-image" class="image-upload" id="imageUploadLabel">
                <i class="fas fa-cloud-upload-alt" id="uploadIcon"></i>
                <div class="image-upload-text" id="uploadText">
                  Click to upload image <span>(Max 2MB, JPG/PNG only)</span>
                </div>
                <input type="file" id="group-image" name="group-image" 
                       accept="image/jpeg,image/png" style="display: none;">
              </label>
            </div>
            
            <div class="form-group">
              <label class="form-label required">Privacy</label>
              <div class="radio-group">
                <label class="radio-option">
                  <input type="radio" name="group-privacy" value="public" checked id="publicPrivacy">
                  <span>Public (Anyone can join)</span>
                </label>
                <label class="radio-option">
                  <input type="radio" name="group-privacy" value="private" id="privatePrivacy">
                  <span>Private (Passcode required)</span>
                </label>
              </div>
            </div>
            
            <div class="form-group" id="passcodeField" style="display: none;">
              <label for="group-passcode" class="form-label required">Passcode</label>
              <input type="password" id="group-passcode" name="group-passcode" class="form-control" 
                     placeholder="Set a passcode (min 4 characters)" minlength="4">
              <small style="font-size: 12px; color: var(--text-light);">Members will need this passcode to join</small>
            </div>
            
            <button type="submit" class="btn-submit" id="submitBtn">
              <i class="fas fa-plus"></i>
              Create Group
            </button>
          </form>
        </div>
      </div>

      <!-- Newly Created Group Highlight -->
      <?php if ($newGroup): ?>
        <div class="alert" style="background-color: #ECFDF3; color: #027A48; border: 1px solid #ABEFC6;">
          <div style="display: flex; align-items: center; gap: var(--space-sm);">
            <i class="fas fa-check-circle"></i>
            <span>Successfully created your group: <strong><?php echo htmlspecialchars($newGroup['name']); ?></strong></span>
          </div>
        </div>
      <?php endif; ?>

      <!-- Left Group Highlight -->
      <?php if ($leftGroup): ?>
        <div class="alert" style="background-color: #FEF3F2; color: #B42318; border: 1px solid #FDA29B;">
          <div style="display: flex; align-items: center; gap: var(--space-sm);">
            <i class="fas fa-check-circle"></i>
            <span>Successfully left the group: <strong><?php echo htmlspecialchars($leftGroup['name']); ?></strong></span>
          </div>
        </div>
      <?php endif; ?>

      <!-- Group Deletion Messages -->
      <?php if (isset($_GET['deleted'])): ?>
        <div class="alert" style="background-color: #ECFDF3; color: #027A48; border: 1px solid #ABEFC6;">
          <div style="display: flex; align-items: center; gap: var(--space-sm);">
            <i class="fas fa-check-circle"></i>
            <span>Group has been successfully deleted.</span>
          </div>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-danger">
          <div style="display: flex; align-items: center; gap: var(--space-sm);">
            <i class="fas fa-exclamation-circle"></i>
            <span>An error occurred while deleting the group. Please try again.</span>
          </div>
        </div>
      <?php endif; ?>

      <!-- Your Groups Section -->
      <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: var(--space-md);">
        <h2 class="section-title-lg">
          <i class="fas fa-users"></i>
          <span>Your Groups</span>
        </h2>
        <button class="btn btn-primary" id="createGroupBtn" style="padding: var(--space-sm) var(--space-md);">
          <i class="fas fa-plus"></i>
          <span>Create Group</span>
        </button>
      </div>
      
      <?php if (empty($userGroups)): ?>
        <div style="text-align: center; padding: var(--space-xl); color: var(--text-light);">
          <i class="fas fa-users" style="font-size: 48px; margin-bottom: var(--space-md); opacity: 0.5;"></i>
          <p>You haven't joined any groups yet.</p>
          <button class="btn btn-primary" id="createGroupBtnEmpty" style="margin-top: var(--space-md);">
            <i class="fas fa-plus"></i>
            Create Your First Group
          </button>
        </div>
      <?php else: ?>
        <div class="groups-grid">
          <?php foreach ($userGroups as $group): ?>
            <div class="group-card">
              <a href="teacher_group_details.php?id=<?php echo $group['id']; ?>" class="group-card-link">
                <div class="group-image-container">
                  <?php if ($group['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($group['image_url']); ?>" alt="<?php echo htmlspecialchars($group['name']); ?>" class="group-image">
                  <?php else: ?>
                    <div class="group-image-placeholder">
                      <i class="fas fa-users"></i>
                    </div>
                  <?php endif; ?>
                </div>
                
                <div class="group-content">
                  <div style="display: flex; align-items: center; gap: 0; margin-bottom: var(--space-xs); flex-wrap: nowrap;">
                    <h3 class="group-title"><?php echo htmlspecialchars($group['name']); ?></h3>
                    <?php if ($group['is_admin']): ?>
                      <span class="admin-badge" title="Admin">
                        <i class="fas fa-crown"></i>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="group-meta">
                    <div class="group-members">
                      <i class="fas fa-users"></i>
                      <span><?php echo $group['member_count']; ?> member<?php echo $group['member_count'] != 1 ? 's' : ''; ?></span>
                    </div>
                  </div>
                  
                  <?php if (!empty($group['description'])): ?>
                    <p class="group-description"><?php echo htmlspecialchars($group['description']); ?></p>
                  <?php endif; ?>
                  
                  <div class="group-footer">
                    <div class="group-privacy <?php echo $group['privacy'] === 'public' ? 'public-privacy' : 'private-privacy'; ?>">
                      <i class="fas fa-<?php echo $group['privacy'] === 'public' ? 'globe' : 'lock'; ?>"></i>
                      <span><?php echo ucfirst($group['privacy']); ?></span>
                    </div>
                    <div>
                      <i class="far fa-calendar-alt"></i>
                      <?php echo date('M j, Y', strtotime($group['created_at'])); ?>
                    </div>
                  </div>
                </div>
              </a>
              
              <?php if ($group['is_admin']): ?>
                <div class="group-actions">
                  <button class="group-menu-btn" onclick="toggleGroupMenu(this, event)" title="Group Options">
                    <i class="fas fa-ellipsis-v"></i>
                  </button>
                  <div class="group-menu-dropdown">
                    <div class="group-menu-item archive" onclick="confirmArchiveGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')">
                      <i class="fas fa-archive"></i>
                      <span>Archive Group</span>
                    </div>
                    <div class="group-menu-item delete" onclick="confirmDeleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')">
                      <i class="fas fa-trash-alt"></i>
                      <span>Delete Group</span>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Public Groups Section -->
      <h2 class="section-title-lg" style="margin-top: var(--space-xl);">
        <i class="fas fa-globe"></i>
        <span>Discover Groups</span>
      </h2>
      
      <?php if (empty($publicGroups)): ?>
        <div style="text-align: center; padding: var(--space-xl); color: var(--text-light);">
          <i class="fas fa-globe" style="font-size: 48px; margin-bottom: var(--space-md); opacity: 0.5;"></i>
          <p>No groups available to join yet.</p>
        </div>
      <?php else: ?>
        <div class="groups-grid">
          <?php foreach ($publicGroups as $group): ?>
            <div class="group-card">
              <a href="teacher_group_details.php?id=<?php echo $group['id']; ?>" class="group-card-link">
                <div class="group-image-container">
                  <?php if ($group['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($group['image_url']); ?>" alt="<?php echo htmlspecialchars($group['name']); ?>" class="group-image">
                  <?php else: ?>
                    <div class="group-image-placeholder">
                      <i class="fas fa-users"></i>
                    </div>
                  <?php endif; ?>
                </div>
                
                <div class="group-content">
                  <div style="display: flex; align-items: center; gap: 0; margin-bottom: var(--space-xs); flex-wrap: nowrap;">
                    <h3 class="group-title"><?php echo htmlspecialchars($group['name']); ?></h3>
                    <?php if ($group['is_admin']): ?>
                      <span class="admin-badge" title="Admin">
                        <i class="fas fa-crown"></i>
                      </span>
                    <?php endif; ?>
                  </div>
                  <div class="group-meta">
                    <div class="group-members">
                      <i class="fas fa-users"></i>
                      <span><?php echo $group['member_count']; ?> member<?php echo $group['member_count'] != 1 ? 's' : ''; ?></span>
                    </div>
                  </div>
                  
                  <?php if (!empty($group['description'])): ?>
                    <p class="group-description"><?php echo htmlspecialchars(substr($group['description'], 0, 100)); ?><?php echo strlen($group['description']) > 100 ? '...' : ''; ?></p>
                  <?php endif; ?>
                  
                  <div class="group-footer">
                    <div class="group-privacy <?php echo $group['privacy'] === 'public' ? 'public-privacy' : 'private-privacy'; ?>">
                      <i class="fas fa-<?php echo $group['privacy'] === 'public' ? 'globe' : 'lock'; ?>"></i>
                      <span><?php echo ucfirst($group['privacy']); ?></span>
                      <?php if ($group['privacy'] === 'private'): ?>
                        <span style="margin-left: 4px; font-size: 11px;">(Passcode Required)</span>
                      <?php endif; ?>
                    </div>
                    <div>
                      <i class="far fa-calendar-alt"></i>
                      <?php echo date('M j, Y', strtotime($group['created_at'])); ?>
                    </div>
                  </div>
                </div>
              </a>
              
              <?php if ($group['is_admin']): ?>
                <div class="group-actions">
                  <button class="group-menu-btn" onclick="toggleGroupMenu(this, event)" title="Group Options">
                    <i class="fas fa-ellipsis-v"></i>
                  </button>
                  <div class="group-menu-dropdown">
                    <div class="group-menu-item archive" onclick="confirmArchiveGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')">
                      <i class="fas fa-archive"></i>
                      <span>Archive Group</span>
                    </div>
                    <div class="group-menu-item delete" onclick="confirmDeleteGroup(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>')">
                      <i class="fas fa-trash-alt"></i>
                      <span>Delete Group</span>
                    </div>
                  </div>
                </div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <!-- Bottom Navigation with Fixed FAB - Mobile Only -->
  <div class="bottom-nav-container">
    <nav class="bottom-nav">
      <a href="teacher_dashboard.php" class="nav-item-mobile">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="demo02_v10/teacher_flashcard.php" class="nav-item-mobile">
        <i class="fas fa-book"></i>
        <span>Decks</span>
      </a>
      
      <!-- FAB Container - stays fixed with nav -->
      <div class="fab-container">
        <button class="fab" id="mobileCreateGroupBtn">
          <i class="fas fa-plus"></i>
        </button>
      </div>
      
      <!-- Spacer for FAB area -->
      <div style="width: 25%;"></div>
      
      <a href="teacher_group.php" class="nav-item-mobile active">
        <i class="fas fa-users"></i>
        <span>Groups</span>
      </a>
      <a href="settings.php" class="nav-item-mobile">
        <i class="fas fa-cog"></i>
        <span>Settings</span>
      </a>
    </nav>
  </div>

  <script>
    // (Keep all the JavaScript from student_group.php)
    // Modal functionality
    const modal = document.getElementById('createGroupModal');
    const createBtns = [
      document.getElementById('createGroupBtn'),
      document.getElementById('mobileCreateGroupBtn'),
      document.getElementById('createGroupBtnEmpty')
    ];
    const closeModal = document.querySelector('.close-modal');
    
    function showCreateModal() {
        modal.style.display = 'block';
        document.body.style.overflow = 'hidden';
    }
    
    function hideCreateModal() {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }
    
    createBtns.forEach(btn => {
        if (btn) btn.addEventListener('click', showCreateModal);
    });
    
    closeModal.addEventListener('click', hideCreateModal);
    
    window.addEventListener('click', (event) => {
        if (event.target === modal) {
            hideCreateModal();
        }
    });
    
    // Image upload preview
    const imageUpload = document.getElementById('group-image');
    const uploadText = document.getElementById('uploadText');
    const uploadIcon = document.getElementById('uploadIcon');
    
    if (imageUpload) {
        imageUpload.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (!file) return;
            
            uploadText.innerHTML = `Selected: <span>${file.name}</span>`;
            uploadIcon.style.display = 'none';
            
            const reader = new FileReader();
            reader.onload = function(event) {
                let imgPreview = document.querySelector('.image-upload img');
                if (!imgPreview) {
                    imgPreview = document.createElement('img');
                    imgPreview.style.maxWidth = '100%';
                    imgPreview.style.maxHeight = '150px';
                    imgPreview.style.borderRadius = 'var(--radius-sm)';
                    document.querySelector('.image-upload').prepend(imgPreview);
                }
                imgPreview.src = event.target.result;
            }
            reader.readAsDataURL(file);
        });
    }
    
    // Form validation
    const groupForm = document.getElementById('groupForm');
    if (groupForm) {
        groupForm.addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
        });
    }
    
    // Show success message if group was created
    <?php if (isset($_GET['created'])): ?>
        setTimeout(() => {
            const createdGroup = document.querySelector(`.group-card[href*="id=<?php echo $_GET['created']; ?>"]`);
            if (createdGroup) {
                createdGroup.scrollIntoView({ behavior: 'smooth', block: 'center' });
                createdGroup.style.boxShadow = '0 0 0 3px var(--success)';
                setTimeout(() => {
                    createdGroup.style.boxShadow = 'var(--shadow-md)';
                }, 2000);
            }
        }, 300);
    <?php endif; ?>
    
    // Dropdown toggle
    function toggleDropdown(element) {
      element.classList.toggle('active');
      const menu = element.parentElement.querySelector('.dropdown-menu');
      menu.classList.toggle('show');
    }
    
    // Passcode field toggle
    const publicPrivacy = document.getElementById('publicPrivacy');
    const privatePrivacy = document.getElementById('privatePrivacy');
    const passcodeField = document.getElementById('passcodeField');
    
    function togglePasscodeField() {
        passcodeField.style.display = privatePrivacy.checked ? 'block' : 'none';
        if (publicPrivacy.checked) {
            document.getElementById('group-passcode').value = '';
        }
    }
    
    publicPrivacy.addEventListener('change', togglePasscodeField);
    privatePrivacy.addEventListener('change', togglePasscodeField);
    
    // Initialize on page load
    togglePasscodeField();

    // Group menu functionality
    function toggleGroupMenu(button, e) {
      if (e) {
        e.stopPropagation();
      }
      
      const dropdown = button.nextElementSibling;
      const isOpen = dropdown.classList.contains('show');
      
      // Close all other dropdowns
      document.querySelectorAll('.group-menu-dropdown.show').forEach(menu => {
        if (menu !== dropdown) {
          menu.classList.remove('show');
        }
      });
      
      // Toggle current dropdown
      dropdown.classList.toggle('show');
      
      // Close dropdown when clicking outside
      if (!isOpen) {
        document.addEventListener('click', function closeDropdown(e) {
          if (!button.contains(e.target) && !dropdown.contains(e.target)) {
            dropdown.classList.remove('show');
            document.removeEventListener('click', closeDropdown);
          }
        });
      }
    }

    // Delete group confirmation
    function confirmDeleteGroup(groupId, groupName) {
      if (confirm(`Are you sure you want to delete the group "${groupName}"? This action cannot be undone.`)) {
        window.location.href = `delete_group.php?id=${groupId}`;
      }
    }

    // Archive group confirmation
    function confirmArchiveGroup(groupId, groupName) {
      if (confirm(`Are you sure you want to archive the group "${groupName}"? You can restore it later from Settings > Archived Groups.`)) {
        // Find the group card to show loading state
        const groupCard = document.querySelector(`[onclick*="confirmArchiveGroup(${groupId}"]`).closest('.group-card');
        const originalHTML = groupCard.innerHTML;
        
        // Show loading state
        groupCard.innerHTML = '<div style="padding: 20px; text-align: center;"><i class="fas fa-spinner fa-spin"></i> Archiving...</div>';
        
        // Send AJAX request
        fetch('archive_group.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `archive_group_id=${groupId}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Remove the group card with animation
            groupCard.style.transition = 'all 0.3s ease';
            groupCard.style.transform = 'scale(0.8)';
            groupCard.style.opacity = '0';
            setTimeout(() => {
              groupCard.remove();
              // Show success message
              const successMsg = document.createElement('div');
              successMsg.className = 'alert';
              successMsg.style.cssText = 'background-color: #ECFDF3; color: #027A48; border: 1px solid #ABEFC6; margin-bottom: var(--space-md);';
              successMsg.innerHTML = `<div style="display: flex; align-items: center; gap: var(--space-sm);"><i class="fas fa-check-circle"></i><span>Group "${groupName}" has been archived successfully!</span></div>`;
              document.querySelector('.main-content').insertBefore(successMsg, document.querySelector('.section-title-lg'));
              setTimeout(() => successMsg.remove(), 3000);
            }, 300);
          } else {
            // Restore original content and show error
            groupCard.innerHTML = originalHTML;
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          // Restore original content and show error
          groupCard.innerHTML = originalHTML;
          alert('Error archiving group. Please try again.');
        });
      }
    }
  </script>
</body>
</html>