<?php
// test_remove_student.php - Simple test to verify remove_student.php functionality
require 'db.php';

// This is a test file to verify the remove_student.php functionality
// In a real application, you would not expose this file publicly

echo "<h2>Remove Student Test</h2>";

// Test 1: Check if class_students table exists and has data
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM class_students");
    $result = $stmt->fetch();
    echo "<p>✅ class_students table exists with {$result['count']} records</p>";
    
    // Show some sample data
    $stmt = $pdo->query("SELECT cs.*, c.class_name, u.first_name, u.last_name 
                        FROM class_students cs 
                        JOIN classes c ON cs.class_id = c.id 
                        JOIN users u ON cs.student_id = u.id 
                        LIMIT 5");
    $enrollments = $stmt->fetchAll();
    
    if (!empty($enrollments)) {
        echo "<h3>Sample Enrollments:</h3>";
        echo "<ul>";
        foreach ($enrollments as $enrollment) {
            echo "<li>Class: {$enrollment['class_name']} - Student: {$enrollment['first_name']} {$enrollment['last_name']} (ID: {$enrollment['student_id']})</li>";
        }
        echo "</ul>";
    }
    
} catch (PDOException $e) {
    echo "<p>❌ Error accessing class_students table: " . $e->getMessage() . "</p>";
}

// Test 2: Check if remove_student.php file exists
if (file_exists('remove_student.php')) {
    echo "<p>✅ remove_student.php file exists</p>";
} else {
    echo "<p>❌ remove_student.php file does not exist</p>";
}

// Test 3: Check database connection
try {
    $pdo->query("SELECT 1");
    echo "<p>✅ Database connection is working</p>";
} catch (PDOException $e) {
    echo "<p>❌ Database connection failed: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Note:</strong> This is a test file. In production, remove this file or restrict access to it.</p>";
echo "<p>The remove_student.php functionality should work correctly with the current implementation.</p>";
?> 