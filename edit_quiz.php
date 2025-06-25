<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

require 'db.php';
require 'includes/theme.php';

$quizId = $_GET['id'] ?? 0;
$teacherId = $_SESSION['user_id'];

// Fetch quiz details
$quiz = [];
$questions = [];
$classes = [];
$assignedClassIds = [];

try {
    // Get quiz info
    $stmt = $pdo->prepare("SELECT * FROM quizzes WHERE id = ? AND created_by = ?");
    $stmt->execute([$quizId, $teacherId]);
    $quiz = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quiz) {
        $_SESSION['error'] = 'Quiz not found or you do not have permission to edit it.';
        header('Location: create_quiz.php');
        exit();
    }

    // Get assigned class IDs
    $stmt = $pdo->prepare("SELECT class_id FROM quiz_classes WHERE quiz_id = ?");
    $stmt->execute([$quizId]);
    $assignedClassIds = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // Get quiz questions
    $stmt = $pdo->prepare("SELECT * FROM quiz_questions WHERE quiz_id = ? ORDER BY id");
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get options for each question
    foreach ($questions as &$question) {
        $stmt = $pdo->prepare("SELECT * FROM quiz_options WHERE question_id = ? ORDER BY id");
        $stmt->execute([$question['id']]);
        $question['options'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Find the correct option index
        foreach ($question['options'] as $index => $option) {
            if ($option['is_correct']) {
                $question['correct_option'] = $index + 1; // Use 1-based index for form value
                break;
            }
        }
    }
    unset($question);

    // Get teacher's classes
    $stmt = $pdo->prepare("SELECT id, class_name, section FROM classes WHERE teacher_id = ?");
    $stmt->execute([$teacherId]);
    $classes = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error fetching quiz data: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading quiz data. Please try again.';
    header('Location: create_quiz.php');
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quiz_title'])) {
    try {
        $pdo->beginTransaction();

        // Update quiz
        $quizTitle = $_POST['quiz_title'] ?? '';
        $quizDescription = $_POST['quiz_description'] ?? '';
        $classIds = $_POST['class_ids'] ?? [];

        $stmt = $pdo->prepare("UPDATE quizzes SET title = ?, description = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->execute([$quizTitle, $quizDescription, $quizId]);

        // Update class assignments: delete old, insert new
        $stmt = $pdo->prepare("DELETE FROM quiz_classes WHERE quiz_id = ?");
        $stmt->execute([$quizId]);

        if (!empty($classIds)) {
            foreach ($classIds as $classId) {
                $stmt = $pdo->prepare("INSERT INTO quiz_classes (quiz_id, class_id) VALUES (?, ?)");
                $stmt->execute([$quizId, $classId]);
            }
        }

        // Delete existing questions and options to recreate them
        $stmt = $pdo->prepare("DELETE FROM quiz_options WHERE question_id IN (SELECT id FROM quiz_questions WHERE quiz_id = ?)");
        $stmt->execute([$quizId]);

        $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
        $stmt->execute([$quizId]);

        // Insert new questions and options
        if (!empty($_POST['questions'])) {
            foreach ($_POST['questions'] as $questionData) {
                $questionText = $questionData['text'] ?? '';
                $points = isset($questionData['points']) ? (int)$questionData['points'] : 1;

                $stmt = $pdo->prepare("INSERT INTO quiz_questions (quiz_id, question_text, points) VALUES (?, ?, ?)");
                $stmt->execute([$quizId, $questionText, $points]);
                $questionId = $pdo->lastInsertId();

                if (!empty($questionData['options'])) {
                    foreach ($questionData['options'] as $optionIndex => $optionData) {
                        $optionText = $optionData['text'] ?? '';
                        $isCorrect = (isset($questionData['correct_option']) && $questionData['correct_option'] == $optionIndex) ? 1 : 0;

                        $stmt = $pdo->prepare("INSERT INTO quiz_options (question_id, option_text, is_correct) VALUES (?, ?, ?)");
                        $stmt->execute([$questionId, $optionText, $isCorrect]);
                    }
                }
            }
        }

        $pdo->commit();
        $_SESSION['success'] = 'Quiz updated successfully!';
        header("Location: quiz_details.php?id=$quizId");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = 'Error updating quiz: ' . $e->getMessage();
        header("Location: edit_quiz.php?id=$quizId");
        exit();
    }
}

// Get theme for the page
$theme = getCurrentTheme();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Quiz - LearnMate</title>
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
            --bg-light: #1A1A1A;
            --bg-white: #2A2A2A;
            --border-light: #333333;
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

        /* Sidebar, Main Content, etc. - All styles from create_quiz.php */
        .sidebar { width: 250px; background-color: var(--bg-white); box-shadow: 2px 0 5px rgba(0,0,0,0.1); padding: 20px; height: 100vh; position: fixed; overflow-y: auto; z-index: 1000; transition: background-color var(--transition), border-color var(--transition); }
        [data-theme="dark"] .sidebar { background-color: var(--bg-white); border-right: 1px solid var(--border-light); }
        .sidebar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid var(--border-light); }
        .sidebar-header h3 { font-size: 1.1rem; font-weight: 600; color: var(--text-dark); margin: 0; display: flex; align-items: center; gap: 8px; }
        .add-quiz-btn { background-color: var(--primary); color: var(--bg-white); border: none; border-radius: 50%; width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: var(--transition); }
        .add-quiz-btn:hover { background-color: var(--primary-light); transform: rotate(90deg); }
        .quiz-list { list-style: none; padding-left: 0; }
        .quiz-item { position: relative; display: flex; align-items: center; }
        .quiz-link { flex-grow: 1; display: block; padding: 10px 12px; border-radius: 6px; color: var(--text-dark); text-decoration: none; transition: var(--transition); display: flex; align-items: center; gap: 10px; font-size: 0.95rem; }
        .quiz-link:hover { background-color: rgba(67, 97, 238, 0.1); }
        .quiz-link.active { background-color: var(--primary); color: var(--bg-white); }
        .quiz-link i { font-size: 0.9rem; }
        .main-content { flex: 1; margin-left: 250px; padding: var(--space-xl); width: 100%; transition: margin-left var(--transition); }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-xl); flex-wrap: wrap; gap: var(--space-md); }
        .header h1 { color: var(--primary); font-size: 1.8rem; display: flex; align-items: center; gap: var(--space-sm); font-weight: 600; }
        .btn { padding: var(--space-sm) var(--space-md); border-radius: var(--radius-md); border: none; font-weight: 500; cursor: pointer; transition: var(--transition); display: inline-flex; align-items: center; gap: var(--space-sm); text-decoration: none; font-size: 0.95rem; }
        .btn-primary { background-color: var(--primary); color: white; box-shadow: var(--shadow-xs); }
        .btn-primary:hover { background-color: var(--primary-dark); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
        .btn-secondary { background-color: var(--bg-white); color: var(--primary); border: 1px solid var(--border-light); box-shadow: var(--shadow-xs); }
        .btn-secondary:hover { background-color: var(--bg-light); transform: translateY(-1px); box-shadow: var(--shadow-sm); }
        .btn-lg { padding: var(--space-md) var(--space-xl); font-size: 1rem; }
        .card { background-color: var(--bg-white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--space-xl); margin-bottom: var(--space-xl); transition: var(--transition); }
        .card:hover { box-shadow: var(--shadow-md); }
        .form-group { margin-bottom: var(--space-lg); }
        label { display: block; margin-bottom: var(--space-sm); font-weight: 600; color: var(--text-dark); font-size: 0.95rem; }
        input[type="text"], input[type="number"], textarea, select { width: 100%; padding: var(--space-sm) var(--space-md); border: 1px solid var(--border-light); border-radius: var(--radius-md); font-family: inherit; transition: var(--transition); background-color: var(--bg-white); color: var(--text-dark); font-size: 0.95rem; }
        input[type="text"]:focus, input[type="number"]:focus, textarea:focus, select:focus { outline: none; border-color: var(--primary); box-shadow: 0 0 0 3px rgba(127, 86, 217, 0.2); }
        textarea { min-height: 120px; resize: vertical; line-height: 1.6; }
        select { appearance: none; background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e"); background-repeat: no-repeat; background-position: right var(--space-md) center; background-size: 16px; padding-right: var(--space-xl); }
        .question-card { background-color: var(--bg-white); border-radius: var(--radius-lg); box-shadow: var(--shadow-sm); padding: var(--space-lg); margin-bottom: var(--space-lg); border-left: 4px solid var(--primary); transition: var(--transition); position: relative; }
        .question-card:hover { box-shadow: var(--shadow-md); }
        .question-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-md); }
        .question-title { font-weight: 600; color: var(--primary); font-size: 1.1rem; }
        .options-container { display: flex; flex-direction: column; gap: var(--space-sm); margin-bottom: var(--space-sm); }
        .option-item { display: flex; align-items: center; gap: var(--space-sm); padding: var(--space-sm); background-color: var(--bg-light); border-radius: var(--radius-md); transition: var(--transition); }
        .option-item:hover { background-color: var(--primary-extra-light); }
        .option-item input[type="text"] { flex: 1; background-color: transparent; border: 1px solid transparent; }
        .option-item input[type="text"]:focus { border-color: var(--border-light); background-color: var(--bg-white); }
        .option-item input[type="radio"] { margin-right: var(--space-sm); accent-color: var(--primary); width: 18px; height: 18px; cursor: pointer; }
        .add-btn { background-color: var(--success); color: white; padding: var(--space-xs) var(--space-sm); border-radius: var(--radius-md); border: none; cursor: pointer; display: flex; align-items: center; gap: var(--space-xs); margin-top: var(--space-sm); transition: var(--transition); }
        .add-btn:hover { background-color: #28b16d; transform: translateY(-1px); }
        .remove-btn { background-color: var(--danger); color: white; border: none; border-radius: var(--radius-md); width: 28px; height: 28px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: var(--transition); }
        .remove-btn:hover { background-color: #e05c5c; transform: scale(1.1); }
        .success-message { color: var(--success); background-color: rgba(50, 213, 131, 0.1); padding: var(--space-md); border-radius: var(--radius-md); margin-bottom: var(--space-lg); display: flex; align-items: center; gap: var(--space-sm); border-left: 4px solid var(--success); }
        .alert-message { color: var(--danger); background-color: rgba(249, 112, 102, 0.1); padding: var(--space-md); border-radius: var(--radius-md); margin-bottom: var(--space-lg); display: flex; align-items: center; gap: var(--space-sm); border-left: 4px solid var(--danger); }
        .loading-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0,0,0,0.5); display: flex; justify-content: center; align-items: center; z-index: 9999; display: none; }
        .spinner { width: 50px; height: 50px; border: 5px solid var(--primary-light); border-radius: 50%; border-top-color: var(--primary); animation: spin 1s ease-in-out infinite; }
        @keyframes spin { to { transform: rotate(360deg); } }
        .char-counter { font-size: 0.8rem; color: var(--text-light); text-align: right; margin-top: var(--space-xs); }
        .char-counter.warning { color: var(--warning); }
        .char-counter.error { color: var(--danger); }
        .kebab-menu { position: relative; margin-left: auto; }
        .kebab-btn { background: none; border: none; color: var(--text-medium); cursor: pointer; padding: 8px; border-radius: 50%; transition: var(--transition); display: flex; align-items: center; justify-content: center; width: 32px; height: 32px; }
        .kebab-btn:hover { background-color: rgba(0,0,0,0.05); color: var(--primary); }
        .kebab-dropdown { position: absolute; right: 0; top: 100%; background-color: var(--bg-white); border-radius: var(--radius-md); box-shadow: var(--shadow-md); padding: var(--space-xs) 0; min-width: 150px; z-index: 100; display: none; }
        .kebab-dropdown.show { display: block; }
        .kebab-dropdown button { width: 100%; padding: var(--space-sm) var(--space-md); text-align: left; background: none; border: none; color: var(--text-dark); cursor: pointer; display: flex; align-items: center; gap: var(--space-sm); font-size: 0.9rem; transition: var(--transition); }
        .kebab-dropdown button:hover { background-color: var(--primary-extra-light); color: var(--primary); }
        .kebab-dropdown button i { width: 18px; text-align: center; }
        @media (max-width: 768px) { .sidebar { width: 100%; height: auto; position: relative; padding: 15px; } .main-content { margin-left: 0; padding: var(--space-md); } .container { padding: 0; } .card, .question-card { padding: var(--space-lg); } .header { flex-direction: column; align-items: flex-start; } .btn-group { flex-direction: column; width: 100%; } .btn-group .btn { width: 100%; margin-bottom: var(--space-sm); } }
        @media (max-width: 480px) { .btn { width: 100%; justify-content: center; } .option-item { flex-wrap: wrap; } .option-item input[type="text"] { width: 100%; order: 2; } }
        ::-webkit-scrollbar { width: 8px; height: 8px; } ::-webkit-scrollbar-track { background: var(--bg-light); } ::-webkit-scrollbar-thumb { background: var(--primary-light); border-radius: var(--radius-full); } ::-webkit-scrollbar-thumb:hover { background: var(--primary); }
    </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
    <div class="loading-overlay"><div class="spinner"></div></div>

    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-edit"></i> Edit Quiz</h3>
        </div>
        <ul class="quiz-list">
             <li class="quiz-item">
                <a href="create_quiz.php" class="quiz-link">
                    <i class="fas fa-plus"></i> Create New Quiz
                </a>
            </li>
            <li class="quiz-item">
                <a href="teacher_dashboard.php" class="quiz-link">
                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                </a>
            </li>
        </ul>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-edit"></i> Edit Quiz: <?php echo htmlspecialchars($quiz['title']); ?></h1>
                <div>
                    <a href="quiz_details.php?id=<?php echo $quizId; ?>" class="btn btn-secondary">
                        <i class="fas fa-eye"></i> View Quiz
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="success-message">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert-message">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <form id="quizForm" method="POST">
                <div class="card">
                    <div class="form-group">
                        <label for="quiz_title"><i class="fas fa-heading"></i> Quiz Title</label>
                        <input type="text" id="quiz_title" name="quiz_title" 
                               value="<?php echo htmlspecialchars($quiz['title']); ?>" 
                               placeholder="Enter quiz title" required maxlength="100">
                        <div class="char-counter"><span id="titleCounter"><?php echo strlen($quiz['title']); ?></span>/100</div>
                    </div>

                    <div class="form-group">
                        <label for="quiz_description"><i class="fas fa-align-left"></i> Description (Optional)</label>
                        <textarea id="quiz_description" name="quiz_description" 
                                  placeholder="Enter a brief description of the quiz" maxlength="500"><?php echo htmlspecialchars($quiz['description']); ?></textarea>
                        <div class="char-counter"><span id="descCounter"><?php echo strlen($quiz['description'] ?? ''); ?></span>/500</div>
                    </div>

                    <div class="form-group">
                        <label><i class="fas fa-users"></i> Assign to Classes (Optional)</label>
                        <div class="classes-checkbox-container" style="margin-top: var(--space-sm);">
                            <?php if (!empty($classes)): ?>
                                <?php foreach ($classes as $class): ?>
                                    <div class="checkbox-item" style="margin-bottom: var(--space-sm);">
                                        <input type="checkbox" id="class_<?php echo htmlspecialchars($class['id']); ?>" 
                                               name="class_ids[]" value="<?php echo htmlspecialchars($class['id']); ?>"
                                               <?php echo in_array($class['id'], $assignedClassIds) ? 'checked' : ''; ?>
                                               style="margin-right: var(--space-sm);">
                                        <label for="class_<?php echo htmlspecialchars($class['id']); ?>" style="display: inline;">
                                            <?php echo htmlspecialchars($class['class_name'] . ' - ' . $class['section']); ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <p style="color: var(--text-light); font-style: italic;">You don't have any classes yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div id="questionsContainer">
                    <!-- Questions will be dynamically added here -->
                </div>

                <div class="btn-group" style="display: flex; gap: var(--space-md); margin-top: var(--space-lg);">
                    <button type="button" id="addQuestionBtn" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Question
                    </button>
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save"></i> Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const questionsContainer = document.getElementById('questionsContainer');
        const addQuestionBtn = document.getElementById('addQuestionBtn');
        const quizForm = document.getElementById('quizForm');
        const loadingOverlay = document.querySelector('.loading-overlay');
        let questionCount = 0;

        // Character counters
        const titleInput = document.getElementById('quiz_title');
        const descInput = document.getElementById('quiz_description');
        const titleCounter = document.getElementById('titleCounter');
        const descCounter = document.getElementById('descCounter');

        function updateCounters() {
            titleCounter.textContent = titleInput.value.length;
            descCounter.textContent = descInput.value.length;
        }
        titleInput.addEventListener('input', updateCounters);
        descInput.addEventListener('input', updateCounters);
        
        // Add question template
        window.addQuestion = function(data = {}, existingQuestionNumber = 0) {
            questionCount++;
            const questionId = `question_${questionCount}`;
            const questionNumber = existingQuestionNumber || questionCount;

            const questionHTML = `
                <div class="question-card" id="${questionId}">
                    <div class="question-header">
                        <div class="question-title">Question #${questionNumber}</div>
                        <button type="button" class="remove-btn" onclick="removeQuestion('${questionId}')" title="Remove question"><i class="fas fa-times"></i></button>
                    </div>
                    <div class="form-group">
                        <label for="${questionId}_text"><i class="fas fa-question"></i> Question Text</label>
                        <input type="text" id="${questionId}_text" name="questions[${questionCount}][text]" placeholder="Enter the question" required maxlength="200" value="${data.question_text || ''}">
                    </div>
                    <div class="form-group">
                        <label for="${questionId}_points"><i class="fas fa-star"></i> Points</label>
                        <input type="number" id="${questionId}_points" name="questions[${questionCount}][points]" min="1" value="${data.points || 1}" max="100">
                    </div>
                    <div class="form-group">
                        <label><i class="fas fa-list-ul"></i> Options (Select the correct answer)</label>
                        <div class="options-container" id="${questionId}_options"></div>
                        <button type="button" class="add-btn" onclick="addOption('${questionId}_options', ${questionCount})"><i class="fas fa-plus"></i> Add Option</button>
                    </div>
                </div>
            `;
            questionsContainer.insertAdjacentHTML('beforeend', questionHTML);

            // Add options
            const optionsContainerId = `${questionId}_options`;
            if (data.options && data.options.length > 0) {
                data.options.forEach((opt, i) => {
                    const isCorrect = (data.correct_option !== undefined && data.correct_option === i + 1);
                    addOption(optionsContainerId, questionCount, opt.text, isCorrect);
                });
            } else {
                for(let i=0; i<4; i++) {
                     addOption(optionsContainerId, questionCount);
                }
            }
        };

        // Add option template
        window.addOption = function(containerId, questionIndex, optionText = '', isCorrect = false) {
            const container = document.getElementById(containerId);
            const optionCount = container.children.length + 1;
            const optionHTML = `
                <div class="option-item">
                    <input type="radio" name="questions[${questionIndex}][correct_option]" value="${optionCount}" required ${isCorrect ? 'checked' : ''}>
                    <input type="text" name="questions[${questionIndex}][options][${optionCount}][text]" placeholder="Enter option text" required maxlength="200" value="${optionText}">
                    <button type="button" class="remove-btn" onclick="this.parentElement.remove()" title="Remove option"><i class="fas fa-times"></i></button>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', optionHTML);
        };
        
        // Remove question
        window.removeQuestion = function(questionId) {
            const questionElement = document.getElementById(questionId);
            if (questionElement && confirm('Are you sure you want to remove this question?')) {
                questionElement.remove();
                const questions = questionsContainer.querySelectorAll('.question-card');
                questions.forEach((q, i) => {
                    q.querySelector('.question-title').textContent = `Question #${i + 1}`;
                });
                questionCount = questions.length;
            }
        };

        // Add existing questions on page load
        const existingQuestions = <?php echo json_encode($questions); ?>;
        existingQuestions.forEach((q, i) => {
            const questionData = {
                question_text: q.question_text,
                points: q.points,
                options: q.options.map(opt => ({ text: opt.option_text })),
                correct_option: q.correct_option
            };
            addQuestion(questionData, i + 1);
        });
        
        // Add new question on button click
        addQuestionBtn.addEventListener('click', () => addQuestion());

        // Form validation before submission
        quizForm.addEventListener('submit', function(e) {
            // Basic validation, can be enhanced
            if (questionsContainer.children.length === 0) {
                alert('A quiz must have at least one question.');
                e.preventDefault();
                return;
            }
            loadingOverlay.style.display = 'flex';
        });
    });
    </script>
</body>
</html>
