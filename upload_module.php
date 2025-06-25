<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'teacher') {
    header('Location: index.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['module_file']) && isset($_POST['class_id']) && isset($_POST['title'])) {
    $classId = (int)$_POST['class_id'];
    $title = trim($_POST['title']);
    $userId = $_SESSION['user_id'];
    $file = $_FILES['module_file'];

    if ($file['error'] === UPLOAD_ERR_OK && strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) === 'pdf') {
        $uploadDir = 'uploads/modules/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        $filename = uniqid('module_') . '.pdf';
        $targetPath = $uploadDir . $filename;
        if (move_uploaded_file($file['tmp_name'], $targetPath)) {
            // Insert into DB
            $stmt = $conn->prepare("INSERT INTO modules (class_id, title, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param('issi', $classId, $title, $targetPath, $userId);
            $stmt->execute();
            header('Location: class_details.php?id=' . $classId);
            exit();
        } else {
            die('Failed to move uploaded file.');
        }
    } else {
        die('Invalid file or upload error.');
    }
} else {
    die('Invalid request.');
} 