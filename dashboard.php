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
          <a href="#dashboard">Dashboard</a>
        </li>
        <li class="nav-item">
          <a href="#calendar">Calendar</a>
        </li>
        <li class="nav-item">
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

    <!-- Main Content Area -->
    <main class="main-content">
      <div class="content-area">
        <div class="dashboard-header">
          <h1>Dashboard</h1>
          <div class="user-name">NUR KHALIDA BINTI NAZERI ></div>
        </div>
        
        <div class="dashboard-grid">
          <!-- Task Statistics -->
          <div class="dashboard-card">
            <h3>Total Task Overall</h3>
            <div class="big-number">24</div>
          </div>
          
          <div class="dashboard-card">
            <h3>Total Task Completed</h3>
            <div class="big-number">22</div>
          </div>
          
          <!-- Due Today Card -->
          <div class="dashboard-card due-today">
            <h3>Due Today</h3>
            <div class="task-details">
              <p><strong>Title:</strong> Submit D5</p>
              <p><strong>Date:</strong> 15/6/2025</p>
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
            <button class="reward-btn">Open Reward</button>
          </div>
          
          <!-- Project Progress -->
          <div class="dashboard-card progress">
            <h3>Project Progress</h3>
            <div class="progress-chart">
              <div class="circular-progress">
                <svg width="120" height="120" viewBox="0 0 120 120">
                  <circle cx="60" cy="60" r="50" stroke="#e0e0e0" stroke-width="10" fill="none"/>
                  <circle cx="60" cy="60" r="50" stroke="#8B7355" stroke-width="10" fill="none"
                          stroke-dasharray="314" stroke-dashoffset="138" transform="rotate(-90 60 60)"/>
                </svg>
                <div class="progress-text">
                  <span class="percentage">56%</span>
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
          <button class="calendar-nav">&lt;</button>
          <h3>JUNE 2025</h3>
          <button class="calendar-nav">&gt;</button>
        </div>
        
        <div class="calendar-grid">
          <div class="calendar-day-header">SUN</div>
          <div class="calendar-day-header">MON</div>
          <div class="calendar-day-header">TUE</div>
          <div class="calendar-day-header">WED</div>
          <div class="calendar-day-header">THU</div>
          <div class="calendar-day-header">FRI</div>
          <div class="calendar-day-header">SAT</div>
          
          <div class="calendar-day">1</div>
          <div class="calendar-day">2</div>
          <div class="calendar-day">3</div>
          <div class="calendar-day">4</div>
          <div class="calendar-day">5</div>
          <div class="calendar-day">6</div>
          <div class="calendar-day">7</div>
          <div class="calendar-day">8</div>
          <div class="calendar-day">9</div>
          <div class="calendar-day">10</div>
          <div class="calendar-day">11</div>
          <div class="calendar-day">12</div>
          <div class="calendar-day">13</div>
          <div class="calendar-day">14</div>
          <div class="calendar-day">15</div>
          <div class="calendar-day">16</div>
          <div class="calendar-day">17</div>
          <div class="calendar-day">18</div>
          <div class="calendar-day">19</div>
          <div class="calendar-day">20</div>
          <div class="calendar-day">21</div>
          <div class="calendar-day">22</div>
          <div class="calendar-day">23</div>
          <div class="calendar-day">24</div>
          <div class="calendar-day">25</div>
          <div class="calendar-day">26</div>
          <div class="calendar-day">27</div>
          <div class="calendar-day">28</div>
          <div class="calendar-day">29</div>
          <div class="calendar-day">30</div>
        </div>
      </div>
    </aside>
  </div>
</body>
</html>