<?php

$host = 'switchyard.proxy.rlwy.net'; // or 'ballast.proxy.rlwy.net' for public URL
$dbname = 'railway';
$username = 'root';
$password = 'mfwZMSewsBKfBJQOdeOmyqMZoRGwewMI';
$port = 33262; // or 33262 if using the public URL

$mysqli = new mysqli($host, $username, $password, $dbname, $port);

if ($mysqli->connect_errno) {
    die("Connection error: " . $mysqli->connect_error);
}

return $mysqli;
