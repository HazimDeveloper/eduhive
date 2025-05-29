<?php
require_once 'config/database.php';
require_once 'config/session.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();
$user_name = $_SESSION['user_name'];

// Get dashboard data
$task_stats_query = "SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as completed_tasks
    FROM tasks WHERE user_id = :user_id";
$stmt = $db->prepare($task_stats_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$task_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get due today task
$due_today_query = "SELECT title, due_date FROM tasks WHERE user_id = :user_id AND due_date = CURDATE() AND status != 'done' LIMIT 1";
$stmt = $db->prepare($due_today_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$due_today_task = $stmt->fetch(PDO::FETCH_ASSOC);

// Get user progress
$progress_query = "SELECT total_badges FROM user_progress WHERE user_id = :user_id";
$stmt = $db->prepare($progress_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user_progress = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate progress percentage
$progress_percentage = 0;
if ($task_stats['total_tasks'] > 0) {
    $progress_percentage = round(($task_stats['completed_tasks'] / $task_stats['total_tasks']) * 100);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Dashboard</title>
  <link rel="stylesheet" href="style.css">
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
        <li class="nav-item active">
          <a href="dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a href="calendar.php">Calendar</a>
        </li>
        <li class="nav-item">
          <a href="class_schedule.php">Class Schedules</a>
        </li>
        <li class="nav-item">
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

    <!-- Main Content Area -->
    <main class="main-content">
      <div class="content-area">
        <div class="dashboard-header">
          <h1>Dashboard</h1>
          <div class="user-name"><?php echo htmlspecialchars($user_name); ?> > <a href="auth/logout.php" style="color: #666; text-decoration: none;">Logout</a></div>
        </div>
        
        <div class="dashboard-grid">
          <!-- Task Statistics -->
          <div class="dashboard-card">
            <h3>Total Task Overall</h3>
            <div class="big-number" id="totalTasks"><?php echo $task_stats['total_tasks']; ?></div>
          </div>
          
          <div class="dashboard-card">
            <h3>Total Task Completed</h3>
            <div class="big-number" id="completedTasks"><?php echo $task_stats['completed_tasks']; ?></div>
          </div>
          
          <!-- Due Today Card -->
          <div class="dashboard-card due-today">
            <h3>Due Today</h3>
            <div class="task-details" id="dueTodayTask">
              <?php if ($due_today_task): ?>
                <p><strong>Title:</strong> <?php echo htmlspecialchars($due_today_task['title']); ?></p>
                <p><strong>Date:</strong> <?php echo date('d/m/Y', strtotime($due_today_task['due_date'])); ?></p>
              <?php else: ?>
                <p>No tasks due today!</p>
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Reminder Card -->
          <div class="dashboard-card reminder">
            <h3>Reminder</h3>
            <div class="reminder-content">
              <h4>Meeting with FYP Supervisor</h4>
              <p><strong>Time:</strong> 11:00am - 12:00pm</p>
              <p><strong>Date:</strong> 14/6/2025</p>
            </div>
          </div>
          
          <!-- Reward Claims -->
          <div class="dashboard-card rewards">
            <h3>Reward Claims</h3>
            <div class="reward-icons">
              <div class="reward-badge">🏆</div>
              <div class="reward-medal">🥇</div>
            </div>
            <button class="reward-btn" onclick="window.location.href='reward.php'">Open Reward</button>
          </div>
          
          <!-- Project Progress -->
          <div class="dashboard-card progress">
            <h3>Project Progress</h3>
            <div class="progress-chart">
              <div class="circular-progress">
                <svg width="120" height="120" viewBox="0 0 120 120">
                  <circle cx="60" cy="60" r="50" stroke="#e0e0e0" stroke-width="10" fill="none"/>
                  <circle cx="60" cy="60" r="50" stroke="#8B7355" stroke-width="10" fill="none"
                          stroke-dasharray="314" stroke-dashoffset="<?php echo 314 - (314 * $progress_percentage / 100); ?>" transform="rotate(-90 60 60)"/>
                </svg>
                <div class="progress-text">
                  <span class="percentage"><?php echo $progress_percentage; ?>%</span>
                  <span class="label">Completed</span>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>

    <!-- Calendar Section -->
    <aside class="calendar-section">
      <div class="calendar-widget">
        <div class="calendar-header">
          <button class="calendar-nav" onclick="changeMonth(-1)">&lt;</button>
          <h3 id="currentMonth">JUNE 2025</h3>
          <button class="calendar-nav" onclick="changeMonth(1)">&gt;</button>
        </div>
        
        <div class="calendar-grid">
          <div class="calendar-day-header">SUN</div>
          <div class="calendar-day-header">MON</div>
          <div class="calendar-day-header">TUE</div>
          <div class="calendar-day-header">WED</div>
          <div class="calendar-day-header">THU</div>
          <div class="calendar-day-header">FRI</div>
          <div class="calendar-day-header">SAT</div>
          
          <div id="calendarDays"></div>
        </div>
      </div>
    </aside>
  </div>

  <script>
    // Auto-refresh dashboard data every 30 seconds
    setInterval(refreshDashboard, 30000);
    
    function refreshDashboard() {
      fetch('api/dashboard.php')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const stats = data.data.task_stats;
            document.getElementById('totalTasks').textContent = stats.total_tasks;
            document.getElementById('completedTasks').textContent = stats.completed_tasks;
            
            // Update due today task
            const dueTask = data.data.due_today_task;
            const dueTodayDiv = document.getElementById('dueTodayTask');
            if (dueTask) {
              dueTodayDiv.innerHTML = `
                <p><strong>Title:</strong> ${dueTask.title}</p>
                <p><strong>Date:</strong> ${new Date(dueTask.due_date).toLocaleDateString()}</p>
              `;
            } else {
              dueTodayDiv.innerHTML = '<p>No tasks due today!</p>';
            }
            
            // Update progress
            const progress = data.data.user_progress;
            if (stats.total_tasks > 0) {
              const percentage = Math.round((stats.completed_tasks / stats.total_tasks) * 100);
              document.querySelector('.percentage').textContent = percentage + '%';
              
              // Update progress circle
              const circle = document.querySelector('.progress-chart circle:last-child');
              const dashOffset = 314 - (314 * percentage / 100);
              circle.setAttribute('stroke-dashoffset', dashOffset);
            }
          }
        })
        .catch(error => console.error('Error refreshing dashboard:', error));
    }

    // Calendar functionality
    let currentDate = new Date();
    
    function renderCalendar() {
      const year = currentDate.getFullYear();
      const month = currentDate.getMonth();
      
      // Update month header
      const monthNames = ["JANUARY", "FEBRUARY", "MARCH", "APRIL", "MAY", "JUNE",
                         "JULY", "AUGUST", "SEPTEMBER", "OCTOBER", "NOVEMBER", "DECEMBER"];
      document.getElementById('currentMonth').textContent = `${monthNames[month]} ${year}`;
      
      // Get first day of month and number of days
      const firstDay = new Date(year, month, 1).getDay();
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      
      // Clear calendar
      const calendarDays = document.getElementById('calendarDays');
      calendarDays.innerHTML = '';
      
      // Add empty cells for days before first day of month
      for (let i = 0; i < firstDay; i++) {
        const emptyDay = document.createElement('div');
        emptyDay.className = 'calendar-day';
        calendarDays.appendChild(emptyDay);
      }
      
      // Add days of month
      const today = new Date();
      for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        dayElement.textContent = day;
        
        // Highlight today
        if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
          dayElement.classList.add('today');
        }
        
        calendarDays.appendChild(dayElement);
      }
    }
    
    function changeMonth(direction) {
      currentDate.setMonth(currentDate.getMonth() + direction);
      renderCalendar();
    }
    
    // Initialize calendar
    renderCalendar();
  </script>
</body>
</html>