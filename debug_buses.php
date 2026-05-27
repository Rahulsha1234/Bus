<?php
require_once __DIR__ . '/config/config.php';

echo "<h2 style='font-family:sans-serif;'>Bus Debug Tool</h2>";

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    echo "<p style='color:green;'>✓ DB Connected</p>";

    // Show ALL buses (no filter)
    echo "<h3>All rows in buses table (no filter):</h3>";
    $all = $pdo->query("SELECT id, admin_id, bus_name, bus_number, bus_type, status FROM buses")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($all)) {
        echo "<p style='color:orange;'>No buses found at all in the table.</p>";
    } else {
        echo "<table border='1' cellpadding='6' style='font-family:monospace;'>";
        echo "<tr><th>id</th><th>admin_id</th><th>bus_name</th><th>bus_number</th><th>bus_type</th><th>status</th></tr>";
        foreach ($all as $row) {
            echo "<tr><td>{$row['id']}</td><td>{$row['admin_id']}</td><td>{$row['bus_name']}</td><td>{$row['bus_number']}</td><td>{$row['bus_type']}</td><td>{$row['status']}</td></tr>";
        }
        echo "</table>";
    }

    // Show columns of buses table
    echo "<h3>Columns in buses table:</h3>";
    $cols = $pdo->query("SHOW COLUMNS FROM buses")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='6' style='font-family:monospace;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
    foreach ($cols as $col) {
        echo "<tr><td>{$col['Field']}</td><td>{$col['Type']}</td><td>{$col['Null']}</td><td>{$col['Default']}</td></tr>";
    }
    echo "</table>";

    // Show all users with role=admin
    echo "<h3>Users with role = admin or superadmin:</h3>";
    $users = $pdo->query("SELECT id, username, role FROM users WHERE role IN ('admin','superadmin')")->fetchAll(PDO::FETCH_ASSOC);
    echo "<table border='1' cellpadding='6' style='font-family:monospace;'>";
    echo "<tr><th>id</th><th>username</th><th>role</th></tr>";
    foreach ($users as $u) {
        echo "<tr><td>{$u['id']}</td><td>{$u['username']}</td><td>{$u['role']}</td></tr>";
    }
    echo "</table>";

    // Try the exact query buses.php uses (admin_id = 2 as example)
    echo "<h3>Test Query with admin_id = 2:</h3>";
    try {
        $stmt = $pdo->prepare("SELECT b.id, b.bus_name, b.bus_number, b.status, b.admin_id FROM buses b WHERE b.admin_id = ? AND b.status = 'active' ORDER BY b.id DESC");
        $stmt->execute([2]);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (empty($result)) {
            echo "<p style='color:orange;'>No active buses found for admin_id = 2</p>";
        } else {
            echo "<table border='1' cellpadding='6' style='font-family:monospace;'>";
            foreach ($result as $r) {
                echo "<tr><td>{$r['id']}</td><td>{$r['bus_name']}</td><td>{$r['bus_number']}</td><td>{$r['status']}</td></tr>";
            }
            echo "</table>";
        }
    } catch (Exception $e) {
        echo "<p style='color:red;'>Query Error: " . $e->getMessage() . "</p>";
    }

    // Check missing columns
    echo "<h3>Missing columns check (discount_type, percentage, fixed):</h3>";
    $needed = ['discount_type', 'percentage', 'fixed', 'status', 'admin_id'];
    foreach ($needed as $col) {
        $exists = $pdo->query("SHOW COLUMNS FROM `buses` LIKE '$col'")->rowCount();
        $color = $exists ? 'green' : 'red';
        $mark  = $exists ? '✓' : '✗ MISSING';
        echo "<p style='color:$color;'>buses.$col — $mark</p>";
    }

} catch (PDOException $e) {
    echo "<p style='color:red;'>DB Error: " . $e->getMessage() . "</p>";
}
?>
