<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Ensure user is logged in
requireLogin();

// Get current user data
$user_id = getCurrentUserId();
$user_name = getCurrentUserName() ?: 'User';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate required fields
        if (empty($_POST['name'])) {
            throw new Exception("Course name is required");
        }

        if (empty($_POST['code'])) {
            throw new Exception("Course code is required");
        }

        // Check if course code already exists for this user
        $database = new Database();
        $existing_course = $database->queryRow(
            "SELECT id FROM courses WHERE user_id = :user_id AND code = :code",
            [':user_id' => $user_id, ':code' => cleanInput($_POST['code'])]
        );

        if ($existing_course) {
            throw new Exception("A course with this code already exists");
        }

        // Validate color format
        $color = cleanInput($_POST['color'] ?? '#8B7355');
        if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $color)) {
            $color = '#8B7355'; // Default color
        }

        // Prepare course data
        $course_data = [
            'user_id' => $user_id,
            'name' => cleanInput($_POST['name']),
            'code' => cleanInput($_POST['code']),
            'description' => cleanInput($_POST['description'] ?? ''),
            'color' => $color
        ];

        // Create the course
        $course_id = createCourse($user_id, $course_data);
        
        if ($course_id) {
            // Set success message
            setMessage('Course created successfully!', 'success');
            
            // Redirect to task page or course management
            $redirect_to = $_POST['redirect_to'] ?? 'task.php';
            header("Location: " . $redirect_to);
            exit();
        } else {
            throw new Exception("Failed to create course in database");
        }

    } catch (Exception $e) {
        error_log("Create course error: " . $e->getMessage());
        $error_message = $e->getMessage();
    }
}

// Get message from session if redirected
$message = getMessage();

// Predefined color options
$color_options = [
    '#8B7355' => 'Brown (Default)',
    '#6c757d' => 'Gray',
    '#fd7e14' => 'Orange',
    '#28a745' => 'Green',
    '#dc3545' => 'Red',
    '#007bff' => 'Blue',
    '#6f42c1' => 'Purple',
    '#e83e8c' => 'Pink',
    '#20c997' => 'Teal',
    '#ffc107' => 'Yellow'
];

