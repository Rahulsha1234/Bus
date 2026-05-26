<?php
/**
 * Route Scheduler CRUD (Full CRUD Support)
 */
require_once __DIR__ . '/header.php';
?>
<!-- Flatpickr CSS & Dark Theme for 24-hour time selector -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/themes/dark.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<?php

$agent_id = $_SESSION['user_id'];
$error = '';
$success = '';

// Handle Actions (Add, Edit, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $error = "Security token validation failed.";
    } else {
        $action = $_POST['action'] ?? '';

        // ADD ROUTE
        if ($action === 'add') {
            $source = trim($_POST['source'] ?? '');
            $destination = trim($_POST['destination'] ?? '');
            $distance = intval($_POST['distance_km'] ?? 0);
            $duration = trim($_POST['duration'] ?? '6 hours');
            
            // Pickup details array compilation
            $pickup_names = $_POST['pickup_name'] ?? [];
            $pickup_times = $_POST['pickup_time'] ?? [];
            $pickups = [];
            foreach ($pickup_names as $idx => $name) {
                if (!empty($name)) {
                    $pickups[] = [
                        'name' => trim($name),
                        'time' => $pickup_times[$idx] ?? '00:00'
                    ];
                }
            }

            // Drop details array compilation
            $drop_names = $_POST['drop_name'] ?? [];
            $drop_times = $_POST['drop_time'] ?? [];
            $drops = [];
            foreach ($drop_names as $idx => $name) {
                if (!empty($name)) {
                    $drops[] = [
                        'name' => trim($name),
                        'time' => $drop_times[$idx] ?? '00:00'
                    ];
                }
            }

            if (empty($source) || empty($destination) || $distance === 0 || empty($pickups) || empty($drops)) {
                $error = "Please fill in all route cities, mileage distance, and at least one pickup/drop milestone.";
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO routes (agent_id, source, destination, distance_km, duration, pickup_points, drop_points, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, 'active')
                ");
                $stmt->execute([
                    $agent_id, 
                    $source, 
                    $destination, 
                    $distance, 
                    $duration,
                    json_encode($pickups), 
                    json_encode($drops)
                ]);
                
                $route_id = $pdo->lastInsertId();
                
                // Add Boarding Points to DB Table
                $b_stmt = $pdo->prepare("INSERT INTO boarding_points (route_id, point_name, departure_time) VALUES (?, ?, ?)");
                foreach ($pickups as $p) {
                    $b_stmt->execute([$route_id, $p['name'], $p['time']]);
                }
                
                // Add Dropping Points to DB Table
                $d_stmt = $pdo->prepare("INSERT INTO dropping_points (route_id, point_name, arrival_time) VALUES (?, ?, ?)");
                foreach ($drops as $d) {
                    $d_stmt->execute([$route_id, $d['name'], $d['time']]);
                }

                $success = "Route registered successfully!";
                log_activity($pdo, $agent_id, 'ROUTE_ADD', "Added route $source to $destination ($distance km, $duration)");
            }
        }

        // UPDATE ROUTE
        elseif ($action === 'edit') {
            $route_id = intval($_POST['route_id'] ?? 0);
            $source = trim($_POST['source'] ?? '');
            $destination = trim($_POST['destination'] ?? '');
            $distance = intval($_POST['distance_km'] ?? 0);
            $duration = trim($_POST['duration'] ?? '6 hours');
            $status = $_POST['status'] ?? 'active';

            // Pickup details array compilation
            $pickup_names = $_POST['pickup_name'] ?? [];
            $pickup_times = $_POST['pickup_time'] ?? [];
            $pickups = [];
            foreach ($pickup_names as $idx => $name) {
                if (!empty($name)) {
                    $pickups[] = [
                        'name' => trim($name),
                        'time' => $pickup_times[$idx] ?? '00:00'
                    ];
                }
            }

            // Drop details array compilation
            $drop_names = $_POST['drop_name'] ?? [];
            $drop_times = $_POST['drop_time'] ?? [];
            $drops = [];
            foreach ($drop_names as $idx => $name) {
                if (!empty($name)) {
                    $drops[] = [
                        'name' => trim($name),
                        'time' => $drop_times[$idx] ?? '00:00'
                    ];
                }
            }

            if (empty($source) || empty($destination) || $distance === 0 || empty($pickups) || empty($drops) || $route_id === 0) {
                $error = "Please fill in all route details and milestones.";
            } else {
                $stmt = $pdo->prepare("
                    UPDATE routes 
                    SET source = ?, destination = ?, distance_km = ?, duration = ?, pickup_points = ?, drop_points = ?, status = ?
                    WHERE id = ? AND agent_id = ?
                ");
                $stmt->execute([
                    $source, 
                    $destination, 
                    $distance, 
                    $duration, 
                    json_encode($pickups), 
                    json_encode($drops), 
                    $status, 
                    $route_id, 
                    $agent_id
                ]);

                // Sync Boarding Points in DB Table
                $pdo->prepare("DELETE FROM boarding_points WHERE route_id = ?")->execute([$route_id]);
                $b_stmt = $pdo->prepare("INSERT INTO boarding_points (route_id, point_name, departure_time) VALUES (?, ?, ?)");
                foreach ($pickups as $p) {
                    $b_stmt->execute([$route_id, $p['name'], $p['time']]);
                }
                
                // Sync Dropping Points in DB Table
                $pdo->prepare("DELETE FROM dropping_points WHERE route_id = ?")->execute([$route_id]);
                $d_stmt = $pdo->prepare("INSERT INTO dropping_points (route_id, point_name, arrival_time) VALUES (?, ?, ?)");
                foreach ($drops as $d) {
                    $d_stmt->execute([$route_id, $d['name'], $d['time']]);
                }

                $success = "Route updated successfully!";
                log_activity($pdo, $agent_id, 'ROUTE_EDIT', "Updated route ID $route_id: $source to $destination");
            }
        }

        // DELETE ROUTE
        elseif ($action === 'delete') {
            $route_id = intval($_POST['route_id'] ?? 0);
            
            // Soft delete
            $stmt = $pdo->prepare("UPDATE routes SET status = 'inactive' WHERE id = ? AND agent_id = ?");
            $stmt->execute([$route_id, $agent_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = "Route removed successfully!";
                log_activity($pdo, $agent_id, 'ROUTE_DELETE', "Soft deleted route ID: $route_id");
            } else {
                $error = "Failed to delete route. Invalid ID or authorization conflict.";
            }
        }
    }
}

// Fetch Agent's Routes
try {
    $stmt = $pdo->prepare("SELECT * FROM routes WHERE agent_id = ? AND status = 'active' ORDER BY id DESC");
    $stmt->execute([$agent_id]);
    $routes = $stmt->fetchAll();
} catch (PDOException $e) {
    $routes = [];
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
    <h4 class="text-white fw-bold mb-0">Scheduled Routes</h4>
    <button class="btn btn-primary-gradient" data-bs-toggle="modal" data-bs-target="#addRouteModal"><i class="fa-solid fa-circle-plus me-2"></i>Schedule Route</button>
</div>

<!-- Routes Table -->
<div class="glass-card p-4">
    <?php if (count($routes) === 0): ?>
        <div class="text-center py-5 text-secondary small">
            <i class="fa-solid fa-route mb-3 d-block" style="font-size: 3rem; color: #475569;"></i>
            No routes configured yet. Setup a travel route to schedule active bus trips.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-swift table-dark table-hover table-borderless align-middle">
                <thead>
                    <tr>
                        <th>Origin City</th>
                        <th>Destination City</th>
                        <th>Distance & Duration</th>
                        <th>Boarding Stations</th>
                        <th>Drop Stations</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $route): 
                        $pickups = json_decode($route['pickup_points'], true) ?? [];
                        $drops = json_decode($route['drop_points'], true) ?? [];
                    ?>
                        <tr>
                            <td><span class="fw-semibold text-white fs-6"><?= htmlspecialchars($route['source']) ?></span></td>
                            <td><span class="fw-semibold text-white fs-6"><?= htmlspecialchars($route['destination']) ?></span></td>
                            <td>
                                <div class="text-white small"><?= htmlspecialchars($route['distance_km']) ?> km</div>
                                <div class="text-secondary small"><?= htmlspecialchars($route['duration'] ?? 'N/A') ?></div>
                            </td>
                            <td>
                                <ul class="list-unstyled mb-0 small text-secondary">
                                    <?php foreach ($pickups as $p): 
                                        $formatted_time = !empty($p['time']) ? date('H:i', strtotime($p['time'])) : '00:00';
                                    ?>
                                        <li><i class="fa-solid fa-circle text-indigo me-1" style="font-size: 0.4rem; color: #818cf8;"></i><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($formatted_time) ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <ul class="list-unstyled mb-0 small text-secondary">
                                    <?php foreach ($drops as $d): 
                                        $formatted_time = !empty($d['time']) ? date('H:i', strtotime($d['time'])) : '00:00';
                                    ?>
                                        <li><i class="fa-solid fa-circle text-pink me-1" style="font-size: 0.4rem; color: #ec4899;"></i><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($formatted_time) ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td class="text-end">
                                <div class="d-flex gap-2 justify-content-end">
                                    <button class="btn btn-secondary-glass py-1 px-2 edit-route-btn" 
                                            data-id="<?= $route['id'] ?>" 
                                            data-source="<?= htmlspecialchars($route['source']) ?>" 
                                            data-destination="<?= htmlspecialchars($route['destination']) ?>" 
                                            data-distance="<?= htmlspecialchars($route['distance_km']) ?>" 
                                            data-duration="<?= htmlspecialchars($route['duration'] ?? '') ?>" 
                                            data-pickups='<?= htmlspecialchars($route['pickup_points'], ENT_QUOTES) ?>' 
                                            data-drops='<?= htmlspecialchars($route['drop_points'], ENT_QUOTES) ?>' 
                                            data-bs-toggle="modal" data-bs-target="#editRouteModal"><i class="fa-solid fa-pen-to-square"></i></button>
                                    <button class="btn btn-secondary-glass py-1 px-2 text-danger small delete-route-btn" data-id="<?= $route['id'] ?>" data-bs-toggle="modal" data-bs-target="#deleteRouteModal"><i class="fa-solid fa-trash-can"></i></button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- ADD ROUTE MODAL -->
<div class="modal fade" id="addRouteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#131a2e; border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-route me-2 text-indigo"></i>Setup Route Layout</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Leaving From (Source)</label>
                            <input type="text" name="source" class="form-control form-control-swift" placeholder="e.g. Bangalore" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Going To (Destination)</label>
                            <input type="text" name="destination" class="form-control form-control-swift" placeholder="e.g. Mumbai" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Distance (km)</label>
                            <input type="number" name="distance_km" class="form-control form-control-swift" placeholder="e.g. 1000" min="10" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Duration (hrs/mins)</label>
                            <input type="text" name="duration" class="form-control form-control-swift" placeholder="e.g. 12 hours" required>
                        </div>
                    </div>

                    <!-- Milestones Configuration Row -->
                    <div class="row mt-4">
                        <!-- Pickups -->
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-indigo fw-bold mb-0">Boarding Stations</h6>
                                <button type="button" class="btn btn-secondary-glass py-1 px-2 small" id="addPickupRowBtn" style="font-size:0.75rem;"><i class="fa-solid fa-plus me-1"></i>Station</button>
                            </div>
                            <div id="pickupRowsContainer">
                                <div class="row g-2 mb-2 alignment-row">
                                    <div class="col-8">
                                        <input type="text" name="pickup_name[]" class="form-control form-control-swift py-1" placeholder="Station name" required>
                                    </div>
                                    <div class="col-4">
                                        <input type="text" name="pickup_time[]" class="form-control form-control-swift py-1 time-picker-24h" placeholder="HH:MM" pattern="^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter time in 24-hour HH:MM format (e.g., 20:30)" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Drops -->
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-pink fw-bold mb-0">Drop-off Stations</h6>
                                <button type="button" class="btn btn-secondary-glass py-1 px-2 small" id="addDropRowBtn" style="font-size:0.75rem;"><i class="fa-solid fa-plus me-1"></i>Station</button>
                            </div>
                            <div id="dropRowsContainer">
                                <div class="row g-2 mb-2 alignment-row">
                                    <div class="col-8">
                                        <input type="text" name="drop_name[]" class="form-control form-control-swift py-1" placeholder="Station name" required>
                                    </div>
                                    <div class="col-4">
                                        <input type="text" name="drop_time[]" class="form-control form-control-swift py-1 time-picker-24h" placeholder="HH:MM" pattern="^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter time in 24-hour HH:MM format (e.g., 20:30)" required>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
                <div class="modal-footer border-secondary border-opacity-20 p-4">
                    <button type="button" class="btn btn-secondary-glass" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary-gradient">Create Route</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT ROUTE MODAL -->
<div class="modal fade" id="editRouteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#131a2e; border-radius: 20px;">
            <div class="modal-header border-secondary border-opacity-20 p-4">
                <h5 class="modal-title fw-bold text-white"><i class="fa-solid fa-route me-2 text-indigo"></i>Modify Route</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="route_id" id="edit_route_id">
                    
                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Leaving From (Source)</label>
                            <input type="text" name="source" id="edit_source" class="form-control form-control-swift" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Going To (Destination)</label>
                            <input type="text" name="destination" id="edit_destination" class="form-control form-control-swift" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Distance (km)</label>
                            <input type="number" name="distance_km" id="edit_distance" class="form-control form-control-swift" min="10" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Duration (hrs/mins)</label>
                            <input type="text" name="duration" id="edit_duration" class="form-control form-control-swift" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label text-secondary small fw-semibold">Route Status</label>
                        <select name="status" id="edit_status" class="form-select form-control-swift" required>
                            <option value="active">Active (Visible)</option>
                            <option value="inactive">Inactive (Hidden)</option>
                        </select>
                    </div>

                    <!-- Milestones Edit Row -->
                    <div class="row mt-4">
                        <!-- Pickups -->
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-indigo fw-bold mb-0">Boarding Stations</h6>
                                <button type="button" class="btn btn-secondary-glass py-1 px-2 small" id="editAddPickupRowBtn" style="font-size:0.75rem;"><i class="fa-solid fa-plus me-1"></i>Station</button>
                            </div>
                            <div id="editPickupRowsContainer"></div>
                        </div>

                        <!-- Drops -->
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-pink fw-bold mb-0">Drop-off Stations</h6>
                                <button type="button" class="btn btn-secondary-glass py-1 px-2 small" id="editAddDropRowBtn" style="font-size:0.75rem;"><i class="fa-solid fa-plus me-1"></i>Station</button>
                            </div>
                            <div id="editDropRowsContainer"></div>
                        </div>
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

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteRouteModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30" style="background:#131a2e; border-radius: 20px;">
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">
                <div class="modal-body p-4 text-center">
                    <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="route_id" id="delete_route_id">
                    
                    <i class="fa-solid fa-circle-exclamation text-danger mb-3" style="font-size: 3rem;"></i>
                    <h5 class="fw-bold mb-2">Delete Route?</h5>
                    <p class="text-secondary small">Are you sure you want to delete this route? Deleting this route will delete all mapped active trips.</p>
                </div>
                <div class="modal-footer border-0 p-3 d-flex justify-content-around">
                    <button type="button" class="btn btn-secondary-glass w-45 py-2" data-bs-dismiss="modal">No</button>
                    <button type="submit" class="btn btn-danger w-45 py-2">Yes, Delete</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // Helper to initialize flatpickr for 24-hour time picking
    function initTimePicker(element) {
        flatpickr(element, {
            enableTime: true,
            noCalendar: true,
            dateFormat: "H:i",
            time_24hr: true,
            disableMobile: true,
            allowInput: true
        });
    }

    // Initialize on page load for static inputs
    initTimePicker('.time-picker-24h');

    // Fill delete values
    $('.delete-route-btn').click(function() {
        $('#delete_route_id').val($(this).data('id'));
    });

    // Handle Edit trigger
    $('.edit-route-btn').click(function() {
        $('#edit_route_id').val($(this).data('id'));
        $('#edit_source').val($(this).data('source'));
        $('#edit_destination').val($(this).data('destination'));
        $('#edit_distance').val($(this).data('distance'));
        $('#edit_duration').val($(this).data('duration'));
        
        // Rebuild dynamic milestones
        var pickups = $(this).data('pickups');
        var drops = $(this).data('drops');
        
        var pickupsContainer = $('#editPickupRowsContainer');
        pickupsContainer.empty();
        pickups.forEach(function(p) {
            // format time in 24h before assigning
            var formattedVal = p.time ? p.time : '00:00';
            var $row = $('<div class="row g-2 mb-2 alignment-row">' +
                       '<div class="col-8"><input type="text" name="pickup_name[]" class="form-control form-control-swift py-1" value="' + p.name + '" required></div>' +
                       '<div class="col-3"><input type="text" name="pickup_time[]" class="form-control form-control-swift py-1 time-picker-24h" value="' + formattedVal + '" pattern="^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter time in 24-hour HH:MM format (e.g., 20:30)" required></div>' +
                       '<div class="col-1 d-flex align-items-center"><button type="button" class="btn btn-link text-danger p-0 delete-row-btn"><i class="fa-solid fa-trash-can"></i></button></div>' +
                       '</div>');
            pickupsContainer.append($row);
            initTimePicker($row.find('.time-picker-24h'));
        });

        var dropsContainer = $('#editDropRowsContainer');
        dropsContainer.empty();
        drops.forEach(function(d) {
            // format time in 24h before assigning
            var formattedVal = d.time ? d.time : '00:00';
            var $row = $('<div class="row g-2 mb-2 alignment-row">' +
                       '<div class="col-8"><input type="text" name="drop_name[]" class="form-control form-control-swift py-1" value="' + d.name + '" required></div>' +
                       '<div class="col-3"><input type="text" name="drop_time[]" class="form-control form-control-swift py-1 time-picker-24h" value="' + formattedVal + '" pattern="^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter time in 24-hour HH:MM format (e.g., 20:30)" required></div>' +
                       '<div class="col-1 d-flex align-items-center"><button type="button" class="btn btn-link text-danger p-0 delete-row-btn"><i class="fa-solid fa-trash-can"></i></button></div>' +
                       '</div>');
            dropsContainer.append($row);
            initTimePicker($row.find('.time-picker-24h'));
        });
    });

    // Dynamic row addition for Pickups (ADD)
    $('#addPickupRowBtn').click(function() {
        var $row = $('<div class="row g-2 mb-2 alignment-row">' +
                  '<div class="col-8"><input type="text" name="pickup_name[]" class="form-control form-control-swift py-1" placeholder="Station name" required></div>' +
                  '<div class="col-3"><input type="text" name="pickup_time[]" class="form-control form-control-swift py-1 time-picker-24h" placeholder="HH:MM" pattern="^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter time in 24-hour HH:MM format (e.g., 20:30)" required></div>' +
                  '<div class="col-1 d-flex align-items-center"><button type="button" class="btn btn-link text-danger p-0 delete-row-btn"><i class="fa-solid fa-trash-can"></i></button></div>' +
                  '</div>');
        $('#pickupRowsContainer').append($row);
        initTimePicker($row.find('.time-picker-24h'));
    });

    // Dynamic row addition for Drops (ADD)
    $('#addDropRowBtn').click(function() {
        var $row = $('<div class="row g-2 mb-2 alignment-row">' +
                  '<div class="col-8"><input type="text" name="drop_name[]" class="form-control form-control-swift py-1" placeholder="Station name" required></div>' +
                  '<div class="col-3"><input type="text" name="drop_time[]" class="form-control form-control-swift py-1 time-picker-24h" placeholder="HH:MM" pattern="^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter time in 24-hour HH:MM format (e.g., 20:30)" required></div>' +
                  '<div class="col-1 d-flex align-items-center"><button type="button" class="btn btn-link text-danger p-0 delete-row-btn"><i class="fa-solid fa-trash-can"></i></button></div>' +
                  '</div>');
        $('#dropRowsContainer').append($row);
        initTimePicker($row.find('.time-picker-24h'));
    });

    // Dynamic row addition for Pickups (EDIT)
    $('#editAddPickupRowBtn').click(function() {
        var $row = $('<div class="row g-2 mb-2 alignment-row">' +
                  '<div class="col-8"><input type="text" name="pickup_name[]" class="form-control form-control-swift py-1" placeholder="Station name" required></div>' +
                  '<div class="col-3"><input type="text" name="pickup_time[]" class="form-control form-control-swift py-1 time-picker-24h" placeholder="HH:MM" pattern="^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter time in 24-hour HH:MM format (e.g., 20:30)" required></div>' +
                  '<div class="col-1 d-flex align-items-center"><button type="button" class="btn btn-link text-danger p-0 delete-row-btn"><i class="fa-solid fa-trash-can"></i></button></div>' +
                  '</div>');
        $('#editPickupRowsContainer').append($row);
        initTimePicker($row.find('.time-picker-24h'));
    });

    // Dynamic row addition for Drops (EDIT)
    $('#editAddDropRowBtn').click(function() {
        var $row = $('<div class="row g-2 mb-2 alignment-row">' +
                  '<div class="col-8"><input type="text" name="drop_name[]" class="form-control form-control-swift py-1" placeholder="Station name" required></div>' +
                  '<div class="col-3"><input type="text" name="drop_time[]" class="form-control form-control-swift py-1 time-picker-24h" placeholder="HH:MM" pattern="^(0[0-9]|1[0-9]|2[0-3]):[0-5][0-9]$" title="Please enter time in 24-hour HH:MM format (e.g., 20:30)" required></div>' +
                  '<div class="col-1 d-flex align-items-center"><button type="button" class="btn btn-link text-danger p-0 delete-row-btn"><i class="fa-solid fa-trash-can"></i></button></div>' +
                  '</div>');
        $('#editDropRowsContainer').append($row);
        initTimePicker($row.find('.time-picker-24h'));
    });

    // Handle dynamically added row removal
    $(document).on('click', '.delete-row-btn', function() {
        $(this).closest('.alignment-row').remove();
    });
});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
