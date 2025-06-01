<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Ensure user is logged in
requireLogin();

// Get current user data
$user_id = getCurrentUserId();
$user_name = getCurrentUserName() ?: 'User';

// Initialize variables
$task_stats = [];
$courses = [];
$error_message = '';

try {
    // Get task statistics
    $database = new Database();
    $task_stats_query = "SELECT 
        COUNT(*) as total_tasks,
        SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo_tasks,
        SUM(CASE WHEN status = 'progress' THEN 1 ELSE 0 END) as progress_tasks,
        SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_tasks
        FROM tasks WHERE user_id = :user_id";
    
    $task_stats = $database->queryRow($task_stats_query, [':user_id' => $user_id]);
    
    // Get courses with task counts
    $courses = getUserCourses($user_id);
    
    // Get task counts for each course
    foreach ($courses as &$course) {
        $course_tasks_query = "SELECT COUNT(*) as task_count FROM tasks WHERE user_id = :user_id AND course_id = :course_id";
        $course_task_count = $database->queryRow($course_tasks_query, [':user_id' => $user_id, ':course_id' => $course['id']]);
        $course['task_count'] = $course_task_count['task_count'] ?? 0;
    }
    
} catch (Exception $e) {
    error_log("Task page error for user $user_id: " . $e->getMessage());
    $error_message = "Unable to load task data.";
    
    // Default values
    $task_stats = [
        'total_tasks' => 0,
        'todo_tasks' => 0,
        'progress_tasks' => 0,
        'done_tasks' => 0
    ];
}

