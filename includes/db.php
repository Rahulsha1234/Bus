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
    error_log("Database connection failed securely: " . $e->getMessage());
    $host_header = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $is_local = (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1']) 
        || $host_header === 'localhost' 
        || strpos($host_header, '127.0.0.1') !== false);
    if ($is_local) {
        die("Database connection failed. Please check your credentials in config/config.php and ensure MySQL is running: " . htmlspecialchars($e->getMessage()));
    } else {
        die("Database connection failed. Please contact the administrator. The error has been logged securely.");
    }
}



// Verify that the logged-in user exists in the database to prevent stale sessions after database reset
if (isset($_SESSION['user_id'])) {
    try {
        $auth_check = $pdo->prepare("SELECT 1 FROM users WHERE id = ? LIMIT 1");
        $auth_check->execute([$_SESSION['user_id']]);
        if (!$auth_check->fetchColumn()) {
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['login_error'] = "Your session is invalid (database reset). Please log in again.";
            header("Location: " . BASE_URL . "/login.php");
            exit();
        }
    } catch (PDOException $e) {
        // Table 'users' might not exist yet during setup
    }
}

// Automatically mark completed trips based on arrival time and self-heal empty statuses
try {
    // 1. Convert any empty/NULL statuses of future trips to ACTIVE
    $pdo->exec("UPDATE trips SET status = 'ACTIVE' WHERE (status IS NULL OR status = '' OR status = '-') AND arrival_time >= NOW()");
    // 2. Mark past trips as COMPLETED (excluding CANCELLED)
    $pdo->exec("UPDATE trips SET status = 'COMPLETED' WHERE (status IS NULL OR status = '' OR status = '-' OR status IN ('ACTIVE', 'active')) AND arrival_time < NOW()");
} catch (PDOException $e) {
    // Table 'trips' might not exist yet during initial setup
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
$is_admin = isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin';

if ($maintenance_mode === '1' && !$is_admin && $current_script !== 'maintenance.php' && $current_script !== 'login.php') {
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
