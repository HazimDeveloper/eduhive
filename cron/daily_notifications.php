<?php
// config/notification_system.php
require_once 'functions.php';

class NotificationSystem {
    private $db;
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    // Send email notification
    public function sendEmailNotification($user_id, $subject, $message) {
        // Get user email
        $query = "SELECT email, name FROM users WHERE id = :user_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $email_body = "
            <html>
            <head><title>$subject</title></head>
            <body>
                <h2>Hi {$user['name']},</h2>
                <p>$message</p>
                <br>
                <p>Best regards,<br>EduHive Team</p>
            </body>
            </html>
            ";
            
            $headers = "MIME-Version: 1.0" . "\r\n";
            $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
            $headers .= 'From: EduHive <noreply@eduhive.com>' . "\r\n";
            
            $sent = mail($user['email'], $subject, $email_body, $headers);
            
            // Log notification
            $this->logNotification($user_id, 'email', $subject, $message);
            
            return $sent;
        }
        
        return false;
    }
    
    // Send SMS notification (using a service like Twilio)
    public function sendSMSNotification($user_id, $message) {
        // Get user phone
        $query = "SELECT phone, name FROM users WHERE id = :user_id AND phone IS NOT NULL";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && !empty($user['phone'])) {
            // Implement SMS sending logic here
            // Example with Twilio (requires Twilio SDK)
            /*
            require_once 'vendor/autoload.php';
            use Twilio\Rest\Client;
            
            $sid = 'your_twilio_sid';
            $token = 'your_twilio_token';
            $twilio = new Client($sid, $token);
            
            $message = $twilio->messages->create($user['phone'], [
                'from' => 'your_twilio_number',
                'body' => "EduHive: $message"
            ]);
            */
            
            // Log notification
            $this->logNotification($user_id, 'sms', 'SMS Notification', $message);
            
            return true; // Return true for demo purposes
        }
        
        return false;
    }
    
    // Create web notification
    public function createWebNotification($user_id, $title, $message) {
        return createNotification($user_id, 'web', $title, $message);
    }
    
    // Log notification in database
    private function logNotification($user_id, $type, $title, $message) {
        $query = "INSERT INTO notifications (user_id, type, title, message) VALUES (:user_id, :type, :title, :message)";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':type', $type);
        $stmt->bindParam(':title', $title);
        $stmt->bindParam(':message', $message);
        $stmt->execute();
    }
    
    // Send reminder notifications
    public function sendTaskReminders() {
        // Get tasks due tomorrow
        $query = "SELECT t.*, u.email, u.name, u.phone 
                  FROM tasks t 
                  JOIN users u ON t.user_id = u.id 
                  WHERE t.due_date = DATE_ADD(CURDATE(), INTERVAL 1 DAY) 
                  AND t.status != 'done'";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($tasks as $task) {
            $message = "Reminder: Your task '{$task['title']}' is due tomorrow!";
            
            // Send email reminder
            $this->sendEmailNotification($task['user_id'], 'Task Reminder', $message);
            
            // Send SMS reminder if phone exists
            if (!empty($task['phone'])) {
                $this->sendSMSNotification($task['user_id'], $message);
            }
            
            // Create web notification
            $this->createWebNotification($task['user_id'], 'Task Reminder', $message);
        }
    }
    
    // Send daily digest
    public function sendDailyDigest($user_id) {
        $dashboard_data = getDashboardData($user_id);
        
        $message = "Daily Summary:\n";
        $message .= "- Total Tasks: {$dashboard_data['task_stats']['total_tasks']}\n";
        $message .= "- Completed: {$dashboard_data['task_stats']['completed_tasks']}\n";
        $message .= "- Due Today: {$dashboard_data['task_stats']['due_today']}\n";
        
        if (count($dashboard_data['upcoming_events']) > 0) {
            $message .= "\nUpcoming Events:\n";
            foreach ($dashboard_data['upcoming_events'] as $event) {
                $message .= "- {$event['title']} on {$event['start_date']}\n";
            }
        }
        
        $this->sendEmailNotification($user_id, 'Daily Digest - EduHive', $message);
    }
}

// Cron job script - run daily
// cron/daily_notifications.php
require_once '../config/notification_system.php';

$notification_system = new NotificationSystem();

// Send task reminders
$notification_system->sendTaskReminders();

// Send daily digest to all active users
$database = new Database();
$db = $database->getConnection();

$query = "SELECT DISTINCT user_id FROM user_progress WHERE last_login >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)";
$stmt = $db->prepare($query);
$stmt->execute();
$active_users = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach ($active_users as $user_id) {
    $notification_system->sendDailyDigest($user_id);
}

echo "Daily notifications sent successfully!";
?>