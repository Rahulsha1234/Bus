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
if (!function_exists('calculate_dynamic_pricing')) {
    function calculate_dynamic_pricing($pdo, $trip_id, $base_fare) {
        $base_fare = floatval($base_fare);
        
        try {
            // 1. Get Trip Operator and Dates
            $trip_stmt = $pdo->prepare("SELECT bus_id, admin_id, departure_time FROM trips WHERE id = ? LIMIT 1");
            $trip_stmt->execute([$trip_id]);
            $trip = $trip_stmt->fetch();
            if (!$trip) {
                return [
                    'occupancy_percent' => 0,
                    'occupancy_increase_pct' => 0,
                    'time_increase_pct' => 0,
                    'occupancy_adjustment' => 0,
                    'time_adjustment' => 0,
                    'final_price' => $base_fare
                ];
            }
            
            $operator_id = intval($trip['admin_id']);
            $bus_id = intval($trip['bus_id']);
            $dep_time_str = $trip['departure_time'];

            // 2. Fetch active settings for the operator (fallback to global settings)
            $sett_stmt = $pdo->prepare("
                SELECT enable_dynamic_pricing, dynamic_pricing_mode 
                FROM global_pricing_settings 
                WHERE operator_id = ? OR operator_id IS NULL 
                ORDER BY operator_id DESC 
                LIMIT 1
            ");
            $sett_stmt->execute([$operator_id]);
            $settings = $sett_stmt->fetch();
            
            $enabled = $settings ? intval($settings['enable_dynamic_pricing']) : 1;
            $mode = $settings ? $settings['dynamic_pricing_mode'] : 'custom';
            
            if (!$enabled) {
                return [
                    'occupancy_percent' => 0,
                    'occupancy_increase_pct' => 0,
                    'time_increase_pct' => 0,
                    'occupancy_adjustment' => 0,
                    'time_adjustment' => 0,
                    'final_price' => $base_fare
                ];
            }

            // 3. Calculate Occupancy %
            $total_seats = intval($pdo->query("SELECT COUNT(*) FROM bus_seats WHERE bus_id = $bus_id AND is_active = 1")->fetchColumn());
            if ($total_seats <= 0) {
                $total_seats = intval($pdo->query("SELECT total_seats FROM buses WHERE id = $bus_id")->fetchColumn());
            }
            if ($total_seats <= 0) $total_seats = 40; // absolute fallback
            
            $booked_stmt = $pdo->prepare("SELECT COUNT(*) FROM trip_seats WHERE trip_id = ? AND status = 'booked'");
            $booked_stmt->execute([$trip_id]);
            $booked_seats = intval($booked_stmt->fetchColumn());
            
            $occupancy_percent = round(($booked_seats / $total_seats) * 100, 2);

            // 4. Calculate Time Remaining
            $now = time();
            $dep_time = strtotime($dep_time_str);
            $diff_seconds = $dep_time - $now;
            $days_remaining = ($diff_seconds > 0) ? floor($diff_seconds / 86400) : 0;

            // 5. Determine rules based on Mode
            $occ_increase_pct = 0;
            $time_increase_pct = 0;

            if ($mode === 'conservative') {
                // Occupancy rules: 0-50% -> 0%, 51-80% -> 5%, 81-90% -> 10%, 91-100% -> 15%
                if ($occupancy_percent > 90) $occ_increase_pct = 15;
                elseif ($occupancy_percent > 80) $occ_increase_pct = 10;
                elseif ($occupancy_percent > 50) $occ_increase_pct = 5;
                
                // Time rules: >7 days -> 0%, 3-7 days -> 5%, <3 days -> 10%
                if ($days_remaining < 3) $time_increase_pct = 10;
                elseif ($days_remaining <= 7) $time_increase_pct = 5;
            } elseif ($mode === 'balanced') {
                // Occupancy rules: 0-50% -> 0%, 51-70% -> 10%, 71-85% -> 20%, 86-95% -> 35%, 96-100% -> 50%
                if ($occupancy_percent > 95) $occ_increase_pct = 50;
                elseif ($occupancy_percent > 85) $occ_increase_pct = 35;
                elseif ($occupancy_percent > 70) $occ_increase_pct = 20;
                elseif ($occupancy_percent > 50) $occ_increase_pct = 10;
                
                // Time rules: >7 days -> 0%, 3-7 days -> 10%, 1-2 days -> 20%, <24h (0 days) -> 30%
                if ($days_remaining == 0) $time_increase_pct = 30;
                elseif ($days_remaining <= 2) $time_increase_pct = 20;
                elseif ($days_remaining <= 7) $time_increase_pct = 10;
            } elseif ($mode === 'aggressive') {
                // Occupancy rules: 0-50% -> 0%, 51-60% -> 15%, 61-80% -> 30%, 81-90% -> 50%, 91-100% -> 75%
                if ($occupancy_percent > 90) $occ_increase_pct = 75;
                elseif ($occupancy_percent > 80) $occ_increase_pct = 50;
                elseif ($occupancy_percent > 60) $occ_increase_pct = 30;
                elseif ($occupancy_percent > 50) $occ_increase_pct = 15;
                
                // Time rules: >7 days -> 0%, 3-7 days -> 15%, 1-2 days -> 30%, <24h (0 days) -> 50%
                if ($days_remaining == 0) $time_increase_pct = 50;
                elseif ($days_remaining <= 2) $time_increase_pct = 30;
                elseif ($days_remaining <= 7) $time_increase_pct = 15;
            } else {
                // custom mode: Fetch rules from DB
                // Occupancy rule matching
                $occ_rules_stmt = $pdo->prepare("
                    SELECT min_occupancy, max_occupancy, price_increase_percentage 
                    FROM occupancy_pricing_rules 
                    WHERE (operator_id = ? OR operator_id IS NULL) AND status = 'active'
                    ORDER BY operator_id DESC, sort_order ASC
                ");
                $occ_rules_stmt->execute([$operator_id]);
                $occ_rules = $occ_rules_stmt->fetchAll();
                
                // Check if operator specific exists, if not use global
                $operator_has_rules = false;
                foreach ($occ_rules as $r) {
                    if ($r['min_occupancy'] !== null && $occupancy_percent >= floatval($r['min_occupancy']) && $occupancy_percent <= floatval($r['max_occupancy'])) {
                        $occ_increase_pct = floatval($r['price_increase_percentage']);
                        break;
                    }
                }

                // Time rule matching
                $time_rules_stmt = $pdo->prepare("
                    SELECT min_days, max_days, price_increase_percentage 
                    FROM time_pricing_rules 
                    WHERE (operator_id = ? OR operator_id IS NULL) AND status = 'active'
                    ORDER BY operator_id DESC, sort_order ASC
                ");
                $time_rules_stmt->execute([$operator_id]);
                $time_rules = $time_rules_stmt->fetchAll();
                
                foreach ($time_rules as $r) {
                    if ($days_remaining >= intval($r['min_days']) && $days_remaining <= intval($r['max_days'])) {
                        $time_increase_pct = floatval($r['price_increase_percentage']);
                        break;
                    }
                }
            }

            // Calculations
            $occupancy_adjustment = round(($base_fare * $occ_increase_pct) / 100, 2);
            $time_adjustment = round(($base_fare * $time_increase_pct) / 100, 2);
            $final_price = $base_fare + $occupancy_adjustment + $time_adjustment;

            return [
                'occupancy_percent' => $occupancy_percent,
                'occupancy_increase_pct' => $occ_increase_pct,
                'time_increase_pct' => $time_increase_pct,
                'occupancy_adjustment' => $occupancy_adjustment,
                'time_adjustment' => $time_adjustment,
                'final_price' => $final_price
            ];

        } catch (Exception $e) {
            error_log("Dynamic pricing engine exception: " . $e->getMessage());
            return [
                'occupancy_percent' => 0,
                'occupancy_increase_pct' => 0,
                'time_increase_pct' => 0,
                'occupancy_adjustment' => 0,
                'time_adjustment' => 0,
                'final_price' => $base_fare
            ];
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

        // 3. Fallback to Dynamic Pricing Engine calculations
        $pricing = calculate_dynamic_pricing($pdo, $trip_id, $trip_base_fare);
        return $pricing['final_price'];
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

// Single source of truth for vehicle classifications
if (!function_exists('get_vehicle_classifications')) {
    function get_vehicle_classifications() {
        return [
            'Sleeper' => [
                'display' => 'Full Sleeper Layout',
                'layout' => 'Sleeper'
            ],
            'Seater' => [
                'display' => 'Full Seater Layout',
                'layout' => 'Seater'
            ],
            'Mixed' => [
                'display' => 'Mixed Layout',
                'layout' => 'Mixed'
            ]
        ];
    }
}