// Calculate started counts
$todo_started = $task_stats['todo_tasks'];
$progress_started = $task_stats['progress_tasks'];
$done_started = $task_stats['done_tasks'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - My Tasks</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .task-main {
      flex: 1;
      background: #f8f9fa;
      overflow-y: auto;
      padding: 40px;
    }

    .task-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
    }

    .task-title-section {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .task-title-section h1 {
      font-size: 48px;
      font-weight: 400;
      color: #333;
      margin: 0;
    }

    .task-icon {
      width: 60px;
      height: 60px;
      background: #8B7355;
      border-radius: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 30px;
    }

    .header-actions {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    .user-name {
      font-size: 16px;
      color: #666;
      font-weight: 400;
    }

    .add-course-btn,
    .add-task-btn {
      padding: 12px 24px;
      border: none;
      border-radius: 25px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      font-size: 14px;
      text-decoration: none;
      display: inline-block;
    }

    .add-course-btn {
      background: #D4B5A0;
      color: #333;
    }

    .add-task-btn {
      background: #8B7355;
      color: white;
    }

    .add-course-btn:hover,
    .add-task-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* Task Status Cards */
    .task-status-grid {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 30px;
      margin-bottom: 40px;
      max-width: 800px;
    }

    .status-card {
      background: #D4B5A0;
      border-radius: 20px;
      padding: 30px;
      display: flex;
      align-items: center;
      gap: 20px;
      min-height: 120px;
    }

    .status-card.done-card {
      grid-column: 1 / -1;
      max-width: 400px;
    }

    .status-icon {
      width: 60px;
      height: 60px;
      background: rgba(0, 0, 0, 0.1);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: #333;
    }

    .status-content h3 {
      font-size: 24px;
      font-weight: 500;
      color: #333;
      margin: 0 0 8px 0;
    }

    .status-content p {
      font-size: 16px;
      color: #333;
      margin: 0;
      font-weight: 400;
    }

    /* Course Cards */
    .course-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 30px;
      max-width: 1000px;
    }

    .course-card {
      border-radius: 20px;
      padding: 40px 30px;
      text-align: center;
      min-height: 200px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
    }

    .course-card.fyp-card {
      background: #A6845C;
      color: white;
    }

    .course-card.programming-card {
      background: #8B8B8B;
      color: white;
    }

    .course-card.harta-card {
      background: #E6A885;
      color: #333;
    }

    .course-card h3 {
      font-size: 24px;
      font-weight: 500;
      margin: 0 0 20px 0;
      text-transform: uppercase;
      letter-spacing: 1px;
    }

    .task-count {
      font-size: 16px;
      margin: 0 0 20px 0;
      opacity: 0.9;
    }

    .add-task-course-btn {
      padding: 10px 20px;
      background: rgba(255, 255, 255, 0.2);
      color: inherit;
      border: 1px solid rgba(255, 255, 255, 0.3);
      border-radius: 20px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
    }

    .harta-card .add-task-course-btn {
      background: rgba(0, 0, 0, 0.1);
      border-color: rgba(0, 0, 0, 0.2);
    }

    .add-task-course-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      transform: translateY(-2px);
    }

    .harta-card .add-task-course-btn:hover {
      background: rgba(0, 0, 0, 0.2);
    }

    /* Responsive Design */
    @media (max-width: 1024px) {
      .task-main {
        padding: 30px;
      }
      
      .task-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
      }
      
      .course-grid {
        grid-template-columns: repeat(2, 1fr);
      }
    }

    @media (max-width: 768px) {
      .task-main {
        padding: 20px;
      }
      
      .task-title-section h1 {
        font-size: 36px;
      }
      
      .task-status-grid,
      .course-grid {
        grid-template-columns: 1fr;
      }
      
      .header-actions {
        width: 100%;
        justify-content: space-between;
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
          <div class="logo-circle-small">
            <div class="graduation-cap-small">🎓</div>
            <div class="location-pin-small">📍</div>
          </div>
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

    <!-- Main Task Content -->
    <main class="task-main">
      <div class="task-header">
        <div class="task-title-section">
          <h1>My Tasks</h1>
          <div class="task-icon">📋</div>
        </div>
        <div class="header-actions">
          <span class="user-name"><?php echo htmlspecialchars($user_name); ?> ></span>
          <a href="create_course.php" class="add-course-btn">+ Add Course</a>
          <a href="create_task.php" class="add-task-btn">+ Add Task</a>
        </div>
      </div>
      
      <?php if ($error_message): ?>
      <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
      <?php endif; ?>
      
      <!-- Task Status Cards -->
      <div class="task-status-grid">
        <div class="status-card todo-card">
          <div class="status-icon">⏰</div>
          <div class="status-content">
            <h3>To Do</h3>
            <p><?php echo $task_stats['todo_tasks']; ?> task<?php echo $task_stats['todo_tasks'] != 1 ? 's' : ''; ?> now • <?php echo $todo_started; ?> started</p>
          </div>
        </div>
        
        <div class="status-card progress-card">
          <div class="status-icon">⚡</div>
          <div class="status-content">
            <h3>In Progress</h3>
            <p><?php echo $task_stats['progress_tasks']; ?> task<?php echo $task_stats['progress_tasks'] != 1 ? 's' : ''; ?> now • <?php echo $progress_started; ?> started</p>
          </div>
        </div>
        
        <div class="status-card done-card">
          <div class="status-icon">✅</div>
          <div class="status-content">
            <h3>Done</h3>
            <p><?php echo $task_stats['done_tasks']; ?> task<?php echo $task_stats['done_tasks'] != 1 ? 's' : ''; ?> now • <?php echo $done_started; ?> started</p>
          </div>
        </div>
      </div>
      
      <!-- Course Cards -->
      <div class="course-grid">
        <?php 
        // Default courses if none exist
        $default_courses = [
          ['name' => 'FYP', 'task_count' => 0, 'class' => 'fyp-card'],
          ['name' => 'PROGRAMMING', 'task_count' => 0, 'class' => 'programming-card'],
          ['name' => 'HARTA', 'task_count' => 0, 'class' => 'harta-card']
        ];
        
        $display_courses = [];
        if (empty($courses)) {
            $display_courses = $default_courses;
        } else {
            // Map existing courses to display format
            foreach ($courses as $course) {
                $course_name = strtoupper($course['name']);
                $class_name = 'fyp-card'; // default
                
                if (strpos($course_name, 'PROGRAMMING') !== false || strpos($course_name, 'TP') !== false) {
                    $class_name = 'programming-card';
                } elseif (strpos($course_name, 'HARTA') !== false) {
                    $class_name = 'harta-card';
                }
                
                $display_courses[] = [
                    'name' => $course_name,
                    'task_count' => $course['task_count'],
                    'class' => $class_name,
                    'id' => $course['id']
                ];
            }
            
            // Fill up to 3 courses
            while (count($display_courses) < 3) {
                $display_courses[] = $default_courses[count($display_courses)];
            }
        }
        
        foreach (array_slice($display_courses, 0, 3) as $course): 
        ?>
        <div class="course-card <?php echo $course['class']; ?>">
          <h3><?php echo htmlspecialchars($course['name']); ?></h3>
          <p class="task-count">
            <?php if ($course['task_count'] > 0): ?>
              <?php echo $course['task_count']; ?> task<?php echo $course['task_count'] != 1 ? 's' : ''; ?> pending
            <?php else: ?>
              No task
            <?php endif; ?>
          </p>
          <a href="create_task.php<?php echo isset($course['id']) ? '?course_id=' . $course['id'] : ''; ?>" class="add-task-course-btn">+ Add Task</a>
        </div>
        <?php endforeach; ?>
      </div>
    </main>
  </div>
</body>
</html>