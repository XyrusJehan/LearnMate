<?php
// get_highlights.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'learnmate';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get highlights for a specific PDF file and page
if (isset($_GET['pdf_file_id']) && isset($_GET['page_number'])) {
    $pdfId = (int)$_GET['pdf_file_id'];
    $pageNumber = (int)$_GET['page_number'];
    
    // Verify the PDF exists
    $checkStmt = $conn->prepare("SELECT id FROM pdf_files WHERE id = ?");
    $checkStmt->bind_param("i", $pdfId);
    $checkStmt->execute();
    $checkResult = $checkStmt->get_result();
    
    if ($checkResult->num_rows === 0) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'PDF file not found']);
        exit();
    }
    $checkStmt->close();
    
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
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'highlights' => $highlights
    ]);
} else {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
}

$conn->close();
?>