<?php
session_start();
require '../database.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: admin_login.php");
    exit;
}

// Handle notification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['mark_all_read'])) {
        // Mark all notifications as read
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE admin_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['user_id']);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "All notifications marked as read!";
                $_SESSION['flash_type'] = 'success';
            }
            $stmt->close();
        }
        header("Location: admin_notifications.php");
        exit;
    }
    
    if (isset($_POST['delete_all'])) {
        // Delete all notifications
        $stmt = $conn->prepare("DELETE FROM notifications WHERE admin_id = ?");
        if ($stmt) {
            $stmt->bind_param("i", $_SESSION['admin_id']);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "All notifications deleted!";
                $_SESSION['flash_type'] = 'success';
            }
            $stmt->close();
        }
        header("Location: admin_notifications.php");
        exit;
    }
    
    if (isset($_POST['mark_read']) && isset($_POST['notification_id'])) {
        // Mark single notification as read
        $notification_id = sanitize_input($_POST['notification_id']);
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND admin_id = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $notification_id, $_SESSION['user_id']);
            if ($stmt->execute()) {
                $_SESSION['flash_message'] = "Notification marked as read!";
                $_SESSION['flash_type'] = 'success';
            }
            $stmt->close();
        }
        header("Location: admin_notifications.php");
        exit;
    }
    
    if (isset($_POST['send_notification'])) {
        // Send notification to users
        $title = sanitize_input($_POST['title'] ?? '');
        $message = sanitize_input($_POST['message'] ?? '');
        $notification_type = sanitize_input($_POST['notification_type'] ?? 'info');
        
        if (empty($title) || empty($message)) {
            $_SESSION['flash_message'] = "Title and message are required!";
            $_SESSION['flash_type'] = 'danger';
        } else {
            // Create notification for admin (system notification)
            $stmt = $conn->prepare("INSERT INTO notifications (admin_id, title, message, type) VALUES (?, ?, ?, ?)");
            if ($stmt) {
                $admin_notification_title = "Notification Sent";
                $admin_notification_message = "You sent a notification: {$title}";
                $stmt->bind_param("isss", $_SESSION['user_id'], $admin_notification_title, $admin_notification_message, $notification_type);
                $stmt->execute();
                $stmt->close();
            }
            
            $_SESSION['flash_message'] = "Notification created successfully!";
            $_SESSION['flash_type'] = 'success';
            header("Location: admin_notifications.php");
            exit;
        }
    }
}

// Flash message handling
if (isset($_SESSION['flash_message'])) {
    $flash_message = $_SESSION['flash_message'];
    $flash_type = $_SESSION['flash_type'] ?? 'success';
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Fetch admin notifications
$notifications = [];
$stmt = $conn->prepare("SELECT * FROM notifications WHERE admin_id = ? ORDER BY created_at DESC");
if ($stmt) {
    $stmt->bind_param("i", $_SESSION['user_id']);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// Get notification statistics
$total_notifications = count($notifications);
$unread_count = 0;
foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unread_count++;
    }
}

