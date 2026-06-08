<?php
/**
 * Agent Portal Checkout Page (Includes direct discount and bypasses customer account details restriction)
 */
require_once __DIR__ . '/header.php';

$page_title = __('checkout', 'Checkout');

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

// Fetch current agent's wallet status and balance
$wallet_stmt = $pdo->prepare("SELECT balance, status FROM agent_wallets WHERE agent_id = ?");
$wallet_stmt->execute([$_SESSION['user_id']]);
$wallet = $wallet_stmt->fetch() ?: ['balance' => 0.00, 'status' => 'active'];
$wallet_balance = floatval($wallet['balance']);
$wallet_status = $wallet['status'];


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
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-users text-indigo me-2"></i><?= __('passenger_details_partner', 'Passenger Details (Partner Desk)') ?></h4>
            
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
                            <h5 class="text-white fw-bold"><i class="fa-solid fa-chair text-indigo me-2"></i><?= __('passenger', 'Passenger') ?> #<?= $idx + 1 ?> <span class="text-secondary small font-monospace">(<?= __('seat', 'Seat') ?> <?= htmlspecialchars($seat) ?>)</span></h5>
                            <span class="badge bg-indigo">₹<?= number_format($seat_fares[$seat], 2) ?></span>
                        </div>
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label class="form-label text-secondary small fw-semibold"><?= __('name', 'Name') ?></label>
                                <input type="text" name="passenger_name[]" class="form-control form-control-swift" placeholder="<?= __('passenger_name', 'Passenger Name') ?>" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-secondary small fw-semibold"><?= __('age', 'Age') ?></label>
                                <input type="number" name="passenger_age[]" class="form-control form-control-swift" placeholder="<?= __('age', 'Age') ?>" min="5" max="100" required>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label class="form-label text-secondary small fw-semibold"><?= __('gender', 'Gender') ?></label>
                                <select name="passenger_gender[]" class="form-select form-control-swift" required>
                                    <?php if ($is_adjacent_to_female[$seat]): ?>
                                        <option value="Female" selected><?= __('female', 'Female') ?></option>
                                        <option value="Other"><?= __('other', 'Other') ?></option>
                                    <?php else: ?>
                                        <option value="Male"><?= __('male', 'Male') ?></option>
                                        <option value="Female"><?= __('female', 'Female') ?></option>
                                        <option value="Other"><?= __('other', 'Other') ?></option>
                                    <?php endif; ?>
                                </select>
                                <?php if ($is_adjacent_to_female[$seat]): ?>
                                    <div class="text-warning small mt-1 font-semibold" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> <?= __('female_adjacent_restriction', 'Adjacent to Female (Male not allowed)') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Customer info to bypass separate customer account constraint -->
                <h4 class="fw-bold text-white mt-5 mb-4"><i class="fa-solid fa-address-book text-pink me-2"></i><?= __('passenger_contact_info', 'Passenger Contact Information') ?></h4>
                <div class="p-4 rounded-4 border border-secondary border-opacity-20 bg-dark bg-opacity-20 mb-4">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold"><?= __('passenger_contact_name', 'Passenger Contact Name') ?></label>
                            <input type="text" name="contact_name" class="form-control form-control-swift" placeholder="<?= __('name', 'Name') ?>" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold"><?= __('passenger_email_address', 'Passenger Email Address') ?></label>
                            <input type="email" name="contact_email" class="form-control form-control-swift" placeholder="passenger@domain.com" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold"><?= __('passenger_mobile_number', 'Passenger Mobile Number') ?></label>
                            <input type="tel" name="contact_phone" class="form-control form-control-swift" placeholder="Passenger 10-digit mobile" required>
                        </div>
                    </div>
                </div>
                <div class="d-grid mt-4">
                    <button type="button" id="btnInitiatePayment" class="btn btn-primary-gradient py-3 text-uppercase fw-bold" style="border-radius: 12px; letter-spacing: 0.5px;" <?= ($wallet_status === 'frozen') ? 'disabled' : '' ?>>
                        <?php if ($wallet_status === 'frozen'): ?>
                            <i class="fa-solid fa-ban me-2"></i>Wallet Frozen
                        <?php else: ?>
                            <i class="fa-solid fa-wallet me-2"></i>Pay & Book using Wallet
                        <?php endif; ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Panel -->
    <div class="col-lg-4">
        <div class="glass-card p-4 shadow-lg" style="border-radius: 20px; position: sticky; top: 100px;">
            <div class="d-flex align-items-center justify-content-between mb-4">
                <h4 class="fw-bold text-white mb-0"><i class="fa-solid fa-receipt text-pink me-2"></i><?= __('fare_details', 'Fare Details') ?></h4>
                <button type="button" id="btnAgentTooltip" class="btn btn-secondary-glass py-1 px-2 rounded-3 small" style="font-size: 0.8rem;" title="<?= __('view_agent_breakdown', 'View Agent Pricing Breakdown') ?>">
                    <i class="fa-solid fa-circle-info text-warning me-1"></i> <?= __('agent_info', 'Agent Info') ?>
                </button>
            </div>
            <div class="mb-4">
                <span class="text-secondary small d-block"><?= __('voyage_class', 'Voyage Class') ?></span>
                <span class="text-white fw-bold"><?= htmlspecialchars($trip['bus_name']) ?></span>
            </div>
            
            <div class="mb-4">
                <span class="text-secondary small d-block"><?= __('stations_route', 'Stations Route') ?></span>
                <span class="text-white fw-semibold"><?= htmlspecialchars($trip['source']) ?> <?= __('to', 'to') ?> <?= htmlspecialchars($trip['destination']) ?></span>
            </div>

            <div class="border-top border-secondary border-opacity-20 pt-3">
                <div class="d-flex justify-content-between text-secondary small mb-2">
                    <span><?= __('seats', 'Seats') ?> (<?= count($seats) ?> <?= __('seats_left', 'berths') ?>)</span>
                    <span class="text-white fw-semibold"><?= htmlspecialchars($selected_seats) ?></span>
                </div>
                <div class="d-flex justify-content-between text-secondary small mb-2">
                    <span><?= __('base_ticket_fare', 'Base Ticket Fare') ?></span>
                    <span>₹<?= number_format($total_fare, 2) ?></span>
                </div>
                <?php if ($total_discount > 0): ?>
                    <div class="d-flex justify-content-between text-secondary small mb-2 text-warning">
                        <span>Agent Partner Discount</span>
                        <span>-₹<?= number_format($total_discount, 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between text-white fw-bold fs-5 pt-3 border-top border-secondary border-opacity-30 mb-3">
                    <span>Net Payable</span>
                    <span class="text-indigo">₹<?= number_format($final_fare, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between text-secondary small mb-2">
                    <span>Wallet Balance</span>
                    <span class="text-info fw-bold">₹<span id="agent-display-balance"><?= number_format($wallet_balance, 2) ?></span></span>
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
                        <span class="text-secondary small" style="font-size:0.75rem;">Merchant: <?= SYSTEM_NAME ?> Inc.</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <span class="text-secondary small d-block">AMOUNT TO RECHARGE</span>
                    <h2 class="fw-bold" style="font-size: 2.5rem; color:#ffc107;">₹<span id="razorpay-recharge-amount">0.00</span></h2>
                </div>
                <div class="p-3 rounded-4 bg-dark bg-opacity-30 border border-secondary border-opacity-20 mb-4 small text-secondary">
                    <div class="d-flex justify-content-between mb-2"><span>Order Reference</span><span class="text-white font-monospace" id="razorpay-order-ref">RECH-<?= time() ?></span></div>
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

<!-- WALLET RECHARGE CHOOSE AMOUNT MODAL -->
<div class="modal fade" id="walletRechargeModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-secondary text-white shadow-2xl" style="border-radius: 24px; background: #121829;">
            <div class="modal-header border-secondary p-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="p-2 rounded-3 text-white d-flex align-items-center justify-content-center" style="background:#ffc107; color: #000 !important;"><i class="fa-solid fa-wallet text-dark"></i></span>
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0">Insufficient Wallet Balance</h6>
                        <span class="text-secondary small" style="font-size:0.75rem;">Recharge wallet to continue booking</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="p-3 rounded-4 bg-dark bg-opacity-30 border border-secondary border-opacity-20 mb-4 small">
                    <div class="d-flex justify-content-between mb-2"><span>Total Fare</span><span class="text-white fw-bold">₹<span id="recharge-total-fare">0.00</span></span></div>
                    <div class="d-flex justify-content-between mb-2"><span>Wallet Balance</span><span class="text-white fw-bold">₹<span id="recharge-wallet-balance">0.00</span></span></div>
                    <div class="d-flex justify-content-between border-top border-secondary border-opacity-20 pt-2 text-warning fw-bold"><span>Shortfall</span><span>₹<span id="recharge-shortfall">0.00</span></span></div>
                </div>
                
                <div class="d-grid gap-3">
                    <button type="button" id="btnRechargeShortfall" class="btn btn-warning py-3 fw-bold text-dark">
                        <i class="fa-solid fa-credit-card me-2"></i>Recharge Shortfall (₹<span id="recharge-shortfall-btn">0.00</span>)
                    </button>
                    
                    <div class="border-top border-secondary border-opacity-20 my-2 pt-3 text-center small text-secondary">OR RECHARGE CUSTOM AMOUNT</div>
                    
                    <div class="input-group">
                        <span class="input-group-text bg-dark border-secondary text-secondary">₹</span>
                        <input type="number" id="customRechargeAmount" class="form-control form-control-swift bg-dark text-white border-secondary" placeholder="Enter custom amount (e.g. 1000)" min="1">
                        <button type="button" id="btnRechargeCustom" class="btn btn-indigo text-white px-4">Recharge Custom</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var rechargeAmount = 0;

    // Direct booking attempt when clicking Pay & Issue Ticket
    $('#btnInitiatePayment').click(function() {
        var form = $('#paymentCheckForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        processWalletBooking();
    });

    function processWalletBooking() {
        var originalBtnText = $('#btnInitiatePayment').html();
        $('#btnInitiatePayment').html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing Wallet Debit...').addClass('disabled');
        
        var formData = $('#paymentCheckForm').serialize();
        $.ajax({
            url: '<?= BASE_URL ?>/checkout.php?action=process_payment',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    window.location.href = '<?= BASE_URL ?>/ticket.php?ref=' + response.booking_ref;
                } else if (response.insufficient_wallet) {
                    // Show insufficient balance modal
                    $('#recharge-total-fare').text(Number(response.total_fare).toFixed(2));
                    $('#recharge-wallet-balance').text(Number(response.wallet_balance).toFixed(2));
                    $('#recharge-shortfall').text(Number(response.shortfall).toFixed(2));
                    $('#recharge-shortfall-btn').text(Number(response.shortfall).toFixed(2));
                    
                    // Reset custom amount field
                    $('#customRechargeAmount').val(Math.ceil(response.shortfall));
                    
                    $('#walletRechargeModal').modal('show');
                    $('#btnInitiatePayment').html(originalBtnText).removeClass('disabled');
                } else {
                    alert("Booking Error: " + response.message);
                    $('#btnInitiatePayment').html(originalBtnText).removeClass('disabled');
                }
            },
            error: function() {
                alert("CRITICAL ERROR: Failed to communicate with payment processor.");
                $('#btnInitiatePayment').html(originalBtnText).removeClass('disabled');
            }
        });
    }

    // Recharge options triggers
    $('#btnRechargeShortfall').click(function() {
        var shortfallVal = parseFloat($('#recharge-shortfall').text());
        if (isNaN(shortfallVal) || shortfallVal <= 0) return;
        rechargeAmount = shortfallVal;
        
        $('#walletRechargeModal').modal('hide');
        $('#razorpay-recharge-amount').text(rechargeAmount.toFixed(2));
        $('#razorpayModal').modal('show');
    });

    $('#btnRechargeCustom').click(function() {
        var customAmount = parseFloat($('#customRechargeAmount').val());
        var shortfallVal = parseFloat($('#recharge-shortfall').text());
        if (isNaN(customAmount) || customAmount <= 0) {
            alert("Please enter a valid recharge amount.");
            return;
        }
        if (customAmount < shortfallVal) {
            alert("Custom recharge amount must cover the shortfall of ₹" + shortfallVal.toFixed(2));
            return;
        }
        rechargeAmount = customAmount;
        
        $('#walletRechargeModal').modal('hide');
        $('#razorpay-recharge-amount').text(rechargeAmount.toFixed(2));
        $('#razorpayModal').modal('show');
    });

    // Handle payment simulation inside Razorpay Modal
    $('.select-payment-opt').click(function() {
        var status = $(this).data('status');
        if (status === 'failed') {
            alert("Simulated recharge failed. Please try again.");
            $('#razorpayModal').modal('hide');
            return;
        }
        
        $('#razorpayModal').modal('hide');
        
        // Call ajax/wallet_recharge.php to credit the wallet
        var mockPayId = 'pay_' + Math.random().toString(36).substr(2, 9);
        var mockOrderId = 'order_' + Math.random().toString(36).substr(2, 9);
        var mockSig = 'sig_' + Math.random().toString(36).substr(2, 9);
        
        $.ajax({
            url: '<?= BASE_URL ?>/ajax/wallet_recharge.php',
            type: 'POST',
            data: {
                csrf_token: $('#payment_csrf_token').val(),
                amount: rechargeAmount,
                razorpay_payment_id: mockPayId,
                razorpay_order_id: mockOrderId,
                razorpay_signature: mockSig
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert("Recharge of ₹" + rechargeAmount.toFixed(2) + " Successful! Continuing booking automatically...");
                    // Update display balance
                    $('#agent-display-balance').text(Number(response.new_balance).toFixed(2));
                    // Re-trigger the booking
                    processWalletBooking();
                } else {
                    alert("Recharge Error: " + response.message);
                }
            },
            error: function() {
                alert("CRITICAL ERROR: Failed to recharge wallet.");
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
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-user-secret text-warning me-2"></i><?= __('agent_pricing_details', 'Agent Pricing Details') ?></h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between mb-2 text-secondary small">
                    <span><?= __('seats_selected_colon', 'Seats Selected:') ?></span>
                    <span class="text-white fw-bold font-monospace"><?= htmlspecialchars($selected_seats) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 text-secondary small">
                    <span><?= __('public_gross_fare', 'Public Gross Fare:') ?></span>
                    <span class="text-white">₹<?= number_format($total_fare, 2) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 text-warning small">
                    <span><?= __('agent_discount_colon', 'Agent Discount:') ?></span>
                    <span class="fw-bold">₹<?= number_format($total_discount, 2) ?></span>
                </div>
                <hr class="border-secondary border-opacity-30 my-3">
                <div class="d-flex justify-content-between align-items-center text-white fw-bold fs-5">
                    <span><?= __('net_payable_agent', 'Net Payable (Agent):') ?></span>
                    <span class="text-success">₹<?= number_format($final_fare, 2) ?></span>
                </div>
            </div>
            <div class="modal-footer border-0 p-3 bg-dark bg-opacity-20 text-center text-secondary small" style="border-radius: 0 0 20px 20px; justify-content: center;">
                <span><i class="fa-solid fa-shield-halved me-1"></i> <?= __('agent_checkout_note', 'Customer will only see public fare on checkout.') ?></span>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
