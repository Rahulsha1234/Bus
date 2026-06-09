<?php
/**
 * Run GST Migrations
 */
require_once __DIR__ . '/../config/config.php';

try {
    $dsn = "mysql:host=127.0.0.1;port=3306;dbname=bus_booking;charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Connected successfully to " . DB_NAME . ".\n";
    
    $migration_file = __DIR__ . '/002_add_gst_fields.sql';
    if (!file_exists($migration_file)) {
        die("Migration file not found: $migration_file\n");
    }
    
    $sql = file_get_contents($migration_file);
    
    // Check if fields already exist
    $chk = $pdo->query("SHOW COLUMNS FROM bookings LIKE 'base_fare'");
    if ($chk->rowCount() > 0) {
        echo "GST fields seem to already exist in bookings table. Executing system_settings configuration seeds and cancellations check...\n";
        
        // Seed default system settings
        $pdo->exec("INSERT INTO system_settings (setting_key, setting_value) VALUES 
        ('gst_rate', '5.00'),
        ('gst_status', '1'),
        ('gst_name', 'GST'),
        ('gst_effective_date', CURDATE())
        ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        
        // Check if cancellations fields exist, if not add them
        $chk_c = $pdo->query("SHOW COLUMNS FROM cancellation_requests LIKE 'refund_base_fare'");
        if ($chk_c->rowCount() == 0) {
            $pdo->exec("ALTER TABLE cancellation_requests ADD COLUMN refund_base_fare DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                        ADD COLUMN refund_gst DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                        ADD COLUMN total_refund DECIMAL(10,2) NOT NULL DEFAULT 0.00");
        }
        echo "Checks and seeds completed successfully!\n";
    } else {
        $pdo->exec($sql);
        echo "GST migration completed successfully!\n";
    }
    
} catch (PDOException $e) {
    die("Database migration failed: " . $e->getMessage() . "\n");
}
