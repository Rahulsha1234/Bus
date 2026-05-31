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
            COALESCE(
                (SELECT MIN(custom_price) FROM seat_price_overrides WHERE trip_id = t.id AND custom_price > 0),
                (SELECT MIN(current_price) FROM seat_pricing WHERE trip_id = t.id AND current_price > 0),
                (SELECT MIN(base_price) FROM bus_seats bs WHERE bs.bus_id = b.id AND bs.base_price > 0 AND bs.is_active = 1),
                t.base_fare
            ) AS base_fare,
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
            <i class="fa-solid fa-pen-to-square me-2"></i>Modify Search
        </button>
    </div>

    <!-- Collapsible Inline Search Form (Shown by default) -->
    <div class="collapse show mt-4 pt-3 border-top border-secondary border-opacity-20" id="modifySearchCollapse">
        <form action="<?= BASE_URL ?>/search.php" method="GET" class="row g-3 align-items-end">
            <!-- Source dropdown -->
            <div class="col-md-3">
                <label for="source_search" class="form-label text-secondary small fw-semibold">Leaving From</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-location-dot"></i></span>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="source_search" class="form-control form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" placeholder="Select Origin..." value="<?= htmlspecialchars($source) ?>" autocomplete="off" required>
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
                <label for="destination_search" class="form-label text-secondary small fw-semibold">Going To</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-location-crosshairs"></i></span>
                    <div class="autocomplete-wrapper">
                        <input type="text" id="destination_search" class="form-control form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" placeholder="Select Destination..." value="<?= htmlspecialchars($destination) ?>" autocomplete="off" required>
                        <input type="hidden" name="destination" id="destination" value="<?= htmlspecialchars($destination) ?>">
                    </div>
                </div>
                <div id="dest-loading" class="small text-muted mt-1" style="display:none;"><i class="fa-solid fa-spinner fa-spin me-1"></i>Loading...</div>
            </div>

            <!-- Date Picker -->
            <div class="col-md-3">
                <label for="date" class="form-label text-secondary small fw-semibold">Travel Date</label>
                <div class="input-group">
                    <span class="input-group-text bg-dark border-secondary border-end-0 text-secondary" style="border-radius: 12px 0 0 12px;"><i class="fa-solid fa-calendar-days"></i></span>
                    <input type="date" name="date" id="date" class="form-control form-control-swift border-start-0" style="border-radius: 0 12px 12px 0;" min="<?= date('Y-m-d') ?>" value="<?= htmlspecialchars($date) ?>" required>
                </div>
            </div>

            <!-- Search Button -->
            <div class="col-md-2">
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary-gradient py-2 fw-bold text-uppercase" style="font-size: 0.9rem;">Search</button>
                </div>
            </div>
        </form>
    </div>
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

                        <!-- Bus Name & Type Info -->
                        <div class="col-md-3">
                            <h5 class="fw-bold text-dark mb-1" style="font-family: 'Plus Jakarta Sans', sans-serif; color: var(--text-primary) !important;"><?= htmlspecialchars($trip['bus_name']) ?></h5>
                            <span class="badge bg-light text-success border border-success border-opacity-25 text-uppercase small py-1 px-2" style="font-size: 0.75rem; font-weight: 600; color: var(--accent-primary) !important;"><?= htmlspecialchars($trip['bus_type']) ?></span>
                            <div class="text-secondary small mt-2"><i class="fa-solid fa-hashtag me-2"></i><?= htmlspecialchars($trip['bus_number']) ?></div>
                        </div>

                        <!-- Timings & Route Milestones -->
                        <div class="col-md-4">
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
                                <button class="btn btn-light py-1 px-2 small text-secondary dropdown-toggle border" type="button" data-bs-toggle="dropdown" style="font-size: 0.8rem; background: var(--bg-secondary) !important;">
                                    Pickup / Drop points
                                </button>
                                <ul class="dropdown-menu dropdown-menu-dark glass-card p-3 border-0 mt-2" style="min-width: 250px;">
                                    <h6 class="text-success small fw-bold mb-2" style="color: var(--accent-primary) !important;">Pickups (<?= htmlspecialchars($source) ?>)</h6>
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
                                    <h6 class="text-success small fw-bold mb-2" style="color: var(--accent-secondary) !important;">Drops (<?= htmlspecialchars($destination) ?>)</h6>
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
                            <div class="mb-2">
                                <span class="text-secondary small">Starting at </span>
                                <span class="fs-4 fw-bold text-success" style="color: var(--accent-primary) !important;"><?= CURRENCY ?><?= number_format($trip['base_fare'], 2) ?></span>
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

            // Reset
            $destInput.prop('disabled', true);
            destinationsList = [];
            $loading.hide();

            if (!source) {
                return;
            }

            $loading.show();

            $.getJSON('<?= BASE_URL ?>/ajax/get_destinations.php', {
                source: source
            }, function(data) {
                $loading.hide();
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

<?php
require_once __DIR__ . '/includes/footer.php';
?>