<?php
session_start();
require 'database.php';

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id']) || !isset($_SESSION['full_name'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];
$success_msg = $error_msg = '';
$password_error = '';

// Fetch user data
$sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Handle settings form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Check if it's password change or settings update
    if (isset($_POST['change_password'])) {
        // Password change logic
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';
        
        // Verify current password (assuming passwords are hashed)
        if (password_verify($current_password, $user['password'])) {
            if ($new_password === $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE users SET password = ? WHERE id = ?";
                    $stmt = $conn->prepare($update_sql);
                    $stmt->bind_param("si", $hashed_password, $user_id);
                    
                    if ($stmt->execute()) {
                        $success_msg = "Password updated successfully!";
                    } else {
                        $password_error = "Error updating password: " . $conn->error;
                    }
                } else {
                    $password_error = "New password must be at least 6 characters long";
                }
            } else {
                $password_error = "New passwords do not match";
            }
        } else {
            $password_error = "Current password is incorrect";
        }
    } else {
        // Settings update logic
        $communication_preference = $_POST['communication_preference'] ?? '';
        $notifications = isset($_POST['notifications']) ? 1 : 0;
        $language = $_POST['language'] ?? 'en';
        $theme = $_POST['theme'] ?? 'light';

        $update_sql = "UPDATE users SET 
                      communication_preference = ?,
                      notifications_enabled = ?,
                      language_preference = ?,
                      theme_preference = ?
                      WHERE id = ?";
        
        $stmt = $conn->prepare($update_sql);
        $stmt->bind_param("sissi", $communication_preference, $notifications, $language, $theme, $user_id);
        
        if ($stmt->execute()) {
            $success_msg = "Settings updated successfully!";
            // Refresh user data
            $sql = "SELECT * FROM users WHERE id = ? LIMIT 1";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $error_msg = "Error updating settings: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - PWD System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .settings-card {
            background: white;
            border-radius: 0.5rem;
            box-shadow: 0 0.15rem 1.75rem 0 rgba(58, 59, 69, 0.1);
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .setting-section {
            border-bottom: 1px solid #e3e6f0;
            padding: 1.5rem 0;
        }
        .setting-section:last-child {
            border-bottom: none;
        }
        .settings-header {
            background: linear-gradient(120deg, #4e73df, #224abe);
            color: white;
            padding: 1.5rem;
            border-radius: 0.5rem 0.5rem 0 0;
            margin: -2rem -2rem 2rem -2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            background: #f8f9fc;
        }

        .container.mt-4 {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        .row {
            justify-content: center;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
        }

        .col-lg-9 {
            display: flex;
            justify-content: center;
        }

        .settings-card {
            width: 100%;
            max-width: 800px;
            margin: 0;
        }
        
        .btn-return {
            background: linear-gradient(120deg, #858796, #6c757d);
            border: none;
            color: white;
        }
        
        .btn-return:hover {
            background: linear-gradient(120deg, #6c757d, #5a6268);
            color: white;
        }
    </style>
</head>
<body>
    

    <div class="container mt-4">
        <div class="row">
            <div class="col-lg-9">
                <div class="settings-card">
                    <!-- Header with gradient background -->
                    <div class="settings-header">
                        <h2 class="mb-0"><i class="fas fa-cog me-2"></i>Settings</h2>
                        <a href="user_dashboard.php" class="btn btn-return">
                            <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                        </a>
                    </div>
                    
                    <?php if ($success_msg): ?>
                        <div class="alert alert-success"><?php echo $success_msg; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($error_msg): ?>
                        <div class="alert alert-danger"><?php echo $error_msg; ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <!-- Communication Preferences -->
                        <div class="setting-section">
                            <h5>Communication Preferences</h5>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="communication_preference" 
                                    value="email" <?php echo ($user['communication_preference'] ?? '') == 'email' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Email</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="radio" name="communication_preference" 
                                    value="sms" <?php echo ($user['communication_preference'] ?? '') == 'sms' ? 'checked' : ''; ?>>
                                <label class="form-check-label">SMS</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="communication_preference" 
                                    value="both" <?php echo ($user['communication_preference'] ?? '') == 'both' ? 'checked' : ''; ?>>
                                <label class="form-check-label">Both Email & SMS</label>
                            </div>
                        </div>

                        <!-- Notification Settings -->
                        <div class="setting-section">
                            <h5>Notification Settings</h5>
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="notifications" 
                                    <?php echo ($user['notifications_enabled'] ?? 0) ? 'checked' : ''; ?>>
                                <label class="form-check-label">Enable email notifications</label>
                            </div>
                        </div>

                        <!-- Language Preferences -->
                        <div class="setting-section">
                            <h5>Language Preferences</h5>
                            <select class="form-select" name="language">
                                <option value="en" <?php echo ($user['language_preference'] ?? 'en') == 'en' ? 'selected' : ''; ?>>English</option>
                                <option value="sw" <?php echo ($user['language_preference'] ?? '') == 'sw' ? 'selected' : ''; ?>>Swahili</option>
                            </select>
                        </div>

                        <!-- Theme Preferences -->
                        <div class="setting-section">
                            <h5>Theme Preferences</h5>
                            <select class="form-select" name="theme">
                                <option value="light" <?php echo ($user['theme_preference'] ?? 'light') == 'light' ? 'selected' : ''; ?>>Light</option>
                                <option value="dark" <?php echo ($user['theme_preference'] ?? '') == 'dark' ? 'selected' : ''; ?>>Dark</option>
                            </select>
                        </div>

                        <!-- Password Change Section -->
                        <div class="setting-section">
                            <h5>Change Password</h5>
                            <?php if ($password_error): ?>
                                <div class="alert alert-danger"><?php echo $password_error; ?></div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Current Password</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">New Password</label>
                                <input type="password" name="new_password" class="form-control" required minlength="6">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Confirm New Password</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="6">
                            </div>
                            
                            <button type="submit" name="change_password" class="btn btn-warning">
                                <i class="fas fa-key me-2"></i>Change Password
                            </button>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-2"></i>Save Settings
                            </button>
                            <a href="user_dashboard.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>