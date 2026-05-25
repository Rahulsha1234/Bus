<?php
/**
 * Customer Booking History & Cancellations Dashboard
 */
require_once __DIR__ . '/includes/auth_middleware.php';

// Redirect to login if guest
if (!is_logged_in()) {
    $_SESSION['redirect_url'] = BASE_URL . "/bookings.php";
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$page_title = "My Booking History";
$customer_id = $_SESSION['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT 
            b.id AS booking_id,
            b.booking_reference,
            b.total_amount,
            b.discount_amount,
            b.promo_code,
            b.payment_status,
            b.boarding_point,
            b.dropping_point,
            b.created_at,
            b.status AS booking_status,
            t.departure_time,
            bs.bus_name,
            bs.bus_number,
            r.source,
            r.destination,
            (SELECT GROUP_CONCAT(seat_number ORDER BY seat_number ASC SEPARATOR ', ') 
             FROM booking_seats 
             WHERE booking_id = b.id) AS seat_numbers,
            cr.request_number AS cancel_req_num,
            cr.status AS cancel_req_status
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        LEFT JOIN cancellation_requests cr ON b.id = cr.booking_id
        WHERE b.customer_id = ?
        ORDER BY b.created_at DESC
    ");
    $stmt->execute([$customer_id]);
    $bookings = $stmt->fetchAll();

} catch (PDOException $e) {
    die("Error retrieving bookings: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="glass-card p-5 mb-5" style="border-radius: 20px;">
    <div class="d-flex align-items-center justify-content-between mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold text-white mb-1"><i class="fa-solid fa-receipt text-indigo me-2"></i>My Bookings</h3>
            <p class="text-secondary mb-0">View booking details, print boarding passes, and manage cancellations.</p>
        </div>
        <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary-gradient px-4 py-2"><i class="fa-solid fa-magnifying-glass me-2"></i>Book New Journey</a>
    </div>

    <?php if (empty($bookings)): ?>
        <div class="text-center py-5">
            <div class="text-secondary mb-3" style="font-size: 3rem;"><i class="fa-solid fa-ticket-simple"></i></div>
            <h5 class="text-white fw-bold">No Bookings Found</h5>
            <p class="text-secondary small">You have not booked any bus voyages yet.</p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover align-middle" style="background: transparent;">
                <thead>
                    <tr class="border-bottom border-secondary border-opacity-35 text-secondary small">
                        <th>Reference ID</th>
                        <th>Fleet / Bus Name</th>
                        <th>Journey Details</th>
                        <th>Seats</th>
                        <th>Amount</th>
                        <th>Booking Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($bookings as $b): ?>
                        <tr class="border-bottom border-secondary border-opacity-15">
                            <td>
                                <span class="font-monospace fw-bold text-indigo" style="color: #818cf8;"><?= htmlspecialchars($b['booking_reference']) ?></span>
                                <div class="text-secondary" style="font-size: 0.75rem;"><?= date('d M Y', strtotime($b['created_at'])) ?></div>
                            </td>
                            <td>
                                <span class="text-white fw-semibold"><?= htmlspecialchars($b['bus_name']) ?></span>
                                <div class="text-secondary font-monospace" style="font-size: 0.75rem;"><?= htmlspecialchars($b['bus_number']) ?></div>
                            </td>
                            <td>
                                <div class="text-white small fw-bold">
                                    <?= htmlspecialchars($b['source']) ?> <i class="fa-solid fa-arrow-right mx-1 text-secondary" style="font-size: 0.7rem;"></i> <?= htmlspecialchars($b['destination']) ?>
                                </div>
                                <div class="text-secondary small mt-1">
                                    <i class="fa-regular fa-clock me-1 text-indigo"></i><?= date('d M Y, H:i', strtotime($b['departure_time'])) ?>
                                </div>
                                <div class="text-secondary small mt-1" style="font-size: 0.75rem;">
                                    Board: <span class="text-white"><?= htmlspecialchars($b['boarding_point']) ?></span> | Drop: <span class="text-white"><?= htmlspecialchars($b['dropping_point']) ?></span>
                                </div>
                            </td>
                            <td>
                                <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($b['seat_numbers']) ?></span>
                            </td>
                             <td>
                                <span class="text-white fw-semibold d-block">₹<?= number_format($b['total_amount'], 2) ?></span>
                                <?php if ($b['discount_amount'] > 0): ?>
                                    <span class="text-success small d-block" style="font-size:0.75rem;">-₹<?= number_format($b['discount_amount'], 2) ?> (<?= htmlspecialchars($b['promo_code']) ?>)</span>
                                <?php endif; ?>
                             </td>
                            <td>
                                <?php if ($b['booking_status'] === 'cancelled'): ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">CANCELLED</span>
                                <?php elseif (!empty($b['cancel_req_status'])): ?>
                                    <?php if ($b['cancel_req_status'] === 'pending'): ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25" title="Request #: <?= $b['cancel_req_num'] ?>">CANCEL PENDING</span>
                                    <?php elseif ($b['cancel_req_status'] === 'approved'): ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25">CANCEL APPROVED</span>
                                    <?php else: ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">ACTIVE (REJECTED)</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25">ACTIVE & PAID</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <div class="btn-group gap-2">
                                    <a href="<?= BASE_URL ?>/ticket.php?ref=<?= urlencode($b['booking_reference']) ?>" class="btn btn-secondary-glass btn-sm" title="View Digital Ticket">
                                        <i class="fa-solid fa-eye"></i>
                                    </a>
                                    <a href="<?= BASE_URL ?>/ticket_pdf.php?ref=<?= urlencode($b['booking_reference']) ?>" target="_blank" class="btn btn-secondary-glass btn-sm" title="Download E-Ticket PDF / Reprint">
                                        <i class="fa-solid fa-file-pdf"></i>
                                    </a>
                                    
                                    <?php if ($b['booking_status'] === 'active' && empty($b['cancel_req_status'])): ?>
                                        <!-- Open Cancel Booking Modal/Link -->
                                        <a href="<?= BASE_URL ?>/cancellations.php?booking_id=<?= $b['booking_id'] ?>" class="btn btn-danger-glass btn-sm" title="Request Cancellation">
                                            <i class="fa-solid fa-ban"></i>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
