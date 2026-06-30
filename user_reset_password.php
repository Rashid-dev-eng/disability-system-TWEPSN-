<?php
session_start();

// Check if reset session exists
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_token']) || !isset($_SESSION['user_type'])) {
    header("Location: login.php");
    exit;
}

$errors = [];
$success = '';

// Only user reset functionality
if ($_SESSION['user_type'] === 'user') {
    require 'database.php';
} else {
    // If admin somehow gets here, redirect to admin login
    header("Location: admin/admin_login.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // User PIN validation (4-digit)
    if (empty($new_password)) {
        $errors[] = "Please enter a new PIN.";
    } elseif (strlen($new_password) !== 4 || !ctype_digit($new_password)) {
        $errors[] = "PIN must be a 4-digit number.";
    }
    
    if ($new_password !== $confirm_password) {
        $errors[] = "PINs do not match.";
    }
    
    if (empty($errors)) {
        $email = $_SESSION['reset_email'];
        $reset_token = $_SESSION['reset_token'];
        
        // First, verify the reset token is valid and not expired
        $check_stmt = $conn->prepare("SELECT id, token_expiry FROM users WHERE email = ? AND reset_token = ?");
        if ($check_stmt) {
            $check_stmt->bind_param("ss", $email, $reset_token);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows === 1) {
                $user = $check_result->fetch_assoc();
                
                // Check if token is not expired
                if (strtotime($user['token_expiry']) > time()) {
                    // Hash the PIN before storing
                    $hashed_pin = password_hash($new_password, PASSWORD_DEFAULT);
                    
                    // Update user PIN with hashed version
                    $update_stmt = $conn->prepare("UPDATE users SET pin = ?, reset_token = NULL, token_expiry = NULL WHERE email = ? AND reset_token = ?");
                    
                    if ($update_stmt) {
                        $update_stmt->bind_param("sss", $hashed_pin, $email, $reset_token);
                        if ($update_stmt->execute()) {
                            // Check if update was successful
                            if ($update_stmt->affected_rows > 0) {
                                // Store user_type before unsetting session
                                $user_type = $_SESSION['user_type'];
                                
                                // Log the action
                                $log_stmt = $conn->prepare("INSERT INTO user_activity_log (user_id, action, description, ip_address) SELECT id, 'PIN Reset', 'User reset their PIN successfully', ? FROM users WHERE email = ?");
                                if ($log_stmt) {
                                    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
                                    $log_stmt->bind_param("ss", $ip_address, $email);
                                    $log_stmt->execute();
                                    $log_stmt->close();
                                }
                                
                                // Clear reset session
                                unset($_SESSION['reset_email']);
                                unset($_SESSION['reset_token']);
                                unset($_SESSION['user_type']);
                                
                                // Set success message in session for login page
                                $_SESSION['reset_success'] = "PIN has been reset successfully! Please login with your new PIN.";
                                
                                // Redirect to login page
                                header("Location: login.php");
                                exit;
                            } else {
                                $errors[] = "Failed to update PIN. Please try again.";
                            }
                        } else {
                            $errors[] = "Database error: " . $update_stmt->error;
                        }
                        $update_stmt->close();
                    } else {
                        $errors[] = "Database preparation error.";
                    }
                } else {
                    $errors[] = "Reset token has expired. Please request a new reset link.";
                }
            } else {
                $errors[] = "Invalid reset token. Please request a new reset link.";
            }
            $check_stmt->close();
        } else {
            $errors[] = "Database error. Please try again.";
        }
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
    <title>Reset PIN</title>
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
    <div class="reset-container">
        <div class="logo">
            <h1>Reset PIN</h1>
        </div>
        
        <div class="instructions">
            Enter your new 4-digit PIN below.
        </div>
        
        <!-- PHP Error Display -->
        <?php if (!empty($errors)): ?>
            <div class="message error php-error">
                <?php foreach ($errors as $error): ?>
                    <div><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="user_reset_password.php">
            <div class="form-group">
                <label for="new_password">New PIN (4 digits)</label>
                <input type="password" id="new_password" name="new_password" required 
                       placeholder="Enter 4-digit PIN" maxlength="4" pattern="[0-9]{4}" title="Please enter exactly 4 digits">
            </div>
            
            <div class="form-group">
                <label for="confirm_password">Confirm PIN</label>
                <input type="password" id="confirm_password" name="confirm_password" required 
                       placeholder="Confirm 4-digit PIN" maxlength="4" pattern="[0-9]{4}" title="Please enter exactly 4 digits">
            </div>
            
            <button type="submit" class="change-btn">Change PIN</button>
        </form>
        
        <div style="text-align: center; margin-top: 1rem;">
            <a href="login.php" style="color: #667eea; text-decoration: none;">← Back to Login</a>
        </div>
    </div>

    <script>
        // Auto-hide PHP errors after 5 seconds
        setTimeout(() => {
            const phpErrors = document.querySelectorAll('.php-error');
            phpErrors.forEach(error => {
                error.style.display = 'none';
            });
        }, 5000);

        // PIN validation for users - only allow numbers
        document.getElementById('new_password').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
        
        document.getElementById('confirm_password').addEventListener('input', function(e) {
            this.value = this.value.replace(/\D/g, '').slice(0, 4);
        });
    </script>
</body>
</html>