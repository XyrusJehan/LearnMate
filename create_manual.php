<?php
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
    die("Connection failed: " . $conn->connect_error);
}

// Initialize messages
$successMessage = '';
$errorMessage = '';

// Handle folder creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_folder'])) {
        $folderName = $conn->real_escape_string(trim($_POST['folder_name']));
        
        if (!empty($folderName)) {
            $checkStmt = $conn->prepare("SELECT id FROM folders WHERE name = ?");
            $checkStmt->bind_param("s", $folderName);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $_SESSION['error'] = "A folder with that name already exists";
            } else {
                $stmt = $conn->prepare("INSERT INTO folders (name, created_at) VALUES (?, NOW())");
                $stmt->bind_param("s", $folderName);
                
                if ($stmt->execute()) {
                    $newFolderId = $conn->insert_id;
                    $_SESSION['success'] = "Folder created successfully!";
                    $_SESSION['active_folder'] = $newFolderId;
                    header("Location: create_manual.php?folder_id=" . $newFolderId);
                    exit();
                } else {
                    $_SESSION['error'] = "Error creating folder: " . $conn->error;
                }
            }
        } else {
            $_SESSION['error'] = "Folder name cannot be empty";
        }
    }
    
    // Handle flashcard deletion
    if (isset($_POST['delete_flashcard'])) {
        $flashcardId = (int)$_POST['flashcard_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get term and definition IDs
            $stmt = $conn->prepare("SELECT term_id, definition_id FROM flashcards WHERE id = ?");
            $stmt->bind_param("i", $flashcardId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $termId = $row['term_id'];
                $definitionId = $row['definition_id'];
                
                // Delete from flashcards table
                $stmt = $conn->prepare("DELETE FROM flashcards WHERE id = ?");
                $stmt->bind_param("i", $flashcardId);
                $stmt->execute();
                
                // Delete from term_definitions table
                $stmt = $conn->prepare("DELETE FROM term_definitions WHERE term_id = ? AND definition_id = ?");
                $stmt->bind_param("ii", $termId, $definitionId);
                $stmt->execute();
                
                // Delete from terms table
                $stmt = $conn->prepare("DELETE FROM terms WHERE id = ?");
                $stmt->bind_param("i", $termId);
                $stmt->execute();
                
                // Delete from definitions table
                $stmt = $conn->prepare("DELETE FROM definitions WHERE id = ?");
                $stmt->bind_param("i", $definitionId);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['success'] = 'Flashcard deleted successfully!';
            } else {
                throw new Exception("Flashcard not found");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Error deleting flashcard: ' . $e->getMessage();
        }
        
        header("Location: create_manual.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
        exit();
    }
    
    // Handle flashcard updates
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_flashcard'])) {
        $flashcardId = (int)$_POST['flashcard_id'];
        $term = $conn->real_escape_string($_POST['edit_term']);
        $definition = $conn->real_escape_string($_POST['edit_definition']);
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Get term and definition IDs
            $stmt = $conn->prepare("SELECT term_id, definition_id FROM flashcards WHERE id = ?");
            $stmt->bind_param("i", $flashcardId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $termId = $row['term_id'];
                $definitionId = $row['definition_id'];
                
                // Update term
                $stmt = $conn->prepare("UPDATE terms SET term_text = ? WHERE id = ?");
                $stmt->bind_param("si", $term, $termId);
                $stmt->execute();
                
                // Update definition
                $stmt = $conn->prepare("UPDATE definitions SET definition_text = ? WHERE id = ?");
                $stmt->bind_param("si", $definition, $definitionId);
                $stmt->execute();
                
                // Update flashcard
                $stmt = $conn->prepare("UPDATE flashcards SET front_content = ?, back_content = ? WHERE id = ?");
                $stmt->bind_param("ssi", $term, $definition, $flashcardId);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['success'] = 'Flashcard updated successfully!';
                header("Location: create_manual.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
                exit();
            } else {
                throw new Exception("Flashcard not found");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Error updating flashcard: ' . $e->getMessage();
            header("Location: create_manual.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
            exit();
        }
    }

    // Handle flashcard move
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_flashcards'])) {
        $flashcardIds = $_POST['flashcard_ids'];
        $targetFolderId = isset($_POST['target_folder_id']) ? (int)$_POST['target_folder_id'] : null;
        
        if (empty($flashcardIds)) {
            $_SESSION['error'] = 'No flashcards selected to move';
            header("Location: create_manual.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
            exit();
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Prepare the statement
            $stmt = $conn->prepare("UPDATE flashcards SET folder_id = ? WHERE id = ?");
            
            // Convert string IDs to array if single ID was passed
            if (!is_array($flashcardIds)) {
                $flashcardIds = [$flashcardIds];
            }
            
            // Move each flashcard
            foreach ($flashcardIds as $id) {
                $flashcardId = (int)$id;
                $stmt->bind_param("ii", $targetFolderId, $flashcardId);
                $stmt->execute();
            }
            
            $conn->commit();
            $_SESSION['success'] = 'Flashcard(s) moved successfully!';
            header("Location: create_manual.php" . ($targetFolderId ? "?folder_id=$targetFolderId" : ""));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Error moving flashcard(s): ' . $e->getMessage();
            header("Location: create_manual.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
            exit();
        }
    }
}

// Handle session messages
if (isset($_SESSION['error'])) {
    $errorMessage = '<div class="alert"><i class="fas fa-exclamation-circle"></i> ' . $_SESSION['error'] . '</div>';
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $successMessage = '<div class="success"><i class="fas fa-check-circle"></i> ' . $_SESSION['success'] . '</div>';
    unset($_SESSION['success']);
}

// Initialize active folder
$activeFolderId = null;
$activeFolderName = null;

// Handle folder selection
if (isset($_GET['folder_id'])) {
    $activeFolderId = (int)$_GET['folder_id'];
    $_SESSION['active_folder'] = $activeFolderId;
} elseif (isset($_SESSION['active_folder'])) {
    $activeFolderId = (int)$_SESSION['active_folder'];
}

// Get folder name if active
if ($activeFolderId) {
    $stmt = $conn->prepare("SELECT id, name FROM folders WHERE id = ?");
    $stmt->bind_param("i", $activeFolderId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $folder = $result->fetch_assoc();
        $activeFolderName = $folder['name'];
    }
}

// Handle flashcard creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['term'])) {
    $term = $conn->real_escape_string($_POST['term']);
    $definition = $conn->real_escape_string($_POST['definition']);
    $folderId = $activeFolderId ?: null;

    // Start transaction
    $conn->begin_transaction();

    try {
        // Insert term
        $stmt = $conn->prepare("INSERT INTO terms (term_text) VALUES (?)");
        $stmt->bind_param("s", $term);
        $stmt->execute();
        $termId = $conn->insert_id;
        
        // Insert definition
        $stmt = $conn->prepare("INSERT INTO definitions (definition_text) VALUES (?)");
        $stmt->bind_param("s", $definition);
        $stmt->execute();
        $definitionId = $conn->insert_id;
        
        // Create term-definition relationship
        $stmt = $conn->prepare("INSERT INTO term_definitions (term_id, definition_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $termId, $definitionId);
        $stmt->execute();
        
        // Insert flashcard with folder_id
        $stmt = $conn->prepare("INSERT INTO flashcards (folder_id, term_id, definition_id, front_content, back_content, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisss", $folderId, $termId, $definitionId, $term, $definition);
        $stmt->execute();
        $flashcardId = $conn->insert_id;
        
        $conn->commit();
        $_SESSION['success'] = 'Flashcard created successfully!' . ($activeFolderName ? ' Saved in folder: ' . $activeFolderName : '');
        header("Location: create_manual.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = '<div class="alert"><i class="fas fa-exclamation-circle"></i> Error creating flashcard: ' . $e->getMessage() . '</div>';
    }
}

// Get flashcards for active folder
$flashcards = [];
if ($activeFolderId) {
    $stmt = $conn->prepare("
        SELECT f.id, t.term_text, d.definition_text 
        FROM flashcards f
        JOIN terms t ON f.term_id = t.id
        JOIN definitions d ON f.definition_id = d.id
        WHERE f.folder_id = ?
        ORDER BY f.created_at DESC
    ");
    $stmt->bind_param("i", $activeFolderId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $flashcards[] = $row;
    }
}

// Get all folders (simple flat list)
$allFolders = [];
$stmt = $conn->prepare("SELECT id, name FROM folders ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $allFolders[] = $row;
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Flashcard - Manual Entry</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #4361ee;
            --primary-light: #3f37c9;
            --secondary: #3a0ca3;
            --success: #4cc9f0;
            --danger: #f72585;
            --warning: #f8961e;
            --info: #4895ef;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --white: #ffffff;
            --card-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            color: var(--dark);
            line-height: 1.6;
            display: flex;
        }

        .sidebar {
            width: 250px;
            background-color: var(--white);
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            padding: 20px;
            height: 100vh;
            position: fixed;
            overflow-y: auto;
            z-index: 1000;
        }

        .sidebar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .sidebar-header h3 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .add-folder-btn {
            background-color: var(--primary);
            color: var(--white);
            border: none;
            border-radius: 50%;
            width: 28px;
            height: 28px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
        }

        .add-folder-btn:hover {
            background-color: var(--primary-light);
            transform: rotate(90deg);
        }

        .folder-list {
            list-style: none;
            padding-left: 0;
        }

        .folder-item {
            margin-bottom: 3px;
        }

        .folder-link {
            display: block;
            padding: 10px 12px;
            border-radius: 6px;
            color: var(--dark);
            text-decoration: none;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .folder-link:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }

        .folder-link.active {
            background-color: var(--primary);
            color: var(--white);
        }

        .folder-link i {
            font-size: 0.9rem;
        }

        .main-content {
            flex: 1;
            margin-left: 5px;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            margin-left: 5px;
            width: 100%;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }

        h1 {
            color: var(--primary);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.5rem;
        }

        .back-btn {
            background-color: var(--primary);
            color: var(--white);
            padding: 10px 15px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.95rem;
        }

        .back-btn:hover {
            background-color: var(--primary-light);
        }

        .flashcard-form {
            background: var(--white);
            padding: 30px;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            width: 100%;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: var(--dark);
            font-size: 0.95rem;
        }

        input[type="text"],
        textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-family: inherit;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        textarea {
            min-height: 150px;
            resize: vertical;
        }

        input[type="text"]:focus,
        textarea:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.2);
        }

        .submit-btn {
            background-color: var(--success);
            color: var(--white);
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.95rem;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 10px;
        }

        .submit-btn:hover {
            background-color: #3aa8d9;
            transform: translateY(-2px);
        }

        .alert, .success {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .alert {
            background-color: rgba(247, 37, 133, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .success {
            background-color: rgba(76, 201, 240, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .active-folder-indicator {
            margin-bottom: 20px;
            padding: 12px;
            background-color: rgba(67, 97, 238, 0.1);
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 0.95rem;
        }

        .flashcards-container {
            margin-top: 30px;
            background: var(--white);
            padding: 25px;
            border-radius: 10px;
            box-shadow: var(--card-shadow);
            width: 100%;
        }

        .flashcards-container h2 {
            color: var(--primary);
            margin-bottom: 20px;
            font-size: 1.3rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .flashcards-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
            width: 100%;
        }

        .flashcard {
            background: #f9f9f9;
            border-left: 4px solid var(--primary);
            padding: 15px;
            border-radius: 5px;
            width: 100%;
            box-sizing: border-box;
            position: relative;
            transition: var(--transition);
        }

        .flashcard-term {
            margin-bottom: 10px;
            font-weight: 600;
            word-break: break-word;
        }

        .flashcard-definition {
            word-break: break-word;
        }

        .no-flashcards {
            margin-top: 30px;
            text-align: center;
            color: var(--gray);
            font-style: italic;
            padding: 20px;
            background: #f9f9f9;
            border-radius: 8px;
        }

        .flashcard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
        }

        .dropdown {
            position: relative;
            display: inline-block;
        }

        .dropdown-btn {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            padding: 5px;
            font-size: 1.2rem;
            transition: var(--transition);
        }

        .dropdown-btn:hover {
            color: var(--dark);
        }

        .dropdown-content {
            display: none;
            position: absolute;
            right: 0;
            background-color: var(--white);
            min-width: 150px;
            box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
            z-index: 1;
            border-radius: 8px;
            overflow: hidden;
        }

        .dropdown-content a {
            color: var(--dark);
            padding: 10px 15px;
            text-decoration: none;
            display: block;
            font-size: 0.9rem;
            transition: var(--transition);
        }

        .dropdown-content a:hover {
            background-color: #f1f1f1;
            color: var(--primary);
        }

        .dropdown-content a i {
            margin-right: 8px;
            width: 16px;
            text-align: center;
        }

        .show {
            display: block;
            animation: fadeIn 0.2s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-5px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .modal-content {
            background-color: var(--white);
            margin: 15% auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 400px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: modalFadeIn 0.3s ease-out;
        }

        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            margin-bottom: 15px;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--dark);
            font-size: 1.2rem;
            font-weight: 600;
        }

        .modal-body p {
            color: var(--gray);
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        .modal-body strong {
            color: var(--dark);
        }

        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
        }

        .btn {
            padding: 10px 18px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            font-size: 0.9rem;
        }

        .cancel-btn {
            background: none;
            color: var(--gray);
            border: 1px solid #ddd;
        }

        .cancel-btn:hover {
            background: #f5f5f5;
        }

        .primary-btn {
            background-color: var(--primary);
            color: white;
            border: none;
        }

        .primary-btn:hover {
            background-color: var(--primary-light);
        }

        .edit-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .edit-modal-content {
            background-color: var(--white);
            margin: 10% auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: modalFadeIn 0.3s ease-out;
        }

        .edit-modal-header {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
        }

        .edit-modal-header h3 {
            margin: 0;
            color: var(--dark);
            font-size: 1.3rem;
            font-weight: 600;
        }

        .close-edit-modal {
            color: var(--gray);
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .close-edit-modal:hover {
            color: var(--danger);
        }

        .edit-modal-body {
            padding: 15px 0;
        }

        .edit-form-group {
            margin-bottom: 20px;
        }

        .edit-form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: 25px;
        }

        /* Move Flashcard Styles */
        .move-mode .flashcard {
            padding-left: 40px;
            position: relative;
        }

        .move-mode .flashcard-checkbox {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            cursor: pointer;
            display: block !important;
        }

        .move-mode .dropdown {
            display: none;
        }

        .move-mode .flashcards-list {
            padding-bottom: 80px; /* Space for the move actions bar */
        }

        .move-actions-bar {
            position: fixed;
            bottom: 0;
            left: 250px;
            right: 0;
            background: var(--white);
            padding: 15px 20px;
            box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
            z-index: 1000;
            transform: translateY(100%);
            transition: transform 0.3s ease;
        }

        .move-mode .move-actions-bar {
            transform: translateY(0);
        }

        .move-actions-left {
            flex: 1;
        }

        .move-actions-center {
            flex: 2;
            text-align: center;
        }

        .move-actions-right {
            flex: 1;
        }

        .move-select-all {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            color: var(--primary);
            font-weight: 500;
        }

        .move-select-all input {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        .move-btn {
            padding: 8px 20px;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 500;
            transition: var(--transition);
            border: none;
            font-size: 0.9rem;
        }

        .move-cancel {
            background: none;
            color: var(--danger);
            border: 1px solid var(--danger);
        }

        .move-cancel:hover {
            background-color: rgba(247, 37, 133, 0.1);
        }

        .move-submit {
            background-color: var(--primary);
            color: white;
        }

        .move-submit:hover {
            background-color: var(--primary-light);
        }

        /* Move Folder Selection Modal */
        .move-folder-modal {
            display: none;
            position: fixed;
            z-index: 2100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(2px);
        }

        .move-folder-modal-content {
            background-color: var(--white);
            margin: 10% auto;
            padding: 25px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            animation: modalFadeIn 0.3s ease-out;
        }

        .move-folder-list {
            max-height: 300px;
            overflow-y: auto;
            margin: 15px 0;
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 10px;
        }

        .move-folder-item {
            padding: 10px;
            cursor: pointer;
            border-radius: 5px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .move-folder-item:hover {
            background-color: rgba(67, 97, 238, 0.1);
        }

        .move-folder-item.selected {
            background-color: rgba(67, 97, 238, 0.2);
            font-weight: 500;
        }

        .move-folder-item i {
            color: var(--primary);
        }

        .loading {
            padding: 15px;
            text-align: center;
            color: var(--primary);
            font-style: italic;
        }

        @media (max-width: 768px) {
            body {
                flex-direction: column;
            }

            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
                padding: 15px;
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .container {
                padding: 0;
            }
            
            .flashcard-form {
                padding: 20px;
            }

            .modal-content {
                margin: 20% auto;
                padding: 20px;
            }

            .edit-modal-content {
                margin: 15% auto;
                padding: 20px;
            }

            .move-actions-bar {
                left: 0;
            }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h3><i class="fas fa-folder"></i> My Folders</h3>
            <button class="add-folder-btn" title="Add New Folder">
                <i class="fas fa-plus"></i>
            </button>
        </div>
        <ul class="folder-list">
            <li class="folder-item">
                <a href="create_manual.php" class="folder-link<?php echo !$activeFolderId ? ' active' : '' ?>">
                    <i class="fas fa-inbox"></i> All Flashcards
                </a>
            </li>
            <?php foreach ($allFolders as $folder): ?>
                <li class="folder-item">
                    <a href="create_manual.php?folder_id=<?php echo $folder['id']; ?>" 
                       class="folder-link<?php echo $activeFolderId == $folder['id'] ? ' active' : '' ?>">
                        <i class="fas fa-folder"></i> <?php echo htmlspecialchars($folder['name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="main-content">
        <div class="container">
            <div class="header">
                <h1><i class="fas fa-keyboard"></i> Create Flashcard</h1>
                <a href="flashcard.php" class="back-btn"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>

            <?php if ($activeFolderName): ?>
                <div class="active-folder-indicator">
                    <i class="fas fa-folder-open"></i>
                    <strong>Active Folder:</strong> <?php echo htmlspecialchars($activeFolderName); ?>
                </div>
            <?php endif; ?>

            <?php echo $errorMessage; ?>
            <?php echo $successMessage; ?>

            <form class="flashcard-form" method="POST" action="create_manual.php">
                <div class="form-group">
                    <label for="term"><i class="fas fa-font"></i> Term</label>
                    <input type="text" id="term" name="term" placeholder="Enter the term or concept" required>
                </div>

                <div class="form-group">
                    <label for="definition"><i class="fas fa-info-circle"></i> Definition</label>
                    <textarea id="definition" name="definition" placeholder="Enter the definition or explanation" required></textarea>
                </div>

                <button type="submit" class="submit-btn"><i class="fas fa-save"></i> Create Flashcard</button>
            </form>

            <?php if ($activeFolderId): ?>
                <div class="flashcards-container">
                    <h2><i class="fas fa-layer-group"></i> Flashcards in this folder</h2>
                    <?php if (!empty($flashcards)): ?>
                        <div class="flashcards-list">
                            <?php foreach ($flashcards as $card): ?>
                                <div class="flashcard" id="flashcard-<?php echo $card['id']; ?>">
                                    <input type="checkbox" class="flashcard-checkbox" id="flashcard_<?php echo $card['id']; ?>" value="<?php echo $card['id']; ?>" style="display: none;">
                                    <div class="flashcard-header">
                                        <div class="flashcard-term">
                                            <strong>Term:</strong> <?php echo htmlspecialchars($card['term_text']); ?>
                                        </div>
                                        <div class="dropdown">
                                            <button class="dropdown-btn"><i class="fas fa-ellipsis-v"></i></button>
                                            <div class="dropdown-content">
                                                <a href="#" class="edit-flashcard" data-id="<?php echo $card['id']; ?>" data-term="<?php echo htmlspecialchars($card['term_text']); ?>" data-definition="<?php echo htmlspecialchars($card['definition_text']); ?>">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                                <a href="#" class="move-flashcard" data-id="<?php echo $card['id']; ?>">
                                                    <i class="fas fa-arrows-alt"></i> Move
                                                </a>
                                                <a href="#" class="delete-flashcard" data-id="<?php echo $card['id']; ?>">
                                                    <i class="fas fa-trash"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flashcard-definition">
                                        <strong>Definition:</strong> <?php echo htmlspecialchars($card['definition_text']); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="no-flashcards">
                            <p>No flashcards in this folder yet.</p>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Move Actions Bar (hidden by default) -->
    <div class="move-actions-bar">
        <div class="move-actions-left">
            <div class="move-select-all">
                <input type="checkbox" id="select-all-move">
                <label for="select-all-move">Select All</label>
            </div>
        </div>
        <div class="move-actions-center">
            <button class="move-btn move-submit" id="move-selected-btn">
                <i class="fas fa-arrow-right"></i> Move Selected
            </button>
        </div>
        <div class="move-actions-right">
            <button class="move-btn move-cancel" id="cancel-move-btn">
                <i class="fas fa-times"></i> Cancel
            </button>
        </div>
    </div>

    <!-- Move Folder Selection Modal -->
    <div id="moveFolderModal" class="move-folder-modal">
        <div class="move-folder-modal-content">
            <div class="edit-modal-header">
                <h3><i class="fas fa-folder"></i> Move to Folder</h3>
                <span class="close-edit-modal">&times;</span>
            </div>
            <div class="edit-modal-body">
                <div class="move-folder-list">
                    <div class="move-folder-item" data-folder-id="">
                        <i class="fas fa-inbox"></i> All Flashcards
                    </div>
                    <?php foreach ($allFolders as $folder): ?>
                        <div class="move-folder-item" data-folder-id="<?php echo $folder['id']; ?>">
                            <i class="fas fa-folder"></i> <?php echo htmlspecialchars($folder['name']); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="edit-form-actions">
                    <button type="button" class="btn cancel-btn" id="cancel-move-folder">Cancel</button>
                    <button type="button" class="btn primary-btn" id="confirm-move-folder">Move Here</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Folder Modal -->
    <div id="folderModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add folder</h3>
            </div>
            <div class="modal-body">
                <form id="createFolderForm" method="POST">
                    <div class="form-group">
                        <input type="text" id="folder_name" name="folder_name" placeholder="e.g. Enzymes" required>
                    </div>
                    <div class="form-actions">
                        <button type="button" class="btn cancel-btn">Cancel</button>
                        <button type="submit" name="create_folder" class="btn primary-btn">Add folder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Flashcard Modal -->
    <div id="editFlashcardModal" class="edit-modal">
        <div class="edit-modal-content">
            <div class="edit-modal-header">
                <h3><i class="fas fa-edit"></i> Edit Flashcard</h3>
                <span class="close-edit-modal">&times;</span>
            </div>
            <div class="edit-modal-body">
                <form id="editFlashcardForm" method="POST">
                    <input type="hidden" id="flashcard_id" name="flashcard_id" value="">
                    <div class="edit-form-group">
                        <label for="edit_term"><i class="fas fa-font"></i> Term</label>
                        <input type="text" id="edit_term" name="edit_term" class="form-control" required>
                    </div>
                    <div class="edit-form-group">
                        <label for="edit_definition"><i class="fas fa-info-circle"></i> Definition</label>
                        <textarea id="edit_definition" name="edit_definition" class="form-control" rows="5" required></textarea>
                    </div>
                    <div class="edit-form-actions">
                        <button type="button" class="btn cancel-btn close-edit-modal-btn">Cancel</button>
                        <button type="submit" name="update_flashcard" class="btn primary-btn">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add folder button functionality
            document.querySelector('.add-folder-btn').addEventListener('click', function() {
                const modal = document.getElementById('folderModal');
                modal.style.display = 'block';
                document.getElementById('folder_name').focus();
            });

            // Modal functionality
            const modal = document.getElementById('folderModal');
            const cancelBtn = document.querySelector('.cancel-btn');
            
            function closeModal() {
                modal.style.display = 'none';
            }
            
            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    closeModal();
                }
            });
            
            cancelBtn.addEventListener('click', closeModal);
            
            // Handle form submission
            const folderForm = document.getElementById('createFolderForm');
            folderForm.addEventListener('submit', function(e) {
                const folderName = document.getElementById('folder_name').value.trim();
                if (!folderName) {
                    e.preventDefault();
                    alert('Please enter a folder name');
                    return;
                }
            });

            // Dropdown menu functionality
            document.querySelectorAll('.dropdown-btn').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const dropdown = this.nextElementSibling;
                    const isShowing = dropdown.classList.contains('show');
                    
                    // Close all other dropdowns first
                    document.querySelectorAll('.dropdown-content').forEach(d => {
                        d.classList.remove('show');
                    });
                    
                    // Toggle this dropdown
                    if (!isShowing) {
                        dropdown.classList.add('show');
                    }
                });
            });

            // Close dropdowns when clicking elsewhere
            document.addEventListener('click', function() {
                document.querySelectorAll('.dropdown-content').forEach(dropdown => {
                    dropdown.classList.remove('show');
                });
            });

            // Edit Flashcard Modal functionality
            const editModal = document.getElementById('editFlashcardModal');
            const closeEditModalBtn = document.querySelector('.close-edit-modal');
            const closeEditModalBtn2 = document.querySelector('.close-edit-modal-btn');
            
            function openEditModal(flashcardId, term, definition) {
                document.getElementById('flashcard_id').value = flashcardId;
                document.getElementById('edit_term').value = term;
                document.getElementById('edit_definition').value = definition;
                editModal.style.display = 'block';
                
                // Close any open dropdown menus
                document.querySelectorAll('.dropdown-content').forEach(d => {
                    d.classList.remove('show');
                });
            }
            
            function closeEditModal() {
                editModal.style.display = 'none';
            }
            
            closeEditModalBtn.addEventListener('click', closeEditModal);
            closeEditModalBtn2.addEventListener('click', closeEditModal);
            
            window.addEventListener('click', function(event) {
                if (event.target === editModal) {
                    closeEditModal();
                }
            });
            
            // Handle edit button clicks
            document.querySelectorAll('.edit-flashcard').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const flashcardId = this.getAttribute('data-id');
                    const term = this.getAttribute('data-term');
                    const definition = this.getAttribute('data-definition');
                    
                    openEditModal(flashcardId, term, definition);
                });
            });
            
            // Handle form submission
            const editForm = document.getElementById('editFlashcardForm');
            editForm.addEventListener('submit', function(e) {
                const term = document.getElementById('edit_term').value.trim();
                const definition = document.getElementById('edit_definition').value.trim();
                
                if (!term || !definition) {
                    e.preventDefault();
                    alert('Both term and definition are required');
                    return;
                }
            });

            // Move functionality
            let selectedFlashcards = [];
            const moveFolderModal = document.getElementById('moveFolderModal');
            let selectedFolderId = null;

            // Handle move button clicks
            document.querySelectorAll('.move-flashcard').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const flashcardId = this.getAttribute('data-id');
                    
                    // Enter move mode
                    document.body.classList.add('move-mode');
                    
                    // Check the clicked flashcard
                    const checkbox = document.querySelector(`.flashcard-checkbox[value="${flashcardId}"]`);
                    checkbox.checked = true;
                    checkbox.style.display = 'block';
                    
                    // Close any open dropdown menus
                    document.querySelectorAll('.dropdown-content').forEach(d => {
                        d.classList.remove('show');
                    });
                    
                    // Scroll to show the move actions bar
                    setTimeout(() => {
                        window.scrollBy(0, 100);
                    }, 300);
                });
            });

            // Select All checkbox for move
            document.getElementById('select-all-move').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.flashcard-checkbox');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = this.checked;
                });
            });

            // Cancel move button
            document.getElementById('cancel-move-btn').addEventListener('click', function() {
                // Exit move mode
                document.body.classList.remove('move-mode');
                
                // Uncheck all checkboxes
                document.querySelectorAll('.flashcard-checkbox').forEach(checkbox => {
                    checkbox.checked = false;
                    checkbox.style.display = 'none';
                });
                
                // Uncheck select all
                document.getElementById('select-all-move').checked = false;
                
                // Scroll back up if needed
                setTimeout(() => {
                    window.scrollBy(0, -100);
                }, 300);
            });

            // Move selected button
            document.getElementById('move-selected-btn').addEventListener('click', function() {
                // Get selected flashcard IDs
                selectedFlashcards = [];
                document.querySelectorAll('.flashcard-checkbox:checked').forEach(checkbox => {
                    selectedFlashcards.push(checkbox.value);
                });
                
                if (selectedFlashcards.length === 0) {
                    alert('Please select at least one flashcard to move');
                    return;
                }
                
                // Show folder selection modal
                moveFolderModal.style.display = 'block';
                
                // Reset folder selection
                selectedFolderId = null;
                document.querySelectorAll('.move-folder-item').forEach(item => {
                    item.classList.remove('selected');
                });
            });

            // Folder selection in move modal
            document.querySelectorAll('.move-folder-item').forEach(item => {
                item.addEventListener('click', function() {
                    // Remove previous selection
                    document.querySelectorAll('.move-folder-item').forEach(i => {
                        i.classList.remove('selected');
                    });
                    
                    // Select this folder
                    this.classList.add('selected');
                    selectedFolderId = this.getAttribute('data-folder-id');
                });
            });

            // Cancel move folder
            document.getElementById('cancel-move-folder').addEventListener('click', function() {
                moveFolderModal.style.display = 'none';
            });

            // Confirm move folder
            document.getElementById('confirm-move-folder').addEventListener('click', function() {
                if (!selectedFolderId && selectedFolderId !== '') {
                    alert('Please select a folder');
                    return;
                }
                
                // Create a form to submit
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'create_manual.php';
                
                // Add flashcard IDs
                selectedFlashcards.forEach(id => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'flashcard_ids[]';
                    input.value = id;
                    form.appendChild(input);
                });
                
                // Add target folder ID
                const folderInput = document.createElement('input');
                folderInput.type = 'hidden';
                folderInput.name = 'target_folder_id';
                folderInput.value = selectedFolderId;
                form.appendChild(folderInput);
                
                // Add submit button
                const submitInput = document.createElement('input');
                submitInput.type = 'hidden';
                submitInput.name = 'move_flashcards';
                submitInput.value = '1';
                form.appendChild(submitInput);
                
                // Submit the form
                document.body.appendChild(form);
                form.submit();
            });

            // Close move folder modal
            document.querySelector('#moveFolderModal .close-edit-modal').addEventListener('click', function() {
                moveFolderModal.style.display = 'none';
            });

            // Handle delete button clicks
            document.querySelectorAll('.delete-flashcard').forEach(btn => {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    const flashcardId = this.getAttribute('data-id');
                    const flashcardElement = document.getElementById(`flashcard-${flashcardId}`);
                    
                    // Show loading state
                    const originalContent = flashcardElement.innerHTML;
                    flashcardElement.innerHTML = '<div class="loading">Deleting... <i class="fas fa-spinner fa-spin"></i></div>';
                    
                    // Create form data
                    const formData = new FormData();
                    formData.append('flashcard_id', flashcardId);
                    formData.append('delete_flashcard', '1');
                    
                    // Send AJAX request
                    fetch('create_manual.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (response.redirected) {
                            window.location.href = response.url;
                        } else {
                            return response.text();
                        }
                    })
                    .then(data => {
                        // If not redirected, remove the flashcard from DOM
                        flashcardElement.remove();
                        
                        // Show success message
                        const successMessage = document.createElement('div');
                        successMessage.className = 'success';
                        successMessage.innerHTML = '<i class="fas fa-check-circle"></i> Flashcard deleted successfully!';
                        document.querySelector('.flashcards-container').prepend(successMessage);
                        
                        // Remove success message after 3 seconds
                        setTimeout(() => {
                            successMessage.remove();
                        }, 3000);
                    })
                    .catch(error => {
                        // Restore original content on error
                        flashcardElement.innerHTML = originalContent;
                        
                        // Show error message
                        const errorMessage = document.createElement('div');
                        errorMessage.className = 'alert';
                        errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error deleting flashcard. Please try again.';
                        document.querySelector('.flashcards-container').prepend(errorMessage);
                        
                        // Remove error message after 3 seconds
                        setTimeout(() => {
                            errorMessage.remove();
                        }, 3000);
                        
                        console.error('Error:', error);
                    });
                });
            });
        });
    </script>
</body>
</html>