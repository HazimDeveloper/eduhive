<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Ensure user is logged in
requireLogin();

// Get current user data
$user_id = getCurrentUserId();
$user_name = getCurrentUserName() ?: 'User';
$user_email = getCurrentUserEmail();

// Get courses for dropdown
$courses = getUserCourses($user_id);

// Get course_id from URL if provided
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty($_POST['title'])) {
            throw new Exception("Task title is required");
        }

        if (empty($_POST['due_date'])) {
            throw new Exception("Due date is required");
        }

        // Validate date format
        $due_date = $_POST['due_date'];
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $due_date)) {
            throw new Exception("Invalid date format");
        }

        // Check if date is not in the past
        if (strtotime($due_date) < strtotime('today')) {
            throw new Exception("Due date cannot be in the past");
        }

        // Validate time format if provided
        if (!empty($_POST['start_time']) && !preg_match('/^\d{2}:\d{2}$/', $_POST['start_time'])) {
            throw new Exception("Invalid start time format");
        }

        if (!empty($_POST['end_time']) && !preg_match('/^\d{2}:\d{2}$/', $_POST['end_time'])) {
            throw new Exception("Invalid end time format");
        }

        // Validate reminder settings
        $reminder_time = cleanInput($_POST['reminder_time'] ?? '');
        $reminder_type = cleanInput($_POST['reminder_type'] ?? '');
        
        if ($reminder_time && $reminder_type) {
            if ($reminder_type === 'whatsapp' && empty($_POST['whatsapp_number'])) {
                throw new Exception("WhatsApp number is required for WhatsApp reminders");
            }
            
            if ($reminder_type === 'email' && empty($user_email)) {
                throw new Exception("Email address is required for email reminders");
            }
        }

        // Validate priority
        $valid_priorities = ['low', 'medium', 'high'];
        $priority = $_POST['priority'] ?? 'medium';
        if (!in_array($priority, $valid_priorities)) {
            $priority = 'medium';
        }

        // Handle file upload
        $uploaded_files = [];
        if (!empty($_FILES['task_files']['name'][0])) {
            $uploaded_files = handleFileUpload($_FILES['task_files'], $user_id);
        }

        // Prepare task data
        $task_data = [
            'user_id' => $user_id,
            'title' => cleanInput($_POST['title']),
            'description' => cleanInput($_POST['description'] ?? ''),
            'due_date' => $due_date,
            'start_time' => !empty($_POST['start_time']) ? $_POST['start_time'] : null,
            'end_time' => !empty($_POST['end_time']) ? $_POST['end_time'] : null,
            'course_id' => !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null,
            'priority' => $priority,
            'status' => 'todo',
            'reminder_type' => $reminder_type
        ];

        // Create the task
        $task_id = createEnhancedTask($user_id, $task_data);
        
        if ($task_id) {
            // Save uploaded files to database
            if (!empty($uploaded_files)) {
                foreach ($uploaded_files as $file_info) {
                    saveTaskFile($task_id, $user_id, $file_info);
                }
            }

            // Create reminder if specified
            if ($reminder_time && $reminder_type) {
                $reminder_data = [
                    'task_id' => $task_id,
                    'user_id' => $user_id,
                    'reminder_time' => $reminder_time,
                    'reminder_type' => $reminder_type,
                    'due_date' => $due_date,
                    'start_time' => $task_data['start_time'],
                    'title' => $task_data['title'],
                    'description' => $task_data['description'],
                    'whatsapp_number' => cleanInput($_POST['whatsapp_number'] ?? ''),
                    'email' => $user_email
                ];
                
                $reminder_id = createTaskReminder($reminder_data);
                if (!$reminder_id) {
                    error_log("Failed to create reminder for task: " . $task_id);
                }
            }
            
            // Redirect with success message
            $file_message = !empty($uploaded_files) ? ' Files uploaded successfully.' : '';
            setMessage('Task created successfully!' . ($reminder_time && $reminder_type ? ' Reminder set.' : '') . $file_message, 'success');
            
            // Redirect based on source
            if (!empty($_POST['course_id'])) {
                header("Location: task.php?course_filter=" . $_POST['course_id']);
            } else {
                header("Location: task.php");
            }
            exit();
        } else {
            throw new Exception("Failed to create task in database");
        }

    } catch (Exception $e) {
        error_log("Create task error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}

// Get message from session if redirected
$message = getMessage();

/**
 * Handle file upload with security and validation
 */
function handleFileUpload($files, $user_id) {
    $upload_dir = 'uploads/tasks/';
    $user_upload_dir = $upload_dir . $user_id . '/';
    
    // Create directory if it doesn't exist
    if (!is_dir($user_upload_dir)) {
        if (!mkdir($user_upload_dir, 0755, true)) {
            throw new Exception("Failed to create upload directory");
        }
    }
    
    $uploaded_files = [];
    $allowed_extensions = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'];
    $allowed_mime_types = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation'
    ];
    $max_file_size = 10 * 1024 * 1024; // 10MB
    
    // Handle multiple files
    $file_count = count($files['name']);
    
    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
            continue; // Skip empty file slots
        }
        
        if ($files['error'][$i] !== UPLOAD_ERR_OK) {
            throw new Exception("File upload error: " . getUploadErrorMessage($files['error'][$i]));
        }
        
        $original_name = $files['name'][$i];
        $file_size = $files['size'][$i];
        $file_type = $files['type'][$i];
        $tmp_name = $files['tmp_name'][$i];
        
        // Validate file size
        if ($file_size > $max_file_size) {
            throw new Exception("File '{$original_name}' is too large. Maximum size is 10MB.");
        }
        
        // Validate file extension
        $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
        if (!in_array($file_extension, $allowed_extensions)) {
            throw new Exception("File '{$original_name}' has an invalid extension. Allowed: " . implode(', ', $allowed_extensions));
        }
        
        // Validate MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $detected_mime = finfo_file($finfo, $tmp_name);
        finfo_close($finfo);
        
        if (!in_array($detected_mime, $allowed_mime_types)) {
            throw new Exception("File '{$original_name}' has an invalid file type.");
        }
        
        // Generate unique filename
        $unique_name = uniqid() . '_' . time() . '.' . $file_extension;
        $destination = $user_upload_dir . $unique_name;
        
        // Move uploaded file
        if (!move_uploaded_file($tmp_name, $destination)) {
            throw new Exception("Failed to upload file '{$original_name}'");
        }
        
        $uploaded_files[] = [
            'original_name' => $original_name,
            'stored_name' => $unique_name,
            'file_path' => $destination,
            'file_size' => $file_size,
            'file_type' => $detected_mime,
            'file_extension' => $file_extension
        ];
    }
    
    return $uploaded_files;
}

