<?php 


// auth/register_process.php
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register'])) {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        jsonResponse(false, "Please fill in all fields");
    }

    if ($password !== $confirm_password) {
        jsonResponse(false, "Passwords do not match");
    }

    if (strlen($password) < 6) {
        jsonResponse(false, "Password must be at least 6 characters long");
    }

    $database = new Database();
    $db = $database->getConnection();

    // Check if email already exists
    $check_query = "SELECT id FROM users WHERE email = :email";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':email', $email);
    $check_stmt->execute();

    if ($check_stmt->rowCount() > 0) {
        jsonResponse(false, "Email already exists");
    }

    // Hash password and create user
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $db->beginTransaction();
    
    try {
        $insert_query = "INSERT INTO users (name, email, password) VALUES (:name, :email, :password)";
        $insert_stmt = $db->prepare($insert_query);
        $insert_stmt->bindParam(':name', $name);
        $insert_stmt->bindParam(':email', $email);
        $insert_stmt->bindParam(':password', $hashed_password);
        $insert_stmt->execute();
        
        $user_id = $db->lastInsertId();
        
        // Create default user progress
        $progress_query = "INSERT INTO user_progress (user_id, total_badges, total_points, rank_position, last_login) 
                          VALUES (:user_id, 0, 0, 1, CURDATE())";
        $progress_stmt = $db->prepare($progress_query);
        $progress_stmt->bindParam(':user_id', $user_id);
        $progress_stmt->execute();
        
        // Create default courses
        $default_courses = [
            ['FYP', 'FYP2024', 'Final Year Project', 'brown'],
            ['PROGRAMMING', 'PROG101', 'Programming Course', 'blue'],
            ['HARTA', 'HARTA101', 'Property Development Course', 'orange']
        ];
        
        $course_query = "INSERT INTO courses (user_id, name, code, description, color) VALUES (:user_id, :name, :code, :description, :color)";
        $course_stmt = $db->prepare($course_query);
        
        foreach ($default_courses as $course) {
            $course_stmt->bindParam(':user_id', $user_id);
            $course_stmt->bindParam(':name', $course[0]);
            $course_stmt->bindParam(':code', $course[1]);
            $course_stmt->bindParam(':description', $course[2]);
            $course_stmt->bindParam(':color', $course[3]);
            $course_stmt->execute();
        }
        
        $db->commit();
        
        setUserSession($user_id, $name, $email);
        jsonResponse(true, "Registration successful", ['redirect' => 'dashboard.php']);
        
    } catch (Exception $e) {
        $db->rollBack();
        jsonResponse(false, "Registration failed: " . $e->getMessage());
    }
}
