<?php
// test_db.php - Simple database test
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

echo "<h2>Database Connection Test</h2>";
echo "<p>Database connection successful!</p>";

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    echo "<p>User ID: " . $_SESSION['user_id'] . "</p>";
} else {
    echo "<p>No user logged in</p>";
}

// Check PDF files table
echo "<h3>PDF Files Table:</h3>";
$result = $conn->query("SELECT id, original_filename, storage_path FROM pdf_files LIMIT 5");
if ($result && $result->num_rows > 0) {
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Filename</th><th>Storage Path</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . htmlspecialchars($row['original_filename']) . "</td>";
        echo "<td>" . htmlspecialchars($row['storage_path']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "<p>No PDF files found in database</p>";
}

// Check folders table
echo "<h3>Folders Table:</h3>";
if (isset($_SESSION['user_id'])) {
    $stmt = $conn->prepare("SELECT id, name FROM folders WHERE user_id = ?");
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Name</th></tr>";
        while ($row = $result->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row['id'] . "</td>";
            echo "<td>" . htmlspecialchars($row['name']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p>No folders found for user</p>";
    }
    $stmt->close();
}

$conn->close();
?> 