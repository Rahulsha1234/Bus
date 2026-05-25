<?php
/**
 * Security Middleware and Security Utility Functions
 */
require_once __DIR__ . '/config.php';

// Generate CSRF Token
if (!function_exists('get_csrf_token')) {
    function get_csrf_token() {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

// Verify CSRF Token
if (!function_exists('verify_csrf_token')) {
    function verify_csrf_token($token) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }
}

// Sanitize Output against XSS
if (!function_exists('sanitize')) {
    function sanitize($data) {
        return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
    }
}

// Check Role access (Middleware)
if (!function_exists('require_role')) {
    function require_role($allowed_roles) {
        if (session_status() == PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
            $_SESSION['login_error'] = "Please log in to access this page.";
            header("Location: " . BASE_URL . "/login.php");
            exit();
        }

        if (is_array($allowed_roles)) {
            if (!in_array($_SESSION['user_role'], $allowed_roles)) {
                die("Access Denied: You do not have the required permissions to view this resource.");
            }
        } else {
            if ($_SESSION['user_role'] !== $allowed_roles) {
                die("Access Denied: You do not have the required permissions to view this resource.");
            }
        }
    }
}

// Activity Logging
if (!function_exists('log_activity')) {
    function log_activity($pdo, $user_id, $action_type, $details) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $stmt = $pdo->prepare("
                INSERT INTO activity_logs (user_id, action_type, details, ip_address) 
                VALUES (:user_id, :action_type, :details, :ip)
            ");
            $stmt->execute([
                ':user_id' => $user_id,
                ':action_type' => $action_type,
                ':details' => $details,
                ':ip' => $ip
            ]);
        } catch (Exception $e) {
            // Silently fail logging to avoid breaking user flows
        }
    }
}
