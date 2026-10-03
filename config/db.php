<?php
// Prevent multiple definitions
if (!defined('BASE')) {
    define('BASE', '/bhconnect');
}

$host = 'localhost';
$user = 'root';
$pass = '';
$db   = 'bhconnect';

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("DB connection failed: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");
?>