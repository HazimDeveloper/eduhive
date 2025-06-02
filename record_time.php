<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Ensure user is logged in
requireLogin();

// Get current user data
$user_id = getCurrentUserId();
$user_name = getCurrentUserName() ?: 'User';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'start_timer':
            $entry_data = [
                'title' => $_POST['title'] ?? 'New Task',
                'description' => $_POST['description'] ?? '',
                'task_id' => $_POST['task_id'] ?? null,
                'course_id' => $_POST['course_id'] ?? null
            ];
            
            $entry_id = startTimeEntry($user_id, $entry_data);
            echo json_encode(['success' => $entry_id !== false, 'entry_id' => $entry_id]);
            exit();
            
        case 'stop_timer':
            $entry_id = (int)$_POST['entry_id'];
            $success = stopTimeEntry($entry_id, $user_id);
            echo json_encode(['success' => $success]);
            exit();
            
        case 'delete_entry':
            $entry_id = (int)$_POST['entry_id'];
            $success = deleteTimeEntry($entry_id, $user_id);
            echo json_encode(['success' => $success]);
            exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Get data for the page
$stats = getTimeTrackingStats($user_id);
$time_entries = getTimeEntries($user_id, 20);
$active_entry = getActiveTimeEntry($user_id);
$courses = getUserCourses($user_id);
$tasks = getUserTasks($user_id, 'progress'); // Get in-progress tasks
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Record Time</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="record-time.css">
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
        <li class="nav-item">
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
        <li class="nav-item active">
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

    <!-- Main Record Time Content -->
    <main class="record-time-main">
      <div class="record-time-header">
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?> ></div>
      </div>
      
      <!-- Statistics Cards -->
      <div class="stats-grid">
        <div class="stat-card ongoing-card">
          <div class="stat-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
              <line x1="16" y1="2" x2="16" y2="6"/>
              <line x1="8" y1="2" x2="8" y2="6"/>
              <line x1="3" y1="10" x2="21" y2="10"/>
              <polyline points="11,14 13,16 17,12"/>
            </svg>
          </div>
          <div class="stat-content">
            <div class="stat-number"><?php echo $stats['ongoing_tasks']; ?></div>
            <div class="stat-label">Ongoing Tasks</div>
          </div>
        </div>
        
        <div class="stat-card hours-card">
          <div class="stat-icon">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <circle cx="12" cy="12" r="10"/>
              <polyline points="12,6 12,12 16,14"/>
            </svg>
          </div>
          <div class="stat-content">
            <div class="stat-number"><?php echo $stats['formatted_time']; ?></div>
            <div class="stat-label">Total Hours</div>
          </div>
        </div>
        
        <!-- Timer Card -->
        <div class="timer-card <?php echo $active_entry ? 'running' : ''; ?>" id="timerCard">
          <div class="timer-display <?php echo $active_entry ? 'running' : ''; ?>" id="timerDisplay">
            <?php if ($active_entry): ?>
              <?php
              $elapsed = time() - strtotime($active_entry['start_time']);
              echo formatDuration($elapsed);
              ?>
            <?php else: ?>
              00:00:00
            <?php endif; ?>
          </div>
          <div class="timer-controls">
            <button class="play-btn <?php echo $active_entry ? 'active' : ''; ?>" id="playBtn" <?php echo $active_entry ? 'disabled' : ''; ?>>
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <polygon points="5,3 19,12 5,21" fill="currentColor"/>
              </svg>
            </button>
            <button class="stop-btn <?php echo $active_entry ? 'active' : ''; ?>" id="stopBtn" <?php echo !$active_entry ? 'disabled' : ''; ?>>
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <rect x="6" y="6" width="12" height="12" fill="currentColor"/>
              </svg>
            </button>
          </div>
        </div>
      </div>
      
      <!-- New Task Section -->
      <div class="new-task-section">
        <div class="new-task-form">
          <button class="new-task-btn" id="newTaskBtn" <?php echo $active_entry ? 'disabled' : ''; ?>>
            <span>+ NEW TASK</span>
            <div class="play-icon">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                <polygon points="5,3 19,12 5,21" fill="currentColor"/>
              </svg>
            </div>
          </button>
        </div>
      </div>
      
      <!-- Time Report Section -->
      <div class="time-report-section">
        <div class="report-header">
          <h2>TIME REPORT</h2>
          <div class="report-actions">
            <button class="export-btn" onclick="exportTimeReport()">Export</button>
            <button class="filter-btn" onclick="refreshData()">Refresh</button>
          </div>
        </div>
        
        <div class="report-table">
          <div class="table-header">
            <div class="column-header task-column">
              <span>Task</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6,9 12,15 18,9"/>
              </svg>
            </div>
            <div class="column-header duration-column">
              <span>Duration</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6,9 12,15 18,9"/>
              </svg>
            </div>
            <div class="column-header date-column">
              <span>Date</span>
              <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <polyline points="6,9 12,15 18,9"/>
              </svg>
            </div>
            <div class="column-header actions-column"></div>
          </div>
          
          <div class="table-body" id="timeEntriesTable">
            <?php if (empty($time_entries)): ?>
              <div class="no-entries">No time entries yet. Start tracking your tasks!</div>
            <?php else: ?>
              <?php foreach ($time_entries as $entry): ?>
                <div class="table-row" data-entry-id="<?php echo $entry['id']; ?>">
                  <div class="task-cell">
                    <div class="task-icon <?php echo getTaskColorClass($entry['title']); ?>">
                      <?php echo getTaskIcon($entry['title']); ?>
                    </div>
                    <span><?php echo htmlspecialchars($entry['title']); ?></span>
                  </div>
                  <div class="duration-cell">
                    <?php echo $entry['duration'] ? formatDuration($entry['duration']) : 'In progress'; ?>
                  </div>
                  <div class="date-cell">
                    <?php echo date('d - m - Y', strtotime($entry['date'])); ?>
                  </div>
                  <div class="actions-cell">
                    <button class="menu-btn" onclick="deleteTimeEntry(<?php echo $entry['id']; ?>)">🗑️</button>
                  </div>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- New Task Modal -->
  <div id="newTaskModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3>Start New Task</h3>
      <form id="newTaskForm">
        <input type="text" id="taskName" placeholder="Task Name" required>
        
        <select id="taskCategory">
          <option value="">Select Course/Category</option>
          <?php foreach ($courses as $course): ?>
            <option value="course_<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
          <?php endforeach; ?>
          <option value="custom">Custom Task</option>
        </select>
        
        <select id="linkedTask">
          <option value="">Link to existing task (optional)</option>
          <?php foreach ($tasks as $task): ?>
            <option value="<?php echo $task['id']; ?>"><?php echo htmlspecialchars($task['title']); ?></option>
          <?php endforeach; ?>
        </select>
        
        <textarea id="taskDescription" placeholder="Task Description (Optional)"></textarea>
        
        <div class="form-buttons">
          <button type="submit">Start Timer</button>
          <button type="button" id="cancelNewTask">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    let timerInterval;
    let currentEntryId = <?php echo $active_entry ? $active_entry['id'] : 'null'; ?>;
    let startTime = <?php echo $active_entry ? 'new Date("' . $active_entry['start_time'] . '").getTime()' : 'null'; ?>;

    document.addEventListener('DOMContentLoaded', function() {
      initializeEventListeners();
      
      if (currentEntryId) {
        startTimerDisplay();
      }
    });

    function initializeEventListeners() {
      const playBtn = document.getElementById('playBtn');
      const stopBtn = document.getElementById('stopBtn');
      const newTaskBtn = document.getElementById('newTaskBtn');
      const newTaskModal = document.getElementById('newTaskModal');
      const closeModal = document.querySelector('.close');
      const cancelBtn = document.getElementById('cancelNewTask');
      const newTaskForm = document.getElementById('newTaskForm');

      playBtn.addEventListener('click', startTimer);
      stopBtn.addEventListener('click', stopTimer);
      newTaskBtn.addEventListener('click', () => {
        if (!currentEntryId) {
          newTaskModal.style.display = 'block';
        }
      });

      closeModal.addEventListener('click', () => {
        newTaskModal.style.display = 'none';
      });

      cancelBtn.addEventListener('click', () => {
        newTaskModal.style.display = 'none';
      });

      window.addEventListener('click', (event) => {
        if (event.target === newTaskModal) {
          newTaskModal.style.display = 'none';
        }
      });

      newTaskForm.addEventListener('submit', handleNewTask);
    }

    function startTimer() {
      if (currentEntryId) return;
      
      document.getElementById('newTaskBtn').click();
    }

    function stopTimer() {
      if (!currentEntryId) return;
      
      fetch('record_time.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=stop_timer&entry_id=${currentEntryId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          clearInterval(timerInterval);
          currentEntryId = null;
          startTime = null;
          
          document.getElementById('timerDisplay').textContent = '00:00:00';
          document.getElementById('timerDisplay').classList.remove('running');
          document.getElementById('timerCard').classList.remove('running');
          document.getElementById('playBtn').classList.remove('active');
          document.getElementById('playBtn').disabled = false;
          document.getElementById('stopBtn').classList.remove('active');
          document.getElementById('stopBtn').disabled = true;
          document.getElementById('newTaskBtn').disabled = false;
          
          showNotification('Timer stopped and saved!', 'success');
          refreshTimeEntries();
        } else {
          showNotification('Failed to stop timer', 'error');
        }
      })
      .catch(error => {
        console.error('Error stopping timer:', error);
        showNotification('Error stopping timer', 'error');
      });
    }

    function handleNewTask(e) {
      e.preventDefault();
      
      const taskName = document.getElementById('taskName').value;
      const taskCategory = document.getElementById('taskCategory').value;
      const linkedTask = document.getElementById('linkedTask').value;
      const taskDescription = document.getElementById('taskDescription').value;
      
      let courseId = null;
      if (taskCategory.startsWith('course_')) {
        courseId = taskCategory.replace('course_', '');
      }
      
      const formData = new FormData();
      formData.append('action', 'start_timer');
      formData.append('title', taskName);
      formData.append('description', taskDescription);
      formData.append('course_id', courseId);
      formData.append('task_id', linkedTask);
      
      fetch('record_time.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          currentEntryId = data.entry_id;
          startTime = new Date().getTime();
          
          document.getElementById('newTaskModal').style.display = 'none';
          document.getElementById('newTaskForm').reset();
          
          startTimerDisplay();
          
          document.getElementById('playBtn').classList.add('active');
          document.getElementById('playBtn').disabled = true;
          document.getElementById('stopBtn').classList.add('active');
          document.getElementById('stopBtn').disabled = false;
          document.getElementById('newTaskBtn').disabled = true;
          document.getElementById('timerCard').classList.add('running');
          
          showNotification(`Timer started for: ${taskName}`, 'success');
        } else {
          showNotification('Failed to start timer', 'error');
        }
      })
      .catch(error => {
        console.error('Error starting timer:', error);
        showNotification('Error starting timer', 'error');
      });
    }

    function startTimerDisplay() {
      timerInterval = setInterval(updateTimerDisplay, 1000);
      updateTimerDisplay();
    }

    function updateTimerDisplay() {
      if (!startTime) return;
      
      const now = new Date().getTime();
      const elapsed = Math.floor((now - startTime) / 1000);
      
      const hours = Math.floor(elapsed / 3600);
      const minutes = Math.floor((elapsed % 3600) / 60);
      const seconds = elapsed % 60;
      
      const display = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
      document.getElementById('timerDisplay').textContent = display;
    }

    function deleteTimeEntry(entryId) {
      if (!confirm('Are you sure you want to delete this time entry?')) {
        return;
      }
      
      fetch('record_time.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=delete_entry&entry_id=${entryId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          document.querySelector(`[data-entry-id="${entryId}"]`).remove();
          showNotification('Time entry deleted', 'success');
          refreshStats();
        } else {
          showNotification('Failed to delete time entry', 'error');
        }
      })
      .catch(error => {
        console.error('Error deleting time entry:', error);
        showNotification('Error deleting time entry', 'error');
      });
    }

    function refreshTimeEntries() {
      setTimeout(() => {
        location.reload();
      }, 1000);
    }

    function refreshStats() {
      setTimeout(() => {
        location.reload();
      }, 500);
    }

    function refreshData() {
      location.reload();
    }

    function exportTimeReport() {
      const entries = document.querySelectorAll('.table-row[data-entry-id]');
      let csv = 'Task,Duration,Date\n';
      
      entries.forEach(row => {
        const task = row.querySelector('.task-cell span').textContent;
        const duration = row.querySelector('.duration-cell').textContent;
        const date = row.querySelector('.date-cell').textContent;
        csv += `"${task}","${duration}","${date}"\n`;
      });
      
      const blob = new Blob([csv], { type: 'text/csv' });
      const url = window.URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = 'time_report.csv';
      a.click();
      window.URL.revokeObjectURL(url);
      
      showNotification('Time report exported!', 'success');
    }

    function showNotification(message, type = 'info') {
      const notification = document.createElement('div');
      notification.className = `notification ${type}`;
      notification.textContent = message;
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
      
      document.body.appendChild(notification);
      
      setTimeout(() => notification.style.opacity = '1', 100);
      setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
          if (document.body.contains(notification)) {
            document.body.removeChild(notification);
          }
        }, 300);
      }, 3000);
    }
  </script>
</body>
</html>