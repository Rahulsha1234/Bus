<?php
/**
 * Temporary Database SQL Importer / Restorer
 * IMPORTANT: Delete this file after importing your backup!
 */
require_once __DIR__ . '/includes/db.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['sql_file']) && $_FILES['sql_file']['error'] === UPLOAD_ERR_OK) {
        $file_path = $_FILES['sql_file']['tmp_name'];
        $sql_content = file_get_contents($file_path);
        
        try {
            // Disable foreign key checks temporarily
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Drop all existing tables in the database to prevent duplicate table errors
            $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            foreach ($tables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS `$table`");
            }
            
            $handle = fopen($file_path, "r");
            $queries_executed = 0;
            if ($handle) {
                $tempLine = '';
                while (($line = fgets($handle)) !== false) {
                    $line_trim = trim($line);
                    // Skip comments and empty lines
                    if (strpos($line_trim, '--') === 0 || $line_trim === '' || strpos($line_trim, '#') === 0 || strpos($line_trim, '/*') === 0) {
                        continue;
                    }
                    
                    $tempLine .= $line;
                    
                    if (substr($line_trim, -1) === ';') {
                        $query = trim($tempLine);
                        // Skip USE or CREATE DATABASE commands
                        if (stripos($query, 'CREATE DATABASE') === 0 || stripos($query, 'USE ') === 0) {
                            $tempLine = '';
                            continue;
                        }
                        if (!empty($query)) {
                            $pdo->exec($query);
                            $queries_executed++;
                        }
                        $tempLine = '';
                    }
                }
                fclose($handle);
            }
            
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $message = "Database migrated and imported successfully!<br><strong>Connected to DB:</strong> " . DB_NAME . " on " . DB_HOST . "<br><strong>Executed queries:</strong> " . $queries_executed . "<br>Please check phpMyAdmin now. Also delete both 'download_backup.php' and 'import_backup.php' from your server immediately.";
        } catch (Exception $e) {
            $error = "Import failed: " . $e->getMessage();
        }
    } else {
        $error = "Please select a valid SQL file to upload.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Migration Tool</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card shadow border-0 mt-5">
                <div class="card-body p-5">
                    <h3 class="fw-bold text-center mb-4">SwiftBus DB Importer</h3>
                    <p class="text-muted text-center small mb-4">Upload the SQL file downloaded from your old hosting to import it into your new database configuration.</p>
                    
                    <?php if ($message): ?>
                        <div class="alert alert-success"><?= $message ?></div>
                    <?php endif; ?>

                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="mb-4">
                            <label class="form-label fw-semibold small">Choose SQL Backup File</label>
                            <input type="file" name="sql_file" class="form-control" accept=".sql" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100 py-2 fw-bold">Upload &amp; Migrate Database</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
