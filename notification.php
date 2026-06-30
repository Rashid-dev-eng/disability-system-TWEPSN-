<?php
// PHP session and authentication logic will be added later
// For now, this is a pure frontend simulation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification Settings - PWD System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
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
        }
        
        .notification-container {
            background: #f8f9fc;
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .notification-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        
        .notification-header {
            background: linear-gradient(120deg, var(--primary-color), #224abe);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        .settings-section {
            padding: 1.5rem;
        }
        
        .section-title {
            color: var(--primary-color);
            border-bottom: 2px solid var(--light-bg);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .settings-group {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .form-switch .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .notification-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .notification-item {
            border-left: 4px solid transparent;
            padding: 1rem;
            margin-bottom: 0.5rem;
            border-radius: 0.25rem;
            transition: all 0.3s;
        }
        
        .notification-item.unread {
            border-left-color: var(--primary-color);
            background-color: #f8f9fe;
        }
        
        .notification-item:hover {
            background-color: #f0f4f8;
        }
        
        .notification-priority-high {
            border-left-color: var(--danger-color) !important;
        }
        
        .notification-priority-medium {
            border-left-color: var(--warning-color) !important;
        }
        
        .notification-time {
            font-size: 0.875rem;
            color: var(--secondary-color);
        }
        
        .badge-notification {
            padding: 0.3rem 0.6rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
        }
        
        .tab-content {
            min-height: 500px;
        }
        
        .template-card {
            border: 1px solid #e3e6f0;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s;
        }
        
        .template-card:hover {
            border-color: var(--primary-color);
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body>
    <div class="notification-container">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="notification-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h2 class="mb-0">
                                    <i class="fas fa-bell me-2"></i>Notification Management
                                </h2>
                                <p class="mb-0">Manage system notifications and alerts</p>
                            </div>
                            <div class="col-md-6 text-md-end">
                                <a href="admin_dashboard.php" class="btn btn-light me-2">
                                    <i class="fas fa-arrow-left me-1"></i> Back to Dashboard
                                </a>
                                <button class="btn btn-light">
                                    <i class="fas fa-sync-alt me-1"></i> Refresh
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- Notification Navigation -->
                <div class="col-lg-3">
                    <div class="notification-card">
                        <div class="settings-section">
                            <div class="nav flex-column nav-pills" id="notificationTabs" role="tablist">
                                <button class="nav-link active" id="settings-tab" data-bs-toggle="pill" data-bs-target="#settings" type="button">
                                    <i class="fas fa-cog me-2"></i>Notification Settings
                                </button>
                                <button class="nav-link" id="inbox-tab" data-bs-toggle="pill" data-bs-target="#inbox" type="button">
                                    <i class="fas fa-inbox me-2"></i>Notification Inbox
                                    <span class="badge bg-danger float-end">3</span>
                                </button>
                                <button class="nav-link" id="templates-tab" data-bs-toggle="pill" data-bs-target="#templates" type="button">
                                    <i class="fas fa-envelope me-2"></i>Message Templates
                                </button>
                                <button class="nav-link" id="alerts-tab" data-bs-toggle="pill" data-bs-target="#alerts" type="button">
                                    <i class="fas fa-exclamation-triangle me-2"></i>System Alerts
                                </button>
                                <button class="nav-link" id="logs-tab" data-bs-toggle="pill" data-bs-target="#logs" type="button">
                                    <i class="fas fa-history me-2"></i>Notification Logs
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Stats -->
                    <div class="notification-card mt-3">
                        <div class="settings-section">
                            <h6 class="mb-3">Notification Stats</h6>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Unread Notifications:</span>
                                <strong class="text-primary">3</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>Today's Notifications:</span>
                                <strong>12</strong>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span>This Week:</span>
                                <strong>45</strong>
                            </div>
                            <div class="d-flex justify-content-between">
                                <span>Total Notifications:</span>
                                <strong>1,248</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notification Content -->
                <div class="col-lg-9">
                    <div class="notification-card">
                        <div class="tab-content" id="notificationTabContent">
                            <!-- Notification Settings -->
                            <div class="tab-pane fade show active" id="settings">
                                <div class="settings-section">
                                    <h4 class="section-title">
                                        <i class="fas fa-cog me-2"></i>Notification Preferences
                                    </h4>
                                    
                                    <div class="settings-group">
                                        <h6 class="mb-3">Email Notifications</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="emailSystemAlerts" checked>
                                            <label class="form-check-label" for="emailSystemAlerts">
                                                System Alerts & Maintenance
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="emailSecurity" checked>
                                            <label class="form-check-label" for="emailSecurity">
                                                Security Notifications
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="emailReports">
                                            <label class="form-check-label" for="emailReports">
                                                Weekly Reports
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="emailBackup" checked>
                                            <label class="form-check-label" for="emailBackup">
                                                Backup Completion Alerts
                                            </label>
                                        </div>
                                    </div>

                                    <div class="settings-group">
                                        <h6 class="mb-3">In-App Notifications</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="appNewRegistrations" checked>
                                            <label class="form-check-label" for="appNewRegistrations">
                                                New User Registrations
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="appDataExports" checked>
                                            <label class="form-check-label" for="appDataExports">
                                                Data Export Requests
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="appSystemErrors" checked>
                                            <label class="form-check-label" for="appSystemErrors">
                                                System Error Alerts
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="appLoginAlerts" checked>
                                            <label class="form-check-label" for="appLoginAlerts">
                                                Suspicious Login Attempts
                                            </label>
                                        </div>
                                    </div>

                                    <div class="settings-group">
                                        <h6 class="mb-3">SMS Notifications</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="smsEmergency">
                                            <label class="form-check-label" for="smsEmergency">
                                                Emergency System Alerts
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="smsMaintenance">
                                            <label class="form-check-label" for="smsMaintenance">
                                                Maintenance Notifications
                                            </label>
                                        </div>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            SMS notifications are only sent for critical system events.
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="button" class="btn btn-secondary me-2">Reset to Default</button>
                                        <button type="button" class="btn btn-primary">Save Preferences</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Notification Inbox -->
                            <div class="tab-pane fade" id="inbox">
                                <div class="settings-section">
                                    <h4 class="section-title">
                                        <i class="fas fa-inbox me-2"></i>Notification Inbox
                                    </h4>
                                    
                                    <div class="d-flex justify-content-between mb-3">
                                        <div class="btn-group">
                                            <button class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-inbox me-1"></i> All
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-envelope me-1"></i> Unread
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-check me-1"></i> Read
                                            </button>
                                        </div>
                                        <div class="btn-group">
                                            <button class="btn btn-outline-danger btn-sm">
                                                <i class="fas fa-trash me-1"></i> Delete All
                                            </button>
                                            <button class="btn btn-outline-success btn-sm">
                                                <i class="fas fa-check-double me-1"></i> Mark All Read
                                            </button>
                                        </div>
                                    </div>

                                    <div class="notification-list">
                                        <!-- Unread Notifications -->
                                        <div class="notification-item unread notification-priority-high">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <i class="fas fa-exclamation-circle text-danger me-2"></i>
                                                        System Backup Failed
                                                    </h6>
                                                    <p class="mb-1">The scheduled system backup failed to complete. Please check storage availability.</p>
                                                    <small class="notification-time">
                                                        <i class="fas fa-clock me-1"></i>10 minutes ago
                                                        <span class="badge badge-notification bg-danger ms-2">High Priority</span>
                                                    </small>
                                                </div>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="notification-item unread">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <i class="fas fa-user-plus text-success me-2"></i>
                                                        New User Registration
                                                    </h6>
                                                    <p class="mb-1">Sarah Juma has registered in the system. Requires verification.</p>
                                                    <small class="notification-time">
                                                        <i class="fas fa-clock me-1"></i>2 hours ago
                                                        <span class="badge badge-notification bg-info ms-2">Normal</span>
                                                    </small>
                                                </div>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="notification-item unread notification-priority-medium">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <i class="fas fa-database text-warning me-2"></i>
                                                        Storage Space Warning
                                                    </h6>
                                                    <p class="mb-1">System storage is at 85% capacity. Consider cleaning up old data.</p>
                                                    <small class="notification-time">
                                                        <i class="fas fa-clock me-1"></i>5 hours ago
                                                        <span class="badge badge-notification bg-warning ms-2">Medium Priority</span>
                                                    </small>
                                                </div>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Read Notifications -->
                                        <div class="notification-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <i class="fas fa-sync-alt text-info me-2"></i>
                                                        System Update Available
                                                    </h6>
                                                    <p class="mb-1">New system version v2.1.1 is available for installation.</p>
                                                    <small class="notification-time">
                                                        <i class="fas fa-clock me-1"></i>Yesterday
                                                    </small>
                                                </div>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="notification-item">
                                            <div class="d-flex justify-content-between align-items-start">
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-1">
                                                        <i class="fas fa-chart-line text-primary me-2"></i>
                                                        Weekly Report Generated
                                                    </h6>
                                                    <p class="mb-1">Weekly system performance report has been generated and is ready for review.</p>
                                                    <small class="notification-time">
                                                        <i class="fas fa-clock me-1"></i>2 days ago
                                                    </small>
                                                </div>
                                                <div class="btn-group">
                                                    <button class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="d-flex justify-content-between mt-3">
                                        <span class="text-muted">Showing 5 of 1,248 notifications</span>
                                        <button class="btn btn-outline-primary btn-sm">
                                            Load More Notifications
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Message Templates -->
                            <div class="tab-pane fade" id="templates">
                                <div class="settings-section">
                                    <h4 class="section-title">
                                        <i class="fas fa-envelope me-2"></i>Message Templates
                                    </h4>
                                    
                                    <div class="d-flex justify-content-between mb-3">
                                        <button class="btn btn-primary btn-sm">
                                            <i class="fas fa-plus me-1"></i> New Template
                                        </button>
                                        <div class="btn-group">
                                            <button class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-download me-1"></i> Export
                                            </button>
                                            <button class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-upload me-1"></i> Import
                                            </button>
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <div class="template-card">
                                                <h6 class="mb-2">Welcome Email</h6>
                                                <p class="text-muted small mb-2">Sent to new users after registration</p>
                                                <div class="d-flex justify-content-between">
                                                    <span class="badge bg-success">Active</span>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="template-card">
                                                <h6 class="mb-2">Password Reset</h6>
                                                <p class="text-muted small mb-2">Sent when user requests password reset</p>
                                                <div class="d-flex justify-content-between">
                                                    <span class="badge bg-success">Active</span>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="template-card">
                                                <h6 class="mb-2">System Maintenance</h6>
                                                <p class="text-muted small mb-2">Notification for scheduled maintenance</p>
                                                <div class="d-flex justify-content-between">
                                                    <span class="badge bg-success">Active</span>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <div class="template-card">
                                                <h6 class="mb-2">Account Verification</h6>
                                                <p class="text-muted small mb-2">Email verification for new accounts</p>
                                                <div class="d-flex justify-content-between">
                                                    <span class="badge bg-warning text-dark">Draft</span>
                                                    <div class="btn-group">
                                                        <button class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <button class="btn btn-sm btn-outline-secondary">
                                                            <i class="fas fa-eye"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- System Alerts and Logs tabs would continue here -->
                            <!-- Content structure similar to the tabs above -->

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
        // Save notification settings
        $('.btn-primary').on('click', function() {
            const btn = $(this);
            const originalText = btn.html();
            
            btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');
            btn.prop('disabled', true);
            
            setTimeout(function() {
                alert('Notification settings saved successfully!');
                btn.html(originalText);
                btn.prop('disabled', false);
            }, 1500);
        });

        // Mark as read functionality
        $('.notification-item .btn-outline-secondary').on('click', function() {
            const notification = $(this).closest('.notification-item');
            notification.removeClass('unread');
            alert('Notification marked as read!');
        });

        // Delete notification
        $('.notification-item .btn-outline-danger').on('click', function() {
            const notification = $(this).closest('.notification-item');
            if(confirm('Are you sure you want to delete this notification?')) {
                notification.slideUp(300, function() {
                    $(this).remove();
                });
            }
        });

        // Mark all as read
        $('.btn-outline-success').on('click', function() {
            $('.notification-item').removeClass('unread');
            alert('All notifications marked as read!');
        });

        // Delete all notifications
        $('.btn-outline-danger').on('click', function() {
            if(confirm('Are you sure you want to delete all notifications? This action cannot be undone.')) {
                $('.notification-item').slideUp(300, function() {
                    $(this).remove();
                });
                alert('All notifications deleted!');
            }
        });

        // Refresh button
        $('.btn-light').last().on('click', function() {
            const btn = $(this);
            const originalText = btn.html();
            
            btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Refreshing...');
            
            setTimeout(function() {
                alert('Notifications refreshed!');
                btn.html(originalText);
            }, 1000);
        });

        // Template actions
        $('.template-card .btn-outline-primary').on('click', function() {
            const templateName = $(this).closest('.template-card').find('h6').text();
            alert(`Editing template: ${templateName}`);
        });

        $('.template-card .btn-outline-secondary').on('click', function() {
            const templateName = $(this).closest('.template-card').find('h6').text();
            alert(`Previewing template: ${templateName}`);
        });
    });
    </script>
</body>
</html>