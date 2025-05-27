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
          <div class="logo-circle-small">
            <div class="graduation-cap-small">🎓</div>
            <div class="location-pin-small">📍</div>
          </div>
        </div>
        <h2>EduHive</h2>
      </div>
      
      <ul class="nav-menu">
        <li class="nav-item">
          <a href="dashboard.html">Dashboard</a>
        </li>
        <li class="nav-item">
          <a href="calendar.html">Calendar</a>
        </li>
        <li class="nav-item">
          <a href="schedule.html">Class Schedules</a>
        </li>
        <li class="nav-item">
          <a href="tasks.html">Task</a>
        </li>
        <li class="nav-item active">
          <a href="#record">Record Time</a>
        </li>
        <li class="nav-item">
          <a href="#reward">Reward</a>
        </li>
        <li class="nav-item">
          <a href="#team">Team Members</a>
        </li>
      </ul>
    </nav>

    <!-- Main Record Time Content -->
    <main class="record-time-main">
      <div class="record-time-header">
        <div class="user-name">NUR KHALIDA BINTI NAZERI ></div>
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
            <div class="stat-number">12</div>
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
            <div class="stat-number">4H 28M</div>
            <div class="stat-label">Total Hours</div>
          </div>
        </div>
        
        <!-- Timer Card -->
        <div class="timer-card">
          <div class="timer-display" id="timerDisplay">00:00:00</div>
          <div class="timer-controls">
            <button class="play-btn" id="playBtn">
              <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                <polygon points="5,3 19,12 5,21" fill="currentColor"/>
              </svg>
            </button>
            <button class="stop-btn" id="stopBtn">
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
          <button class="new-task-btn" id="newTaskBtn">
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
            <button class="export-btn">Export</button>
            <button class="filter-btn">Filter</button>
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
          
          <div class="table-body">
            <div class="table-row">
              <div class="task-cell">
                <div class="task-icon programming-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="4,17 10,11 4,5"/>
                    <line x1="12" y1="19" x2="20" y2="19"/>
                  </svg>
                </div>
                <span>Programming</span>
              </div>
              <div class="duration-cell">01:57:34</div>
              <div class="date-cell">16 - 12 - 2024</div>
              <div class="actions-cell">
                <button class="menu-btn">⋯</button>
              </div>
            </div>
            
            <div class="table-row">
              <div class="task-cell">
                <div class="task-icon fyp-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <circle cx="12" cy="12" r="3"/>
                    <path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/>
                  </svg>
                </div>
                <span>FYP</span>
              </div>
              <div class="duration-cell">04:44:14</div>
              <div class="date-cell">28 - 12 - 2024</div>
              <div class="actions-cell">
                <button class="menu-btn">⋯</button>
              </div>
            </div>
            
            <div class="table-row">
              <div class="task-cell">
                <div class="task-icon harta-icon">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/>
                    <polyline points="9,22 9,12 15,12 15,22"/>
                  </svg>
                </div>
                <span>Pembangunan Harta</span>
              </div>
              <div class="duration-cell">03:32:12</div>
              <div class="date-cell">2 - 01 - 2025</div>
              <div class="actions-cell">
                <button class="menu-btn">⋯</button>
              </div>
            </div>
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
        <select id="taskCategory" required>
          <option value="">Select Category</option>
          <option value="Programming">Programming</option>
          <option value="FYP">FYP</option>
          <option value="Harta">Pembangunan Harta</option>
          <option value="Other">Other</option>
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
    let startTime;
    let elapsedTime = 0;
    let isRunning = false;
    let currentTask = null;

    // Timer functions
    function updateTimerDisplay() {
      const totalSeconds = Math.floor(elapsedTime / 1000);
      const hours = Math.floor(totalSeconds / 3600);
      const minutes = Math.floor((totalSeconds % 3600) / 60);
      const seconds = totalSeconds % 60;
      
      const display = `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
      document.getElementById('timerDisplay').textContent = display;
    }

    function startTimer() {
      if (!isRunning) {
        startTime = Date.now() - elapsedTime;
        timerInterval = setInterval(() => {
          elapsedTime = Date.now() - startTime;
          updateTimerDisplay();
        }, 1000);
        isRunning = true;
        document.getElementById('playBtn').classList.add('active');
        document.getElementById('stopBtn').classList.remove('active');
      }
    }

    function stopTimer() {
      if (isRunning) {
        clearInterval(timerInterval);
        isRunning = false;
        document.getElementById('playBtn').classList.remove('active');
        document.getElementById('stopBtn').classList.add('active');
        
        // Save the time entry if there's a current task
        if (currentTask && elapsedTime > 0) {
          saveTimeEntry();
        }
      }
    }

    function resetTimer() {
      clearInterval(timerInterval);
      elapsedTime = 0;
      isRunning = false;
      updateTimerDisplay();
      document.getElementById('playBtn').classList.remove('active');
      document.getElementById('stopBtn').classList.remove('active');
      currentTask = null;
    }

    function saveTimeEntry() {
      const timeEntries = JSON.parse(localStorage.getItem('eduhive-time-entries')) || [];
      const entry = {
        id: Date.now(),
        task: currentTask.name,
        category: currentTask.category,
        duration: elapsedTime,
        date: new Date().toISOString(),
        description: currentTask.description || ''
      };
      
      timeEntries.unshift(entry);
      localStorage.setItem('eduhive-time-entries', JSON.stringify(timeEntries));
      
      // Update the table
      renderTimeEntries();
      
      // Reset timer
      resetTimer();
      
      showNotification(`Time entry saved: ${formatDuration(elapsedTime)}`);
    }

    function formatDuration(milliseconds) {
      const totalSeconds = Math.floor(milliseconds / 1000);
      const hours = Math.floor(totalSeconds / 3600);
      const minutes = Math.floor((totalSeconds % 3600) / 60);
      const seconds = totalSeconds % 60;
      
      if (hours > 0) {
        return `${hours}h ${minutes}m ${seconds}s`;
      } else if (minutes > 0) {
        return `${minutes}m ${seconds}s`;
      } else {
        return `${seconds}s`;
      }
    }

    function renderTimeEntries() {
      const timeEntries = JSON.parse(localStorage.getItem('eduhive-time-entries')) || [];
      const tableBody = document.querySelector('.table-body');
      
      if (timeEntries.length === 0) {
        tableBody.innerHTML = '<div class="no-entries">No time entries yet. Start tracking your tasks!</div>';
        return;
      }
      
      tableBody.innerHTML = timeEntries.map(entry => `
        <div class="table-row">
          <div class="task-cell">
            <div class="task-icon ${entry.category.toLowerCase()}-icon">
              ${getTaskIcon(entry.category)}
            </div>
            <span>${entry.task}</span>
          </div>
          <div class="duration-cell">${formatDurationDisplay(entry.duration)}</div>
          <div class="date-cell">${formatDate(entry.date)}</div>
          <div class="actions-cell">
            <button class="menu-btn" onclick="deleteEntry(${entry.id})">⋯</button>
          </div>
        </div>
      `).join('');
    }

    function formatDurationDisplay(milliseconds) {
      const totalSeconds = Math.floor(milliseconds / 1000);
      const hours = Math.floor(totalSeconds / 3600);
      const minutes = Math.floor((totalSeconds % 3600) / 60);
      const seconds = totalSeconds % 60;
      
      return `${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
    }

    function formatDate(dateString) {
      const date = new Date(dateString);
      return date.toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric'
      }).replace(/\//g, ' - ');
    }

    function getTaskIcon(category) {
      const icons = {
        'Programming': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="4,17 10,11 4,5"/><line x1="12" y1="19" x2="20" y2="19"/></svg>',
        'FYP': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M12 1v6m0 6v6m11-7h-6m-6 0H1"/></svg>',
        'Harta': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9,22 9,12 15,12 15,22"/></svg>',
        'Other': '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/></svg>'
      };
      return icons[category] || icons['Other'];
    }

    function showNotification(message) {
      // Simple notification - could be enhanced with a toast system
      alert(message);
    }

    function deleteEntry(entryId) {
      if (confirm('Are you sure you want to delete this time entry?')) {
        let timeEntries = JSON.parse(localStorage.getItem('eduhive-time-entries')) || [];
        timeEntries = timeEntries.filter(entry => entry.id !== entryId);
        localStorage.setItem('eduhive-time-entries', JSON.stringify(timeEntries));
        renderTimeEntries();
        showNotification('Time entry deleted successfully!');
      }
    }

    // Event listeners
    document.addEventListener('DOMContentLoaded', function() {
      const playBtn = document.getElementById('playBtn');
      const stopBtn = document.getElementById('stopBtn');
      const newTaskBtn = document.getElementById('newTaskBtn');
      const newTaskModal = document.getElementById('newTaskModal');
      const closeModal = document.querySelector('.close');
      const cancelBtn = document.getElementById('cancelNewTask');
      const newTaskForm = document.getElementById('newTaskForm');

      // Timer controls
      playBtn.addEventListener('click', startTimer);
      stopBtn.addEventListener('click', stopTimer);

      // New task modal
      newTaskBtn.addEventListener('click', () => {
        newTaskModal.style.display = 'block';
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

      // New task form submission
      newTaskForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        currentTask = {
          name: document.getElementById('taskName').value,
          category: document.getElementById('taskCategory').value,
          description: document.getElementById('taskDescription').value
        };
        
        newTaskModal.style.display = 'none';
        newTaskForm.reset();
        
        // Start timer automatically
        resetTimer();
        startTimer();
        
        showNotification(`Timer started for: ${currentTask.name}`);
      });

      // Initialize
      updateTimerDisplay();
      renderTimeEntries();
    });
  </script>
</body>
</html>