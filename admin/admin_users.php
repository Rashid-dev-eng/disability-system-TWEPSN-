<?php
session_start();
require '../database.php';

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Enhanced input sanitization
function sanitize_input($data) {
    if (is_array($data)) {
        return array_map('sanitize_input', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    
    // Additional security for database queries
    global $conn;
    if ($conn) {
        $data = $conn->real_escape_string($data);
    }
    
    return $data;
}

// Enhanced audit logging function with fallback
function logAdminAction($conn, $action, $description, $target_user_id = null) {
    // Check if audit_log table exists and has required columns
    $table_check = $conn->query("SHOW TABLES LIKE 'audit_log'");
    if ($table_check->num_rows > 0) {
        // Check if the table has the new columns
        $column_check = $conn->query("SHOW COLUMNS FROM audit_log LIKE 'admin_username'");
        if ($column_check->num_rows > 0) {
            // Use new schema with enhanced columns
            $stmt = $conn->prepare("INSERT INTO audit_log (admin_id, admin_username, action, description, target_user_id, ip_address, user_agent) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            $admin_id = $_SESSION['user_id'];
            $admin_username = $_SESSION['username'] ?? 'Admin';
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
            
            if ($stmt) {
                $stmt->bind_param("isssiss", $admin_id, $admin_username, $action, $description, $target_user_id, $ip_address, $user_agent);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
        } else {
            // Use old schema (if you had a simpler audit_log table)
            $stmt = $conn->prepare("INSERT INTO audit_log (user_id, user_email, action, description, ip_address) VALUES (?, ?, ?, ?, ?)");
            
            $admin_id = $_SESSION['admin_id'];
            $admin_username = $_SESSION['username'] ?? 'Admin';
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            
            if ($stmt) {
                $stmt->bind_param("issss", $admin_id, $admin_username, $action, $description, $ip_address);
                $result = $stmt->execute();
                $stmt->close();
                return $result;
            }
        }
    }
    
    // If audit_log table doesn't exist or prepare failed, log to error log instead
    error_log("Admin Action: " . $action . " - " . $description);
    return false;
}

// Create audit_log table if it doesn't exist
function createAuditLogTable($conn) {
    $sql = "CREATE TABLE IF NOT EXISTS audit_log (
        id INT PRIMARY KEY AUTO_INCREMENT,
        admin_id INT,
        admin_username VARCHAR(100),
        action VARCHAR(100) NOT NULL,
        description TEXT,
        target_user_id INT,
        ip_address VARCHAR(45),
        user_agent TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_admin_id (admin_id),
        INDEX idx_action (action),
        INDEX idx_created_at (created_at)
    )";
    
    return $conn->query($sql);
}

// Initialize audit_log table
createAuditLogTable($conn);

// Rate limiting for user actions
function checkRateLimit($action, $limit = 10, $timeframe = 60) {
    if (!isset($_SESSION['rate_limits'])) {
        $_SESSION['rate_limits'] = [];
    }
    
    $key = "rate_limit_{$action}";
    $current_time = time();
    
    if (!isset($_SESSION['rate_limits'][$key])) {
        $_SESSION['rate_limits'][$key] = [
            'count' => 1,
            'time' => $current_time
        ];
        return true;
    }
    
    $rate_data = $_SESSION['rate_limits'][$key];
    
    if ($current_time - $rate_data['time'] > $timeframe) {
        $_SESSION['rate_limits'][$key] = [
            'count' => 1,
            'time' => $current_time
        ];
        return true;
    }
    
    if ($rate_data['count'] >= $limit) {
        return false;
    }
    
    $_SESSION['rate_limits'][$key]['count']++;
    return true;
}

// Handle user actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && isset($_POST['user_id'])) {
        $user_id = sanitize_input($_POST['user_id']);
        $action = sanitize_input($_POST['action']);
        
        // Rate limiting for delete actions
        if ($action === 'delete' && !checkRateLimit('user_delete', 5, 60)) {
            $_SESSION['flash_message'] = "Too many delete attempts. Please wait a minute.";
            $_SESSION['flash_type'] = 'danger';
            header("Location: admin_users.php");
            exit;
        }
        
        if ($action === 'delete') {
            // First get user info for logging
            $stmt = $conn->prepare("SELECT full_name, email FROM users WHERE id = ?");
            $user_name = '';
            $user_email = '';
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $user_name = $row['full_name'];
                    $user_email = $row['email'];
                }
                $stmt->close();
            }
            
            $stmt = $conn->prepare("DELETE FROM users WHERE id = ? AND role = 'user'");
            if ($stmt) {
                $stmt->bind_param("i", $user_id);
                if ($stmt->execute()) {
                    // Enhanced logging using new function
                    logAdminAction($conn, "USER_DELETED", "Deleted user: {$user_name} ({$user_email})", $user_id);
                    
                    $_SESSION['flash_message'] = "User deleted successfully!";
                    $_SESSION['flash_type'] = 'success';
                } else {
                    $_SESSION['flash_message'] = "Failed to delete user: " . $stmt->error;
                    $_SESSION['flash_type'] = 'danger';
                }
                $stmt->close();
            } else {
                $_SESSION['flash_message'] = "Error preparing delete statement";
                $_SESSION['flash_type'] = 'danger';
            }
        }
        header("Location: admin_users.php");
        exit;
    }
}

