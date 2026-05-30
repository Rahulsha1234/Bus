<?php
/**
 * Trip Scheduler CRUD & Management
 */
require_once __DIR__ . '/header.php';
?>
<!-- Flatpickr CSS for 24-hour time selector -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
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

                    // Verify bus ownership
                    $bus_chk = $pdo->prepare("SELECT id, seat_layout_type FROM buses WHERE id = ? AND admin_id = ? AND status = 'active' LIMIT 1");
                    $bus_chk->execute([$bus_id, $admin_id]);
                    $bus = $bus_chk->fetch();

                    // Verify route ownership
                    $route_chk = $pdo->prepare("SELECT 1 FROM routes WHERE id = ? AND admin_id = ? AND status = 'active' LIMIT 1");
                    $route_chk->execute([$route_id, $admin_id]);
                    $route_exists = $route_chk->fetchColumn();

                    if (!$bus) {
                        $pdo->rollBack();
                        $error = "Invalid bus selection or unauthorized ownership.";
                    } elseif (!$route_exists) {
                        $pdo->rollBack();
                        $error = "Invalid route selection or unauthorized ownership.";
                    } else {
                        // 2. Schedule Trip
                        $stmt = $pdo->prepare("
                            INSERT INTO trips (bus_id, route_id, admin_id, departure_time, arrival_time, base_fare, discount_type, percentage, fixed, status) 
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'ACTIVE')
                        ");
                        $stmt->execute([$bus_id, $route_id, $admin_id, $dep_time, $arr_time, $fare, $discount_type, $percentage, $fixed]);
                        $trip_id = $pdo->lastInsertId();

                        // 3. Initialize all seat records (Optimized Bulk Insert to eliminate N+1)
                        // Get custom seats from layout if configured
                        $layout_seats_stmt = $pdo->prepare("SELECT seat_number, base_price FROM bus_seats WHERE bus_id = ? AND is_active = 1");
                        $layout_seats_stmt->execute([$bus_id]);
                        $layout_seats = $layout_seats_stmt->fetchAll();

                        if (count($layout_seats) > 0) {
                            $seat_values = [];
                            $seat_params = [];
                            $price_values = [];
                            $price_params = [];
                            foreach ($layout_seats as $ls) {
                                $seat_values[] = "(?, ?, 'available')";
                                $seat_params[] = $trip_id;
                                $seat_params[] = $ls['seat_number'];

                                $price_values[] = "(?, ?, ?, ?, ?)";
                                $price_params[] = $trip_id;
                                $price_params[] = $ls['seat_number'];
                                $price_params[] = $ls['base_price'];
                                $price_params[] = $ls['base_price'];
                                $price_params[] = $ls['base_price'];
                            }
                            $pdo->prepare("INSERT INTO trip_seats (trip_id, seat_number, status) VALUES " . implode(',', $seat_values))->execute($seat_params);
                            $pdo->prepare("INSERT INTO seat_pricing (trip_id, seat_number, base_price, current_price, offer_price) VALUES " . implode(',', $price_values))->execute($price_params);
                        } else {
                            $seat_values = [];
                            $seat_params = [];
                            $price_values = [];
                            $price_params = [];
                            // Standard layout fallback
                            if ($bus['seat_layout_type'] === '2x1_sleeper') {
                                for ($i = 1; $i <= 15; $i++) {
                                    foreach (["L$i", "U$i"] as $snum) {
                                        $sf = ($snum[0] === 'U') ? $fare + 100 : $fare;
                                        $seat_values[] = "(?, ?, 'available')";
                                        $seat_params[] = $trip_id;
                                        $seat_params[] = $snum;

                                        $price_values[] = "(?, ?, ?, ?, ?)";
                                        $price_params[] = $trip_id;
                                        $price_params[] = $snum;
                                        $price_params[] = $sf;
                                        $price_params[] = $sf;
                                        $price_params[] = $sf;
                                    }
                                }
                            } else {
                                for ($i = 1; $i <= 40; $i++) {
                                    $snum = strval($i);
                                    $seat_values[] = "(?, ?, 'available')";
                                    $seat_params[] = $trip_id;
                                    $seat_params[] = $snum;

                                    $price_values[] = "(?, ?, ?, ?, ?)";
                                    $price_params[] = $trip_id;
                                    $price_params[] = $snum;
                                    $price_params[] = $fare;
                                    $price_params[] = $fare;
                                    $price_params[] = $fare;
                                }
                            }
                            $pdo->prepare("INSERT INTO trip_seats (trip_id, seat_number, status) VALUES " . implode(',', $seat_values))->execute($seat_params);
                            $pdo->prepare("INSERT INTO seat_pricing (trip_id, seat_number, base_price, current_price, offer_price) VALUES " . implode(',', $price_values))->execute($price_params);
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
            $status = $_POST['status'] ?? 'ACTIVE';
            $discount_type = $_POST['discount_type'] ?? 'none';
            $percentage = floatval($_POST['percentage'] ?? 0.00);
            $fixed = floatval($_POST['fixed'] ?? 0.00);

            if ($trip_id === 0 || $bus_id === 0 || $route_id === 0 || empty($dep_time) || empty($arr_time)) {
                $error = "Please fill in all scheduling fields.";
            } elseif (strtotime($dep_time) >= strtotime($arr_time)) {
                $error = "Departure date/time must be earlier than Arrival date/time.";
            } else {
                // Verify target bus, route and trip ownership
                $trip_chk = $pdo->prepare("SELECT 1 FROM trips WHERE id = ? AND admin_id = ? LIMIT 1");
                $trip_chk->execute([$trip_id, $admin_id]);
                $trip_exists = $trip_chk->fetchColumn();

                $bus_chk = $pdo->prepare("SELECT 1 FROM buses WHERE id = ? AND admin_id = ? AND status = 'active' LIMIT 1");
                $bus_chk->execute([$bus_id, $admin_id]);
                $bus_exists = $bus_chk->fetchColumn();

                $route_chk = $pdo->prepare("SELECT 1 FROM routes WHERE id = ? AND admin_id = ? AND status = 'active' LIMIT 1");
                $route_chk->execute([$route_id, $admin_id]);
                $route_exists = $route_chk->fetchColumn();

                if (!$trip_exists) {
                    $error = "Unauthorized trip update request.";
                } elseif (!$bus_exists) {
                    $error = "Invalid or unauthorized bus selection.";
                } elseif (!$route_exists) {
                    $error = "Invalid or unauthorized route selection.";
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE trips 
                        SET bus_id = ?, route_id = ?, departure_time = ?, arrival_time = ?, base_fare = ?, discount_type = ?, percentage = ?, fixed = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$bus_id, $route_id, $dep_time, $arr_time, $fare, $discount_type, $percentage, $fixed, $status, $trip_id]);
                    $success = "Trip details updated successfully!";
                    log_activity($pdo, $admin_id, 'TRIP_EDIT', "Updated Trip ID: $trip_id");
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
                $del = $pdo->prepare("UPDATE trips SET status = 'CANCELLED' WHERE id = ?");
                $del->execute([$trip_id]);
                
                $success = "Trip cancelled and removed successfully!";
                log_activity($pdo, $admin_id, 'TRIP_DELETE', "Soft deleted Trip ID: $trip_id");
            } else {
                $error = "Failed to cancel trip. Unauthorized deletion request.";
            }
        }
    }
}

