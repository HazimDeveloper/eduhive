<?php
// reset_password.php
require_once 'config/functions.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $token = $_POST['token'];
    
    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in all fields";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long";
    } else {
        $result = resetPassword($token, $new_password);
        if ($result['success']) {
            $success = $result['message'];
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - EduHive</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <div class="login-wrapper">
        <div class="login-box">
            <div class="brand">
                <div class="logo">
                    <div class="logo-circle">
                        <div class="graduation-cap">🎓</div>
                        <div class="location-pin">📍</div>
                    </div>
                </div>
                <h1>EduHive</h1>
            </div>

            <div class="login-form">
                <h2 class="login-title">RESET PASSWORD</h2>
                
                <?php if ($error): ?>
                    <div class="message error"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="message success"><?php echo $success; ?></div>
                    <a href="login.php" style="display: inline-block; margin-top: 20px; padding: 12px 24px; background: linear-gradient(45deg, #b19176, #8B7355); color: white; text-decoration: none; border-radius: 25px;">Go to Login</a>
                <?php else: ?>
                    <form method="POST">
                        <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                        <input type="password" name="password" placeholder="New Password" required minlength="6">
                        <input type="password" name="confirm_password" placeholder="Confirm Password" required>
                        <button type="submit">Reset Password</button>
                    </form>
                <?php endif; ?>
                
                <div class="back-link">
                    <a href="login.php">Back to Login</a>
                </div>
            </div>
        </div>
    </div>

    <style>
        .message {
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            text-align: center;
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
    </style>
</body>
</html>