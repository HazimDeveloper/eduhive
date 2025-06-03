<?php
require_once 'config/session.php';
require_once 'config/database.php';
require_once 'config/functions.php';

// Check if user is logged in and has admin privileges
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $course_data = [
            'name' => $_POST['name'],
            'code' => $_POST['code'],
            'description' => $_POST['description'] ?? '',
            'created_by' => $_SESSION['user_id']
        ];

        $course_id = createCourse($course_data);
        
        if ($course_id) {
            logActivity($_SESSION['user_id'], 'course_created', "Created course: " . $course_data['name']);
            $success_message = "Course created successfully!";
        } else {
            $error_message = "Failed to create course. Please try again.";
        }
        
    } catch (Exception $e) {
        error_log("Create course error: " . $e->getMessage());
        $error_message = "An error occurred while creating the course.";
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Course | EduHive</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <?php include 'sidebar.php'; ?>
    
    <div class="content">
        <h1>Create New Course</h1>
        
        <?php if ($error_message): ?>
            <div class="alert error"><?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>
        
        <?php if ($success_message): ?>
            <div class="alert success"><?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>
        
        <form method="POST" class="course-form">
            <div class="form-group">
                <label for="name">Course Name*</label>
                <input type="text" id="name" name="name" required>
            </div>
            
            <div class="form-group">
                <label for="code">Course Code*</label>
                <input type="text" id="code" name="code" required>
            </div>
            
            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" rows="4"></textarea>
            </div>
            
            <button type="submit" class="btn">Create Course</button>
        </form>
    </div>
</body>
</html>