<?php
require_once __DIR__ . '/config/config.php';

echo "<h2 style='font-family:sans-serif;'>Bus Admin-ID Fix</h2>";

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "<p style='color:green;'>✓ DB Connected</p>";

    // Step 1: Fix buses that have admin_id = 0 but agent_id is set
    $fixed1 = $pdo->exec("UPDATE buses SET admin_id = agent_id WHERE admin_id = 0 AND agent_id > 0");
    echo "<p style='color:green;'>✓ Fixed $fixed1 buses: copied agent_id → admin_id</p>";

    // Step 2: Any remaining buses with admin_id = 0, assign to the only admin (aslitravels = id 2)
    $fixed2 = $pdo->exec("UPDATE buses SET admin_id = 2 WHERE admin_id = 0");
    echo "<p style='color:green;'>✓ Fixed $fixed2 remaining buses: assigned to admin_id = 2</p>";

    // Verify
    echo "<h3>Buses after fix:</h3>";
    $all = $pdo->query("SELECT id, admin_id, bus_name, bus_number, status FROM buses")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='6' style='font-family:monospace;'>";
    echo "<tr><th>id</th><th>admin_id</th><th>bus_name</th><th>bus_number</th><th>status</th></tr>";
    foreach ($all as $row) {
        echo "<tr><td>{$row['id']}</td><td>{$row['admin_id']}</td><td>{$row['bus_name']}</td><td>{$row['bus_number']}</td><td>{$row['status']}</td></tr>";
    }
    echo "</table>";

    echo "<h3 style='color:green;'>Done! Delete this file now.</h3>";

} catch (PDOException $e) {
    echo "<p style='color:red;'>Error: " . $e->getMessage() . "</p>";
}
?>
