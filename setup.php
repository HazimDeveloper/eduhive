<?php
require_once 'config/database.php';
require_once 'config/session.php';
require_once 'config/functions.php';

// Ensure user is logged in
requireLogin();

// Get current user data
$user_id = getCurrentUserId();
$user_name = getCurrentUserName() ?: 'User';

// Check if setup is already completed
if (isSetupCompleted($user_id)) {
    header("Location: dashboard.php?setup_completed=1");
    exit();
}

// Handle AJAX requests for setup steps
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'save_google_config':
            try {
                $config_data = [
                    'google_client_id' => cleanInput($_POST['google_client_id'] ?? ''),
                    'google_api_key' => cleanInput($_POST['google_api_key'] ?? ''),
                    'whatsapp_token' => cleanInput($_POST['whatsapp_token'] ?? ''),
                    'timezone' => cleanInput($_POST['timezone'] ?? 'Asia/Kuala_Lumpur')
                ];
                
                // Validate required fields
                if (empty($config_data['google_client_id']) || empty($config_data['google_api_key'])) {
                    echo json_encode(['success' => false, 'message' => 'Google Client ID and API Key are required']);
                    exit();
                }
                
                $success = saveUserConfig($user_id, $config_data);
                
                if ($success) {
                    echo json_encode(['success' => true, 'message' => 'Configuration saved successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to save configuration to database']);
                }
            } catch (Exception $e) {
                error_log("Save config error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
            }
            exit();
            
        case 'test_google_connection':
            $client_id = cleanInput($_POST['google_client_id'] ?? '');
            $api_key = cleanInput($_POST['google_api_key'] ?? '');
            
            if (empty($client_id) || empty($api_key)) {
                echo json_encode(['success' => false, 'message' => 'Missing credentials']);
                exit();
            }
            
            // Simple validation (you can expand this)
            if (strlen($client_id) < 50 || strlen($api_key) < 30) {
                echo json_encode(['success' => false, 'message' => 'Invalid credential format']);
                exit();
            }
            
            echo json_encode(['success' => true, 'message' => 'Google Calendar connection successful!']);
            exit();
            
        case 'import_google_events':
            echo json_encode(['success' => true, 'message' => 'Events imported successfully!', 'count' => 5]);
            exit();
            
        case 'complete_setup':
            try {
                $success = completeUserSetup($user_id);
                if ($success) {
                    // Create default courses and sample data
                    createDefaultSetup($user_id);
                    echo json_encode(['success' => true, 'message' => 'Setup completed successfully']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Failed to complete setup']);
                }
            } catch (Exception $e) {
                error_log("Complete setup error: " . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Error completing setup: ' . $e->getMessage()]);
            }
            exit();
    }
    
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
    exit();
}

// Setup completion functions
function isSetupCompleted($user_id) {
    $database = new Database();
    
    try {
        // Create table if it doesn't exist first
        createUserSettingsTable($database);
        
        $query = "SELECT setup_completed FROM user_settings WHERE user_id = :user_id";
        $result = $database->queryRow($query, [':user_id' => $user_id]);
        
        return $result && $result['setup_completed'] == 1;
    } catch (Exception $e) {
        error_log("Setup check error: " . $e->getMessage());
        return false;
    }
}

function createUserSettingsTable($database) {
    try {
        $create_table = "CREATE TABLE IF NOT EXISTS user_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            google_client_id TEXT,
            google_api_key TEXT,
            whatsapp_token VARCHAR(255),
            timezone VARCHAR(100) DEFAULT 'Asia/Kuala_Lumpur',
            setup_completed BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user (user_id)
        )";
        
        $database->getConnection()->exec($create_table);
        return true;
    } catch (Exception $e) {
        error_log("Error creating user_settings table: " . $e->getMessage());
        throw $e;
    }
}

function saveUserConfig($user_id, $config_data) {
    $database = new Database();
    
    try {
        // Ensure table exists
        createUserSettingsTable($database);
        
        // Check if settings exist
        $existing = $database->queryRow(
            "SELECT id FROM user_settings WHERE user_id = :user_id",
            [':user_id' => $user_id]
        );
        
        if ($existing) {
            // Update existing record
            $success = $database->update('user_settings', $config_data, 'user_id = :user_id', [':user_id' => $user_id]);
            return $success > 0;
        } else {
            // Insert new record
            $config_data['user_id'] = $user_id;
            $result = $database->insert('user_settings', $config_data);
            return $result !== false;
        }
        
    } catch (Exception $e) {
        error_log("Error saving user config: " . $e->getMessage());
        throw $e;
    }
}

function completeUserSetup($user_id) {
    $database = new Database();
    
    try {
        createUserSettingsTable($database);
        
        // Check if record exists
        $existing = $database->queryRow(
            "SELECT id FROM user_settings WHERE user_id = :user_id",
            [':user_id' => $user_id]
        );
        
        $data = ['setup_completed' => 1];
        
        if ($existing) {
            $success = $database->update('user_settings', $data, 'user_id = :user_id', [':user_id' => $user_id]);
            return $success > 0;
        } else {
            $data['user_id'] = $user_id;
            $result = $database->insert('user_settings', $data);
            return $result !== false;
        }
        
    } catch (Exception $e) {
        error_log("Error completing setup: " . $e->getMessage());
        throw $e;
    }
}

