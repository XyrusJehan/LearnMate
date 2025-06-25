<?php
// take_quiz.php
session_start();
require 'db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get quiz ID from URL
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$studentId = $_SESSION['user_id'];

// Fetch quiz details
try {
    $stmt = $pdo->prepare("
        SELECT q.*, c.class_name, qc.class_id 
        FROM quizzes q
        LEFT JOIN quiz_classes qc ON q.id = qc.quiz_id
        LEFT JOIN classes c ON qc.class_id = c.id
        WHERE q.id = ?
    ");
    $stmt->execute([$quizId]);
    $quiz = $stmt->fetch();

    if (!$quiz) {
        $_SESSION['error'] = "Quiz not found";
        header('Location: student_dashboard.php');
        exit();
    }

    // Check if student is enrolled in the class (if quiz is assigned to a class)
    if (isset($quiz['class_id']) && $quiz['class_id']) {
        $stmt = $pdo->prepare("
            SELECT 1 FROM class_students 
            WHERE class_id = ? AND student_id = ?
        ");
        $stmt->execute([$quiz['class_id'], $studentId]);
        if (!$stmt->fetch()) {
            $_SESSION['error'] = "You are not enrolled in this class";
            header('Location: student_dashboard.php');
            exit();
        }
    }

    // Check if student has already taken this quiz
    $stmt = $pdo->prepare("
        SELECT 1 FROM quiz_results 
        WHERE quiz_id = ? AND student_id = ?
    ");
    $stmt->execute([$quizId, $studentId]);
    if ($stmt->fetch()) {
        $_SESSION['error'] = "You have already taken this quiz";
        header("Location: quiz_results.php?id=$quizId");
        exit();
    }

    // Fetch all questions with their options in one query
    $stmt = $pdo->prepare("
        SELECT qq.id AS question_id, qq.question_text, qq.points,
               qo.id AS option_id, qo.option_text, qo.is_correct
        FROM quiz_questions qq
        LEFT JOIN quiz_options qo ON qo.question_id = qq.id
        WHERE qq.quiz_id = ?
        ORDER BY qq.id, qo.id
    ");
    $stmt->execute([$quizId]);
    $results = $stmt->fetchAll();

    // Organize questions and options
    $questions = [];
    foreach ($results as $row) {
        $questionId = $row['question_id'];
        
        if (!isset($questions[$questionId])) {
            $questions[$questionId] = [
                'id' => $questionId,
                'question_text' => $row['question_text'],
                'points' => $row['points'],
                'options' => []
            ];
        }
        
        if ($row['option_id']) {
            $questions[$questionId]['options'][] = [
                'id' => $row['option_id'],
                'option_text' => $row['option_text'],
                'is_correct' => $row['is_correct']
            ];
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $pdo->beginTransaction();

        // Calculate score
        $score = 0;
        $totalQuestions = count($questions);
        
        // Record quiz result FIRST to get the result_id
        $stmt = $pdo->prepare("
            INSERT INTO quiz_results (quiz_id, student_id, score, total_questions)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$quizId, $studentId, 0, $totalQuestions]); // Initialize with score=0
        $resultId = $pdo->lastInsertId();
        
        // Record each answer and calculate score
        foreach ($questions as $question) {
            $questionId = $question['id'];
            $selectedOptionId = $_POST['question_' . $questionId] ?? null;
            
            if ($selectedOptionId) {
                // Find the selected option
                $selectedOption = null;
                foreach ($question['options'] as $option) {
                    if ($option['id'] == $selectedOptionId) {
                        $selectedOption = $option;
                        break;
                    }
                }
                
                if ($selectedOption) {
                    // Record the answer with the correct result_id
                    $stmt = $pdo->prepare("
                        INSERT INTO quiz_answers (result_id, question_id, option_id, is_correct)
                        VALUES (?, ?, ?, ?)
                    ");
                    $stmt->execute([$resultId, $questionId, $selectedOptionId, $selectedOption['is_correct']]);
                    
                    if ($selectedOption['is_correct']) {
                        $score += $question['points'];
                    }
                }
            }
        }
        
        // Update the quiz result with the final score
        $stmt = $pdo->prepare("
            UPDATE quiz_results 
            SET score = ?
            WHERE id = ?
        ");
        $stmt->execute([$score, $resultId]);
        
        $pdo->commit();
        
        // Redirect to results page
        header("Location: quiz_results.php?id=$quizId");
        exit();
        
    } catch (PDOException $e) {
        $pdo->rollBack();
        die("Database error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Take Quiz - LearnMate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #7F56D9;
      --primary-light: #9E77ED;
      --primary-dark: #6941C6;
      --text-dark: #101828;
      --text-medium: #475467;
      --text-light: #98A2B3;
      --bg-light: #F9FAFB;
      --bg-white: #FFFFFF;
      --border-light: #EAECF0;
      --radius-md: 8px;
      --space-sm: 8px;
      --space-md: 16px;
      --space-lg: 24px;
    }
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', sans-serif; }
    body { background-color: var(--bg-light); color: var(--text-dark); line-height: 1.5; padding: var(--space-md); }
    .container { max-width: 800px; margin: 0 auto; }
    .header { display: flex; justify-content: space-between; align-items: center; margin-bottom: var(--space-lg); }
    .btn { background-color: var(--primary); color: white; padding: var(--space-sm) var(--space-md); border: none; border-radius: var(--radius-md); cursor: pointer; font-weight: 500; }
    .btn:hover { background-color: var(--primary-dark); }
    .quiz-card { background-color: var(--bg-white); border-radius: var(--radius-md); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: var(--space-lg); margin-bottom: var(--space-lg); }
    .quiz-title { font-size: 24px; font-weight: 600; margin-bottom: var(--space-sm); }
    .quiz-description { color: var(--text-medium); margin-bottom: var(--space-lg); }
    .question { margin-bottom: var(--space-lg); padding-bottom: var(--space-lg); border-bottom: 1px solid var(--border-light); }
    .question:last-child { border-bottom: none; }
    .question-text { font-weight: 500; margin-bottom: var(--space-md); }
    .option { display: block; margin-bottom: var(--space-sm); padding: var(--space-sm); border-radius: var(--radius-md); cursor: pointer; }
    .option:hover { background-color: var(--bg-light); }
    .option input { margin-right: var(--space-sm); }
    .time-remaining { background-color: var(--primary-light); color: white; padding: var(--space-sm) var(--space-md); border-radius: var(--radius-md); font-weight: 500; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <h1>Take Quiz</h1>
      <div class="time-remaining">
        <i class="fas fa-clock"></i> Time remaining: Unlimited
      </div>
    </div>
    
    <form method="POST">
      <div class="quiz-card">
        <h2 class="quiz-title"><?php echo htmlspecialchars($quiz['title']); ?></h2>
        <p class="quiz-description"><?php echo htmlspecialchars($quiz['description']); ?></p>
        
        <?php $questionNumber = 1; ?>
        <?php foreach ($questions as $question): ?>
          <div class="question">
            <p class="question-text">
              <?php echo $questionNumber . '. ' . htmlspecialchars($question['question_text']); ?>
              <span style="color: var(--text-light); font-size: 14px;">(<?php echo $question['points']; ?> point<?php echo $question['points'] > 1 ? 's' : ''; ?>)</span>
            </p>
            
            <?php foreach ($question['options'] as $option): ?>
              <label class="option">
                <input type="radio" name="question_<?php echo $question['id']; ?>" value="<?php echo $option['id']; ?>" required>
                <?php echo htmlspecialchars($option['option_text']); ?>
              </label>
            <?php endforeach; ?>
          </div>
          <?php $questionNumber++; ?>
        <?php endforeach; ?>
        
        <div style="text-align: center; margin-top: var(--space-lg);">
          <button type="submit" class="btn">
            <i class="fas fa-paper-plane"></i> Submit Quiz
          </button>
        </div>
      </div>
    </form>
  </div>
</body>
</html>