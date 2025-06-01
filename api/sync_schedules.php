<?php
// api/sync_schedules.php - Sync class schedules to Google Calendar
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
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

try {
    $database = new Database();
    
    // Get all class schedules for the user
    $query = "SELECT cs.*, c.name as course_name, c.code as course_code, c.color as course_color
              FROM class_schedules cs 
              LEFT JOIN courses c ON cs.course_id = c.id 
              WHERE cs.user_id = :user_id 
              ORDER BY cs.day_of_week, cs.start_time";
    
    $schedules = $database->query($query, [':user_id' => $user_id]);
    
    if ($schedules) {
        // Format schedules for Google Calendar integration
        $formatted_schedules = [];
        
        foreach ($schedules as $schedule) {
            $formatted_schedules[] = [
                'id' => $schedule['id'],
                'class_code' => $schedule['class_code'],
                'course_name' => $schedule['course_name'] ?: 'Unknown Course',
                'course_code' => $schedule['course_code'] ?: $schedule['class_code'],
                'day_of_week' => $schedule['day_of_week'],
                'start_time' => $schedule['start_time'],
                'end_time' => $schedule['end_time'],
                'location' => $schedule['location'] ?: 'No location specified',
                'mode' => $schedule['mode'] ?: 'physical',
                'instructor' => $schedule['instructor'] ?: 'No instructor specified',
                'color' => $schedule['course_color'] ?: '#8B7355'
            ];
        }
        
        echo json_encode([
            'success' => true, 
            'schedules' => $formatted_schedules,
            'count' => count($formatted_schedules)
        ]);
        
    } else {
        echo json_encode([
            'success' => true, 
            'schedules' => [],
            'count' => 0,
            'message' => 'No class schedules found'
        ]);
    }
    
} catch (Exception $e) {
    error_log("Sync schedules API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Failed to retrieve class schedules'
    ]);
}
?>