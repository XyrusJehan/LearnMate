<?php
// get_highlights.php
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
$db_name = 'learnmate';
$db_port = '47909';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name,$db_port);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get highlights for a specific PDF file and page (only if PDF belongs to current user)
if (isset($_GET['pdf_id']) && isset($_GET['page'])) {
    $pdfId = (int)$_GET['pdf_id'];
    $pageNumber = (int)$_GET['page'];
    
    // First verify the PDF belongs to the current user
    $checkStmt = $conn->prepare("SELECT id FROM pdf_files WHERE id = ? AND user_id = ?");
    $checkStmt->bind_param("ii", $pdfId, $_SESSION['user_id']);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Access denied to this PDF']);
        exit();
    }
    
    // Get highlights for the PDF and page
    $stmt = $conn->prepare("SELECT * FROM highlights WHERE pdf_file_id = ? AND page_number = ?");
    $stmt->bind_param("ii", $pdfId, $pageNumber);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $highlights = [];
    while ($row = $result->fetch_assoc()) {
        $highlights[] = $row;
    }
    
    $stmt->close();
    $checkStmt->close();
    
    header('Content-Type: application/json');
    echo json_encode($highlights);
} else {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameters']);
}

$conn->close();
?>