// Fetch Agent's Scheduled Trips with Pagination and Filter Lifecycle System
try {
    $filter = $_GET['filter'] ?? 'upcoming';
    $params = [$admin_id];

    // Aggregated statistics counters query
    $stats_stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN t.status IN ('ACTIVE', 'active') AND t.departure_time >= NOW() THEN 1 ELSE 0 END) AS upcoming_count,
            SUM(CASE WHEN t.status IN ('COMPLETED', 'completed') THEN 1 ELSE 0 END) AS completed_count,
            SUM(CASE WHEN t.status IN ('CANCELLED', 'cancelled') THEN 1 ELSE 0 END) AS cancelled_count
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        WHERE b.admin_id = ?
    ");
    $stats_stmt->execute([$admin_id]);
    $stats = $stats_stmt->fetch();
    $upcoming_count = intval($stats['upcoming_count'] ?? 0);
    $completed_count = intval($stats['completed_count'] ?? 0);
    $cancelled_count = intval($stats['cancelled_count'] ?? 0);

    // Build conditional where clause for filter
    $where = "WHERE b.admin_id = ?";
    if ($filter === 'upcoming') {
        $where .= " AND t.status IN ('ACTIVE', 'active') AND t.departure_time >= NOW()";
    } elseif ($filter === 'completed') {
        $where .= " AND t.status IN ('COMPLETED', 'completed')";
    } elseif ($filter === 'cancelled') {
        $where .= " AND t.status IN ('CANCELLED', 'cancelled')";
    }

    $count_stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        $where
    ");
    $count_stmt->execute($params);
    $total_records = intval($count_stmt->fetchColumn());

    $limit = 10;
    $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
    $offset = ($page - 1) * $limit;
    $total_pages = ceil($total_records / $limit);

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
        $where
        ORDER BY t.departure_time DESC
        LIMIT " . intval($limit) . " OFFSET " . intval($offset) . "
    ");
    $stmt->execute($params);
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
    $upcoming_count = 0;
    $completed_count = 0;
    $cancelled_count = 0;
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

