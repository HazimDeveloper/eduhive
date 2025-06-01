<?php
// config/session.php - Simple session management

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in, redirect to login if not
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

/**
 * Check if user is currently logged in
 * @return bool True if logged in, false otherwise
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['user_email']) && 
           !empty($_SESSION['user_id']);
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
 * @return bool True on success
 */
function loginUser($user) {
    if (empty($user) || !isset($user['id'])) {
        return false;
    }
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_name'] = $user['name'] ?? '';
    $_SESSION['user_role'] = $user['role'] ?? 'user';
    $_SESSION['login_time'] = time();
    
    return true;
}

/**
 * Logout user and destroy session
 */
function logoutUser() {
    // Clear all session variables
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
    
    // Redirect to login
    header("Location: login.php");
    exit();
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
 * Get current logged-in user data
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
        'login_time' => $_SESSION['login_time'] ?? null
    ];
}

/**
 * Set simple message for next page load
 * @param string $message Message text
 * @param string $type Message type (success, error, info)
 */
function setMessage($message, $type = 'info') {
    $_SESSION['message'] = [
        'text' => $message,
        'type' => $type
    ];
}

/**
 * Get and clear message
 * @return array|null Message data or null
 */
function getMessage() {
    if (isset($_SESSION['message'])) {
        $message = $_SESSION['message'];
        unset($_SESSION['message']);
        return $message;
    }
    return null;
}

/**
 * Check if there's a message
 * @return bool True if message exists
 */
function hasMessage() {
    return isset($_SESSION['message']);
}

?>