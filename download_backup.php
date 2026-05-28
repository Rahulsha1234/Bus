<?php
/**
 * Temporary Database Backup Generator (No Auth required for migration purpose)
 * IMPORTANT: Delete this file after downloading your backup!
 */
require_once __DIR__ . '/includes/db.php';

try {
    $backup_name = 'bus_booking_migration_' . date('Y-m-d_H-i-s') . '.sql';
    
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $backup_name . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    echo "-- SwiftBus Migration Backup\n";
    echo "-- Generated on: " . date('Y-m-d H:i:s') . "\n\n";

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

    // Disable foreign key checks for clean structure rebuild
    echo "SET FOREIGN_KEY_CHECKS = 0;\n\n";

    foreach ($tables as $table) {
        // Check if table exists
        $check = $pdo->query("SHOW TABLES LIKE '$table'")->fetch();
        if (!$check) continue;

        echo "DROP TABLE IF EXISTS `$table`;\n";
        
        $create_stmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch(PDO::FETCH_NUM);
        echo $create_stmt[1] . ";\n\n";

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
                        $values[] = $pdo->quote($val);
                    }
                }
                $row_strings[] = "(" . implode(", ", $values) . ")";
            }
            echo implode(",\n", $row_strings) . ";\n\n";
        }
    }

    echo "SET FOREIGN_KEY_CHECKS = 1;\n";
    exit();

} catch (Exception $e) {
    header('Content-Type: text/html');
    die("Migration backup generation failed: " . $e->getMessage());
}
