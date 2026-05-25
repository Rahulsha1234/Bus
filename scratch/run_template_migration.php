<?php
require_once __DIR__ . '/../includes/db.php';

try {
    $sql = file_get_contents(__DIR__ . '/../database/migration_templates.sql');
    $pdo->exec($sql);
    echo "Layout templates migration ran successfully!\n";
} catch (Exception $e) {
    echo "Error running template migration: " . $e->getMessage() . "\n";
}
