<?php
// PHP session and authentication logic will be added later
// For now, this is a pure frontend simulation
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PWD System</title>
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
        
        .settings-container {
            background: #f8f9fc;
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .settings-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.15);
            margin-bottom: 1.5rem;
        }
        
        .settings-header {
            background: linear-gradient(120deg, var(--primary-color), #224abe);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
        }
        
        .settings-sidebar {
            background: var(--light-bg);
            border-radius: 0.5rem;
            padding: 1rem;
            height: fit-content;
        }
        
        .nav-settings .nav-link {
            color: var(--secondary-color);
            padding: 0.75rem 1rem;
            border-radius: 0.35rem;
            margin-bottom: 0.25rem;
        }
        
        .nav-settings .nav-link.active {
            background: var(--primary-color);
            color: white;
        }
        
        .nav-settings .nav-link:hover {
            background: #eaecf4;
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
        
        .form-switch .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }
        
        .settings-group {
            background: var(--light-bg);
            padding: 1.5rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .danger-zone {
            border: 2px solid var(--danger-color);
            background: #f8d7da;
            padding: 1.5rem;
            border-radius: 0.5rem;
        }
        
        .backup-status {
            padding: 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.875rem;
        }
        .test-notification {
    transition: all 0.3s ease;
}

.test-notification:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.input-group-text {
    background-color: var(--light-bg);
    border-color: #d1d3e2;
}

.alert {
    border-left: 4px solid;
}

.alert-info {
    border-left-color: var(--info-color);
}

.alert-warning {
    border-left-color: var(--warning-color);
}
    </style>
</head>
<body>
    <div class="settings-container">
        <div class="container-fluid">
            <!-- Header Section -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="settings-header">
                        <div class="row align-items-center">
                            <div class="col-md-6">
                                <h2 class="mb-0">
                                    <i class="fas fa-cog me-2"></i>System Settings
                                </h2>
                                <p class="mb-0">Configure system preferences and settings</p>
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
                <!-- Settings Sidebar -->
                <div class="col-lg-3">
                    <div class="settings-card settings-sidebar">
                        <div class="nav flex-column nav-pills nav-settings" id="v-pills-tab" role="tablist">
                            <button class="nav-link active" id="v-pills-general-tab" data-bs-toggle="pill" data-bs-target="#v-pills-general" type="button">
                                <i class="fas fa-sliders-h me-2"></i>General Settings
                            </button>
                            <button class="nav-link" id="v-pills-system-tab" data-bs-toggle="pill" data-bs-target="#v-pills-system" type="button">
                                <i class="fas fa-server me-2"></i>System Configuration
                            </button>
                            <button class="nav-link" id="v-pills-security-tab" data-bs-toggle="pill" data-bs-target="#v-pills-security" type="button">
                                <i class="fas fa-shield-alt me-2"></i>Security Settings
                            </button>
                            <button class="nav-link" id="v-pills-backup-tab" data-bs-toggle="pill" data-bs-target="#v-pills-backup" type="button">
                                <i class="fas fa-database me-2"></i>Backup & Restore
                            </button>
                            <button class="nav-link" id="v-pills-notifications-tab" data-bs-toggle="pill" data-bs-target="#v-pills-notifications" type="button">
                                <i class="fas fa-bell me-2"></i>Notifications
                            </button>
                            
                        </div>
                    </div>
                </div>

                <!-- Settings Content -->
                <div class="col-lg-9">
                    <div class="settings-card">
                        <div class="tab-content" id="v-pills-tabContent">
                            <!-- General Settings -->
                            <div class="tab-pane fade show active" id="v-pills-general">
                                <div class="settings-section">
                                    <h4 class="section-title">
                                        <i class="fas fa-sliders-h me-2"></i>General Settings
                                    </h4>
                                    
                                    <div class="settings-group">
                                        <h6 class="mb-3">System Information</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">System Name</label>
                                                <input type="text" class="form-control" value="PWD Registration System">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">System Version</label>
                                                <input type="text" class="form-control" value="v2.1.0" readonly>
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Admin Email</label>
                                                <input type="email" class="form-control" value="admin@twepsn.go.tz">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Support Phone</label>
                                                <input type="tel" class="form-control" value="+255 754 000 001">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="settings-group">
                                        <h6 class="mb-3">System Preferences</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="maintenanceMode" checked>
                                            <label class="form-check-label" for="maintenanceMode">
                                                Maintenance Mode
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="userRegistration" checked>
                                            <label class="form-check-label" for="userRegistration">
                                                Allow User Registration
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="autoUpdate" checked>
                                            <label class="form-check-label" for="autoUpdate">
                                                Automatic Updates
                                            </label>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="button" class="btn btn-secondary me-2">Cancel</button>
                                        <button type="button" class="btn btn-primary">Save Changes</button>
                                    </div>
                                </div>
                            </div>

                            <!-- System Configuration -->
                            <div class="tab-pane fade" id="v-pills-system">
                                <div class="settings-section">
                                    <h4 class="section-title">
                                        <i class="fas fa-server me-2"></i>System Configuration
                                    </h4>
                                    
                                    <div class="settings-group">
                                        <h6 class="mb-3">Database Settings</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Database Host</label>
                                                <input type="text" class="form-control" value="localhost">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Database Name</label>
                                                <input type="text" class="form-control" value="pwd_system_db">
                                            </div>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Database User</label>
                                                <input type="text" class="form-control" value="pwd_admin">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Database Port</label>
                                                <input type="number" class="form-control" value="3306">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="settings-group">
                                        <h6 class="mb-3">Performance Settings</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Cache Duration</label>
                                                <select class="form-select">
                                                    <option>15 minutes</option>
                                                    <option selected>30 minutes</option>
                                                    <option>1 hour</option>
                                                    <option>2 hours</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Session Timeout</label>
                                                <select class="form-select">
                                                    <option>15 minutes</option>
                                                    <option>30 minutes</option>
                                                    <option selected>1 hour</option>
                                                    <option>2 hours</option>
                                                </select>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="button" class="btn btn-secondary me-2">Cancel</button>
                                        <button type="button" class="btn btn-primary">Save Configuration</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Security Settings -->
                            <div class="tab-pane fade" id="v-pills-security">
                                <div class="settings-section">
                                    <h4 class="section-title">
                                        <i class="fas fa-shield-alt me-2"></i>Security Settings
                                    </h4>
                                    
                                    <div class="settings-group">
                                        <h6 class="mb-3">Password Policies</h6>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Minimum Password Length</label>
                                                <input type="number" class="form-control" value="8" min="6" max="20">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Password Expiry</label>
                                                <select class="form-select">
                                                    <option>30 days</option>
                                                    <option selected>60 days</option>
                                                    <option>90 days</option>
                                                    <option>Never</option>
                                                </select>
                                            </div>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="passwordComplexity" checked>
                                            <label class="form-check-label" for="passwordComplexity">
                                                Require Complex Passwords
                                            </label>
                                        </div>
                                    </div>

                                    <div class="settings-group">
                                        <h6 class="mb-3">Two-Factor Authentication</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="require2FA">
                                            <label class="form-check-label" for="require2FA">
                                                Require 2FA for Admin Users
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="allowSMS2FA" checked>
                                            <label class="form-check-label" for="allowSMS2FA">
                                                Allow SMS Authentication
                                            </label>
                                        </div>
                                    </div>

                                    <div class="settings-group">
                                        <h6 class="mb-3">Login Security</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="loginAttempts" checked>
                                            <label class="form-check-label" for="loginAttempts">
                                                Limit Login Attempts
                                            </label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="ipWhitelisting">
                                            <label class="form-check-label" for="ipWhitelisting">
                                                Enable IP Whitelisting
                                            </label>
                                        </div>
                                    </div>

                                    <div class="text-end">
                                        <button type="button" class="btn btn-secondary me-2">Cancel</button>
                                        <button type="button" class="btn btn-primary">Update Security</button>
                                    </div>
                                </div>
                            </div>

                            <!-- Backup & Restore -->
                            <div class="tab-pane fade" id="v-pills-backup">
                                <div class="settings-section">
                                    <h4 class="section-title">
                                        <i class="fas fa-database me-2"></i>Backup & Restore
                                    </h4>
                                    
                                    <div class="settings-group">
                                        <h6 class="mb-3">Backup Settings</h6>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" id="autoBackup" checked>
                                            <label class="form-check-label" for="autoBackup">
                                                Automatic Daily Backups
                                            </label>
                                        </div>
                                        <div class="row mb-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Backup Retention</label>
                                                <select class="form-select">
                                                    <option>7 days</option>
                                                    <option selected>30 days</option>
                                                    <option>60 days</option>
                                                    <option>90 days</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Backup Time</label>
                                                <input type="time" class="form-control" value="02:00">
                                            </div>
                                        </div>
                                    </div>

                                    <div class="settings-group">
                                        <h6 class="mb-3">Current Backup Status</h6>
                                        <div class="alert alert-info">
                                            <i class="fas fa-info-circle me-2"></i>
                                            Last backup: Today at 02:00 AM | Size: 45.2 MB
                                        </div>
                                        <div class="d-grid gap-2">
                                            <button class="btn btn-success">
                                                <i class="fas fa-download me-2"></i>Create Backup Now
                                            </button>
                                            <button class="btn btn-warning">
                                                <i class="fas fa-upload me-2"></i>Restore from Backup
                                            </button>
                                        </div>
                                    </div>

                                    <div class="danger-zone">
                                        <h6 class="text-danger mb-3">
                                            <i class="fas fa-exclamation-triangle me-2"></i>Danger Zone
                                        </h6>
                                        <p class="text-muted mb-3">
                                            These actions are irreversible. Please proceed with caution.
                                        </p>
                                        <div class="d-grid">
                                            <button class="btn btn-danger">
                                                <i class="fas fa-trash me-2"></i>Delete All Data
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Notifications Tab -->
<div class="tab-pane fade" id="v-pills-notifications" role="tabpanel">
    <div class="settings-section">
        <h4 class="section-title">
            <i class="fas fa-bell me-2"></i>Notification Settings
        </h4>
        
        <div class="settings-group">
            <h6 class="mb-3">
                <i class="fas fa-envelope me-2"></i>Email Notifications
            </h6>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="emailSystemAlerts" checked>
                <label class="form-check-label" for="emailSystemAlerts">
                    System Alerts & Maintenance Notifications
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="emailSecurityAlerts" checked>
                <label class="form-check-label" for="emailSecurityAlerts">
                    Security Alerts & Login Attempts
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="emailBackupReports">
                <label class="form-check-label" for="emailBackupReports">
                    Backup Completion Reports
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="emailWeeklyReports" checked>
                <label class="form-check-label" for="emailWeeklyReports">
                    Weekly Summary Reports
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="emailUserRegistrations" checked>
                <label class="form-check-label" for="emailUserRegistrations">
                    New User Registration Alerts
                </label>
            </div>
        </div>

        <div class="settings-group">
            <h6 class="mb-3">
                <i class="fas fa-mobile-alt me-2"></i>SMS Notifications
            </h6>
            <div class="alert alert-info mb-3">
                <i class="fas fa-info-circle me-2"></i>
                SMS notifications are only sent for critical system events and emergencies.
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="smsEmergencyAlerts" checked>
                <label class="form-check-label" for="smsEmergencyAlerts">
                    Emergency System Alerts
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="smsMaintenanceAlerts">
                <label class="form-check-label" for="smsMaintenanceAlerts">
                    System Maintenance Notifications
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="smsSecurityBreaches" checked>
                <label class="form-check-label" for="smsSecurityBreaches">
                    Critical Security Breach Alerts
                </label>
            </div>
        </div>

        <div class="settings-group">
            <h6 class="mb-3">
                <i class="fas fa-desktop me-2"></i>In-App Notifications
            </h6>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="appNewRegistrations" checked>
                <label class="form-check-label" for="appNewRegistrations">
                    New User Registration Notifications
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="appDataExports" checked>
                <label class="form-check-label" for="appDataExports">
                    Data Export Completion Alerts
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="appSystemErrors" checked>
                <label class="form-check-label" for="appSystemErrors">
                    System Error & Warning Notifications
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="appLoginAlerts" checked>
                <label class="form-check-label" for="appLoginAlerts">
                    Suspicious Login Attempt Alerts
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="appBackupAlerts" checked>
                <label class="form-check-label" for="appBackupAlerts">
                    Backup Status Notifications
                </label>
            </div>
        </div>

        <div class="settings-group">
            <h6 class="mb-3">
                <i class="fas fa-sliders-h me-2"></i>Notification Preferences
            </h6>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Notification Sound</label>
                    <select class="form-select" id="notificationSound">
                        <option value="default" selected>Default Sound</option>
                        <option value="chime">Gentle Chime</option>
                        <option value="alert">Alert Tone</option>
                        <option value="none">No Sound</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Desktop Notifications</label>
                    <select class="form-select" id="desktopNotifications">
                        <option value="enabled" selected>Enabled</option>
                        <option value="disabled">Disabled</option>
                        <option value="critical">Critical Only</option>
                    </select>
                </div>
            </div>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Do Not Disturb Hours</label>
                    <div class="input-group">
                        <input type="time" class="form-control" value="22:00">
                        <span class="input-group-text">to</span>
                        <input type="time" class="form-control" value="06:00">
                    </div>
                    <div class="form-text">Notifications will be silenced during these hours</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Notification Retention</label>
                    <select class="form-select" id="notificationRetention">
                        <option value="7">7 days</option>
                        <option value="30" selected>30 days</option>
                        <option value="90">90 days</option>
                        <option value="365">1 year</option>
                        <option value="forever">Keep forever</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="settings-group">
            <h6 class="mb-3">
                <i class="fas fa-user-shield me-2"></i>Admin Alert Preferences
            </h6>
            <div class="row mb-3">
                <div class="col-md-6">
                    <label class="form-label">Critical Alert Threshold</label>
                    <select class="form-select" id="criticalAlertThreshold">
                        <option value="immediate">Immediate</option>
                        <option value="15min">15 minutes</option>
                        <option value="30min" selected>30 minutes</option>
                        <option value="1hour">1 hour</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Alert Escalation</label>
                    <select class="form-select" id="alertEscalation">
                        <option value="none">No Escalation</option>
                        <option value="1hour" selected>Escalate after 1 hour</option>
                        <option value="4hours">Escalate after 4 hours</option>
                        <option value="24hours">Escalate after 24 hours</option>
                    </select>
                </div>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="escalateToSuperAdmin" checked>
                <label class="form-check-label" for="escalateToSuperAdmin">
                    Escalate critical alerts to Super Admin
                </label>
            </div>
            <div class="form-check form-switch mb-3">
                <input class="form-check-input" type="checkbox" id="weekendAlerts" checked>
                <label class="form-check-label" for="weekendAlerts">
                    Send alerts on weekends
                </label>
            </div>
        </div>

        <div class="settings-group">
            <h6 class="mb-3">
                <i class="fas fa-test-tube me-2"></i>Test Notification Settings
            </h6>
            <div class="alert alert-warning mb-3">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Test your notification settings to ensure they are working correctly.
            </div>
            <div class="row">
                <div class="col-md-4 mb-2">
                    <button class="btn btn-outline-primary w-100 test-notification" data-type="email">
                        <i class="fas fa-envelope me-1"></i> Test Email
                    </button>
                </div>
                <div class="col-md-4 mb-2">
                    <button class="btn btn-outline-info w-100 test-notification" data-type="sms">
                        <i class="fas fa-sms me-1"></i> Test SMS
                    </button>
                </div>
                <div class="col-md-4 mb-2">
                    <button class="btn btn-outline-success w-100 test-notification" data-type="app">
                        <i class="fas fa-bell me-1"></i> Test In-App
                    </button>
                </div>
            </div>
        </div>

        <div class="text-end">
            <button type="button" class="btn btn-secondary me-2" id="resetNotificationSettings">
                <i class="fas fa-undo me-1"></i> Reset to Default
            </button>
            <button type="button" class="btn btn-primary" id="saveNotificationSettings">
                <i class="fas fa-save me-1"></i> Save Notification Settings
            </button>
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
        // Save settings functionality
        $('.btn-primary').on('click', function() {
            const btn = $(this);
            const originalText = btn.html();
            
            btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Saving...');
            btn.prop('disabled', true);
            
            setTimeout(function() {
                alert('Settings saved successfully!');
                btn.html(originalText);
                btn.prop('disabled', false);
            }, 1500);
        });

        // Backup button functionality
        $('.btn-success').on('click', function() {
            const btn = $(this);
            const originalText = btn.html();
            
            btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Backing up...');
            
            setTimeout(function() {
                alert('Backup completed successfully!');
                btn.html(originalText);
            }, 2000);
        });

        // Restore button functionality
        $('.btn-warning').on('click', function() {
            if(confirm('Are you sure you want to restore from backup? This will overwrite current data.')) {
                const btn = $(this);
                const originalText = btn.html();
                
                btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Restoring...');
                
                setTimeout(function() {
                    alert('Restore completed successfully!');
                    btn.html(originalText);
                }, 2500);
            }
        });

        // Danger zone button
        $('.btn-danger').on('click', function() {
            if(confirm('⚠️ DANGER: This will permanently delete ALL data. Are you absolutely sure?')) {
                if(confirm('This action cannot be undone. Please type "DELETE" to confirm:')) {
                    const confirmation = prompt('Type "DELETE" to confirm:');
                    if(confirmation === 'DELETE') {
                        alert('Data deletion process started...');
                    } else {
                        alert('Deletion cancelled.');
                    }
                }
            }
        });

        // Refresh button
        $('.btn-light').last().on('click', function() {
            const btn = $(this);
            const originalText = btn.html();
            
            btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Refreshing...');
            
            setTimeout(function() {
                alert('Settings refreshed!');
                btn.html(originalText);
            }, 1000);
        });
    });
    // Notification tab specific functionality
