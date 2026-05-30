<?php
/**
 * AJAX Seat Hold Handler
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

    $pdo->beginTransaction();

    // 4. Check current seat status
    $seat_stmt = $pdo->prepare("SELECT status, hold_expires_at FROM trip_seats WHERE trip_id = ? AND seat_number = ? LIMIT 1 FOR UPDATE");
    $seat_stmt->execute([$trip_id, $seat]);
    $current_seat = $seat_stmt->fetch();
    
    $now = date('Y-m-d H:i:s');
    if ($current_seat) {
        $status = $current_seat['status'];
        if ($status === 'hold' && strtotime($current_seat['hold_expires_at']) < strtotime($now)) {
            $status = 'available';
        }

        if ($status !== 'available') {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => 'This seat is already booked or held.']);
            exit();
        }
    }

    // 5. Update status to hold (Long term hold for 7 days representing offline ticket book block)
    $expires = date('Y-m-d H:i:s', strtotime('+7 days'));
    $session_marker = 'agent_hold_' . $agent_id;

    $hold_stmt = $pdo->prepare("
        INSERT INTO trip_seats (trip_id, seat_number, status, hold_expires_at, locked_by_session)
        VALUES (:trip_id, :seat, 'hold', :expires, :session)
        ON DUPLICATE KEY UPDATE 
            status = 'hold', 
            hold_expires_at = :expires_update, 
            locked_by_session = :session_update
    ");
    $hold_stmt->execute([
        ':trip_id' => $trip_id,
        ':seat' => $seat,
        ':expires' => $expires,
        ':session' => $session_marker,
        ':expires_update' => $expires,
        ':session_update' => $session_marker
    ]);

    log_activity($pdo, $agent_id, 'SEAT_HOLD_MANUAL', "Placed manual hold on Trip $trip_id, Seat $seat.");
    $pdo->commit();
    echo json_encode(['success' => true]);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
