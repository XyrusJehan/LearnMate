<?php

$host = 'localhost';
$dbname = 'learnmate';
$username ="root"; // Change to your MySQL username
$password = ''; // Change to your MySQL password

$mysqli = new mysqli(hostname: $host, username: $username, password: $password, database: $dbname);

if ($mysqli->connect_errno) {
    die("Connection error: " . $mysqli->connect_error);
}

return $mysqli;
