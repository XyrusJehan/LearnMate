<?php

$host = 'switchyard.proxy.rlwy.net';
$dbname = 'railway';
$username = 'root';
$password = 'mfwZMSewsBKfBJQOdeOmyqMZoRGwewMI'; // From MYSQL_ROOT_PASSWORD
$port = 47909;

$mysqli = new mysqli($host, $username, $password, $dbname, $port);

if ($mysqli->connect_errno) {
    die("Connection error: " . $mysqli->connect_error);
}

return $mysqli;