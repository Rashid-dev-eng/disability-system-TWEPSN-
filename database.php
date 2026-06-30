<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "disability-tracker";

// Create connection for users
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("User Database Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// REMOVE THIS LINE - it's causing the message to display
// echo "User database connected successfully";
?>