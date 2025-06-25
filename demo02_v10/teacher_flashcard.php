<?php
// flashcard.php
// Combined Flashcard Dashboard with Create Flashcards section

// Start session and check authentication
session_start();
require '../db.php';
require '../includes/theme.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit();
}

// Get theme for the page
$theme = getCurrentTheme();

// Handle PDF file upload
$uploadMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['pdf_file'])) {
    $uploadDir = 'uploads/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    $originalFilename = basename($_FILES['pdf_file']['name']);
    $targetFile = $uploadDir . uniqid() . '_' . $originalFilename;
    $fileSize = $_FILES['pdf_file']['size'];
    
    // Check if file is a PDF
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));
    if ($fileType !== 'pdf') {
        $uploadMessage = '<div class="alert"><i class="fas fa-exclamation-circle"></i> Only PDF files are allowed.</div>';
    } elseif ($_FILES['pdf_file']['size'] > 5000000) { // 5MB limit
        $uploadMessage = '<div class="alert"><i class="fas fa-exclamation-circle"></i> File is too large. Maximum 5MB allowed.</div>';
    } elseif (move_uploaded_file($_FILES['pdf_file']['tmp_name'], $targetFile)) {
        // Insert into database with user_id
        $stmt = $pdo->prepare("INSERT INTO pdf_files (original_filename, storage_path, file_size, user_id) VALUES (?, ?, ?, ?)");
        $stmt->execute([$originalFilename, $targetFile, $fileSize, $_SESSION['user_id']]);
        $uploadMessage = '<div class="success"><i class="fas fa-check-circle"></i> PDF uploaded successfully!</div>';
    } else {
        $uploadMessage = '<div class="alert"><i class="fas fa-exclamation-circle"></i> Sorry, there was an error uploading your file.</div>';
    }
}

function getSingleValue($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetchColumn();
    return $result ? $result : 0;
}

