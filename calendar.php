<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Calendar</title>
  <link rel="stylesheet" href="style.css">
  <script src="https://apis.google.com/js/api.js"></script>
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
        <li class="nav-item active">
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
          <a href="#group">Group Members</a>
        </li>
      </ul>
    </nav>

    <!-- Main Calendar Content -->
    <main class="calendar-main">
      <div class="calendar-header-section">
        <div class="calendar-title-section">
          <h1>JUNE</h1>
          <h1 class="year">2025</h1>
        </div>
        <div class="user-name">NUR KHALIDA BINTI NAZERI ></div>
      </div>
      
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
          <!-- Week 1 -->
          <div class="calendar-cell">
            <div class="date-number">1</div>
            <div class="event-container">
              <div class="event event-green">Harta</div>
            </div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">2</div>
            <div class="event-container">
              <div class="event event-brown">LMCR3362</div>
            </div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">3</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">4</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">5</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">6</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">7</div>
          </div>
          
          <!-- Week 2 -->
          <div class="calendar-cell">
            <div class="date-number">8</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">9</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">10</div>
            <div class="event-container">
              <div class="event event-brown">LMC4311Z</div>
            </div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">11</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">12</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">13</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">14</div>
          </div>
          
          <!-- Week 3 -->
          <div class="calendar-cell">
            <div class="date-number">15</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">16</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">17</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">18</div>
            <div class="event-container">
              <div class="event event-red">W. Prog</div>
            </div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">19</div>
            <div class="event-container">
              <div class="event event-brown">TP2543</div>
            </div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">20</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">21</div>
          </div>
          
          <!-- Week 4 -->
          <div class="calendar-cell">
            <div class="date-number">22</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">23</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">24</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">25</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">26</div>
            <div class="event-container">
              <div class="event event-brown">TP2543</div>
              <div class="event event-yellow">FYP</div>
            </div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">27</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">28</div>
          </div>
          
          <!-- Week 5 -->
          <div class="calendar-cell">
            <div class="date-number">29</div>
          </div>
          <div class="calendar-cell">
            <div class="date-number">30</div>
          </div>
          <div class="calendar-cell empty"></div>
          <div class="calendar-cell empty"></div>
          <div class="calendar-cell empty"></div>
          <div class="calendar-cell empty"></div>
          <div class="calendar-cell empty"></div>
        </div>
      </div>
      
      <!-- Google Calendar Integration Controls -->
      <div class="calendar-controls">
        <button id="authorizeButton" class="control-btn">Connect Google Calendar</button>
        <button id="signoutButton" class="control-btn" style="display: none;">Sign Out</button>
        <button id="loadEventsButton" class="control-btn" style="display: none;">Load Events</button>
        <button id="addEventButton" class="control-btn" style="display: none;">Add Event</button>
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
            <div class="form-buttons">
              <button type="submit">Add Event</button>
              <button type="button" id="cancelEvent">Cancel</button>
            </div>
          </form>
        </div>
      </div>
    </main>
  </div>

  <script>
    // Google Calendar API configuration
    const CLIENT_ID = 'your-google-client-id';
    const API_KEY = 'your-google-api-key';
    const DISCOVERY_DOC = 'https://www.googleapis.com/discovery/v1/apis/calendar/v3/rest';
    const SCOPES = 'https://www.googleapis.com/auth/calendar';

    let tokenClient;
    let gapi_inited = false;
    let gis_inited = false;

    // Initialize Google API
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
        callback: '',
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
      }
    }

    // Load events from Google Calendar
    async function loadGoogleCalendarEvents() {
      let response;
      try {
        const request = {
          'calendarId': 'primary',
          'timeMin': (new Date()).toISOString(),
          'showDeleted': false,
          'singleEvents': true,
          'maxResults': 10,
          'orderBy': 'startTime',
        };
        response = await gapi.client.calendar.events.list(request);
      } catch (err) {
        console.error('Error loading events:', err);
        return;
      }

      const events = response.result.items;
      if (!events || events.length == 0) {
        console.log('No events found.');
        return;
      }

      // Display events in calendar
      displayEventsInCalendar(events);
    }

    function displayEventsInCalendar(events) {
      // Clear existing dynamic events
      const dynamicEvents = document.querySelectorAll('.event.dynamic');
      dynamicEvents.forEach(event => event.remove());

      events.forEach(event => {
        const start = event.start.dateTime || event.start.date;
        const eventDate = new Date(start);
        const day = eventDate.getDate();
        
        // Find the calendar cell for this date
        const cells = document.querySelectorAll('.calendar-cell');
        cells.forEach(cell => {
          const dateNumber = cell.querySelector('.date-number');
          if (dateNumber && parseInt(dateNumber.textContent) === day) {
            let eventContainer = cell.querySelector('.event-container');
            if (!eventContainer) {
              eventContainer = document.createElement('div');
              eventContainer.className = 'event-container';
              cell.appendChild(eventContainer);
            }
            
            const eventElement = document.createElement('div');
            eventElement.className = 'event event-blue dynamic';
            eventElement.textContent = event.summary || 'No title';
            eventContainer.appendChild(eventElement);
          }
        });
      });
    }

    // Add new event to Google Calendar
    async function addEventToCalendar(eventData) {
      const event = {
        'summary': eventData.title,
        'description': eventData.description,
        'start': {
          'dateTime': eventData.start,
          'timeZone': 'Asia/Kuala_Lumpur',
        },
        'end': {
          'dateTime': eventData.end,
          'timeZone': 'Asia/Kuala_Lumpur',
        },
      };

      try {
        const request = gapi.client.calendar.events.insert({
          'calendarId': 'primary',
          'resource': event
        });
        
        const response = await request;
        console.log('Event created: ' + response.result.htmlLink);
        loadGoogleCalendarEvents(); // Refresh calendar
      } catch (err) {
        console.error('Error creating event:', err);
      }
    }

    // Modal and form handling
    document.addEventListener('DOMContentLoaded', function() {
      const modal = document.getElementById('eventModal');
      const addEventBtn = document.getElementById('addEventButton');
      const closeModal = document.querySelector('.close');
      const cancelBtn = document.getElementById('cancelEvent');
      const eventForm = document.getElementById('eventForm');

      // Event listeners
      document.getElementById('authorizeButton').addEventListener('click', handleAuthClick);
      document.getElementById('signoutButton').addEventListener('click', handleSignoutClick);
      document.getElementById('loadEventsButton').addEventListener('click', loadGoogleCalendarEvents);
      
      addEventBtn.addEventListener('click', () => {
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

      eventForm.addEventListener('submit', (e) => {
        e.preventDefault();
        
        const title = document.getElementById('eventTitle').value;
        const date = document.getElementById('eventDate').value;
        const time = document.getElementById('eventTime').value;
        const description = document.getElementById('eventDescription').value;

        const startDateTime = new Date(date + 'T' + time);
        const endDateTime = new Date(startDateTime.getTime() + 60*60*1000); // 1 hour later

        const eventData = {
          title: title,
          description: description,
          start: startDateTime.toISOString(),
          end: endDateTime.toISOString()
        };

        addEventToCalendar(eventData);
        modal.style.display = 'none';
        eventForm.reset();
      });
    });

    // Load Google APIs
    window.onload = function() {
      gapiLoaded();
      gisLoaded();
    };
  </script>
  <script async defer src="https://apis.google.com/js/api.js" onload="gapiLoaded()"></script>
  <script async defer src="https://accounts.google.com/gsi/client" onload="gisLoaded()"></script>
</body>
</html>