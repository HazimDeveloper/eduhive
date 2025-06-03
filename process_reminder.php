<?php
// process_reminders.php - Cron job to process and send reminders
require_once 'config/database.php';
require_once 'config/functions.php';

// This script should be run every minute via cron job
// Example cron: * * * * * /usr/bin/php /path/to/your/project/process_reminders.php

/**
 * Process pending reminders
 */
function processReminders() {
    $database = new Database();
    
    try {
        // Get pending reminders that are due now
        $query = "SELECT * FROM task_reminders 
                  WHERE status = 'pending' 
                  AND scheduled_datetime <= NOW()
                  ORDER BY scheduled_datetime ASC";
        
        $reminders = $database->query($query);
        
        if (empty($reminders)) {
            echo "No reminders to process.\n";
            return;
        }
        
        echo "Processing " . count($reminders) . " reminders...\n";
        
        foreach ($reminders as $reminder) {
            $success = false;
            
            switch ($reminder['reminder_type']) {
                case 'email':
                    $success = sendEmailReminder($reminder);
                    break;
                case 'whatsapp':
                    $success = sendWhatsAppReminder($reminder);
                    break;
                case 'notification':
                    $success = createWebNotification($reminder);
                    break;
            }
            
            // Update reminder status
            $status = $success ? 'sent' : 'failed';
            $update_data = [
                'status' => $status,
                'sent_at' => date('Y-m-d H:i:s')
            ];
            
            $database->update('task_reminders', $update_data, 'id = :id', [':id' => $reminder['id']]);
            
            echo "Reminder {$reminder['id']} ({$reminder['reminder_type']}): " . ($success ? 'SENT' : 'FAILED') . "\n";
        }
        
    } catch (Exception $e) {
        error_log("Error processing reminders: " . $e->getMessage());
        echo "Error: " . $e->getMessage() . "\n";
    }
}

/**
 * Send email reminder
 */
