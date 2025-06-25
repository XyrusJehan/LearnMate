<?php
session_start();
require 'db.php';
require 'includes/theme.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$userId = $_SESSION['user_id'];
$theme = getCurrentTheme();

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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Archived Groups - LearnMate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/theme.css">
  <style>
    body { background: var(--bg-light); color: var(--text-dark); font-family: 'Inter', sans-serif; }
    .container { max-width: 800px; margin: 40px auto; padding: 0 16px; }
    .settings-card { background: var(--bg-white); border-radius: 12px; box-shadow: var(--shadow-sm); padding: 28px 24px; margin-bottom: 32px; }
    .settings-title { font-size: 1.4rem; font-weight: 600; margin-bottom: 18px; display: flex; align-items: center; gap: 10px; color: var(--primary); }
    .archived-group-card { background: var(--bg-light); border-radius: 8px; margin-bottom: 18px; padding: 18px 16px; box-shadow: var(--shadow-xs); position: relative; display: flex; align-items: center; justify-content: space-between; }
    .archived-group-info { display: flex; align-items: center; gap: 10px; flex: 1; }
    .group-details { display: flex; flex-direction: column; gap: 4px; }
    .group-name { font-weight: 600; color: var(--text-dark); font-size: 1.1rem; }
    .group-meta { font-size: 0.9rem; color: var(--text-light); }
    .dropdown { position: relative; }
    .dropdown-btn { background: none; border: none; color: var(--text-light); cursor: pointer; padding: 5px; font-size: 1.2rem; }
    .dropdown-content { display: none; position: absolute; right: 0; background: var(--bg-white); min-width: 140px; box-shadow: var(--shadow-md); z-index: 1000; border-radius: 8px; overflow: hidden; }
    .dropdown-content.show { display: block; animation: fadeIn 0.2s; }
    .dropdown-content a { color: var(--text-dark); padding: 10px 15px; text-decoration: none; display: block; font-size: 0.95rem; transition: var(--transition); }
    .dropdown-content a:hover { background: var(--bg-light); color: var(--primary); }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(-5px); } to { opacity: 1; transform: translateY(0); } }
    .success { background: rgba(50, 213, 131, 0.1); color: var(--success); border-left: 4px solid var(--success); padding: 8px 12px; border-radius: 8px; margin-bottom: 16px; }
    .back-btn { display: inline-block; margin-bottom: 18px; color: var(--primary); text-decoration: none; font-weight: 500; }
    .back-btn i { margin-right: 6px; }
    .group-image { width: 40px; height: 40px; border-radius: 8px; object-fit: cover; }
    .group-image-placeholder { width: 40px; height: 40px; border-radius: 8px; background: linear-gradient(135deg, var(--primary-light) 0%, var(--primary) 100%); display: flex; align-items: center; justify-content: center; color: white; font-size: 16px; }
  </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
  <div class="container">
    <a href="settings.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Settings</a>
    <div class="settings-card">
      <h2 class="settings-title"><i class="fas fa-archive"></i> Archived Groups</h2>
      <?php if (empty($archivedGroups)): ?>
        <div style="color: var(--text-light); padding: 16px;">No archived groups.</div>
      <?php else: ?>
        <div class="archived-groups-list">
          <?php foreach ($archivedGroups as $group): ?>
            <div class="archived-group-card" id="archived-group-<?php echo $group['id']; ?>">
              <div class="archived-group-info">
                <?php if ($group['image_url']): ?>
                  <img src="<?php echo htmlspecialchars($group['image_url']); ?>" alt="<?php echo htmlspecialchars($group['name']); ?>" class="group-image">
                <?php else: ?>
                  <div class="group-image-placeholder">
                    <i class="fas fa-users"></i>
                  </div>
                <?php endif; ?>
                <div class="group-details">
                  <div class="group-name"><?php echo htmlspecialchars($group['name']); ?></div>
                  <div class="group-meta">
                    <i class="fas fa-users"></i> <?php echo $group['member_count']; ?> member<?php echo $group['member_count'] != 1 ? 's' : ''; ?> • 
                    <i class="fas fa-<?php echo $group['privacy'] === 'public' ? 'globe' : 'lock'; ?>"></i> <?php echo ucfirst($group['privacy']); ?> • 
                    Archived <?php echo date('M j, Y', strtotime($group['created_at'])); ?>
                  </div>
                </div>
              </div>
              <div class="dropdown group-dropdown" style="margin-left: 4px;">
                <button class="dropdown-btn group-dropdown-btn" title="More actions" tabindex="0"><i class="fas fa-ellipsis-v"></i></button>
                <div class="dropdown-content group-dropdown-content">
                  <a href="#" class="restore-group-btn" data-id="<?php echo $group['id']; ?>"><i class="fas fa-undo"></i> Restore</a>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <script>
    // Dropdown menu functionality
    document.querySelectorAll('.group-dropdown-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.stopPropagation();
        const dropdown = this.nextElementSibling;
        const isShowing = dropdown.classList.contains('show');
        document.querySelectorAll('.group-dropdown-content').forEach(d => d.classList.remove('show'));
        if (!isShowing) dropdown.classList.add('show');
      });
    });
    document.addEventListener('click', function() {
      document.querySelectorAll('.group-dropdown-content').forEach(dropdown => dropdown.classList.remove('show'));
    });
    // Prevent click inside dropdown from closing it
    document.querySelectorAll('.group-dropdown-content').forEach(menu => {
      menu.addEventListener('click', function(e) { e.stopPropagation(); });
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
        fetch('archived_groups.php', {
          method: 'POST',
          headers: { 'X-Requested-With': 'XMLHttpRequest' },
          body: new URLSearchParams({ restore_group_id: groupId })
        })
        .then(res => res.json())
        .then(data => {
          if (data.success) {
            groupCard.remove();
            const msg = document.createElement('div');
            msg.className = 'success';
            msg.innerHTML = '<i class="fas fa-check-circle"></i> Group restored!';
            document.querySelector('.archived-groups-list').prepend(msg);
            setTimeout(() => msg.remove(), 2500);
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
  </script>
</body>
</html> 