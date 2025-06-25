<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit;
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['id']) || !isset($data['content'])) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

$announcement_id = $data['id'];
$content = trim($data['content']);

if (empty($content)) {
    echo json_encode(['success' => false, 'message' => 'Announcement content cannot be empty']);
    exit;
}

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
        echo json_encode(['success' => false, 'message' => 'You do not have permission to edit this announcement']);
        exit;
    }
    
    // Update the announcement
    $stmt = $conn->prepare("UPDATE announcements SET content = ? WHERE id = ?");
    $stmt->bind_param("si", $content, $announcement_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to update announcement']);
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?> 