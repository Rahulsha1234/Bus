<?php
/**
 * Trip Scheduler CRUD & Management
 */
require_once __DIR__ . '/header.php';
?>
<!-- Flatpickr CSS & Dark Theme for 24-hour time selector -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<?php

$admin_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token validation failed.";
    } else {
        $action = $_POST['action'] ?? '';

        // ADD TRIP
        if ($action === 'add') {
            $bus_id = intval($_POST['bus_id'] ?? 0);
            $route_id = intval($_POST['route_id'] ?? 0);
            $dep_time = $_POST['departure_time'] ?? '';
            $arr_time = $_POST['arrival_time'] ?? '';
            $fare = floatval($_POST['base_fare'] ?? 0.00);
            $discount_type = $_POST['discount_type'] ?? 'none';
            $percentage = floatval($_POST['percentage'] ?? 0.00);
            $fixed = floatval($_POST['fixed'] ?? 0.00);

            if ($bus_id === 0 || $route_id === 0 || empty($dep_time) || empty($arr_time)) {
                $error = "Please fill in all scheduling fields.";
            } elseif (strtotime($dep_time) >= strtotime($arr_time)) {
                $error = "Departure date/time must be earlier than Arrival date/time.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Fetch bus details
                    $bus_stmt = $pdo->prepare("SELECT total_seats, seat_layout_type FROM buses WHERE id = ? AND admin_id = ? LIMIT 1");
                    $bus_stmt->execute([$bus_id, $admin_id]);
                    $bus = $bus_stmt->fetch();

                    if (!$bus) {
                        $pdo->rollBack();
                        $error = "Invalid bus selection or unauthorized ownership.";
                    } else {
                        // 2. Schedule Trip
                        $stmt = $pdo->prepare("
                            INSERT INTO trips (bus_id, route_id, admin_id, departure_time, arrival_time, base_fare, discount_type, percentage, fixed, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')
                        ");
                        $stmt->execute([$bus_id, $route_id, $admin_id, $dep_time, $arr_time, $fare, $discount_type, $percentage, $fixed]);
                        $trip_id = $pdo->lastInsertId();

                        // 3. Initialize all seat records
                        // Get custom seats from layout if configured
                        $layout_seats_stmt = $pdo->prepare("SELECT seat_number, base_price FROM bus_seats WHERE bus_id = ? AND is_active = 1");
                        $layout_seats_stmt->execute([$bus_id]);
                        $layout_seats = $layout_seats_stmt->fetchAll();

                        $seatInsertStmt = $pdo->prepare("INSERT INTO trip_seats (trip_id, seat_number, status) VALUES (?, ?, 'available')");
                        $priceInsertStmt = $pdo->prepare("INSERT INTO seat_pricing (trip_id, seat_number, base_price, current_price, offer_price) VALUES (?, ?, ?, ?, ?)");

                        if (count($layout_seats) > 0) {
                            foreach ($layout_seats as $ls) {
                                $seatInsertStmt->execute([$trip_id, $ls['seat_number']]);
                                $priceInsertStmt->execute([$trip_id, $ls['seat_number'], $ls['base_price'], $ls['base_price'], $ls['base_price']]);
                            }
                        } else {
                            // Standard layout fallback
                            if ($bus['seat_layout_type'] === '2x1_sleeper') {
                                for ($i = 1; $i <= 15; $i++) {
                                    $seatInsertStmt->execute([$trip_id, "L$i"]);
                                    $priceInsertStmt->execute([$trip_id, "L$i", $fare, $fare, $fare]);
                                    $seatInsertStmt->execute([$trip_id, "U$i"]);
                                    $priceInsertStmt->execute([$trip_id, "U$i", $fare + 100, $fare + 100, $fare + 100]);
                                }
                            } else {
                                for ($i = 1; $i <= 40; $i++) {
                                    $seatInsertStmt->execute([$trip_id, strval($i)]);
                                    $priceInsertStmt->execute([$trip_id, strval($i), $fare, $fare, $fare]);
                                }
                            }
                        }

                        $pdo->commit();
                        $success = "Trip scheduled successfully and seats initialized!";
                        log_activity($pdo, $admin_id, 'TRIP_ADD', "Scheduled Trip ID: $trip_id (Bus ID $bus_id, Route ID $route_id)");
                    }

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Failed to schedule trip: " . $e->getMessage();
                }
            }
        }

        // EDIT TRIP
        elseif ($action === 'edit') {
            $trip_id = intval($_POST['trip_id'] ?? 0);
            $bus_id = intval($_POST['bus_id'] ?? 0);
            $route_id = intval($_POST['route_id'] ?? 0);
            $dep_time = $_POST['departure_time'] ?? '';
            $arr_time = $_POST['arrival_time'] ?? '';
            $fare = floatval($_POST['base_fare'] ?? 0.00);
            $status = $_POST['status'] ?? 'active';
            $discount_type = $_POST['discount_type'] ?? 'none';
            $percentage = floatval($_POST['percentage'] ?? 0.00);
            $fixed = floatval($_POST['fixed'] ?? 0.00);

            if ($trip_id === 0 || $bus_id === 0 || $route_id === 0 || empty($dep_time) || empty($arr_time)) {
                $error = "Please fill in all scheduling fields.";
            } elseif (strtotime($dep_time) >= strtotime($arr_time)) {
                $error = "Departure date/time must be earlier than Arrival date/time.";
            } else {
                // Verify trip ownership
                $chk = $pdo->prepare("SELECT t.id FROM trips t JOIN buses b ON t.bus_id = b.id WHERE t.id = ? AND b.admin_id = ? LIMIT 1");
                $chk->execute([$trip_id, $admin_id]);

                if ($chk->fetchColumn()) {
                    $stmt = $pdo->prepare("
                        UPDATE trips 
                        SET bus_id = ?, route_id = ?, departure_time = ?, arrival_time = ?, base_fare = ?, discount_type = ?, percentage = ?, fixed = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$bus_id, $route_id, $dep_time, $arr_time, $fare, $discount_type, $percentage, $fixed, $status, $trip_id]);
                    $success = "Trip details updated successfully!";
                    log_activity($pdo, $admin_id, 'TRIP_EDIT', "Updated Trip ID: $trip_id");
                } else {
                    $error = "Unauthorized trip update request.";
                }
            }
        }

        // DELETE TRIP
        elseif ($action === 'delete') {
            $trip_id = intval($_POST['trip_id'] ?? 0);
            
            // Verify trip ownership
            $chk = $pdo->prepare("SELECT t.id FROM trips t JOIN buses b ON t.bus_id = b.id WHERE t.id = ? AND b.admin_id = ? LIMIT 1");
            $chk->execute([$trip_id, $admin_id]);
            
            if ($chk->fetchColumn()) {
                // Soft delete trip
                $del = $pdo->prepare("UPDATE trips SET status = 'cancelled' WHERE id = ?");
                $del->execute([$trip_id]);
                
                $success = "Trip cancelled and removed successfully!";
                log_activity($pdo, $admin_id, 'TRIP_DELETE', "Soft deleted Trip ID: $trip_id");
            } else {
                $error = "Failed to cancel trip. Unauthorized deletion request.";
            }
        }
    }
}

