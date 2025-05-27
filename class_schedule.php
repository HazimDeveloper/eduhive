<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Class Schedule</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="schedule.css">
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
        <li class="nav-item active">
          <a href="#schedules">Class Schedules</a>
        </li>
        <li class="nav-item">
          <a href="#task">Task</a>
        </li>
        <li class="nav-item">
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

    <!-- Main Schedule Content -->
    <main class="schedule-main">
      <div class="schedule-header">
        <h1>CLASS SCHEDULE</h1>
        <div class="user-info">
          <span class="user-name">NUR KHALIDA BINTI NAZERI ></span>
          <button class="update-btn">Update</button>
        </div>
      </div>
      
      <!-- Schedule Table -->
      <div class="schedule-container">
        <div class="schedule-grid">
          <!-- Time Column -->
          <div class="time-column">
            <div class="time-header"></div>
            <div class="time-slot">8:00 - 09:59</div>
            <div class="time-slot">10:00 - 11:59</div>
            <div class="time-slot">12:00 - 13:59</div>
            <div class="time-slot">14:00 - 15:59</div>
            <div class="time-slot">16:00 - 17:59</div>
            <div class="time-slot">18:00 - 19:59</div>
          </div>
          
          <!-- Day Columns -->
          <!-- Monday -->
          <div class="day-column">
            <div class="day-header">Mon</div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">LMCR3362</div>
                <div class="class-mode">ONLINE</div>
              </div>
            </div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">TU3833 (L)</div>
                <div class="class-mode">DM</div>
              </div>
            </div>
            <div class="class-slot empty"></div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">TU3833 (T)</div>
                <div class="class-mode">BK4</div>
              </div>
            </div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">FYP</div>
              </div>
            </div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">TN3513 (L)</div>
                <div class="class-mode">ONLINE</div>
              </div>
            </div>
          </div>
          
          <!-- Tuesday -->
          <div class="day-column">
            <div class="day-header">Tue</div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">LMCR3112</div>
                <div class="class-mode">ONLINE</div>
              </div>
            </div>
            <div class="class-slot empty"></div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">TN3513 (Lab)</div>
                <div class="class-mode">MP3</div>
              </div>
            </div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
          </div>
          
          <!-- Wednesday -->
          <div class="day-column">
            <div class="day-header">Wed</div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
          </div>
          
          <!-- Thursday -->
          <div class="day-column">
            <div class="day-header">Thu</div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">TP2543 (L)</div>
                <div class="class-mode">ONLINE</div>
              </div>
            </div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">LMCR2432</div>
                <div class="class-mode">ONLINE</div>
              </div>
            </div>
            <div class="class-slot empty"></div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">LMRS1512</div>
                <div class="class-mode">ONLINE</div>
              </div>
            </div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
          </div>
          
          <!-- Friday -->
          <div class="day-column">
            <div class="day-header">Fri</div>
            <div class="class-slot">
              <div class="class-card">
                <div class="class-code">TP2543 (Lab)</div>
                <div class="class-mode">MP1</div>
              </div>
            </div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
            <div class="class-slot empty"></div>
          </div>
        </div>
      </div>
      
      <!-- Schedule Actions -->
      <div class="schedule-actions">
        <button class="action-btn primary">Add New Class</button>
        <button class="action-btn secondary">Export Schedule</button>
        <button class="action-btn secondary">Print Schedule</button>
      </div>
    </main>
  </div>

  <!-- Add Class Modal -->
  <div id="addClassModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3>Add New Class</h3>
      <form id="addClassForm">
        <input type="text" id="classCode" placeholder="Class Code (e.g., LMCR3362)" required>
        <select id="classDay" required>
          <option value="">Select Day</option>
          <option value="monday">Monday</option>
          <option value="tuesday">Tuesday</option>
          <option value="wednesday">Wednesday</option>
          <option value="thursday">Thursday</option>
          <option value="friday">Friday</option>
        </select>
        <select id="classTime" required>
          <option value="">Select Time</option>
          <option value="8:00 - 09:59">8:00 - 09:59</option>
          <option value="10:00 - 11:59">10:00 - 11:59</option>
          <option value="12:00 - 13:59">12:00 - 13:59</option>
          <option value="14:00 - 15:59">14:00 - 15:59</option>
          <option value="16:00 - 17:59">16:00 - 17:59</option>
          <option value="18:00 - 19:59">18:00 - 19:59</option>
        </select>
        <input type="text" id="classMode" placeholder="Mode/Location (e.g., ONLINE, BK4)" required>
        <div class="form-buttons">
          <button type="submit">Add Class</button>
          <button type="button" id="cancelClass">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const modal = document.getElementById('addClassModal');
      const addClassBtn = document.querySelector('.action-btn.primary');
      const closeModal = document.querySelector('.close');
      const cancelBtn = document.getElementById('cancelClass');
      const addClassForm = document.getElementById('addClassForm');
      const updateBtn = document.querySelector('.update-btn');

      // Event listeners
      addClassBtn.addEventListener('click', () => {
        modal.style.display = 'block';
      });

      closeModal.addEventListener('click', () => {
        modal.style.display = 'none';
      });

      cancelBtn.addEventListener('click', () => {
        modal.style.display = 'none';
      });

      window.addEventListener('click', (event) => {
        if (event.target === modal) {
          modal.style.display = 'none';
        }
      });

      // Add class form submission
      addClassForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const classCode = document.getElementById('classCode').value;
        const classDay = document.getElementById('classDay').value;
        const classTime = document.getElementById('classTime').value;
        const classMode = document.getElementById('classMode').value;

        // Here you would typically send this data to your backend
        console.log('New class:', { classCode, classDay, classTime, classMode });
        
        // For demo purposes, we'll just show an alert
        alert(`Class ${classCode} added for ${classDay} at ${classTime}`);
        
        modal.style.display = 'none';
        addClassForm.reset();
      });

      // Update button functionality
      updateBtn.addEventListener('click', () => {
        alert('Update functionality would sync with your academic system');
      });

      // Export schedule functionality
      document.querySelector('.action-btn.secondary').addEventListener('click', () => {
        alert('Schedule exported successfully!');
      });

      // Print schedule functionality
      document.querySelectorAll('.action-btn.secondary')[1].addEventListener('click', () => {
        window.print();
      });

      // Make class cards interactive
      document.querySelectorAll('.class-card').forEach(card => {
        card.addEventListener('click', () => {
          const classCode = card.querySelector('.class-code').textContent;
          const classMode = card.querySelector('.class-mode')?.textContent || '';
          alert(`Class Details:\nCode: ${classCode}\nMode: ${classMode}`);
        });
      });
    });
  </script>
</body>
</html>