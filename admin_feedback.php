<?php
session_start();
require 'db.php';
require 'includes/theme.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit();
}

$theme = getCurrentTheme();

// Get all reports with user information
try {
    $stmt = $pdo->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email, u.role 
        FROM reports r 
        JOIN users u ON r.user_id = u.id 
        ORDER BY 
            CASE r.status 
                WHEN 'pending' THEN 1 
                WHEN 'in_progress' THEN 2 
                WHEN 'resolved' THEN 3 
            END,
            r.created_at DESC
    ");
    $stmt->execute();
    $reports = $stmt->fetchAll();
} catch (PDOException $e) {
    $error = "Database error: " . $e->getMessage();
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
    <title>User Feedback - LearnMate Admin</title>
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

        [data-theme="dark"] {
            --text-dark: #FFFFFF;
            --text-medium: #E0E0E0;
            --text-light: #CCCCCC;
            --bg-light: #1A1A1A;
            --bg-white: #2A2A2A;
            --border-light: #333333;
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

        .main-content {
            flex: 1;
            padding: var(--space-xl);
            background-color: var(--bg-light);
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
        }

        .header-title {
            font-size: 24px;
            font-weight: 600;
            color: var(--text-dark);
        }

        .card {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--space-xl);
            margin-bottom: var(--space-lg);
        }

        .reports-list {
            display: flex;
            flex-direction: column;
            gap: var(--space-md);
        }

        .report-item {
            background-color: var(--bg-white);
            border: 1px solid var(--border-light);
            border-radius: var(--radius-md);
            padding: var(--space-lg);
            transition: var(--transition);
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

        .report-item:hover {
            box-shadow: var(--shadow-md);
        }

        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: var(--space-md);
        }

        .report-user {
            display: flex;
            flex-direction: column;
            gap: var(--space-xs);
        }

        .user-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 16px;
        }

        .user-info {
            color: var(--text-light);
            font-size: 14px;
        }

        .report-meta {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: var(--space-xs);
        }

        .report-date {
            color: var(--text-light);
            font-size: 14px;
        }

        .report-status {
            display: inline-block;
            padding: 4px 12px;
            border-radius: var(--radius-full);
            font-size: 12px;
            font-weight: 600;
            color: white;
        }

        .status-pending {
            background-color: var(--warning);
        }

        .status-in_progress {
            background-color: var(--secondary);
        }

        .status-resolved {
            background-color: var(--success);
        }

        .report-message {
            color: var(--text-medium);
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: var(--space-md);
            white-space: pre-wrap;
        }

        .report-actions {
            display: flex;
            gap: var(--space-sm);
            margin-top: var(--space-md);
        }

        .btn {
            padding: 8px 16px;
            border-radius: var(--radius-md);
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
        }

        .btn:hover {
            transform: translateY(-1px);
        }

        .btn-in-progress {
            background-color: var(--secondary);
            color: white;
        }

        .btn-resolve {
            background-color: var(--success);
            color: white;
        }

        .btn-delete {
            background-color: var(--danger);
            color: white;
        }

        .empty-state {
            text-align: center;
            padding: var(--space-xl);
            color: var(--text-medium);
        }

        .empty-state i {
            font-size: 48px;
            color: var(--text-light);
            margin-bottom: var(--space-md);
        }

        @media (min-width: 768px) {
            .sidebar {
                display: flex;
                flex-direction: column;
            }
        }
    </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
    <div class="app-container">
        <?php include 'includes/admin_sidebar.php'; ?>

        <main class="main-content">
            <div class="header">
                <h1 class="header-title">User Feedback</h1>
            </div>

            <div class="card">
                <?php if (!empty($reports)): ?>
                <div class="reports-list">
                    <?php foreach ($reports as $report): ?>
                    <div class="report-item">
                        <div class="report-header">
                            <div class="report-user">
                                <div class="user-name">
                                    <?php echo htmlspecialchars($report['first_name'] . ' ' . $report['last_name']); ?>
                                </div>
                                <div class="user-info">
                                    <?php echo htmlspecialchars($report['email']); ?> • 
                                    <span class="badge" style="background-color: var(--primary-light);">
                                        <?php echo ucfirst($report['role']); ?>
                                    </span>
                                </div>
                            </div>
                            <div class="report-meta">
                                <div class="report-date">
                                    <?php echo time_elapsed_string($report['created_at']); ?>
                                </div>
                                <span class="report-status status-<?php echo $report['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="report-message">
                            <?php echo nl2br(htmlspecialchars($report['message'])); ?>
                        </div>
                        <div class="report-actions">
                            <?php if ($report['status'] === 'pending'): ?>
                            <button class="btn btn-in-progress" onclick="updateReportStatus(<?php echo $report['id']; ?>, 'in_progress')">
                                <i class="fas fa-clock"></i> Mark In Progress
                            </button>
                            <?php endif; ?>
                            <?php if ($report['status'] !== 'resolved'): ?>
                            <button class="btn btn-resolve" onclick="updateReportStatus(<?php echo $report['id']; ?>, 'resolved')">
                                <i class="fas fa-check"></i> Mark Resolved
                            </button>
                            <?php endif; ?>
                            <button class="btn btn-delete" onclick="deleteReport(<?php echo $report['id']; ?>)">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-comment-slash"></i>
                    <h3>No Reports Yet</h3>
                    <p>There are no user reports to display.</p>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <script>
        // Function to update report status
        async function updateReportStatus(reportId, status) {
            try {
                const response = await fetch('update_report.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        report_id: reportId,
                        status: status
                    })
                });
                
                if (response.ok) {
                    location.reload();
                } else {
                    alert('Failed to update report status');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while updating the report');
            }
        }

        // Function to delete report
        async function deleteReport(reportId) {
            if (!confirm('Are you sure you want to delete this report?')) {
                return;
            }

            try {
                const response = await fetch('delete_report.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        report_id: reportId
                    })
                });
                
                if (response.ok) {
                    location.reload();
                } else {
                    alert('Failed to delete report');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('An error occurred while deleting the report');
            }
        }
    </script>
</body>
</html> 