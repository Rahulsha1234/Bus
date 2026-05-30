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
            "SELECT DISTINCT r.destination 
             FROM routes r 
             JOIN trips t ON r.id = t.route_id 
             WHERE r.source = ? AND r.status = 'active' AND t.status = 'ACTIVE' AND t.departure_time >= NOW() AND r.admin_id = ? 
             ORDER BY r.destination ASC"
        );
        $stmt->execute([$source, $admin_id]);
    } else {
        $stmt = $pdo->prepare(
            "SELECT DISTINCT r.destination 
             FROM routes r 
             JOIN trips t ON r.id = t.route_id 
             WHERE r.source = ? AND r.status = 'active' AND t.status = 'ACTIVE' AND t.departure_time >= NOW() 
             ORDER BY r.destination ASC"
        );
        $stmt->execute([$source]);
    }
    $destinations = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo json_encode($destinations);
} catch (PDOException $e) {
    echo json_encode([]);
}
