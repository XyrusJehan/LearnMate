<?php
session_start();
require '../db.php';
require '../includes/theme.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

$correct = isset($_GET['correct']) ? (int)$_GET['correct'] : 0;
$incorrect = isset($_GET['incorrect']) ? (int)$_GET['incorrect'] : 0;
$total = $correct + $incorrect;
$percentage = $total > 0 ? round(($correct / $total) * 100) : 0;

// Get theme for the page
$theme = getCurrentTheme();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Review Complete - LearnMate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/theme.css">
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
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: var(--space-lg);
            flex: 1;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
        }

        .back-btn {
            background-color: var(--primary);
            color: white;
            padding: var(--space-sm) var(--space-md);
            border: none;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            transition: var(--transition);
        }

        .back-btn:hover {
            background-color: var(--primary-dark);
        }

        .result-card {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            padding: var(--space-xl);
            box-shadow: var(--shadow-lg);
            text-align: center;
            margin-bottom: var(--space-lg);
        }

        .result-icon {
            font-size: 64px;
            margin-bottom: var(--space-md);
            color: var(--primary);
        }

        .result-title {
            font-size: 24px;
            font-weight: 600;
            margin-bottom: var(--space-md);
            color: var(--text-dark);
        }

        .result-stats {
            display: flex;
            justify-content: center;
            gap: var(--space-xl);
            margin-bottom: var(--space-lg);
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-size: 36px;
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-light);
        }

        .stat-value.correct {
            color: var(--success);
        }

        .stat-value.incorrect {
            color: var(--danger);
        }

        .stat-value.total {
            color: var(--primary);
        }

        .actions {
            display: flex;
            gap: var(--space-md);
            justify-content: center;
        }

        .action-btn {
            padding: var(--space-md) var(--space-lg);
            border: none;
            border-radius: var(--radius-md);
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            transition: var(--transition);
            text-decoration: none;
        }

        .review-btn {
            background-color: var(--primary);
            color: white;
        }

        .review-btn:hover {
            background-color: var(--primary-dark);
        }

        .dashboard-btn {
            background-color: var(--bg-light);
            color: var(--text-dark);
        }

        .dashboard-btn:hover {
            background-color: var(--border-light);
        }
    </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-check-circle"></i> Review Complete</h1>
            <a href="flashcard.php" class="back-btn">
                <i class="fas fa-arrow-left"></i>
                Back to Dashboard
            </a>
        </div>

        <div class="result-card">
            <div class="result-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <h2 class="result-title">Great job!</h2>
            
            <div class="result-stats">
                <div class="stat-item">
                    <div class="stat-value correct"><?php echo $correct; ?></div>
                    <div class="stat-label">Correct</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value incorrect"><?php echo $incorrect; ?></div>
                    <div class="stat-label">Incorrect</div>
                </div>
                <div class="stat-item">
                    <div class="stat-value total"><?php echo $percentage; ?>%</div>
                    <div class="stat-label">Accuracy</div>
                </div>
            </div>

            <div class="actions">
                <a href="review.php" class="action-btn review-btn">
                    <i class="fas fa-redo"></i>
                    Review Again
                </a>
                <a href="flashcard.php" class="action-btn dashboard-btn">
                    <i class="fas fa-home"></i>
                    Back to Dashboard
                </a>
            </div>
        </div>
    </div>
</body>
</html> 