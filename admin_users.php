<?php
// Handle AJAX get user for edit (must be before session/auth and HTML)
if (isset($_GET['get_user']) && is_numeric($_GET['get_user'])) {
  require 'db.php';
  $stmt = $pdo->prepare('SELECT id, first_name, last_name, email, role FROM users WHERE id = ?');
  $stmt->execute([$_GET['get_user']]);
  $user = $stmt->fetch(PDO::FETCH_ASSOC);
  header('Content-Type: application/json');
  echo json_encode($user);
  exit;
}

session_start();
require 'db.php';
require 'includes/theme.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}
$theme = getCurrentTheme();
// Handle user deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare('DELETE FROM users WHERE id = ?');
    $stmt->execute([$_GET['delete']]);
    header('Location: admin_users.php');
    exit();
}
// Fetch all users
$stmt = $pdo->query('SELECT id, first_name, last_name, email, role, created_at FROM users ORDER BY created_at DESC');
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Management - LearnMate Admin</title>
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

    .admin-tools-card {
      background-color: #F0F9FF;
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      margin-top: var(--space-xl);
    }

    .card-title {
      font-size: 14px;
      font-weight: 600;
      margin-bottom: var(--space-md);
      color: var(--primary-dark);
    }

    .action-btn {
      width: 100%;
      padding: var(--space-sm);
      border-radius: var(--radius-md);
      border: none;
      background-color: var(--primary);
      color: white;
      font-weight: 500;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: var(--space-sm);
      cursor: pointer;
      transition: var(--transition);
      margin-bottom: var(--space-sm);
    }

    .action-btn:hover {
      background-color: var(--primary-dark);
      transform: translateY(-1px);
    }

    .action-btn.secondary {
      background-color: white;
      color: var(--primary);
      border: 1px solid var(--border-light);
    }

    .action-btn.secondary:hover {
      background-color: #F9F5FF;
      color: var(--primary-dark);
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
      margin-bottom: var(--space-lg);
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

    /* Content Styles */
    .section-title-lg {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: var(--space-md);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .card {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-sm);
      padding: var(--space-md);
      margin-bottom: var(--space-lg);
    }

    table {
      width: 100%;
      border-collapse: collapse;
      background: var(--bg-white);
      border-radius: var(--radius-lg);
      overflow: hidden;
      box-shadow: var(--shadow-xs);
    }

    th, td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border-light);
    }

    th {
      background: var(--bg-light);
      color: var(--text-medium);
      font-weight: 600;
      font-size: 14px;
    }

    td {
      font-size: 15px;
    }

    tr:last-child td {
      border-bottom: none;
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

    .btn {
      padding: 8px 16px;
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

    .btn-danger {
      background-color: var(--danger);
      color: white;
    }

    .btn-danger:hover {
      background-color: #d32f2f;
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

    /* Floating Action Button integrated with nav */
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

    /* Responsive Design */
    @media (min-width: 640px) {
      /* Tablet styles */
      .main-content {
        padding: var(--space-lg);
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

    .modal-card {
      display: none;
      position: fixed;
      top: 0; left: 0; width: 100vw; height: 100vh;
      background: rgba(0,0,0,0.3);
      z-index: 1000;
      align-items: center;
      justify-content: center;
      transition: background 0.2s;
    }
    .modal-card[style*="display: flex"] {
      display: flex !important;
    }
    .modal-content {
      background: var(--bg-white);
      color: var(--text-dark);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-lg);
      padding: 32px 28px 24px 28px;
      min-width: 320px;
      max-width: 90vw;
      position: relative;
      width: 100%;
      max-width: 400px;
      display: flex;
      flex-direction: column;
      gap: 0.5rem;
      transition: background 0.2s, color 0.2s;
    }
    .modal-title {
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 18px;
      color: var(--primary-dark);
      text-align: left;
    }
    .modal-group {
      margin-bottom: 14px;
    }
    .modal-input {
      width: 100%;
      padding: 10px 12px;
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
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
    .modal-actions {
      display: flex;
      gap: 10px;
      margin-top: 10px;
    }
    .modal-close {
      position: absolute;
      top: 12px;
      right: 16px;
      background: none;
      border: none;
      font-size: 1.6rem;
      color: var(--text-light);
      cursor: pointer;
      transition: color 0.2s;
      z-index: 2;
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
      background: var(--bg-white);
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
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content Area -->
    <main class="main-content">
      <!-- Mobile Header -->
      <header class="header">
        <h1 class="header-title">User Management</h1>
        <div class="header-actions">
          <button class="header-btn">
            <i class="fas fa-search"></i>
          </button>
          <button class="header-btn">
            <i class="fas fa-bell"></i>
          </button>
        </div>
      </header>

      <div class="card">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-md);">
          <h2 class="section-title-lg">
            <i class="fas fa-users"></i>
            <span>All Users</span>
          </h2>
          <button class="btn btn-primary" id="openCreateUserModal">
            <i class="fas fa-user-plus"></i> Create User
          </button>
        </div>
        
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($users as $user): ?>
              <tr>
                <td><?php echo $user['id']; ?></td>
                <td><?php echo htmlspecialchars($user['first_name'].' '.$user['last_name']); ?></td>
                <td><?php echo htmlspecialchars($user['email']); ?></td>
                <td>
                  <span class="badge <?php echo $user['role']; ?>"><?php echo ucfirst($user['role']); ?></span>
                </td>
                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                <td>
                  <button type="button" class="btn btn-primary edit-user-btn" data-id="<?php echo $user['id']; ?>" style="padding: 6px 12px; font-size: 14px;">
                    <i class="fas fa-edit"></i> Edit
                  </button>
                  <a href="admin_users.php?delete=<?php echo $user['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 14px;" onclick="return confirm('Delete this user?');">
                    <i class="fas fa-trash"></i> Delete
                  </a>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </main>
  </div>

  <!-- Bottom Navigation with Fixed FAB - Mobile Only -->
  <div class="bottom-nav-container">
    <nav class="bottom-nav">
      <a href="admin_dashboard.php" class="nav-item-mobile">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="admin_users.php" class="nav-item-mobile active">
        <i class="fas fa-users"></i>
        <span>Users</span>
      </a>
      <a href="logout.php" class="nav-item-mobile">
        <i class="fas fa-sign-out-alt"></i>
        <span>Logout</span>
      </a>
      <!-- FAB Container - stays fixed with nav -->
      <div class="fab-container">
        <button class="fab">
          <i class="fas fa-plus"></i>
        </button>
      </div>
      
      <!-- Spacer for FAB area -->
      <div style="width: 25%;"></div>
      
      <a href="admin_classes.php" class="nav-item-mobile">
        <i class="fas fa-chalkboard"></i>
        <span>Classes</span>
      </a>
      <a href="admin_logs.php" class="nav-item-mobile">
        <i class="fas fa-clipboard-list"></i>
        <span>Logs</span>
      </a>
    </nav>
  </div>

  <!-- Create User Modal -->
  <div id="createUserModal" class="modal-card" style="display:none;">
    <div class="modal-content">
      <button type="button" class="modal-close" id="closeCreateUserModal" aria-label="Close">&times;</button>
      <h2 class="modal-title">Create New User</h2>
      <form method="POST" id="createUserForm">
        <div class="modal-group"><input type="text" name="new_first_name" placeholder="First Name" required class="modal-input"></div>
        <div class="modal-group"><input type="text" name="new_last_name" placeholder="Last Name" required class="modal-input"></div>
        <div class="modal-group"><input type="email" name="new_email" placeholder="Email" required class="modal-input"></div>
        <div class="modal-group"><input type="password" name="new_password" placeholder="Password" required class="modal-input"></div>
        <div class="modal-group">
          <select name="new_role" required class="modal-input">
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn btn-primary" style="flex:1;">Create</button>
        </div>
      </form>
      <div id="createUserMsg" class="modal-msg"></div>
    </div>
  </div>

  <!-- Edit User Modal -->
  <div id="editUserModal" class="modal-card" style="display:none;">
    <div class="modal-content">
      <button type="button" class="modal-close" id="closeEditUserModal" aria-label="Close">&times;</button>
      <h2 class="modal-title">Edit User</h2>
      <form method="POST" id="editUserForm">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="modal-group"><input type="text" name="edit_first_name" id="edit_first_name" placeholder="First Name" required class="modal-input"></div>
        <div class="modal-group"><input type="text" name="edit_last_name" id="edit_last_name" placeholder="Last Name" required class="modal-input"></div>
        <div class="modal-group"><input type="email" name="edit_email" id="edit_email" placeholder="Email" required class="modal-input"></div>
        <div class="modal-group">
          <select name="edit_role" id="edit_role" required class="modal-input">
            <option value="student">Student</option>
            <option value="teacher">Teacher</option>
            <option value="admin">Admin</option>
          </select>
        </div>
        <div class="modal-group">
          <input type="password" name="edit_password" id="edit_password" placeholder="New Password (leave blank to keep current)" class="modal-input">
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn btn-primary" style="flex:1;">Save Changes</button>
        </div>
      </form>
      <div id="editUserMsg" class="modal-msg"></div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Open Create User Modal from either button
      document.getElementById('openCreateUserModal').onclick = openCreateUserModal;
      var sidebarBtn = document.getElementById('openCreateUserModalSidebar');
      if (sidebarBtn) sidebarBtn.onclick = openCreateUserModal;
      function openCreateUserModal() {
        document.getElementById('createUserModal').style.display = 'flex';
        document.getElementById('createUserForm').reset();
        document.getElementById('createUserMsg').innerHTML = '';
      }
      document.getElementById('closeCreateUserModal').onclick = function() {
        document.getElementById('createUserModal').style.display = 'none';
        document.getElementById('createUserMsg').innerHTML = '';
      };
      document.getElementById('createUserForm').onsubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        const res = await fetch('admin_users.php', { method: 'POST', body: data });
        const text = await res.text();
        const msg = text.match(/<div id="createUserMsg">([\s\S]*?)<\/div>/);
        document.getElementById('createUserMsg').innerHTML = msg ? msg[1] : 'User created!';
        if (msg && msg[1].includes('success')) setTimeout(()=>location.reload(), 1200);
      };
      // Edit User Modal logic (event delegation)
      document.body.addEventListener('click', async function(e) {
        if (e.target.closest('.edit-user-btn')) {
          const btn = e.target.closest('.edit-user-btn');
          const id = btn.getAttribute('data-id');
          const res = await fetch('admin_users.php?get_user='+id);
          const user = await res.json();
          document.getElementById('edit_id').value = user.id;
          document.getElementById('edit_first_name').value = user.first_name;
          document.getElementById('edit_last_name').value = user.last_name;
          document.getElementById('edit_email').value = user.email;
          document.getElementById('edit_role').value = user.role;
          document.getElementById('edit_password').value = '';
          document.getElementById('editUserMsg').innerHTML = '';
          document.getElementById('editUserModal').style.display = 'flex';
        }
      });
      document.getElementById('closeEditUserModal').onclick = function() {
        document.getElementById('editUserModal').style.display = 'none';
        document.getElementById('editUserMsg').innerHTML = '';
      };
      document.getElementById('editUserForm').onsubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        data.append('edit_user', '1');
        const res = await fetch('admin_users.php', { method: 'POST', body: data });
        const text = await res.text();
        const msg = text.match(/<div id="editUserMsg">([\s\S]*?)<\/div>/);
        document.getElementById('editUserMsg').innerHTML = msg ? msg[1] : 'User updated!';
        if (msg && msg[1].includes('success')) setTimeout(()=>location.reload(), 1200);
      };
    });
  </script>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_email'])) {
  $first = trim($_POST['new_first_name'] ?? '');
  $last = trim($_POST['new_last_name'] ?? '');
  $email = trim($_POST['new_email'] ?? '');
  $pass = $_POST['new_password'] ?? '';
  $role = $_POST['new_role'] ?? 'student';
  $msg = '';
  if (!$first || !$last || !$email || !$pass || !in_array($role, ['student','teacher','admin'])) {
    $msg = '<span style="color:red">All fields required.</span>';
  } else {
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
      $msg = '<span style="color:red">Email already exists.</span>';
    } else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);
      $stmt = $pdo->prepare('INSERT INTO users (first_name, last_name, email, password, role, created_at) VALUES (?, ?, ?, ?, ?, NOW())');
      if ($stmt->execute([$first, $last, $email, $hash, $role])) {
        $msg = '<span style="color:green">User created successfully!</span>';
      } else {
        $msg = '<span style="color:red">Failed to create user.</span>';
      }
    }
  }
  
  exit;
}

