<?php
/**
 * Authentication API Endpoint
 * Handles logout action
 */

require_once '../includes/db.php';
require_once '../includes/auth.php';
require_once '../includes/functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'logout':
        logout(); // This redirects, so we won't reach here
        break;

    case 'check':
        // Check if user is authenticated
        if (isAuthenticated()) {
            echo json_encode(['success' => true, 'user' => getCurrentUser()]);
        } else {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>
