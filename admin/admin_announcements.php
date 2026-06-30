<?php
// admin_announcements.php - Complete Single File Solution
session_start();

// Database Configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "disability-tracker";

// Create database connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Database Connection failed: " . $conn->connect_error);
}

// Set charset to utf8
$conn->set_charset("utf8");

// Auto-create announcements table if not exists
$createTableSQL = "CREATE TABLE IF NOT EXISTS announcements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
    target_audience ENUM('all_users', 'education_users', 'mobility_users', 'service_users') DEFAULT 'all_users',
    status ENUM('draft', 'published', 'archived') DEFAULT 'published',
    created_by VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)";

if (!$conn->query($createTableSQL)) {
    die("Error creating announcements table: " . $conn->error);
}

// Check if admin is logged in - FIXED SESSION CHECK
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Handle AJAX requests for editing
if (isset($_GET['ajax']) && $_GET['ajax'] == 'get_announcement') {
    $announcement_id = intval($_GET['id'] ?? 0);
    if ($announcement_id > 0) {
        $sql = "SELECT * FROM announcements WHERE id = $announcement_id";
        $result = $conn->query($sql);
        if ($result && $result->num_rows > 0) {
            echo json_encode(['success' => true, 'announcement' => $result->fetch_assoc()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Announcement not found']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid announcement ID']);
    }
    exit;
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['create_announcement'])) {
        $title = $conn->real_escape_string($_POST['title'] ?? '');
        $message = $conn->real_escape_string($_POST['message'] ?? '');
        $priority = $conn->real_escape_string($_POST['priority'] ?? 'medium');
        $target_audience = $conn->real_escape_string($_POST['target_audience'] ?? 'all_users');
        $created_by = "Administrator"; // Always set as Administrator
        
        if (!empty($title) && !empty($message)) {
            $sql = "INSERT INTO announcements (title, message, priority, target_audience, created_by) 
                    VALUES ('$title', '$message', '$priority', '$target_audience', '$created_by')";
            
            if ($conn->query($sql) === TRUE) {
                $_SESSION['flash_message'] = "Announcement published successfully!";
                $_SESSION['flash_type'] = 'success';
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $_SESSION['flash_message'] = "Error creating announcement: " . $conn->error;
                $_SESSION['flash_type'] = 'danger';
            }
        } else {
            $_SESSION['flash_message'] = "Please fill in all required fields.";
            $_SESSION['flash_type'] = 'warning';
        }
    }
    
    if (isset($_POST['delete_announcement'])) {
        $announcement_id = intval($_POST['announcement_id'] ?? 0);
        
        if ($announcement_id > 0) {
            $sql = "DELETE FROM announcements WHERE id = $announcement_id";
            
            if ($conn->query($sql) === TRUE) {
                $_SESSION['flash_message'] = "Announcement deleted successfully!";
                $_SESSION['flash_type'] = 'success';
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $_SESSION['flash_message'] = "Error deleting announcement: " . $conn->error;
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
    
    if (isset($_POST['update_announcement'])) {
        $announcement_id = intval($_POST['announcement_id'] ?? 0);
        $title = $conn->real_escape_string($_POST['title'] ?? '');
        $message = $conn->real_escape_string($_POST['message'] ?? '');
        $priority = $conn->real_escape_string($_POST['priority'] ?? 'medium');
        $target_audience = $conn->real_escape_string($_POST['target_audience'] ?? 'all_users');
        $status = $conn->real_escape_string($_POST['status'] ?? 'published');
        $created_by = "Administrator"; // Always update as Administrator
        
        if ($announcement_id > 0 && !empty($title) && !empty($message)) {
            $sql = "UPDATE announcements 
                    SET title = '$title', message = '$message', priority = '$priority', 
                        target_audience = '$target_audience', status = '$status', created_by = '$created_by' 
                    WHERE id = $announcement_id";
            
            if ($conn->query($sql) === TRUE) {
                $_SESSION['flash_message'] = "Announcement updated successfully!";
                $_SESSION['flash_type'] = 'success';
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $_SESSION['flash_message'] = "Error updating announcement: " . $conn->error;
                $_SESSION['flash_type'] = 'danger';
            }
        }
    }
}

// Get all announcements
$sql = "SELECT * FROM announcements 
        ORDER BY 
          CASE priority 
            WHEN 'high' THEN 1 
            WHEN 'medium' THEN 2 
            WHEN 'low' THEN 3 
          END, 
          created_at DESC";
$result = $conn->query($sql);
$announcements = [];

if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $announcements[] = $row;
    }
}

