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
$class_schedules = [];
$courses = [];
$error_message = '';

try {
    // Get user's courses
    $courses = getUserCourses($user_id);
    
    // Get class schedules with course information
    $class_schedules = getClassSchedulesWithCourses($user_id);
    
} catch (Exception $e) {
    error_log("Class schedule error for user $user_id: " . $e->getMessage());
    $error_message = "Unable to load class schedule data.";
}

// Helper function to get class schedules with course data
function getClassSchedulesWithCourses($user_id) {
    try {
        $database = new Database();
        $query = "SELECT cs.*, c.name as course_name, c.code as course_code, c.color as course_color
                  FROM class_schedules cs 
                  LEFT JOIN courses c ON cs.course_id = c.id 
                  WHERE cs.user_id = :user_id 
                  ORDER BY cs.day_of_week, cs.start_time";
        
        return $database->query($query, [':user_id' => $user_id]) ?: [];
        
    } catch (Exception $e) {
        error_log("Error getting class schedules: " . $e->getMessage());
        return [];
    }
}

// Helper function to get classes for specific day and time
function getClassForSlot($schedules, $day, $time_slot) {
    $time_ranges = [
        '8:00 - 09:59' => ['08:00:00', '09:59:59'],
        '10:00 - 11:59' => ['10:00:00', '11:59:59'],
        '12:00 - 13:59' => ['12:00:00', '13:59:59'],
        '14:00 - 15:59' => ['14:00:00', '15:59:59'],
        '16:00 - 17:59' => ['16:00:00', '17:59:59'],
        '18:00 - 19:59' => ['18:00:00', '19:59:59']
    ];
    
    if (!isset($time_ranges[$time_slot])) {
        return null;
    }
    
    $start_range = $time_ranges[$time_slot][0];
    $end_range = $time_ranges[$time_slot][1];
    
    foreach ($schedules as $schedule) {
        if (strtolower($schedule['day_of_week']) === strtolower($day)) {
            $class_start = $schedule['start_time'];
            $class_end = $schedule['end_time'];
            
            // Check if class time overlaps with time slot
            if ($class_start >= $start_range && $class_start <= $end_range) {
                return $schedule;
            }
        }
    }
    
    return null;
}

// Days and time slots
$days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday'];
$time_slots = [
    '8:00 - 09:59',
    '10:00 - 11:59', 
    '12:00 - 13:59',
    '14:00 - 15:59',
    '16:00 - 17:59',
    '18:00 - 19:59'
];

// Function to get display name for class
function getClassDisplayName($schedule) {
    if (!empty($schedule['course_code'])) {
        return $schedule['course_code'];
    }
    return $schedule['class_code'];
}

// Function to get class mode/location
function getClassMode($schedule) {
    if (!empty($schedule['location'])) {
        return $schedule['location'];
    }
    return strtoupper($schedule['mode']);
}

