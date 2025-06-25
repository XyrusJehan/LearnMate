<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['theme'])) {
    $_SESSION['theme'] = $_POST['theme'];
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}
?> 