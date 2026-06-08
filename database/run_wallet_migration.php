<?php
/**
 * Run Wallet Migrations
 */
require_once __DIR__ . '/../config/config.php';

try {
    // Connect to database
    $dsn = "mysql:host=127.0.0.1;port=3306;dbname=bus_booking;charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Connected successfully to " . DB_NAME . ".\n";
    
    // Read migration file
    $migration_file = __DIR__ . '/wallet_migration.sql';
    if (!file_exists($migration_file)) {
        die("Migration file not found: $migration_file\n");
    }
    
    $sql = file_get_contents($migration_file);
    
    // Execute SQL
    $pdo->exec($sql);
    echo "Wallet migration completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database migration failed: " . $e->getMessage() . "\n");
}
