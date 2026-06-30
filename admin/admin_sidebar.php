<?php
// admin_sidebar.php
// session_start();
require '../database.php';

// Check if user is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Fetch admin data from database
$admin_id = $_SESSION['admin_id'];
$admin_data = null;

$query = "SELECT username, email FROM admins WHERE admin_id = ?";
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

// Get display data with fallbacks
$display_name = $admin_data['username'] ?? ($_SESSION['username'] ?? 'Admin');
$display_email = $admin_data['email'] ?? 'Administrator';
?>

<!-- Sidebar -->
<div class="sidebar bg-dark vh-100 position-fixed" style="width: 250px; z-index: 1000;">
    <div class="p-4">
        <!-- Admin Info -->
        <div class="text-center text-white mb-4">
            <i class="fas fa-user-shield fa-3x mb-3 text-white"></i>
            <h5 class="mb-1" style="color: #fff;"><?php echo htmlspecialchars($display_name); ?></h5>
            <small class="text-white-50"><?php echo htmlspecialchars($display_email); ?></small>
        </div>

        <!-- Navigation -->
        <ul class="nav flex-column">
            <li class="nav-item mb-2">
                <a class="nav-link text-white d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard.php' ? 'active' : ''; ?>" href="admin_dashboard.php">
                    <i class="fas fa-tachometer-alt me-3"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'active' : ''; ?>" href="admin_users.php">
                    <i class="fas fa-users me-3"></i>
                    <span>Manage Users</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'admin_service_applications.php' ? 'active' : ''; ?>" href="admin_service_applications.php">
                    <i class="fas fa-file-alt me-3"></i>
                    <span>Applications</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'admin_appointments.php' ? 'active' : ''; ?>" href="admin_appointments.php">
                    <i class="fas fa-calendar me-3"></i>
                    <span>Appointments</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'admin_announcements.php' ? 'active' : ''; ?>" href="admin_announcements.php">
                    <i class="fas fa-bullhorn me-3"></i>
                    <span>Announcements</span>
                </a>
            </li>
            <li class="nav-item mb-2">
                <a class="nav-link text-white d-flex align-items-center <?php echo basename($_SERVER['PHP_SELF']) == 'admin_reports.php' ? 'active' : ''; ?>" href="admin_reports.php">
                    <i class="fas fa-chart-bar me-3"></i>
                    <span>Reports</span>
                </a>
            </li>
            
            <li class="nav-item mt-4 pt-3 border-top border-white-50">
                <a class="nav-link text-white d-flex align-items-center" href="admin_logout.php">
                    <i class="fas fa-sign-out-alt me-3"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</div>

<style>
.sidebar {
    background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%) !important;
    box-shadow: 3px 0 15px rgba(0,0,0,0.1);
}
.nav-link {
    transition: all 0.3s;
    border-radius: 8px;
    padding: 12px 15px;
    margin: 4px 0;
    color: #fff !important;
    font-weight: 500;
}
.nav-link:hover {
    background-color: rgba(255,255,255,0.15);
    transform: translateX(8px);
}
.nav-link.active {
    background-color: rgba(52, 152, 219, 0.3);
    border-left: 4px solid #fff;
}
</style>