<?php
/**
 * Route Scheduler CRUD
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

        // ADD ROUTE
        if ($action === 'add') {
            $source = trim($_POST['source'] ?? '');
            $destination = trim($_POST['destination'] ?? '');
            $distance = intval($_POST['distance_km'] ?? 0);
            
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
                    INSERT INTO routes (agent_id, source, destination, distance_km, pickup_points, drop_points) 
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $agent_id, 
                    $source, 
                    $destination, 
                    $distance, 
                    json_encode($pickups), 
                    json_encode($drops)
                ]);
                $success = "Route registered successfully!";
                log_activity($pdo, $agent_id, 'ROUTE_ADD', "Added route $source to $destination ($distance km)");
            }
        }

        // DELETE ROUTE
        elseif ($action === 'delete') {
            $route_id = intval($_POST['route_id'] ?? 0);
            
            // Verify ownership and delete
            $stmt = $pdo->prepare("DELETE FROM routes WHERE id = ? AND agent_id = ?");
            $stmt->execute([$route_id, $agent_id]);
            
            if ($stmt->rowCount() > 0) {
                $success = "Route removed successfully!";
                log_activity($pdo, $agent_id, 'ROUTE_DELETE', "Deleted route ID: $route_id");
            } else {
                $error = "Failed to delete route. Invalid ID or authorization conflict.";
            }
        }
    }
}

// Fetch Agent's Routes
try {
    $stmt = $pdo->prepare("SELECT * FROM routes WHERE agent_id = ? ORDER BY id DESC");
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
                        <th>Distance (km)</th>
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
                            <td><span class="font-monospace text-secondary small"><?= htmlspecialchars($route['distance_km']) ?> km</span></td>
                            <td>
                                <ul class="list-unstyled mb-0 small text-secondary">
                                    <?php foreach ($pickups as $p): ?>
                                        <li><i class="fa-solid fa-circle text-indigo me-1" style="font-size: 0.4rem; color: #818cf8;"></i><?= htmlspecialchars($p['name']) ?> (<?= htmlspecialchars($p['time']) ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td>
                                <ul class="list-unstyled mb-0 small text-secondary">
                                    <?php foreach ($drops as $d): ?>
                                        <li><i class="fa-solid fa-circle text-pink me-1" style="font-size: 0.4rem; color: #ec4899;"></i><?= htmlspecialchars($d['name']) ?> (<?= htmlspecialchars($d['time']) ?>)</li>
                                    <?php endforeach; ?>
                                </ul>
                            </td>
                            <td class="text-end">
                                <button class="btn btn-secondary-glass py-1 px-2 text-danger small delete-route-btn" data-id="<?= $route['id'] ?>" data-bs-toggle="modal" data-bs-target="#deleteRouteModal"><i class="fa-solid fa-trash-can"></i></button>
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
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Leaving From (Source)</label>
                            <input type="text" name="source" class="form-control form-control-swift" placeholder="e.g. Bangalore" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Going To (Destination)</label>
                            <input type="text" name="destination" class="form-control form-control-swift" placeholder="e.g. Mumbai" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Distance Mileage (km)</label>
                            <input type="number" name="distance_km" class="form-control form-control-swift" placeholder="e.g. 1000" min="10" required>
                        </div>
                    </div>

                    <!-- Milestones Configuration Row -->
                    <div class="row mt-4">
                        <!-- Pickups -->
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-indigo fw-bold mb-0">Boarding Milestones</h6>
                                <button type="button" class="btn btn-secondary-glass py-1 px-2 small" id="addPickupRowBtn" style="font-size:0.75rem;"><i class="fa-solid fa-plus me-1"></i>Station</button>
                            </div>
                            <div id="pickupRowsContainer">
                                <div class="row g-2 mb-2 alignment-row">
                                    <div class="col-8">
                                        <input type="text" name="pickup_name[]" class="form-control form-control-swift py-1" placeholder="Station name" required>
                                    </div>
                                    <div class="col-4">
                                        <input type="time" name="pickup_time[]" class="form-control form-control-swift py-1" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Drops -->
                        <div class="col-md-6 mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h6 class="text-pink fw-bold mb-0">Drop-off Milestones</h6>
                                <button type="button" class="btn btn-secondary-glass py-1 px-2 small" id="addDropRowBtn" style="font-size:0.75rem;"><i class="fa-solid fa-plus me-1"></i>Station</button>
                            </div>
                            <div id="dropRowsContainer">
                                <div class="row g-2 mb-2 alignment-row">
                                    <div class="col-8">
                                        <input type="text" name="drop_name[]" class="form-control form-control-swift py-1" placeholder="Station name" required>
                                    </div>
                                    <div class="col-4">
                                        <input type="time" name="drop_time[]" class="form-control form-control-swift py-1" required>
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
    // Fill delete values
    $('.delete-route-btn').click(function() {
        $('#delete_route_id').val($(this).data('id'));
    });

    // Dynamic row addition for Pickups
    $('#addPickupRowBtn').click(function() {
        var row = '<div class="row g-2 mb-2 alignment-row">' +
                  '<div class="col-8"><input type="text" name="pickup_name[]" class="form-control form-control-swift py-1" placeholder="Station name" required></div>' +
                  '<div class="col-3"><input type="time" name="pickup_time[]" class="form-control form-control-swift py-1" required></div>' +
                  '<div class="col-1 d-flex align-items-center"><button type="button" class="btn btn-link text-danger p-0 delete-row-btn"><i class="fa-solid fa-trash-can"></i></button></div>' +
                  '</div>';
        $('#pickupRowsContainer').append(row);
    });

    // Dynamic row addition for Drops
    $('#addDropRowBtn').click(function() {
        var row = '<div class="row g-2 mb-2 alignment-row">' +
                  '<div class="col-8"><input type="text" name="drop_name[]" class="form-control form-control-swift py-1" placeholder="Station name" required></div>' +
                  '<div class="col-3"><input type="time" name="drop_time[]" class="form-control form-control-swift py-1" required></div>' +
                  '<div class="col-1 d-flex align-items-center"><button type="button" class="btn btn-link text-danger p-0 delete-row-btn"><i class="fa-solid fa-trash-can"></i></button></div>' +
                  '</div>';
        $('#dropRowsContainer').append(row);
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
