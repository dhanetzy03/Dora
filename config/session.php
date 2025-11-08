<?php
/**
 * Session Management
 * Shukran Café Inventory Tracking System
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is logged in
 */
function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data
 */
function get_current_user() {
    if (!is_logged_in()) {
        return null;
    }
    
    return [
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'full_name' => $_SESSION['full_name'] ?? null,
        'role' => $_SESSION['role'] ?? null
    ];
}

/**
 * Set user session
 */
function set_user_session($user_data) {
    $_SESSION['user_id'] = $user_data['user_id'];
    $_SESSION['username'] = $user_data['username'];
    $_SESSION['full_name'] = $user_data['full_name'];
    $_SESSION['role'] = $user_data['role'];
    $_SESSION['login_time'] = time();
}

/**
 * Destroy user session (logout)
 */
function destroy_user_session() {
    session_unset();
    session_destroy();
}

/**
 * Check if user is admin
 */
function is_admin() {
    return is_logged_in() && $_SESSION['role'] === 'admin';
}

/**
 * Check if user is staff
 */
function is_staff() {
    return is_logged_in() && $_SESSION['role'] === 'staff';
}

/**
 * Require login - redirect if not logged in
 */
function require_login($redirect = '/src/auth/login.php') {
    if (!is_logged_in()) {
        header('Location: ' . $redirect);
        exit();
    }
}

/**
 * Require admin - redirect if not admin
 */
function require_admin($redirect = '/src/dashboard/staff.php') {
    require_login();
    
    if (!is_admin()) {
        header('Location: ' . $redirect);
        exit();
    }
}

/**
 * Set flash message
 */
function set_flash_message($type, $message) {
    $_SESSION['flash_message'] = [
        'type' => $type, // success, error, warning, info
        'message' => $message
    ];
}

/**
 * Get and clear flash message
 */
function get_flash_message() {
    if (isset($_SESSION['flash_message'])) {
        $message = $_SESSION['flash_message'];
        unset($_SESSION['flash_message']);
        return $message;
    }
    return null;
}

/**
 * CSRF Token Generation and Validation
 */
function generate_csrf_token() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Check session timeout (30 minutes)
 */
function check_session_timeout($timeout = 1800) {
    if (is_logged_in()) {
        $last_activity = $_SESSION['login_time'] ?? time();
        
        if ((time() - $last_activity) > $timeout) {
            destroy_user_session();
            return false;
        }
        
        $_SESSION['login_time'] = time();
    }
    
    return true;
}
?>
