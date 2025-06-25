<?php
session_start();
require 'db.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    exit('Unauthorized');
}

// Get JSON data
$data = json_decode(file_get_contents('php://input'), true);

if (!isset($data['report_id'])) {
    http_response_code(400);
    exit('Missing report ID');
}

$reportId = intval($data['report_id']);

try {
    $stmt = $pdo->prepare('DELETE FROM reports WHERE id = ?');
    $stmt->execute([$reportId]);
    
    if ($stmt->rowCount() > 0) {
        http_response_code(200);
        exit('Report deleted successfully');
    } else {
        http_response_code(404);
        exit('Report not found');
    }
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database error: ' . $e->getMessage());
} 