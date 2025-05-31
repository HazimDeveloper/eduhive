<?php
// config/functions.php - Core utility functions for EduHive

// Include required dependencies
require_once 'database.php';
require_once 'session.php';

/**
 * Get dashboard data for a user
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
            SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo_tasks,
            SUM(CASE WHEN status = 'progress' THEN 1 ELSE 0 END) as progress_tasks,
            SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_tasks,
            SUM(CASE WHEN due_date = CURDATE() AND status != 'done' THEN 1 ELSE 0 END) as due_today
            FROM tasks WHERE user_id = :user_id";
        
        $stmt = $db->prepare($task_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $data['task_stats'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get due today task details
        $due_today_query = "SELECT title, due_date FROM tasks 
                           WHERE user_id = :user_id AND due_date = CURDATE() AND status != 'done' 
                           LIMIT 1";
        $stmt = $db->prepare($due_today_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $data['due_today_task'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Get upcoming events (next 7 days)
        $events_query = "SELECT title, start_date, end_date FROM events 
                        WHERE user_id = :user_id AND start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                        ORDER BY start_date LIMIT 5";
        $stmt = $db->prepare($events_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $data['upcoming_events'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Get user progress
        $progress_query = "SELECT total_badges, total_points, streak_days FROM user_progress WHERE user_id = :user_id";
        $stmt = $db->prepare($progress_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $data['user_progress'] = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // If no progress record exists, create default
        if (!$data['user_progress']) {
            $data['user_progress'] = [
                'total_badges' => 0,
                'total_points' => 0,
                'streak_days' => 0
            ];
        }
        
    } catch (PDOException $e) {
        error_log("Error getting dashboard data: " . $e->getMessage());
        return false;
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
    
    $task_id = $database->insert('tasks', $data);
    
    if ($task_id) {
        logActivity($user_id, 'task_created', "Created task: " . $task_data['title']);
        createNotification($user_id, 'web', 'Task Created', 'New task "' . $task_data['title'] . '" has been created.');
    }
    
    return $task_id;
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
    
    // Verify task belongs to user
    $existing_task = $database->queryRow(
        "SELECT * FROM tasks WHERE id = :id AND user_id = :user_id",
        [':id' => $task_id, ':user_id' => $user_id]
    );
    
    if (!$existing_task) {
        return false;
    }
    
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
        
        // Award points for completion
        updateUserProgress($user_id, ['total_points' => 10]);
        createNotification($user_id, 'web', 'Task Completed!', 'Congratulations! You completed "' . $existing_task['title'] . '"');
    }
    
    $success = $database->update('tasks', $data, 'id = :id AND user_id = :user_id', [':id' => $task_id, ':user_id' => $user_id]);
    
    if ($success) {
        logActivity($user_id, 'task_updated', "Updated task: " . $existing_task['title']);
    }
    
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
    
    // Get task details for logging
    $task = $database->queryRow(
        "SELECT title FROM tasks WHERE id = :id AND user_id = :user_id",
        [':id' => $task_id, ':user_id' => $user_id]
    );
    
    if (!$task) {
        return false;
    }
    
    $success = $database->delete('tasks', 'id = :id AND user_id = :user_id', [':id' => $task_id, ':user_id' => $user_id]);
    
    if ($success) {
        logActivity($user_id, 'task_deleted', "Deleted task: " . $task['title']);
    }
    
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
    
    $course_id = $database->insert('courses', $data);
    
    if ($course_id) {
        logActivity($user_id, 'course_created', "Created course: " . $course_data['name']);
    }
    
    return $course_id;
}

/**
 * Create a notification for a user
 * @param int $user_id User ID
 * @param string $type Notification type (web, email, sms)
 * @param string $title Notification title
 * @param string $message Notification message
 * @return bool Success status
 */
