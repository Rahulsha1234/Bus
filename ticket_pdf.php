<?php
/**
 * Premium E-Ticket PDF / Print Controller
 */
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/includes/pdf_template.php';

$ref = $_GET['ref'] ?? '';
if (empty($ref)) {
    die("Invalid reference.");
}

try {
    // 0. Ensure user is logged in
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
    $curr_user_id = intval($_SESSION['user_id']);
    $curr_user_role = $_SESSION['user_role'] ?? 'customer';
    $is_customer_copy = true; // Default to Customer Copy
    if (isset($_GET['view']) && $_GET['view'] === 'agent' && $curr_user_role !== 'customer') {
        $is_customer_copy = false;
    }

    // Fetch Booking details
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
            b.base_fare,
            b.gst_rate,
            b.gst_amount,
            b.total_fare_after_tax,
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
    die("Error retrieving ticket: " . $e->getMessage());
}

// 1. Render Header Toolbar
$additional_buttons = '';
if ($curr_user_role !== 'customer') {
    if ($is_customer_copy) {
        $additional_buttons = '<a href="?ref=' . urlencode($booking['booking_reference']) . '&view=agent" class="btn-pdf-outline"><i class="fa-solid fa-user-secret me-2"></i>Agent Copy</a>';
    } else {
        $additional_buttons = '<a href="?ref=' . urlencode($booking['booking_reference']) . '&view=customer" class="btn-pdf-outline"><i class="fa-solid fa-users me-2"></i>Customer Copy</a>';
    }
}
render_pdf_head("E-Ticket: " . $booking['booking_reference']);
render_pdf_toolbar("ticket.php?ref=" . urlencode($booking['booking_reference']), $additional_buttons);
?>