// Function to get class card color based on course or type
function getClassColor($schedule) {
    if (!empty($schedule['course_color'])) {
        return $schedule['course_color'];
    }
    
    // Default colors based on class type or code
    $class_name = strtolower($schedule['class_code']);
    
    if (strpos($class_name, 'lmcr') !== false) {
        return '#8B7355'; // Brown for LMCR courses
    } elseif (strpos($class_name, 'tp') !== false) {
        return '#d4956c'; // Light brown for TP courses  
    } elseif (strpos($class_name, 'tn') !== false) {
        return '#6c757d'; // Gray for TN courses
    } elseif (strpos($class_name, 'tu') !== false) {
        return '#b19176'; // Medium brown for TU courses
    } elseif (strpos($class_name, 'fyp') !== false) {
        return '#8B7355'; // Brown for FYP
    } else {
        return '#c4a68a'; // Default light brown
    }
}
?>
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
          <a href="dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a href="calendar.php">Calendar</a>
        </li>
        <li class="nav-item active">
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

    <!-- Main Schedule Content -->
    <main class="schedule-main">
      <div class="schedule-header">
        <h1>CLASS SCHEDULE</h1>
        <div class="user-info">
          <span class="user-name"><?php echo htmlspecialchars($user_name); ?> ></span>
          <button class="update-btn" id="updateBtn">Update</button>
        </div>
      </div>
      
      <?php if ($error_message): ?>
      <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
      <?php endif; ?>
      
      <!-- Schedule Table -->
      <div class="schedule-container">
        <div class="schedule-grid">
          <!-- Time Column -->
          <div class="time-column">
            <div class="time-header"></div>
            <?php foreach ($time_slots as $time_slot): ?>
            <div class="time-slot"><?php echo $time_slot; ?></div>
            <?php endforeach; ?>
          </div>
          
          <!-- Day Columns -->
          <?php foreach ($days as $day): ?>
          <div class="day-column">
            <div class="day-header"><?php echo ucfirst(substr($day, 0, 3)); ?></div>
            
            <?php foreach ($time_slots as $time_slot): ?>
            <div class="class-slot">
              <?php 
              $class = getClassForSlot($class_schedules, $day, $time_slot);
              if ($class): 
                $color = getClassColor($class);
                $display_name = getClassDisplayName($class);
                $mode = getClassMode($class);
              ?>
              <div class="class-card" 
                   style="background: <?php echo $color; ?>; color: white;"
                   onclick="viewClassDetails(<?php echo $class['id']; ?>)"
                   title="<?php echo htmlspecialchars($display_name . ' - ' . $class['start_time'] . ' to ' . $class['end_time']); ?>">
                <div class="class-code"><?php echo htmlspecialchars($display_name); ?></div>
                <div class="class-mode"><?php echo htmlspecialchars($mode); ?></div>
              </div>
              <?php endif; ?>
            </div>
            <?php endforeach; ?>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      
      <!-- Schedule Actions -->
      <div class="schedule-actions">
        <button class="action-btn primary" id="addClassBtn">Add New Class</button>
        <button class="action-btn secondary" id="exportBtn">Export Schedule</button>
        <button class="action-btn secondary" id="printBtn">Print Schedule</button>
        <button class="action-btn secondary" id="syncCalendarBtn">Sync to Calendar</button>
      </div>
    </main>
  </div>

  <!-- Add Class Modal -->
  <div id="addClassModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3>Add New Class</h3>
      <form id="addClassForm">
        <input type="text" id="classCode" name="class_code" placeholder="Class Code (e.g., LMCR3362)" required>
        
        <select id="classCourse" name="course_id">
          <option value="">Select Course (Optional)</option>
          <?php foreach ($courses as $course): ?>
          <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name'] . ' (' . $course['code'] . ')'); ?></option>
          <?php endforeach; ?>
        </select>
        
        <select id="classDay" name="day_of_week" required>
          <option value="">Select Day</option>
          <option value="monday">Monday</option>
          <option value="tuesday">Tuesday</option>
          <option value="wednesday">Wednesday</option>
          <option value="thursday">Thursday</option>
          <option value="friday">Friday</option>
        </select>
        
        <input type="time" id="classStartTime" name="start_time" placeholder="Start Time" required>
        <input type="time" id="classEndTime" name="end_time" placeholder="End Time" required>
        <input type="text" id="classLocation" name="location" placeholder="Location/Mode (e.g., ONLINE, BK4)">
        <input type="text" id="classInstructor" name="instructor" placeholder="Instructor (Optional)">
        
        <select id="classMode" name="mode">
          <option value="physical">Physical</option>
          <option value="online">Online</option>
        </select>
        
        <div class="form-buttons">
          <button type="submit">Add Class</button>
          <button type="button" id="cancelClass">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View Class Details Modal -->
  <div id="viewClassModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3 id="classDetailsTitle">Class Details</h3>
      <div id="classDetailsContent">
        <!-- Class details will be loaded here -->
      </div>
      <div class="form-buttons">
        <button type="button" id="editClassBtn">Edit Class</button>
        <button type="button" id="deleteClassBtn">Delete Class</button>
        <button type="button" id="closeClassDetails">Close</button>
      </div>
    </div>
  </div>

  <script>
    let currentClassId = null;

    document.addEventListener('DOMContentLoaded', function() {
      initializeEventListeners();
    });

    function initializeEventListeners() {
      const addClassModal = document.getElementById('addClassModal');
      const viewClassModal = document.getElementById('viewClassModal');
      const addClassBtn = document.getElementById('addClassBtn');
      const updateBtn = document.getElementById('updateBtn');
      const exportBtn = document.getElementById('exportBtn');
      const printBtn = document.getElementById('printBtn');
      const syncCalendarBtn = document.getElementById('syncCalendarBtn');
      const closeModals = document.querySelectorAll('.close');
      const cancelBtn = document.getElementById('cancelClass');
      const addClassForm = document.getElementById('addClassForm');

      // Add class button
      addClassBtn.addEventListener('click', () => {
        addClassModal.style.display = 'block';
      });

      // Update button
      updateBtn.addEventListener('click', () => {
        showNotification('Syncing with academic system...', 'info');
        setTimeout(() => {
          location.reload();
        }, 1500);
      });

      // Export button
      exportBtn.addEventListener('click', exportSchedule);

      // Print button
      printBtn.addEventListener('click', () => {
        window.print();
      });

      // Sync to calendar button
      syncCalendarBtn.addEventListener('click', syncToCalendar);

      // Close modals
      closeModals.forEach(close => {
        close.addEventListener('click', () => {
          addClassModal.style.display = 'none';
          viewClassModal.style.display = 'none';
        });
      });

      cancelBtn.addEventListener('click', () => {
        addClassModal.style.display = 'none';
      });

      document.getElementById('closeClassDetails').addEventListener('click', () => {
        viewClassModal.style.display = 'none';
      });

      // Close modal on outside click
      window.addEventListener('click', (event) => {
        if (event.target === addClassModal) {
          addClassModal.style.display = 'none';
        }
        if (event.target === viewClassModal) {
          viewClassModal.style.display = 'none';
        }
      });

      // Form submission
      addClassForm.addEventListener('submit', handleAddClass);

      // Edit and delete buttons
      document.getElementById('editClassBtn').addEventListener('click', editCurrentClass);
      document.getElementById('deleteClassBtn').addEventListener('click', deleteCurrentClass);
    }

    function handleAddClass(e) {
      e.preventDefault();
      
      const formData = new FormData(e.target);
      
      fetch('api/class_schedules.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Class added successfully!', 'success');
          document.getElementById('addClassModal').style.display = 'none';
          e.target.reset();
          setTimeout(() => location.reload(), 1000);
        } else {
          showNotification('Failed to add class: ' + data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error adding class:', error);
        showNotification('Error adding class', 'error');
      });
    }

    function viewClassDetails(classId) {
      currentClassId = classId;
      
      fetch(`api/class_schedules.php?id=${classId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            displayClassDetails(data.data);
          } else {
            showNotification('Failed to load class details', 'error');
          }
        })
        .catch(error => {
          console.error('Error fetching class details:', error);
          showNotification('Error loading class details', 'error');
        });
    }

    function displayClassDetails(classData) {
      document.getElementById('classDetailsTitle').textContent = classData.class_code + ' - Class Details';
      
      const content = document.getElementById('classDetailsContent');
      content.innerHTML = `
        <div class="class-detail-item">
          <strong>Class Code:</strong> ${classData.class_code}
        </div>
        <div class="class-detail-item">
          <strong>Course:</strong> ${classData.course_name || 'No course assigned'}
        </div>
        <div class="class-detail-item">
          <strong>Day:</strong> ${classData.day_of_week.charAt(0).toUpperCase() + classData.day_of_week.slice(1)}
        </div>
        <div class="class-detail-item">
          <strong>Time:</strong> ${classData.start_time} - ${classData.end_time}
        </div>
        <div class="class-detail-item">
          <strong>Location:</strong> ${classData.location || 'No location specified'}
        </div>
        <div class="class-detail-item">
          <strong>Mode:</strong> ${classData.mode || 'Physical'}
        </div>
        <div class="class-detail-item">
          <strong>Instructor:</strong> ${classData.instructor || 'No instructor specified'}
        </div>
      `;
      
      document.getElementById('viewClassModal').style.display = 'block';
    }

    function editCurrentClass() {
      if (currentClassId) {
        showNotification('Edit functionality coming soon!', 'info');
      }
    }

    function deleteCurrentClass() {
      if (currentClassId && confirm('Are you sure you want to delete this class?')) {
        fetch('api/class_schedules.php', {
          method: 'DELETE',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `id=${currentClassId}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showNotification('Class deleted successfully!', 'success');
            document.getElementById('viewClassModal').style.display = 'none';
            setTimeout(() => location.reload(), 1000);
          } else {
            showNotification('Failed to delete class: ' + data.message, 'error');
          }
        })
        .catch(error => {
          console.error('Error deleting class:', error);
          showNotification('Error deleting class', 'error');
        });
      }
    }

    function exportSchedule() {
      const scheduleData = {
        user: '<?php echo htmlspecialchars($user_name); ?>',
        classes: []
      };
      
      // Collect schedule data
      document.querySelectorAll('.class-card').forEach(card => {
        const code = card.querySelector('.class-code').textContent;
        const mode = card.querySelector('.class-mode').textContent;
        scheduleData.classes.push({ code, mode });
      });
      
      // Create and download JSON file
      const dataStr = JSON.stringify(scheduleData, null, 2);
      const dataBlob = new Blob([dataStr], {type: 'application/json'});
      const url = URL.createObjectURL(dataBlob);
      const link = document.createElement('a');
      link.href = url;
      link.download = 'class_schedule.json';
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);
      URL.revokeObjectURL(url);
      
      showNotification('Schedule exported successfully!', 'success');
    }

    function syncToCalendar() {
      fetch('api/sync_schedules.php')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            showNotification(`${data.count} class schedules ready for calendar sync!`, 'success');
            // Open calendar page
            setTimeout(() => {
              window.open('calendar.php', '_blank');
            }, 1000);
          } else {
            showNotification('Failed to prepare schedule sync: ' + data.message, 'error');
          }
        })
        .catch(error => {
          console.error('Error syncing to calendar:', error);
          showNotification('Error syncing to calendar', 'error');
        });
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
      } else if (type === 'warning') {
        notification.style.backgroundColor = '#ffc107';
        notification.style.color = '#333';
      } else {
        notification.style.backgroundColor = '#17a2b8';
      }
      
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.style.opacity = '1';
      }, 100);
      
      setTimeout(() => {
        notification.style.opacity = '0';
        setTimeout(() => {
          if (document.body.contains(notification)) {
            document.body.removeChild(notification);
          }
        }, 300);
      }, 3000);
    }

    // Make class cards interactive with hover effects
    document.querySelectorAll('.class-card').forEach(card => {
      card.addEventListener('mouseenter', function() {
        this.style.transform = 'translateY(-2px)';
        this.style.boxShadow = '0 6px 20px rgba(0, 0, 0, 0.15)';
      });
      
      card.addEventListener('mouseleave', function() {
        this.style.transform = 'translateY(0)';
        this.style.boxShadow = '0 2px 10px rgba(139, 115, 85, 0.2)';
      });
    });
  </script>

  <style>
    /* Additional styles for class details */
    .class-detail-item {
      margin: 12px 0;
      padding: 10px 0;
      border-bottom: 1px solid #f1f3f4;
      font-size: 14px;
    }

    .class-detail-item:last-child {
      border-bottom: none;
    }

    .class-detail-item strong {
      color: #8B7355;
      margin-right: 10px;
    }

    /* Notification styles */
    .notification {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    /* Print styles */
    @media print {
      .sidebar,
      .schedule-actions,
      .update-btn {
        display: none !important;
      }
      
      .schedule-main {
        padding: 0;
      }
      
      .schedule-container {
        box-shadow: none;
        border: 1px solid #ccc;
      }
      
      .class-card {
        background: #f8f9fa !important;
        color: #333 !important;
        border: 1px solid #ccc;
      }
    }
  </style>
</body>
</html>