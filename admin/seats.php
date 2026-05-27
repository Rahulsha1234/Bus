<?php
/**
 * Agent Seat Control Panel (Hold, Release, Block, Unblock, Seat Price Management)
 */
require_once __DIR__ . '/header.php';

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Fetch Agent's scheduled active trips
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id AS trip_id,
            t.departure_time,
            b.bus_name,
            b.bus_number,
            r.source,
            r.destination
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        JOIN routes r ON t.route_id = r.id
        WHERE b.admin_id = ? AND t.status = 'active'
        ORDER BY t.departure_time DESC
    ");
    $stmt->execute([$admin_id]);
    $trips = $stmt->fetchAll();
} catch (PDOException $e) {
    $trips = [];
}

$selected_trip_id = intval($_GET['trip_id'] ?? 0);
$trip_details = null;
$seats_list = [];

if ($selected_trip_id > 0) {
    try {
        // Verify ownership
        $stmt = $pdo->prepare("
            SELECT t.id, t.bus_id, t.base_fare, b.seat_layout_type, b.bus_name, r.source, r.destination 
            FROM trips t
            JOIN buses b ON t.bus_id = b.id
            JOIN routes r ON t.route_id = r.id
            WHERE t.id = ? AND b.admin_id = ? AND t.status = 'active'
            LIMIT 1
        ");
        $stmt->execute([$selected_trip_id, $admin_id]);
        $trip_details = $stmt->fetch();

        if ($trip_details) {
            // Handle Seat Bulk Operations
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
                $csrf_token = $_POST['csrf_token'] ?? '';
                if (!verify_csrf_token($csrf_token)) {
                    $error = "Security token validation failed.";
                } else {
                    $action = $_POST['action'];
                    $target_seats_str = $_POST['seats_list'] ?? '';
                    $target_seats = array_filter(array_map('trim', explode(',', $target_seats_str)));

                    if (empty($target_seats)) {
                        $error = "No seats selected for allocation modification.";
                    } else {
                        try {
                            $pdo->beginTransaction();

                            if ($action === 'price') {
                                $final_price = floatval($_POST['final_price'] ?? 0.00);
                                if ($final_price <= 0) {
                                    $error = "Seat price must be a positive numeric value.";
                                } else {
                                    // 1. Insert/Update seat_price_overrides
                                    $stmt = $pdo->prepare("
                                        INSERT INTO seat_price_overrides (trip_id, seat_number, custom_price, updated_by)
                                        VALUES (?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE custom_price = VALUES(custom_price), updated_by = VALUES(updated_by)
                                    ");
                                    foreach ($target_seats as $seat) {
                                        $stmt->execute([$selected_trip_id, $seat, $final_price, $admin_id]);
                                    }
                                    
                                    // 2. Also insert/update seat_pricing for backwards compatibility
                                    $stmt2 = $pdo->prepare("
                                        INSERT INTO seat_pricing (trip_id, seat_number, base_price, current_price, offer_price)
                                        VALUES (?, ?, ?, ?, ?)
                                        ON DUPLICATE KEY UPDATE base_price = VALUES(base_price), current_price = VALUES(current_price), offer_price = VALUES(offer_price)
                                    ");
                                    foreach ($target_seats as $seat) {
                                        $stmt2->execute([$selected_trip_id, $seat, $final_price, $final_price, $final_price]);
                                    }
                                    
                                    $success = "Successfully updated Final Seat Price to ₹" . number_format($final_price, 2) . " for " . count($target_seats) . " seat(s).";
                                    log_activity($pdo, $admin_id, 'SEAT_PRICE_OVERRIDE', "Set custom price ₹$final_price for seats (" . implode(',', $target_seats) . ") on Trip: $selected_trip_id");
                                }
                            } 
                            elseif ($action === 'toggle_block') {
                                $blocked_seats_count = 0;
                                $unblocked_seats_count = 0;
                                foreach ($target_seats as $seat) {
                                    if (is_seat_blocked($pdo, $selected_trip_id, $seat)) {
                                        // Unblock it
                                        $stmt1 = $pdo->prepare("DELETE FROM seat_blocks WHERE trip_id = ? AND seat_number = ?");
                                        $stmt1->execute([$selected_trip_id, $seat]);
                                        
                                        $stmt2 = $pdo->prepare("UPDATE trip_seats SET status = 'available' WHERE trip_id = ? AND seat_number = ?");
                                        $stmt2->execute([$selected_trip_id, $seat]);
                                        $unblocked_seats_count++;
                                    } else {
                                        // Block it
                                        $stmt1 = $pdo->prepare("
                                            INSERT INTO seat_blocks (trip_id, seat_number, blocked_by)
                                            VALUES (?, ?, ?)
                                            ON DUPLICATE KEY UPDATE blocked_by = VALUES(blocked_by)
                                        ");
                                        $stmt1->execute([$selected_trip_id, $seat, $admin_id]);
                                        
                                        $stmt2 = $pdo->prepare("
                                            INSERT INTO trip_seats (trip_id, seat_number, status)
                                            VALUES (?, ?, 'blocked')
                                            ON DUPLICATE KEY UPDATE status = 'blocked'
                                        ");
                                        $stmt2->execute([$selected_trip_id, $seat]);
                                        $blocked_seats_count++;
                                    }
                                }
                                $success = "Successfully updated " . count($target_seats) . " seat(s) status ($blocked_seats_count blocked, $unblocked_seats_count unblocked).";
                                log_activity($pdo, $admin_id, 'SEAT_BLOCK_TOGGLE', "Toggled block/unblock for seats (" . implode(',', $target_seats) . ") on Trip: $selected_trip_id");
                            }

                            if (empty($error)) {
                                $pdo->commit();
                            } else {
                                $pdo->rollBack();
                            }
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            $error = "Allocation Action failed: " . $e->getMessage();
                        }
                    }
                }
            }

            // Fetch Grid Dimension details
            $layout_stmt = $pdo->prepare("SELECT * FROM bus_layouts WHERE bus_id = ? LIMIT 1");
            $layout_stmt->execute([$trip_details['bus_id']]);
            $layout = $layout_stmt->fetch();

            $rows_count = $layout ? intval($layout['rows_count']) : 10;
            $cols_count = $layout ? intval($layout['cols_count']) : 5;

            // Fetch all seats configured for this bus, left-joining status overrides
            $seats_stmt = $pdo->prepare("
                SELECT 
                    s.seat_number, s.row_pos, s.col_pos, s.seat_type, s.is_active,
                    ts.status AS trip_seat_status,
                    sb.id AS is_blocked_override,
                    spo.custom_price
                FROM bus_seats s
                LEFT JOIN trip_seats ts ON s.seat_number = ts.seat_number AND ts.trip_id = ?
                LEFT JOIN seat_blocks sb ON s.seat_number = sb.seat_number AND sb.trip_id = ?
                LEFT JOIN seat_price_overrides spo ON s.seat_number = spo.seat_number AND spo.trip_id = ?
                WHERE s.bus_id = ? AND s.is_active = 1
            ");
            $seats_stmt->execute([$selected_trip_id, $selected_trip_id, $selected_trip_id, $trip_details['bus_id']]);
            $db_seats = $seats_stmt->fetchAll();

            // Booked genders
            $gender_stmt = $pdo->prepare("
                SELECT bs.seat_number, bs.passenger_gender 
                FROM booking_seats bs
                JOIN bookings b ON bs.booking_id = b.id
                WHERE b.trip_id = ? AND b.status = 'active'
            ");
            $gender_stmt->execute([$selected_trip_id]);
            $booked_genders = $gender_stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

            foreach ($db_seats as $s) {
                $status = 'available';
                
                // If booked
                if ($s['trip_seat_status'] === 'booked') {
                    $status = 'booked';
                }
                
                // Override status if blocked
                if ($s['is_blocked_override'] || $s['trip_seat_status'] === 'blocked') {
                    $status = 'blocked';
                }

                if ($status === 'booked' && ($booked_genders[$s['seat_number']] ?? '') === 'Female') {
                    $status = 'female_booked';
                }

                // Pricing
                $price = get_actual_seat_price($pdo, $selected_trip_id, $s['seat_number'], $trip_details['base_fare']);

                $seats_list[] = [
                    'number' => $s['seat_number'],
                    'row' => intval($s['row_pos']),
                    'col' => intval($s['col_pos']),
                    'type' => $s['seat_type'],
                    'status' => $status,
                    'price' => $price
                ];
            }

            // Apply adjacent Female Protection rules
            foreach ($seats_list as $seatNum => $sInfo) {
                if ($sInfo['status'] === 'female_booked') {
                    $adj_col = -1;
                    if ($sInfo['col'] === 0) $adj_col = 1;
                    elseif ($sInfo['col'] === 1) $adj_col = 0;
                    elseif ($sInfo['col'] === 3) $adj_col = 4;
                    elseif ($sInfo['col'] === 4) $adj_col = 3;

                    if ($adj_col !== -1) {
                        foreach ($seats_list as $otherIdx => $otherInfo) {
                            if ($otherInfo['row'] === $sInfo['row'] && $otherInfo['col'] === $adj_col && $otherInfo['status'] === 'available') {
                                $seats_list[$otherIdx]['status'] = 'female_protected';
                            }
                        }
                    }
                }
            }
        }
    } catch (PDOException $e) {
        $error = "Database Error: " . $e->getMessage();
    }
}
?>

<?php if (!empty($error)): ?>
    <div class="alert alert-danger border-0 bg-danger bg-opacity-10 text-danger rounded-3" role="alert">
        <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if (!empty($success)): ?>
    <div class="alert alert-success border-0 bg-success bg-opacity-10 text-success rounded-3" role="alert">
        <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- Configuration Side Panel -->
    <div class="col-md-4">
        <div class="glass-card p-4">
            <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-compass text-indigo me-2"></i>Map Control Panel</h5>
            
            <form action="" method="GET" class="mb-4">
                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Choose Trip Voyage</label>
                    <select name="trip_id" class="form-select form-control-swift" onchange="this.form.submit()" required>
                        <option value="">Select Schedule...</option>
                        <?php foreach ($trips as $t): ?>
                            <option value="<?= $t['trip_id'] ?>" <?= ($selected_trip_id === intval($t['trip_id'])) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['source']) ?> to <?= htmlspecialchars($t['destination']) ?> (<?= date('d M, H:i', strtotime($t['departure_time'])) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>

            <?php if ($trip_details): ?>
                <hr class="border-secondary mb-4">
                
                <form action="" method="POST" id="seatActionForm">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="seats_list" id="action_seats_list" value="">
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Selected Seats:</label>
                        <div id="selection_preview" class="p-2 border border-secondary border-opacity-10 rounded bg-dark bg-opacity-20 font-semibold small text-indigo">
                            0 Seats Selected
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-semibold">Execute Allocation Action</label>
                        <select name="action" id="action_selector" class="form-select form-control-swift" required>
                            <option value="price">Modify Seat Price</option>
                            <option value="toggle_block">Block / Unblock Seat</option>
                        </select>
                    </div>

                    <!-- Pricing Overrides block (Shown only if 'price' selected) -->
                    <div id="pricing_fields" class="p-3 mb-4 rounded border border-secondary border-opacity-20 bg-dark bg-opacity-10">
                        <div class="mb-0">
                            <label class="form-label text-secondary small">Final Seat Price (₹)</label>
                            <input type="number" name="final_price" class="form-control form-control-swift py-1" value="500" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-gradient w-100 py-3 font-semibold">
                        Apply Allocations
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Seating Layout Selector -->
    <div class="col-md-8">
        <div class="glass-card p-4">
            <?php if (!$trip_details): ?>
                <div class="text-center py-5 text-secondary small">
                    <i class="fa-solid fa-chair mb-3 d-block" style="font-size: 3.5rem; color:#475569;"></i>
                    Please select an active voyage from the control panel to view allocations.
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary border-opacity-20 flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0 text-white">Seat Selection Layout</h5>
                        <span class="text-secondary small">Hold Shift to select ranges / click cells to toggle.</span>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" id="btnSelectAll" class="btn btn-secondary-glass py-1 px-2 small">Select All</button>
                        <button type="button" id="btnSelectNone" class="btn btn-secondary-glass py-1 px-2 small">Clear Selection</button>
                    </div>
                </div>

                <!-- Legend details -->
                <div class="d-flex gap-3 mb-4 justify-content-center flex-wrap small">
                    <div class="legend-item"><span class="legend-dot" style="background:#198754; border:1px solid #146c43;"></span><span class="text-secondary">Available</span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:#dc3545; border:1px solid #b02a37;"></span><span class="text-secondary">Booked</span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:#343a40; border:1px solid #212529;"></span><span class="text-secondary">Blocked</span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:#f472b6; border:1px solid #db2777;"></span><span class="text-secondary">Female Booked</span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:transparent; border:2px dashed #db2777;"></span><span class="text-secondary">Female Adjacent Restricted</span></div>
                </div>

                <div class="text-center overflow-auto py-2">
                    <div id="seats-builder-canvas" class="mx-auto" style="display: inline-grid; gap: 10px; padding: 15px; border-radius: 12px; background: rgba(0,0,0,0.15);"></div>
                </div>
            <?php endif; ?>
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
.console-seat-box.available { background: rgba(25, 135, 84, 0.15); border-color: #198754; color: #198754; }
.console-seat-box.booked { background: #dc3545; border-color: #b02a37; color: #ffffff; }
.console-seat-box.blocked { background: #343a40; border-color: #212529; color: #adb5bd; }
.console-seat-box.female_booked { background: #f472b6; border-color: #db2777; color: #ffffff; }
.console-seat-box.female_protected { background: transparent; border: 2px dashed #db2777; color: #db2777; }

.console-seat-box.selected-action {
    outline: 2px solid var(--accent-primary) !important;
    outline-offset: 2px;
}
.console-seat-box .price-lbl {
    font-size: 0.55rem;
    opacity: 0.85;
}
</style>

<script>
$(document).ready(function() {
    <?php if ($trip_details): ?>
    var seats = <?= json_encode($seats_list) ?>;
    var rows = <?= $rows_count ?>;
    var cols = <?= $cols_count ?>;
    var selectedSeats = [];

    function renderConsoleGrid() {
        var canvas = $('#seats-builder-canvas');
        canvas.empty();
        canvas.css({
            'grid-template-rows': 'repeat(' + rows + ', 60px)',
            'grid-template-columns': 'repeat(' + (cols + 1) + ', 60px)'
        });

        for (var r = 0; r < rows; r++) {
            var rowHeaderCell = $('<div class="grid-cell" style="cursor: pointer; font-size: 0.7rem; color: var(--text-muted);" data-row-header="' + r + '">Row ' + (r + 1) + '</div>');
            rowHeaderCell.click(handleRowHeaderClick(r));
            canvas.append(rowHeaderCell);
            
            for (var c = 0; c < cols; c++) {
                var seat = seats.find(s => s.row === r && s.col === c);
                var cell = $('<div class="grid-cell"></div>');

                if (seat) {
                    var isSelected = selectedSeats.includes(seat.number) ? ' selected-action' : '';
                    var typeClass = ' type-' + seat.type.toLowerCase().replace(/ /g, '-');
                    var box = $('<div class="console-seat-box ' + seat.status + isSelected + typeClass + '" data-seat="' + seat.number + '">' +
                        '<span>' + seat.number + '</span>' +
                        '<span class="price-lbl">₹' + seat.price.toFixed(0) + '</span>' +
                        '</div>');
                    
                    box.click(handleSeatToggle(seat));
                    cell.append(box);
                }
                canvas.append(cell);
            }
        }
    }

    function handleSeatToggle(seat) {
        return function() {
            if (seat.status === 'booked' || seat.status === 'female_booked') {
                alert("Booked seats cannot be modified.");
                return;
            }
            if (selectedSeats.includes(seat.number)) {
                selectedSeats = selectedSeats.filter(num => num !== seat.number);
            } else {
                selectedSeats.push(seat.number);
            }
            renderConsoleGrid();
            updateSelectionPreview();
        };
    }

    function handleRowHeaderClick(rowIdx) {
        return function() {
            var rowSeats = seats.filter(s => s.row === rowIdx && s.status !== 'booked' && s.status !== 'female_booked');
            var rowSeatNums = rowSeats.map(s => s.number);
            
            // Check if all are already selected
            var allSelected = rowSeatNums.every(num => selectedSeats.includes(num));
            if (allSelected) {
                // Remove all from selection
                selectedSeats = selectedSeats.filter(num => !rowSeatNums.includes(num));
            } else {
                // Add all to selection
                rowSeatNums.forEach(num => {
                    if (!selectedSeats.includes(num)) {
                        selectedSeats.push(num);
                    }
                });
            }
            renderConsoleGrid();
            updateSelectionPreview();
        };
    }

    function updateSelectionPreview() {
        $('#action_seats_list').val(selectedSeats.join(','));
        $('#selection_preview').text(selectedSeats.length + " Seat(s) Selected (" + selectedSeats.join(', ') + ")");
    }

    $('#btnSelectAll').click(function() {
        selectedSeats = seats.filter(s => s.status !== 'booked' && s.status !== 'female_booked').map(s => s.number);
        renderConsoleGrid();
        updateSelectionPreview();
    });

    $('#btnSelectNone').click(function() {
        selectedSeats = [];
        renderConsoleGrid();
        updateSelectionPreview();
    });

    $('#action_selector').change(function() {
        if ($(this).val() === 'price') {
            $('#pricing_fields').slideDown();
        } else {
            $('#pricing_fields').slideUp();
        }
    });

    renderConsoleGrid();
    <?php endif; ?>
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
