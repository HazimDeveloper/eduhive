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

<?php
// Additional functions to add to config/functions.php for class schedule support

/**
 * Get all class schedules for a user with course information
 * @param int $user_id User ID
 * @return array Class schedules data
 */
function getUserClassSchedules($user_id) {
    $database = new Database();
    
    return $database->query(
        "SELECT cs.*, c.name as course_name, c.code as course_code, c.color as course_color
         FROM class_schedules cs 
         LEFT JOIN courses c ON cs.course_id = c.id 
         WHERE cs.user_id = :user_id 
         ORDER BY 
         FIELD(cs.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
         cs.start_time",
        [':user_id' => $user_id]
    ) ?: [];
}

/**
 * Create a new class schedule
 * @param int $user_id User ID
 * @param array $schedule_data Schedule data
 * @return int|false Schedule ID or false on error
 */
function createClassSchedule($user_id, $schedule_data) {
    $database = new Database();
    
    $data = [
        'user_id' => $user_id,
        'class_code' => $schedule_data['class_code'],
        'day_of_week' => $schedule_data['day_of_week'],
        'start_time' => $schedule_data['start_time'],
        'end_time' => $schedule_data['end_time'],
        'location' => $schedule_data['location'] ?? '',
        'mode' => $schedule_data['mode'] ?? 'physical',
        'instructor' => $schedule_data['instructor'] ?? '',
        'course_id' => $schedule_data['course_id'] ?? null
    ];
    
    return $database->insert('class_schedules', $data);
}

/**
 * Update class schedule
 * @param int $schedule_id Schedule ID
 * @param int $user_id User ID (for security)
 * @param array $schedule_data Updated schedule data
 * @return bool Success status
 */
function updateClassSchedule($schedule_id, $user_id, $schedule_data) {
    $database = new Database();
    
    // Check if schedule belongs to user
    $existing_schedule = $database->queryRow(
        "SELECT * FROM class_schedules WHERE id = :id AND user_id = :user_id",
        [':id' => $schedule_id, ':user_id' => $user_id]
    );
    
    if (!$existing_schedule) {
        return false;
    }
    
    // Prepare update data (only include non-null values)
    $data = array_filter([
        'class_code' => $schedule_data['class_code'] ?? null,
        'day_of_week' => $schedule_data['day_of_week'] ?? null,
        'start_time' => $schedule_data['start_time'] ?? null,
        'end_time' => $schedule_data['end_time'] ?? null,
        'location' => $schedule_data['location'] ?? null,
        'mode' => $schedule_data['mode'] ?? null,
        'instructor' => $schedule_data['instructor'] ?? null,
        'course_id' => $schedule_data['course_id'] ?? null
    ], function($value) {
        return $value !== null;
    });
    
    $success = $database->update('class_schedules', $data, 'id = :id AND user_id = :user_id', [':id' => $schedule_id, ':user_id' => $user_id]);
    
    return $success > 0;
}

/**
 * Delete class schedule
 * @param int $schedule_id Schedule ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function deleteClassSchedule($schedule_id, $user_id) {
    $database = new Database();
    
    $success = $database->delete('class_schedules', 'id = :id AND user_id = :user_id', [':id' => $schedule_id, ':user_id' => $user_id]);
    
    return $success > 0;
}

/**
 * Get class schedules for a specific day
 * @param int $user_id User ID
 * @param string $day_of_week Day of week (monday, tuesday, etc.)
 * @return array Class schedules for the day
 */
function getClassSchedulesForDay($user_id, $day_of_week) {
    $database = new Database();
    
    return $database->query(
        "SELECT cs.*, c.name as course_name, c.code as course_code 
         FROM class_schedules cs 
         LEFT JOIN courses c ON cs.course_id = c.id 
         WHERE cs.user_id = :user_id AND cs.day_of_week = :day_of_week 
         ORDER BY cs.start_time",
        [':user_id' => $user_id, ':day_of_week' => $day_of_week]
    ) ?: [];
}

/**
 * Check if there's a time conflict with existing schedules
 * @param int $user_id User ID
 * @param string $day_of_week Day of week
 * @param string $start_time Start time
 * @param string $end_time End time
 * @param int|null $exclude_id Schedule ID to exclude from check
 * @return bool True if conflict exists
 */
function hasScheduleConflict($user_id, $day_of_week, $start_time, $end_time, $exclude_id = null) {
    $database = new Database();
    
    $query = "SELECT id FROM class_schedules 
              WHERE user_id = :user_id 
              AND day_of_week = :day_of_week";
    
    $params = [
        ':user_id' => $user_id,
        ':day_of_week' => $day_of_week,
        ':start_time' => $start_time,
        ':end_time' => $end_time
    ];
    
    if ($exclude_id) {
        $query .= " AND id != :exclude_id";
        $params[':exclude_id'] = $exclude_id;
    }
    
    $query .= " AND (
                    (start_time <= :start_time AND end_time > :start_time) OR
                    (start_time < :end_time AND end_time >= :end_time) OR
                    (start_time >= :start_time AND end_time <= :end_time)
                )";
    
    $result = $database->queryRow($query, $params);
    
    return !empty($result);
}

