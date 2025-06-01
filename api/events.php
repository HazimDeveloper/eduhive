<?php
// api/events.php - Events API endpoint for calendar
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
    error_log("Events API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handleGetRequest($database, $user_id) {
    if (isset($_GET['id'])) {
        // Get single event
        $event_id = (int)$_GET['id'];
        $query = "SELECT e.*, c.name as course_name, c.code as course_code 
                  FROM events e 
                  LEFT JOIN courses c ON e.course_id = c.id 
                  WHERE e.id = :id AND e.user_id = :user_id";
        
        $event = $database->queryRow($query, [':id' => $event_id, ':user_id' => $user_id]);
        
        if ($event) {
            echo json_encode(['success' => true, 'data' => $event]);
        } else {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Event not found']);
        }
        
    } elseif (isset($_GET['export']) && $_GET['export'] === 'ical') {
        // Export calendar as iCal
        exportToiCal($database, $user_id);
        
    } else {
        // Get events for a date range
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        $query = "SELECT e.*, c.name as course_name, c.code as course_code, c.color as course_color 
                  FROM events e 
                  LEFT JOIN courses c ON e.course_id = c.id 
                  WHERE e.user_id = :user_id 
                  AND DATE(e.start_datetime) BETWEEN :start_date AND :end_date
                  ORDER BY e.start_datetime ASC";
        
        $events = $database->query($query, [
            ':user_id' => $user_id,
            ':start_date' => $start_date,
            ':end_date' => $end_date
        ]);
        
        echo json_encode(['success' => true, 'data' => $events ?: []]);
    }
}

function handlePostRequest($database, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // If no JSON input, try form data
    if (!$input) {
        $input = $_POST;
    }
    
    // Validate required fields
    $required_fields = ['title', 'start_datetime', 'event_type'];
    foreach ($required_fields as $field) {
        if (empty($input[$field])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => "Missing required field: $field"]);
            return;
        }
    }
    
    // Prepare event data
    $event_data = [
        'user_id' => $user_id,
        'title' => cleanInput($input['title']),
        'description' => cleanInput($input['description'] ?? ''),
        'start_datetime' => $input['start_datetime'],
        'end_datetime' => $input['end_datetime'] ?? $input['start_datetime'],
        'location' => cleanInput($input['location'] ?? ''),
        'event_type' => $input['event_type'],
        'course_id' => !empty($input['course_id']) ? (int)$input['course_id'] : null,
        'google_event_id' => $input['google_event_id'] ?? null,
        'color' => $input['color'] ?? '#8B7355'
    ];
    
    // Validate datetime format
    if (!validateDateTime($event_data['start_datetime']) || !validateDateTime($event_data['end_datetime'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid datetime format']);
        return;
    }
    
    // Insert event
    $event_id = $database->insert('events', $event_data);
    
    if ($event_id) {
        echo json_encode(['success' => true, 'message' => 'Event created successfully', 'id' => $event_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create event']);
    }
}

function handlePutRequest($database, $user_id) {
    parse_str(file_get_contents('php://input'), $input);
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID required']);
        return;
    }
    
    $event_id = (int)$input['id'];
    
    // Check if event belongs to user
    $existing_event = $database->queryRow(
        "SELECT id FROM events WHERE id = :id AND user_id = :user_id",
        [':id' => $event_id, ':user_id' => $user_id]
    );
    
    if (!$existing_event) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found']);
        return;
    }
    
    // Prepare update data (only include provided fields)
    $update_data = [];
    $allowed_fields = ['title', 'description', 'start_datetime', 'end_datetime', 'location', 'event_type', 'course_id', 'color'];
    
    foreach ($allowed_fields as $field) {
        if (isset($input[$field])) {
            if (in_array($field, ['start_datetime', 'end_datetime']) && !validateDateTime($input[$field])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => "Invalid datetime format for $field"]);
                return;
            }
            $update_data[$field] = $field === 'course_id' ? (int)$input[$field] : cleanInput($input[$field]);
        }
    }
    
    if (empty($update_data)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No data to update']);
        return;
    }
    
    $success = $database->update('events', $update_data, 'id = :id AND user_id = :user_id', [
        ':id' => $event_id, 
        ':user_id' => $user_id
    ]);
    
    if ($success) {
        echo json_encode(['success' => true, 'message' => 'Event updated successfully']);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to update event']);
    }
}

function handleDeleteRequest($database, $user_id) {
    parse_str(file_get_contents('php://input'), $input);
    
    if (empty($input['id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Event ID required']);
        return;
    }
    
    $event_id = (int)$input['id'];
    
    $success = $database->delete('events', 'id = :id AND user_id = :user_id', [
        ':id' => $event_id, 
        ':user_id' => $user_id
    ]);
    
    if ($success > 0) {
        echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
    } else {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Event not found or already deleted']);
    }
}

function validateDateTime($datetime) {
    return (bool)strtotime($datetime);
}

function exportToiCal($database, $user_id) {
    $year = $_GET['year'] ?? date('Y');
    $month = $_GET['month'] ?? date('n');
    
    $start_date = sprintf('%04d-%02d-01', $year, $month);
    $end_date = date('Y-m-t', strtotime($start_date));
    
    $query = "SELECT e.*, c.name as course_name 
              FROM events e 
              LEFT JOIN courses c ON e.course_id = c.id 
              WHERE e.user_id = :user_id 
              AND DATE(e.start_datetime) BETWEEN :start_date AND :end_date
              ORDER BY e.start_datetime ASC";
    
    $events = $database->query($query, [
        ':user_id' => $user_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="eduhive_calendar_' . $year . '_' . $month . '.ics"');
    
    echo "BEGIN:VCALENDAR\r\n";
    echo "VERSION:2.0\r\n";
    echo "PRODID:-//EduHive//Calendar//EN\r\n";
    echo "CALSCALE:GREGORIAN\r\n";
    
    foreach ($events as $event) {
        $start_datetime = new DateTime($event['start_datetime']);
        $end_datetime = new DateTime($event['end_datetime']);
        
        echo "BEGIN:VEVENT\r\n";
        echo "UID:" . $event['id'] . "@eduhive.com\r\n";
        echo "DTSTART:" . $start_datetime->format('Ymd\THis\Z') . "\r\n";
        echo "DTEND:" . $end_datetime->format('Ymd\THis\Z') . "\r\n";
        echo "SUMMARY:" . str_replace(["\r", "\n"], "\\n", $event['title']) . "\r\n";
        
        if (!empty($event['description'])) {
            echo "DESCRIPTION:" . str_replace(["\r", "\n"], "\\n", $event['description']) . "\r\n";
        }
        
        if (!empty($event['location'])) {
            echo "LOCATION:" . str_replace(["\r", "\n"], "\\n", $event['location']) . "\r\n";
        }
        
        echo "CATEGORIES:" . strtoupper($event['event_type']) . "\r\n";
        echo "CREATED:" . date('Ymd\THis\Z') . "\r\n";
        echo "LAST-MODIFIED:" . date('Ymd\THis\Z') . "\r\n";
        echo "END:VEVENT\r\n";
    }
    
    echo "END:VCALENDAR\r\n";
    exit();
}
?>