<div class="pdf-container">
    <?php 
    // 2. Render Header Component
    $header_title = $is_customer_copy ? "E-Ticket & Boarding Pass" : "Agent Invoice copy";
    render_pdf_header($header_title, "Booking Reference", $booking['booking_reference']); 
    ?>

    <!-- Status Highlight Row -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <span class="info-label">Booking Status</span>
            <div>
                <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-2 rounded-pill fw-bold">
                    <i class="fa-solid fa-circle-check me-1"></i> CONFIRMED & PAID
                </span>
            </div>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="info-label">Booked Date</div>
            <div class="info-value"><?= date('d M Y, H:i', strtotime($booking['created_at'])) ?></div>
        </div>
    </div>

    <!-- 3. Journey & Fleet Info Card Component -->
    <?php render_pdf_section_title("Journey & Fleet Information", "fa-solid fa-route"); ?>
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="pdf-info-card">
                <div class="mb-3">
                    <div class="info-label">Route Voyage</div>
                    <div class="info-value fs-5 text-success">
                        <?= htmlspecialchars($booking['source']) ?> 
                        <i class="fa-solid fa-arrow-right mx-2 text-muted" style="font-size:0.9rem;"></i> 
                        <?= htmlspecialchars($booking['destination']) ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-6">
                        <div class="info-label">Departure</div>
                        <div class="info-value small"><?= date('d M Y, H:i', strtotime($booking['departure_time'])) ?></div>
                    </div>
                    <div class="col-6">
                        <div class="info-label">Arrival (Est)</div>
                        <div class="info-value small"><?= date('d M Y, H:i', strtotime($booking['arrival_time'])) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="pdf-info-card">
                <div class="mb-3">
                    <div class="info-label">Fleet Operator</div>
                    <div class="info-value fs-5"><?= htmlspecialchars($booking['bus_name']) ?></div>
                    <span class="badge bg-light border text-dark mt-1 font-monospace fs-7 text-uppercase"><?= htmlspecialchars($booking['bus_type']) ?></span>
                </div>
                <div>
                    <div class="info-label">Bus Registration No.</div>
                    <div class="info-value font-monospace"><?= htmlspecialchars($booking['bus_number']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Boarding/Dropping Milestones -->
    <div class="row g-4 mb-4">
        <div class="col-md-6">
            <div class="pdf-info-card" style="border-left: 4px solid var(--pdf-primary);">
                <div class="info-label text-success"><i class="fa-solid fa-circle-arrow-up me-1"></i>Boarding Point</div>
                <div class="info-value mt-1"><?= htmlspecialchars($booking['boarding_point']) ?></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="pdf-info-card" style="border-left: 4px solid var(--pdf-gold);">
                <div class="info-label text-danger"><i class="fa-solid fa-circle-arrow-down me-1"></i>Dropping Point</div>
                <div class="info-value mt-1"><?= htmlspecialchars($booking['dropping_point']) ?></div>
            </div>
        </div>
    </div>

    <!-- 4. Passenger Information Table Component -->
    <?php render_pdf_section_title("Passenger Details", "fa-solid fa-users"); ?>
    <table class="pdf-table">
        <thead>
            <tr>
                <th style="width: 15%;">Seat No.</th>
                <th>Passenger Name</th>
                <th style="width: 30%;">Age / Gender</th>
                <th style="width: 25%; text-align: right;">Fare Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($passengers as $p): ?>
                <tr>
                    <td class="font-monospace fw-bold text-success"><?= htmlspecialchars($p['seat_number']) ?></td>
                    <td class="fw-semibold"><?= htmlspecialchars($p['passenger_name']) ?></td>
                    <td><?= htmlspecialchars($p['passenger_age']) ?> yrs / <?= htmlspecialchars($p['passenger_gender']) ?></td>
                    <td class="text-end fw-semibold">₹<?= number_format($p['price'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- Contact & Fare Summary Row -->
    <div class="row g-4 mb-4">
        <!-- Support & Customer Info -->
        <div class="col-md-6">
            <div class="pdf-info-card">
                <div class="mb-3">
                    <div class="info-label"><i class="fa-solid fa-user me-1"></i>Primary Contact Details</div>
                    <div class="fw-bold"><?= htmlspecialchars($booking['customer_name']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($booking['customer_email']) ?> | <?= htmlspecialchars($booking['customer_phone']) ?></div>
                </div>
                <hr style="margin: 10px 0; opacity: 0.15;">
                <div>
                    <div class="info-label"><i class="fa-solid fa-headset me-1"></i>Support Contact</div>
                    <div class="small">
                        <strong>Operator:</strong> <?= htmlspecialchars($operator['operator_name']) ?><br>
                        <strong>Phone:</strong> <?= htmlspecialchars($operator['contact_number']) ?> | WhatsApp: <?= htmlspecialchars($operator['whatsapp_number']) ?><br>
                        <strong>Emergency:</strong> <span class="text-danger fw-bold"><?= htmlspecialchars($operator['emergency_number']) ?></span>
                    </div>
                </div>
            </div>
        </div>
        <!-- 5. Summary Component -->
        <div class="col-md-6 text-md-end">
            <?php
                $show_discount = true;
                $base_fare_disp = floatval($booking['base_fare']);
                $gst_amount_disp = floatval($booking['gst_amount']);
                $total_paid_disp = floatval($booking['total_amount']);

                if ($is_customer_copy && $booking['booking_source'] === 'agent') {
                    $show_discount = false;
                    $base_fare_disp = floatval($booking['base_fare']) + floatval($booking['discount_amount']);
                    $gst_amount_disp = ($base_fare_disp * floatval($booking['gst_rate'])) / 100.00;
                    $total_paid_disp = $base_fare_disp + $gst_amount_disp;
                }
            ?>
            <div class="pdf-summary-box d-block ms-md-auto">
                <h6 class="info-label text-start border-bottom pb-2 mb-3">Fare Breakdown</h6>
                <div class="pdf-summary-item">
                    <span>Base Fare:</span>
                    <strong>₹<?= number_format($base_fare_disp, 2) ?></strong>
                </div>
                <?php if ($show_discount && $booking['discount_amount'] > 0): ?>
                    <div class="pdf-summary-item text-success">
                        <span>Discount Applied:</span>
                        <strong>-₹<?= number_format($booking['discount_amount'], 2) ?></strong>
                    </div>
                <?php endif; ?>
                <div class="pdf-summary-item">
                    <span>GST (<?= number_format($booking['gst_rate'], 1) ?>%):</span>
                    <strong>₹<?= number_format($gst_amount_disp, 2) ?></strong>
                </div>
                <!-- SGST & CGST Split -->
                <div class="pdf-summary-item text-muted" style="font-size: 0.75rem; padding-left: 15px;">
                    <span>CGST (<?= number_format($booking['gst_rate']/2, 2) ?>%):</span>
                    <strong>₹<?= number_format($gst_amount_disp/2, 2) ?></strong>
                </div>
                <div class="pdf-summary-item text-muted" style="font-size: 0.75rem; padding-left: 15px;">
                    <span>SGST (<?= number_format($booking['gst_rate']/2, 2) ?>%):</span>
                    <strong>₹<?= number_format($gst_amount_disp/2, 2) ?></strong>
                </div>
                <?php 
                $invoice_convenience = floatval($total_paid_disp) - (floatval($base_fare_disp) + floatval($gst_amount_disp));
                if ($invoice_convenience > 0.01):
                ?>
                    <div class="pdf-summary-item">
                        <span>Convenience Fee:</span>
                        <strong>₹<?= number_format($invoice_convenience, 2) ?></strong>
                    </div>
                <?php endif; ?>
                <div class="pdf-summary-total">
                    <span>Total Paid:</span>
                    <span>₹<?= number_format($total_paid_disp, 2) ?></span>
                </div>
                <div class="small text-muted text-start mt-2" style="font-size: 0.7rem;">*GST included as per applicable government regulations.</div>
            </div>
        </div>
    </div>

    <!-- Guidelines -->
    <div class="mt-4 p-3 rounded bg-light bg-opacity-50 small text-center text-muted border">
        <i class="fa-solid fa-circle-exclamation me-1 text-success"></i> 
        Please arrive at the boarding point at least 15 minutes before the departure time. Carry this ticket along with a valid ID proof.
    </div>

    <?php render_pdf_footer(); ?>
</div>
