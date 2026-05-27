<?php
/**
 * AJAX: Get destinations available from a given source
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

header('Content-Type: application/json');

$source = trim($_GET['source'] ?? '');

if (empty($source)) {
    echo json_encode([]);
    exit();
}

try {
    $admin_id = intval($_GET['admin_id'] ?? 0);
    if ($admin_id > 0) {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT destination FROM routes 
             WHERE source = ? AND status = 'active' AND admin_id = ? 
             ORDER BY destination ASC"
        );
        $stmt->execute([$source, $admin_id]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT destination FROM routes 
             WHERE source = ? AND status = 'active' 
             ORDER BY destination ASC"
        );
        $stmt->execute([$source]);
    }
    $destinations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($destinations);
} catch (PDOException $e) {
    echo json_encode([]);
}
