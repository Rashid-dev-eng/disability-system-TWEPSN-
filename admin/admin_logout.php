<?php
session_start();
require 'admin_database.php';

// Log logout activity if admin was logged in
if (isset($_SESSION['admin_id']) && isset($_SESSION['email'])) {
    $admin_id = $_SESSION['admin_id'];
    $admin_email = $_SESSION['email'];
    
    // Log the logout action
    try {
        $log_sql = "INSERT INTO audit_log (user_id, user_email, action, description, ip_address) VALUES (?, ?, ?, ?, ?)";
        $log_stmt = $admin_conn->prepare($log_sql);
        if ($log_stmt) {
            $action = "Admin Logout";
            $description = "Admin logged out: " . ($_SESSION['full_name'] ?? 'Unknown');
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $log_stmt->bind_param("issss", $admin_id, $admin_email, $action, $description, $ip_address);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Admin logout logging error: " . $e->getMessage());
    }
}

// Destroy all session data
session_unset();
session_destroy();

// Close database connection
$admin_conn->close();

// Redirect to admin login page with success message
header("Location: admin_login.php?logout=success");
exit;
?>