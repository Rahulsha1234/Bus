<?php
/**
 * Agent Seat Control Panel (Hold, Release, Block, Unblock, Seat Price Management)
 */
require_once __DIR__ . '/header.php';

$agent_id = $_SESSION['user_id'];
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
        WHERE b.agent_id = ? AND t.status = 'active'
        ORDER BY t.departure_time DESC
    ");
    $stmt->execute([$agent_id]);
    $trips = $stmt->fetchAll();
} catch (PDOException $e) {
    $trips = [];
}

$selected_trip_id = intval($_GET['trip_id'] ?? 0);
$trip_details = null;
$seats_list = [];

if ($selected_trip_id > 0) {
    // Verify ownership
    $stmt = $pdo->prepare("
        SELECT t.id, t.bus_id, b.seat_layout_type, b.bus_name, r.source, r.destination 
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        JOIN routes r ON t.route_id = r.id
        WHERE t.id = ? AND b.agent_id = ? AND t.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$selected_trip_id, $agent_id]);
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

                        if ($action === 'hold') {
                            $stmt = $pdo->prepare("
                                INSERT INTO trip_seats (trip_id, seat_number, status, hold_expires_at)
                                VALUES (?, ?, 'hold', NULL)
                                ON DUPLICATE KEY UPDATE status = 'hold', hold_expires_at = NULL
                            ");
                            foreach ($target_seats as $seat) {
                                $stmt->execute([$selected_trip_id, $seat]);
                            }
                            $success = "Successfully placed " . count($target_seats) . " seat(s) on manual Hold.";
                            log_activity($pdo, $agent_id, 'SEAT_HOLD_BULK', "Held seats (" . implode(',', $target_seats) . ") on Trip: $selected_trip_id");
                        } 
                        elseif ($action === 'release') {
                            $stmt = $pdo->prepare("UPDATE trip_seats SET status = 'available', hold_expires_at = NULL WHERE trip_id = ? AND seat_number = ?");
                            foreach ($target_seats as $seat) {
                                $stmt->execute([$selected_trip_id, $seat]);
                            }
                            $success = "Successfully released " . count($target_seats) . " seat(s) back to available pool.";
                            log_activity($pdo, $agent_id, 'SEAT_RELEASE_BULK', "Released seats (" . implode(',', $target_seats) . ") on Trip: $selected_trip_id");
                        } 
                        elseif ($action === 'block') {
                            $stmt = $pdo->prepare("
                                INSERT INTO trip_seats (trip_id, seat_number, status)
                                VALUES (?, ?, 'blocked')
                                ON DUPLICATE KEY UPDATE status = 'blocked'
                            ");
                            foreach ($target_seats as $seat) {
                                $stmt->execute([$selected_trip_id, $seat]);
                            }
                            $success = "Successfully Blocked " . count($target_seats) . " seat(s).";
                            log_activity($pdo, $agent_id, 'SEAT_BLOCK_BULK', "Blocked seats (" . implode(',', $target_seats) . ") on Trip: $selected_trip_id");
                        } 
                        elseif ($action === 'unblock') {
                            $stmt = $pdo->prepare("UPDATE trip_seats SET status = 'available' WHERE trip_id = ? AND seat_number = ?");
                            foreach ($target_seats as $seat) {
                                $stmt->execute([$selected_trip_id, $seat]);
                            }
                            $success = "Successfully Unblocked " . count($target_seats) . " seat(s).";
                            log_activity($pdo, $agent_id, 'SEAT_UNBLOCK_BULK', "Unblocked seats (" . implode(',', $target_seats) . ") on Trip: $selected_trip_id");
                        } 
                        elseif ($action === 'price') {
                            $base_price = floatval($_POST['base_price'] ?? 0.00);
                            $current_price = floatval($_POST['current_price'] ?? 0.00);
                            $offer_price = floatval($_POST['offer_price'] ?? 0.00);

                            if ($base_price <= 0 || $current_price <= 0 || $offer_price <= 0) {
                                $error = "Fares must be positive numeric values.";
                            } else {
                                $stmt = $pdo->prepare("
                                    INSERT INTO seat_pricing (trip_id, seat_number, base_price, current_price, offer_price)
                                    VALUES (?, ?, ?, ?, ?)
                                    ON DUPLICATE KEY UPDATE base_price = VALUES(base_price), current_price = VALUES(current_price), offer_price = VALUES(offer_price)
                                ");
                                foreach ($target_seats as $seat) {
                                    $stmt->execute([$selected_trip_id, $seat, $base_price, $current_price, $offer_price]);
                                }
                                $success = "Pricing overrides applied to " . count($target_seats) . " seat(s).";
                                log_activity($pdo, $agent_id, 'PRICE_OVERRIDE_BULK', "Override fares for seats (" . implode(',', $target_seats) . ") on Trip: $selected_trip_id");
                            }
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
                ts.status, ts.hold_expires_at,
                sp.base_price AS current_base, sp.current_price AS current_cur
            FROM bus_seats s
            LEFT JOIN trip_seats ts ON s.seat_number = ts.seat_number AND ts.trip_id = ?
            LEFT JOIN seat_pricing sp ON s.seat_number = sp.seat_number AND sp.trip_id = ?
            WHERE s.bus_id = ? AND s.is_active = 1
        ");
        $seats_stmt->execute([$selected_trip_id, $selected_trip_id, $trip_details['bus_id']]);
        $db_seats = $seats_stmt->fetchAll();

        // Convert to mapped list
        $now = date('Y-m-d H:i:s');
        foreach ($db_seats as $s) {
            $status = !empty($s['status']) ? $s['status'] : 'available';
            // Expired hold behaves as available
            if ($status === 'hold' && !empty($s['hold_expires_at']) && strtotime($s['hold_expires_at']) < strtotime($now)) {
                $status = 'available';
            }

            $seats_list[] = [
                'number' => $s['seat_number'],
                'row' => intval($s['row_pos']),
                'col' => intval($s['col_pos']),
                'type' => $s['seat_type'],
                'status' => $status,
                'price' => !empty($s['current_cur']) ? floatval($s['current_cur']) : (!empty($s['current_base']) ? floatval($s['current_base']) : 500.00)
            ];
        }
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
                        <label class="form-label text-secondary small fw-semibold">Selected Seats Count:</label>
                        <div id="selection_preview" class="p-2 border border-secondary border-opacity-10 rounded bg-dark bg-opacity-20 font-semibold small text-indigo">
                            0 Seats Selected
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-semibold">Execute Allocation Action</label>
                        <select name="action" id="action_selector" class="form-select form-control-swift" required>
                            <option value="hold">Offline Hold (Indefinite)</option>
                            <option value="release">Release Hold / Block</option>
                            <option value="block">Block Seats (System Block)</option>
                            <option value="unblock">Unblock Seats</option>
                            <option value="price">Modify Seat Fares (Trip Overrides)</option>
                        </select>
                    </div>

                    <!-- Pricing Overrides block (Shown only if 'price' selected) -->
                    <div id="pricing_fields" style="display: none;" class="p-3 mb-4 rounded border border-secondary border-opacity-20 bg-dark bg-opacity-10">
                        <div class="mb-2">
                            <label class="form-label text-secondary small">Base Price (₹)</label>
                            <input type="number" name="base_price" class="form-control form-control-swift py-1" value="500">
                        </div>
                        <div class="mb-2">
                            <label class="form-label text-secondary small">Current Price (₹)</label>
                            <input type="number" name="current_price" class="form-control form-control-swift py-1" value="500">
                        </div>
                        <div class="mb-0">
                            <label class="form-label text-secondary small">Offer Price (₹)</label>
                            <input type="number" name="offer_price" class="form-control form-control-swift py-1" value="500">
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
                        <h5 class="fw-bold mb-0">Seat Selection Layout</h5>
                        <span class="text-secondary small">Hold Shift to select ranges / click cells to toggle.</span>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" id="btnSelectAll" class="btn btn-secondary-glass py-1 px-2 small">Select All</button>
                        <button type="button" id="btnSelectNone" class="btn btn-secondary-glass py-1 px-2 small">Clear Selection</button>
                    </div>
                </div>

                <!-- Legend details -->
                <div class="d-flex gap-3 mb-4 justify-content-center flex-wrap small">
                    <div class="legend-item"><span class="legend-dot bg-success"></span><span class="text-secondary">Available</span></div>
                    <div class="legend-item"><span class="legend-dot bg-warning"></span><span class="text-secondary">Held</span></div>
                    <div class="legend-item"><span class="legend-dot bg-danger"></span><span class="text-secondary">Booked</span></div>
                    <div class="legend-item"><span class="legend-dot bg-dark border border-secondary"></span><span class="text-secondary">Blocked</span></div>
                    <div class="legend-item"><span class="legend-dot bg-primary"></span><span class="text-secondary">VIP Reserved</span></div>
                    <div class="legend-item"><span class="legend-dot bg-info"></span><span class="text-secondary">Temp Locked</span></div>
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
.console-seat-box.available { background: rgba(78, 135, 82, 0.12); border-color: rgba(78, 135, 82, 0.35); color: var(--seat-available); }
.console-seat-box.hold { background: rgba(217, 140, 69, 0.12); border-color: rgba(217, 140, 69, 0.35); color: var(--seat-hold); }
.console-seat-box.booked { background: rgba(184, 92, 92, 0.12); border-color: rgba(184, 92, 92, 0.35); color: var(--seat-booked); }
.console-seat-box.blocked { background: #1a1f2c; border-color: #2D3442; color: #4E5A70; }
.console-seat-box.reserved { background: rgba(99, 102, 241, 0.12); border-color: rgba(99, 102, 241, 0.35); color: #818cf8; }
.console-seat-box.temp_locked { background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.35); color: #fbbf24; }

.console-seat-box.selected-action {
    outline: 2px solid var(--accent-primary) !important;
    outline-offset: 2px;
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
            'grid-template-columns': 'repeat(' + cols + ', 60px)'
        });

        for (var r = 0; r < rows; r++) {
            var rowHeaderCell = $('<div class="grid-cell" style="cursor: pointer; font-size: 0.7rem; color: var(--text-muted);" data-row-header="' + r + '">Row ' + (r + 1) + '</div>');
            rowHeaderCell.click(handleRowHeaderClick(r));
            
            for (var c = 0; c < cols; c++) {
                var seat = seats.find(s => s.row === r && s.col === c);
                var cell = $('<div class="grid-cell"></div>');

                if (seat) {
                    var isSelected = selectedSeats.includes(seat.number) ? ' selected-action' : '';
                    var box = $('<div class="console-seat-box ' + seat.status + isSelected + '" data-seat="' + seat.number + '">' +
                        '<span>' + seat.number + '</span>' +
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
            if (seat.status === 'booked') {
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
            var rowSeats = seats.filter(s => s.row === rowIdx && s.status !== 'booked');
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
        selectedSeats = seats.filter(s => s.status !== 'booked').map(s => s.number);
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
