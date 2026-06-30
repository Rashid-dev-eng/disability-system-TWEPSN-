<?php
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "disability-tracker";

// Create connection for admin
$admin_conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($admin_conn->connect_error) {
    die("Admin Database Connection failed: " . $admin_conn->connect_error);
}

// Set charset to utf8
$admin_conn->set_charset("utf8");

// REMOVE THE ECHO LINE - No connection message displayed
?>