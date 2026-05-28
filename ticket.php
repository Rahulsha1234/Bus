<?php
/**
 * Ticket Invoice & Print view
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$ref = $_GET['ref'] ?? '';
if (empty($ref)) {
    die("Invalid reference.");
}

$page_title = "Ticket Invoice - $ref";

// Fetch Booking details
try {
    // 0. Ensure user is logged in
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        $_SESSION['login_error'] = "Please log in to view this ticket receipt.";
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
    $curr_user_id = intval($_SESSION['user_id']);
    $curr_user_role = $_SESSION['user_role'] ?? 'customer';

    $is_customer_copy = false;
    if ((isset($_GET['view']) && $_GET['view'] === 'customer') || $curr_user_role === 'customer') {
        $is_customer_copy = true;
    }

    $stmt = $pdo->prepare("
        SELECT 
            b.id AS booking_id,
            b.customer_id,
            b.booking_reference,
            b.customer_name,
            b.customer_email,
            b.customer_phone,
            b.total_amount,
            b.discount_amount,
            b.original_fare,
            b.promo_code,
            b.payment_status,
            b.created_at,
            b.boarding_point,
            b.dropping_point,
            b.booking_source,
            t.departure_time,
            t.arrival_time,
            bs.id AS bus_id,
            bs.bus_name,
            bs.bus_number,
            bs.bus_type,
            r.source,
            r.destination
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        WHERE b.booking_reference = :ref
        LIMIT 1
    ");
    $stmt->execute([':ref' => $ref]);
    $booking = $stmt->fetch();

    if (!$booking) {
        die("Ticket Reference not found.");
    }

    // 0.5. Verify ownership for customers
    if ($curr_user_role === 'customer' && intval($booking['customer_id']) !== $curr_user_id) {
        die("Access Denied: You do not have permission to view this ticket receipt.");
    }

    // Fetch Seats/Passengers
    $seats_stmt = $pdo->prepare("
        SELECT seat_number, passenger_name, passenger_age, passenger_gender, price 
        FROM booking_seats 
        WHERE booking_id = :booking_id
    ");
    $seats_stmt->execute([':booking_id' => $booking['booking_id']]);
    $passengers = $seats_stmt->fetchAll();

    // Fetch Operator Contact details
    $op_stmt = $pdo->prepare("SELECT * FROM operator_contacts WHERE bus_id = ? LIMIT 1");
    $op_stmt->execute([$booking['bus_id']]);
    $operator = $op_stmt->fetch() ?: [
        'operator_name' => 'SwiftBus Fleet Operations',
        'contact_number' => '+1 (555) 234-5678',
        'whatsapp_number' => '+1 (555) 234-5678',
        'emergency_number' => '+1 (555) 911-0099',
        'support_email' => 'support@swiftbus-fleet.com'
    ];

} catch (PDOException $e) {
    die("Error retrieving booking details: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Print-media overrides for cleaner layout output -->
<style>
@media print {
    body {
        background: white !important;
        color: black !important;
    }
    .navbar-swift, .notice-marquee, footer, .no-print {
        display: none !important;
    }
    .container {
        margin: 0 !important;
        padding: 0 !important;
        max-width: 100% !important;
    }
    .glass-card {
        background: white !important;
        color: black !important;
        border: 2px solid #ccc !important;
        box-shadow: none !important;
        backdrop-filter: none !important;
        border-radius: 0 !important;
    }
    .text-indigo, .text-gradient, .text-gradient-purple {
        color: black !important;
        background: none !important;
        -webkit-text-fill-color: initial !important;
    }
}
</style>

<div class="row justify-content-center">
    <div class="col-lg-8">
        
        <!-- Ticket print toolbar (no-print helper) -->
        <?php
            $is_agent_booking = ($booking['booking_source'] ?? '') === 'agent';
            $book_another_url = $is_agent_booking
                ? BASE_URL . '/agent/search.php'
                : BASE_URL . '/index.php';
            $book_another_label = $is_agent_booking ? 'Search Another Trip' : 'Book Another';
        ?>
        <div class="d-flex justify-content-between align-items-center mb-4 no-print flex-wrap gap-2">
            <a href="<?= $book_another_url ?>" class="btn btn-secondary-glass py-2 px-3 small"><i class="fa-solid fa-<?= $is_agent_booking ? 'arrow-left' : 'house' ?> me-2"></i><?= $book_another_label ?></a>
            <div class="d-flex gap-2">
                <?php if ($curr_user_role !== 'customer'): ?>
                    <?php if ($is_customer_copy): ?>
                        <a href="?ref=<?= urlencode($ref) ?>" class="btn btn-secondary-glass py-2 px-3 small"><i class="fa-solid fa-user-secret me-2"></i>Agent Copy</a>
                    <?php else: ?>
                        <a href="?ref=<?= urlencode($ref) ?>&view=customer" class="btn btn-secondary-glass py-2 px-3 small"><i class="fa-solid fa-users me-2"></i>Customer Copy (Original Price)</a>
                    <?php endif; ?>
                <?php endif; ?>
                <a href="<?= BASE_URL ?>/ticket_pdf.php?ref=<?= urlencode($ref) ?><?= $is_customer_copy ? '&view=customer' : '' ?>" target="_blank" class="btn btn-primary-gradient py-2 px-4 fw-bold"><i class="fa-solid fa-file-pdf me-2"></i>Download E-Ticket PDF</a>
            </div>
        </div>

        <!-- STUNNING INVOICE TICKET CONTAINER -->
        <div class="glass-card p-5 mb-5 shadow-2xl position-relative" style="border-radius: 24px; overflow:hidden;">
            <!-- Subtle backdrop graphics -->
            <div style="position:absolute; right:-50px; bottom:-50px; opacity:0.04; font-size: 20rem; color:white; pointer-events:none;">
                <i class="fa-solid fa-bus"></i>
            </div>
            
            <!-- Branding Header -->
            <div class="d-flex flex-wrap justify-content-between align-items-center border-bottom border-secondary border-opacity-25 pb-4 mb-4 g-3">
                <div>
                    <h3 class="fw-bold text-white d-flex align-items-center gap-2 mb-1">
                        <i class="fa-solid fa-bus text-indigo" style="color:#818cf8;"></i>
                        <span class="text-gradient"><?= SYSTEM_NAME ?></span>
                    </h3>
                    <span class="text-secondary small font-monospace">E-Ticket & Boarding Pass</span>
                </div>
                <div class="text-md-end">
                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 py-2 px-4 rounded-pill fs-6 fw-bold">CONFIRMED & PAID</span>
                    <div class="text-secondary small mt-2">Booked on: <?= date('d M Y H:i', strtotime($booking['created_at'])) ?></div>
                </div>
            </div>

            <!-- Booking reference layout card -->
            <div class="row g-4 mb-4">
                <div class="col-md-6 col-sm-6">
                    <span class="text-secondary small d-block">TICKET REFERENCE</span>
                    <span class="fs-4 fw-bold text-white font-monospace" style="color:#a5b4fc;"><?= htmlspecialchars($booking['booking_reference']) ?></span>
                </div>
                <div class="col-md-6 col-sm-6 text-md-end">
                    <!-- Mock Barcode -->
                    <div class="d-inline-block text-center bg-white p-2 rounded-2 border" style="letter-spacing: 2px;">
                        <span style="font-family: 'Libre Barcode 39', cursive; font-size: 2rem; color: black;"><i class="fa-solid fa-barcode text-dark" style="font-size: 2.2rem; width:150px;"></i></span>
                    </div>
                </div>
            </div>

            <!-- Trip Schedule Cards -->
            <h5 class="text-indigo fw-bold mb-3 small text-uppercase"><i class="fa-solid fa-route me-2"></i>Trip Details</h5>
            <div class="p-4 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-20 mb-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <span class="text-secondary small d-block">ROUTE</span>
                        <span class="text-white fw-bold fs-5"><?= htmlspecialchars($booking['source']) ?> <i class="fa-solid fa-arrow-right text-indigo" style="font-size:0.8rem;"></i> <?= htmlspecialchars($booking['destination']) ?></span>
                    </div>
                    <div class="col-md-4 text-md-center">
                        <span class="text-secondary small d-block">BOARDING TIME</span>
                        <span class="text-white fw-semibold fs-5"><?= date('d M Y, H:i', strtotime($booking['departure_time'])) ?></span>
                    </div>
                    <div class="col-md-4 text-md-end">
                        <span class="text-secondary small d-block">FLEET INFORMATION</span>
                        <span class="text-white fw-bold d-block"><?= htmlspecialchars($booking['bus_name']) ?></span>
                        <span class="badge bg-secondary text-uppercase small" style="font-size:0.7rem;"><?= htmlspecialchars($booking['bus_type']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Boarding & Dropping Points -->
            <h5 class="text-indigo fw-bold mb-3 small text-uppercase"><i class="fa-solid fa-location-dot me-2"></i>Milestones Selected</h5>
            <div class="p-4 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-20 mb-4">
                <div class="row g-3">
                    <div class="col-md-6">
                        <span class="text-secondary small d-block">BOARDING POINT</span>
                        <span class="text-white fw-semibold"><?= htmlspecialchars($booking['boarding_point']) ?></span>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <span class="text-secondary small d-block">DROPPING POINT</span>
                        <span class="text-white fw-semibold"><?= htmlspecialchars($booking['dropping_point']) ?></span>
                    </div>
                </div>
            </div>

            <!-- Passenger Details Grid -->
            <h5 class="text-indigo fw-bold mb-3 small text-uppercase"><i class="fa-solid fa-users me-2"></i>Passenger Information</h5>
            <div class="table-responsive mb-4">
                <table class="table table-swift table-dark table-borderless align-middle" style="background:transparent;">
                    <thead>
                        <tr class="border-bottom border-secondary border-opacity-20">
                            <th>Seat</th>
                            <th>Passenger Name</th>
                            <th>Age / Gender</th>
                            <th class="text-end">Ticket Price</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($passengers as $p): ?>
                            <tr class="border-bottom border-secondary border-opacity-10">
                                <td class="font-monospace fw-bold text-indigo" style="color:#818cf8;"><?= htmlspecialchars($p['seat_number']) ?></td>
                                <td class="text-white fw-semibold"><?= htmlspecialchars($p['passenger_name']) ?></td>
                                <td class="text-secondary"><?= htmlspecialchars($p['passenger_age']) ?> yrs / <?= htmlspecialchars($p['passenger_gender']) ?></td>
                                <td class="text-end text-white fw-semibold">₹<?= number_format($p['price'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Primary Contact details & Payment total footer -->
            <div class="row align-items-end g-4 pt-3 border-top border-secondary border-opacity-20">
                <div class="col-md-6 col-sm-6 small text-secondary">
                    <h6 class="text-secondary fw-bold small text-uppercase mb-2"><i class="fa-solid fa-address-book me-2"></i>Primary Contact</h6>
                    <div>Name: <span class="text-white fw-semibold"><?= htmlspecialchars($booking['customer_name']) ?></span></div>
                    <div>Email: <span class="text-white"><?= htmlspecialchars($booking['customer_email']) ?></span></div>
                    <div>Phone: <span class="text-white"><?= htmlspecialchars($booking['customer_phone']) ?></span></div>

                    <h6 class="text-secondary fw-bold small text-uppercase mt-3 mb-2"><i class="fa-solid fa-headset me-2"></i>Bus Operator Support</h6>
                    <div>Operator: <span class="text-white fw-semibold"><?= htmlspecialchars($operator['operator_name']) ?></span></div>
                    <div>Phone: <span class="text-white"><?= htmlspecialchars($operator['contact_number']) ?></span></div>
                    <div>Emergency: <span class="text-danger fw-bold"><?= htmlspecialchars($operator['emergency_number']) ?></span></div>
                </div>
                <div class="col-md-6 col-sm-6 text-md-end">
                    <?php if (!$is_customer_copy && $booking['discount_amount'] > 0): ?>
                        <div class="mb-2">
                            <span class="text-secondary small d-block">PROMO DISCOUNT (<?= htmlspecialchars($booking['promo_code'] ?? 'Agent Discount') ?>)</span>
                            <span class="text-success fw-bold">-₹<?= number_format($booking['discount_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <span class="text-secondary small d-block">TOTAL FARE PAID</span>
                    <span class="fs-2 fw-bold text-indigo" style="color:#818cf8;">₹<?= number_format($is_customer_copy ? (floatval($booking['original_fare']) > 0 ? floatval($booking['original_fare']) : floatval($booking['total_amount']) + floatval($booking['discount_amount'])) : floatval($booking['total_amount']), 2) ?></span>
                    <div class="text-secondary small" style="font-size:0.75rem;">Inclusive of Processing Taxes</div>
                </div>
            </div>

            <!-- Security instructions footer -->
            <div class="mt-5 p-3 rounded-3 bg-dark bg-opacity-30 border border-secondary border-opacity-10 small text-secondary text-center">
                <i class="fa-solid fa-circle-exclamation text-indigo me-2"></i> Please report at the boarding station at least 15 minutes before departure with a printed copy or SMS confirmation of this ticket.
            </div>

        </div>

    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
