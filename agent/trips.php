<?php
/**
 * Trip Scheduler CRUD
 */
require_once __DIR__ . '/header.php';

$agent_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Actions (Add, Delete)
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

            if ($bus_id === 0 || $route_id === 0 || empty($dep_time) || empty($arr_time) || $fare === 0.00) {
                $error = "Please fill in all scheduling fields.";
            } elseif (strtotime($dep_time) >= strtotime($arr_time)) {
                $error = "Departure date/time must be earlier than Arrival date/time.";
            } else {
                try {
                    $pdo->beginTransaction();

                    // 1. Fetch bus details (layout, total seats) to generate trip_seats
                    $bus_stmt = $pdo->prepare("SELECT total_seats, seat_layout_type FROM buses WHERE id = ? AND agent_id = ? LIMIT 1");
                    $bus_stmt->execute([$bus_id, $agent_id]);
                    $bus = $bus_stmt->fetch();

                    if (!$bus) {
                        $pdo->rollBack();
                        $error = "Invalid bus selection or unauthorized ownership.";
                    } else {
                        // 2. Schedule Trip
                        $stmt = $pdo->prepare("
                            INSERT INTO trips (bus_id, route_id, departure_time, arrival_time, base_fare) 
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([$bus_id, $route_id, $dep_time, $arr_time, $fare]);
                        $trip_id = $pdo->lastInsertId();

                        // 3. Initialize all seat records as 'available'
                        $seatInsertStmt = $pdo->prepare("INSERT INTO trip_seats (trip_id, seat_number, status) VALUES (?, ?, 'available')");
                        
                        if ($bus['seat_layout_type'] === '2x1_sleeper') {
                            // L1-L15 and U1-U15
                            for ($i = 1; $i <= 15; $i++) {
                                $seatInsertStmt->execute([$trip_id, "L$i"]);
                                $seatInsertStmt->execute([$trip_id, "U$i"]);
                            }
                        } else {
                            // Seater 1-40
                            for ($i = 1; $i <= 40; $i++) {
                                $seatInsertStmt->execute([$trip_id, strval($i)]);
                            }
                        }

                        $pdo->commit();
                        $success = "Trip scheduled successfully and seats initialized!";
                        log_activity($pdo, $agent_id, 'TRIP_ADD', "Scheduled Trip ID: $trip_id (Bus ID $bus_id, Route ID $route_id)");
                    }

                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = "Failed to schedule trip: " . $e->getMessage();
                }
            }
        }

        // DELETE TRIP
        elseif ($action === 'delete') {
            $trip_id = intval($_POST['trip_id'] ?? 0);
            
            try {
                $pdo->beginTransaction();
                
                // Verify trip ownership through bus association
                $chk = $pdo->prepare("
                    SELECT t.id 
                    FROM trips t
                    JOIN buses b ON t.bus_id = b.id
                    WHERE t.id = ? AND b.agent_id = ? 
                    LIMIT 1
                ");
                $chk->execute([$trip_id, $agent_id]);
                
                if ($chk->fetchColumn()) {
                    $del = $pdo->prepare("DELETE FROM trips WHERE id = ?");
                    $del->execute([$trip_id]);
                    
                    $pdo->commit();
                    $success = "Trip cancelled and removed successfully!";
                    log_activity($pdo, $agent_id, 'TRIP_DELETE', "Deleted Trip ID: $trip_id");
                } else {
                    $pdo->rollBack();
                    $error = "Failed to cancel trip. Unauthorized deletion request.";
                }
            } catch (Exception $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Critical deletion failure: " . $e->getMessage();
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
            b.bus_name,
            b.bus_number,
            b.bus_type,
            r.source,
            r.destination
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        JOIN routes r ON t.route_id = r.id
        WHERE b.agent_id = ?
        ORDER BY t.departure_time DESC
    ");
    $stmt->execute([$agent_id]);
    $trips = $stmt->fetchAll();

    // Fetch buses and routes lists for scheduling form selects
    $buses_stmt = $pdo->prepare("SELECT id, bus_name, bus_number, bus_type FROM buses WHERE agent_id = ?");
    $buses_stmt->execute([$agent_id]);
    $agent_buses = $buses_stmt->fetchAll();

    $routes_stmt = $pdo->prepare("SELECT id, source, destination, distance_km FROM routes WHERE agent_id = ?");
    $routes_stmt->execute([$agent_id]);
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
                        <th>Base Ticket Price</th>
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
                            <td><span class="fw-bold text-indigo fs-6">₹<?= number_format($trip['base_fare'], 2) ?></span></td>
                            <td class="text-end">
                                <button class="btn btn-secondary-glass py-1 px-2 text-danger small delete-trip-btn" data-id="<?= $trip['trip_id'] ?>" data-bs-toggle="modal" data-bs-target="#deleteTripModal"><i class="fa-solid fa-ban"></i></button>
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
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#131a2e; border-radius: 20px;">
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
                            <input type="datetime-local" name="departure_time" class="form-control form-control-swift" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Arrival Date & Time</label>
                            <input type="datetime-local" name="arrival_time" class="form-control form-control-swift" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Base Ticket Price (₹)</label>
                        <input type="number" name="base_fare" class="form-control form-control-swift" placeholder="e.g. 750.00" min="50" step="10" required>
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

<!-- CANCEL TRIP CONFIRMATION MODAL -->
<div class="modal fade" id="deleteTripModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#131a2e; border-radius: 20px;">
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
    $('.delete-trip-btn').click(function() {
        $('#delete_trip_id').val($(this).data('id'));
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
