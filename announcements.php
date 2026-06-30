<?php
session_start();
require 'database.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['full_name'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = trim($_SESSION['full_name']);

// Fetch all published announcements (matching your admin panel status)
$announcements = [];
$error_message = "";

try {
    // Use 'published' status instead of 'active' to match your admin panel
    $sql = "SELECT * FROM announcements WHERE status = 'published' ORDER BY created_at DESC";
    $stmt = $conn->prepare($sql);
    
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $announcements[] = $row;
            }
        } else {
            $error_message = "No published announcements found. Announcements may be in draft status or none have been created yet.";
        }
        $stmt->close();
    } else {
        $error_message = "Database query error: " . $conn->error;
    }
} catch (Exception $e) {
    error_log("Announcements error: " . $e->getMessage());
    $error_message = "Database connection error. Please try again later.";
}

// Function to format time display
function formatAnnouncementTime($timestamp) {
    $now = new DateTime();
    $announcementTime = new DateTime($timestamp);
    $interval = $now->diff($announcementTime);
    
    // If today, show time only
    if ($announcementTime->format('Y-m-d') === $now->format('Y-m-d')) {
        return 'Today at ' . $announcementTime->format('g:i A');
    }
    // If yesterday
    elseif ($announcementTime->format('Y-m-d') === $now->modify('-1 day')->format('Y-m-d')) {
        return 'Yesterday at ' . $announcementTime->format('g:i A');
    }
    // If within the last 7 days
    elseif ($interval->days < 7) {
        return $announcementTime->format('l at g:i A');
    }
    // Otherwise show full date with time
    else {
        return $announcementTime->format('M j, Y \a\t g:i A');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Announcements - PWD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .announcement-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(58, 59, 69, 0.1);
            margin-bottom: 1.5rem;
            border-left: 4px solid #4e73df;
            transition: all 0.3s ease;
        }
        .announcement-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(58, 59, 69, 0.15);
        }
        .announcement-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px 8px 0 0;
        }
        .announcement-date {
            color: #e0e0e0;
            font-size: 0.85rem;
        }
        .back-btn {
            background: rgba(255,255,255,0.2);
            border: 1px solid rgba(255,255,255,0.3);
            color: white;
        }
        .back-btn:hover {
            background: rgba(255,255,255,0.3);
            color: white;
        }
        .priority-high { border-left-color: #e74a3b; }
        .priority-medium { border-left-color: #f6c23e; }
        .priority-low { border-left-color: #1cc88a; }
        .time-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
        }
        .announcement-meta {
            border-top: 1px solid #e9ecef;
            padding-top: 0.75rem;
            margin-top: 0.75rem;
        }
    </style>
</head>
<body>
    <div style="background: #f8f9fc; min-height: 100vh;">
        <nav class="navbar navbar-dark" style="background: linear-gradient(120deg, #4e73df, #224abe);">
            <div class="container">
                <a class="navbar-brand" href="user_dashboard.php">
                    <i class="fas fa-bullhorn me-2"></i>All Announcements
                </a>
                <div>
                    <a href="user_dashboard.php" class="btn back-btn me-2">
                        <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </nav>

        <div class="container py-4">
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-warning text-center">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Notice</h5>
                    <?php echo htmlspecialchars($error_message); ?>
                    <div class="mt-2">
                        <small>Only announcements with 'published' status are visible to users.</small>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (empty($announcements) && empty($error_message)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-bullhorn fa-3x text-muted mb-3"></i>
                    <h3 class="text-muted">No Announcements Available</h3>
                    <p class="text-muted">There are no published announcements at the moment.</p>
                    <a href="user_dashboard.php" class="btn btn-primary mt-2">
                        <i class="fas fa-arrow-left me-1"></i> Return to Dashboard
                    </a>
                </div>
            <?php else: ?>
                <div class="row">
                    <?php foreach ($announcements as $announcement): ?>
                        <div class="col-lg-6 mb-4">
                            <div class="announcement-card priority-<?php echo htmlspecialchars($announcement['priority']); ?>">
                                <div class="announcement-header">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="mb-1 flex-grow-1"><?php echo htmlspecialchars($announcement['title']); ?></h5>
                                        <span class="time-badge">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo formatAnnouncementTime($announcement['created_at']); ?>
                                        </span>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <small>
                                            Priority: <span class="badge bg-<?php 
                                                switch($announcement['priority']) {
                                                    case 'high': echo 'danger'; break;
                                                    case 'medium': echo 'warning'; break;
                                                    case 'low': echo 'success'; break;
                                                    default: echo 'secondary';
                                                }
                                            ?>"><?php echo htmlspecialchars(ucfirst($announcement['priority'])); ?></span>
                                        </small>
                                        <small class="announcement-date">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('F j, Y', strtotime($announcement['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>
                                <div class="p-3">
                                    <p class="mb-3"><?php echo nl2br(htmlspecialchars($announcement['message'])); ?></p>
                                    
                                    <div class="announcement-meta">
                                        <div class="row">
                                            <div class="col-6">
                                                <small class="text-muted">
                                                    <i class="fas fa-users me-1"></i>
                                                    <?php 
                                                        switch($announcement['target_audience']) {
                                                            case 'all_users': echo 'All Users'; break;
                                                            case 'education_users': echo 'Education Support'; break;
                                                            case 'mobility_users': echo 'Mobility Assistance'; break;
                                                            case 'service_users': echo 'General Service'; break;
                                                            default: echo htmlspecialchars($announcement['target_audience']);
                                                        }
                                                    ?>
                                                </small>
                                            </div>
                                            <div class="col-6 text-end">
                                                <small class="text-muted">
                                                    <i class="fas fa-user me-1"></i>
                                                    By <?php echo htmlspecialchars($announcement['created_by']); ?>
                                                </small>
                                            </div>
                                        </div>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar-alt me-1"></i>
                                                Published: <?php echo date('M j, Y \a\t g:i A', strtotime($announcement['created_at'])); ?>
                                            </small>
                                        </div>
                                        <?php if ($announcement['updated_at'] != $announcement['created_at']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-edit me-1"></i>
                                                Updated: <?php echo date('M j, Y \a\t g:i A', strtotime($announcement['updated_at'])); ?>
                                            </small>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>