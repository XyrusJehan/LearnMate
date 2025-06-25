<?php
require 'db.php';
session_start();

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Not logged in or not authorized']);
    exit();
}

// Get the POST data
$data = json_decode(file_get_contents('php://input'), true);
$classId = isset($data['class_id']) ? (int)$data['class_id'] : 0;
$studentId = isset($data['student_id']) ? (int)$data['student_id'] : 0;

// Validate input
if ($classId <= 0 || $studentId <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid class or student ID']);
    exit();
}

try {
    // Verify the teacher owns the class
    $stmt = $pdo->prepare("SELECT teacher_id FROM classes WHERE id = ?");
    $stmt->execute([$classId]);
    $class = $stmt->fetch();
    
    if (!$class || $class['teacher_id'] != $_SESSION['user_id']) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'message' => 'You do not have permission to remove students from this class']);
        exit();
    }
    
    // Verify the student exists and is enrolled in the class
    $stmt = $pdo->prepare("SELECT cs.*, u.first_name, u.last_name FROM class_students cs 
                          JOIN users u ON cs.student_id = u.id 
                          WHERE cs.class_id = ? AND cs.student_id = ?");
    $stmt->execute([$classId, $studentId]);
    $enrollment = $stmt->fetch();
    
    if (!$enrollment) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['success' => false, 'message' => 'Student not found in class']);
        exit();
    }
    
    // Remove the student from the class
    $stmt = $pdo->prepare("DELETE FROM class_students WHERE class_id = ? AND student_id = ?");
    $stmt->execute([$classId, $studentId]);
    
    if ($stmt->rowCount() > 0) {
        // Log the removal for audit purposes (optional)
        $studentName = $enrollment['first_name'] . ' ' . $enrollment['last_name'];
        error_log("Student {$studentName} (ID: {$studentId}) removed from class ID: {$classId} by teacher ID: {$_SESSION['user_id']}");
        
        echo json_encode(['success' => true, 'message' => 'Student removed successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to remove student from class']);
    }
    
} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 