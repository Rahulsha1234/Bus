<?php
require_once __DIR__ . '/config/config.php';

echo "<h2 style='font-family:sans-serif;'>Collation Fix Tool</h2>";

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
    echo "<p style='color:green;'>✓ DB Connected</p>";

    // We will alter the collation of seat_number in the tables to utf8mb4_general_ci to match bus_seats
    $queries = [
        "ALTER TABLE bus_seats MODIFY seat_number VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL",
        "ALTER TABLE trip_seats MODIFY seat_number VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL",
        "ALTER TABLE seat_pricing MODIFY seat_number VARCHAR(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci NOT NULL"
    ];

    foreach ($queries as $sql) {
        try {
            $pdo->exec($sql);
            echo "<p style='color:green;'>✓ Executed: $sql</p>";
        } catch (PDOException $e) {
            echo "<p style='color:red;'>✗ Failed: $sql. Error: " . $e->getMessage() . "</p>";
        }
    }

    echo "<p>All done! Try refreshing your page now.</p>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Connection failed: " . $e->getMessage() . "</p>";
}
