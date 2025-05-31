<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Check for remember me cookie
checkRememberMeCookie();

// Get flash message if any
$flash_message = getFlashMessage();

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Security token invalid. Please try again.', 'error');
        header("Location: login.php");
        exit();
    }
    
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember_me = isset($_POST['remember_me']);
    
    // Validate input
    if (empty($email) || empty($password)) {
        setFlashMessage('Please fill in all fields.', 'error');
    } elseif (!validateEmail($email)) {
        setFlashMessage('Please enter a valid email address.', 'error');
    } else {
        // Get user from database
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $query = "SELECT * FROM users WHERE email = :email";
            $stmt = $db->prepare($query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Verify password
                if (password_verify($password, $user['password'])) {
                    // Check if email is verified (if required)
                    $email_verification_required = getSetting('email_verification_required', false);
                    
                    if ($email_verification_required && !$user['email_verified']) {
                        setFlashMessage('Please verify your email address before logging in.', 'warning');
                    } else {
                        // Login successful
                        if (loginUser($user, $remember_me)) {
                            logActivity($user['id'], 'login_success', 'User logged in successfully');
                            
                            // Check if this is an AJAX request
                            if (isAjaxRequest()) {
                                jsonResponse(true, 'Login successful', [
                                    'redirect' => $_SESSION['redirect_after_login'] ?? 'dashboard.php'
                                ]);
                            } else {
                                setFlashMessage('Welcome back, ' . $user['name'] . '!', 'success');
                                redirectAfterLogin();
                            }
                        } else {
                            setFlashMessage('Login failed. Please try again.', 'error');
                            logActivity($user['id'], 'login_failed', 'Login system error');
                        }
                    }
                } else {
                    // Invalid password
                    setFlashMessage('Invalid email or password.', 'error');
                    logActivity($user['id'], 'login_failed', 'Invalid password attempt from IP: ' . getUserIP());
                }
            } else {
                // User not found
                setFlashMessage('Invalid email or password.', 'error');
                
                // Log failed attempt without user ID
                error_log("Failed login attempt for email: $email from IP: " . getUserIP());
            }
            
        } catch (PDOException $e) {
            error_log("Database error during login: " . $e->getMessage());
            setFlashMessage('A system error occurred. Please try again later.', 'error');
        }
    }
    
    // If AJAX request and we reach here, it means login failed
    if (isAjaxRequest()) {
        $flash = getFlashMessage();
        jsonResponse(false, $flash['message'] ?? 'Login failed');
    }
    
    // Redirect to avoid form resubmission
    header("Location: login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduHive - Login</title>
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
        .login-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .login-content {
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

        /* Flash Message */
        .flash-message {
            padding: 15px 20px;
            margin-bottom: 30px;
            border-radius: 12px;
            font-weight: 500;
            animation: slideDown 0.3s ease;
        }

        .flash-message.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .flash-message.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .flash-message.warning {
            background-color: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
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
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 30px;
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
            position: relative;
            overflow: hidden;
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #B8956A, #A6845C);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(196, 164, 132, 0.4);
        }

        .submit-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .submit-btn.loading {
            color: transparent;
        }

        .spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
            display: none;
        }

        .submit-btn.loading .spinner {
            display: block;
        }

        @keyframes spin {
            to {
                transform: translate(-50%, -50%) rotate(360deg);
            }
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

        .separator {
            color: #999;
            font-size: 20px;
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

            .footer-links {
                flex-direction: column;
                gap: 20px;
            }

            .separator {
                display: none;
            }
        }

        @media (max-width: 480px) {
            .top-logo {
                position: static;
                justify-content: center;
                margin-bottom: 30px;
                margin-top: 20px;
            }

            .login-container {
                padding: 20px;
            }

            .login-content {
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

    <!-- Main Login Container -->
    <div class="login-container">
        <div class="login-content">
            <h1 class="page-title">LOG IN TO YOUR ACCOUNT</h1>
            
            <!-- Flash Message -->
            <?php if ($flash_message): ?>
                <div class="flash-message <?php echo htmlspecialchars($flash_message['type']); ?>">
                    <?php echo htmlspecialchars($flash_message['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form id="loginForm" class="login-form" method="POST">
                <?php echo getCSRFTokenField(); ?>
                
                <div class="form-group">
                    <input type="email" id="email" name="email" class="form-input" placeholder="Email" required autocomplete="email">
                </div>
                
                <div class="form-group">
                    <input type="password" id="password" name="password" class="form-input" placeholder="Password" required autocomplete="current-password">
                </div>
                
                <button type="submit" id="loginBtn" class="submit-btn">
                    <span class="btn-text">Sign In</span>
                    <div class="spinner"></div>
                </button>
            </form>
            
            <!-- Footer Links -->
            <div class="footer-links">
                <a href="recovery.php">Can't Log in?</a>
                <span class="separator">•</span>
                <a href="register.php">Create an account</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const loginForm = document.getElementById('loginForm');
            const loginBtn = document.getElementById('loginBtn');
            
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Show loading state
                loginBtn.classList.add('loading');
                loginBtn.disabled = true;
                
                // Get form data
                const formData = new FormData(loginForm);
                
                // Submit form via AJAX
                fetch('login.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Success - show success message and redirect
                        showMessage('Login successful! Redirecting...', 'success');
                        
                        setTimeout(() => {
                            window.location.href = data.data.redirect || 'dashboard.php';
                        }, 1000);
                    } else {
                        // Error - show error message
                        showMessage(data.message, 'error');
                        
                        // Reset form state
                        loginBtn.classList.remove('loading');
                        loginBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred. Please try again.', 'error');
                    
                    // Reset form state
                    loginBtn.classList.remove('loading');
                    loginBtn.disabled = false;
                });
            });
            
            function showMessage(message, type) {
                // Remove existing messages
                const existingMessage = document.querySelector('.flash-message');
                if (existingMessage) {
                    existingMessage.remove();
                }
                
                // Create new message
                const messageDiv = document.createElement('div');
                messageDiv.className = `flash-message ${type}`;
                messageDiv.textContent = message;
                
                // Insert before form
                const loginContent = document.querySelector('.login-content');
                const form = document.querySelector('.login-form');
                loginContent.insertBefore(messageDiv, form);
                
                // Auto-remove error messages after 5 seconds
                if (type === 'error') {
                    setTimeout(() => {
                        if (messageDiv.parentNode) {
                            messageDiv.remove();
                        }
                    }, 5000);
                }
            }
            
            // Auto-focus email field
            document.getElementById('email').focus();
            
            // Handle Enter key navigation
            document.getElementById('email').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.getElementById('password').focus();
                }
            });
            
            document.getElementById('password').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    loginForm.dispatchEvent(new Event('submit'));
                }
            });
        });
    </script>
</body>
</html>