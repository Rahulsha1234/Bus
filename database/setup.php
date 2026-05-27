<?php
/**
 * Database Setup and Seeding Script
 */
$lock_file = __DIR__ . '/setup.lock';
if (file_exists($lock_file)) {
    die("<h2>Setup is locked!</h2><p>The database has already been initialized. To re-run setup, delete the file: <code>database/setup.lock</code>.</p>");
}

$host = '127.0.0.1;port=3307';
$user = 'root';
$pass = '';

try {
    // 1. Connect without DB selected
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Connected to MySQL successfully.<br>";

    // 2. Read and parse schema.sql
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        die("schema.sql not found at: $schemaFile");
    }
    
    $sql = file_get_contents($schemaFile);
    
    // Execute SQL statements
    $pdo->exec("CREATE DATABASE IF NOT EXISTS bus_booking");
    $pdo->exec("USE bus_booking");
    $pdo->exec($sql);
    echo "Database and schema initialized successfully.<br>";

    echo "<h3>SETUP COMPLETED SUCCESSFULLY!</h3>";
    file_put_contents($lock_file, date('Y-m-d H:i:s'));
    echo "Use the following credentials to log in:<br>";
    echo "<b>Super Admin:</b> admin / admin123<br>";
    echo "<b>Bus Operator (Admin):</b> aslitravels / 123456789<br>";
    echo "<b>Agent:</b> asliagent / 123456789<br>";
    echo "<b>Customer:</b> jyoti / 123456789<br>";

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "<br>";
    echo "Please check if your local WAMP server is running and default MySQL settings are correct.";
}
