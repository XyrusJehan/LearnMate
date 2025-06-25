<?php
// save_highlight.php
// Handle saving highlights and creating terms/definitions

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

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
$pdfId = (int)$_POST['pdf_id'];
$pageNumber = (int)$_POST['page_number'];
$contentType = $_POST['content_type'];
$contentText = $conn->real_escape_string($_POST['content_text']);
$positionData = $conn->real_escape_string($_POST['position_data']);
$termText = isset($_POST['term_text']) ? $conn->real_escape_string($_POST['term_text']) : '';
$definitionText = isset($_POST['definition_text']) ? $conn->real_escape_string($_POST['definition_text']) : '';

// Verify the PDF belongs to the current user
$checkStmt = $conn->prepare("SELECT id FROM pdf_files WHERE id = ? AND user_id = ?");
$checkStmt->bind_param("ii", $pdfId, $_SESSION['user_id']);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows === 0) {
    http_response_code(403);
    echo json_encode(['error' => 'Access denied to this PDF']);
    exit();
}

$checkStmt->close();

// Validate input
if ($pdfId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid PDF ID']);
    exit();
}

if ($pageNumber <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid page number']);
    exit();
}

if (!in_array($contentType, ['term', 'definition'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid content type']);
    exit();
}

if (empty($contentText)) {
    http_response_code(400);
    echo json_encode(['error' => 'Content text cannot be empty']);
    exit();
}

// Start transaction
$conn->begin_transaction();

try {
    $contentId = null;
    $savedTerm = '';
    $savedDefinition = '';

    if ($contentType === 'term') {
        if (empty($termText)) {
            throw new Exception('Term text cannot be empty');
        }
        
        // Insert into terms table
        $stmt = $conn->prepare("INSERT INTO terms (term_text) VALUES (?)");
        $stmt->bind_param("s", $termText);
        if (!$stmt->execute()) {
            throw new Exception('Error saving term: ' . $stmt->error);
        }
        $contentId = $stmt->insert_id;
        $savedTerm = $termText;
        $stmt->close();
    } else {
        if (empty($definitionText)) {
            throw new Exception('Definition text cannot be empty');
        }
        
        // Insert into definitions table
        $stmt = $conn->prepare("INSERT INTO definitions (definition_text) VALUES (?)");
        $stmt->bind_param("s", $definitionText);
        if (!$stmt->execute()) {
            throw new Exception('Error saving definition: ' . $stmt->error);
        }
        $contentId = $stmt->insert_id;
        $savedDefinition = $definitionText;
        $stmt->close();
    }

    // Insert into highlights table
    $stmt = $conn->prepare("INSERT INTO highlights (pdf_file_id, page_number, content_type, content_id, content_text, position_data) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("iisiss", $pdfId, $pageNumber, $contentType, $contentId, $contentText, $positionData);
    if (!$stmt->execute()) {
        throw new Exception('Error saving highlight: ' . $stmt->error);
    }
    $highlightId = $stmt->insert_id;
    $stmt->close();

    // Commit transaction
    $conn->commit();

    $response = [
        'success' => true,
        'id' => $highlightId,
        'content_id' => $contentId,
        'term_text' => $savedTerm,
        'definition_text' => $savedDefinition,
        'message' => 'Highlight saved successfully'
    ];
} catch (Exception $e) {
    $conn->rollback();
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
}

$conn->close();

echo json_encode($response);
?>