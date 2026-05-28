<?php
/**
 * Trip Seating Pricing Configuration Page
 */
require_once __DIR__ . '/header.php';

$trip_id = intval($_GET['trip_id'] ?? 0);
if ($trip_id === 0) {
    header("Location: " . BASE_URL . "/admin/trips.php");
    exit();
}

// Fetch trip details
$stmt = $pdo->prepare("
    SELECT t.*, b.bus_name, b.bus_type, b.seat_layout_type, r.source, r.destination 
    FROM trips t
    JOIN buses b ON t.bus_id = b.id
    JOIN routes r ON t.route_id = r.id
    WHERE t.id = ? AND b.admin_id = ? AND t.status = 'active'
    LIMIT 1
");
$stmt->execute([$trip_id, $_SESSION['user_id']]);
$trip = $stmt->fetch();

if (!$trip) {
    die("Trip not found or unauthorized.");
}

$error = '';
$success = '';

// Handle Pricing Updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_pricing') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token validation failed.";
    } else {
        $apply_target = $_POST['apply_target'] ?? 'selected'; // 'selected' or 'entire_bus'
        $seat_price = floatval($_POST['seat_price'] ?? 0.00);
        $target_seats = $_POST['target_seats'] ?? ''; // Comma separated list

        if ($seat_price <= 0) {
            $error = "Price must be a positive numeric value.";
        } else {
            try {
                $pdo->beginTransaction();

                // Prepare queries for both tables to keep them synchronized
                $upsert = $pdo->prepare("
                    INSERT INTO seat_pricing (trip_id, seat_number, base_price, current_price, offer_price)
                    VALUES (?, ?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE base_price = VALUES(base_price), current_price = VALUES(current_price), offer_price = VALUES(offer_price)
                ");

                $upsert_override = $pdo->prepare("
                    INSERT INTO seat_price_overrides (trip_id, seat_number, custom_price, updated_by)
                    VALUES (?, ?, ?, ?)
                    ON DUPLICATE KEY UPDATE custom_price = VALUES(custom_price), updated_by = VALUES(updated_by)
                ");

                if ($apply_target === 'entire_bus') {
                    // Update pricing for all active seats of this bus
                    $seats_stmt = $pdo->prepare("SELECT seat_number FROM bus_seats WHERE bus_id = ? AND is_active = 1");
                    $seats_stmt->execute([$trip['bus_id']]);
                    $all_seats = $seats_stmt->fetchAll(PDO::FETCH_COLUMN);

                    foreach ($all_seats as $seat_num) {
                        $upsert->execute([$trip_id, $seat_num, $seat_price, $seat_price, $seat_price]);
                        $upsert_override->execute([$trip_id, $seat_num, $seat_price, $_SESSION['user_id']]);
                    }
                    
                    log_activity($pdo, $_SESSION['user_id'], 'PRICE_CHANGE_BULK', "Updated pricing for all seats on Trip ID: $trip_id to ₹$seat_price");
                    $success = "Pricing applied to all seats on the bus successfully!";
                } else {
                    // Apply to selected seats
                    $seats_array = array_filter(array_map('trim', explode(',', $target_seats)));
                    if (empty($seats_array)) {
                        $error = "No target seats selected for pricing modification.";
                    } else {
                        foreach ($seats_array as $seat_num) {
                            $upsert->execute([$trip_id, $seat_num, $seat_price, $seat_price, $seat_price]);
                            $upsert_override->execute([$trip_id, $seat_num, $seat_price, $_SESSION['user_id']]);
                        }
                        
                        log_activity($pdo, $_SESSION['user_id'], 'PRICE_CHANGE_SINGLE', "Updated pricing for seats (" . implode(',', $seats_array) . ") on Trip ID: $trip_id to ₹$seat_price");
                        $success = "Pricing applied to selected seats (" . implode(', ', $seats_array) . ") successfully!";
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
                $error = "Failed to update pricing overrides: " . $e->getMessage();
            }
        }
    }
}

// Fetch visual layout constraints
$layout_stmt = $pdo->prepare("SELECT * FROM bus_layouts WHERE bus_id = ? LIMIT 1");
$layout_stmt->execute([$trip['bus_id']]);
$layout = $layout_stmt->fetch();

$rows_count = $layout ? intval($layout['rows_count']) : 10;
$cols_count = $layout ? intval($layout['cols_count']) : 5;

// Fetch configured seats joining both pricing tables
$seats_stmt = $pdo->prepare("
    SELECT s.*, 
           p.base_price AS trip_base, 
           COALESCE(spo.custom_price, p.current_price) AS trip_current, 
           p.offer_price AS trip_offer
    FROM bus_seats s
    LEFT JOIN seat_pricing p ON s.seat_number = p.seat_number AND p.trip_id = ?
    LEFT JOIN seat_price_overrides spo ON s.seat_number = spo.seat_number AND spo.trip_id = ?
    WHERE s.bus_id = ? AND s.is_active = 1
");
$seats_stmt->execute([$trip_id, $trip_id, $trip['bus_id']]);
$db_seats = $seats_stmt->fetchAll();

// Map layout seats
$seats_json = [];
foreach ($db_seats as $s) {
    $base = !empty($s['trip_base']) ? floatval($s['trip_base']) : floatval($s['base_price']);
    $curr = !empty($s['trip_current']) ? floatval($s['trip_current']) : $base;
    $offr = !empty($s['trip_offer']) ? floatval($s['trip_offer']) : $base;
    
    $seats_json[] = [
        'number' => $s['seat_number'],
        'row' => intval($s['row_pos']),
        'col' => intval($s['col_pos']),
        'type' => $s['seat_type'],
        'base_price' => $base,
        'current_price' => $curr,
        'offer_price' => $offr
    ];
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
    <!-- Pricing Config Form Panel -->
    <div class="col-md-4">
        <div class="glass-card p-4">
            <h5 class="fw-bold mb-3"><i class="fa-solid fa-tags text-indigo me-2"></i>Configure Seat Fare</h5>
            
            <form action="" method="POST" id="pricingForm">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="action" value="save_pricing">
                <input type="hidden" name="target_seats" id="form_target_seats" value="">

                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Target Application</label>
                    <select name="apply_target" id="apply_target" class="form-select form-control-swift" required>
                        <option value="selected">Selected Seat(s) Only</option>
                        <option value="entire_bus">Entire Bus (Apply to all seats)</option>
                    </select>
                </div>

                <div class="p-3 mb-4 rounded bg-dark bg-opacity-20 border border-secondary border-opacity-10" id="selected_seats_preview_block">
                    <span class="text-secondary small d-block mb-1">Target Seats Selected:</span>
                    <div id="selected_seats_badges" class="d-flex flex-wrap gap-1">
                        <span class="text-muted small">No seats selected. Tap seats in the grid to select.</span>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Seat Price (₹)</label>
                    <input type="number" name="seat_price" id="seat_price" class="form-control form-control-swift" value="<?= htmlspecialchars($trip['base_fare']) ?>" min="50" step="10" required>
                </div>

                <button type="submit" id="btnApplyPricing" class="btn btn-primary-gradient w-100 py-3 font-semibold">
                    <i class="fa-solid fa-check-double me-2"></i>Apply Price Change
                </button>
            </form>

            <a href="trips.php" class="btn btn-secondary-glass w-100 mt-2">Back to Active Schedules</a>
        </div>
    </div>

    <!-- Visual Interactive Grid -->
    <div class="col-md-8">
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary border-opacity-20 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-white mb-0"><?= htmlspecialchars($trip['bus_name']) ?></h4>
                    <span class="text-secondary small"><?= htmlspecialchars($trip['source']) ?> to <?= htmlspecialchars($trip['destination']) ?></span>
                </div>
                <div class="d-flex gap-1">
                    <button type="button" id="btnSelectAll" class="btn btn-secondary-glass py-1 px-2 small">Select All</button>
                    <button type="button" id="btnSelectNone" class="btn btn-secondary-glass py-1 px-2 small">Clear Selection</button>
                </div>
            </div>

            <div class="text-center overflow-auto py-3">
                <div id="grid-canvas" class="mx-auto" style="display: inline-grid; gap: 10px; padding: 15px; border-radius: 12px; background: rgba(0,0,0,0.15);"></div>
            </div>
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
.pricing-seat-box {
    width: 100%;
    height: 100%;
    border-radius: 8px;
    border: 1px solid var(--border-glass);
    background: var(--bg-secondary);
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
.pricing-seat-box:hover {
    border-color: var(--accent-primary);
    background: rgba(200, 169, 107, 0.08);
}
.pricing-seat-box.selected {
    background: var(--accent-gold-gradient);
    border-color: var(--accent-primary);
    color: var(--text-white-fixed);
    outline: 2px solid var(--accent-primary) !important;
    outline-offset: 2px;
}
.pricing-seat-box .seat-num {
    font-size: 0.8rem;
    font-weight: 700;
}
.pricing-seat-box .seat-price {
    font-size: 0.55rem;
    font-weight: 500;
    opacity: 0.85;
}
.pricing-seat-box.sleeper-berth {
    height: 130px;
    position: relative;
    z-index: 10;
}
</style>

<script>
$(document).ready(function() {
    var seats = <?= json_encode($seats_json) ?>;
    var rows = <?= $rows_count ?>;
    var cols = <?= $cols_count ?>;
    var selectedSeats = [];

    function renderGrid() {
        var canvas = $('#grid-canvas');
        canvas.empty();
        canvas.css({
            'grid-template-rows': 'repeat(' + rows + ', 60px)',
            'grid-template-columns': 'repeat(' + (cols + 1) + ', 60px)'
        });

        // Map occupied cells due to row-spanning sleepers
        var occupied = {};
        seats.forEach(function(s) {
            var isSleeper = s.type.toLowerCase().indexOf('sleeper') !== -1 && s.type.toLowerCase().indexOf('semi') === -1;
            if (isSleeper) {
                occupied[(s.row + 1) + ',' + s.col] = true;
            }
        });

        for (var r = 0; r < rows; r++) {
            var rowHeaderCell = $('<div class="grid-cell" style="cursor: pointer; font-size: 0.7rem; color: var(--text-muted);" data-row-header="' + r + '">Row ' + (r + 1) + '</div>');
            rowHeaderCell.css({
                'grid-row': (r + 1),
                'grid-column': 1
            });
            rowHeaderCell.click(handleRowHeaderClick(r));
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

                var seat = seats.find(s => s.row === r && s.col === c);
                var cell = $('<div class="grid-cell"></div>');
                cell.css({
                    'grid-row': (r + 1),
                    'grid-column': (c + 2)
                });

                if (seat) {
                    var isSelected = selectedSeats.includes(seat.number) ? ' selected' : '';
                    var typeClass = ' type-' + seat.type.toLowerCase().replace(/ /g, '-');
                    var isSleeper = seat.type.toLowerCase().indexOf('sleeper') !== -1 && seat.type.toLowerCase().indexOf('semi') === -1;
                    if (isSleeper) {
                        cell.css({
                            'grid-row': (r + 1) + ' / span 2',
                            'height': '130px'
                        });
                    }
                    var sleeperClass = isSleeper ? ' sleeper-berth' : '';
                    var box = $('<div class="pricing-seat-box' + isSelected + typeClass + sleeperClass + '" data-seat="' + seat.number + '">' +
                        '<span class="seat-num">' + seat.number + '</span>' +
                        '<span class="seat-price">₹' + seat.current_price.toFixed(0) + '</span>' +
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
            if (selectedSeats.includes(seat.number)) {
                selectedSeats = selectedSeats.filter(s => s !== seat.number);
            } else {
                selectedSeats.push(seat.number);
            }
            renderGrid();
            updateSelectedPreview();
        };
    }

    function handleRowHeaderClick(rowIdx) {
        return function() {
            var rowSeats = seats.filter(s => s.row === rowIdx);
            var rowSeatNums = rowSeats.map(s => s.number);
            
            var allSelected = rowSeatNums.every(num => selectedSeats.includes(num));
            if (allSelected) {
                selectedSeats = selectedSeats.filter(num => !rowSeatNums.includes(num));
            } else {
                rowSeatNums.forEach(num => {
                    if (!selectedSeats.includes(num)) {
                        selectedSeats.push(num);
                    }
                });
            }
            renderGrid();
            updateSelectedPreview();
        };
    }

    function updateSelectedPreview() {
        var badgesContainer = $('#selected_seats_badges');
        badgesContainer.empty();

        if (selectedSeats.length === 0) {
            badgesContainer.append('<span class="text-muted small">No seats selected. Tap seats in the grid to select.</span>');
            $('#form_target_seats').val('');
            return;
        }

        selectedSeats.forEach(function(num) {
            badgesContainer.append('<span class="badge bg-indigo p-2 px-3 rounded-pill" style="font-size: 0.75rem;">' + num + '</span>');
        });

        $('#form_target_seats').val(selectedSeats.join(','));

        var firstSeat = seats.find(s => s.number === selectedSeats[0]);
        if (firstSeat) {
            $('#seat_price').val(firstSeat.current_price);
        }
    }

    $('#btnSelectAll').click(function() {
        selectedSeats = seats.map(s => s.number);
        renderGrid();
        updateSelectedPreview();
    });

    $('#btnSelectNone').click(function() {
        selectedSeats = [];
        renderGrid();
        updateSelectedPreview();
    });

    $('#apply_target').change(function() {
        if ($(this).val() === 'entire_bus') {
            $('#selected_seats_preview_block').hide();
        } else {
            $('#selected_seats_preview_block').show();
        }
    });

    renderGrid();
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
