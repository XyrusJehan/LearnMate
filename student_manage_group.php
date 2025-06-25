<?php
session_start();
require 'db.php';
require 'includes/theme.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$studentId = $_SESSION['user_id'];
$theme = getCurrentTheme();

// Get group ID from URL
if (!isset($_GET['id'])) {
    header('Location: student_group.php');
    exit();
}

$groupId = $_GET['id'];

// Fetch group details and verify admin status
$stmt = $pdo->prepare("
    SELECT g.*, 
           COUNT(gm.user_id) as member_count,
           MAX(CASE WHEN gm.user_id = ? AND gm.is_admin = 1 THEN 1 ELSE 0 END) as is_admin
    FROM `groups` g
    LEFT JOIN group_members gm ON g.id = gm.group_id
    WHERE g.id = ?
    GROUP BY g.id
");
$stmt->execute([$studentId, $groupId]);
$group = $stmt->fetch();

if (!$group || !$group['is_admin']) {
    header('Location: student_group_details.php?id=' . $groupId);
    exit();
}

// Fetch classes for sidebar
$stmt = $pdo->prepare("
    SELECT c.* 
    FROM classes c
    JOIN class_students cs ON c.id = cs.class_id
    WHERE cs.student_id = ?
");
$stmt->execute([$studentId]);
$classes = $stmt->fetchAll();

$success = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $groupName = trim($_POST['group-name']);
    $description = trim($_POST['group-description'] ?? '');
    $privacy = $_POST['group-privacy'] ?? 'public';
    $passcode = ($privacy === 'private') ? trim($_POST['group-passcode'] ?? '') : null;
    
    // Validate inputs
    if (empty($groupName)) {
        $error = "Group name is required";
    } elseif (strlen($groupName) > 100) {
        $error = "Group name must be less than 100 characters";
    } elseif ($privacy === 'private' && (empty($passcode) || strlen($passcode) < 4)) {
        $error = "Passcode must be at least 4 characters for private groups";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Handle image upload if a new one is provided
            $imageUrl = $group['image_url'];
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
                        // Delete old image if exists
                        if ($group['image_url'] && file_exists($group['image_url'])) {
                            unlink($group['image_url']);
                        }
                        $imageUrl = $targetPath;
                    } else {
                        throw new Exception("Failed to upload image");
                    }
                } else {
                    throw new Exception("Invalid file type or size (max 2MB JPG/PNG only)");
                }
            }
            
            // Update group details
            $stmt = $pdo->prepare("
                UPDATE `groups`
                SET name = ?, 
                    description = ?, 
                    privacy = ?, 
                    passcode = ?,
                    image_url = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $groupName,
                $description,
                $privacy,
                $passcode ? password_hash($passcode, PASSWORD_DEFAULT) : null,
                $imageUrl,
                $groupId
            ]);
            
            $pdo->commit();
            $success = "Group details updated successfully!";
            
            // Refresh group data
            $stmt = $pdo->prepare("
                SELECT g.*, 
                       COUNT(gm.user_id) as member_count,
                       MAX(CASE WHEN gm.user_id = ? AND gm.is_admin = 1 THEN 1 ELSE 0 END) as is_admin
                FROM `groups` g
                LEFT JOIN group_members gm ON g.id = gm.group_id
                WHERE g.id = ?
                GROUP BY g.id
            ");
            $stmt->execute([$studentId, $groupId]);
            $group = $stmt->fetch();
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = "Failed to update group: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Group - LearnMate</title>
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

        /* App Container */
        .app-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - Desktop */
        .sidebar {
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
            background-color: #F9F5FF;
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
            background-color: #F9F5FF;
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
            padding: var(--space-xl);
            position: relative;
            background-color: var(--bg-light);
            width: calc(100% - 280px);
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Form Card */
        .form-card {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: var(--space-lg);
            margin-bottom: var(--space-lg);
            box-shadow: var(--shadow-sm);
        }

        .form-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: var(--space-lg);
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

        .image-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: var(--space-xl);
            border: 2px dashed var(--border-light);
            border-radius: var(--radius-md);
            cursor: pointer;
            transition: var(--transition);
        }

        .image-upload:hover {
            border-color: var(--primary);
            background-color: var(--primary-extra-light);
        }

        .image-upload i {
            font-size: 32px;
            color: var(--primary);
            margin-bottom: var(--space-sm);
        }

        .image-upload-text {
            text-align: center;
            color: var(--text-medium);
        }

        .image-upload-text span {
            display: block;
            font-size: 12px;
            color: var(--text-light);
            margin-top: var(--space-xs);
        }

        .image-preview {
            width: 100%;
            max-width: 300px;
            margin: var(--space-md) auto;
            border-radius: var(--radius-md);
            overflow: hidden;
        }

        .image-preview img {
            width: 100%;
            height: auto;
            display: block;
        }

        .radio-group {
            display: flex;
            gap: var(--space-md);
            margin-top: var(--space-xs);
        }

        .radio-option {
            display: flex;
            align-items: center;
            gap: var(--space-xs);
            cursor: pointer;
        }

        .radio-option input[type="radio"] {
            width: 16px;
            height: 16px;
            margin: 0;
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
            display: flex;
            align-items: center;
            justify-content: center;
            gap: var(--space-sm);
        }

        .btn-submit:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

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

        @media (min-width: 768px) {
            .main-content {
                padding: var(--space-xl);
            }
            
            .sidebar {
                display: flex;
                flex-direction: column;
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

        <main class="main-content">
            <!-- Edit Group Form -->
            <div class="form-card">
                <?php if ($success): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php elseif ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <h2 class="form-title">Edit Group Details</h2>

                <form method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="group-name" class="form-label required">Group Name</label>
                        <input type="text" id="group-name" name="group-name" class="form-control" 
                               value="<?php echo htmlspecialchars($group['name']); ?>" required maxlength="100">
                    </div>

                    <div class="form-group">
                        <label for="group-description" class="form-label">Description</label>
                        <textarea id="group-description" name="group-description" class="form-control form-textarea" 
                                  maxlength="500"><?php echo htmlspecialchars($group['description']); ?></textarea>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Group Image</label>
                        <?php if ($group['image_url']): ?>
                            <div class="image-preview">
                                <img src="<?php echo htmlspecialchars($group['image_url']); ?>" 
                                     alt="Current group image">
                            </div>
                        <?php endif; ?>
                        <label for="group-image" class="image-upload" id="imageUploadLabel">
                            <i class="fas fa-cloud-upload-alt" id="uploadIcon"></i>
                            <div class="image-upload-text" id="uploadText">
                                Click to change image <span>(Max 2MB, JPG/PNG only)</span>
                            </div>
                            <input type="file" id="group-image" name="group-image" 
                                   accept="image/jpeg,image/png" style="display: none;">
                        </label>
                    </div>

                    <div class="form-group">
                        <label class="form-label required">Privacy</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="group-privacy" value="public" 
                                       <?php echo $group['privacy'] === 'public' ? 'checked' : ''; ?> id="publicPrivacy">
                                <span>Public (Anyone can join)</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="group-privacy" value="private" 
                                       <?php echo $group['privacy'] === 'private' ? 'checked' : ''; ?> id="privatePrivacy">
                                <span>Private (Passcode required)</span>
                            </label>
                        </div>
                    </div>

                    <div class="form-group" id="passcodeField" style="<?php echo $group['privacy'] === 'private' ? '' : 'display: none;'; ?>">
                        <label for="group-passcode" class="form-label required">Passcode</label>
                        <input type="password" id="group-passcode" name="group-passcode" class="form-control" 
                               placeholder="Set a new passcode (min 4 characters)" minlength="4">
                        <small style="font-size: 12px; color: var(--text-light);">
                            Leave blank to keep current passcode
                        </small>
                    </div>

                    <button type="submit" class="btn-submit">
                        <i class="fas fa-save"></i>
                        Save Changes
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
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
                    let imgPreview = document.querySelector('.image-preview');
                    if (!imgPreview) {
                        imgPreview = document.createElement('div');
                        imgPreview.className = 'image-preview';
                        document.querySelector('.image-upload').insertAdjacentElement('beforebegin', imgPreview);
                    }
                    imgPreview.innerHTML = `<img src="${event.target.result}" alt="Preview">`;
                }
                reader.readAsDataURL(file);
            });
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

        // Dropdown toggle
        function toggleDropdown(element) {
            element.classList.toggle('active');
            const menu = element.parentElement.querySelector('.dropdown-menu');
            menu.classList.toggle('show');
        }
    </script>
</body>
</html>