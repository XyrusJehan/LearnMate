<?php
session_start();
require 'db.php';
require 'includes/theme.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$userRole = $_SESSION['role'];
$theme = getCurrentTheme();

// Handle AJAX restore folder
if (isset($_POST['restore_folder_id'])) {
    $folderId = (int)$_POST['restore_folder_id'];
    $response = ["success" => false, "message" => "Unknown error."];
    $stmtRestore = $pdo->prepare("UPDATE folders SET is_archived = 0 WHERE id = ? AND user_id = ?");
    if ($stmtRestore->execute([$folderId, $userId])) {
        $response = ["success" => true, "message" => "Folder restored."];
    } else {
        $response = ["success" => false, "message" => "Failed to restore folder."];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Handle AJAX restore group
if (isset($_POST['restore_group_id'])) {
    $groupId = (int)$_POST['restore_group_id'];
    $response = ["success" => false, "message" => "Unknown error."];
    
    // Verify that the user is an admin of the group
    $stmt = $pdo->prepare("
        SELECT g.* 
        FROM groups g
        JOIN group_members gm ON g.id = gm.group_id
        WHERE g.id = ? AND gm.user_id = ? AND gm.is_admin = 1
    ");
    $stmt->execute([$groupId, $userId]);
    $group = $stmt->fetch();
    
    if ($group) {
        $stmtRestore = $pdo->prepare("UPDATE groups SET is_archived = 0 WHERE id = ?");
        if ($stmtRestore->execute([$groupId])) {
            $response = ["success" => true, "message" => "Group restored."];
        } else {
            $response = ["success" => false, "message" => "Failed to restore group."];
        }
    } else {
        $response = ["success" => false, "message" => "Group not found or you don't have permission to restore it."];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

// Fetch archived folders
$stmt = $pdo->prepare("SELECT id, name FROM folders WHERE user_id = ? AND is_archived = 1 ORDER BY name");
$stmt->execute([$userId]);
$archivedFolders = $stmt->fetchAll();

// Fetch archived groups where user is admin
$stmt = $pdo->prepare("
    SELECT g.*, 
           (SELECT COUNT(*) FROM group_members WHERE group_id = g.id) as member_count
    FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE g.is_archived = 1 AND gm.user_id = ? AND gm.is_admin = 1
    ORDER BY g.created_at DESC
");
$stmt->execute([$userId]);
$archivedGroups = $stmt->fetchAll();

// Get current view (folders or groups)
$view = $_GET['view'] ?? 'folders';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Archived - LearnMate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/theme.css">
  <style>
    body { 
      background: var(--bg-light); 
      color: var(--text-dark); 
      font-family: 'Inter', sans-serif; 
    }
    .container { 
      max-width: 800px; 
      margin: 40px auto; 
      padding: 0 16px; 
    }
    .settings-card { 
      background: var(--bg-white); 
      border-radius: 12px; 
      box-shadow: var(--shadow-sm); 
      padding: 28px 24px; 
      margin-bottom: 32px; 
    }
    .settings-title { 
      font-size: 1.4rem; 
      font-weight: 600; 
      margin-bottom: 18px; 
      display: flex; 
      align-items: center; 
      gap: 10px; 
      color: var(--primary); 
    }
    .back-btn { 
      display: inline-block; 
      margin-bottom: 18px; 
      color: var(--primary); 
      text-decoration: none; 
      font-weight: 500; 
    }
    .back-btn i { 
      margin-right: 6px; 
    }
    
    /* Tab Navigation */
    .tab-nav {
      display: flex;
      background: var(--bg-light);
      border-radius: 8px;
      padding: 4px;
      margin-bottom: 24px;
    }
    .tab-btn {
      flex: 1;
      padding: 12px 16px;
      border: none;
      background: none;
      color: var(--text-medium);
      font-weight: 500;
      cursor: pointer;
      border-radius: 6px;
      transition: all 0.2s ease;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
    }
    .tab-btn.active {
      background: var(--bg-white);
      color: var(--primary);
      box-shadow: var(--shadow-xs);
    }
    .tab-btn:hover:not(.active) {
      color: var(--text-dark);
    }
    
    /* Archived Items */
    .archived-item-card { 
      background: var(--bg-light); 
      border-radius: 8px; 
      margin-bottom: 18px; 
      padding: 18px 16px; 
      box-shadow: var(--shadow-xs); 
      position: relative; 
      display: flex; 
      align-items: center; 
      justify-content: space-between; 
    }
    .archived-item-info { 
      display: flex; 
      align-items: center; 
      gap: 10px; 
      flex: 1; 
    }
    .item-details { 
      display: flex; 
      flex-direction: column; 
      gap: 4px; 
    }
    .item-name { 
      font-weight: 600; 
      color: var(--text-dark); 
      font-size: 1.1rem; 
    }
    .item-meta { 
      font-size: 0.9rem; 
      color: var(--text-light); 
    }
    
    /* Dropdown */
    .dropdown { 
      position: relative; 
    }
    .dropdown-btn { 
      background: none; 
      border: none; 
      color: var(--text-light); 
      cursor: pointer; 
      padding: 5px; 
      font-size: 1.2rem; 
    }
    .dropdown-content { 
      display: none; 
      position: absolute; 
      right: 0; 
      background: var(--bg-white); 
      min-width: 140px; 
      box-shadow: var(--shadow-md); 
      z-index: 1000; 
      border-radius: 8px; 
      overflow: hidden; 
    }
    .dropdown-content.show { 
      display: block; 
      animation: fadeIn 0.2s; 
    }
    .dropdown-content a { 
      color: var(--text-dark); 
      padding: 10px 15px; 
      text-decoration: none; 
      display: block; 
      font-size: 0.95rem; 
      transition: var(--transition); 
    }
    .dropdown-content a:hover { 
      background: var(--bg-light); 
      color: var(--primary); 
    }
    
    /* Group specific styles */
    .group-image { 
      width: 40px; 
      height: 40px; 
      border-radius: 8px; 
      object-fit: cover; 
    }
    .group-image-placeholder { 
      width: 40px; 
      height: 40px; 
      border-radius: 8px; 
      background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%); 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      color: white; 
      font-size: 16px; 
    }
    
    /* Success message */
    .success { 
      background: rgba(50, 213, 131, 0.1); 
      color: var(--success); 
      border-left: 4px solid var(--success); 
      padding: 8px 12px; 
      border-radius: 8px; 
      margin-bottom: 16px; 
    }
    
    /* Empty state */
    .empty-state {
      text-align: center;
      padding: 40px 20px;
      color: var(--text-light);
    }
    .empty-state i {
      font-size: 3rem;
      margin-bottom: 16px;
      opacity: 0.5;
    }
    
    @keyframes fadeIn { 
      from { 
        opacity: 0; 
        transform: translateY(-5px); 
      } 
      to { 
        opacity: 1; 
        transform: translateY(0); 
      } 
    }
  </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
  <div class="container">
    <a href="settings.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    
    <div class="settings-card">
      <h2 class="settings-title"><i class="fas fa-archive"></i> Archived Items</h2>
      
      <!-- Tab Navigation -->
      <div class="tab-nav">
        <button class="tab-btn <?php echo $view === 'folders' ? 'active' : ''; ?>" onclick="switchView('folders')">
          <i class="fas fa-folder"></i>
          Folders (<?php echo count($archivedFolders); ?>)
        </button>
        <button class="tab-btn <?php echo $view === 'groups' ? 'active' : ''; ?>" onclick="switchView('groups')">
          <i class="fas fa-users"></i>
          Groups (<?php echo count($archivedGroups); ?>)
        </button>
      </div>
      
      <!-- Folders View -->
      <div id="folders-view" class="view-content" style="display: <?php echo $view === 'folders' ? 'block' : 'none'; ?>;">
        <?php if (empty($archivedFolders)): ?>
          <div class="empty-state">
            <i class="fas fa-folder-open"></i>
            <div>No archived folders</div>
          </div>
        <?php else: ?>
          <div class="archived-items-list">
            <?php foreach ($archivedFolders as $folder): ?>
              <div class="archived-item-card" id="archived-folder-<?php echo $folder['id']; ?>">
                <div class="archived-item-info">
                  <i class="fas fa-folder" style="color: var(--primary);"></i>
                  <div class="item-details">
                    <div class="item-name"><?php echo htmlspecialchars($folder['name']); ?></div>
                    <div class="item-meta">Archived folder</div>
                  </div>
                </div>
                <div class="dropdown folder-dropdown">
                  <button class="dropdown-btn folder-dropdown-btn" title="More actions" tabindex="0">
                    <i class="fas fa-ellipsis-v"></i>
                  </button>
                  <div class="dropdown-content folder-dropdown-content">
                    <a href="#" class="restore-folder-btn" data-id="<?php echo $folder['id']; ?>">
                      <i class="fas fa-undo"></i> Restore
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      
      <!-- Groups View -->
      <div id="groups-view" class="view-content" style="display: <?php echo $view === 'groups' ? 'block' : 'none'; ?>;">
        <?php if (empty($archivedGroups)): ?>
          <div class="empty-state">
            <i class="fas fa-users"></i>
            <div>No archived groups</div>
          </div>
        <?php else: ?>
          <div class="archived-items-list">
            <?php foreach ($archivedGroups as $group): ?>
              <div class="archived-item-card" id="archived-group-<?php echo $group['id']; ?>">
                <div class="archived-item-info">
                  <?php if ($group['image_url']): ?>
                    <img src="<?php echo htmlspecialchars($group['image_url']); ?>" alt="<?php echo htmlspecialchars($group['name']); ?>" class="group-image">
                  <?php else: ?>
                    <div class="group-image-placeholder">
                      <i class="fas fa-users"></i>
                    </div>
                  <?php endif; ?>
                  <div class="item-details">
                    <div class="item-name"><?php echo htmlspecialchars($group['name']); ?></div>
                    <div class="item-meta">
                      <i class="fas fa-users"></i> <?php echo $group['member_count']; ?> member<?php echo $group['member_count'] != 1 ? 's' : ''; ?> • 
                      <i class="fas fa-<?php echo $group['privacy'] === 'public' ? 'globe' : 'lock'; ?>"></i> <?php echo ucfirst($group['privacy']); ?> • 
                      Created <?php echo date('M j, Y', strtotime($group['created_at'])); ?>
                    </div>
                  </div>
                </div>
                <div class="dropdown group-dropdown">
                  <button class="dropdown-btn group-dropdown-btn" title="More actions" tabindex="0">
                    <i class="fas fa-ellipsis-v"></i>
                  </button>
                  <div class="dropdown-content group-dropdown-content">
                    <a href="#" class="restore-group-btn" data-id="<?php echo $group['id']; ?>">
                      <i class="fas fa-undo"></i> Restore
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    // Switch between views
    function switchView(view) {
      // Update URL without page reload
      const url = new URL(window.location);
      url.searchParams.set('view', view);
      window.history.pushState({}, '', url);
      
      // Hide all views
      document.querySelectorAll('.view-content').forEach(content => {
        content.style.display = 'none';
      });
      
      // Show selected view
      document.getElementById(view + '-view').style.display = 'block';
      
      // Update tab buttons
      document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
      });
      event.target.classList.add('active');
    }
    
    // Dropdown menu functionality
    document.querySelectorAll('.dropdown-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdown = this.nextElementSibling;
        const isShowing = dropdown.classList.contains('show');
        
        // Close all other dropdowns
        document.querySelectorAll('.dropdown-content').forEach(d => d.classList.remove('show'));
        
        if (!isShowing) dropdown.classList.add('show');
      });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function() {
      document.querySelectorAll('.dropdown-content').forEach(dropdown => dropdown.classList.remove('show'));
    });
    
    // Prevent click inside dropdown from closing it
    document.querySelectorAll('.dropdown-content').forEach(menu => {
      menu.addEventListener('click', function(e) { 
        e.stopPropagation(); 
      });
    });
    
    // Folder restore AJAX
    document.querySelectorAll('.restore-folder-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (!confirm('Restore this folder?')) return;
        
        const folderId = this.getAttribute('data-id');
        const folderCard = document.getElementById('archived-folder-' + folderId);
        const originalHTML = folderCard.innerHTML;
        
        folderCard.innerHTML = '<span>Restoring... <i class="fas fa-spinner fa-spin"></i></span>';
        
        fetch('archived.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ restore_folder_id: folderId })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            folderCard.remove();
            showSuccessMessage('Folder restored!');
            updateFolderCount();
          } else {
            folderCard.innerHTML = originalHTML;
            alert('Error: ' + data.message);
          }
        })
        .catch(err => {
          folderCard.innerHTML = originalHTML;
          alert('Error restoring folder.');
        });
      });
    });
    
    // Group restore AJAX
    document.querySelectorAll('.restore-group-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        if (!confirm('Restore this group?')) return;
        
        const groupId = this.getAttribute('data-id');
        const groupCard = document.getElementById('archived-group-' + groupId);
        const originalHTML = groupCard.innerHTML;
        
        groupCard.innerHTML = '<span>Restoring... <i class="fas fa-spinner fa-spin"></i></span>';
        
        fetch('archived.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ restore_group_id: groupId })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            groupCard.remove();
            showSuccessMessage('Group restored!');
            updateGroupCount();
          } else {
            groupCard.innerHTML = originalHTML;
            alert('Error: ' + data.message);
          }
        })
        .catch(err => {
          groupCard.innerHTML = originalHTML;
          alert('Error restoring group.');
        });
      });
    });
    
    // Show success message
    function showSuccessMessage(message) {
      const msg = document.createElement('div');
      msg.className = 'success';
      msg.innerHTML = '<i class="fas fa-check-circle"></i> ' + message;
      
      const container = document.querySelector('.archived-items-list');
      if (container) {
        container.prepend(msg);
        setTimeout(() => msg.remove(), 2500);
      }
    }
    
    // Update counts
    function updateFolderCount() {
      const folderTab = document.querySelector('.tab-btn:first-child');
      const currentCount = parseInt(folderTab.textContent.match(/\((\d+)\)/)[1]);
      folderTab.innerHTML = `<i class="fas fa-folder"></i> Folders (${currentCount - 1})`;
    }
    
    function updateGroupCount() {
      const groupTab = document.querySelector('.tab-btn:last-child');
      const currentCount = parseInt(groupTab.textContent.match(/\((\d+)\)/)[1]);
      groupTab.innerHTML = `<i class="fas fa-users"></i> Groups (${currentCount - 1})`;
    }
  </script>
</body>
</html> 