<?php
// create_folder.php
session_start();

// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'learnmate';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Check connection
if ($conn->connect_error) {
    die(json_encode(['success' => false, 'message' => "Connection failed: " . $conn->connect_error]));
}

// Get folder name from POST request
$name = isset($_POST['name']) ? trim($_POST['name']) : '';

if (empty($name)) {
    die(json_encode(['success' => false, 'message' => 'Folder name cannot be empty']));
}

// Insert new folder
$stmt = $conn->prepare("INSERT INTO folders (name) VALUES (?)");
$stmt->bind_param("s", $name);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    $folder_id = $stmt->insert_id;
    echo json_encode(['success' => true, 'id' => $folder_id, 'name' => $name]);
} else {
    echo json_encode(['success' => false, 'message' => 'Error creating folder']);
}

$stmt->close();
$conn->close();
?>