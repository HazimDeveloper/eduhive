<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - My Tasks</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="tasks.css">
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
        <li class="nav-item active">
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

    <!-- Main Task Content -->
    <main class="task-main">
      <div class="task-header">
        <div class="task-title-section">
          <h1>My Tasks</h1>
          <div class="task-icon">📋</div>
        </div>
        <div class="header-actions">
          <span class="user-name">NUR KHALIDA BINTI NAZERI ></span>
          <button class="add-course-btn">+ Add Course</button>
          <button class="add-task-btn">+ Add Task</button>
        </div>
      </div>
      
      <!-- Task Status Cards -->
      <div class="task-status-grid">
        <div class="status-card todo-card">
          <div class="status-icon">⏰</div>
          <div class="status-content">
            <h3>To Do</h3>
            <p>1 task now • 1 started</p>
          </div>
        </div>
        
        <div class="status-card progress-card">
          <div class="status-icon">⚡</div>
          <div class="status-content">
            <h3>In Progress</h3>
            <p>5 tasks now • 1 started</p>
          </div>
        </div>
        
        <div class="status-card done-card">
          <div class="status-icon">✅</div>
          <div class="status-content">
            <h3>Done</h3>
            <p>18 tasks now • 18 started</p>
          </div>
        </div>
      </div>
      
      <!-- Course Cards -->
      <div class="course-grid">
        <div class="course-card fyp-card">
          <h3>FYP</h3>
          <p class="task-count">1 task pending</p>
          <button class="add-task-course-btn">+ Add Task</button>
        </div>
        
        <div class="course-card programming-card">
          <h3>PROGRAMMING</h3>
          <p class="task-count">No task</p>
          <button class="add-task-course-btn">+ Add Task</button>
        </div>
        
        <div class="course-card harta-card">
          <h3>HARTA</h3>
          <p class="task-count">No task</p>
          <button class="add-task-course-btn">+ Add Task</button>
        </div>
      </div>
      
      <!-- Task List Section -->
      <div class="task-lists">
        <div class="task-column">
          <h4>To Do Tasks</h4>
          <div class="task-list" id="todoTasks">
            <div class="task-item">
              <input type="checkbox" class="task-checkbox">
              <div class="task-details">
                <h5>Submit Literature Review</h5>
                <p>FYP - Due: Tomorrow</p>
              </div>
              <div class="task-actions">
                <button class="edit-task">✏️</button>
                <button class="delete-task">🗑️</button>
              </div>
            </div>
          </div>
        </div>
        
        <div class="task-column">
          <h4>In Progress Tasks</h4>
          <div class="task-list" id="progressTasks">
            <div class="task-item">
              <input type="checkbox" class="task-checkbox">
              <div class="task-details">
                <h5>Chapter 3 Research</h5>
                <p>FYP - Due: Next week</p>
              </div>
              <div class="task-actions">
                <button class="edit-task">✏️</button>
                <button class="delete-task">🗑️</button>
              </div>
            </div>
            <!-- Add more in-progress tasks here -->
          </div>
        </div>
        
        <div class="task-column">
          <h4>Completed Tasks</h4>
          <div class="task-list" id="completedTasks">
            <div class="task-item completed">
              <input type="checkbox" class="task-checkbox" checked>
              <div class="task-details">
                <h5>Research Proposal</h5>
                <p>FYP - Completed</p>
              </div>
              <div class="task-actions">
                <button class="delete-task">🗑️</button>
              </div>
            </div>
            <!-- More completed tasks would be loaded here -->
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Add Task Modal -->
  <div id="addTaskModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3>Add New Task</h3>
      <form id="addTaskForm">
        <input type="text" id="taskTitle" placeholder="Task Title" required>
        <textarea id="taskDescription" placeholder="Task Description"></textarea>
        <select id="taskCourse" required>
          <option value="">Select Course</option>
          <option value="FYP">FYP</option>
          <option value="PROGRAMMING">PROGRAMMING</option>
          <option value="HARTA">HARTA</option>
        </select>
        <select id="taskPriority" required>
          <option value="">Select Priority</option>
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
        </select>
        <input type="date" id="taskDueDate" required>
        <div class="form-buttons">
          <button type="submit">Add Task</button>
          <button type="button" id="cancelTask">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <!-- Add Course Modal -->
  <div id="addCourseModal" class="modal" style="display: none;">
    <div class="modal-content">
      <span class="close">&times;</span>
      <h3>Add New Course</h3>
      <form id="addCourseForm">
        <input type="text" id="courseName" placeholder="Course Name" required>
        <input type="text" id="courseCode" placeholder="Course Code" required>
        <textarea id="courseDescription" placeholder="Course Description"></textarea>
        <select id="courseColor" required>
          <option value="">Select Color Theme</option>
          <option value="brown">Brown</option>
          <option value="blue">Blue</option>
          <option value="green">Green</option>
          <option value="orange">Orange</option>
          <option value="purple">Purple</option>
        </select>
        <div class="form-buttons">
          <button type="submit">Add Course</button>
          <button type="button" id="cancelCourse">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Modal elements
      const taskModal = document.getElementById('addTaskModal');
      const courseModal = document.getElementById('addCourseModal');
      const addTaskBtns = document.querySelectorAll('.add-task-btn, .add-task-course-btn');
      const addCourseBtn = document.querySelector('.add-course-btn');
      const closeModals = document.querySelectorAll('.close');
      const cancelBtns = document.querySelectorAll('#cancelTask, #cancelCourse');

      // Task and course data
      let tasks = JSON.parse(localStorage.getItem('eduhive-tasks')) || [];
      let courses = JSON.parse(localStorage.getItem('eduhive-courses')) || [
        { name: 'FYP', code: 'FYP', color: 'brown' },
        { name: 'PROGRAMMING', code: 'PROG', color: 'blue' },
        { name: 'HARTA', code: 'HARTA', color: 'orange' }
      ];

      // Event listeners for modals
      addTaskBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          taskModal.style.display = 'block';
        });
      });

      addCourseBtn.addEventListener('click', () => {
        courseModal.style.display = 'block';
      });

      closeModals.forEach(close => {
        close.addEventListener('click', () => {
          taskModal.style.display = 'none';
          courseModal.style.display = 'none';
        });
      });

      cancelBtns.forEach(cancel => {
        cancel.addEventListener('click', () => {
          taskModal.style.display = 'none';
          courseModal.style.display = 'none';
        });
      });

      window.addEventListener('click', (event) => {
        if (event.target === taskModal) {
          taskModal.style.display = 'none';
        }
        if (event.target === courseModal) {
          courseModal.style.display = 'none';
        }
      });

      // Add task form submission
      document.getElementById('addTaskForm').addEventListener('submit', (e) => {
        e.preventDefault();
        
        const newTask = {
          id: Date.now(),
          title: document.getElementById('taskTitle').value,
          description: document.getElementById('taskDescription').value,
          course: document.getElementById('taskCourse').value,
          priority: document.getElementById('taskPriority').value,
          dueDate: document.getElementById('taskDueDate').value,
          status: 'todo',
          createdAt: new Date().toISOString()
        };

        tasks.push(newTask);
        localStorage.setItem('eduhive-tasks', JSON.stringify(tasks));
        
        updateTaskCounts();
        renderTasks();
        
        taskModal.style.display = 'none';
        document.getElementById('addTaskForm').reset();
        
        showNotification('Task added successfully!');
      });

      // Add course form submission
      document.getElementById('addCourseForm').addEventListener('submit', (e) => {
        e.preventDefault();
        
        const newCourse = {
          name: document.getElementById('courseName').value,
          code: document.getElementById('courseCode').value,
          description: document.getElementById('courseDescription').value,
          color: document.getElementById('courseColor').value
        };

        courses.push(newCourse);
        localStorage.setItem('eduhive-courses', JSON.stringify(courses));
        
        updateCourseGrid();
        updateCourseOptions();
        
        courseModal.style.display = 'none';
        document.getElementById('addCourseForm').reset();
        
        showNotification('Course added successfully!');
      });

      // Task management functions
      function updateTaskCounts() {
        const todoCount = tasks.filter(task => task.status === 'todo').length;
        const progressCount = tasks.filter(task => task.status === 'progress').length;
        const doneCount = tasks.filter(task => task.status === 'done').length;

        document.querySelector('.todo-card p').textContent = `${todoCount} task${todoCount !== 1 ? 's' : ''} now • ${todoCount} started`;
        document.querySelector('.progress-card p').textContent = `${progressCount} task${progressCount !== 1 ? 's' : ''} now • ${progressCount} started`;
        document.querySelector('.done-card p').textContent = `${doneCount} task${doneCount !== 1 ? 's' : ''} now • ${doneCount} started`;
      }

      function renderTasks() {
        const todoList = document.getElementById('todoTasks');
        const progressList = document.getElementById('progressTasks');
        const completedList = document.getElementById('completedTasks');

        todoList.innerHTML = '';
        progressList.innerHTML = '';
        completedList.innerHTML = '';

        tasks.forEach(task => {
          const taskElement = createTaskElement(task);
          
          if (task.status === 'todo') {
            todoList.appendChild(taskElement);
          } else if (task.status === 'progress') {
            progressList.appendChild(taskElement);
          } else if (task.status === 'done') {
            completedList.appendChild(taskElement);
          }
        });
      }

      function createTaskElement(task) {
        const taskDiv = document.createElement('div');
        taskDiv.className = `task-item ${task.status === 'done' ? 'completed' : ''}`;
        taskDiv.innerHTML = `
          <input type="checkbox" class="task-checkbox" ${task.status === 'done' ? 'checked' : ''} 
                 onchange="updateTaskStatus(${task.id}, this.checked)">
          <div class="task-details">
            <h5>${task.title}</h5>
            <p>${task.course} - Due: ${formatDate(task.dueDate)}</p>
          </div>
          <div class="task-actions">
            ${task.status !== 'done' ? '<button class="edit-task" onclick="editTask(' + task.id + ')">✏️</button>' : ''}
            <button class="delete-task" onclick="deleteTask(${task.id})">🗑️</button>
          </div>
        `;
        return taskDiv;
      }

      function formatDate(dateString) {
        const date = new Date(dateString);
        const today = new Date();
        const tomorrow = new Date(today);
        tomorrow.setDate(tomorrow.getDate() + 1);

        if (date.toDateString() === today.toDateString()) {
          return 'Today';
        } else if (date.toDateString() === tomorrow.toDateString()) {
          return 'Tomorrow';
        } else {
          return date.toLocaleDateString();
        }
      }

      function updateCourseGrid() {
        // This would update the course grid display
        // Implementation depends on your specific needs
      }

      function updateCourseOptions() {
        const courseSelect = document.getElementById('taskCourse');
        courseSelect.innerHTML = '<option value="">Select Course</option>';
        
        courses.forEach(course => {
          const option = document.createElement('option');
          option.value = course.name;
          option.textContent = course.name;
          courseSelect.appendChild(option);
        });
      }

      function showNotification(message) {
        // Simple notification - you could enhance this
        alert(message);
      }

      // Global functions for task management
      window.updateTaskStatus = function(taskId, isCompleted) {
        const task = tasks.find(t => t.id === taskId);
        if (task) {
          task.status = isCompleted ? 'done' : 'todo';
          localStorage.setItem('eduhive-tasks', JSON.stringify(tasks));
          updateTaskCounts();
          setTimeout(() => renderTasks(), 100); // Small delay for smooth transition
        }
      };

      window.deleteTask = function(taskId) {
        if (confirm('Are you sure you want to delete this task?')) {
          tasks = tasks.filter(t => t.id !== taskId);
          localStorage.setItem('eduhive-tasks', JSON.stringify(tasks));
          updateTaskCounts();
          renderTasks();
          showNotification('Task deleted successfully!');
        }
      };

      window.editTask = function(taskId) {
        // Implement edit functionality
        const task = tasks.find(t => t.id === taskId);
        if (task) {
          // Pre-fill the modal with task data
          document.getElementById('taskTitle').value = task.title;
          document.getElementById('taskDescription').value = task.description;
          document.getElementById('taskCourse').value = task.course;
          document.getElementById('taskPriority').value = task.priority;
          document.getElementById('taskDueDate').value = task.dueDate;
          
          taskModal.style.display = 'block';
          
          // Modify form submission to update instead of create
          // This is a simplified version - you'd want more robust edit handling
        }
      };

      // Initialize the page
      updateTaskCounts();
      renderTasks();
      updateCourseOptions();
    });
  </script>
</body>
</html>