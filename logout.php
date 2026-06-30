<?php
session_start();
require 'database.php';

// Log logout activity if user was logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['email'])) {
    $user_id = $_SESSION['user_id'];
    $user_email = $_SESSION['email'];
    
    // Log the logout action
    try {
        $log_sql = "INSERT INTO user_activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        if ($log_stmt) {
            $action = "User Logout";
            $description = "User logged out: " . ($_SESSION['full_name'] ?? 'Unknown');
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $log_stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        error_log("User logout logging error: " . $e->getMessage());
    }
}

// Destroy all session data
session_unset();
session_destroy();

// Close database connection
$conn->close();

// Redirect to user login page with success message
header("Location: login.php?logout=success");
exit;
?>