function createNotification($user_id, $type = 'web', $title = '', $message = '') {
    $database = new Database();
    
    try {
        $query = "INSERT INTO notifications (user_id, type, title, message, created_at) 
                  VALUES (:user_id, :type, :title, :message, NOW())";
        
        $stmt = $database->getConnection()->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        
        return $stmt->execute();
        
    } catch (PDOException $e) {
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get notifications for a user
 * @param int $user_id User ID
 * @param bool $unread_only Get only unread notifications
 * @param int $limit Limit number of notifications
 * @return array Notifications
 */
function getNotifications($user_id, $unread_only = false, $limit = 20) {
    $database = new Database();
    
    try {
        $where_clause = $unread_only ? "AND is_read = FALSE" : "";
        
        $query = "SELECT * FROM notifications 
                  WHERE user_id = :user_id $where_clause 
                  ORDER BY created_at DESC LIMIT :limit";
        
        $stmt = $database->getConnection()->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Mark notification as read
 * @param int $notification_id Notification ID
 * @param int $user_id User ID (for security)
 * @return bool Success status
 */
function markNotificationAsRead($notification_id, $user_id) {
    $database = new Database();
    
    try {
        $query = "UPDATE notifications SET is_read = TRUE, read_at = NOW() 
                  WHERE id = :notification_id AND user_id = :user_id";
        
        $stmt = $database->getConnection()->prepare($query);
        $stmt->bindParam(':notification_id', $notification_id);
        $stmt->bindParam(':user_id', $user_id);
        
        return $stmt->execute();
        
    } catch (PDOException $e) {
        error_log("Error marking notification as read: " . $e->getMessage());
        return false;
    }
}

/**
 * Update user progress
 * @param int $user_id User ID
 * @param array $progress_data Progress data to update
 * @return bool Success status
 */
function updateUserProgress($user_id, $progress_data) {
    $database = new Database();
    
    try {
        // Check if progress record exists
        $existing = $database->queryRow(
            "SELECT * FROM user_progress WHERE user_id = :user_id",
            [':user_id' => $user_id]
        );
        
        if ($existing) {
            // Update existing record
            $update_data = [];
            if (isset($progress_data['total_badges'])) {
                $update_data['total_badges'] = $existing['total_badges'] + $progress_data['total_badges'];
            }
            if (isset($progress_data['total_points'])) {
                $update_data['total_points'] = $existing['total_points'] + $progress_data['total_points'];
            }
            if (isset($progress_data['streak_days'])) {
                $update_data['streak_days'] = $progress_data['streak_days'];
            }
            
            return $database->update('user_progress', $update_data, 'user_id = :user_id', [':user_id' => $user_id]) > 0;
        } else {
            // Create new record
            $progress_data['user_id'] = $user_id;
            return $database->insert('user_progress', $progress_data) !== false;
        }
        
    } catch (PDOException $e) {
        error_log("Error updating user progress: " . $e->getMessage());
        return false;
    }
}

/**
 * Send email using PHP mail function
 * @param string $to Recipient email
 * @param string $subject Email subject
 * @param string $message Email body
 * @param string $from Sender email (optional)
 * @return bool Success status
 */
function sendEmail($to, $subject, $message, $from = 'noreply@eduhive.com') {
    $headers = array(
        'MIME-Version: 1.0',
        'Content-type: text/html; charset=utf-8',
        'From: EduHive <' . $from . '>',
        'Reply-To: ' . $from,
        'X-Mailer: PHP/' . phpversion()
    );
    
    $headers_string = implode("\r\n", $headers);
    
    return mail($to, $subject, $message, $headers_string);
}

/**
 * Generate password reset token
 * @param string $email User email
 * @return string|false Token string or false on error
 */
function generatePasswordResetToken($email) {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Check if user exists
        $user_query = "SELECT id FROM users WHERE email = :email";
        $stmt = $db->prepare($user_query);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return false; // User not found
        }
        
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $user['id'];
        
        // Generate token
        $token = bin2hex(random_bytes(32));
        $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token in database
        $token_query = "INSERT INTO email_tokens (user_id, token, type, expires_at) 
                       VALUES (:user_id, :token, 'password_reset', :expires_at)";
        
        $stmt = $db->prepare($token_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':token', $token);
        $stmt->bindParam(':expires_at', $expires_at);
        
        if ($stmt->execute()) {
            return $token;
        }
        
        return false;
        
    } catch (PDOException $e) {
        error_log("Error generating password reset token: " . $e->getMessage());
        return false;
    }
}

/**
 * Reset password using token
 * @param string $token Reset token
 * @param string $new_password New password
 * @return array Result with success status and message
 */
function resetPassword($token, $new_password) {
    $database = new Database();
    $db = $database->getConnection();
    
    try {
        // Verify token
        $token_query = "SELECT user_id FROM email_tokens 
                       WHERE token = :token AND type = 'password_reset' 
                       AND expires_at > NOW() AND used = FALSE";
        
        $stmt = $db->prepare($token_query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        if ($stmt->rowCount() === 0) {
            return ['success' => false, 'message' => 'Invalid or expired token'];
        }
        
        $token_data = $stmt->fetch(PDO::FETCH_ASSOC);
        $user_id = $token_data['user_id'];
        
        // Hash new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update password
        $update_query = "UPDATE users SET password = :password WHERE id = :user_id";
        $stmt = $db->prepare($update_query);
        $stmt->bindParam(':password', $hashed_password);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        
        // Mark token as used
        $mark_used_query = "UPDATE email_tokens SET used = TRUE WHERE token = :token";
        $stmt = $db->prepare($mark_used_query);
        $stmt->bindParam(':token', $token);
        $stmt->execute();
        
        // Log activity
        logActivity($user_id, 'password_reset', 'Password reset using token');
        
        return ['success' => true, 'message' => 'Password reset successfully'];
        
    } catch (PDOException $e) {
        error_log("Error resetting password: " . $e->getMessage());
        return ['success' => false, 'message' => 'Database error occurred'];
    }
}

/**
 * Sanitize and validate input data
 * @param string $data Input data
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
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
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Validate password strength
 * @param string $password Password to validate
 * @param int $min_length Minimum length (default: 6)
 * @return array Validation result with success and message
 */
function validatePassword($password, $min_length = 6) {
    if (strlen($password) < $min_length) {
        return ['valid' => false, 'message' => "Password must be at least {$min_length} characters long"];
    }
    
    if (!preg_match('/[A-Za-z]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one letter'];
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        return ['valid' => false, 'message' => 'Password must contain at least one number'];
    }
    
    return ['valid' => true, 'message' => 'Password is valid'];
}

/**
 * Generate secure random string
 * @param int $length String length
 * @return string Random string
 */
function generateRandomString($length = 32) {
    return bin2hex(random_bytes($length / 2));
}

/**
 * Log activity for audit trail
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $details Additional details
 * @return bool Success status
 */
if (!function_exists('logActivity')) {
    function logActivity($user_id, $action, $details = '') {
        $database = new Database();
        
        try {
            $query = "INSERT INTO activity_logs (user_id, action, details, ip_address, user_agent, created_at) 
                      VALUES (:user_id, :action, :details, :ip_address, :user_agent, NOW())";
            
            $stmt = $database->getConnection()->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':action', $action);
            $stmt->bindParam(':details', $details);
            $stmt->bindParam(':ip_address', getUserIP());
            $stmt->bindParam(':user_agent', $_SERVER['HTTP_USER_AGENT'] ?? '');
            
            return $stmt->execute();
            
        } catch (PDOException $e) {
            error_log("Error logging activity: " . $e->getMessage());
            return false;
        }
    }
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
        if ($time < 31536000) return floor($time/2592000) . ' months ago';
        
        return floor($time/31536000) . ' years ago';
        
    } catch (Exception $e) {
        return $datetime;
    }
}

/**
 * JSON response helper
 * @param bool $success Success status
 * @param string $message Response message
 * @param array $data Additional data
 * @param int $code HTTP status code
 */
function jsonResponse($success, $message = '', $data = [], $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if (!empty($data)) {
        $response['data'] = $data;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit();
}

/**
 * Check if request is AJAX
 * @return bool True if AJAX request
 */
if (!function_exists('isAjaxRequest')) {
    function isAjaxRequest() {
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
               strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }
}

/**
 * Get user's IP address
 * @return string IP address
 */
if (!function_exists('getUserIP')) {
    function getUserIP() {
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            return $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            return $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        }
    }
}

/**
 * Check if user has permission for action
 * @param int $user_id User ID
 * @param string $permission Permission to check
 * @return bool True if has permission
 */
function hasPermission($user_id, $permission) {
    // Simple role-based permission system
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    $permissions = [
        'admin' => ['*'], // Admin has all permissions
        'user' => ['view_own_data', 'edit_own_data', 'create_tasks', 'manage_own_courses']
    ];
    
    return in_array('*', $permissions[$user_role] ?? []) || 
           in_array($permission, $permissions[$user_role] ?? []);
}

/**
 * Upload file with validation
 * @param array $file $_FILES array element
 * @param string $upload_dir Upload directory
 * @param array $allowed_types Allowed file types
 * @param int $max_size Maximum file size in bytes
 * @return array Result with success status and file path
 */
function uploadFile($file, $upload_dir = 'uploads/', $allowed_types = ['jpg', 'jpeg', 'png', 'gif'], $max_size = 5242880) {
    if (!isset($file['name']) || empty($file['name'])) {
        return ['success' => false, 'message' => 'No file selected'];
    }
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Upload error occurred'];
    }
    
    if ($file['size'] > $max_size) {
        return ['success' => false, 'message' => 'File too large'];
    }
    
    $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($file_extension, $allowed_types)) {
        return ['success' => false, 'message' => 'File type not allowed'];
    }
    
    // Generate unique filename
    $filename = uniqid() . '.' . $file_extension;
    $filepath = $upload_dir . $filename;
    
    // Create directory if it doesn't exist
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'file_path' => $filepath, 'filename' => $filename];
    } else {
        return ['success' => false, 'message' => 'Failed to move uploaded file'];
    }
}

