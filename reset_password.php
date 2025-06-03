<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Get the token from URL
$token = $_GET['token'] ?? '';
$error_message = '';
$success_message = '';

// Check if token exists
if (empty($token)) {
    $error_message = "No reset token provided. Please request a new password reset.";
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error_message) {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $form_token = $_POST['token'] ?? '';
    
    // Basic validation
    if (empty($new_password)) {
        $error_message = "Please enter a new password.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif (empty($confirm_password)) {
        $error_message = "Please confirm your password.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif ($form_token !== $token) {
        $error_message = "Invalid form submission.";
    } else {
        // Try to reset the password
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            // First, check if the token is valid
            $token_query = "SELECT rt.*, u.email, u.name 
                           FROM reset_tokens rt 
                           JOIN users u ON rt.user_id = u.id 
                           WHERE rt.token = :token 
                           AND rt.used = 0 
                           AND rt.expires_at > NOW() 
                           AND u.status = 'active'";
            
            $stmt = $db->prepare($token_query);
            $stmt->bindParam(':token', $token);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
                $user_id = $token_data['user_id'];
                
                // Hash the new password
                $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                
                // Start transaction
                $db->beginTransaction();
                
                // Update user password
                $update_query = "UPDATE users SET password = :password WHERE id = :id";
                $update_stmt = $db->prepare($update_query);
                $update_stmt->bindParam(':password', $hashed_password);
                $update_stmt->bindParam(':id', $user_id);
                $update_success = $update_stmt->execute();
                
                if ($update_success) {
                    // Mark token as used
                    $token_update = "UPDATE reset_tokens SET used = 1 WHERE token = :token";
                    $token_stmt = $db->prepare($token_update);
                    $token_stmt->bindParam(':token', $token);
                    $token_stmt->execute();
                    
                    // Commit transaction
                    $db->commit();
                    
                    $success_message = "Password has been reset successfully! You can now log in with your new password.";
                } else {
                    $db->rollback();
                    $error_message = "Failed to update password. Please try again.";
                }
            } else {
                $error_message = "Invalid or expired reset token. Please request a new password reset.";
            }
            
        } catch (Exception $e) {
            $db->rollback();
            error_log("Password reset error: " . $e->getMessage());
            $error_message = "System error. Please try again later.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduHive - Reset Password</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Top Logo */
        .top-logo {
            position: fixed;
            top: 30px;
            left: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 1000;
        }

        .logo-text {
            font-size: 32px;
            font-weight: 600;
            color: #333;
            letter-spacing: -1px;
        }

        /* Main Container */
        .reset-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .reset-content {
            width: 100%;
            max-width: 500px;
            text-align: center;
        }

        /* Page Title */
        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 40px;
            letter-spacing: 2px;
        }

        /* Message Styles */
        .message {
            padding: 15px 20px;
            margin-bottom: 30px;
            border-radius: 12px;
            font-weight: 500;
        }

        .message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        /* Form Styling */
        .reset-form {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }

        .form-group {
            margin-bottom: 25px;
            text-align: left;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #333;
        }

        .form-input {
            width: 100%;
            padding: 15px 20px;
            font-size: 16px;
            border: 2px solid #ddd;
            border-radius: 10px;
            outline: none;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            border-color: #4A90A4;
            box-shadow: 0 0 0 3px rgba(74, 144, 164, 0.1);
        }

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #C4A484, #B8956A);
            color: white;
            font-size: 16px;
            font-weight: 600;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #B8956A, #A6845C);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(196, 164, 132, 0.4);
        }

        .submit-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Success Actions */
        .success-actions {
            margin-top: 30px;
        }

        .login-btn {
            display: inline-block;
            padding: 12px 30px;
            background: linear-gradient(135deg, #C4A484, #B8956A);
            color: white;
            text-decoration: none;
            border-radius: 25px;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .login-btn:hover {
            background: linear-gradient(135deg, #B8956A, #A6845C);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(196, 164, 132, 0.4);
        }

        /* Footer */
        .footer-links {
            margin-top: 30px;
            text-align: center;
        }

        .footer-links a {
            color: #666;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s ease;
        }

        .footer-links a:hover {
            color: #333;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .top-logo {
                position: static;
                justify-content: center;
                margin-bottom: 30px;
                margin-top: 20px;
            }

            .reset-container {
                padding: 20px;
            }

            .reset-form {
                padding: 30px 20px;
            }

            .page-title {
                font-size: 20px;
                margin-bottom: 30px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Logo -->
    <div class="top-logo">
        <img src="logoo.png" width="60px" alt="">
        <div class="logo-text">EduHive</div>
    </div>

    <!-- Main Reset Container -->
    <div class="reset-container">
        <div class="reset-content">
            <h1 class="page-title">RESET YOUR PASSWORD</h1>
            
            <!-- Show Error Message -->
            <?php if ($error_message): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
                <div class="footer-links">
                    <a href="recovery.php">← Request New Reset Link</a>
                </div>
            <?php endif; ?>
            
            <!-- Show Success Message -->
            <?php if ($success_message): ?>
                <div class="message success">
                    <?php echo htmlspecialchars($success_message); ?>
                </div>
                <div class="success-actions">
                    <a href="login.php" class="login-btn">Go to Login</a>
                </div>
            <?php endif; ?>
            
            <!-- Reset Form (only show if no error and no success) -->
            <?php if (!$error_message && !$success_message): ?>
            <div class="reset-form">
                <form method="POST">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-input" 
                               placeholder="Enter your new password"
                               required 
                               minlength="6">
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input" 
                               placeholder="Confirm your new password"
                               required>
                    </div>
                    
                    <button type="submit" class="submit-btn" id="submitBtn">
                        Reset Password
                    </button>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Footer Links -->
            <div class="footer-links">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        // Simple password matching validation
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const submitBtn = document.getElementById('submitBtn');
            
            if (newPassword && confirmPassword && submitBtn) {
                function checkPasswords() {
                    const newPass = newPassword.value;
                    const confirmPass = confirmPassword.value;
                    
                    if (confirmPass && newPass !== confirmPass) {
                        confirmPassword.style.borderColor = '#dc3545';
                        submitBtn.disabled = true;
                    } else {
                        confirmPassword.style.borderColor = '#ddd';
                        submitBtn.disabled = false;
                    }
                }
                
                confirmPassword.addEventListener('input', checkPasswords);
                newPassword.addEventListener('input', checkPasswords);
                
                // Auto-focus first field
                newPassword.focus();
            }
        });
    </script>
</body>
</html>