// Fetch Agent's Scheduled Trips
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
            t.bus_id,
            t.route_id,
            t.status AS trip_status,
            b.bus_name,
            b.bus_number,
            b.bus_type,
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

    // Fetch active buses and routes lists
    $buses_stmt = $pdo->prepare("SELECT id, bus_name, bus_number, bus_type FROM buses WHERE admin_id = ? AND status = 'active'");
    $buses_stmt->execute([$admin_id]);
    $agent_buses = $buses_stmt->fetchAll();

    $routes_stmt = $pdo->prepare("SELECT id, source, destination, distance_km FROM routes WHERE admin_id = ? AND status = 'active'");
    $routes_stmt->execute([$admin_id]);
    $agent_routes = $routes_stmt->fetchAll();

} catch (PDOException $e) {
    $trips = [];
    $agent_buses = [];
    $agent_routes = [];
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

<!-- Actions Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="text-white fw-bold mb-0">Active Schedules</h4>
    <button class="btn btn-primary-gradient" data-bs-toggle="modal" data-bs-target="#scheduleTripModal"><i class="fa-solid fa-circle-plus me-2"></i>Schedule Trip</button>
</div>

<!-- Trips Table -->
<div class="glass-card p-4">
    <?php if (count($trips) === 0): ?>
        <div class="text-center py-5 text-secondary small">
            <i class="fa-solid fa-calendar-xmark mb-3 d-block" style="font-size: 3rem; color: #475569;"></i>
            No scheduled active trips. Put your registered buses on routes to accept customer ticket sales.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle">
                <thead>
                    <tr>
                        <th>Bus details</th>
                        <th>Route details</th>
                        <th>Departure Timing</th>
                        <th>Arrival Timing</th>
                        <th>Agent Discount</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($trips as $trip): ?>
                        <tr>
                            <td>
                                <span class="fw-semibold text-white d-block"><?= htmlspecialchars($trip['bus_name']) ?></span>
                                <span class="badge bg-secondary small text-uppercase" style="font-size:0.7rem;"><?= htmlspecialchars($trip['bus_type']) ?></span>
                                <span class="font-monospace text-secondary ms-2 small" style="font-size: 0.8rem;">(<?= htmlspecialchars($trip['bus_number']) ?>)</span>
                            </td>
                            <td><span class="fw-semibold text-white"><?= htmlspecialchars($trip['source']) ?> <i class="fa-solid fa-arrow-right mx-1 text-indigo"></i> <?= htmlspecialchars($trip['destination']) ?></span></td>
                            <td class="text-white"><?= date('d M Y, H:i', strtotime($trip['departure_time'])) ?></td>
                            <td class="text-secondary small"><?= date('d M Y, H:i', strtotime($trip['arrival_time'])) ?></td>
                            <td>
                                <span class="font-monospace text-warning small">
                                    <?php 
                                    if (($trip['discount_type'] ?? 'none') === 'percentage') {
                                        echo htmlspecialchars($trip['percentage']) . '%';
                                    } elseif (($trip['discount_type'] ?? 'none') === 'fixed') {
                                        echo '₹' . htmlspecialchars($trip['fixed']);
                                    } else {
                                        echo 'None';
                                    }
                                    ?>
                                </span>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <a href="trip_pricing.php?trip_id=<?= $trip['trip_id'] ?>" class="btn btn-secondary-glass py-1 px-2 small" title="Configure Seat Prices"><i class="fa-solid fa-tags text-indigo"></i></a>
                                    <button class="btn btn-secondary-glass py-1 px-2 edit-trip-btn" 
                                            data-id="<?= $trip['trip_id'] ?>" 
                                            data-bus="<?= $trip['bus_id'] ?>" 
                                            data-route="<?= $trip['route_id'] ?>" 
                                            data-dep="<?= date('Y-m-d\TH:i', strtotime($trip['departure_time'])) ?>" 
                                            data-arr="<?= date('Y-m-d\TH:i', strtotime($trip['arrival_time'])) ?>" 
                                            data-fare="<?= $trip['base_fare'] ?>" 
                                            data-status="<?= $trip['trip_status'] ?>"
                                            data-discount="<?= htmlspecialchars($trip['discount_type'] ?? 'none') ?>"
                                            data-percentage="<?= htmlspecialchars($trip['percentage'] ?? '0.00') ?>"
                                            data-fixed="<?= htmlspecialchars($trip['fixed'] ?? '0.00') ?>"
                                            data-bs-toggle="modal" data-bs-target="#editTripModal" title="Edit Trip"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button class="btn btn-secondary-glass py-1 px-2 text-danger small delete-trip-btn" data-id="<?= $trip['trip_id'] ?>" data-bs-toggle="modal" data-bs-target="#deleteTripModal" title="Cancel Trip"><i class="fa-solid fa-ban"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- SCHEDULE TRIP MODAL -->
<div class="modal fade" id="scheduleTripModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-calendar-plus me-2 text-indigo"></i>Schedule New Trip</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Select Bus</label>
                        <select name="bus_id" class="form-select form-control-swift" required>
                            <option value="">Choose Vehicle...</option>
                            <?php foreach ($agent_buses as $ab): ?>
                                <option value="<?= $ab['id'] ?>"><?= htmlspecialchars($ab['bus_name']) ?> (<?= htmlspecialchars($ab['bus_number']) ?> - <?= htmlspecialchars($ab['bus_type']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Select Route</label>
                        <select name="route_id" class="form-select form-control-swift" required>
                            <option value="">Choose Route...</option>
                            <?php foreach ($agent_routes as $ar): ?>
                                <option value="<?= $ar['id'] ?>"><?= htmlspecialchars($ar['source']) ?> to <?= htmlspecialchars($ar['destination']) ?> (<?= $ar['distance_km'] ?> km)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Departure Date & Time</label>
                            <input type="text" name="departure_time" class="form-control form-control-swift datetime-picker-24h" placeholder="YYYY-MM-DD HH:MM" pattern="^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01]) (0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter date and time in YYYY-MM-DD HH:MM format (e.g. 2026-05-26 15:30)" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Arrival Date & Time</label>
                            <input type="text" name="arrival_time" class="form-control form-control-swift datetime-picker-24h" placeholder="YYYY-MM-DD HH:MM" pattern="^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01]) (0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter date and time in YYYY-MM-DD HH:MM format (e.g. 2026-05-26 15:30)" required>
                        </div>
                    </div>

                    <input type="hidden" name="base_fare" value="0.00">

                    <!-- Agent Partner Discount Configuration -->
                    <div class="row border-top border-secondary border-opacity-20 pt-3 mt-3">
                        <h6 class="text-white fw-bold mb-3 small text-uppercase">Agent Partner Discount</h6>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Discount Type</label>
                            <select name="discount_type" class="form-select form-control-swift">
                                <option value="none">None</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Percentage (%)</label>
                            <input type="number" name="percentage" class="form-control form-control-swift" min="0" max="100" step="0.01" value="0.00">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Fixed (₹)</label>
                            <input type="number" name="fixed" class="form-control form-control-swift" min="0" step="0.01" value="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-20 p-4">
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Schedule Operations</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT TRIP MODAL -->
<div class="modal fade" id="editTripModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-calendar-check me-2 text-indigo"></i>Modify Trip Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="trip_id" id="edit_trip_id">
                    
                    <div class="mb-3">
                         <label class="form-label text-secondary small fw-semibold">Select Bus</label>
                        <select name="bus_id" id="edit_bus_id" class="form-select form-control-swift" required>
                            <?php foreach ($agent_buses as $ab): ?>
                                <option value="<?= $ab['id'] ?>"><?= htmlspecialchars($ab['bus_name']) ?> (<?= htmlspecialchars($ab['bus_number']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Select Route</label>
                        <select name="route_id" id="edit_route_id" class="form-select form-control-swift" required>
                            <?php foreach ($agent_routes as $ar): ?>
                                <option value="<?= $ar['id'] ?>"><?= htmlspecialchars($ar['source']) ?> to <?= htmlspecialchars($ar['destination']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Departure Date & Time</label>
                            <input type="text" name="departure_time" id="edit_departure_time" class="form-control form-control-swift datetime-picker-24h" placeholder="YYYY-MM-DD HH:MM" pattern="^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01]) (0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter date and time in YYYY-MM-DD HH:MM format (e.g. 2026-05-26 15:30)" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Arrival Date & Time</label>
                            <input type="text" name="arrival_time" id="edit_arrival_time" class="form-control form-control-swift datetime-picker-24h" placeholder="YYYY-MM-DD HH:MM" pattern="^\d{4}-(0[1-9]|1[0-2])-(0[1-9]|[12][0-9]|3[01]) (0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter date and time in YYYY-MM-DD HH:MM format (e.g. 2026-05-26 15:30)" required>
                        </div>
                    </div>

                    <input type="hidden" name="base_fare" id="edit_base_fare">

                    <!-- Agent Partner Discount Configuration -->
                    <div class="row border-top border-secondary border-opacity-20 pt-3 mt-3">
                        <h6 class="text-white fw-bold mb-3 small text-uppercase">Agent Partner Discount</h6>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Discount Type</label>
                            <select name="discount_type" id="edit_discount_type" class="form-select form-control-swift">
                                <option value="none">None</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Percentage (%)</label>
                            <input type="number" name="percentage" id="edit_percentage" class="form-control form-control-swift" min="0" max="100" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Fixed (₹)</label>
                            <input type="number" name="fixed" id="edit_fixed" class="form-control form-control-swift" min="0" step="0.01">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Trip Status</label>
                        <select name="status" id="edit_status" class="form-select form-control-swift" required>
                            <option value="active">Active (Available)</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer border-secondary border-opacity-20 p-4">
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- CANCEL TRIP CONFIRMATION MODAL -->
<div class="modal fade" id="deleteTripModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="border-radius: 20px;">
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="trip_id" id="delete_trip_id">
                    
                    <i class="fa-solid fa-circle-exclamation text-danger mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold mb-2">Cancel scheduled trip?</h5>
                    <p class="text-secondary small">Are you sure you want to cancel this trip? Deleting it will release all ticket holds and seat allocations.</p>
                </div>
                <div class="modal-footer border-0 p-3 d-flex justify-content-around">
                    <button type="button" class="btn btn-secondary-glass w-45 py-2" data-bs-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger w-45 py-2">Yes, Cancel</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Initialize flatpickr for datetime input in 24h mode allowing keyboard typing
    flatpickr('.datetime-picker-24h', {
        enableTime: true,
        dateFormat: "Y-m-d H:i",
        time_24hr: true,
        disableMobile: true,
        allowInput: true
    });

    $('.delete-trip-btn').click(function() {
        $('#delete_trip_id').val($(this).data('id'));
    });

    $('.edit-trip-btn').click(function() {
        $('#edit_trip_id').val($(this).data('id'));
        $('#edit_bus_id').val($(this).data('bus'));
        $('#edit_route_id').val($(this).data('route'));
        
        var depVal = $(this).data('dep');
        var arrVal = $(this).data('arr');
        // Replace T with space to match YYYY-MM-DD HH:MM format
        depVal = depVal ? depVal.replace('T', ' ') : '';
        arrVal = arrVal ? arrVal.replace('T', ' ') : '';
        
        $('#edit_departure_time')[0]._flatpickr.setDate(depVal);
        $('#edit_arrival_time')[0]._flatpickr.setDate(arrVal);
        
        $('#edit_base_fare').val($(this).data('fare'));
        $('#edit_discount_type').val($(this).data('discount'));
        $('#edit_percentage').val($(this).data('percentage'));
        $('#edit_fixed').val($(this).data('fixed'));
        $('#edit_status').val($(this).data('status'));
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
