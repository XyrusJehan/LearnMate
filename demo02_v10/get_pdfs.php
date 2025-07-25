<?php
// get_pdfs.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

// Database configuration
$db_host = 'switchyard.proxy.rlwy.net';
$db_user = 'root';
$db_pass = 'mfwZMSewsBKfBJQOdeOmyqMZoRGwewMI';
$db_name = 'railway';
$db_port = '47909';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name,$db_port);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get PDFs for the current user only
$stmt = $conn->prepare("SELECT id, original_filename, file_size, upload_date FROM pdf_files WHERE user_id = ? ORDER BY upload_date DESC");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();

$pdfs = [];
while ($row = $result->fetch_assoc()) {
    $pdfs[] = [
        'id' => $row['id'],
        'original_filename' => $row['original_filename'],
        'file_size' => $row['file_size'],
        'upload_date' => $row['upload_date']
    ];
}

$stmt->close();
$conn->close();

// Return JSON response
header('Content-Type: application/json');
echo json_encode($pdfs);
?> 