/**
 * Debug function - only show in development
 * @param mixed $data Data to debug
 * @param bool $die Stop execution after debug
 */
function debug($data, $die = false) {
    if (getenv('APP_ENV') !== 'production') {
        echo '<pre style="background: #f4f4f4; padding: 10px; border: 1px solid #ddd; margin: 10px 0;">';
        print_r($data);
        echo '</pre>';
        
        if ($die) {
            die();
        }
    }
}

/**
 * Get application settings
 * @param string $key Setting key
 * @param mixed $default Default value if not found
 * @return mixed Setting value
 */
function getSetting($key, $default = null) {
    static $settings = null;
    
    if ($settings === null) {
        $settings = [];
        
        try {
            $database = new Database();
            $results = $database->query("SELECT setting_key, setting_value, setting_type FROM settings");
            
            // Check if query returned valid results
            if (is_array($results)) {
                foreach ($results as $setting) {
                    $value = $setting['setting_value'];
                    
                    // Convert based on type
                    switch ($setting['setting_type']) {
                        case 'boolean':
                            $value = (bool) $value;
                            break;
                        case 'number':
                            $value = is_numeric($value) ? (float) $value : $value;
                            break;
                        case 'json':
                            $value = json_decode($value, true);
                            break;
                    }
                    
                    $settings[$setting['setting_key']] = $value;
                }
            }
        } catch (Exception $e) {
            error_log("Error loading settings: " . $e->getMessage());
            // Return default values if settings table doesn't exist
            $settings = [
                'registration_enabled' => true,
                'email_verification_required' => false,
                'app_name' => 'EduHive'
            ];
        }
    }
    
    return $settings[$key] ?? $default;
}