// Track user profile views
if (isset($_GET['view_user']) && is_numeric($_GET['view_user'])) {
    $viewed_user_id = sanitize_input($_GET['view_user']);
    logAdminAction($conn, "USER_VIEWED", "Viewed user details", $viewed_user_id);
}

// Track search activities
if (isset($_GET['search']) && !empty($_GET['search']) || isset($_GET['disability']) && !empty($_GET['disability'])) {
    $search_term = $_GET['search'] ?? '';
    $disability_filter = $_GET['disability'] ?? '';
    
    $search_description = "Searched users";
    if (!empty($search_term)) $search_description .= " with term: '{$search_term}'";
    if (!empty($disability_filter)) $search_description .= " with disability filter: '{$disability_filter}'";
    
    logAdminAction($conn, "USER_SEARCH", $search_description);
}

// Flash message handling
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Fetch users with secure search and filter
$search_term = sanitize_input($_GET['search'] ?? '');
$disability_filter = sanitize_input($_GET['disability'] ?? '');

// Build secure query
$query = "SELECT * FROM users WHERE role = 'user'";
$params = [];
$types = "";

// Safe search filter
if (!empty($search_term)) {
    $query .= " AND (full_name LIKE ? OR email LIKE ? OR phone LIKE ?)";
    $search_term_like = "%$search_term%";
    $params[] = $search_term_like;
    $params[] = $search_term_like;
    $params[] = $search_term_like;
    $types .= "sss";
}

// Safe disability filter
if (!empty($disability_filter)) {
    $query .= " AND disability_type = ?";
    $params[] = $disability_filter;
    $types .= "s";
}

$query .= " ORDER BY created_at DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $users = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $users = [];
    error_log("Error preparing users query: " . $conn->error);
}

// FIXED: Enhanced User Statistics - Query database directly for accurate counts
$total_users = count($users);

// Get today's users count directly from database
$today_start = date('Y-m-d 00:00:00');
$today_end = date('Y-m-d 23:59:59');
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND created_at BETWEEN ? AND ?");
$today_users = 0;
if ($stmt) {
    $stmt->bind_param("ss", $today_start, $today_end);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $today_users = $row['count'];
    }
    $stmt->close();
}

// Get this week's users count
$week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
$week_end = date('Y-m-d 23:59:59', strtotime('sunday this week'));
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND created_at BETWEEN ? AND ?");
$week_users = 0;
if ($stmt) {
    $stmt->bind_param("ss", $week_start, $week_end);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $week_users = $row['count'];
    }
    $stmt->close();
}

// Get this month's users count
$month_start = date('Y-m-01 00:00:00');
$month_end = date('Y-m-t 23:59:59');
$stmt = $conn->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'user' AND created_at BETWEEN ? AND ?");
$month_users = 0;
if ($stmt) {
    $stmt->bind_param("ss", $month_start, $month_end);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $month_users = $row['count'];
    }
    $stmt->close();
}

// Disability type distribution
$disability_stats = [];
$stmt = $conn->prepare("SELECT disability_type, COUNT(*) as count FROM users WHERE role = 'user' AND disability_type IS NOT NULL GROUP BY disability_type");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $disability_stats[$row['disability_type']] = $row['count'];
    }
    $stmt->close();
}

