<?php
// setup_middleware.php - Check if user needs to complete setup
require_once 'config/database.php';
require_once 'config/session.php';

/**
 * Check if user has completed setup and redirect if necessary
 * Call this function at the top of protected pages
 * 
 * @param array $excluded_pages Pages that don't require setup check
 */
function checkSetupStatus($excluded_pages = []) {
    // Skip setup check for certain pages
    $current_page = basename($_SERVER['PHP_SELF']);
    $default_excluded = [
        'setup.php',
        'login.php', 
        'register.php', 
        'recovery.php',
        'simple_reset.php',
        'reset_password.php',
        'logout.php',
        'verify_email.php'
    ];
    
    $excluded_pages = array_merge($default_excluded, $excluded_pages);
    
    if (in_array($current_page, $excluded_pages)) {
        return;
    }
    
    // Check if user is logged in
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
    
    $user_id = getCurrentUserId();
    
    // Check if setup is completed
    if (!isSetupCompleted($user_id)) {
        header("Location: setup.php");
        exit();
    }
}

/**
 * Check if user has completed the setup process
 * 
 * @param int $user_id User ID
 * @return bool True if setup is completed
 */
function isSetupCompleted($user_id) {
    $database = new Database();
    
    try {
        // Create table if it doesn't exist
        createSetupTable($database);
        
        $query = "SELECT setup_completed FROM user_settings WHERE user_id = :user_id";
        $result = $database->queryRow($query, [':user_id' => $user_id]);
        
        return $result && $result['setup_completed'] == 1;
    } catch (Exception $e) {
        error_log("Setup check error: " . $e->getMessage());
        return false; // Assume setup not completed if there's an error
    }
}

/**
 * Get user's setup configuration
 * 
 * @param int $user_id User ID
 * @return array User configuration
 */
function getUserSetupConfig($user_id) {
    $database = new Database();
    
    try {
        createSetupTable($database);
        
        $query = "SELECT * FROM user_settings WHERE user_id = :user_id";
        $result = $database->queryRow($query, [':user_id' => $user_id]);
        
        return $result ?: [];
    } catch (Exception $e) {
        error_log("Error getting user config: " . $e->getMessage());
        return [];
    }
}

/**
 * Create user_settings table if it doesn't exist
 * 
 * @param Database $database Database instance
 */
