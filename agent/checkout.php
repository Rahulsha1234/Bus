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
    WHERE t.id = ? AND t.admin_id = ? AND t.status = 'ACTIVE'
    LIMIT 1
");
$trip_stmt->execute([$trip_id, $parent_admin_id]);
$trip = $trip_stmt->fetch();

if (!$trip) {
    die("Trip details not found or unauthorized.");
}

// Pre-warm helper tables (avoids DDL inside any later transaction)
ensure_refactor_tables_exist($pdo);

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
    // Use the same dynamic pricing helper as book.php and checkout.php
    $fare = get_actual_seat_price($pdo, $trip_id, $seat, $base_fare);
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
                        <i class="fa-solid fa-lock me-2"></i>Pay &amp; Issue Ticket &mdash; &#8377;<?= number_format($total_fare, 2) ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Panel -->
    <div class="col-lg-4">
        <div class="glass-card p-4 shadow-lg" style="border-radius: 20px; position: sticky; top: 100px;">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold text-white mb-0"><i class="fa-solid fa-receipt text-pink me-2"></i>Fare Details</h4>
                <button type="button" id="btnAgentTooltip" class="btn btn-secondary-glass py-1 px-2 rounded-3 small" style="font-size: 0.8rem;" title="View Agent Pricing Breakdown">
                    <i class="fa-solid fa-circle-info text-warning me-1"></i> Agent Info
                </button>
            </div>
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
                    <span>Base Ticket Fare</span>
                    <span>₹<?= number_format($total_fare, 2) ?></span>
                </div>
                <?php if (false && $total_discount > 0): ?>
                    <div class="d-flex justify-content-between text-secondary small mb-3">
                        <span>Agent Direct Discount</span>
                        <span class="text-success">-₹<?= number_format($total_discount, 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between text-white fw-bold fs-5 pt-3 border-top border-secondary border-opacity-30">
                    <span>Total Amount</span>
                    <span class="text-indigo">₹<?= number_format($total_fare, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MOCK RAZORPAY GATEWAY OVERLAY MODAL -->
<div class="modal fade" id="razorpayModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-secondary text-white shadow-2xl" style="border-radius: 24px; background: #121829;">
            <div class="modal-header border-secondary p-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="p-2 rounded-3 text-white d-flex align-items-center justify-content-center" style="background:#5252ff;"><i class="fa-solid fa-shield-halved"></i></span>
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0">Razorpay Secure Checkout</h6>
                        <span class="text-secondary small" style="font-size:0.75rem;">Merchant: <?= defined("SYSTEM_NAME") ? SYSTEM_NAME : "Bus Booking" ?> Inc.</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <span class="text-secondary small d-block">AMOUNT TO PAY</span>
                    <h2 class="fw-bold" style="font-size: 2.5rem; color:#0F5132;">&#8377;<?= number_format($final_fare, 2) ?></h2>
                    <?php if (false && $total_discount > 0): ?>
                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-25 px-3 py-1 mt-1">Agent Discount: -&#8377;<?= number_format($total_discount, 2) ?></span>
                    <?php endif; ?>
                </div>
                <div class="p-3 rounded-4 bg-dark bg-opacity-30 border border-secondary border-opacity-20 mb-4 small text-secondary">
                    <div class="d-flex justify-content-between mb-2"><span>Order Reference</span><span class="text-white font-monospace">AGT-<?= time() ?></span></div>
                    <div class="d-flex justify-content-between"><span>Seats</span><span class="text-white"><?= htmlspecialchars($selected_seats) ?></span></div>
                </div>
                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Select Payment Method</label>
                    <div class="d-grid gap-3">
                        <button type="button" class="btn btn-secondary-glass text-start py-3 px-3 d-flex align-items-center justify-content-between w-100 rounded-3 select-payment-opt" data-status="success">
                            <span><i class="fa-solid fa-credit-card text-success me-3"></i>Credit / Debit Card (Simulate Success)</span>
                            <i class="fa-solid fa-chevron-right text-secondary small"></i>
                        </button>
                        <button type="button" class="btn btn-secondary-glass text-start py-3 px-3 d-flex align-items-center justify-content-between w-100 rounded-3 select-payment-opt" data-status="failed">
                            <span><i class="fa-solid fa-circle-xmark text-danger me-3"></i>Simulate Failed Transaction</span>
                            <i class="fa-solid fa-chevron-right text-secondary small"></i>
                        </button>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-secondary p-4 d-flex justify-content-between align-items-center">
                <span class="text-secondary small" style="font-size: 0.75rem;"><i class="fa-solid fa-lock me-1"></i>256-bit SSL Encrypted Connection</span>
                <span class="text-secondary small font-monospace text-uppercase" style="font-size: 0.75rem;">Razorpay v3</span>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Open Razorpay modal on button click
    $('#btnInitiatePayment').click(function() {
        var form = $('#paymentCheckForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }
        $('#razorpayModal').modal('show');
    });

    // Handle payment simulation
    $('.select-payment-opt').click(function() {
        var status = $(this).data('status');
        if (status === 'failed') {
            alert('Mock Payment Failed: Simulated transaction failure. Please use card simulation for success.');
            $('#razorpayModal').modal('hide');
            return;
        }
        $('#razorpayModal').modal('hide');
        var originalBtnText = $('#btnInitiatePayment').html();
        $('#btnInitiatePayment').html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing Secure Transaction...').addClass('disabled');
        var formData = $('#paymentCheckForm').serialize();
        $.ajax({
            url: '<?= BASE_URL ?>/checkout.php?action=process_payment',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    window.location.href = '<?= BASE_URL ?>/ticket.php?ref=' + response.booking_ref;
                } else {
                    alert('Booking Error: ' + response.message);
                    $('#btnInitiatePayment').html(originalBtnText).removeClass('disabled');
                }
            },
            error: function() {
                alert('CRITICAL ERROR: Failed to communicate with payment processor.');
                $('#btnInitiatePayment').html(originalBtnText).removeClass('disabled');
            }
        });
    });

    $('#btnAgentTooltip').click(function() {
        $('#agentInfoModal').modal('show');
    });
});
</script>

<!-- AGENT INFO MODAL -->
<div class="modal fade" id="agentInfoModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-secondary text-white shadow-2xl" style="border-radius: 20px; background: #121829;">
            <div class="modal-header border-secondary p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-user-secret text-warning me-2"></i>Agent Pricing Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between mb-2 text-secondary small">
                    <span>Seats Selected:</span>
                    <span class="text-white fw-bold font-monospace"><?= htmlspecialchars($selected_seats) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 text-secondary small">
                    <span>Public Gross Fare:</span>
                    <span class="text-white">₹<?= number_format($total_fare, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 text-warning small">
                    <span>Agent Discount:</span>
                    <span class="fw-bold">₹<?= number_format($total_discount, 2) ?></span>
                </div>
                <hr class="border-secondary border-opacity-30 my-3">
                <div class="d-flex justify-content-between align-items-center text-white fw-bold fs-5">
                    <span>Net Payable (Agent):</span>
                    <span class="text-success">₹<?= number_format($final_fare, 2) ?></span>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-dark bg-opacity-20 text-center text-secondary small" style="border-radius: 0 0 20px 20px; justify-content: center;">
                <span><i class="fa-solid fa-shield-halved me-1"></i> Customer will only see public fare on checkout.</span>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
