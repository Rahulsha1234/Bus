<?php
/**
 * Global Configuration Settings
 */

// Production Error Handling (Suppress raw paths/errors, keep server logs active)
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);

// Define Base URL dynamically
if (!defined('BASE_URL')) {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Auto detect if running in a subdirectory or direct htdocs root
    $script_dir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME']));
    $base_dir = ($script_dir === '/' || $script_dir === '\\') ? '' : rtrim($script_dir, '/');
    
    // If inside portal folders, strip them from base url detection
    $base_dir = preg_replace('/\/(admin|super_admin|agent|ajax|includes|config)$/', '', $base_dir);
    
    define('BASE_URL', $protocol . '://' . $host . $base_dir);
}

// Database Credentials (Dynamic configuration for Local WAMP and Live Server)
$host = $_SERVER['HTTP_HOST'] ?? '';
$is_local = php_sapi_name() === 'cli' || ((in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) 
    || $host === 'localhost' 
    || strpos($host, '127.0.0.1') !== false)
    && strpos($host, 'byethost') === false);


if ($is_local) {
    define('DB_HOST', '127.0.0.1;port=3307');
    define('DB_USER', 'root');
    define('DB_PASS', '');
    define('DB_NAME', 'bus_booking');
} else {
    define('DB_HOST', 'sql311.byethost22.com');
    define('DB_USER', 'b22_42038100');
    define('DB_PASS', '123456789');
    define('DB_NAME', 'b22_42038100_bus');
}

// System Settings
define('SYSTEM_NAME', 'SwiftBus');
define('CURRENCY', '₹');
define('COMMISSION_RATE', 2.00); // 2% commission to Super Admin

// Session Configuration (Strict Security)
if (session_status() == PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);

    // Detect secure connection (accounting for proxies/load balancers)
    $is_secure = false;
    if (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] == 1)) {
        $is_secure = true;
    } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        $is_secure = true;
    }

    // Set SameSite=Lax for compatibility with shared subdomains
    if (PHP_VERSION_ID >= 70300) {
        session_set_cookie_params([
            'path' => '/',
            'secure' => $is_secure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    } else {
        // Fallback for PHP versions older than 7.3
        session_set_cookie_params(0, '/; SameSite=Lax', null, $is_secure, true);
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
