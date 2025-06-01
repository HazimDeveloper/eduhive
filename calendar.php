<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Ensure user is logged in
requireLogin();

// Get current user data
$user_id = getCurrentUserId();
$user_name = getCurrentUserName() ?: 'User';

// Get current month and year from URL parameters or use current date
$current_month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');
$current_year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');

// Validate month and year
if ($current_month < 1 || $current_month > 12) {
    $current_month = date('n');
}
if ($current_year < 1970 || $current_year > 2050) {
    $current_year = date('Y');
}

// Initialize variables
$events = [];
$class_schedules = [];
$courses = [];
$error_message = '';

try {
    // Get user's courses and class schedules
    $courses = getUserCourses($user_id);
    $class_schedules = getClassSchedules($user_id);
    
    // Get calendar events for the current month
    $events = getCalendarEvents($user_id, $current_year, $current_month);
    
} catch (Exception $e) {
    error_log("Calendar error for user $user_id: " . $e->getMessage());
    $error_message = "Unable to load calendar data.";
}

// Helper functions
function getClassSchedules($user_id) {
    try {
        $database = new Database();
        $query = "SELECT cs.*, c.name as course_name, c.code as course_code, c.color 
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

function getCalendarEvents($user_id, $year, $month) {
    try {
        $database = new Database();
        
        // Get first and last day of the month
        $first_day = date('Y-m-01', mktime(0, 0, 0, $month, 1, $year));
        $last_day = date('Y-m-t', mktime(0, 0, 0, $month, 1, $year));
        
        // Get events from database
        $query = "SELECT e.*, c.name as course_name, c.code as course_code, c.color as course_color 
                  FROM events e 
                  LEFT JOIN courses c ON e.course_id = c.id 
                  WHERE e.user_id = :user_id 
                  AND DATE(e.start_datetime) BETWEEN :first_day AND :last_day
                  ORDER BY e.start_datetime ASC";
        
        $db_events = $database->query($query, [
            ':user_id' => $user_id,
            ':first_day' => $first_day,
            ':last_day' => $last_day
        ]) ?: [];
        
        return $db_events;
        
    } catch (Exception $e) {
        error_log("Error getting calendar events: " . $e->getMessage());
        return [];
    }
}

function getScheduleForDay($class_schedules, $day_name) {
    $schedule_events = [];
    $day_mapping = [
        'sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
        'thursday' => 4, 'friday' => 5, 'saturday' => 6
    ];
    
    foreach ($class_schedules as $schedule) {
        if (strtolower($schedule['day_of_week']) === strtolower($day_name)) {
            $schedule_events[] = $schedule;
        }
    }
    
    return $schedule_events;
}

function getEventsForDay($events, $day, $month, $year) {
    $day_events = [];
    $target_date = sprintf('%04d-%02d-%02d', $year, $month, $day);
    
    foreach ($events as $event) {
        $event_date = date('Y-m-d', strtotime($event['start_datetime']));
        if ($event_date === $target_date) {
            $day_events[] = $event;
        }
    }
    
    return $day_events;
}

// Month navigation
$month_names = [
    1 => 'JANUARY', 2 => 'FEBRUARY', 3 => 'MARCH', 4 => 'APRIL',
    5 => 'MAY', 6 => 'JUNE', 7 => 'JULY', 8 => 'AUGUST',
    9 => 'SEPTEMBER', 10 => 'OCTOBER', 11 => 'NOVEMBER', 12 => 'DECEMBER'
];

// Calendar calculations
$first_day_of_month = mktime(0, 0, 0, $current_month, 1, $current_year);
$days_in_month = date('t', $first_day_of_month);
$day_of_week = date('w', $first_day_of_month); // 0 = Sunday

// Days of week for class schedules
$days_of_week = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Calendar</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://apis.google.com/js/api.js"></script>
  <script src="https://accounts.google.com/gsi/client"></script>
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
        <li class="nav-item active">
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

    <!-- Main Calendar Content -->
    <main class="calendar-main">
      <div class="calendar-header-section">
        <div class="calendar-title-section">
          <h1><?php echo $month_names[$current_month]; ?></h1>
          <h1 class="year"><?php echo $current_year; ?></h1>
        </div>
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?> ></div>
      </div>
      
      <?php if ($error_message): ?>
      <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 8px; margin-bottom: 20px; border: 1px solid #f5c6cb;">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
      <?php endif; ?>
      
      <!-- Calendar Grid -->
      <div class="full-calendar">
        <div class="calendar-header-row">
          <div class="day-header">SUNDAY</div>
          <div class="day-header">MONDAY</div>
          <div class="day-header">TUESDAY</div>
          <div class="day-header">WEDNESDAY</div>
          <div class="day-header">THURSDAY</div>
          <div class="day-header">FRIDAY</div>
          <div class="day-header">SATURDAY</div>
        </div>
        
        <div class="calendar-body" id="calendarGrid">
          <?php
          $calendar_day = 1;
          $today = date('j');
          $today_month = date('n');
          $today_year = date('Y');
          
          // Calculate total weeks needed
          $weeks = ceil(($days_in_month + $day_of_week) / 7);
          
          for ($week = 0; $week < $weeks; $week++) {
            for ($day = 0; $day < 7; $day++) {
              $cell_number = ($week * 7) + $day;
              
              echo '<div class="calendar-cell';
              
              if ($cell_number < $day_of_week || $calendar_day > $days_in_month) {
                echo ' empty';
              } else {
                // Check if this is today
                if ($calendar_day == $today && $current_month == $today_month && $current_year == $today_year) {
                  echo ' today';
                }
              }
              
              echo '" onclick="selectDate(' . ($calendar_day <= $days_in_month && $cell_number >= $day_of_week ? $calendar_day : 0) . ')">';
              
              if ($cell_number >= $day_of_week && $calendar_day <= $days_in_month) {
                echo '<div class="date-number">' . $calendar_day . '</div>';
                
                echo '<div class="event-container">';
                
                // Get class schedules for this day
                $day_name = $days_of_week[$day];
                $day_schedules = getScheduleForDay($class_schedules, $day_name);
                
                // Show class schedules
                foreach ($day_schedules as $schedule) {
                  $class_name = !empty($schedule['course_code']) ? $schedule['course_code'] : $schedule['class_code'];
                  echo '<div class="event event-brown" title="' . htmlspecialchars($class_name . ' - ' . $schedule['start_time']) . '">';
                  echo htmlspecialchars($class_name);
                  echo '</div>';
                }
                
                // Get regular events for this day
                $day_events = getEventsForDay($events, $calendar_day, $current_month, $current_year);
                
                foreach ($day_events as $event) {
                  $event_class = 'event';
                  
                  // Determine event color based on type
                  switch ($event['event_type']) {
                    case 'exam':
                      $event_class .= ' event-red';
                      break;
                    case 'assignment':
                      $event_class .= ' event-yellow';
                      break;
                    case 'meeting':
                      $event_class .= ' event-blue';
                      break;
                    case 'class':
                      $event_class .= ' event-brown';
                      break;
                    default:
                      $event_class .= ' event-green';
                  }
                  
                  $event_title = !empty($event['course_code']) ? $event['course_code'] : $event['title'];
                  echo '<div class="' . $event_class . '" onclick="viewEvent(' . $event['id'] . ')" title="' . htmlspecialchars($event['title']) . '">';
                  echo htmlspecialchars(strlen($event_title) > 8 ? substr($event_title, 0, 8) . '...' : $event_title);
                  echo '</div>';
                }
                
                echo '</div>';
                
                $calendar_day++;
              }
              
              echo '</div>';
            }
          }
          ?>
        </div>
      </div>
      
      <!-- Google Calendar Integration Controls -->
      <div class="calendar-controls">
        <button id="authorizeButton" class="control-btn">🔗 Connect Google Calendar</button>
        <button id="signoutButton" class="control-btn" style="display: none;">🔐 Sign Out</button>
        <button id="loadEventsButton" class="control-btn" style="display: none;">📥 Load Google Events</button>
        <button id="addEventButton" class="control-btn" style="display: none;">➕ Add Event</button>
        <button id="syncSchedulesButton" class="control-btn">📚 Sync Class Schedules</button>
      </div>
    </main>
  </div>

  <!-- Event Form Modal -->
  <div id="eventModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3>Add New Event</h3>
      <form id="eventForm">
        <input type="text" id="eventTitle" placeholder="Event Title" required>
        <input type="date" id="eventDate" required>
        <input type="time" id="eventTime" required>
        <textarea id="eventDescription" placeholder="Description"></textarea>
        
        <select id="eventType" required>
          <option value="">Select Event Type</option>
          <option value="class">Class</option>
          <option value="exam">Exam</option>
          <option value="assignment">Assignment</option>
          <option value="meeting">Meeting</option>
          <option value="other">Other</option>
        </select>
        
        <select id="eventCourse">
          <option value="">Select Course (Optional)</option>
          <?php foreach ($courses as $course): ?>
          <option value="<?php echo $course['id']; ?>"><?php echo htmlspecialchars($course['name']); ?></option>
          <?php endforeach; ?>
        </select>
        
        <div class="form-buttons">
          <button type="submit">Add Event</button>
          <button type="button" id="cancelEvent">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- View Event Modal -->
  <div id="viewEventModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3 id="viewEventTitle">Event Details</h3>
      <div id="viewEventContent"></div>
      <div class="form-buttons">
        <button type="button" id="editEventBtn">Edit</button>
        <button type="button" id="deleteEventBtn">Delete</button>
        <button type="button" id="closeViewEvent">Close</button>
      </div>
    </div>
  </div>

  <script>
    // Google Calendar API configuration
    const CLIENT_ID = 'your-google-client-id.googleusercontent.com';
    const API_KEY = 'your-google-api-key';
    const DISCOVERY_DOC = 'https://www.googleapis.com/discovery/v1/apis/calendar/v3/rest';
    const SCOPES = 'https://www.googleapis.com/auth/calendar';

    let tokenClient;
    let gapi_inited = false;
    let gis_inited = false;
    let selectedDate = null;
    let currentEventId = null;

    // Initialize Google APIs
    function gapiLoaded() {
      gapi.load('client', initializeGapiClient);
    }

    async function initializeGapiClient() {
      await gapi.client.init({
        apiKey: API_KEY,
        discoveryDocs: [DISCOVERY_DOC],
      });
      gapi_inited = true;
      maybeEnableButtons();
    }

    function gisLoaded() {
      tokenClient = google.accounts.oauth2.initTokenClient({
        client_id: CLIENT_ID,
        scope: SCOPES,
        callback: '', // defined later
      });
      gis_inited = true;
      maybeEnableButtons();
    }

    function maybeEnableButtons() {
      if (gapi_inited && gis_inited) {
        document.getElementById('authorizeButton').style.display = 'inline-block';
      }
    }

    // Authorization
    function handleAuthClick() {
      tokenClient.callback = async (resp) => {
        if (resp.error !== undefined) {
          throw (resp);
        }
        document.getElementById('signoutButton').style.display = 'inline-block';
        document.getElementById('authorizeButton').style.display = 'none';
        document.getElementById('loadEventsButton').style.display = 'inline-block';
        document.getElementById('addEventButton').style.display = 'inline-block';
        
        showNotification('Google Calendar connected successfully!', 'success');
      };

      if (gapi.client.getToken() === null) {
        tokenClient.requestAccessToken({prompt: 'consent'});
      } else {
        tokenClient.requestAccessToken({prompt: ''});
      }
    }

    function handleSignoutClick() {
      const token = gapi.client.getToken();
      if (token !== null) {
        google.accounts.oauth2.revoke(token.access_token);
        gapi.client.setToken('');
        document.getElementById('authorizeButton').style.display = 'inline-block';
        document.getElementById('signoutButton').style.display = 'none';
        document.getElementById('loadEventsButton').style.display = 'none';
        document.getElementById('addEventButton').style.display = 'none';
        
        showNotification('Disconnected from Google Calendar', 'info');
      }
    }

    // Load events from Google Calendar
    async function loadGoogleCalendarEvents() {
      try {
        const currentMonth = <?php echo $current_month; ?>;
        const currentYear = <?php echo $current_year; ?>;
        
        const timeMin = new Date(currentYear, currentMonth - 1, 1).toISOString();
        const timeMax = new Date(currentYear, currentMonth, 0, 23, 59, 59).toISOString();
        
        const request = {
          'calendarId': 'primary',
          'timeMin': timeMin,
          'timeMax': timeMax,
          'showDeleted': false,
          'singleEvents': true,
          'maxResults': 100,
          'orderBy': 'startTime',
        };
        
        const response = await gapi.client.calendar.events.list(request);
        const events = response.result.items;
        
        if (events && events.length > 0) {
          await saveGoogleEventsToDatabase(events);
          showNotification(`Loaded ${events.length} events from Google Calendar`, 'success');
          setTimeout(() => location.reload(), 1000);
        } else {
          showNotification('No events found in Google Calendar for this month', 'info');
        }
      } catch (err) {
        console.error('Error loading Google Calendar events:', err);
        showNotification('Error loading Google Calendar events', 'error');
      }
    }

    // Save Google Calendar events to database
    async function saveGoogleEventsToDatabase(events) {
      for (const event of events) {
        const eventData = {
          title: event.summary || 'No title',
          description: event.description || '',
          start_datetime: event.start.dateTime || event.start.date,
          end_datetime: event.end.dateTime || event.end.date,
          location: event.location || '',
          event_type: 'other',
          google_event_id: event.id
        };
        
        try {
          const response = await fetch('api/events.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
            },
            body: JSON.stringify(eventData)
          });
          
          if (!response.ok) {
            console.error('Failed to save event:', event.summary);
          }
        } catch (error) {
          console.error('Error saving event to database:', error);
        }
      }
    }

    // Add event to Google Calendar
    async function addEventToGoogleCalendar(eventData) {
      const event = {
        'summary': eventData.title,
        'description': eventData.description,
        'start': {
          'dateTime': eventData.start_datetime,
          'timeZone': 'Asia/Kuala_Lumpur',
        },
        'end': {
          'dateTime': eventData.end_datetime,
          'timeZone': 'Asia/Kuala_Lumpur',
        },
        'location': eventData.location
      };

      try {
        const request = await gapi.client.calendar.events.insert({
          'calendarId': 'primary',
          'resource': event
        });
        
        showNotification('Event added to Google Calendar!', 'success');
        return request.result.id;
      } catch (err) {
        console.error('Error creating Google Calendar event:', err);
        showNotification('Error adding event to Google Calendar', 'error');
        return null;
      }
    }

    // Sync class schedules to Google Calendar
    async function syncClassSchedulesToGoogle() {
      if (!gapi.client.getToken()) {
        showNotification('Please connect to Google Calendar first', 'error');
        return;
      }
      
      try {
        const response = await fetch('api/sync_schedules.php');
        const data = await response.json();
        
        if (data.success) {
          for (const schedule of data.schedules) {
            await addClassScheduleToGoogle(schedule);
          }
          showNotification('Class schedules synced to Google Calendar!', 'success');
        } else {
          showNotification('Error syncing schedules: ' + data.message, 'error');
        }
      } catch (error) {
        console.error('Error syncing schedules:', error);
        showNotification('Error syncing class schedules', 'error');
      }
    }

    async function addClassScheduleToGoogle(schedule) {
      // Create recurring events for class schedules
      const event = {
        'summary': schedule.class_code + ' - ' + schedule.course_name,
        'description': `Class: ${schedule.course_name}\nLocation: ${schedule.location}\nMode: ${schedule.mode}`,
        'start': {
          'dateTime': getNextClassDateTime(schedule),
          'timeZone': 'Asia/Kuala_Lumpur',
        },
        'end': {
          'dateTime': getNextClassEndDateTime(schedule),
          'timeZone': 'Asia/Kuala_Lumpur',
        },
        'location': schedule.location,
        'recurrence': [
          'RRULE:FREQ=WEEKLY;BYDAY=' + getDayAbbr(schedule.day_of_week)
        ]
      };

      try {
        await gapi.client.calendar.events.insert({
          'calendarId': 'primary',
          'resource': event
        });
      } catch (err) {
        console.error('Error adding class schedule to Google Calendar:', err);
      }
    }

    function getNextClassDateTime(schedule) {
      // Calculate next occurrence of this class
      const today = new Date();
      const dayMap = {'sunday': 0, 'monday': 1, 'tuesday': 2, 'wednesday': 3, 'thursday': 4, 'friday': 5, 'saturday': 6};
      const targetDay = dayMap[schedule.day_of_week.toLowerCase()];
      
      let nextDate = new Date(today);
      while (nextDate.getDay() !== targetDay) {
        nextDate.setDate(nextDate.getDate() + 1);
      }
      
      const [hours, minutes] = schedule.start_time.split(':');
      nextDate.setHours(parseInt(hours), parseInt(minutes), 0, 0);
      
      return nextDate.toISOString();
    }

    function getNextClassEndDateTime(schedule) {
      const startDateTime = new Date(getNextClassDateTime(schedule));
      const [hours, minutes] = schedule.end_time.split(':');
      const endDateTime = new Date(startDateTime);
      endDateTime.setHours(parseInt(hours), parseInt(minutes), 0, 0);
      
      return endDateTime.toISOString();
    }

    function getDayAbbr(dayName) {
      const dayMap = {
        'sunday': 'SU', 'monday': 'MO', 'tuesday': 'TU', 'wednesday': 'WE',
        'thursday': 'TH', 'friday': 'FR', 'saturday': 'SA'
      };
      return dayMap[dayName.toLowerCase()] || 'MO';
    }

    // Event management functions
    function selectDate(day) {
      if (day > 0) {
        const year = <?php echo $current_year; ?>;
        const month = <?php echo $current_month; ?>;
        selectedDate = `${year}-${month.toString().padStart(2, '0')}-${day.toString().padStart(2, '0')}`;
        
        // Remove previous selections
        document.querySelectorAll('.calendar-cell.selected').forEach(cell => {
          cell.classList.remove('selected');
        });
        
        // Add selection to clicked cell
        event.currentTarget.classList.add('selected');
        
        if (gapi.client.getToken()) {
          openEventModal();
        } else {
          showNotification('Connect to Google Calendar to add events', 'info');
        }
      }
    }

    function openEventModal() {
      const modal = document.getElementById('eventModal');
      const form = document.getElementById('eventForm');
      
      form.reset();
      
      if (selectedDate) {
        document.getElementById('eventDate').value = selectedDate;
      }
      
      modal.style.display = 'block';
    }

    function viewEvent(eventId) {
      event.stopPropagation();
      // Implementation for viewing event details
      showNotification('Event details functionality coming soon!', 'info');
    }

    // Initialize event listeners
    document.addEventListener('DOMContentLoaded', function() {
      // Modal controls
      const eventModal = document.getElementById('eventModal');
      const viewEventModal = document.getElementById('viewEventModal');
      const closeModals = document.querySelectorAll('.close');
      
      // Button event listeners
      document.getElementById('authorizeButton').addEventListener('click', handleAuthClick);
      document.getElementById('signoutButton').addEventListener('click', handleSignoutClick);
      document.getElementById('loadEventsButton').addEventListener('click', loadGoogleCalendarEvents);
      document.getElementById('addEventButton').addEventListener('click', openEventModal);
      document.getElementById('syncSchedulesButton').addEventListener('click', syncClassSchedulesToGoogle);
      
      // Close modal events
      closeModals.forEach(close => {
        close.addEventListener('click', () => {
          eventModal.style.display = 'none';
          viewEventModal.style.display = 'none';
        });
      });
      
      document.getElementById('cancelEvent').addEventListener('click', () => {
        eventModal.style.display = 'none';
      });
      
      // Form submission
      document.getElementById('eventForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        
        const formData = new FormData(e.target);
        const eventData = {
          title: formData.get('title'),
          description: formData.get('description'),
          start_datetime: formData.get('event_date') + 'T' + formData.get('start_time'),
          end_datetime: formData.get('event_date') + 'T' + (formData.get('end_time') || formData.get('start_time')),
          event_type: formData.get('event_type'),
          course_id: formData.get('course_id') || null
        };
        
        // Add to Google Calendar
        const googleEventId = await addEventToGoogleCalendar(eventData);
        
        if (googleEventId) {
          eventData.google_event_id = googleEventId;
          
          // Save to local database
          try {
            const response = await fetch('api/events.php', {
              method: 'POST',
              headers: {
                'Content-Type': 'application/json',
              },
              body: JSON.stringify(eventData)
            });
            
            if (response.ok) {
              showNotification('Event added successfully!', 'success');
              eventModal.style.display = 'none';
              setTimeout(() => location.reload(), 1000);
            } else {
              showNotification('Event added to Google Calendar but failed to save locally', 'warning');
            }
          } catch (error) {
            showNotification('Event added to Google Calendar but failed to save locally', 'warning');
          }
        }
      });
      
      // Close modals on outside click
      window.addEventListener('click', (event) => {
        if (event.target === eventModal) {
          eventModal.style.display = 'none';
        }
        if (event.target === viewEventModal) {
          viewEventModal.style.display = 'none';
        }
      });
    });

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
      }, 4000);
    }

    // Load Google APIs when page loads
    window.onload = function() {
      if (typeof gapi !== 'undefined') {
        gapiLoaded();
      }
      if (typeof google !== 'undefined') {
        gisLoaded();
      }
    };
  </script>
  
  <!-- Load Google APIs -->
  <script async defer src="https://apis.google.com/js/api.js" onload="gapiLoaded()"></script>
  <script async defer src="https://accounts.google.com/gsi/client" onload="gisLoaded()"></script>

  <style>
    /* Calendar-specific styles to match the original design */
    .calendar-cell.selected {
      box-shadow: inset 0 0 0 2px #8B7355;
    }
    
    .control-btn {
      margin: 5px;
      padding: 10px 15px;
      font-size: 14px;
    }
    
    .notification {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    /* Ensure proper event spacing */
    .event-container .event {
      margin-bottom: 2px;
    }
    
    .event-container .event:last-child {
      margin-bottom: 0;
    }
  </style>
</body>
</html>