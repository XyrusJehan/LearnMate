<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Dashboard - LearnMate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
      --radius-sm: 6px;
      --radius-md: 8px;
      --radius-lg: 12px;
      --radius-xl: 16px;
      --space-xs: 4px;
      --space-sm: 8px;
      --space-md: 16px;
      --space-lg: 24px;
      --space-xl: 32px;
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
    }

    .app-container {
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar */
    .sidebar {
      width: 280px;
      background-color: var(--bg-white);
      border-right: 1px solid var(--border-light);
      padding: var(--space-xl);
      position: sticky;
      top: 0;
      height: 100vh;
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
      padding: var(--space-xl);
    }

    .header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--space-xl);
    }

    .header-title {
      font-weight: 600;
      font-size: 24px;
    }

    .user-info {
      display: flex;
      align-items: center;
      gap: var(--space-md);
    }

    .user-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      background-color: #F9F5FF;
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
    }

    /* Stats Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(4, 1fr);
      gap: var(--space-md);
      margin-bottom: var(--space-xl);
    }

    .stat-card {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      box-shadow: var(--shadow-sm);
      border-left: 4px solid var(--primary);
    }

    .stat-value {
      font-size: 28px;
      font-weight: 600;
      margin-bottom: var(--space-xs);
      color: var(--primary);
    }

    .stat-label {
      font-size: 14px;
      color: var(--text-light);
    }

    /* Content Sections */
    .content-section {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      box-shadow: var(--shadow-sm);
      margin-bottom: var(--space-lg);
    }

    .section-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--space-md);
    }

    .section-title-lg {
      font-size: 18px;
      font-weight: 600;
    }

    .btn {
      padding: 8px 16px;
      border-radius: var(--radius-md);
      border: none;
      font-weight: 500;
      cursor: pointer;
    }

    .btn-primary {
      background-color: var(--primary);
      color: white;
    }

    .btn-primary:hover {
      background-color: var(--primary-dark);
    }

    /* Tables */
    table {
      width: 100%;
      border-collapse: collapse;
    }

    th, td {
      padding: 12px 16px;
      text-align: left;
      border-bottom: 1px solid var(--border-light);
    }

    th {
      background-color: #F9F5FF;
      color: var(--primary-dark);
      font-weight: 600;
    }

    tr:hover {
      background-color: var(--bg-light);
    }

    .badge {
      display: inline-block;
      padding: 4px 8px;
      border-radius: var(--radius-full);
      font-size: 12px;
      font-weight: 500;
    }

    .badge-success {
      background-color: #ECFDF3;
      color: #027A48;
    }

    .badge-warning {
      background-color: #FFFAEB;
      color: #B54708;
    }

    .badge-danger {
      background-color: #FEF3F2;
      color: #B42318;
    }

    .actions {
      display: flex;
      gap: var(--space-xs);
    }

    .action-btn {
      padding: 6px;
      border-radius: var(--radius-sm);
      border: none;
      background: none;
      cursor: pointer;
    }

    .action-btn.edit {
      color: var(--primary);
    }

    .action-btn.delete {
      color: var(--danger);
    }

    /* Tabs */
    .tabs {
      display: flex;
      border-bottom: 1px solid var(--border-light);
      margin-bottom: var(--space-md);
    }

    .tab {
      padding: var(--space-sm) var(--space-md);
      cursor: pointer;
      position: relative;
    }

    .tab.active {
      color: var(--primary);
      font-weight: 600;
    }

    .tab.active:after {
      content: '';
      position: absolute;
      bottom: -1px;
      left: 0;
      right: 0;
      height: 2px;
      background-color: var(--primary);
    }
  </style>
