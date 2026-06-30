<?php
session_start();
require '../database.php';

// Define sanitize_input function if it doesn't exist
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

if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Handle appointment status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $appointment_id = sanitize_input($_POST['appointment_id'] ?? '');
    $action = sanitize_input($_POST['action']);
    
    if ($appointment_id && in_array($action, ['approved', 'completed', 'cancelled', 'rejected'])) {
        $stmt = $conn->prepare("UPDATE appointments SET status = ?, updated_at = NOW() WHERE id = ?");
        if ($stmt) {
            $stmt->bind_param("si", $action, $appointment_id);
            if ($stmt->execute()) {
                
                // Get appointment details for notification
                $appointment_stmt = $conn->prepare("SELECT user_id, purpose, appointment_date, appointment_time FROM appointments WHERE id = ?");
                $appointment_stmt->bind_param("i", $appointment_id);
                $appointment_stmt->execute();
                $appointment_result = $appointment_stmt->get_result();
                $appointment_data = $appointment_result->fetch_assoc();
                $appointment_stmt->close();
                
                // Create notification for user
                $notification_stmt = $conn->prepare("INSERT INTO notifications (user_id, title, message, type, related_id, related_type) VALUES (?, ?, ?, ?, ?, 'appointment')");
                
                $notification_title = "Appointment Updated";
                $notification_message = "Your appointment for '{$appointment_data['purpose']}' on {$appointment_data['appointment_date']} at {$appointment_data['appointment_time']} has been {$action}.";
                
                $notification_stmt->bind_param("isssi", 
                    $appointment_data['user_id'],
                    $notification_title,
                    $notification_message,
                    $action,
                    $appointment_id
                );
                $notification_stmt->execute();
                $notification_stmt->close();
                
                // Log the action
                $log_stmt = $conn->prepare("INSERT INTO audit_log (user_id, user_email, action, description, ip_address) VALUES (?, ?, ?, ?, ?)");
                $admin_id = $_SESSION['user_id'];
                $admin_email = $_SESSION['admin_username'] ?? 'Admin';
                $action_text = "Appointment Updated";
                $description = "Appointment ID {$appointment_id} marked as {$action}";
                $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                $log_stmt->bind_param("issss", $admin_id, $admin_email, $action_text, $description, $ip);
                $log_stmt->execute();
                $log_stmt->close();
                
                $_SESSION['flash_message'] = "Appointment marked as {$action}! User has been notified.";
                $_SESSION['flash_type'] = 'success';
            } else {
                $_SESSION['flash_message'] = "Failed to update appointment.";
                $_SESSION['flash_type'] = 'danger';
            }
            $stmt->close();
        }
    }
    header("Location: admin_appointments.php");
    exit;
}

// Flash message handling
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Fetch appointments with secure filters
$status_filter = sanitize_input($_GET['status'] ?? 'all');
$date_filter = sanitize_input($_GET['date'] ?? '');

// Build secure query
$query = "SELECT a.*, u.full_name, u.phone, u.email 
          FROM appointments a 
          JOIN users u ON a.user_id = u.id 
          WHERE 1=1";
$params = [];
$types = "";

// Safe status filter
$allowed_statuses = ['pending', 'approved', 'scheduled', 'completed', 'cancelled', 'rejected'];
if ($status_filter !== 'all' && in_array($status_filter, $allowed_statuses)) {
    $query .= " AND a.status = ?";
    $params[] = $status_filter;
    $types .= "s";
}

// Safe date filter
if (!empty($date_filter) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date_filter)) {
    $query .= " AND a.appointment_date = ?";
    $params[] = $date_filter;
    $types .= "s";
}

$query .= " ORDER BY a.created_at DESC, a.appointment_date DESC";

$stmt = $conn->prepare($query);
if ($stmt) {
    if (!empty($params)) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    $appointments = [];
    error_log("Error preparing appointments query: " . $conn->error);
}

