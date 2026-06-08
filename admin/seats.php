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
    ensure_refactor_tables_exist($pdo);
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
                    $error = __('security_validation_failed', "Security token validation failed.");
                } else {
                    $action = $_POST['action'];
                    $target_seats_str = $_POST['seats_list'] ?? '';
                    $target_seats = array_filter(array_map('trim', explode(',', $target_seats_str)));

                    if (empty($target_seats)) {
                        $error = __('no_seats_selected_allocation', "No seats selected for allocation modification.");
                    } else {
                        try {
                            $pdo->beginTransaction();

                            if ($action === 'price') {
                                $final_price = floatval($_POST['final_price'] ?? 0.00);
                                if ($final_price <= 0) {
                                    $error = __('price_positive_numeric', "Seat price must be a positive numeric value.");
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
                                    
                                    $success = __('seat_price_updated_success_prefix', "Successfully updated Final Seat Price to ₹") . number_format($final_price, 2) . __('for_mid_label', " for ") . count($target_seats) . __('seats_suffix', " seat(s).");
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
                                $success = __('seats_updated_success_prefix', "Successfully updated ") . count($target_seats) . __('seats_status_mid_label', " seat(s) status (") . $blocked_seats_count . __('blocked_mid_label', " blocked, ") . $unblocked_seats_count . __('unblocked_suffix', " unblocked).");
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
                            $error = __('allocation_action_failed', "Allocation Action failed: ") . $e->getMessage();
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
        $error = __('database_error', "Database Error: ") . $e->getMessage();
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
            <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-compass text-indigo me-2"></i><?= __('map_control_panel_hdr', 'Map Control Panel') ?></h5>
            
            <form action="" method="GET" class="mb-4">
                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold"><?= __('choose_trip_voyage_label', 'Choose Trip Voyage') ?></label>
                    <select name="trip_id" class="form-select form-control-swift" onchange="this.form.submit()" required>
                        <option value=""><?= __('select_schedule_placeholder', 'Select Schedule...') ?></option>
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
                        <label class="form-label text-secondary small fw-semibold"><?= __('selected_seats_lbl', 'Selected Seats:') ?></label>
                        <div id="selection_preview" class="p-2 border border-secondary border-opacity-10 rounded bg-dark bg-opacity-20 font-semibold small text-indigo">
                            <?= __('zero_seats_selected_desc', '0 Seats Selected') ?>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label text-secondary small fw-semibold"><?= __('execute_allocation_action_lbl', 'Execute Allocation Action') ?></label>
                        <select name="action" id="action_selector" class="form-select form-control-swift" required>
                            <option value="price"><?= __('modify_seat_price_opt', 'Modify Seat Price') ?></option>
                            <option value="toggle_block"><?= __('block_unblock_seat_opt', 'Block / Unblock Seat') ?></option>
                        </select>
                    </div>

                    <!-- Pricing Overrides block (Shown only if 'price' selected) -->
                    <div id="pricing_fields" class="p-3 mb-4 rounded border border-secondary border-opacity-20 bg-dark bg-opacity-10">
                        <div class="mb-0">
                            <label class="form-label text-secondary small"><?= __('final_seat_price_label', 'Final Seat Price (₹)') ?></label>
                            <input type="number" name="final_price" class="form-control form-control-swift py-1" value="500" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary-gradient w-100 py-3 font-semibold">
                        <?= __('apply_allocations_btn', 'Apply Allocations') ?>
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
                    <?= __('select_voyage_view_allocations_desc', 'Please select an active voyage from the control panel to view allocations.') ?>
                </div>
            <?php else: ?>
                <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary border-opacity-20 flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0 text-white"><?= __('seat_selection_layout_hdr', 'Seat Selection Layout') ?></h5>
                        <span class="text-secondary small"><?= __('seat_selection_instructions', 'Click cells to select one / Hold Ctrl + click to select multiple.') ?></span>
                    </div>
                    <div class="d-flex gap-1">
                        <button type="button" id="btnSelectAll" class="btn btn-secondary-glass py-1 px-2 small"><?= __('select_all_btn', 'Select All') ?></button>
                        <button type="button" id="btnSelectNone" class="btn btn-secondary-glass py-1 px-2 small"><?= __('clear_selection_btn', 'Clear Selection') ?></button>
                    </div>
                </div>

                <!-- Legend details -->
                <div class="d-flex gap-3 mb-4 justify-content-center flex-wrap small">
                    <div class="legend-item"><span class="legend-dot" style="background:#198754; border:1px solid #146c43;"></span><span class="text-secondary"><?= __('legend_available', 'Available') ?></span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:#dc3545; border:1px solid #b02a37;"></span><span class="text-secondary"><?= __('legend_booked', 'Booked') ?></span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:#343a40; border:1px solid #212529;"></span><span class="text-secondary"><?= __('legend_blocked', 'Blocked') ?></span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:#f472b6; border:1px solid #db2777;"></span><span class="text-secondary"><?= __('legend_female_booked', 'Female Booked') ?></span></div>
                    <div class="legend-item"><span class="legend-dot" style="background:transparent; border:2px dashed #db2777;"></span><span class="text-secondary"><?= __('legend_female_adjacent_restricted', 'Female Adjacent Restricted') ?></span></div>
                </div>

                <div class="text-center overflow-auto py-2">
                    <!-- Tab Buttons if mixed/sleeper has upper berths -->
                    <div id="deck-tabs-container" style="display: none;">
                        <ul class="nav nav-pills justify-content-center mb-4 gap-2" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link btn-secondary-glass active px-4 py-2" id="admin-low-deck-tab" data-bs-toggle="pill" data-bs-target="#admin-low-deck-pane" type="button" role="tab"><?= __('lower_deck_tab', 'Lower Deck') ?></button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link btn-secondary-glass px-4 py-2" id="admin-up-deck-tab" data-bs-toggle="pill" data-bs-target="#admin-up-deck-pane" type="button" role="tab"><?= __('upper_deck_tab', 'Upper Deck') ?></button>
                            </li>
                        </ul>
                    </div>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="admin-low-deck-pane" role="tabpanel">
                            <div class="seat-map-container shadow-lg overflow-auto py-3" style="max-width: 100%;">
                                <div id="seats-canvas-lower" class="mx-auto" style="display: inline-grid; gap: 10px; padding: 15px; border-radius: 12px; background: rgba(0,0,0,0.15);"></div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="admin-up-deck-pane" role="tabpanel">
                            <div class="seat-map-container shadow-lg overflow-auto py-3" style="max-width: 100%;">
                                <div id="seats-canvas-upper" class="mx-auto" style="display: inline-grid; gap: 10px; padding: 15px; border-radius: 12px; background: rgba(0,0,0,0.15);"></div>
                            </div>
                        </div>
                    </div>
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
.console-seat-box.sleeper-berth {
    height: 130px;
    position: relative;
    z-index: 10;
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
        var hasUpperSeats = seats.some(s => s.type.toLowerCase().includes('upper'));
        if (hasUpperSeats) {
            $('#deck-tabs-container').show();
        } else {
            $('#deck-tabs-container').hide();
        }

        renderCanvas($('#seats-canvas-lower'), false, hasUpperSeats);
        if (hasUpperSeats) {
            renderCanvas($('#seats-canvas-upper'), true, hasUpperSeats);
        }
    }

    function renderCanvas(canvas, getUpper, hasUpper) {
        canvas.empty();
        canvas.css({
            'grid-template-rows': 'repeat(' + rows + ', 60px)',
            'grid-template-columns': 'repeat(' + (cols + 1) + ', 60px)'
        });

        var canvasSeats = seats.filter(s => {
            var isUpper = s.type.toLowerCase().includes('upper');
            return getUpper ? isUpper : (!hasUpper || !isUpper);
        });

        // Map occupied cells due to row-spanning sleepers in this deck to render spacers
        var occupied = {};
        canvasSeats.forEach(function(s) {
            var isSleeper = s.type.toLowerCase().includes('sleeper') && !s.type.toLowerCase().includes('semi');
            if (isSleeper) {
                occupied[(s.row + 1) + ',' + s.col] = true;
            }
        });

        for (var r = 0; r < rows; r++) {
            var rowHeaderCell = $('<div class="grid-cell" style="cursor: pointer; font-size: 0.7rem; color: var(--text-muted);" data-row-header="' + r + '"><?= __('row_label_grid', 'Row') ?> ' + (r + 1) + '</div>');
            rowHeaderCell.css({
                'grid-row': (r + 1),
                'grid-column': 1
            });
            rowHeaderCell.click(handleRowHeaderClick(r, canvasSeats));
            canvas.append(rowHeaderCell);
            
            for (var c = 0; c < cols; c++) {
                if (occupied[r + ',' + c]) {
                    var spacer = $('<div class="grid-cell spacer-cell"></div>');
                    spacer.css({
                        'grid-row': (r + 1),
                        'grid-column': (c + 2),
                        'visibility': 'hidden',
                        'pointer-events': 'none'
                    });
                    canvas.append(spacer);
                    continue;
                }

                var seat = canvasSeats.find(s => s.row === r && s.col === c);
                var cell = $('<div class="grid-cell"></div>');
                cell.css({
                    'grid-row': (r + 1),
                    'grid-column': (c + 2)
                });

                if (seat) {
                    var isSelected = selectedSeats.includes(seat.number) ? ' selected-action' : '';
                    var typeClass = ' type-' + seat.type.toLowerCase().replace(/ /g, '-');
                    var isSleeper = seat.type.toLowerCase().indexOf('sleeper') !== -1 && seat.type.toLowerCase().indexOf('semi') === -1;
                    if (isSleeper) {
                        cell.css({
                            'grid-row': (r + 1) + ' / span 2',
                            'height': '130px'
                        });
                    }
                    var sleeperClass = isSleeper ? ' sleeper-berth' : '';
                    var box = $('<div class="console-seat-box ' + seat.status + isSelected + typeClass + sleeperClass + '" data-seat="' + seat.number + '">' +
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
        return function(e) {
            if (seat.status === 'booked' || seat.status === 'female_booked') {
                alert("<?= __('booked_seats_not_modifiable_alert', 'Booked seats cannot be modified.') ?>");
                return;
            }
            
            // Check if Ctrl or Cmd key is pressed for multi-selection
            var isMulti = e.ctrlKey || e.metaKey;
            
            if (isMulti) {
                if (selectedSeats.includes(seat.number)) {
                    selectedSeats = selectedSeats.filter(num => num !== seat.number);
                } else {
                    selectedSeats.push(seat.number);
                }
            } else {
                // Default single select
                if (selectedSeats.includes(seat.number) && selectedSeats.length === 1) {
                    selectedSeats = [];
                } else {
                    selectedSeats = [seat.number];
                }
            }
            renderConsoleGrid();
            updateSelectionPreview();
        };
    }

    function handleRowHeaderClick(rowIdx, canvasSeats) {
        return function() {
            var targetSeatsList = canvasSeats || seats;
            var rowSeats = targetSeatsList.filter(s => s.row === rowIdx && s.status !== 'booked' && s.status !== 'female_booked');
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
        $('#selection_preview').text(selectedSeats.length + " <?= __('seats_selected_summary_label', 'Seat(s) Selected') ?> (" + selectedSeats.join(', ') + ")");
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
