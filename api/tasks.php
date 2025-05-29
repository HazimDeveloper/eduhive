
<?php
// api/tasks.php - Task management API
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Get all tasks
    $query = "SELECT t.*, c.name as course_name FROM tasks t 
              LEFT JOIN courses c ON t.course_id = c.id 
              WHERE t.user_id = :user_id 
              ORDER BY t.created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, "Tasks retrieved successfully", $tasks);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Add new task
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $course = sanitizeInput($_POST['course']);
    $priority = sanitizeInput($_POST['priority']);
    $due_date = $_POST['due_date'];
    
    // Get course ID
    $course_query = "SELECT id FROM courses WHERE name = :course AND user_id = :user_id";
    $course_stmt = $db->prepare($course_query);
    $course_stmt->bindParam(':course', $course);
    $course_stmt->bindParam(':user_id', $user_id);
    $course_stmt->execute();
    $course_result = $course_stmt->fetch(PDO::FETCH_ASSOC);
    $course_id = $course_result ? $course_result['id'] : null;
    
    $query = "INSERT INTO tasks (user_id, course_id, title, description, priority, due_date) 
              VALUES (:user_id, :course_id, :title, :description, :priority, :due_date)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':priority', $priority);
    $stmt->bindParam(':due_date', $due_date);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Task added successfully");
    } else {
        jsonResponse(false, "Failed to add task");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'PUT') {
    // Update task status
    parse_str(file_get_contents("php://input"), $_PUT);
    $task_id = $_PUT['task_id'];
    $status = $_PUT['status'];
    
    $query = "UPDATE tasks SET status = :status WHERE id = :task_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':task_id', $task_id);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Task updated successfully");
    } else {
        jsonResponse(false, "Failed to update task");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    // Delete task
    parse_str(file_get_contents("php://input"), $_DELETE);
    $task_id = $_DELETE['task_id'];
    
    $query = "DELETE FROM tasks WHERE id = :task_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':task_id', $task_id);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Task deleted successfully");
    } else {
        jsonResponse(false, "Failed to delete task");
    }
}

// api/schedule.php - Class schedule management
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = "SELECT * FROM class_schedules WHERE user_id = :user_id ORDER BY day_of_week, time_slot";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, "Schedules retrieved successfully", $schedules);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $class_code = sanitizeInput($_POST['class_code']);
    $day = sanitizeInput($_POST['day']);
    $time_slot = sanitizeInput($_POST['time_slot']);
    $mode_location = sanitizeInput($_POST['mode_location']);
    
    $query = "INSERT INTO class_schedules (user_id, class_code, day_of_week, time_slot, mode_location) 
              VALUES (:user_id, :class_code, :day, :time_slot, :mode_location)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':class_code', $class_code);
    $stmt->bindParam(':day', $day);
    $stmt->bindParam(':time_slot', $time_slot);
    $stmt->bindParam(':mode_location', $mode_location);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Class added successfully");
    } else {
        jsonResponse(false, "Failed to add class");
    }
}

// api/calendar.php - Calendar events management
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = "SELECT * FROM calendar_events WHERE user_id = :user_id ORDER BY start_date, start_time";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, "Events retrieved successfully", $events);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitizeInput($_POST['title']);
    $description = sanitizeInput($_POST['description']);
    $start_date = $_POST['start_date'];
    $start_time = $_POST['start_time'];
    
    $query = "INSERT INTO calendar_events (user_id, title, description, start_date, start_time) 
              VALUES (:user_id, :title, :description, :start_date, :start_time)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':title', $title);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':start_date', $start_date);
    $stmt->bindParam(':start_time', $start_time);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Event added successfully");
    } else {
        jsonResponse(false, "Failed to add event");
    }
}

// api/time_tracking.php - Time tracking management
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = "SELECT * FROM time_entries WHERE user_id = :user_id ORDER BY date_recorded DESC, created_at DESC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    jsonResponse(true, "Time entries retrieved successfully", $entries);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $task_name = sanitizeInput($_POST['task_name']);
    $category = sanitizeInput($_POST['category']);
    $duration_minutes = intval($_POST['duration_minutes']);
    $description = sanitizeInput($_POST['description']);
    $date_recorded = $_POST['date_recorded'];
    
    $query = "INSERT INTO time_entries (user_id, task_name, category, duration_minutes, description, date_recorded) 
              VALUES (:user_id, :task_name, :category, :duration_minutes, :description, :date_recorded)";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':task_name', $task_name);
    $stmt->bindParam(':category', $category);
    $stmt->bindParam(':duration_minutes', $duration_minutes);
    $stmt->bindParam(':description', $description);
    $stmt->bindParam(':date_recorded', $date_recorded);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Time entry saved successfully");
    } else {
        jsonResponse(false, "Failed to save time entry");
    }
}

// api/team_members.php - Team members management
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    $query = "SELECT * FROM team_members WHERE user_id = :user_id ORDER BY group_name, name";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $members = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group members by group_name
    $grouped = [];
    foreach ($members as $member) {
        $grouped[$member['group_name']][] = $member;
    }
    
    jsonResponse(true, "Team members retrieved successfully", $grouped);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name']);
    $email = sanitizeInput($_POST['email']);
    $group_name = sanitizeInput($_POST['group_name']);
    $role = sanitizeInput($_POST['role']);
    
    $query = "INSERT INTO team_members (user_id, name, email, group_name, role, date_added, last_active) 
              VALUES (:user_id, :name, :email, :group_name, :role, CURDATE(), CURDATE())";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':name', $name);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':group_name', $group_name);
    $stmt->bindParam(':role', $role);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Team member added successfully");
    } else {
        jsonResponse(false, "Failed to add team member");
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'DELETE') {
    parse_str(file_get_contents("php://input"), $_DELETE);
    $member_id = $_DELETE['member_id'];
    
    $query = "DELETE FROM team_members WHERE id = :member_id AND user_id = :user_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':member_id', $member_id);
    $stmt->bindParam(':user_id', $user_id);
    
    if ($stmt->execute()) {
        jsonResponse(true, "Team member deleted successfully");
    } else {
        jsonResponse(false, "Failed to delete team member");
    }
}