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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/bookings.php");
    exit();
}

$csrf_token = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf_token)) {
    die("Error: Security CSRF token validation failed.");
}

$page_title = "Ticket Cancellation";
$booking_id = intval($_POST['booking_id'] ?? 0);
$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['user_role'] ?? 'customer';

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
            b.booking_source,
            b.base_fare,
            b.gst_rate,
            b.gst_amount,
            b.total_fare_after_tax,
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
        WHERE b.id = ? AND (b.customer_id = ? OR b.agent_id = ?)
        LIMIT 1
    ");
    $stmt->execute([$booking_id, $user_id, $user_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        die("Booking record not found or access denied.");
    }

    // Fetch all active seats for this booking
    $seats_stmt = $pdo->prepare("SELECT * FROM booking_seats WHERE booking_id = ?");
    $seats_stmt->execute([$booking_id]);
    $all_seats = $seats_stmt->fetchAll();
    
    $active_seats = [];
    foreach ($all_seats as $s) {
        if ($s['status'] === 'active') {
            $active_seats[] = $s;
        }
    }

    // Determine if we need to show the seat selection screen
    $confirm_cancellation = intval($_POST['confirm_cancellation'] ?? 0);
    $selected_seats = $_POST['seats'] ?? [];

    if ($confirm_cancellation === 0 && count($active_seats) > 0) {
        // Render seat selection view
        require_once __DIR__ . '/includes/header.php';
        ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="glass-card p-5" style="border-radius: 20px;">
                    <h3 class="fw-bold text-white mb-2"><i class="fa-solid fa-chair text-indigo me-2"></i>Select Seats to Cancel</h3>
                    <p class="text-secondary small mb-4">Please select which seat(s) you wish to cancel from your booking. A cancellation request will be submitted to the operator.</p>

                    <form method="POST" action="<?= BASE_URL ?>/cancellations.php">
                        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                        <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
                        <input type="hidden" name="confirm_cancellation" value="1">

                        <div class="p-4 rounded-4 bg-dark bg-opacity-30 border border-secondary border-opacity-15 mb-4 small">
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary">Booking Reference:</span>
                                <span class="text-indigo fw-bold font-monospace"><?= htmlspecialchars($booking['booking_reference']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary">Voyage:</span>
                                <span class="text-white fw-semibold"><?= htmlspecialchars($booking['source']) ?> to <?= htmlspecialchars($booking['destination']) ?></span>
                            </div>
                            <div class="d-flex justify-content-between mb-2">
                                <span class="text-secondary">Travel Date:</span>
                                <span class="text-white"><?= date('d M Y, H:i', strtotime($booking['departure_time'])) ?></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label text-secondary small fw-semibold">Available Seats in Booking</label>
                            <div class="row g-3">
                                <?php foreach ($active_seats as $seat): ?>
                                    <div class="col-md-6">
                                        <div class="p-3 rounded-3 border border-secondary border-opacity-20 bg-dark bg-opacity-20 d-flex align-items-center gap-3">
                                            <input type="checkbox" name="seats[]" value="<?= htmlspecialchars($seat['seat_number']) ?>" id="seat_<?= htmlspecialchars($seat['seat_number']) ?>" checked style="width: 20px; height: 20px;">
                                            <label for="seat_<?= htmlspecialchars($seat['seat_number']) ?>" class="text-white mb-0 cursor-pointer w-100">
                                                <strong>Seat <?= htmlspecialchars($seat['seat_number']) ?></strong>
                                                <span class="d-block text-secondary small"><?= htmlspecialchars($seat['passenger_name']) ?> (<?= htmlspecialchars($seat['passenger_gender']) ?>, <?= htmlspecialchars($seat['passenger_age']) ?> yrs)</span>
                                                <span class="text-success small">Fare: ₹<?= number_format($seat['price'], 2) ?></span>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between gap-3">
                            <a href="<?= BASE_URL ?>/bookings.php" class="btn btn-secondary-glass rounded-3 px-4 py-2">Back to Bookings</a>
                            <button type="submit" class="btn btn-danger py-2 px-4 rounded-3 fw-bold">Submit Cancellation Request</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
        require_once __DIR__ . '/includes/footer.php';
        exit();
    }

    if (empty($selected_seats)) {
        die("Error: No seats selected for cancellation.");
    }

    // Process new cancellation request submission
    $request_number = 'CAN' . time() . rand(100, 999);
    
    // Sum total base price of selected seats
    $refund_base_fare = 0;
    foreach ($all_seats as $s) {
        if (in_array($s['seat_number'], $selected_seats)) {
            $refund_base_fare += floatval($s['price']);
        }
    }

    // Calculate proportional GST
    $refund_gst = $refund_base_fare * ($booking['gst_rate'] / 100);
    $total_refund = $refund_base_fare + $refund_gst;

    $pdo->beginTransaction();

    // Set refund type: if agent, credit goes to wallet. Else cash.
    $refund_type = ($booking['booking_source'] === 'agent') ? 'wallet' : 'cash';

    // Insert cancellation request record
    $ins_stmt = $pdo->prepare("
        INSERT INTO cancellation_requests (booking_id, request_number, refund_amount, status, cancelled_seats, refund_type, refund_base_fare, refund_gst, total_refund)
        VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?)
    ");
    $ins_stmt->execute([
        $booking_id,
        $request_number,
        $total_refund,
        json_encode($selected_seats),
        $refund_type,
        $refund_base_fare,
        $refund_gst,
        $total_refund
    ]);

    // Mark selected seats status to 'cancel_requested' in booking_seats
    $seat_placeholders = implode(',', array_fill(0, count($selected_seats), '?'));
    $update_seats_status = $pdo->prepare("
        UPDATE booking_seats 
        SET status = 'cancel_requested' 
        WHERE booking_id = ? AND seat_number IN ($seat_placeholders)
    ");
    $update_seats_status->execute(array_merge([$booking_id], $selected_seats));

    // Fetch Agent ID to notify
    $agent_id = null;
    if ($booking['booking_source'] === 'agent') {
        $agent_id = $user_id;
    }

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
    log_activity($pdo, $user_id, 'CANCELLATION_REQUEST', "Submitted cancellation request $request_number for Booking " . $booking['booking_reference'] . " (Seats: " . implode(', ', $selected_seats) . ")");

    $pdo->commit();
    $request_status = 'pending';

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
            
            <h3 class="fw-bold text-white mb-2"><?= __('cancel_request_submitted', 'Cancellation Request Submitted') ?></h3>
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
                    <span class="text-secondary">Cancelled Seats:</span>
                    <span class="text-danger fw-bold font-monospace"><?= htmlspecialchars(implode(', ', $selected_seats)) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Refund Base Fare:</span>
                    <span class="text-white fw-bold">₹<?= number_format($refund_base_fare, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span class="text-secondary">Refund GST (<?= number_format($booking['gst_rate'], 1) ?>%):</span>
                    <span class="text-white fw-bold">₹<?= number_format($refund_gst, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 border-top border-secondary border-opacity-20 pt-2">
                    <span class="text-secondary">Estimated Total Refund:</span>
                    <span class="text-success fw-bold fs-6">₹<?= number_format($total_refund, 2) ?></span>
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
