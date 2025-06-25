<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id'])) {  // Fixed missing parenthesis here
    header('HTTP/1.1 401 Unauthorized');
    die(json_encode(['success' => false, 'message' => 'Unauthorized']));
}

if ($_SESSION['role'] !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    die(json_encode(['success' => false, 'message' => 'Forbidden']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    header('HTTP/1.1 405 Method Not Allowed');
    die(json_encode(['success' => false, 'message' => 'Method Not Allowed']));
}

if (!isset($_GET['id'])) {
    header('HTTP/1.1 400 Bad Request');
    die(json_encode(['success' => false, 'message' => 'Quiz ID required']));
}

$quizId = (int)$_GET['id'];
$teacherId = $_SESSION['user_id'];

try {
    // Verify the quiz belongs to the teacher
    $stmt = $pdo->prepare("SELECT id FROM quizzes WHERE id = ? AND created_by = ?");
    $stmt->execute([$quizId, $teacherId]);
    $quiz = $stmt->fetch();
    
    if (!$quiz) {
        header('HTTP/1.1 404 Not Found');
        die(json_encode(['success' => false, 'message' => 'Quiz not found or not owned by you']));
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Delete quiz options and questions first due to foreign key constraints
    $stmt = $pdo->prepare("DELETE qo FROM quiz_options qo 
                          JOIN quiz_questions qq ON qo.question_id = qq.id 
                          WHERE qq.quiz_id = ?");
    $stmt->execute([$quizId]);
    
    // Delete quiz questions
    $stmt = $pdo->prepare("DELETE FROM quiz_questions WHERE quiz_id = ?");
    $stmt->execute([$quizId]);
    
    // Delete quiz
    $stmt = $pdo->prepare("DELETE FROM quizzes WHERE id = ?");
    $stmt->execute([$quizId]);
    
    $pdo->commit();
    
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error deleting quiz: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    die(json_encode(['success' => false, 'message' => 'Database error']));
}
?>