function sendEmailReminder($reminder) {
    try {
        $to = $reminder['recipient_email'];
        $subject = "EduHive Task Reminder";
        $message = $reminder['message'];
        
        // Headers for HTML email
        $headers = "MIME-Version: 1.0" . "\r\n";
        $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
        $headers .= "From: EduHive <noreply@eduhive.com>" . "\r\n";
        $headers .= "Reply-To: noreply@eduhive.com" . "\r\n";
        
        // Convert message to HTML
        $html_message = "
        <html>
        <head>
            <title>EduHive Task Reminder</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #8B7355; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .footer { padding: 10px; text-align: center; font-size: 12px; color: #666; }
                .button { display: inline-block; padding: 10px 20px; background: #8B7355; color: white; text-decoration: none; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔔 Task Reminder</h2>
                </div>
                <div class='content'>
                    <p>" . nl2br(htmlspecialchars($message)) . "</p>
                    <p><a href='http://your-domain.com/task.php' class='button'>View Tasks</a></p>
                </div>
                <div class='footer'>
                    <p>This is an automated reminder from EduHive. Please do not reply to this email.</p>
                </div>
            </div>
        </body>
        </html>";
        
        // Send email
        $result = mail($to, $subject, $html_message, $headers);
        
        if ($result) {
            logReminderActivity($reminder['id'], 'email', 'sent', 'Email sent successfully');
            return true;
        } else {
            logReminderActivity($reminder['id'], 'email', 'failed', 'Failed to send email');
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Email reminder error: " . $e->getMessage());
        logReminderActivity($reminder['id'], 'email', 'failed', $e->getMessage());
        return false;
    }
}

/**
 * Send WhatsApp reminder (using WhatsApp Business API)
 */
function sendWhatsAppReminder($reminder) {
    try {
        $phone_number = '60' . $reminder['recipient_whatsapp']; // Add Malaysia country code
        $message = $reminder['message'];
        
        // Example using WhatsApp Business API (you need to configure this)
        $whatsapp_api_url = 'https://graph.facebook.com/v17.0/YOUR_PHONE_NUMBER_ID/messages';
        $access_token = 'YOUR_WHATSAPP_ACCESS_TOKEN'; // Get from Meta Business
        
        $data = [
            'messaging_product' => 'whatsapp',
            'to' => $phone_number,
            'type' => 'text',
            'text' => [
                'body' => $message
            ]
        ];
        
        $headers = [
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $whatsapp_api_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 200) {
            logReminderActivity($reminder['id'], 'whatsapp', 'sent', 'WhatsApp message sent successfully');
            return true;
        } else {
            logReminderActivity($reminder['id'], 'whatsapp', 'failed', 'WhatsApp API error: ' . $response);
            return false;
        }
        
    } catch (Exception $e) {
        error_log("WhatsApp reminder error: " . $e->getMessage());
        logReminderActivity($reminder['id'], 'whatsapp', 'failed', $e->getMessage());
        
        // Fallback: Try using a simpler SMS service or log for manual sending
        logReminderForManualSending($reminder);
        return false;
    }
}

/**
 * Create web notification (stored in database for user to see)
 */
function createWebNotification($reminder) {
    $database = new Database();
    
    try {
        // Create notifications table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS user_notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('reminder', 'info', 'warning', 'success') DEFAULT 'reminder',
            is_read BOOLEAN DEFAULT FALSE,
            task_id INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
            FOREIGN KEY (task_id) REFERENCES tasks(id) ON DELETE SET NULL
        )";
        $database->getConnection()->exec($create_table);
        
        // Insert notification
        $notification_data = [
            'user_id' => $reminder['user_id'],
            'title' => 'Task Reminder',
            'message' => $reminder['message'],
            'type' => 'reminder',
            'task_id' => $reminder['task_id'],
            'is_read' => false
        ];
        
        $notification_id = $database->insert('user_notifications', $notification_data);
        
        if ($notification_id) {
            logReminderActivity($reminder['id'], 'notification', 'sent', 'Web notification created');
            return true;
        } else {
            logReminderActivity($reminder['id'], 'notification', 'failed', 'Failed to create notification');
            return false;
        }
        
    } catch (Exception $e) {
        error_log("Web notification error: " . $e->getMessage());
        logReminderActivity($reminder['id'], 'notification', 'failed', $e->getMessage());
        return false;
    }
}

/**
 * Log reminder activity
 */
function logReminderActivity($reminder_id, $type, $status, $details) {
    $database = new Database();
    
    try {
        // Create reminder_logs table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS reminder_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            reminder_id INT NOT NULL,
            type VARCHAR(50) NOT NULL,
            status VARCHAR(50) NOT NULL,
            details TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (reminder_id) REFERENCES task_reminders(id) ON DELETE CASCADE
        )";
        $database->getConnection()->exec($create_table);
        
        $log_data = [
            'reminder_id' => $reminder_id,
            'type' => $type,
            'status' => $status,
            'details' => $details
        ];
        
        $database->insert('reminder_logs', $log_data);
        
    } catch (Exception $e) {
        error_log("Error logging reminder activity: " . $e->getMessage());
    }
}

/**
 * Log reminder for manual sending (fallback)
 */
function logReminderForManualSending($reminder) {
    $log_message = "MANUAL REMINDER NEEDED:\n";
    $log_message .= "Type: " . $reminder['reminder_type'] . "\n";
    $log_message .= "Recipient: " . ($reminder['recipient_whatsapp'] ?: $reminder['recipient_email']) . "\n";
    $log_message .= "Message: " . $reminder['message'] . "\n";
    $log_message .= "Scheduled: " . $reminder['scheduled_datetime'] . "\n";
    $log_message .= "---\n";
    
    error_log($log_message);
    
    // You could also save this to a file for manual processing
    file_put_contents('manual_reminders.log', $log_message, FILE_APPEND | LOCK_EX);
}

/**
 * Clean up old reminders and logs
 */
function cleanupOldReminders() {
    $database = new Database();
    
    try {
        // Delete reminders older than 30 days
        $database->getConnection()->exec("DELETE FROM task_reminders WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        
        // Delete logs older than 60 days
        $database->getConnection()->exec("DELETE FROM reminder_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)");
        
        echo "Cleaned up old reminders and logs.\n";
        
    } catch (Exception $e) {
        error_log("Error cleaning up reminders: " . $e->getMessage());
    }
}

// Main execution
if (php_sapi_name() === 'cli') {
    // Running from command line (cron)
    echo "Starting reminder processing at " . date('Y-m-d H:i:s') . "\n";
    processReminders();
    
    // Clean up old records once per day (check if hour is 2 AM)
    if (date('H') == '02') {
        cleanupOldReminders();
    }
    
    echo "Reminder processing completed at " . date('Y-m-d H:i:s') . "\n";
} else {
    // Running from web (for testing)
    header('Content-Type: text/plain');
    echo "This script should be run via cron job.\n";
    echo "To test manually, run: php process_reminders.php\n";
}
?>