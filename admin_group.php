<?php
// admin_group.php
session_start();
require 'db.php';
require 'includes/theme.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

// Get theme for the page
$theme = getCurrentTheme();

// Handle group deletion
if (isset($_GET['delete'])) {
    $groupId = $_GET['delete'];
    
    try {
        $pdo->beginTransaction();
        
        // Delete group members first
        $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
        $stmt->execute([$groupId]);
        
        // Delete shared folders
        $stmt = $pdo->prepare("DELETE FROM shared_folders WHERE group_id = ?");
        $stmt->execute([$groupId]);
        
        // Delete group posts and comments
        $stmt = $pdo->prepare("SELECT id FROM group_posts WHERE group_id = ?");
        $stmt->execute([$groupId]);
        $postIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (!empty($postIds)) {
            $placeholders = implode(',', array_fill(0, count($postIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM post_comments WHERE post_id IN ($placeholders)");
            $stmt->execute($postIds);
            
            $stmt = $pdo->prepare("DELETE FROM group_posts WHERE id IN ($placeholders)");
            $stmt->execute($postIds);
        }
        
        // Get group image path before deletion
        $stmt = $pdo->prepare("SELECT image_url FROM `groups` WHERE id = ?");
        $stmt->execute([$groupId]);
        $group = $stmt->fetch();
        
        // Delete the group
        $stmt = $pdo->prepare("DELETE FROM `groups` WHERE id = ?");
        $stmt->execute([$groupId]);
        
        $pdo->commit();
        
        // Delete group image file if exists
        if ($group && $group['image_url'] && file_exists($group['image_url'])) {
            unlink($group['image_url']);
        }
        
        header('Location: admin_group.php?deleted=1');
        exit();
    } catch (PDOException $e) {
        $pdo->rollBack();
        header('Location: admin_group.php?error=1');
        exit();
    }
}

// Handle add group
if (isset($_POST['add_group_name'])) {
    $name = $_POST['add_group_name'];
    $description = $_POST['add_group_description'];

    $privacy = $_POST['add_group_privacy'];
    $created_by = $_SESSION['user_id'];
    $image_url = null;

    // Handle image upload if provided
    if (isset($_FILES['add_group_image']) && $_FILES['add_group_image']['error'] === UPLOAD_ERR_OK) {
        $imgTmp = $_FILES['add_group_image']['tmp_name'];
        $imgName = basename($_FILES['add_group_image']['name']);
        $imgExt = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imgExt, $allowed)) {
            $newName = 'uploads/groups/' . uniqid('group_', true) . '.' . $imgExt;
            if (!is_dir('uploads/groups')) { mkdir('uploads/groups', 0777, true); }
            if (move_uploaded_file($imgTmp, $newName)) {
                $image_url = $newName;
            }
        }
    }

    $stmt = $pdo->prepare("INSERT INTO `groups` (name, description, privacy, created_by, image_url, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    if ($stmt->execute([$name, $description,  $privacy, $created_by, $image_url])) {
        header('Location: admin_group.php?added=1');
        exit();
    } else {
        header('Location: admin_group.php?adderror=1');
        exit();
    }
}

// Handle edit group
if (isset($_POST['edit_group_id'])) {
    $id = $_POST['edit_group_id'];
    $name = $_POST['edit_group_name'];
    $description = $_POST['edit_group_description'];

    $privacy = $_POST['edit_group_privacy'];
    $image_url = null;

    // Get current image
    $stmt = $pdo->prepare("SELECT image_url FROM `groups` WHERE id = ?");
    $stmt->execute([$id]);
    $group = $stmt->fetch();
    $current_image = $group ? $group['image_url'] : null;

    // Handle image upload if provided
    if (isset($_FILES['edit_group_image']) && $_FILES['edit_group_image']['error'] === UPLOAD_ERR_OK) {
        $imgTmp = $_FILES['edit_group_image']['tmp_name'];
        $imgName = basename($_FILES['edit_group_image']['name']);
        $imgExt = strtolower(pathinfo($imgName, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($imgExt, $allowed)) {
            $newName = 'uploads/groups/' . uniqid('group_', true) . '.' . $imgExt;
            if (!is_dir('uploads/groups')) { mkdir('uploads/groups', 0777, true); }
            if (move_uploaded_file($imgTmp, $newName)) {
                $image_url = $newName;
                // Delete old image if exists
                if ($current_image && file_exists($current_image)) {
                    unlink($current_image);
                }
            }
        }
    } else {
        $image_url = $current_image;
    }

    $stmt = $pdo->prepare("UPDATE `groups` SET name=?, description=?,  privacy=?, image_url=? WHERE id=?");
    if ($stmt->execute([$name, $description,  $privacy, $image_url, $id])) {
        header('Location: admin_group.php?edited=1');
        exit();
    } else {
        header('Location: admin_group.php?editerror=1');
        exit();
    }
}

// Handle group search and filtering
$search = $_GET['search'] ?? '';

$privacy = $_GET['privacy'] ?? '';

// Build query for groups
$query = "SELECT 
            g.*, 
            COUNT(gm.user_id) as member_count,
            u.first_name as creator_first_name,
            u.last_name as creator_last_name
          FROM `groups` g
          LEFT JOIN group_members gm ON g.id = gm.group_id
          LEFT JOIN users u ON g.created_by = u.id
          WHERE 1=1";

$params = [];

if (!empty($search)) {
    $query .= " AND (g.name LIKE :search OR g.description LIKE :search2)";
    $params[':search'] = "%$search%";
    $params[':search2'] = "%$search%";
}


if (!empty($privacy)) {
    $query .= " AND g.privacy = :privacy";
    $params[':privacy'] = $privacy;
}

$query .= " GROUP BY g.id ORDER BY g.created_at DESC";

// Get total count for pagination
$countQuery = "SELECT COUNT(*) as total FROM `groups`";
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute();
$totalGroups = $countStmt->fetch()['total'];

// Pagination
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;
$totalPages = ceil($totalGroups / $perPage);

$query .= " LIMIT :limit OFFSET :offset";
$params[':limit'] = (int)$perPage;
$params[':offset'] = (int)$offset;

// Execute the query
$stmt = $pdo->prepare($query);
foreach ($params as $key => $value) {
    if (strpos($key, ':') === 0) {
        $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
}
$stmt->execute();
$groups = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Group Management - LearnMate</title>
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
      --bg-dark: #1A1A1A;
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

    /* App Container - Mobile First */
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

    /* Filter Section */
    .filter-section {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      margin-bottom: var(--space-lg);
      box-shadow: var(--shadow-sm);
    }

    .filter-form {
      display: grid;
      grid-template-columns: 1fr;
      gap: var(--space-md);
    }

    .filter-row {
      display: flex;
      flex-wrap: wrap;
      gap: var(--space-md);
      align-items: center;
    }

    .filter-group {
      flex: 1;
      min-width: 200px;
    }

    .filter-label {
      display: block;
      font-size: 14px;
      font-weight: 500;
      margin-bottom: var(--space-xs);
      color: var(--text-dark);
    }

    .filter-input {
      width: 100%;
      padding: var(--space-sm) var(--space-md);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      font-size: 14px;
    }

    .filter-select {
      width: 100%;
      padding: var(--space-sm) var(--space-md);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      font-size: 14px;
      background-color: white;
    }

    .filter-btn {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      border: none;
      background-color: var(--primary);
      color: white;
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
      align-self: flex-end;
      height: 40px;
    }

    .filter-btn:hover {
      background-color: var(--primary-dark);
    }

    .reset-btn {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      border: 1px solid var(--border-light);
      background-color: white;
      color: var(--text-medium);
      font-weight: 500;
      cursor: pointer;
      transition: var(--transition);
      align-self: flex-end;
      height: 40px;
    }

    .reset-btn:hover {
      background-color: var(--bg-light);
    }

    /* Groups Table */
    .groups-table {
      width: 100%;
      border-collapse: collapse;
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }

    .groups-table th {
      background-color: #F9F5FF;
      color: var(--primary-dark);
      font-weight: 600;
      text-align: left;
      padding: var(--space-md);
      font-size: 14px;
    }

    .groups-table td {
      padding: var(--space-md);
      border-bottom: 1px solid var(--border-light);
      font-size: 14px;
      vertical-align: middle;
    }

    .groups-table tr:last-child td {
      border-bottom: none;
    }

    .groups-table tr:hover {
      background-color:var(--bg-light);
    }
    

    .group-image {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-md);
      object-fit: cover;
      margin-right: var(--space-sm);
    }

    .group-image-placeholder {
      width: 40px;
      height: 40px;
      border-radius: var(--radius-md);
      background-color: #F9F5FF;
      display: flex;
      align-items: center;
      justify-content: center;
      color: var(--primary);
      margin-right: var(--space-sm);
    }

    .group-name {
      font-weight: 500;
      color: var(--text-dark);
    }

    .group-creator {
      font-size: 13px;
      color: var(--text-medium);
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

    .action-btn {
      padding: 6px 12px;
      border-radius: var(--radius-sm);
      border: none;
      font-weight: 500;
      font-size: 13px;
      cursor: pointer;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: var(--space-xs);
    }

    .action-btn.view {
      background-color: var(--primary-light);
      color: white;
    }

    .action-btn.view:hover {
      background-color: var(--primary);
    }

    .action-btn.delete {
      background-color: #FEF3F2;
      color: var(--danger);
    }

    .action-btn.delete:hover {
      background-color: #FEE4E2;
    }

    /* Pagination */
    .pagination {
      display: flex;
      justify-content: center;
      margin-top: var(--space-lg);
      gap: var(--space-sm);
    }

    .pagination-btn {
      width: 36px;
      height: 36px;
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--bg-white);
      border: 1px solid var(--border-light);
      color: var(--text-medium);
      cursor: pointer;
      transition: var(--transition);
    }

    .pagination-btn:hover {
      background-color: var(--bg-light);
    }

    .pagination-btn.active {
      background-color: var(--primary);
      color: white;
      border-color: var(--primary);
    }

    .pagination-btn.disabled {
      opacity: 0.5;
      cursor: not-allowed;
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: var(--space-xl);
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
    }

    .empty-state i {
      font-size: 48px;
      color: var(--text-light);
      margin-bottom: var(--space-md);
    }

    .empty-state p {
      color: var(--text-medium);
      margin-bottom: var(--space-md);
    }

    .empty-state .btn {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      border: none;
      background-color: var(--primary);
      color: white;
      font-weight: 500;
      cursor: pointer;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .empty-state .btn:hover {
      background-color: var(--primary-dark);
    }

    /* Success/Error Messages */
    .alert {
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-lg);
      font-size: 14px;
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .alert-success {
      background-color: #ECFDF3;
      color: #027A48;
      border: 1px solid #ABEFC6;
    }

    .alert-error {
      background-color: #FEF3F2;
      color: #B42318;
      border: 1px solid #FDA29B;
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
    @media (min-width: 640px) {
      /* Tablet styles */
      .main-content {
        padding: var(--space-lg);
      }
      
      .filter-form {
        grid-template-columns: 1fr auto;
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
    }

    @media (min-width: 1024px) {
      /* Desktop styles */
      .filter-form {
        grid-template-columns: repeat(4, 1fr) auto auto;
      }
    }

    .modal-content {
      background: white;
      color: #222;
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0,0,0,0.18);
      padding: 32px 28px 24px 28px;
      min-width: 320px;
      max-width: 90vw;
      position: relative;
      width: 100%;
      max-width: 400px;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      border: 1px solid var(--border-light);
    }
    .modal-label {
      display: block;
      font-weight: 600;
      margin-bottom: 6px;
      text-align: left;
    }
    .modal-input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border-light);
      border-radius: 8px;
      font-size: 15px;
      background: var(--bg-light);
      color: var(--text-dark);
      transition: background 0.2s, color 0.2s, border 0.2s;
    }
    .modal-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 2px var(--primary-light);
    }
    .modal-title {
      color: var(--primary-dark);
    }
    .modal-close {
      color: #aaa;
      transition: color 0.2s;
    }
    .modal-close:hover {
      color: var(--danger);
    }
    .modal-msg {
      margin-top: 10px;
      min-height: 18px;
      font-size: 14px;
      text-align: left;
    }
    /* Dark mode for modal */
    body[data-theme="dark"] .modal-content {
      background: var(--bg-light);
      color: var(--text-dark);
      border: 1px solid var(--border-light);
    }
    body[data-theme="dark"] .modal-input {
      background: var(--bg-light);
      color: var(--text-dark);
      border: 1px solid var(--border-light);
    }
    body[data-theme="dark"] .modal-title {
      color: var(--primary-light);
    }
    body[data-theme="dark"] .modal-close {
      color: var(--text-light);
    }
    body[data-theme="dark"] .modal-close:hover {
      color: var(--danger);
    }
  </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
  <div class="app-container">
    <!-- Sidebar - Desktop -->
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
      <!-- Mobile Header -->
      <header class="header">
        <h1 class="header-title">Group Management</h1>
        <div class="header-actions">
          <button class="header-btn">
            <i class="fas fa-search"></i>
          </button>
        </div>
      </header>

      <!-- Success/Error Messages -->
      <?php if (isset($_GET['deleted'])): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i>
          <span>Group has been successfully deleted.</span>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <span>An error occurred while deleting the group.</span>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['added'])): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i>
          <span>Group has been successfully added.</span>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['adderror'])): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <span>An error occurred while adding the group.</span>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['edited'])): ?>
        <div class="alert alert-success">
          <i class="fas fa-check-circle"></i>
          <span>Group has been successfully updated.</span>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['editerror'])): ?>
        <div class="alert alert-error">
          <i class="fas fa-exclamation-circle"></i>
          <span>An error occurred while updating the group.</span>
        </div>
      <?php endif; ?>

      <!-- Add Group Button -->
      <div style="margin-bottom: 24px; text-align: right;">
        <button onclick="openAddGroupModal()" class="btn" style="background: var(--primary); color: white;">
          <i class="fas fa-plus"></i> Add Group
        </button>
      </div>

      <!-- Add Group Modal -->
      <div id="addGroupModal" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:1000; align-items:center; justify-content:center;">
        <div style="background:white; padding:32px; border-radius:12px; max-width:400px; width:100%; position:relative;">
          <h2 style="margin-bottom:16px;">Add Group</h2>
          <form id="addGroupForm" method="POST" enctype="multipart/form-data">
            <div style="margin-bottom:12px;">
              <label>Name</label>
              <input type="text" name="add_group_name" class="filter-input" required>
            </div>
            <div style="margin-bottom:12px;">
              <label>Description</label>
              <textarea name="add_group_description" class="filter-input" required></textarea>
            </div>

            <div style="margin-bottom:12px;">
              <label>Privacy</label>
              <select name="add_group_privacy" class="filter-select" required>
                <option value="public">Public</option>
                <option value="private">Private</option>
              </select>
            </div>
            <div style="margin-bottom:12px;">
              <label>Image</label>
              <input type="file" name="add_group_image" accept="image/*" class="filter-input">
            </div>
            <div style="text-align:right;">
              <button type="button" onclick="closeAddGroupModal()" class="btn" style="margin-right:8px; background:#eee; color:#333;">Cancel</button>
              <button type="submit" class="btn" style="background: var(--primary); color: white;">Add</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Filter Section -->
      <div class="filter-section">
        <form method="GET" class="filter-form">
          <div class="filter-group">
            <label for="search" class="filter-label">Search</label>
            <input type="text" id="search" name="search" class="filter-input" placeholder="Search groups..." value="<?php echo htmlspecialchars($search); ?>">
          </div>
          

          
          <div class="filter-group">
            <label for="privacy" class="filter-label">Privacy</label>
            <select id="privacy" name="privacy" class="filter-select">
              <option value="">All Privacy</option>
              <option value="public" <?php echo $privacy === 'public' ? 'selected' : ''; ?>>Public</option>
              <option value="private" <?php echo $privacy === 'private' ? 'selected' : ''; ?>>Private</option>
            </select>
          </div>
          
          <button type="submit" class="filter-btn">
            <i class="fas fa-filter"></i>
            <span>Filter</span>
          </button>
          
          <a href="admin_group.php" class="reset-btn">
            <i class="fas fa-undo"></i>
            <span>Reset</span>
          </a>
        </form>
      </div>

      <!-- Groups Table -->
      <?php if (empty($groups)): ?>
        <div class="empty-state">
          <i class="fas fa-users-slash"></i>
          <h3>No Groups Found</h3>
          <p>There are no groups matching your criteria.</p>
          <a href="admin_group.php" class="btn">
            <i class="fas fa-undo"></i>
            <span>Reset Filters</span>
          </a>
        </div>
      <?php else: ?>
        <div style="overflow-x: auto;">
          <table class="groups-table">
            <thead>
              <tr>
                <th>Group</th>
                <th>Creator</th>
                <th>Members</th>
                <th>Privacy</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($groups as $group): ?>
                <tr>
                  <td>
                    <div style="display: flex; align-items: center;">
                      <?php if ($group['image_url']): ?>
                        <img src="<?php echo htmlspecialchars($group['image_url']); ?>" alt="<?php echo htmlspecialchars($group['name']); ?>" class="group-image">
                      <?php else: ?>
                        <div class="group-image-placeholder">
                          <i class="fas fa-users"></i>
                        </div>
                      <?php endif; ?>
                      <div>
                        <div class="group-name"><?php echo htmlspecialchars($group['name']); ?></div>
                        <div class="group-creator"><?php echo htmlspecialchars(substr($group['description'], 0, 30) . (strlen($group['description']) > 30 ? '...' : '')); ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <?php echo htmlspecialchars($group['creator_first_name'] . ' ' . htmlspecialchars($group['creator_last_name'])); ?>
                  </td>
 
                  <td>
                    <?php echo $group['member_count']; ?>
                  </td>
                  <td>
                    <?php if ($group['privacy'] === 'public'): ?>
                      <span class="badge badge-success">
                        <i class="fas fa-globe"></i> Public
                      </span>
                    <?php else: ?>
                      <span class="badge badge-warning">
                        <i class="fas fa-lock"></i> Private
                      </span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php echo date('M j, Y', strtotime($group['created_at'])); ?>
                  </td>
                  <td>
                    <div style="display: flex; gap: var(--space-sm);">
                      <!-- Edit Button -->
                      <button onclick="openEditGroupModal(<?php echo $group['id']; ?>, '<?php echo htmlspecialchars(addslashes($group['name'])); ?>', '<?php echo htmlspecialchars(addslashes($group['description'])); ?>', '<?php echo $group['privacy']; ?>', '<?php echo htmlspecialchars(addslashes($group['image_url'])); ?>')" class="action-btn view" style="background: var(--warning); color: white;">
                        <i class="fas fa-edit"></i>
                        <span>Edit</span>
                      </button>
                      <button onclick="confirmDelete(<?php echo $group['id']; ?>)" class="action-btn delete">
                        <i class="fas fa-trash-alt"></i>
                        <span>Delete</span>
                      </button>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
          <div class="pagination">
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => 1])); ?>" class="pagination-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
              <i class="fas fa-angle-double-left"></i>
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => max(1, $page - 1)])); ?>" class="pagination-btn <?php echo $page == 1 ? 'disabled' : ''; ?>">
              <i class="fas fa-angle-left"></i>
            </a>
            
            <?php 
              $start = max(1, min($page - 2, $totalPages - 4));
              $end = min($totalPages, $start + 4);
              
              for ($i = $start; $i <= $end; $i++): 
            ?>
              <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>" class="pagination-btn <?php echo $i == $page ? 'active' : ''; ?>">
                <?php echo $i; ?>
              </a>
            <?php endfor; ?>
            
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => min($totalPages, $page + 1)])); ?>" class="pagination-btn <?php echo $page == $totalPages ? 'disabled' : ''; ?>">
              <i class="fas fa-angle-right"></i>
            </a>
            <a href="?<?php echo http_build_query(array_merge($_GET, ['page' => $totalPages])); ?>" class="pagination-btn <?php echo $page == $totalPages ? 'disabled' : ''; ?>">
              <i class="fas fa-angle-double-right"></i>
            </a>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <!-- Edit Group Modal -->
      <div id="editGroupModal" class="modal-card" style="display:none; position:fixed; top:0; left:0; width:100vw; height:100vh; background:rgba(0,0,0,0.3); z-index:1000; align-items:center; justify-content:center;">
        <div class="modal-content" style="background:white; color:#222; border-radius:12px; box-shadow:0 8px 32px rgba(0,0,0,0.18); padding:32px 28px 24px 28px; min-width:320px; max-width:90vw; position:relative; width:100%; max-width:400px; display:flex; flex-direction:column; gap:0.5rem;">
          <button type="button" class="modal-close" onclick="closeEditGroupModal()" aria-label="Close" style="position:absolute; top:12px; right:16px; background:none; border:none; font-size:1.6rem; color:#aaa; cursor:pointer; transition:color 0.2s; z-index:2;">&times;</button>
          <h2 class="modal-title" style="font-size:1.3rem; font-weight:700; margin-bottom:18px; color:var(--primary-dark); text-align:left;">Edit Group</h2>
          <form id="editGroupForm" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="edit_group_id" id="edit_group_id">
            <div class="modal-group" style="margin-bottom:14px;">
              <label for="edit_group_name" class="modal-label" style="display:block; font-weight:600; margin-bottom:6px; text-align:left;">Name</label>
              <input type="text" name="edit_group_name" id="edit_group_name" class="modal-input" required>
            </div>
            <div class="modal-group" style="margin-bottom:14px;">
              <label for="edit_group_description" class="modal-label" style="display:block; font-weight:600; margin-bottom:6px; text-align:left;">Description</label>
              <textarea name="edit_group_description" id="edit_group_description" class="modal-input" required></textarea>
            </div>
            <div class="modal-group" style="margin-bottom:14px;">
              <label for="edit_group_privacy" class="modal-label" style="display:block; font-weight:600; margin-bottom:6px; text-align:left;">Privacy</label>
              <select name="edit_group_privacy" id="edit_group_privacy" class="modal-input" required>
                <option value="public">Public</option>
                <option value="private">Private</option>
              </select>
            </div>
            <div class="modal-group" style="margin-bottom:14px;">
              <label for="edit_group_image" class="modal-label" style="display:block; font-weight:600; margin-bottom:6px; text-align:left;">Image</label>
              <input type="file" name="edit_group_image" id="edit_group_image" accept="image/*" class="modal-input">
              <div id="current_group_image" style="margin-top:8px;"></div>
            </div>
            <div class="modal-actions" style="display:flex; gap:10px; margin-top:10px;">
              <button type="button" onclick="closeEditGroupModal()" class="btn" style="flex:1; background:#eee; color:#333;">Cancel</button>
              <button type="submit" class="btn btn-primary" style="flex:1; background: var(--primary); color: white;">Save</button>
            </div>
          </form>
          <div id="editGroupMsg" class="modal-msg" style="margin-top:10px; min-height:18px; font-size:14px; text-align:left;"></div>
        </div>
      </div>
    </main>
  </div>

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
      <a href="logout.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'logout.php') ? ' active' : ''; ?>">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
      <a href="admin_classes.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_classes.php') ? ' active' : ''; ?>">
        <i class="fas fa-chalkboard"></i>
        <span>Classes</span>
      </a>
      <a href="admin_group.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_group.php') ? ' active' : ''; ?>">
        <i class="fas fa-users"></i>
        <span>Groups</span>
      </a>
      <a href="admin_logs.php" class="nav-item-mobile<?php echo (basename($_SERVER['PHP_SELF']) == 'admin_logs.php') ? ' active' : ''; ?>">
        <i class="fas fa-clipboard-list"></i>
        <span>Logs</span>
      </a>
    </nav>
  </div>

  <script>
    function confirmDelete(groupId) {
      if (confirm('Are you sure you want to delete this group? This action cannot be undone.')) {
        window.location.href = 'admin_group.php?delete=' + groupId;
      }
    }

    // Add Group Modal Functions
    function openAddGroupModal() {
      document.getElementById('addGroupModal').style.display = 'flex';
    }
    function closeAddGroupModal() {
      document.getElementById('addGroupModal').style.display = 'none';
    }

    // Edit Group Modal Functions
    function openEditGroupModal(id, name, description,  privacy, imageUrl) {
      document.getElementById('edit_group_id').value = id;
      document.getElementById('edit_group_name').value = name;
      document.getElementById('edit_group_description').value = description;

      document.getElementById('edit_group_privacy').value = privacy;
      if (imageUrl) {
        document.getElementById('current_group_image').innerHTML = '<img src="' + imageUrl + '" alt="Current Image" style="width:60px; height:60px; border-radius:8px;">';
      } else {
        document.getElementById('current_group_image').innerHTML = '';
      }
      document.getElementById('editGroupModal').style.display = 'flex';
    }
    function closeEditGroupModal() {
      document.getElementById('editGroupModal').style.display = 'none';
    }
  </script>
</body>
</html>