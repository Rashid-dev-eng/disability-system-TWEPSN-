<?php
session_start();

// Database connection
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "disability-tracker";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// Define sanitize_input function
if (!function_exists('sanitize_input')) {
    function sanitize_input($data) {
        if ($data === null) {
            return '';
        }
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return $data;
    }
}

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Handle application status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $application_id = sanitize_input($_POST['application_id'] ?? '');
    $action = sanitize_input($_POST['action']);
    $application_type = sanitize_input($_POST['application_type'] ?? 'service_applications');
    
    if ($application_id && in_array($action, ['approved', 'completed', 'rejected'])) {
        $table_name = $application_type;
        
        $stmt = $conn->prepare("UPDATE $table_name SET status = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $action, $application_id);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Application marked as {$action}!";
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = "Failed to update application.";
                $_SESSION['flash_type'] = 'danger';
            }
            $stmt->close();
        }
    }
    header("Location: admin_service_applications.php");
    exit;
}

// Flash message handling
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Fetch applications from all tables with secure filters
$status_filter = sanitize_input($_GET['status'] ?? 'all');
$search_term = sanitize_input($_GET['search'] ?? '');
$service_filter = sanitize_input($_GET['service_type'] ?? 'all');

// Initialize applications array
$applications = [];
$status_counts = [
    'all' => 0,
    'pending' => 0,
    'approved' => 0,
    'completed' => 0,
    'rejected' => 0
];

// Fetch from ALL three tables
$tables_to_check = [
    'education_support_applications' => 'Education Support',
    'mobility_assistance_applications' => 'Mobility Assistance', 
    'service_applications' => 'General Service'
];

foreach ($tables_to_check as $table_name => $display_name) {
    // Check if table exists
    $table_exists = $conn->query("SHOW TABLES LIKE '$table_name'");
    if ($table_exists && $table_exists->num_rows > 0) {
        
        // Build base query for each table
        $query = "SELECT 
                    app.*, 
                    u.full_name, 
                    u.phone, 
                    u.email, 
                    u.date_of_birth, 
                    u.gender, 
                    u.address, 
                    u.city, 
                    u.country, 
                    u.disability_type as user_disability, 
                    u.emergency_contact, 
                    u.created_at as user_created,
                    '$table_name' as application_type,
                    '$display_name' as service_type_display
                  FROM $table_name app 
                  JOIN users u ON app.user_id = u.id 
                  WHERE 1=1";
        
        $params = [];
        $types = "";

        // Add status filter
        if ($status_filter !== 'all') {
            $query .= " AND app.status = ?";
            $params[] = $status_filter;
            $types .= "s";
        }

        // Add search filter
        if (!empty($search_term)) {
            $query .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
            $search_term_like = "%$search_term%";
            $params[] = $search_term_like;
            $params[] = $search_term_like;
            $params[] = $search_term_like;
            $types .= "sss";
        }

        // Add service type filter
        if ($service_filter !== 'all' && $service_filter === $display_name) {
            // Only show applications from this table if service type matches
        } elseif ($service_filter !== 'all') {
            continue; // Skip this table if service filter doesn't match
        }

        $query .= " ORDER BY app.created_at DESC";

        $stmt = $conn->prepare($query);
        if ($stmt) {
            if (!empty($params)) {
                $stmt->bind_param($types, ...$params);
            }
            if ($stmt->execute()) {
                $result = $stmt->get_result();
                if ($result) {
                    while ($row = $result->fetch_assoc()) {
                        $applications[] = $row;
                    }
                }
            }
            $stmt->close();
        }

        // Get status counts for this table
        $count_query = "SELECT status, COUNT(*) as count FROM $table_name GROUP BY status";
        $count_result = $conn->query($count_query);
        if ($count_result) {
            while ($row = $count_result->fetch_assoc()) {
                $status = $row['status'];
                if (isset($status_counts[$status])) {
                    $status_counts[$status] += $row['count'];
                }
            }
        }
    }
}

// Calculate total counts
$status_counts['all'] = array_sum([
    $status_counts['pending'],
    $status_counts['approved'], 
    $status_counts['completed'],
    $status_counts['rejected']
]);

// Get total applications count
$total_applications = $status_counts['all'];

// Get service types for filter dropdown
$service_types = [
    ['service_type' => 'Education Support'],
    ['service_type' => 'Mobility Assistance'],
    ['service_type' => 'General Service']
];

// Sort applications by creation date (newest first)
usort($applications, function($a, $b) {
    return strtotime($b['created_at']) - strtotime($a['created_at']);
});

