<?php
// save_flashcard.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
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
    echo json_encode(['error' => 'Database connection failed']);
    exit();
}

// Get POST data
$pdf_file_id = isset($_POST['pdf_file_id']) ? (int)$_POST['pdf_file_id'] : 0;
$page_number = isset($_POST['page_number']) ? (int)$_POST['page_number'] : 1;
$term_text = isset($_POST['term_text']) ? $_POST['term_text'] : '';
$definition_text = isset($_POST['definition_text']) ? $_POST['definition_text'] : '';
$position_data = isset($_POST['position_data']) ? $_POST['position_data'] : '';

// Verify the PDF belongs to the current user
$checkStmt = $conn->prepare("SELECT id FROM pdf_files WHERE id = ? AND user_id = ?");
$checkStmt->bind_param("ii", $pdf_file_id, $_SESSION['user_id']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied to this PDF']);
    exit();
}

$checkStmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // Insert term
    $stmt = $conn->prepare("INSERT INTO terms (term_text) VALUES (?)");
    $stmt->bind_param("s", $term_text);
    $stmt->execute();
    $term_id = $conn->insert_id;
    
    // Insert definition
    $stmt = $conn->prepare("INSERT INTO definitions (definition_text) VALUES (?)");
    $stmt->bind_param("s", $definition_text);
    $stmt->execute();
    $definition_id = $conn->insert_id;
    
    // Create term-definition relationship
    $stmt = $conn->prepare("INSERT INTO term_definitions (term_id, definition_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $term_id, $definition_id);
    $stmt->execute();
    
    // Insert flashcard
    $stmt = $conn->prepare("INSERT INTO flashcards (term_id, definition_id, front_content, back_content, created_at) VALUES (?, ?, ?, ?, NOW())");
    $stmt->bind_param("iisss", $term_id, $definition_id, $term_text, $definition_text);
    $stmt->execute();
    $flashcard_id = $conn->insert_id;
    
    // Insert term highlight
    $stmt = $conn->prepare("INSERT INTO highlights (pdf_file_id, page_number, content_type, content_text, content_id, position_data) VALUES (?, ?, 'term', ?, ?, ?)");
    $stmt->bind_param("iisis", $pdf_file_id, $page_number, $term_text, $term_id, $position_data);
    $stmt->execute();
    
    // Insert definition highlight
    $stmt = $conn->prepare("INSERT INTO highlights (pdf_file_id, page_number, content_type, content_text, content_id, position_data) VALUES (?, ?, 'definition', ?, ?, ?)");
    $stmt->bind_param("iisis", $pdf_file_id, $page_number, $definition_text, $definition_id, $position_data);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Flashcard created successfully',
        'flashcard_id' => $flashcard_id,
        'term_id' => $term_id,
        'definition_id' => $definition_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['error' => 'Failed to create flashcard: ' . $e->getMessage()]);
}

$conn->close();
?>