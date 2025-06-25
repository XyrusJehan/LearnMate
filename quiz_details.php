<?php
session_start();
require 'db.php';
require 'includes/theme.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$quizId = $_GET['id'] ?? 0;
$teacherId = $_SESSION['user_id'];

// Get quiz details
$quiz = [];
$questions = [];
$classesWithQuiz = [];

try {
    // Get quiz info
    $stmt = $pdo->prepare("
        SELECT q.*
        FROM quizzes q
        WHERE q.id = ? AND q.created_by = ?
    ");
    $stmt->execute([$quizId, $teacherId]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $_SESSION['error'] = 'Quiz not found or you don\'t have permission to view it';
        header('Location: teacher_dashboard.php');
        exit();
    }

    // Get questions
    $stmt = $pdo->prepare("
        SELECT qq.*, 
               (SELECT COUNT(*) FROM quiz_options qo WHERE qo.question_id = qq.id) as option_count
        FROM quiz_questions qq
        WHERE qq.quiz_id = ?
        ORDER BY qq.id
    ");
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get classes this quiz is assigned to
    $stmt = $pdo->prepare("
        SELECT c.id, c.class_name, c.section 
        FROM quiz_classes qc
        JOIN classes c ON qc.class_id = c.id
        WHERE qc.quiz_id = ?
    ");
    $stmt->execute([$quizId]);
    $classesWithQuiz = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Fetch teacher's quizzes for sidebar
    $quizzes = [];
    $stmt = $pdo->prepare("SELECT id, title FROM quizzes WHERE created_by = ? ORDER BY created_at DESC LIMIT 20");
    $stmt->execute([$teacherId]);
    $quizzes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching quiz details: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading quiz details';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Details - LearnMate</title>
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
            --bg-light: #1A1A2A;
            --bg-white: #2A2A3A;
            --border-light: #333344;
            --primary-extra-light: #2A1A4A;
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
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 250px;
            background-color: var(--bg-white);
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            z-index: 1000;
            transition: background-color var(--transition), border-color var(--transition);
        }

        [data-theme="dark"] .sidebar {
            background-color: var(--bg-white);
            border-right: 1px solid var(--border-light);
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid var(--border-light);
        }

        .sidebar-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        [data-theme="dark"] .sidebar-header h3 {
            color: var(--text-dark);
        }

        .add-quiz-btn {
            background-color: var(--primary);
            color: var(--bg-white);
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .add-quiz-btn:hover {
            background-color: var(--primary-light);
            transform: rotate(90deg);
        }

        .quiz-list {
            list-style: none;
            padding-left: 0;
        }

        .quiz-item {
            position: relative;
            display: flex;
            align-items: center;
        }

        .quiz-link {
            flex-grow: 1;
            display: block;
            padding: 10px 12px;
            border-radius: 6px;
            color: var(--text-dark);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        [data-theme="dark"] .quiz-link {
            color: var(--text-dark);
        }

        .quiz-link:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }

        .quiz-link.active {
            background-color: var(--primary);
            color: var(--bg-white);
        }

        .quiz-link i {
            font-size: 0.9rem;
        }

        /* Main Content Styles */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: var(--space-xl);
            width: 100%;
            transition: margin-left var(--transition);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Header Styles */
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: var(--space-xl);
            flex-wrap: wrap;
            gap: var(--space-md);
        }

        .header h1 {
            color: var(--primary);
            font-size: 1.8rem;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-weight: 600;
        }

        /* Button Styles */
        .btn {
            padding: var(--space-sm) var(--space-md);
            border-radius: var(--radius-md);
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: var(--space-sm);
            text-decoration: none;
            font-size: 0.95rem;
        }

        .btn-primary {
            background-color: var(--primary);
            color: white;
            box-shadow: var(--shadow-xs);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-secondary {
            background-color: var(--bg-white);
            color: var(--primary);
            border: 1px solid var(--border-light);
            box-shadow: var(--shadow-xs);
        }

        .btn-secondary:hover {
            background-color: var(--bg-light);
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn-lg {
            padding: var(--space-md) var(--space-xl);
            font-size: 1rem;
        }

        /* Card Styles */
        .card {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--space-xl);
            margin-bottom: var(--space-xl);
            transition: var(--transition);
        }

        .card:hover {
            box-shadow: var(--shadow-md);
        }

        .questions-list {
            margin-bottom: var(--space-xl);
        }

        .question-item {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--space-lg);
            margin-bottom: var(--space-md);
        }

        .options-list {
            margin-top: var(--space-md);
        }

        .option-item {
            padding: var(--space-sm);
            margin-bottom: var(--space-xs);
            border-radius: var(--radius-md);
            background-color: var(--bg-light);
            display: flex;
            align-items: center;
        }

        .correct-option {
            background-color: rgba(50, 213, 131, 0.1);
            border-left: 3px solid var(--success);
        }

        .option-marker {
            width: 20px;
            height: 20px;
            border-radius: 50%;
            background-color: var(--border-light);
            margin-right: var(--space-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .correct-marker {
            background-color: var(--success);
            color: white;
        }

        .classes-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: var(--space-md);
            margin-bottom: var(--space-xl);
        }

        .class-card {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--space-md);
        }

        .class-name {
            font-weight: 600;
            margin-bottom: var(--space-xs);
        }

        .class-section {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        /* Message Styles */
        .error-message {
            color: var(--danger);
            font-size: 0.9rem;
            margin-top: var(--space-xs);
        }

        .success-message {
            color: var(--success);
            background-color: rgba(50, 213, 131, 0.1);
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            border-left: 4px solid var(--success);
        }

        .alert-message {
            color: var(--danger);
            background-color: rgba(249, 112, 102, 0.1);
            padding: var(--space-md);
            border-radius: var(--radius-md);
            margin-bottom: var(--space-lg);
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            border-left: 4px solid var(--danger);
        }

        /* Responsive Styles */
        @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 15px;
            }

            .main-content {
                margin-left: 0;
                padding: var(--space-md);
            }
            
            .container {
                padding: 0;
            }
            
            .card, .question-item {
                padding: var(--space-lg);
            }
            
            .header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-group .btn {
                width: 100%;
                margin-bottom: var(--space-sm);
            }
        }

        @media (max-width: 480px) {
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .classes-list {
                grid-template-columns: 1fr;
            }
        }

        /* Kebab menu styles */
        .quiz-item {
            position: relative;
            display: flex;
            align-items: center;
        }

        .quiz-link {
            flex-grow: 1;
        }

        .kebab-menu {
            position: relative;
            margin-left: auto;
        }

        .kebab-btn {
            background: none;
            border: none;
            color: var(--text-medium);
            cursor: pointer;
            padding: 8px;
            border-radius: 50%;
            transition: var(--transition);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }

        .kebab-btn:hover {
            background-color: rgba(0, 0, 0, 0.05);
            color: var(--primary);
        }

        .kebab-dropdown {
            position: absolute;
            right: 0;
            top: 100%;
            background-color: var(--bg-white);
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-md);
            padding: var(--space-xs) 0;
            min-width: 150px;
            z-index: 100;
            display: none;
        }

        .kebab-dropdown.show {
            display: block;
        }

        .kebab-dropdown button {
            width: 100%;
            padding: var(--space-sm) var(--space-md);
            text-align: left;
            background: none;
            border: none;
            color: var(--text-dark);
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: var(--space-sm);
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .kebab-dropdown button:hover {
            background-color: var(--primary-extra-light);
            color: var(--primary);
        }

        .kebab-dropdown button i {
            width: 18px;
            text-align: center;
        }

        /* Loading indicator */
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(0, 0, 0, 0.5);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            display: none;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--primary-light);
            border-radius: 50%;
            border-top-color: var(--primary);
            animation: spin 1s ease-in-out infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
    <div class="loading-overlay">
        <div class="spinner"></div>
    </div>
    
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-question-circle"></i> My Quizzes</h3>
            <button class="add-quiz-btn" title="Create New Quiz" onclick="window.location.href='create_quiz.php'">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <ul class="quiz-list">
            <?php foreach ($quizzes as $q): ?>
                <li class="quiz-item">
                    <a href="quiz_details.php?id=<?php echo htmlspecialchars($q['id']); ?>" 
                       class="quiz-link <?php echo $q['id'] == $quizId ? 'active' : ''; ?>">
                        <i class="fas fa-question"></i> <?php echo htmlspecialchars($q['title']); ?>
                    </a>
                    <div class="kebab-menu">
                        <button class="kebab-btn" onclick="toggleKebabMenu(this)">
                            <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="kebab-dropdown">
                            <button onclick="editQuiz(<?php echo htmlspecialchars($q['id']); ?>)">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button onclick="deleteQuiz(<?php echo htmlspecialchars($q['id']); ?>, '<?php echo htmlspecialchars(addslashes($q['title'])); ?>')">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
            <?php if (empty($quizzes)): ?>
                <li class="quiz-item">
                    <div class="quiz-link" style="color: var(--text-light); font-style: italic;">
                        <i class="fas fa-info-circle"></i> No quizzes yet
                    </div>
                </li>
            <?php endif; ?>
        </ul>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-poll"></i> Quiz Details</h1>
                <div>
                    <a href="create_quiz.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> New Quiz
                    </a>
                    <a href="teacher_dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert-message">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <div class="card">
                <h2><?php echo htmlspecialchars($quiz['title']); ?></h2>
                <?php if ($quiz['description']): ?>
                    <p style="margin-top: var(--space-sm); color: var(--text-medium);">
                        <?php echo htmlspecialchars($quiz['description']); ?>
                    </p>
                <?php endif; ?>
                
                <div style="margin-top: var(--space-md); display: flex; gap: var(--space-md);">
                    <div style="color: var(--text-light);">
                        <i class="fas fa-calendar-alt"></i> 
                        Created: <?php echo date('M j, Y', strtotime($quiz['created_at'])); ?>
                    </div>
                </div>
            </div>

            <h2 style="margin-bottom: var(--space-md);">
                <i class="fas fa-chalkboard-teacher"></i> Assigned Classes
            </h2>
            
            <?php if (count($classesWithQuiz) > 0): ?>
                <div class="classes-list">
                    <?php foreach ($classesWithQuiz as $class): ?>
                        <div class="class-card">
                            <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                            <div class="class-section">Section: <?php echo htmlspecialchars($class['section']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="card" style="text-align: center; padding: var(--space-xl);">
                    <i class="fas fa-info-circle" style="font-size: 2rem; color: var(--text-light); margin-bottom: var(--space-sm);"></i>
                    <h3>Not assigned to any classes</h3>
                    <p style="color: var(--text-light);">This quiz hasn't been assigned to any classes yet.</p>
                </div>
            <?php endif; ?>

            <h2 style="margin-bottom: var(--space-md);">
                <i class="fas fa-list-ol"></i> Questions
            </h2>
            
            <div class="questions-list">
                <?php foreach ($questions as $index => $question): ?>
                    <div class="question-item">
                        <h3>Question #<?php echo $index + 1; ?> (<?php echo $question['points']; ?> pts)</h3>
                        <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                        
                        <div class="options-list">
                            <?php 
                            $stmt = $pdo->prepare("SELECT * FROM quiz_options WHERE question_id = ?");
                            $stmt->execute([$question['id']]);
                            $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($options as $option): ?>
                                <div class="option-item <?php echo $option['is_correct'] ? 'correct-option' : ''; ?>">
                                    <div class="option-marker <?php echo $option['is_correct'] ? 'correct-marker' : ''; ?>">
                                        <?php echo $option['is_correct'] ? '✓' : chr(64 + $option['id'] - $options[0]['id'] + 1); ?>
                                    </div>
                                    <?php echo htmlspecialchars($option['option_text']); ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Toggle kebab menu visibility
        function toggleKebabMenu(button) {
            // Close all other open menus first
            document.querySelectorAll('.kebab-dropdown.show').forEach(menu => {
                if (menu !== button.nextElementSibling) {
                    menu.classList.remove('show');
                }
            });
            
            // Toggle current menu
            const dropdown = button.nextElementSibling;
            dropdown.classList.toggle('show');
            
            // Close when clicking outside
            const clickHandler = function(e) {
                if (!dropdown.contains(e.target) && e.target !== button) {
                    dropdown.classList.remove('show');
                    document.removeEventListener('click', clickHandler);
                }
            };
            
            document.addEventListener('click', clickHandler);
        }

        // Edit quiz function
        function editQuiz(quizId) {
            // Close the menu
            document.querySelectorAll('.kebab-dropdown.show').forEach(menu => {
                menu.classList.remove('show');
            });
            
            // Redirect to edit page
            window.location.href = `edit_quiz.php?id=${quizId}`;
        }

        // Delete quiz function
        function deleteQuiz(quizId, quizTitle) {
            // Close the menu
            document.querySelectorAll('.kebab-dropdown.show').forEach(menu => {
                menu.classList.remove('show');
            });
            
            if (confirm(`Are you sure you want to delete the quiz "${quizTitle}"? This action cannot be undone.`)) {
                // Show loading indicator
                document.querySelector('.loading-overlay').style.display = 'flex';
                
                // Send delete request
                fetch(`delete_quiz.php?id=${quizId}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Reload the page to reflect changes
                        window.location.reload();
                    } else {
                        alert('Error deleting quiz: ' + (data.message || 'Unknown error'));
                        document.querySelector('.loading-overlay').style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error deleting quiz');
                    document.querySelector('.loading-overlay').style.display = 'none';
                });
            }
        }
    </script>
</body>
</html>