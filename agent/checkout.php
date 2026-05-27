<?php
/**
 * Agent Portal Checkout Page (Includes direct discount and bypasses customer account details restriction)
 */
require_once __DIR__ . '/header.php';

$page_title = "Checkout";

$trip_id = $_POST['trip_id'] ?? '';
$selected_seats = $_POST['selected_seats'] ?? '';

if (empty($trip_id) || empty($selected_seats)) {
    header("Location: " . BASE_URL . "/agent/search.php");
    exit();
}

$seats = explode(',', $selected_seats);

// Fetch Trip details and check ownership
$trip_stmt = $pdo->prepare("
    SELECT t.base_fare, t.discount_type, t.percentage, t.fixed, b.bus_name, r.source, r.destination 
    FROM trips t 
    JOIN buses b ON t.bus_id = b.id
    JOIN routes r ON t.route_id = r.id
    WHERE t.id = ? AND t.admin_id = ? AND t.status = 'active'
    LIMIT 1
");
$trip_stmt->execute([$trip_id, $parent_admin_id]);
$trip = $trip_stmt->fetch();

if (!$trip) {
    die("Trip details not found or unauthorized.");
}

$base_fare = floatval($trip['base_fare']);
$total_fare = 0;
$seat_fares = [];
$total_discount = 0;

$discount_val = 0;
if ($trip['discount_type'] === 'percentage') {
    $discount_val = floatval($trip['percentage']);
} elseif ($trip['discount_type'] === 'fixed') {
    $discount_val = floatval($trip['fixed']);
}

foreach ($seats as $seat) {
    $fare = $base_fare;
    if (strpos($seat, 'U') === 0) {
        $fare += 100; // Upper berth premium
    }
    $seat_fares[$seat] = $fare;
    $total_fare += $fare;

    $applied_discount = 0;
    if ($trip['discount_type'] === 'percentage') {
        $applied_discount = ($fare * $discount_val) / 100;
    } elseif ($trip['discount_type'] === 'fixed') {
        $applied_discount = $discount_val;
    }
    $total_discount += $applied_discount;
}

$total_discount = round($total_discount, 2);
$final_fare = max(0, $total_fare - $total_discount);

// Fetch boarding and dropping points from POST
$boarding_point = $_POST['boarding_point'] ?? '';
$dropping_point = $_POST['dropping_point'] ?? '';

// Load seat coordinates for adjacent female checking
$coords_stmt = $pdo->prepare("
    SELECT s.seat_number, s.row_pos, s.col_pos 
    FROM bus_seats s
    JOIN trips t ON s.bus_id = t.bus_id
    WHERE t.id = ? AND s.is_active = 1
");
$coords_stmt->execute([$trip_id]);
$coords_db = $coords_stmt->fetchAll(PDO::FETCH_ASSOC);

$seat_coords = [];
foreach ($coords_db as $c) {
    $seat_coords[$c['seat_number']] = [
        'row' => intval($c['row_pos']),
        'col' => intval($c['col_pos'])
    ];
}

// Booked female check
$female_booked_stmt = $pdo->prepare("
    SELECT DISTINCT bs.seat_number 
    FROM booking_seats bs
    JOIN bookings b ON bs.booking_id = b.id
    WHERE b.trip_id = ? AND b.status = 'active' AND bs.passenger_gender = 'Female'
");
$female_booked_stmt->execute([$trip_id]);
$female_booked_seats = $female_booked_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

$is_adjacent_to_female = [];
foreach ($seats as $seat) {
    $is_adjacent_to_female[$seat] = false;
    if (isset($seat_coords[$seat])) {
        $myRow = $seat_coords[$seat]['row'];
        $myCol = $seat_coords[$seat]['col'];
        
        $adj_col = -1;
        if ($myCol === 0) $adj_col = 1;
        elseif ($myCol === 1) $adj_col = 0;
        elseif ($myCol === 3) $adj_col = 4;
        elseif ($myCol === 4) $adj_col = 3;
        
        if ($adj_col !== -1) {
            foreach ($seat_coords as $sNum => $coord) {
                if ($coord['row'] === $myRow && $coord['col'] === $adj_col) {
                    if (in_array($sNum, $female_booked_seats)) {
                        $is_adjacent_to_female[$seat] = true;
                        break;
                    }
                }
            }
        }
    }
}
?>

<div class="row g-4">
    <!-- Passenger Form Column -->
    <div class="col-lg-8">
        <div class="glass-card p-5" style="border-radius: 20px;">
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-users text-indigo me-2"></i>Passenger Details (Partner Desk)</h4>
            
            <form id="paymentCheckForm" autocomplete="off">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" id="payment_csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="trip_id" value="<?= htmlspecialchars($trip_id) ?>">
                <input type="hidden" name="selected_seats" value="<?= htmlspecialchars($selected_seats) ?>">
                <input type="hidden" name="boarding_point" value="<?= htmlspecialchars($boarding_point) ?>">
                <input type="hidden" name="dropping_point" value="<?= htmlspecialchars($dropping_point) ?>">

                <!-- Loop seats -->
                <?php foreach ($seats as $idx => $seat): ?>
                    <div class="p-4 rounded-4 mb-4 border border-secondary border-opacity-20 bg-dark bg-opacity-20">
                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-secondary border-opacity-10 pb-2">
                            <h5 class="text-white fw-bold"><i class="fa-solid fa-chair text-indigo me-2"></i>Passenger #<?= $idx + 1 ?> <span class="text-secondary small font-monospace">(Seat <?= htmlspecialchars($seat) ?>)</span></h5>
                            <span class="badge bg-indigo">₹<?= number_format($seat_fares[$seat], 2) ?></span>
                        </div>
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label class="form-label text-secondary small fw-semibold">Full Name</label>
                                <input type="text" name="passenger_name[]" class="form-control form-control-swift" placeholder="Passenger full name" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-secondary small fw-semibold">Age</label>
                                <input type="number" name="passenger_age[]" class="form-control form-control-swift" placeholder="Age" min="5" max="100" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-secondary small fw-semibold">Gender</label>
                                <select name="passenger_gender[]" class="form-select form-control-swift" required>
                                    <?php if ($is_adjacent_to_female[$seat]): ?>
                                        <option value="Female" selected>Female</option>
                                        <option value="Other">Other</option>
                                    <?php else: ?>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    <?php endif; ?>
                                </select>
                                <?php if ($is_adjacent_to_female[$seat]): ?>
                                    <div class="text-warning small mt-1 font-semibold" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Adjacent to Female (Male not allowed)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Customer info to bypass separate customer account constraint -->
                <h4 class="fw-bold text-white mt-5 mb-4"><i class="fa-solid fa-address-book text-pink me-2"></i>Passenger Contact Information</h4>
                <div class="p-4 rounded-4 border border-secondary border-opacity-20 bg-dark bg-opacity-20 mb-4">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Passenger Contact Name</label>
                            <input type="text" name="contact_name" class="form-control form-control-swift" placeholder="Contact Full Name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Passenger Email Address</label>
                            <input type="email" name="contact_email" class="form-control form-control-swift" placeholder="passenger@domain.com" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Passenger Mobile Number</label>
                            <input type="tel" name="contact_phone" class="form-control form-control-swift" placeholder="Passenger 10-digit mobile" required>
                        </div>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="button" id="btnInitiatePayment" class="btn btn-primary-gradient py-3 text-uppercase fw-bold" style="border-radius: 12px; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-check-double me-2"></i>Process Booking & Issue Ticket
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Panel -->
    <div class="col-lg-4">
        <div class="glass-card p-4 shadow-lg" style="border-radius: 20px; position: sticky; top: 100px;">
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-receipt text-pink me-2"></i>Fare Details</h4>
            <div class="mb-4">
                <span class="text-secondary small d-block">Voyage Class</span>
                <span class="text-white fw-bold"><?= htmlspecialchars($trip['bus_name']) ?></span>
            </div>
            
            <div class="mb-4">
                <span class="text-secondary small d-block">Stations Route</span>
                <span class="text-white fw-semibold"><?= htmlspecialchars($trip['source']) ?> to <?= htmlspecialchars($trip['destination']) ?></span>
            </div>

            <div class="border-top border-secondary border-opacity-20 pt-3">
                <div class="d-flex justify-content-between text-secondary small mb-2">
                    <span>Seats (<?= count($seats) ?> berths)</span>
                    <span class="text-white fw-semibold"><?= htmlspecialchars($selected_seats) ?></span>
                </div>
                <div class="d-flex justify-content-between text-secondary small mb-3">
                    <span>Original Gross Fare</span>
                    <span>₹<?= number_format($total_fare, 2) ?></span>
                </div>
                <?php if ($total_discount > 0): ?>
                    <div class="d-flex justify-content-between text-secondary small mb-3">
                        <span>Agent Direct Discount</span>
                        <span class="text-success">-₹<?= number_format($total_discount, 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between text-white fw-bold fs-5 pt-3 border-top border-secondary border-opacity-30">
                    <span>Total Paid</span>
                    <span class="text-indigo">₹<?= number_format($final_fare, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    $('#btnInitiatePayment').click(function() {
        var form = $('#paymentCheckForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        if (!confirm("Confirm booking this ticket? The ticket will be booked immediately using the agent account discount settings.")) {
            return;
        }

        var originalBtnText = $('#btnInitiatePayment').html();
        $('#btnInitiatePayment').html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Issuing Ticket...').addClass('disabled');

        // Compile payload
        var formData = $('#paymentCheckForm').serialize();

        $.ajax({
            url: '<?= BASE_URL ?>/checkout.php?action=process_payment',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert("Ticket booked successfully!");
                    window.location.href = '<?= BASE_URL ?>/ticket.php?ref=' + response.booking_ref;
                } else {
                    alert("Booking Failed: " + response.message);
                    $('#btnInitiatePayment').html(originalBtnText).removeClass('disabled');
                }
            },
            error: function() {
                alert("CRITICAL ERROR: Failed to communicate with booking engine.");
                $('#btnInitiatePayment').html(originalBtnText).removeClass('disabled');
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
