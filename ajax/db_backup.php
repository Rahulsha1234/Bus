<?php
/**
 * Safe Database SQL Backup Generator & Downloader
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config/security.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Authenticate Admin Role
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Access Denied: You do not have permissions to execute database backups.");
}

try {
    // Generate script header
    $backup_name = 'bus_booking_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    // Set download attachment headers
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backup_name . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "-- SwiftBus Relational Database SQL Backup\n";
    echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
    echo "-- Database Host: " . DB_HOST . "\n";
    echo "-- Database Name: " . DB_NAME . "\n";
    echo "--------------------------------------------------------\n\n";
    echo "CREATE DATABASE IF NOT EXISTS " . DB_NAME . ";\n";
    echo "USE " . DB_NAME . ";\n\n";

    // List tables in correct dependency order
    $tables = [
        'users',
        'agent_profiles',
        'buses',
        'routes',
        'trips',
        'trip_seats',
        'bookings',
        'booking_seats',
        'weekly_settlements',
        'system_settings',
        'activity_logs'
    ];

    foreach ($tables as $table) {
        echo "-- --------------------------------------------------------\n";
        echo "-- Table structure for table `$table`\n";
        echo "-- --------------------------------------------------------\n";
        echo "DROP TABLE IF EXISTS `$table`;\n";
        
        // Get Create Table statement
        $create_stmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        echo $create_stmt[1] . ";\n\n";

        echo "-- Dumping data for table `$table`\n";
        
        // Get all rows
        $rows_stmt = $pdo->query("SELECT * FROM `$table`");
        $rows = $rows_stmt->fetchAll(PDO::FETCH_NUM);

        if (count($rows) > 0) {
            echo "INSERT INTO `$table` VALUES \n";
            $row_strings = [];
            foreach ($rows as $row) {
                $values = [];
                foreach ($row as $val) {
                    if ($val === null) {
                        $values[] = "NULL";
                    } else {
                        // Secure escaping for SQL string literal injection prevention
                        $values[] = $pdo->quote($val);
                    }
                }
                $row_strings[] = "(" . implode(", ", $values) . ")";
            }
            echo implode(",\n", $row_strings) . ";\n\n";
        } else {
            echo "-- (No records found)\n\n";
        }
    }

    log_activity($pdo, $_SESSION['user_id'], 'OWNER_DB_BACKUP', "Downloaded database backup: $backup_name");
    exit();

} catch (Exception $e) {
    header('Content-Type: text/html');
    die("Database backup generation failed: " . $e->getMessage());
}
