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

<?php
// Add these functions to the end of config/functions.php

/**
 * Get time tracking statistics for a user
 * @param int $user_id User ID
 * @return array Time tracking statistics
 */
function getTimeTrackingStats($user_id) {
    $database = new Database();
    
    try {
        // Get ongoing tasks count
        $ongoing_query = "SELECT COUNT(*) as ongoing_tasks FROM tasks WHERE user_id = :user_id AND status IN ('todo', 'progress')";
        $ongoing_result = $database->queryRow($ongoing_query, [':user_id' => $user_id]);
        
        // Get total time in seconds
        $time_query = "SELECT SUM(duration) as total_duration FROM time_entries WHERE user_id = :user_id AND duration IS NOT NULL";
        $time_result = $database->queryRow($time_query, [':user_id' => $user_id]);
        
        $total_seconds = $time_result['total_duration'] ?? 0;
        $hours = floor($total_seconds / 3600);
        $minutes = floor(($total_seconds % 3600) / 60);
        
        return [
            'ongoing_tasks' => $ongoing_result['ongoing_tasks'] ?? 0,
            'total_hours' => $hours,
            'total_minutes' => $minutes,
            'formatted_time' => sprintf('%dH %dM', $hours, $minutes)
        ];
        
    } catch (Exception $e) {
        error_log("Error getting time tracking stats: " . $e->getMessage());
        return [
            'ongoing_tasks' => 0,
            'total_hours' => 0,
            'total_minutes' => 0,
            'formatted_time' => '0H 0M'
        ];
    }
}

/**
 * Get time entries for a user
 * @param int $user_id User ID
 * @param int $limit Number of entries to return
 * @return array Time entries
 */
function getTimeEntries($user_id, $limit = 50) {
    $database = new Database();
    
    try {
        $query = "SELECT te.*, t.title as task_title, c.name as course_name, c.code as course_code
                  FROM time_entries te
                  LEFT JOIN tasks t ON te.task_id = t.id
                  LEFT JOIN courses c ON te.course_id = c.id
                  WHERE te.user_id = :user_id
                  ORDER BY te.date DESC, te.created_at DESC
                  LIMIT :limit";
        
        $stmt = $database->getConnection()->prepare($query);
        $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
    } catch (Exception $e) {
        error_log("Error getting time entries: " . $e->getMessage());
        return [];
    }
}

/**
 * Start a new time entry
 * @param int $user_id User ID
 * @param array $entry_data Time entry data
 * @return int|false Time entry ID or false on error
 */
function startTimeEntry($user_id, $entry_data) {
    $database = new Database();
    
    try {
        $data = [
            'user_id' => $user_id,
            'title' => cleanInput($entry_data['title']),
            'description' => cleanInput($entry_data['description'] ?? ''),
            'task_id' => !empty($entry_data['task_id']) ? (int)$entry_data['task_id'] : null,
            'course_id' => !empty($entry_data['course_id']) ? (int)$entry_data['course_id'] : null,
            'start_time' => date('Y-m-d H:i:s'),
            'date' => date('Y-m-d')
        ];
        
        return $database->insert('time_entries', $data);
        
    } catch (Exception $e) {
        error_log("Error starting time entry: " . $e->getMessage());
        return false;
    }
}

/**
 * Stop a time entry
 * @param int $entry_id Time entry ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function stopTimeEntry($entry_id, $user_id) {
    $database = new Database();
    
    try {
        // Get the time entry
        $entry = $database->queryRow(
            "SELECT * FROM time_entries WHERE id = :id AND user_id = :user_id AND end_time IS NULL",
            [':id' => $entry_id, ':user_id' => $user_id]
        );
        
        if (!$entry) {
            return false;
        }
        
        $end_time = date('Y-m-d H:i:s');
        $start_time = $entry['start_time'];
        
        // Calculate duration in seconds
        $duration = strtotime($end_time) - strtotime($start_time);
        
        $update_data = [
            'end_time' => $end_time,
            'duration' => $duration
        ];
        
        $success = $database->update('time_entries', $update_data, 'id = :id AND user_id = :user_id', [
            ':id' => $entry_id, 
            ':user_id' => $user_id
        ]);
        
        return $success > 0;
        
    } catch (Exception $e) {
        error_log("Error stopping time entry: " . $e->getMessage());
        return false;
    }
}

/**
 * Get active time entry for user
 * @param int $user_id User ID
 * @return array|null Active time entry or null
 */
