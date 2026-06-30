<?php
session_start();
require 'admin_database.php';

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input
    $email = $admin_conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($email) || empty($password)) {
        $errors[] = "Please fill in all fields.";
    }

    if (empty($errors)) {
        // First, ensure admins table exists with password column
        create_admin_tables($admin_conn);
        
        // Check if admin exists using prepared statement - USING ADMINS TABLE PASSWORD
        $stmt = $admin_conn->prepare("SELECT id, user_id, username, email, password, department 
                                     FROM admins 
                                     WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                
                // Verify password FROM ADMINS TABLE
                if (password_verify($password, $admin['password'])) {
                    // Successful login - set session
                    $_SESSION['user_id'] = $admin['user_id'];
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['full_name'] = $admin['username'];
                    $_SESSION['email'] = $admin['email'];
                    $_SESSION['department'] = $admin['department'];
                    $_SESSION['role'] = 'admin';
                    $_SESSION['login_time'] = time();
                    
                    // Log the login activity
                    log_admin_action($admin_conn, $admin['id'], $admin['email'], "Admin Login", "Admin logged in successfully");
                    
                    // Redirect to dashboard
                    header("Location: admin_dashboard.php");
                    exit;
                } else {
                    $errors[] = "Invalid email or password.";
                }
            } else {
                $errors[] = "Invalid email or password.";
            }
            $stmt->close();
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }
}

/**
 * Create admin tables if they don't exist (with password column)
 */
function create_admin_tables($conn) {
    try {
        // Create admins table if not exists WITH PASSWORD COLUMN
        $admins_table = "CREATE TABLE IF NOT EXISTS admins (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(100) UNIQUE NOT NULL,
            email VARCHAR(255) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL,
            department VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            INDEX username_idx (username),
            INDEX email_idx (email)
        )";
        $conn->query($admins_table);
        
        // Create audit_log table if not exists
        $audit_table = "CREATE TABLE IF NOT EXISTS audit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT,
            user_email VARCHAR(255),
            action VARCHAR(255) NOT NULL,
            description TEXT,
            ip_address VARCHAR(45),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX user_id_idx (user_id),
            INDEX action_idx (action)
        )";
        $conn->query($audit_table);
        
    } catch (Exception $e) {
        error_log("Admin table creation error: " . $e->getMessage());
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

// Close database connection
$admin_conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .login-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .login-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .links-container {
            text-align: center;
            margin-top: 1.5rem;
        }
        .links-container a {
            color: #667eea;
            text-decoration: none;
            display: block;
            margin-bottom: 0.5rem;
        }
        .links-container a:hover {
            text-decoration: underline;
            color: #764ba2;
        }
        .php-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 1rem;
            border-radius: 5px;
            text-align: center;
            animation: fadeOut 3s ease-in-out forwards;
            animation-delay: 3s;
        }
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; display: none; }
        }
        .message {
            padding: 10px;
            margin-bottom: 1rem;
            border-radius: 5px;
            text-align: center;
            display: none;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="login-container d-flex align-items-center">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5">
                    <div class="card login-card">
                        <div class="card-body p-5">
                            <div class="text-center mb-4">
                                <i class="fas fa-user-shield fa-3x text-primary mb-3"></i>
                                <h2 class="card-title">Admin Login</h2>
                                <p class="text-muted">Sign in to your administrator account</p>
                            </div>
                            
                            <!-- PHP Error Display -->
                            <?php if (!empty($errors)): ?>
                                <div class="php-error">
                                    <?php foreach ($errors as $error): ?>
                                        <div><?php echo htmlspecialchars($error); ?></div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Success Message from Registration -->
                            <?php if (isset($_SESSION['flash_message'])): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php 
                                        echo htmlspecialchars($_SESSION['flash_message']); 
                                        unset($_SESSION['flash_message']);
                                        unset($_SESSION['flash_type']);
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div id="message" class="message"></div>
                            
                            <form id="loginForm" method="POST" action="">
                                <div class="mb-3">
                                    <label class="form-label">Email Address</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                        <input type="email" name="email" class="form-control" placeholder="Enter your email" required 
                                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>
                                </div>
                                
                                <div class="mb-4">
                                    <label class="form-label">Password</label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                                        <button class="btn btn-outline-secondary toggle-password" type="button">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-grid">
                                    <button type="submit" class="btn btn-primary btn-lg">
                                        <i class="fas fa-sign-in-alt me-2"></i>Login
                                    </button>
                                </div>
                            </form>

                            <div class="links-container">
                                <a href="admin_forgot_password.php">Forgot Password?</a>
                                <p>Don't have an account? <a href="admin_register.php">Register here</a></p>
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
        $(document).ready(function() {
            // Auto-hide PHP errors after 3 seconds
            setTimeout(() => {
                $('.php-error').fadeOut(300, function() {
                    $(this).hide();
                });
            }, 3000);

            // Auto-hide success messages after 3 seconds
            setTimeout(() => {
                $('.alert-success').fadeOut(300, function() {
                    $(this).hide();
                });
            }, 3000);

            // Toggle password visibility
            $('.toggle-password').on('click', function() {
                const passwordInput = $(this).closest('.input-group').find('input');
                const type = passwordInput.attr('type') === 'password' ? 'text' : 'password';
                passwordInput.attr('type', type);
                $(this).find('i').toggleClass('fa-eye fa-eye-slash');
            });

            // Form validation
            $('#loginForm').on('submit', function(e) {
                const email = $('input[name="email"]').val().trim();
                const password = $('input[name="password"]').val();
                
                if (!email || !password) {
                    showMessage('Please fill in all fields', 'error');
                    e.preventDefault();
                    return false;
                }
                
                // Email validation
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    showMessage('Please enter a valid email address', 'error');
                    e.preventDefault();
                    return false;
                }
                
                showMessage('Logging in...', 'success');
            });

            function showMessage(text, type) {
                const messageDiv = $('#message');
                messageDiv.text(text);
                messageDiv.removeClass('success error').addClass(type);
                messageDiv.show();
                
                // Show for only 3 seconds
                setTimeout(() => {
                    messageDiv.fadeOut(300);
                }, 3000);
            }

            // Check URL parameters for messages
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('registered') === 'success') {
                showMessage('Registration successful! Please login.', 'success');
            }
            if (urlParams.get('logout') === 'success') {
                showMessage('You have been logged out successfully.', 'success');
            }
            if (urlParams.get('session') === 'expired') {
                showMessage('Your session has expired. Please login again.', 'error');
            }
        });
    </script>
</body>
</html>