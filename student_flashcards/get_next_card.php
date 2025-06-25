<?php
session_start();
require '../db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not logged in']);
    exit();
}

$studentId = $_SESSION['user_id'];

try {
    // Get a random flashcard from the student's folders
    $stmt = $pdo->prepare("
        SELECT f.*, t.term_text, d.definition_text 
        FROM flashcards f
        JOIN terms t ON f.term_id = t.id
        JOIN definitions d ON f.definition_id = d.id
        JOIN folders fo ON f.folder_id = fo.id
        WHERE fo.user_id = ?
        ORDER BY RAND()
        LIMIT 1
    ");
    $stmt->execute([$studentId]);
    $card = $stmt->fetch();

    if ($card) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'term' => $card['term_text'],
            'definition' => $card['definition_text']
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'No more cards']);
    }
} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Database error']);
} 