/**
 * Get today's class schedule
 * @param int $user_id User ID
 * @return array Today's classes
 */
function getTodayClasses($user_id) {
    $today = strtolower(date('l')); // Get current day name in lowercase
    return getClassSchedulesForDay($user_id, $today);
}

/**
 * Get next upcoming class
 * @param int $user_id User ID
 * @return array|null Next class or null if none
 */
function getNextClass($user_id) {
    $database = new Database();
    $current_time = date('H:i:s');
    $current_day = strtolower(date('l'));
    
    // First, try to find a class today after current time
    $query = "SELECT cs.*, c.name as course_name, c.code as course_code 
              FROM class_schedules cs 
              LEFT JOIN courses c ON cs.course_id = c.id 
              WHERE cs.user_id = :user_id 
              AND cs.day_of_week = :current_day 
              AND cs.start_time > :current_time 
              ORDER BY cs.start_time ASC 
              LIMIT 1";
    
    $next_class = $database->queryRow($query, [
        ':user_id' => $user_id,
        ':current_day' => $current_day,
        ':current_time' => $current_time
    ]);
    
    if ($next_class) {
        return $next_class;
    }
    
    // If no class today, find next class in upcoming days
    $query = "SELECT cs.*, c.name as course_name, c.code as course_code 
              FROM class_schedules cs 
              LEFT JOIN courses c ON cs.course_id = c.id 
              WHERE cs.user_id = :user_id 
              ORDER BY 
              FIELD(cs.day_of_week, 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'),
              cs.start_time ASC 
              LIMIT 1";
    
    return $database->queryRow($query, [':user_id' => $user_id]);
}

/**
 * Format time for display
 * @param string $time Time in 24-hour format
 * @return string Formatted time
 */
function formatClassTime($time) {
    if (empty($time)) return '';
    
    try {
        $datetime = new DateTime($time);
        return $datetime->format('g:i A'); // 12-hour format with AM/PM
    } catch (Exception $e) {
        return $time; // Return original if formatting fails
    }
}

/**
 * Get week schedule summary
 * @param int $user_id User ID
 * @return array Week schedule organized by days
 */
function getWeekSchedule($user_id) {
    $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
    $week_schedule = [];
    
    foreach ($days as $day) {
        $week_schedule[$day] = getClassSchedulesForDay($user_id, $day);
    }
    
    return $week_schedule;
}
?>

<?php
// Additional functions to add to config/functions.php for enhanced task management

/**
 * Get task statistics for dashboard
 * @param int $user_id User ID
 * @return array Task statistics
 */
function getTaskStatistics($user_id) {
    $database = new Database();
    
    $query = "SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo_tasks,
        SUM(CASE WHEN status = 'progress' THEN 1 ELSE 0 END) as progress_tasks,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_tasks
        FROM tasks WHERE user_id = :user_id";
    
    $result = $database->queryRow($query, [':user_id' => $user_id]);
    
    return $result ?: [
        'total_tasks' => 0,
        'todo_tasks' => 0,
        'progress_tasks' => 0,
        'done_tasks' => 0
    ];
}

/**
 * Create a new task with enhanced data
 * @param int $user_id User ID
 * @param array $task_data Enhanced task data
 * @return int|false Task ID or false on error
 */
function createEnhancedTask($user_id, $task_data) {
    $database = new Database();
    
    $data = [
        'user_id' => $user_id,
        'title' => $task_data['title'],
        'description' => $task_data['description'] ?? '',
        'status' => $task_data['status'] ?? 'todo',
        'priority' => $task_data['priority'] ?? 'medium',
        'due_date' => $task_data['due_date'] ?? null,
        'start_time' => $task_data['start_time'] ?? null,
        'end_time' => $task_data['end_time'] ?? null,
        'course_id' => $task_data['course_id'] ?? null,
        'reminder_type' => $task_data['reminder_type'] ?? null
    ];
    
    return $database->insert('tasks', $data);
}

/**
 * Get tasks for a specific course
 * @param int $user_id User ID
 * @param int $course_id Course ID
 * @param string $status Filter by status (optional)
 * @return array Tasks for the course
 */
function getTasksForCourse($user_id, $course_id, $status = null) {
    $database = new Database();
    
    $query = "SELECT t.*, c.name as course_name 
              FROM tasks t 
              LEFT JOIN courses c ON t.course_id = c.id 
              WHERE t.user_id = :user_id AND t.course_id = :course_id";
    
    $params = [':user_id' => $user_id, ':course_id' => $course_id];
    
    if ($status) {
        $query .= " AND t.status = :status";
        $params[':status'] = $status;
    }
    
    $query .= " ORDER BY t.due_date ASC, t.created_at DESC";
    
    return $database->query($query, $params) ?: [];
}

/**
 * Get upcoming tasks (due within next 7 days)
 * @param int $user_id User ID
 * @return array Upcoming tasks
 */
function getUpcomingTasks($user_id) {
    $database = new Database();
    
    $query = "SELECT t.*, c.name as course_name 
              FROM tasks t 
              LEFT JOIN courses c ON t.course_id = c.id 
              WHERE t.user_id = :user_id 
              AND t.status != 'done' 
              AND t.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
              ORDER BY t.due_date ASC, t.priority DESC";
    
    return $database->query($query, [':user_id' => $user_id]) ?: [];
}

/**
 * Get overdue tasks
 * @param int $user_id User ID
 * @return array Overdue tasks
 */
function getOverdueTasks($user_id) {
    $database = new Database();
    
    $query = "SELECT t.*, c.name as course_name 
              FROM tasks t 
              LEFT JOIN courses c ON t.course_id = c.id 
              WHERE t.user_id = :user_id 
              AND t.status != 'done' 
              AND t.due_date < CURDATE()
              ORDER BY t.due_date ASC";
    
    return $database->query($query, [':user_id' => $user_id]) ?: [];
}

/**
 * Get task counts for each course
 * @param int $user_id User ID
 * @return array Course task counts
 */
function getCourseTaskCounts($user_id) {
    $database = new Database();
    
    $query = "SELECT c.id, c.name, c.code,
              COUNT(t.id) as total_tasks,
              SUM(CASE WHEN t.status = 'todo' THEN 1 ELSE 0 END) as pending_tasks,
              SUM(CASE WHEN t.status = 'progress' THEN 1 ELSE 0 END) as progress_tasks,
              SUM(CASE WHEN t.status = 'done' THEN 1 ELSE 0 END) as completed_tasks
              FROM courses c
              LEFT JOIN tasks t ON c.id = t.course_id
              WHERE c.user_id = :user_id
              GROUP BY c.id, c.name, c.code
              ORDER BY c.name";
    
    return $database->query($query, [':user_id' => $user_id]) ?: [];
}

/**
 * Update task status with automatic completion tracking
 * @param int $task_id Task ID
 * @param int $user_id User ID (for security)
 * @param string $new_status New status
 * @return bool Success status
 */
function updateTaskStatus($task_id, $user_id, $new_status) {
    $database = new Database();
    
    // Check if task belongs to user
    $existing_task = $database->queryRow(
        "SELECT * FROM tasks WHERE id = :id AND user_id = :user_id",
        [':id' => $task_id, ':user_id' => $user_id]
    );
    
    if (!$existing_task) {
        return false;
    }
    
    $update_data = ['status' => $new_status];
    
    // Add completion timestamp if marking as done
    if ($new_status === 'done' && $existing_task['status'] !== 'done') {
        $update_data['completed_at'] = date('Y-m-d H:i:s');
    } elseif ($new_status !== 'done' && $existing_task['status'] === 'done') {
        $update_data['completed_at'] = null;
    }
    
    $success = $database->update('tasks', $update_data, 'id = :id AND user_id = :user_id', [
        ':id' => $task_id, 
        ':user_id' => $user_id
    ]);
    
    return $success > 0;
}

/**
 * Get tasks due today
 * @param int $user_id User ID
 * @return array Today's tasks
 */
function getTodayTasks($user_id) {
    $database = new Database();
    
    $query = "SELECT t.*, c.name as course_name 
              FROM tasks t 
              LEFT JOIN courses c ON t.course_id = c.id 
              WHERE t.user_id = :user_id 
              AND DATE(t.due_date) = CURDATE()
              AND t.status != 'done'
              ORDER BY t.start_time ASC, t.priority DESC";
    
    return $database->query($query, [':user_id' => $user_id]) ?: [];
}

/**
 * Search tasks by title or description
 * @param int $user_id User ID
 * @param string $search_term Search term
 * @return array Matching tasks
 */
function searchTasks($user_id, $search_term) {
    $database = new Database();
    
    $query = "SELECT t.*, c.name as course_name 
              FROM tasks t 
              LEFT JOIN courses c ON t.course_id = c.id 
              WHERE t.user_id = :user_id 
              AND (t.title LIKE :search OR t.description LIKE :search)
              ORDER BY t.due_date ASC, t.created_at DESC";
    
    $search_param = '%' . $search_term . '%';
    
    return $database->query($query, [
        ':user_id' => $user_id,
        ':search' => $search_param
    ]) ?: [];
}

/**
 * Get task completion rate for a date range
 * @param int $user_id User ID
 * @param string $start_date Start date
 * @param string $end_date End date
 * @return array Completion statistics
 */
function getTaskCompletionRate($user_id, $start_date, $end_date) {
    $database = new Database();
    
    $query = "SELECT 
              COUNT(*) as total_tasks,
              SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_tasks
              FROM tasks 
              WHERE user_id = :user_id 
              AND due_date BETWEEN :start_date AND :end_date";
    
    $result = $database->queryRow($query, [
        ':user_id' => $user_id,
        ':start_date' => $start_date,
        ':end_date' => $end_date
    ]);
    
    if ($result && $result['total_tasks'] > 0) {
        $result['completion_rate'] = round(($result['completed_tasks'] / $result['total_tasks']) * 100, 2);
    } else {
        $result['completion_rate'] = 0;
    }
    
    return $result;
}

/**
 * Get next task to work on (smart prioritization)
 * @param int $user_id User ID
 * @return array|null Next recommended task
 */
function getNextRecommendedTask($user_id) {
    $database = new Database();
    
    // Priority: overdue tasks first, then due today, then by priority and due date
    $query = "SELECT t.*, c.name as course_name,
              CASE 
                WHEN t.due_date < CURDATE() THEN 1
                WHEN DATE(t.due_date) = CURDATE() THEN 2
                WHEN t.due_date <= DATE_ADD(CURDATE(), INTERVAL 3 DAY) THEN 3
                ELSE 4
              END as urgency_score
              FROM tasks t 
              LEFT JOIN courses c ON t.course_id = c.id 
              WHERE t.user_id = :user_id 
              AND t.status IN ('todo', 'progress')
              ORDER BY urgency_score ASC, 
                       FIELD(t.priority, 'high', 'medium', 'low') ASC,
                       t.due_date ASC
              LIMIT 1";
    
    return $database->queryRow($query, [':user_id' => $user_id]);
}

/**
 * Format task time for display
 * @param string $time Time string
 * @return string Formatted time
 */
function formatTaskTime($time) {
    if (empty($time)) return '';
    
    try {
        return date('g:i A', strtotime($time));
    } catch (Exception $e) {
        return $time;
    }
}

/**
 * Get task priority color
 * @param string $priority Priority level
 * @return string CSS color class
 */
function getTaskPriorityColor($priority) {
    switch (strtolower($priority)) {
        case 'high':
            return '#dc3545'; // Red
        case 'medium':
            return '#ffc107'; // Yellow
        case 'low':
            return '#28a745'; // Green
        default:
            return '#6c757d'; // Gray
    }
}

/**
 * Calculate task duration in hours
 * @param string $start_time Start time
 * @param string $end_time End time
 * @return float Duration in hours
 */
function calculateTaskDuration($start_time, $end_time) {
    if (empty($start_time) || empty($end_time)) {
        return 0;
    }
    
    try {
        $start = new DateTime($start_time);
        $end = new DateTime($end_time);
        $interval = $start->diff($end);
        
        return $interval->h + ($interval->i / 60);
    } catch (Exception $e) {
        return 0;
    }
}
?>