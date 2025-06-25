<?php
// take_quiz.php
session_start();
require 'db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id']) {
    header('Location: index.php');
    exit();
}

// Get quiz ID from URL
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$studentId = $_SESSION['user_id'];

// Fetch quiz details
try {
    $stmt = $pdo->prepare("
        SELECT q.*, c.class_name 
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
    if ($quiz['class_id']) {
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

    // Fetch quiz questions with options
    $stmt = $pdo->prepare("
        SELECT qq.id, qq.question_text, qq.points
        FROM quiz_questions qq
        WHERE qq.quiz_id = ?
        ORDER BY qq.id
    ");
    $stmt->execute([$quizId]);
    $questions = $stmt->fetchAll();

    // Fetch options for each question
    foreach ($questions as &$question) {
        $stmt = $pdo->prepare("
            SELECT id, option_text 
            FROM quiz_options 
            WHERE question_id = ?
            ORDER BY id
        ");
        $stmt->execute([$question['id']]);
        $question['options'] = $stmt->fetchAll();
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
        
        // Record each answer and check correctness
        foreach ($questions as $question) {
            $questionId = $question['id'];
            $selectedOptionId = $_POST['question_' . $questionId] ?? null;
            
            if ($selectedOptionId) {
                // Check if selected option is correct
                $stmt = $pdo->prepare("
                    SELECT is_correct 
                    FROM quiz_options 
                    WHERE id = ?
                ");
                $stmt->execute([$selectedOptionId]);
                $isCorrect = $stmt->fetch()['is_correct'];
                
                // Record the answer
                $stmt = $pdo->prepare("
                    INSERT INTO quiz_answers (question_id, option_id, is_correct)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$questionId, $selectedOptionId, $isCorrect]);
                
                if ($isCorrect) {
                    $score += $question['points'];
                }
            }
        }
        
        // Record quiz result
        $stmt = $pdo->prepare("
            INSERT INTO quiz_results (quiz_id, student_id, score, total_questions)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$quizId, $studentId, $score, $totalQuestions]);
        $resultId = $pdo->lastInsertId();
        
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
        
        <?php foreach ($questions as $index => $question): ?>
          <div class="question">
            <p class="question-text">
              <?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?>
              <span style="color: var(--text-light); font-size: 14px;">(<?php echo $question['points']; ?> point<?php echo $question['points'] > 1 ? 's' : ''; ?>)</span>
            </p>
            
            <?php foreach ($question['options'] as $option): ?>
              <label class="option">
                <input type="radio" name="question_<?php echo $question['id']; ?>" value="<?php echo $option['id']; ?>" required>
                <?php echo htmlspecialchars($option['option_text']); ?>
              </label>
            <?php endforeach; ?>
          </div>
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