<!-- Trip Statistics Summary Cards -->
<div class="row g-4 mb-4 text-center">
    <div class="col-md-4">
        <div class="glass-card p-3" style="border-top: 3px solid #198754;">
            <span class="text-secondary small d-block mb-1">UPCOMING TRIPS</span>
            <span class="fs-4 fw-bold text-success font-monospace"><?= $upcoming_count ?></span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card p-3" style="border-top: 3px solid #fbbf24;">
            <span class="text-secondary small d-block mb-1">COMPLETED TRIPS</span>
            <span class="fs-4 fw-bold text-warning font-monospace"><?= $completed_count ?></span>
        </div>
    </div>
    <div class="col-md-4">
        <div class="glass-card p-3" style="border-top: 3px solid #ef4444;">
            <span class="text-secondary small d-block mb-1">CANCELLED TRIPS</span>
            <span class="fs-4 fw-bold text-danger font-monospace"><?= $cancelled_count ?></span>
        </div>
    </div>
</div>

<!-- Actions & Filter Toolbar -->
<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
    <div class="d-flex flex-wrap gap-2">
        <a href="?filter=upcoming" class="btn <?= $filter === 'upcoming' ? 'btn-primary-gradient' : 'btn-secondary-glass' ?> py-2 px-3 small">Upcoming</a>
        <a href="?filter=completed" class="btn <?= $filter === 'completed' ? 'btn-primary-gradient' : 'btn-secondary-glass' ?> py-2 px-3 small">Completed</a>
        <a href="?filter=cancelled" class="btn <?= $filter === 'cancelled' ? 'btn-primary-gradient' : 'btn-secondary-glass' ?> py-2 px-3 small">Cancelled</a>
        <a href="?filter=all" class="btn <?= $filter === 'all' ? 'btn-primary-gradient' : 'btn-secondary-glass' ?> py-2 px-3 small">All Trips</a>
    </div>
    <button class="btn btn-primary-gradient" data-bs-toggle="modal" data-bs-target="#scheduleTripModal"><i class="fa-solid fa-circle-plus me-2"></i>Schedule Trip</button>
</div>

