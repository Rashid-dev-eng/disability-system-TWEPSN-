<?php
session_start();
require '../database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

$admin_id = $_SESSION['admin_id'];

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_field'])) {
    $field_to_update = $_POST['field_name'];
    $new_value = $_POST['new_value'];
    
    // Validate and sanitize input
    $allowed_fields = ['username', 'email', 'phone', 'department'];
    if (in_array($field_to_update, $allowed_fields)) {
        $new_value = $conn->real_escape_string(trim($new_value));
        
        // Basic validation
        if (!empty($new_value)) {
            $update_sql = "UPDATE admins SET $field_to_update = '$new_value' WHERE user_id = $admin_id";
            
            if ($conn->query($update_sql) === TRUE) {
                $_SESSION['flash_message'] = ucfirst($field_to_update) . " updated successfully!";
                $_SESSION['flash_type'] = 'success';
                
                // Refresh admin data
                header("Location: " . $_SERVER['PHP_SELF']);
                exit;
            } else {
                $_SESSION['flash_message'] = "Error updating " . $field_to_update . ": " . $conn->error;
                $_SESSION['flash_type'] = 'danger';
            }
        } else {
            $_SESSION['flash_message'] = "Please enter a valid value";
            $_SESSION['flash_type'] = 'warning';
        }
    } else {
        $_SESSION['flash_message'] = "Invalid field selected";
        $_SESSION['flash_type'] = 'danger';
    }
}

// First, let's check what columns actually exist in your admins table
$table_check = $conn->query("DESCRIBE admins");
$admin_columns = [];
while ($row = $table_check->fetch_assoc()) {
    $admin_columns[] = $row['Field'];
}

// Build query based on actual columns in your admins table
$columns_to_select = [];
if (in_array('username', $admin_columns)) $columns_to_select[] = 'username';
if (in_array('email', $admin_columns)) $columns_to_select[] = 'email';
if (in_array('phone', $admin_columns)) $columns_to_select[] = 'phone';
if (in_array('department', $admin_columns)) $columns_to_select[] = 'department';
if (in_array('created_at', $admin_columns)) $columns_to_select[] = 'created_at';
if (in_array('last_login', $admin_columns)) $columns_to_select[] = 'last_login';

// If no specific columns found, use *
if (empty($columns_to_select)) {
    $query = "SELECT * FROM admins WHERE user_id = ?";
} else {
    $query = "SELECT " . implode(', ', $columns_to_select) . " FROM admins WHERE user_id = ?";
}

// Fetch admin data from admins table
$admin_data = null;
$stmt = $conn->prepare($query);
if ($stmt) {
    $stmt->bind_param("i", $admin_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin_data = $result->fetch_assoc();
    $stmt->close();
}

// If still no data, let's check what's actually in the admins table
if (!$admin_data) {
    $check_all = $conn->query("SELECT * FROM admins WHERE user_id = $admin_id");
    if ($check_all && $check_all->num_rows > 0) {
        $admin_data = $check_all->fetch_assoc();
    }
}

// Helper function to safely get values
function get_admin_value($data, $key, $default = 'Not set') {
    if (!$data || !isset($data[$key]) || $data[$key] === null || $data[$key] === '') {
        return $default;
    }
    return $data[$key];
}

// Format dates
$member_since = 'Not set';
if (!empty($admin_data['created_at']) && $admin_data['created_at'] !== '0000-00-00 00:00:00') {
    $member_since = date('F j, Y', strtotime($admin_data['created_at']));
}

$last_login = 'Never';
if (!empty($admin_data['last_login']) && $admin_data['last_login'] !== '0000-00-00 00:00:00') {
    $last_login = date('M j, Y g:i A', strtotime($admin_data['last_login']));
}

// Get the username to display
$display_username = get_admin_value($admin_data, 'username', 'Administrator');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile - Disability System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-container {
            margin-top: 80px;
        }
        
        .profile-pic {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border: 5px solid #e3f2fd;
        }
        
        .info-card {
            border: none;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            border-radius: 15px;
        }
        
        .info-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0 !important;
            border: none;
        }
        
        .info-item {
            padding: 12px 0;
            border-bottom: 1px solid #e9ecef;
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #495057;
            font-size: 0.9rem;
        }
        
        .info-value {
            color: #212529;
            font-weight: 500;
        }
        
        .update-field-card {
            border-left: 4px solid #28a745;
        }
        
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
    </style>
