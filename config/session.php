<?php
// config/session.php - Session management and authentication functions

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Configure session settings for security
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', isset($_SERVER['HTTPS']));
    ini_set('session.cookie_samesite', 'Strict');
    
    session_start();
}

/**
 * Check if user is logged in, redirect to login if not
 * @param string $redirect_to URL to redirect to after login (optional)
 */
function requireLogin($redirect_to = '') {
    if (!isLoggedIn()) {
        // Store the current page for redirect after login
        if (empty($redirect_to)) {
            $redirect_to = $_SERVER['REQUEST_URI'];
        }
        $_SESSION['redirect_after_login'] = $redirect_to;
        
        // Redirect to login page
        header("Location: login.php");
        exit();
    }
    
    // Update last activity
    updateLastActivity();
}

/**
 * Check if user is currently logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['user_email']) && 
           !empty($_SESSION['user_id']) &&
           !isSessionExpired();
}

/**
 * Get current logged-in user's ID
 * @return int|null User ID if logged in, null otherwise
 */
function getCurrentUserId() {
    if (isLoggedIn()) {
        return (int) $_SESSION['user_id'];
    }
    return null;
}

/**
 * Get current logged-in user's email
 * @return string|null User email if logged in, null otherwise
 */
function getCurrentUserEmail() {
    if (isLoggedIn()) {
        return $_SESSION['user_email'];
    }
    return null;
}

/**
 * Get current logged-in user's name
 * @return string|null User name if logged in, null otherwise
 */
function getCurrentUserName() {
    if (isLoggedIn()) {
        return $_SESSION['user_name'] ?? null;
    }
    return null;
}

/**
 * Get current logged-in user's role
 * @return string|null User role if logged in, null otherwise
 */
function getCurrentUserRole() {
    if (isLoggedIn()) {
        return $_SESSION['user_role'] ?? 'user';
    }
    return null;
}

/**
 * Login user and set session variables
 * @param array $user User data from database
 * @param bool $remember_me Set remember me cookie (optional)
 * @return bool True on success
 */
function loginUser($user, $remember_me = false) {
    if (empty($user) || !isset($user['id'])) {
        return false;
    }
    
    // Regenerate session ID for security
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'] ?? '';
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    $_SESSION['login_time'] = time();
    $_SESSION['last_activity'] = time();
    $_SESSION['ip_address'] = getUserIP();
    $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Set remember me cookie if requested
    if ($remember_me) {
        setRememberMeCookie($user['id']);
    }
    
    // Update last login in database
    updateLastLogin($user['id']);
    
    return true;
}

/**
 * Logout user and destroy session
 * @param bool $redirect Whether to redirect to login page
 */
function logoutUser($redirect = false) {
    $user_id = getCurrentUserId();
    
    // Clear remember me cookie
    clearRememberMeCookie();
    
    // Log logout activity
    if ($user_id) {
        logActivity($user_id, 'logout', 'User logged out');
    }
    
    // Unset all session variables
    $_SESSION = array();
    
    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Destroy session
    session_destroy();
    
    if ($redirect) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Check if user has specific role
 * @param string $role Role to check
 * @return bool True if user has role, false otherwise
 */
function hasRole($role) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return getCurrentUserRole() === $role;
}

/**
 * Check if user is admin
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    return hasRole('admin');
}

/**
 * Check if user has any of the specified roles
 * @param array $roles Array of roles to check
 * @return bool True if user has any of the roles
 */
function hasAnyRole($roles) {
    if (!isLoggedIn()) {
        return false;
    }
    
    return in_array(getCurrentUserRole(), $roles);
}

/**
 * Require specific role, redirect if not authorized
 * @param string|array $required_roles Required role(s)
 * @param string $redirect_url URL to redirect to if unauthorized
 */
function requireRole($required_roles, $redirect_url = 'dashboard.php') {
    requireLogin(); // Ensure user is logged in first
    
    $user_role = getCurrentUserRole();
    $required_roles = is_array($required_roles) ? $required_roles : [$required_roles];
    
    if (!in_array($user_role, $required_roles)) {
        setFlashMessage('You do not have permission to access this page.', 'error');
        header("Location: " . $redirect_url);
        exit();
    }
}

