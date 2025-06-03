<?php
// api/notifications.php - API for handling user notifications
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

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
            handleGetNotifications($database, $user_id);
            break;
            
        case 'POST':
            handleMarkAsRead($database, $user_id);
            break;
            
        case 'DELETE':
            handleDeleteNotification($database, $user_id);
            break;
            
        default:
            http_response_code(405);
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            break;
    }
    
} catch (Exception $e) {
    error_log("Notifications API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Internal server error']);
}

function handleGetNotifications($database, $user_id) {
    // Get unread count
    if (isset($_GET['count_only'])) {
        $count_query = "SELECT COUNT(*) as unread_count FROM user_notifications WHERE user_id = :user_id AND is_read = FALSE";
        $result = $database->queryRow($count_query, [':user_id' => $user_id]);
        echo json_encode(['success' => true, 'unread_count' => $result['unread_count'] ?? 0]);
        return;
    }
    
    // Get all notifications
    $query = "SELECT n.*, t.title as task_title 
              FROM user_notifications n 
              LEFT JOIN tasks t ON n.task_id = t.id 
              WHERE n.user_id = :user_id 
              ORDER BY n.created_at DESC 
              LIMIT 50";
    
    $notifications = $database->query($query, [':user_id' => $user_id]);
    
    // Format notifications
    $formatted_notifications = [];
    foreach ($notifications as $notification) {
        $formatted_notifications[] = [
            'id' => $notification['id'],
            'title' => $notification['title'],
            'message' => $notification['message'],
            'type' => $notification['type'],
            'is_read' => (bool)$notification['is_read'],
            'task_id' => $notification['task_id'],
            'task_title' => $notification['task_title'],
            'created_at' => $notification['created_at'],
            'time_ago' => timeAgo($notification['created_at'])
        ];
    }
    
    echo json_encode(['success' => true, 'notifications' => $formatted_notifications]);
}

function handleMarkAsRead($database, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (isset($input['notification_id'])) {
        // Mark specific notification as read
        $notification_id = (int)$input['notification_id'];
        $success = $database->update('user_notifications', 
            ['is_read' => true], 
            'id = :id AND user_id = :user_id', 
            [':id' => $notification_id, ':user_id' => $user_id]
        );
        
        echo json_encode(['success' => $success > 0, 'message' => 'Notification marked as read']);
        
    } elseif (isset($input['mark_all_read'])) {
        // Mark all notifications as read
        $success = $database->update('user_notifications', 
            ['is_read' => true], 
            'user_id = :user_id AND is_read = FALSE', 
            [':user_id' => $user_id]
        );
        
        echo json_encode(['success' => $success >= 0, 'message' => 'All notifications marked as read']);
        
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
    }
}

function handleDeleteNotification($database, $user_id) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (empty($input['notification_id'])) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Notification ID required']);
        return;
    }
    
    $notification_id = (int)$input['notification_id'];
    $success = $database->delete('user_notifications', 
        'id = :id AND user_id = :user_id', 
        [':id' => $notification_id, ':user_id' => $user_id]
    );
    
    echo json_encode(['success' => $success > 0, 'message' => 'Notification deleted']);
}
?>

<!-- notification_widget.php - Include this in your main layout -->
<style>
.notification-bell {
    position: relative;
    cursor: pointer;
    padding: 8px;
    border-radius: 50%;
    transition: background-color 0.3s ease;
}

.notification-bell:hover {
    background-color: rgba(139, 115, 85, 0.1);
}

.notification-icon {
    width: 24px;
    height: 24px;
    fill: #666;
}

.notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    font-size: 11px;
    font-weight: bold;
    display: flex;
    align-items: center;
    justify-content: center;
    transform: translate(25%, -25%);
}

.notification-dropdown {
    position: absolute;
    top: 100%;
    right: 0;
    background: white;
    border: 1px solid #e1e5e9;
    border-radius: 12px;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    width: 350px;
    max-height: 400px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
}

.notification-dropdown.show {
    display: block;
}

.notification-header {
    padding: 15px 20px;
    border-bottom: 1px solid #e1e5e9;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.notification-header h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.mark-all-read {
    background: none;
    border: none;
    color: #8B7355;
    font-size: 14px;
    cursor: pointer;
    text-decoration: underline;
}

.notification-list {
    max-height: 300px;
    overflow-y: auto;
}

.notification-item {
    padding: 15px 20px;
    border-bottom: 1px solid #f1f3f4;
    cursor: pointer;
    transition: background-color 0.3s ease;
    position: relative;
}

