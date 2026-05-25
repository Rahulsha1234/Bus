<?php
/**
 * Global Configuration Settings
 */

// Define Base URL dynamically
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_dir = '/bus'; // Set matching folder inside www
    define('BASE_URL', $protocol . '://' . $host . $base_dir);
}

// Database Credentials
define('DB_HOST', '127.0.0.1;port=3307');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bus_booking');

// System Settings
define('SYSTEM_NAME', 'SwiftBus');
define('CURRENCY', '₹');
define('COMMISSION_RATE', 2.00); // 2% commission to Super Admin

// Session Configuration (Strict Security)
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);

    // Enable secure cookies if HTTPS
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }

    session_start();
}

// Session Idle Timeout (30 Minutes)
$timeout_duration = 1800; // 30 mins
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > $timeout_duration)) {
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['timeout_message'] = "Your session has expired due to inactivity. Please log in again.";
}
$_SESSION['LAST_ACTIVITY'] = time();
