<?php
/**
 * Agent Seat Controller (Hold/Release)
 */
require_once __DIR__ . '/header.php';

$agent_id = $_SESSION['user_id'];

// Fetch Agent's scheduled active trips to populate dropdown selector
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id AS trip_id,
            t.departure_time,
            b.bus_name,
            b.bus_number,
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
} catch (PDOException $e) {
    $trips = [];
}

// Check if a specific trip is selected
$selected_trip_id = $_GET['trip_id'] ?? '';
$trip_details = null;
$seat_status_lookup = [];

if (!empty($selected_trip_id)) {
    try {
        // Fetch Selected Trip info
        $stmt = $pdo->prepare("
            SELECT t.id, b.seat_layout_type, b.bus_name, r.source, r.destination 
            FROM trips t
            JOIN buses b ON t.bus_id = b.id
            JOIN routes r ON t.route_id = r.id
            WHERE t.id = ? AND b.agent_id = ?
            LIMIT 1
        ");
        $stmt->execute([$selected_trip_id, $agent_id]);
        $trip_details = $stmt->fetch();

        if ($trip_details) {
            // Fetch seat details
            $seats_stmt = $pdo->prepare("SELECT seat_number, status, hold_expires_at FROM trip_seats WHERE trip_id = ?");
            $seats_stmt->execute([$selected_trip_id]);
            $seats = $seats_stmt->fetchAll();

            $now = date('Y-m-d H:i:s');
            foreach ($seats as $s) {
                $status = $s['status'];
                // Expired hold treats as available
                if ($status === 'hold' && !empty($s['hold_expires_at']) && strtotime($s['hold_expires_at']) < strtotime($now)) {
                    $status = 'available';
                }
                $seat_status_lookup[$s['seat_number']] = $status;
            }
        }
    } catch (PDOException $e) {
        // Fail silently
    }
}
?>

<div class="row g-4 mb-4">
    <!-- Trip Selector Sidebar -->
    <div class="col-lg-4">
        <div class="glass-card p-4 shadow-lg h-100">
            <h5 class="fw-bold text-white mb-4"><i class="fa-solid fa-list-check text-indigo me-2"></i>Select Active Schedule</h5>
            
            <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET">
                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Choose Trip Voyage</label>
                    <select name="trip_id" class="form-select form-control-swift" onchange="this.form.submit()" required>
                        <option value="">Select Schedule...</option>
                        <?php foreach ($trips as $t): ?>
                            <option value="<?= $t['trip_id'] ?>" <?= ($selected_trip_id == $t['trip_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['source']) ?> to <?= htmlspecialchars($t['destination']) ?> (<?= date('d M, H:i', strtotime($t['departure_time'])) ?>) - <?= htmlspecialchars($t['bus_number']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </form>
            
            <?php if ($trip_details): ?>
                <div class="p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10 mt-4 small text-secondary">
                    <h6 class="text-white fw-bold mb-2">Instructions</h6>
                    <p class="mb-2"><span class="badge bg-success bg-opacity-20 text-success border border-success border-opacity-10 me-2"><i class="fa-solid fa-tap"></i>Tap Seat</span>Click an available green seat to place it on manual **Offline Hold**.</p>
                    <p class="mb-0"><span class="badge bg-warning bg-opacity-20 text-warning border border-warning border-opacity-10 me-2"><i class="fa-solid fa-rotate"></i>Release</span>Click a held yellow seat to instantly **Release** it back to the public pool.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Interactive Grid Column -->
    <div class="col-lg-8">
        <div class="glass-card p-4 shadow-lg h-100">
            <?php if (!$trip_details): ?>
                <div class="text-center py-5 text-secondary small h-100 d-flex flex-column align-items-center justify-content-center">
                    <i class="fa-solid fa-chair mb-3 d-block" style="font-size: 4rem; color:#475569;"></i>
                    Please select an active trip schedule from the sidebar to load the seat allocation map.
                </div>
            <?php else: ?>
                <div class="d-flex align-items-center justify-content-between mb-4 pb-3 border-bottom border-secondary border-opacity-20">
                    <div>
                        <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-compass text-pink me-2"></i>Seat Map Console</h4>
                        <span class="text-secondary small">Trip: <?= htmlspecialchars($trip_details['source']) ?> to <?= htmlspecialchars($trip_details['destination']) ?> (<?= htmlspecialchars($trip_details['bus_name']) ?>)</span>
                    </div>
                    <!-- Legend -->
                    <div class="d-flex gap-2">
                        <div class="legend-item"><span class="legend-dot bg-success"></span><span class="small text-secondary">Available</span></div>
                        <div class="legend-item"><span class="legend-dot bg-warning"></span><span class="small text-secondary">Held</span></div>
                        <div class="legend-item"><span class="legend-dot bg-danger"></span><span class="small text-secondary">Booked</span></div>
                    </div>
                </div>

                <div class="text-center py-3">
                    <?php if ($trip_details['seat_layout_type'] === '2x1_sleeper'): ?>
                        <!-- Berth tabs -->
                        <ul class="nav nav-pills justify-content-center mb-4 gap-2" id="agentBerthTab" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link btn-secondary-glass active px-4 py-2" id="a-lower-tab" data-bs-toggle="pill" data-bs-target="#a-lower-berth" type="button" role="tab">Lower Berth</button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link btn-secondary-glass px-4 py-2" id="a-upper-tab" data-bs-toggle="pill" data-bs-target="#a-upper-berth" type="button" role="tab">Upper Berth</button>
                            </li>
                        </ul>

                        <div class="tab-content" id="agentBerthTabContent">
                            <!-- Lower Berth -->
                            <div class="tab-pane fade show active" id="a-lower-berth" role="tabpanel">
                                <div class="seat-map-container">
                                    <div class="d-flex justify-content-between mb-4 pb-2 border-bottom border-secondary border-opacity-15">
                                        <span class="text-secondary small">FRONT</span>
                                        <span class="text-secondary small"><i class="fa-solid fa-steering-wheel"></i></span>
                                    </div>
                                    <div class="seat-grid-sleeper">
                                        <?php 
                                        for ($i = 1; $i <= 15; $i++) {
                                            $seatNum = "L" . $i;
                                            $status = $seat_status_lookup[$seatNum] ?? 'available';
                                            echo '<div class="seat sleeper-berth control-seat-trigger ' . $status . '" data-seat="' . $seatNum . '">' . $seatNum . '</div>';
                                            if ($i % 2 === 0 && ($i + 1) % 3 === 0) {
                                                echo '<div class="seat-walkway"></div>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Upper Berth -->
                            <div class="tab-pane fade" id="a-upper-berth" role="tabpanel">
                                <div class="seat-map-container">
                                    <div class="d-flex justify-content-between mb-4 pb-2 border-bottom border-secondary border-opacity-15">
                                        <span class="text-secondary small">FRONT</span>
                                        <span class="text-secondary small"><i class="fa-solid fa-steering-wheel"></i></span>
                                    </div>
                                    <div class="seat-grid-sleeper">
                                        <?php 
                                        for ($i = 1; $i <= 15; $i++) {
                                            $seatNum = "U" . $i;
                                            $status = $seat_status_lookup[$seatNum] ?? 'available';
                                            echo '<div class="seat sleeper-berth control-seat-trigger ' . $status . '" data-seat="' . $seatNum . '">' . $seatNum . '</div>';
                                            if ($i % 2 === 0 && ($i + 1) % 3 === 0) {
                                                echo '<div class="seat-walkway"></div>';
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Seater Grid -->
                        <div class="seat-map-container" style="max-width: 450px;">
                            <div class="d-flex justify-content-between mb-4 pb-2 border-bottom border-secondary border-opacity-15">
                                <span class="text-secondary small">FRONT / ENGINE</span>
                                <span class="text-secondary small"><i class="fa-solid fa-steering-wheel"></i></span>
                            </div>
                            <div class="seat-grid-seater">
                                <?php 
                                for ($i = 1; $i <= 40; $i++) {
                                    $seatNum = strval($i);
                                    $status = $seat_status_lookup[$seatNum] ?? 'available';
                                    echo '<div class="seat control-seat-trigger ' . $status . '" data-seat="' . $seatNum . '">' . $seatNum . '</div>';
                                    if ($i % 4 === 2) {
                                        echo '<div class="seat-walkway"></div>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- CSRF for AJAX -->
<input type="hidden" id="ajax_csrf_token" value="<?= get_csrf_token() ?>">

<script>
$(document).ready(function() {
    
    // Tap seat behavior
    $('.control-seat-trigger').click(function() {
        var seat = $(this).data('seat');
        var tripId = '<?= $selected_trip_id ?>';
        var csrf = $('#ajax_csrf_token').val();
        var cell = $(this);

        if (cell.hasClass('booked')) {
            alert("This seat is permanently booked by a passenger. Booked seats cannot be manually held or released.");
            return;
        }

        if (cell.hasClass('available')) {
            // Initiate Hold
            if (confirm("Hold Seat " + seat + " for offline booking?")) {
                $.ajax({
                    url: '<?= BASE_URL ?>/ajax/hold_seat.php',
                    type: 'POST',
                    data: { trip_id: tripId, seat_number: seat, csrf_token: csrf },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            cell.removeClass('available').addClass('hold');
                            alert("Seat " + seat + " successfully put on hold.");
                        } else {
                            alert("Action Failed: " + response.message);
                        }
                    },
                    error: function() {
                        alert("CRITICAL: Server failed to process hold request.");
                    }
                });
            }
        } else if (cell.hasClass('hold')) {
            // Initiate Release
            if (confirm("Release held Seat " + seat + " back to available status?")) {
                $.ajax({
                    url: '<?= BASE_URL ?>/ajax/release_seat.php',
                    type: 'POST',
                    data: { trip_id: tripId, seat_number: seat, csrf_token: csrf },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            cell.removeClass('hold').addClass('available');
                            alert("Seat " + seat + " released successfully.");
                        } else {
                            alert("Action Failed: " + response.message);
                        }
                    },
                    error: function() {
                        alert("CRITICAL: Server failed to process release request.");
                    }
                });
            }
        }
    });

});
</script>

<?php
require_once __DIR__ . '/footer.php';
?>
