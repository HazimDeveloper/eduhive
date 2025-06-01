<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

$token = $_GET['token'] ?? '';
$error_message = '';
$success_message = '';

// Validate token on page load
if (empty($token)) {
    $error_message = "No reset token provided.";
} else {
    $token_validation = validateResetToken($token);
    if (!$token_validation['valid']) {
        $error_message = $token_validation['message'];
    }
}

// Handle password reset form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error_message) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $submitted_token = $_POST['token'] ?? '';
    
    // Basic validation
    if (empty($new_password)) {
        $error_message = "Please enter a new password.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif (empty($confirm_password)) {
        $error_message = "Please confirm your password.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif ($submitted_token !== $token) {
        $error_message = "Invalid token.";
    } else {
        // Reset password
        $result = resetPassword($submitted_token, $new_password);
        
        if ($result['success']) {
            $success_message = $result['message'];
        } else {
            $error_message = $result['message'];
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

        /* Top Left Logo */
        .top-logo {
            position: fixed;
            top: 30px;
            left: 30px;
            display: flex;
            align-items: center;
            gap: 15px;
            z-index: 1000;
        }

        .logo-circle {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #4A90A4, #357A8C);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 4px 12px rgba(74, 144, 164, 0.3);
        }

        .graduation-cap {
            font-size: 24px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
        }

        .location-pin {
            font-size: 14px;
            position: absolute;
            bottom: -2px;
            right: -2px;
            background: #FF9800;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid white;
            color: white;
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
            margin-bottom: 60px;
            letter-spacing: 2px;
        }

        /* Message Styles */
        .message {
            padding: 15px 20px;
            margin-bottom: 30px;
            border-radius: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
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

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Form Styling */
        .reset-form {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }

        .form-group {
            position: relative;
        }

        .form-input {
            width: 100%;
            padding: 20px 25px;
            font-size: 18px;
            border: 3px solid #333;
            border-radius: 50px;
            outline: none;
            background: #f8f9fa;
            color: #333;
            transition: all 0.3s ease;
        }

        .form-input:focus {
            border-color: #4A90A4;
            background: white;
            box-shadow: 0 0 0 3px rgba(74, 144, 164, 0.1);
        }

        .form-input::placeholder {
            color: #666;
            font-weight: 500;
        }

        /* Password strength indicator */
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
            font-weight: 500;
            text-align: left;
            padding-left: 25px;
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
            text-align: left;
            padding-left: 25px;
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

        /* Submit Button */
        .submit-btn {
            width: 100%;
            padding: 18px;
            font-size: 18px;
            font-weight: 600;
            background: linear-gradient(135deg, #C4A484, #B8956A);
            color: white;
            border: none;
            border-radius: 50px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #B8956A, #A6845C);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(196, 164, 132, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
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
            box-shadow: 0 8px 25px rgba(196, 164, 132, 0.4);
        }

        /* Footer Links */
        .footer-links {
            margin-top: 50px;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            font-size: 16px;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .top-logo {
                top: 20px;
                left: 20px;
                gap: 10px;
            }

            .logo-circle {
                width: 50px;
                height: 50px;
            }

            .graduation-cap {
                font-size: 20px;
            }

            .location-pin {
                font-size: 12px;
                width: 20px;
                height: 20px;
            }

            .logo-text {
                font-size: 28px;
            }

            .page-title {
                font-size: 20px;
                margin-bottom: 40px;
            }

            .form-input {
                padding: 18px 22px;
                font-size: 16px;
            }

            .submit-btn {
                padding: 16px;
                font-size: 16px;
            }

            .reset-form {
                gap: 20px;
            }
        }

        @media (max-width: 480px) {
            .top-logo {
                position: static;
                justify-content: center;
                margin-bottom: 30px;
                margin-top: 20px;
            }

            .reset-container {
                padding: 20px;
            }

            .reset-content {
                max-width: 100%;
            }

            .page-title {
                font-size: 18px;
                margin-bottom: 30px;
            }

            .form-input {
                padding: 16px 20px;
                font-size: 16px;
            }

            .submit-btn {
                padding: 14px;
                font-size: 16px;
            }

            .footer-links {
                margin-top: 40px;
                font-size: 14px;
            }

            .password-strength,
            .password-match {
                font-size: 11px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Left Logo -->
    <div class="top-logo">
        <div class="logo-circle">
            <div class="graduation-cap">🎓</div>
            <div class="location-pin">📍</div>
        </div>
        <div class="logo-text">EduHive</div>
    </div>

    <!-- Main Reset Container -->
    <div class="reset-container">
        <div class="reset-content">
            <h1 class="page-title">RESET YOUR PASSWORD</h1>
            
            <!-- Show Messages -->
            <?php if ($error_message): ?>
                <div class="message error">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
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
            <form class="reset-form" method="POST">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                
                <div class="form-group">
                    <input type="password" name="password" id="password" class="form-input" placeholder="New Password" required autocomplete="new-password" minlength="6">
                    <div id="passwordStrength" class="password-strength"></div>
                </div>
                
                <div class="form-group">
                    <input type="password" name="confirm_password" id="confirm_password" class="form-input" placeholder="Confirm New Password" required autocomplete="new-password">
                    <div id="passwordMatch" class="password-match"></div>
                </div>
                
                <button type="submit" class="submit-btn" id="submitBtn">
                    Reset Password
                </button>
            </form>
            <?php endif; ?>
            
            <!-- Footer Links -->
            <div class="footer-links">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');
            const passwordStrength = document.getElementById('passwordStrength');
            const passwordMatch = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');
            
            if (passwordField && confirmPasswordField) {
                // Password strength checker
                passwordField.addEventListener('input', function() {
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
                    if (confirmPasswordField.value) {
                        checkPasswordMatch();
                    }
                    
                    updateSubmitButton();
                });
                
                // Password match checker
                confirmPasswordField.addEventListener('input', checkPasswordMatch);
                
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
                    const password = passwordField.value;
                    const confirmPassword = confirmPasswordField.value;
                    
                    if (confirmPassword === '') {
                        passwordMatch.textContent = '';
                        passwordMatch.className = 'password-match';
                    } else if (password === confirmPassword) {
                        passwordMatch.textContent = 'Passwords match';
                        passwordMatch.className = 'password-match visible match';
                    } else {
                        passwordMatch.textContent = 'Passwords do not match';
                        passwordMatch.className = 'password-match visible no-match';
                    }
                    
                    updateSubmitButton();
                }
                
                function updateSubmitButton() {
                    const password = passwordField.value;
                    const confirmPassword = confirmPasswordField.value;
                    const isValid = password.length >= 6 && password === confirmPassword;
                    
                    submitBtn.disabled = !isValid;
                }
                
                // Auto-focus password field
                passwordField.focus();
            }
        });
    </script>
</body>
</html>