/**
 * Update last activity timestamp
 */
function updateLastActivity() {
    if (isLoggedIn()) {
        $_SESSION['last_activity'] = time();
    }
}

/**
 * Check if session has expired (optional timeout)
 * @param int $timeout Timeout in seconds (default: 3600 = 1 hour)
 * @return bool True if expired, false otherwise
 */
function isSessionExpired($timeout = 3600) {
    if (!isset($_SESSION['user_id'])) {
        return true;
    }
    
    if (isset($_SESSION['last_activity'])) {
        $elapsed = time() - $_SESSION['last_activity'];
        return $elapsed > $timeout;
    }
    
    return false;
}

/**
 * Get user data for current session
 * @return array|null User data or null if not logged in
 */
function getCurrentUser() {
    if (!isLoggedIn()) {
        return null;
    }
    
    return [
        'id' => getCurrentUserId(),
        'email' => getCurrentUserEmail(),
        'name' => getCurrentUserName(),
        'role' => getCurrentUserRole(),
        'login_time' => $_SESSION['login_time'] ?? null,
        'last_activity' => $_SESSION['last_activity'] ?? null,
        'ip_address' => $_SESSION['ip_address'] ?? null
    ];
}

/**
 * Set flash message for next page load
 * @param string $message Message text
 * @param string $type Message type (success, error, warning, info)
 */
function setFlashMessage($message, $type = 'info') {
    $_SESSION['flash_message'] = [
        'message' => $message,
        'type' => $type,
        'timestamp' => time()
    ];
}

/**
 * Get and clear flash message
 * @return array|null Flash message data or null
 */
function getFlashMessage() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * Check if there's a flash message
 * @return bool True if flash message exists
 */
function hasFlashMessage() {
    return isset($_SESSION['flash_message']);
}

/**
 * Redirect to dashboard or specified page after login
 * @param string $default_page Default page to redirect to
 */
function redirectAfterLogin($default_page = 'dashboard.php') {
    $redirect_to = $_SESSION['redirect_after_login'] ?? $default_page;
    unset($_SESSION['redirect_after_login']);
    
    header("Location: " . $redirect_to);
    exit();
}

/**
 * CSRF Token functions for security
 */

/**
 * Generate CSRF token
 * @return string CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 * @param string $token Token to verify
 * @return bool True if valid, false otherwise
 */
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Get CSRF token input field HTML
 * @return string HTML input field
 */
function getCSRFTokenField() {
    $token = generateCSRFToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Require valid CSRF token, exit if invalid
 */
function requireCSRFToken() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($token)) {
        http_response_code(403);
        die('CSRF token validation failed');
    }
}

/**
 * Remember Me functionality
 */

/**
 * Set remember me cookie
 * @param int $user_id User ID
 * @param int $days Days to remember (default: 30)
 */
function setRememberMeCookie($user_id, $days = 30) {
    $token = bin2hex(random_bytes(32));
    $expires = time() + ($days * 24 * 60 * 60);
    
    // Store token in database
    require_once 'database.php';
    $database = new Database();
    
    // Clean old remember tokens for this user
    $database->delete('remember_tokens', 'user_id = :user_id', [':user_id' => $user_id]);
    
    // Insert new token
    $database->insert('remember_tokens', [
        'user_id' => $user_id,
        'token' => hash('sha256', $token),
        'expires_at' => date('Y-m-d H:i:s', $expires)
    ]);
    
    // Set cookie
    setcookie('remember_me', $token, $expires, '/', '', isset($_SERVER['HTTPS']), true);
}

/**
 * Check remember me cookie and auto-login
 * @return bool True if auto-login successful
 */
function checkRememberMeCookie() {
    if (!isset($_COOKIE['remember_me'])) {
        return false;
    }
    
    $token = $_COOKIE['remember_me'];
    $token_hash = hash('sha256', $token);
    
    require_once 'database.php';
    $database = new Database();
    
    // Find valid token
    $token_data = $database->queryRow(
        "SELECT rt.user_id, u.* FROM remember_tokens rt 
         JOIN users u ON rt.user_id = u.id 
         WHERE rt.token = :token AND rt.expires_at > NOW()",
        [':token' => $token_hash]
    );
    
    if ($token_data) {
        // Auto-login user
        if (loginUser($token_data)) {
            // Refresh the remember me token
            setRememberMeCookie($token_data['user_id']);
            return true;
        }
    }
    
    // Clear invalid cookie
    clearRememberMeCookie();
    return false;
}