// Count pending applications for display
$pending_count = 0;
foreach ($applications as $app) {
    if ($app['status'] === 'pending') {
        $pending_count++;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Applications - Disability System Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .flash-message {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
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
        
        .status-badge {
            font-size: 0.75rem;
            padding: 0.35rem 0.65rem;
        }
        
        .application-card {
            transition: all 0.3s ease;
            border-left: 4px solid #ffc107;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .application-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .stat-card {
            transition: transform 0.2s;
            border-radius: 10px;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .card-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(45deg, #007bff, #0056b3);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.5rem;
            margin-right: 15px;
        }
        
        .application-service-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
            margin-bottom: 10px;
        }
        
        .card-disability-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        
        .mobility { background: linear-gradient(45deg, #28a745, #20c997); }
        .education { background: linear-gradient(45deg, #17a2b8, #6f42c1); }
        .default-service { background: linear-gradient(45deg, #6c757d, #495057); }
        
        .btn-action {
            margin: 2px;
            font-size: 0.8rem;
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
                <div class="alert alert-<?php echo $flash_type; ?> alert-dismissible fade show" role="alert">
                    <i class="fas fa-<?php echo $flash_type === 'success' ? 'check' : 'exclamation'; ?>-circle me-2"></i>
                    <?php echo htmlspecialchars($flash_message); ?>
                </div>
            </div>
            <script>
                setTimeout(() => {
                    const flash = document.querySelector('.flash-message');
                    if (flash) flash.remove();
                }, 3000);
            </script>
            <?php endif; ?>

            <div class="container-fluid" style="margin-top: 100px;">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-concierge-bell text-primary me-2"></i>Service Applications
                    </h1>
                    <div>
                        <span class="badge bg-warning me-2">Pending: <?php echo $status_counts['pending']; ?></span>
                        <span class="badge bg-secondary">Total: <?php echo $total_applications; ?></span>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Applications</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $status_counts['all']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-file-alt fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending Review</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $status_counts['pending']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Approved</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $status_counts['approved']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Completed</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $status_counts['completed']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-double fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filters -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-filter me-2"></i>Filter Applications
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Search</label>
                                <input type="text" name="search" class="form-control" 
                                       placeholder="Search by name, email, or phone..."
                                       value="<?php echo htmlspecialchars($search_term); ?>">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Service Type</label>
                                <select name="service_type" class="form-control">
                                    <option value="all" <?php echo $service_filter === 'all' ? 'selected' : ''; ?>>All Service Types</option>
                                    <?php foreach ($service_types as $type): ?>
                                        <option value="<?php echo htmlspecialchars($type['service_type']); ?>" 
                                                <?php echo $service_filter === $type['service_type'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($type['service_type']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-2">
                                <label class="form-label">&nbsp;</label>
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-search me-2"></i>Filter
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Applications Cards -->
<!-- Applications Cards -->
<div class="card shadow mb-4">
    <div class="card-header bg-white py-3">
        <h6 class="m-0 font-weight-bold text-primary">
            <i class="fas fa-list me-2"></i>All Service Applications
            <span class="badge bg-primary ms-2">
                <?php echo count($applications); ?> total
            </span>
        </h6>
    </div>
    <div class="card-body">
        <?php if (!empty($applications)): ?>
            <div class="row">
                <?php 
                foreach ($applications as $app): 
                    // Determine status color and border
                    $status_color = match($app['status']) {
                        'pending' => 'warning',
                        'approved' => 'success',
                        'completed' => 'info',
                        'rejected' => 'danger',
                        default => 'secondary'
                    };
                    
                    $application_type_display = match($app['application_type']) {
                        'education_support_applications' => 'Education Support',
                        'mobility_assistance_applications' => 'Mobility Assistance',
                        'service_applications' => 'General Service',
                        default => 'Service'
                    };
                    
                    // Get initials for avatar
                    $initials = '';
                    $name_parts = explode(' ', $app['full_name']);
                    if (count($name_parts) >= 2) {
                        $initials = strtoupper(substr($name_parts[0], 0, 1) . substr($name_parts[count($name_parts)-1], 0, 1));
                    } else {
                        $initials = strtoupper(substr($app['full_name'], 0, 2));
                    }
                    
                    // Service icon based on service type
                    $service_icon = 'fa-concierge-bell';
                    $service_bg = 'default-service';
                    if (strpos(strtolower($app['service_type_display']), 'education') !== false) {
                        $service_icon = 'fa-graduation-cap';
                        $service_bg = 'education';
                    } elseif (strpos(strtolower($app['service_type_display']), 'mobility') !== false) {
                        $service_icon = 'fa-wheelchair';
                        $service_bg = 'mobility';
                    }
                    
                    // Get application-specific details
                    $application_details = '';
                    if ($app['application_type'] === 'education_support_applications') {
                        // Education Support specific details
                        $education_level = $app['education_level'] ?? 'Not specified';
                        $institution = $app['institution_name'] ?? $app['primary_school'] ?? $app['secondary_school'] ?? 'Not specified';
                        $support_type = $app['support_type'] ?? 'Not specified';
                        
                        $application_details = "
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Education Level:</strong> {$education_level}</small>
                            </div>
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Institution:</strong> {$institution}</small>
                            </div>
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Support Type:</strong> " . ucfirst($support_type) . "</small>
                            </div>
                        ";
                        
                        // Show support options that were selected
                        $support_options = [];
                        if (($app['financial_assistance'] ?? 'no') === 'yes') $support_options[] = 'Financial';
                        if (($app['learning_materials'] ?? 'no') === 'yes') $support_options[] = 'Materials';
                        if (($app['tutoring_support'] ?? 'no') === 'yes') $support_options[] = 'Tutoring';
                        if (($app['special_accommodations'] ?? 'no') === 'yes') $support_options[] = 'Accommodations';
                        
                        if (!empty($support_options)) {
                            $application_details .= "
                                <div class='mb-2'>
                                    <small class='text-muted'><strong>Support Needed:</strong> " . implode(', ', $support_options) . "</small>
                                </div>
                            ";
                        }
                        
                    } elseif ($app['application_type'] === 'mobility_assistance_applications') {
                        // Mobility Assistance specific details
                        $device_type = $app['device_type'] ?? 'Not specified';
                        $required_device = $app['required_device'] ?? 'Not specified';
                        $urgency_level = $app['urgency_level'] ?? 'medium';
                        $medical_condition = !empty($app['medical_condition']) ? 
                            (strlen($app['medical_condition']) > 50 ? substr($app['medical_condition'], 0, 50) . '...' : $app['medical_condition']) : 
                            'Not specified';
                        
                        $urgency_badge_color = match($urgency_level) {
                            'low' => 'success',
                            'medium' => 'warning',
                            'high' => 'danger',
                            'emergency' => 'dark',
                            default => 'secondary'
                        };
                        
                        $application_details = "
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Device Type:</strong> {$device_type}</small>
                            </div>
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Required:</strong> {$required_device}</small>
                            </div>
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Medical Condition:</strong> {$medical_condition}</small>
                            </div>
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Urgency:</strong> 
                                    <span class='badge bg-{$urgency_badge_color}'>" . ucfirst($urgency_level) . "</span>
                                </small>
                            </div>
                        ";
                        
                    } else {
                        // General Service specific details
                        $service_type = $app['service_type'] ?? 'Not specified';
                        $disability_type = $app['disability_type'] ?? 'Not specified';
                        $region = $app['region'] ?? 'Not specified';
                        $district = $app['district'] ?? 'Not specified';
                        $purpose = !empty($app['message']) ? 
                            (strlen($app['message']) > 60 ? substr($app['message'], 0, 60) . '...' : $app['message']) : 
                            'Not specified';
                        
                        $application_details = "
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Service Type:</strong> {$service_type}</small>
                            </div>
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Disability:</strong> " . ucfirst($disability_type) . "</small>
                            </div>
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Location:</strong> {$district}, {$region}</small>
                            </div>
                            <div class='mb-2'>
                                <small class='text-muted'><strong>Purpose:</strong> {$purpose}</small>
                            </div>
                        ";
                    }
                    
                    // Get additional notes/message
                    $additional_info = '';
                    if ($app['application_type'] === 'education_support_applications') {
                        $additional_info = $app['additional_notes'] ?? $app['other_support_details'] ?? '';
                    } elseif ($app['application_type'] === 'mobility_assistance_applications') {
                        $additional_info = $app['additional_notes'] ?? '';
                    } else {
                        $additional_info = $app['message'] ?? '';
                    }
                ?>
                <div class="col-xl-4 col-lg-6 col-md-6 mb-4">
                    <div class="card application-card application-<?php echo $app['status']; ?> h-100">
                        <div class="card-body">
                            <!-- Header with applicant info -->
                            <div class="d-flex align-items-start mb-3">
                                <div class="card-avatar">
                                    <?php echo $initials; ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1"><?php echo htmlspecialchars($app['full_name']); ?></h5>
                                    <p class="text-muted small mb-1">
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($app['email']); ?>
                                    </p>
                                    <p class="text-muted small mb-0">
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($app['phone'] ?: 'Not provided'); ?>
                                    </p>
                                </div>
                                <div class="text-end">
                                    <span class="badge bg-<?php echo $status_color; ?> status-badge">
                                        <?php echo ucfirst($app['status']); ?>
                                    </span>
                                    <br>
                                    <small class="text-muted">
                                        <?php echo date('M j, Y', strtotime($app['created_at'])); ?>
                                    </small>
                                </div>
                            </div>
                            
                            <!-- Service Information -->
                            <div class="mb-3">
                                <div class="d-flex align-items-center mb-2">
                                    <div class="application-service-icon <?php echo $service_bg; ?>">
                                        <i class="fas <?php echo $service_icon; ?>"></i>
                                    </div>
                                    <div>
                                        <h6 class="mb-0"><?php echo htmlspecialchars($app['service_type_display']); ?></h6>
                                        <small class="text-muted"><?php echo $application_type_display; ?></small>
                                    </div>
                                </div>
                                
                                <?php if (isset($app['user_disability']) && !empty($app['user_disability'])): ?>
                                    <span class="badge bg-light text-dark card-disability-badge">
                                        <i class="fas fa-wheelchair me-1"></i><?php echo htmlspecialchars($app['user_disability']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Application Specific Details -->
                            <div class="mb-3">
                                <?php echo $application_details; ?>
                            </div>
                            
                            <!-- Additional Info Preview -->
                            <div class="mb-3">
                                <?php if (!empty($additional_info)): ?>
                                    <p class="small text-muted mb-1">
                                        <strong>Notes:</strong> 
                                        <?php 
                                        if (strlen($additional_info) > 100) {
                                            echo htmlspecialchars(substr($additional_info, 0, 100)) . '...';
                                        } else {
                                            echo htmlspecialchars($additional_info);
                                        }
                                        ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Actions -->
                            <div class="d-flex justify-content-between align-items-center">
                                <button class="btn btn-sm btn-outline-primary view-application" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#applicationModal"
                                        data-app='<?php echo htmlspecialchars(json_encode($app)); ?>'
                                        title="View Details">
                                    <i class="fas fa-eye me-1"></i>View
                                </button>
                                
                                <div>
                                    <?php if ($app['status'] === 'pending'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="application_type" value="<?php echo $app['application_type']; ?>">
                                        <button type="submit" name="action" value="approved" 
                                                class="btn btn-sm btn-outline-success btn-action"
                                                onclick="return confirm('Approve this application?')"
                                                title="Approve Application">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="submit" name="action" value="rejected" 
                                                class="btn btn-sm btn-outline-danger btn-action"
                                                onclick="return confirm('Reject this application?')"
                                                title="Reject Application">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </form>
                                    <?php elseif ($app['status'] === 'approved'): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="application_id" value="<?php echo $app['id']; ?>">
                                        <input type="hidden" name="application_type" value="<?php echo $app['application_type']; ?>">
                                        <button type="submit" name="action" value="completed" 
                                                class="btn btn-sm btn-outline-info btn-action"
                                                onclick="return confirm('Mark this application as completed?')"
                                                title="Mark Completed">
                                            <i class="fas fa-check-double"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="text-center text-muted py-5">
                <i class="fas fa-inbox fa-3x mb-3"></i><br>
                No applications found
                <?php if ($status_filter !== 'all' || !empty($search_term) || $service_filter !== 'all'): ?>
                    <br><small class="text-muted">Try changing your filters</small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
            </div>
        </div>
    </div>

    <!-- Application Details Modal -->
    <div class="modal fade" id="applicationModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-alt me-2"></i>Application & User Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="applicationDetails">
                    <!-- Details will be loaded here by JavaScript -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.view-application').on('click', function() {
                const app = JSON.parse($(this).data('app'));
                
                // Format date of birth
                const dob = app.date_of_birth ? new Date(app.date_of_birth).toLocaleDateString() : 'Not provided';
                
                // Calculate age
                let age = 'N/A';
                if (app.date_of_birth) {
                    const birthDate = new Date(app.date_of_birth);
                    const today = new Date();
                    age = today.getFullYear() - birthDate.getFullYear();
                    const monthDiff = today.getMonth() - birthDate.getMonth();
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                        age--;
                    }
                }

                // Build application-specific details based on application type
                let applicationSpecificDetails = '';
                
                if (app.application_type === 'education_support_applications') {
                    applicationSpecificDetails = `
                        <div class="col-md-6 mb-2">
                            <strong>Education Level:</strong><br>${app.education_level || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Course/Program:</strong><br>${app.course_program || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Institution:</strong><br>${app.institution || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Support Needed:</strong><br>${app.support_needed || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Current Challenges:</strong><br>${app.current_challenges || 'Not specified'}
                        </div>
                    `;
                } else if (app.application_type === 'mobility_assistance_applications') {
                    applicationSpecificDetails = `
                        <div class="col-md-6 mb-2">
                            <strong>Mobility Aid Type:</strong><br>${app.aid_type || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Specific Needs:</strong><br>${app.specific_needs || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Usage Frequency:</strong><br>${app.usage_frequency || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Mobility Challenges:</strong><br>${app.mobility_challenges || 'Not specified'}
                        </div>
                    `;
                } else {
                    applicationSpecificDetails = `
                        <div class="col-md-6 mb-2">
                            <strong>Service Type:</strong><br>${app.service_type || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Purpose:</strong><br>${app.purpose || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Disability Type:</strong><br>${app.disability_type || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Region:</strong><br>${app.region || 'Not specified'}
                        </div>
                        <div class="col-md-6 mb-2">
                            <strong>Specific Requirements:</strong><br>${app.specific_requirements || 'Not specified'}
                        </div>
                    `;
                }

                // Get additional information based on application type
                let additionalInfo = '';
                if (app.application_type === 'education_support_applications') {
                    additionalInfo = app.support_needed || app.current_challenges || app.notes || 'No additional information provided.';
                } else if (app.application_type === 'mobility_assistance_applications') {
                    additionalInfo = app.specific_needs || app.mobility_challenges || app.notes || 'No additional information provided.';
                } else {
                    additionalInfo = app.message || app.specific_requirements || app.notes || 'No additional information provided.';
                }

                $('#applicationDetails').html(`
                    <div class="row">
                        <!-- User Information -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-primary text-white">
                                    <h6 class="mb-0"><i class="fas fa-user me-2"></i>User Information</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <strong>Full Name:</strong><br>${app.full_name}
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Email:</strong><br>${app.email}
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Phone:</strong><br>${app.phone || 'Not provided'}
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Date of Birth:</strong><br>${dob} (${age} years)
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Gender:</strong><br>${app.gender || 'Not provided'}
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Disability:</strong><br>${app.user_disability || 'Not specified'}
                                        </div>
                                        <div class="col-12 mb-2">
                                            <strong>Address:</strong><br>${app.address || 'Not provided'}, ${app.city || ''} ${app.country || ''}
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Emergency Contact:</strong><br>${app.emergency_contact || 'Not provided'}
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Member Since:</strong><br>${new Date(app.user_created).toLocaleDateString()}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Application Details -->
                        <div class="col-md-6">
                            <div class="card mb-4">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fas fa-file-alt me-2"></i>Application Details</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-2">
                                            <strong>Application Type:</strong><br>
                                            <span class="badge bg-primary">${app.application_type === 'education_support_applications' ? 'Education Support' : app.application_type === 'mobility_assistance_applications' ? 'Mobility Assistance' : 'General Service'}</span>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Service Type:</strong><br>
                                            <span class="badge bg-info">${app.service_type_display}</span>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Status:</strong><br>
                                            <span class="badge bg-${app.status === 'pending' ? 'warning' : app.status === 'approved' ? 'success' : app.status === 'completed' ? 'info' : 'danger'}">
                                                ${app.status.charAt(0).toUpperCase() + app.status.slice(1)}
                                            </span>
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Applied Date:</strong><br>${new Date(app.created_at).toLocaleString()}
                                        </div>
                                        <div class="col-md-6 mb-2">
                                            <strong>Last Updated:</strong><br>${app.updated_at ? new Date(app.updated_at).toLocaleString() : 'Not updated'}
                                        </div>
                                        ${applicationSpecificDetails}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Additional Information -->
                    <div class="card">
                        <div class="card-header bg-secondary text-white">
                            <h6 class="mb-0"><i class="fas fa-comment me-2"></i>Additional Information</h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-0">${additionalInfo}</p>
                        </div>
                    </div>
                `);
            });
        });
    </script>
</body>
</html>