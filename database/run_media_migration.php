<?php
/**
 * Run Media, Amenities, Specifications, Reviews, Tracking, and Experience System Migrations
 */
if (php_sapi_name() !== 'cli') {
    // Also allow running via web requests if authorized or just for local setup
}

require_once __DIR__ . '/../config/config.php';

try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Connected successfully to " . DB_NAME . ".\n";
    
    $migration_file = __DIR__ . '/media_amenities_reviews_tracking_migration.sql';
    if (!file_exists($migration_file)) {
        die("Migration file not found: $migration_file\n");
    }
    
    $sql = file_get_contents($migration_file);
    
    $pdo->exec($sql);
    echo "Media, Amenities, Specs, Policies, Reviews, Tracking migration completed successfully!\n";
    
} catch (PDOException $e) {
    die("Database migration failed: " . $e->getMessage() . "\n");
}
