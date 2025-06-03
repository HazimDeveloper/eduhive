<?php
// DEBUG VERSION OF login.php
// Add error reporting to see what's happening
error_reporting(E_ALL);
ini_set('display_errors', 1);

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
$debug_info = []; // For debugging

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    $debug_info[] = "Form submitted with email: " . $email;
    
    // Basic validation
    if (empty($email)) {
        setMessage('Please enter your email address.', 'error');
        $debug_info[] = "Error: Empty email";
    } elseif (empty($password)) {
        setMessage('Please enter your password.', 'error');
        $debug_info[] = "Error: Empty password";
    } elseif (!isValidEmail($email)) {
        setMessage('Please enter a valid email address.', 'error');
        $debug_info[] = "Error: Invalid email format";
    } else {
        $debug_info[] = "Validation passed, attempting database connection...";
        
        // Try to login
        $database = new Database();
        
        // Test database connection
        try {
            $db = $database->getConnection();
            $debug_info[] = "Database connection successful";
            
            // Check if user exists first
            $check_query = "SELECT id, name, email, status, email_verified FROM users WHERE email = :email";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':email', $email);
            $check_stmt->execute();
            
            $debug_info[] = "Query executed. Rows found: " . $check_stmt->rowCount();
            
            if ($check_stmt->rowCount() > 0) {
                $user_info = $check_stmt->fetch(PDO::FETCH_ASSOC);
                $debug_info[] = "User found: " . json_encode($user_info);
                
                // Now get user with password for login
                $query = "SELECT * FROM users WHERE email = :email AND status = 'active'";
                $stmt = $db->prepare($query);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                if ($stmt->rowCount() > 0) {
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                    $debug_info[] = "Active user found, checking password...";
                    
                    // Check password
                    if (password_verify($password, $user['password'])) {
                        $debug_info[] = "Password verified successfully!";
                        
                        // Password is correct - login user
                        if (loginUser($user)) {
                            // Update last login time
                            $update_query = "UPDATE users SET last_login = NOW() WHERE id = :id";
                            $update_stmt = $db->prepare($update_query);
                            $update_stmt->bindParam(':id', $user['id']);
                            $update_stmt->execute();
                            
                            $debug_info[] = "Login successful, redirecting...";
                            
                            // Login successful - redirect to dashboard
                            setMessage('Welcome back, ' . $user['name'] . '!', 'success');
                            header("Location: dashboard.php");
                            exit();
                        } else {
                            setMessage('Login failed. Please try again.', 'error');
                            $debug_info[] = "loginUser() function failed";
                        }
                    } else {
                        // Wrong password
                        setMessage('Invalid email or password.', 'error');
                        $debug_info[] = "Password verification failed";
                        $debug_info[] = "Stored hash: " . substr($user['password'], 0, 30) . "...";
                        $debug_info[] = "Password length: " . strlen($password);
                    }
                } else {
                    // User not found or inactive
                    setMessage('Invalid email or password.', 'error');
                    $debug_info[] = "No active user found with this email";
                }
            } else {
                setMessage('Invalid email or password.', 'error');
                $debug_info[] = "No user found with email: " . $email;
                
                // Show what users DO exist (for debugging)
                $all_users_query = "SELECT email FROM users LIMIT 5";
                $all_users_stmt = $db->prepare($all_users_query);
                $all_users_stmt->execute();
                $existing_emails = $all_users_stmt->fetchAll(PDO::FETCH_COLUMN);
                $debug_info[] = "Existing emails in database: " . json_encode($existing_emails);
            }
            
        } catch (PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            setMessage('System error. Please try again later.', 'error');
            $debug_info[] = "Database error: " . $e->getMessage();
        }
    }
    
    // Redirect to avoid form resubmission
    // For debugging, we'll show debug info instead of redirecting
    if (!headers_sent()) {
        // header("Location: login.php");
        // exit();
    }
}

// Test password hash for debugging
$test_password = 'password123';
$correct_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi';
$hash_test = password_verify($test_password, $correct_hash);
$debug_info[] = "Password hash test: " . ($hash_test ? "PASS" : "FAIL");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EduHive - Login (Debug Mode)</title>
    <style>
        /* Your existing login styles here */
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

        .debug-panel {
            background: #000;
            color: #00ff00;
            padding: 20px;
            font-family: monospace;
            font-size: 12px;
            border-bottom: 3px solid #ff0000;
            max-height: 300px;
            overflow-y: auto;
        }

        .debug-panel h3 {
            color: #ffff00;
            margin-bottom: 10px;
        }

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

        .page-title {
            font-size: 24px;
            font-weight: 600;
            color: #333;
            margin-bottom: 60px;
            letter-spacing: 2px;
        }

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

        .login-form {
            display: flex;
            flex-direction: column;
            gap: 30px;
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

        .test-credentials {
            background: #e8f4f8;
            border: 2px solid #4A90A4;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
        }

        .test-credentials h4 {
            color: #2c5aa0;
            margin-bottom: 10px;
        }

        .test-credentials p {
            margin: 5px 0;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <!-- Debug Panel -->
    <?php if (!empty($debug_info)): ?>
    <div class="debug-panel">
        <h3>🐛 DEBUG INFORMATION</h3>
        <?php foreach ($debug_info as $info): ?>
            <div><?php echo htmlspecialchars($info); ?></div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Top Left Logo -->
    <div class="top-logo">
        <img src="logoo.png" width="60px" alt="">
        <div class="logo-text">EduHive</div>
    </div>

    <!-- Main Login Container -->
    <div class="login-container">
        <div class="login-content">
            <h1 class="page-title">LOG IN TO YOUR ACCOUNT (DEBUG MODE)</h1>
            
            <!-- Test Credentials -->
            <div class="test-credentials">
                <h4>🧪 Test Credentials:</h4>
                <p><strong>Email:</strong> alice@student.edu.my</p>
                <p><strong>Password:</strong> password123</p>
                <p><em>Copy-paste these exactly!</em></p>
            </div>
            
            <!-- Show Message if exists -->
            <?php if ($message): ?>
                <div class="message <?php echo htmlspecialchars($message['type']); ?>">
                    <?php echo htmlspecialchars($message['text']); ?>
                </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form class="login-form" method="POST">
                <div class="form-group">
                    <input type="email" name="email" class="form-input" placeholder="Email" required autocomplete="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                </div>
                
                <div class="form-group">
                    <input type="password" name="password" class="form-input" placeholder="Password" required autocomplete="current-password">
                </div>
                
                <button type="submit" class="submit-btn">
                    Sign In (Debug Mode)
                </button>
            </form>
            
            <!-- Footer Links -->
            <div class="footer-links">
                <a href="recovery.php">Can't Log in?</a>
                <span>•</span>
                <a href="register.php">Create an account</a>
            </div>
        </div>
    </div>

    <script>
        // Auto-fill Alice's credentials for testing
        document.addEventListener('DOMContentLoaded', function() {
            const emailInput = document.querySelector('input[name="email"]');
            const passwordInput = document.querySelector('input[name="password"]');
            
            // Pre-fill with Alice's credentials
            if (!emailInput.value) {
                emailInput.value = 'alice@student.edu.my';
            }
        });
    </script>
</body>
</html>