/**
 * Save task file information to database
 */
function saveTaskFile($task_id, $user_id, $file_info) {
    $database = new Database();
    
    // Create task_files table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS task_files (
        id INT AUTO_INCREMENT PRIMARY KEY,
        task_id INT NOT NULL,
        user_id INT NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        stored_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_size INT NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        file_extension VARCHAR(10) NOT NULL,
        uploaded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $database->getConnection()->exec($create_table);
    
    $file_data = [
        'task_id' => $task_id,
        'user_id' => $user_id,
        'original_name' => $file_info['original_name'],
        'stored_name' => $file_info['stored_name'],
        'file_path' => $file_info['file_path'],
        'file_size' => $file_info['file_size'],
        'file_type' => $file_info['file_type'],
        'file_extension' => $file_info['file_extension']
    ];
    
    return $database->insert('task_files', $file_data);
}

/**
 * Get upload error message
 */
function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return "File is too large";
        case UPLOAD_ERR_PARTIAL:
            return "File was only partially uploaded";
        case UPLOAD_ERR_NO_TMP_DIR:
            return "Missing temporary folder";
        case UPLOAD_ERR_CANT_WRITE:
            return "Failed to write file to disk";
        case UPLOAD_ERR_EXTENSION:
            return "File upload stopped by extension";
        default:
            return "Unknown upload error";
    }
}

/**
 * Create task reminder
 */
function createTaskReminder($reminder_data) {
    $database = new Database();
    
    try {
        // Create reminders table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS task_reminders (
            id INT AUTO_INCREMENT PRIMARY KEY,
            task_id INT NOT NULL,
            user_id INT NOT NULL,
            reminder_type ENUM('email', 'whatsapp', 'notification') NOT NULL,
            reminder_time VARCHAR(50) NOT NULL,
            scheduled_datetime DATETIME NOT NULL,
            recipient_email VARCHAR(100),
            recipient_whatsapp VARCHAR(20),
            message TEXT NOT NULL,
            status ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
            sent_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE CASCADE,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        $database->getConnection()->exec($create_table);
        
        // Calculate scheduled datetime
        $scheduled_datetime = calculateReminderDateTime(
            $reminder_data['due_date'], 
            $reminder_data['start_time'], 
            $reminder_data['reminder_time']
        );
        
        // Generate message
        $message = generateReminderMessage($reminder_data);
        
        // Prepare reminder record
        $data = [
            'task_id' => $reminder_data['task_id'],
            'user_id' => $reminder_data['user_id'],
            'reminder_type' => $reminder_data['reminder_type'],
            'reminder_time' => $reminder_data['reminder_time'],
            'scheduled_datetime' => $scheduled_datetime,
            'recipient_email' => $reminder_data['reminder_type'] === 'email' ? $reminder_data['email'] : null,
            'recipient_whatsapp' => $reminder_data['reminder_type'] === 'whatsapp' ? $reminder_data['whatsapp_number'] : null,
            'message' => $message,
            'status' => 'pending'
        ];
        
        return $database->insert('task_reminders', $data);
        
    } catch (Exception $e) {
        error_log("Error creating reminder: " . $e->getMessage());
        return false;
    }
}

/**
 * Calculate reminder datetime
 */
function calculateReminderDateTime($due_date, $start_time, $reminder_time) {
    $task_datetime = $due_date . ' ' . ($start_time ?: '09:00:00');
    $task_timestamp = strtotime($task_datetime);
    
    switch ($reminder_time) {
        case '1_minute':
            return date('Y-m-d H:i:s', $task_timestamp - 60);
        case '5_minutes':
            return date('Y-m-d H:i:s', $task_timestamp - 300);
        case '20_minutes':
            return date('Y-m-d H:i:s', $task_timestamp - 1200);
        case '1_day':
            return date('Y-m-d H:i:s', $task_timestamp - 86400);
        case '5_days':
            return date('Y-m-d H:i:s', $task_timestamp - 432000);
        case '7_days':
            return date('Y-m-d H:i:s', $task_timestamp - 604800);
        default:
            return date('Y-m-d H:i:s', $task_timestamp - 3600); // 1 hour default
    }
}

/**
 * Generate reminder message
 */
