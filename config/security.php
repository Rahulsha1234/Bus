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

// Auto create overrides and blocks tables if they don't exist
if (!function_exists('ensure_refactor_tables_exist')) {
    function ensure_refactor_tables_exist($pdo) {
        static $run = false;
        if ($run) return;
        try {
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS seat_price_overrides (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    trip_id INT NOT NULL,
                    seat_number VARCHAR(20) NOT NULL,
                    custom_price DECIMAL(10,2) NOT NULL,
                    updated_by INT NOT NULL,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
                    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_trip_seat_override (trip_id, seat_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS seat_blocks (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    trip_id INT NOT NULL,
                    seat_number VARCHAR(20) NOT NULL,
                    blocked_by INT NOT NULL,
                    blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
                    FOREIGN KEY (blocked_by) REFERENCES users(id) ON DELETE CASCADE,
                    UNIQUE KEY unique_trip_seat_block (trip_id, seat_number)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ");
            $run = true;
        } catch (Exception $e) {
            // fail silently
        }
    }
}

// Get dynamic resolved seat price using override hierarchy
if (!function_exists('get_actual_seat_price')) {
    function get_actual_seat_price($pdo, $trip_id, $seat_number, $trip_base_fare) {
        try {
            ensure_refactor_tables_exist($pdo);
            // 1. Check seat_price_overrides
            $stmt = $pdo->prepare("SELECT custom_price FROM seat_price_overrides WHERE trip_id = ? AND seat_number = ? LIMIT 1");
            $stmt->execute([$trip_id, $seat_number]);
            $price = $stmt->fetchColumn();
            if ($price !== false && $price !== null) {
                return floatval($price);
            }
            
            // 2. Check seat_pricing (legacy)
            $stmt = $pdo->prepare("SELECT current_price FROM seat_pricing WHERE trip_id = ? AND seat_number = ? LIMIT 1");
            $stmt->execute([$trip_id, $seat_number]);
            $price = $stmt->fetchColumn();
            if ($price !== false && $price !== null) {
                return floatval($price);
            }
        } catch (Exception $e) {
            // fallback if tables don't exist yet
        }

        // 3. Fallback to trip base_fare
        return floatval($trip_base_fare);
    }
}

// Check if seat is blocked either in seat_blocks or trip_seats status
if (!function_exists('is_seat_blocked')) {
    function is_seat_blocked($pdo, $trip_id, $seat_number) {
        try {
            ensure_refactor_tables_exist($pdo);
            // 1. Check seat_blocks
            $stmt = $pdo->prepare("SELECT 1 FROM seat_blocks WHERE trip_id = ? AND seat_number = ? LIMIT 1");
            $stmt->execute([$trip_id, $seat_number]);
            if ($stmt->fetchColumn()) {
                return true;
            }
            
            // 2. Check trip_seats status
            $stmt = $pdo->prepare("SELECT status FROM trip_seats WHERE trip_id = ? AND seat_number = ? LIMIT 1");
            $stmt->execute([$trip_id, $seat_number]);
            $status = $stmt->fetchColumn();
            if ($status === 'blocked') {
                return true;
            }
        } catch (Exception $e) {
            // fallback
        }

        return false;
    }
}
