<?php
// api/courses.php - Course management API
require_once '../config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Get all courses for the user
    $query = "SELECT * FROM courses WHERE user_id = :user_id ORDER BY name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, "Courses retrieved successfully", $courses);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new course
    $name = sanitizeInput($_POST['name']);
    $code = sanitizeInput($_POST['code']);
    $description = sanitizeInput($_POST['description'] ?? '');
    $color = sanitizeInput($_POST['color']);
    
    if (empty($name) || empty($code) || empty($color)) {
        jsonResponse(false, "Please fill in all required fields");
    }
    
    // Check if course name already exists for this user
    $check_query = "SELECT id FROM courses WHERE name = :name AND user_id = :user_id";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->bindParam(':name', $name);
    $check_stmt->bindParam(':user_id', $user_id);
    $check_stmt->execute();
    
    if ($check_stmt->rowCount() > 0) {
        jsonResponse(false, "Course with this name already exists");
    }
    
    $query = "INSERT INTO courses (user_id, name, code, description, color) 
              VALUES (:user_id, :name, :code, :description, :color)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':code', $code);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':color', $color);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Course added successfully");
    } else {
        jsonResponse(false, "Failed to add course");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Update course
    parse_str(file_get_contents("php://input"), $_PUT);
    $course_id = $_PUT['course_id'];
    $name = sanitizeInput($_PUT['name']);
    $code = sanitizeInput($_PUT['code']);
    $description = sanitizeInput($_PUT['description'] ?? '');
    $color = sanitizeInput($_PUT['color']);
    
    $query = "UPDATE courses SET name = :name, code = :code, description = :description, color = :color 
              WHERE id = :course_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':code', $code);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':color', $color);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Course updated successfully");
    } else {
        jsonResponse(false, "Failed to update course");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    // Delete course
    parse_str(file_get_contents("php://input"), $_DELETE);
    $course_id = $_DELETE['course_id'];
    
    // Check if course has associated tasks
    $task_check_query = "SELECT COUNT(*) as task_count FROM tasks WHERE course_id = :course_id";
    $task_check_stmt = $db->prepare($task_check_query);
    $task_check_stmt->bindParam(':course_id', $course_id);
    $task_check_stmt->execute();
    $task_result = $task_check_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($task_result['task_count'] > 0) {
        jsonResponse(false, "Cannot delete course with existing tasks. Please delete tasks first.");
    }
    
    $query = "DELETE FROM courses WHERE id = :course_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Course deleted successfully");
    } else {
        jsonResponse(false, "Failed to delete course");
    }
}
?>