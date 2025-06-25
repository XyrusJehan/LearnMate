<?php
session_start();
require 'db.php';
require 'includes/theme.php';
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}
$theme = getCurrentTheme();

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare('DELETE FROM classes WHERE id = ?');
    $stmt->execute([$_GET['delete']]);
    header('Location: admin_classes.php');
    exit();
}

// Handle add class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    $name = trim($_POST['class_name'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $teacher_id = intval($_POST['teacher_id'] ?? 0);
    $msg = '';
    if (!$name || !$section || !$teacher_id) {
        $msg = '<span style="color:red">All fields required.</span>';
    } else {
        $stmt = $pdo->prepare('INSERT INTO classes (class_name, section, teacher_id, created_at) VALUES (?, ?, ?, NOW())');
        if ($stmt->execute([$name, $section, $teacher_id])) {
            $msg = '<span style="color:green">Class added!</span>';
        } else {
            $msg = '<span style="color:red">Failed to add class.</span>';
        }
    }
    echo '<div id="addClassMsg">'.$msg.'</div>';
    exit;
}

// Handle get class for edit
if (isset($_GET['get_class']) && is_numeric($_GET['get_class'])) {
    $stmt = $pdo->prepare('SELECT * FROM classes WHERE id = ?');
    $stmt->execute([$_GET['get_class']]);
    $class = $stmt->fetch(PDO::FETCH_ASSOC);
    header('Content-Type: application/json');
    echo json_encode($class);
    exit;
}

// Handle edit class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_class'])) {
    $id = intval($_POST['edit_id']);
    $name = trim($_POST['edit_class_name'] ?? '');
    $section = trim($_POST['edit_section'] ?? '');
    $teacher_id = intval($_POST['edit_teacher_id'] ?? 0);
    $msg = '';
    if (!$name || !$section || !$teacher_id) {
        $msg = '<span style="color:red">All fields required.</span>';
    } else {
        $stmt = $pdo->prepare('UPDATE classes SET class_name=?, section=?, teacher_id=? WHERE id=?');
        if ($stmt->execute([$name, $section, $teacher_id, $id])) {
            $msg = '<span style="color:green">Class updated!</span>';
        } else {
            $msg = '<span style="color:red">Failed to update class.</span>';
        }
    }
    echo '<div id="editClassMsg">'.$msg.'</div>';
    exit;
}

// Fetch all classes with teacher name
$stmt = $pdo->query('SELECT c.*, u.first_name, u.last_name FROM classes c LEFT JOIN users u ON c.teacher_id = u.id ORDER BY c.created_at DESC');
$classes = $stmt->fetchAll();

