<?php
/**
 * Agent Portal Seat Selection (Parent operator ownership checked)
 */
require_once __DIR__ . '/header.php';

$trip_id = intval($_GET['trip_id'] ?? 0);
if ($trip_id === 0) {
    header("Location: " . BASE_URL . "/agent/search.php");
    exit();
}

$page_title = "Select Seats";

// Fetch Trip details and verify ownership (trip must belong to parent operator admin_id)
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id AS trip_id,
            t.departure_time,
            t.arrival_time,
            t.base_fare,
            t.discount_type,
            t.percentage,
            t.fixed,
            b.id AS bus_id,
            b.bus_name,
            b.bus_type,
            b.seat_layout_type,
            b.total_seats,
            r.id AS route_id,
            r.source,
            r.destination,
            r.pickup_points,
            r.drop_points
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        JOIN routes r ON t.route_id = r.id
        WHERE t.id = :trip_id AND t.admin_id = :parent_admin_id AND t.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([
        ':trip_id' => $trip_id,
        ':parent_admin_id' => $parent_admin_id
    ]);
    $trip = $stmt->fetch();
    
    if (!$trip) {
        die("Trip not found or unauthorized.");
    }
    
    // Fetch Boarding and Dropping points
    $boarding_stations = $pdo->prepare("SELECT point_name AS name, departure_time AS time FROM boarding_points WHERE route_id = ?");
    $boarding_stations->execute([$trip['route_id']]);
    $boardings = $boarding_stations->fetchAll();
    
    if (empty($boardings)) {
        $boardings = json_decode($trip['pickup_points'], true) ?? [];
    }

    $dropping_stations = $pdo->prepare("SELECT point_name AS name, arrival_time AS time FROM dropping_points WHERE route_id = ?");
    $dropping_stations->execute([$trip['route_id']]);
    $droppings = $dropping_stations->fetchAll();

    if (empty($droppings)) {
        $droppings = json_decode($trip['drop_points'], true) ?? [];
    }

    // Fetch custom seating layout grid dimensions
    $layout_stmt = $pdo->prepare("SELECT rows_count, cols_count, layout_type FROM bus_layouts WHERE bus_id = ? LIMIT 1");
    $layout_stmt->execute([$trip['bus_id']]);
    $layout = $layout_stmt->fetch();

    $rows_count = $layout ? intval($layout['rows_count']) : 10;
    $cols_count = $layout ? intval($layout['cols_count']) : 5;

    // Fetch seat statuses & pricing overrides
    $seats_stmt = $pdo->prepare("
        SELECT 
            s.seat_number, s.row_pos, s.col_pos, s.seat_type, s.is_active,
            ts.status AS seat_status, ts.locked_at, ts.locked_by_session, ts.hold_expires_at,
            sp.base_price AS trip_base, sp.current_price AS trip_cur, sp.offer_price AS trip_off
        FROM bus_seats s
        LEFT JOIN trip_seats ts ON s.seat_number = ts.seat_number AND ts.trip_id = ?
        LEFT JOIN seat_pricing sp ON s.seat_number = sp.seat_number AND sp.trip_id = ?
        WHERE s.bus_id = ? AND s.is_active = 1
    ");
    $seats_stmt->execute([$trip_id, $trip_id, $trip['bus_id']]);
    $db_seats = $seats_stmt->fetchAll();

    // Map dynamic seats
    $seats_lookup = [];
    $now = date('Y-m-d H:i:s');
    $ten_mins_ago = date('Y-m-d H:i:s', strtotime('-10 minutes'));
    $session_id = session_id();

    // Load booked genders to check female protection rules
    $gender_stmt = $pdo->prepare("
        SELECT bs.seat_number, bs.passenger_gender 
        FROM booking_seats bs
        JOIN bookings b ON bs.booking_id = b.id
        WHERE b.trip_id = ? AND b.status = 'active'
    ");
    $gender_stmt->execute([$trip_id]);
    $booked_genders = $gender_stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Calculate agent fare discount per seat base
    $discount_preview = 0;
    if ($trip['discount_type'] === 'percentage') {
        $discount_preview = floatval($trip['percentage']); // percentage
    } elseif ($trip['discount_type'] === 'fixed') {
        $discount_preview = floatval($trip['fixed']); // fixed amount
    }

    // Parse statuses
    foreach ($db_seats as $s) {
        $seatNum = $s['seat_number'];
        $status = !empty($s['seat_status']) ? $s['seat_status'] : 'available';

        // Check locks expiration
        if ($status === 'temp_locked') {
            if (empty($s['locked_at']) || $s['locked_at'] <= $ten_mins_ago) {
                $status = 'available';
            } elseif ($s['locked_by_session'] === $session_id) {
                $status = 'selected';
            }
        }
        // Check holds expiration
        if ($status === 'hold' && !empty($s['hold_expires_at']) && $s['hold_expires_at'] < $now) {
            $status = 'available';
        }

        // Apply booked gender coloring overrides
        if ($status === 'booked' && ($booked_genders[$seatNum] ?? '') === 'Female') {
            $status = 'female_booked';
        }

        // Map pricing
        $seat_base = !empty($s['trip_off']) ? floatval($s['trip_off']) : (!empty($s['trip_cur']) ? floatval($s['trip_cur']) : (!empty($s['trip_base']) ? floatval($s['trip_base']) : floatval($trip['base_fare'])));

        // Calculate direct discounted fare for Agent view
        $applied_discount = 0;
        if ($trip['discount_type'] === 'percentage') {
            $applied_discount = ($seat_base * $discount_preview) / 100;
        } elseif ($trip['discount_type'] === 'fixed') {
            $applied_discount = $discount_preview;
        }
        $agent_final_fare = max(0, $seat_base - $applied_discount);

        $seats_lookup[$seatNum] = [
            'number' => $seatNum,
            'row' => intval($s['row_pos']),
            'col' => intval($s['col_pos']),
            'type' => $s['seat_type'] ?? 'Normal',
            'status' => $status,
            'original_price' => $seat_base,
            'price' => $agent_final_fare,
            'discount' => $applied_discount
        ];
    }

    // Filter out seats that are physically overlapped by a sleeper berth starting in the row above them
    $overlapped_seats = [];
    foreach ($seats_lookup as $sNum => $sInfo) {
        $isSleeper = (strpos($sInfo['type'], 'Sleeper') !== false);
        if ($isSleeper) {
            $target_row = $sInfo['row'] + 1;
            $target_col = $sInfo['col'];
            foreach ($seats_lookup as $otherNum => $otherInfo) {
                if ($otherInfo['row'] === $target_row && $otherInfo['col'] === $target_col && $otherNum !== $sNum) {
                    $overlapped_seats[] = $otherNum;
                }
            }
        }
    }
    foreach ($overlapped_seats as $oNum) {
        unset($seats_lookup[$oNum]);
    }

    // Apply adjacent Female Protection rules
    foreach ($seats_lookup as $seatNum => $sInfo) {
        if ($sInfo['status'] === 'female_booked') {
            $adj_col = -1;
            if ($sInfo['col'] === 0) $adj_col = 1;
            elseif ($sInfo['col'] === 1) $adj_col = 0;
            elseif ($sInfo['col'] === 3) $adj_col = 4;
            elseif ($sInfo['col'] === 4) $adj_col = 3;

            if ($adj_col !== -1) {
                foreach ($seats_lookup as $otherNum => $otherInfo) {
                    if ($otherInfo['row'] === $sInfo['row'] && $otherInfo['col'] === $adj_col && $otherInfo['status'] === 'available') {
                        $seats_lookup[$otherNum]['status'] = 'female_protected';
                    }
                }
            }
        }
    }

} catch (PDOException $e) {
    die("Error fetching voyage mapping: " . $e->getMessage());
}
?>

<div class="row g-4">
    <!-- Seating layout selection -->
    <div class="col-lg-7">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4 border-bottom border-secondary pb-3 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-chair text-indigo me-2"></i>Select Passenger Seat</h4>
                    <span class="text-secondary small">Tap on seats to choose. Red/Orange/Black seats are unavailable.</span>
                </div>
            </div>

            <!-- Seat status legend -->
            <div class="d-flex gap-3 mb-4 justify-content-center flex-wrap small">
                <div class="legend-item"><span class="legend-dot bg-success" style="background:#E2E8F0 !important; border:1px solid #CBD5E1 !important;"></span><span class="text-secondary">Available</span></div>
                <div class="legend-item"><span class="legend-dot" style="background:var(--accent-gold-gradient);"></span><span class="text-secondary">Selected</span></div>
                <div class="legend-item"><span class="legend-dot bg-danger" style="background:#FCA5A5 !important; border:1px solid #EF4444 !important;"></span><span class="text-secondary">Booked</span></div>
                <div class="legend-item"><span class="legend-dot bg-warning" style="background:#FDE68A !important; border:1px solid #F59E0B !important;"></span><span class="text-secondary">Hold</span></div>
                <div class="legend-item"><span class="legend-dot bg-dark" style="background:#1F2937 !important; border:1px solid #111827 !important;"></span><span class="text-secondary">Blocked</span></div>
                <div class="legend-item"><span class="legend-dot bg-primary" style="background:#BFDBFE !important; border:1px solid #3B82F6 !important;"></span><span class="text-secondary">Reserved</span></div>
                <div class="legend-item"><span class="legend-dot" style="background:#FBCFE8 !important; border:1px solid #EC4899 !important;"></span><span class="text-secondary">Female Protected</span></div>
            </div>

            <!-- Seating Grid -->
            <div class="text-center py-4">
                <div class="seat-map-container shadow-lg overflow-auto py-3">
                    <div id="seats-builder-canvas" class="mx-auto" style="display: inline-grid; gap: 10px; padding: 15px; border-radius: 12px; background: rgba(0,0,0,0.15);"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Booking Summary & Parameters Side Panel -->
    <div class="col-lg-5">
        <div class="glass-card p-4 shadow-lg">
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-receipt text-indigo me-2"></i>Reservation Details</h4>
            
            <div class="p-3 mb-4 rounded-4 bg-dark bg-opacity-30 border border-secondary border-opacity-15 small text-secondary">
                <div class="d-flex justify-content-between mb-2"><span>Voyage Class</span><span class="text-white fw-bold"><?= htmlspecialchars($trip['bus_name']) ?></span></div>
                <div class="d-flex justify-content-between mb-2"><span>Schedules</span><span class="text-white font-monospace"><?= date('d M Y, H:i', strtotime($trip['departure_time'])) ?></span></div>
                <div class="d-flex justify-content-between"><span>Voyage Route</span><span class="text-white fw-semibold"><?= htmlspecialchars($trip['source']) ?> to <?= htmlspecialchars($trip['destination']) ?></span></div>
            </div>

            <!-- Form parameters -->
            <form action="checkout.php" method="POST" id="checkoutTriggerForm">
                <input type="hidden" name="trip_id" value="<?= $trip_id ?>">
                <input type="hidden" name="selected_seats" id="post_seats_value" value="">

                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Choose Boarding point</label>
                    <select name="boarding_point" class="form-select form-control-swift" required>
                        <option value="">Select pickup station...</option>
                        <?php foreach ($boardings as $b): ?>
                            <option value="<?= htmlspecialchars($b['name'] . ' (' . $b['time'] . ')') ?>"><?= htmlspecialchars($b['name'] . ' - departs ' . $b['time']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Choose Dropping point</label>
                    <select name="dropping_point" class="form-select form-control-swift" required>
                        <option value="">Select dropping station...</option>
                        <?php foreach ($droppings as $d): ?>
                            <option value="<?= htmlspecialchars($d['name'] . ' (' . $d['time'] . ')') ?>"><?= htmlspecialchars($d['name'] . ' - arrival ' . $d['time']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="p-3 mb-4 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-15" id="agent_seats_preview" style="display: none;">
                    <div class="d-flex justify-content-between text-secondary small mb-2"><span>Seats Selected</span><span class="text-white fw-bold font-monospace" id="lblSeatsList">--</span></div>
                    <div class="d-flex justify-content-between text-secondary small mb-2"><span>Fare Price (Gross)</span><span class="text-white" id="lblGrossFare">₹0.00</span></div>
                    <div class="d-flex justify-content-between text-secondary small mb-2"><span>Agent Discount</span><span class="text-warning fw-bold" id="lblDiscount">₹0.00</span></div>
                    <div class="d-flex justify-content-between text-white fw-bold fs-5 pt-3 border-top border-secondary border-opacity-20">
                        <span>Total Paid Fare</span>
                        <span class="text-success" id="lblFinalFare">₹0.00</span>
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" id="btnGoCheckout" class="btn btn-primary-gradient py-3 text-uppercase fw-bold disabled" style="letter-spacing: 0.5px;">
                        Proceed to Booking Details
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.grid-cell {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.console-seat-box {
    width: 100%;
    height: 100%;
    border-radius: 8px;
    border: 1px solid var(--border-glass);
    color: var(--text-main);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 0.8rem;
    font-weight: 700;
    transition: all 0.2s ease;
}
.console-seat-box.available { background: rgba(78, 135, 82, 0.12); border-color: rgba(78, 135, 82, 0.35); color: var(--seat-available); }
.console-seat-box.hold { background: rgba(217, 140, 69, 0.12); border-color: rgba(217, 140, 69, 0.35); color: var(--seat-hold); }
.console-seat-box.booked { background: rgba(184, 92, 92, 0.12); border-color: rgba(184, 92, 92, 0.35); color: var(--seat-booked); }
.console-seat-box.blocked { background: #1a1f2c; border-color: #2D3442; color: #4E5A70; }
.console-seat-box.reserved { background: rgba(99, 102, 241, 0.12); border-color: rgba(99, 102, 241, 0.35); color: #818cf8; }
.console-seat-box.temp_locked { background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.35); color: #fbbf24; }
.console-seat-box.female_booked, .console-seat-box.female_protected { background: rgba(236, 72, 153, 0.12); border-color: rgba(236, 72, 153, 0.35); color: #ec4899; }

.console-seat-box.selected {
    background: var(--accent-gold-gradient);
    border-color: var(--accent-primary);
    color: var(--text-white-fixed);
}
.console-seat-box .price-lbl {
    font-size: 0.55rem;
    opacity: 0.85;
}
</style>

<script>
$(document).ready(function() {
    var seats = <?= json_encode(array_values($seats_lookup)) ?>;
    var rows = <?= $rows_count ?>;
    var cols = <?= $cols_count ?>;
    var selectedSeats = [];

    function renderGrid() {
        var canvas = $('#seats-builder-canvas');
        canvas.empty();
        canvas.css({
            'grid-template-rows': 'repeat(' + rows + ', 60px)',
            'grid-template-columns': 'repeat(' + cols + ', 60px)'
        });

        for (var r = 0; r < rows; r++) {
            for (var c = 0; c < cols; c++) {
                var seat = seats.find(s => s.row === r && s.col === c);
                var cell = $('<div class="grid-cell"></div>');

                if (seat) {
                    var isSelected = selectedSeats.includes(seat.number) ? ' selected' : '';
                    var typeClass = ' type-' + seat.type.toLowerCase().replace(/ /g, '-');
                    var box = $('<div class="console-seat-box ' + seat.status + isSelected + typeClass + '" data-seat="' + seat.number + '">' +
                        '<span>' + seat.number + '</span>' +
                        '<span class="price-lbl">₹' + seat.price.toFixed(0) + '</span>' +
                        '</div>');
                    
                    box.click(handleSeatClick(seat));
                    cell.append(box);
                }
                canvas.append(cell);
            }
        }
    }

    function handleSeatClick(seat) {
        return function() {
            if (seat.status === 'booked' || seat.status === 'hold' || seat.status === 'blocked' || seat.status === 'reserved' || seat.status === 'female_booked') {
                alert("This seat is currently unavailable.");
                return;
            }

            var csrf = '<?= get_csrf_token() ?>';
            var action = selectedSeats.includes(seat.number) ? 'unlock' : 'lock';

            // Communicate with temp lock AJAX to secure seat lock
            $.ajax({
                url: '<?= BASE_URL ?>/ajax/lock_seats.php',
                type: 'POST',
                data: {
                    trip_id: <?= $trip_id ?>,
                    seat_number: seat.number,
                    action: action,
                    csrf_token: csrf
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        if (action === 'lock') {
                            selectedSeats.push(seat.number);
                        } else {
                            selectedSeats = selectedSeats.filter(num => num !== seat.number);
                        }
                        renderGrid();
                        updateFareComputation();
                    } else {
                        alert(response.message);
                    }
                },
                error: function() {
                    alert("Communication with reservation manager failed.");
                }
            });
        };
    }

    function updateFareComputation() {
        if (selectedSeats.length === 0) {
            $('#agent_seats_preview').hide();
            $('#btnGoCheckout').addClass('disabled');
            $('#post_seats_value').val('');
            return;
        }

        var gross = 0;
        var discount = 0;
        var finalFare = 0;

        selectedSeats.forEach(function(num) {
            var seatInfo = seats.find(s => s.number === num);
            if (seatInfo) {
                gross += seatInfo.original_price;
                discount += seatInfo.discount;
                finalFare += seatInfo.price;
            }
        });

        $('#lblSeatsList').text(selectedSeats.join(', '));
        $('#lblGrossFare').text('₹' + gross.toFixed(2));
        $('#lblDiscount').text('₹' + discount.toFixed(2));
        $('#lblFinalFare').text('₹' + finalFare.toFixed(2));

        $('#post_seats_value').val(selectedSeats.join(','));
        $('#agent_seats_preview').show();
        $('#btnGoCheckout').removeClass('disabled');
    }

    renderGrid();
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