function getActiveTimeEntry($user_id) {
    $database = new Database();
    
    try {
        return $database->queryRow(
            "SELECT * FROM time_entries WHERE user_id = :user_id AND end_time IS NULL ORDER BY start_time DESC LIMIT 1",
            [':user_id' => $user_id]
        );
    } catch (Exception $e) {
        error_log("Error getting active time entry: " . $e->getMessage());
        return null;
    }
}

/**
 * Delete a time entry
 * @param int $entry_id Time entry ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function deleteTimeEntry($entry_id, $user_id) {
    $database = new Database();
    
    try {
        $success = $database->delete('time_entries', 'id = :id AND user_id = :user_id', [
            ':id' => $entry_id, 
            ':user_id' => $user_id
        ]);
        
        return $success > 0;
        
    } catch (Exception $e) {
        error_log("Error deleting time entry: " . $e->getMessage());
        return false;
    }
}

/**
 * Format duration seconds to human readable format
 * @param int $seconds Duration in seconds
 * @return string Formatted duration
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return '00:00:' . str_pad($seconds, 2, '0', STR_PAD_LEFT);
    }
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

/**
 * Get task icon based on task/course name
 * @param string $name Task or course name
 * @return string Icon/emoji
 */
function getTaskIcon($name) {
    $name_lower = strtolower($name);
    
    if (strpos($name_lower, 'programming') !== false || strpos($name_lower, 'tp') !== false) {
        return '💻';
    } elseif (strpos($name_lower, 'fyp') !== false) {
        return '🎯';
    } elseif (strpos($name_lower, 'harta') !== false || strpos($name_lower, 'property') !== false) {
        return '🏠';
    } else {
        return '📋';
    }
}

/**
 * Get task color class based on task/course name
 * @param string $name Task or course name
 * @return string CSS class name
 */
function getTaskColorClass($name) {
    $name_lower = strtolower($name);
    
    if (strpos($name_lower, 'programming') !== false || strpos($name_lower, 'tp') !== false) {
        return 'programming-icon';
    } elseif (strpos($name_lower, 'fyp') !== false) {
        return 'fyp-icon';
    } elseif (strpos($name_lower, 'harta') !== false || strpos($name_lower, 'property') !== false) {
        return 'harta-icon';
    } else {
        return 'default-icon';
    }
}
?>

<?php
// Add these functions to the end of config/functions.php

/**
 * Get user progress and reward statistics
 * @param int $user_id User ID
 * @return array User progress data
 */
function getUserProgress($user_id) {
    $database = new Database();
    
    try {
        // Check if user_progress table exists, if not create default
        $progress_query = "SELECT * FROM user_progress WHERE user_id = :user_id";
        $progress = $database->queryRow($progress_query, [':user_id' => $user_id]);
        
        if (!$progress) {
            // Create default progress record
            $default_progress = [
                'user_id' => $user_id,
                'total_badges' => 0,
                'daily_streak' => 0,
                'total_points' => 0,
                'last_daily_claim' => null,
                'created_at' => date('Y-m-d H:i:s')
            ];
            
            // Try to insert, ignore if table doesn't exist
            try {
                $database->insert('user_progress', $default_progress);
                $progress = $default_progress;
            } catch (Exception $e) {
                // Table might not exist, return default values
                $progress = $default_progress;
            }
        }
        
        return $progress;
        
    } catch (Exception $e) {
        error_log("Error getting user progress: " . $e->getMessage());
        return [
            'user_id' => $user_id,
            'total_badges' => 0,
            'daily_streak' => 0,
            'total_points' => 0,
            'last_daily_claim' => null
        ];
    }
}

/**
 * Get user's current ranking among all users
 * @param int $user_id User ID
 * @return int User's rank
 */
