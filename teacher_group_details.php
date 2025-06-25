<?php
session_start();
require 'db.php';
require 'includes/theme.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$teacherId = $_SESSION['user_id'];
$theme = getCurrentTheme();

// Get group ID from URL
if (!isset($_GET['id'])) {
    header('Location: teacher_group.php');
    exit();
}

$groupId = $_GET['id'];

// Fetch group details
$stmt = $pdo->prepare("
    SELECT g.*, 
           COUNT(gm.user_id) as member_count,
           MAX(CASE WHEN gm.user_id = ? AND gm.is_admin = 1 THEN 1 ELSE 0 END) as is_admin,
           MAX(CASE WHEN gm.user_id = ? THEN 1 ELSE 0 END) as is_member
    FROM `groups` g
    LEFT JOIN group_members gm ON g.id = gm.group_id
    WHERE g.id = ?
    GROUP BY g.id
");
$stmt->execute([$teacherId, $teacherId, $groupId]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: teacher_group.php');
    exit();
}

// Fetch teacher's classes
$stmt = $pdo->prepare("SELECT * FROM classes WHERE teacher_id = ?");
$stmt->execute([$teacherId]);
$classes = $stmt->fetchAll();

// Handle group management actions
$success = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['join-group'])) {
        if ($group['is_member']) {
            $error = "You are already a member of this group";
        } else {
            if ($group['privacy'] === 'private') {
                if (empty($_POST['passcode'])) {
                    $error = "Passcode is required for private groups";
                } elseif (!password_verify($_POST['passcode'], $group['passcode'])) {
                    $error = "Incorrect passcode. Please try again.";
                }
            }
            
            if (empty($error)) {
                try {
                    $pdo->beginTransaction();
                    
                    $stmt = $pdo->prepare("
                        INSERT INTO group_members (group_id, user_id, joined_at)
                        VALUES (?, ?, NOW())
                    ");
                    $stmt->execute([$groupId, $teacherId]);
                    
                    $pdo->commit();
                    
                    $success = "You have successfully joined the group!";
                    // Refresh group data
                    $stmt = $pdo->prepare("
                        SELECT g.*, 
                               COUNT(gm.user_id) as member_count,
                               MAX(CASE WHEN gm.user_id = ? AND gm.is_admin = 1 THEN 1 ELSE 0 END) as is_admin,
                               MAX(CASE WHEN gm.user_id = ? THEN 1 ELSE 0 END) as is_member
                        FROM `groups` g
                        LEFT JOIN group_members gm ON g.id = gm.group_id
                        WHERE g.id = ?
                        GROUP BY g.id
                    ");
                    $stmt->execute([$teacherId, $teacherId, $groupId]);
                    $group = $stmt->fetch();
                } catch (PDOException $e) {
                    $pdo->rollBack();
                    $error = "Failed to join group: " . $e->getMessage();
                }
            }
        }
    }
    elseif (isset($_POST['remove-member'])) {
        $memberId = $_POST['member_id'];
        if ($group['is_admin']) {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    DELETE FROM group_members 
                    WHERE group_id = ? AND user_id = ?
                ");
                $stmt->execute([$groupId, $memberId]);
                
                $pdo->commit();
                $success = "Member removed successfully";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Failed to remove member: " . $e->getMessage();
            }
        } else {
            $error = "You don't have permission to remove members";
        }
    }
    elseif (isset($_POST['promote-admin'])) {
        $memberId = $_POST['member_id'];
        if ($group['is_admin']) {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    UPDATE group_members 
                    SET is_admin = 1 
                    WHERE group_id = ? AND user_id = ?
                ");
                $stmt->execute([$groupId, $memberId]);
                
                $pdo->commit();
                $success = "Member promoted to admin successfully";
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Failed to promote member: " . $e->getMessage();
            }
        } else {
            $error = "You don't have permission to promote members";
        }
    }
    elseif (isset($_POST['share-folder'])) {
        if (!$group['is_member']) {
            $error = "You must be a member to share folders in this group";
        } elseif (empty($_POST['selected_folder'])) {
            $error = "Please select a folder to share";
        } else {
            try {
                $pdo->beginTransaction();
                
                $folderId = $_POST['selected_folder'];
                
                // Check if folder is already shared
                $stmt = $pdo->prepare("
                    SELECT id FROM shared_folders 
                    WHERE group_id = ? AND folder_id = ?
                ");
                $stmt->execute([$groupId, $folderId]);
                if ($stmt->fetch()) {
                    $error = "This folder is already shared in the group";
                } else {
                    // Insert into shared_folders
                    $stmt = $pdo->prepare("
                        INSERT INTO shared_folders (group_id, folder_id, shared_by, shared_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $stmt->execute([$groupId, $folderId, $teacherId]);
                    
                    $pdo->commit();
                    $success = "Your folder has been shared successfully!";
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Failed to share folder: " . $e->getMessage();
            }
        }
    }
    elseif (isset($_POST['leave-group'])) {
        if (!$group['is_member']) {
            $error = "You are not a member of this group";
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare("
                    DELETE FROM group_members 
                    WHERE group_id = ? AND user_id = ?
                ");
                $stmt->execute([$groupId, $teacherId]);
                
                $pdo->commit();
                header('Location: teacher_group.php?left=' . $groupId);
                exit();
            } catch (PDOException $e) {
                $pdo->rollBack();
                $error = "Failed to leave group: " . $e->getMessage();
            }
        }
    }
}

// Fetch user's folders with flashcard counts
$stmt = $pdo->prepare("
    SELECT f.id, f.name, COUNT(fc.id) as flashcard_count
    FROM folders f
    LEFT JOIN flashcards fc ON f.id = fc.folder_id
    WHERE f.user_id = ?
    GROUP BY f.id
    ORDER BY f.name
");
$stmt->execute([$teacherId]);
$userFolders = $stmt->fetchAll();

// Fetch shared folders in the group
$stmt = $pdo->prepare("
    SELECT sf.*, f.name as folder_name, u.first_name, u.last_name,
           (SELECT COUNT(*) FROM flashcards WHERE folder_id = f.id) as flashcard_count
    FROM shared_folders sf
    JOIN folders f ON sf.folder_id = f.id
    JOIN users u ON sf.shared_by = u.id
    WHERE sf.group_id = ?
    ORDER BY sf.shared_at DESC
");
$stmt->execute([$groupId]);
$sharedFolders = $stmt->fetchAll();

// Fetch group members
$stmt = $pdo->prepare("
    SELECT u.id, u.first_name, u.last_name, u.email, gm.joined_at, gm.is_admin
    FROM group_members gm
    JOIN users u ON gm.user_id = u.id
    WHERE gm.group_id = ?
    ORDER BY gm.is_admin DESC, gm.joined_at ASC
");
$stmt->execute([$groupId]);
$members = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?php echo htmlspecialchars($group['name']); ?> - LearnMate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/theme.css">
  <style>

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

    /* Typography */
    h1, h2, h3, h4, h5, h6 {
      font-weight: 600;
      color: var(--text-dark);
    }

    h1 { font-size: 24px; }
    h2 { font-size: 20px; }
    h3 { font-size: 18px; }
    h4 { font-size: 16px; }

    .text-sm { font-size: 14px; }
    .text-xs { font-size: 12px; }

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
      justify-content: space-between;
      align-items: center;
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      cursor: pointer;
      color: var(--text-medium);
      font-weight: 500;
      transition: var(--transition);
    }

    .dropdown-toggle:hover {
      background-color: var(--primary-extra-light);
      color: var(--primary-dark);
    }

    .dropdown-menu {
      display: none;
      position: relative;
      background-color: var(--bg-white);
      border-radius: var(--radius-md);
      padding: var(--space-sm);
      margin-top: var(--space-xs);
      box-shadow: var(--shadow-sm);
    }

    .dropdown-menu.show {
      display: block;
    }

    .dropdown-item {
      display: flex;
      align-items: center;
      padding: var(--space-sm) var(--space-md);
      text-decoration: none;
      color: var(--text-medium);
      border-radius: var(--radius-sm);
      transition: var(--transition);
    }

    .dropdown-item:hover {
      background-color: var(--primary-extra-light);
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
      max-width: 1200px;
      margin: 0 auto;
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

    /* Group Header - Enhanced */
    .group-header {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-lg);
      margin-bottom: var(--space-lg);
      box-shadow: var(--shadow-sm);
      position: relative;
      overflow: hidden;
    }
    
    .group-image-container {
      position: relative;
      margin-bottom: var(--space-md);
      width: 200px;
      height: 200px;
      margin: 0 auto var(--space-md);
    }
    
    .group-image-large {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: var(--radius-full);
      transition: var(--transition);
    }
    
    .group-image-large:hover {
      transform: scale(1.02);
    }
    
    .group-image-placeholder-large {
      width: 100%;
      height: 100%;
      background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%);
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 48px;
    }
    
    .group-title-large {
      font-size: 24px;
      font-weight: 700;
      margin-bottom: var(--space-sm);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }
    
    .group-meta-large {
      display: flex;
      gap: var(--space-md);
      margin-bottom: var(--space-md);
      flex-wrap: wrap;
    }
    
    .group-meta-item {
      display: flex;
      align-items: center;
      gap: var(--space-xs);
      font-size: 14px;
      color: var(--text-medium);
    }
    
    .group-meta-item i {
      color: var(--primary);
    }
    
    .group-description-large {
      font-size: 15px;
      line-height: 1.6;
      color: var(--text-medium);
      margin-bottom: var(--space-lg);
      padding: var(--space-md);
      background-color: var(--bg-light);
      border-radius: var(--radius-md);
      border-left: 3px solid var(--primary);
    }
    
    .group-actions {
      display: flex;
      gap: var(--space-sm);
      margin-top: var(--space-md);
      flex-wrap: wrap;
    }

    /* Cards */
    .card {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-lg);
      margin-bottom: var(--space-lg);
      box-shadow: var(--shadow-sm);
      transition: var(--transition);
    }

    .card:hover {
      box-shadow: var(--shadow-md);
    }

    .card-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--space-lg);
      padding-bottom: var(--space-sm);
      border-bottom: 1px solid var(--border-light);
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

    .tab:hover {
      color: var(--primary);
    }

    .tab.active {
      color: var(--primary);
      border-bottom-color: var(--primary);
    }

    .tab-content {
      display: none;
    }

    .tab-content.active {
      display: block;
    }

    /* Members List - Enhanced */
    .members-grid {
      display: flex;
      flex-direction: column;
      gap: var(--space-sm);
    }
    
    .member-card {
      display: flex;
      align-items: center;
      padding: var(--space-md);
      border-radius: var(--radius-md);
      background-color: var(--bg-white);
      border: 1px solid var(--border-light);
      transition: var(--transition);
      width: 100%;
    }
    
    .member-card:hover {
      border-color: var(--primary);
      transform: translateX(4px);
    }
    
    .member-avatar {
      width: 40px;
      height: 40px;
      background-color: var(--primary-light);
      color: white;
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      margin-right: var(--space-md);
      flex-shrink: 0;
      font-size: 14px;
    }
    
    .member-info {
      flex: 1;
      min-width: 0;
    }
    
    .member-name {
      font-weight: 600;
      font-size: 15px;
      margin-bottom: 4px;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .member-email {
      font-size: 13px;
      color: var(--text-light);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    
    .member-meta {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      margin-left: var(--space-md);
    }
    
    .member-role {
      font-size: 12px;
      padding: 4px 8px;
      border-radius: var(--radius-sm);
      background-color: var(--primary);
      color: white;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      box-shadow: var(--shadow-xs);
      white-space: nowrap;
    }
    
    .member-joined {
      font-size: 12px;
      color: var(--text-light);
    }

    /* Shared Folders - Enhanced */
    .folders-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: var(--space-md);
    }
    
    .folder-card {
      display: flex;
      flex-direction: column;
      padding: var(--space-md);
      border-radius: var(--radius-md);
      background-color: var(--bg-light);
      border: 1px solid var(--border-light);
      transition: var(--transition);
    }
    
    .folder-card:hover {
      border-color: var(--primary);
      transform: translateY(-2px);
    }
    
    .folder-icon {
      font-size: 32px;
      color: var(--primary);
      margin-bottom: var(--space-sm);
    }
    
    .folder-name {
      font-weight: 600;
      margin-bottom: var(--space-xs);
    }
    
    .folder-meta {
      font-size: 14px;
      color: var(--text-light);
      margin-bottom: var(--space-md);
    }
    
    .folder-shared-by {
      display: flex;
      align-items: center;
      margin-top: auto;
      padding-top: var(--space-md);
      border-top: 1px solid var(--border-light);
    }
    
    .folder-shared-by-avatar {
      width: 24px;
      height: 24px;
      background-color: var(--primary-light);
      color: white;
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 10px;
      margin-right: var(--space-sm);
    }
    
    .folder-shared-by-info {
      font-size: 12px;
      color: var(--text-light);
    }
    
    .folder-actions {
      display: flex;
      justify-content: flex-end;
      margin-top: var(--space-sm);
    }

    /* Forms - Enhanced */
    .form-card {
      background-color: var(--bg-white);
      padding: var(--space-lg);
      border-radius: var(--radius-lg);
      margin-bottom: var(--space-lg);
      box-shadow: var(--shadow-sm);
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
      min-height: 120px;
      resize: vertical;
    }

    /* Buttons - Enhanced */
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
      text-decoration: none;
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
      transform: translateY(-1px);
      box-shadow: var(--shadow-sm);
    }

    .btn-outline {
      background-color: transparent;
      border: 1px solid var(--border-light);
      color: var(--text-medium);
      text-decoration: none;
    }

    .btn-outline:hover {
      background-color: var(--bg-light);
      border-color: var(--primary);
      color: var(--primary);
    }

    .btn-sm {
      padding: var(--space-xs) var(--space-sm);
      font-size: 13px;
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
      box-shadow: var(--shadow-sm);
    }

    /* Alerts - Enhanced */
    .alert {
      padding: var(--space-md);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-lg);
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .alert i {
      font-size: 18px;
    }

    .alert-danger {
      background-color: var(--danger-light);
      color: var(--danger);
      border: 1px solid var(--danger-light);
    }

    .alert-success {
      background-color: var(--success-light);
      color: var(--success);
      border: 1px solid var(--success-light);
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: var(--space-xl);
      color: var(--text-light);
    }

    .empty-state i {
      font-size: 48px;
      margin-bottom: var(--space-md);
      opacity: 0.5;
    }

    .empty-state p {
      margin-bottom: var(--space-md);
    }

    /* Bottom Navigation */
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

    /* Confirmation Modal Styles */
    .confirmation-modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 1000;
      justify-content: center;
      align-items: center;
    }

    .modal-content {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      width: 90%;
      max-width: 400px;
      padding: var(--space-lg);
      box-shadow: var(--shadow-xl);
      animation: fadeIn 0.3s ease-out;
    }

    .modal-header {
      margin-bottom: var(--space-lg);
    }

    .modal-title {
      font-size: 18px;
      font-weight: 600;
      color: var(--text-dark);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .modal-body {
      margin-bottom: var(--space-lg);
      color: var(--text-medium);
    }

    .modal-footer {
      display: flex;
      justify-content: flex-end;
      gap: var(--space-sm);
    }

    .modal-btn {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      font-weight: 600;
      cursor: pointer;
      transition: var(--transition);
    }

    .modal-btn-cancel {
      background-color: var(--bg-light);
      border: 1px solid var(--border-light);
      color: var(--text-medium);
    }

    .modal-btn-cancel:hover {
      background-color: var(--border-light);
    }

    .modal-btn-confirm {
      background-color: var(--danger);
      color: white;
      border: none;
    }

    .modal-btn-confirm:hover {
      background-color: #D92D20;
    }

    /* Responsive Design */
    @media (min-width: 640px) {
      .main-content {
        padding: var(--space-lg);
      }
      
      .group-image-container {
        width: 240px;
        height: 240px;
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
      
      .group-image-container {
        width: 280px;
        height: 280px;
      }
      
      .members-grid {
        grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      }
    }

    @media (min-width: 1024px) {
      .group-header {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: var(--space-md);
        padding: var(--space-lg);
      }
      
      .group-image-container {
        margin: var(--space-md) auto;
        width: 300px;
        height: 300px;
      }
      
      .group-header-content {
        padding: var(--space-md);
        margin-top: var(--space-md);
      }
      
      .folders-grid {
        grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
      }
    }

    @media (min-width: 1200px) {
      .main-content {
        padding: var(--space-xl) var(--space-2xl);
      }
    }

    /* Animations */
    @keyframes fadeIn {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .animate-fade {
      animation: fadeIn 0.3s ease-out forwards;
    }

    .back-button-container {
      position: absolute;
      bottom: var(--space-lg);
      right: var(--space-lg);
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
          <i class="fas fa-layer-group"></i>
          <span>Flashcard Decks</span>
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
          <a href="teacher_group.php" class="header-btn">
            <i class="fas fa-arrow-left"></i>
          </a>
          <h1 class="header-title">Group Details</h1>
        </div>
        <div class="header-actions">
          <?php if ($group['is_member']): ?>
            <button class="header-btn" onclick="document.getElementById('createPostForm').style.display='block'">
              <i class="fas fa-folder-open"></i>
            </button>
          <?php endif; ?>
        </div>
      </header>

      <!-- Group Header - Enhanced -->
      <div class="group-header animate-fade">
        <div class="group-image-container">
          <?php if ($group['image_url']): ?>
            <img src="<?php echo htmlspecialchars($group['image_url']); ?>" alt="<?php echo htmlspecialchars($group['name']); ?>" class="group-image-large">
          <?php else: ?>
            <div class="group-image-placeholder-large">
              <i class="fas fa-users"></i>
            </div>
          <?php endif; ?>
        </div>
        
        <div class="group-header-content">
          <h1 class="group-title-large">
            <?php echo htmlspecialchars($group['name']); ?>
          </h1>
          
          <div class="group-meta-large">
            <div class="group-meta-item">
              <i class="fas fa-users"></i>
              <span><?php echo $group['member_count']; ?> member<?php echo $group['member_count'] != 1 ? 's' : ''; ?></span>
            </div>
            <div class="group-meta-item">
              <i class="fas fa-<?php echo $group['privacy'] === 'public' ? 'globe' : 'lock'; ?>"></i>
              <span><?php echo ucfirst($group['privacy']); ?></span>
            </div>
          </div>
          
          <?php if (!empty($group['description'])): ?>
            <div class="group-description-large"><?php echo nl2br(htmlspecialchars($group['description'])); ?></div>
          <?php endif; ?>
          
          <?php if ($group['is_member']): ?>
            <div class="group-actions">
              <button class="btn btn-primary" onclick="document.getElementById('createPostForm').style.display='block'">
                <i class="fas fa-folder-open"></i> Share Folder
              </button>
              <?php if ($group['is_admin']): ?>
                <a href="teacher_manage_group.php?id=<?php echo $groupId; ?>" class="btn btn-outline">
                  <i class="fas fa-cog"></i> Manage Group
                </a>
              <?php endif; ?>
              <button type="button" class="btn btn-outline" style="color: var(--danger);" 
                      onclick="document.getElementById('leaveGroupModal').style.display='flex'">
                <i class="fas fa-sign-out-alt"></i> Leave Group
              </button>
            </div>
          <?php endif; ?>

          <div class="back-button-container">
            <a href="teacher_group.php" class="btn btn-outline">
              <i class="fas fa-arrow-left"></i> Back
            </a>
          </div>
        </div>
      </div>
      
      <!-- Join Group Form (for non-members) -->
      <?php if (!$group['is_member']): ?>
        <div class="form-card animate-fade">
          <?php if ($success): ?>
            <div class="alert" style="background-color: #ECFDF3; color: #027A48; border: 1px solid #ABEFC6;">
              <div style="display: flex; align-items: center; gap: var(--space-sm);">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
              </div>
            </div>
          <?php elseif ($error): ?>
            <div class="alert" style="background-color: #FEF3F2; color: #B42318; border: 1px solid #FDA29B;">
              <div style="display: flex; align-items: center; gap: var(--space-sm);">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
              </div>
            </div>
          <?php endif; ?>
          
          <h3 style="margin-bottom: var(--space-md);">Join this group</h3>
          
          <form method="POST">
            <?php if ($group['privacy'] === 'private'): ?>
              <div class="form-group">
                <label for="passcode" class="form-label required">Passcode</label>
                <input type="password" id="passcode" name="passcode" class="form-control" 
                       placeholder="Enter group passcode" required>
                <small class="text-xs" style="color: var(--text-light); display: block; margin-top: var(--space-xs);">
                  This is a private group - you need the passcode to join
                </small>
              </div>
            <?php endif; ?>
            
            <button type="submit" name="join-group" class="btn-submit">
              <i class="fas fa-user-plus"></i>
              Join Group
            </button>
          </form>
        </div>
      <?php endif; ?>
      
      <!-- Tab Navigation -->
      <?php if ($group['is_member']): ?>
        <?php if ($success): ?>
          <div class="alert" style="background-color: #ECFDF3; color: #027A48; border: 1px solid #ABEFC6;">
            <div style="display: flex; align-items: center; gap: var(--space-sm);">
              <i class="fas fa-check-circle"></i>
              <span><?php echo htmlspecialchars($success); ?></span>
            </div>
          </div>
        <?php elseif ($error): ?>
          <div class="alert" style="background-color: #FEF3F2; color: #B42318; border: 1px solid #FDA29B;">
            <div style="display: flex; align-items: center; gap: var(--space-sm);">
              <i class="fas fa-exclamation-circle"></i>
              <span><?php echo htmlspecialchars($error); ?></span>
            </div>
          </div>
        <?php endif; ?>
        <div class="card">
          <div class="tabs">
            <div class="tab active" onclick="switchTab('members')">
              <i class="fas fa-users"></i> Members
            </div>
            <div class="tab" onclick="switchTab('folders')">
              <i class="fas fa-folder-open"></i> Shared Folders
            </div>
          </div>
          
          <!-- Members Tab -->
          <div id="members-tab" class="tab-content active">
            <div class="members-grid">
              <?php foreach ($members as $member): ?>
                <div class="member-card">
                  <div class="member-avatar">
                    <?php 
                      $words = explode(' ', $member['first_name'] . ' ' . $member['last_name']);
                      $initials = '';
                      foreach ($words as $word) {
                        $initials .= strtoupper(substr($word, 0, 1));
                        if (strlen($initials) >= 2) break;
                      }
                      echo $initials;
                    ?>
                  </div>
                  <div class="member-info">
                    <div class="member-name"><?php echo htmlspecialchars($member['first_name'] . ' ' . $member['last_name']); ?></div>
                    <div class="member-email"><?php echo htmlspecialchars($member['email']); ?></div>
                  </div>
                  <div class="member-meta">
                    <?php if ($member['is_admin']): ?>
                      <div class="member-role">Admin</div>
                    <?php endif; ?>
                    <div class="member-joined"><?php echo date('M j', strtotime($member['joined_at'])); ?></div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          
          <!-- Shared Folders Tab -->
          <div id="folders-tab" class="tab-content">
            <?php if (empty($sharedFolders)): ?>
              <div class="empty-state">
                <i class="fas fa-folder-open"></i>
                <p>No folders shared yet. Be the first to share!</p>
                <button class="btn btn-primary" onclick="document.getElementById('createPostForm').style.display='block'">
                  <i class="fas fa-folder-plus"></i> Share a Folder
                </button>
              </div>
            <?php else: ?>
              <div class="folders-grid">
                <?php foreach ($sharedFolders as $folder): ?>
                  <div class="folder-card animate-fade">
                    <i class="fas fa-folder folder-icon"></i>
                    <div class="folder-name"><?php echo htmlspecialchars($folder['folder_name']); ?></div>
                    <div class="folder-meta"><?php echo $folder['flashcard_count']; ?> flashcards</div>
                    
                    <div class="folder-shared-by">
                      <div class="folder-shared-by-avatar">
                        <?php 
                          $words = explode(' ', $folder['first_name'] . ' ' . $folder['last_name']);
                          $initials = '';
                          foreach ($words as $word) {
                            $initials .= strtoupper(substr($word, 0, 1));
                            if (strlen($initials) >= 2) break;
                          }
                          echo $initials;
                        ?>
                      </div>
                      <div class="folder-shared-by-info">
                        Shared by <?php echo htmlspecialchars($folder['first_name'] . ' ' . $folder['last_name']); ?>
                        <div class="text-xs"><?php echo date('M j, Y', strtotime($folder['shared_at'])); ?></div>
                      </div>
                    </div>
                    
                    <div class="folder-actions">
                      <a href="teacher_view_shared_folder.php?group_id=<?php echo $groupId; ?>&folder_id=<?php echo $folder['folder_id']; ?>" 
                        class="btn btn-primary btn-sm">
                        <i class="fas fa-eye"></i> View Flashcards
                      </a>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endif; ?>
      
      <!-- Create Post Form (for members) -->
      <?php if ($group['is_member']): ?>
        <div class="form-card animate-fade" id="createPostForm" style="<?php echo isset($_POST['share-folder']) ? '' : 'display: none;'; ?>">
          <?php if ($error && isset($_POST['share-folder'])): ?>
            <div class="alert" style="background-color: #FEF3F2; color: #B42318; border: 1px solid #FDA29B;">
              <div style="display: flex; align-items: center; gap: var(--space-sm);">
                <i class="fas fa-exclamation-circle"></i>
                <span><?php echo htmlspecialchars($error); ?></span>
              </div>
            </div>
          <?php elseif ($success && isset($_POST['share-folder'])): ?>
            <div class="alert" style="background-color: #ECFDF3; color: #027A48; border: 1px solid #ABEFC6;">
              <div style="display: flex; align-items: center; gap: var(--space-sm);">
                <i class="fas fa-check-circle"></i>
                <span><?php echo htmlspecialchars($success); ?></span>
              </div>
            </div>
          <?php endif; ?>
          
          <div class="card-header">
            <h3>Share Folder</h3>
            <button class="btn btn-outline btn-sm" onclick="document.getElementById('createPostForm').style.display='none'">
              <i class="fas fa-times"></i>
            </button>
          </div>
          
          <form method="POST">
            <div class="form-group">
              <label class="form-label">Select Folder to Share</label>
              <div class="folders-grid">
                <?php foreach ($userFolders as $folder): ?>
                  <label class="folder-card">
                    <input type="radio" name="selected_folder" value="<?php echo $folder['id']; ?>" required style="display: none;">
                    <div class="folder-preview">
                      <i class="fas fa-folder folder-icon"></i>
                      <div class="folder-name"><?php echo htmlspecialchars($folder['name']); ?></div>
                      <div class="folder-meta"><?php echo $folder['flashcard_count']; ?> flashcards</div>
                    </div>
                  </label>
                <?php endforeach; ?>
              </div>
            </div>
            <button type="submit" name="share-folder" class="btn-submit">
              <i class="fas fa-share-alt"></i>
              Share Selected Folder
            </button>
          </form>
        </div>
      <?php endif; ?>
    </main>
  </div>

  <!-- Leave Group Confirmation Modal -->
  <div id="leaveGroupModal" class="confirmation-modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 class="modal-title">
          <i class="fas fa-exclamation-triangle" style="color: var(--warning);"></i>
          Leave Group Confirmation
        </h3>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to leave the group "<?php echo htmlspecialchars($group['name']); ?>"?</p>
        <p class="text-sm" style="color: var(--text-light); margin-top: var(--space-sm);">
          You won't be able to access shared folders unless you're re-join the group.
        </p>
      </div>
      <div class="modal-footer">
        <button class="modal-btn modal-btn-cancel" onclick="document.getElementById('leaveGroupModal').style.display='none'">
          Cancel
        </button>
        <form method="POST" style="display: inline;">
          <button type="submit" name="leave-group" class="modal-btn modal-btn-confirm">
            <i class="fas fa-sign-out-alt"></i> Leave Group
          </button>
        </form>
      </div>
    </div>
  </div>

  <!-- Bottom Navigation with Fixed FAB - Mobile Only -->
  <div class="bottom-nav-container">
    <nav class="bottom-nav">
      <a href="teacher_dashboard.php" class="nav-item-mobile">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="teacher_classes.php" class="nav-item-mobile">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Classes</span>
      </a>
      
      <div class="fab-container">
        <button class="fab" id="mobileCreateGroupBtn" onclick="location.href='teacher_create_class.php'">
          <i class="fas fa-plus"></i>
        </button>
      </div>
      
      <div style="width: 25%;"></div>
      
      <a href="teacher_group.php" class="nav-item-mobile active">
        <i class="fas fa-users"></i>
        <span>Groups</span>
      </a>
      <a href="#" class="nav-item-mobile">
        <i class="fas fa-chart-line"></i>
        <span>Reports</span>
      </a>
    </nav>
  </div>

  <script>
    function toggleDropdown(element) {
      element.classList.toggle('active');
      const menu = element.parentElement.querySelector('.dropdown-menu');
      menu.classList.toggle('show');
    }
    
    function switchTab(tabName) {
      document.querySelectorAll('.tab-content').forEach(tab => {
        tab.classList.remove('active');
      });
      
      document.querySelectorAll('.tabs .tab').forEach(tab => {
        tab.classList.remove('active');
      });
      
      document.getElementById(tabName + '-tab').classList.add('active');
      event.currentTarget.classList.add('active');
    }
    
    function togglePostForm() {
      const form = document.getElementById('createPostForm');
      if (form.style.display === 'none' || !form.style.display) {
        form.style.display = 'block';
        form.classList.add('animate-fade');
      } else {
        form.classList.remove('animate-fade');
        form.style.display = 'none';
      }
    }
    
    document.addEventListener('DOMContentLoaded', function() {
      // Auto-hide success alerts after 3 seconds
      const successAlerts = document.querySelectorAll('.alert[style*="background-color: #ECFDF3"]');
      successAlerts.forEach(alert => {
        setTimeout(() => {
          alert.style.opacity = '0';
          alert.style.transition = 'opacity 0.5s ease';
          setTimeout(() => {
            alert.style.display = 'none';
          }, 500);
        }, 3000);
      });
      
      switchTab('members');
    });

    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('leaveGroupModal');
      if (event.target == modal) {
        modal.style.display = 'none';
      }
    }
  </script>
</body>
</html>