// Get the redirect destination
$redirect_to = $_GET['redirect_to'] ?? 'task.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Create New Course</title>
  <link rel="stylesheet" href="style.css">
  <style>
    .create-course-main {
      flex: 1;
      background: #f8f9fa;
      overflow-y: auto;
      padding: 40px;
    }

    .create-course-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 40px;
    }

    .create-course-header h1 {
      font-size: 48px;
      font-weight: 400;
      color: #333;
      margin: 0;
    }

    .user-name {
      font-size: 16px;
      color: #666;
      font-weight: 400;
    }

    .back-link {
      color: #8B7355;
      text-decoration: none;
      font-weight: 500;
      margin-bottom: 20px;
      display: inline-block;
    }

    .back-link:hover {
      text-decoration: underline;
    }

    /* Form Styles */
    .course-form {
      max-width: 600px;
      background: white;
      padding: 40px;
      border-radius: 20px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
    }

    .form-group {
      margin-bottom: 30px;
    }

    .form-group label {
      display: block;
      font-size: 16px;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e1e5e9;
      border-radius: 8px;
      font-size: 16px;
      background: #f8f9fa;
      transition: all 0.3s ease;
      box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      outline: none;
      border-color: #8B7355;
      background: white;
      box-shadow: 0 0 0 3px rgba(139, 115, 85, 0.1);
    }

    .form-group textarea {
      min-height: 100px;
      resize: vertical;
    }

    /* Color Selection */
    .color-selection {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 15px;
      margin-top: 10px;
    }

    .color-option {
      position: relative;
    }

    .color-option input[type="radio"] {
      display: none;
    }

    .color-option label {
      display: block;
      width: 60px;
      height: 60px;
      border-radius: 12px;
      cursor: pointer;
      border: 3px solid transparent;
      transition: all 0.3s ease;
      position: relative;
    }

    .color-option input[type="radio"]:checked + label {
      border-color: #333;
      transform: scale(1.1);
    }

    .color-option label::after {
      content: '';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      width: 24px;
      height: 24px;
      border-radius: 50%;
      background: white;
      opacity: 0;
      transition: opacity 0.3s ease;
    }

    .color-option input[type="radio"]:checked + label::after {
      opacity: 1;
    }

    .color-option label::before {
      content: '✓';
      position: absolute;
      top: 50%;
      left: 50%;
      transform: translate(-50%, -50%);
      color: #333;
      font-weight: bold;
      font-size: 16px;
      opacity: 0;
      transition: opacity 0.3s ease;
      z-index: 1;
    }

    .color-option input[type="radio"]:checked + label::before {
      opacity: 1;
    }

    /* Course preview */
    .course-preview {
      margin-top: 20px;
      padding: 20px;
      border-radius: 12px;
      transition: all 0.3s ease;
      background: #8B7355;
      color: white;
    }

    .preview-title {
      font-size: 18px;
      font-weight: 600;
      margin-bottom: 5px;
    }

    .preview-code {
      font-size: 14px;
      opacity: 0.9;
      margin-bottom: 10px;
    }

    .preview-description {
      font-size: 14px;
      opacity: 0.8;
    }

    /* Submit Button */
    .submit-section {
      margin-top: 40px;
      display: flex;
      gap: 15px;
    }

    .create-course-btn {
      flex: 1;
      padding: 15px 30px;
      background: linear-gradient(45deg, #8B7355, #6d5d48);
      color: white;
      border: none;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
    }

    .create-course-btn:hover {
      background: linear-gradient(45deg, #6d5d48, #5a4d3c);
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(139, 115, 85, 0.4);
    }

    .cancel-btn {
      padding: 15px 30px;
      background: #f8f9fa;
      color: #666;
      border: 2px solid #e1e5e9;
      border-radius: 8px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-decoration: none;
      display: inline-block;
      text-align: center;
    }

    .cancel-btn:hover {
      background: #e9ecef;
      border-color: #adb5bd;
    }

    /* Message Styles */
    .message {
      padding: 15px 20px;
      margin-bottom: 20px;
      border-radius: 8px;
      font-weight: 500;
    }

    .message.success {
      background-color: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .message.error {
      background-color: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    /* Required field indicator */
    .required {
      color: #dc3545;
    }

    /* Help text */
    .help-text {
      font-size: 14px;
      color: #6c757d;
      margin-top: 5px;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .create-course-main {
        padding: 20px;
      }
      
      .create-course-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 10px;
      }
      
      .create-course-header h1 {
        font-size: 36px;
      }
      
      .course-form {
        padding: 30px 20px;
      }
      
      .color-selection {
        grid-template-columns: repeat(3, 1fr);
        gap: 10px;
      }
      
      .color-option label {
        width: 50px;
        height: 50px;
      }
      
      .submit-section {
        flex-direction: column;
      }
    }
  </style>
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

    <!-- Main Create Course Content -->
    <main class="create-course-main">
      <a href="<?php echo htmlspecialchars($redirect_to); ?>" class="back-link">← Back to Tasks</a>
      
      <div class="create-course-header">
        <h1>Create New Course</h1>
        <div class="user-name"><?php echo htmlspecialchars($user_name); ?> ></div>
      </div>
      
      <?php if (isset($message)): ?>
      <div class="message <?php echo htmlspecialchars($message['type']); ?>">
        <?php echo htmlspecialchars($message['text']); ?>
      </div>
      <?php endif; ?>
      
      <?php if (isset($error_message)): ?>
      <div class="message error">
        <?php echo htmlspecialchars($error_message); ?>
      </div>
      <?php endif; ?>
      
      <form class="course-form" method="POST">
        <input type="hidden" name="redirect_to" value="<?php echo htmlspecialchars($redirect_to); ?>">
        
        <div class="form-group">
          <label for="name">Course Name <span class="required">*</span></label>
          <input type="text" id="name" name="name" required placeholder="e.g., Final Year Project" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
          <div class="help-text">Enter the full name of your course</div>
        </div>
        
        <div class="form-group">
          <label for="code">Course Code <span class="required">*</span></label>
          <input type="text" id="code" name="code" required placeholder="e.g., FYP, TP2543, CS101" value="<?php echo htmlspecialchars($_POST['code'] ?? ''); ?>">
          <div class="help-text">Short code to identify your course</div>
        </div>
        
        <div class="form-group">
          <label for="description">Description</label>
          <textarea id="description" name="description" placeholder="Describe what this course is about..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
          <div class="help-text">Optional description of the course content</div>
        </div>
        
        <div class="form-group">
          <label>Course Color</label>
          <div class="help-text" style="margin-bottom: 10px;">Choose a color to represent this course</div>
          <div class="color-selection">
            <?php foreach ($color_options as $color_value => $color_name): ?>
            <div class="color-option">
              <input type="radio" id="color_<?php echo substr($color_value, 1); ?>" name="color" value="<?php echo $color_value; ?>" 
                     <?php echo (($_POST['color'] ?? '#8B7355') === $color_value) ? 'checked' : ''; ?>>
              <label for="color_<?php echo substr($color_value, 1); ?>" style="background-color: <?php echo $color_value; ?>;" title="<?php echo $color_name; ?>"></label>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        
        <!-- Course Preview -->
        <div class="form-group">
          <label>Preview</label>
          <div class="course-preview" id="coursePreview">
            <div class="preview-title" id="previewTitle">Course Name</div>
            <div class="preview-code" id="previewCode">COURSE_CODE</div>
            <div class="preview-description" id="previewDescription">Course description will appear here</div>
          </div>
        </div>
        
        <div class="submit-section">
          <button type="submit" class="create-course-btn">Create Course</button>
          <a href="<?php echo htmlspecialchars($redirect_to); ?>" class="cancel-btn">Cancel</a>
        </div>
      </form>
    </main>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      // Get form elements
      const nameInput = document.getElementById('name');
      const codeInput = document.getElementById('code');
      const descriptionInput = document.getElementById('description');
      const colorInputs = document.querySelectorAll('input[name="color"]');
      
      // Get preview elements
      const previewTitle = document.getElementById('previewTitle');
      const previewCode = document.getElementById('previewCode');
      const previewDescription = document.getElementById('previewDescription');
      const coursePreview = document.getElementById('coursePreview');
      
      // Update preview function
      function updatePreview() {
        const name = nameInput.value.trim() || 'Course Name';
        const code = codeInput.value.trim() || 'COURSE_CODE';
        const description = descriptionInput.value.trim() || 'Course description will appear here';
        
        previewTitle.textContent = name;
        previewCode.textContent = code;
        previewDescription.textContent = description;
        
        // Update preview color
        const selectedColor = document.querySelector('input[name="color"]:checked');
        if (selectedColor) {
          coursePreview.style.backgroundColor = selectedColor.value;
        }
      }
      
      // Add event listeners for real-time preview
      nameInput.addEventListener('input', updatePreview);
      codeInput.addEventListener('input', updatePreview);
      descriptionInput.addEventListener('input', updatePreview);
      
      colorInputs.forEach(colorInput => {
        colorInput.addEventListener('change', updatePreview);
      });
      
      // Auto-generate course code from name
      nameInput.addEventListener('input', function() {
        const name = this.value.trim();
        if (name && !codeInput.value) {
          // Generate code from first letters of words
          const words = name.split(' ');
          let code = '';
          
          words.forEach(word => {
            if (word.length > 0) {
              code += word.charAt(0).toUpperCase();
            }
          });
          
          // Limit to 6 characters
          code = code.substring(0, 6);
          codeInput.value = code;
          updatePreview();
        }
      });
      
      // Form validation
      const form = document.querySelector('.course-form');
      form.addEventListener('submit', function(e) {
        const name = nameInput.value.trim();
        const code = codeInput.value.trim();
        
        if (!name) {
          e.preventDefault();
          alert('Please enter a course name');
          nameInput.focus();
          return;
        }
        
        if (!code) {
          e.preventDefault();
          alert('Please enter a course code');
          codeInput.focus();
          return;
        }
        
        // Validate course code format (letters and numbers only)
        if (!/^[A-Za-z0-9]+$/.test(code)) {
          e.preventDefault();
          alert('Course code should contain only letters and numbers');
          codeInput.focus();
          return;
        }
      });
      
      // Initial preview update
      updatePreview();
      
      // Auto-focus name field
      nameInput.focus();
      
      // Course code formatting
      codeInput.addEventListener('input', function() {
        // Convert to uppercase and remove spaces
        this.value = this.value.toUpperCase().replace(/\s/g, '');
      });
      
      // Keyboard shortcuts
      document.addEventListener('keydown', function(e) {
        // Ctrl/Cmd + Enter to submit form
        if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
          form.submit();
        }
        
        // Escape to cancel
        if (e.key === 'Escape') {
          if (confirm('Are you sure you want to cancel? Any unsaved changes will be lost.')) {
            window.location.href = '<?php echo htmlspecialchars($redirect_to); ?>';
          }
        }
      });
    });
  </script>
</body>
</html>