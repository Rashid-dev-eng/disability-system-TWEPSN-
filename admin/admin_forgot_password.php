    <?php
    session_start();
    require 'admin_database.php';

    $errors = [];
    $success = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $email = $admin_conn->real_escape_string($_POST['email'] ?? '');
        
        if (empty($email)) {
            $errors[] = "Please enter your email address.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Please enter a valid email address.";
        }
        
        if (empty($errors)) {
            // Check if email exists in admins table
            $stmt = $admin_conn->prepare("SELECT id, username FROM admins WHERE email = ?");
            if ($stmt) {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows === 1) {
                    $admin = $result->fetch_assoc();
                    
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $token_expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset token in database
                    // Current problematic line:
                    $update_stmt = $admin_conn->prepare("UPDATE admins SET reset_token = ?, token_expiry = ? WHERE id = ?");

    // Make sure your admins table has these columns first
                    if ($update_stmt) {
                        $update_stmt->bind_param("ssi", $reset_token, $token_expiry, $admin['id']);
                        if ($update_stmt->execute()) {
                            // In real implementation, send email with reset link
                            $_SESSION['reset_email'] = $email;
                            $_SESSION['reset_token'] = $reset_token;
                            $_SESSION['user_type'] = 'admin';
                            
                            header("Location: reset_password.php");
                            exit;
                        }
                        $update_stmt->close();
                    }
                } else {
                    $errors[] = "No admin found with this email address.";
                }
                $stmt->close();
            } else {
                $errors[] = "Database error. Please try again.";
            }
        }
    }

    $admin_conn->close();
    ?>

    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Forgot Password</title>
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

            .forgot-container {
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

            .reset-btn {
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

            .reset-btn:hover {
                transform: translateY(-2px);
            }

            .back-to-login {
                text-align: center;
                margin-top: 1rem;
            }

            .back-to-login a {
                color: #667eea;
                text-decoration: none;
            }

            .back-to-login a:hover {
                text-decoration: underline;
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
        <div class="forgot-container">
            <div class="logo">
                <h1>Reset Password</h1>
            </div>
            
            <div class="instructions">
                Enter your email address and we'll send you a link to reset your password.
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
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" required 
                        placeholder="Enter your registered email"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="reset-btn">Send Reset Link</button>
            </form>
            
            <div class="back-to-login">
                <a href="admin_login.php">← Back to Login</a>
            </div>
        </div>

        <script>
            // Auto-hide PHP errors after 3 seconds
            setTimeout(() => {
                const phpErrors = document.querySelectorAll('.php-error');
                phpErrors.forEach(error => {
                    error.style.display = 'none';
                });
            }, 3000);
        </script>
    </body>
    </html>