// Handle Edit User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_user'])) {
  $id = intval($_POST['edit_id']);
  $first = trim($_POST['edit_first_name'] ?? '');
  $last = trim($_POST['edit_last_name'] ?? '');
  $email = trim($_POST['edit_email'] ?? '');
  $role = $_POST['edit_role'] ?? 'student';
  $pass = $_POST['edit_password'] ?? '';
  $msg = '';
  if (!$first || !$last || !$email || !in_array($role, ['student','teacher','admin'])) {
    $msg = '<span style="color:red">All fields required.</span>';
  } else {
    // Check for email conflict
    $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id != ?');
    $stmt->execute([$email, $id]);
    if ($stmt->fetch()) {
      $msg = '<span style="color:red">Email already exists.</span>';
    } else {
      if ($pass) {
        $hash = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, email=?, role=?, password=? WHERE id=?');
        $ok = $stmt->execute([$first, $last, $email, $role, $hash, $id]);
      } else {
        $stmt = $pdo->prepare('UPDATE users SET first_name=?, last_name=?, email=?, role=? WHERE id=?');
        $ok = $stmt->execute([$first, $last, $email, $role, $id]);
      }
      if ($ok) {
        $msg = '<span style="color:green">User updated successfully!</span>';
      } else {
        $msg = '<span style="color:red">Failed to update user.</span>';
      }
    }
  }
  
  exit;
}
?>
</body>
</html>