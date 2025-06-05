<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// If user is already logged in, redirect to dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit();
}

// Get any existing message
$message = getMessage();

// Handle recovery form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    
    // Basic validation
    if (empty($email)) {
        setMessage('Please enter your email address.', 'error');
    } elseif (!isValidEmail($email)) {
        setMessage('Please enter a valid email address.', 'error');
    } else {
        // Check if user exists
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            $user_query = "SELECT id, name FROM users WHERE email = :email AND status = 'active'";
            $user = $database->queryRow($user_query, [':email' => $email]);
            
            if ($user) {
                // Store email in session for password reset
                $_SESSION['reset_email'] = $email;
                $_SESSION['reset_user_id'] = $user['id'];
                
                // Redirect to simple reset page
                header("Location: simple_reset.php");
                exit();
            } else {
                // Don't reveal if email exists or not for security
                setMessage('If this email exists in our system, you can now reset your password.', 'success');
            }
            
        } catch (Exception $e) {
            error_log("Password recovery error: " . $e->getMessage());
            setMessage('System error. Please try again later.', 'error');
        }
    }
    
    // Redirect to avoid form resubmission
    header("Location: recovery.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduHive - Password Recovery</title>
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

        .logo-text {
            font-size: 32px;
            font-weight: 600;
            color: #333;
            letter-spacing: -1px;
        }

        /* Main Container */
        .recovery-container {
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 40px 20px;
        }

        .recovery-content {
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

        .message.info {
            background-color: #d1ecf1;
            color: #0c5460;
            border: 1px solid #bee5eb;
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
        .recovery-form {
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
        }

        .submit-btn:hover {
            background: linear-gradient(135deg, #B8956A, #A6845C);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(196, 164, 132, 0.4);
        }

        .submit-btn:active {
            transform: translateY(0);
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

            .recovery-container {
                padding: 20px;
            }

            .recovery-content {
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
        <img src="logoo.png" width="60px" alt="">
        <div class="logo-text">EduHive</div>
    </div>

    <!-- Main Recovery Container -->
    <div class="recovery-container">
        <div class="recovery-content">
            <h1 class="page-title">RESET YOUR PASSWORD</h1>
            
            <!-- Show Message if exists -->
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($message['type']); ?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Recovery Form -->
            <form class="recovery-form" method="POST">
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="Enter your email address" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="submit-btn">
                    Continue to Reset Password
                </button>
            </form>
            
            <!-- Footer Links -->
            <div class="footer-links">
                <a href="login.php">← Back to Login</a>
                <span class="separator">•</span>
                <a href="register.php">Create an account</a>
            </div>
        </div>
    </div>

    <script>
        // Simple form enhancements
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.querySelector('input[name="email"]');
            
            // Auto-focus email field
            emailInput.focus();
            
            // Auto-hide success messages after 3 seconds
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.opacity = '0';
                    setTimeout(function() {
                        successMessage.style.display = 'none';
                    }, 300);
                }, 3000);
            }
        });
    </script>
</body>
</html>