<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Redirect if already logged in
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Check if registration is enabled
$registration_enabled = getSetting('registration_enabled', true);
if (!$registration_enabled) {
    setFlashMessage('Registration is currently disabled.', 'error');
    header("Location: login.php");
    exit();
}

// Get flash message if any
$flash_message = getFlashMessage();

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('Security token invalid. Please try again.', 'error');
        header("Location: register.php");
        exit();
    }
    
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $terms_accepted = isset($_POST['terms_accepted']);
    
    $errors = [];
    
    // Validate input
    if (empty($name)) {
        $errors[] = 'Full name is required.';
    } elseif (strlen($name) < 2) {
        $errors[] = 'Name must be at least 2 characters long.';
    } elseif (strlen($name) > 100) {
        $errors[] = 'Name must be less than 100 characters.';
    }
    
    if (empty($email)) {
        $errors[] = 'Email address is required.';
    } elseif (!validateEmail($email)) {
        $errors[] = 'Please enter a valid email address.';
    }
    
    if (empty($password)) {
        $errors[] = 'Password is required.';
    } else {
        $password_validation = validatePassword($password, 6);
        if (!$password_validation['valid']) {
            $errors[] = $password_validation['message'];
        }
    }
    
    if (empty($confirm_password)) {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }
    
    if (!$terms_accepted) {
        $errors[] = 'You must accept the Terms of Service and Privacy Policy.';
    }
    
    // Check if email already exists
    if (empty($errors)) {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $check_query = "SELECT id FROM users WHERE email = :email";
            $stmt = $db->prepare($check_query);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $errors[] = 'An account with this email address already exists.';
            }
        } catch (PDOException $e) {
            error_log("Database error during registration: " . $e->getMessage());
            $errors[] = 'A system error occurred. Please try again later.';
        }
    }
    
    // If no errors, create the user
    if (empty($errors)) {
        try {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $email_verification_required = getSetting('email_verification_required', false);
            
            $user_data = [
                'name' => $name,
                'email' => $email,
                'password' => $hashed_password,
                'email_verified' => !$email_verification_required,
                'role' => 'user',
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            $user_id = $database->insert('users', $user_data);
            
            if ($user_id) {
                // Create initial user progress record
                $database->insert('user_progress', [
                    'user_id' => $user_id,
                    'total_badges' => 0,
                    'total_points' => 0,
                    'last_login' => date('Y-m-d')
                ]);
                
                // Log registration activity
                logActivity($user_id, 'user_registered', 'New user registered: ' . $email);
                
                // Send welcome notification
                createNotification($user_id, 'web', 'Welcome to EduHive!', 'Thank you for joining EduHive. Start by creating your first task or course.');
                
                if ($email_verification_required) {
                    // Send verification email
                    $verification_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+24 hours'));
                    
                    $database->insert('email_tokens', [
                        'user_id' => $user_id,
                        'token' => $verification_token,
                        'type' => 'verification',
                        'expires_at' => $expires_at
                    ]);
                    
                    $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/verify_email.php?token=" . $verification_token;
                    $email_subject = "Verify Your EduHive Account";
                    $email_body = "
                    <html>
                    <body>
                        <h2>Welcome to EduHive, " . htmlspecialchars($name) . "!</h2>
                        <p>Thank you for registering. Please click the link below to verify your email address:</p>
                        <p><a href='" . $verification_link . "' style='background: #8B7355; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Verify Email</a></p>
                        <p>Or copy and paste this link into your browser:</p>
                        <p>" . $verification_link . "</p>
                        <p>This link will expire in 24 hours.</p>
                        <br>
                        <p>Best regards,<br>The EduHive Team</p>
                    </body>
                    </html>
                    ";
                    
                    if (sendEmail($email, $email_subject, $email_body)) {
                        $success_message = 'Registration successful! Please check your email to verify your account.';
                    } else {
                        $success_message = 'Registration successful! However, we could not send the verification email. Please contact support.';
                    }
                    
                    setFlashMessage($success_message, 'success');
                } else {
                    // Auto-login if email verification not required
                    $user_data['id'] = $user_id;
                    if (loginUser($user_data)) {
                        setFlashMessage('Welcome to EduHive, ' . $name . '! Your account has been created successfully.', 'success');
                        
                        if (isAjaxRequest()) {
                            jsonResponse(true, 'Registration successful', [
                                'redirect' => 'dashboard.php'
                            ]);
                        } else {
                            header("Location: dashboard.php");
                            exit();
                        }
                    }
                }
                
                if (isAjaxRequest()) {
                    jsonResponse(true, $success_message ?? 'Registration successful');
                } else {
                    header("Location: login.php");
                    exit();
                }
            } else {
                $errors[] = 'Failed to create account. Please try again.';
            }
            
        } catch (PDOException $e) {
            error_log("Database error during user creation: " . $e->getMessage());
            $errors[] = 'A system error occurred. Please try again later.';
        }
    }
    
    // If we have errors and this is an AJAX request
    if (!empty($errors) && isAjaxRequest()) {
        jsonResponse(false, implode(' ', $errors));
    }
    
    // Set flash message for errors
    if (!empty($errors)) {
        setFlashMessage(implode(' ', $errors), 'error');
        header("Location: register.php");
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduHive - Create Account</title>
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
        .register-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .register-content {
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
        .register-form {
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

        /* Checkbox styling */
        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            text-align: left;
            margin: 10px 0;
        }

        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            margin-top: 2px;
            flex-shrink: 0;
        }

        .checkbox-group label {
            font-size: 14px;
            color: #666;
            cursor: pointer;
            line-height: 1.4;
        }

        .checkbox-group label a {
            color: #4A90A4;
            text-decoration: none;
        }

        .checkbox-group label a:hover {
            text-decoration: underline;
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

        .footer-links span {
            color: #666;
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

            .register-form {
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

            .register-container {
                padding: 20px;
            }

            .register-content {
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

            .checkbox-group {
                font-size: 13px;
            }

            .password-strength {
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

    <!-- Main Register Container -->
    <div class="register-container">
        <div class="register-content">
            <h1 class="page-title">CREATE YOUR ACCOUNT</h1>
            
            <!-- Flash Message -->
            <?php if ($flash_message): ?>
                <div class="flash-message <?php echo htmlspecialchars($flash_message['type']); ?>">
                    <?php echo htmlspecialchars($flash_message['message']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Register Form -->
            <form id="registerForm" class="register-form" method="POST">
                <?php echo getCSRFTokenField(); ?>
                
                <div class="form-group">
                    <input type="text" id="name" name="name" class="form-input" placeholder="Full Name" required autocomplete="name" maxlength="100">
                </div>
                
                <div class="form-group">
                    <input type="email" id="email" name="email" class="form-input" placeholder="Email" required autocomplete="email">
                </div>
                
                <div class="form-group">
                    <input type="password" id="password" name="password" class="form-input" placeholder="Password" required autocomplete="new-password" minlength="6">
                    <div id="passwordStrength" class="password-strength"></div>
                </div>
                
                <div class="form-group">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" placeholder="Confirm Password" required autocomplete="new-password">
                    <div id="passwordMatch" class="password-strength"></div>
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" id="terms_accepted" name="terms_accepted" value="1" required>
                    <label for="terms_accepted">
                        I agree to the <a href="#" target="_blank">Terms of Service</a> and <a href="#" target="_blank">Privacy Policy</a>
                    </label>
                </div>
                
                <button type="submit" id="registerBtn" class="submit-btn">
                    <span class="btn-text">Create Account</span>
                    <div class="spinner"></div>
                </button>
            </form>
            
            <!-- Footer Links -->
            <div class="footer-links">
                <span>Already have an account? </span>
                <a href="login.php">Sign In</a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const registerForm = document.getElementById('registerForm');
            const registerBtn = document.getElementById('registerBtn');
            const passwordField = document.getElementById('password');
            const confirmPasswordField = document.getElementById('confirm_password');
            const passwordStrength = document.getElementById('passwordStrength');
            const passwordMatch = document.getElementById('passwordMatch');
            
            // Password strength checker
            passwordField.addEventListener('input', function() {
                const password = this.value;
                const strength = checkPasswordStrength(password);
                
                passwordStrength.textContent = strength.text;
                passwordStrength.className = `password-strength ${strength.class}`;
                
                // Check password match if confirm field has value
                if (confirmPasswordField.value) {
                    checkPasswordMatch();
                }
            });
            
            // Password match checker
            confirmPasswordField.addEventListener('input', checkPasswordMatch);
            
            function checkPasswordStrength(password) {
                if (password.length === 0) {
                    return { text: '', class: '' };
                }
                
                let score = 0;
                
                // Length check
                if (password.length >= 6) score++;
                if (password.length >= 8) score++;
                
                // Character variety checks
                if (/[a-z]/.test(password)) score++;
                if (/[A-Z]/.test(password)) score++;
                if (/[0-9]/.test(password)) score++;
                if (/[^A-Za-z0-9]/.test(password)) score++;
                
                if (score < 3) {
                    return { text: 'Weak password', class: 'weak' };
                } else if (score < 5) {
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
                    passwordMatch.className = 'password-strength';
                } else if (password === confirmPassword) {
                    passwordMatch.textContent = 'Passwords match';
                    passwordMatch.className = 'password-strength strong';
                } else {
                    passwordMatch.textContent = 'Passwords do not match';
                    passwordMatch.className = 'password-strength weak';
                }
            }
            
            // Form submission
            registerForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate passwords match
                if (passwordField.value !== confirmPasswordField.value) {
                    showMessage('Passwords do not match.', 'error');
                    return;
                }
                
                // Show loading state
                registerBtn.classList.add('loading');
                registerBtn.disabled = true;
                
                // Get form data
                const formData = new FormData(registerForm);
                
                // Submit form via AJAX
                fetch('register.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Success - show success message
                        showMessage(data.message || 'Registration successful!', 'success');
                        
                        // Redirect after delay
                        setTimeout(() => {
                            if (data.data && data.data.redirect) {
                                window.location.href = data.data.redirect;
                            } else {
                                window.location.href = 'login.php';
                            }
                        }, 2000);
                    } else {
                        // Error - show error message
                        showMessage(data.message, 'error');
                        
                        // Reset form state
                        registerBtn.classList.remove('loading');
                        registerBtn.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showMessage('An error occurred. Please try again.', 'error');
                    
                    // Reset form state
                    registerBtn.classList.remove('loading');
                    registerBtn.disabled = false;
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
                const registerContent = document.querySelector('.register-content');
                const form = document.querySelector('.register-form');
                registerContent.insertBefore(messageDiv, form);
                
                // Auto-remove error messages after 7 seconds
                if (type === 'error') {
                    setTimeout(() => {
                        if (messageDiv.parentNode) {
                            messageDiv.remove();
                        }
                    }, 7000);
                }
            }
            
            // Auto-focus name field
            document.getElementById('name').focus();
            
            // Handle Enter key navigation
            const formInputs = document.querySelectorAll('.form-input');
            formInputs.forEach((input, index) => {
                input.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        if (index < formInputs.length - 1) {
                            formInputs[index + 1].focus();
                        } else {
                            registerForm.dispatchEvent(new Event('submit'));
                        }
                    }
                });
            });
            
            // Real-time email validation
            document.getElementById('email').addEventListener('blur', function() {
                const email = this.value;
                if (email && !isValidEmail(email)) {
                    this.style.borderColor = '#dc3545';
                } else {
                    this.style.borderColor = '#333';
                }
            });
            
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
        });
    </script>
</body>
</html>