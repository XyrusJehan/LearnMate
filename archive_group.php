<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['archive_group_id'])) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Unauthorized access"]);
    exit();
}

$userId = $_SESSION['user_id'];
$groupId = (int)$_POST['archive_group_id'];

// Verify that the user is an admin of the group
$stmt = $pdo->prepare("
    SELECT g.* 
    FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE g.id = ? AND gm.user_id = ? AND gm.is_admin = 1
");
$stmt->execute([$groupId, $userId]);
$group = $stmt->fetch();

if (!$group) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Group not found or you don't have permission to archive it"]);
    exit();
}

try {
    // Archive the group
    $stmt = $pdo->prepare("UPDATE groups SET is_archived = 1 WHERE id = ?");
    if ($stmt->execute([$groupId])) {
        header('Content-Type: application/json');
        echo json_encode(["success" => true, "message" => "Group archived successfully"]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(["success" => false, "message" => "Failed to archive group"]);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(["success" => false, "message" => "Database error: " . $e->getMessage()]);
}
?> 