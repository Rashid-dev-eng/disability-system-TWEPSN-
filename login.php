<?php
session_start();
require 'database.php';

// Check for reset success message at the VERY TOP
$reset_success = '';
if (isset($_SESSION['reset_success'])) {
    $reset_success = $_SESSION['reset_success'];
    unset($_SESSION['reset_success']); // Clear the message after displaying
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect and sanitize input
    $email = $conn->real_escape_string($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Basic validation
    if (empty($email) || empty($password)) {
        $errors[] = "Please fill in all fields.";
    }

    if (empty($errors)) {
        // Check if user exists using prepared statement - using email instead of phone
        $stmt = $conn->prepare("SELECT id, full_name, email, phone, pin, role FROM users WHERE email = ?");
        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $user = $result->fetch_assoc();
                
                // Verify PIN using password_verify for hashed PIN
                if (password_verify($password, $user['pin'])) {
                    // Successful login - set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['phone'] = $user['phone'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['login_time'] = time();
                    
                    // Log the login activity
                    log_user_action($conn, $user['id'], $user['email'], "User Login", "User logged in successfully");
                    
                    // Redirect to dashboard
                    header("Location: user_dashboard.php");
                    exit;
                } else {
                    $errors[] = "Invalid email address or PIN.";
                }
            } else {
                $errors[] = "Invalid email address or PIN.";
            }
            $stmt->close();
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }
}

/**
 * Log user actions for audit trail
 */
function log_user_action($conn, $user_id, $user_email, $action, $description) {
    $log_sql = "INSERT INTO user_activity_log (user_id, action, description, ip_address) VALUES (?, ?, ?, ?)";
    $log_stmt = $conn->prepare($log_sql);
    if ($log_stmt) {
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $log_stmt->bind_param("isss", $user_id, $action, $description, $ip_address);
        $log_stmt->execute();
        $log_stmt->close();
    }
}

// Close database connection
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PWD System</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }

        .logo {
            text-align: center;
            margin-bottom: 2rem;
        }

        .logo h1 {
            color: #333;
            font-size: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #555;
            font-weight: bold;
        }

        .form-group input {
            width: 100%;
            padding: 12px;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            transition: border-color 0.3s;
        }

        .form-group input:focus {
            outline: none;
            border-color: #667eea;
        }

        .login-btn {
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            transition: transform 0.2s;
        }

        .login-btn:hover {
            transform: translateY(-2px);
        }

        .links-container {
            text-align: center;
            margin-top: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .links-container a {
            color: #667eea;
            text-decoration: none;
            transition: color 0.3s;
        }

        .links-container a:hover {
            text-decoration: underline;
            color: #764ba2;
        }

        .message {
            padding: 10px;
            margin-bottom: 1rem;
            border-radius: 5px;
            text-align: center;
        }

        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block !important;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block !important;
        }

        .register-link {
            margin-top: 0.5rem;
            font-weight: bold;
        }

        .php-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            padding: 10px;
            margin-bottom: 1rem;
            border-radius: 5px;
            text-align: center;
            animation: fadeOut 5s ease-in-out forwards;
        }

        .php-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            padding: 10px;
            margin-bottom: 1rem;
            border-radius: 5px;
            text-align: center;
            animation: fadeOut 5s ease-in-out forwards;
        }

        @keyframes fadeOut {
            0% { opacity: 1; display: block; }
            80% { opacity: 1; }
            100% { opacity: 0; display: none; }
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <h1>Welcome</h1>
        </div>
        
        <!-- Success Message from Reset -->
        <?php if (!empty($reset_success)): ?>
            <div class="php-success">
                <?php echo htmlspecialchars($reset_success); ?>
            </div>
        <?php endif; ?>
        
        <!-- PHP Error Display -->
        <?php if (!empty($errors)): ?>
            <div class="php-error">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <div id="message" class="message"></div>
        
        <form id="loginForm" method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" required placeholder="Enter your email address" 
                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="password">PIN</label>
                <input type="password" id="password" name="password" required placeholder="Enter your 4-digit PIN" maxlength="4">
            </div>
            
            <button type="submit" class="login-btn">Login</button>
        </form>
        
        <div class="links-container">
            <a href="forgot_password.php">Forgot PIN?</a>
            <p class="register-link">Don't have an account?<a href="register.php"> Register here</a></p>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const messageDiv = document.getElementById('message');
            
            // Simple validation
            if (!email || !password) {
                showMessage('Please fill in all fields', 'error');
                e.preventDefault();
                return;
            }
            
            // PIN validation
            if (password.length !== 4 || !/^\d+$/.test(password)) {
                showMessage('PIN must be a 4-digit number', 'error');
                e.preventDefault();
                return;
            }
            
            // Email validation
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            if (!emailRegex.test(email)) {
                showMessage('Please enter a valid email address', 'error');
                e.preventDefault();
                return;
            }
            
            showMessage('Logging in...', 'success');
        });
        
        function showMessage(text, type) {
            const messageDiv = document.getElementById('message');
            messageDiv.textContent = text;
            messageDiv.className = 'message ' + type;
            messageDiv.style.display = 'block';
        }
        
        // Auto-hide PHP errors after 5 seconds
        setTimeout(() => {
            const phpErrors = document.querySelectorAll('.php-error, .php-success');
            phpErrors.forEach(error => {
                error.style.display = 'none';
            });
        }, 5000);
    </script>
</body>
</html>