function getUserRanking($user_id) {
    $database = new Database();
    
    try {
        // Calculate ranking based on completed tasks and points
        $task_stats = getTaskStatistics($user_id);
        $completed_tasks = $task_stats['done_tasks'] ?? 0;
        
        // Simple ranking: count users with more completed tasks
        $ranking_query = "SELECT COUNT(DISTINCT t.user_id) + 1 as user_rank
                         FROM tasks t 
                         WHERE t.status = 'done'
                         GROUP BY t.user_id
                         HAVING COUNT(t.id) > :completed_tasks";
        
        $result = $database->queryRow($ranking_query, [':completed_tasks' => $completed_tasks]);
        
        return $result['user_rank'] ?? 1;
        
    } catch (Exception $e) {
        error_log("Error getting user ranking: " . $e->getMessage());
        return 1; // Default to rank 1
    }
}

/**
 * Check reward eligibility for a user
 * @param int $user_id User ID
 * @return array Reward eligibility status
 */
function checkRewardEligibility($user_id) {
    $database = new Database();
    $progress = getUserProgress($user_id);
    $task_stats = getTaskStatistics($user_id);
    
    $rewards = [
        'daily' => [
            'eligible' => false,
            'claimed' => false,
            'title' => 'Daily reward',
            'description' => 'Check in daily to claim this reward',
            'points' => 10
        ],
        'first_task' => [
            'eligible' => false,
            'claimed' => false,
            'title' => 'Completed One Task',
            'description' => 'Complete your first task',
            'points' => 25
        ],
        'ten_tasks' => [
            'eligible' => false,
            'claimed' => false,
            'title' => 'Completed more than Ten Task',
            'description' => 'Complete 10 or more tasks',
            'points' => 100
        ]
    ];
    
    // Check daily reward eligibility
        $last_claim = $progress['last_daily_claim'] ?? null;
        $today = date('Y-m-d');
        
        if (!$last_claim || $last_claim !== $today) {
            $rewards['daily']['eligible'] = true;
        } else {
            $rewards['daily']['claimed'] = true;
        }
    
    // Check first task completion
    $completed_tasks = $task_stats['done_tasks'] ?? 0;
    if ($completed_tasks >= 1) {
        $rewards['first_task']['eligible'] = true;
        
        // Check if already claimed
        if (hasClaimedReward($user_id, 'first_task')) {
            $rewards['first_task']['claimed'] = true;
            $rewards['first_task']['eligible'] = false;
        }
    }
    
    // Check ten tasks completion
    if ($completed_tasks >= 10) {
        $rewards['ten_tasks']['eligible'] = true;
        
        // Check if already claimed
        if (hasClaimedReward($user_id, 'ten_tasks')) {
            $rewards['ten_tasks']['claimed'] = true;
            $rewards['ten_tasks']['eligible'] = false;
        }
    }
    
    return $rewards;
}

/**
 * Check if user has claimed a specific reward
 * @param int $user_id User ID
 * @param string $reward_type Reward type
 * @return bool True if claimed
 */
function hasClaimedReward($user_id, $reward_type) {
    $database = new Database();
    
    try {
        $query = "SELECT id FROM user_rewards WHERE user_id = :user_id AND reward_type = :reward_type";
        $result = $database->queryRow($query, [':user_id' => $user_id, ':reward_type' => $reward_type]);
        
        return !empty($result);
        
    } catch (Exception $e) {
        // Table might not exist, return false
        return false;
    }
}

/**
 * Claim a reward for user
 * @param int $user_id User ID
 * @param string $reward_type Reward type
 * @return array Result with success status and message
 */
