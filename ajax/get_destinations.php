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
    $stmt = $pdo->prepare(
        "SELECT DISTINCT destination FROM routes 
         WHERE source = ? AND status = 'active' 
         ORDER BY destination ASC"
    );
    $stmt->execute([$source]);
    $destinations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($destinations);
} catch (PDOException $e) {
    echo json_encode([]);
}
