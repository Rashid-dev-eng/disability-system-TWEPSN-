<?php
session_start();
require '../database.php';

// Check if admin is logged in - FIXED SESSION CHECK
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Safe query execution function
function executeQuery($conn, $query) {
    $result = $conn->query($query);
    if (!$result) {
        error_log("Query failed: " . $conn->error);
        error_log("Query: " . $query);
        return false;
    }
    return $result;
}

// Get total users count
$total_users = 0;
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM users");
if ($result) {
    $total_users = $result->fetch_assoc()['count'];
}

// Get pending applications count (across all application tables)
$pending_applications = 0;
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM mobility_assistance_applications WHERE status = 'pending'");
if ($result) {
    $pending_applications += $result->fetch_assoc()['count'];
}
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM education_support_applications WHERE status = 'pending'");
if ($result) {
    $pending_applications += $result->fetch_assoc()['count'];
}
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM service_applications WHERE status = 'pending'");
if ($result) {
    $pending_applications += $result->fetch_assoc()['count'];
}

// Get approved applications count (across all application tables)
$approved_applications = 0;
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM mobility_assistance_applications WHERE status = 'approved'");
if ($result) {
    $approved_applications += $result->fetch_assoc()['count'];
}
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM education_support_applications WHERE status = 'approved'");
if ($result) {
    $approved_applications += $result->fetch_assoc()['count'];
}
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM service_applications WHERE status = 'approved'");
if ($result) {
    $approved_applications += $result->fetch_assoc()['count'];
}

// Get scheduled appointments count
$scheduled_appointments = 0;
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM appointments WHERE status = 'scheduled'");
if ($result) {
    $scheduled_appointments = $result->fetch_assoc()['count'];
}

// Get total applications count
$total_applications = $pending_applications + $approved_applications;

// Get recent activity (last 5 activities)
$recent_activities = [];
$activity_query = "
    (SELECT 'user_registration' as type, full_name, email, created_at as timestamp 
     FROM users 
     ORDER BY created_at DESC LIMIT 3)
    UNION ALL
    (SELECT 'application_submitted' as type, applicant_name as full_name, email, created_at as timestamp 
     FROM mobility_assistance_applications 
     ORDER BY created_at DESC LIMIT 2)
    UNION ALL
    (SELECT 'application_submitted' as type, applicant_name as full_name, email, created_at as timestamp 
     FROM education_support_applications 
     ORDER BY created_at DESC LIMIT 2)
    UNION ALL
    (SELECT 'application_submitted' as type, applicant_name as full_name, email, created_at as timestamp 
     FROM service_applications 
     ORDER BY created_at DESC LIMIT 2)
    UNION ALL
    (SELECT 'appointment_scheduled' as type, patient_name as full_name, email, created_at as timestamp 
     FROM appointments 
     ORDER BY created_at DESC LIMIT 2)
    ORDER BY timestamp DESC 
    LIMIT 5
";

$activity_result = executeQuery($conn, $activity_query);
if ($activity_result) {
    while ($row = $activity_result->fetch_assoc()) {
        $recent_activities[] = $row;
    }
}

// Get recent registrations (last 3 users)
$recent_registrations = [];
$registration_query = "SELECT full_name, email, created_at FROM users ORDER BY created_at DESC LIMIT 3";
$registration_result = executeQuery($conn, $registration_query);
if ($registration_result) {
    while ($row = $registration_result->fetch_assoc()) {
        $recent_registrations[] = $row;
    }
}

// Get application status counts for chart
$pending_chart = $pending_applications;
$approved_chart = $approved_applications;
$rejected_applications = 0;