// Fetch all teachers for dropdown
$teachers = $pdo->query("SELECT id, first_name, last_name FROM users WHERE role = 'teacher' ORDER BY first_name, last_name")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Class Management - LearnMate Admin</title>
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

    /* Modal Styles */
    .modal-card {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(0,0,0,0.3);
      z-index: 1000;
      align-items: center;
      justify-content: center;
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
        <h1 class="header-title">Class Management</h1>
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
            <i class="fas fa-chalkboard"></i>
            <span>All Classes</span>
          </h2>
          <button class="btn btn-primary" id="openAddClassModal">
            <i class="fas fa-plus"></i> Add Class
          </button>
        </div>
        
        <div style="overflow-x: auto;">
          <table>
            <thead>
              <tr>
                <th>ID</th>
                <th>Class Name</th>
                <th>Section</th>
                <th>Teacher</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($classes as $class): ?>
              <tr>
                <td><?php echo $class['id']; ?></td>
                <td><?php echo htmlspecialchars($class['class_name']); ?></td>
                <td><?php echo htmlspecialchars($class['section']); ?></td>
                <td><?php echo htmlspecialchars($class['first_name'].' '.$class['last_name']); ?></td>
                <td><?php echo date('M d, Y', strtotime($class['created_at'])); ?></td>
                <td>
                  <button type="button" class="btn btn-primary" data-id="<?php echo $class['id']; ?>" style="padding: 6px 12px; font-size: 14px;">
                    <i class="fas fa-edit"></i> Edit
                  </button>
                  <a href="admin_classes.php?delete=<?php echo $class['id']; ?>" class="btn btn-danger" style="padding: 6px 12px; font-size: 14px;" onclick="return confirm('Delete this class?');">
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

  <!-- Add Class Modal -->
  <div id="addClassModal" class="modal-card" style="display:none;">
    <div class="modal-content">
      <button type="button" class="modal-close" id="closeAddClassModal" aria-label="Close">&times;</button>
      <h2 class="modal-title">Add New Class</h2>
      <form method="POST" id="addClassForm">
        <div class="modal-group"><input type="text" name="class_name" placeholder="Class Name" required class="modal-input"></div>
        <div class="modal-group"><input type="text" name="section" placeholder="Section" required class="modal-input"></div>
        <div class="modal-group">
          <select name="teacher_id" required class="modal-input">
            <option value="">Select Teacher</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn btn-primary" style="flex:1;">Add Class</button>
        </div>
      </form>
      <div id="addClassMsg" class="modal-msg"></div>
    </div>
  </div>

  <!-- Edit Class Modal -->
  <div id="editClassModal" class="modal-card" style="display:none;">
    <div class="modal-content">
      <button type="button" class="modal-close" id="closeEditClassModal" aria-label="Close">&times;</button>
      <h2 class="modal-title">Edit Class</h2>
      <form method="POST" id="editClassForm">
        <input type="hidden" name="edit_id" id="edit_id">
        <div class="modal-group"><input type="text" name="edit_class_name" id="edit_class_name" placeholder="Class Name" required class="modal-input"></div>
        <div class="modal-group"><input type="text" name="edit_section" id="edit_section" placeholder="Section" required class="modal-input"></div>
        <div class="modal-group">
          <select name="edit_teacher_id" id="edit_teacher_id" required class="modal-input">
            <option value="">Select Teacher</option>
            <?php foreach ($teachers as $t): ?>
              <option value="<?php echo $t['id']; ?>"><?php echo htmlspecialchars($t['first_name'].' '.$t['last_name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="modal-actions">
          <button type="submit" class="btn btn-primary" style="flex:1;">Save Changes</button>
        </div>
      </form>
      <div id="editClassMsg" class="modal-msg"></div>
    </div>
  </div>

  <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Add Class Modal
      document.getElementById('openAddClassModal').onclick = function() {
        document.getElementById('addClassModal').style.display = 'flex';
        document.getElementById('addClassForm').reset();
        document.getElementById('addClassMsg').innerHTML = '';
      };
      document.getElementById('closeAddClassModal').onclick = function() {
        document.getElementById('addClassModal').style.display = 'none';
        document.getElementById('addClassMsg').innerHTML = '';
      };
      document.getElementById('addClassForm').onsubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        data.append('add_class', '1');
        const res = await fetch('admin_classes.php', { method: 'POST', body: data });
        const text = await res.text();
        const msg = text.match(/<div id="addClassMsg">([\s\S]*?)<\/div>/);
        document.getElementById('addClassMsg').innerHTML = msg ? msg[1] : 'Class added!';
        if (msg && msg[1].includes('green')) setTimeout(()=>location.reload(), 1200);
      };

      // Edit Class Modal
      document.querySelectorAll('.btn-primary').forEach(btn => {
        if (btn.getAttribute('data-id')) {
          btn.onclick = async function() {
            const id = this.getAttribute('data-id');
            const res = await fetch('admin_classes.php?get_class='+id);
            const c = await res.json();
            document.getElementById('edit_id').value = c.id;
            document.getElementById('edit_class_name').value = c.class_name;
            document.getElementById('edit_section').value = c.section;
            document.getElementById('edit_teacher_id').value = c.teacher_id;
            document.getElementById('editClassMsg').innerHTML = '';
            document.getElementById('editClassModal').style.display = 'flex';
          };
        }
      });

      document.getElementById('closeEditClassModal').onclick = function() {
        document.getElementById('editClassModal').style.display = 'none';
        document.getElementById('editClassMsg').innerHTML = '';
      };

      document.getElementById('editClassForm').onsubmit = async function(e) {
        e.preventDefault();
        const form = e.target;
        const data = new FormData(form);
        data.append('edit_class', '1');
        const res = await fetch('admin_classes.php', { method: 'POST', body: data });
        const text = await res.text();
        const msg = text.match(/<div id="editClassMsg">([\s\S]*?)<\/div>/);
        document.getElementById('editClassMsg').innerHTML = msg ? msg[1] : 'Class updated!';
        if (msg && msg[1].includes('green')) setTimeout(()=>location.reload(), 1200);
      };

      // FAB button for mobile
      document.querySelector('.fab').onclick = function() {
        document.getElementById('addClassModal').style.display = 'flex';
        document.getElementById('addClassForm').reset();
        document.getElementById('addClassMsg').innerHTML = '';
      };
    });
  </script>
</body>
</html>