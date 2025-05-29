<?php
require_once 'config/database.php';

requireLogin();

$database = new Database();
$db = $database->getConnection();
$user_id = getCurrentUserId();
$user_name = $_SESSION['user_name'];

// Get task statistics
$task_stats_query = "SELECT 
    COUNT(*) as total_tasks,
    SUM(CASE WHEN status = 'todo' THEN 1 ELSE 0 END) as todo_tasks,
    SUM(CASE WHEN status = 'progress' THEN 1 ELSE 0 END) as progress_tasks,
    SUM(CASE WHEN status = 'done' THEN 1 ELSE 0 END) as done_tasks
    FROM tasks WHERE user_id = :user_id";
$stmt = $db->prepare($task_stats_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$task_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get courses
$courses_query = "SELECT * FROM courses WHERE user_id = :user_id ORDER BY name";
$stmt = $db->prepare($courses_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
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
          <a href="dashboard.php">Dashboard</a>
        </li>
        <li class="nav-item">
          <a href="calendar.php">Calendar</a>
        </li>
        <li class="nav-item">
          <a href="class_schedule.php">Class Schedules</a>
        </li>
        <li class="nav-item active">
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

    <!-- Main Task Content -->
    <main class="task-main">
      <div class="task-header">
        <div class="task-title-section">
          <h1>My Tasks</h1>
          <div class="task-icon">📋</div>
        </div>
        <div class="header-actions">
          <span class="user-name"><?php echo htmlspecialchars($user_name); ?> ></span>
          <button class="add-course-btn" id="addCourseBtn">+ Add Course</button>
          <button class="add-task-btn" id="addTaskBtn">+ Add Task</button>
        </div>
      </div>
      
      <!-- Task Status Cards -->
      <div class="task-status-grid">
        <div class="status-card todo-card">
          <div class="status-icon">⏰</div>
          <div class="status-content">
            <h3>To Do</h3>
            <p id="todoCount"><?php echo $task_stats['todo_tasks']; ?> task<?php echo $task_stats['todo_tasks'] != 1 ? 's' : ''; ?> now</p>
          </div>
        </div>
        
        <div class="status-card progress-card">
          <div class="status-icon">⚡</div>
          <div class="status-content">
            <h3>In Progress</h3>
            <p id="progressCount"><?php echo $task_stats['progress_tasks']; ?> task<?php echo $task_stats['progress_tasks'] != 1 ? 's' : ''; ?> now</p>
          </div>
        </div>
        
        <div class="status-card done-card">
          <div class="status-icon">✅</div>
          <div class="status-content">
            <h3>Done</h3>
            <p id="doneCount"><?php echo $task_stats['done_tasks']; ?> task<?php echo $task_stats['done_tasks'] != 1 ? 's' : ''; ?> now</p>
          </div>
        </div>
      </div>
      
      <!-- Course Cards -->
      <div class="course-grid">
        <?php foreach ($courses as $course): ?>
        <div class="course-card <?php echo strtolower($course['name']); ?>-card">
          <h3><?php echo htmlspecialchars($course['name']); ?></h3>
          <p class="task-count" id="taskCount<?php echo $course['id']; ?>">Loading...</p>
          <button class="add-task-course-btn" data-course="<?php echo htmlspecialchars($course['name']); ?>">+ Add Task</button>
        </div>
        <?php endforeach; ?>
      </div>
      
      <!-- Task List Section -->
      <div class="task-lists">
        <div class="task-column">
          <h4>To Do Tasks</h4>
          <div class="task-list" id="todoTasks">
            <!-- Tasks will be loaded here via JavaScript -->
          </div>
        </div>
        
        <div class="task-column">
          <h4>In Progress Tasks</h4>
          <div class="task-list" id="progressTasks">
            <!-- Tasks will be loaded here via JavaScript -->
          </div>
        </div>
        
        <div class="task-column">
          <h4>Completed Tasks</h4>
          <div class="task-list" id="completedTasks">
            <!-- Tasks will be loaded here via JavaScript -->
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
        <input type="text" id="taskTitle" name="title" placeholder="Task Title" required>
        <textarea id="taskDescription" name="description" placeholder="Task Description"></textarea>
        <select id="taskCourse" name="course" required>
          <option value="">Select Course</option>
          <?php foreach ($courses as $course): ?>
          <option value="<?php echo htmlspecialchars($course['name']); ?>"><?php echo htmlspecialchars($course['name']); ?></option>
          <?php endforeach; ?>
        </select>
        <select id="taskPriority" name="priority" required>
          <option value="">Select Priority</option>
          <option value="low">Low</option>
          <option value="medium">Medium</option>
          <option value="high">High</option>
        </select>
        <input type="date" id="taskDueDate" name="due_date" required>
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
        <input type="text" id="courseName" name="name" placeholder="Course Name" required>
        <input type="text" id="courseCode" name="code" placeholder="Course Code" required>
        <textarea id="courseDescription" name="description" placeholder="Course Description"></textarea>
        <select id="courseColor" name="color" required>
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
    let tasks = [];
    let courses = <?php echo json_encode($courses); ?>;

    document.addEventListener('DOMContentLoaded', function() {
      initializeEventListeners();
      loadTasks();
      updateCourseTaskCounts();
    });

    function initializeEventListeners() {
      // Modal controls
      const taskModal = document.getElementById('addTaskModal');
      const courseModal = document.getElementById('addCourseModal');
      const addTaskBtn = document.getElementById('addTaskBtn');
      const addCourseBtn = document.getElementById('addCourseBtn');
      const closeModals = document.querySelectorAll('.close');
      const cancelBtns = document.querySelectorAll('#cancelTask, #cancelCourse');

      // Add task button
      addTaskBtn.addEventListener('click', () => {
        taskModal.style.display = 'block';
      });

      // Add course button
      addCourseBtn.addEventListener('click', () => {
        courseModal.style.display = 'block';
      });

      // Course-specific add task buttons
      document.querySelectorAll('.add-task-course-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
          const courseName = e.target.getAttribute('data-course');
          document.getElementById('taskCourse').value = courseName;
          taskModal.style.display = 'block';
        });
      });

      // Close modals
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

      // Close modal on outside click
      window.addEventListener('click', (event) => {
        if (event.target === taskModal) {
          taskModal.style.display = 'none';
        }
        if (event.target === courseModal) {
          courseModal.style.display = 'none';
        }
      });

      // Form submissions
      document.getElementById('addTaskForm').addEventListener('submit', handleAddTask);
      document.getElementById('addCourseForm').addEventListener('submit', handleAddCourse);
    }

    function loadTasks() {
      fetch('api/tasks.php')
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            tasks = data.data;
            renderTasks();
            updateTaskCounts();
          } else {
            showNotification('Failed to load tasks: ' + data.message, 'error');
          }
        })
        .catch(error => {
          console.error('Error loading tasks:', error);
          showNotification('Error loading tasks', 'error');
        });
    }

    function renderTasks() {
      const todoList = document.getElementById('todoTasks');
      const progressList = document.getElementById('progressTasks');
      const completedList = document.getElementById('completedTasks');

      todoList.innerHTML = '';
      progressList.innerHTML = '';
      completedList.innerHTML = '';

      if (tasks.length === 0) {
        todoList.innerHTML = '<div class="no-tasks">No tasks yet. Click "Add Task" to get started!</div>';
        return;
      }

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
      taskDiv.setAttribute('data-priority', task.priority);
      
      taskDiv.innerHTML = `
        <input type="checkbox" class="task-checkbox" ${task.status === 'done' ? 'checked' : ''} 
               onchange="updateTaskStatus(${task.id}, this.checked)">
        <div class="task-details">
          <h5>${escapeHtml(task.title)}</h5>
          <p>${task.course_name || 'No Course'} - Due: ${formatDate(task.due_date)}</p>
        </div>
        <div class="task-actions">
          ${task.status !== 'done' ? `<button class="edit-task" onclick="editTask(${task.id})">✏️</button>` : ''}
          <button class="delete-task" onclick="deleteTask(${task.id})">🗑️</button>
        </div>
      `;
      
      return taskDiv;
    }

    function handleAddTask(e) {
      e.preventDefault();
      
      const formData = new FormData(e.target);
      
      fetch('api/tasks.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Task added successfully!', 'success');
          document.getElementById('addTaskModal').style.display = 'none';
          e.target.reset();
          loadTasks();
        } else {
          showNotification('Failed to add task: ' + data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error adding task:', error);
        showNotification('Error adding task', 'error');
      });
    }

    function handleAddCourse(e) {
      e.preventDefault();
      
      const formData = new FormData(e.target);
      
      fetch('api/courses.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Course added successfully!', 'success');
          document.getElementById('addCourseModal').style.display = 'none';
          e.target.reset();
          location.reload(); // Reload to show new course
        } else {
          showNotification('Failed to add course: ' + data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error adding course:', error);
        showNotification('Error adding course', 'error');
      });
    }

    function updateTaskStatus(taskId, isCompleted) {
      const status = isCompleted ? 'done' : 'todo';
      
      fetch('api/tasks.php', {
        method: 'PUT',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `task_id=${taskId}&status=${status}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          loadTasks(); // Reload tasks to update display
          showNotification('Task updated successfully!', 'success');
        } else {
          showNotification('Failed to update task: ' + data.message, 'error');
          // Revert checkbox state
          const checkbox = document.querySelector(`input[onchange="updateTaskStatus(${taskId}, this.checked)"]`);
          if (checkbox) checkbox.checked = !isCompleted;
        }
      })
      .catch(error => {
        console.error('Error updating task:', error);
        showNotification('Error updating task', 'error');
      });
    }

    function deleteTask(taskId) {
      if (!confirm('Are you sure you want to delete this task?')) {
        return;
      }
      
      fetch('api/tasks.php', {
        method: 'DELETE',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `task_id=${taskId}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Task deleted successfully!', 'success');
          loadTasks();
        } else {
          showNotification('Failed to delete task: ' + data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error deleting task:', error);
        showNotification('Error deleting task', 'error');
      });
    }

    function editTask(taskId) {
      const task = tasks.find(t => t.id == taskId);
      if (!task) return;
      
      // Pre-fill the form with task data
      document.getElementById('taskTitle').value = task.title;
      document.getElementById('taskDescription').value = task.description || '';
      document.getElementById('taskCourse').value = task.course_name || '';
      document.getElementById('taskPriority').value = task.priority;
      document.getElementById('taskDueDate').value = task.due_date;
      
      document.getElementById('addTaskModal').style.display = 'block';
      
      // Modify form to update instead of create
      const form = document.getElementById('addTaskForm');
      form.onsubmit = function(e) {
        e.preventDefault();
        updateTask(taskId, new FormData(form));
      };
    }

    function updateTask(taskId, formData) {
      formData.append('task_id', taskId);
      
      fetch('api/tasks.php', {
        method: 'PUT',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showNotification('Task updated successfully!', 'success');
          document.getElementById('addTaskModal').style.display = 'none';
          loadTasks();
          
          // Reset form handler
          document.getElementById('addTaskForm').onsubmit = handleAddTask;
        } else {
          showNotification('Failed to update task: ' + data.message, 'error');
        }
      })
      .catch(error => {
        console.error('Error updating task:', error);
        showNotification('Error updating task', 'error');
      });
    }

    function updateTaskCounts() {
      const todoCount = tasks.filter(task => task.status === 'todo').length;
      const progressCount = tasks.filter(task => task.status === 'progress').length;
      const doneCount = tasks.filter(task => task.status === 'done').length;

      document.getElementById('todoCount').textContent = `${todoCount} task${todoCount !== 1 ? 's' : ''} now`;
      document.getElementById('progressCount').textContent = `${progressCount} task${progressCount !== 1 ? 's' : ''} now`;
      document.getElementById('doneCount').textContent = `${doneCount} task${doneCount !== 1 ? 's' : ''} now`;
    }

    function updateCourseTaskCounts() {
      courses.forEach(course => {
        const courseTaskCount = tasks.filter(task => task.course_name === course.name).length;
        const taskCountElement = document.getElementById(`taskCount${course.id}`);
        if (taskCountElement) {
          taskCountElement.textContent = courseTaskCount > 0 ? `${courseTaskCount} task${courseTaskCount !== 1 ? 's' : ''}` : 'No tasks';
        }
      });
    }

    function formatDate(dateString) {
      if (!dateString) return 'No due date';
      
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

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
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
        border-radius: 5px;
        color: white;
        font-weight: 500;
        z-index: 1000;
        opacity: 0;
        transition: opacity 0.3s ease;
      `;
      
      if (type === 'success') {
        notification.style.backgroundColor = '#28a745';
      } else if (type === 'error') {
        notification.style.backgroundColor = '#dc3545';
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
          document.body.removeChild(notification);
        }, 300);
      }, 3000);
    }

    // Refresh tasks every 30 seconds
    setInterval(loadTasks, 30000);
  </script>

  <style>
    .no-tasks {
      text-align: center;
      color: #999;
      font-style: italic;
      padding: 40px 20px;
      background: #f8f9fa;
      border-radius: 12px;
      border: 2px dashed #ddd;
    }
    
    .notification {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }
  </style>
</body>
</html>