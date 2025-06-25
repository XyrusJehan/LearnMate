<?php
// quiz_results.php
session_start();
require 'db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Get quiz ID from URL
$quizId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$studentId = $_SESSION['user_id'];

// Fetch quiz result
try {
    $stmt = $pdo->prepare("
        SELECT qr.*, q.title, q.description, c.class_name
        FROM quiz_results qr
        JOIN quizzes q ON qr.quiz_id = q.id
        LEFT JOIN quiz_classes qc ON q.id = qc.quiz_id
        LEFT JOIN classes c ON qc.class_id = c.id
        WHERE qr.quiz_id = ? AND qr.student_id = ?
    ");
    $stmt->execute([$quizId, $studentId]);
    $result = $stmt->fetch();

    if (!$result) {
        $_SESSION['error'] = "Quiz result not found";
        header('Location: student_dashboard.php');
        exit();
    }

    // Fetch questions with correct answers and student's answers
    $stmt = $pdo->prepare("
        SELECT qq.id, qq.question_text, 
               GROUP_CONCAT(qo.id) AS option_ids,
               GROUP_CONCAT(qo.option_text) AS option_texts,
               GROUP_CONCAT(qo.is_correct) AS option_correct,
               qa.option_id AS selected_option_id
        FROM quiz_questions qq
        LEFT JOIN quiz_options qo ON qo.question_id = qq.id
        LEFT JOIN quiz_answers qa ON qa.question_id = qq.id AND qa.result_id = ?
        WHERE qq.quiz_id = ?
        GROUP BY qq.id
    ");
    $stmt->execute([$result['id'], $quizId]);
    $questions = $stmt->fetchAll();

    // Process questions data
    foreach ($questions as &$question) {
        $optionIds = explode(',', $question['option_ids']);
        $optionTexts = explode(',', $question['option_texts']);
        $optionCorrect = explode(',', $question['option_correct']);
        
        $question['options'] = [];
        for ($i = 0; $i < count($optionIds); $i++) {
            $question['options'][] = [
                'id' => $optionIds[$i],
                'text' => $optionTexts[$i],
                'is_correct' => $optionCorrect[$i]
            ];
        }
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Quiz Results - LearnMate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #7F56D9;
      --primary-light: #9E77ED;
      --primary-dark: #6941C6;
      --success: #32D583;
      --danger: #F97066;
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
    .header { margin-bottom: var(--space-lg); }
    .btn { background-color: var(--primary); color: white; padding: var(--space-sm) var(--space-md); border: none; border-radius: var(--radius-md); cursor: pointer; font-weight: 500; text-decoration: none; display: inline-block; }
    .btn:hover { background-color: var(--primary-dark); }
    .result-card { background-color: var(--bg-white); border-radius: var(--radius-md); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: var(--space-lg); margin-bottom: var(--space-lg); text-align: center; }
    .score { font-size: 48px; font-weight: 600; margin: var(--space-md) 0; }
    .score-text { font-size: 18px; color: var(--text-medium); }
    .quiz-title { font-size: 24px; font-weight: 600; margin-bottom: var(--space-sm); }
    .quiz-description { color: var(--text-medium); margin-bottom: var(--space-md); }
    .question { margin-bottom: var(--space-lg); padding-bottom: var(--space-lg); border-bottom: 1px solid var(--border-light); }
    .question:last-child { border-bottom: none; }
    .question-text { font-weight: 500; margin-bottom: var(--space-md); }
    .option { display: block; margin-bottom: var(--space-sm); padding: var(--space-sm); border-radius: var(--radius-md); }
    .correct { background-color: rgba(50, 213, 131, 0.1); border-left: 3px solid var(--success); }
    .incorrect { background-color: rgba(249, 112, 102, 0.1); border-left: 3px solid var(--danger); }
    .selected { font-weight: 600; }
    .feedback { margin-top: var(--space-sm); font-style: italic; }
  </style>
</head>
<body>
  <div class="container">
    <div class="header">
      <a href="student_dashboard.php" class="btn">
        <i class="fas fa-arrow-left"></i> Back to Dashboard
      </a>
    </div>
    
    <div class="result-card">
      <h2 class="quiz-title"><?php echo htmlspecialchars($result['title']); ?></h2>
      <p class="quiz-description"><?php echo htmlspecialchars($result['description']); ?></p>
      
      <?php if ($result['class_name']): ?>
        <p>Class: <?php echo htmlspecialchars($result['class_name']); ?></p>
      <?php endif; ?>
      
      <div class="score">
        <?php echo $result['score']; ?>/<?php echo $result['total_questions']; ?>
      </div>
      <div class="score-text">
        <?php 
          $percentage = ($result['score'] / $result['total_questions']) * 100;
          if ($percentage >= 80) {
              echo "Excellent work!";
          } elseif ($percentage >= 60) {
              echo "Good job!";
          } elseif ($percentage >= 40) {
              echo "Keep practicing!";
          } else {
              echo "Review the material and try again!";
          }
        ?>
      </div>
    </div>
    
    <div style="background-color: var(--bg-white); border-radius: var(--radius-md); box-shadow: 0 1px 3px rgba(0,0,0,0.1); padding: var(--space-lg);">
      <h3 style="margin-bottom: var(--space-lg);">Question Review</h3>
      
      <?php foreach ($questions as $index => $question): ?>
        <div class="question">
          <p class="question-text">
            <?php echo ($index + 1) . '. ' . htmlspecialchars($question['question_text']); ?>
          </p>
          
          <?php foreach ($question['options'] as $option): ?>
            <div class="option <?php echo $option['is_correct'] ? 'correct' : ''; ?> <?php echo $option['id'] == $question['selected_option_id'] && !$option['is_correct'] ? 'incorrect' : ''; ?> <?php echo $option['id'] == $question['selected_option_id'] ? 'selected' : ''; ?>">
              <?php echo htmlspecialchars($option['text']); ?>
              <?php if ($option['is_correct']): ?>
                <div class="feedback">Correct answer</div>
              <?php elseif ($option['id'] == $question['selected_option_id']): ?>
                <div class="feedback">Your answer</div>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>