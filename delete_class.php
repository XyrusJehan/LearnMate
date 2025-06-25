<?php
session_start();
require 'db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit();
}

if ($_SESSION['role'] !== 'teacher') {
    header('HTTP/1.1 403 Forbidden');
    echo json_encode(['success' => false, 'message' => 'Only teachers can delete classes']);
    exit();
}

// Get the raw POST data
$data = json_decode(file_get_contents('php://input'), true);
$classId = $data['class_id'] ?? null;

if (!$classId || !is_numeric($classId)) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode(['success' => false, 'message' => 'Invalid class ID']);
    exit();
}

try {
    $pdo->beginTransaction();

    // 1. Verify the class belongs to this teacher
    $stmt = $pdo->prepare("SELECT id, teacher_id FROM classes WHERE id = ?");
    $stmt->execute([$classId]);
    $class = $stmt->fetch();

    if (!$class) {
        header('HTTP/1.1 404 Not Found');
        echo json_encode(['success' => false, 'message' => 'Class not found']);
        exit();
    }

    if ($class['teacher_id'] != $_SESSION['user_id']) {
        header('HTTP/1.1 403 Forbidden');
        echo json_encode(['success' => false, 'message' => 'You can only delete your own classes']);
        exit();
    }

    // 2. Delete related records in the correct order
    
    // Delete class students
    $stmt = $pdo->prepare("DELETE FROM class_students WHERE class_id = ?");
    $stmt->execute([$classId]);

    // Delete activities related to this class
    $stmt = $pdo->prepare("DELETE FROM activities WHERE group_id = ?");
    $stmt->execute([$classId]);

    // Delete class PDFs if the table exists
    try {
        $stmt = $pdo->prepare("DELETE FROM class_pdfs WHERE class_id = ?");
        $stmt->execute([$classId]);
    } catch (PDOException $e) {
        // Table might not exist, ignore
    }

    // Delete modules if the table exists
    try {
        $stmt = $pdo->prepare("DELETE FROM modules WHERE class_id = ?");
        $stmt->execute([$classId]);
    } catch (PDOException $e) {
        // Table might not exist, ignore
    }

    // Delete announcements related to this class if tables exist
    try {
        // First get announcement IDs
        $stmt = $pdo->prepare("SELECT announcement_id FROM announcement_classes WHERE class_id = ?");
        $stmt->execute([$classId]);
        $announcementIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        if (!empty($announcementIds)) {
            // Delete from announcement_students
            $placeholders = implode(',', array_fill(0, count($announcementIds), '?'));
            $stmt = $pdo->prepare("DELETE FROM announcement_students WHERE announcement_id IN ($placeholders)");
            $stmt->execute($announcementIds);

            // Delete from announcement_classes
            $stmt = $pdo->prepare("DELETE FROM announcement_classes WHERE class_id = ?");
            $stmt->execute([$classId]);

            // Delete from announcements
            $stmt = $pdo->prepare("DELETE FROM announcements WHERE id IN ($placeholders)");
            $stmt->execute($announcementIds);
        }
    } catch (PDOException $e) {
        // Tables might not exist, ignore
    }

    // 3. Finally, delete the class itself
    $stmt = $pdo->prepare("DELETE FROM classes WHERE id = ?");
    $stmt->execute([$classId]);

    $pdo->commit();

    echo json_encode(['success' => true, 'message' => 'Class deleted successfully']);
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error deleting class: " . $e->getMessage());
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>