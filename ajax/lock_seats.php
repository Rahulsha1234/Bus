<?php
/**
 * AJAX Temporary Seat Lock Handler (10 Minutes Expiry)
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure user is logged in to lock seats
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to select seats.']);
    exit();
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit();
}

$trip_id = intval($_POST['trip_id'] ?? 0);
$seat = trim($_POST['seat_number'] ?? '');
$action = $_POST['action'] ?? ''; // 'lock' or 'unlock'
$session_id = session_id();

if ($trip_id === 0 || empty($seat) || !in_repeat_action($action, ['lock', 'unlock'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters.']);
    exit();
}

function in_repeat_action($val, $arr) {
    return in_array($val, $arr);
}

try {
    $pdo->beginTransaction();

    $now = date('Y-m-d H:i:s');
    $seven_mins_ago = date('Y-m-d H:i:s', strtotime('-7 minutes'));

    // Fetch current status
    $stmt = $pdo->prepare("SELECT status, locked_at, locked_by_session FROM trip_seats WHERE trip_id = ? AND seat_number = ? LIMIT 1 FOR UPDATE");
    $stmt->execute([$trip_id, $seat]);
    $current = $stmt->fetch();

    if ($action === 'lock') {
        if ($current) {
            $status = $current['status'];
            // Check if locked and not expired
            if ($status === 'temp_locked' && !empty($current['locked_at']) && $current['locked_at'] > $seven_mins_ago) {
                if ($current['locked_by_session'] !== $session_id) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'This seat is temporarily locked by another user.']);
                    exit();
                } else {
                    $pdo->rollBack();
                    echo json_encode(['success' => true, 'message' => 'Already locked by you.']);
                    exit();
                }
            }
            
            // Check if booked or held
            if (in_array($status, ['booked', 'hold', 'blocked', 'reserved', 'female_booked', 'female_protected'])) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => 'This seat is already booked, blocked, or held.']);
                exit();
            }
        }

        // Lock the seat
        $lock_stmt = $pdo->prepare("
            INSERT INTO trip_seats (trip_id, seat_number, status, locked_at, locked_by_session)
            VALUES (:trip_id, :seat, 'temp_locked', :now, :session)
            ON DUPLICATE KEY UPDATE 
                status = 'temp_locked', 
                locked_at = :now_up, 
                locked_by_session = :session_up
        ");
        $lock_stmt->execute([
            ':trip_id' => $trip_id,
            ':seat' => $seat,
            ':now' => $now,
            ':session' => $session_id,
            ':now_up' => $now,
            ':session_up' => $session_id
        ]);

        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'Seat locked successfully.']);
        exit();
    } 
    elseif ($action === 'unlock') {
        if ($current && $current['status'] === 'temp_locked' && $current['locked_by_session'] === $session_id) {
            $unlock_stmt = $pdo->prepare("UPDATE trip_seats SET status = 'available', locked_at = NULL, locked_by_session = NULL WHERE trip_id = ? AND seat_number = ?");
            $unlock_stmt->execute([$trip_id, $seat]);
            $pdo->commit();
            echo json_encode(['success' => true, 'message' => 'Seat unlocked successfully.']);
            exit();
        }
        $pdo->commit();
        echo json_encode(['success' => true, 'message' => 'No active lock to release.']);
        exit();
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    exit();
}
