<?php
// leave_class.php
session_start();
require 'db.php';

// Check if user is logged in and is a student
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
    exit();
}

$studentId = $_SESSION['user_id'];
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['class_id'])) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Class ID is required']);
    exit();
}

$classId = $data['class_id'];

// Validate class ID
if ($classId <= 0) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid class ID']);
    exit();
}

try {
    // Check if the student is enrolled in the class
    $stmt = $pdo->prepare("SELECT * FROM class_students WHERE class_id = ? AND student_id = ?");
    $stmt->execute([$classId, $studentId]);
    
    if ($stmt->rowCount() === 0) {
        echo json_encode(['success' => false, 'message' => 'You are not enrolled in this class']);
        exit();
    }
    
    // Get class details for activity logging
    $stmt = $pdo->prepare("SELECT class_name FROM classes WHERE id = ?");
    $stmt->execute([$classId]);
    $class = $stmt->fetch();
    
    if (!$class) {
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        exit();
    }
    
    // Begin transaction
    $pdo->beginTransaction();
    
    // Remove student from the class
    $stmt = $pdo->prepare("DELETE FROM class_students WHERE class_id = ? AND student_id = ?");
    $stmt->execute([$classId, $studentId]);
    
    // Record activity - explicitly set group_id to NULL
    $stmt = $pdo->prepare("INSERT INTO activities (student_id, description, icon, group_id) VALUES (?, ?, ?, NULL)");
    $stmt->execute([
        $studentId,
        "Left class: " . $class['class_name'],
        "sign-out-alt"
    ]);
    
    // Commit transaction
    $pdo->commit();
    
    echo json_encode(['success' => true, 'message' => 'Successfully left the class']);
    
} catch (PDOException $e) {
    // Rollback transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 