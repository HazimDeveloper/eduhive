<?php 
// api/dashboard.php - Dashboard data
require_once '../config/database.php';
require_once '../config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();

if ($_SERVER['REQUEST_METHOD'] == 'GET') {
    // Get task statistics
    $task_stats_query = "SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_tasks,
        SUM(CASE WHEN due_date = CURDATE() THEN 1 ELSE 0 END) as due_today
        FROM tasks WHERE user_id = :user_id";
    $stmt = $db->prepare($task_stats_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $task_stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get due today task
    $due_today_query = "SELECT * FROM tasks WHERE user_id = :user_id AND due_date = CURDATE() LIMIT 1";
    $stmt = $db->prepare($due_today_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $due_today_task = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Get upcoming events
    $events_query = "SELECT * FROM calendar_events WHERE user_id = :user_id AND start_date >= CURDATE() ORDER BY start_date LIMIT 5";
    $stmt = $db->prepare($events_query_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $upcoming_events = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user progress
    $progress_query = "SELECT * FROM user_progress WHERE user_id = :user_id";
    $stmt = $db->prepare($progress_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $user_progress = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $dashboard_data = [
        'task_stats' => $task_stats,
        'due_today_task' => $due_today_task,
        'upcoming_events' => $upcoming_events,
        'user_progress' => $user_progress
    ];
    
    jsonResponse(true, "Dashboard data retrieved successfully", $dashboard_data);
}