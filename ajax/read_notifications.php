<?php
/**
 * AJAX Handler to Mark Notifications as Read
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit();
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['user_role'] ?? '';

if (!in_array($role, ['admin', 'agent'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized role']);
    exit();
}

try {
    if ($role === 'admin') {
        $stmt = $pdo->prepare("UPDATE system_notifications SET is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE user_role = 'admin' AND user_id IS NULL AND is_read = 0");
        $stmt->execute();
    } else {
        $stmt = $pdo->prepare("UPDATE system_notifications SET is_read = 1, read_at = COALESCE(read_at, NOW()) WHERE user_role = 'agent' AND user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
    }

    echo json_encode(['success' => true]);
    exit();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    exit();
}
