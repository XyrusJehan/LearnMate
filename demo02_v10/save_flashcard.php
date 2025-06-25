<?php
// save_flashcard.php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
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
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit();
}

// Get POST data
$pdf_file_id = isset($_POST['pdf_file_id']) ? (int)$_POST['pdf_file_id'] : 0;
$page_number = isset($_POST['page_number']) ? (int)$_POST['page_number'] : 1;
$term = isset($_POST['term']) ? $_POST['term'] : '';
$definition = isset($_POST['definition']) ? $_POST['definition'] : '';
$folder_id = isset($_POST['folder_id']) ? (int)$_POST['folder_id'] : 0;
$position_data = isset($_POST['position_data']) ? $_POST['position_data'] : '';

$userId = $_SESSION['user_id'];

// Verify the PDF exists
$checkStmt = $conn->prepare("SELECT id FROM pdf_files WHERE id = ?");
$checkStmt->bind_param("i", $pdf_file_id);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'PDF file not found']);
    exit();
}
$checkStmt->close();

// Verify folder belongs to user
$folderStmt = $conn->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
$folderStmt->bind_param("ii", $folder_id, $userId);
$folderStmt->execute();
$folderResult = $folderStmt->get_result();

if ($folderResult->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Access denied to this folder']);
    exit();
}
$folderStmt->close();

// Start transaction
$conn->begin_transaction();

try {
    // Insert term
    $stmt = $conn->prepare("INSERT INTO terms (term_text) VALUES (?)");
    $stmt->bind_param("s", $term);
    $stmt->execute();
    $term_id = $conn->insert_id;
    
    // Insert definition
    $stmt = $conn->prepare("INSERT INTO definitions (definition_text) VALUES (?)");
    $stmt->bind_param("s", $definition);
    $stmt->execute();
    $definition_id = $conn->insert_id;
    
    // Create term-definition relationship
    $stmt = $conn->prepare("INSERT INTO term_definitions (term_id, definition_id) VALUES (?, ?)");
    $stmt->bind_param("ii", $term_id, $definition_id);
    $stmt->execute();
    
    // Insert flashcard
    $stmt = $conn->prepare("INSERT INTO flashcards (folder_id, term_id, definition_id, front_content, back_content, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("iiiss", $folder_id, $term_id, $definition_id, $term, $definition);
    $stmt->execute();
    $flashcard_id = $conn->insert_id;
    
    // Insert term highlight
    $stmt = $conn->prepare("INSERT INTO highlights (pdf_file_id, page_number, content_type, content_text, content_id, position_data) VALUES (?, ?, 'term', ?, ?, ?)");
    $stmt->bind_param("iisis", $pdf_file_id, $page_number, $term, $term_id, $position_data);
    $stmt->execute();
    
    // Insert definition highlight
    $stmt = $conn->prepare("INSERT INTO highlights (pdf_file_id, page_number, content_type, content_text, content_id, position_data) VALUES (?, ?, 'definition', ?, ?, ?)");
    $stmt->bind_param("iisis", $pdf_file_id, $page_number, $definition, $definition_id, $position_data);
    $stmt->execute();
    
    $conn->commit();
    
    echo json_encode([
        'success' => true,
        'message' => 'Flashcard saved successfully',
        'flashcard_id' => $flashcard_id
    ]);
    
} catch (Exception $e) {
    $conn->rollback();
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save flashcard: ' . $e->getMessage()]);
}

$conn->close();
?>