.notification-item:hover {
    background-color: #f8f9fa;
}

.notification-item.unread {
    background-color: rgba(139, 115, 85, 0.05);
}

.notification-item.unread::before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #8B7355;
}

.notification-content {
    display: flex;
    gap: 12px;
}

.notification-icon-type {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    flex-shrink: 0;
}

.notification-icon-type.reminder {
    background: rgba(139, 115, 85, 0.1);
    color: #8B7355;
}

.notification-icon-type.info {
    background: rgba(23, 162, 184, 0.1);
    color: #17a2b8;
}

.notification-icon-type.warning {
    background: rgba(255, 193, 7, 0.1);
    color: #ffc107;
}

.notification-icon-type.success {
    background: rgba(40, 167, 69, 0.1);
    color: #28a745;
}

.notification-details {
    flex: 1;
}

.notification-title {
    font-weight: 600;
    margin: 0 0 4px 0;
    font-size: 14px;
    color: #333;
}

.notification-message {
    font-size: 13px;
    color: #666;
    margin: 0 0 4px 0;
    line-height: 1.4;
}

.notification-time {
    font-size: 12px;
    color: #999;
}

.notification-actions {
    display: flex;
    gap: 8px;
    margin-top: 8px;
}

.notification-action {
    background: none;
    border: 1px solid #e1e5e9;
    border-radius: 4px;
    padding: 4px 8px;
    font-size: 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.notification-action:hover {
    background: #f8f9fa;
}

.notification-action.delete {
    color: #dc3545;
    border-color: #dc3545;
}

.notification-action.delete:hover {
    background: #dc3545;
    color: white;
}

.notification-empty {
    padding: 40px 20px;
    text-align: center;
    color: #666;
}

.notification-empty-icon {
    font-size: 48px;
    margin-bottom: 10px;
    opacity: 0.5;
}

@media (max-width: 768px) {
    .notification-dropdown {
        width: 300px;
        right: -20px;
    }
}
</style>

<div class="notification-widget">
    <div class="notification-bell" id="notificationBell">
        <svg class="notification-icon" viewBox="0 0 24 24">
            <path d="M12 2C13.1 2 14 2.9 14 4C14 5.1 13.1 6 12 6C10.9 6 10 5.1 10 4C10 2.9 10.9 2 12 2ZM21 19V20H3V19L5 17V11C5 7.9 7 5.2 10 4.3V4C10 2.9 10.9 2 12 2S14 2.9 14 4V4.3C17 5.2 19 7.9 19 11V17L21 19ZM17 11C17 8.2 14.8 6 12 6S7 8.2 7 11V18H17V11Z"/>
        </svg>
        <span class="notification-badge" id="notificationBadge" style="display: none;">0</span>
    </div>
    
    <div class="notification-dropdown" id="notificationDropdown">
        <div class="notification-header">
            <h3>Notifications</h3>
            <button class="mark-all-read" id="markAllRead">Mark all as read</button>
        </div>
        <div class="notification-list" id="notificationList">
            <div class="notification-empty">
                <div class="notification-empty-icon">🔔</div>
                <p>No notifications yet</p>
            </div>
        </div>
    </div>
</div>

<script>
class NotificationManager {
    constructor() {
        this.bell = document.getElementById('notificationBell');
        this.badge = document.getElementById('notificationBadge');
        this.dropdown = document.getElementById('notificationDropdown');
        this.list = document.getElementById('notificationList');
        this.markAllBtn = document.getElementById('markAllRead');
        
        this.init();
    }
    
    init() {
        // Event listeners
        this.bell.addEventListener('click', (e) => {
            e.stopPropagation();
            this.toggleDropdown();
        });
        
        this.markAllBtn.addEventListener('click', () => {
            this.markAllAsRead();
        });
        
        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.notification-widget')) {
                this.closeDropdown();
            }
        });
        
        // Load notifications
        this.loadNotifications();
        this.loadUnreadCount();
        
        // Auto-refresh every 30 seconds
        setInterval(() => {
            this.loadUnreadCount();
            if (this.dropdown.classList.contains('show')) {
                this.loadNotifications();
            }
        }, 30000);
    }
    
    toggleDropdown() {
        if (this.dropdown.classList.contains('show')) {
            this.closeDropdown();
        } else {
            this.openDropdown();
        }
    }
    
    openDropdown() {
        this.dropdown.classList.add('show');
        this.loadNotifications();
    }
    
    closeDropdown() {
        this.dropdown.classList.remove('show');
    }
    
    async loadUnreadCount() {
        try {
            const response = await fetch('api/notifications.php?count_only=1');
            const data = await response.json();
            
            if (data.success) {
                this.updateBadge(data.unread_count);
            }
        } catch (error) {
            console.error('Error loading unread count:', error);
        }
    }
    
    async loadNotifications() {
        try {
            const response = await fetch('api/notifications.php');
            const data = await response.json();
            
            if (data.success) {
                this.renderNotifications(data.notifications);
            }
        } catch (error) {
            console.error('Error loading notifications:', error);
        }
    }
    
    updateBadge(count) {
        if (count > 0) {
            this.badge.textContent = count > 99 ? '99+' : count;
            this.badge.style.display = 'flex';
        } else {
            this.badge.style.display = 'none';
        }
    }
    
    renderNotifications(notifications) {
        if (notifications.length === 0) {
            this.list.innerHTML = `
                <div class="notification-empty">
                    <div class="notification-empty-icon">🔔</div>
                    <p>No notifications yet</p>
                </div>
            `;
            return;
        }
        
        const html = notifications.map(notification => this.renderNotification(notification)).join('');
        this.list.innerHTML = html;
        
        // Add event listeners to notification items
        this.list.querySelectorAll('.notification-item').forEach(item => {
            const notificationId = item.dataset.notificationId;
            
            item.addEventListener('click', (e) => {
                if (!e.target.classList.contains('notification-action')) {
                    this.markAsRead(notificationId);
                    
                    // Navigate to task if available
                    const taskId = item.dataset.taskId;
                    if (taskId) {
                        window.location.href = `task.php?highlight=${taskId}`;
                    }
                }
            });
            
            // Delete button
            const deleteBtn = item.querySelector('.delete');
            if (deleteBtn) {
                deleteBtn.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.deleteNotification(notificationId);
                });
            }
        });
    }
    
    renderNotification(notification) {
        const iconMap = {
            reminder: '🔔',
            info: 'ℹ️',
            warning: '⚠️',
            success: '✅'
        };
        
        return `
            <div class="notification-item ${!notification.is_read ? 'unread' : ''}" 
                 data-notification-id="${notification.id}" 
                 data-task-id="${notification.task_id || ''}">
                <div class="notification-content">
                    <div class="notification-icon-type ${notification.type}">
                        ${iconMap[notification.type] || '🔔'}
                    </div>
                    <div class="notification-details">
                        <div class="notification-title">${this.escapeHtml(notification.title)}</div>
                        <div class="notification-message">${this.escapeHtml(notification.message)}</div>
                        <div class="notification-time">${notification.time_ago}</div>
                        <div class="notification-actions">
                            ${!notification.is_read ? '<button class="notification-action mark-read">Mark as read</button>' : ''}
                            <button class="notification-action delete">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }
    
    async markAsRead(notificationId) {
        try {
            const response = await fetch('api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: parseInt(notificationId) })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Update UI
                const item = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (item) {
                    item.classList.remove('unread');
                    const markReadBtn = item.querySelector('.mark-read');
                    if (markReadBtn) {
                        markReadBtn.remove();
                    }
                }
                
                this.loadUnreadCount();
            }
        } catch (error) {
            console.error('Error marking notification as read:', error);
        }
    }
    
    async markAllAsRead() {
        try {
            const response = await fetch('api/notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ mark_all_read: true })
            });
            
            const data = await response.json();
            
            if (data.success) {
                this.loadNotifications();
                this.loadUnreadCount();
            }
        } catch (error) {
            console.error('Error marking all as read:', error);
        }
    }
    
    async deleteNotification(notificationId) {
        if (!confirm('Are you sure you want to delete this notification?')) {
            return;
        }
        
        try {
            const response = await fetch('api/notifications.php', {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: parseInt(notificationId) })
            });
            
            const data = await response.json();
            
            if (data.success) {
                // Remove from UI
                const item = document.querySelector(`[data-notification-id="${notificationId}"]`);
                if (item) {
                    item.remove();
                }
                
                this.loadUnreadCount();
                
                // Check if list is empty
                if (this.list.children.length === 0) {
                    this.renderNotifications([]);
                }
            }
        } catch (error) {
            console.error('Error deleting notification:', error);
        }
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize notification manager when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    new NotificationManager();
});
</script>