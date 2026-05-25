<?php
/**
 * Bus Search Results
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$source = $_GET['source'] ?? '';
$destination = $_GET['destination'] ?? '';
$date = $_GET['date'] ?? '';

if (empty($source) || empty($destination) || empty($date)) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$page_title = "Search Results: $source to $destination";

// Fetch Matching Trips
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
            b.total_seats,
            r.distance_km,
            r.pickup_points,
            r.drop_points,
            (SELECT COUNT(*) FROM trip_seats ts WHERE ts.trip_id = t.id AND ts.status = 'available') AS available_seats
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        JOIN routes r ON t.route_id = r.id
        WHERE r.source = :source 
          AND r.destination = :destination 
          AND DATE(t.departure_time) = :date
        ORDER BY t.departure_time ASC
    ");
    $stmt->execute([
        ':source' => $source,
        ':destination' => $destination,
        ':date' => $date
    ]);
    $trips = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database search failed: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Search Bar Resubmission Ticker (Compact & Sleek) -->
<div class="glass-card p-3 mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3" style="border-radius: 12px;">
    <div class="d-flex align-items-center gap-3">
        <span class="badge bg-indigo py-2 px-3 text-uppercase" style="background:#6366f1;"><?= htmlspecialchars($source) ?> <i class="fa-solid fa-arrow-right mx-1"></i> <?= htmlspecialchars($destination) ?></span>
        <span class="text-secondary small"><i class="fa-regular fa-calendar me-2"></i><?= date('D, d M Y', strtotime($date)) ?></span>
    </div>
    <a href="<?= BASE_URL ?>/index.php" class="btn btn-secondary-glass py-2 px-3 small" style="font-size: 0.85rem;"><i class="fa-solid fa-rotate-left me-2"></i>Modify Search</a>
</div>

<h4 class="fw-bold mb-4 text-white">Available Services (<?= count($trips) ?> found)</h4>

<?php if (count($trips) === 0): ?>
    <div class="glass-card p-5 text-center my-5">
        <i class="fa-solid fa-circle-info text-secondary mb-3" style="font-size: 4rem;"></i>
        <h3 class="text-white fw-bold">No Buses Found</h3>
        <p class="text-secondary">We couldn't find any bus scheduled from <?= htmlspecialchars($source) ?> to <?= htmlspecialchars($destination) ?> on <?= date('d M Y', strtotime($date)) ?>.</p>
        <div class="d-flex justify-content-center gap-3 mt-4">
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary-gradient"><i class="fa-solid fa-arrow-left me-2"></i>Back to Home</a>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <div class="col-lg-12">
            <?php foreach ($trips as $trip): 
                $dep_time = new DateTime($trip['departure_time']);
                $arr_time = new DateTime($trip['arrival_time']);
                $duration = $dep_time->diff($arr_time);
                $duration_str = $duration->format('%h hrs %i mins');
                
                $pickups = json_decode($trip['pickup_points'], true) ?? [];
                $drops = json_decode($trip['drop_points'], true) ?? [];
            ?>
                <!-- Individual Bus Card -->
                <div class="glass-card p-4 mb-4" style="border-radius: 16px;">
                    <div class="row align-items-center g-3">
                        
                        <!-- Bus Name & Type Info -->
                        <div class="col-md-3">
                            <h5 class="fw-bold text-white mb-1"><?= htmlspecialchars($trip['bus_name']) ?></h5>
                            <span class="badge bg-secondary text-uppercase small py-1 px-2" style="font-size: 0.75rem;"><?= htmlspecialchars($trip['bus_type']) ?></span>
                            <div class="text-secondary small mt-2"><i class="fa-solid fa-hashtag me-2"></i><?= htmlspecialchars($trip['bus_number']) ?></div>
                        </div>

                        <!-- Timings & Route Milestones -->
                        <div class="col-md-4">
                            <div class="d-flex align-items-center justify-content-between text-center">
                                <div>
                                    <div class="fw-bold text-white fs-5"><?= $dep_time->format('H:i') ?></div>
                                    <div class="text-secondary small"><?= htmlspecialchars($source) ?></div>
                                </div>
                                <div class="w-50 px-3 position-relative">
                                    <div class="text-secondary small mb-1"><?= $duration_str ?></div>
                                    <div style="height: 2px; background: rgba(255,255,255,0.15); position: relative;">
                                        <div style="width: 8px; height: 8px; border-radius:50%; background:#818cf8; position:absolute; top:-3px; left:0;"></div>
                                        <div style="width: 8px; height: 8px; border-radius:50%; background:#db2777; position:absolute; top:-3px; right:0;"></div>
                                    </div>
                                    <div class="text-secondary small mt-1"><?= htmlspecialchars($trip['distance_km']) ?> km</div>
                                </div>
                                <div>
                                    <div class="fw-bold text-white fs-5"><?= $arr_time->format('H:i') ?></div>
                                    <div class="text-secondary small"><?= htmlspecialchars($destination) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Milestones / Pickup Drop toggles -->
                        <div class="col-md-2 text-center text-md-start">
                            <div class="dropdown">
                                <button class="btn btn-secondary-glass py-1 px-2 small text-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown" style="font-size: 0.8rem;">
                                    Pickup / Drop points
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark glass-card p-3 border-0 mt-2" style="min-width: 250px;">
                                    <h6 class="text-indigo small fw-bold mb-2">Pickups (<?= htmlspecialchars($source) ?>)</h6>
                                    <?php foreach ($pickups as $p): ?>
                                        <li class="small text-secondary mb-1 d-flex justify-content-between">
                                            <span><?= htmlspecialchars($p['name']) ?></span>
                                            <span class="text-white"><?= htmlspecialchars($p['time']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                    <li><hr class="dropdown-divider border-secondary my-2"></li>
                                    <h6 class="text-pink small fw-bold mb-2">Drops (<?= htmlspecialchars($destination) ?>)</h6>
                                    <?php foreach ($drops as $d): ?>
                                        <li class="small text-secondary mb-1 d-flex justify-content-between">
                                            <span><?= htmlspecialchars($d['name']) ?></span>
                                            <span class="text-white"><?= htmlspecialchars($d['time']) ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Price & Seats Display -->
                        <div class="col-md-3 text-center text-md-end">
                            <div class="mb-2">
                                <span class="text-secondary small">Starting at </span>
                                <span class="fs-4 fw-bold text-indigo" style="color:#818cf8;"><?= CURRENCY ?><?= number_format($trip['base_fare'], 2) ?></span>
                            </div>
                            
                            <!-- Seat badge indicator -->
                            <div class="mb-3">
                                <?php if ($trip['available_seats'] > 10): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success py-2 px-3 rounded-pill border border-success border-opacity-25"><?= htmlspecialchars($trip['available_seats']) ?> seats left</span>
                                <?php elseif ($trip['available_seats'] > 0): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning py-2 px-3 rounded-pill border border-warning border-opacity-25"><?= htmlspecialchars($trip['available_seats']) ?> seats left</span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger py-2 px-3 rounded-pill border border-danger border-opacity-25">SOLD OUT</span>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if ($trip['available_seats'] > 0): ?>
                                    <a href="<?= BASE_URL ?>/book.php?trip_id=<?= $trip['trip_id'] ?>" class="btn btn-primary-gradient w-100 text-uppercase fw-bold py-2" style="font-size: 0.9rem;">Select Seats</a>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100 py-2 text-uppercase fw-bold disabled" style="font-size: 0.9rem;">House Full</button>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
