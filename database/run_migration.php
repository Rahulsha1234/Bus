<?php
/**
 * Run Schema Migrations
 */
if (php_sapi_name() !== 'cli') {
    die("Access Denied: This script can only be executed via the CLI (command line interface).");
}

require_once __DIR__ . '/../config/config.php';

try {
    // Connect to database
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Connected successfully to " . DB_NAME . ".\n";
    
    // Read migration file
    $migration_file = __DIR__ . '/migration.sql';
    if (!file_exists($migration_file)) {
        die("Migration file not found: $migration_file\n");
    }
    
    $sql = file_get_contents($migration_file);
    
    // Execute sql
    $pdo->exec($sql);
    echo "Migration completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database migration failed: " . $e->getMessage() . "\n");
}