// Get appointment statistics
$stats_query = "SELECT status, COUNT(*) as count FROM appointments GROUP BY status";
$stats_result = $conn->query($stats_query);
$appointment_stats = [];
while ($row = $stats_result->fetch_assoc()) {
    $appointment_stats[$row['status']] = $row['count'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Appointment Management - Disability System Admin</title>
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
        
        .table-actions {
            white-space: nowrap;
        }
        
        .status-badge {
            font-size: 0.75em;
        }
        
        .stat-card {
            transition: transform 0.2s;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
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
                    <h1 class="h3 mb-0 text-gray-800">Appointment Management</h1>
                    <div>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#statsModal">
                            <i class="fas fa-chart-bar me-2"></i>View Statistics
                        </button>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-primary shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                            Total</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo array_sum($appointment_stats); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-warning shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                            Pending</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $appointment_stats['pending'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-clock fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-success shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                            Approved</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $appointment_stats['approved'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-check-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-info shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                            Scheduled</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $appointment_stats['scheduled'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-calendar-check fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-secondary shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-secondary text-uppercase mb-1">
                                            Completed</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo $appointment_stats['completed'] ?? 0; ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-flag-checkered fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-xl-2 col-md-4 mb-4">
                        <div class="card border-left-danger shadow h-100 py-2 stat-card">
                            <div class="card-body">
                                <div class="row no-gutters align-items-center">
                                    <div class="col mr-2">
                                        <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                            Cancelled</div>
                                        <div class="h5 mb-0 font-weight-bold text-gray-800">
                                            <?php echo ($appointment_stats['cancelled'] ?? 0) + ($appointment_stats['rejected'] ?? 0); ?>
                                        </div>
                                    </div>
                                    <div class="col-auto">
                                        <i class="fas fa-times-circle fa-2x text-gray-300"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Filters -->
                <div class="card shadow mb-4">
                    <div class="card-body">
                        <form method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Status</label>
                                <select name="status" class="form-control" onchange="this.form.submit()">
                                    <option value="all" <?php echo $status_filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                    <option value="scheduled" <?php echo $status_filter === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                    <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Date</label>
                                <input type="date" name="date" class="form-control" 
                                       value="<?php echo htmlspecialchars($date_filter); ?>"
                                       onchange="this.form.submit()">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">&nbsp;</label>
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-filter me-2"></i>Apply Filters
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Appointments Table -->
                <div class="card shadow mb-4">
                    <div class="card-header bg-white">
                        <h6 class="m-0 font-weight-bold text-primary">
                            <i class="fas fa-calendar me-2"></i>Appointments
                            <span class="badge bg-secondary ms-2"><?php echo count($appointments); ?></span>
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>User Information</th>
                                        <th>Appointment Details</th>
                                        <th>Disability Type</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($appointments)): ?>
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5">
                                            <i class="fas fa-calendar fa-3x mb-3"></i><br>
                                            No appointments found matching your criteria
                                        </td>
                                    </tr>
                                    <?php else: ?>
                                        <?php foreach ($appointments as $apt): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($apt['full_name']); ?></strong><br>
                                                <small class="text-muted">
                                                    <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($apt['phone']); ?><br>
                                                    <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($apt['email']); ?>
                                                </small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($apt['purpose']); ?></strong><br>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar me-1"></i><?php echo date('M j, Y', strtotime($apt['appointment_date'])); ?><br>
                                                    <i class="fas fa-clock me-1"></i><?php echo $apt['appointment_time']; ?>
                                                </small>
                                            </td>
                                            <td><?php echo htmlspecialchars($apt['disability_type']); ?></td>
                                            <td>
                                                <span class="badge status-badge bg-<?php 
                                                    echo match($apt['status']) {
                                                        'pending' => 'warning',
                                                        'approved' => 'success',
                                                        'scheduled' => 'primary',
                                                        'completed' => 'secondary',
                                                        'cancelled' => 'danger',
                                                        'rejected' => 'dark',
                                                        default => 'secondary'
                                                    }; 
                                                ?>">
                                                    <?php echo ucfirst($apt['status']); ?>
                                                </span>
                                            </td>
                                            <td class="table-actions">
                                                <button class="btn btn-sm btn-outline-primary view-appointment" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#appointmentModal"
                                                        data-apt='<?php echo htmlspecialchars(json_encode($apt)); ?>'
                                                        title="View Details">
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                
                                                <?php if ($apt['status'] === 'pending'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                                    <button type="submit" name="action" value="approved" 
                                                            class="btn btn-sm btn-outline-success"
                                                            onclick="return confirm('Approve this appointment?')"
                                                            title="Approve Appointment">
                                                        <i class="fas fa-check"></i> Approve
                                                    </button>
                                                    <button type="submit" name="action" value="rejected" 
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Reject this appointment?')"
                                                            title="Reject Appointment">
                                                        <i class="fas fa-times"></i> Reject
                                                    </button>
                                                </form>
                                                <?php elseif ($apt['status'] === 'approved' || $apt['status'] === 'scheduled'): ?>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="appointment_id" value="<?php echo $apt['id']; ?>">
                                                    <button type="submit" name="action" value="completed" 
                                                            class="btn btn-sm btn-outline-success"
                                                            onclick="return confirm('Mark this appointment as completed?')"
                                                            title="Mark Completed">
                                                        <i class="fas fa-check-double"></i> Complete
                                                    </button>
                                                    <button type="submit" name="action" value="cancelled" 
                                                            class="btn btn-sm btn-outline-danger"
                                                            onclick="return confirm('Cancel this appointment?')"
                                                            title="Cancel Appointment">
                                                        <i class="fas fa-times"></i> Cancel
                                                    </button>
                                                </form>
                                                <?php endif; ?>
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

    <!-- Appointment Details Modal -->
    <div class="modal fade" id="appointmentModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="appointmentDetails">
                    <!-- Details will be loaded here -->
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Modal -->
    <div class="modal fade" id="statsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Appointment Statistics</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Total Appointments
                            <span class="badge bg-primary rounded-pill"><?php echo array_sum($appointment_stats); ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Pending Approval
                            <span class="badge bg-warning rounded-pill"><?php echo $appointment_stats['pending'] ?? 0; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Approved
                            <span class="badge bg-success rounded-pill"><?php echo $appointment_stats['approved'] ?? 0; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Scheduled
                            <span class="badge bg-info rounded-pill"><?php echo $appointment_stats['scheduled'] ?? 0; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Completed
                            <span class="badge bg-secondary rounded-pill"><?php echo $appointment_stats['completed'] ?? 0; ?></span>
                        </div>
                        <div class="list-group-item d-flex justify-content-between align-items-center">
                            Cancelled/Rejected
                            <span class="badge bg-danger rounded-pill"><?php echo ($appointment_stats['cancelled'] ?? 0) + ($appointment_stats['rejected'] ?? 0); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.view-appointment').on('click', function() {
                const apt = JSON.parse($(this).data('apt'));
                $('#appointmentDetails').html(`
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2">User Information</h6>
                            <p><strong>Name:</strong> ${apt.full_name}</p>
                            <p><strong>Phone:</strong> ${apt.phone}</p>
                            <p><strong>Email:</strong> ${apt.email}</p>
                            <p><strong>Disability Type:</strong> ${apt.disability_type}</p>
                        </div>
                        <div class="col-md-6">
                            <h6 class="border-bottom pb-2">Appointment Details</h6>
                            <p><strong>Date:</strong> ${new Date(apt.appointment_date).toLocaleDateString()}</p>
                            <p><strong>Time:</strong> ${apt.appointment_time}</p>
                            <p><strong>Purpose:</strong> ${apt.purpose}</p>
                            <p><strong>Status:</strong> <span class="badge bg-${getStatusBadgeColor(apt.status)}">${apt.status}</span></p>
                            <p><strong>Created:</strong> ${new Date(apt.created_at).toLocaleString()}</p>
                            <p><strong>Last Updated:</strong> ${new Date(apt.updated_at).toLocaleString()}</p>
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-12">
                            <h6 class="border-bottom pb-2">Additional Notes</h6>
                            <div class="border p-3 rounded bg-light">
                                ${apt.message ? apt.message.replace(/\n/g, '<br>') : '<em class="text-muted">No additional notes provided.</em>'}
                            </div>
                        </div>
                    </div>
                `);
            });
            
            function getStatusBadgeColor(status) {
                const colors = {
                    'pending': 'warning',
                    'approved': 'success',
                    'scheduled': 'primary',
                    'completed': 'secondary',
                    'cancelled': 'danger',
                    'rejected': 'dark'
                };
                return colors[status] || 'secondary';
            }
        });
    </script>
</body>
</html>