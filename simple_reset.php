<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Check if user came from recovery page
if (!isset($_SESSION['reset_email']) || !isset($_SESSION['reset_user_id'])) {
    header("Location: recovery.php");
    exit();
}

$email = $_SESSION['reset_email'];
$user_id = $_SESSION['reset_user_id'];
$error_message = '';
$success_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Basic validation
    if (empty($new_password)) {
        $error_message = "Please enter a new password.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif (empty($confirm_password)) {
        $error_message = "Please confirm your password.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        // Try to reset the password
        $database = new Database();
        
        try {
            // Hash the new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update user password
            $success = $database->update('users', 
                ['password' => $hashed_password], 
                'id = :id', 
                [':id' => $user_id]
            );
            
            if ($success) {
                // Clear session data
                unset($_SESSION['reset_email']);
                unset($_SESSION['reset_user_id']);
                
                $success_message = "Password has been reset successfully! You can now log in with your new password.";
            } else {
                $error_message = "Failed to update password. Please try again.";
            }
            
        } catch (Exception $e) {
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
            margin-bottom: 20px;
            letter-spacing: 2px;
        }

        .page-subtitle {
            font-size: 16px;
            color: #666;
            margin-bottom: 40px;
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

        /* Password strength indicator */
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .password-strength.visible {
            opacity: 1;
        }

        .password-strength.weak {
            color: #dc3545;
        }

        .password-strength.medium {
            color: #ffc107;
        }

        .password-strength.strong {
            color: #28a745;
        }

        /* Password match indicator */
        .password-match {
            margin-top: 8px;
            font-size: 12px;
            font-weight: 500;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .password-match.visible {
            opacity: 1;
        }

        .password-match.match {
            color: #28a745;
        }

        .password-match.no-match {
            color: #dc3545;
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
            <h1 class="page-title">SET NEW PASSWORD</h1>
            <p class="page-subtitle">Enter your new password for: <strong><?php echo htmlspecialchars($email); ?></strong></p>
            
            <!-- Show Error Message -->
            <?php if ($error_message): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error_message); ?>
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
            
            <!-- Reset Form (only show if no success) -->
            <?php if (!$success_message): ?>
            <div class="reset-form">
                <form method="POST">
                    <div class="form-group">
                        <label for="new_password">New Password</label>
                        <input type="password" 
                               id="new_password" 
                               name="new_password" 
                               class="form-input" 
                               placeholder="Enter your new password"
                               required 
                               minlength="6">
                        <div id="passwordStrength" class="password-strength"></div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm New Password</label>
                        <input type="password" 
                               id="confirm_password" 
                               name="confirm_password" 
                               class="form-input" 
                               placeholder="Confirm your new password"
                               required>
                        <div id="passwordMatch" class="password-match"></div>
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
        // Password validation and matching
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('new_password');
            const confirmPassword = document.getElementById('confirm_password');
            const passwordStrength = document.getElementById('passwordStrength');
            const passwordMatch = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');
            
            if (newPassword && confirmPassword && submitBtn) {
                // Password strength checker
                newPassword.addEventListener('input', function() {
                    const password = this.value;
                    const strength = checkPasswordStrength(password);
                    
                    if (password.length === 0) {
                        passwordStrength.textContent = '';
                        passwordStrength.className = 'password-strength';
                    } else {
                        passwordStrength.textContent = strength.text;
                        passwordStrength.className = `password-strength visible ${strength.class}`;
                    }
                    
                    // Check password match if confirm field has value
                    if (confirmPassword.value) {
                        checkPasswordMatch();
                    }
                });
                
                // Password match checker
                confirmPassword.addEventListener('input', checkPasswordMatch);
                
                function checkPasswordStrength(password) {
                    if (password.length < 6) {
                        return { text: 'Too short (minimum 6 characters)', class: 'weak' };
                    }
                    
                    let score = 0;
                    
                    // Length check
                    if (password.length >= 8) score++;
                    
                    // Character variety checks
                    if (/[a-z]/.test(password)) score++;
                    if (/[A-Z]/.test(password)) score++;
                    if (/[0-9]/.test(password)) score++;
                    if (/[^A-Za-z0-9]/.test(password)) score++;
                    
                    if (score < 2) {
                        return { text: 'Weak password', class: 'weak' };
                    } else if (score < 4) {
                        return { text: 'Medium strength', class: 'medium' };
                    } else {
                        return { text: 'Strong password', class: 'strong' };
                    }
                }
                
                function checkPasswordMatch() {
                    const password = newPassword.value;
                    const confirmPass = confirmPassword.value;
                    
                    if (confirmPass === '') {
                        passwordMatch.textContent = '';
                        passwordMatch.className = 'password-match';
                        submitBtn.disabled = false;
                    } else if (password === confirmPass) {
                        passwordMatch.textContent = 'Passwords match';
                        passwordMatch.className = 'password-match visible match';
                        submitBtn.disabled = false;
                    } else {
                        passwordMatch.textContent = 'Passwords do not match';
                        passwordMatch.className = 'password-match visible no-match';
                        submitBtn.disabled = true;
                    }
                }
                
                // Auto-focus first field
                newPassword.focus();
            }
        });
    </script>
</body>
</html>