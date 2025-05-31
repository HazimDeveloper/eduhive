<?php
// verify_email.php
require_once 'config/functions.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Verify token
    $query = "SELECT user_id FROM email_tokens WHERE token = :token AND type = 'verification' AND expires_at > NOW() AND used = FALSE";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':token', $token);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $token_data['user_id'];
        
        // Update user as verified
        $update_query = "UPDATE users SET email_verified = TRUE WHERE id = :user_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':user_id', $user_id);
        $update_stmt->execute();
        
        // Mark token as used
        $token_update = "UPDATE email_tokens SET used = TRUE WHERE token = :token";
        $token_stmt = $db->prepare($token_update);
        $token_stmt->bindParam(':token', $token);
        $token_stmt->execute();
        
        $message = "Email verified successfully! You can now log in.";
        $success = true;
    } else {
        $message = "Invalid or expired verification token.";
        $success = false;
    }
} else {
    $message = "No verification token provided.";
    $success = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - EduHive</title>
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
                <h2 class="login-title">EMAIL VERIFICATION</h2>
                <div class="message <?php echo $success ? 'success' : 'error'; ?>">
                    <?php echo $message; ?>
                </div>
                <a href="login.php" style="display: inline-block; margin-top: 20px; padding: 12px 24px; background: linear-gradient(45deg, #b19176, #8B7355); color: white; text-decoration: none; border-radius: 25px;">Go to Login</a>
            </div>
        </div>
    </div>
</body>
</html>