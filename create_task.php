<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Ensure user is logged in
requireLogin();

// Get current user data
$user_id = getCurrentUserId();
$user_name = getCurrentUserName() ?: 'User';

// Get courses for dropdown
$courses = getUserCourses($user_id);

// Get course_id from URL if provided
$selected_course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $database = new Database();
        
        // Prepare task data
        $task_data = [
            'user_id' => $user_id,
            'title' => cleanInput($_POST['title']),
            'description' => cleanInput($_POST['description'] ?? ''),
            'due_date' => $_POST['date'],
            'start_time' => $_POST['start_time'] ?? null,
            'end_time' => $_POST['end_time'] ?? null,
            'course_id' => !empty($_POST['course_id']) ? (int)$_POST['course_id'] : null,
            'reminder_type' => cleanInput($_POST['reminder_type'] ?? ''),
            'status' => 'todo',
            'priority' => 'medium'
        ];
        
        // Create the task
        $task_id = $database->insert('tasks', $task_data);
        
        if ($task_id) {
            // Redirect back to tasks page with success message
            header("Location: task.php?success=1");
            exit();
        } else {
            $error_message = "Failed to create task. Please try again.";
        }
        
    } catch (Exception $e) {
        error_log("Create task error: " . $e->getMessage());
        $error_message = "An error occurred while creating the task.";
    }
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
    .create-task-main {
      flex: 1;
      background: #f8f9fa;
      overflow-y: auto;
      padding: 40px;
    }

    .create-task-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 50px;
    }

    .create-task-header h1 {
      font-size: 48px;
      font-weight: 400;
      color: #333;
      margin: 0;
    }

    .user-name {
      font-size: 16px;
      color: #666;
      font-weight: 400;
    }

    .import-file-section {
      text-align: right;
    }

    .import-file-text {
      font-size: 24px;
      color: #333;
      margin-bottom: 10px;
      font-weight: 400;
    }

    .import-file-icon {
      width: 60px;
      height: 60px;
      background: #8B7355;
      border-radius: 10px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 24px;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .import-file-icon:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    /* Form Styles */
    .task-form {
      max-width: 600px;
      margin-top: 40px;
    }

    .form-group {
      margin-bottom: 40px;
      position: relative;
    }

    .form-group label {
      display: block;
      font-size: 24px;
      color: #333;
      margin-bottom: 15px;
      font-weight: 400;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 15px 20px;
      border: 2px solid #ddd;
      border-radius: 10px;
      font-size: 18px;
      background: white;
      transition: all 0.3s ease;
      box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #8B7355;
      box-shadow: 0 0 0 3px rgba(139, 115, 85, 0.1);
    }

    .form-group textarea {
      min-height: 120px;
      resize: vertical;
    }

    /* Date field with calendar icon */
    .date-input-wrapper {
      position: relative;
    }

    .date-input-wrapper::after {
      content: '📅';
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      font-size: 20px;
      pointer-events: none;
    }

    /* Time inputs in same row */
    .time-row {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 30px;
    }

    /* Dropdown arrows */
    .form-group select {
      appearance: none;
      background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='8' fill='%23333'%3E%3Cpath d='M6 8L0 0h12z'/%3E%3C/svg%3E");
      background-repeat: no-repeat;
      background-position: right 20px center;
      background-size: 12px;
      cursor: pointer;
    }

    /* Submit Button */
    .submit-section {
      margin-top: 60px;
      text-align: right;
    }

    .create-task-btn {
      padding: 15px 30px;
      background: #8B7355;
      color: white;
      border: none;
      border-radius: 25px;
      font-size: 18px;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.3s ease;
      min-width: 200px;
    }

    .create-task-btn:hover {
      background: #6d5d48;
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(139, 115, 85, 0.4);
    }

    /* Error Message */
    .error-message {
      background: #f8d7da;
      color: #721c24;
      padding: 15px;
      border-radius: 8px;
      margin-bottom: 20px;
      border: 1px solid #f5c6cb;
    }

    /* Hidden file input */
    .hidden-file-input {
      display: none;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .create-task-main {
        padding: 20px;
      }
      
      .create-task-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 20px;
      }
      
      .create-task-header h1 {
        font-size: 36px;
      }
      
      .time-row {
        grid-template-columns: 1fr;
        gap: 20px;
      }
      
      .import-file-section {
        text-align: left;
      }
      
      .form-group label {
        font-size: 20px;
      }
      
      .form-group input,
      .form-group select,
      .form-group textarea {
        font-size: 16px;
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

    <!-- Main Create Task Content -->
    <main class="create-task-main">
      <div class="create-task-header">
        <h1>Create New Task</h1>
        <div class="header-right">
          <div class="import-file-section">
            <div class="import-file-text">Import File</div>
            <div class="import-file-icon" onclick="document.getElementById('fileInput').click()">
              📁
            </div>
            <input type="file" id="fileInput" class="hidden-file-input" accept=".txt,.csv,.json" onchange="handleFileImport(this)">
          </div>
          <div class="user-name" style="margin-top: 20px;"><?php echo htmlspecialchars($user_name); ?> ></div>
        </div>
      </div>
      
      <?php if (isset($error_message)): ?>
      <div class="error-message">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
      <?php endif; ?>
      
      <form class="task-form" method="POST">
        <div class="form-group">
          <label for="title">Title</label>
          <input type="text" id="title" name="title" required>
        </div>
        
        <div class="form-group">
          <label for="date">Date</label>
          <div class="date-input-wrapper">
            <input type="date" id="date" name="date" required>
          </div>
        </div>
        
        <div class="form-group">
          <div class="time-row">
            <div>
              <label for="start_time">Start Time</label>
              <select id="start_time" name="start_time">
                <option value="">Select start time</option>
                <?php for ($h = 0; $h < 24; $h++): ?>
                  <?php for ($m = 0; $m < 60; $m += 30): ?>
                    <?php $time = sprintf('%02d:%02d', $h, $m); ?>
                    <option value="<?php echo $time; ?>"><?php echo date('g:i A', strtotime($time)); ?></option>
                  <?php endfor; ?>
                <?php endfor; ?>
              </select>
            </div>
            <div>
              <label for="end_time">End Time</label>
              <select id="end_time" name="end_time">
                <option value="">Select end time</option>
                <?php for ($h = 0; $h < 24; $h++): ?>
                  <?php for ($m = 0; $m < 60; $m += 30): ?>
                    <?php $time = sprintf('%02d:%02d', $h, $m); ?>
                    <option value="<?php echo $time; ?>"><?php echo date('g:i A', strtotime($time)); ?></option>
                  <?php endfor; ?>
                <?php endfor; ?>
              </select>
            </div>
          </div>
        </div>
        
        <div class="form-group">
          <label for="course_id">Added to Course:</label>
          <select id="course_id" name="course_id">
            <option value="">Select course</option>
            <?php foreach ($courses as $course): ?>
            <option value="<?php echo $course['id']; ?>" <?php echo ($course['id'] == $selected_course_id) ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($course['name']); ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        
        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="Enter task description..."></textarea>
        </div>
        
        <div class="form-group">
          <label for="reminder_type">Device / System Reminder</label>
          <select id="reminder_type" name="reminder_type">
            <option value="">Select reminder type</option>
            <option value="none">No reminder</option>
            <option value="15min">15 minutes before</option>
            <option value="30min">30 minutes before</option>
            <option value="1hour">1 hour before</option>
            <option value="1day">1 day before</option>
            <option value="email">Email notification</option>
            <option value="push">Push notification</option>
          </select>
        </div>
        
        <div class="submit-section">
          <button type="submit" class="create-task-btn">Create New Task</button>
        </div>
      </form>
    </main>
  </div>

  <script>
    function handleFileImport(input) {
      const file = input.files[0];
      if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
          try {
            const content = e.target.result;
            
            // Try to parse as JSON first
            if (file.name.endsWith('.json')) {
              const data = JSON.parse(content);
              populateFormFromJSON(data);
            } else if (file.name.endsWith('.csv')) {
              populateFormFromCSV(content);
            } else {
              // Treat as plain text
              document.getElementById('description').value = content;
            }
            
            showNotification('File imported successfully!', 'success');
          } catch (error) {
            showNotification('Error importing file: ' + error.message, 'error');
          }
        };
        reader.readAsText(file);
      }
    }
    
    function populateFormFromJSON(data) {
      if (data.title) document.getElementById('title').value = data.title;
      if (data.date) document.getElementById('date').value = data.date;
      if (data.start_time) document.getElementById('start_time').value = data.start_time;
      if (data.end_time) document.getElementById('end_time').value = data.end_time;
      if (data.description) document.getElementById('description').value = data.description;
      if (data.reminder_type) document.getElementById('reminder_type').value = data.reminder_type;
    }
    
    function populateFormFromCSV(content) {
      const lines = content.split('\n');
      if (lines.length > 1) {
        const headers = lines[0].split(',');
        const values = lines[1].split(',');
        
        for (let i = 0; i < headers.length; i++) {
          const header = headers[i].trim().toLowerCase();
          const value = values[i] ? values[i].trim() : '';
          
          if (header === 'title') document.getElementById('title').value = value;
          else if (header === 'date') document.getElementById('date').value = value;
          else if (header === 'description') document.getElementById('description').value = value;
        }
      }
    }
    
    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 25px;
        border-radius: 8px;
        color: white;
        font-weight: 500;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
        max-width: 300px;
      `;
      
      if (type === 'success') {
        notification.style.backgroundColor = '#28a745';
      } else if (type === 'error') {
        notification.style.backgroundColor = '#dc3545';
      } else {
        notification.style.backgroundColor = '#17a2b8';
      }
      
      notification.textContent = message;
      document.body.appendChild(notification);
      
      setTimeout(() => notification.style.opacity = '1', 100);
      setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => document.body.removeChild(notification), 300);
      }, 3000);
    }

    // Auto-set minimum date to today
    document.getElementById('date').min = new Date().toISOString().split('T')[0];
    
    // Auto-update end time when start time changes
    document.getElementById('start_time').addEventListener('change', function() {
      const startTime = this.value;
      if (startTime) {
        const [hours, minutes] = startTime.split(':');
        const endHour = parseInt(hours) + 1;
        const endTime = (endHour < 24) ? `${endHour.toString().padStart(2, '0')}:${minutes}` : '23:59';
        document.getElementById('end_time').value = endTime;
      }
    });
  </script>
</body>
</html>