</head>
<body class="d-flex">
    <!-- Sidebar -->
    <?php include('admin_sidebar.php'); ?>
    
    <!-- Content -->
    <div class="flex-grow-1">
        <!-- Topbar -->
        <?php include('admin_topbar.php'); ?>

        <!-- Flash Message -->
        <?php if (isset($_SESSION['flash_message'])): ?>
        <div class="flash-message">
            <div class="alert alert-<?php echo $_SESSION['flash_type']; ?> alert-dismissible fade show" role="alert">
                <i class="fas fa-<?php echo $_SESSION['flash_type'] === 'success' ? 'check' : 'exclamation'; ?>-circle me-2"></i>
                <?php echo htmlspecialchars($_SESSION['flash_message']); ?>
            </div>
        </div>
        <?php 
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
        ?>
        <script>
            setTimeout(() => {
                const flash = document.querySelector('.flash-message');
                if (flash) flash.remove();
            }, 3000);
        </script>
        <?php endif; ?>

        <div class="container-fluid profile-container">
            <div class="row justify-content-center">
                <div class="col-xl-8 col-lg-10">
                    <!-- Page Header -->
                    <div class="d-sm-flex align-items-center justify-content-between mb-4">
                        <h1 class="h3 mb-0 text-gray-800">
                            <i class="fas fa-user-shield text-primary me-2"></i>Admin Profile
                        </h1>
                        <div class="text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            <?php echo date('F j, Y'); ?>
                        </div>
                    </div>

                    <!-- Main Profile Card -->
                    <div class="card info-card mb-4">
                        <div class="card-header">
                            <h4 class="m-0 font-weight-bold">
                                <i class="fas fa-id-card me-2"></i>Administrator Information
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="row align-items-center">
                                <!-- Profile Picture -->
                                <div class="col-md-4 text-center mb-4 mb-md-0">
                                    <img class="profile-pic rounded-circle" 
                                         src="https://ui-avatars.com/api/?name=<?php echo urlencode($display_username); ?>&background=4e73df&color=fff&size=150"
                                         alt="Profile Picture">
                                    <div class="mt-3">
                                        <span class="badge bg-success me-2">
                                            <i class="fas fa-circle me-1"></i>Active
                                        </span>
                                        <span class="badge bg-primary">
                                            <i class="fas fa-user-shield me-1"></i>Administrator
                                        </span>
                                    </div>
                                </div>
                                
                                <!-- Basic Information -->
                                <div class="col-md-8">
                                    <div class="row">
                                        <div class="col-sm-6 info-item">
                                            <div class="info-label">Username</div>
                                            <div class="info-value">
                                                <i class="fas fa-at me-2 text-primary"></i>
                                                <?php echo htmlspecialchars($display_username); ?>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 info-item">
                                            <div class="info-label">Admin ID</div>
                                            <div class="info-value">
                                                <i class="fas fa-id-card me-2 text-primary"></i>
                                                #<?php echo $admin_id; ?>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 info-item">
                                            <div class="info-label">Email Address</div>
                                            <div class="info-value">
                                                <i class="fas fa-envelope me-2 text-primary"></i>
                                                <?php echo htmlspecialchars(get_admin_value($admin_data, 'email', 'Not set')); ?>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 info-item">
                                            <div class="info-label">Phone Number</div>
                                            <div class="info-value">
                                                <i class="fas fa-phone me-2 text-primary"></i>
                                                <?php echo htmlspecialchars(get_admin_value($admin_data, 'phone', 'Not set')); ?>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 info-item">
                                            <div class="info-label">Department</div>
                                            <div class="info-value">
                                                <i class="fas fa-building me-2 text-primary"></i>
                                                <?php echo htmlspecialchars(get_admin_value($admin_data, 'department', 'Administration')); ?>
                                            </div>
                                        </div>
                                        <div class="col-sm-6 info-item">
                                            <div class="info-label">Role</div>
                                            <div class="info-value">
                                                <i class="fas fa-user-tie me-2 text-primary"></i>
                                                System Administrator
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Additional Information Cards -->
                    <div class="row">
                        <!-- Account Details -->
                        <div class="col-md-6 mb-4">
                            <div class="card info-card h-100">
                                <div class="card-header">
                                    <h5 class="m-0 font-weight-bold">
                                        <i class="fas fa-user-circle me-2"></i>Account Details
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="info-item">
                                        <div class="info-label">Member Since</div>
                                        <div class="info-value">
                                            <i class="fas fa-calendar-plus me-2 text-info"></i>
                                            <?php echo $member_since; ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Last Login</div>
                                        <div class="info-value">
                                            <i class="fas fa-sign-in-alt me-2 text-info"></i>
                                            <?php echo $last_login; ?>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Account Type</div>
                                        <div class="info-value">
                                            <i class="fas fa-user-tie me-2 text-info"></i>
                                            System Administrator
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- System Information -->
                        <div class="col-md-6 mb-4">
                            <div class="card info-card h-100">
                                <div class="card-header">
                                    <h5 class="m-0 font-weight-bold">
                                        <i class="fas fa-cogs me-2"></i>System Information
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="info-item">
                                        <div class="info-label">Role</div>
                                        <div class="info-value">
                                            <i class="fas fa-shield-alt me-2 text-success"></i>
                                            Full System Access
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Status</div>
                                        <div class="info-value">
                                            <i class="fas fa-check-circle me-2 text-success"></i>
                                            <span class="text-success">Active</span>
                                        </div>
                                    </div>
                                    <div class="info-item">
                                        <div class="info-label">Permissions</div>
                                        <div class="info-value">
                                            <i class="fas fa-key me-2 text-success"></i>
                                            All Administrative Rights
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Update Field -->
                    <div class="card info-card mb-4 update-field-card">
                        <div class="card-header">
                            <h5 class="m-0 font-weight-bold">
                                <i class="fas fa-edit me-2"></i>Quick Update Field
                            </h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" id="quickUpdateForm">
                                <input type="hidden" name="update_field" value="1">
                                <div class="row g-3 align-items-end">
                                    <div class="col-md-4">
                                        <label class="form-label">Select Field to Update</label>
                                        <select name="field_name" class="form-control" required id="fieldSelect">
                                            <option value="">Choose field...</option>
                                            <option value="username">Username</option>
                                            <option value="email">Email Address</option>
                                            <option value="phone">Phone Number</option>
                                            <option value="department">Department</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <label class="form-label">New Value</label>
                                        <input type="text" name="new_value" class="form-control" 
                                               placeholder="Enter new value..." required id="valueInput">
                                    </div>
                                    <div class="col-md-3">
                                        <button type="submit" class="btn btn-success w-100">
                                            <i class="fas fa-save me-2"></i>Update Field
                                        </button>
                                    </div>
                                </div>
                                <div class="mt-2">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle me-1"></i>
                                        Select any field above and enter new value to update individually
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>

                    <!-- Debug Information (remove this in production) -->
                    <?php if (!$admin_data): ?>
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h6 class="m-0">
                                <i class="fas fa-exclamation-triangle me-2"></i>Debug Information
                            </h6>
                        </div>
                        <div class="card-body">
                            <p class="mb-2"><strong>Admin ID:</strong> <?php echo $admin_id; ?></p>
                            <p class="mb-2"><strong>Data Found:</strong> <?php echo $admin_data ? 'Yes' : 'No'; ?></p>
                            <p class="mb-0"><strong>Available Columns in admins table:</strong> <?php echo implode(', ', $admin_columns); ?></p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Add some interactivity to the form
        document.getElementById('fieldSelect').addEventListener('change', function() {
            const fieldName = this.value;
            const valueInput = document.getElementById('valueInput');
            
            // Update placeholder based on selected field
            if (fieldName) {
                valueInput.placeholder = 'Enter new ' + fieldName + '...';
            } else {
                valueInput.placeholder = 'Enter new value...';
            }
        });

        // Form validation
        document.getElementById('quickUpdateForm').addEventListener('submit', function(e) {
            const fieldSelect = document.getElementById('fieldSelect');
            const valueInput = document.getElementById('valueInput');
            
            if (!fieldSelect.value) {
                alert('Please select a field to update');
                e.preventDefault();
                return;
            }
            
            if (!valueInput.value.trim()) {
                alert('Please enter a value');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>