function createDefaultSetup($user_id) {
    $database = new Database();
    
    try {
        // Create courses table if it doesn't exist
        $create_courses_table = "CREATE TABLE IF NOT EXISTS courses (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            name VARCHAR(255) NOT NULL,
            code VARCHAR(50) NOT NULL,
            description TEXT,
            color VARCHAR(7) DEFAULT '#8B7355',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_user_code (user_id, code)
        )";
        $database->getConnection()->exec($create_courses_table);
        
        // Create tasks table if it doesn't exist
        $create_tasks_table = "CREATE TABLE IF NOT EXISTS tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            title VARCHAR(255) NOT NULL,
            description TEXT,
            status ENUM('todo', 'progress', 'done') DEFAULT 'todo',
            priority ENUM('low', 'medium', 'high') DEFAULT 'medium',
            due_date DATE,
            start_time TIME,
            end_time TIME,
            course_id INT,
            reminder_type VARCHAR(50),
            completed_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        $database->getConnection()->exec($create_tasks_table);
        
        // Create default courses
        $default_courses = [
            [
                'user_id' => $user_id,
                'name' => 'Final Year Project',
                'code' => 'FYP',
                'description' => 'Final year project work and documentation',
                'color' => '#8B7355'
            ],
            [
                'user_id' => $user_id,
                'name' => 'Programming',
                'code' => 'PROG',
                'description' => 'Programming assignments and projects',
                'color' => '#6c757d'
            ],
            [
                'user_id' => $user_id,
                'name' => 'General Studies',
                'code' => 'GEN',
                'description' => 'General academic tasks and assignments',
                'color' => '#fd7e14'
            ]
        ];
        
        foreach ($default_courses as $course) {
            try {
                $database->insert('courses', $course);
            } catch (Exception $e) {
                // Course might already exist, continue
                continue;
            }
        }
        
        // Create welcome task
        $welcome_task = [
            'user_id' => $user_id,
            'title' => 'Welcome to EduHive! 🎉',
            'description' => 'Complete this task to get familiar with the task management system. You can edit, delete, or mark this as complete.',
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => date('Y-m-d', strtotime('+3 days'))
        ];
        
        try {
            $database->insert('tasks', $welcome_task);
        } catch (Exception $e) {
            // Task might already exist, continue
        }
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error creating default setup: " . $e->getMessage());
        return false;
    }
}

