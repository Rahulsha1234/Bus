<?php
/**
 * Temporary Database Verification Script
 */
require_once __DIR__ . '/config/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    // Expected tables inside database
    $expected_tables = [
        'users', 'agent_profiles', 'buses', 'routes', 'boarding_points', 
        'dropping_points', 'trips', 'trip_seats', 'bookings', 'booking_seats', 
        'cancellation_requests', 'weekly_settlements', 'system_settings', 
        'system_notifications', 'activity_logs', 'operator_contacts', 
        'bus_layouts', 'bus_seats', 'seat_pricing', 'seat_holds', 'layout_templates'
    ];

    // Fetch existing tables from DB
    $stmt = $pdo->query("SHOW TABLES");
    $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<h2 style='font-family:sans-serif;'>SwiftBus Database Verification Panel</h2>";
    echo "<p style='font-family:sans-serif;'>Checking tables in: <b>" . DB_NAME . "</b> (" . DB_HOST . ")</p>";
    echo "<table border='1' cellpadding='10' cellspacing='0' style='font-family:sans-serif; border-collapse:collapse;'>";
    echo "<tr bgcolor='#f2f2f2'><th>Table Name</th><th>Status</th></tr>";

    $missing_count = 0;
    foreach ($expected_tables as $table) {
        $status = in_array($table, $existing_tables) 
            ? "<span style='color:green; font-weight:bold;'>✓ Present</span>" 
            : "<span style='color:red; font-weight:bold;'>✗ Missing</span>";
        
        if (!in_array($table, $existing_tables)) {
            $missing_count++;
        }

        echo "<tr><td>$table</td><td>$status</td></tr>";
    }
    echo "</table>";

    if ($missing_count === 0) {
        echo "<h3 style='color:green; font-family:sans-serif;'>Success: All " . count($expected_tables) . " tables are present in the database! Ready to deploy.</h3>";
    } else {
        echo "<h3 style='color:red; font-family:sans-serif;'>Alert: $missing_count tables are missing. Please import schema.sql to update.</h3>";
    }

} catch (PDOException $e) {
    echo "<h3 style='color:red;'>Connection Error: " . $e->getMessage() . "</h3>";
}
