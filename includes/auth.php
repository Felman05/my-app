<?php
/**
 * Authentication & Authorization
 * Session management and role-based access control
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Check if user is authenticated
 * @return bool
 */
function isAuthenticated() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Get current user data from session
 * @return array|null
 */
function getCurrentUser() {
    if (!isAuthenticated()) {
        return null;
    }
    return [
        'id'   => $_SESSION['user_id'],
        'role' => $_SESSION['role'] ?? 'tourist',
        'name' => $_SESSION['name'] ?? '',
        'email' => $_SESSION['email'] ?? ''
    ];
}

/**
 * Require specific role, redirect to login if not authenticated or authorized
 * @param string $requiredRole 'tourist', 'local', or 'admin'
 */
function requireRole($requiredRole) {
    if (!isAuthenticated()) {
        header('Location: /doon-app/login.php');
        exit;
    }

    $userRole = $_SESSION['role'] ?? 'tourist';
    if ($userRole !== $requiredRole && $userRole !== 'admin') {
        header('HTTP/1.0 403 Forbidden');
        die('Access denied.');
    }
}

/**
 * Logout user and destroy session
 */
function logout() {
    session_destroy();
    header('Location: /doon-app/index.php');
    exit;
}

/**
 * Hash password using bcrypt
 * @param string $password
 * @return string
 */
function hashPassword($password) {
    return password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
}

/**
 * Verify password against hash
 * @param string $password
 * @param string $hash
 * @return bool
 */
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

/**
 * Set user session
 * @param array $user User data from database
 */
function setUserSession($user) {
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['name'];
    $_SESSION['email']   = $user['email'];
}

/**
 * Escape HTML output for security
 * @param string $string
 * @return string
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