// Regional distribution
$region_stats = [];
$stmt = $conn->prepare("SELECT region, COUNT(*) as count FROM users WHERE role = 'user' AND region IS NOT NULL GROUP BY region");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $region_stats[$row['region']] = $row['count'];
    }
    $stmt->close();
}

// Get disability types for filter
$disability_types = [];
$stmt = $conn->prepare("SELECT DISTINCT disability_type FROM users WHERE disability_type IS NOT NULL AND role = 'user'");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $disability_types[] = $row['disability_type'];
    }
    $stmt->close();
}

// Log page view (with error handling)
logAdminAction($conn, "PAGE_VIEW", "Accessed user management page");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Disability System Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --sidebar-blue: #2c3e50;
            --sidebar-light-blue: #3498db;
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --dark-color: #34495e;
            --light-bg: #ecf0f1;
        }
        
        body {
            background-color: var(--light-bg);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            font-size: 0.9rem;
        }
        
        .flash-message {
            position: fixed;
            top: 90px;
            right: 30px;
            z-index: 1000;
            animation: slideInRight 0.4s ease, slideOutRight 0.4s ease 2.7s forwards;
        }
        
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease;
            margin-bottom: 20px;
            font-size: 0.85rem;
        }
        
        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.12);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--sidebar-blue), var(--sidebar-light-blue));
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
            border: none;
            font-size: 0.9rem;
        }
        
        .card-header h4 {
            margin: 0;
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .table-responsive {
            border-radius: 8px;
            overflow: hidden;
        }
        
        .table {
            margin-bottom: 0;
            font-size: 0.85rem;
        }
        
        .table thead th {
            background: linear-gradient(135deg, var(--sidebar-blue), var(--sidebar-light-blue));
            color: white;
            border: none;
            padding: 12px 10px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .table tbody td {
            padding: 12px 10px;
            vertical-align: middle;
            border-color: #f8f9fa;
            font-size: 0.85rem;
        }
        
        .table tbody tr {
            transition: all 0.2s ease;
        }
        
        .table tbody tr:hover {
            background-color: rgba(52, 152, 219, 0.05);
        }
        
        .btn {
            border-radius: 6px;
            font-weight: 500;
            padding: 6px 12px;
            transition: all 0.2s ease;
            border: none;
            font-size: 0.8rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--sidebar-blue), var(--sidebar-light-blue));
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--danger-color), #ee5a52);
        }
        
        .btn-sm {
            padding: 5px 10px;
            font-size: 0.75rem;
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 15px;
            font-weight: 500;
            font-size: 0.75rem;
        }
        
        .form-control {
            border-radius: 6px;
            border: 1px solid #bdc3c7;
            padding: 8px 12px;
            transition: all 0.2s ease;
            font-size: 0.85rem;
        }
        
        .form-control:focus {
            border-color: var(--sidebar-light-blue);
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.1);
        }
        
        .stats-card {
            text-align: center;
            padding: 15px;
            border-radius: 8px;
            background: white;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            font-size: 0.85rem;
        }
        
        .stats-number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--sidebar-blue);
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: var(--dark-color);
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .user-avatar {
            width: 35px;
            height: 35px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e3e6f0;
        }
        
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
            font-size: 0.85rem;
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--sidebar-blue), var(--sidebar-light-blue));
            color: white;
            border-radius: 10px 10px 0 0;
            border: none;
            padding: 15px 20px;
            font-size: 0.9rem;
        }
        
        .empty-state {
            padding: 40px 20px;
            text-align: center;
            color: var(--dark-color);
            font-size: 0.85rem;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: #bdc3c7;
            margin-bottom: 15px;
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
            justify-content: center;
        }
        
        h1, h2, h3, h4, h5, h6 {
            color: var(--sidebar-blue);
        }
        
        .text-muted {
            color: #7f8c8d !important;
            font-size: 0.8rem;
        }
        
        .page-title {
            font-size: 1.3rem;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .page-subtitle {
            font-size: 0.85rem;
            color: #7f8c8d;
        }

        @media (max-width: 768px) {
            .card-body {
                padding: 12px;
            }
            
            .table-responsive {
                font-size: 0.8rem;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .btn-sm {
                padding: 6px 10px;
            }
            
            .stats-number {
                font-size: 1.5rem;
            }
        }
         @media (max-width: 768px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .topbar {
                left: 0;
            }
            .main-content {
                margin-left: 0;
                margin-top: 70px;
                width: 100%;
            }
        }

        /* Enhanced stats cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: white;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            transition: transform 0.2s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
        }
        
        .stat-icon {
            font-size: 1.5rem;
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--sidebar-blue);
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 0.7rem;
            color: var(--dark-color);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
    </style>
</head>
<body>
    <div id="wrapper" class="d-flex">
        <?php include('admin_sidebar.php'); ?>
        
        <div id="content-wrapper" class="w-100">
            <?php include('admin_topbar.php'); ?>
            
            <!-- Flash Message -->
            <?php if (isset($flash_message)): ?>
            <div class="flash-message">
                <div class="alert alert-<?php echo $flash_type; ?> alert-dismissible fade show shadow" role="alert" style="border-radius: 8px; font-size: 0.85rem;">
                    <i class="fas fa-<?php echo $flash_type === 'success' ? 'check' : 'exclamation'; ?>-circle me-2"></i>
                    <?php echo htmlspecialchars($flash_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            </div>
            <script>
                setTimeout(() => {
                    const flash = document.querySelector('.flash-message');
                    if (flash) flash.remove();
                }, 4000);
            </script>
            <?php endif; ?>

            <div class="container-fluid py-3">
                <!-- Header Section -->
                <div class="row mb-3">
                    <div class="col-12">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h1 class="page-title">User Management</h1>
                                <p class="page-subtitle">Manage and monitor all registered users</p>
                            </div>
                            <div class="stats-card">
                                <div class="stats-number"><?php echo $total_users; ?></div>
                                <div class="stats-label">Total Users</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Enhanced Statistics Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon text-primary">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-value"><?php echo $total_users; ?></div>
                        <div class="stat-label">Total Users</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon text-success">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="stat-value"><?php echo $today_users; ?></div>
                        <div class="stat-label">Today</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon text-info">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <div class="stat-value"><?php echo $week_users; ?></div>
                        <div class="stat-label">This Week</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-icon text-warning">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="stat-value"><?php echo $month_users; ?></div>
                        <div class="stat-label">This Month</div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card">
                    <div class="card-header">
                        <h4 class="mb-0"><i class="fas fa-search me-2"></i>Search & Filter</h4>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-5">
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-end-0">
                                        <i class="fas fa-search text-muted"></i>
                                    </span>
                                    <input type="text" name="search" class="form-control border-start-0" 
                                           placeholder="Search by name, email, or phone..."
                                           value="<?php echo htmlspecialchars($search_term); ?>">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <select name="disability" class="form-control" onchange="this.form.submit()">
                                    <option value="">All Disability Types</option>
                                    <?php foreach ($disability_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type); ?>" 
                                                <?php echo $disability_filter === $type ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <div class="d-grid gap-2 d-md-flex">
                                    <button type="submit" class="btn btn-primary flex-fill">
                                        <i class="fas fa-search me-1"></i>Search
                                    </button>
                                    <a href="admin_users.php" class="btn btn-outline-secondary">
                                        <i class="fas fa-redo me-1"></i>Reset
                                    </a>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Users Table -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4 class="mb-0"><i class="fas fa-users me-2"></i>Registered Users</h4>
                        <span class="badge bg-primary"><?php echo $total_users; ?> Users</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 50px;">Avatar</th>
                                        <th>User Info</th>
                                        <th>Contact</th>
                                        <th>Location</th>
                                        <th>Disability</th>
                                        <th>Registration</th>
                                        <th style="width: 100px;">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($users)): ?>
                                    <tr>
                                        <td colspan="7">
                                            <div class="empty-state">
                                                <i class="fas fa-users-slash"></i>
                                                <h5 class="mt-3 mb-2" style="font-size: 1rem;">No Users Found</h5>
                                                <p class="text-muted">No users match your search criteria</p>
                                                <a href="admin_users.php" class="btn btn-primary mt-2 btn-sm">
                                                    <i class="fas fa-redo me-1"></i>Reset Filters
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td>
                                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=2c3e50&color=fff&size=35" 
                                                     class="user-avatar" alt="<?php echo htmlspecialchars($user['full_name']); ?>">
                                            </td>
                                            <td>
                                                <div>
                                                    <strong class="d-block" style="font-size: 0.9rem;"><?php echo htmlspecialchars($user['full_name']); ?></strong>
                                                    <small class="text-muted">PWD<?php echo str_pad($user['id'], 4, '0', STR_PAD_LEFT); ?></small>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <div class="small"><?php echo htmlspecialchars($user['email'] ?? 'Not provided'); ?></div>
                                                    <div class="small text-muted"><?php echo htmlspecialchars($user['phone']); ?></div>
                                                </div>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['region'] ?? 'Not specified'); ?></td>
                                            <td>
                                                <span class="badge bg-light text-dark border" style="font-size: 0.7rem;">
                                                    <?php echo htmlspecialchars($user['disability_type'] ?? 'Not specified'); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="small">
                                                    <div><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                                                    <div class="text-muted"><?php echo date('g:i A', strtotime($user['created_at'])); ?></div>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-primary view-user" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#userModal"
                                                            data-user='<?php echo htmlspecialchars(json_encode($user)); ?>'
                                                            title="View Details">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                        <input type="hidden" name="action" value="delete">
                                                        <button type="submit" class="btn btn-sm btn-danger" title="Delete User">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Modal -->
    <div class="modal fade" id="userModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" style="font-size: 1rem;"><i class="fas fa-user-circle me-2"></i>User Details</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-3" id="userDetails">
                    <!-- Details will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $('.view-user').on('click', function() {
            const user = JSON.parse($(this).data('user'));
            $('#userDetails').html(`
                <div class="row">
                    <div class="col-md-4 text-center mb-3">
                        <img src="https://ui-avatars.com/api/?name=${encodeURIComponent(user.full_name)}&background=2c3e50&color=fff&size=120" 
                             class="rounded-circle mb-2 shadow" style="width: 120px; height: 120px;">
                        <h5 style="font-size: 1.1rem;" class="fw-bold">${user.full_name}</h5>
                        <p class="text-muted" style="font-size: 0.8rem;">PWD${user.id.toString().padStart(4, '0')}</p>
                        <div class="badge bg-primary" style="font-size: 0.75rem;">${user.disability_type || 'Not specified'}</div>
                    </div>
                    <div class="col-md-8">
                        <div class="row g-2">
                            <div class="col-12">
                                <h6 class="text-primary mb-2 border-bottom pb-1" style="font-size: 0.9rem;">Contact Information</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Email Address</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">${user.email || '<span class="text-muted">Not provided</span>'}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Phone Number</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">${user.phone}</div>
                            </div>
                            <div class="col-12 mt-2">
                                <h6 class="text-primary mb-2 border-bottom pb-1" style="font-size: 0.9rem;">Personal Information</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Region</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">${user.region || '<span class="text-muted">Not specified</span>'}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">District</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">${user.district || '<span class="text-muted">Not specified</span>'}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Date of Birth</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">${user.date_of_birth ? new Date(user.date_of_birth).toLocaleDateString() : '<span class="text-muted">Not provided</span>'}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Gender</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">${user.gender || '<span class="text-muted">Not specified</span>'}</div>
                            </div>
                            <div class="col-12 mt-2">
                                <h6 class="text-primary mb-2 border-bottom pb-1" style="font-size: 0.9rem;">Disability Information</h6>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Disability Type</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">${user.disability_type || '<span class="text-muted">Not specified</span>'}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Disability Severity</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">${user.disability_severity || '<span class="text-muted">Not specified</span>'}</div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label text-muted small mb-1">Communication Preference</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">${user.communication_preference || '<span class="text-muted">Not specified</span>'}</div>
                            </div>
                            <div class="col-12 mt-2">
                                <h6 class="text-primary mb-2 border-bottom pb-1" style="font-size: 0.9rem;">Registration Details</h6>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small mb-1">Registration Date & Time</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">
                                    ${new Date(user.created_at).toLocaleDateString()} at ${new Date(user.created_at).toLocaleTimeString()}
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label text-muted small mb-1">Last Updated</label>
                                <div class="fw-semibold" style="font-size: 0.85rem;">
                                    ${new Date(user.updated_at).toLocaleDateString()} at ${new Date(user.updated_at).toLocaleTimeString()}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `);
        });
    </script>
</body>
</html>