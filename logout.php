<?php
/**
 * Logout Controller
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/security.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['user_id'])) {
    log_activity($pdo, $_SESSION['user_id'], 'LOGOUT', 'User signed out manually.');
}

// Unset all session variables
$_SESSION = [];

// Destroy session cookies if any
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Restart session for redirect feedback message
session_start();
$_SESSION['timeout_message'] = "You have been logged out successfully.";

header("Location: " . BASE_URL . "/login.php");
exit();
