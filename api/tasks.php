<?php
// api/tasks.php - Tasks API endpoint
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../config/database.php';
require_once '../config/session.php';
require_once '../config/functions.php';

// Ensure user is logged in
if (!isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'User not authenticated']);
    exit();
}

$user_id = getCurrentUserId();
$method = $_SERVER['REQUEST_METHOD'];

try {
    $database = new Database();
    
    switch ($method) {
        case 'GET':
            handleGetRequest($database, $user_id);
            break;
            
        case 'POST':
            handlePostRequest($database, $user_id);
            break;
            
        case 'PUT':
            handlePutRequest($database, $user_id);
            break;
            
        case 'DELETE':
            handleDeleteRequest($database, $user_id);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Tasks API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handleGetRequest($database, $user_id) {
    if (isset($_GET['id'])) {
        // Get single task
        $task_id = (int)$_GET['id'];
        $query = "SELECT t.*, c.name as course_name, c.code as course_code 
                  FROM tasks t 
                  LEFT JOIN courses c ON t.course_id = c.id 
                  WHERE t.id = :id AND t.user_id = :user_id";
        
        $task = $database->queryRow($query, [':id' => $task_id, ':user_id' => $user_id]);
        
        if ($task) {
            echo json_encode(['success' => true, 'data' => $task]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Task not found']);
        }
        
    } elseif (isset($_GET['status'])) {
        // Get tasks by status
        $status = cleanInput($_GET['status']);
        $query = "SELECT t.*, c.name as course_name, c.code as course_code 
                  FROM tasks t 
                  LEFT JOIN courses c ON t.course_id = c.id 
                  WHERE t.user_id = :user_id AND t.status = :status 
                  ORDER BY t.due_date ASC, t.created_at DESC";
        
        $tasks = $database->query($query, [':user_id' => $user_id, ':status' => $status]);
        
        echo json_encode(['success' => true, 'data' => $tasks ?: []]);
        
    } elseif (isset($_GET['course_id'])) {
        // Get tasks for specific course
        $course_id = (int)$_GET['course_id'];
        $query = "SELECT t.*, c.name as course_name, c.code as course_code 
                  FROM tasks t 
                  LEFT JOIN courses c ON t.course_id = c.id 
                  WHERE t.user_id = :user_id AND t.course_id = :course_id 
                  ORDER BY t.due_date ASC, t.created_at DESC";
        
        $tasks = $database->query($query, [':user_id' => $user_id, ':course_id' => $course_id]);
        
        echo json_encode(['success' => true, 'data' => $tasks ?: []]);
        
    } else {
        // Get all tasks for user
        $query = "SELECT t.*, c.name as course_name, c.code as course_code 
                  FROM tasks t 
                  LEFT JOIN courses c ON t.course_id = c.id 
                  WHERE t.user_id = :user_id 
                  ORDER BY t.due_date ASC, t.created_at DESC";
        
        $tasks = $database->query($query, [':user_id' => $user_id]);
        
        echo json_encode(['success' => true, 'data' => $tasks ?: []]);
    }
}

function handlePostRequest($database, $user_id) {
    // Validate required fields
    $required_fields = ['title'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Prepare task data
    $task_data = [
        'user_id' => $user_id,
        'title' => cleanInput($_POST['title']),
        'description' => cleanInput($_POST['description'] ?? ''),
        'status' => cleanInput($_POST['status'] ?? 'todo'),
        'priority' => cleanInput($_POST['priority'] ?? 'medium'),
        'due_date' => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
        'start_time' => !empty($_POST['start_time']) ? $_POST['start_time'] : null,
        'end_time' => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
        'course_id' => !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null,
        'reminder_type' => cleanInput($_POST['reminder_type'] ?? '')
    ];
    
    // Validate status
    $valid_statuses = ['todo', 'progress', 'done'];
    if (!in_array($task_data['status'], $valid_statuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid status']);
        return;
    }
    
    // Validate priority
    $valid_priorities = ['low', 'medium', 'high'];
    if (!in_array($task_data['priority'], $valid_priorities)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid priority']);
        return;
    }
    
    // Validate date format if provided
    if ($task_data['due_date'] && !validateDate($task_data['due_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        return;
    }
    
    // Insert task
    $task_id = $database->insert('tasks', $task_data);
    
    if ($task_id) {
        echo json_encode(['success' => true, 'message' => 'Task created successfully', 'id' => $task_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create task']);
    }
}

function handlePutRequest($database, $user_id) {
    parse_str(file_get_contents('php://input'), $input);
    
    if (empty($input['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Task ID required']);
        return;
    }
    
    $task_id = (int)$input['task_id'];
    
    // Check if task belongs to user
    $existing_task = $database->queryRow(
        "SELECT * FROM tasks WHERE id = :id AND user_id = :user_id",
        [':id' => $task_id, ':user_id' => $user_id]
    );
    
    if (!$existing_task) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Task not found']);
        return;
    }
    
    // Prepare update data (only include provided fields)
    $update_data = [];
    $allowed_fields = ['title', 'description', 'status', 'priority', 'due_date', 'start_time', 'end_time', 'course_id', 'reminder_type'];
    
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            if ($field === 'course_id') {
                $update_data[$field] = !empty($input[$field]) ? (int)$input[$field] : null;
            } else {
                $update_data[$field] = cleanInput($input[$field]);
            }
        }
    }
    
    if (empty($update_data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No data to update']);
        return;
    }
    
    // Validate status if provided
    if (isset($update_data['status'])) {
        $valid_statuses = ['todo', 'progress', 'done'];
        if (!in_array($update_data['status'], $valid_statuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid status']);
            return;
        }
        
        // Add completion timestamp if marking as done
        if ($update_data['status'] === 'done' && $existing_task['status'] !== 'done') {
            $update_data['completed_at'] = date('Y-m-d H:i:s');
        } elseif ($update_data['status'] !== 'done' && $existing_task['status'] === 'done') {
            $update_data['completed_at'] = null;
        }
    }
    
    // Validate priority if provided
    if (isset($update_data['priority'])) {
        $valid_priorities = ['low', 'medium', 'high'];
        if (!in_array($update_data['priority'], $valid_priorities)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid priority']);
            return;
        }
    }
    
    // Validate date format if provided
    if (isset($update_data['due_date']) && $update_data['due_date'] && !validateDate($update_data['due_date'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid date format']);
        return;
    }
    
    $success = $database->update('tasks', $update_data, 'id = :id AND user_id = :user_id', [
        ':id' => $task_id, 
        ':user_id' => $user_id
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Task updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update task']);
    }
}

function handleDeleteRequest($database, $user_id) {
    parse_str(file_get_contents('php://input'), $input);
    
    if (empty($input['task_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Task ID required']);
        return;
    }
    
    $task_id = (int)$input['task_id'];
    
    $success = $database->delete('tasks', 'id = :id AND user_id = :user_id', [
        ':id' => $task_id, 
        ':user_id' => $user_id
    ]);
    
    if ($success > 0) {
        echo json_encode(['success' => true, 'message' => 'Task deleted successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Task not found or already deleted']);
    }
}

function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}
?>