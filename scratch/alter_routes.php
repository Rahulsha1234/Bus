<?php
require_once __DIR__ . '/../config/config.php';
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    $pdo->exec("ALTER TABLE routes ADD COLUMN IF NOT EXISTS duration VARCHAR(50) NOT NULL DEFAULT '6 hours';");
    echo "Column 'duration' added successfully.\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