function generateReminderMessage($reminder_data) {
    $type = $reminder_data['reminder_type'];
    $title = $reminder_data['title'];
    $due_date = date('M j, Y', strtotime($reminder_data['due_date']));
    $start_time = $reminder_data['start_time'] ? date('g:i A', strtotime($reminder_data['start_time'])) : '';
    
    if ($type === 'whatsapp') {
        $message = "🔔 *EduHive Task Reminder*\n\n";
        $message .= "📋 *Task:* {$title}\n";
        $message .= "📅 *Due:* {$due_date}";
        if ($start_time) {
            $message .= " at {$start_time}";
        }
        $message .= "\n\n";
        if (!empty($reminder_data['description'])) {
            $message .= "📝 *Details:* " . $reminder_data['description'] . "\n\n";
        }
        $message .= "💪 Time to get it done!\n";
        $message .= "✨ _EduHive - Your Academic Assistant_";
        
    } elseif ($type === 'email') {
        $message = "Task Reminder: {$title}\n\n";
        $message .= "Due Date: {$due_date}";
        if ($start_time) {
            $message .= " at {$start_time}";
        }
        $message .= "\n\n";
        if (!empty($reminder_data['description'])) {
            $message .= "Description: " . $reminder_data['description'] . "\n\n";
        }
        $message .= "Don't forget to complete your task!\n\n";
        $message .= "Best regards,\nEduHive Team";
        
    } else { // notification
        $message = "Reminder: {$title} is due on {$due_date}";
        if ($start_time) {
            $message .= " at {$start_time}";
        }
    }
    
    return $message;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Create New Task</title>
  <link rel="stylesheet" href="style.css">
  <style>
    /* Create Task Page Specific Styles */
    .create-task-main {
      flex: 1;
      background: #f8f9fa;
      overflow-y: auto;
      padding: 30px 40px;
    }

    .create-task-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
    }

    .create-task-header h1 {
      font-size: 36px;
      font-weight: 700;
      color: #333;
      margin: 0;
    }

    .back-link {
      color: #8B7355;
      text-decoration: none;
      font-weight: 500;
      margin-bottom: 20px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s ease;
      padding: 8px 16px;
      border-radius: 20px;
    }

    .back-link:hover {
      background: rgba(139, 115, 85, 0.1);
      transform: translateX(-2px);
    }

    /* Enhanced Form Container */
    .task-form-container {
      max-width: 800px;
      margin: 0 auto;
    }

    .task-form {
      background: white;
      border-radius: 20px;
      padding: 40px;
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
      position: relative;
      overflow: hidden;
    }

    .task-form::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: linear-gradient(90deg, #b19176 0%, #8B7355 100%);
    }

    /* Form Grid Layout */
    .form-row {
      display: grid;
      grid-template-columns: 1fr;
      gap: 30px;
      margin-bottom: 30px;
    }

    .form-row.two-cols {
      grid-template-columns: 1fr 1fr;
    }

    .form-group {
      position: relative;
    }

    .form-group label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 15px 20px;
      border: 2px solid #e1e5e9;
      border-radius: 12px;
      font-size: 16px;
      background: #f8f9fa;
      transition: all 0.3s ease;
      box-sizing: border-box;
      font-family: inherit;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #8B7355;
      background: white;
      box-shadow: 0 0 0 4px rgba(139, 115, 85, 0.1);
      transform: translateY(-1px);
    }

    .form-group textarea {
      min-height: 120px;
      resize: vertical;
      line-height: 1.6;
    }

    /* Enhanced Priority Selection */
    .priority-selection {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
      margin-top: 8px;
    }

    .priority-option {
      position: relative;
    }

    .priority-option input[type="radio"] {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .priority-option label {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 15px 12px;
      background: white;
      border: 2px solid #e1e5e9;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-size: 13px;
    }

    .priority-option input[type="radio"]:checked + label {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
    }

    .priority-option.low label {
      color: #28a745;
      border-color: #28a745;
    }

    .priority-option.low input[type="radio"]:checked + label {
      background: #28a745;
      color: white;
    }

    .priority-option.medium label {
      color: #ffc107;
      border-color: #ffc107;
    }

    .priority-option.medium input[type="radio"]:checked + label {
      background: #ffc107;
      color: #333;
    }

    .priority-option.high label {
      color: #dc3545;
      border-color: #dc3545;
    }

    .priority-option.high input[type="radio"]:checked + label {
      background: #dc3545;
      color: white;
    }

    /* File Upload Section */
    .file-upload-section {
      background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
      border: 2px solid #c3e6cb;
      border-radius: 16px;
      padding: 30px;
      margin: 40px 0;
      position: relative;
    }

    .file-upload-section::before {
      content: '📎';
      position: absolute;
      top: -15px;
      left: 30px;
      background: white;
      padding: 8px 12px;
      border-radius: 20px;
      font-size: 18px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .file-upload-section h3 {
      margin: 0 0 25px 0;
      color: #155724;
      font-size: 18px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      padding-left: 40px;
    }

    .file-upload-area {
      border: 3px dashed #28a745;
      border-radius: 16px;
      padding: 40px 20px;
      text-align: center;
      background: white;
      transition: all 0.3s ease;
      cursor: pointer;
      position: relative;
      overflow: hidden;
    }

    .file-upload-area:hover {
      border-color: #155724;
      background: #f8fff8;
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(40, 167, 69, 0.2);
    }

    .file-upload-area.dragover {
      border-color: #155724;
      background: #e8f5e8;
      transform: scale(1.02);
    }

    .file-upload-icon {
      font-size: 48px;
      color: #28a745;
      margin-bottom: 20px;
      display: block;
    }

    .file-upload-text {
      font-size: 18px;
      font-weight: 600;
      color: #155724;
      margin-bottom: 10px;
    }

    .file-upload-hint {
      font-size: 14px;
      color: #6c757d;
      margin-bottom: 20px;
    }

    .file-upload-formats {
      display: flex;
      justify-content: center;
      gap: 15px;
      flex-wrap: wrap;
    }

    .format-tag {
      padding: 6px 12px;
      background: #28a745;
      color: white;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
    }

    .file-input-hidden {
      position: absolute;
      width: 1px;
      height: 1px;
      padding: 0;
      margin: -1px;
      overflow: hidden;
      clip: rect(0, 0, 0, 0);
      white-space: nowrap;
      border: 0;
    }

    /* Selected Files Display */
    .selected-files {
      margin-top: 25px;
      display: none;
    }

    .selected-files.show {
      display: block;
    }

    .selected-files h4 {
      margin: 0 0 15px 0;
      color: #155724;
      font-size: 16px;
      font-weight: 600;
    }

    .file-list {
      display: flex;
      flex-direction: column;
      gap: 12px;
    }

    .file-item {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px;
      background: white;
      border: 2px solid #c3e6cb;
      border-radius: 12px;
      transition: all 0.3s ease;
    }

    .file-item:hover {
      border-color: #28a745;
      box-shadow: 0 4px 15px rgba(40, 167, 69, 0.2);
    }

    .file-icon {
      width: 40px;
      height: 40px;
      border-radius: 8px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 18px;
      color: white;
      font-weight: 600;
    }

    .file-icon.pdf { background: #dc3545; }
    .file-icon.doc { background: #2b579a; }
    .file-icon.docx { background: #2b579a; }
    .file-icon.xls { background: #217346; }
    .file-icon.xlsx { background: #217346; }
    .file-icon.ppt { background: #d24726; }
    .file-icon.pptx { background: #d24726; }

    .file-details {
      flex: 1;
    }

    .file-name {
      font-weight: 600;
      color: #333;
      margin-bottom: 4px;
      word-break: break-word;
    }

    .file-size {
      font-size: 13px;
      color: #6c757d;
    }

    .file-remove {
      background: #dc3545;
      color: white;
      border: none;
      width: 32px;
      height: 32px;
      border-radius: 50%;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all 0.3s ease;
      font-size: 16px;
      font-weight: 600;
    }

    .file-remove:hover {
      background: #c82333;
      transform: scale(1.1);
    }

    /* Enhanced Reminder Section */
    .reminder-section {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      border: 2px solid #e1e5e9;
      border-radius: 16px;
      padding: 30px;
      margin: 40px 0;
      position: relative;
    }

    .reminder-section::before {
      content: '🔔';
      position: absolute;
      top: -15px;
      left: 30px;
      background: white;
      padding: 8px 12px;
      border-radius: 20px;
      font-size: 18px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .reminder-section h3 {
      margin: 0 0 25px 0;
      color: #333;
      font-size: 18px;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 1px;
      padding-left: 40px;
    }

    .reminder-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
      margin-bottom: 25px;
    }

    /* Reminder Time Options */
    .reminder-time-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
      margin-top: 10px;
    }

    .reminder-time-option {
      position: relative;
    }

    .reminder-time-option input[type="radio"] {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .reminder-time-option label {
      display: block;
      padding: 12px 16px;
      background: white;
      border: 2px solid #e1e5e9;
      border-radius: 10px;
      cursor: pointer;
      transition: all 0.3s ease;
      text-align: center;
      font-weight: 500;
      font-size: 14px;
    }

    .reminder-time-option input[type="radio"]:checked + label {
      background: #8B7355;
      color: white;
      border-color: #8B7355;
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(139, 115, 85, 0.3);
    }

    /* Reminder Type Selection */
    .reminder-type-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
      margin-top: 10px;
    }

    .reminder-type-option {
      position: relative;
    }

    .reminder-type-option input[type="radio"] {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .reminder-type-option label {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 8px;
      padding: 20px 15px;
      background: white;
      border: 2px solid #e1e5e9;
      border-radius: 12px;
      cursor: pointer;
      transition: all 0.3s ease;
      font-weight: 600;
      text-align: center;
    }

    .reminder-type-option input[type="radio"]:checked + label {
      background: #8B7355;
      color: white;
      border-color: #8B7355;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(139, 115, 85, 0.3);
    }

    .reminder-type-icon {
      font-size: 24px;
    }

    /* Enhanced WhatsApp Input */
    .whatsapp-input {
      display: none;
      margin-top: 20px;
      padding: 20px;
      background: linear-gradient(135deg, rgba(37, 211, 102, 0.1) 0%, rgba(37, 211, 102, 0.05) 100%);
      border: 2px solid rgba(37, 211, 102, 0.2);
      border-radius: 12px;
      animation: slideDown 0.3s ease;
    }

    .whatsapp-input.show {
      display: block;
    }

    .whatsapp-number-input {
      display: flex;
      align-items: center;
      gap: 0;
      margin-bottom: 12px;
    }

    .whatsapp-prefix {
      background: #25D366;
      color: white;
      padding: 15px 20px;
      border-radius: 12px 0 0 12px;
      font-weight: 700;
      display: flex;
      align-items: center;
      gap: 8px;
      border: 2px solid #25D366;
    }

    .whatsapp-number {
      flex: 1;
      border-radius: 0 12px 12px 0;
      border-left: none;
      border-color: #25D366;
    }

    .whatsapp-help {
      font-size: 13px;
      color: #155724;
      font-style: italic;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* Email Input */
    .email-input {
      display: none;
      margin-top: 20px;
      padding: 20px;
      background: linear-gradient(135deg, rgba(0, 123, 255, 0.1) 0%, rgba(0, 123, 255, 0.05) 100%);
      border: 2px solid rgba(0, 123, 255, 0.2);
      border-radius: 12px;
      animation: slideDown 0.3s ease;
    }

    .email-input.show {
      display: block;
    }

    .email-input p {
      margin: 0;
      color: #0c5460;
      font-weight: 500;
    }

    /* Enhanced Submit Section */
    .submit-section {
      margin-top: 50px;
      display: flex;
      gap: 20px;
      justify-content: flex-end;
    }

    .submit-btn {
      padding: 18px 40px;
      background: linear-gradient(45deg, #b19176, #8B7355);
      color: white;
      border: none;
      border-radius: 25px;
      font-size: 16px;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 1px;
      box-shadow: 0 4px 15px rgba(139, 115, 85, 0.3);
    }

    .submit-btn:hover {
      background: linear-gradient(45deg, #8B7355, #6d5d48);
      transform: translateY(-3px);
      box-shadow: 0 8px 25px rgba(139, 115, 85, 0.4);
    }

    .cancel-btn {
      padding: 18px 30px;
      background: white;
      color: #666;
      border: 2px solid #e1e5e9;
      border-radius: 25px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
      text-align: center;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .cancel-btn:hover {
      background: #f8f9fa;
      border-color: #8B7355;
      color: #8B7355;
      transform: translateY(-2px);
    }

    /* Enhanced Message Styles */
    .message {
      padding: 20px 25px;
      margin-bottom: 30px;
      border-radius: 12px;
      font-weight: 500;
      border: none;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
      animation: slideDown 0.3s ease;
    }

    .message.success {
      background: linear-gradient(135deg, #d4edda 0%, #c3e6cb 100%);
      color: #155724;
    }

    .message.error {
      background: linear-gradient(135deg, #f8d7da 0%, #f5c6cb 100%);
      color: #721c24;
    }

    /* Required field indicator */
    .required {
      color: #dc3545;
      font-weight: 700;
    }

    /* Animations */
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* Course Badge */
    .course-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      background: #8B7355;
      color: white;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-top: 5px;
    }

    /* Custom Select Styling */
    select {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='%23666'%3E%3Cpath d='M6 8L0 0h12z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 20px center;
      background-size: 12px;
      cursor: pointer;
    }

    /* File Upload Progress */
    .upload-progress {
      margin-top: 15px;
      display: none;
    }

    .upload-progress.show {
      display: block;
    }

    .progress-bar {
      width: 100%;
      height: 8px;
      background: #e9ecef;
      border-radius: 4px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #28a745, #20c997);
      width: 0%;
      transition: width 0.3s ease;
    }

    .progress-text {
      margin-top: 8px;
      font-size: 14px;
      color: #666;
      text-align: center;
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
      .create-task-main {
        padding: 20px;
      }
      
      .form-row.two-cols {
        grid-template-columns: 1fr;
      }
      
      .reminder-grid {
        grid-template-columns: 1fr;
        gap: 20px;
      }
      
      .reminder-time-grid {
        grid-template-columns: 1fr;
      }
      
      .reminder-type-grid {
        grid-template-columns: 1fr;
      }

      .file-upload-formats {
        gap: 10px;
      }
    }

    @media (max-width: 768px) {
      .create-task-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
      }
      
      .create-task-header h1 {
        font-size: 28px;
      }
      
      .task-form {
        padding: 30px 20px;
      }
      
      .priority-selection {
        grid-template-columns: 1fr;
      }
      
      .submit-section {
        flex-direction: column;
      }
      
      .whatsapp-number-input {
        flex-direction: column;
        gap: 10px;
      }
      
      .whatsapp-prefix {
        border-radius: 12px;
        justify-content: center;
      }
      
      .whatsapp-number {
        border-radius: 12px;
        border: 2px solid #25D366;
      }

      .file-upload-area {
        padding: 30px 15px;
      }

      .file-upload-icon {
        font-size: 36px;
      }

      .file-upload-text {
        font-size: 16px;
      }

      .file-upload-formats {
        flex-direction: column;
        align-items: center;
        gap: 8px;
      }
    }
  </style>
</head>
<body class="dashboard-body">
  <div class="dashboard-container">
    <!-- Sidebar Navigation -->
    <nav class="sidebar">
      <div class="sidebar-header">
        <div class="sidebar-logo">
          <img src="logoo.png" width="40px" alt="">
        </div>
        <h2>EduHive</h2>
      </div>
      
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a href="calendar.php">Calendar</a>
        </li>
        <li class="nav-item">
          <a href="class_schedule.php">Class Schedules</a>
        </li>
        <li class="nav-item active">
          <a href="task.php">Task</a>
        </li>
        <li class="nav-item">
          <a href="record_time.php">Record Time</a>
        </li>
        <li class="nav-item">
          <a href="reward.php">Reward</a>
        </li>
        <li class="nav-item">
          <a href="team_member.php">Team Members</a>
        </li>
      </ul>
    </nav>

    <!-- Main Create Task Content -->
    <main class="create-task-main">
      <a href="task.php" class="back-link">← Back to Tasks</a>
      
      <div class="create-task-header">
        <h1>Create New Task</h1>
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?> ></div>
      </div>
      
      <?php if (isset($message)): ?>
      <div class="message <?php echo htmlspecialchars($message['type']); ?>">
        <?php echo htmlspecialchars($message['text']); ?>
      </div>
      <?php endif; ?>
      
      <?php if (isset($error_message)): ?>
      <div class="message error">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
      <?php endif; ?>
      
      <div class="task-form-container">
        <form class="task-form" method="POST" enctype="multipart/form-data">
          <!-- Task Details Section -->
          <div class="form-row">
            <div class="form-group">
              <label for="title">Task Title <span class="required">*</span></label>
              <input type="text" id="title" name="title" required placeholder="Enter your task title..." value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
            </div>
          </div>
          
          <div class="form-row">
            <div class="form-group">
              <label for="description">Description</label>
              <textarea id="description" name="description" placeholder="Describe your task in detail..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
            </div>
          </div>
          
          <!-- Date and Time Section -->
          <div class="form-row two-cols">
            <div class="form-group">
              <label for="due_date">Due Date <span class="required">*</span></label>
              <input type="date" id="due_date" name="due_date" required value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
              <label for="course_id">Course</label>
              <select id="course_id" name="course_id">
                <option value="">Select a course (optional)</option>
                <?php foreach ($courses as $course): ?>
                <option value="<?php echo $course['id']; ?>" 
                        <?php echo (($course['id'] == $selected_course_id) || ($course['id'] == ($_POST['course_id'] ?? 0))) ? 'selected' : ''; ?>>
                  <?php echo htmlspecialchars($course['name'] . ' (' . $course['code'] . ')'); ?>
                </option>
                <?php endforeach; ?>
              </select>
              <?php if (!empty($_POST['course_id'])): ?>
                <?php 
                $selected_course = array_filter($courses, function($c) { return $c['id'] == $_POST['course_id']; });
                if (!empty($selected_course)):
                  $course = reset($selected_course);
                ?>
                <div class="course-badge">📚 <?php echo htmlspecialchars($course['code']); ?></div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          </div>
          
          <div class="form-row two-cols">
            <div class="form-group">
              <label for="start_time">Start Time (Optional)</label>
              <input type="time" id="start_time" name="start_time" value="<?php echo htmlspecialchars($_POST['start_time'] ?? ''); ?>">
            </div>
            <div class="form-group">
              <label for="end_time">End Time (Optional)</label>
              <input type="time" id="end_time" name="end_time" value="<?php echo htmlspecialchars($_POST['end_time'] ?? ''); ?>">
            </div>
          </div>
          
          <!-- Priority Section -->
          <div class="form-row">
            <div class="form-group">
              <label>Priority Level</label>
              <div class="priority-selection">
                <div class="priority-option low">
                  <input type="radio" id="priority_low" name="priority" value="low" <?php echo (($_POST['priority'] ?? 'medium') === 'low') ? 'checked' : ''; ?>>
                  <label for="priority_low">🟢 Low</label>
                </div>
                <div class="priority-option medium">
                  <input type="radio" id="priority_medium" name="priority" value="medium" <?php echo (($_POST['priority'] ?? 'medium') === 'medium') ? 'checked' : ''; ?>>
                  <label for="priority_medium">🟡 Medium</label>
                </div>
                <div class="priority-option high">
                  <input type="radio" id="priority_high" name="priority" value="high" <?php echo (($_POST['priority'] ?? 'medium') === 'high') ? 'checked' : ''; ?>>
                  <label for="priority_high">🔴 High</label>
                </div>
              </div>
            </div>
          </div>

          <!-- File Upload Section -->
          <div class="file-upload-section">
            <h3>📎 Attach Files</h3>
            
            <div class="file-upload-area" id="fileUploadArea">
              <input type="file" id="taskFiles" name="task_files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx" class="file-input-hidden">
              
              <span class="file-upload-icon">📄</span>
              <div class="file-upload-text">Click to browse or drag & drop files</div>
              <div class="file-upload-hint">Maximum file size: 10MB per file</div>
              
              <div class="file-upload-formats">
                <span class="format-tag">PDF</span>
                <span class="format-tag">DOC</span>
                <span class="format-tag">DOCX</span>
                <span class="format-tag">XLS</span>
                <span class="format-tag">XLSX</span>
                <span class="format-tag">PPT</span>
                <span class="format-tag">PPTX</span>
              </div>
            </div>

            <div class="upload-progress" id="uploadProgress">
              <div class="progress-bar">
                <div class="progress-fill" id="progressFill"></div>
              </div>
              <div class="progress-text" id="progressText">Preparing files...</div>
            </div>
            
            <div class="selected-files" id="selectedFiles">
              <h4>📁 Selected Files</h4>
              <div class="file-list" id="fileList"></div>
            </div>
          </div>
          
          <!-- Enhanced Reminder Section -->
          <div class="reminder-section">
            <h3>Set Reminder</h3>
            
            <div class="reminder-grid">
              <div class="form-group">
                <label>Reminder Time</label>
                <div class="reminder-time-grid">
                  <div class="reminder-time-option">
                    <input type="radio" id="reminder_1min" name="reminder_time" value="1_minute">
                    <label for="reminder_1min">1 minute before</label>
                  </div>
                  <div class="reminder-time-option">
                    <input type="radio" id="reminder_5min" name="reminder_time" value="5_minutes">
                    <label for="reminder_5min">5 minutes before</label>
                  </div>
                  <div class="reminder-time-option">
                    <input type="radio" id="reminder_20min" name="reminder_time" value="20_minutes">
                    <label for="reminder_20min">20 minutes before</label>
                  </div>
                  <div class="reminder-time-option">
                    <input type="radio" id="reminder_1day" name="reminder_time" value="1_day">
                    <label for="reminder_1day">1 day before</label>
                  </div>
                  <div class="reminder-time-option">
                    <input type="radio" id="reminder_5days" name="reminder_time" value="5_days">
                    <label for="reminder_5days">5 days before</label>
                  </div>
                  <div class="reminder-time-option">
                    <input type="radio" id="reminder_7days" name="reminder_time" value="7_days">
                    <label for="reminder_7days">7 days before</label>
                  </div>
                </div>
              </div>
              
              <div class="form-group">
                <label>Reminder Method</label>
                <div class="reminder-type-grid">
                  <div class="reminder-type-option">
                    <input type="radio" id="reminder_email" name="reminder_type" value="email">
                    <label for="reminder_email">
                      <span class="reminder-type-icon">📧</span>
                      Email
                    </label>
                  </div>
                  <div class="reminder-type-option">
                    <input type="radio" id="reminder_whatsapp" name="reminder_type" value="whatsapp">
                    <label for="reminder_whatsapp">
                      <span class="reminder-type-icon">📱</span>
                      WhatsApp
                    </label>
                  </div>
                  <div class="reminder-type-option">
                    <input type="radio" id="reminder_notification" name="reminder_type" value="notification">
                    <label for="reminder_notification">
                      <span class="reminder-type-icon">🔔</span>
                      Website
                    </label>
                  </div>
                </div>
              </div>
            </div>
            
            <!-- WhatsApp Input -->
            <div id="whatsappInput" class="whatsapp-input">
              <div class="whatsapp-number-input">
                <div class="whatsapp-prefix">
                  📱 +60
                </div>
                <input type="tel" 
                       id="whatsapp_number" 
                       name="whatsapp_number" 
                       class="whatsapp-number"
                       placeholder="123456789" 
                       pattern="[0-9]{8,10}"
                       maxlength="10">
              </div>
              <div class="whatsapp-help">
                💡 Enter your WhatsApp number without country code (Malaysia +60)
              </div>
            </div>
            
            <!-- Email Input -->
            <div id="emailInput" class="email-input">
              <p><strong>📧 Email Reminder:</strong> <?php echo htmlspecialchars($user_email); ?></p>
              <p style="font-size: 13px; color: #0c5460; margin-top: 8px; opacity: 0.8;">
                Reminder will be sent to your registered email address
              </p>
            </div>
          </div>
          
          <!-- Submit Section -->
          <div class="submit-section">
            <a href="task.php" class="cancel-btn">Cancel</a>
            <button type="submit" class="submit-btn">Create Task</button>
          </div>
        </form>
      </div>
    </main>
  </div>

  <script>
    let selectedFiles = [];
    
    document.addEventListener('DOMContentLoaded', function() {
      initializeEventListeners();
      initializeFileUpload();
      
      // Set minimum date to today
      const dueDateInput = document.getElementById('due_date');
      const today = new Date().toISOString().split('T')[0];
      dueDateInput.min = today;
      
      // Auto-focus title field
      document.getElementById('title').focus();
    });

    function initializeEventListeners() {
      // Auto-update end time when start time changes
      const startTimeInput = document.getElementById('start_time');
      const endTimeInput = document.getElementById('end_time');
      
      startTimeInput.addEventListener('change', function() {
        if (this.value && !endTimeInput.value) {
          const [hours, minutes] = this.value.split(':');
          const endHour = parseInt(hours) + 1;
          const endTime = (endHour < 24) ? `${endHour.toString().padStart(2, '0')}:${minutes}` : '23:59';
          endTimeInput.value = endTime;
        }
      });
      
      // Handle reminder type changes
      const reminderTypeInputs = document.querySelectorAll('input[name="reminder_type"]');
      const whatsappInput = document.getElementById('whatsappInput');
      const emailInput = document.getElementById('emailInput');
      
      reminderTypeInputs.forEach(input => {
        input.addEventListener('change', function() {
          // Hide all specific inputs
          whatsappInput.classList.remove('show');
          emailInput.classList.remove('show');
          
          // Show specific input based on selection
          if (this.value === 'whatsapp') {
            whatsappInput.classList.add('show');
          } else if (this.value === 'email') {
            emailInput.classList.add('show');
          }
          
          showReminderPreview();
        });
      });
      
      // Handle reminder time changes
      const reminderTimeInputs = document.querySelectorAll('input[name="reminder_time"]');
      reminderTimeInputs.forEach(input => {
        input.addEventListener('change', showReminderPreview);
      });
      
      // WhatsApp number formatting
      const whatsappNumberInput = document.getElementById('whatsapp_number');
      if (whatsappNumberInput) {
        whatsappNumberInput.addEventListener('input', function() {
          this.value = this.value.replace(/\D/g, '').substring(0, 10);
        });
        
        whatsappNumberInput.addEventListener('keypress', function(e) {
          if (!/\d/.test(e.key) && !['Backspace', 'Delete', 'Tab', 'Enter', 'ArrowLeft', 'ArrowRight'].includes(e.key)) {
            e.preventDefault();
          }
        });
      }
      
      // Form validation
      const form = document.querySelector('.task-form');
      form.addEventListener('submit', function(e) {
        if (!validateForm()) {
          e.preventDefault();
        }
      });
    }

    function initializeFileUpload() {
      const fileUploadArea = document.getElementById('fileUploadArea');
      const fileInput = document.getElementById('taskFiles');
      const selectedFilesDiv = document.getElementById('selectedFiles');
      const fileList = document.getElementById('fileList');

      // Click to upload
      fileUploadArea.addEventListener('click', function() {
        fileInput.click();
      });

      // Drag and drop functionality
      fileUploadArea.addEventListener('dragover', function(e) {
        e.preventDefault();
        this.classList.add('dragover');
      });

      fileUploadArea.addEventListener('dragleave', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
      });

      fileUploadArea.addEventListener('drop', function(e) {
        e.preventDefault();
        this.classList.remove('dragover');
        
        const files = Array.from(e.dataTransfer.files);
        handleFileSelection(files);
      });

      // File input change
      fileInput.addEventListener('change', function() {
        const files = Array.from(this.files);
        handleFileSelection(files);
      });
    }

    function handleFileSelection(files) {
      const allowedTypes = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-powerpoint', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'];
      const maxSize = 10 * 1024 * 1024; // 10MB
      
      for (const file of files) {
        // Validate file type
        if (!allowedTypes.includes(file.type) && !isValidFileExtension(file.name)) {
          showNotification(`Invalid file type: ${file.name}`, 'error');
          continue;
        }
        
        // Validate file size
        if (file.size > maxSize) {
          showNotification(`File too large: ${file.name} (max 10MB)`, 'error');
          continue;
        }
        
        // Check for duplicates
        if (selectedFiles.some(f => f.name === file.name && f.size === file.size)) {
          showNotification(`File already selected: ${file.name}`, 'warning');
          continue;
        }
        
        selectedFiles.push(file);
      }
      
      updateFileList();
      updateFileInput();
    }

    function isValidFileExtension(filename) {
      const validExtensions = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.ppt', '.pptx'];
      const extension = filename.toLowerCase().substring(filename.lastIndexOf('.'));
      return validExtensions.includes(extension);
    }

    function updateFileList() {
      const selectedFilesDiv = document.getElementById('selectedFiles');
      const fileList = document.getElementById('fileList');
      
      if (selectedFiles.length === 0) {
        selectedFilesDiv.classList.remove('show');
        return;
      }
      
      selectedFilesDiv.classList.add('show');
      
      fileList.innerHTML = selectedFiles.map((file, index) => {
        const extension = file.name.substring(file.name.lastIndexOf('.') + 1).toLowerCase();
        const size = formatFileSize(file.size);
        
        return `
          <div class="file-item">
            <div class="file-icon ${extension}">
              ${getFileIcon(extension)}
            </div>
            <div class="file-details">
              <div class="file-name">${file.name}</div>
              <div class="file-size">${size}</div>
            </div>
            <button type="button" class="file-remove" onclick="removeFile(${index})" title="Remove file">
              ×
            </button>
          </div>
        `;
      }).join('');
    }

    function updateFileInput() {
      const fileInput = document.getElementById('taskFiles');
      const dataTransfer = new DataTransfer();
      
      selectedFiles.forEach(file => {
        dataTransfer.items.add(file);
      });
      
      fileInput.files = dataTransfer.files;
    }

    function removeFile(index) {
      selectedFiles.splice(index, 1);
      updateFileList();
      updateFileInput();
      
      if (selectedFiles.length === 0) {
        showNotification('All files removed', 'info');
      }
    }

    function getFileIcon(extension) {
      const icons = {
        'pdf': 'PDF',
        'doc': 'DOC',
        'docx': 'DOC',
        'xls': 'XLS',
        'xlsx': 'XLS',
        'ppt': 'PPT',
        'pptx': 'PPT'
      };
      return icons[extension] || '📄';
    }

    function formatFileSize(bytes) {
      if (bytes === 0) return '0 Bytes';
      
      const k = 1024;
      const sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    function validateForm() {
      const title = document.getElementById('title').value.trim();
      const dueDate = document.getElementById('due_date').value;
      
      if (!title) {
        showNotification('Please enter a task title', 'error');
        document.getElementById('title').focus();
        return false;
      }
      
      if (!dueDate) {
        showNotification('Please select a due date', 'error');
        document.getElementById('due_date').focus();
        return false;
      }
      
      // Check if due date is not in the past
      const selectedDate = new Date(dueDate);
      const today = new Date();
      today.setHours(0, 0, 0, 0);
      
      if (selectedDate < today) {
        showNotification('Due date cannot be in the past', 'error');
        document.getElementById('due_date').focus();
        return false;
      }
      
      // Validate time range if both times are provided
      const startTime = document.getElementById('start_time').value;
      const endTime = document.getElementById('end_time').value;
      
      if (startTime && endTime && startTime >= endTime) {
        showNotification('End time must be after start time', 'error');
        document.getElementById('end_time').focus();
        return false;
      }
      
      // Validate reminder settings
      const reminderTime = document.querySelector('input[name="reminder_time"]:checked');
      const reminderType = document.querySelector('input[name="reminder_type"]:checked');
      
      if (reminderTime && !reminderType) {
        showNotification('Please select a reminder method', 'error');
        return false;
      }
      
      if (reminderType && !reminderTime) {
        showNotification('Please select when to send the reminder', 'error');
        return false;
      }
      
      if (reminderType && reminderType.value === 'whatsapp') {
        const whatsappNumber = document.getElementById('whatsapp_number').value;
        if (!whatsappNumber || whatsappNumber.length < 8) {
          showNotification('Please enter a valid WhatsApp number (at least 8 digits)', 'error');
          document.getElementById('whatsapp_number').focus();
          return false;
        }
      }

      // Validate file uploads
      if (selectedFiles.length > 10) {
        showNotification('Maximum 10 files allowed', 'error');
        return false;
      }

      const totalSize = selectedFiles.reduce((total, file) => total + file.size, 0);
      const maxTotalSize = 50 * 1024 * 1024; // 50MB total
      
      if (totalSize > maxTotalSize) {
        showNotification('Total file size cannot exceed 50MB', 'error');
        return false;
      }
      
      return true;
    }
    
    function showReminderPreview() {
      const reminderTime = document.querySelector('input[name="reminder_time"]:checked');
      const reminderType = document.querySelector('input[name="reminder_type"]:checked');
      
      if (reminderTime && reminderType) {
        const timeText = reminderTime.nextElementSibling.textContent.trim();
        const typeText = reminderType.nextElementSibling.textContent.trim();
        showNotification(`Reminder: ${timeText} via ${typeText}`, 'success');
      }
    }
    
    function showNotification(message, type = 'info') {
      // Remove any existing notifications
      const existingNotification = document.querySelector('.temp-notification');
      if (existingNotification) {
        existingNotification.remove();
      }
      
      const notification = document.createElement('div');
      notification.className = 'temp-notification';
      notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 12px;
        color: white;
        font-weight: 600;
        z-index: 1000;
        opacity: 0;
        transition: all 0.3s ease;
        max-width: 350px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.15);
        word-wrap: break-word;
      `;
      
      if (type === 'success') {
        notification.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
      } else if (type === 'error') {
        notification.style.background = 'linear-gradient(45deg, #dc3545, #c82333)';
      } else if (type === 'info') {
        notification.style.background = 'linear-gradient(45deg, #17a2b8, #138496)';
      } else if (type === 'warning') {
        notification.style.background = 'linear-gradient(45deg, #ffc107, #e0a800)';
        notification.style.color = '#333';
      }
      
      notification.textContent = message;
      document.body.appendChild(notification);
      
      // Animate in
      setTimeout(() => notification.style.opacity = '1', 100);
      
      // Animate out and remove
      setTimeout(() => {
        notification.style.opacity = '0';
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
          if (document.body.contains(notification)) {
            document.body.removeChild(notification);
          }
        }, 300);
      }, 4000);
    }

    // File upload progress simulation (for better UX)
    function simulateUploadProgress() {
      const progressDiv = document.getElementById('uploadProgress');
      const progressFill = document.getElementById('progressFill');
      const progressText = document.getElementById('progressText');
      
      if (selectedFiles.length === 0) return;
      
      progressDiv.classList.add('show');
      let progress = 0;
      
      const interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress >= 100) {
          progress = 100;
          clearInterval(interval);
          progressText.textContent = 'Files ready for upload!';
          setTimeout(() => {
            progressDiv.classList.remove('show');
          }, 2000);
        } else {
          progressText.textContent = `Processing files... ${Math.round(progress)}%`;
        }
        
        progressFill.style.width = progress + '%';
      }, 200);
    }

    // Enhanced form submission with file validation
    document.querySelector('.task-form').addEventListener('submit', function(e) {
      if (selectedFiles.length > 0) {
        // Show upload progress
        simulateUploadProgress();
        
        // Add a slight delay to show the progress animation
        setTimeout(() => {
          if (validateForm()) {
            showNotification('Creating task with attachments...', 'info');
          }
        }, 500);
      }
    });

    // Keyboard shortcuts
    document.addEventListener('keydown', function(e) {
      // Ctrl/Cmd + Enter to submit form
      if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
        e.preventDefault();
        if (validateForm()) {
          document.querySelector('.task-form').submit();
        }
      }
      
      // Escape to cancel
      if (e.key === 'Escape') {
        if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
          window.location.href = 'task.php';
        }
      }
    });

    // Auto-save draft functionality (simple localStorage implementation)
    function saveDraft() {
      const formData = {
        title: document.getElementById('title').value,
        description: document.getElementById('description').value,
        due_date: document.getElementById('due_date').value,
        start_time: document.getElementById('start_time').value,
        end_time: document.getElementById('end_time').value,
        course_id: document.getElementById('course_id').value,
        priority: document.querySelector('input[name="priority"]:checked')?.value || 'medium',
        timestamp: Date.now()
      };
      
      localStorage.setItem('eduhive_task_draft', JSON.stringify(formData));
    }

    function loadDraft() {
      const draft = localStorage.getItem('eduhive_task_draft');
      if (!draft) return;
      
      try {
        const formData = JSON.parse(draft);
        const now = Date.now();
        const oneHour = 60 * 60 * 1000;
        
        // Only load draft if it's less than 1 hour old
        if (now - formData.timestamp < oneHour) {
          if (confirm('Found a recent draft. Would you like to restore it?')) {
            document.getElementById('title').value = formData.title || '';
            document.getElementById('description').value = formData.description || '';
            document.getElementById('due_date').value = formData.due_date || '';
            document.getElementById('start_time').value = formData.start_time || '';
            document.getElementById('end_time').value = formData.end_time || '';
            document.getElementById('course_id').value = formData.course_id || '';
            
            const priorityInput = document.querySelector(`input[name="priority"][value="${formData.priority}"]`);
            if (priorityInput) {
              priorityInput.checked = true;
            }
            
            showNotification('Draft restored successfully!', 'success');
          }
        }
      } catch (e) {
        console.error('Error loading draft:', e);
      }
    }

    // Auto-save every 30 seconds
    setInterval(saveDraft, 30000);

    // Load draft on page load
    setTimeout(loadDraft, 1000);

    // Clear draft on successful submission
    document.querySelector('.task-form').addEventListener('submit', function() {
      localStorage.removeItem('eduhive_task_draft');
    });

    // Warn user about unsaved changes
    let hasUnsavedChanges = false;
    
    document.querySelectorAll('input, textarea, select').forEach(element => {
      element.addEventListener('input', () => {
        hasUnsavedChanges = true;
      });
    });

    window.addEventListener('beforeunload', function(e) {
      if (hasUnsavedChanges) {
        e.preventDefault();
        e.returnValue = '';
      }
    });

    // Mark changes as saved when form is submitted
    document.querySelector('.task-form').addEventListener('submit', function() {
      hasUnsavedChanges = false;
    });
  </script>
</body>
</html>