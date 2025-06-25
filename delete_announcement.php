<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id'])) {
    echo json_encode(['success' => false, 'message' => 'Missing announcement ID']);
    exit;
}

$announcement_id = $data['id'];

try {
    // First check if the announcement belongs to the current user
    $stmt = $conn->prepare("SELECT user_id FROM announcements WHERE id = ?");
    $stmt->bind_param("i", $announcement_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        exit;
    }
    
    $announcement = $result->fetch_assoc();
    if ($announcement['user_id'] != $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'You do not have permission to delete this announcement']);
        exit;
    }
    
    // Begin transaction
    $conn->begin_transaction();
    
    try {
        // Delete from announcement_classes first (due to foreign key constraint)
        $stmt = $conn->prepare("DELETE FROM announcement_classes WHERE announcement_id = ?");
        $stmt->bind_param("i", $announcement_id);
        $stmt->execute();
        
        // Then delete the announcement
        $stmt = $conn->prepare("DELETE FROM announcements WHERE id = ?");
        $stmt->bind_param("i", $announcement_id);
        
        if ($stmt->execute()) {
            $conn->commit();
            echo json_encode(['success' => true]);
        } else {
            throw new Exception('Failed to delete announcement');
        }
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 