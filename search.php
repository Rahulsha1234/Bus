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

$page_title = __('search_results', 'Search Results') . ": $source " . __('to', 'to') . " $destination";

// Fetch Matching Trips
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id AS trip_id,
            t.departure_time,
            t.arrival_time,
            COALESCE(
                (SELECT MIN(custom_price) FROM seat_price_overrides WHERE trip_id = t.id AND custom_price > 0),
                (SELECT MIN(current_price) FROM seat_pricing WHERE trip_id = t.id AND current_price > 0),
                (SELECT MIN(base_price) FROM bus_seats bs WHERE bs.bus_id = b.id AND bs.base_price > 0 AND bs.is_active = 1),
                t.base_fare
            ) AS base_fare,
            b.id AS bus_id,
            b.bus_name,
            b.bus_number,
            b.bus_type,
            b.total_seats,
            r.distance_km,
            r.pickup_points,
            r.drop_points,
            (SELECT COUNT(*) FROM trip_seats ts WHERE ts.trip_id = t.id AND ts.status = 'available') AS available_seats,
            COALESCE((SELECT is_verified FROM bus_verifications WHERE bus_id = b.id), 0) AS is_verified,
            COALESCE((SELECT AVG(rating) FROM bus_reviews WHERE bus_id = b.id AND status = 'approved'), 0.00) AS avg_rating,
            COALESCE((SELECT COUNT(*) FROM bus_reviews WHERE bus_id = b.id AND status = 'approved'), 0) AS total_reviews,
            (SELECT file_path FROM bus_media WHERE bus_id = b.id AND media_type = 'image' ORDER BY sort_order ASC LIMIT 1) AS thumbnail,
            (SELECT COUNT(*) FROM bus_tracking WHERE bus_id = b.id) AS has_tracking
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        JOIN routes r ON t.route_id = r.id
        WHERE r.source = :source 
          AND r.destination = :destination 
          AND DATE(t.departure_time) = :date
          AND t.status = 'ACTIVE'
          AND t.departure_time >= NOW()
        ORDER BY t.departure_time ASC
    ");
    $stmt->execute([
        ':source' => $source,
        ':destination' => $destination,
        ':date' => $date
    ]);
    $trips = $stmt->fetchAll();

    // Fetch unique sources from active routes only for the modify search panel
    $sources_stmt = $pdo->query("
        SELECT DISTINCT r.source 
        FROM routes r 
        JOIN trips t ON r.id = t.route_id 
        WHERE r.status = 'active' AND t.status = 'ACTIVE' AND t.departure_time >= NOW() 
        ORDER BY r.source ASC
    ");
    $sources = $sources_stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    die("Database search failed: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Search Bar Resubmission Ticker (Compact & Sleek with Inline Edit) -->
<div class="glass-card p-3 mb-4" style="border-radius: 12px;">
    <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex align-items-center gap-3">
            <span class="badge bg-indigo py-2 px-3 text-uppercase" style="background:var(--accent-primary) !important;"><?= htmlspecialchars($source) ?> <i class="fa-solid fa-arrow-right mx-1"></i> <?= htmlspecialchars($destination) ?></span>
            <span class="text-secondary small"><i class="fa-regular fa-calendar me-2"></i><?= date('D, d M Y', strtotime($date)) ?></span>
        </div>
        <button class="btn btn-secondary-glass py-2 px-3 small" style="font-size: 0.85rem;" type="button" data-bs-toggle="collapse" data-bs-target="#modifySearchCollapse" aria-expanded="false" aria-controls="modifySearchCollapse">
            <i class="fa-solid fa-pen-to-square me-2"></i><?= __('modify_search', 'Modify Search') ?>
        </button>
    </div>

    <!-- Collapsible Inline Search Form (Shown by default) -->
    <div class="collapse show mt-4 pt-3 border-top border-secondary border-opacity-20" id="modifySearchCollapse">
        <form action="<?= BASE_URL ?>/search.php" method="GET" class="row g-3 align-items-end">
            <!-- Source dropdown -->
            <div class="col-md-3">
                <label for="source_search" class="form-label text-secondary small fw-semibold"><?= __('leaving_from', 'Leaving From') ?></label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-location-dot"></i></span>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="source_search" class="form-control form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" placeholder="<?= __('select_origin', 'Select Origin...') ?>" value="<?= htmlspecialchars($source) ?>" autocomplete="off" required>
                        <input type="hidden" name="source" id="source" value="<?= htmlspecialchars($source) ?>">
                    </div>
                </div>
            </div>

            <!-- Swap Button -->
            <div class="col-md-1 text-center mb-2 d-none d-md-block">
                <button type="button" id="swapCities" class="btn btn-secondary-glass p-2 rounded-circle" style="width: 42px; height: 42px; display: flex; align-items: center; justify-content: center; margin: 0 auto;">
                    <i class="fa-solid fa-right-left"></i>
                </button>
            </div>

            <!-- Destination dropdown -->
            <div class="col-md-3">
                <div class="d-flex justify-content-between align-items-center mb-1" style="min-height: 21px;">
                    <label for="destination_search" class="form-label text-secondary small fw-semibold mb-0"><?= __('going_to', 'Going To') ?></label>
                    <div id="dest-empty" class="small text-warning" style="display:none; font-weight: 500;"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= __('no_routes', 'No routes.') ?></div>
                    <div id="dest-loading" class="small text-muted" style="display:none; font-weight: 500;"><i class="fa-solid fa-spinner fa-spin me-1"></i><?= __('loading', 'Loading...') ?></div>
                </div>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-location-crosshairs"></i></span>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="destination_search" class="form-control form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" placeholder="<?= __('select_destination', 'Select Destination...') ?>" value="<?= htmlspecialchars($destination) ?>" autocomplete="off" required>
                        <input type="hidden" name="destination" id="destination" value="<?= htmlspecialchars($destination) ?>">
                    </div>
                </div>
            </div>

            <!-- Date Picker -->
            <div class="col-md-3">
                <label for="date" class="form-label text-secondary small fw-semibold"><?= __('travel_date', 'Travel Date') ?></label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-calendar-days"></i></span>
                    <input type="date" name="date" id="date" class="form-control form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($date) ?>" required>
                </div>
            </div>

            <!-- Search Button -->
            <div class="col-md-2">
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary-gradient py-2 fw-bold text-uppercase" style="font-size: 0.9rem;"><?= __('btn_search', 'Search') ?></button>
                </div>
            </div>
        </form>
    </div>
</div>

<h4 class="fw-bold mb-4 text-white"><?= __('available_buses', 'Available Services') ?> (<?= count($trips) ?> <?= __('found', 'found') ?>)</h4>

<?php if (count($trips) === 0): ?>
    <div class="glass-card p-5 text-center my-5">
        <i class="fa-solid fa-circle-info text-secondary mb-3" style="font-size: 4rem;"></i>
        <h3 class="text-white fw-bold"><?= __('no_buses_found', 'No Buses Found') ?></h3>
        <p class="text-secondary"><?= __('try_different_date', 'Try searching for a different date or route.') ?></p>
        <div class="d-flex justify-content-center gap-3 mt-4">
            <a href="<?= BASE_URL ?>/index.php" class="btn btn-primary-gradient"><i class="fa-solid fa-arrow-left me-2"></i><?= __('go_home', 'Back to Home') ?></a>
        </div>
    </div>
<?php else: ?>
    <div class="row">
        <div class="col-lg-12">
            <?php foreach ($trips as $trip):
                $dep_time = new DateTime($trip['departure_time']);
                $arr_time = new DateTime($trip['arrival_time']);
                $duration = $dep_time->diff($arr_time);
                $total_hours = ($duration->days * 24) + $duration->h;
                $duration_str = $total_hours . ' hrs ' . $duration->i . ' mins';

                $pickups = json_decode($trip['pickup_points'], true) ?? [];
                $drops = json_decode($trip['drop_points'], true) ?? [];
                $trip_dep_fmt = $dep_time->format('H:i');
                $trip_arr_fmt = $arr_time->format('H:i');
            ?>
                <!-- Individual Bus Card -->
                <div class="bg-white text-dark p-4 mb-4 border rounded-4 shadow-sm" style="background: var(--card-bg) !important; border: 1px solid var(--border-color) !important;">
                    <div class="row align-items-center g-3">
                        
                        <!-- Bus Thumbnail -->
                        <div class="col-md-2">
                            <div class="position-relative overflow-hidden rounded-3 bg-dark" style="height: 110px;">
                                <?php if (!empty($trip['thumbnail'])): ?>
                                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($trip['thumbnail']) ?>" class="w-100 h-100 object-fit-cover" alt="Bus Image">
                                <?php else: ?>
                                    <div class="w-100 h-100 d-flex flex-column justify-content-center align-items-center text-center p-2 text-secondary bg-black bg-opacity-25" style="font-size:0.7rem; color:var(--text-secondary) !important;">
                                        <i class="fa-solid fa-bus-simple mb-1 fs-5 text-secondary"></i>
                                        <span><?= __('photos_unavailable', 'No photos uploaded') ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Bus Name & Type Info -->
                        <div class="col-md-3">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <h5 class="fw-bold text-dark mb-0" style="font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-primary) !important;"><?= htmlspecialchars($trip['bus_name']) ?></h5>
                                <?php if ($trip['is_verified']): ?>
                                    <span class="text-success" title="<?= __('verified_bus', 'Verified Bus') ?>"><i class="fa-solid fa-circle-check"></i></span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                <span class="badge bg-light text-success border border-success border-opacity-25 text-uppercase small py-1 px-2" style="font-size: 0.7rem; font-weight: 600; color: var(--accent-primary) !important;"><?= htmlspecialchars($trip['bus_type']) ?></span>
                                <?php if ($trip['has_tracking']): ?>
                                    <span class="badge bg-info bg-opacity-10 text-info border border-info border-opacity-25 small py-1 px-2" style="font-size: 0.7rem;"><i class="fa-solid fa-location-dot fa-fade me-1 text-info"></i><?= __('live_tracking', 'Live Tracking') ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex align-items-center gap-2 text-secondary small">
                                <span class="font-monospace"><i class="fa-solid fa-hashtag me-1"></i><?= htmlspecialchars($trip['bus_number']) ?></span>
                                <?php if ($trip['total_reviews'] > 0): ?>
                                    <span class="text-warning fw-semibold ms-2"><i class="fa-solid fa-star me-1 text-warning"></i><?= number_format($trip['avg_rating'], 1) ?> (<?= $trip['total_reviews'] ?>)</span>
                                <?php endif; ?>
                            </div>

                            <!-- Quick Action Details buttons -->
                            <div class="d-flex gap-2 mt-3">
                                <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 rounded-2" style="font-size:0.75rem; background: var(--bg-secondary) !important; border-color: var(--border-color) !important; color: var(--text-secondary) !important;" onclick="openBusDetails(<?= $trip['bus_id'] ?>, 'photos')"><i class="fa-solid fa-images me-1 text-indigo"></i><?= __('btn_view_photos', 'Photos') ?></button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 rounded-2" style="font-size:0.75rem; background: var(--bg-secondary) !important; border-color: var(--border-color) !important; color: var(--text-secondary) !important;" onclick="openBusDetails(<?= $trip['bus_id'] ?>, 'amenities')"><i class="fa-solid fa-gift me-1 text-indigo"></i><?= __('btn_view_amenities', 'Amenities') ?></button>
                                <button type="button" class="btn btn-xs btn-outline-secondary py-1 px-2 rounded-2" style="font-size:0.75rem; background: var(--bg-secondary) !important; border-color: var(--border-color) !important; color: var(--text-secondary) !important;" onclick="openBusDetails(<?= $trip['bus_id'] ?>, 'specs')"><i class="fa-solid fa-info-circle me-1 text-indigo"></i><?= __('btn_view_details', 'Details') ?></button>
                            </div>
                        </div>

                        <!-- Timings & Route Milestones -->
                        <div class="col-md-3">
                            <div class="d-flex align-items-center justify-content-between text-center">
                                <div>
                                    <div class="fw-bold text-dark fs-5" style="color: var(--text-primary) !important;"><?= $dep_time->format('H:i') ?></div>
                                    <div class="text-secondary small"><?= htmlspecialchars($source) ?></div>
                                </div>
                                <div class="w-50 px-3 position-relative">
                                    <div class="text-secondary small mb-1"><?= $duration_str ?></div>
                                    <div style="height: 2px; background: var(--border-color); opacity: 0.8; position: relative;">
                                        <div style="width: 8px; height: 8px; border-radius:50%; background:var(--accent-primary); position:absolute; top:-3px; left:0;"></div>
                                        <div style="width: 8px; height: 8px; border-radius:50%; background:var(--accent-secondary); position:absolute; top:-3px; right:0;"></div>
                                    </div>
                                    <div class="text-secondary small mt-1"><?= htmlspecialchars($trip['distance_km']) ?> km</div>
                                </div>
                                <div>
                                    <div class="fw-bold text-dark fs-5" style="color: var(--text-primary) !important;"><?= $arr_time->format('H:i') ?></div>
                                    <div class="text-secondary small"><?= htmlspecialchars($destination) ?></div>
                                </div>
                            </div>
                        </div>

                        <!-- Milestones / Pickup Drop toggles -->
                        <div class="col-md-2 text-center text-md-start">
                            <div class="dropdown">
                                <button class="btn btn-light py-1 px-2 small text-secondary dropdown-toggle border" type="button" data-bs-toggle="dropdown" style="font-size: 0.8rem; background: var(--bg-secondary) !important; border-color: var(--border-color) !important; color: var(--text-secondary) !important;">
                                    <?= __('pickup_drop', 'Pickup / Drop points') ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark glass-card p-3 border-0 mt-2" style="min-width: 250px;">
                                    <h6 class="text-success small fw-bold mb-2" style="color: var(--accent-primary) !important;"><?= __('pickups', 'Pickups') ?> (<?= htmlspecialchars($source) ?>)</h6>
                                    <?php foreach ($pickups as $p):
                                        $pt = $p['time'] ?? '';
                                        $has_time = !empty($pt) && $pt !== '00:00' && $pt !== '00:00:00';
                                        $display_time = $has_time ? date('H:i', strtotime($pt)) : $trip_dep_fmt;
                                    ?>
                                        <li class="small text-secondary mb-1 d-flex justify-content-between">
                                            <span><?= htmlspecialchars($p['name']) ?></span>
                                            <span class="text-white ms-3"><?= $display_time ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                    <li>
                                        <hr class="dropdown-divider border-secondary my-2">
                                    </li>
                                    <h6 class="text-success small fw-bold mb-2" style="color: var(--accent-secondary) !important;"><?= __('drops', 'Drops') ?> (<?= htmlspecialchars($destination) ?>)</h6>
                                    <?php foreach ($drops as $d):
                                        $dt = $d['time'] ?? '';
                                        $has_time = !empty($dt) && $dt !== '00:00' && $dt !== '00:00:00';
                                        $display_time = $has_time ? date('H:i', strtotime($dt)) : $trip_arr_fmt;
                                    ?>
                                        <li class="small text-secondary mb-1 d-flex justify-content-between">
                                            <span><?= htmlspecialchars($d['name']) ?></span>
                                            <span class="text-white ms-3"><?= $display_time ?></span>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        </div>

                        <!-- Price & Seats Display -->
                        <div class="col-md-3 text-center text-md-end">
                            <?php 
                            $gst_info = calculate_gst($trip['base_fare']);
                            $total_fare = $gst_info['total'];
                            $gst_amt = $gst_info['amount'];
                            $gst_rate = $gst_info['rate'];
                            ?>
                            <div class="mb-2">
                                <span class="text-secondary small"><?= __('starting_from', 'Starting From') ?> </span>
                                <span class="fs-4 fw-bold text-success" style="color: var(--accent-primary) !important;"><?= CURRENCY ?><?= number_format($total_fare, 2) ?></span>
                                <div class="mt-1">
                                    <a href="#fare-details-<?= $trip['trip_id'] ?>" data-bs-toggle="collapse" class="text-secondary small text-decoration-none" style="font-size: 0.8rem; font-weight: 500;">
                                        <i class="fa-solid fa-circle-info me-1"></i><?= __('fare_details', 'Fare Details') ?> <i class="fa-solid fa-chevron-down ms-1" style="font-size: 0.7rem;"></i>
                                    </a>
                                </div>
                                <div class="collapse mt-2 text-start" id="fare-details-<?= $trip['trip_id'] ?>">
                                    <div class="p-2 rounded text-start border" style="font-size: 0.8rem; background: var(--bg-secondary) !important; border-color: var(--border-color) !important; color: var(--text-secondary) !important;">
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?= __('base_fare', 'Base Fare') ?>:</span>
                                            <span class="fw-semibold text-dark" style="color: var(--text-primary) !important;"><?= CURRENCY ?><?= number_format($trip['base_fare'], 2) ?></span>
                                        </div>
                                        <div class="d-flex justify-content-between mb-1">
                                            <span><?= __('gst', 'GST') ?> (<?= $gst_rate ?>%):</span>
                                            <span class="fw-semibold text-dark" style="color: var(--text-primary) !important;"><?= CURRENCY ?><?= number_format($gst_amt, 2) ?></span>
                                        </div>
                                        <div class="border-top my-1 border-secondary border-opacity-20"></div>
                                        <div class="d-flex justify-content-between fw-bold text-dark" style="color: var(--text-primary) !important;">
                                            <span><?= __('total_fare', 'Total Fare') ?>:</span>
                                            <span><?= CURRENCY ?><?= number_format($total_fare, 2) ?></span>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Seat badge indicator -->
                            <div class="mb-3">
                                <?php if ($trip['available_seats'] > 10): ?>
                                    <span class="badge bg-success bg-opacity-10 text-success py-2 px-3 rounded-pill border border-success border-opacity-25"><?= htmlspecialchars($trip['available_seats']) ?> <?= __('seats_left', 'seats left') ?></span>
                                <?php elseif ($trip['available_seats'] > 0): ?>
                                    <span class="badge bg-warning bg-opacity-10 text-warning py-2 px-3 rounded-pill border border-warning border-opacity-25"><?= htmlspecialchars($trip['available_seats']) ?> <?= __('seats_left', 'seats left') ?></span>
                                <?php else: ?>
                                    <span class="badge bg-danger bg-opacity-10 text-danger py-2 px-3 rounded-pill border border-danger border-opacity-25"><?= __('sold_out', 'SOLD OUT') ?></span>
                                <?php endif; ?>
                            </div>

                            <div>
                                <?php if ($trip['available_seats'] > 0): ?>
                                    <a href="<?= BASE_URL ?>/book.php?trip_id=<?= $trip['trip_id'] ?>" class="btn btn-primary-gradient w-100 text-uppercase fw-bold py-2" style="font-size: 0.9rem;"><?= __('select_seats', 'Select Seats') ?></a>
                                <?php else: ?>
                                    <button class="btn btn-secondary w-100 py-2 text-uppercase fw-bold disabled" style="font-size: 0.9rem;"><?= __('house_full', 'House Full') ?></button>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<script>
    $(document).ready(function() {
        // Setup sources array from PHP
        var sourcesList = <?= json_encode($sources) ?> || [];
        var destinationsList = [];

        // Helper function to create suggestion dropdown
        function setupAutocomplete($input, $hidden, listData, onSelect) {
            var wrapperClass = 'autocomplete-wrapper';
            var suggestionsClass = 'autocomplete-suggestions';
            var suggestionClass = 'autocomplete-suggestion';

            $input.on('focus click input', function() {
                var val = $(this).val().toLowerCase();
                var $wrapper = $(this).closest('.' + wrapperClass);
                
                // Remove existing suggestions
                $wrapper.find('.' + suggestionsClass).remove();

                // Filter list
                var filtered = listData.filter(function(item) {
                    return item.toLowerCase().indexOf(val) > -1;
                });

                if (filtered.length === 0) return;

                var $suggestions = $('<div class="' + suggestionsClass + '"></div>');
                $.each(filtered, function(i, item) {
                    var $sug = $('<div class="' + suggestionClass + '">' + item + '</div>');
                    $sug.on('mousedown', function(e) {
                        e.preventDefault(); // prevent blur
                        $input.val(item);
                        $hidden.val(item).trigger('change');
                        $wrapper.find('.' + suggestionsClass).remove();
                        if (onSelect) onSelect(item);
                    });
                    $suggestions.append($sug);
                });
                $wrapper.append($suggestions);
            });

            $input.on('blur', function() {
                setTimeout(function() {
                    $input.closest('.' + wrapperClass).find('.' + suggestionsClass).remove();
                }, 200);
            });
        }

        // Init Autocomplete for Source input
        setupAutocomplete($('#source_search'), $('#source'), sourcesList, function(selectedSource) {
            loadDestinations(selectedSource);
        });

        // Function to load destinations via AJAX
        function loadDestinations(source, callback) {
            var $destInput = $('#destination_search');
            var $destHidden = $('#destination');
            var $loading = $('#dest-loading');
            var $empty = $('#dest-empty');

            // Reset
            $destInput.prop('disabled', true);
            destinationsList = [];
            $loading.hide();
            $empty.hide();

            if (!source) {
                return;
            }

            $loading.show();

            $.getJSON('<?= BASE_URL ?>/ajax/get_destinations.php', {
                source: source
            }, function(data) {
                $loading.hide();
                
                if (data.length === 0) {
                    $empty.show();
                    return;
                }

                destinationsList = data;
                $destInput.prop('disabled', false);
                
                // Re-init setup with updated destinationsList
                setupAutocomplete($destInput, $destHidden, destinationsList);
                
                if (callback) callback();
            }).fail(function() {
                $loading.hide();
                $destInput.val('Error loading routes');
            });
        }

        // Swapper functionality
        $('#swapCities').on('click', function() {
            var srcVal = $('#source').val();
            var destVal = $('#destination').val();

            if (!destVal) return;

            $('#source_search').val(destVal);
            $('#source').val(destVal);

            loadDestinations(destVal, function() {
                $('#destination_search').val(srcVal);
                $('#destination').val(srcVal);
            });
        });

        // Initialize active destinations list for current source on page load
        var currentSource = $('#source').val();
        if (currentSource) {
            var currentDestVal = '<?= htmlspecialchars($destination) ?>';
            loadDestinations(currentSource, function() {
                // Ensure correct destination is set after loading
                $('#destination_search').val(currentDestVal);
                $('#destination').val(currentDestVal);
            });
        }
    });
</script>

<!-- Bus Experience Details Modal -->
<div class="modal fade" id="busDetailsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content glass-card text-white border-secondary border-opacity-30 p-3" style="background:#111111; border-radius: 20px; border: 1px solid rgba(255,255,255,0.08) !important;">
            <div class="modal-header border-0 pb-0">
                <div>
                    <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2">
                        <span id="modal_bus_name">Bus Details</span>
                        <span id="modal_bus_verified" class="text-success small" style="display:none;"><i class="fa-solid fa-circle-check"></i> Verified</span>
                    </h5>
                    <span id="modal_bus_type" class="text-secondary small">AC Sleeper</span>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body py-3">
                <!-- Tabs Navigation -->
                <ul class="nav nav-pills mb-3 gap-2" id="modalBusTab" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link btn btn-secondary-glass active px-3 py-1 border-0 text-white" id="m-photos-tab" data-bs-toggle="pill" data-bs-target="#m-photos-pane" type="button" role="tab"><i class="fa-solid fa-images me-2"></i>Photos</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link btn btn-secondary-glass px-3 py-1 border-0 text-white" id="m-amenities-tab" data-bs-toggle="pill" data-bs-target="#m-amenities-pane" type="button" role="tab"><i class="fa-solid fa-gift me-2"></i>Amenities</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link btn btn-secondary-glass px-3 py-1 border-0 text-white" id="m-specs-tab" data-bs-toggle="pill" data-bs-target="#m-specs-pane" type="button" role="tab"><i class="fa-solid fa-gears me-2"></i>Specifications</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link btn btn-secondary-glass px-3 py-1 border-0 text-white" id="m-policies-tab" data-bs-toggle="pill" data-bs-target="#m-policies-pane" type="button" role="tab"><i class="fa-solid fa-shield-halved me-2"></i>Policies</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link btn btn-secondary-glass px-3 py-1 border-0 text-white" id="m-reviews-tab" data-bs-toggle="pill" data-bs-target="#m-reviews-pane" type="button" role="tab"><i class="fa-solid fa-star me-2 text-warning"></i>Reviews</button>
                    </li>
                    <li class="nav-item" id="m-tracking-tab-li" style="display:none;">
                        <button class="nav-link btn btn-secondary-glass px-3 py-1 border-0 text-white" id="m-tracking-tab" data-bs-toggle="pill" data-bs-target="#m-tracking-pane" type="button" role="tab"><i class="fa-solid fa-map-location-dot me-2 text-info"></i>Tracking</button>
                    </li>
                </ul>

                <!-- Tabs Content -->
                <div class="tab-content text-white" id="modalBusTabContent">
                    <!-- Photos Tab -->
                    <div class="tab-pane fade show active" id="m-photos-pane" role="tabpanel">
                        <div id="modal_gallery_container" class="row g-2">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- Amenities Tab -->
                    <div class="tab-pane fade" id="m-amenities-pane" role="tabpanel">
                        <div id="modal_amenities_container" class="row g-2">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- Specifications Tab -->
                    <div class="tab-pane fade" id="m-specs-pane" role="tabpanel">
                        <div class="row g-3" id="modal_specs_container">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- Policies Tab -->
                    <div class="tab-pane fade" id="m-policies-pane" role="tabpanel">
                        <div class="d-flex flex-column gap-3" id="modal_policies_container">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- Reviews Tab -->
                    <div class="tab-pane fade" id="m-reviews-pane" role="tabpanel">
                        <div class="row g-3 mb-4" id="modal_reviews_summary_container">
                            <!-- Populated by JS -->
                        </div>
                        <div class="d-flex flex-column gap-2" id="modal_reviews_list_container">
                            <!-- Populated by JS -->
                        </div>
                    </div>

                    <!-- Live Tracking Tab -->
                    <div class="tab-pane fade" id="m-tracking-pane" role="tabpanel">
                        <div class="card bg-black bg-opacity-50 border-0 p-3 mb-3">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="small text-secondary mb-1">Current Location</div>
                                    <h6 class="fw-bold text-white" id="track_curr_loc">N/A</h6>
                                </div>
                                <div class="col-md-6">
                                    <div class="small text-secondary mb-1">Estimated Arrival Time (ETA)</div>
                                    <h6 class="fw-bold text-success" id="track_eta">N/A</h6>
                                </div>
                            </div>
                        </div>
                        <div id="modal_map" style="height: 300px; border-radius: 12px; background: #222;" class="position-relative"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

<script>
var leafletMap = null;
var leafletMarker = null;

function openBusDetails(busId, activeTab) {
    // Show modal first
    var myModalEl = document.getElementById('busDetailsModal');
    var modal = bootstrap.Modal.getInstance(myModalEl) || new bootstrap.Modal(myModalEl);
    modal.show();

    // Reset tabs
    document.querySelectorAll('#modalBusTab .nav-link').forEach(function(btn) {
        btn.classList.remove('active');
    });
    document.querySelectorAll('#modalBusTabContent .tab-pane').forEach(function(pane) {
        pane.classList.remove('show', 'active');
    });

    var targetTabBtn = document.getElementById('m-' + activeTab + '-tab');
    var targetPane = document.getElementById('m-' + activeTab + '-pane');
    if (targetTabBtn && targetPane) {
        targetTabBtn.classList.add('active');
        targetPane.classList.add('show', 'active');
    }

    // Load details via AJAX
    $.getJSON('<?= BASE_URL ?>/ajax/get_bus_details_ajax.php', { bus_id: busId }, function(res) {
        if (!res.success) {
            alert(res.message);
            return;
        }

        // Main Title
        document.getElementById('modal_bus_name').innerText = res.bus.bus_name + " (" + res.bus.bus_number + ")";
        document.getElementById('modal_bus_type').innerText = res.bus.bus_type;
        document.getElementById('modal_bus_verified').style.display = res.bus.is_verified ? 'inline' : 'none';

        // Photos Tab
        var pContainer = document.getElementById('modal_gallery_container');
        pContainer.innerHTML = '';
        if (res.media.length === 0) {
            pContainer.innerHTML = '<div class="col-12 text-center text-secondary py-4"><i class="fa-solid fa-images mb-2 d-block fs-3"></i>Operator has not uploaded bus photos yet</div>';
        } else {
            res.media.forEach(function(m) {
                var col = document.createElement('div');
                col.className = 'col-md-4 col-sm-6';
                if (m.media_type === 'video') {
                    col.innerHTML = `
                        <div class="position-relative overflow-hidden rounded-3 bg-black" style="height: 150px;">
                            <video class="w-100 h-100 object-fit-cover" controls>
                                <source src="<?= BASE_URL ?>/${m.file_path}" type="video/mp4">
                            </video>
                        </div>
                    `;
                } else {
                    col.innerHTML = `
                        <div class="position-relative overflow-hidden rounded-3 bg-dark" style="height: 150px;">
                            <a href="<?= BASE_URL ?>/${m.file_path}" target="_blank">
                                <img src="<?= BASE_URL ?>/${m.file_path}" class="w-100 h-100 object-fit-cover" alt="">
                            </a>
                            <span class="position-absolute bottom-0 start-0 w-100 bg-black bg-opacity-70 p-1 text-center text-indigo text-uppercase small" style="font-size:0.6rem;">${m.category.replace('_', ' ')}</span>
                        </div>
                    `;
                }
                pContainer.appendChild(col);
            });
        }

        // Amenities Tab
        var aContainer = document.getElementById('modal_amenities_container');
        aContainer.innerHTML = '';
        if (res.amenities.length === 0) {
            aContainer.innerHTML = '<div class="col-12 text-center text-secondary py-4">No amenities specified</div>';
        } else {
            res.amenities.forEach(function(am) {
                var col = document.createElement('div');
                col.className = 'col-md-4 col-sm-6';
                var icon = '<i class="fa-solid fa-circle-check text-success me-2"></i>';
                if (am.is_custom && am.icon_path) {
                    icon = `<img src="<?= BASE_URL ?>/${am.icon_path}" class="me-2" style="width:20px; height:20px;">`;
                }
                col.innerHTML = `
                    <div class="p-2 rounded bg-dark border border-secondary border-opacity-15 d-flex align-items-center">
                        ${icon}
                        <span class="small text-white">${am.amenity_name}</span>
                    </div>
                `;
                aContainer.appendChild(col);
            });
        }

        // Specifications Tab
        var sContainer = document.getElementById('modal_specs_container');
        sContainer.innerHTML = `
            <div class="col-md-6">
                <div class="text-secondary small">Manufacturer</div>
                <div class="fw-semibold text-white">${res.specifications.manufacturer || 'N/A'}</div>
            </div>
            <div class="col-md-6">
                <div class="text-secondary small">Model</div>
                <div class="fw-semibold text-white">${res.specifications.model || 'N/A'}</div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Year of Manufacture</div>
                <div class="fw-semibold text-white">${res.specifications.year || 'N/A'}</div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">Fuel Type</div>
                <div class="fw-semibold text-white">${res.specifications.fuel_type || 'N/A'}</div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary small">AC Type</div>
                <div class="fw-semibold text-white">${res.specifications.ac_type || 'N/A'}</div>
            </div>
            <div class="col-md-6">
                <div class="text-secondary small">Berth Layout</div>
                <div class="fw-semibold text-white">${res.specifications.sleeper_layout || 'N/A'}</div>
            </div>
            <div class="col-12">
                <div class="text-secondary small">Description</div>
                <div class="text-white-50 small">${res.specifications.description || 'No description provided.'}</div>
            </div>
        `;

        // Policies Tab
        var poContainer = document.getElementById('modal_policies_container');
        poContainer.innerHTML = `
            <div>
                <h6 class="fw-bold text-indigo mb-1"><i class="fa-solid fa-shield-halved me-2 text-indigo"></i>Cancellation Policy</h6>
                <p class="small text-secondary mb-0">${res.policies.cancellation_policy || 'Standard cancellation policy applies.'}</p>
            </div>
            <div>
                <h6 class="fw-bold text-indigo mb-1"><i class="fa-solid fa-briefcase me-2 text-indigo"></i>Luggage Policy</h6>
                <p class="small text-secondary mb-0">${res.policies.luggage_policy || 'Standard baggage allowances apply.'}</p>
            </div>
            <div>
                <h6 class="fw-bold text-indigo mb-1"><i class="fa-solid fa-baby me-2 text-indigo"></i>Child Policy</h6>
                <p class="small text-secondary mb-0">${res.policies.child_policy || 'Standard policies for child seats apply.'}</p>
            </div>
        `;

        // Reviews Tab
        var revSumContainer = document.getElementById('modal_reviews_summary_container');
        var revListContainer = document.getElementById('modal_reviews_list_container');
        
        if (parseInt(res.reviews_summary.total_reviews) === 0) {
            revSumContainer.innerHTML = '<div class="col-12 text-center text-secondary py-4">No passenger reviews available.</div>';
            revListContainer.innerHTML = '';
        } else {
            revSumContainer.innerHTML = `
                <div class="col-md-4 text-center d-flex flex-column justify-content-center align-items-center border-end border-secondary border-opacity-20">
                    <h1 class="fw-bold text-warning mb-1">${parseFloat(res.reviews_summary.avg_rating).toFixed(1)}</h1>
                    <div class="text-warning mb-1"><i class="fa-solid fa-star"></i></div>
                    <span class="text-secondary small">${res.reviews_summary.total_reviews} Reviews</span>
                </div>
                <div class="col-md-8">
                    <div class="row g-2">
                        <div class="col-6 small text-secondary">Cleanliness: <span class="text-white fw-bold">${parseFloat(res.reviews_summary.avg_cleanliness).toFixed(1)}</span></div>
                        <div class="col-6 small text-secondary">Comfort: <span class="text-white fw-bold">${parseFloat(res.reviews_summary.avg_comfort).toFixed(1)}</span></div>
                        <div class="col-6 small text-secondary">Punctuality: <span class="text-white fw-bold">${parseFloat(res.reviews_summary.avg_punctuality).toFixed(1)}</span></div>
                        <div class="col-6 small text-secondary">Staff Behavior: <span class="text-white fw-bold">${parseFloat(res.reviews_summary.avg_staff).toFixed(1)}</span></div>
                        <div class="col-6 small text-secondary">Safety: <span class="text-white fw-bold">${parseFloat(res.reviews_summary.avg_safety).toFixed(1)}</span></div>
                    </div>
                </div>
            `;

            revListContainer.innerHTML = '';
            res.reviews.forEach(function(r) {
                var row = document.createElement('div');
                row.className = 'p-3 rounded bg-dark border border-secondary border-opacity-15 mb-2';
                row.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <span class="fw-bold text-white small">${r.username}</span>
                        <span class="text-warning small"><i class="fa-solid fa-star me-1"></i>${parseFloat(r.rating).toFixed(1)}</span>
                    </div>
                    <p class="small text-secondary mb-0">${r.review_text || ''}</p>
                `;
                revListContainer.appendChild(row);
            });
        }

        // Live Tracking Tab
        var trackingLi = document.getElementById('m-tracking-tab-li');
        if (res.tracking) {
            trackingLi.style.display = 'block';
            document.getElementById('track_curr_loc').innerText = res.tracking.current_location_name || 'In Transit';
            document.getElementById('track_eta').innerText = res.tracking.eta || 'Calculating...';

            // Init Map on show
            var tabEl = document.getElementById('m-tracking-tab');
            tabEl.onclick = function() {
                setTimeout(function() {
                    initTrackingMap(parseFloat(res.tracking.latitude), parseFloat(res.tracking.longitude), res.tracking.current_location_name);
                }, 200);
            };
        } else {
            trackingLi.style.display = 'none';
        }
    });
}

function initTrackingMap(lat, lng, name) {
    if (leafletMap) {
        leafletMap.setView([lat, lng], 13);
        leafletMarker.setLatLng([lat, lng]).bindPopup(name || 'Bus Current Location').openPopup();
        leafletMap.invalidateSize();
    } else {
        leafletMap = L.map('modal_map').setView([lat, lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; OpenStreetMap contributors'
        }).addTo(leafletMap);

        leafletMarker = L.marker([lat, lng]).addTo(leafletMap)
            .bindPopup(name || 'Bus Current Location')
            .openPopup();
            
        setTimeout(function() {
            leafletMap.invalidateSize();
        }, 100);
    }
}
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>