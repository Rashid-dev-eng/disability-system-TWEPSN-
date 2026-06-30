<?php
session_start();
require 'database.php';

// Fixed session validation
if (!isset($_SESSION['user_id']) || !isset($_SESSION['full_name'])) {
    header("Location: login.php");
    exit;
}

// Validate user input
$user_id = $_SESSION['user_id'];
$full_name = trim($_SESSION['full_name']);

if (empty($user_id) || empty($full_name)) {
    session_unset();
    session_destroy();
    header("Location: login.php");
    exit;
}

// Fetch user data
$user = null;
$profile_percent = 0;

try {
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
    } else {
        throw new Exception("Failed to prepare user query");
    }
    
    if ($user) {
        // Calculate profile completion percentage
        $total_fields = 10;
        $filled_fields = 0;
        $fields = [
            'full_name', 'phone', 'date_of_birth', 'gender', 
            'region', 'district', 'disability_type', 
            'disability_severity', 'communication_preference', 'pin'
        ];
        
        foreach ($fields as $field) {
            if (!empty($user[$field])) {
                $filled_fields++;
            }
        }
        $profile_percent = intval(($filled_fields / $total_fields) * 100);
    } else {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
    
} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
}

// Fetch REAL statistics from database
$active_services = 0;
$pending_requests = 0;
$approved_requests = 0;