</head>
<body>
  <div class="app-container">
    <!-- Sidebar -->
    <aside class="sidebar">
      <div class="sidebar-header">
        <div class="logo">LM</div>
        <div class="app-name">LearnMate</div>
      </div>
      
      <div class="nav-section">
        <div class="section-title">Menu</div>
        <a href="admin_dashboard.php" class="nav-item active">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>
        <a href="admin_analytics.php" class="nav-item">
          <i class="fas fa-chart-bar"></i>
          <span>Analytics</span>
        </a>
        <a href="admin_approval.php" class="nav-item">
          <i class="fas fa-user-check"></i>
          <span>Approvals</span>
        </a>
      </div>
      
      <div class="nav-section">
        <div class="section-title">Settings</div>
        <a href="../settings.php" class="nav-item">
          <i class="fas fa-cog"></i>
          <span>Settings</span>
        </a>
        <a href="../logout.php" class="nav-item">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
      <div class="header">
        <h1 class="header-title">Admin Dashboard</h1>
        <div class="user-info">
          <span>Welcome, <?php echo $_SESSION['admin_name'] ?? 'Admin'; ?></span>
          <div class="user-avatar">A</div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value">24</div>
          <div class="stat-label">Active Teachers</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">487</div>
          <div class="stat-label">Registered Students</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">36</div>
          <div class="stat-label">Courses</div>
        </div>
        <div class="stat-card">
          <div class="stat-value">82%</div>
          <div class="stat-label">Active Usage</div>
        </div>
      </div>

      <!-- Teachers Section -->
      <div class="content-section">
        <div class="section-header">
          <h2 class="section-title-lg">Teacher Management</h2>
          <button class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Teacher
          </button>
        </div>

        <div class="tabs">
          <div class="tab active">All Teachers</div>
          <div class="tab">Pending Approval</div>
          <div class="tab">Suspended</div>
        </div>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Courses</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>T101</td>
              <td>Dr. Sarah Johnson</td>
              <td>s.johnson@learnmate.edu</td>
              <td>Biology, Chemistry</td>
              <td><span class="badge badge-success">Active</span></td>
              <td class="actions">
                <button class="action-btn edit"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
            <tr>
              <td>T102</td>
              <td>Prof. Michael Chen</td>
              <td>m.chen@learnmate.edu</td>
              <td>Physics, Math</td>
              <td><span class="badge badge-success">Active</span></td>
              <td class="actions">
                <button class="action-btn edit"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
            <tr>
              <td>T103</td>
              <td>Ms. Emily Wilson</td>
              <td>e.wilson@learnmate.edu</td>
              <td>English Literature</td>
              <td><span class="badge badge-warning">Pending</span></td>
              <td class="actions">
                <button class="action-btn edit"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
            <tr>
              <td>T104</td>
              <td>Dr. Robert Davis</td>
              <td>r.davis@learnmate.edu</td>
              <td>History, Political Science</td>
              <td><span class="badge badge-danger">Suspended</span></td>
              <td class="actions">
                <button class="action-btn edit"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Students Section -->
      <div class="content-section">
        <div class="section-header">
          <h2 class="section-title-lg">Student Management</h2>
          <button class="btn btn-primary">
            <i class="fas fa-plus"></i> Add Student
          </button>
        </div>

        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Name</th>
              <th>Email</th>
              <th>Enrolled Courses</th>
              <th>Groups</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>S1001</td>
              <td>Alex Johnson</td>
              <td>alex.j@student.edu</td>
              <td>Biology 101, Chemistry</td>
              <td>Bio Study, Chem Club</td>
              <td class="actions">
                <button class="action-btn edit"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
            <tr>
              <td>S1002</td>
              <td>Maria Garcia</td>
              <td>maria.g@student.edu</td>
              <td>Physics, Math</td>
              <td>Physics Group</td>
              <td class="actions">
                <button class="action-btn edit"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
            <tr>
              <td>S1003</td>
              <td>James Wilson</td>
              <td>james.w@student.edu</td>
              <td>English, History</td>
              <td>Literature Club</td>
              <td class="actions">
                <button class="action-btn edit"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
            <tr>
              <td>S1004</td>
              <td>Sarah Lee</td>
              <td>Sarah.l@student.edu</td>
              <td>Chemistry, Math</td>
              <td>Chem Club, Mathletes</td>
              <td class="actions">
                <button class="action-btn edit"><i class="fas fa-edit"></i></button>
                <button class="action-btn delete"><i class="fas fa-trash"></i></button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </main>
  </div>
</body>
</html>