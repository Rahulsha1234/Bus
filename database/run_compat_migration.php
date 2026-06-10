<?php
/**
 * Robust Database Migration Runner (MySQL Version Compatible)
 */
require_once __DIR__ . '/../config/config.php';

function run_sql_file($pdo, $filePath) {
    echo "\n----------------------------------------\n";
    echo "Running migration file: " . basename($filePath) . "\n";
    echo "----------------------------------------\n";

    if (!file_exists($filePath)) {
        echo "Error: File not found: $filePath\n";
        return false;
    }

    $sql = file_get_contents($filePath);
    
    // Split queries by semicolon followed by a newline (handles most standard formatted SQLs)
    $queries = preg_split('/;\s*[\r\n]+/', $sql);

    $successCount = 0;
    $ignoredCount = 0;
    $failedCount = 0;

    foreach ($queries as $query) {
        $query = trim($query);
        if (empty($query)) {
            continue;
        }

        // Clean up SQL: replace "ADD COLUMN IF NOT EXISTS" and "ADD INDEX IF NOT EXISTS" if present
        $originalQuery = $query;
        $query = preg_replace('/ADD\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+/i', 'ADD COLUMN ', $query);
        $query = preg_replace('/ADD\s+INDEX\s+IF\s+NOT\s+EXISTS\s+/i', 'ADD INDEX ', $query);
        $query = preg_replace('/ALTER\s+TABLE\s+(\w+)\s+ADD\s+INDEX\s+IF\s+NOT\s+EXISTS/i', 'ALTER TABLE $1 ADD INDEX', $query);
        $query = preg_replace('/MODIFY\s+COLUMN\s+IF\s+NOT\s+EXISTS\s+/i', 'MODIFY COLUMN ', $query);

        try {
            $pdo->exec($query);
            echo "SUCCESS: " . substr(preg_replace('/\s+/', ' ', $query), 0, 80) . "...\n";
            $successCount++;
        } catch (PDOException $e) {
            $errorCode = $e->errorInfo[1] ?? 0;
            // 1060: Duplicate column name
            // 1061: Duplicate key name (index already exists)
            // 1050: Table already exists
            if (in_array($errorCode, [1050, 1060, 1061])) {
                echo "IGNORED (Already Exists): " . substr(preg_replace('/\s+/', ' ', $query), 0, 80) . "...\n";
                $ignoredCount++;
            } else {
                echo "FAILED: " . $e->getMessage() . "\nQuery: " . $query . "\n";
                $failedCount++;
            }
        }
    }

    echo "\nFile " . basename($filePath) . " execution summary:\n";
    echo "  Success: $successCount\n";
    echo "  Ignored (already applied): $ignoredCount\n";
    echo "  Failed: $failedCount\n";

    return $failedCount === 0;
}

try {
    // Connect to database
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    
    echo "Connected successfully to " . DB_NAME . " on local host.\n";
    
    // Run all migrations
    $migrations = [
        __DIR__ . '/migration.sql',
        __DIR__ . '/002_add_gst_fields.sql',
        __DIR__ . '/payment_gateway_migration.sql',
        __DIR__ . '/wallet_migration.sql'
    ];

    $allSuccess = true;
    foreach ($migrations as $migration) {
        if (!run_sql_file($pdo, $migration)) {
            $allSuccess = false;
        }
    }

    if ($allSuccess) {
        echo "\n========================================\n";
        echo "All database migrations executed successfully!\n";
        echo "========================================\n";
    } else {
        echo "\n========================================\n";
        echo "Some migrations had errors. Please check the log above.\n";
        echo "========================================\n";
    }
    
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}