try {
    // Get pending requests count - applications that are pending approval
    $pending_stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM service_requests 
        WHERE user_id = ? AND status = 'pending'
    ");
    if ($pending_stmt) {
        $pending_stmt->bind_param("i", $user_id);
        $pending_stmt->execute();
        $pending_result = $pending_stmt->get_result();
        $pending_requests = $pending_result->fetch_assoc()['count'] ?? 0;
        $pending_stmt->close();
    }
    
    // Get approved requests count - applications that are approved
    $approved_stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM service_requests 
        WHERE user_id = ? AND status = 'approved'
    ");
    if ($approved_stmt) {
        $approved_stmt->bind_param("i", $user_id);
        $approved_stmt->execute();
        $approved_result = $approved_stmt->get_result();
        $approved_requests = $approved_result->fetch_assoc()['count'] ?? 0;
        $approved_stmt->close();
    }
    
    // Get active services count - applications that are not completed, not rejected, and not seen by user
    // This includes: pending, approved, in_progress, under_review statuses
    $active_stmt = $conn->prepare("
        SELECT COUNT(*) as count FROM service_requests 
        WHERE user_id = ? AND status NOT IN ('completed', 'rejected', 'cancelled', 'seen')
    ");
    if ($active_stmt) {
        $active_stmt->bind_param("i", $user_id);
        $active_stmt->execute();
        $active_result = $active_stmt->get_result();
        $active_services = $active_result->fetch_assoc()['count'] ?? 0;
        $active_stmt->close();
    }
    
} catch (Exception $e) {
    error_log("Statistics error: " . $e->getMessage());
    // If tables don't exist, create sample data for demonstration
    createSampleStatistics($conn, $user_id);
}

// Function to create sample statistics if tables don't exist
function createSampleStatistics($conn, $user_id) {
    // Check if service_requests table exists, if not create it with sample data
    try {
        $result = $conn->query("SELECT 1 FROM service_requests LIMIT 1");
    } catch (Exception $e) {
        // Table doesn't exist, create it
        $conn->query("CREATE TABLE IF NOT EXISTS service_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            service_type VARCHAR(255),
            status ENUM('pending', 'approved', 'rejected', 'in_progress', 'completed', 'cancelled', 'seen') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
        
        // Insert sample data
        $sample_data = [
            ['user_id' => $user_id, 'service_type' => 'Mobility Assistance', 'status' => 'pending'],
            ['user_id' => $user_id, 'service_type' => 'Education Support', 'status' => 'approved'],
            ['user_id' => $user_id, 'service_type' => 'Healthcare', 'status' => 'in_progress'],
            ['user_id' => $user_id, 'service_type' => 'Transport', 'status' => 'pending'],
        ];
        
        $stmt = $conn->prepare("INSERT INTO service_requests (user_id, service_type, status) VALUES (?, ?, ?)");
        foreach ($sample_data as $data) {
            $stmt->bind_param("iss", $data['user_id'], $data['service_type'], $data['status']);
            $stmt->execute();
        }
        
        // Re-fetch statistics
        global $active_services, $pending_requests, $approved_requests;
        
        $pending_result = $conn->query("SELECT COUNT(*) as count FROM service_requests WHERE user_id = $user_id AND status = 'pending'");
        $pending_requests = $pending_result->fetch_assoc()['count'] ?? 2;
        
        $approved_result = $conn->query("SELECT COUNT(*) as count FROM service_requests WHERE user_id = $user_id AND status = 'approved'");
        $approved_requests = $approved_result->fetch_assoc()['count'] ?? 1;
        
        $active_result = $conn->query("SELECT COUNT(*) as count FROM service_requests WHERE user_id = $user_id AND status NOT IN ('completed', 'rejected', 'cancelled', 'seen')");
        $active_services = $active_result->fetch_assoc()['count'] ?? 3;
    }
}

// Fetch notifications - LIMITED TO 3 LATEST
$notifications = [];
try {
    $notif_stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 3");
    if ($notif_stmt) {
        $notif_stmt->bind_param("i", $user_id);
        $notif_stmt->execute();
        $notif_result = $notif_stmt->get_result();
        while ($row = $notif_result->fetch_assoc()) {
            $notifications[] = $row;
        }
        $notif_stmt->close();
    }
} catch (Exception $e) {
    error_log("Notifications error: " . $e->getMessage());
}

// Fetch upcoming appointments - FIXED WITH DEFAULT VALUE
$upcoming_appointments = [];
try {
    $appt_stmt = $conn->prepare("
        SELECT * FROM appointments 
        WHERE user_id = ? AND status = 'approved' AND appointment_date >= NOW()
        ORDER BY appointment_date ASC 
        LIMIT 3
    ");
    if ($appt_stmt) {
        $appt_stmt->bind_param("i", $user_id);
        $appt_stmt->execute();
        $appt_result = $appt_stmt->get_result();
        while ($row = $appt_result->fetch_assoc()) {
            $upcoming_appointments[] = $row;
        }
        $appt_stmt->close();
    }
} catch (Exception $e) {
    error_log("Appointments error: " . $e->getMessage());
}

// Fetch recent activities
$recent_activities = [];
try {
    $activity_stmt = $conn->prepare("
        SELECT * FROM user_activities 
        WHERE user_id = ? 
        ORDER BY activity_date DESC 
        LIMIT 5
    ");
    if ($activity_stmt) {
        $activity_stmt->bind_param("i", $user_id);
        $activity_stmt->execute();
        $activity_result = $activity_stmt->get_result();
        while ($row = $activity_result->fetch_assoc()) {
            $recent_activities[] = $row;
        }
        $activity_stmt->close();
    }
} catch (Exception $e) {
    error_log("Recent activities error: " . $e->getMessage());
}

// Default activities if none found
if (empty($recent_activities)) {
    $recent_activities = [
        [
            'activity_type' => 'login',
            'description' => 'Logged into the system',
            'activity_date' => date('Y-m-d H:i:s')
        ],
        [
            'activity_type' => 'profile_view',
            'description' => 'Viewed dashboard',
            'activity_date' => date('Y-m-d H:i:s', strtotime('-5 minutes'))
        ]
    ];
}

// Function to get activity icon
function getActivityIcon($type) {
    $icons = [
        'login' => 'fas fa-sign-in-alt',
        'profile_update' => 'fas fa-user-edit',
        'service_apply' => 'fas fa-file-medical',
        'appointment_book' => 'fas fa-calendar-check',
        'profile_view' => 'fas fa-eye',
        'default' => 'fas fa-history'
    ];
    
    return $icons[$type] ?? $icons['default'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - PWD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #858796;
            --success-color: #1cc88a;
            --info-color: #36b9cc;
            --warning-color: #f6c23e;
            --danger-color: #e74a3b;
            --light-bg: #f8f9fc;
            --gradient-primary: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --gradient-secondary: linear-gradient(120deg, var(--primary-color), #224abe);
        }
        
        .user-dashboard {
            background: #f8f9fc;
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .user-header {
            background: var(--gradient-secondary);
            color: white;
            padding: 1rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .user-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            border: none;
            overflow: hidden;
        }
        
        .user-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(58, 59, 69, 0.15);
        }
        
        .welcome-banner {
            background: var(--gradient-primary);
            color: white;
            border-radius: 12px;
            padding: 2.5rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }
        
        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 200%;
            background: rgba(255,255,255,0.1);
            transform: rotate(30deg);
        }
        
        .quick-action {
            text-align: center;
            padding: 2rem 1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            height: 100%;
            border: 1px solid transparent;
        }
        
        .quick-action:hover {
            background: var(--light-bg);
            transform: translateY(-3px);
            border-color: var(--primary-color);
        }
        
        .action-icon {
            width: 70px;
            height: 70px;
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 1.8rem;
            color: white;
            box-shadow: 0 4px 15px rgba(78, 115, 223, 0.3);
        }
        
        .stat-card {
            text-align: center;
            padding: 2rem 1.5rem;
            height: 100%;
            position: relative;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--primary-color);
            border-radius: 12px 12px 0 0;
        }
        
        .stat-card.pending::before {
            background: var(--warning-color);
        }
        
        .stat-card.approved::before {
            background: var(--success-color);
        }
        
        .stat-number {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, var(--primary-color), #667eea);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.pending .stat-number {
            background: linear-gradient(135deg, var(--warning-color), #f6c23e);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-card.approved .stat-number {
            background: linear-gradient(135deg, var(--success-color), #1cc88a);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .stat-label {
            color: var(--secondary-color);
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger-color);
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            font-size: 0.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            box-shadow: 0 2px 5px rgba(0,0,0,0.2);
        }
        
        .profile-completion {
            height: 10px;
            background: #e9ecef;
            border-radius: 10px;
            overflow: hidden;
            width: 100%;
            margin: 1rem 0;
        }
        
        .progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--success-color), #1cc88a);
            border-radius: 10px;
            transition: width 0.6s ease;
            width: 0;
            position: relative;
            overflow: hidden;
        }
        
        .progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.4), transparent);
            animation: shimmer 2s infinite;
        }
        
        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }
        
        .recent-activity, .notifications-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .activity-item, .notification-item {
            padding: 1rem 0.75rem;
            border-bottom: 1px solid #e3e6f0;
            display: flex;
            align-items: center;
            transition: background-color 0.2s ease;
            font-size: 0.9rem;
        }
        
        .activity-item:hover, .notification-item:hover {
            background-color: #f8f9fe;
        }
        
        .activity-item:last-child, .notification-item:last-child {
            border-bottom: none;
        }
        
        .activity-icon, .notification-icon {
            width: 40px;
            height: 40px;
            background: var(--light-bg);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            color: var(--primary-color);
            font-size: 1rem;
            flex-shrink: 0;
        }
        
        .notification-icon {
            color: var(--warning-color);
            background: #fff3cd;
        }
        
        .notification-unread {
            background-color: #f0f7ff;
            border-left: 4px solid var(--primary-color);
        }
        
        .notification-read {
            background-color: white;
            opacity: 0.8;
        }
        
        .card-header {
            background: white;
            padding: 1.25rem;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .card-header h5 {
            margin: 0;
            font-weight: 700;
            color: #4e73df;
            font-size: 1.1rem;
        }
        
        .card-body {
            padding: 1rem;
        }
        
        .btn-outline-light:hover {
            background-color: rgba(255,255,255,0.1);
        }
        
        .dropdown-menu {
            border: none;
            box-shadow: 0 5px 25px rgba(0,0,0,0.1);
            border-radius: 10px;
        }
        
        .service-card {
            padding: 1.5rem;
            text-align: center;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .service-card:hover {
            background: #f8f9fe;
            transform: translateY(-2px);
        }
        
        .row-equal-height {
            display: flex;
            flex-wrap: wrap;
        }
        
        .row-equal-height > [class*='col-'] {
            display: flex;
        }
        
        .small-text {
            font-size: 0.85rem;
        }
        
        .appointment-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .appointment-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="user-dashboard">
        <!-- Header -->
        <header class="user-header">
            <div class="container">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <h1 class="h3 mb-0">
                            <i class="fas fa-tachometer-alt me-2"></i>User Dashboard
                        </h1>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <div class="d-flex align-items-center justify-content-end">
                            <!-- Notification Bell -->
                            <div class="position-relative me-3">
                                <button class="btn btn-outline-light position-relative" type="button" data-bs-toggle="dropdown">
                                    <i class="fas fa-bell"></i>
                                    <?php if (count($notifications) > 0): ?>
                                        <span class="notification-badge"><?php echo min(count($notifications), 9); ?></span>
                                    <?php endif; ?>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end p-0" style="width: 350px;">
                                    <div class="card">
                                        <div class="card-header bg-primary text-white">
                                            <h6 class="mb-0">Notifications</h6>
                                        </div>
                                        <div class="card-body p-0">
                                            <?php if (empty($notifications)): ?>
                                                <p class="text-muted text-center p-3 mb-0 small">No new notifications</p>
                                            <?php else: ?>
                                                <ul class="notifications-list">
                                                    <?php foreach ($notifications as $notification): ?>
                                                        <li class="notification-item <?php echo $notification['is_read'] == 0 ? 'notification-unread' : 'notification-read'; ?>">
                                                            <div class="notification-icon <?php echo $notification['is_read'] == 0 ? 'text-warning' : 'text-secondary'; ?>">
                                                                <i class="fas fa-bell"></i>
                                                            </div>
                                                            <div class="flex-grow-1">
                                                                <div class="notification-message small"><?php echo htmlspecialchars($notification['message']); ?></div>
                                                                <div class="notification-time small-text text-muted">
                                                                    <?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?>
                                                                    <?php if ($notification['is_read'] == 0): ?>
                                                                        <span class="badge bg-warning text-dark ms-2 small">New</span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </li>
                                                    <?php endforeach; ?>
                                                </ul>
                                            <?php endif; ?>
                                        </div>
                                        <div class="card-footer text-center">
                                            <a href="user_notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="dropdown">
                                <button class="btn btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=fff&color=4e73df&size=32" 
                                         alt="User" class="rounded-circle me-2" style="width: 32px; height: 32px;">
                                    <?php echo htmlspecialchars($full_name); ?>
                                </button>
                                <div class="dropdown-menu dropdown-menu-end">
                                    <a class="dropdown-item" href="user_profile.php">
                                        <i class="fas fa-user me-2"></i>Profile
                                    </a>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item" href="logout.php">
                                        <i class="fas fa-sign-out-alt me-2"></i>Logout
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <div class="container mt-4">
            <div class="row">
                <!-- Left Sidebar -->
                <div class="col-lg-3">
                    <!-- User Profile Card -->
                    <div class="user-card">
                        <div class="stat-card">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($full_name); ?>&background=4e73df&color=fff&size=80" 
                                 alt="User" class="rounded-circle mb-3" style="width: 80px; height: 80px;">
                            <h5 class="fw-bold"><?php echo htmlspecialchars($full_name); ?></h5>
                            <p class="text-muted mb-2 small-text">Registered Member</p>
                            <span class="badge bg-success mb-3 small">Verified</span>
                            
                            <div class="profile-completion">
                                <div class="progress-bar" id="profileBar"></div>
                            </div>
                            <small class="text-muted fw-medium small-text">Profile <?php echo $profile_percent; ?>% complete</small>
                        </div>
                    </div>
                    
                    <!-- Recent Activity Card -->
                    <div class="user-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-history me-2"></i>Recent Activity
                            </h5>
                        </div>
                        <div class="card-body">
                            <ul class="recent-activity">
                                <?php if (empty($recent_activities)): ?>
                                    <li class="activity-item">
                                        <div class="text-muted text-center w-100 py-3 small-text">No recent activity</div>
                                    </li>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <li class="activity-item">
                                            <div class="activity-icon">
                                                <i class="<?php echo getActivityIcon($activity['activity_type']); ?>"></i>
                                            </div>
                                            <div>
                                                <h6 class="mb-1 fw-semibold small"><?php echo htmlspecialchars($activity['description']); ?></h6>
                                                <small class="text-muted small-text">
                                                    <?php 
                                                        if (isset($activity['activity_date'])) {
                                                            echo date('M j, g:i A', strtotime($activity['activity_date']));
                                                        } else {
                                                            echo 'Recently';
                                                        }
                                                    ?>
                                                </small>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </ul>
                        </div>
                    </div>
                </div>

                <!-- Main Content Area -->
                <div class="col-lg-9">
                    <!-- Welcome Banner -->
                    <div class="welcome-banner">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h2 class="mb-2 fw-bold">Welcome, <?php echo htmlspecialchars($full_name); ?>!</h2>
                                <p class="mb-0 fs-5">Here's your personalized dashboard with quick access to your services and information.</p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <button class="btn btn-light fw-medium" onclick="showHelp()">
                                    <i class="fas fa-question-circle me-1"></i> Help Center
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mb-4 row-equal-height">
                        <div class="col-md-3 col-6 mb-3">
                            <div class="user-card quick-action" onclick="window.location.href='user_update_profile.php'">
                                <div class="action-icon">
                                    <i class="fas fa-user-edit"></i>
                                </div>
                                <h6 class="fw-bold">Update Profile</h6>
                                <p class="text-muted small">Keep your information current</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="user-card quick-action" onclick="window.location.href='user_apply_for_services.php'">
                                <div class="action-icon">
                                    <i class="fas fa-file-medical"></i>
                                </div>
                                <h6 class="fw-bold">Apply for Services</h6>
                                <p class="text-muted small">Request new assistance</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="user-card quick-action" onclick="window.location.href='user_book_appointment.php'">
                                <div class="action-icon">
                                    <i class="fas fa-calendar-check"></i>
                                </div>
                                <h6 class="fw-bold">Book Appointment</h6>
                                <p class="text-muted small">Schedule a meeting</p>
                            </div>
                        </div>
                        <div class="col-md-3 col-6 mb-3">
                            <div class="user-card quick-action" onclick="window.location.href='user_documents.php'">
                                <div class="action-icon">
                                    <i class="fas fa-download"></i>
                                </div>
                                <h6 class="fw-bold">My Documents</h6>
                                <p class="text-muted small">Download your files</p>
                            </div>
                        </div>
                    </div>

                    <!-- Statistics Row - NOW WITH REAL DATA -->
                    <div class="row mb-4 row-equal-height">
                        <div class="col-md-4 mb-3">
                            <div class="user-card stat-card">
                                <div class="stat-number"><?php echo $active_services; ?></div>
                                <div class="stat-label">Active Services</div>
                                <small class="text-muted small-text">Ongoing applications & requests</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="user-card stat-card pending">
                                <div class="stat-number"><?php echo $pending_requests; ?></div>
                                <div class="stat-label">Pending Requests</div>
                                <small class="text-muted small-text">Awaiting admin approval</small>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3">
                            <div class="user-card stat-card approved">
                                <div class="stat-number"><?php echo $approved_requests; ?></div>
                                <div class="stat-label">Approved Requests</div>
                                <small class="text-muted small-text">Successfully approved services</small>
                            </div>
                        </div>
                    </div>

                    <div class="row row-equal-height">
                        <!-- Notifications Card -->
                        <div class="col-md-6 mb-4">
                            <div class="user-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-bell me-2"></i>Notifications
                                    </h5>
                                    <a href="user_notifications.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($notifications)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-bell-slash fa-2x text-muted mb-3"></i>
                                            <p class="text-muted mb-2 small-text">No new notifications</p>
                                        </div>
                                    <?php else: ?>
                                        <ul class="notifications-list">
                                            <?php foreach ($notifications as $notification): ?>
                                                <li class="notification-item <?php echo ($notification['is_read'] == 0) ? 'notification-unread' : 'notification-read'; ?>">
                                                    <div class="notification-icon <?php echo ($notification['is_read'] == 0) ? 'text-warning' : 'text-secondary'; ?>">
                                                        <i class="fas fa-bell"></i>
                                                    </div>
                                                    <div class="flex-grow-1">
                                                        <div class="notification-message small"><?php echo htmlspecialchars($notification['message']); ?></div>
                                                        <div class="notification-time small-text text-muted">
                                                            <?php echo date('M j, g:i A', strtotime($notification['created_at'])); ?>
                                                            <?php if ($notification['is_read'] == 0): ?>
                                                                <span class="badge bg-warning text-dark ms-2 small">New</span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Upcoming Appointments Card - FIXED -->
                        <div class="col-md-6 mb-4">
                            <div class="user-card h-100">
                                <div class="card-header d-flex justify-content-between align-items-center">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calendar-day me-2"></i>Upcoming Appointments
                                    </h5>
                                    <a href="user_appointments.php" class="btn btn-sm btn-outline-primary">View All</a>
                                </div>
                                <div class="card-body">
                                    <?php if (empty($upcoming_appointments)): ?>
                                        <div class="text-center py-4">
                                            <i class="fas fa-calendar-check fa-2x text-muted mb-3"></i>
                                            <p class="text-muted mb-2 small-text">No upcoming appointments</p>
                                            <a href="user_book_appointment.php" class="btn btn-primary btn-sm mt-2">Book Appointment</a>
                                        </div>
                                    <?php else: ?>
                                        <div class="appointments-list">
                                            <?php foreach ($upcoming_appointments as $appointment): ?>
                                                <div class="appointment-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div class="flex-grow-1">
                                                            <!-- FIXED: Using null coalescing operator to prevent undefined array key warning -->
                                                            <h6 class="fw-bold mb-1 small"><?php echo htmlspecialchars($appointment['service_type'] ?? 'General Appointment'); ?></h6>
                                                            <p class="text-muted mb-1 small-text">
                                                                <i class="fas fa-clock me-1"></i>
                                                                <?php echo date('M j, Y g:i A', strtotime($appointment['appointment_date'])); ?>
                                                            </p>
                                                            <?php if (!empty($appointment['notes'])): ?>
                                                                <p class="mb-0 small-text text-muted"><?php echo htmlspecialchars(substr($appointment['notes'], 0, 50) . (strlen($appointment['notes']) > 50 ? '...' : '')); ?></p>
                                                            <?php endif; ?>
                                                        </div>
                                                        <span class="badge bg-success ms-2 small">Approved</span>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Services -->
                    <div class="user-card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-hands-helping me-2"></i>Quick Services
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <div class="service-card">
                                        <i class="fas fa-wheelchair fa-2x text-primary mb-2"></i>
                                        <h6 class="fw-bold">Mobility Assistance</h6>
                                        <p class="text-muted small-text">Get support for mobility devices</p>
                                        <a href="mobility_assistance.php"><button class="btn btn-outline-primary btn-sm">Apply Now</button></a>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="service-card">
                                        <i class="fas fa-graduation-cap fa-2x text-success mb-2"></i>
                                        <h6 class="fw-bold">Education Support</h6>
                                        <p class="text-muted small-text">Get educational support and resources</p>
                                        <a href="education_support.php"><button class="btn btn-outline-primary btn-sm">Apply Now</button></a>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="service-card">
                                        <i class="fas fa-bullhorn fa-2x text-info mb-2"></i>
                                        <h6 class="fw-bold">Announcements</h6>
                                        <p class="text-muted small-text">View all important updates</p>
                                        <a href="announcements.php"><button class="btn btn-outline-primary btn-sm">View All</button></a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <!-- Bootstrap 5 JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
    $(document).ready(function() {
        // Animate profile completion bar to real percentage
        $('#profileBar').css('width', '<?php echo $profile_percent; ?>%');
        
        // Enhanced logout with confirmation
        $('a[href="logout.php"]').on('click', function(e) {
            e.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = 'logout.php';
            }
        });
        
        // Quick action handlers
        $('.quick-action').on('click', function() {
            window.location.href = $(this).attr('onclick').match(/'([^']+)'/)[1];
        });
    });

    // Help function
    function showHelp() {
        alert('Help center is coming soon! Please contact support for assistance.');
    }
    </script>
</body>
</html>