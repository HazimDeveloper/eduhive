<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';
require_once 'setup_middleware.php';

checkSetupStatus();
// Ensure user is logged in
requireLogin();

// Get current user data
$user_id = getCurrentUserId();
$user_name = getCurrentUserName() ?: 'User';
$user_email = getCurrentUserEmail();

// Get user's setup configuration for enhanced features
$user_config = getUserSetupConfig($user_id);
$has_google_config = hasValidGoogleConfig($user_id);
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
$upcoming_reminder = null;
$error_message = '';

try {
    // Get dashboard data using the function from functions.php
    $fetched_data = getDashboardData($user_id);
    
    // Get upcoming reminder
    $upcoming_reminder = getUpcomingReminder($user_id);
    
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
             <img src="logoo.png"  width="40px" alt="">
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
          <h1><?php echo getGreeting(); ?>, <?php echo htmlspecialchars($user_name); ?>!</h1>
          <div class="user-name">
            <?php if ($has_google_config): ?>
              <span style="color: #28a745; margin-right: 10px;">✅ Google Calendar Connected</span>
            <?php endif; ?>
            <?php echo htmlspecialchars($user_name); ?> > 
            <a href="logout.php" style="color: #666; text-decoration: none;" 
               onclick="return confirm('Are you sure you want to logout?')">Logout</a>
          </div>
        </div>
        
        <?php if ($error_message): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
          <?php echo htmlspecialchars($error_message); ?>
        </div>
        <?php endif; ?>
        
        <!-- Enhanced Feature Banner for new users -->
        <?php if (!$has_google_config): ?>
        <div style="background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%); border: 1px solid #2196f3; border-radius: 12px; padding: 20px; margin-bottom: 30px;">
          <h3 style="color: #1565c0; margin: 0 0 10px 0; font-size: 18px;">🚀 Complete Your Setup</h3>
          <p style="color: #1976d2; margin: 0 0 15px 0;">Connect your Google Calendar for automatic sync and enhanced features!</p>
          <a href="setup.php" style="background: #2196f3; color: white; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 600;">Complete Setup</a>
        </div>
        <?php endif; ?>
        
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
          
          <!-- Reminder Card - Now Dynamic -->
          <div class="dashboard-card reminder">
            <h3>Reminder</h3>
            <div class="reminder-content">
              <?php if ($upcoming_reminder): ?>
                <h4><?php echo htmlspecialchars($upcoming_reminder['title']); ?></h4>
                <p><strong>Time:</strong> <?php echo htmlspecialchars($upcoming_reminder['time']); ?></p>
                <p><strong>Date:</strong> <?php echo htmlspecialchars($upcoming_reminder['date']); ?></p>
              <?php else: ?>
                <h4>No upcoming reminders</h4>
                <p><strong>All caught up!</strong> Check your calendar for future events.</p>
              <?php endif; ?>
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
        <!-- Setup Status Widget (for users who completed setup) -->
      <?php if ($has_google_config): ?>
      <div style="background: white; border-radius: 15px; padding: 20px; margin-top: 20px;">
        <h4 style="margin: 0 0 15px 0; color: #333; font-size: 16px;">🔗 Integrations</h4>
        <div style="display: flex; flex-direction: column; gap: 10px;">
          <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
            <span style="color: #28a745; font-size: 18px;">✅</span>
            <div>
              <div style="font-weight: 600; color: #333; font-size: 14px;">Google Calendar</div>
              <div style="font-size: 12px; color: #666;">Connected & Syncing</div>
            </div>
          </div>
          
          <?php if (!empty($user_config['whatsapp_token'])): ?>
          <div style="display: flex; align-items: center; gap: 10px; padding: 10px; background: #f8f9fa; border-radius: 8px;">
            <span style="color: #28a745; font-size: 18px;">📱</span>
            <div>
              <div style="font-weight: 600; color: #333; font-size: 14px;">WhatsApp</div>
              <div style="font-size: 12px; color: #666;">Reminders Active</div>
            </div>
          </div>
          <?php endif; ?>
          
          <button onclick="window.location.href='setup.php'" style="background: rgba(139, 115, 85, 0.1); color: #8B7355; border: 1px solid #8B7355; padding: 8px 16px; border-radius: 6px; font-size: 12px; cursor: pointer; margin-top: 10px;">
            ⚙️ Manage Settings
          </button>
        </div>
      </div>
      <?php endif; ?>
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
    
    // Show setup completion celebration for first-time users
    <?php if ($has_google_config && isset($_GET['setup_completed'])): ?>
    setTimeout(() => {
      if (!localStorage.getItem('eduhive_setup_celebrated')) {
        showSetupCelebration();
        localStorage.setItem('eduhive_setup_celebrated', 'true');
      }
    }, 1000);
    
    function showSetupCelebration() {
      const celebration = document.createElement('div');
      celebration.innerHTML = `
        <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.8); z-index: 10000; display: flex; align-items: center; justify-content: center;">
          <div style="background: white; padding: 40px; border-radius: 20px; text-align: center; max-width: 500px; margin: 20px;">
            <div style="font-size: 60px; margin-bottom: 20px;">🎉</div>
            <h2 style="color: #28a745; margin: 0 0 15px 0;">Setup Complete!</h2>
            <p style="color: #666; margin: 0 0 25px 0; line-height: 1.6;">
              Welcome to EduHive! Your account is now fully configured and ready to help you manage your academic life.
            </p>
            <button onclick="this.parentElement.parentElement.remove()" style="background: #28a745; color: white; border: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; cursor: pointer;">
              Get Started! 🚀
            </button>
          </div>
        </div>
      `;
      document.body.appendChild(celebration);
    }
    <?php endif; ?>
  </script>
</body>
</html>