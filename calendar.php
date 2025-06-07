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
$google_config = null;
$auto_sync_enabled = false;

try {
    // Get user's Google configuration
    $google_config = getUserGoogleConfig($user_id);
    
    // Check if auto-sync should be enabled
    if ($google_config && !empty($google_config['google_client_id']) && !empty($google_config['google_api_key'])) {
        $auto_sync_enabled = true;
    }
    
    // Get user's courses and class schedules
    $courses = getUserCourses($user_id);
    $class_schedules = getClassSchedules($user_id);
    
    // Get calendar events for the current month
    $events = getCalendarEvents($user_id, $current_year, $current_month);
    
} catch (Exception $e) {
    error_log("Calendar error for user $user_id: " . $e->getMessage());
    $error_message = "Unable to load calendar data.";
}

// Function to get user's Google configuration
function getUserGoogleConfig($user_id) {
    try {
        $database = new Database();
        $query = "SELECT * FROM user_settings WHERE user_id = :user_id AND setup_completed = 1";
        return $database->queryRow($query, [':user_id' => $user_id]);
    } catch (Exception $e) {
        error_log("Error getting Google config: " . $e->getMessage());
        return null;
    }
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
          <img src="logoo.png" width="40px" alt="">
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
        <div class="user-name">
          <?php if ($auto_sync_enabled): ?>
            <span id="syncStatus" class="sync-status">🔄 Auto-Sync Enabled</span>
          <?php else: ?>
            <span class="sync-status offline">⚠️ Setup Google Calendar to enable auto-sync</span>
          <?php endif; ?>
          <?php echo htmlspecialchars($user_name); ?> >
        </div>
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
      
    </main>
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
    // Auto-sync configuration
    const AUTO_SYNC_ENABLED = <?php echo $auto_sync_enabled ? 'true' : 'false'; ?>;
    const GOOGLE_CONFIG = <?php echo $google_config ? json_encode($google_config) : 'null'; ?>;
    
    // Google Calendar API configuration
    let CLIENT_ID = '';
    let API_KEY = '';
    let DISCOVERY_DOC = 'https://www.googleapis.com/discovery/v1/apis/calendar/v3/rest';
    let SCOPES = 'https://www.googleapis.com/auth/calendar.readonly';

    let tokenClient;
    let gapi_inited = false;
    let gis_inited = false;
    let selectedDate = null;
    let currentEventId = null;
    let autoSyncTimer = null;

    // Initialize with user's Google configuration
    if (GOOGLE_CONFIG) {
        CLIENT_ID = GOOGLE_CONFIG.google_client_id || '';
        API_KEY = GOOGLE_CONFIG.google_api_key || '';
    }

    // Initialize Google APIs
    function gapiLoaded() {
        if (API_KEY) {
            gapi.load('client', initializeGapiClient);
        }
    }

    async function initializeGapiClient() {
        try {
            await gapi.client.init({
                apiKey: API_KEY,
                discoveryDocs: [DISCOVERY_DOC],
            });
            gapi_inited = true;
            maybeEnableAutoSync();
        } catch (error) {
            console.error('Error initializing Google API client:', error);
        }
    }

    function gisLoaded() {
        if (CLIENT_ID) {
            tokenClient = google.accounts.oauth2.initTokenClient({
                client_id: CLIENT_ID,
                scope: SCOPES,
                callback: handleAuthCallback,
            });
            gis_inited = true;
            maybeEnableAutoSync();
        }
    }

    function maybeEnableAutoSync() {
        if (AUTO_SYNC_ENABLED && gapi_inited && gis_inited) {
            // Try to get existing token from storage
            const savedToken = localStorage.getItem('google_calendar_token');
            if (savedToken) {
                try {
                    const tokenData = JSON.parse(savedToken);
                    if (tokenData.expires_at > Date.now()) {
                        gapi.client.setToken(tokenData);
                        startAutoSync();
                        updateSyncStatus('✅ Auto-Sync Active', 'active');
                        return;
                    }
                } catch (e) {
                    console.log('Invalid saved token');
                }
            }
            
            // Auto-authorize silently
            autoAuthorize();
        }
    }

    function handleAuthCallback(resp) {
        if (resp.error !== undefined) {
            console.error('Authorization error:', resp);
            updateSyncStatus('❌ Authorization Failed', 'error');
            return;
        }
        
        // Save token with expiration
        const tokenData = {
            ...resp,
            expires_at: Date.now() + (resp.expires_in * 1000)
        };
        localStorage.setItem('google_calendar_token', JSON.stringify(tokenData));
        
        startAutoSync();
        updateSyncStatus('✅ Auto-Sync Active', 'active');
        showNotification('Google Calendar connected successfully!', 'success');
    }

    function autoAuthorize() {
        if (gapi.client.getToken() === null) {
            // Request access token with prompt: none for silent auth
            tokenClient.requestAccessToken({prompt: ''});
        } else {
            startAutoSync();
            updateSyncStatus('✅ Auto-Sync Active', 'active');
        }
    }

    function startAutoSync() {
        if (autoSyncTimer) {
            clearInterval(autoSyncTimer);
        }
        
        // Initial sync
        syncGoogleCalendarEvents();
        
        // Set up periodic sync every 15 minutes
        autoSyncTimer = setInterval(() => {
            syncGoogleCalendarEvents();
        }, 15 * 60 * 1000);
        
        console.log('Auto-sync started - syncing every 15 minutes');
    }

    async function syncGoogleCalendarEvents() {
        if (!gapi.client.getToken()) {
            console.log('No auth token available for sync');
            return;
        }

        try {
            updateSyncStatus('🔄 Syncing...', 'syncing');
            
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
                updateSyncStatus('✅ Auto-Sync Active', 'active');
                console.log(`Auto-synced ${events.length} events from Google Calendar`);
                
                // Refresh calendar display
                setTimeout(() => location.reload(), 1000);
            } else {
                updateSyncStatus('✅ Auto-Sync Active', 'active');
                console.log('No new events to sync');
            }
        } catch (err) {
            console.error('Auto-sync error:', err);
            updateSyncStatus('⚠️ Sync Error - Retrying', 'error');
            
            // Retry after 5 minutes on error
            setTimeout(() => {
                if (gapi.client.getToken()) {
                    syncGoogleCalendarEvents();
                }
            }, 5 * 60 * 1000);
        }
    }

    function updateSyncStatus(text, status) {
        const statusElement = document.getElementById('syncStatus');
        if (statusElement) {
            statusElement.textContent = text;
            statusElement.className = `sync-status ${status}`;
        }
    }

    // Save Google Calendar events to database
    async function saveGoogleEventsToDatabase(events) {
        const existingEvents = new Set();
        
        // Get existing Google event IDs to avoid duplicates
        try {
            const response = await fetch('api/events.php?google_events_only=1');
            const data = await response.json();
            if (data.success && data.data) {
                data.data.forEach(event => {
                    if (event.google_event_id) {
                        existingEvents.add(event.google_event_id);
                    }
                });
            }
        } catch (error) {
            console.log('Could not fetch existing events:', error);
        }
        
        let newEventsCount = 0;
        
        for (const event of events) {
            // Skip if event already exists
            if (existingEvents.has(event.id)) {
                continue;
            }
            
            const eventData = {
                title: event.summary || 'No title',
                description: event.description || '',
                start_datetime: event.start.dateTime || event.start.date + 'T09:00:00',
                end_datetime: event.end.dateTime || event.end.date + 'T10:00:00',
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
                
                if (response.ok) {
                    newEventsCount++;
                }
            } catch (error) {
                console.error('Error saving event to database:', error);
            }
        }
        
        if (newEventsCount > 0) {
            console.log(`Saved ${newEventsCount} new events from Google Calendar`);
        }
    }

    // Manual sync function
    async function manualSyncGoogleCalendar() {
        if (!gapi.client.getToken()) {
            showNotification('Please wait for auto-sync to initialize...', 'info');
            return;
        }
        
        showNotification('Manually syncing Google Calendar...', 'info');
        await syncGoogleCalendarEvents();
        showNotification('Manual sync completed!', 'success');
    }

    // Sync class schedules to Google Calendar
    async function syncClassSchedulesToGoogle() {
        if (!gapi.client.getToken()) {
            showNotification('Google Calendar not connected', 'error');
            return;
        }
        
        try {
            const response = await fetch('api/sync_schedules.php');
            const data = await response.json();
            
            if (data.success) {
                showNotification('Class schedules synced to Google Calendar!', 'success');
                // Refresh after sync
                setTimeout(() => location.reload(), 1000);
            } else {
                showNotification('Error syncing schedules: ' + data.message, 'error');
            }
        } catch (error) {
            console.error('Error syncing schedules:', error);
            showNotification('Error syncing class schedules', 'error');
        }
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
            
            if (AUTO_SYNC_ENABLED) {
                openEventModal();
            } else {
                showNotification('Setup Google Calendar to add events', 'info');
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
        showNotification('Event details functionality coming soon!', 'info');
    }

    // Initialize event listeners
    document.addEventListener('DOMContentLoaded', function() {
        // Modal controls
        const eventModal = document.getElementById('eventModal');
        const viewEventModal = document.getElementById('viewEventModal');
        const closeModals = document.querySelectorAll('.close');
        
        // Button event listeners
        const manualSyncBtn = document.getElementById('manualSyncButton');
        if (manualSyncBtn) {
            manualSyncBtn.addEventListener('click', manualSyncGoogleCalendar);
        }
        
        const addEventBtn = document.getElementById('addEventButton');
        if (addEventBtn) {
            addEventBtn.addEventListener('click', openEventModal);
        }
        
        document.getElementById('syncSchedulesButton').addEventListener('click', syncClassSchedulesToGoogle);
        
        const exportBtn = document.getElementById('exportButton');
        if (exportBtn) {
            exportBtn.addEventListener('click', () => {
                window.open('api/events.php?export=ical&year=<?php echo $current_year; ?>&month=<?php echo $current_month; ?>', '_blank');
            });
        }
        
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
                title: formData.get('title') || document.getElementById('eventTitle').value,
                description: formData.get('description') || document.getElementById('eventDescription').value,
                start_datetime: formData.get('event_date') + 'T' + formData.get('start_time') || 
                               document.getElementById('eventDate').value + 'T' + document.getElementById('eventTime').value,
                end_datetime: formData.get('event_date') + 'T' + (formData.get('end_time') || formData.get('start_time')) ||
                             document.getElementById('eventDate').value + 'T' + document.getElementById('eventTime').value,
                event_type: formData.get('event_type') || document.getElementById('eventType').value,
                course_id: formData.get('course_id') || document.getElementById('eventCourse').value || null
            };
            
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
                    showNotification('Failed to add event', 'error');
                }
            } catch (error) {
                showNotification('Error adding event', 'error');
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

    // Page visibility API to sync when user returns to tab
    document.addEventListener('visibilitychange', function() {
        if (!document.hidden && AUTO_SYNC_ENABLED && gapi_inited && gapi.client.getToken()) {
            // Sync when user returns to the tab
            setTimeout(() => {
                syncGoogleCalendarEvents();
            }, 1000);
        }
    });

    // Cleanup on page unload
    window.addEventListener('beforeunload', function() {
        if (autoSyncTimer) {
            clearInterval(autoSyncTimer);
        }
    });
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
      background: linear-gradient(45deg, #b19176, #8B7355);
      color: white;
      border: none;
      border-radius: 25px;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
    }
    
    .control-btn:hover {
      background: linear-gradient(45deg, #8B7355, #6d5d48);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(139, 115, 85, 0.4);
    }
    
    .notification {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
    
    /* Sync status styles */
    .sync-status {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      margin-right: 15px;
      animation: pulse 2s infinite;
    }
    
    .sync-status.active {
      background: rgba(40, 167, 69, 0.1);
      color: #28a745;
      border: 1px solid rgba(40, 167, 69, 0.2);
      animation: none;
    }
    
    .sync-status.syncing {
      background: rgba(0, 123, 255, 0.1);
      color: #007bff;
      border: 1px solid rgba(0, 123, 255, 0.2);
      animation: pulse 1s infinite;
    }
    
    .sync-status.error {
      background: rgba(220, 53, 69, 0.1);
      color: #dc3545;
      border: 1px solid rgba(220, 53, 69, 0.2);
      animation: shake 0.5s ease-in-out;
    }
    
    .sync-status.offline {
      background: rgba(255, 193, 7, 0.1);
      color: #ffc107;
      border: 1px solid rgba(255, 193, 7, 0.2);
      animation: none;
    }
    
    @keyframes pulse {
      0% { opacity: 1; }
      50% { opacity: 0.7; }
      100% { opacity: 1; }
    }
    
    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-5px); }
      75% { transform: translateX(5px); }
    }
    
    /* Ensure proper event spacing */
    .event-container .event {
      margin-bottom: 2px;
    }
    
    .event-container .event:last-child {
      margin-bottom: 0;
    }

    /* Enhanced calendar controls */
    .calendar-controls {
      display: flex;
      justify-content: center;
      gap: 15px;
      margin-top: 30px;
      flex-wrap: wrap;
    }

    /* Responsive adjustments */
    @media (max-width: 768px) {
      .calendar-controls {
        flex-direction: column;
        align-items: center;
      }
      
      .control-btn {
        width: 200px;
        margin: 5px 0;
      }
      
      .sync-status {
        font-size: 11px;
        padding: 4px 8px;
        margin-right: 10px;
      }
    }
  </style>
</body>
</html>