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
        // Send recovery email
        $result = sendPasswordRecovery($email);
        
        if ($result['success']) {
            setMessage($result['message'], 'success');
            
            // In development, show the reset link
            if (isset($result['reset_link'])) {
                $_SESSION['reset_link'] = $result['reset_link'];
            }
        } else {
            setMessage($result['message'], 'error');
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

        /* Reset Link Container (Development) */
        .reset-link-container {
            background: #e7f3ff;
            border: 2px solid #4A90A4;
            border-radius: 15px;
            padding: 20px;
            margin: 30px 0;
            animation: slideDown 0.3s ease;
        }

        .reset-link-header {
            font-weight: 600;
            color: #2c5aa0;
            margin-bottom: 15px;
            font-size: 16px;
            text-align: center;
        }

        .reset-link-box {
            display: flex;
            gap: 10px;
            align-items: center;
            background: white;
            padding: 15px;
            border-radius: 10px;
            border: 1px solid #4A90A4;
        }

        .reset-link {
            flex: 1;
            color: #2c5aa0;
            text-decoration: none;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            word-break: break-all;
            line-height: 1.4;
            transition: color 0.3s ease;
        }

        .reset-link:hover {
            color: #1a4480;
            text-decoration: underline;
        }

        .copy-btn {
            background: #4A90A4;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            white-space: nowrap;
        }

        .copy-btn:hover {
            background: #357A8C;
            transform: translateY(-1px);
        }

        .copy-btn.copied {
            background: #28a745;
        }

        .reset-link-note {
            text-align: center;
            font-size: 13px;
            color: #666;
            margin-top: 10px;
            font-style: italic;
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

            .reset-link-container {
                padding: 15px;
            }

            .reset-link-box {
                flex-direction: column;
                gap: 10px;
                padding: 12px;
            }

            .reset-link {
                font-size: 13px;
                text-align: center;
            }

            .copy-btn {
                width: 100%;
                padding: 10px;
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

            .reset-link-container {
                padding: 12px;
                margin: 20px 0;
            }

            .reset-link-header {
                font-size: 14px;
            }

            .reset-link {
                font-size: 12px;
            }

            .copy-btn {
                font-size: 12px;
                padding: 8px 12px;
            }
        }
    </style>
</head>
<body>
    <!-- Top Left Logo -->
    <div class="top-logo">
        <!-- Top Left Logo -->
    <div class="top-logo">
        <img src="logoo.png" width="60px" alt="">
        <div class="logo-text">EduHive</div>
    </div>
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
            
            <!-- Development Reset Link -->
            <?php if (isset($_SESSION['reset_link'])): ?>
                <div class="reset-link-container">
                    <div class="reset-link-header">Development Mode - Reset Link:</div>
                    <div class="reset-link-box">
                        <a href="<?php echo htmlspecialchars($_SESSION['reset_link']); ?>" 
                           target="_blank" 
                           class="reset-link"
                           id="resetLink">
                            <?php echo htmlspecialchars($_SESSION['reset_link']); ?>
                        </a>
                        <button type="button" class="copy-btn" onclick="copyResetLink()" id="copyBtn">
                            📋 Copy
                        </button>
                    </div>
                    <div class="reset-link-note">Click to open in new tab or copy the link</div>
                </div>
                <?php unset($_SESSION['reset_link']); ?>
            <?php endif; ?>
            
            <!-- Recovery Form -->
            <form class="recovery-form" method="POST">
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="Enter your email address" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <button type="submit" class="submit-btn">
                    Send Recovery Link
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
            
            // Auto-hide success messages after 5 seconds
            const successMessage = document.querySelector('.message.success');
            if (successMessage) {
                setTimeout(function() {
                    successMessage.style.opacity = '0';
                    setTimeout(function() {
                        successMessage.style.display = 'none';
                    }, 300);
                }, 5000);
            }
        });

        // Copy reset link to clipboard
        function copyResetLink() {
            const resetLink = document.getElementById('resetLink');
            const copyBtn = document.getElementById('copyBtn');
            
            if (resetLink && copyBtn) {
                // Create a temporary textarea to copy the text
                const textarea = document.createElement('textarea');
                textarea.value = resetLink.href;
                document.body.appendChild(textarea);
                textarea.select();
                textarea.setSelectionRange(0, 99999); // For mobile devices
                
                try {
                    // Copy the text
                    const successful = document.execCommand('copy');
                    
                    if (successful) {
                        // Show success feedback
                        const originalText = copyBtn.textContent;
                        copyBtn.textContent = '✓ Copied!';
                        copyBtn.classList.add('copied');
                        
                        // Reset button after 2 seconds
                        setTimeout(function() {
                            copyBtn.textContent = originalText;
                            copyBtn.classList.remove('copied');
                        }, 2000);
                    } else {
                        // Fallback: select the link text for manual copying
                        const range = document.createRange();
                        range.selectNode(resetLink);
                        window.getSelection().removeAllRanges();
                        window.getSelection().addRange(range);
                        
                        copyBtn.textContent = 'Select & Copy';
                    }
                } catch (err) {
                    // Fallback for older browsers
                    const range = document.createRange();
                    range.selectNode(resetLink);
                    window.getSelection().removeAllRanges();
                    window.getSelection().addRange(range);
                    
                    copyBtn.textContent = 'Select & Copy';
                }
                
                // Remove the temporary textarea
                document.body.removeChild(textarea);
            }
        }
    </script>
</body>
</html>