function claimReward($user_id, $reward_type) {
    $database = new Database();
    
    try {
        $rewards = checkRewardEligibility($user_id);
        
        if (!isset($rewards[$reward_type])) {
            return ['success' => false, 'message' => 'Invalid reward type'];
        }
        
        $reward = $rewards[$reward_type];
        
        if ($reward['claimed']) {
            return ['success' => false, 'message' => 'Reward already claimed'];
        }
        
        if (!$reward['eligible']) {
            return ['success' => false, 'message' => 'Not eligible for this reward'];
        }
        
        // Record the reward claim
        try {
            $reward_data = [
                'user_id' => $user_id,
                'reward_type' => $reward_type,
                'points_earned' => $reward['points'],
                'claimed_at' => date('Y-m-d H:i:s')
            ];
            
            $database->insert('user_rewards', $reward_data);
        } catch (Exception $e) {
            // Table might not exist, continue anyway
            error_log("Could not insert into user_rewards table: " . $e->getMessage());
        }
        
        // Update user progress
        $progress = getUserProgress($user_id);
        $new_badges = ($progress['total_badges'] ?? 0) + 1;
        $new_points = ($progress['total_points'] ?? 0) + $reward['points'];
        
        $update_data = [
            'total_badges' => $new_badges,
            'total_points' => $new_points
        ];
        
        // Special handling for daily reward
        if ($reward_type === 'daily') {
            $update_data['last_daily_claim'] = date('Y-m-d');
            $update_data['daily_streak'] = ($progress['daily_streak'] ?? 0) + 1;
        }
        
        // Update or insert user progress
        try {
            $existing = $database->queryRow("SELECT id FROM user_progress WHERE user_id = :user_id", [':user_id' => $user_id]);
            
            if ($existing) {
                $database->update('user_progress', $update_data, 'user_id = :user_id', [':user_id' => $user_id]);
            } else {
                $update_data['user_id'] = $user_id;
                $database->insert('user_progress', $update_data);
            }
        } catch (Exception $e) {
            error_log("Could not update user_progress: " . $e->getMessage());
        }
        
        return [
            'success' => true, 
            'message' => 'Reward claimed successfully!',
            'reward' => $reward,
            'new_badges' => $new_badges,
            'points_earned' => $reward['points']
        ];
        
    } catch (Exception $e) {
        error_log("Error claiming reward: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error claiming reward'];
    }
}

/**
 * Get user's collected badges
 * @param int $user_id User ID
 * @return array Collection of badges
 */
function getUserBadges($user_id) {
    $rewards = checkRewardEligibility($user_id);
    $badges = [];
    
    foreach ($rewards as $type => $reward) {
        if ($reward['claimed']) {
            $badge = [
                'type' => $type,
                'title' => $reward['title'],
                'description' => $reward['description'],
                'icon' => getBadgeIcon($type),
                'color' => getBadgeColor($type)
            ];
            $badges[] = $badge;
        }
    }
    
    // Add some default badges for display
    $default_badges = [
        ['type' => 'daily', 'title' => 'Daily Achiever', 'icon' => '🏆', 'color' => '#FFD700'],
        ['type' => 'task', 'title' => 'Task Master', 'icon' => '🎯', 'color' => '#dc3545'],
        ['type' => 'time', 'title' => 'Time Keeper', 'icon' => '⏰', 'color' => '#17a2b8'],
        ['type' => 'streak', 'title' => '7-Day Streak', 'icon' => '🔥', 'color' => '#fd7e14'],
        ['type' => 'productivity', 'title' => 'Productivity Pro', 'icon' => '📈', 'color' => '#28a745'],
        ['type' => 'milestone', 'title' => 'Milestone', 'icon' => '🌟', 'color' => '#6f42c1']
    ];
    
    return array_merge($badges, array_slice($default_badges, 0, 6 - count($badges)));
}

/**
 * Get badge icon for reward type
 * @param string $type Reward type
 * @return string Badge icon
 */
function getBadgeIcon($type) {
    $icons = [
        'daily' => '🏆',
        'first_task' => '🎯',
        'ten_tasks' => '🌟',
        'time_keeper' => '⏰',
        'streak' => '🔥',
        'productivity' => '📈'
    ];
    
    return $icons[$type] ?? '🏅';
}

/**
 * Get badge color for reward type
 * @param string $type Reward type
 * @return string Color hex code
 */
function getBadgeColor($type) {
    $colors = [
        'daily' => '#FFD700',
        'first_task' => '#dc3545',
        'ten_tasks' => '#B8860B',
        'time_keeper' => '#17a2b8',
        'streak' => '#fd7e14',
        'productivity' => '#28a745'
    ];
    
    return $colors[$type] ?? '#8B7355';
}

/**
 * Get leaderboard data
 * @param int $limit Number of top users to return
 * @return array Leaderboard data
 */
function getLeaderboard($limit = 10) {
    $database = new Database();
    
    try {
        $query = "SELECT u.id, u.name, u.email,
                  COUNT(t.id) as completed_tasks,
                  COALESCE(up.total_points, 0) as total_points,
                  COALESCE(up.total_badges, 0) as total_badges
                  FROM users u
                  LEFT JOIN tasks t ON u.id = t.user_id AND t.status = 'done'
                  LEFT JOIN user_progress up ON u.id = up.user_id
                  WHERE u.status = 'active'
                  GROUP BY u.id, u.name, u.email, up.total_points, up.total_badges
                  ORDER BY completed_tasks DESC, total_points DESC
                  LIMIT :limit";
        
        $stmt = $database->getConnection()->prepare($query);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        
    } catch (Exception $e) {
        error_log("Error getting leaderboard: " . $e->getMessage());
        return [];
    }
}

/**
 * Initialize reward tables if they don't exist
 * @return bool Success status
 */
function initializeRewardTables() {
    $database = new Database();
    
    try {
        // Create user_progress table if it doesn't exist
        $progress_table = "CREATE TABLE IF NOT EXISTS user_progress (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            total_badges INT DEFAULT 0,
            total_points INT DEFAULT 0,
            daily_streak INT DEFAULT 0,
            last_daily_claim DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        // Create user_rewards table if it doesn't exist
        $rewards_table = "CREATE TABLE IF NOT EXISTS user_rewards (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            reward_type VARCHAR(50) NOT NULL,
            points_earned INT DEFAULT 0,
            claimed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_reward (user_id, reward_type),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $database->getConnection()->exec($progress_table);
        $database->getConnection()->exec($rewards_table);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error initializing reward tables: " . $e->getMessage());
        return false;
    }
}
?>

<?php
// Add these functions to the end of config/functions.php

/**
 * Get all team members for a user
 * @param int $user_id User ID
 * @return array Team members data
 */
function getUserTeamMembers($user_id) {
    $database = new Database();
    
    try {
        $query = "SELECT * FROM team_members 
                  WHERE user_id = :user_id AND status = 'active' 
                  ORDER BY group_name, created_at DESC";
        
        return $database->query($query, [':user_id' => $user_id]) ?: [];
        
    } catch (Exception $e) {
        error_log("Error getting team members: " . $e->getMessage());
        return [];
    }
}

/**
 * Get team members grouped by group name
 * @param int $user_id User ID
 * @return array Team members grouped by group
 */
function getTeamMembersGrouped($user_id) {
    $members = getUserTeamMembers($user_id);
    $grouped = [];
    
    foreach ($members as $member) {
        $group = $member['group_name'];
        if (!isset($grouped[$group])) {
            $grouped[$group] = [];
        }
        $grouped[$group][] = $member;
    }
    
    return $grouped;
}

/**
 * Add a new team member
 * @param int $user_id User ID
 * @param array $member_data Member data
 * @return int|false Member ID or false on error
 */
function addTeamMember($user_id, $member_data) {
    $database = new Database();
    
    try {
        // Check if email already exists for this user
        $check_query = "SELECT id FROM team_members 
                       WHERE user_id = :user_id AND email = :email AND status = 'active'";
        $existing = $database->queryRow($check_query, [
            ':user_id' => $user_id,
            ':email' => $member_data['email']
        ]);
        
        if ($existing) {
            return false; // Email already exists
        }
        
        $data = [
            'user_id' => $user_id,
            'name' => cleanInput($member_data['name']),
            'email' => cleanInput($member_data['email']),
            'role' => cleanInput($member_data['role'] ?? ''),
            'group_name' => cleanInput($member_data['group_name']),
            'status' => 'active'
        ];
        
        return $database->insert('team_members', $data);
        
    } catch (Exception $e) {
        error_log("Error adding team member: " . $e->getMessage());
        return false;
    }
}

/**
 * Update team member
 * @param int $member_id Member ID
 * @param int $user_id User ID (for security)
 * @param array $member_data Updated member data
 * @return bool Success status
 */
function updateTeamMember($member_id, $user_id, $member_data) {
    $database = new Database();
    
    try {
        // Check if member belongs to user
        $existing = $database->queryRow(
            "SELECT * FROM team_members WHERE id = :id AND user_id = :user_id AND status = 'active'",
            [':id' => $member_id, ':user_id' => $user_id]
        );
        
        if (!$existing) {
            return false;
        }
        
        // Check if email is being changed and if new email already exists
        if ($member_data['email'] !== $existing['email']) {
            $check_query = "SELECT id FROM team_members 
                           WHERE user_id = :user_id AND email = :email AND id != :id AND status = 'active'";
            $duplicate = $database->queryRow($check_query, [
                ':user_id' => $user_id,
                ':email' => $member_data['email'],
                ':id' => $member_id
            ]);
            
            if ($duplicate) {
                return false; // Email already exists
            }
        }
        
        $update_data = [
            'name' => cleanInput($member_data['name']),
            'email' => cleanInput($member_data['email']),
            'role' => cleanInput($member_data['role'] ?? ''),
            'group_name' => cleanInput($member_data['group_name'])
        ];
        
        $success = $database->update('team_members', $update_data, 'id = :id AND user_id = :user_id', [
            ':id' => $member_id, 
            ':user_id' => $user_id
        ]);
        
        return $success > 0;
        
    } catch (Exception $e) {
        error_log("Error updating team member: " . $e->getMessage());
        return false;
    }
}

/**
 * Delete team member (soft delete)
 * @param int $member_id Member ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function deleteTeamMember($member_id, $user_id) {
    $database = new Database();
    
    try {
        // Soft delete by setting status to inactive
        $success = $database->update('team_members', ['status' => 'inactive'], 'id = :id AND user_id = :user_id', [
            ':id' => $member_id, 
            ':user_id' => $user_id
        ]);
        
        return $success > 0;
        
    } catch (Exception $e) {
        error_log("Error deleting team member: " . $e->getMessage());
        return false;
    }
}

/**
 * Get team member by ID
 * @param int $member_id Member ID
 * @param int $user_id User ID (for security)
 * @return array|false Member data or false if not found
 */
function getTeamMember($member_id, $user_id) {
    $database = new Database();
    
    try {
        return $database->queryRow(
            "SELECT * FROM team_members WHERE id = :id AND user_id = :user_id AND status = 'active'",
            [':id' => $member_id, ':user_id' => $user_id]
        );
        
    } catch (Exception $e) {
        error_log("Error getting team member: " . $e->getMessage());
        return false;
    }
}

/**
 * Get team member statistics
 * @param int $user_id User ID
 * @return array Statistics
 */
function getTeamMemberStats($user_id) {
    $database = new Database();
    
    try {
        $stats_query = "SELECT 
            COUNT(*) as total_members,
            COUNT(DISTINCT group_name) as total_groups
            FROM team_members 
            WHERE user_id = :user_id AND status = 'active'";
        
        $stats = $database->queryRow($stats_query, [':user_id' => $user_id]);
        
        return $stats ?: [
            'total_members' => 0,
            'total_groups' => 0
        ];
        
    } catch (Exception $e) {
        error_log("Error getting team member stats: " . $e->getMessage());
        return [
            'total_members' => 0,
            'total_groups' => 0
        ];
    }
}

/**
 * Get user initials for avatar
 * @param string $name Full name
 * @return string Initials
 */
function getUserInitials($name) {
    $words = explode(' ', trim($name));
    $initials = '';
    
    foreach ($words as $word) {
        if (!empty($word)) {
            $initials .= strtoupper($word[0]);
        }
    }
    
    return substr($initials, 0, 2); // Return max 2 initials
}

/**
 * Format date for team member display
 * @param string $date Date string
 * @return string Formatted date
 */
function formatMemberDate($date) {
    if (empty($date)) return '';
    
    try {
        $datetime = new DateTime($date);
        return $datetime->format('M j, Y');
    } catch (Exception $e) {
        return $date;
    }
}
?>
<?php
// Add these functions to your config/functions.php file (or replace existing ones)

/**
 * Generate a secure password reset token
 * @return string Random token
 */
function generateResetToken() {
    return bin2hex(random_bytes(32));
}

/**
 * Send password recovery email (simplified version)
 * @param string $email User email
 * @return array Result with success status and message
 */
function sendPasswordRecovery($email) {
    $database = new Database();
    
    try {
        // Check if user exists
        $user_query = "SELECT id, name FROM users WHERE email = :email AND status = 'active'";
        $user = $database->queryRow($user_query, [':email' => $email]);
        
        if (!$user) {
            // Don't reveal if email exists or not for security
            return ['success' => true, 'message' => 'If this email exists in our system, you will receive a recovery link.'];
        }
        
        // Generate reset token
        $token = generateResetToken();
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour')); // Token expires in 1 hour
        
        // Create reset_tokens table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS reset_tokens (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            expires_at TIMESTAMP NOT NULL,
            used TINYINT(1) DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX(token),
            INDEX(user_id),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $database->getConnection()->exec($create_table);
        
        // Delete any existing tokens for this user
        $database->getConnection()->prepare("DELETE FROM reset_tokens WHERE user_id = ?")->execute([$user['id']]);
        
        // Insert new token
        $token_data = [
            'user_id' => $user['id'],
            'token' => $token,
            'expires_at' => $expires_at,
            'used' => 0
        ];
        
        $token_id = $database->insert('reset_tokens', $token_data);
        
        if ($token_id) {
            // In a real application, you would send an email here
            // For development, we'll return the reset link
            $base_url = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
            $base_url .= $_SERVER['HTTP_HOST'];
            $base_url .= dirname($_SERVER['PHP_SELF']);
            $reset_link = $base_url . "/reset_password.php?token=" . $token;
            
            // Log the reset link for development
            error_log("Password reset link for {$email}: {$reset_link}");
            
            return [
                'success' => true, 
                'message' => 'Recovery instructions have been sent to your email address.',
                'reset_link' => $reset_link // Remove this in production
            ];
        } else {
            return ['success' => false, 'message' => 'Failed to generate recovery link.'];
        }
        
    } catch (Exception $e) {
        error_log("Password recovery error: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error. Please try again later.'];
    }
}

/**
 * Validate password reset token (simplified)
 * @param string $token Reset token
 * @return array Token validation result
 */
function validateResetToken($token) {
    $database = new Database();
    
    try {
        $query = "SELECT rt.*, u.email, u.name 
                  FROM reset_tokens rt 
                  JOIN users u ON rt.user_id = u.id 
                  WHERE rt.token = :token 
                  AND rt.used = 0 
                  AND rt.expires_at > NOW() 
                  AND u.status = 'active'";
        
        $result = $database->queryRow($query, [':token' => $token]);
        
        if ($result) {
            return ['valid' => true, 'data' => $result];
        } else {
            return ['valid' => false, 'message' => 'Invalid or expired reset token.'];
        }
        
    } catch (Exception $e) {
        error_log("Token validation error: " . $e->getMessage());
        return ['valid' => false, 'message' => 'System error.'];
    }
}

/**
 * Reset user password with token (simplified)
 * @param string $token Reset token
 * @param string $new_password New password
 * @return array Reset result
 */
function resetPassword($token, $new_password) {
    $database = new Database();
    
    try {
        // Validate token first
        $token_validation = validateResetToken($token);
        
        if (!$token_validation['valid']) {
            return ['success' => false, 'message' => $token_validation['message']];
        }
        
        $token_data = $token_validation['data'];
        $user_id = $token_data['user_id'];
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Start transaction
        $db = $database->getConnection();
        $db->beginTransaction();
        
        // Update user password
        $update_success = $database->update('users', 
            ['password' => $hashed_password], 
            'id = :id', 
            [':id' => $user_id]
        );
        
        if ($update_success) {
            // Mark token as used
            $database->update('reset_tokens', 
                ['used' => 1], 
                'token = :token', 
                [':token' => $token]
            );
            
            $db->commit();
            
            return [
                'success' => true, 
                'message' => 'Password has been reset successfully. You can now log in with your new password.'
            ];
        } else {
            $db->rollback();
            return ['success' => false, 'message' => 'Failed to update password.'];
        }
        
    } catch (Exception $e) {
        if (isset($db)) {
            $db->rollback();
        }
        error_log("Password reset error: " . $e->getMessage());
        return ['success' => false, 'message' => 'System error. Please try again later.'];
    }
}

/**
 * Clean up expired reset tokens
 * @return int Number of tokens cleaned up
 */
function cleanupExpiredTokens() {
    $database = new Database();
    
    try {
        $db = $database->getConnection();
        $stmt = $db->prepare("DELETE FROM reset_tokens WHERE expires_at < NOW() OR used = 1");
        $stmt->execute();
        return $stmt->rowCount();
    } catch (Exception $e) {
        error_log("Token cleanup error: " . $e->getMessage());
        return 0;
    }
}
?>
<?php
// Add this function to the end of your existing config/functions.php file

/**
 * Get the next upcoming reminder for dashboard
 * @param int $user_id User ID
 * @return array|null Reminder data or null if no upcoming reminders
 */
function getUpcomingReminder($user_id) {
    $database = new Database();
    
    try {
        // First, check for upcoming events (next 7 days)
        $events_query = "SELECT 
            title,
            start_datetime,
            event_type,
            location
            FROM events 
            WHERE user_id = :user_id 
            AND start_datetime >= NOW() 
            AND start_datetime <= DATE_ADD(NOW(), INTERVAL 7 DAY)
            ORDER BY start_datetime ASC 
            LIMIT 1";
        
        $event = $database->queryRow($events_query, [':user_id' => $user_id]);
        
        if ($event) {
            $start_time = new DateTime($event['start_datetime']);
            return [
                'title' => $event['title'],
                'time' => $start_time->format('g:i A'),
                'date' => $start_time->format('j/n/Y'),
                'type' => 'event'
            ];
        }
        
        // Second, check for upcoming tasks with due dates
        $tasks_query = "SELECT 
            title,
            due_date,
            priority
            FROM tasks 
            WHERE user_id = :user_id 
            AND status != 'done'
            AND due_date IS NOT NULL
            AND due_date >= CURDATE()
            AND due_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            ORDER BY due_date ASC, priority DESC
            LIMIT 1";
        
        $task = $database->queryRow($tasks_query, [':user_id' => $user_id]);
        
        if ($task) {
            $due_date = new DateTime($task['due_date']);
            return [
                'title' => $task['title'] . ' (Task Due)',
                'time' => 'All day',
                'date' => $due_date->format('j/n/Y'),
                'type' => 'task'
            ];
        }
        
        // Third, check for next class from schedule
        $today = strtolower(date('l'));
        $current_time = date('H:i:s');
        
        // Get next class today (if any)
        $class_today_query = "SELECT 
            cs.class_code,
            cs.start_time,
            cs.end_time,
            cs.location,
            c.name as course_name
            FROM class_schedules cs
            LEFT JOIN courses c ON cs.course_id = c.id
            WHERE cs.user_id = :user_id 
            AND cs.day_of_week = :today
            AND cs.start_time > :current_time
            ORDER BY cs.start_time ASC
            LIMIT 1";
        
        $class_today = $database->queryRow($class_today_query, [
            ':user_id' => $user_id,
            ':today' => $today,
            ':current_time' => $current_time
        ]);
        
        if ($class_today) {
            $start_time = new DateTime($class_today['start_time']);
            $end_time = new DateTime($class_today['end_time']);
            
            return [
                'title' => $class_today['course_name'] ?: $class_today['class_code'],
                'time' => $start_time->format('g:i A') . ' - ' . $end_time->format('g:i A'),
                'date' => 'Today',
                'type' => 'class'
            ];
        }
        
        // If no class today, get next class in upcoming days
        $days_order = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
        $today_index = array_search($today, $days_order);
        
        // Check next 7 days
        for ($i = 1; $i <= 7; $i++) {
            $next_day_index = ($today_index + $i) % 7;
            $next_day = $days_order[$next_day_index];
            
            $next_class_query = "SELECT 
                cs.class_code,
                cs.start_time,
                cs.end_time,
                cs.location,
                cs.day_of_week,
                c.name as course_name
                FROM class_schedules cs
                LEFT JOIN courses c ON cs.course_id = c.id
                WHERE cs.user_id = :user_id 
                AND cs.day_of_week = :day
                ORDER BY cs.start_time ASC
                LIMIT 1";
            
            $next_class = $database->queryRow($next_class_query, [
                ':user_id' => $user_id,
                ':day' => $next_day
            ]);
            
            if ($next_class) {
                $start_time = new DateTime($next_class['start_time']);
                $end_time = new DateTime($next_class['end_time']);
                
                // Calculate the actual date
                $target_date = new DateTime();
                $target_date->modify("+$i days");
                
                return [
                    'title' => $next_class['course_name'] ?: $next_class['class_code'],
                    'time' => $start_time->format('g:i A') . ' - ' . $end_time->format('g:i A'),
                    'date' => $target_date->format('j/n/Y'),
                    'type' => 'class'
                ];
            }
        }
        
        // No reminders found
        return null;
        
    } catch (Exception $e) {
        error_log("Error getting upcoming reminder: " . $e->getMessage());
        return null;
    }
}
?>