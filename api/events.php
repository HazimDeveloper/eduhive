<?php
// api/events.php - Enhanced Events API endpoint with auto-sync support
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
require_once '../setup_middleware.php';

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
        
    } elseif (isset($_GET['google_events_only'])) {
        // Get only Google Calendar events (for sync checking)
        $query = "SELECT id, google_event_id, start_datetime, end_datetime, title 
                  FROM events 
                  WHERE user_id = :user_id 
                  AND google_event_id IS NOT NULL 
                  AND google_event_id != ''
                  ORDER BY start_datetime ASC";
        
        $events = $database->query($query, [':user_id' => $user_id]);
        
        echo json_encode(['success' => true, 'data' => $events ?: []]);
        
    } elseif (isset($_GET['sync_status'])) {
        // Get sync status and statistics
        $sync_stats = getSyncStatistics($user_id);
        $google_config = getUserGoogleConfig($user_id);
        $auto_sync_enabled = hasValidGoogleConfig($user_id);
        
        echo json_encode([
            'success' => true,
            'data' => [
                'auto_sync_enabled' => $auto_sync_enabled,
                'google_configured' => !empty($google_config),
                'sync_statistics' => $sync_stats,
                'needs_setup' => needsGoogleReSetup($user_id)
            ]
        ]);
        
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
    
    // Handle bulk sync from Google Calendar
    if (isset($input['action']) && $input['action'] === 'bulk_sync_google') {
        handleBulkGoogleSync($database, $user_id, $input);
        return;
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
    
    // Check for duplicate Google events
    if (!empty($event_data['google_event_id'])) {
        $existing_query = "SELECT id FROM events WHERE user_id = :user_id AND google_event_id = :google_event_id";
        $existing = $database->queryRow($existing_query, [
            ':user_id' => $user_id,
            ':google_event_id' => $event_data['google_event_id']
        ]);
        
        if ($existing) {
            echo json_encode(['success' => false, 'message' => 'Event already exists', 'duplicate' => true]);
            return;
        }
    }
    
    // Insert event
    $event_id = $database->insert('events', $event_data);
    
    if ($event_id) {
        // Log the sync activity if this is from Google Calendar
        if (!empty($event_data['google_event_id'])) {
            logAutoSyncActivity($user_id, 'google_event_imported', 'success', "Event: " . $event_data['title']);
        }
        
        echo json_encode(['success' => true, 'message' => 'Event created successfully', 'id' => $event_id]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Failed to create event']);
    }
}

function handleBulkGoogleSync($database, $user_id, $input) {
    if (empty($input['events']) || !is_array($input['events'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No events provided for sync']);
        return;
    }
    
    $events = $input['events'];
    $imported_count = 0;
    $skipped_count = 0;
    $error_count = 0;
    
    foreach ($events as $google_event) {
        try {
            // Check if event already exists
            $existing_query = "SELECT id FROM events WHERE user_id = :user_id AND google_event_id = :google_event_id";
            $existing = $database->queryRow($existing_query, [
                ':user_id' => $user_id,
                ':google_event_id' => $google_event['id']
            ]);
            
            if ($existing) {
                $skipped_count++;
                continue;
            }
            
            // Prepare event data
            $event_data = [
                'user_id' => $user_id,
                'title' => cleanInput($google_event['summary'] ?? 'No title'),
                'description' => cleanInput($google_event['description'] ?? ''),
                'start_datetime' => $google_event['start']['dateTime'] ?? $google_event['start']['date'] . 'T09:00:00',
                'end_datetime' => $google_event['end']['dateTime'] ?? $google_event['end']['date'] . 'T10:00:00',
                'location' => cleanInput($google_event['location'] ?? ''),
                'event_type' => 'other',
                'google_event_id' => $google_event['id'],
                'color' => '#8B7355'
            ];
            
            // Insert event
            $event_id = $database->insert('events', $event_data);
            
            if ($event_id) {
                $imported_count++;
            } else {
                $error_count++;
            }
            
        } catch (Exception $e) {
            error_log("Error importing Google event: " . $e->getMessage());
            $error_count++;
        }
    }
    
    // Log bulk sync activity
    logAutoSyncActivity(
        $user_id, 
        'bulk_google_sync', 
        $error_count > 0 ? 'partial_success' : 'success',
        "Imported: $imported_count, Skipped: $skipped_count, Errors: $error_count"
    );
    
    echo json_encode([
        'success' => true,
        'message' => 'Bulk sync completed',
        'stats' => [
            'imported' => $imported_count,
            'skipped' => $skipped_count,
            'errors' => $error_count,
            'total_processed' => count($events)
        ]
    ]);
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
    echo "METHOD:PUBLISH\r\n";
    echo "X-WR-CALNAME:EduHive Calendar\r\n";
    echo "X-WR-TIMEZONE:Asia/Kuala_Lumpur\r\n";
    
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
        echo "STATUS:CONFIRMED\r\n";
        
        if (!empty($event['google_event_id'])) {
            echo "X-GOOGLE-EVENT-ID:" . $event['google_event_id'] . "\r\n";
        }
        
        echo "CREATED:" . date('Ymd\THis\Z') . "\r\n";
        echo "LAST-MODIFIED:" . date('Ymd\THis\Z') . "\r\n";
        echo "SEQUENCE:0\r\n";
        echo "END:VEVENT\r\n";
    }
    
    echo "END:VCALENDAR\r\n";
    exit();
}

/**
 * Enhanced event creation with auto-categorization
 */
function createEnhancedEvent($database, $user_id, $event_data) {
    // Auto-categorize events based on title/description
    if (!isset($event_data['event_type']) || $event_data['event_type'] === 'other') {
        $event_data['event_type'] = categorizeEvent($event_data['title'], $event_data['description'] ?? '');
    }
    
    // Auto-assign course if possible
    if (empty($event_data['course_id'])) {
        $event_data['course_id'] = findMatchingCourse($database, $user_id, $event_data['title']);
    }
    
    return $database->insert('events', $event_data);
}

/**
 * Auto-categorize events based on content
 */
function categorizeEvent($title, $description) {
    $title_lower = strtolower($title);
    $desc_lower = strtolower($description);
    $content = $title_lower . ' ' . $desc_lower;
    
    // Exam keywords
    if (preg_match('/\b(exam|test|quiz|midterm|final|assessment)\b/', $content)) {
        return 'exam';
    }
    
    // Assignment keywords
    if (preg_match('/\b(assignment|homework|project|report|submission|due)\b/', $content)) {
        return 'assignment';
    }
    
    // Class keywords
    if (preg_match('/\b(lecture|class|tutorial|lab|seminar|workshop)\b/', $content)) {
        return 'class';
    }
    
    // Meeting keywords
    if (preg_match('/\b(meeting|discussion|consultation|review|presentation)\b/', $content)) {
        return 'meeting';
    }
    
    return 'other';
}

/**
 * Find matching course based on event title
 */
function findMatchingCourse($database, $user_id, $title) {
    try {
        $query = "SELECT id FROM courses 
                  WHERE user_id = :user_id 
                  AND (LOWER(:title) LIKE CONCAT('%', LOWER(code), '%') 
                       OR LOWER(:title) LIKE CONCAT('%', LOWER(name), '%'))
                  LIMIT 1";
        
        $result = $database->queryRow($query, [
            ':user_id' => $user_id,
            ':title' => $title
        ]);
        
        return $result ? $result['id'] : null;
        
    } catch (Exception $e) {
        return null;
    }
}

/**
 * Clean up duplicate Google Calendar events
 */
function cleanupDuplicateGoogleEvents($database, $user_id) {
    try {
        // Find duplicate Google events (same google_event_id)
        $query = "SELECT google_event_id, COUNT(*) as count 
                  FROM events 
                  WHERE user_id = :user_id 
                  AND google_event_id IS NOT NULL 
                  AND google_event_id != ''
                  GROUP BY google_event_id 
                  HAVING count > 1";
        
        $duplicates = $database->query($query, [':user_id' => $user_id]);
        
        foreach ($duplicates as $duplicate) {
            // Keep the first one, delete the rest
            $delete_query = "DELETE FROM events 
                            WHERE user_id = :user_id 
                            AND google_event_id = :google_event_id 
                            AND id NOT IN (
                                SELECT * FROM (
                                    SELECT MIN(id) 
                                    FROM events 
                                    WHERE user_id = :user_id 
                                    AND google_event_id = :google_event_id
                                ) as keep_id
                            )";
            
            $database->query($delete_query, [
                ':user_id' => $user_id,
                ':google_event_id' => $duplicate['google_event_id']
            ]);
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error cleaning up duplicate events: " . $e->getMessage());
        return false;
    }
}

/**
 * Get calendar statistics
 */
function getCalendarStats($database, $user_id) {
    try {
        $stats_query = "SELECT 
            COUNT(*) as total_events,
            SUM(CASE WHEN google_event_id IS NOT NULL THEN 1 ELSE 0 END) as google_events,
            SUM(CASE WHEN event_type = 'class' THEN 1 ELSE 0 END) as class_events,
            SUM(CASE WHEN event_type = 'exam' THEN 1 ELSE 0 END) as exam_events,
            SUM(CASE WHEN event_type = 'assignment' THEN 1 ELSE 0 END) as assignment_events,
            COUNT(DISTINCT DATE(start_datetime)) as active_days
            FROM events 
            WHERE user_id = :user_id 
            AND start_datetime >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        return $database->queryRow($stats_query, [':user_id' => $user_id]);
        
    } catch (Exception $e) {
        error_log("Error getting calendar stats: " . $e->getMessage());
        return null;
    }
}
?>