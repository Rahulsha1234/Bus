<?php
/**
 * AJAX Seat Release Handler
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// 1. Authorization checks
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized request.']);
    exit();
}

// 2. Validate CSRF
$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Security token mismatch.']);
    exit();
}

$trip_id = intval($_POST['trip_id'] ?? 0);
$seat = trim($_POST['seat_number'] ?? '');
$agent_id = $_SESSION['user_id'];

if ($trip_id === 0 || empty($seat)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit();
}

try {
    // 3. Verify that the agent actually owns the trip
    $chk_stmt = $pdo->prepare("
        SELECT t.id 
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        WHERE t.id = ? AND b.agent_id = ? 
        LIMIT 1
    ");
    $chk_stmt->execute([$trip_id, $agent_id]);
    
    if (!$chk_stmt->fetchColumn()) {
        echo json_encode(['success' => false, 'message' => 'Unauthorized trip configuration access.']);
        exit();
    }

    // 4. Update status to available
    $release_stmt = $pdo->prepare("
        UPDATE trip_seats 
        SET status = 'available', hold_expires_at = NULL, locked_by_session = NULL 
        WHERE trip_id = ? AND seat_number = ?
    ");
    $release_stmt->execute([$trip_id, $seat]);

    log_activity($pdo, $agent_id, 'SEAT_RELEASE_MANUAL', "Released manual hold on Trip $trip_id, Seat $seat.");
    echo json_encode(['success' => true]);
    exit();

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
