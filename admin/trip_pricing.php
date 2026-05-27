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
        $base_price = floatval($_POST['base_price'] ?? 0.00);
        $current_price = floatval($_POST['current_price'] ?? 0.00);
        $offer_price = floatval($_POST['offer_price'] ?? 0.00);
        $target_seats = $_POST['target_seats'] ?? ''; // Comma separated list

        if ($base_price <= 0 || $current_price <= 0 || $offer_price <= 0) {
            $error = "Prices must be positive numeric values.";
        } else {
            try {
                $pdo->beginTransaction();

                if ($apply_target === 'entire_bus') {
                    // Update pricing for all seats in this trip
                    // First get all active seat numbers for this trip
                    $seats_stmt = $pdo->prepare("SELECT seat_number FROM trip_seats WHERE trip_id = ?");
                    $seats_stmt->execute([$trip_id]);
                    $all_seats = $seats_stmt->fetchAll(PDO::FETCH_COLUMN);

                    $upsert = $pdo->prepare("
                        INSERT INTO seat_pricing (trip_id, seat_number, base_price, current_price, offer_price)
                        VALUES (?, ?, ?, ?, ?)
                        ON DUPLICATE KEY UPDATE base_price = VALUES(base_price), current_price = VALUES(current_price), offer_price = VALUES(offer_price)
                    ");
                    foreach ($all_seats as $seat_num) {
                        $upsert->execute([$trip_id, $seat_num, $base_price, $current_price, $offer_price]);
                    }
                    
                    log_activity($pdo, $_SESSION['user_id'], 'PRICE_CHANGE_BULK', "Updated pricing for all seats on Trip ID: $trip_id. Base: $base_price, Current: $current_price, Offer: $offer_price");
                    $success = "Pricing applied to all seats on the bus successfully!";
                } else {
                    // Apply to selected seats
                    $seats_array = array_filter(array_map('trim', explode(',', $target_seats)));
                    if (empty($seats_array)) {
                        $error = "No target seats selected for pricing modification.";
                    } else {
                        $upsert = $pdo->prepare("
                            INSERT INTO seat_pricing (trip_id, seat_number, base_price, current_price, offer_price)
                            VALUES (?, ?, ?, ?, ?)
                            ON DUPLICATE KEY UPDATE base_price = VALUES(base_price), current_price = VALUES(current_price), offer_price = VALUES(offer_price)
                        ");
                        foreach ($seats_array as $seat_num) {
                            $upsert->execute([$trip_id, $seat_num, $base_price, $current_price, $offer_price]);
                        }
                        
                        log_activity($pdo, $_SESSION['user_id'], 'PRICE_CHANGE_SINGLE', "Updated pricing for seats (" . implode(',', $seats_array) . ") on Trip ID: $trip_id. Base: $base_price, Current: $current_price, Offer: $offer_price");
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

// Fetch configured seats
$seats_stmt = $pdo->prepare("
    SELECT s.*, p.base_price AS trip_base, p.current_price AS trip_current, p.offer_price AS trip_offer
    FROM bus_seats s
    LEFT JOIN seat_pricing p ON s.seat_number = p.seat_number AND p.trip_id = ?
    WHERE s.bus_id = ? AND s.is_active = 1
");
$seats_stmt->execute([$trip_id, $trip['bus_id']]);
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
    <div class="col-md-5">
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

                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Base Price (₹)</label>
                    <input type="number" name="base_price" id="base_price" class="form-control form-control-swift" value="<?= htmlspecialchars($trip['base_fare']) ?>" min="50" step="10" required>
                </div>

                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold">Current Price (₹)</label>
                    <input type="number" name="current_price" id="current_price" class="form-control form-control-swift" value="<?= htmlspecialchars($trip['base_fare']) ?>" min="50" step="10" required>
                </div>

                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Offer Price (Discounted) (₹)</label>
                    <input type="number" name="offer_price" id="offer_price" class="form-control form-control-swift" value="<?= htmlspecialchars($trip['base_fare']) ?>" min="50" step="10" required>
                </div>

                <button type="submit" id="btnApplyPricing" class="btn btn-primary-gradient w-100 py-3 font-semibold">
                    <i class="fa-solid fa-check-double me-2"></i>Apply Price Change
                </button>
            </form>

            <a href="trips.php" class="btn btn-secondary-glass w-100 mt-2">Back to Active Schedules</a>
        </div>
    </div>

    <!-- Visual Interactive Grid -->
    <div class="col-md-7">
        <div class="glass-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary border-opacity-20">
                <div>
                    <h4 class="fw-bold text-white mb-0"><?= htmlspecialchars($trip['bus_name']) ?></h4>
                    <span class="text-secondary small"><?= htmlspecialchars($trip['source']) ?> to <?= htmlspecialchars($trip['destination']) ?></span>
                </div>
                <div class="legend-item"><span class="legend-dot" style="background: var(--accent-indigo);"></span><span class="small text-secondary">Selected</span></div>
            </div>

            <div class="text-center overflow-auto py-3">
                <div id="grid-canvas" class="mx-auto" style="display: inline-grid; gap: 10px; padding: 15px; border-radius: 12px; background: rgba(0,0,0,0.15);"></div>
            </div>
        </div>
    </div>
</div>

<style>
.grid-cell {
    width: 65px;
    height: 65px;
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
}
.pricing-seat-box .seat-num {
    font-size: 0.8rem;
    font-weight: 700;
}
.pricing-seat-box .seat-price {
    font-size: 0.6rem;
    font-weight: 500;
    opacity: 0.85;
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
            'grid-template-rows': 'repeat(' + rows + ', 65px)',
            'grid-template-columns': 'repeat(' + cols + ', 65px)'
        });

        for (var r = 0; r < rows; r++) {
            for (var c = 0; c < cols; c++) {
                var seat = seats.find(s => s.row === r && s.col === c);
                var cell = $('<div class="grid-cell"></div>');

                if (seat) {
                    var isSelected = selectedSeats.includes(seat.number) ? ' selected' : '';
                    var typeClass = ' type-' + seat.type.toLowerCase().replace(/ /g, '-');
                    var box = $('<div class="pricing-seat-box' + isSelected + typeClass + '" data-seat="' + seat.number + '">' +
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

        // Prepopulate fields with the first selected seat values for convenience
        var firstSeat = seats.find(s => s.number === selectedSeats[0]);
        if (firstSeat) {
            $('#base_price').val(firstSeat.base_price);
            $('#current_price').val(firstSeat.current_price);
            $('#offer_price').val(firstSeat.offer_price);
        }
    }

    // Apply target select change handler
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
