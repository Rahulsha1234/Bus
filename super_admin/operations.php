<?php
/**
 * Super Admin Operations Management Panel (Buses, Routes, Trips)
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
require_role('super_admin');

$error = '';
$success = '';

// Handle Delete Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        $_SESSION['error'] = "Security token validation failed.";
    } else {
        $action = $_POST['action'] ?? '';
        $id = intval($_POST['id'] ?? 0);

        if ($id > 0) {
            try {
                $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");

                if ($action === 'delete_bus') {
                    // Get bus details for logging
                    $stmt = $pdo->prepare("SELECT bus_name, bus_number FROM buses WHERE id = ?");
                    $stmt->execute([$id]);
                    $bus = $stmt->fetch();
                    $bus_info = $bus ? "{$bus['bus_name']} ({$bus['bus_number']})" : "ID $id";

                    $del = $pdo->prepare("DELETE FROM buses WHERE id = ?");
                    $del->execute([$id]);
                    
                    $_SESSION['success'] = "Bus $bus_info deleted successfully!";
                    log_activity($pdo, $_SESSION['user_id'], 'SUPER_ADMIN_BUS_DELETE', "Hard deleted bus: $bus_info");
                } 
                elseif ($action === 'delete_route') {
                    // Get route details for logging
                    $stmt = $pdo->prepare("SELECT source, destination FROM routes WHERE id = ?");
                    $stmt->execute([$id]);
                    $route = $stmt->fetch();
                    $route_info = $route ? "{$route['source']} -> {$route['destination']}" : "ID $id";

                    $del = $pdo->prepare("DELETE FROM routes WHERE id = ?");
                    $del->execute([$id]);
                    
                    $_SESSION['success'] = "Route $route_info deleted successfully!";
                    log_activity($pdo, $_SESSION['user_id'], 'SUPER_ADMIN_ROUTE_DELETE', "Hard deleted route: $route_info");
                } 
                elseif ($action === 'delete_trip') {
                    $del = $pdo->prepare("DELETE FROM trips WHERE id = ?");
                    $del->execute([$id]);
                    
                    $_SESSION['success'] = "Trip ID $id deleted successfully!";
                    log_activity($pdo, $_SESSION['user_id'], 'SUPER_ADMIN_TRIP_DELETE', "Hard deleted trip ID: $id");
                }

                $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            } catch (PDOException $e) {
                $_SESSION['error'] = "Database error during deletion: " . $e->getMessage();
            }
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . (isset($_GET['tab']) ? '?tab=' . urlencode($_GET['tab']) : ''));
    exit();
}

require_once __DIR__ . '/header.php';

$error = $_SESSION['error'] ?? '';
$success = $_SESSION['success'] ?? '';
unset($_SESSION['error'], $_SESSION['success']);

$active_tab = $_GET['tab'] ?? 'buses';

// Fetch Data based on active tab
$buses = [];
$routes = [];
$trips = [];

try {
    if ($active_tab === 'buses') {
        $stmt = $pdo->query("
            SELECT b.*, u.username AS operator_name 
            FROM buses b 
            LEFT JOIN users u ON b.admin_id = u.id 
            ORDER BY b.id DESC
        ");
        $buses = $stmt->fetchAll();
    } elseif ($active_tab === 'routes') {
        $stmt = $pdo->query("
            SELECT r.*, u.username AS operator_name 
            FROM routes r 
            LEFT JOIN users u ON r.admin_id = u.id 
            ORDER BY r.id DESC
        ");
        $routes = $stmt->fetchAll();
    } elseif ($active_tab === 'trips') {
        $stmt = $pdo->query("
            SELECT t.*, b.bus_name, b.bus_number, r.source, r.destination, u.username AS operator_name 
            FROM trips t 
            LEFT JOIN buses b ON t.bus_id = b.id 
            LEFT JOIN routes r ON t.route_id = r.id 
            LEFT JOIN users u ON t.admin_id = u.id 
            ORDER BY t.id DESC
        ");
        $trips = $stmt->fetchAll();
    }
} catch (PDOException $e) {
    $error = "Error fetching data: " . $e->getMessage();
}

$page_title = "Manage Operations";
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

<!-- Tabs navigation -->
<div class="d-flex gap-2 mb-4">
    <a href="?tab=buses" class="btn <?= $active_tab === 'buses' ? 'btn-primary-gradient' : 'btn-secondary-glass' ?> px-4 py-2">
        <i class="fa-solid fa-bus me-2"></i>Buses
    </a>
    <a href="?tab=routes" class="btn <?= $active_tab === 'routes' ? 'btn-primary-gradient' : 'btn-secondary-glass' ?> px-4 py-2">
        <i class="fa-solid fa-route me-2"></i>Routes
    </a>
    <a href="?tab=trips" class="btn <?= $active_tab === 'trips' ? 'btn-primary-gradient' : 'btn-secondary-glass' ?> px-4 py-2">
        <i class="fa-solid fa-calendar-days me-2"></i>Trips
    </a>
</div>

<div class="glass-card p-4">
    <?php if ($active_tab === 'buses'): ?>
        <h5 class="text-white fw-bold mb-4"><i class="fa-solid fa-bus text-indigo me-2"></i>All Registered Vehicles (Buses)</h5>
        <?php if (count($buses) === 0): ?>
            <div class="text-center py-5 text-secondary small">No buses registered yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-swift table-dark table-hover table-borderless align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bus Name</th>
                            <th>Plate Number</th>
                            <th>Type</th>
                            <th>Seats</th>
                            <th>Status</th>
                            <th>Operator</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($buses as $bus): ?>
                            <tr class="border-bottom border-secondary border-opacity-10">
                                <td class="font-monospace text-secondary"><?= $bus['id'] ?></td>
                                <td>
                                    <span class="fw-bold text-white small"><?= htmlspecialchars($bus['bus_name']) ?></span>
                                </td>
                                <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($bus['bus_number']) ?></span></td>
                                <td><span class="text-secondary small"><?= htmlspecialchars($bus['bus_type']) ?></span></td>
                                <td><span class="font-monospace text-secondary"><?= $bus['total_seats'] ?></span></td>
                                <td>
                                    <span class="badge <?= $bus['status'] === 'active' ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 <?= $bus['status'] === 'active' ? 'text-success' : 'text-danger' ?>">
                                        <?= strtoupper($bus['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-white"><i class="fa-solid fa-user-tie text-indigo me-1"></i><?= htmlspecialchars($bus['operator_name'] ?? 'System / Deleted') ?></span>
                                </td>
                                <td class="text-end">
                                    <form action="?tab=buses" method="POST" onsubmit="return confirm('WARNING: Are you sure you want to PERMANENTLY delete this bus? This will cascade delete any associated trips/seats.');" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_bus">
                                        <input type="hidden" name="id" value="<?= $bus['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm px-3 rounded-pill">
                                            <i class="fa-solid fa-trash-can me-1"></i>Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'routes'): ?>
        <h5 class="text-white fw-bold mb-4"><i class="fa-solid fa-route text-indigo me-2"></i>All Scheduled Paths (Routes)</h5>
        <?php if (count($routes) === 0): ?>
            <div class="text-center py-5 text-secondary small">No routes registered yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-swift table-dark table-hover table-borderless align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Source</th>
                            <th>Destination</th>
                            <th>Distance</th>
                            <th>Status</th>
                            <th>Operator</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($routes as $route): ?>
                            <tr class="border-bottom border-secondary border-opacity-10">
                                <td class="font-monospace text-secondary"><?= $route['id'] ?></td>
                                <td><span class="fw-bold text-white small"><?= htmlspecialchars($route['source']) ?></span></td>
                                <td><span class="fw-bold text-white small"><?= htmlspecialchars($route['destination']) ?></span></td>
                                <td><span class="font-monospace text-secondary"><?= htmlspecialchars($route['distance_km']) ?> km</span></td>
                                <td>
                                    <span class="badge <?= $route['status'] === 'active' ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 <?= $route['status'] === 'active' ? 'text-success' : 'text-danger' ?>">
                                        <?= strtoupper($route['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-white"><i class="fa-solid fa-user-tie text-indigo me-1"></i><?= htmlspecialchars($route['operator_name'] ?? 'System / Deleted') ?></span>
                                </td>
                                <td class="text-end">
                                    <form action="?tab=routes" method="POST" onsubmit="return confirm('WARNING: Are you sure you want to PERMANENTLY delete this route? This will delete all mapped trips.');" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_route">
                                        <input type="hidden" name="id" value="<?= $route['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm px-3 rounded-pill">
                                            <i class="fa-solid fa-trash-can me-1"></i>Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>

    <?php elseif ($active_tab === 'trips'): ?>
        <h5 class="text-white fw-bold mb-4"><i class="fa-solid fa-calendar-days text-indigo me-2"></i>All Scheduled Trips</h5>
        <?php if (count($trips) === 0): ?>
            <div class="text-center py-5 text-secondary small">No trips scheduled yet.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-swift table-dark table-hover table-borderless align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Bus Name / Number</th>
                            <th>Route</th>
                            <th>Departure</th>
                            <th>Arrival</th>
                            <th>Fare</th>
                            <th>Status</th>
                            <th>Operator</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($trips as $trip): ?>
                            <tr class="border-bottom border-secondary border-opacity-10">
                                <td class="font-monospace text-secondary"><?= $trip['id'] ?></td>
                                <td>
                                    <span class="fw-bold text-white small"><?= htmlspecialchars($trip['bus_name'] ?? 'Deleted Bus') ?></span>
                                    <span class="d-block text-secondary small font-monospace"><?= htmlspecialchars($trip['bus_number'] ?? '') ?></span>
                                </td>
                                <td>
                                    <span class="text-white small"><?= htmlspecialchars($trip['source'] ?? 'Deleted') ?> &rarr; <?= htmlspecialchars($trip['destination'] ?? 'Deleted') ?></span>
                                </td>
                                <td><span class="text-secondary small"><?= date('d M H:i', strtotime($trip['departure_time'])) ?></span></td>
                                <td><span class="text-secondary small"><?= date('d M H:i', strtotime($trip['arrival_time'])) ?></span></td>
                                <td><span class="font-monospace text-white"><?= CURRENCY ?><?= htmlspecialchars($trip['base_fare']) ?></span></td>
                                <td>
                                    <span class="badge <?= $trip['status'] === 'active' ? 'bg-success' : 'bg-danger' ?> bg-opacity-10 <?= $trip['status'] === 'active' ? 'text-success' : 'text-danger' ?>">
                                        <?= strtoupper($trip['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="small text-white"><i class="fa-solid fa-user-tie text-indigo me-1"></i><?= htmlspecialchars($trip['operator_name'] ?? 'System / Deleted') ?></span>
                                </td>
                                <td class="text-end">
                                    <form action="?tab=trips" method="POST" onsubmit="return confirm('WARNING: Are you sure you want to PERMANENTLY delete this trip? This will delete all seat layouts and bookings for this trip.');" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                                        <input type="hidden" name="action" value="delete_trip">
                                        <input type="hidden" name="id" value="<?= $trip['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-sm px-3 rounded-pill">
                                            <i class="fa-solid fa-trash-can me-1"></i>Delete
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/footer.php';
?>
