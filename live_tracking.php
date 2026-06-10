<?php
/**
 * Customer / Agent Live Bus Tracking Screen
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$booking_id = intval($_GET['booking_id'] ?? 0);

try {
    // Fetch booking details & matching tracking
    $stmt = $pdo->prepare("
        SELECT b.*, t.bus_id, t.departure_time, t.arrival_time, bus.bus_name, bus.bus_number,
               tr.latitude, tr.longitude, tr.current_location_name, tr.eta, tr.boarding_status, tr.trip_status, tr.updated_at
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bus ON t.bus_id = bus.id
        LEFT JOIN bus_tracking tr ON bus.id = tr.bus_id
        WHERE b.id = ? AND b.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([$booking_id]);
    $booking = $stmt->fetch();

    if (!$booking) {
        die("Invalid booking reference or tracking unauthorized.");
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

$page_title = "Live Bus Tracking";
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">
    <!-- Header -->
    <div class="glass-card p-4 mb-4 d-flex justify-content-between align-items-center" style="border-radius: 20px;">
        <div>
            <h3 class="fw-bold text-white mb-1"><i class="fa-solid fa-map-location-dot me-2 text-info"></i>Live Bus Tracking</h3>
            <p class="text-secondary small mb-0">Real-time status tracking for <strong><?= htmlspecialchars($booking['bus_name']) ?></strong> (<?= htmlspecialchars($booking['bus_number']) ?>)</p>
        </div>
        <a href="bookings.php" class="btn btn-secondary-glass rounded-3"><i class="fa-solid fa-arrow-left me-2"></i>Back to Bookings</a>
    </div>

    <?php if (empty($booking['latitude'])): ?>
        <div class="glass-card p-5 text-center my-5" style="border-radius: 20px;">
            <i class="fa-solid fa-location-crosshairs text-secondary mb-3 fa-beat" style="font-size: 4rem;"></i>
            <h4 class="text-white fw-bold">Live Tracking Unavailable</h4>
            <p class="text-secondary">The operator has not initialized the tracking device or updated simulation coordinates for this bus yet.</p>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <!-- Details Panel -->
            <div class="col-lg-4">
                <div class="glass-card p-4 h-100" style="border-radius: 20px;">
                    <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-circle-info me-2 text-indigo"></i>Tracking Status</h5>
                    
                    <div class="mb-3">
                        <span class="text-secondary small d-block">Current Location Landmark</span>
                        <h6 class="fw-bold text-white fs-6"><?= htmlspecialchars($booking['current_location_name'] ?: 'In Transit') ?></h6>
                    </div>

                    <div class="mb-3">
                        <span class="text-secondary small d-block">Estimated Arrival (ETA)</span>
                        <h6 class="fw-bold text-success fs-5"><?= htmlspecialchars($booking['eta'] ?: 'Calculating...') ?></h6>
                    </div>

                    <div class="mb-3">
                        <span class="text-secondary small d-block">Boarding Status</span>
                        <span class="badge bg-secondary text-uppercase"><?= htmlspecialchars($booking['boarding_status'] ?: 'N/A') ?></span>
                    </div>

                    <div class="mb-3">
                        <span class="text-secondary small d-block">Trip Status</span>
                        <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-20 text-uppercase"><?= htmlspecialchars($booking['trip_status'] ?: 'On Time') ?></span>
                    </div>

                    <div class="border-top border-secondary border-opacity-20 pt-3 mt-4 small text-secondary">
                        <i class="fa-regular fa-clock me-1"></i>Last updated: <?= date('d M, H:i:s', strtotime($booking['updated_at'])) ?>
                    </div>
                </div>
            </div>

            <!-- Map View -->
            <div class="col-lg-8">
                <div class="glass-card p-2 h-100" style="border-radius: 20px; overflow:hidden; min-height: 450px;">
                    <div id="live_map" style="height: 100%; min-height: 450px; border-radius: 15px; background:#222;"></div>
                </div>
            </div>
        </div>

        <!-- Leaflet Resources -->
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        
        <script>
        $(document).ready(function() {
            var lat = <?= floatval($booking['latitude']) ?>;
            var lng = <?= floatval($booking['longitude']) ?>;
            var name = '<?= htmlspecialchars($booking['current_location_name'] ?: 'Bus Location') ?>';

            var map = L.map('live_map').setView([lat, lng], 14);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            var marker = L.marker([lat, lng]).addTo(map)
                .bindPopup('<strong>' + name + '</strong><br>ETA: <?= htmlspecialchars($booking['eta']) ?>')
                .openPopup();

            // Auto refresh simulation logic every 10 seconds for user experience
            setInterval(function() {
                $.getJSON('<?= BASE_URL ?>/ajax/get_bus_details_ajax.php', { bus_id: <?= $booking['bus_id'] ?> }, function(res) {
                    if (res.success && res.tracking) {
                        var newLat = parseFloat(res.tracking.latitude);
                        var newLng = parseFloat(res.tracking.longitude);
                        marker.setLatLng([newLat, newLng]);
                        map.panTo([newLat, newLng]);
                    }
                });
            }, 10000);
        });
        </script>
    <?php endif; ?>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
