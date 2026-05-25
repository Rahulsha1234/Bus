<?php
/**
 * Database Connection & Global Interceptors
 */
require_once __DIR__ . '/../config/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException $e) {
    // If database doesn't exist, try connecting without DB to show a helpful message
    try {
        $temp_pdo = new PDO("mysql:host=" . DB_HOST, DB_USER, DB_PASS);
        die("<div style='font-family:sans-serif; text-align:center; padding: 50px;'>
            <h2>Database connection successful, but 'bus_booking' database does not exist.</h2>
            <p>Please run the setup script to initialize the application:</p>
            <a href='" . BASE_URL . "/database/setup.php' style='display:inline-block; padding: 10px 20px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:5px;'>Run Database Setup</a>
        </div>");
    } catch (Exception $ex) {
        die("Database connection failed. Please ensure MySQL is running: " . $e->getMessage());
    }
}

// Fetch general system settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM system_settings");
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
} catch (PDOException $e) {
    // Settings table might not exist yet during initial setup
}

$maintenance_mode = $settings['maintenance_mode'] ?? '0';
$custom_notice = $settings['custom_notice'] ?? '';
$suspend_agent_panel = $settings['suspend_agent_panel'] ?? '0';

// Global variables for views
$GLOBALS['custom_notice'] = $custom_notice;

// Maintenance mode interception
$current_script = basename($_SERVER['SCRIPT_NAME']);
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';

if ($maintenance_mode === '1' && !$is_admin && $current_script !== 'maintenance.php' && $current_script !== 'login.php' && $current_script !== 'setup.php') {
    header("Location: " . BASE_URL . "/maintenance.php");
    exit();
}

// Agent panel suspension interception
if ($suspend_agent_panel === '1' && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'agent' && strpos($_SERVER['REQUEST_URI'], '/agent/') !== false && $current_script !== 'login.php' && $current_script !== 'logout.php') {
    // Agent is attempting to access agent pages while suspended
    session_unset();
    session_destroy();
    session_start();
    $_SESSION['login_error'] = "The Agent Panel is temporarily suspended by the Super Admin.";
    header("Location: " . BASE_URL . "/login.php");
    exit();
}