// Function to execute query and return all rows
function getAllRows($pdo, $sql, $params = []) {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get all PDFs for the modal (filtered by user)
$allPDFs = getAllRows($pdo, "SELECT id, original_filename, file_size FROM pdf_files WHERE user_id = ? ORDER BY upload_date DESC", [$_SESSION['user_id']]);

// Get teacher's classes for the sidebar dropdown
$teacherId = $_SESSION['user_id'];
$classes = getAllRows($pdo, "
    SELECT c.* 
    FROM classes c
    WHERE c.teacher_id = ?
", [$teacherId]);

$host = 'switchyard.proxy.rlwy.net';
$dbname = 'railway';
$username = 'root';
$password = 'mfwZMSewsBKfBJQOdeOmyqMZoRGwewMI'; // From MYSQL_ROOT_PASSWORD
$port = 47909;

$mysqli = new mysqli($host, $username, $password, $dbname, $port);


// Initialize messages
$successMessage = '';
$errorMessage = '';

// Handle folder creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_folder'])) {
        $folderName = $conn->real_escape_string(trim($_POST['folder_name']));
        
        if (!empty($folderName)) {
            if (!isset($_SESSION['user_id'])) {
                $_SESSION['error'] = "You must be logged in to create folders";
                header("Location: teacher_flashcard.php");
                exit();
            }

            $userId = $_SESSION['user_id'];
            
            $checkStmt = $pdo->prepare("SELECT id FROM folders WHERE name = ? AND user_id = ?");
            $checkStmt->bind_param("si", $folderName, $userId);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();
            
            if ($checkResult->num_rows > 0) {
                $_SESSION['error'] = "A folder with that name already exists";
            } else {
                $stmt = $pdo->prepare("INSERT INTO folders (name, user_id, created_at) VALUES (?, ?, NOW())");
                $stmt->bind_param("si", $folderName, $userId);
                
                if ($stmt->execute()) {
                    $newFolderId = $conn->insert_id;
                    $_SESSION['success'] = "Folder created successfully!";
                    $_SESSION['active_folder'] = $newFolderId;
                    header("Location: teacher_flashcard.php?folder_id=" . $newFolderId);
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
            $stmt = $pdo->prepare("SELECT term_id, definition_id FROM flashcards WHERE id = ?");
            $stmt->bind_param("i", $flashcardId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $termId = $row['term_id'];
                $definitionId = $row['definition_id'];
                
                // Delete from flashcards table
                $stmt = $pdo->prepare("DELETE FROM flashcards WHERE id = ?");
                $stmt->bind_param("i", $flashcardId);
                $stmt->execute();
                
                // Delete from term_definitions table
                $stmt = $pdo->prepare("DELETE FROM term_definitions WHERE term_id = ? AND definition_id = ?");
                $stmt->bind_param("ii", $termId, $definitionId);
                $stmt->execute();
                
                // Delete from terms table
                $stmt = $pdo->prepare("DELETE FROM terms WHERE id = ?");
                $stmt->bind_param("i", $termId);
                $stmt->execute();
                
                // Delete from definitions table
                $stmt = $pdo->prepare("DELETE FROM definitions WHERE id = ?");
                $stmt->bind_param("i", $definitionId);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['success'] = 'Flashcard deleted successfully!';
                header("Location: teacher_flashcard.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
                exit();
            } else {
                throw new Exception("Flashcard not found");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Error deleting flashcard: ' . $e->getMessage();
        }
        
        header("Location: teacher_flashcard.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
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
            $stmt = $pdo->prepare("SELECT term_id, definition_id FROM flashcards WHERE id = ?");
            $stmt->bind_param("i", $flashcardId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $termId = $row['term_id'];
                $definitionId = $row['definition_id'];
                
                // Update term
                $stmt = $pdo->prepare("UPDATE terms SET term_text = ? WHERE id = ?");
                $stmt->bind_param("si", $term, $termId);
                $stmt->execute();
                
                // Update definition
                $stmt = $pdo->prepare("UPDATE definitions SET definition_text = ? WHERE id = ?");
                $stmt->bind_param("si", $definition, $definitionId);
                $stmt->execute();
                
                // Update flashcard
                $stmt = $pdo->prepare("UPDATE flashcards SET front_content = ?, back_content = ? WHERE id = ?");
                $stmt->bind_param("ssi", $term, $definition, $flashcardId);
                $stmt->execute();
                
                $conn->commit();
                $_SESSION['success'] = 'Flashcard updated successfully!';
                header("Location: teacher_flashcard.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
                exit();
            } else {
                throw new Exception("Flashcard not found");
            }
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Error updating flashcard: ' . $e->getMessage();
            header("Location: teacher_flashcard.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
            exit();
        }
    }

    // Handle flashcard move
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_flashcards'])) {
        $flashcardIds = $_POST['flashcard_ids'];
        $targetFolderId = isset($_POST['target_folder_id']) ? (int)$_POST['target_folder_id'] : null;
        
        if (empty($flashcardIds)) {
            $_SESSION['error'] = 'No flashcards selected to move';
            header("Location: teacher_flashcard.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
            exit();
        }
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Prepare the statement
            $stmt = $pdo->prepare("UPDATE flashcards SET folder_id = ? WHERE id = ?");
            
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
            header("Location: teacher_flashcard.php" . ($targetFolderId ? "?folder_id=$targetFolderId" : ""));
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = 'Error moving flashcard(s): ' . $e->getMessage();
            header("Location: teacher_flashcard.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
            exit();
        }
    }

    // Handle folder deletion (AJAX)
    if (isset($_POST['delete_folder_id'])) {
        $folderId = (int)$_POST['delete_folder_id'];
        $userId = $_SESSION['user_id'];
        $response = ["success" => false, "message" => "Unknown error."];
        
        // Start transaction
        $conn->begin_transaction();
        try {
            // Check folder ownership
            $stmt = $pdo->prepare("SELECT id FROM folders WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $folderId, $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows === 0) {
                throw new Exception("Folder not found or not owned by user.");
            }
            // Get all flashcards in the folder
            $stmt = $pdo->prepare("SELECT id, term_id, definition_id FROM flashcards WHERE folder_id = ?");
            $stmt->bind_param("i", $folderId);
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $flashcardId = $row['id'];
                $termId = $row['term_id'];
                $definitionId = $row['definition_id'];
                // Delete from flashcards
                $del = $pdo->prepare("DELETE FROM flashcards WHERE id = ?");
                $del->bind_param("i", $flashcardId);
                $del->execute();
                // Delete from term_definitions
                $del = $pdo->prepare("DELETE FROM term_definitions WHERE term_id = ? AND definition_id = ?");
                $del->bind_param("ii", $termId, $definitionId);
                $del->execute();
                // Delete from terms
                $del = $pdo->prepare("DELETE FROM terms WHERE id = ?");
                $del->bind_param("i", $termId);
                $del->execute();
                // Delete from definitions
                $del = $pdo->prepare("DELETE FROM definitions WHERE id = ?");
                $del->bind_param("i", $definitionId);
                $del->execute();
            }
            // Delete the folder
            $stmt = $pdo->prepare("DELETE FROM folders WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $folderId, $userId);
            $stmt->execute();
            $conn->commit();
            $response = ["success" => true, "message" => "Folder deleted successfully."];
        } catch (Exception $e) {
            $conn->rollback();
            $response = ["success" => false, "message" => $e->getMessage()];
        }
        header('Content-Type: application/json');
        echo json_encode($response);
        exit();
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
    $stmt = $pdo->prepare("SELECT id, name FROM folders WHERE id = ?");
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
        $stmt = $pdo->prepare("INSERT INTO terms (term_text) VALUES (?)");
        $stmt->bind_param("s", $term);
        $stmt->execute();
        $termId = $conn->insert_id;
        
        // Insert definition
        $stmt = $pdo->prepare("INSERT INTO definitions (definition_text) VALUES (?)");
        $stmt->bind_param("s", $definition);
        $stmt->execute();
        $definitionId = $conn->insert_id;
        
        // Create term-definition relationship
        $stmt = $pdo->prepare("INSERT INTO term_definitions (term_id, definition_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $termId, $definitionId);
        $stmt->execute();
        
        // Insert flashcard with folder_id
        $stmt = $pdo->prepare("INSERT INTO flashcards (folder_id, term_id, definition_id, front_content, back_content, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("iisss", $folderId, $termId, $definitionId, $term, $definition);
        $stmt->execute();
        $flashcardId = $conn->insert_id;
        
        $conn->commit();
        $_SESSION['success'] = 'Flashcard created successfully!' . ($activeFolderName ? ' Saved in folder: ' . $activeFolderName : '');
        header("Location: teacher_flashcard.php" . ($activeFolderId ? "?folder_id=$activeFolderId" : ""));
        exit();
    } catch (Exception $e) {
        $conn->rollback();
        $errorMessage = '<div class="alert"><i class="fas fa-exclamation-circle"></i> Error creating flashcard: ' . $e->getMessage() . '</div>';
    }
}

// Get flashcards for active folder
$flashcards = [];
if ($activeFolderId) {
    $stmt = $pdo->prepare("
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
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("SELECT id, name FROM folders WHERE user_id = ? AND is_archived = 0 ORDER BY name");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $allFolders[] = $row;
    }
}

// Handle folder archiving (AJAX)
if (isset($_POST['archive_folder_id'])) {
    $folderId = (int)$_POST['archive_folder_id'];
    $userId = $_SESSION['user_id'];
    $response = ["success" => false, "message" => "Unknown error."];
    $stmt = $pdo->prepare("UPDATE folders SET is_archived = 1 WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $folderId, $userId);
    if ($stmt->execute()) {
        $response = ["success" => true, "message" => "Folder archived successfully."];
    } else {
        $response = ["success" => false, "message" => "Failed to archive folder."];
    }
    header('Content-Type: application/json');
    echo json_encode($response);
    exit();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Flashcard Dashboard - LearnMate</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/theme.css">
  <style>
    :root {
      --primary: #7F56D9;
      --primary-light: #9E77ED;
      --primary-dark: #6941C6;
      --primary-darker: #53389E;
      --secondary: #36BFFA;
      --success: #32D583;
      --warning: #FDB022;
      --danger: #F97066;
      --text-dark: #101828;
      --text-medium: #475467;
      --text-light: #98A2B3;
      --bg-light: #F9FAFB;
      --bg-white: #FFFFFF;
      --border-light: #EAECF0;
      --shadow-xs: 0 1px 2px rgba(16, 24, 40, 0.05);
      --shadow-sm: 0 1px 3px rgba(16, 24, 40, 0.1), 0 1px 2px rgba(16, 24, 40, 0.06);
      --shadow-md: 0 4px 6px -1px rgba(16, 24, 40, 0.1), 0 2px 4px -1px rgba(16, 24, 40, 0.06);
      --shadow-lg: 0 10px 15px -3px rgba(16, 24, 40, 0.1), 0 4px 6px -2px rgba(16, 24, 40, 0.05);
      --shadow-xl: 0 20px 25px -5px rgba(16, 24, 40, 0.1), 0 10px 10px -5px rgba(16, 24, 40, 0.04);
      --radius-sm: 6px;
      --radius-md: 8px;
      --radius-lg: 12px;
      --radius-xl: 16px;
      --radius-full: 9999px;
      --space-xs: 4px;
      --space-sm: 8px;
      --space-md: 16px;
      --space-lg: 24px;
      --space-xl: 32px;
      --transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Inter', sans-serif;
    }

    body {
      background-color: var(--bg-light);
      color: var(--text-dark);
      line-height: 1.5;
    }

    .app-container {
      display: flex;
      min-height: 100vh;
    }

    .sidebars {
      display: flex;
      flex-direction: column;
      width: 280px;
      min-width: 280px;
      height: 100vh;
      background-color: var(--bg-white);
      border-right: 1px solid var(--border-light);
      padding: var(--space-xl);
      position: sticky;
      top: 0;
      overflow-y: auto;
      z-index: 10;
    }

    .sidebar-headers {
      display: flex;
      flex-direction: row;
      align-items: center;
      gap: var(--space-sm);
      margin-bottom: var(--space-xl);
      padding-bottom: var(--space-md);
      border-bottom: 1px solid var(--border-light);
      flex-shrink: 0;
    }

    .logo {
      width: 32px;
      height: 32px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      border-radius: var(--radius-md);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 600;
    }
    
    .app-name {
      font-weight: 600;
      font-size: 18px;
      color: var(--text-dark);
    }

    .nav-section {
      margin-bottom: var(--space-xl);
      flex-shrink: 0;
    }

    .nav-section {
    margin-bottom: var(--space-xl);
    }

    .section-title {
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: 0.05em;
      color: var(--text-light);
      margin-bottom: var(--space-sm);
      font-weight: 600;
    }

    .nav-item {
      display: flex;
      align-items: center;
      gap: var(--space-sm);
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-xs);
      text-decoration: none;
      color: var(--text-medium);
      font-weight: 500;
      transition: var(--transition);
    }

    .nav-item:hover {
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }

    .nav-item.active {
      background-color: #F9F5FF;
      color: var(--primary-dark);
      font-weight: 600;
    }

    .nav-item i {
      width: 20px;
      text-align: center;
    }

    .dropdown {
      position: relative;
      margin-bottom: var(--space-xs);
    }
    
    .dropdown-toggle {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: var(--space-sm) var(--space-md);
      border-radius: var(--radius-md);
      cursor: pointer;
      color: var(--text-medium);
      font-weight: 500;
      transition: var(--transition);
    }
    
    .dropdown-toggle:hover {
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }
    
    .dropdown-menu {
      display: none;
      position: relative;
      background-color: var(--bg-white);
      border-radius: var(--radius-md);
      padding: var(--space-sm);
      margin-top: var(--space-xs);
      box-shadow: var(--shadow-sm);
    }
    
    .dropdown-menu.show {
      display: block;
    }
    
    .dropdown-item {
      display: flex;
      align-items: center;
      padding: var(--space-sm) var(--space-md);
      text-decoration: none;
      color: var(--text-medium);
      border-radius: var(--radius-sm);
      transition: var(--transition);
    }
    
    .dropdown-item:hover {
      background-color: #F9F5FF;
      color: var(--primary-dark);
    }
    
    .profile-initial {
      width: 32px;
      height: 32px;
      background-color: var(--primary-light);
      color: white;
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 600;
      font-size: 14px;
      margin-right: var(--space-sm);
    }

    .class-name {
      font-weight: 500;
      font-size: 14px;
    }

    .class-section {
      font-size: 12px;
      color: var(--text-light);
    }

    .main-content {
      flex: 1;
      padding: var(--space-md);
      position: relative;
      background-color: var(--bg-light);
      width: 100%;
    }

    .header {
      background-color: var(--bg-white);
      padding: var(--space-md);
      position: sticky;
      top: 0;
      z-index: 10;
      box-shadow: var(--shadow-sm);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .header-title {
      font-weight: 600;
      font-size: 18px;
    }

    .header-actions {
      display: flex;
      gap: var(--space-sm);
    }

    .header-btn {
      width: 36px;
      height: 36px;
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      background-color: var(--bg-light);
      border: none;
      color: var(--text-medium);
      cursor: pointer;
    }

    .create-flashcards {
      background: var(--bg-white);
      padding: var(--space-lg);
      border-radius: var(--radius-lg);
      margin-bottom: var(--space-lg);
      box-shadow: var(--shadow-sm);
      border-left: 4px solid var(--success);
      transition: var(--transition);
    }

    .create-flashcards:hover {
      box-shadow: var(--shadow-md);
    }

    .create-flashcards h2 {
      margin-top: 0;
      color: var(--success);
      margin-bottom: var(--space-md);
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .flashcard-options {
      display: flex;
      gap: var(--space-md);
      margin-top: var(--space-md);
    }

    .btn {
      background-color: var(--primary);
      color: var(--bg-white);
      padding: var(--space-sm) var(--space-md);
      border: none;
      border-radius: var(--radius-md);
      cursor: pointer;
      font-weight: 600;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .btn:hover {
      background-color: var(--primary-dark);
      transform: translateY(-2px);
    }

    .btn-manual {
      background-color: var(--primary);
    }

    .btn-select {
      background-color: var(--secondary);
    }

    .btn-manual:hover {
      background-color: var(--primary-dark);
    }

    .upload-form {
      background: var(--bg-white);
      padding: var(--space-lg);
      border-radius: var(--radius-lg);
      margin-bottom: var(--space-lg);
      box-shadow: var(--shadow-sm);
      border-left: 4px solid var(--primary);
      transition: var(--transition);
    }

    .upload-form:hover {
      box-shadow: var(--shadow-md);
    }

    .upload-form h2 {
      margin-top: 0;
      color: var(--primary);
      margin-bottom: var(--space-md);
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    input[type="file"] {
      width: 100%;
      padding: var(--space-sm);
      border: 2px dashed rgba(127, 86, 217, 0.3);
      border-radius: var(--radius-md);
      margin-bottom: var(--space-md);
      transition: var(--transition);
    }

    .alert, .success {
      padding: var(--space-sm);
      border-radius: var(--radius-md);
      margin: var(--space-sm) 0;
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .alert {
      background-color: rgba(249, 112, 102, 0.1);
      color: var(--danger);
      border-left: 4px solid var(--danger);
    }

    .success {
      background-color: rgba(50, 213, 131, 0.1);
      color: var(--success);
      border-left: 4px solid var(--success);
    }

    input[type="file"]:hover {
      border-color: var(--primary);
    }

    .modal {
      display: none;
      position: fixed;
      z-index: 1000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      overflow: hidden;
      backdrop-filter: blur(5px);
      animation: fadeIn 0.3s ease;
    }

    .modal-content {
      background-color: var(--bg-white);
      position: absolute;
      left: 50%;
      top: 50%;
      transform: translate(-50%, -50%);
      padding: var(--space-lg);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow-xl);
      width: 85%;
      max-width: 800px;
      max-height: 85vh;
      overflow-y: auto;
      border: 1px solid var(--border-light);
    }

    .modal-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: var(--space-lg);
      padding-bottom: var(--space-md);
      border-bottom: 1px solid var(--border-light);
    }

    .modal-header h2 {
      margin: 0;
      color: var(--primary);
      font-size: 1.5rem;
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .close-btn {
      background: none;
      border: none;
      font-size: 28px;
      color: var(--text-light);
      cursor: pointer;
      padding: var(--space-xs);
      transition: var(--transition);
    }

    .close-btn:hover {
      color: var(--danger);
      transform: rotate(90deg);
    }

    .pdf-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(220px, 1fr));
      gap: var(--space-md);
      margin-top: var(--space-md);
    }

    .pdf-card {
      background: var(--bg-white);
      border-radius: var(--radius-lg);
      padding: var(--space-lg);
      transition: var(--transition);
      border: 1px solid var(--border-light);
      text-align: center;
      cursor: pointer;
      position: relative;
      overflow: hidden;
      box-shadow: var(--shadow-sm);
    }

    .pdf-card:hover {
      transform: translateY(-5px);
      box-shadow: var(--shadow-md);
      border-color: var(--primary);
    }

    .pdf-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 4px;
      background: var(--primary);
      transform: scaleX(0);
      transform-origin: left;
      transition: transform 0.3s ease;
    }

    .pdf-card:hover::before {
      transform: scaleX(1);
    }

    .pdf-icon-large {
      font-size: 48px;
      color: var(--danger);
      margin-bottom: var(--space-md);
      transition: var(--transition);
    }

    .pdf-card:hover .pdf-icon-large {
      transform: scale(1.1);
    }

    .pdf-name {
      font-weight: 600;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-bottom: var(--space-xs);
      color: var(--text-dark);
    }

    .pdf-size {
      font-size: 13px;
      color: var(--text-light);
      margin-top: var(--space-xs);
    }

    .search-container {
      position: relative;
      margin-bottom: var(--space-md);
    }

    .search-input {
      width: 100%;
      padding: var(--space-sm) var(--space-sm) var(--space-sm) var(--space-xl);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      font-size: 14px;
      transition: var(--transition);
      background-color: var(--bg-light);
    }

    .search-input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(127, 86, 217, 0.2);
    }

    .search-icon {
      position: absolute;
      left: var(--space-md);
      top: 50%;
      transform: translateY(-50%);
      color: var(--text-light);
    }

    .empty-state {
      text-align: center;
      padding: var(--space-xl) var(--space-md);
      color: var(--text-light);
      grid-column: 1 / -1;
    }

    .empty-icon {
      font-size: 60px;
      margin-bottom: var(--space-md);
      color: var(--border-light);
    }

    .spinner {
      display: none;
      width: 40px;
      height: 40px;
      margin: var(--space-lg) auto;
      border: 4px solid var(--border-light);
      border-radius: 50%;
      border-top: 4px solid var(--primary);
      animation: spin 1s linear infinite;
    }

    @keyframes spin {
      0% { transform: rotate(0deg); }
      100% { transform: rotate(360deg); }
    }

    .bottom-nav-container {
      position: fixed;
      bottom: 0;
      left: 0;
      right: 0;
      z-index: 20;
    }

    .bottom-nav {
      background-color: var(--bg-white);
      display: flex;
      justify-content: space-around;
      align-items: center;
      padding: var(--space-sm) 0;
      box-shadow: var(--shadow-sm);
      position: relative;
      height: 60px;
    }

    .nav-item-mobile {
      display: flex;
      flex-direction: column;
      align-items: center;
      text-decoration: none;
      color: var(--text-light);
      font-size: 10px;
      gap: 4px;
      z-index: 1;
      width: 25%;
    }

    .nav-item-mobile i {
      font-size: 20px;
    }

    .nav-item-mobile.active {
      color: var(--primary);
    }

    .fab-container {
      position: absolute;
      left: 50%;
      top: -20px;
      transform: translateX(-50%);
      width: 56px;
      height: 56px;
      display: flex;
      justify-content: center;
      align-items: center;
    }

    .fab {
      width: 56px;
      height: 56px;
      background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
      border-radius: var(--radius-full);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      box-shadow: var(--shadow-lg);
      border: none;
      cursor: pointer;
      z-index: 2;
      text-decoration: none;
    }

    .fab i {
      font-size: 24px;
    }

    /* New styles for PDF management */
    .pdf-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: var(--space-sm);
      border-bottom: 1px solid var(--border-light);
    }

    .pdf-item:last-child {
      border-bottom: none;
    }

    .delete-btn {
      background-color: var(--danger);
      color: white;
      border: none;
      padding: var(--space-xs) var(--space-sm);
      border-radius: var(--radius-sm);
      cursor: pointer;
      transition: var(--transition);
    }

    .delete-btn:hover {
      background-color: #d32f2f;
    }

    /* Manual flashcard creation styles */
    .manual-flashcard-container {
      background: var(--bg-white);
      padding: var(--space-lg);
      border-radius: var(--radius-lg);
      margin-bottom: var(--space-lg);
      box-shadow: var(--shadow-sm);
      border-left: 4px solid var(--primary);
      transition: var(--transition);
    }

    .manual-flashcard-container:hover {
      box-shadow: var(--shadow-md);
    }

    .manual-flashcard-container h2 {
      margin-top: 0;
      color: var(--primary);
      margin-bottom: var(--space-md);
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .manual-flashcard-form {
      display: flex;
      flex-direction: column;
      gap: var(--space-md);
    }

    .form-group {
      margin-bottom: var(--space-md);
    }

    label {
      display: block;
      margin-bottom: var(--space-sm);
      font-weight: 600;
      color: var(--text-dark);
    }

    input[type="text"],
    textarea {
      width: 100%;
      padding: var(--space-sm);
      border: 1px solid var(--border-light);
      border-radius: var(--radius-md);
      font-family: inherit;
      font-size: 0.95rem;
      transition: var(--transition);
      background-color: var(--bg-white);
      color: var(--text-dark);
    }

    textarea {
      min-height: 150px;
      resize: vertical;
    }

    input[type="text"]:focus,
    textarea:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(127, 86, 217, 0.2);
    }

    .submit-btn {
      background-color: var(--secondary);
      color: var(--bg-white);
      padding: var(--space-sm) var(--space-md);
      border: none;
      border-radius: var(--radius-md);
      cursor: pointer;
      font-weight: 600;
      transition: var(--transition);
      display: inline-flex;
      align-items: center;
      gap: var(--space-sm);
    }

    .submit-btn:hover {
      background-color: var(--primary-dark); /* fallback if --secondary-dark is not defined */
      transform: translateY(-2px);
    }

    /* Flashcard folders sidebar */
    .flashcard-sidebar {
      width: 250px;
      background-color: var(--bg-white);
      box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
      padding: 0;
      height: 100%;
      overflow: hidden;
      transition: background-color var(--transition), border-color var(--transition);
      position: relative;
      display: flex;
      flex-direction: column;
    }

    .flashcard-sidebar .sidebar-header {
      display: flex;
      align-items: center;
      position: sticky;
      top: 0;
      z-index: 2;
      background: var(--bg-white);
      margin-bottom: 0;
      padding: 8px 20px 4px 20px;
      border-bottom: 1px solid var(--border-light);
      min-height: 36px;
      /* Add any other folder sidebar-specific styles here */
    }

    .folder-list-scroll {
      flex: 1 1 auto;
      overflow-y: auto;
      padding: 0 20px 0 20px;
      margin: 0;
      max-height: calc(100vh - 60px);
    }

    .folder-list {
      list-style: none;
      padding-left: 0;
      margin-top: 0;
    }

    .add-folder-btn {
      background-color: var(--primary);
      color: var(--bg-white);
      border: none;
      border-radius: 50%;
      width: 32px;
      height: 32px;
      display: flex;
      align-items: center;
      justify-content: center;
      cursor: pointer;
      transition: var(--transition);
      font-size: 1.1rem;
      margin-left: auto;
      margin-top: -2px; /* nudge button slightly upward */
    }

    .add-folder-btn i {
      font-size: 1.1rem;
    }

    .add-folder-btn:hover {
      background-color: var(--primary-light);
      transform: rotate(90deg);
    }

    @media (min-width: 768px) {
      .flashcard-sidebar {
        max-height: 80vh;
        overflow-y: auto;
      }
    }

    .sidebar-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
      padding-bottom: 10px;
      border-bottom: 1px solid var(--border-light);
    }

    .sidebar-header h3 {
      font-size: 1.1rem;
      font-weight: 600;
      color: var(--text-dark);
      margin: 0;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .folder-list {
      list-style: none;
      padding-left: 0;
      margin-top: 0; /* remove extra space so list starts right below header */
    }

    .folder-item {
      margin-bottom: 3px;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0;
      border-radius: 6px;
      transition: var(--transition);
    }

    .folder-item:hover {
      background-color: rgba(67, 97, 238, 0.05);
    }

    .folder-link {
      flex: 1;
      padding: 10px 12px;
      border-radius: 6px;
      color: var(--text-dark);
      text-decoration: none;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.95rem;
      border: none;
      background: none;
      cursor: pointer;
    }

    .folder-link:hover {
      background-color: rgba(67, 97, 238, 0.1);
    }

    .folder-link.active {
      background-color: var(--primary);
      color: var(--bg-white);
    }

    .folder-link i {
      font-size: 0.9rem;
    }

    .folder-dropdown {
      position: relative;
      margin-right: 8px;
    }

    .folder-dropdown-btn {
      background: none;
      border: none;
      color: var(--text-light);
      cursor: pointer;
      padding: 8px;
      font-size: 1rem;
      transition: var(--transition);
      border-radius: 4px;
      opacity: 0.6;
      visibility: visible;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
    }

    .pdf-card:hover .pdf-dropdown-btn {
      opacity: 1;
      color: var(--text-dark);
    }

    .pdf-dropdown-btn:hover {
      color: var(--text-dark);
      background-color: rgba(0, 0, 0, 0.05);
      opacity: 1;
    }

    .folder-dropdown-content {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      background-color: var(--bg-white);
      min-width: 140px;
      box-shadow: var(--shadow-md);
      z-index: 1000;
      border-radius: 8px;
      overflow: hidden;
      transition: background-color var(--transition), box-shadow var(--transition);
      border: 1px solid var(--border-light);
      margin-top: 4px;
    }

    .folder-dropdown-content.show {
      display: block;
      animation: fadeIn 0.2s;
    }

    .folder-dropdown-content a {
      color: var(--text-dark);
      padding: 10px 15px;
      text-decoration: none;
      display: block;
      font-size: 0.9rem;
      transition: var(--transition);
    }

    .folder-dropdown-content a:hover {
      background-color: var(--bg-light);
      color: var(--primary);
    }

    .folder-dropdown-content a i {
      margin-right: 8px;
      width: 16px;
      text-align: center;
    }

    /* Flashcard list styles */
    .flashcards-container {
      margin-top: 30px;
      background: var(--bg-white);
      padding: 25px;
      border-radius: 10px;
      box-shadow: var(--shadow-md);
      width: 100%;
      transition: background-color var(--transition), box-shadow var(--transition);
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
      background: var(--bg-light);
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
      color: var(--text-dark);
    }

    .flashcard-definition {
      word-break: break-word;
      color: var(--text-dark);
    }

    .no-flashcards {
      margin-top: 30px;
      text-align: center;
      color: var(--text-light);
      font-style: italic;
      padding: 20px;
      background: var(--bg-light);
      border-radius: 8px;
    }

    .flashcard-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
    }

    .dropdown {
      position: relative;
      display: inline-block;
    }

    .dropdown-btn {
      background: none;
      border: none;
      color: var(--text-light);
      cursor: pointer;
      padding: 5px;
      font-size: 1.2rem;
      transition: var(--transition);
    }

    .dropdown-btn:hover {
      color: var(--text-dark);
    }

    .dropdown-content {
      display: none;
      position: absolute;
      right: 0;
      background-color: var(--bg-white);
      min-width: 150px;
      box-shadow: var(--shadow-md);
      z-index: 1;
      border-radius: 8px;
      overflow: hidden;
      transition: background-color var(--transition), box-shadow var(--transition);
    }

    .dropdown-content a {
      color: var(--text-dark);
      padding: 10px 15px;
      text-decoration: none;
      display: block;
      font-size: 0.9rem;
      transition: var(--transition);
    }

    .dropdown-content a:hover {
      background-color: var(--bg-light);
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

    /* Modal styles */
    .folder-modal {
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

    .folder-modal-content {
      background-color: var(--bg-white);
      margin: 15% auto;
      padding: 25px;
      border-radius: 12px;
      width: 90%;
      max-width: 400px;
      box-shadow: var(--shadow-lg);
      animation: modalFadeIn 0.3s ease-out;
      transition: background-color var(--transition), box-shadow var(--transition);
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

    .folder-modal-header {
      margin-bottom: 15px;
    }

    .folder-modal-header h3 {
      margin: 0;
      color: var(--text-dark);
      font-size: 1.2rem;
      font-weight: 600;
    }

    .folder-modal-body p {
      color: var(--text-light);
      font-size: 0.9rem;
      margin-bottom: 20px;
    }

    .folder-modal-body strong {
      color: var(--text-dark);
    }

    .folder-form-actions {
      display: flex;
      justify-content: flex-end;
      gap: 10px;
      margin-top: 25px;
    }

    .folder-btn {
      padding: 10px 18px;
      border-radius: 6px;
      cursor: pointer;
      font-weight: 500;
      transition: var(--transition);
      font-size: 0.9rem;
    }

    .folder-cancel-btn {
      background: none;
      color: var(--text-light);
      border: 1px solid var(--border-light);
    }

    .folder-cancel-btn:hover {
      background: var(--bg-light);
    }

    .folder-primary-btn {
      background-color: var(--primary);
      color: var(--bg-white);
    }

    .folder-primary-btn:hover {
      background-color: var(--primary-light);
    }

    /* Edit Flashcard Modal */
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
      background-color: var(--bg-white);
      margin: 10% auto;
      padding: 25px;
      border-radius: 12px;
      width: 90%;
      max-width: 600px;
      box-shadow: var(--shadow-lg);
      animation: modalFadeIn 0.3s ease-out;
      transition: background-color var(--transition), box-shadow var(--transition);
    }

    .edit-modal-header {
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      border-bottom: 1px solid var(--border-light);
      padding-bottom: 10px;
    }

    .edit-modal-header h3 {
      margin: 0;
      color: var(--text-dark);
      font-size: 1.3rem;
      font-weight: 600;
    }

    .close-edit-modal {
      color: var(--text-light);
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

    /* Layout adjustments */
    .manual-flashcard-wrapper {
      display: flex;
      gap: 20px;
    }

    .manual-flashcard-content {
      flex: 1;
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

    @media (max-width: 768px) {
      .manual-flashcard-wrapper {
        flex-direction: column;
      }
      
      .flashcard-sidebar {
        width: 100%;
        height: auto;
        position: relative;
        padding: 15px;
      }
      
      .manual-flashcard-content {
        width: 100%;
      }
    }

    @media (min-width: 640px) {
      .main-content {
        padding: var(--space-lg);
      }
    }

   @media (min-width: 768px) {
  body {
    padding-bottom: 0;
  }
  
  .bottom-nav-container {
    display: none;
  }
  
  .sidebar {
    display: flex;
    flex-direction: column;
  }
  
  .main-content {
    width: calc(100% - 260px);
    padding: var(--space-xl);
  }
  
  .header {
    display: none;
  }
}

    @media (min-width: 1024px) {
      .dashboard {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
      }
    }

    .flashcard-list-sidebar {
      background-color: var(--bg-white);
      box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
      padding: 0;
      height: 80vh;
      overflow: hidden;
      transition: background-color var(--transition), border-color var(--transition);
      border-left: 1px solid var(--border-light);
      border-right: none;
      position: relative;
      min-width: 320px;
      max-width: 400px;
      width: 100%;
      display: flex;
      flex-direction: column;
      z-index: 2;
    }
    .flashcards-scroll-area {
      flex: 1 1 auto;
      overflow-y: auto;
      padding: 0 20px 0 20px;
      margin: 0;
      max-height: calc(100vh - 60px);
      min-height: 0;
      display: flex;
      flex-direction: column;
    }
    .flashcards-sticky-header {
      position: sticky;
      top: 0;
      z-index: 3;
      background: var(--bg-white);
      padding-bottom: 10px;
      margin-bottom: 10px;
      border-bottom: 1px solid var(--border-light);
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .flashcards-container {
      margin-top: 0;
      background: var(--bg-white);
      padding: 0;
      border-radius: 10px;
      box-shadow: none;
      width: 100%;
      transition: background-color var(--transition), box-shadow var(--transition);
      flex: 1 1 auto;
    }
    @media (max-width: 1200px) {
      .flashcard-list-sidebar {
        min-width: 220px;
        max-width: 100vw;
      }
    }
    @media (max-width: 900px) {
      .manual-flashcard-wrapper {
        flex-direction: column;
      }
      .flashcard-list-sidebar {
        width: 100%;
        min-width: 0;
        max-width: 100vw;
        margin-top: 20px;
        border-left: none;
        border-top: 1px solid var(--border-light);
        box-shadow: none;
      }
    }

    .btn-fit {
      width: auto !important;
      min-width: 0 !important;
      padding-left: 18px;
      padding-right: 18px;
      display: inline-flex;
      justify-content: center;
    }

    .form-actions-row {
      display: flex;
      align-items: center; /* Ensures vertical alignment */
      gap: 12px;
      margin-top: 16px;
    }

    .btn, .submit-btn {
      height: 40px;
      display: flex;
      align-items: center;
      padding: 0 18px;
      font-size: 1rem;
      box-sizing: border-box;
    }

    .folder-dropdown-content {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      background-color: var(--bg-white);
      min-width: 140px;
      box-shadow: var(--shadow-md);
      z-index: 1000;
      border-radius: 8px;
      overflow: hidden;
      transition: background-color var(--transition), box-shadow var(--transition);
    }
    .folder-dropdown-content.show {
      display: block;
      animation: fadeIn 0.2s;
    }

    .bordered-form {
      border: 2px solid var(--primary-light);
      border-radius: 12px;
      padding: 32px 24px;
      background: var(--bg-white);
      box-shadow: 0 2px 8px rgba(127,86,217,0.04);
      margin-bottom: 24px;
    }

    /* Improved folder layout styles */
    .folder-item {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0;
      border-radius: 6px;
      transition: var(--transition);
      margin-bottom: 3px;
    }

    .folder-item:hover {
      background-color: rgba(67, 97, 238, 0.05);
    }

    .folder-link {
      flex: 1;
      padding: 10px 12px;
      border-radius: 6px;
      color: var(--text-dark);
      text-decoration: none;
      transition: var(--transition);
      display: flex;
      align-items: center;
      gap: 10px;
      font-size: 0.95rem;
      border: none;
      background: none;
      cursor: pointer;
      min-width: 0;
    }

    .folder-link:hover {
      background-color: rgba(67, 97, 238, 0.1);
    }

    .folder-link.active {
      background-color: var(--primary);
      color: var(--bg-white);
    }

    .folder-link i {
      font-size: 0.9rem;
      flex-shrink: 0;
    }

    .folder-dropdown {
      position: relative;
      margin-right: 8px;
      flex-shrink: 0;
    }

    .folder-dropdown-btn {
      background: none;
      border: none;
      color: var(--text-light);
      cursor: pointer;
      padding: 8px;
      font-size: 1rem;
      transition: var(--transition);
      border-radius: 4px;
      opacity: 0.6;
      visibility: visible;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
    }

    .folder-item:hover .folder-dropdown-btn {
      opacity: 1;
      visibility: visible;
    }

    .folder-dropdown-btn:hover {
      color: var(--text-dark);
      background-color: rgba(0, 0, 0, 0.05);
      opacity: 1;
    }

    .folder-dropdown-content {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      background-color: var(--bg-white);
      min-width: 140px;
      box-shadow: var(--shadow-md);
      z-index: 1000;
      border-radius: 8px;
      overflow: hidden;
      transition: background-color var(--transition), box-shadow var(--transition);
      border: 1px solid var(--border-light);
      margin-top: 4px;
    }

    .folder-dropdown-content.show {
      display: block;
      animation: fadeIn 0.2s;
    }

    .folder-dropdown-content a {
      color: var(--text-dark);
      padding: 10px 15px;
      text-decoration: none;
      display: block;
      font-size: 0.9rem;
      transition: var(--transition);
    }

    .folder-dropdown-content a:hover {
      background-color: var(--bg-light);
      color: var(--primary);
    }

    .folder-dropdown-content a i {
      margin-right: 8px;
      width: 16px;
      text-align: center;
    }

    /* PDF Card Dropdown Styles */
    .pdf-card-header {
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      position: relative;
      margin-bottom: var(--space-md);
    }

    .pdf-dropdown {
      position: relative;
      z-index: 10;
    }

    .pdf-dropdown-btn {
      background: none;
      border: none;
      color: var(--text-light);
      cursor: pointer;
      padding: 8px;
      font-size: 1rem;
      transition: var(--transition);
      border-radius: 4px;
      opacity: 0;
      visibility: hidden;
      display: flex;
      align-items: center;
      justify-content: center;
      width: 32px;
      height: 32px;
    }

    .pdf-card:hover .pdf-dropdown-btn {
      opacity: 1;
      visibility: visible;
    }

    .pdf-dropdown-btn:hover {
      color: var(--text-dark);
      background-color: rgba(0, 0, 0, 0.05);
    }

    .pdf-dropdown-content {
      display: none;
      position: absolute;
      right: 0;
      top: 100%;
      background-color: var(--bg-white);
      min-width: 120px;
      box-shadow: var(--shadow-md);
      z-index: 1000;
      border-radius: 8px;
      overflow: hidden;
      transition: background-color var(--transition), box-shadow var(--transition);
      border: 1px solid var(--border-light);
      margin-top: 4px;
    }

    .pdf-dropdown-content.show {
      display: block;
      animation: fadeIn 0.2s;
    }

    .pdf-dropdown-content a {
      color: var(--text-dark);
      padding: 10px 15px;
      text-decoration: none;
      display: block;
      font-size: 0.9rem;
      transition: var(--transition);
    }

    .pdf-dropdown-content a:hover {
      background-color: var(--bg-light);
      color: var(--danger);
    }

    .pdf-dropdown-content a i {
      margin-right: 8px;
      width: 16px;
      text-align: center;
    }
  </style>
</head>
<body data-theme="<?php echo htmlspecialchars($theme); ?>">
  <div class="app-container">
    <!-- Sidebar - Desktop -->
    <aside class="sidebars">
      <div class="sidebar-headers">
        <div class="logo">LM</div>
        <div class="app-name">LearnMate</div>
      </div>
      
      <div class="nav-section">
        <div class="section-title">Menu</div>
        <a href="../teacher_dashboard.php" class="nav-item">
          <i class="fas fa-home"></i>
          <span>Dashboard</span>
        </a>
        
        <!-- Classes Dropdown -->
        <div class="dropdown">
          <div class="dropdown-toggle" onclick="toggleDropdown(this)">
            <div style="display: flex; align-items: center; gap: var(--space-sm);">
              <i class="fas fa-chalkboard-teacher"></i>
              <span>My Classes</span>
            </div>
            <i class="fas fa-chevron-down"></i>
          </div>
          <div class="dropdown-menu">
            <?php if (empty($classes)): ?>
              <div class="dropdown-item" style="padding: var(--space-sm);">
                No classes yet
              </div>
            <?php else: ?>
              <?php foreach ($classes as $class): ?>
                <a href="../class_details.php?id=<?php echo $class['id']; ?>" class="dropdown-item">
                  <div style="display: flex; align-items: center;">
                    <div class="profile-initial">
                      <?php 
                        $words = explode(' ', $class['class_name']);
                        $initials = '';
                        foreach ($words as $word) {
                          $initials .= strtoupper(substr($word, 0, 1));
                          if (strlen($initials) >= 2) break;
                        }
                        echo $initials;
                      ?>
                    </div>
                    <div>
                      <div class="class-name"><?php echo htmlspecialchars($class['class_name']); ?></div>
                      <div class="class-section"><?php echo htmlspecialchars($class['section'] ?? ''); ?></div>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            <?php endif; ?>
            <a href="../create_class.php" class="dropdown-item" style="margin-top: var(--space-sm); color: var(--primary);">
              <i class="fas fa-plus"></i> Create New Class
            </a>
          </div>
        </div>
        
        <a href="../teacher_group.php" class="nav-item">
          <i class="fas fa-users"></i>
          <span>Groups</span>
        </a>
      </div>
      
      <div class="nav-section">
        <div class="section-title">Content</div>
        <a href="teacher_flashcard.php" class="nav-item active">
          <i class="fas fa-layer-group"></i>
          <span>Flashcard Decks</span>
        </a>
        <a href="../create_quiz.php" class="nav-item">
          <i class="fas fa-question-circle"></i>
          <span>Create Quiz</span>
        </a>
      </div>
      
      <div class="nav-section">
        <div class="section-title">Settings</div>
        <a href="../settings.php" class="nav-item">
          <i class="fas fa-cog"></i>
          <span>Settings</span>
        </a>
        <a href="../logout.php" class="nav-item">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </div>
    </aside>
    <!-- Main Content Area -->
    <main class="main-content">
      <!-- Mobile Header -->
      <header class="header">
        <h1 class="header-title">Flashcard Dashboard</h1>
        <div class="header-actions">
          <button class="header-btn" onclick="window.history.back()">
            <i class="fas fa-arrow-left"></i>
          </button>
          <button class="header-btn">
            <i class="fas fa-bell"></i>
          </button>
        </div>
      </header>

      <h1 class="section-title-lg">
        <i class="fas fa-book-open"></i>
        <span>Flashcard Dashboard</span>
      </h1>
      
      <!-- Manual Flashcard Creation Section -->
      <div class="manual-flashcard-container">
        <h2><i class="fas fa-keyboard"></i> Create Flashcard</h2>
        
        <div class="manual-flashcard-wrapper">
          <!-- Flashcard Folders Sidebar (Left) -->
          <div class="flashcard-sidebar">
            <div class="sidebar-header">
              <h3><i class="fas fa-folder"></i> My Folders</h3>
              <button class="add-folder-btn" title="Add New Folder">
                <i class="fas fa-plus"></i>
              </button>
            </div>
            <div class="folder-list-scroll">
              <ul class="folder-list">
                <li class="folder-item">
                  <a href="teacher_flashcard.php" class="folder-link<?php echo !$activeFolderId ? ' active' : '' ?>">
                    <i class="fas fa-inbox"></i> All Flashcards
                  </a>
                </li>
                <?php foreach ($allFolders as $folder): ?>
                  <li class="folder-item">
                    <a href="teacher_flashcard.php?folder_id=<?php echo $folder['id']; ?>" 
                       class="folder-link<?php echo $activeFolderId == $folder['id'] ? ' active' : '' ?>">
                      <i class="fas fa-folder"></i> <?php echo htmlspecialchars($folder['name']); ?>
                    </a>
                    <div class="dropdown folder-dropdown">
                      <button class="dropdown-btn folder-dropdown-btn" title="More actions" tabindex="0">
                        <i class="fas fa-ellipsis-v"></i>
                      </button>
                      <div class="dropdown-content folder-dropdown-content">
                        <a href="#" class="delete-folder-btn" data-id="<?php echo $folder['id']; ?>">
                          <i class="fas fa-trash"></i> Delete
                        </a>
                        <a href="#" class="archive-folder-btn" data-id="<?php echo $folder['id']; ?>">
                          <i class="fas fa-archive"></i> Archive
                        </a>
                      </div>
                    </div>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          </div>
          
          <!-- Manual Flashcard Content (Center) -->
          <div class="manual-flashcard-content">
            <?php if ($activeFolderName): ?>
              <div class="active-folder-indicator">
                <i class="fas fa-folder-open"></i>
                <strong>Active Folder:</strong> <?php echo htmlspecialchars($activeFolderName); ?>
              </div>
            <?php endif; ?>

            <?php echo $errorMessage; ?>
            <?php echo $successMessage; ?>

            <form class="manual-flashcard-form bordered-form" method="POST" action="teacher_flashcard.php">
              <div class="form-group">
                <label for="term"><i class="fas fa-font"></i> Term</label>
                <input type="text" id="term" name="term" placeholder="Enter the term or concept" required>
              </div>

              <div class="form-group">
                <label for="definition"><i class="fas fa-info-circle"></i> Definition</label>
                <textarea id="definition" name="definition" placeholder="Enter the definition or explanation" required></textarea>
              </div>
              <div class="form-actions-row">
                <button class="submit-btn btn-select" id="selectBtn" type="button">
                  <i class="fas fa-highlighter"></i> From Select
                </button>
                <button type="submit" class="submit-btn">
                  <i class="fas fa-save"></i> Create Flashcard
                </button>
              </div>
            </form>
            <?php if ($activeFolderId && !empty($flashcards)): ?>
              <form method="POST" action="export_pdf.php" target="_blank" class="export-form">
                <input type="hidden" name="folder_id" value="<?php echo $activeFolderId; ?>">
                <button type="submit" class="btn btn-fit">
                  <i class="fas fa-file-pdf"></i> Download PDF Review Sheet
                </button>
              </form>
            <?php endif; ?>
          </div>

          <!-- Flashcard List Sidebar (Right) -->
          <div class="flashcard-list-sidebar">
            <div class="flashcards-scroll-area">
              <div class="flashcards-sticky-header">
                <h2><i class="fas fa-layer-group"></i> Flashcards in this folder</h2>
              </div>
              <div class="flashcards-container">
                <?php if ($activeFolderId && !empty($flashcards)): ?>
                  <div class="flashcards-list">
                    <?php foreach ($flashcards as $card): ?>
                      <div class="flashcard" id="flashcard-<?php echo $card['id']; ?>">
                        <div class="flashcard-header">
                          <div class="flashcard-term">
                            <strong>Term:</strong> <?php echo htmlspecialchars($card['term_text']); ?>
                          </div>
                          <div class="dropdown">
                            <button class="dropdown-btn">
                              <i class="fas fa-ellipsis-v"></i>
                            </button>
                            <div class="dropdown-content">
                              <a href="#" class="edit-flashcard" data-id="<?php echo $card['id']; ?>" data-term="<?php echo htmlspecialchars($card['term_text']); ?>" data-definition="<?php echo htmlspecialchars($card['definition_text']); ?>">
                                <i class="fas fa-edit"></i> Edit
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
            </div>
          </div>
        </div>
      </div>

     
      
      <!-- PDF Upload Form -->
      <div class="upload-form">
        <h2><i class="fas fa-file-upload"></i> Upload PDF File</h2>
        <?php echo $uploadMessage; ?>
        <form action="" method="post" enctype="multipart/form-data">
          <input type="file" name="pdf_file" accept=".pdf" required>
          <button type="submit" class="btn"><i class="fas fa-upload"></i> Upload PDF</button>
        </form>
        <p> <br> </p>
        <button class="btn" id="managePdfBtn"><i class="fas fa-file-pdf"></i> Manage PDFs</button>
      </div>
    </main>
  </div>

  <!-- Add Folder Modal -->
  <div id="folderModal" class="folder-modal">
    <div class="folder-modal-content">
      <div class="folder-modal-header">
        <h3>Add folder</h3>
      </div>
      <div class="folder-modal-body">
        <form id="createFolderForm" method="POST">
          <div class="form-group">
            <input type="text" id="folder_name" name="folder_name" placeholder="e.g. Enzymes" required>
          </div>
          <div class="folder-form-actions">
            <button type="button" class="folder-btn folder-cancel-btn">Cancel</button>
            <button type="submit" name="create_folder" class="folder-btn folder-primary-btn">Add folder</button>
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

  <!-- Enhanced PDF Selection Modal -->
  <div id="pdfModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fas fa-file-pdf"></i> Select a PDF to Highlight</h2>
        <button class="close-btn" id="closeModal">×</button>
      </div>
      
      <div class="search-container">
        <i class="fas fa-search search-icon"></i>
        <input type="text" id="pdfSearch" class="search-input" placeholder="Search PDFs...">
      </div>
      
      <div id="loadingSpinner" class="spinner"></div>
      
      <div id="pdfResults">
        <?php if (count($allPDFs) > 0): ?>
          <div class="pdf-grid">
            <?php foreach ($allPDFs as $pdf): 
              $fileSize = round($pdf['file_size'] / 1024, 1); // Convert to KB
            ?>
            <div class="pdf-card" data-pdf-id="<?php echo $pdf['id']; ?>" data-pdf-name="<?php echo htmlspecialchars(strtolower($pdf['original_filename'])); ?>">
              <div class="pdf-card-header">
                <div class="pdf-icon-large"><i class="fas fa-file-pdf"></i></div>
                <div class="pdf-dropdown">
                  
                  <div class="pdf-dropdown-content">
                    <a href="#" class="delete-pdf-btn" data-id="<?php echo $pdf['id']; ?>" data-name="<?php echo htmlspecialchars($pdf['original_filename']); ?>">
                      <i class="fas fa-trash"></i> Delete
                    </a>
                  </div>
                </div>
              </div>
              <div class="pdf-name"><?php echo htmlspecialchars($pdf['original_filename']); ?></div>
              <div class="pdf-size"><?php echo $fileSize; ?> KB</div>
            </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-file-pdf"></i></div>
            <h3>No PDFs Found</h3>
            <p>Upload your first PDF to start creating flashcards from highlights</p>
            <button onclick="closeModalAndShowUpload()" class="btn btn-manual">
              <i class="fas fa-upload"></i> Upload PDF
            </button>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Manage PDFs Modal -->
  <div id="managePdfModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2><i class="fas fa-file-pdf"></i> Manage Uploaded PDFs</h2>
        <button class="close-btn" id="closeManageModal">×</button>
      </div>
      <div id="pdfList">
        <!-- PDFs will be loaded here dynamically -->
      </div>
    </div>
  </div>

  <!-- Bottom Navigation with Fixed FAB - Mobile Only -->
  <div class="bottom-nav-container">
    <nav class="bottom-nav">
      <a href="../teacher_dashboard.php" class="nav-item-mobile">
        <i class="fas fa-home"></i>
        <span>Home</span>
      </a>
      <a href="../teacher_dashboard.php" class="nav-item-mobile">
        <i class="fas fa-chalkboard-teacher"></i>
        <span>Classes</span>
      </a>
      
      <!-- FAB Container -->
      <div class="fab-container">
        <a href="teacher_flashcard.php" class="fab">
          <i class="fas fa-plus"></i>
        </a>
      </div>
      
      <!-- Spacer for FAB area -->
      <div style="width: 25%;"></div>
      
      <a href="teacher_flashcard.php" class="nav-item-mobile active">
        <i class="fas fa-book-open"></i>
        <span>Flashcards</span>
      </a>
    </nav>
  </div>

  <script>
    // Original sidebar functionality
    function toggleDropdown(element) {
      element.classList.toggle('active');
      const menu = element.parentElement.querySelector('.dropdown-menu');
      menu.classList.toggle('show');
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(event) {
      if (!event.target.closest('.dropdown')) {
        document.querySelectorAll('.dropdown-menu').forEach(menu => {
          menu.classList.remove('show');
        });
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
          toggle.classList.remove('active');
        });
      }
    });

    // Manual flashcard creation functionality
    document.addEventListener('DOMContentLoaded', function() {
      // Add folder button functionality
      document.querySelector('.add-folder-btn').addEventListener('click', function() {
        const modal = document.getElementById('folderModal');
        modal.style.display = 'block';
        document.getElementById('folder_name').focus();
      });

      // Modal functionality
      const modal = document.getElementById('folderModal');
      const cancelBtn = document.querySelector('.folder-cancel-btn');
      
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
      document.querySelectorAll('.dropdown-btn:not(.folder-dropdown-btn)').forEach(btn => {
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
          fetch('teacher_flashcard.php', {
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

      // PDF selection modal functionality
      const pdfModal = document.getElementById('pdfModal');
      const selectBtn = document.getElementById('selectBtn');
      const closeBtn = document.getElementById('closeModal');
      const pdfSearch = document.getElementById('pdfSearch');
      const pdfCards = document.querySelectorAll('.pdf-card');
      const loadingSpinner = document.getElementById('loadingSpinner');
      const pdfResults = document.getElementById('pdfResults');

      selectBtn.addEventListener('click', function(e) {
        e.preventDefault();
        pdfModal.style.display = 'block';
        document.body.style.overflow = 'hidden';
        setTimeout(() => pdfSearch.focus(), 100);
      });

      function closePdfModal() {
        pdfModal.style.display = 'none';
        document.body.style.overflow = 'auto';
        pdfSearch.value = '';
        filterPDFs();
      }

      function closeModalAndShowUpload() {
        closePdfModal();
        document.querySelector('.upload-form').scrollIntoView({
          behavior: 'smooth'
        });
        document.querySelector('input[type="file"]').focus();
      }

      closeBtn.addEventListener('click', closePdfModal);

      window.addEventListener('click', function(event) {
        if (event.target == pdfModal) {
          closePdfModal();
        }
      });

      function selectPDF(pdfId) {
        loadingSpinner.style.display = 'block';
        pdfResults.style.opacity = '0.5';
        setTimeout(() => {
          window.location.href = `pdf_select.php?mode=highlight&id=${pdfId}`;
        }, 500);
      }

      function filterPDFs() {
        const searchTerm = pdfSearch.value.toLowerCase();
        pdfCards.forEach(card => {
          const pdfName = card.getAttribute('data-pdf-name');
          if (pdfName.includes(searchTerm)) {
            card.style.display = 'block';
          } else {
            card.style.display = 'none';
          }
        });
      }

      pdfSearch.addEventListener('input', filterPDFs);

      document.querySelectorAll('.pdf-card').forEach(card => {
        card.addEventListener('click', function() {
          const pdfId = this.getAttribute('data-pdf-id');
          selectPDF(pdfId);
        });
      });

      document.addEventListener('keydown', function(e) {
        if (pdfModal.style.display === 'block' && e.key === 'Escape') {
          closePdfModal();
        }
      });

      // Manage PDFs functionality
      const managePdfModal = document.getElementById('managePdfModal');
      const managePdfBtn = document.getElementById('managePdfBtn');
      const closeManageModal = document.getElementById('closeManageModal');

      managePdfBtn.addEventListener('click', function() {
        managePdfModal.style.display = 'block';
        loadPdfList();
      });

      closeManageModal.addEventListener('click', function() {
        managePdfModal.style.display = 'none';
      });

      window.addEventListener('click', function(event) {
        if (event.target == managePdfModal) {
          managePdfModal.style.display = 'none';
        }
      });

      function loadPdfList() {
        const pdfList = document.getElementById('pdfList');
        pdfList.innerHTML = '<p>Loading...</p>';
        
        fetch('get_pdfs.php')
          .then(response => response.json())
          .then(data => {
            pdfList.innerHTML = '';
            if (data.length > 0) {
              data.forEach(pdf => {
                const pdfItem = document.createElement('div');
                pdfItem.className = 'pdf-item';
                pdfItem.innerHTML = `
                  <span>${pdf.original_filename}</span>
                  <button class="delete-btn" data-id="${pdf.id}"><i class="fas fa-trash"></i> Delete</button>
                `
              });
            }
          });
      }

      // Prefill manual form if term/definition are in URL
      function getQueryParam(name) {
        const url = new URL(window.location.href);
        return url.searchParams.get(name);
      }
      const termVal = getQueryParam('term');
      const defVal = getQueryParam('definition');
      if (termVal) document.getElementById('term').value = termVal;
      if (defVal) document.getElementById('definition').value = defVal;

      // Folder dropdown menu functionality
      document.querySelectorAll('.folder-dropdown-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.stopPropagation();
          const dropdown = this.nextElementSibling;
          const isShowing = dropdown.classList.contains('show');
          // Close all other folder dropdowns first
          document.querySelectorAll('.folder-dropdown-content').forEach(d => {
            d.classList.remove('show');
          });
          // Toggle this dropdown
          if (!isShowing) {
            dropdown.classList.add('show');
          }
        });
      });
      // Close folder dropdowns when clicking elsewhere
      document.addEventListener('click', function() {
        document.querySelectorAll('.folder-dropdown-content').forEach(dropdown => {
          dropdown.classList.remove('show');
        });
      });
      // Prevent click inside dropdown from closing it
      document.querySelectorAll('.folder-dropdown-content').forEach(menu => {
        menu.addEventListener('click', function(e) {
          e.stopPropagation();
        });
      });
      // Folder delete AJAX
      document.querySelectorAll('.delete-folder-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          if (!confirm('Are you sure you want to delete this folder and all its flashcards? This cannot be undone.')) return;
          const folderId = this.getAttribute('data-id');
          const folderItem = this.closest('.folder-item');
          // Show loading state
          const originalHTML = folderItem.innerHTML;
          folderItem.innerHTML = '<span>Deleting... <i class="fas fa-spinner fa-spin"></i></span>';
          fetch('teacher_flashcard.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ delete_folder_id: folderId })
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              folderItem.remove();
              // Optionally show a message
              const msg = document.createElement('div');
              msg.className = 'success';
              msg.innerHTML = '<i class="fas fa-check-circle"></i> Folder deleted successfully!';
              document.querySelector('.manual-flashcard-content').prepend(msg);
              setTimeout(() => msg.remove(), 3000);
              // If the deleted folder was active, reload to "All Flashcards"
              if (window.location.search.includes('folder_id=' + folderId)) {
                window.location.href = 'teacher_flashcard.php';
              }
            } else {
              folderItem.innerHTML = originalHTML;
              alert('Error: ' + data.message);
            }
          })
          .catch(err => {
            folderItem.innerHTML = originalHTML;
            alert('Error deleting folder.');
          });
        });
      });
      // Folder archive AJAX
      document.querySelectorAll('.archive-folder-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          if (!confirm('Are you sure you want to archive this folder?')) return;
          const folderId = this.getAttribute('data-id');
          const folderItem = this.closest('.folder-item');
          // Show loading state
          const originalHTML = folderItem.innerHTML;
          folderItem.innerHTML = '<span>Archiving... <i class="fas fa-spinner fa-spin"></i></span>';
          fetch('teacher_flashcard.php', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: new URLSearchParams({ archive_folder_id: folderId })
          })
          .then(res => res.json())
          .then(data => {
            if (data.success) {
              folderItem.remove();
              // Optionally show a message
              const msg = document.createElement('div');
              msg.className = 'success';
              msg.innerHTML = '<i class="fas fa-check-circle"></i> Folder archived successfully!';
              document.querySelector('.manual-flashcard-content').prepend(msg);
              setTimeout(() => msg.remove(), 3000);
              // If the archived folder was active, reload to "All Flashcards"
              if (window.location.search.includes('folder_id=' + folderId)) {
                window.location.href = 'teacher_flashcard.php';
              }
            } else {
              folderItem.innerHTML = originalHTML;
              alert('Error: ' + data.message);
            }
          })
          .catch(err => {
            folderItem.innerHTML = originalHTML;
            alert('Error archiving folder.');
          });
        });
      });

      // Move functionality
      let selectedFlashcards = [];
      const moveFolderModal = document.getElementById('moveFolderModal');
      let selectedFolderId = null;

      // Handle move-flashcard button clicks (single flashcard move)
      document.querySelectorAll('.move-flashcard').forEach(btn => {
        btn.addEventListener('click', function(e) {
          e.preventDefault();
          const flashcardId = this.getAttribute('data-id');
          // Show folder selection modal
          moveFolderModal.style.display = 'block';
          selectedFolderId = null;
          document.querySelectorAll('.move-folder-item').forEach(item => {
            item.classList.remove('selected');
          });
          // Set up confirm button for single move
          const confirmBtn = document.getElementById('confirm-move-folder');
          // Remove previous event listeners
          const newConfirmBtn = confirmBtn.cloneNode(true);
          confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);
          newConfirmBtn.addEventListener('click', function() {
            if (!selectedFolderId && selectedFolderId !== '') {
              alert('Please select a folder');
              return;
            }
            // Create a form to submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = 'teacher_flashcard.php';
            // Add single flashcard ID
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'flashcard_ids[]';
            input.value = flashcardId;
            form.appendChild(input);
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
        });
      });
    });

    // Add JS for multi-select move mode
    // Multi-select move mode
    const moveActionsBar = document.querySelector('.move-actions-bar');
    const moveSelectedBtn = document.getElementById('move-selected-btn');
    const cancelMoveBtn = document.getElementById('cancel-move-btn');
    const selectAllMove = document.getElementById('select-all-move');
    let moveMode = false;

    // Start move mode from three-dot menu
    document.querySelectorAll('.move-multiselect-flashcard').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        moveMode = true;
        document.body.classList.add('move-mode');
        // Show checkboxes for all flashcards
        document.querySelectorAll('.flashcard-checkbox').forEach(checkbox => {
          checkbox.style.display = 'inline-block';
        });
        // Show move actions bar
        moveActionsBar.style.display = 'flex';
      });
    });

    // Cancel move mode
    cancelMoveBtn.addEventListener('click', function() {
      moveMode = false;
      document.body.classList.remove('move-mode');
      // Hide checkboxes
      document.querySelectorAll('.flashcard-checkbox').forEach(checkbox => {
        checkbox.checked = false;
        checkbox.style.display = 'none';
      });
      // Hide move actions bar
      moveActionsBar.style.display = 'none';
      // Uncheck select all
      selectAllMove.checked = false;
    });

    // Select all functionality
    selectAllMove.addEventListener('change', function() {
      const checked = this.checked;
      document.querySelectorAll('.flashcard-checkbox').forEach(checkbox => {
        checkbox.checked = checked;
      });
    });

    // Move selected button
    moveSelectedBtn.addEventListener('click', function() {
      // Get selected flashcard IDs
      selectedFlashcards = [];
      document.querySelectorAll('.flashcard-checkbox:checked').forEach(checkbox => {
        selectedFlashcards.push(checkbox.value);
      });
      if (selectedFlashcards.length === 0) {
        alert('Please select at least one flashcard to move');
        return;
      }

      // Remove any existing folder list
      let oldFolderList = document.getElementById('inline-move-folder-list');
      if (oldFolderList) oldFolderList.remove();

      // Show inline folder list below the move-actions-bar
      let folderList = document.createElement('div');
      folderList.id = 'inline-move-folder-list';
      folderList.style.background = '#fff';
      folderList.style.boxShadow = '0 2px 8px rgba(16,24,40,0.08)';
      folderList.style.padding = '16px';
      folderList.style.borderRadius = '8px';
      folderList.style.position = 'fixed';
      folderList.style.left = '50%';
      folderList.style.bottom = '70px';
      folderList.style.transform = 'translateX(-50%)';
      folderList.style.zIndex = '1100';
      folderList.style.minWidth = '320px';
      folderList.innerHTML = `<div style='font-weight:600;margin-bottom:10px;'>Select a folder to move to:</div>`;

      // Add folder options
      const allOption = document.createElement('div');
      allOption.className = 'move-folder-item';
      allOption.setAttribute('data-folder-id', '');
      allOption.style.cursor = 'pointer';
      allOption.style.padding = '8px 0';
      allOption.innerHTML = `<i class='fas fa-inbox'></i> All Flashcards`;
      folderList.appendChild(allOption);

      <?php foreach ($allFolders as $folder): ?>
      const folderDiv<?php echo $folder['id']; ?> = document.createElement('div');
      folderDiv<?php echo $folder['id']; ?>.className = 'move-folder-item';
      folderDiv<?php echo $folder['id']; ?>.setAttribute('data-folder-id', '<?php echo $folder['id']; ?>');
      folderDiv<?php echo $folder['id']; ?>.style.cursor = 'pointer';
      folderDiv<?php echo $folder['id']; ?>.style.padding = '8px 0';
      folderDiv<?php echo $folder['id']; ?>.innerHTML = `<i class='fas fa-folder'></i> <?php echo htmlspecialchars($folder['name']); ?>`;
      folderList.appendChild(folderDiv<?php echo $folder['id']; ?>);
      <?php endforeach; ?>

      // Add action buttons
      const actionsDiv = document.createElement('div');
      actionsDiv.style.marginTop = '16px';
      actionsDiv.style.display = 'flex';
      actionsDiv.style.gap = '12px';
      const moveHereBtn = document.createElement('button');
      moveHereBtn.textContent = 'Move here';
      moveHereBtn.className = 'btn primary-btn';
      moveHereBtn.disabled = true;
      const cancelBtn = document.createElement('button');
      cancelBtn.textContent = 'Cancel';
      cancelBtn.className = 'btn cancel-btn';
      actionsDiv.appendChild(moveHereBtn);
      actionsDiv.appendChild(cancelBtn);
      folderList.appendChild(actionsDiv);
      document.body.appendChild(folderList);

      // Folder selection logic
      let selectedInlineFolderId = null;
      folderList.querySelectorAll('.move-folder-item').forEach(item => {
        item.addEventListener('click', function() {
          folderList.querySelectorAll('.move-folder-item').forEach(i => i.classList.remove('selected'));
          this.classList.add('selected');
          selectedInlineFolderId = this.getAttribute('data-folder-id');
          moveHereBtn.disabled = false;
        });
      });

      // Move here logic
      moveHereBtn.addEventListener('click', function() {
        if (selectedInlineFolderId === null) {
          alert('Please select a folder');
          return;
        }
        // Create a form to submit
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = 'teacher_flashcard.php';
        selectedFlashcards.forEach(id => {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = 'flashcard_ids[]';
          input.value = id;
          form.appendChild(input);
        });
        const folderInput = document.createElement('input');
        folderInput.type = 'hidden';
        folderInput.name = 'target_folder_id';
        folderInput.value = selectedInlineFolderId;
        form.appendChild(folderInput);
        const submitInput = document.createElement('input');
        submitInput.type = 'hidden';
        submitInput.name = 'move_flashcards';
        submitInput.value = '1';
        form.appendChild(submitInput);
        document.body.appendChild(form);
        form.submit();
      });

      // Cancel logic
      cancelBtn.addEventListener('click', function() {
        folderList.remove();
      });
    });

    // PDF Dropdown Menu functionality
    document.querySelectorAll('.pdf-dropdown-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.stopPropagation(); // Prevent card click
        const dropdown = this.nextElementSibling;
        const isShowing = dropdown.classList.contains('show');
        
        // Close all other PDF dropdowns first
        document.querySelectorAll('.pdf-dropdown-content').forEach(d => {
          d.classList.remove('show');
        });
        
        // Toggle this dropdown
        if (!isShowing) {
          dropdown.classList.add('show');
        }
      });
    });

    // Close PDF dropdowns when clicking elsewhere
    document.addEventListener('click', function(e) {
      if (!e.target.closest('.pdf-dropdown')) {
        document.querySelectorAll('.pdf-dropdown-content').forEach(dropdown => {
          dropdown.classList.remove('show');
        });
      }
    });

    // PDF Delete functionality
    document.querySelectorAll('.delete-pdf-btn').forEach(btn => {
      btn.addEventListener('click', function(e) {
        e.preventDefault();
        e.stopPropagation(); // Prevent card click
        
        const pdfId = this.getAttribute('data-id');
        const pdfName = this.getAttribute('data-name');
        
        if (!confirm(`Are you sure you want to delete "${pdfName}"? This action cannot be undone.`)) {
          return;
        }
        
        const pdfCard = this.closest('.pdf-card');
        const originalContent = pdfCard.innerHTML;
        
        // Show loading state
        pdfCard.innerHTML = '<div style="text-align: center; padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Deleting...</div>';
        
        // Send delete request
        const formData = new FormData();
        formData.append('pdf_id', pdfId);
        
        fetch('delete_pdf.php', {
          method: 'POST',
          body: formData
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            // Remove the card with animation
            pdfCard.style.transition = 'all 0.3s ease';
            pdfCard.style.transform = 'scale(0.8)';
            pdfCard.style.opacity = '0';
            
            setTimeout(() => {
              pdfCard.remove();
              
              // Check if no more PDFs
              const remainingCards = document.querySelectorAll('.pdf-card');
              if (remainingCards.length === 0) {
                const pdfResults = document.getElementById('pdfResults');
                pdfResults.innerHTML = `
                  <div class="empty-state">
                    <div class="empty-icon"><i class="fas fa-file-pdf"></i></div>
                    <h3>No PDFs Found</h3>
                    <p>Upload your first PDF to start creating flashcards from highlights</p>
                    <button onclick="closeModalAndShowUpload()" class="btn btn-manual">
                      <i class="fas fa-upload"></i> Upload PDF
                    </button>
                  </div>
                `;
              }
            }, 300);
            
            // Show success message
            const successMessage = document.createElement('div');
            successMessage.className = 'success';
            successMessage.innerHTML = `<i class="fas fa-check-circle"></i> PDF "${pdfName}" deleted successfully!`;
            successMessage.style.position = 'fixed';
            successMessage.style.top = '20px';
            successMessage.style.right = '20px';
            successMessage.style.zIndex = '2000';
            successMessage.style.padding = '12px 20px';
            successMessage.style.borderRadius = '8px';
            successMessage.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            document.body.appendChild(successMessage);
            
            setTimeout(() => {
              successMessage.remove();
            }, 3000);
            
          } else {
            // Restore original content on error
            pdfCard.innerHTML = originalContent;
            
            // Show error message
            const errorMessage = document.createElement('div');
            errorMessage.className = 'alert';
            errorMessage.innerHTML = `<i class="fas fa-exclamation-circle"></i> Error: ${data.message}`;
            errorMessage.style.position = 'fixed';
            errorMessage.style.top = '20px';
            errorMessage.style.right = '20px';
            errorMessage.style.zIndex = '2000';
            errorMessage.style.padding = '12px 20px';
            errorMessage.style.borderRadius = '8px';
            errorMessage.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
            document.body.appendChild(errorMessage);
            
            setTimeout(() => {
              errorMessage.remove();
            }, 3000);
          }
        })
        .catch(error => {
          // Restore original content on error
          pdfCard.innerHTML = originalContent;
          
          // Show error message
          const errorMessage = document.createElement('div');
          errorMessage.className = 'alert';
          errorMessage.innerHTML = '<i class="fas fa-exclamation-circle"></i> Error deleting PDF. Please try again.';
          errorMessage.style.position = 'fixed';
          errorMessage.style.top = '20px';
          errorMessage.style.right = '20px';
          errorMessage.style.zIndex = '2000';
          errorMessage.style.padding = '12px 20px';
          errorMessage.style.borderRadius = '8px';
          errorMessage.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
          document.body.appendChild(errorMessage);
          
          setTimeout(() => {
            errorMessage.remove();
          }, 3000);
          
          console.error('Error:', error);
        });
      });
    });
  </script>
</body>
</html>