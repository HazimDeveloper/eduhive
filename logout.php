<?php
// auth/logout.php - Simple logout handler
require_once 'config/session.php';

// Check if user is logged in
if (isLoggedIn()) {
    // Clean up expired tokens before logout
    try {
        require_once 'config/functions.php';
        cleanupExpiredTokens();
    } catch (Exception $e) {
        // Ignore cleanup errors during logout
    }
    
    // Logout user (this will redirect to login.php)
    logoutUser();
} else {
    // User not logged in, redirect to login
    header("Location: login.php");
    exit();
}
?>