$('.test-notification').on('click', function() {
    const notificationType = $(this).data('type');
    const btn = $(this);
    const originalText = btn.html();
    
    btn.html('<i class="fas fa-spinner fa-spin me-1"></i> Testing...');
    btn.prop('disabled', true);
    
    setTimeout(function() {
        let message = '';
        switch(notificationType) {
            case 'email':
                message = 'Test email notification sent! Check your inbox.';
                break;
            case 'sms':
                message = 'Test SMS notification sent! Check your phone.';
                break;
            case 'app':
                message = 'Test in-app notification displayed!';
                break;
        }
        alert(message);
        btn.html(originalText);
        btn.prop('disabled', false);
    }, 2000);
});

// Save notification settings
$('#saveNotificationSettings').on('click', function() {
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

// Reset notification settings
$('#resetNotificationSettings').on('click', function() {
    if(confirm('Are you sure you want to reset all notification settings to default?')) {
        // Reset all checkboxes and selects to default values
        $('.form-check-input').prop('checked', function() {
            return this.defaultChecked;
        });
        
        $('.form-select').each(function() {
            $(this).val($(this).find('option[selected]').val() || $(this).find('option:first').val());
        });
        
        alert('Notification settings have been reset to default values.');
    }
});

// Initialize tooltips for better UX
$('[data-bs-toggle="tooltip"]').tooltip();
    </script>
</body>
</html>