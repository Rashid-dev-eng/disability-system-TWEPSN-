<?php
session_start();

// Check if reset session exists
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_token']) || !isset($_SESSION['user_type'])) {
    header("Location: admin_login.php");
    exit;
}

$errors = [];
$success = '';

if ($_SESSION['user_type'] === 'user') {
    require 'database.php';
    $conn_type = 'user';
} else {
    require 'admin_database.php';
    $conn_type = 'admin';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation based on user type
    if ($_SESSION['user_type'] === 'user') {
        // User PIN validation (4-digit)
        if (empty($new_password)) {
            $errors[] = "Please enter a new PIN.";
        } elseif (strlen($new_password) !== 4 || !ctype_digit($new_password)) {
            $errors[] = "PIN must be a 4-digit number.";
        }
    } else {
        // Admin password validation
        if (empty($new_password)) {
            $errors[] = "Please enter a new password.";
        } elseif (strlen($new_password) < 6) {
            $errors[] = "Password must be at least 6 characters long.";
        }
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $email = $_SESSION['reset_email'];
        $reset_token = $_SESSION['reset_token'];
        
        if ($_SESSION['user_type'] === 'user') {
            // Update user PIN
            $stmt = $conn->prepare("UPDATE users SET pin = ?, reset_token = NULL, token_expiry = NULL WHERE email = ? AND reset_token = ?");
        } else {
            // Update admin password
            $stmt = $admin_conn->prepare("UPDATE admins SET password = ?, reset_token = NULL, token_expiry = NULL WHERE email = ? AND reset_token = ?");
        }
        
        if ($stmt) {
            $stmt->bind_param("sss", $hashed_password, $email, $reset_token);
            if ($stmt->execute()) {
                // Log the action
                if ($_SESSION['user_type'] === 'user') {
                    $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, action, description, ip_address) SELECT id, 'Password Reset', 'User reset their PIN', ? FROM users WHERE email = ?");
                    if ($log_stmt) {
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                        $log_stmt->bind_param("ss", $ip_address, $email);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                } else {
                    $log_stmt = $admin_conn->prepare("INSERT INTO audit_log (user_id, user_email, action, description, ip_address) SELECT id, email, 'Admin Password Reset', 'Admin reset their password', ? FROM admins WHERE email = ?");
                    if ($log_stmt) {
                        $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                        $log_stmt->bind_param("ss", $ip_address, $email);
                        $log_stmt->execute();
                        $log_stmt->close();
                    }
                }
                
                // Clear reset session
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_token']);
                unset($_SESSION['user_type']);
                
                // Redirect to appropriate login with success message
                if ($_SESSION['user_type'] === 'user') {
                    header("Location: login.php?reset=success");
                } else {
                    header("Location: admin_login.php?reset=success");
                }
                exit;
            }
            $stmt->close();
        } else {
            $errors[] = "Database error. Please try again.";
        }
    }
}

// Close database connection
if ($_SESSION['user_type'] === 'user') {
    $conn->close();
} else {
    $admin_conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password</title>
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

        .reset-container {
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

        .instructions {
            text-align: center;
            color: #666;
            margin-bottom: 1.5rem;
            line-height: 1.5;
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

        .change-btn {
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

        .change-btn:hover {
            transform: translateY(-2px);
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
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .php-error {
            animation: fadeOut 3s ease-in-out forwards;
            animation-delay: 3s;
        }

        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; display: none; }
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="logo">
            <h1>Reset <?php echo $_SESSION['user_type'] === 'user' ? 'PIN' : 'Password'; ?></h1>
        </div>
        
        <div class="instructions">
            Enter your new <?php echo $_SESSION['user_type'] === 'user' ? '4-digit PIN' : 'password'; ?> below.
        </div>
        
        <!-- PHP Error Display -->
        <?php if (!empty($errors)): ?>
            <div class="message error php-error">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="form-group">
                <label for="new_password">
                    New <?php echo $_SESSION['user_type'] === 'user' ? 'PIN (4 digits)' : 'Password'; ?>
                </label>
                <input type="password" id="new_password" name="new_password" required 
                       placeholder="<?php echo $_SESSION['user_type'] === 'user' ? 'Enter 4-digit PIN' : 'Enter new password'; ?>"
                       maxlength="<?php echo $_SESSION['user_type'] === 'user' ? '4' : '255'; ?>">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">
                    Confirm <?php echo $_SESSION['user_type'] === 'user' ? 'PIN' : 'Password'; ?>
                </label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       placeholder="<?php echo $_SESSION['user_type'] === 'user' ? 'Confirm 4-digit PIN' : 'Confirm new password'; ?>"
                       maxlength="<?php echo $_SESSION['user_type'] === 'user' ? '4' : '255'; ?>">
            </div>
            
            <button type="submit" class="change-btn">Change <?php echo $_SESSION['user_type'] === 'user' ? 'PIN' : 'Password'; ?></button>
        </form>
    </div>

    <script>
        // Auto-hide PHP errors after 3 seconds
        setTimeout(() => {
            const phpErrors = document.querySelectorAll('.php-error');
            phpErrors.forEach(error => {
                error.style.display = 'none';
            });
        }, 3000);

        // PIN validation for users
        <?php if ($_SESSION['user_type'] === 'user'): ?>
        document.getElementById('new_password').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
        
        document.getElementById('confirm_password').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
        <?php endif; ?>
    </script>
</body>
</html>