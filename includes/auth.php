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
        'id'            => $_SESSION['user_id'],
        'role'          => $_SESSION['role'] ?? 'tourist',
        'name'          => $_SESSION['name'] ?? '',
        'email'         => $_SESSION['email'] ?? '',
        'date_of_birth' => $_SESSION['date_of_birth'] ?? null,
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

    // Force password change for local providers on any page, not just dashboard
    if ($requiredRole === 'local' && !empty($_SESSION['must_change_password'])) {
        $currentPage = basename($_SERVER['PHP_SELF']);
        if ($currentPage !== 'dashboard.php') {
            header('Location: /doon-app/local/dashboard.php');
            exit;
        }
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
    $_SESSION['user_id']              = $user['id'];
    $_SESSION['role']                 = $user['role'];
    $_SESSION['name']                 = $user['name'];
    $_SESSION['email']                = $user['email'];
    $_SESSION['date_of_birth']        = $user['date_of_birth'] ?? null;
    $_SESSION['must_change_password'] = (int) ($user['must_change_password'] ?? 0);
}

function dobToGenerationalProfile($dob) {
    if (!$dob) return null;
    $year = (int) date('Y', strtotime($dob));
    if ($year >= 1997) return 'gen_z';
    if ($year >= 1981) return 'millennial';
    if ($year >= 1965) return 'gen_x';
    if ($year >= 1946) return 'boomer';
    return null;
}

function calcAge($dob) {
    if (!$dob) return null;
    return (int) date_diff(date_create($dob), date_create('today'))->y;
}

/**
 * Escape HTML output for security
 * @param string $string
 * @return string
 */
function escape($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}
