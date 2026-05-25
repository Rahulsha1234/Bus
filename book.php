<?php
/**
 * Seat Booking Page
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$trip_id = $_GET['trip_id'] ?? '';
if (empty($trip_id)) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$page_title = "Select Seats";

// Fetch Trip Details
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id AS trip_id,
            t.departure_time,
            t.arrival_time,
            t.base_fare,
            b.bus_name,
            b.bus_type,
            b.seat_layout_type,
            b.total_seats,
            r.source,
            r.destination
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        JOIN routes r ON t.route_id = r.id
        WHERE t.id = :trip_id
        LIMIT 1
    ");
    $stmt->execute([':trip_id' => $trip_id]);
    $trip = $stmt->fetch();
    
    if (!$trip) {
        die("Trip not found.");
    }
    
    // Fetch seat statuses
    $seats_stmt = $pdo->prepare("
        SELECT seat_number, status, hold_expires_at 
        FROM trip_seats 
        WHERE trip_id = :trip_id
    ");
    $seats_stmt->execute([':trip_id' => $trip_id]);
    $db_seats = $seats_stmt->fetchAll();
    
    // Convert to easy lookup array
    $seat_status_lookup = [];
    $now = date('Y-m-d H:i:s');
    foreach ($db_seats as $s) {
        $status = $s['status'];
        // If status is hold but expired, treat as available
        if ($status === 'hold' && !empty($s['hold_expires_at']) && strtotime($s['hold_expires_at']) < strtotime($now)) {
            $status = 'available';
        }
        $seat_status_lookup[$s['seat_number']] = $status;
    }
} catch (PDOException $e) {
    die("Error fetching trip seat mapping: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-4">
    <!-- Seat Layout Selection Section -->
    <div class="col-lg-7">
        <div class="glass-card p-4" style="border-radius: 20px;">
            <div class="d-flex align-items-center justify-content-between mb-4 border-bottom border-secondary pb-3">
                <div>
                    <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-chair text-indigo me-2"></i>Select Your Seat</h4>
                    <span class="text-secondary small">Click on seat to select (Max 6 seats)</span>
                </div>
                <!-- Interactive Legend -->
                <div class="d-flex gap-2 flex-wrap">
                    <div class="legend-item"><span class="legend-dot" style="background: rgba(16, 185, 129, 0.2); border: 1px solid var(--seat-available);"></span><span class="small text-secondary">Available</span></div>
                    <div class="legend-item"><span class="legend-dot" style="background: var(--accent-indigo);"></span><span class="small text-secondary">Selected</span></div>
                    <div class="legend-item"><span class="legend-dot" style="background: rgba(239, 68, 68, 0.2); border: 1px solid var(--seat-booked);"></span><span class="small text-secondary">Booked</span></div>
                    <div class="legend-item"><span class="legend-dot" style="background: rgba(245, 158, 11, 0.2); border: 1px solid var(--seat-hold);"></span><span class="small text-secondary">Hold</span></div>
                </div>
            </div>

            <!-- Seat Layout Grid -->
            <div class="text-center py-4">
                <?php if ($trip['seat_layout_type'] === '2x1_sleeper'): ?>
                    <!-- Tabs for Lower and Upper Berth -->
                    <ul class="nav nav-pills justify-content-center mb-4 gap-2" id="berthTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-secondary-glass active px-4 py-2" id="lower-tab" data-bs-toggle="pill" data-bs-target="#lower-berth" type="button" role="tab">Lower Berth</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link btn-secondary-glass px-4 py-2" id="upper-tab" data-bs-toggle="pill" data-bs-target="#upper-berth" type="button" role="tab">Upper Berth</button>
                        </li>
                    </ul>

                    <div class="tab-content" id="berthTabContent">
                        <!-- Lower Berth Grid -->
                        <div class="tab-pane fade show active" id="lower-berth" role="tabpanel">
                            <div class="seat-map-container shadow-lg">
                                <div class="d-flex justify-content-between mb-4 pb-2 border-bottom border-secondary border-opacity-20">
                                    <span class="text-secondary small fw-semibold">FRONT</span>
                                    <span class="text-muted small"><i class="fa-solid fa-steering-wheel me-2"></i>DRIVER</span>
                                </div>
                                <div class="seat-grid-sleeper">
                                    <?php 
                                    // Sleeper berths: L1 to L15 (Columns: 2 Seats, 1 Walkway, 1 Seat)
                                    for ($i = 1; $i <= 15; $i++) {
                                        $seatNum = "L" . $i;
                                        $status = $seat_status_lookup[$seatNum] ?? 'available';
                                        
                                        // Print seat
                                        echo '<div class="seat sleeper-berth ' . $status . '" data-seat="' . $seatNum . '" data-price="' . $trip['base_fare'] . '">' . $seatNum . '</div>';
                                        
                                        // After every 2 seats, print walkthrough gap except for column alignment
                                        if ($i % 2 === 0 && ($i + 1) % 3 === 0) {
                                            echo '<div class="seat-walkway"></div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>

                        <!-- Upper Berth Grid -->
                        <div class="tab-pane fade" id="upper-berth" role="tabpanel">
                            <div class="seat-map-container shadow-lg">
                                <div class="d-flex justify-content-between mb-4 pb-2 border-bottom border-secondary border-opacity-20">
                                    <span class="text-secondary small fw-semibold">FRONT</span>
                                    <span class="text-muted small"><i class="fa-solid fa-steering-wheel me-2"></i>DRIVER</span>
                                </div>
                                <div class="seat-grid-sleeper">
                                    <?php 
                                    // Upper berths: U1 to U15
                                    for ($i = 1; $i <= 15; $i++) {
                                        $seatNum = "U" . $i;
                                        $status = $seat_status_lookup[$seatNum] ?? 'available';
                                        
                                        echo '<div class="seat sleeper-berth ' . $status . '" data-seat="' . $seatNum . '" data-price="' . ($trip['base_fare'] + 100) . '">' . $seatNum . '</div>'; // Upper berths get a small premium
                                        
                                        if ($i % 2 === 0 && ($i + 1) % 3 === 0) {
                                            echo '<div class="seat-walkway"></div>';
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Standard Seater layout 2x2 -->
                    <div class="seat-map-container shadow-lg" style="max-width: 450px;">
                        <div class="d-flex justify-content-between mb-4 pb-2 border-bottom border-secondary border-opacity-20">
                            <span class="text-secondary small fw-semibold font-monospace">FRONT / ENGINE</span>
                            <span class="text-secondary small"><i class="fa-solid fa-dharmachakra fa-spin me-2" style="color: #64748b;"></i>DRIVER</span>
                        </div>
                        
                        <div class="seat-grid-seater">
                            <?php 
                            // 40 Seater Seats: Rows of 4 seats (1, 2, Walkway, 3, 4)
                            for ($i = 1; $i <= 40; $i++) {
                                $seatNum = strval($i);
                                $status = $seat_status_lookup[$seatNum] ?? 'available';
                                
                                // Render seat
                                echo '<div class="seat ' . $status . '" data-seat="' . $seatNum . '" data-price="' . $trip['base_fare'] . '">' . $seatNum . '</div>';
                                
                                // Insert walkway in the middle (after 2nd column)
                                if ($i % 4 === 2) {
                                    echo '<div class="seat-walkway"></div>';
                                }
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Booking Summary Sidebar -->
    <div class="col-lg-5">
        <div class="glass-card p-4 h-100" style="border-radius: 20px;">
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-file-invoice-dollar text-pink me-2"></i>Trip Summary</h4>
            
            <div class="mb-4">
                <span class="text-secondary small d-block">Operator Fleet</span>
                <span class="text-white fw-bold fs-5"><?= htmlspecialchars($trip['bus_name']) ?></span>
                <span class="badge bg-indigo ms-2 text-uppercase" style="font-size:0.7rem;"><?= htmlspecialchars($trip['bus_type']) ?></span>
            </div>

            <div class="row mb-4 border-bottom border-secondary border-opacity-30 pb-3">
                <div class="col-6">
                    <span class="text-secondary small d-block">Departure Route</span>
                    <span class="text-white fw-semibold"><?= htmlspecialchars($trip['source']) ?> to <?= htmlspecialchars($trip['destination']) ?></span>
                </div>
                <div class="col-6 text-end">
                    <span class="text-secondary small d-block">Date & Time</span>
                    <span class="text-white fw-semibold"><?= date('d M, H:i', strtotime($trip['departure_time'])) ?></span>
                </div>
            </div>

            <!-- Seat calculations -->
            <div class="mb-4">
                <h6 class="text-indigo fw-bold small text-uppercase mb-3">Selected Seats</h6>
                <div id="no-seat-warning" class="text-secondary small p-3 rounded bg-dark bg-opacity-20 border border-secondary border-opacity-10 text-center">
                    No seats selected yet. Please tap on available seats.
                </div>
                
                <ul class="list-group list-group-flush" id="selected-seats-list" style="display: none; background:transparent;">
                    <!-- Appended dynamically -->
                </ul>
            </div>

            <!-- Dynamic Invoice Summary -->
            <div class="p-3 rounded-4 bg-dark bg-opacity-30 border border-secondary border-opacity-20 mb-4" id="invoice-block" style="display: none;">
                <div class="d-flex justify-content-between small text-secondary mb-2">
                    <span>Base Ticket Fare</span>
                    <span id="invoice-base-fare">₹0.00</span>
                </div>
                <div class="d-flex justify-content-between small text-secondary mb-3">
                    <span>Admin Processing GST (Included)</span>
                    <span>₹0.00</span>
                </div>
                <div class="d-flex justify-content-between text-white fw-bold fs-5 pt-2 border-top border-secondary border-opacity-40">
                    <span>Total Amount</span>
                    <span class="text-indigo" id="invoice-total">₹0.00</span>
                </div>
            </div>

            <!-- Proceed Form -->
            <form action="<?= BASE_URL ?>/checkout.php" method="POST" id="seatProceedForm">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="trip_id" value="<?= htmlspecialchars($trip_id) ?>">
                <input type="hidden" name="selected_seats" id="hidden_selected_seats" value="">
                
                <button type="submit" id="btnProceedCheckout" class="btn btn-primary-gradient w-100 py-3 text-uppercase fw-bold disabled" style="border-radius: 12px; letter-spacing: 0.5px;">
                    <i class="fa-solid fa-circle-arrow-right me-2"></i>Proceed to passenger details
                </button>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var selectedSeats = [];
    var maxSeats = 6;

    // Handle Seat Click
    $('.seat.available').click(function() {
        var seatNum = $(this).data('seat');
        var seatPrice = parseFloat($(this).data('price'));

        if ($(this).hasClass('selected')) {
            // Remove from selected
            $(this).removeClass('selected');
            selectedSeats = selectedSeats.filter(item => item.number !== seatNum);
        } else {
            // Check max limit
            if (selectedSeats.length >= maxSeats) {
                alert("You can select up to " + maxSeats + " seats per ticket.");
                return;
            }
            // Add to selected
            $(this).addClass('selected');
            selectedSeats.push({ number: seatNum, price: seatPrice });
        }

        updateInvoice();
    });

    function updateInvoice() {
        if (selectedSeats.length === 0) {
            $('#no-seat-warning').show();
            $('#selected-seats-list').hide();
            $('#invoice-block').hide();
            $('#btnProceedCheckout').addClass('disabled');
            $('#hidden_selected_seats').val('');
            return;
        }

        $('#no-seat-warning').hide();
        $('#selected-seats-list').show().empty();
        $('#invoice-block').show();
        $('#btnProceedCheckout').removeClass('disabled');

        var totalFare = 0;
        var seatNumsOnly = [];

        selectedSeats.forEach(function(seat) {
            totalFare += seat.price;
            seatNumsOnly.push(seat.number);

            $('#selected-seats-list').append(
                '<li class="list-group-item d-flex justify-content-between align-items-center bg-transparent border-secondary border-opacity-10 text-white py-2 px-0 small">' +
                '<span><i class="fa-solid fa-chair text-indigo me-2"></i>Seat ' + seat.number + '</span>' +
                '<span class="fw-semibold">₹' + seat.price.toFixed(2) + '</span>' +
                '</li>'
            );
        });

        $('#invoice-base-fare').text('₹' + totalFare.toFixed(2));
        $('#invoice-total').text('₹' + totalFare.toFixed(2));
        
        // Populate form inputs
        $('#hidden_selected_seats').val(seatNumsOnly.join(','));
    }
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
