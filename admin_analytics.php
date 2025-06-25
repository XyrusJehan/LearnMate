<?php
// admin_analytics.php
session_start();
require 'db.php';
require 'includes/theme.php';

// Check if user is logged in as admin
if (!isset($_SESSION['user_id'])){
    header('Location: index.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$theme = getCurrentTheme();

// Fetch analytics data
$stats = [
    'total_users' => 0,
    'active_students' => 0,
    'active_teachers' => 0,
    'total_classes' => 0,
    'total_groups' => 0,
    'total_flashcards' => 0
];

try {
    // Total users
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $stats['total_users'] = $stmt->fetch()['count'];
    
    // Active students
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
    $stmt->execute();
    $stats['active_students'] = $stmt->fetch()['count'];
    
    // Active teachers
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'");
    $stmt->execute();
    $stats['active_teachers'] = $stmt->fetch()['count'];
    
    // Total classes
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM classes");
    $stmt->execute();
    $stats['total_classes'] = $stmt->fetch()['count'];
    
    // Total groups
  $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM `groups`");
    $stmt->execute();
    $stats['total_groups'] = $stmt->fetch()['count'];
    
    // Total flashcards
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM flashcards");
    $stmt->execute();
    $stats['total_flashcards'] = $stmt->fetch()['count'];
    
    // Total assignments
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assignments");
    $stmt->execute();
    $stats['total_assignments'] = $stmt->fetch()['count'];
    
    // Total submissions
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM assignment_submissions");
    $stmt->execute();
    $stats['total_submissions'] = $stmt->fetch()['count'];
    
    // Recent activity (last 7 days)
    $stmt = $pdo->prepare("
        SELECT a.*, u.first_name, u.last_name, u.role 
        FROM activities a
        JOIN users u ON a.student_id = u.id
        WHERE a.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY a.created_at DESC
        LIMIT 10
    ");
    $stmt->execute();
    $recent_activities = $stmt->fetchAll();
    
    // User growth data (last 30 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as count,
            SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as students,
            SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as teachers
        FROM users
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at)
        ORDER BY DATE(created_at)
    ");
    $stmt->execute();
    $user_growth = $stmt->fetchAll();
    
    // Most active classes
    $stmt = $pdo->prepare("
        SELECT 
            c.id, 
            c.class_name, 
            c.section,
            COUNT(cs.student_id) as student_count
        FROM classes c
        LEFT JOIN class_students cs ON c.id = cs.class_id
        GROUP BY c.id
        ORDER BY student_count DESC
        LIMIT 5
    ");
    $stmt->execute();
    $active_classes = $stmt->fetchAll();
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
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
  <title>System Analytics - LearnMate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="css/theme.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

    .app-container {
      display: flex;
      min-height: 100vh;
    }

    /* Sidebar Styles */
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

    /* Main Content Styles */
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

    /* Stats Section */
    .stats-grid {
      display: grid;
      gap: var(--space-sm);
      margin-bottom: var(--space-lg);
    }

    .stat-card {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      box-shadow: var(--shadow-sm);
    }

    .stat-value {
      font-size: 24px;
      font-weight: 600;
      margin-bottom: var(--space-xs);
      color: var(--primary);
    }

    .stat-label {
      font-size: 12px;
      color: var(--text-light);
    }

    /* Section Titles */
    .section-title-lg {
      font-size: 20px;
      font-weight: 600;
      margin-bottom: var(--space-md);
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .section-title-lg i {
      color: var(--primary);
    }

    /* Chart Containers */
    .chart-container {
      background-color: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-md);
      margin-bottom: var(--space-lg);
      box-shadow: var(--shadow-sm);
      height: 300px;
    }

    /* Activity Items */
    .activity-item {
      display: flex;
      align-items: flex-start;
      gap: var(--space-md);
      padding: var(--space-sm) 0;
      border-bottom: 1px solid var(--border-light);
    }

    .activity-icon {
      width: 32px;
      height: 32px;
      border-radius: var(--radius-full);
      background-color: #F9F5FF;
      color: var(--primary);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .activity-details {
      flex: 1;
    }

    .activity-title {
      font-weight: 500;
      margin-bottom: 2px;
    }

    .activity-meta {
      font-size: 12px;
      color: var(--text-light);
      display: flex;
      gap: var(--space-sm);
    }

    .user-role {
      font-size: 12px;
      padding: 2px 6px;
      border-radius: var(--radius-sm);
    }

    .user-role.student {
      background-color: #ECFDF3;
      color: #027A48;
    }

    .user-role.teacher {
      background-color: #FDF2FA;
      color: #C11574;
    }

    /* Table Styles */
    .analytics-table {
      width: 100%;
      border-collapse: collapse;
      margin-bottom: var(--space-lg);
    }

    .analytics-table th,
    .analytics-table td {
      padding: var(--space-sm);
      text-align: left;
      border-bottom: 1px solid var(--border-light);
    }

    .analytics-table th {
      font-weight: 600;
      font-size: 14px;
      color: var(--text-medium);
      background-color: var(--bg-light);
    }

    .analytics-table tr:hover {
      background-color: #F9F5FF;
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
      .stats-grid {
        grid-template-columns: repeat(2, 1fr);
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
      
      .stats-grid {
        grid-template-columns: repeat(4, 1fr);
      }
    }

    @media (min-width: 1024px) {
      .chart-container {
        height: 350px;
      }
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
        <h1 class="header-title">System Analytics</h1>
        <div class="header-actions">
          <button class="header-btn">
            <i class="fas fa-search"></i>
          </button>
          <button class="header-btn">
            <i class="fas fa-bell"></i>
          </button>
        </div>
      </header>

      <!-- Stats Section -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-value"><?php echo number_format($stats['total_users']); ?></div>
          <div class="stat-label">Total Users</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo number_format($stats['active_students']); ?></div>
          <div class="stat-label">Active Students</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo number_format($stats['active_teachers']); ?></div>
          <div class="stat-label">Active Teachers</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo number_format($stats['total_classes']); ?></div>
          <div class="stat-label">Total Classes</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo number_format($stats['total_groups']); ?></div>
          <div class="stat-label">Study Groups</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo number_format($stats['total_flashcards']); ?></div>
          <div class="stat-label">Flashcards</div>
        </div>
        <div class="stat-card">
          <div class="stat-value"><?php echo number_format($stats['total_submissions']); ?></div>
          <div class="stat-label">Submissions</div>
        </div>
      </div>

      <!-- User Growth Chart -->
      <h2 class="section-title-lg">
        <i class="fas fa-chart-line"></i>
        <span>User Growth</span>
      </h2>
      
      <div class="chart-container">
        <canvas id="userGrowthChart"></canvas>
      </div>

      <!-- Most Active Classes -->
      <h2 class="section-title-lg">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Most Active Classes</span>
      </h2>
      
      <table class="analytics-table">
        <thead>
          <tr>
            <th>Class Name</th>
            <th>Students</th>
            <th>Engagement</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($active_classes as $class): ?>
          <tr>
            <td><?php echo htmlspecialchars($class['class_name']); ?></td>
            <td><?php echo number_format($class['student_count']); ?></td>
            <td>
              <?php 
                $engagement = $class['student_count'] > 0 ? 
                  round(($class['student_count'] / 100) * 100, 1) : 0;
                echo $engagement . '%';
              ?>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>

      <!-- Recent Activity Section -->
      <h2 class="section-title-lg">
        <i class="fas fa-clock"></i>
        <span>Recent Activity</span>
      </h2>
      
      <div style="background-color: var(--bg-white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--space-sm);">
        <?php if (empty($recent_activities)): ?>
          <div class="activity-item">
            <div class="activity-details">
              <div class="activity-title">No recent activity</div>
            </div>
          </div>
        <?php else: ?>
          <?php foreach ($recent_activities as $activity): ?>
          <div class="activity-item">
            <div class="activity-icon">
              <i class="fas fa-<?php echo htmlspecialchars($activity['icon'] ?? 'bell'); ?>"></i>
            </div>
            <div class="activity-details">
              <div class="activity-title"><?php echo htmlspecialchars($activity['description']); ?></div>
              <div class="activity-meta">
                <span><?php echo time_elapsed_string($activity['created_at']); ?></span>
                <span class="user-role <?php echo htmlspecialchars($activity['role']); ?>">
                  <?php echo htmlspecialchars(ucfirst($activity['role'])); ?>
                </span>
                <span><?php echo htmlspecialchars($activity['first_name'] . ' ' . $activity['last_name']); ?></span>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </main>
  </div>

  <!-- Bottom Navigation - Mobile Only -->
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
    // Initialize user growth chart
    const userGrowthCtx = document.getElementById('userGrowthChart').getContext('2d');
    const userGrowthChart = new Chart(userGrowthCtx, {
      type: 'line',
      data: {
        labels: [
          <?php 
            $dates = array_column($user_growth, 'date');
            foreach ($dates as $date) {
              echo "'" . date('M j', strtotime($date)) . "',";
            }
          ?>
        ],
        datasets: [
          {
            label: 'Total Users',
            data: [
              <?php 
                $counts = [];
                $running_total = 0;
                foreach ($user_growth as $day) {
                  $running_total += $day['count'];
                  echo $running_total . ',';
                }
              ?>
            ],
            borderColor: '#7F56D9',
            backgroundColor: 'rgba(127, 86, 217, 0.1)',
            tension: 0.3,
            fill: true
          },
          {
            label: 'Students',
            data: [
              <?php 
                $running_students = 0;
                foreach ($user_growth as $day) {
                  $running_students += $day['students'];
                  echo $running_students . ',';
                }
              ?>
            ],
            borderColor: '#36BFFA',
            backgroundColor: 'rgba(54, 191, 250, 0.1)',
            tension: 0.3,
            fill: true
          },
          {
            label: 'Teachers',
            data: [
              <?php 
                $running_teachers = 0;
                foreach ($user_growth as $day) {
                  $running_teachers += $day['teachers'];
                  echo $running_teachers . ',';
                }
              ?>
            ],
            borderColor: '#32D583',
            backgroundColor: 'rgba(50, 213, 131, 0.1)',
            tension: 0.3,
            fill: true
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'top',
          },
          tooltip: {
            mode: 'index',
            intersect: false,
          }
        },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: function(value) {
                if (value >= 1000) {
                  return value / 1000 + 'k';
                }
                return value;
              }
            }
          }
        }
      }
    });

    // Toggle dropdown functionality
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
  </script>
</body>
</html>