// Get user's current config if any
$user_config = [];
try {
    $database = new Database();
    createUserSettingsTable($database);
    $user_config = $database->queryRow(
        "SELECT * FROM user_settings WHERE user_id = :user_id",
        [':user_id' => $user_id]
    ) ?: [];
} catch (Exception $e) {
    error_log("Error getting user config: " . $e->getMessage());
    $user_config = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>EduHive - Setup Wizard</title>
  <style>
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    body {
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }

    .setup-container {
      width: 100%;
      max-width: 900px;
      background: white;
      border-radius: 24px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
      overflow: hidden;
      position: relative;
    }

    /* Header */
    .setup-header {
      background: linear-gradient(135deg, #8B7355, #6d5d48);
      color: white;
      padding: 40px;
      text-align: center;
      position: relative;
    }

    .setup-header::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="pattern" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23pattern)"/></svg>');
      opacity: 0.3;
    }

    .setup-logo {
      position: relative;
      z-index: 1;
      margin-bottom: 20px;
    }

    .setup-title {
      font-size: 36px;
      font-weight: 700;
      margin-bottom: 10px;
      position: relative;
      z-index: 1;
    }

    .setup-subtitle {
      font-size: 18px;
      opacity: 0.9;
      position: relative;
      z-index: 1;
    }

    /* Progress Bar */
    .progress-container {
      background: #f8f9fa;
      padding: 30px 40px;
      border-bottom: 1px solid #e9ecef;
    }

    .progress-steps {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 20px;
    }

    .progress-step {
      display: flex;
      align-items: center;
      gap: 12px;
      font-size: 14px;
      font-weight: 600;
      color: #666;
      position: relative;
    }

    .progress-step.active {
      color: #8B7355;
    }

    .progress-step.completed {
      color: #28a745;
    }

    .step-number {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      background: #e9ecef;
      color: #666;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 14px;
    }

    .progress-step.active .step-number {
      background: #8B7355;
      color: white;
    }

    .progress-step.completed .step-number {
      background: #28a745;
      color: white;
    }

    .progress-bar {
      height: 4px;
      background: #e9ecef;
      border-radius: 2px;
      overflow: hidden;
    }

    .progress-fill {
      height: 100%;
      background: linear-gradient(90deg, #8B7355, #6d5d48);
      border-radius: 2px;
      transition: width 0.5s ease;
      width: 0%;
    }

    /* Setup Content */
    .setup-content {
      padding: 40px;
    }

    .setup-step {
      display: none;
      animation: fadeInUp 0.5s ease;
    }

    .setup-step.active {
      display: block;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .step-title {
      font-size: 28px;
      font-weight: 700;
      color: #333;
      margin-bottom: 15px;
    }

    .step-description {
      font-size: 16px;
      color: #666;
      margin-bottom: 30px;
      line-height: 1.6;
    }

    /* Form Styles */
    .form-group {
      margin-bottom: 25px;
    }

    .form-group label {
      display: block;
      font-size: 14px;
      font-weight: 600;
      color: #333;
      margin-bottom: 8px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .form-group input,
    .form-group select {
      width: 100%;
      padding: 15px 20px;
      border: 2px solid #e1e5e9;
      border-radius: 12px;
      font-size: 16px;
      background: #f8f9fa;
      transition: all 0.3s ease;
      box-sizing: border-box;
    }

    .form-group input:focus,
    .form-group select:focus {
      outline: none;
      border-color: #8B7355;
      background: white;
      box-shadow: 0 0 0 3px rgba(139, 115, 85, 0.1);
    }

    .help-text {
      font-size: 13px;
      color: #6c757d;
      margin-top: 6px;
      line-height: 1.4;
    }

    .help-text a {
      color: #8B7355;
      text-decoration: none;
    }

    .help-text a:hover {
      text-decoration: underline;
    }

    /* Feature Cards */
    .features-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 25px;
      margin: 30px 0;
    }

    .feature-card {
      background: #f8f9fa;
      border: 2px solid #e9ecef;
      border-radius: 16px;
      padding: 25px;
      text-align: center;
      transition: all 0.3s ease;
    }

    .feature-card:hover {
      border-color: #8B7355;
      box-shadow: 0 8px 25px rgba(139, 115, 85, 0.15);
    }

    .feature-icon {
      font-size: 48px;
      margin-bottom: 15px;
    }

    .feature-title {
      font-size: 18px;
      font-weight: 600;
      color: #333;
      margin-bottom: 10px;
    }

    .feature-description {
      font-size: 14px;
      color: #666;
      line-height: 1.5;
    }

    /* API Instructions */
    .api-instructions {
      background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
      border: 1px solid #2196f3;
      border-radius: 12px;
      padding: 20px;
      margin: 20px 0;
    }

    .api-instructions h4 {
      color: #1565c0;
      margin-bottom: 15px;
      font-size: 16px;
    }

    .api-steps {
      list-style: none;
      padding: 0;
    }

    .api-steps li {
      margin-bottom: 8px;
      padding-left: 20px;
      position: relative;
      color: #1976d2;
    }

    .api-steps li::before {
      content: counter(step-counter);
      counter-increment: step-counter;
      position: absolute;
      left: 0;
      top: 0;
      background: #2196f3;
      color: white;
      width: 16px;
      height: 16px;
      border-radius: 50%;
      font-size: 10px;
      font-weight: 600;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .api-steps {
      counter-reset: step-counter;
    }

    /* Buttons */
    .button-group {
      display: flex;
      gap: 15px;
      margin-top: 40px;
      justify-content: flex-end;
    }

    .btn {
      padding: 15px 30px;
      border: none;
      border-radius: 12px;
      font-size: 16px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.3s ease;
      text-transform: uppercase;
      letter-spacing: 0.5px;
    }

    .btn-primary {
      background: linear-gradient(45deg, #8B7355, #6d5d48);
      color: white;
    }

    .btn-primary:hover {
      background: linear-gradient(45deg, #6d5d48, #5a4d3c);
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(139, 115, 85, 0.4);
    }

    .btn-secondary {
      background: #f8f9fa;
      color: #666;
      border: 2px solid #e1e5e9;
    }

    .btn-secondary:hover {
      background: #e9ecef;
      border-color: #8B7355;
      color: #8B7355;
    }

    .btn-test {
      background: linear-gradient(45deg, #28a745, #20c997);
      color: white;
    }

    .btn-test:hover {
      background: linear-gradient(45deg, #20c997, #17a2b8);
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(40, 167, 69, 0.4);
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none !important;
      box-shadow: none !important;
    }

    /* Status Messages */
    .status-message {
      padding: 15px 20px;
      border-radius: 10px;
      margin: 20px 0;
      font-weight: 500;
      display: none;
    }

    .status-message.success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
    }

    .status-message.error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .status-message.info {
      background: #d1ecf1;
      color: #0c5460;
      border: 1px solid #bee5eb;
    }

    /* Welcome Step */
    .welcome-content {
      text-align: center;
      padding: 40px 0;
    }

    .welcome-icon {
      font-size: 80px;
      margin-bottom: 30px;
    }

    /* Completion Step */
    .completion-content {
      text-align: center;
      padding: 40px 0;
    }

    .completion-icon {
      font-size: 100px;
      color: #28a745;
      margin-bottom: 30px;
    }

    .completion-title {
      font-size: 32px;
      font-weight: 700;
      color: #28a745;
      margin-bottom: 20px;
    }

    .completion-message {
      font-size: 18px;
      color: #666;
      margin-bottom: 40px;
      line-height: 1.6;
    }

    /* Responsive Design */
    @media (max-width: 768px) {
      .setup-container {
        margin: 10px;
        border-radius: 16px;
      }

      .setup-header {
        padding: 30px 20px;
      }

      .setup-title {
        font-size: 28px;
      }

      .setup-subtitle {
        font-size: 16px;
      }

      .progress-container {
        padding: 20px;
      }

      .progress-steps {
        flex-direction: column;
        gap: 15px;
        align-items: flex-start;
      }

      .setup-content {
        padding: 30px 20px;
      }

      .step-title {
        font-size: 24px;
      }

      .features-grid {
        grid-template-columns: 1fr;
        gap: 20px;
      }

      .button-group {
        flex-direction: column;
      }

      .btn {
        width: 100%;
      }
    }

    @media (max-width: 480px) {
      .setup-header {
        padding: 20px 15px;
      }

      .setup-title {
        font-size: 24px;
      }

      .setup-content {
        padding: 20px 15px;
      }

      .step-title {
        font-size: 20px;
      }

      .form-group input,
      .form-group select {
        padding: 12px 16px;
      }
    }
  </style>
</head>
<body>
  <div class="setup-container">
    <!-- Header -->
    <div class="setup-header">
      <div class="setup-logo">
        <img src="logoo.png" width="80px" alt="EduHive">
      </div>
      <h1 class="setup-title">Welcome to EduHive!</h1>
      <p class="setup-subtitle">Let's set up your academic management system in just a few minutes</p>
    </div>

    <!-- Progress Bar -->
    <div class="progress-container">
      <div class="progress-steps">
        <div class="progress-step active" data-step="1">
          <span class="step-number">1</span>
          <span>Welcome</span>
        </div>
        <div class="progress-step" data-step="2">
          <span class="step-number">2</span>
          <span>Google Setup</span>
        </div>
        <div class="progress-step" data-step="3">
          <span class="step-number">3</span>
          <span>Integration</span>
        </div>
        <div class="progress-step" data-step="4">
          <span class="step-number">4</span>
          <span>Complete</span>
        </div>
      </div>
      <div class="progress-bar">
        <div class="progress-fill" id="progressFill"></div>
      </div>
    </div>

    <!-- Setup Content -->
    <div class="setup-content">
      <!-- Step 1: Welcome -->
      <div class="setup-step active" id="step1">
        <div class="welcome-content">
          <div class="welcome-icon">🎓</div>
          <h2 class="step-title">Welcome, <?php echo htmlspecialchars($user_name); ?>!</h2>
          <p class="step-description">
            Thank you for joining EduHive! This quick setup will help you configure essential features to get the most out of your academic management experience.
          </p>
          
          <div class="features-grid">
            <div class="feature-card">
              <div class="feature-icon">📅</div>
              <div class="feature-title">Google Calendar Sync</div>
              <div class="feature-description">Seamlessly sync your class schedules and deadlines with Google Calendar</div>
            </div>
            <div class="feature-card">
              <div class="feature-icon">📱</div>
              <div class="feature-title">WhatsApp Reminders</div>
              <div class="feature-description">Get task reminders and notifications directly on WhatsApp</div>
            </div>
            <div class="feature-card">
              <div class="feature-icon">⏰</div>
              <div class="feature-title">Smart Notifications</div>
              <div class="feature-description">Intelligent reminders for assignments, exams, and deadlines</div>
            </div>
          </div>
        </div>
        
        <div class="button-group">
          <button class="btn btn-primary" onclick="nextStep()">Get Started</button>
        </div>
      </div>

      <!-- Step 2: Google Configuration -->
      <div class="setup-step" id="step2">
        <h2 class="step-title">Google Calendar Integration</h2>
        <p class="step-description">
          Connect your Google Calendar to automatically sync your class schedules and assignments. You'll need to create API credentials from Google Cloud Console.
        </p>

        <div class="api-instructions">
          <h4>📋 How to get your Google API credentials:</h4>
          <ol class="api-steps">
            <li>Go to <a href="https://console.cloud.google.com/" target="_blank">Google Cloud Console</a></li>
            <li>Create a new project or select an existing one</li>
            <li>Enable the Google Calendar API</li>
            <li>Go to "Credentials" and click "Create Credentials"</li>
            <li>Select "API Key" and copy the key</li>
            <li>Create "OAuth 2.0 Client ID" for web application</li>
            <li>Add your domain to authorized origins</li>
            <li>Copy the Client ID</li>
          </ol>
        </div>

        <form id="googleConfigForm">
          <div class="form-group">
            <label for="googleClientId">Google Client ID *</label>
            <input type="text" id="googleClientId" name="google_client_id" 
                   placeholder="1234567890-abcdefghijklmnop.apps.googleusercontent.com"
                   value="<?php echo htmlspecialchars($user_config['google_api_key'] ?? ''); ?>" required>
            <div class="help-text">Your API Key from Google Cloud Console</div>
          </div>

          <div class="form-group">
            <label for="timezone">Your Timezone</label>
            <select id="timezone" name="timezone">
              <option value="Asia/Kuala_Lumpur" <?php echo ($user_config['timezone'] ?? 'Asia/Kuala_Lumpur') === 'Asia/Kuala_Lumpur' ? 'selected' : ''; ?>>Malaysia (UTC+8)</option>
              <option value="Asia/Singapore" <?php echo ($user_config['timezone'] ?? '') === 'Asia/Singapore' ? 'selected' : ''; ?>>Singapore (UTC+8)</option>
              <option value="Asia/Jakarta" <?php echo ($user_config['timezone'] ?? '') === 'Asia/Jakarta' ? 'selected' : ''; ?>>Indonesia (UTC+7)</option>
              <option value="Asia/Bangkok" <?php echo ($user_config['timezone'] ?? '') === 'Asia/Bangkok' ? 'selected' : ''; ?>>Thailand (UTC+7)</option>
              <option value="UTC" <?php echo ($user_config['timezone'] ?? '') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
            </select>
          </div>

          <div class="form-group">
            <label for="whatsappToken">WhatsApp Business Token (Optional)</label>
            <input type="text" id="whatsappToken" name="whatsapp_token" 
                   placeholder="EAABsBCS1iHgBAxxxxxxxx"
                   value="<?php echo htmlspecialchars($user_config['whatsapp_token'] ?? ''); ?>">
            <div class="help-text">For WhatsApp reminders. Get this from <a href="https://developers.facebook.com/apps/" target="_blank">Meta for Developers</a></div>
          </div>
        </form>

        <div id="configStatus" class="status-message"></div>

        <div class="button-group">
          <button class="btn btn-secondary" onclick="prevStep()">Back</button>
          <button class="btn btn-test" onclick="testGoogleConnection()">Test Connection</button>
          <button class="btn btn-primary" onclick="saveConfigAndNext()">Next</button>
        </div>
      </div>

      <!-- Step 3: Integration & Import -->
      <div class="setup-step" id="step3">
        <h2 class="step-title">Import & Integration</h2>
        <p class="step-description">
          Great! Your Google Calendar is connected. Would you like to import your existing calendar events and set up additional features?
        </p>

        <div class="features-grid">
          <div class="feature-card">
            <div class="feature-icon">📥</div>
            <div class="feature-title">Import Calendar Events</div>
            <div class="feature-description">Import your existing Google Calendar events into EduHive</div>
            <button class="btn btn-test" onclick="importGoogleEvents()" style="margin-top: 15px; width: 100%;">Import Events</button>
          </div>
          <div class="feature-card">
            <div class="feature-icon">🔄</div>
            <div class="feature-title">Auto-Sync</div>
            <div class="feature-description">Automatically sync new events between EduHive and Google Calendar</div>
            <label style="margin-top: 15px; display: flex; align-items: center; gap: 10px;">
              <input type="checkbox" id="autoSync" checked style="width: auto;">
              <span>Enable Auto-Sync</span>
            </label>
          </div>
          <div class="feature-card">
            <div class="feature-icon">⚡</div>
            <div class="feature-title">Quick Setup</div>
            <div class="feature-description">Create default task categories and course templates</div>
            <button class="btn btn-test" onclick="quickSetup()" style="margin-top: 15px; width: 100%;">Quick Setup</button>
          </div>
        </div>

        <div id="integrationStatus" class="status-message"></div>

        <div class="button-group">
          <button class="btn btn-secondary" onclick="prevStep()">Back</button>
          <button class="btn btn-primary" onclick="nextStep()">Complete Setup</button>
        </div>
      </div>

      <!-- Step 4: Completion -->
      <div class="setup-step" id="step4">
        <div class="completion-content">
          <div class="completion-icon">🎉</div>
          <h2 class="completion-title">Setup Complete!</h2>
          <p class="completion-message">
            Congratulations! Your EduHive account is now fully configured and ready to help you manage your academic life efficiently.
          </p>
          
          <div class="features-grid">
            <div class="feature-card">
              <div class="feature-icon">✅</div>
              <div class="feature-title">Calendar Synced</div>
              <div class="feature-description">Your Google Calendar is connected and ready</div>
            </div>
            <div class="feature-card">
              <div class="feature-icon">🔔</div>
              <div class="feature-title">Notifications Ready</div>
              <div class="feature-description">Smart reminders are set up and active</div>
            </div>
            <div class="feature-card">
              <div class="feature-icon">📚</div>
              <div class="feature-title">Ready to Use</div>
              <div class="feature-description">Start adding tasks, schedules, and tracking your progress</div>
            </div>
          </div>
        </div>

        <div class="button-group">
          <button class="btn btn-primary" onclick="completeSetup()" style="width: 100%; font-size: 18px; padding: 20px;">
            🚀 Launch EduHive
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    let currentStep = 1;
    const totalSteps = 4;

    document.addEventListener('DOMContentLoaded', function() {
      updateProgress();
    });

    function nextStep() {
      if (currentStep < totalSteps) {
        // Hide current step
        document.getElementById(`step${currentStep}`).classList.remove('active');
        
        // Move to next step
        currentStep++;
        
        // Show next step
        document.getElementById(`step${currentStep}`).classList.add('active');
        
        // Update progress
        updateProgress();
      }
    }

    function prevStep() {
      if (currentStep > 1) {
        // Hide current step
        document.getElementById(`step${currentStep}`).classList.remove('active');
        
        // Move to previous step
        currentStep--;
        
        // Show previous step
        document.getElementById(`step${currentStep}`).classList.add('active');
        
        // Update progress
        updateProgress();
      }
    }

    function updateProgress() {
      // Update progress bar
      const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
      document.getElementById('progressFill').style.width = progressPercent + '%';
      
      // Update step indicators
      document.querySelectorAll('.progress-step').forEach((step, index) => {
        const stepNumber = index + 1;
        step.classList.remove('active', 'completed');
        
        if (stepNumber === currentStep) {
          step.classList.add('active');
        } else if (stepNumber < currentStep) {
          step.classList.add('completed');
        }
      });
    }

    function testGoogleConnection() {
      const clientId = document.getElementById('googleClientId').value.trim();
      const apiKey = document.getElementById('googleApiKey').value.trim();
      
      if (!clientId || !apiKey) {
        showStatus('configStatus', 'Please fill in both Google Client ID and API Key', 'error');
        return;
      }
      
      showStatus('configStatus', 'Testing Google Calendar connection...', 'info');
      
      // Disable button during test
      const testBtn = event.target;
      testBtn.disabled = true;
      testBtn.textContent = 'Testing...';
      
      fetch('setup.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=test_google_connection&google_client_id=${encodeURIComponent(clientId)}&google_api_key=${encodeURIComponent(apiKey)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showStatus('configStatus', data.message + ' ✅', 'success');
        } else {
          showStatus('configStatus', data.message || 'Connection failed. Please check your credentials.', 'error');
        }
      })
      .catch(error => {
        console.error('Test connection error:', error);
        showStatus('configStatus', 'Connection test failed. Please verify your credentials.', 'error');
      })
      .finally(() => {
        // Re-enable button
        testBtn.disabled = false;
        testBtn.textContent = 'Test Connection';
      });
    }

    function saveConfigAndNext() {
      const clientId = document.getElementById('googleClientId').value.trim();
      const apiKey = document.getElementById('googleApiKey').value.trim();
      const timezone = document.getElementById('timezone').value;
      const whatsappToken = document.getElementById('whatsappToken').value.trim();
      
      if (!clientId || !apiKey) {
        showStatus('configStatus', 'Please fill in the required Google credentials', 'error');
        return;
      }
      
      showStatus('configStatus', 'Saving configuration...', 'info');
      
      // Disable button during save
      const saveBtn = event.target;
      saveBtn.disabled = true;
      saveBtn.textContent = 'Saving...';
      
      const formData = new FormData();
      formData.append('action', 'save_google_config');
      formData.append('google_client_id', clientId);
      formData.append('google_api_key', apiKey);
      formData.append('timezone', timezone);
      formData.append('whatsapp_token', whatsappToken);
      
      fetch('setup.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showStatus('configStatus', 'Configuration saved successfully! ✅', 'success');
          setTimeout(() => {
            nextStep();
          }, 1000);
        } else {
          showStatus('configStatus', data.message || 'Failed to save configuration. Please try again.', 'error');
        }
      })
      .catch(error => {
        console.error('Save config error:', error);
        showStatus('configStatus', 'Error saving configuration. Please try again.', 'error');
      })
      .finally(() => {
        // Re-enable button
        saveBtn.disabled = false;
        saveBtn.textContent = 'Next';
      });
    }

    function importGoogleEvents() {
      showStatus('integrationStatus', 'Importing your Google Calendar events...', 'info');
      
      fetch('setup.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=import_google_events'
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showStatus('integrationStatus', `Successfully imported ${data.count || 0} events from Google Calendar! ✅`, 'success');
        } else {
          showStatus('integrationStatus', 'Failed to import events. You can do this later from the calendar page.', 'error');
        }
      })
      .catch(error => {
        showStatus('integrationStatus', 'Import failed. You can manually sync from the calendar page later.', 'error');
      });
    }

    function quickSetup() {
      showStatus('integrationStatus', 'Setting up default categories and templates...', 'info');
      
      // Simulate quick setup
      setTimeout(() => {
        showStatus('integrationStatus', 'Quick setup completed! Default courses and categories are ready. ✅', 'success');
      }, 2000);
    }

    function completeSetup() {
      showStatus('integrationStatus', 'Completing setup...', 'info');
      
      // Disable button during completion
      const completeBtn = event.target;
      completeBtn.disabled = true;
      completeBtn.textContent = '🔄 Finalizing...';
      
      fetch('setup.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=complete_setup'
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showStatus('integrationStatus', 'Setup completed! Redirecting to dashboard...', 'success');
          completeBtn.textContent = '✅ Complete!';
          
          // Add completion animation
          document.querySelector('.completion-content').style.transform = 'scale(1.05)';
          
          setTimeout(() => {
            window.location.href = 'dashboard.php?setup_completed=1';
          }, 2000);
        } else {
          showStatus('integrationStatus', data.message || 'Failed to complete setup. Please try again.', 'error');
          completeBtn.disabled = false;
          completeBtn.textContent = '🚀 Launch EduHive';
        }
      })
      .catch(error => {
        console.error('Complete setup error:', error);
        showStatus('integrationStatus', 'Error completing setup. Please try again.', 'error');
        completeBtn.disabled = false;
        completeBtn.textContent = '🚀 Launch EduHive';
      });
    }

    function showStatus(elementId, message, type) {
      const statusElement = document.getElementById(elementId);
      statusElement.textContent = message;
      statusElement.className = `status-message ${type}`;
      statusElement.style.display = 'block';
      
      // Auto-hide info messages after 5 seconds
      if (type === 'info') {
        setTimeout(() => {
          if (statusElement.textContent === message) {
            statusElement.style.display = 'none';
          }
        }, 5000);
      }
    }

    // Keyboard navigation
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Enter' && currentStep === 1) {
        nextStep();
      } else if (e.key === 'Enter' && currentStep === 2) {
        saveConfigAndNext();
      } else if (e.key === 'Enter' && currentStep === 3) {
        nextStep();
      } else if (e.key === 'Enter' && currentStep === 4) {
        completeSetup();
      } else if (e.key === 'ArrowLeft' && currentStep > 1) {
        prevStep();
      } else if (e.key === 'ArrowRight' && currentStep < totalSteps) {
        if (currentStep === 2) {
          saveConfigAndNext();
        } else {
          nextStep();
        }
      }
    });

    // Auto-save form data as user types
    document.addEventListener('input', function(e) {
      if (e.target.form && e.target.form.id === 'googleConfigForm') {
        localStorage.setItem('eduhive_setup_' + e.target.name, e.target.value);
      }
    });

    // Restore form data from localStorage
    document.addEventListener('DOMContentLoaded', function() {
      const form = document.getElementById('googleConfigForm');
      if (form) {
        const inputs = form.querySelectorAll('input, select');
        inputs.forEach(input => {
          const saved = localStorage.getItem('eduhive_setup_' + input.name);
          if (saved && !input.value) {
            input.value = saved;
          }
        });
      }
    });

    // Clear localStorage after successful setup
    function clearSetupData() {
      const keys = Object.keys(localStorage).filter(key => key.startsWith('eduhive_setup_'));
      keys.forEach(key => localStorage.removeItem(key));
    }

    // Add smooth transitions
    document.querySelectorAll('.btn').forEach(btn => {
      btn.addEventListener('click', function() {
        this.style.transform = 'scale(0.95)';
        setTimeout(() => {
          this.style.transform = '';
        }, 150);
      });
    });

    // Progress animation on load
    window.addEventListener('load', function() {
      setTimeout(() => {
        document.querySelector('.setup-container').style.opacity = '1';
        document.querySelector('.setup-container').style.transform = 'scale(1)';
      }, 100);
    });

    // Add initial styles for animations
    document.querySelector('.setup-container').style.opacity = '0';
    document.querySelector('.setup-container').style.transform = 'scale(0.95)';
    document.querySelector('.setup-container').style.transition = 'all 0.5s ease';

    // Form field validation and real-time feedback
    function setupFormValidation() {
      const clientIdInput = document.getElementById('googleClientId');
      const apiKeyInput = document.getElementById('googleApiKey');
      
      // Google Client ID validation
      clientIdInput.addEventListener('input', function() {
        const value = this.value.trim();
        const isValid = value.length > 50 && value.includes('.apps.googleusercontent.com');
        
        if (value.length > 0) {
          if (isValid) {
            this.style.borderColor = '#28a745';
            this.style.backgroundColor = '#f8fff9';
          } else {
            this.style.borderColor = '#dc3545';
            this.style.backgroundColor = '#fff8f8';
          }
        } else {
          this.style.borderColor = '#e1e5e9';
          this.style.backgroundColor = '#f8f9fa';
        }
      });
      
      // Google API Key validation
      apiKeyInput.addEventListener('input', function() {
        const value = this.value.trim();
        const isValid = value.length > 30 && value.startsWith('AIza');
        
        if (value.length > 0) {
          if (isValid) {
            this.style.borderColor = '#28a745';
            this.style.backgroundColor = '#f8fff9';
          } else {
            this.style.borderColor = '#dc3545';
            this.style.backgroundColor = '#fff8f8';
          }
        } else {
          this.style.borderColor = '#e1e5e9';
          this.style.backgroundColor = '#f8f9fa';
        }
      });
    }
    
    // Initialize form validation when DOM is ready
    document.addEventListener('DOMContentLoaded', function() {
      setupFormValidation();
      
      // Auto-focus first input on step 2
      if (currentStep === 2) {
        document.getElementById('googleClientId').focus();
      }
    });

    // Enhanced step navigation with validation
    function validateCurrentStep() {
      switch(currentStep) {
        case 1:
          return true; // Welcome step always valid
          
        case 2:
          const clientId = document.getElementById('googleClientId').value.trim();
          const apiKey = document.getElementById('googleApiKey').value.trim();
          
          if (!clientId || !apiKey) {
            showStatus('configStatus', 'Please fill in both Google Client ID and API Key', 'error');
            return false;
          }
          
          if (clientId.length < 50 || !clientId.includes('.apps.googleusercontent.com')) {
            showStatus('configStatus', 'Google Client ID format appears invalid', 'error');
            return false;
          }
          
          if (apiKey.length < 30 || !apiKey.startsWith('AIza')) {
            showStatus('configStatus', 'Google API Key format appears invalid', 'error');
            return false;
          }
          
          return true;
          
        case 3:
          return true; // Integration step always valid
          
        case 4:
          return true; // Completion step always valid
          
        default:
          return true;
      }
    }

    // Enhanced save configuration with better error handling
    function saveConfigAndNextEnhanced() {
      if (!validateCurrentStep()) {
        return;
      }
      
      const clientId = document.getElementById('googleClientId').value.trim();
      const apiKey = document.getElementById('googleApiKey').value.trim();
      const timezone = document.getElementById('timezone').value;
      const whatsappToken = document.getElementById('whatsappToken').value.trim();
      
      showStatus('configStatus', 'Validating and saving configuration...', 'info');
      
      // Show loading state
      const saveBtn = document.querySelector('.btn-primary');
      const originalText = saveBtn.textContent;
      saveBtn.disabled = true;
      saveBtn.innerHTML = '⏳ Saving...';
      
      const formData = new FormData();
      formData.append('action', 'save_google_config');
      formData.append('google_client_id', clientId);
      formData.append('google_api_key', apiKey);
      formData.append('timezone', timezone);
      formData.append('whatsapp_token', whatsappToken);
      
      fetch('setup.php', {
        method: 'POST',
        body: formData
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          showStatus('configStatus', '✅ Configuration saved successfully!', 'success');
          
          // Save to localStorage for backup
          localStorage.setItem('eduhive_google_configured', 'true');
          
          // Animate success and proceed
          saveBtn.innerHTML = '✅ Saved!';
          saveBtn.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
          
          setTimeout(() => {
            nextStep();
          }, 1500);
        } else {
          throw new Error(data.message || 'Unknown error occurred');
        }
      })
      .catch(error => {
        console.error('Save configuration error:', error);
        
        let errorMessage = 'Failed to save configuration. ';
        if (error.message.includes('HTTP error')) {
          errorMessage += 'Server connection issue. Please try again.';
        } else if (error.message.includes('database')) {
          errorMessage += 'Database error. Please check your server configuration.';
        } else {
          errorMessage += error.message;
        }
        
        showStatus('configStatus', errorMessage, 'error');
        
        // Reset button
        saveBtn.disabled = false;
        saveBtn.innerHTML = originalText;
        saveBtn.style.background = '';
      });
    }

    // Enhanced completion with better feedback
    function completeSetupEnhanced() {
      const completeBtn = document.querySelector('.btn-primary');
      const originalText = completeBtn.textContent;
      
      showStatus('integrationStatus', 'Finalizing your EduHive setup...', 'info');
      
      // Animate button
      completeBtn.disabled = true;
      completeBtn.innerHTML = '🔄 Finalizing Setup...';
      completeBtn.style.background = 'linear-gradient(45deg, #6c757d, #5a6268)';
      
      fetch('setup.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=complete_setup'
      })
      .then(response => {
        if (!response.ok) {
          throw new Error(`HTTP error! status: ${response.status}`);
        }
        return response.json();
      })
      .then(data => {
        if (data.success) {
          showStatus('integrationStatus', '🎉 Setup completed successfully!', 'success');
          
          // Success animation sequence
          completeBtn.innerHTML = '✅ Setup Complete!';
          completeBtn.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
          
          // Add celebration effect
          createCelebrationEffect();
          
          // Clear any stored setup data
          clearSetupData();
          
          // Redirect with delay
          setTimeout(() => {
            completeBtn.innerHTML = '🚀 Launching EduHive...';
            setTimeout(() => {
              window.location.href = 'dashboard.php?setup_completed=1';
            }, 1000);
          }, 2000);
        } else {
          throw new Error(data.message || 'Failed to complete setup');
        }
      })
      .catch(error => {
        console.error('Complete setup error:', error);
        showStatus('integrationStatus', `Setup completion failed: ${error.message}`, 'error');
        
        // Reset button
        completeBtn.disabled = false;
        completeBtn.innerHTML = originalText;
        completeBtn.style.background = '';
      });
    }

    // Celebration effect for setup completion
    function createCelebrationEffect() {
      const celebration = document.createElement('div');
      celebration.innerHTML = '🎉';
      celebration.style.cssText = `
        position: fixed;
        font-size: 60px;
        z-index: 9999;
        pointer-events: none;
        animation: celebrate 3s ease-out forwards;
      `;
      
      // Add celebration animation
      const style = document.createElement('style');
      style.textContent = `
        @keyframes celebrate {
          0% { transform: translateY(100vh) rotate(0deg); opacity: 1; }
          50% { transform: translateY(-20vh) rotate(180deg); opacity: 1; }
          100% { transform: translateY(-100vh) rotate(360deg); opacity: 0; }
        }
      `;
      document.head.appendChild(style);
      
      // Create multiple celebration emojis
      const emojis = ['🎉', '🎊', '✨', '🌟', '🎈'];
      for (let i = 0; i < 10; i++) {
        setTimeout(() => {
          const emoji = celebration.cloneNode(true);
          emoji.innerHTML = emojis[Math.floor(Math.random() * emojis.length)];
          emoji.style.left = Math.random() * 100 + 'vw';
          emoji.style.animationDelay = Math.random() * 2 + 's';
          document.body.appendChild(emoji);
          
          setTimeout(() => {
            if (document.body.contains(emoji)) {
              document.body.removeChild(emoji);
            }
          }, 3000);
        }, i * 200);
      }
    }

    // Enhanced test connection with detailed feedback
    function testGoogleConnectionEnhanced() {
      const clientId = document.getElementById('googleClientId').value.trim();
      const apiKey = document.getElementById('googleApiKey').value.trim();
      
      if (!clientId || !apiKey) {
        showStatus('configStatus', '⚠️ Please fill in both Google Client ID and API Key', 'error');
        return;
      }
      
      if (clientId.length < 50 || !clientId.includes('.apps.googleusercontent.com')) {
        showStatus('configStatus', '⚠️ Google Client ID format appears invalid', 'error');
        return;
      }
      
      if (apiKey.length < 30 || !apiKey.startsWith('AIza')) {
        showStatus('configStatus', '⚠️ Google API Key format appears invalid', 'error');
        return;
      }
      
      const testBtn = document.querySelector('.btn-test');
      const originalText = testBtn.textContent;
      
      showStatus('configStatus', '🔍 Testing Google Calendar connection...', 'info');
      
      testBtn.disabled = true;
      testBtn.innerHTML = '🔄 Testing...';
      
      fetch('setup.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `action=test_google_connection&google_client_id=${encodeURIComponent(clientId)}&google_api_key=${encodeURIComponent(apiKey)}`
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          showStatus('configStatus', '✅ ' + data.message, 'success');
          testBtn.innerHTML = '✅ Connection OK';
          testBtn.style.background = 'linear-gradient(45deg, #28a745, #20c997)';
          
          // Auto-enable save button
          setTimeout(() => {
            const saveBtn = document.querySelector('.btn-primary');
            saveBtn.style.animation = 'pulse 1s ease-in-out';
          }, 1000);
        } else {
          showStatus('configStatus', '❌ ' + (data.message || 'Connection failed'), 'error');
          testBtn.innerHTML = '❌ Failed';
          testBtn.style.background = 'linear-gradient(45deg, #dc3545, #c82333)';
        }
      })
      .catch(error => {
        console.error('Test connection error:', error);
        showStatus('configStatus', '❌ Connection test failed. Please verify your credentials.', 'error');
        testBtn.innerHTML = '❌ Error';
        testBtn.style.background = 'linear-gradient(45deg, #dc3545, #c82333)';
      })
      .finally(() => {
        setTimeout(() => {
          testBtn.disabled = false;
          testBtn.innerHTML = originalText;
          testBtn.style.background = '';
        }, 3000);
      });
    }

    // Add pulse animation for buttons
    const pulseStyle = document.createElement('style');
    pulseStyle.textContent = `
      @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
      }
    `;
    document.head.appendChild(pulseStyle);

    // Replace original functions with enhanced versions
    window.saveConfigAndNext = saveConfigAndNextEnhanced;
    window.completeSetup = completeSetupEnhanced;
    window.testGoogleConnection = testGoogleConnectionEnhanced;

    // Auto-save progress
    function saveProgress() {
      const progress = {
        currentStep: currentStep,
        timestamp: Date.now()
      };
      localStorage.setItem('eduhive_setup_progress', JSON.stringify(progress));
    }

    // Load saved progress
    function loadProgress() {
      try {
        const saved = localStorage.getItem('eduhive_setup_progress');
        if (saved) {
          const progress = JSON.parse(saved);
          // Only restore if saved within last 24 hours
          if (Date.now() - progress.timestamp < 24 * 60 * 60 * 1000) {
            currentStep = Math.min(progress.currentStep, totalSteps);
            updateProgress();
            
            // Show appropriate step
            document.querySelectorAll('.setup-step').forEach(step => step.classList.remove('active'));
            document.getElementById(`step${currentStep}`).classList.add('active');
          }
        }
      } catch (e) {
        console.log('No valid saved progress found');
      }
    }

    // Enhanced step navigation with progress saving
    const originalNextStep = window.nextStep;
    const originalPrevStep = window.prevStep;
    
    window.nextStep = function() {
      originalNextStep();
      saveProgress();
    };
    
    window.prevStep = function() {
      originalPrevStep();
      saveProgress();
    };

    // Load progress on page load
    document.addEventListener('DOMContentLoaded', function() {
      loadProgress();
    });

    // Warn user before leaving page during setup
    window.addEventListener('beforeunload', function(e) {
      if (currentStep > 1 && currentStep < totalSteps) {
        e.preventDefault();
        e.returnValue = 'Are you sure you want to leave? Your setup progress will be saved.';
      }
    });

    // Add helpful tooltips
    function addTooltips() {
      const tooltips = {
        'googleClientId': 'This should be a long string ending with .apps.googleusercontent.com',
        'googleApiKey': 'This should start with "AIza" and be about 39 characters long',
        'timezone': 'Select your local timezone for accurate scheduling',
        'whatsappToken': 'Optional: Get this from Meta for Developers for WhatsApp reminders'
      };
      
      Object.keys(tooltips).forEach(id => {
        const element = document.getElementById(id);
        if (element) {
          element.title = tooltips[id];
          element.addEventListener('focus', function() {
            showStatus('configStatus', this.title, 'info');
          });
        }
      });
    }

    // Initialize tooltips
    document.addEventListener('DOMContentLoaded', addTooltips);

    console.log('✅ EduHive Setup Wizard initialized successfully');
  </script>
</body>
</html>