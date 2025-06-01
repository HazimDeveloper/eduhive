<?php
// config/functions.php - Simple utility functions for EduHive

// Include required dependencies
require_once 'database.php';
require_once 'session.php';

/**
 * Get basic dashboard data for a user
 * @param int $user_id User ID
 * @return array Dashboard data
 */
function getDashboardData($user_id) {
    $database = new Database();
    $db = $database->getConnection();
    
    $data = [];
    
    try {
        // Get task statistics
        $task_query = "SELECT 
            COUNT(*) as total_tasks,
            SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_tasks
            FROM tasks WHERE user_id = :user_id";
        
        $stmt = $db->prepare($task_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $data['task_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get due today task
        $due_today_query = "SELECT title, due_date FROM tasks 
                           WHERE user_id = :user_id AND due_date = CURDATE() AND status != 'done' 
                           LIMIT 1";
        $stmt = $db->prepare($due_today_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $data['due_today_task'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get user progress (simple version)
        $progress_query = "SELECT total_badges FROM user_progress WHERE user_id = :user_id";
        $stmt = $db->prepare($progress_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $data['user_progress'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no progress record exists, create default
        if (!$data['user_progress']) {
            $data['user_progress'] = [
                'total_badges' => 0
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Error getting dashboard data: " . $e->getMessage());
        return [
            'task_stats' => [
                'total_tasks' => 0,
                'completed_tasks' => 0
            ],
            'due_today_task' => null,
            'user_progress' => [
                'total_badges' => 0
            ]
        ];
    }
    
    return $data;
}

/**
 * Get all tasks for a user
 * @param int $user_id User ID
 * @param string $status Filter by status (optional)
 * @return array Tasks data
 */
function getUserTasks($user_id, $status = null) {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        $where_clause = "WHERE t.user_id = :user_id";
        $params = [':user_id' => $user_id];
        
        if ($status) {
            $where_clause .= " AND t.status = :status";
            $params[':status'] = $status;
        }
        
        $query = "SELECT t.*, c.name as course_name 
                  FROM tasks t 
                  LEFT JOIN courses c ON t.course_id = c.id 
                  $where_clause 
                  ORDER BY t.due_date ASC, t.created_at DESC";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting user tasks: " . $e->getMessage());
        return [];
    }
}

/**
 * Create a new task
 * @param int $user_id User ID
 * @param array $task_data Task data
 * @return int|false Task ID or false on error
 */
function createTask($user_id, $task_data) {
    $database = new Database();
    
    $data = [
        'user_id' => $user_id,
        'title' => $task_data['title'],
        'description' => $task_data['description'] ?? '',
        'status' => $task_data['status'] ?? 'todo',
        'priority' => $task_data['priority'] ?? 'medium',
        'due_date' => $task_data['due_date'] ?? null,
        'course_id' => $task_data['course_id'] ?? null
    ];
    
    return $database->insert('tasks', $data);
}

/**
 * Update task
 * @param int $task_id Task ID
 * @param int $user_id User ID (for security)
 * @param array $task_data Updated task data
 * @return bool Success status
 */
function updateTask($task_id, $user_id, $task_data) {
    $database = new Database();
    
    // Check if task belongs to user
    $existing_task = $database->queryRow(
        "SELECT * FROM tasks WHERE id = :id AND user_id = :user_id",
        [':id' => $task_id, ':user_id' => $user_id]
    );
    
    if (!$existing_task) {
        return false;
    }
    
    // Prepare update data (only include non-null values)
    $data = array_filter([
        'title' => $task_data['title'] ?? null,
        'description' => $task_data['description'] ?? null,
        'status' => $task_data['status'] ?? null,
        'priority' => $task_data['priority'] ?? null,
        'due_date' => $task_data['due_date'] ?? null,
        'course_id' => $task_data['course_id'] ?? null
    ], function($value) {
        return $value !== null;
    });
    
    // Add completion timestamp if marking as done
    if (isset($data['status']) && $data['status'] === 'done' && $existing_task['status'] !== 'done') {
        $data['completed_at'] = date('Y-m-d H:i:s');
    }
    
    $success = $database->update('tasks', $data, 'id = :id AND user_id = :user_id', [':id' => $task_id, ':user_id' => $user_id]);
    
    return $success > 0;
}

/**
 * Delete task
 * @param int $task_id Task ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function deleteTask($task_id, $user_id) {
    $database = new Database();
    
    $success = $database->delete('tasks', 'id = :id AND user_id = :user_id', [':id' => $task_id, ':user_id' => $user_id]);
    
    return $success > 0;
}

/**
 * Get courses for a user
 * @param int $user_id User ID
 * @return array Courses data
 */
function getUserCourses($user_id) {
    $database = new Database();
    
    return $database->query(
        "SELECT c.*, COUNT(t.id) as task_count 
         FROM courses c 
         LEFT JOIN tasks t ON c.id = t.course_id 
         WHERE c.user_id = :user_id 
         GROUP BY c.id 
         ORDER BY c.name",
        [':user_id' => $user_id]
    );
}

/**
 * Create a new course
 * @param int $user_id User ID
 * @param array $course_data Course data
 * @return int|false Course ID or false on error
 */
function createCourse($user_id, $course_data) {
    $database = new Database();
    
    $data = [
        'user_id' => $user_id,
        'name' => $course_data['name'],
        'code' => $course_data['code'] ?? '',
        'description' => $course_data['description'] ?? '',
        'color' => $course_data['color'] ?? '#8B7355'
    ];
    
    return $database->insert('courses', $data);
}

/**
 * Clean and validate input data
 * @param string $data Input data
 * @return string Cleaned data
 */
function cleanInput($data) {
    if (is_array($data)) {
        return array_map('cleanInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate email address
 * @param string $email Email to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Check password strength (simple version)
 * @param string $password Password to check
 * @param int $min_length Minimum length (default: 6)
 * @return array Validation result with success and message
 */
function checkPassword($password, $min_length = 6) {
    if (strlen($password) < $min_length) {
        return ['valid' => false, 'message' => "Password must be at least {$min_length} characters long"];
    }
    
    return ['valid' => true, 'message' => 'Password is valid'];
}

/**
 * Format date for display
 * @param string $date Date string
 * @param string $format Date format
 * @return string Formatted date
 */
function formatDate($date, $format = 'M j, Y') {
    if (empty($date)) return '';
    
    try {
        $datetime = new DateTime($date);
        return $datetime->format($format);
    } catch (Exception $e) {
        return $date; // Return original if formatting fails
    }
}

/**
 * Calculate time ago string
 * @param string $datetime DateTime string
 * @return string Time ago string
 */
function timeAgo($datetime) {
    if (empty($datetime)) return '';
    
    try {
        $time = time() - strtotime($datetime);
        
        if ($time < 60) return 'just now';
        if ($time < 3600) return floor($time/60) . ' minutes ago';
        if ($time < 86400) return floor($time/3600) . ' hours ago';
        if ($time < 2592000) return floor($time/86400) . ' days ago';
        
        return date('M j, Y', strtotime($datetime));
        
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * Simple JSON response helper
 * @param bool $success Success status
 * @param string $message Response message
 * @param array $data Additional data
 */
function sendJsonResponse($success, $message = '', $data = []) {
    header('Content-Type: application/json');
    
    $response = [
        'success' => $success,
        'message' => $message
    ];
    
    if (!empty($data)) {
        $response['data'] = $data;
    }
    
    echo json_encode($response);
    exit();
}

/**
 * Check if request is AJAX
 * @return bool True if AJAX request
 */
function isAjaxRequest() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

/**
 * Get basic application settings
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value
 */
function getBasicSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        $settings = [
            'app_name' => 'EduHive',
            'registration_enabled' => true,
            'email_verification_required' => false
        ];
        
        try {
            $database = new Database();
            $results = $database->query("SELECT setting_key, setting_value FROM settings");
            
            if (is_array($results)) {
                foreach ($results as $setting) {
                    $settings[$setting['setting_key']] = $setting['setting_value'];
                }
            }
        } catch (Exception $e) {
            // Use default values if settings table doesn't exist
        }
    }
    
    return $settings[$key] ?? $default;
}

/**
 * Simple debug function (only in development)
 * @param mixed $data Data to debug
 */
function simpleDebug($data) {
    if (getenv('APP_ENV') !== 'production') {
        echo '<pre style="background: #f4f4f4; padding: 10px; border: 1px solid #ddd; margin: 10px 0;">';
        print_r($data);
        echo '</pre>';
    }
}

?>