<!-- Trips Table -->
<div class="glass-card p-4">
    <?php if (count($trips) === 0): ?>
        <div class="text-center py-5 text-secondary small">
            <i class="fa-solid fa-calendar-xmark mb-3 d-block" style="font-size: 3rem; color: #475569;"></i>
            No scheduled trips found matching the selected filter.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle datatable-swift">
                <thead>
                    <tr>
                        <th>Bus details</th>
                        <th>Route details</th>
                        <th>Departure Timing</th>
                        <th>Arrival Timing</th>
                        <th>Discount</th>
                        <th>Status</th>
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
                            <td>
                                <?php
                                $status_badge = 'bg-secondary text-white';
                                if (strcasecmp($trip['trip_status'], 'active') === 0) {
                                    $status_badge = 'bg-success bg-opacity-10 text-success border border-success border-opacity-25';
                                } elseif (strcasecmp($trip['trip_status'], 'completed') === 0) {
                                    $status_badge = 'bg-warning bg-opacity-10 text-warning border border-warning border-opacity-25';
                                } elseif (strcasecmp($trip['trip_status'], 'cancelled') === 0) {
                                    $status_badge = 'bg-danger bg-opacity-10 text-danger border border-danger border-opacity-25';
                                }
                                ?>
                                <span class="badge <?= $status_badge ?> text-uppercase" style="font-size: 0.75rem;">
                                    <?= htmlspecialchars($trip['trip_status']) ?>
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
        
        <?php if ($total_pages > 1): ?>
            <div class="d-flex justify-content-between align-items-center mt-4">
                <div class="text-secondary small">
                    Showing <?= $offset + 1 ?> to <?= min($total_records, $offset + $limit) ?> of <?= $total_records ?> entries
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-swift mb-0">
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>" aria-label="Previous">
                                <span aria-hidden="true">&laquo;</span>
                            </a>
                        </li>
                        <?php for ($p = 1; $p <= $total_pages; $p++): ?>
                            <li class="page-item <?= ($p == $page) ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                            </li>
                        <?php endfor; ?>
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>" aria-label="Next">
                                <span aria-hidden="true">&raquo;</span>
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
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
            <form action="" method="POST">
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
                            <select name="discount_type" id="add_discount_type" class="form-select form-control-swift">
                                <option value="none">None</option>
                                <option value="percentage">Percentage (%)</option>
                                <option value="fixed">Fixed (₹)</option>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3" id="add_percentage_wrapper">
                            <label class="form-label text-secondary small fw-semibold">Percentage (%)</label>
                            <input type="number" name="percentage" class="form-control form-control-swift" min="0" max="100" step="0.01" value="0.00">
                        </div>
                        <div class="col-md-4 mb-3" id="add_fixed_wrapper">
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
            <form action="" method="POST">
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
                        <div class="col-md-4 mb-3" id="edit_percentage_wrapper">
                            <label class="form-label text-secondary small fw-semibold">Percentage (%)</label>
                            <input type="number" name="percentage" id="edit_percentage" class="form-control form-control-swift" min="0" max="100" step="0.01">
                        </div>
                        <div class="col-md-4 mb-3" id="edit_fixed_wrapper">
                            <label class="form-label text-secondary small fw-semibold">Fixed (₹)</label>
                            <input type="number" name="fixed" id="edit_fixed" class="form-control form-control-swift" min="0" step="0.01">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Trip Status</label>
                        <select name="status" id="edit_status" class="form-select form-control-swift" required>
                            <option value="ACTIVE">Active (Available)</option>
                            <option value="COMPLETED">Completed</option>
                            <option value="CANCELLED">Cancelled</option>
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
            <form action="" method="POST">
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
        allowInput: true,
        minDate: "today"
    });

    function toggleDiscountFields(prefix) {
        var type = $('#' + prefix + '_discount_type').val();
        if (type === 'percentage') {
            $('#' + prefix + '_percentage_wrapper').show();
            $('#' + prefix + '_fixed_wrapper').hide();
        } else if (type === 'fixed') {
            $('#' + prefix + '_percentage_wrapper').hide();
            $('#' + prefix + '_fixed_wrapper').show();
        } else {
            $('#' + prefix + '_percentage_wrapper').hide();
            $('#' + prefix + '_fixed_wrapper').hide();
        }
    }

    // Bind change events
    $('#add_discount_type').change(function() {
        toggleDiscountFields('add');
    });
    $('#edit_discount_type').change(function() {
        toggleDiscountFields('edit');
    });

    // Run on load
    toggleDiscountFields('add');
    toggleDiscountFields('edit');

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

        // Trigger dynamic fields check on edit load
        toggleDiscountFields('edit');
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
