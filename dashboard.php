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

// Initialize default dashboard data structure
$default_dashboard_data = [
    'task_stats' => [
        'total_tasks' => 0,
        'completed_tasks' => 0
    ],
    'due_today_task' => null,
    'user_progress' => [
        'total_badges' => 0
    ]
];

// Initialize variables
$dashboard_data = $default_dashboard_data;
$error_message = '';

try {
    // Get dashboard data using the function from functions.php
    $fetched_data = getDashboardData($user_id);
    
    // Ensure we have valid data
    if ($fetched_data && is_array($fetched_data)) {
        // Merge with defaults to ensure all keys exist
        $dashboard_data = array_merge($default_dashboard_data, $fetched_data);
        
        // Ensure nested arrays exist
        if (!isset($dashboard_data['task_stats']) || !is_array($dashboard_data['task_stats'])) {
            $dashboard_data['task_stats'] = $default_dashboard_data['task_stats'];
        }
        
        if (!isset($dashboard_data['user_progress']) || !is_array($dashboard_data['user_progress'])) {
            $dashboard_data['user_progress'] = $default_dashboard_data['user_progress'];
        }
        
        // Ensure required keys exist in nested arrays
        $dashboard_data['task_stats'] = array_merge(
            $default_dashboard_data['task_stats'], 
            $dashboard_data['task_stats']
        );
        
        $dashboard_data['user_progress'] = array_merge(
            $default_dashboard_data['user_progress'], 
            $dashboard_data['user_progress']
        );
    }
    
    // Calculate progress percentage safely
    $progress_percentage = 0;
    if (isset($dashboard_data['task_stats']['total_tasks']) && 
        $dashboard_data['task_stats']['total_tasks'] > 0) {
        $progress_percentage = round(
            ($dashboard_data['task_stats']['completed_tasks'] / $dashboard_data['task_stats']['total_tasks']) * 100
        );
    }
    
} catch (Exception $e) {
    error_log("Dashboard error for user $user_id: " . $e->getMessage());
    $error_message = "Unable to load some dashboard data. Default values are shown.";
    
    // Use default values
    $dashboard_data = $default_dashboard_data;
    $progress_percentage = 0;
}

// Helper functions
function getGreeting() {
    $hour = date('H');
    if ($hour < 12) {
        return "Good Morning";
    } elseif ($hour < 17) {
        return "Good Afternoon";
    } else {
        return "Good Evening";
    }
}

// Safe array access helper
function safeGet($array, $key, $default = '') {
    return isset($array[$key]) ? $array[$key] : $default;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Dashboard</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="dashboard.css">
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
          <div class="user-name">
            <?php echo htmlspecialchars($user_name); ?> > 
            <a href="logout.php" style="color: #666; text-decoration: none;" 
               onclick="return confirm('Are you sure you want to logout?')">Logout</a>
          </div>
        </div>
        
        <div class="dashboard-grid">
          <!-- Task Statistics -->
          <div class="dashboard-card">
            <h3>Total Task Overall</h3>
            <div class="big-number" id="totalTasks">
              <?php echo safeGet($dashboard_data['task_stats'], 'total_tasks', 0); ?>
            </div>
          </div>
          
          <div class="dashboard-card">
            <h3>Total Task Completed</h3>
            <div class="big-number" id="completedTasks">
              <?php echo safeGet($dashboard_data['task_stats'], 'completed_tasks', 0); ?>
            </div>
          </div>
          
          <!-- Due Today Card -->
          <div class="dashboard-card due-today">
            <h3>Due Today</h3>
            <div class="task-details" id="dueTodayTask">
              <?php if (!empty($dashboard_data['due_today_task']) && is_array($dashboard_data['due_today_task'])): ?>
                <p><strong>Title:</strong> <?php echo htmlspecialchars(safeGet($dashboard_data['due_today_task'], 'title', 'No title')); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars(date('d/m/Y', strtotime(safeGet($dashboard_data['due_today_task'], 'due_date', 'today')))); ?></p>
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
                          stroke-dasharray="314" 
                          stroke-dashoffset="<?php echo 314 - (314 * $progress_percentage / 100); ?>" 
                          transform="rotate(-90 60 60)"/>
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
          if (data && data.success && data.data) {
            updateDashboardData(data.data);
          }
        })
        .catch(error => console.error('Error refreshing dashboard:', error));
    }
    
    function updateDashboardData(data) {
      try {
        // Update task counts safely
        if (data.task_stats) {
          const totalTasks = data.task_stats.total_tasks || 0;
          const completedTasks = data.task_stats.completed_tasks || 0;
          
          const totalTasksEl = document.getElementById('totalTasks');
          const completedTasksEl = document.getElementById('completedTasks');
          
          if (totalTasksEl) totalTasksEl.textContent = totalTasks;
          if (completedTasksEl) completedTasksEl.textContent = completedTasks;
          
          // Update due today task
          const dueTodayDiv = document.getElementById('dueTodayTask');
          if (dueTodayDiv) {
            const dueTask = data.due_today_task;
            if (dueTask && dueTask.title) {
              dueTodayDiv.innerHTML = `
                <p><strong>Title:</strong> ${escapeHtml(dueTask.title)}</p>
                <p><strong>Date:</strong> ${new Date(dueTask.due_date).toLocaleDateString()}</p>
              `;
            } else {
              dueTodayDiv.innerHTML = '<p>No tasks due today!</p>';
            }
          }
          
          // Update progress circle
          if (totalTasks > 0) {
            const percentage = Math.round((completedTasks / totalTasks) * 100);
            const percentageEl = document.querySelector('.percentage');
            if (percentageEl) {
              percentageEl.textContent = percentage + '%';
            }
            
            // Update progress circle
            const circle = document.querySelector('.progress-chart circle:last-child');
            if (circle) {
              const dashOffset = 314 - (314 * percentage / 100);
              circle.setAttribute('stroke-dashoffset', dashOffset);
            }
          }
        }
      } catch (error) {
        console.error('Error updating dashboard data:', error);
      }
    }
    
    // Helper function to escape HTML
    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
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
      const daysInPrevMonth = new Date(year, month, 0).getDate();
      
      // Clear calendar
      const calendarDays = document.getElementById('calendarDays');
      calendarDays.innerHTML = '';
      
      // Add days from previous month
      for (let i = firstDay - 1; i >= 0; i--) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day other-month';
        dayElement.textContent = daysInPrevMonth - i;
        calendarDays.appendChild(dayElement);
      }
      
      // Add days of current month
      const today = new Date();
      for (let day = 1; day <= daysInMonth; day++) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day';
        dayElement.textContent = day;
        
        // Highlight today
        if (year === today.getFullYear() && month === today.getMonth() && day === today.getDate()) {
          dayElement.classList.add('today');
        }
        
        // Add click event
        dayElement.addEventListener('click', function() {
          // Remove previous selection
          document.querySelectorAll('.calendar-day.selected').forEach(el => {
            el.classList.remove('selected');
          });
          // Add selection to clicked day (only for current month)
          if (!this.classList.contains('other-month')) {
            this.classList.add('selected');
          }
        });
        
        calendarDays.appendChild(dayElement);
      }
      
      // Add days from next month to fill the grid
      const totalCells = calendarDays.children.length;
      const remainingCells = 42 - totalCells; // 6 weeks * 7 days = 42 cells
      
      for (let day = 1; day <= remainingCells && remainingCells < 14; day++) {
        const dayElement = document.createElement('div');
        dayElement.className = 'calendar-day other-month';
        dayElement.textContent = day;
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