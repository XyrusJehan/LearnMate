<?php
// create_folder.php
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
$db_name = 'railway';
$db_port = '47909';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name,$db_port);

// Check connection
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]);
    exit();
}

// Get folder name from POST request
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$userId = $_SESSION['user_id'];

if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Folder name cannot be empty']);
    exit();
}

// Check if folder name already exists for this user
$checkStmt = $conn->prepare("SELECT id FROM folders WHERE name = ? AND user_id = ?");
$checkStmt->bind_param("si", $name, $userId);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'A folder with this name already exists']);
    exit();
}
$checkStmt->close();

// Insert new folder
$stmt = $conn->prepare("INSERT INTO folders (name, user_id, created_at) VALUES (?, ?, NOW())");
$stmt->bind_param("si", $name, $userId);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $folder_id = $stmt->insert_id;
    echo json_encode(['success' => true, 'id' => $folder_id, 'name' => $name]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error creating folder']);
}

$stmt->close();
$conn->close();
?>