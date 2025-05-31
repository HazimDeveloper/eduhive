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
        <!-- Dashboard content will go here -->
        <h1>Welcome to EduHive Dashboard</h1>
        <p>Select a menu item to get started.</p>
      </div>
    </main>

    <!-- Profile Section -->
    <aside class="profile-section">
      <div class="profile-card">
        <h3 class="profile-title">Profile</h3>
        
        <div class="profile-avatar">
          <div class="avatar-circle">
            <div class="avatar-icon">👤</div>
          </div>
        </div>
        
        <div class="profile-info">
          <div class="profile-detail">
            <span class="label">Name:</span>
            <span class="value">NUR KHALIDA BINTI NAZERI</span>
          </div>
          <div class="profile-detail">
            <span class="label">Email:</span>
            <span class="value">khalidanazeri02@gmail.com</span>
          </div>
        </div>
        
        <div class="profile-actions">
          <button class="profile-btn">Update Profile</button>
          <button class="profile-btn">Settings</button>
          <button class="profile-btn logout">Log Out</button>
        </div>
      </div>
    </aside>
  </div>
</body>
</html>