// Get statistics
$stats = [
    'total' => 0,
    'high_priority' => 0,
    'this_month' => 0
];

$totalResult = $conn->query("SELECT COUNT(*) as total FROM announcements");
if ($totalResult) {
    $stats['total'] = $totalResult->fetch_assoc()['total'];
}

$highResult = $conn->query("SELECT COUNT(*) as high_priority FROM announcements WHERE priority = 'high'");
if ($highResult) {
    $stats['high_priority'] = $highResult->fetch_assoc()['high_priority'];
}

$monthResult = $conn->query("SELECT COUNT(*) as this_month FROM announcements 
                            WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) 
                            AND YEAR(created_at) = YEAR(CURRENT_DATE())");
if ($monthResult) {
    $stats['this_month'] = $monthResult->fetch_assoc()['this_month'];
}

// Flash message handling
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Announcements - Disability System Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .flash-message {
            position: fixed;
            top: 20px;
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
        
        .announcement-card {
            transition: all 0.3s ease;
            border-left: 4px solid #007bff;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        }
        
        .announcement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.12);
        }
        
        .priority-high {
            border-left-color: #dc3545;
        }
        
        .priority-medium {
            border-left-color: #ffc107;
        }
        
        .priority-low {
            border-left-color: #28a745;
        }
        
        .badge-priority {
            font-size: 0.7rem;
            padding: 0.35rem 0.65rem;
        }
        
        .create-announcement-card {
            background: linear-gradient(45deg, #f8f9fa, #e9ecef);
            border: 2px dashed #dee2e6;
        }
        
        /* Loading Animation Styles */
        .loading-spinner {
            display: none;
            text-align: center;
            padding: 2rem;
        }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3498db;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin: 0 auto;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .btn-loading {
            position: relative;
            pointer-events: none;
        }
        
        .btn-loading::after {
            content: '';
            position: absolute;
            width: 16px;
            height: 16px;
            top: 50%;
            left: 50%;
            margin-left: -8px;
            margin-top: -8px;
            border: 2px solid transparent;
            border-top-color: #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
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

            <!-- Loading Spinner -->
            <div id="loadingSpinner" class="loading-spinner">
                <div class="spinner"></div>
                <p class="mt-2 text-muted">Loading announcements...</p>
            </div>

            <div class="container-fluid" style="margin-top: 100px;">
                <div class="d-sm-flex align-items-center justify-content-between mb-4">
                    <h1 class="h3 mb-0 text-gray-800">
                        <i class="fas fa-bullhorn text-primary me-2"></i>Announcements Management
                    </h1>
                    <div>
                        <span class="badge bg-primary me-2">Total: <?php echo $stats['total']; ?></span>
                        <span class="badge bg-success">Active: <?php echo $stats['total']; ?></span>
                    </div>
                </div>

                <!-- Create/Edit Announcement Card -->
                <div class="card shadow mb-4 create-announcement-card">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-plus-circle me-2"></i>
                            <span id="formTitle">Create New Announcement</span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <form method="POST" id="announcementForm">
                            <input type="hidden" name="create_announcement" value="1" id="formAction">
                            <input type="hidden" name="announcement_id" id="edit_announcement_id">
                            
                            <div class="row g-3">
                                <div class="col-md-8">
                                    <label class="form-label">Announcement Title *</label>
                                    <input type="text" name="title" id="formTitleInput" class="form-control" 
                                           placeholder="Enter announcement title..." required
                                           maxlength="200">
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Priority</label>
                                    <select name="priority" id="formPriority" class="form-control" required>
                                        <option value="low">Low</option>
                                        <option value="medium" selected>Medium</option>
                                        <option value="high">High</option>
                                    </select>
                                </div>
                                <div class="col-md-2">
                                    <label class="form-label">Target Audience</label>
                                    <select name="target_audience" id="formTargetAudience" class="form-control" required>
                                        <option value="all_users" selected>All Users</option>
                                        <option value="education_users">Education Support</option>
                                        <option value="mobility_users">Mobility Assistance</option>
                                        <option value="service_users">General Service</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <label class="form-label">Message *</label>
                                    <textarea name="message" id="formMessage" class="form-control" rows="5" 
                                              placeholder="Enter your announcement message..." 
                                              required maxlength="1000"></textarea>
                                    <div class="form-text">
                                        <span id="charCount">0</span>/1000 characters
                                    </div>
                                </div>
                                <div class="col-12" id="statusField" style="display: none;">
                                    <label class="form-label">Status</label>
                                    <select name="status" id="formStatus" class="form-control" required>
                                        <option value="draft">Draft</option>
                                        <option value="published" selected>Published</option>
                                        <option value="archived">Archived</option>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="send_email" id="send_email">
                                        <label class="form-check-label" for="send_email">
                                            <i class="fas fa-envelope me-1"></i>Send email notification to users
                                        </label>
                                    </div>
                                    <div class="form-check mt-2">
                                        <input class="form-check-input" type="checkbox" name="send_sms" id="send_sms">
                                        <label class="form-check-label" for="send_sms">
                                            <i class="fas fa-sms me-1"></i>Send SMS notification (if available)
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary" id="submitButton">
                                    <i class="fas fa-paper-plane me-2"></i>Publish Announcement
                                </button>
                                <button type="button" class="btn btn-outline-secondary" onclick="clearForm()">
                                    <i class="fas fa-times me-2"></i>Clear Form
                                </button>
                                <button type="button" class="btn btn-outline-warning" id="cancelEditButton" style="display: none;" onclick="cancelEdit()">
                                    <i class="fas fa-times me-2"></i>Cancel Edit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Existing Announcements -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-white py-3">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-list me-2"></i>Published Announcements
                            <span class="badge bg-primary ms-2"><?php echo $stats['total']; ?> total</span>
                        </h6>
                    </div>
                    <div class="card-body" id="announcementsContainer">
                        <?php if (!empty($announcements)): ?>
                            <div class="row">
                                <?php foreach ($announcements as $announcement): 
                                    $priority_class = 'priority-' . $announcement['priority'];
                                    $priority_badge_color = match($announcement['priority']) {
                                        'high' => 'danger',
                                        'medium' => 'warning',
                                        'low' => 'success',
                                        default => 'secondary'
                                    };
                                    
                                    $audience_badge_color = match($announcement['target_audience']) {
                                        'all_users' => 'primary',
                                        'education_users' => 'info',
                                        'mobility_users' => 'success',
                                        'service_users' => 'warning',
                                        default => 'secondary'
                                    };
                                    
                                    $audience_text = match($announcement['target_audience']) {
                                        'all_users' => 'All Users',
                                        'education_users' => 'Education Support',
                                        'mobility_users' => 'Mobility Assistance',
                                        'service_users' => 'General Service',
                                        default => 'All Users'
                                    };
                                ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card announcement-card h-100 <?php echo $priority_class; ?>">
                                        <div class="card-body">
                                            <!-- Header with priority and actions -->
                                            <div class="d-flex justify-content-between align-items-start mb-3">
                                                <span class="badge bg-<?php echo $priority_badge_color; ?> badge-priority">
                                                    <?php echo ucfirst($announcement['priority']); ?> Priority
                                                </span>
                                                <div class="dropdown">
                                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" 
                                                            type="button" data-bs-toggle="dropdown">
                                                        <i class="fas fa-ellipsis-v"></i>
                                                    </button>
                                                    <ul class="dropdown-menu">
                                                        <li>
                                                            <a class="dropdown-item" href="#" 
                                                               onclick="editAnnouncement(<?php echo $announcement['id']; ?>)">
                                                                <i class="fas fa-edit me-2"></i>Edit & Republish
                                                            </a>
                                                        </li>
                                                        <li>
                                                            <form method="POST" class="d-inline">
                                                                <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                                <button type="submit" name="delete_announcement" 
                                                                        class="dropdown-item text-danger" 
                                                                        onclick="return confirm('Are you sure you want to delete this announcement?')">
                                                                    <i class="fas fa-trash me-2"></i>Delete
                                                                </button>
                                                            </form>
                                                        </li>
                                                    </ul>
                                                </div>
                                            </div>
                                            
                                            <!-- Title and content -->
                                            <h5 class="card-title"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                            <p class="card-text text-muted">
                                                <?php echo htmlspecialchars($announcement['message']); ?>
                                            </p>
                                            
                                            <!-- Metadata -->
                                            <div class="mt-3">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="badge bg-<?php echo $audience_badge_color; ?>">
                                                        <?php echo $audience_text; ?>
                                                    </span>
                                                    <small class="text-muted">
                                                        <?php echo date('M j, Y g:i A', strtotime($announcement['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <div class="mt-2">
                                                    <small class="text-muted">
                                                        <i class="fas fa-user me-1"></i>
                                                        By <?php echo htmlspecialchars($announcement['created_by']); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-bullhorn fa-3x mb-3"></i><br>
                                No announcements published yet
                                <br><small class="text-muted">Create your first announcement using the form above</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total Announcements</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['total']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-bullhorn fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-success shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            High Priority</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['high_priority']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-info shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            This Month</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $stats['this_month']; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-3 col-md-6 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            All Users Reach</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">All</div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-users fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Character count for message textarea
        document.addEventListener('DOMContentLoaded', function() {
            const messageTextarea = document.querySelector('textarea[name="message"]');
            const charCount = document.getElementById('charCount');
            
            if (messageTextarea && charCount) {
                messageTextarea.addEventListener('input', function() {
                    charCount.textContent = this.value.length;
                });
            }

            // Show loading spinner on page load
            showLoading();
            // Simulate loading completion
            setTimeout(hideLoading, 1000);
        });

        function showLoading() {
            document.getElementById('loadingSpinner').style.display = 'block';
            document.getElementById('announcementsContainer').style.opacity = '0.5';
        }

        function hideLoading() {
            document.getElementById('loadingSpinner').style.display = 'none';
            document.getElementById('announcementsContainer').style.opacity = '1';
        }
        
        function clearForm() {
            if (confirm('Are you sure you want to clear the form?')) {
                resetFormToCreate();
                document.getElementById('charCount').textContent = '0';
            }
        }

        function resetFormToCreate() {
            document.getElementById('formTitle').textContent = 'Create New Announcement';
            document.getElementById('formAction').name = 'create_announcement';
            document.getElementById('formAction').value = '1';
            document.getElementById('edit_announcement_id').value = '';
            document.getElementById('formTitleInput').value = '';
            document.getElementById('formMessage').value = '';
            document.getElementById('formPriority').value = 'medium';
            document.getElementById('formTargetAudience').value = 'all_users';
            document.getElementById('formStatus').value = 'published';
            document.getElementById('statusField').style.display = 'none';
            document.getElementById('submitButton').innerHTML = '<i class="fas fa-paper-plane me-2"></i>Publish Announcement';
            document.getElementById('cancelEditButton').style.display = 'none';
            document.getElementById('charCount').textContent = '0';
        }

        function cancelEdit() {
            resetFormToCreate();
        }
        
        function editAnnouncement(id) {
            showLoading();
            
            // Fetch announcement data via AJAX
            fetch('<?php echo $_SERVER['PHP_SELF']; ?>?ajax=get_announcement&id=' + id)
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok');
                    }
                    return response.json();
                })
                .then(data => {
                    hideLoading();
                    if (data.success) {
                        // Populate form with existing data
                        document.getElementById('formTitle').textContent = 'Edit Announcement';
                        document.getElementById('formAction').name = 'update_announcement';
                        document.getElementById('formAction').value = '1';
                        document.getElementById('edit_announcement_id').value = data.announcement.id;
                        document.getElementById('formTitleInput').value = data.announcement.title;
                        document.getElementById('formMessage').value = data.announcement.message;
                        document.getElementById('formPriority').value = data.announcement.priority;
                        document.getElementById('formTargetAudience').value = data.announcement.target_audience;
                        document.getElementById('formStatus').value = data.announcement.status;
                        document.getElementById('statusField').style.display = 'block';
                        document.getElementById('submitButton').innerHTML = '<i class="fas fa-save me-2"></i>Update Announcement';
                        document.getElementById('cancelEditButton').style.display = 'inline-block';
                        document.getElementById('charCount').textContent = data.announcement.message.length;
                        
                        // Scroll to form
                        document.getElementById('formTitleInput').scrollIntoView({ behavior: 'smooth' });
                    } else {
                        alert('Error loading announcement data: ' + (data.message || 'Unknown error'));
                    }
                })
                .catch(error => {
                    hideLoading();
                    console.error('Error:', error);
                    alert('Error loading announcement data. Please try again.');
                });
        }

        // Add loading state to form submission
        document.getElementById('announcementForm').addEventListener('submit', function() {
            const submitButton = document.getElementById('submitButton');
            submitButton.classList.add('btn-loading');
            submitButton.disabled = true;
        });
        
        // Auto-hide flash message after 5 seconds
        setTimeout(() => {
            const flash = document.querySelector('.flash-message');
            if (flash) flash.remove();
        }, 5000);
    </script>
</body>
</html>

<?php
// Close database connection
$conn->close();
?>