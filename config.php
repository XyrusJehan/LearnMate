<?php
// Database configuration for LearnMate
$host = 'localhost';
$user = 'root';
$password = '';
$database = 'learnmate';

$conn = new mysqli($host, $user, $password, $database);
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
?> 