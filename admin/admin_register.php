<?php
session_start();
require 'admin_database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $admin_conn->real_escape_string($_POST['username'] ?? '');
    $email = $admin_conn->real_escape_string($_POST['email'] ?? '');
    $phone = $admin_conn->real_escape_string($_POST['phone'] ?? '');
    $department = $admin_conn->real_escape_string($_POST['department'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($username)) $errors[] = "Username is required.";
    if (empty($email)) $errors[] = "Email address is required.";
    if (empty($phone)) $errors[] = "Phone number is required.";
    if (empty($department)) $errors[] = "Department is required.";
    if (empty($password)) $errors[] = "Password is required.";
    if (empty($confirm_password)) $errors[] = "Please confirm your password.";

    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    if (!empty($phone) && !preg_match('/^\+?[0-9\s\-\(\)]{10,}$/', $phone)) {
        $errors[] = "Please enter a valid phone number.";
    }

    if (!empty($password) && strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    }

    if (!empty($password) && !empty($confirm_password) && $password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // Check for existing username or email in admins table
    if (empty($errors)) {
        $stmt = $admin_conn->prepare("SELECT id FROM admins WHERE username = ? OR email = ? OR phone = ?");
        if ($stmt) {
            $stmt->bind_param("sss", $username, $email, $phone);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $errors[] = "Username, email or phone already exists in admin system.";
            }
            $stmt->close();
        }
    }

    // Create admin account
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        try {
            // Insert into admins table only - removed user_id reference
            $stmt = $admin_conn->prepare("INSERT INTO admins (username, email, phone, password, department) VALUES (?, ?, ?, ?, ?)");
            
            if (!$stmt) {
                throw new Exception("Failed to prepare admin insertion: " . $admin_conn->error);
            }
            
            $stmt->bind_param("sssss", $username, $email, $phone, $hashed_password, $department);
            
            if ($stmt->execute()) {
                $admin_id = $stmt->insert_id;
                
                // Log the registration
                log_admin_action($admin_conn, $admin_id, $email, "Admin Registration", "New admin account created: {$username}");
                
                // Set session and redirect
                $_SESSION['admin_id'] = $admin_id;
                $_SESSION['username'] = $username;
                $_SESSION['email'] = $email;
                $_SESSION['department'] = $department;
                $_SESSION['role'] = 'admin';
                $_SESSION['login_time'] = time();
                
                $_SESSION['flash_message'] = "Admin account created successfully! Welcome to the dashboard.";
                $_SESSION['flash_type'] = 'success';
                
                header("Location: admin_dashboard.php");
                exit;
            } else {
                throw new Exception("Failed to create admin account: " . $stmt->error);
            }
            
            $stmt->close();
            
        } catch (Exception $e) {
            $errors[] = "Registration failed. Please try again. Error: " . $e->getMessage();
            error_log("Admin registration error: " . $e->getMessage());
        }
    }
}

/**
 * Log admin actions for audit trail
 */
function log_admin_action($conn, $admin_id, $admin_email, $action, $description) {
    try {
        $log_sql = "INSERT INTO audit_log (user_id, user_email, action, description, ip_address) VALUES (?, ?, ?, ?, ?)";
        $log_stmt = $conn->prepare($log_sql);
        if ($log_stmt) {
            $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
            $log_stmt->bind_param("issss", $admin_id, $admin_email, $action, $description, $ip_address);
            $log_stmt->execute();
            $log_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Admin activity log error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Registration - Disability System</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --light-bg: #f8f9fc;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            margin: 0;
        }
        
        .register-card {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            max-width: 600px;
            width: 100%;
            margin: 0 auto;
        }
        
        .register-header {
            background: linear-gradient(120deg, var(--primary-color), #224abe);
            color: white;
            padding: 1.5rem;
            text-align: center;
            border-radius: 1rem 1rem 0 0;
        }
        
        .register-body {
            padding: 1.5rem;
        }
        
        .form-section {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 4px solid var(--primary-color);
        }
        
        .btn-register {
            background: linear-gradient(45deg, var(--primary-color), #224abe);
            border: none;
            padding: 0.75rem 2rem;
            font-weight: 600;
        }
        
        .navigation-links {
            text-align: center;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }

        .container {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            width: 100%;
            max-width: 600px;
        }
        
        .php-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 1rem;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-card">
            <div class="register-header">
                <h2><i class="fas fa-user-shield me-2"></i>Admin Registration</h2>
                <p class="mb-0">Create a new administrator account</p>
            </div>
            
            <div class="register-body">
                <?php if (!empty($errors)): ?>
                    <div class="php-error">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        <strong>Please fix the following issues:</strong>
                        <ul class="mb-0 mt-2">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="form-section">
                        <h5 class="mb-3"><i class="fas fa-user-circle me-2"></i>Account Information</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Username *</label>
                                <input type="text" name="username" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                                       required placeholder="Choose a username">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Email Address *</label>
                                <input type="email" name="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                                       required placeholder="your@email.com">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Phone Number *</label>
                                <input type="tel" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                                       required placeholder="+255 XXX XXX XXX">
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Department *</label>
                                <input type="text" name="department" class="form-control" 
                                       value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>" 
                                       required placeholder="Your department">
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-section">
                        <h5 class="mb-3"><i class="fas fa-lock me-2"></i>Security Information</h5>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Password *</label>
                                <input type="password" name="password" id="password" 
                                       class="form-control" required placeholder="Create a password">
                                <div class="form-text">Minimum 6 characters</div>
                            </div>
                            
                            <div class="col-md-6">
                                <label class="form-label">Confirm Password *</label>
                                <input type="password" name="confirm_password" id="confirmPassword" 
                                       class="form-control" required placeholder="Confirm your password">
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-grid">
                        <button type="submit" class="btn btn-register btn-lg">
                            <i class="fas fa-user-plus me-2"></i>Register as Admin
                        </button>
                    </div>
                </form>
                
                <div class="navigation-links">
                    <p class="mb-2">Already have an account? 
                        <a href="admin_login.php" class="text-decoration-none fw-bold">Login here</a>
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Password confirmation check
        document.querySelector('form').addEventListener('submit', function(e) {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            if (password.length < 6) {
                alert('Password must be at least 6 characters long.');
                e.preventDefault();
                return;
            }
            
            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                e.preventDefault();
                return;
            }
        });
    </script>
</body>
</html>

<?php
// Close database connection at the very end
if (isset($admin_conn)) {
    $admin_conn->close();
}