/**
 * Set application setting
 * @param string $key Setting key
 * @param mixed $value Setting value
 * @param string $type Setting type
 * @return bool Success status
 */
function setSetting($key, $value, $type = 'string') {
    $database = new Database();
    
    // Convert value based on type
    switch ($type) {
        case 'boolean':
            $value = $value ? '1' : '0';
            break;
        case 'json':
            $value = json_encode($value);
            break;
        default:
            $value = (string) $value;
    }
    
    // Try to update first
    $updated = $database->update(
        'settings', 
        ['setting_value' => $value, 'setting_type' => $type], 
        'setting_key = :key', 
        [':key' => $key]
    );
    
    // If no rows updated, insert new setting
    if ($updated === 0) {
        return $database->insert('settings', [
            'setting_key' => $key,
            'setting_value' => $value,
            'setting_type' => $type
        ]) !== false;
    }
    
    return $updated > 0;
}

/**
 * Clean old notifications, tokens, and logs
 * @param int $days_old Days to keep (default: 30)
 * @return bool Success status
 */
function cleanupOldData($days_old = 30) {
    $database = new Database();
    
    try {
        // Clean old read notifications
        $database->query(
            "DELETE FROM notifications WHERE is_read = TRUE AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)",
            [':days' => $days_old]
        );
        
        // Clean expired tokens
        $database->query("DELETE FROM email_tokens WHERE expires_at < NOW() OR used = TRUE");
        
        // Clean old activity logs (keep longer - 90 days)
        $database->query(
            "DELETE FROM activity_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)",
            [':days' => 90]
        );
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error cleaning up old data: " . $e->getMessage());
        return false;
    }
}

?>