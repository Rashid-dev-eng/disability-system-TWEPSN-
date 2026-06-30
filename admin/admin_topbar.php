<?php
// admin_topbar.php

require '../database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Fetch admin data from database
$admin_id = $_SESSION['admin_id'];
$admin_data = null;

$query = "SELECT username FROM admins WHERE user_id = ?";
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $admin_data = $result->fetch_assoc();
    }
    $stmt->close();
}

// Get display name with fallback
$display_name = $admin_data['username'] ?? ($_SESSION['username'] ?? 'Admin');
?>

<!-- Topbar -->
<nav class="navbar navbar-expand navbar-dark topbar shadow-sm" style="height: 70px; background: linear-gradient(90deg, #2c3e50 0%, #3498db 100%); position: fixed; top: 0; left: 250px; right: 0; z-index: 999;">
    <div class="container-fluid px-4 d-flex justify-content-between align-items-center">
        <!-- Page Title - Left Side -->
        <div class="navbar-brand me-4">
            <h4 class="mb-0 text-white fw-bold">
                <?php 
                $page_titles = [
                    'admin_dashboard.php' => 'Dashboard',
                    'admin_users.php' => 'Manage Users',
                    'admin_service_applications.php' => 'Applications',
                    'admin_appointments.php' => 'Appointments',
                    'admin_announcements.php' => 'Announcements',
                    'admin_reports.php' => 'Reports',
                    'admin_profile.php' => 'Profile'
                ];
                echo $page_titles[basename($_SERVER['PHP_SELF'])] ?? 'Admin Panel';
                ?>
            </h4>
        </div>

        <!-- Topbar Navbar - Right Side -->
        <div class="d-flex align-items-center gap-4">
            
            <!-- User Dropdown -->
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle d-flex align-items-center text-white p-2" href="#" role="button" data-bs-toggle="dropdown">
                    <div class="d-none d-md-block me-3 text-end">
                        <div class="small text-white-50">Welcome</div>
                        <strong class="text-white"><?php echo htmlspecialchars($display_name); ?></strong>
                    </div>
                    <i class="fas fa-user-circle fa-2x text-white-50"></i>
                </a>
                <div class="dropdown-menu dropdown-menu-end shadow">
                    <a class="dropdown-item" href="admin_profile.php">
                        <i class="fas fa-user me-2"></i>Profile
                    </a>
                    
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item" href="admin_logout.php">
                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Main Content starts here -->
<div class="main-content" style="margin-left: 250px; min-height: calc(100vh - 70px); margin-top: 70px;">