/**
 * Clear remember me cookie
 */
function clearRememberMeCookie() {
    if (isset($_COOKIE['remember_me'])) {
        $token = $_COOKIE['remember_me'];
        $token_hash = hash('sha256', $token);
        
        // Remove from database
        require_once 'database.php';
        $database = new Database();
        $database->delete('remember_tokens', 'token = :token', [':token' => $token_hash]);
    }
    
    // Clear cookie
    setcookie('remember_me', '', time() - 3600, '/', '', isset($_SERVER['HTTPS']), true);
}

/**
 * Security functions
 */

/**
 * Check for suspicious activity
 * @return bool True if suspicious activity detected
 */
function checkSuspiciousActivity() {
    if (!isLoggedIn()) {
        return false;
    }
    
    $current_ip = getUserIP();
    $current_user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    // Check if IP or user agent changed significantly
    if (isset($_SESSION['ip_address']) && $_SESSION['ip_address'] !== $current_ip) {
        logActivity(getCurrentUserId(), 'ip_change', "IP changed from {$_SESSION['ip_address']} to {$current_ip}");
        // You might want to force re-authentication here
    }
    
    if (isset($_SESSION['user_agent']) && $_SESSION['user_agent'] !== $current_user_agent) {
        logActivity(getCurrentUserId(), 'user_agent_change', 'User agent changed');
    }
    
    return false;
}

/**
 * Update last login time in database
 * @param int $user_id User ID
 */
function updateLastLogin($user_id) {
    require_once 'database.php';
    $database = new Database();
    
    $database->update('users', 
        ['updated_at' => date('Y-m-d H:i:s')], 
        'id = :id', 
        [':id' => $user_id]
    );
    
    // Update user progress last login
    $database->query(
        "INSERT INTO user_progress (user_id, last_login) VALUES (:user_id, CURDATE()) 
         ON DUPLICATE KEY UPDATE last_login = CURDATE()",
        [':user_id' => $user_id]
    );
}

/**
 * Session cleanup functions
 */

/**
 * Clean expired sessions and tokens
 */
function cleanupExpiredSessions() {
    require_once 'database.php';
    $database = new Database();
    
    // Clean expired remember tokens
    $database->query("DELETE FROM remember_tokens WHERE expires_at < NOW()");
    
    // Clean expired email tokens
    $database->query("DELETE FROM email_tokens WHERE expires_at < NOW() AND used = FALSE");
}

/**
 * Get user's IP address (helper function)
 * @return string IP address
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Activity logging helper (if functions.php not loaded yet)
 */
if (!function_exists('logActivity')) {
    function logActivity($user_id, $action, $details = '') {
        require_once 'database.php';
        $database = new Database();
        
        try {
            $database->insert('activity_logs', [
                'user_id' => $user_id,
                'action' => $action,
                'details' => $details,
                'ip_address' => getUserIP(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'created_at' => date('Y-m-d H:i:s')
            ]);
        } catch (Exception $e) {
            error_log("Error logging activity: " . $e->getMessage());
        }
    }
}

// Auto-update last activity on each page load
if (isLoggedIn()) {
    updateLastActivity();
    checkSuspiciousActivity();
}

// Check for remember me cookie if not logged in
if (!isLoggedIn() && isset($_COOKIE['remember_me'])) {
    checkRememberMeCookie();
}

// Optional: Check for session timeout and logout if expired
if (isLoggedIn() && isSessionExpired()) {
    logoutUser();
    setFlashMessage('Your session has expired. Please log in again.', 'warning');
    
    // Only redirect if this is not an AJAX request
    if (!isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
        header("Location: login.php");
        exit();
    }
}

// Run cleanup periodically (1% chance per page load)
if (mt_rand(1, 100) === 1) {
    cleanupExpiredSessions();
}

?>