function createSetupTable($database) {
    $create_table = "CREATE TABLE IF NOT EXISTS user_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        google_client_id VARCHAR(255),
        google_api_key VARCHAR(255),
        whatsapp_token VARCHAR(255),
        timezone VARCHAR(100) DEFAULT 'Asia/Kuala_Lumpur',
        auto_sync BOOLEAN DEFAULT TRUE,
        setup_completed BOOLEAN DEFAULT FALSE,
        setup_completed_at TIMESTAMP NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_user (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    
    $database->getConnection()->exec($create_table);
}

/**
 * Mark user setup as completed
 * 
 * @param int $user_id User ID
 * @return bool Success status
 */
function markSetupCompleted($user_id) {
    $database = new Database();
    
    try {
        createSetupTable($database);
        
        // Check if record exists
        $existing = $database->queryRow(
            "SELECT id FROM user_settings WHERE user_id = :user_id",
            [':user_id' => $user_id]
        );
        
        $data = [
            'setup_completed' => 1,
            'setup_completed_at' => date('Y-m-d H:i:s')
        ];
        
        if ($existing) {
            return $database->update('user_settings', $data, 'user_id = :user_id', [':user_id' => $user_id]) > 0;
        } else {
            $data['user_id'] = $user_id;
            return $database->insert('user_settings', $data) !== false;
        }
        
    } catch (Exception $e) {
        error_log("Error marking setup completed: " . $e->getMessage());
        return false;
    }
}

/**
 * Reset user setup (useful for testing or re-setup)
 * 
 * @param int $user_id User ID
 * @return bool Success status
 */
function resetUserSetup($user_id) {
    $database = new Database();
    
    try {
        $data = [
            'setup_completed' => 0,
            'setup_completed_at' => null
        ];
        
        return $database->update('user_settings', $data, 'user_id = :user_id', [':user_id' => $user_id]) >= 0;
        
    } catch (Exception $e) {
        error_log("Error resetting user setup: " . $e->getMessage());
        return false;
    }
}

/**
 * Check if user has valid Google Calendar configuration
 * 
 * @param int $user_id User ID
 * @return bool True if Google config is valid
 */
function hasValidGoogleConfig($user_id) {
    $config = getUserSetupConfig($user_id);
    
    return !empty($config['google_client_id']) && 
           !empty($config['google_api_key']) && 
           $config['setup_completed'] == 1;
}

/**
 * Get user's Google Calendar credentials
 * 
 * @param int $user_id User ID
 * @return array Google credentials
 */
function getGoogleCredentials($user_id) {
    $config = getUserSetupConfig($user_id);
    
    return [
        'client_id' => $config['google_client_id'] ?? '',
        'api_key' => $config['google_api_key'] ?? '',
        'timezone' => $config['timezone'] ?? 'Asia/Kuala_Lumpur'
    ];
}

/**
 * Get user's WhatsApp configuration
 * 
 * @param int $user_id User ID
 * @return array WhatsApp config
 */
function getWhatsAppConfig($user_id) {
    $config = getUserSetupConfig($user_id);
    
    return [
        'token' => $config['whatsapp_token'] ?? '',
        'enabled' => !empty($config['whatsapp_token'])
    ];
}

/**
 * Check if auto-sync is enabled for user
 * 
 * @param int $user_id User ID
 * @return bool True if auto-sync is enabled
 */
function isAutoSyncEnabled($user_id) {
    $config = getUserSetupConfig($user_id);
    
    return $config['auto_sync'] ?? true;
}

/**
 * Update user's Google Calendar configuration
 * 
 * @param int $user_id User ID
 * @param array $google_config Google configuration
 * @return bool Success status
 */
function updateGoogleConfig($user_id, $google_config) {
    $database = new Database();
    
    try {
        createSetupTable($database);
        
        $data = [
            'google_client_id' => $google_config['client_id'] ?? '',
            'google_api_key' => $google_config['api_key'] ?? '',
            'timezone' => $google_config['timezone'] ?? 'Asia/Kuala_Lumpur'
        ];
        
        // Check if record exists
        $existing = $database->queryRow(
            "SELECT id FROM user_settings WHERE user_id = :user_id",
            [':user_id' => $user_id]
        );
        
        if ($existing) {
            return $database->update('user_settings', $data, 'user_id = :user_id', [':user_id' => $user_id]) > 0;
        } else {
            $data['user_id'] = $user_id;
            return $database->insert('user_settings', $data) !== false;
        }
        
    } catch (Exception $e) {
        error_log("Error updating Google config: " . $e->getMessage());
        return false;
    }
}

/**
 * Create default courses and categories for new user
 * 
 * @param int $user_id User ID
 * @return bool Success status
 */
function createDefaultSetup($user_id) {
    $database = new Database();
    
    try {
        // Create default courses
        $default_courses = [
            [
                'name' => 'Final Year Project',
                'code' => 'FYP',
                'description' => 'Final year project work and documentation',
                'color' => '#8B7355'
            ],
            [
                'name' => 'Programming',
                'code' => 'PROG',
                'description' => 'Programming assignments and projects',
                'color' => '#6c757d'
            ],
            [
                'name' => 'General Studies',
                'code' => 'GEN',
                'description' => 'General academic tasks and assignments',
                'color' => '#fd7e14'
            ]
        ];
        
        foreach ($default_courses as $course) {
            $course['user_id'] = $user_id;
            $database->insert('courses', $course);
        }
        
        // Create welcome task
        $welcome_task = [
            'user_id' => $user_id,
            'title' => 'Welcome to EduHive! 🎉',
            'description' => 'Complete this task to get familiar with the task management system. You can edit, delete, or mark this as complete.',
            'status' => 'todo',
            'priority' => 'medium',
            'due_date' => date('Y-m-d', strtotime('+3 days')),
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $database->insert('tasks', $welcome_task);
        
        return true;
        
    } catch (Exception $e) {
        error_log("Error creating default setup: " . $e->getMessage());
        return false;
    }
}

/**
 * Get setup progress percentage
 * 
 * @param int $user_id User ID
 * @return int Progress percentage (0-100)
 */
function getSetupProgress($user_id) {
    $config = getUserSetupConfig($user_id);
    
    if (empty($config)) {
        return 0;
    }
    
    $progress = 0;
    $total_steps = 4;
    
    // Step 1: User registered (always complete if we're here)
    $progress += 25;
    
    // Step 2: Google credentials provided
    if (!empty($config['google_client_id']) && !empty($config['google_api_key'])) {
        $progress += 25;
    }
    
    // Step 3: Additional configuration
    if (!empty($config['timezone'])) {
        $progress += 25;
    }
    
    // Step 4: Setup marked as completed
    if ($config['setup_completed']) {
        $progress += 25;
    }
    
    return min(100, $progress);
}

/**
 * Log setup activity
 * 
 * @param int $user_id User ID
 * @param string $action Action performed
 * @param string $details Additional details
 */
function logSetupActivity($user_id, $action, $details = '') {
    $database = new Database();
    
    try {
        // Create setup_logs table if needed
        $create_logs_table = "CREATE TABLE IF NOT EXISTS setup_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            action VARCHAR(100) NOT NULL,
            details TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $database->getConnection()->exec($create_logs_table);
        
        $log_data = [
            'user_id' => $user_id,
            'action' => $action,
            'details' => $details,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        ];
        
        $database->insert('setup_logs', $log_data);
        
    } catch (Exception $e) {
        error_log("Error logging setup activity: " . $e->getMessage());
    }
}
?>