<?php
session_start();
require 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_GET['id'])) {
    header('Location: student_group.php');
    exit();
}

$groupId = $_GET['id'];
$userId = $_SESSION['user_id'];

// Verify that the user is an admin of the group
$stmt = $pdo->prepare("
    SELECT g.*, g.image_url 
    FROM groups g
    JOIN group_members gm ON g.id = gm.group_id
    WHERE g.id = ? AND gm.user_id = ? AND gm.is_admin = 1
");
$stmt->execute([$groupId, $userId]);
$group = $stmt->fetch();

if (!$group) {
    header('Location: student_group.php');
    exit();
}

try {
    $pdo->beginTransaction();
    
    // Delete group posts/comments first (if they exist)
    $stmt = $pdo->prepare("DELETE FROM group_posts WHERE group_id = ?");
    $stmt->execute([$groupId]);
    
    // Delete group members
    $stmt = $pdo->prepare("DELETE FROM group_members WHERE group_id = ?");
    $stmt->execute([$groupId]);
    
    // Delete the group
    $stmt = $pdo->prepare("DELETE FROM groups WHERE id = ?");
    $stmt->execute([$groupId]);
    
    $pdo->commit();
    
    // Delete group image if it exists
    if ($group['image_url'] && file_exists($group['image_url'])) {
        unlink($group['image_url']);
    }
    
    // Redirect back to groups page with success message
    header('Location: student_group.php?deleted=1');
    exit();
} catch (PDOException $e) {
    $pdo->rollBack();
    error_log("Error deleting group: " . $e->getMessage());
    header('Location: student_group.php?error=1');
    exit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error in group deletion process: " . $e->getMessage());
    header('Location: student_group.php?error=1');
    exit();
} 