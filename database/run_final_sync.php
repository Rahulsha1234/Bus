<?php
/**
 * Database Schema Final Sync Script
 */
require_once __DIR__ . '/../config/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    echo "Connected successfully to " . DB_NAME . ".\n\n";

    // List of sync queries to run
    $queries = [
        // 1. Users Table Updates
        "ALTER TABLE users MODIFY COLUMN role ENUM('customer', 'agent', 'admin', 'super_admin') NOT NULL DEFAULT 'customer'",
        "ALTER TABLE users ADD COLUMN admin_id INT NULL",
        "ALTER TABLE users ADD FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL",

        // 2. Agent Profiles Table Updates
        "ALTER TABLE agent_profiles ADD COLUMN admin_id INT NULL",
        "ALTER TABLE agent_profiles ADD FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL",

        // 3. Create layout_templates if not exists
        "CREATE TABLE IF NOT EXISTS layout_templates (
            id INT AUTO_INCREMENT PRIMARY KEY,
            admin_id INT NOT NULL,
            template_name VARCHAR(100) NOT NULL,
            rows_count INT NOT NULL DEFAULT 8,
            cols_count INT NOT NULL DEFAULT 5,
            layout_type VARCHAR(50) NOT NULL DEFAULT 'Seater',
            seats_data LONGTEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",

        // 4. Buses Table Updates
        "ALTER TABLE buses ADD COLUMN admin_id INT NULL",
        "UPDATE buses SET admin_id = agent_id WHERE admin_id IS NULL",
        "ALTER TABLE buses ADD COLUMN discount_type ENUM('none', 'percentage', 'fixed') NOT NULL DEFAULT 'none'",
        "ALTER TABLE buses ADD COLUMN percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE buses ADD COLUMN fixed DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE buses ADD FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE",

        // 5. Routes Table Updates
        "ALTER TABLE routes ADD COLUMN admin_id INT NULL",
        "UPDATE routes SET admin_id = agent_id WHERE admin_id IS NULL",
        "ALTER TABLE routes ADD FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE",

        // 6. Trips Table Updates
        "ALTER TABLE trips ADD COLUMN admin_id INT NULL",
        "UPDATE trips t JOIN buses b ON t.bus_id = b.id SET t.admin_id = b.admin_id WHERE t.admin_id IS NULL",
        "ALTER TABLE trips ADD COLUMN discount_type ENUM('none','percentage','fixed') NOT NULL DEFAULT 'none'",
        "ALTER TABLE trips ADD COLUMN percentage DECIMAL(5,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE trips ADD COLUMN fixed DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE trips ADD FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE",

        // 7. Bookings Table Updates
        "ALTER TABLE bookings ADD COLUMN admin_id INT NULL",
        "ALTER TABLE bookings ADD COLUMN agent_id INT NULL",
        "ALTER TABLE bookings ADD COLUMN discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE bookings ADD COLUMN promo_code VARCHAR(50) NULL",
        "ALTER TABLE bookings ADD COLUMN booking_source ENUM('customer', 'agent', 'admin') NOT NULL DEFAULT 'customer'",
        "ALTER TABLE bookings ADD COLUMN original_fare DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE bookings ADD COLUMN discount_applied DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE bookings ADD COLUMN final_fare DECIMAL(10,2) NOT NULL DEFAULT 0.00",
        "ALTER TABLE bookings ADD FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE SET NULL",
        "ALTER TABLE bookings ADD FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE SET NULL",

        // 8. Performance Indexes
        "ALTER TABLE bookings ADD INDEX idx_bookings_admin_id (admin_id)",
        "ALTER TABLE trips ADD INDEX idx_trips_admin_id (admin_id)"
    ];

    foreach ($queries as $query) {
        $trimmed = trim(preg_replace('/\s+/', ' ', $query));
        $displayQuery = substr($trimmed, 0, 80) . "...";

        try {
            $pdo->exec($query);
            echo "SUCCESS: $displayQuery\n";
        } catch (PDOException $e) {
            $errorCode = $e->errorInfo[1] ?? 0;
            // 1060: Duplicate column name
            // 1061: Duplicate key name / index
            // 1050: Table already exists
            // 1091: Can't drop key / key doesn't exist
            // 1826: Duplicate foreign key constraint
            if (in_array($errorCode, [1050, 1060, 1061, 1826]) || strpos($e->getMessage(), 'already exists') !== false) {
                echo "IGNORED (Already applied): $displayQuery\n";
            } else {
                echo "FAILED: $trimmed\n";
                echo "  Error: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "\nSchema sync completed!\n";

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}
