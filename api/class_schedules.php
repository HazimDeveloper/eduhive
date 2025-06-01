<?php
// api/class_schedules.php - Class Schedules API endpoint
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
    error_log("Class Schedules API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handleGetRequest($database, $user_id) {
    if (isset($_GET['id'])) {
        // Get single class schedule
        $schedule_id = (int)$_GET['id'];
        $query = "SELECT cs.*, c.name as course_name, c.code as course_code 
                  FROM class_schedules cs 
                  LEFT JOIN courses c ON cs.course_id = c.id 
                  WHERE cs.id = :id AND cs.user_id = :user_id";
        
        $schedule = $database->queryRow($query, [':id' => $schedule_id, ':user_id' => $user_id]);
        
        if ($schedule) {
            echo json_encode(['success' => true, 'data' => $schedule]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Class schedule not found']);
        }
        
    } else {
        // Get all class schedules for user
        $query = "SELECT cs.*, c.name as course_name, c.code as course_code, c.color as course_color 
                  FROM class_schedules cs 
                  LEFT JOIN courses c ON cs.course_id = c.id 
                  WHERE cs.user_id = :user_id 
                  ORDER BY cs.day_of_week, cs.start_time";
        
        $schedules = $database->query($query, [':user_id' => $user_id]);
        
        echo json_encode(['success' => true, 'data' => $schedules ?: []]);
    }
}

function handlePostRequest($database, $user_id) {
    // Validate required fields
    $required_fields = ['class_code', 'day_of_week', 'start_time', 'end_time'];
    foreach ($required_fields as $field) {
        if (empty($_POST[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Prepare schedule data
    $schedule_data = [
        'user_id' => $user_id,
        'class_code' => cleanInput($_POST['class_code']),
        'day_of_week' => cleanInput($_POST['day_of_week']),
        'start_time' => $_POST['start_time'],
        'end_time' => $_POST['end_time'],
        'location' => cleanInput($_POST['location'] ?? ''),
        'mode' => cleanInput($_POST['mode'] ?? 'physical'),
        'instructor' => cleanInput($_POST['instructor'] ?? ''),
        'course_id' => !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null
    ];
    
    // Validate day of week
    $valid_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    if (!in_array($schedule_data['day_of_week'], $valid_days)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid day of week']);
        return;
    }
    
    // Validate time format
    if (!validateTime($schedule_data['start_time']) || !validateTime($schedule_data['end_time'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid time format']);
        return;
    }
    
    // Check for conflicts
    if (hasTimeConflict($database, $user_id, $schedule_data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Time conflict with existing class']);
        return;
    }
    
    // Insert schedule
    $schedule_id = $database->insert('class_schedules', $schedule_data);
    
    if ($schedule_id) {
        echo json_encode(['success' => true, 'message' => 'Class schedule created successfully', 'id' => $schedule_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create class schedule']);
    }
}

function handlePutRequest($database, $user_id) {
    parse_str(file_get_contents('php://input'), $input);
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Schedule ID required']);
        return;
    }
    
    $schedule_id = (int)$input['id'];
    
    // Check if schedule belongs to user
    $existing_schedule = $database->queryRow(
        "SELECT id FROM class_schedules WHERE id = :id AND user_id = :user_id",
        [':id' => $schedule_id, ':user_id' => $user_id]
    );
    
    if (!$existing_schedule) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Class schedule not found']);
        return;
    }
    
    // Prepare update data (only include provided fields)
    $update_data = [];
    $allowed_fields = ['class_code', 'day_of_week', 'start_time', 'end_time', 'location', 'mode', 'instructor', 'course_id'];
    
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
    
    // Validate day of week if provided
    if (isset($update_data['day_of_week'])) {
        $valid_days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        if (!in_array($update_data['day_of_week'], $valid_days)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Invalid day of week']);
            return;
        }
    }
    
    // Validate time format if provided
    if (isset($update_data['start_time']) && !validateTime($update_data['start_time'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid start time format']);
        return;
    }
    
    if (isset($update_data['end_time']) && !validateTime($update_data['end_time'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid end time format']);
        return;
    }
    
    $success = $database->update('class_schedules', $update_data, 'id = :id AND user_id = :user_id', [
        ':id' => $schedule_id, 
        ':user_id' => $user_id
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Class schedule updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update class schedule']);
    }
}

function handleDeleteRequest($database, $user_id) {
    parse_str(file_get_contents('php://input'), $input);
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Schedule ID required']);
        return;
    }
    
    $schedule_id = (int)$input['id'];
    
    $success = $database->delete('class_schedules', 'id = :id AND user_id = :user_id', [
        ':id' => $schedule_id, 
        ':user_id' => $user_id
    ]);
    
    if ($success > 0) {
        echo json_encode(['success' => true, 'message' => 'Class schedule deleted successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Class schedule not found or already deleted']);
    }
}

function validateTime($time) {
    return (bool)preg_match('/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $time);
}

function hasTimeConflict($database, $user_id, $schedule_data) {
    $query = "SELECT id FROM class_schedules 
              WHERE user_id = :user_id 
              AND day_of_week = :day_of_week 
              AND (
                  (start_time <= :start_time AND end_time > :start_time) OR
                  (start_time < :end_time AND end_time >= :end_time) OR
                  (start_time >= :start_time AND end_time <= :end_time)
              )";
    
    $result = $database->queryRow($query, [
        ':user_id' => $user_id,
        ':day_of_week' => $schedule_data['day_of_week'],
        ':start_time' => $schedule_data['start_time'],
        ':end_time' => $schedule_data['end_time']
    ]);
    
    return !empty($result);
}
?>