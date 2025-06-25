<?php
session_start();
require 'db.php';
require 'includes/theme.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$resultId = $_GET['id'] ?? 0;
$studentId = $_SESSION['user_id'];

// Get result details
$result = [];
$quiz = [];
$answers = [];

try {
    // Get result info
    $stmt = $pdo->prepare("
        SELECT qr.*, q.title as quiz_title, q.description as quiz_description,
               u.first_name, u.last_name
        FROM quiz_results qr
        JOIN quizzes q ON qr.quiz_id = q.id
        JOIN users u ON qr.student_id = u.id
        WHERE qr.id = ? AND qr.student_id = ?
    ");
    $stmt->execute([$resultId, $studentId]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$result) {
        $_SESSION['error'] = 'Result not found or you don\'t have permission to view it';
        header('Location: student_dashboard.php');
        exit();
    }

    // Get quiz questions with correct answers
    $stmt = $pdo->prepare("
        SELECT qq.id, qq.question_text, qq.points,
               (SELECT id FROM quiz_options qo WHERE qo.question_id = qq.id AND qo.is_correct = 1) as correct_option_id
        FROM quiz_questions qq
        WHERE qq.quiz_id = ?
        ORDER BY qq.id
    ");
    $stmt->execute([$result['quiz_id']]);
    $questions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get student's answers
    $stmt = $pdo->prepare("
        SELECT qa.question_id, qa.option_id, qa.is_correct,
               qo.option_text, qo.is_correct as option_is_correct
        FROM quiz_answers qa
        JOIN quiz_options qo ON qa.option_id = qo.id
        WHERE qa.result_id = ?
    ");
    $stmt->execute([$resultId]);
    $answers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize answers by question for easier access
    $answersByQuestion = [];
    foreach ($answers as $answer) {
        $answersByQuestion[$answer['question_id']] = $answer;
    }

} catch (PDOException $e) {
    error_log("Error fetching quiz result: " . $e->getMessage());
    $_SESSION['error'] = 'Error loading quiz result';
    header('Location: student_dashboard.php');
    exit();
}

// Calculate percentage
$percentage = round(($result['score'] / $result['total_questions']) * 100);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Result - LearnMate</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/theme.css">
    <style>
        /* Reuse styles from previous pages with additions */
        .result-header {
            background-color: var(--bg-white);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            padding: var(--space-xl);
            margin-bottom: var(--space-xl);
            text-align: center;
        }

        .result-title {
            font-size: 1.8rem;
            margin-bottom: var(--space-sm);
            color: var(--primary);
        }

        .result-score {
            font-size: 3rem;
            font-weight: 600;
            margin: var(--space-lg) 0;
        }

        .high-score {
            color: var(--success);
        }

        .medium-score {
            color: var(--warning);
        }

        .low-score {
            color: var(--danger);
        }

        .result-meta {
            display: flex;
            justify-content: center;
            gap: var(--space-xl);
            margin-top: var(--space-lg);
        }

        .meta-item {
            text-align: center;
        }

        .meta-value {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--primary);
        }

        .meta-label {
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .feedback {
            margin: var(--space-xl) 0;
            padding: var(--space-md);
            border-radius: var(--radius-md);
            background-color: var(--bg-light);
            text-align: center;
            font-style: italic;
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

        .question-header {
            margin-bottom: var(--space-md);
            display: flex;
            justify-content: space-between;
        }

        .question-text {
            font-weight: 500;
        }

        .question-points {
            color: var(--text-light);
        }

        .options-list {
            margin-top: var(--space-md);
        }

        .option-item {
            padding: var(--space-sm);
            margin-bottom: var(--space-xs);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
        }

        .correct-option {
            background-color: rgba(50, 213, 131, 0.1);
            border-left: 3px solid var(--success);
        }

        .incorrect-option {
            background-color: rgba(249, 112, 102, 0.1);
            border-left: 3px solid var(--danger);
        }

        .selected-option {
            background-color: rgba(253, 176, 34, 0.1);
            border-left: 3px solid var(--warning);
        }

        .option-marker {
            width: 24px;
            height: 24px;
            border-radius: 50%;
            background-color: var(--border-light);
            margin-right: var(--space-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
        }

        .correct-marker {
            background-color: var(--success);
            color: white;
        }

        .incorrect-marker {
            background-color: var(--danger);
            color: white;
        }

        .selected-marker {
            background-color: var(--warning);
            color: white;
        }

        .option-text {
            flex: 1;
        }

        .explanation {
            margin-top: var(--space-sm);
            padding: var(--space-sm);
            background-color: var(--bg-light);
            border-radius: var(--radius-md);
            font-size: 0.9rem;
        }

        .actions {
            margin-top: var(--space-xl);
            text-align: center;
        }

        .btn-retake {
            background-color: var(--primary);
            color: white;
            padding: var(--space-md) var(--space-xl);
            border-radius: var(--radius-md);
            text-decoration: none;
            display: inline-block;
        }

        .btn-retake:hover {
            background-color: var(--primary-dark);
        }
    </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
    <div class="container">
        <div class="result-header">
            <h1 class="result-title"><?php echo htmlspecialchars($result['quiz_title']); ?></h1>
            <?php if ($result['quiz_description']): ?>
                <p><?php echo htmlspecialchars($result['quiz_description']); ?></p>
            <?php endif; ?>
            
            <div class="result-score <?php 
                if ($percentage >= 70) echo 'high-score';
                elseif ($percentage >= 40) echo 'medium-score';
                else echo 'low-score';
            ?>">
                <?php echo $percentage; ?>%
            </div>
            
            <div class="feedback">
                <?php
                if ($percentage >= 90) {
                    echo "Excellent work! You've mastered this material.";
                } elseif ($percentage >= 70) {
                    echo "Good job! You have a solid understanding of this topic.";
                } elseif ($percentage >= 50) {
                    echo "Not bad! Review the material to improve your understanding.";
                } else {
                    echo "Keep practicing! Review the material and try again.";
                }
                ?>
            </div>
            
            <div class="result-meta">
                <div class="meta-item">
                    <div class="meta-value"><?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?></div>
                    <div class="meta-label">Correct Answers</div>
                </div>
                <div class="meta-item">
                    <div class="meta-value"><?php echo date('M j, Y', strtotime($result['completed_at'])); ?></div>
                    <div class="meta-label">Date Completed</div>
                </div>
            </div>
        </div>

        <h2 style="margin-bottom: var(--space-md);">
            <i class="fas fa-question-circle"></i> Question Review
        </h2>
        
        <div class="questions-list">
            <?php foreach ($questions as $index => $question): 
                $studentAnswer = $answersByQuestion[$question['id']] ?? null;
                $isCorrect = $studentAnswer && $studentAnswer['is_correct'];
            ?>
                <div class="question-item">
                    <div class="question-header">
                        <div class="question-text">Question #<?php echo $index + 1; ?></div>
                        <div class="question-points">
                            <?php if ($isCorrect): ?>
                                <span style="color: var(--success);">+<?php echo $question['points']; ?> pts</span>
                            <?php else: ?>
                                <span style="color: var(--danger);">+0 pts</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <p><?php echo htmlspecialchars($question['question_text']); ?></p>
                    
                    <div class="options-list">
                        <?php 
                        $stmt = $pdo->prepare("SELECT * FROM quiz_options WHERE question_id = ? ORDER BY id");
                        $stmt->execute([$question['id']]);
                        $options = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        foreach ($options as $option): 
                            $isSelected = $studentAnswer && $studentAnswer['option_id'] == $option['id'];
                            $isRightAnswer = $option['id'] == $question['correct_option_id'];
                        ?>
                            <div class="option-item <?php 
                                if ($isRightAnswer) echo 'correct-option';
                                elseif ($isSelected && !$isRightAnswer) echo 'incorrect-option';
                                elseif ($isSelected) echo 'selected-option';
                            ?>">
                                <div class="option-marker <?php 
                                    if ($isRightAnswer) echo 'correct-marker';
                                    elseif ($isSelected && !$isRightAnswer) echo 'incorrect-marker';
                                    elseif ($isSelected) echo 'selected-marker';
                                ?>">
                                    <?php 
                                    if ($isRightAnswer) echo '✓';
                                    elseif ($isSelected && !$isRightAnswer) echo '✗';
                                    else echo chr(64 + $option['id'] - $options[0]['id'] + 1);
                                    ?>
                                </div>
                                <div class="option-text">
                                    <?php echo htmlspecialchars($option['option_text']); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!$isCorrect && $studentAnswer): ?>
                        <div class="explanation">
                            <strong>Explanation:</strong> The correct answer is marked with a checkmark (✓).
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
        <div class="actions">
            <a href="student_dashboard.php" class="btn-retake">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>
</body>
</html>