// Fetch recent system activities for notifications
$recent_activities = [];
$stmt = $conn->prepare("SELECT * FROM audit_log ORDER BY created_at DESC LIMIT 10");
if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $recent_activities = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Disability System Admin</title>
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
        
        .notification-item {
            border-left: 4px solid;
            transition: all 0.3s;
        }
        
        .notification-item:hover {
            transform: translateX(5px);
        }
        
        .notification-unread {
            background-color: #f8f9fa;
            border-left-color: #007bff;
        }
        
        .notification-info { border-left-color: #17a2b8; }
        .notification-success { border-left-color: #28a745; }
        .notification-warning { border-left-color: #ffc107; }
        .notification-danger { border-left-color: #dc3545; }
        
        .badge-notification {
            font-size: 0.7em;
        }
    </style>
</head>
<body class="d-flex">
    <!-- Sidebar -->
    <?php include('admin_sidebar.php'); ?>
    
    <!-- Content -->
    <div class="flex-grow-1 main-content">
        <!-- Topbar -->
        <?php include('admin_topbar.php'); ?>
        
        <!-- Flash Message -->
        <?php if (isset($flash_message)): ?>
        <div class="flash-message">
            <div class="alert alert-<?php echo $flash_type; ?> alert-dismissible shadow">
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

        <div class="container-fluid p-4">
            <div class="d-sm-flex align-items-center justify-content-between mb-4">
                <h1 class="h3 mb-0 text-gray-800">Notification Center</h1>
                <div>
                    <span class="badge bg-primary me-2">Total: <?php echo $total_notifications; ?></span>
                    <span class="badge bg-warning">Unread: <?php echo $unread_count; ?></span>
                </div>
            </div>

            <div class="row">
                <!-- Send Notification Form -->
                <div class="col-lg-4 mb-4">
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-bell me-2"></i>Create Notification
                            </h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <div class="mb-3">
                                    <label class="form-label">Title *</label>
                                    <input type="text" name="title" class="form-control" 
                                           placeholder="Notification title" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Message *</label>
                                    <textarea name="message" class="form-control" rows="3" 
                                              placeholder="Notification message" required></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Notification Type</label>
                                    <select name="notification_type" class="form-control">
                                        <option value="info">Information</option>
                                        <option value="success">Success</option>
                                        <option value="warning">Warning</option>
                                        <option value="danger">Important</option>
                                    </select>
                                </div>
                                
                                <button type="submit" name="send_notification" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane me-2"></i>Create Notification
                                </button>
                            </form>
                        </div>
                    </div>

                    <!-- Notification Statistics -->
                    <div class="card shadow mt-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-chart-bar me-2"></i>Notification Stats
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <small class="text-muted">Total Notifications</small>
                                <div class="h4"><?php echo $total_notifications; ?></div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Unread Notifications</small>
                                <div class="h4 text-warning"><?php echo $unread_count; ?></div>
                            </div>
                            <div class="mb-3">
                                <small class="text-muted">Read Notifications</small>
                                <div class="h4 text-success"><?php echo $total_notifications - $unread_count; ?></div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <form method="POST" class="d-inline">
                                    <button type="submit" name="mark_all_read" class="btn btn-success w-100">
                                        <i class="fas fa-check-double me-2"></i>Mark All as Read
                                    </button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete all notifications?')">
                                    <button type="submit" name="delete_all" class="btn btn-danger w-100">
                                        <i class="fas fa-trash me-2"></i>Delete All
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="col-lg-8">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-bell me-2"></i>Your Notifications
                            </h6>
                        </div>
                        <div class="card-body">
                            <?php if (empty($notifications)): ?>
                                <div class="text-center text-muted py-5">
                                    <i class="fas fa-bell-slash fa-3x mb-3"></i>
                                    <h5>No notifications yet</h5>
                                    <p>You're all caught up! Notifications will appear here.</p>
                                </div>
                            <?php else: ?>
                                <div class="list-group">
                                    <?php foreach ($notifications as $notification): ?>
                                        <div class="list-group-item notification-item <?php echo !$notification['is_read'] ? 'notification-unread' : ''; ?> <?php echo 'notification-' . ($notification['type'] ?? 'info'); ?>">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                                        <h6 class="mb-0"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                        <?php if (!$notification['is_read']): ?>
                                                            <span class="badge bg-warning badge-notification">New</span>
                                                        <?php endif; ?>
                                                    </div>
                                                    <p class="mb-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <small class="text-muted">
                                                        <i class="fas fa-clock me-1"></i>
                                                        <?php echo date('M j, Y g:i A', strtotime($notification['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <?php if (!$notification['is_read']): ?>
                                                <form method="POST" class="ms-2">
                                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                    <button type="submit" name="mark_read" class="btn btn-sm btn-outline-success" title="Mark as read">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Recent System Activities -->
                    <div class="card shadow">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">
                                <i class="fas fa-history me-2"></i>Recent System Activities
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="list-group">
                                <?php if (empty($recent_activities)): ?>
                                    <div class="text-center text-muted py-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        No recent activities
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="list-group-item">
                                            <div class="d-flex align-items-center">
                                                <span class="badge bg-info me-3">
                                                    Activity
                                                </span>
                                                <div class="flex-grow-1">
                                                    <small><strong><?php echo htmlspecialchars($activity['action']); ?>:</strong> <?php echo htmlspecialchars($activity['description']); ?></small>
                                                    <br>
                                                    <small class="text-muted">
                                                        By: <?php echo htmlspecialchars($activity['user_email'] ?? 'System'); ?> | 
                                                        <?php echo date('M j, g:i A', strtotime($activity['created_at'])); ?>
                                                    </small>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-refresh notifications every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);

        // Mark notification as read when clicked
        document.addEventListener('DOMContentLoaded', function() {
            const notificationItems = document.querySelectorAll('.notification-item');
            notificationItems.forEach(item => {
                item.addEventListener('click', function() {
                    const form = this.querySelector('form');
                    if (form) {
                        form.submit();
                    }
                });
            });
        });
    </script>
</body>
</html>