$result = executeQuery($conn, "SELECT COUNT(*) as count FROM mobility_assistance_applications WHERE status = 'rejected'");
if ($result) {
    $rejected_applications += $result->fetch_assoc()['count'];
}
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM education_support_applications WHERE status = 'rejected'");
if ($result) {
    $rejected_applications += $result->fetch_assoc()['count'];
}
$result = executeQuery($conn, "SELECT COUNT(*) as count FROM service_applications WHERE status = 'rejected'");
if ($result) {
    $rejected_applications += $result->fetch_assoc()['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Disability System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        .flash-message {
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 10000;
            animation: slideInRight 0.3s ease, slideOutRight 0.3s ease 2.7s forwards;
        }
        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
        .stat-card {
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
        }
        .system-alert {
            border-left: 4px solid #dc3545;
        }
        
        /* Sidebar Styles */
        .sidebar {
            background: linear-gradient(180deg, #2c3e50 0%, #3498db 100%) !important;
            min-height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            z-index: 1000;
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
        
        /* Topbar - NO GAPS */
        .topbar {
            background: linear-gradient(90deg, #2c3e50 0%, #3498db 100%) !important;
            height: 70px;
            position: fixed;
            top: 0;
            left: 250px;
            right: 0;
            z-index: 999;
            margin: 0;
            border: none;
        }
        
        /* Main Content - NO GAPS */
        .main-content {
            margin: 0;
            padding: 0;
            margin-left: 100px;
            margin-top: 70px;
            min-height: calc(100vh - 70px);
            background-color: #f8f9fa;
            width: calc(100% - 250px);
        }
        
        /* Content container - NO GAPS */
        .content-container {
            margin: 0;
            padding: 0;
            width: 100%;
            max-width: 100%;
        }
        
        /* Remove all gaps from content */
        .content-container .row {
            margin: 0;
            padding: 20px;
        }
        
        .content-container .col-xl-3, 
        .content-container .col-xl-8, 
        .content-container .col-xl-4, 
        .content-container .col-lg-7, 
        .content-container .col-lg-5, 
        .content-container .col-md-6 {
            padding: 10px;
        }
        
        .content-container .card {
            margin-bottom: 0;
        }
        
        /* Remove gaps from specific sections */
        .content-container > .row:first-child {
            padding-top: 0;
            padding-bottom: 10px;
        }
        
        .content-container > .row:nth-child(2) {
            padding-top: 10px;
            padding-bottom: 10px;
        }
        
        .content-container > .row:last-child {
            padding-top: 10px;
            padding-bottom: 0;
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
    </style>
</head>
<body>
    <!-- Include Sidebar -->
    <?php include('admin_sidebar.php'); ?>
    
    <!-- Include Topbar -->
    <?php include('admin_topbar.php'); ?>

    <!-- Main Content - NO GAPS -->
    <div class="main-content">
        <div class="content-container">
            <!-- Statistics Cards -->
            <div class="row">
                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card border-left-primary shadow h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Total Users</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $total_users; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-users fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card border-left-warning shadow h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Pending Applications</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $pending_applications; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-clock fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card border-left-success shadow h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Approved Applications</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $approved_applications; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6">
                    <div class="card stat-card border-left-info shadow h-100">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Scheduled Appointments</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $scheduled_appointments; ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts and Recent Activity -->
            <div class="row">
                <!-- Applications Chart -->
                <div class="col-xl-8 col-lg-7">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Applications Overview</h6>
                        </div>
                        <div class="card-body">
                            <div class="chart-area">
                                <canvas id="applicationsChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="col-xl-4 col-lg-5">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Activity</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php if (!empty($recent_activities)): ?>
                                    <?php foreach($recent_activities as $activity): ?>
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">
                                                    <?php 
                                                    switch($activity['type']) {
                                                        case 'user_registration':
                                                            echo 'User Registration';
                                                            break;
                                                        case 'application_submitted':
                                                            echo 'Application Submitted';
                                                            break;
                                                        case 'appointment_scheduled':
                                                            echo 'Appointment Scheduled';
                                                            break;
                                                        default:
                                                            echo 'System Activity';
                                                    }
                                                    ?>
                                                </h6>
                                                <small><?php echo time_ago($activity['timestamp']); ?></small>
                                            </div>
                                            <p class="mb-1">
                                                <?php 
                                                switch($activity['type']) {
                                                    case 'user_registration':
                                                        echo 'New user registered in the system';
                                                        break;
                                                    case 'application_submitted':
                                                        echo 'New application was submitted';
                                                        break;
                                                    case 'appointment_scheduled':
                                                        echo 'New appointment was scheduled';
                                                        break;
                                                    default:
                                                        echo 'System activity recorded';
                                                }
                                                ?>
                                            </p>
                                            <small class="text-muted">By: <?php echo htmlspecialchars($activity['email'] ?? 'system'); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No recent activity
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Registrations -->
                    <div class="card shadow mt-3">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Recent Registrations</h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group list-group-flush">
                                <?php if (!empty($recent_registrations)): ?>
                                    <?php foreach($recent_registrations as $user): ?>
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h6>
                                                <small><?php echo time_ago($user['created_at']); ?></small>
                                            </div>
                                            <small class="text-muted">Registered</small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No recent registrations
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Info -->
            <div class="row">
                <div class="col-12">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-info-circle me-2"></i>System Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>System Status:</strong> Operational<br>
                                    <strong>Logged in as:</strong> <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?><br>
                                    <strong>Session ID:</strong> <?php echo session_id(); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Total Users:</strong> <?php echo $total_users; ?><br>
                                    <strong>Total Applications:</strong> <?php echo $total_applications; ?><br>
                                    <strong>Server Time:</strong> <span id="currentTime"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Applications Chart
        document.addEventListener('DOMContentLoaded', function() {
            const ctx = document.getElementById('applicationsChart');
            if (ctx) {
                new Chart(ctx, {
                    type: 'bar',
                    data: {
                        labels: ['Pending', 'Approved', 'Rejected'],
                        datasets: [{
                            label: 'Applications',
                            data: [
                                <?php echo $pending_chart; ?>,
                                <?php echo $approved_chart; ?>,
                                <?php echo $rejected_applications; ?>
                            ],
                            backgroundColor: [
                                '#f6c23e',
                                '#1cc88a',
                                '#e74a3b'
                            ],
                            borderColor: [
                                '#f6c23e',
                                '#1cc88a',
                                '#e74a3b'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        scales: {
                            y: {
                                beginAtZero: true
                            }
                        }
                    }
                });
            }

            // Update current time
            function updateTime() {
                const now = new Date();
                document.getElementById('currentTime').textContent = now.toLocaleString();
            }
            updateTime();
            setInterval(updateTime, 1000);
        });
    </script>
</body>
</html>

<?php
// Helper function to display time ago
function time_ago($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } else {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    }
}