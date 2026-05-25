<?php
/**
 * Ticket Cancellation Request Controller & Confirmation view
 */
require_once __DIR__ . '/includes/auth_middleware.php';

// Redirect to login if guest
if (!is_logged_in()) {
    $_SESSION['redirect_url'] = BASE_URL . "/bookings.php";
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$page_title = "Ticket Cancellation";
$booking_id = intval($_GET['booking_id'] ?? 0);
$customer_id = $_SESSION['user_id'];

if ($booking_id === 0) {
    header("Location: " . BASE_URL . "/bookings.php");
    exit();
}

try {
    // 1. Fetch booking details to verify ownership and check status
    $stmt = $pdo->prepare("
        SELECT 
            b.id AS booking_id,
            b.booking_reference,
            b.total_amount,
            b.status AS booking_status,
            t.departure_time,
            t.id AS trip_id,
            bs.id AS bus_id,
            bs.bus_name,
            bs.bus_number,
            r.source,
            r.destination
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        WHERE b.id = ? AND b.customer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$booking_id, $customer_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        die("Booking record not found or access denied.");
    }

    // 2. Check if already requested or cancelled
    $req_stmt = $pdo->prepare("SELECT * FROM cancellation_requests WHERE booking_id = ? LIMIT 1");
    $req_stmt->execute([$booking_id]);
    $existing_request = $req_stmt->fetch();

    if ($booking['booking_status'] === 'cancelled' || $existing_request) {
        // Redirection fallback or show info if already requested
        $request_number = $existing_request ? $existing_request['request_number'] : 'N/A';
        $request_status = $existing_request ? $existing_request['status'] : 'approved';
    } else {
        // Process new cancellation request submission
        $request_number = 'CAN' . time() . rand(100, 999);
        $refund_amount = $booking['total_amount']; // Agent/Admin can deduct cancellation charge later

        $pdo->beginTransaction();

        // Insert cancellation request record
        $ins_stmt = $pdo->prepare("
            INSERT INTO cancellation_requests (booking_id, request_number, refund_amount, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $ins_stmt->execute([$booking_id, $request_number, $refund_amount]);

        // Fetch Agent ID to notify
        $agent_stmt = $pdo->prepare("
            SELECT b.agent_id 
            FROM trips t 
            JOIN buses b ON t.bus_id = b.id 
            WHERE t.id = ? 
            LIMIT 1
        ");
        $agent_stmt->execute([$booking['trip_id']]);
        $agent_id = $agent_stmt->fetchColumn();

        // Notify Agent
        if ($agent_id) {
            $notif_stmt = $pdo->prepare("
                INSERT INTO system_notifications (user_id, user_role, message) 
                VALUES (?, 'agent', ?)
            ");
            $notif_stmt->execute([$agent_id, "New Cancellation Request $request_number submitted for Booking " . $booking['booking_reference']]);
        }

        // Notify Admin
        $notif_admin = $pdo->prepare("
            INSERT INTO system_notifications (user_id, user_role, message) 
            VALUES (NULL, 'admin', ?)
        ");
        $notif_admin->execute(["New Cancellation Request $request_number submitted for Booking " . $booking['booking_reference']]);

        // Audit Log
        log_activity($pdo, $customer_id, 'CANCELLATION_REQUEST', "Submitted cancellation request $request_number for Booking " . $booking['booking_reference'] . " (Booking ID: $booking_id)");

        $pdo->commit();
        $request_status = 'pending';
    }

    // Fetch Operator Contact Details to display to customer
    $op_stmt = $pdo->prepare("SELECT * FROM operator_contacts WHERE bus_id = ? LIMIT 1");
    $op_stmt->execute([$booking['bus_id']]);
    $operator = $op_stmt->fetch() ?: [
        'operator_name' => 'SwiftBus Fleet Operations',
        'contact_number' => '+1 (555) 234-5678',
        'whatsapp_number' => '+1 (555) 234-5678',
        'emergency_number' => '+1 (555) 911-0099',
        'support_email' => 'support@swiftbus-fleet.com'
    ];

} catch (Exception $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Cancellation transaction failed: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center">
    <div class="col-lg-7">
        <div class="glass-card p-5 text-center" style="border-radius: 20px;">
            <div class="text-warning mb-4" style="font-size: 4rem;"><i class="fa-solid fa-circle-exclamation"></i></div>
            
            <h3 class="fw-bold text-white mb-2">Cancellation Request Submitted</h3>
            <p class="text-secondary small mb-4">Your request has been successfully registered. The operations team will review and approve refund processing.</p>

            <div class="p-4 rounded-4 bg-dark bg-opacity-30 border border-secondary border-opacity-15 mb-4 text-start small">
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Cancellation Request No:</span>
                    <span class="text-indigo fw-bold font-monospace"><?= htmlspecialchars($request_number) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Ticket Reference:</span>
                    <span class="text-white fw-semibold font-monospace"><?= htmlspecialchars($booking['booking_reference']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Voyage:</span>
                    <span class="text-white fw-semibold"><?= htmlspecialchars($booking['source']) ?> to <?= htmlspecialchars($booking['destination']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Travel Date:</span>
                    <span class="text-white"><?= date('d M Y, H:i', strtotime($booking['departure_time'])) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-secondary">Status:</span>
                    <span class="badge bg-warning text-uppercase"><?= htmlspecialchars($request_status) ?></span>
                </div>
            </div>

            <!-- Operator support contacts -->
            <div class="p-4 rounded-4 border border-secondary border-opacity-15 text-start bg-dark bg-opacity-10 mb-4">
                <h6 class="fw-bold text-indigo mb-3"><i class="fa-solid fa-headset me-2"></i>Bus Operator Support</h6>
                <div class="small">
                    <div class="mb-2"><strong>Operator:</strong> <?= htmlspecialchars($operator['operator_name']) ?></div>
                    <div class="mb-2"><strong>Direct Helpline:</strong> <?= htmlspecialchars($operator['contact_number']) ?></div>
                    <div class="mb-2"><strong>WhatsApp Support:</strong> <?= htmlspecialchars($operator['whatsapp_number']) ?></div>
                    <div class="mb-2"><strong>Emergency Hot-Line:</strong> <span class="text-danger fw-bold"><?= htmlspecialchars($operator['emergency_number']) ?></span></div>
                    <div><strong>Support Email:</strong> <?= htmlspecialchars($operator['support_email']) ?></div>
                </div>
            </div>

            <div class="d-flex justify-content-center gap-3">
                <a href="<?= BASE_URL ?>/bookings.php" class="btn btn-secondary-glass px-4 py-2"><i class="fa-solid fa-arrow-left me-2"></i>My Bookings</a>
                <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary-gradient px-4 py-2"><i class="fa-solid fa-house me-2"></i>Home</a>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
