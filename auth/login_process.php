<?php
// auth/login_process.php
require_once '../config/database.php';
require_once '../config/session.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];

    if (empty($email) || empty($password)) {
        jsonResponse(false, "Please fill in all fields");
    }

    $database = new Database();
    $db = $database->getConnection();

    $query = "SELECT id, name, email, password FROM users WHERE email = :email";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':email', $email);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (password_verify($password, $user['password'])) {
            setUserSession($user['id'], $user['name'], $user['email']);
            
            // Update last login
            $update_query = "UPDATE user_progress SET last_login = CURDATE() WHERE user_id = :user_id";
            $update_stmt = $db->prepare($update_query);
            $update_stmt->bindParam(':user_id', $user['id']);
            $update_stmt->execute();
            
            jsonResponse(true, "Login successful", ['redirect' => 'dashboard.php']);
        } else {
            jsonResponse(false, "Invalid password");
        }
    } else {
        jsonResponse(false, "User not found");
    }
}

?>