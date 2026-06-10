<?php
/**
 * Seat Booking Page (Customer View with Real-time Locks & Safety Rules)
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$trip_id = intval($_GET['trip_id'] ?? 0);
if ($trip_id === 0) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

$page_title = __('select_seats', 'Select Seats');

// Redirect to login if guest
if (!is_logged_in()) {
    $_SESSION['redirect_url'] = BASE_URL . "/book.php?trip_id=" . $trip_id;
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

// Fetch Trip details
try {
    $stmt = $pdo->prepare("
        SELECT 
            t.id AS trip_id,
            t.departure_time,
            t.arrival_time,
            t.base_fare,
            b.id AS bus_id,
            b.bus_name,
            b.bus_type,
            b.seat_layout_type,
            b.total_seats,
            r.id AS route_id,
            r.source,
            r.destination,
            r.pickup_points,
            r.drop_points
        FROM trips t
        JOIN buses b ON t.bus_id = b.id
        JOIN routes r ON t.route_id = r.id
        WHERE t.id = :trip_id AND t.status = 'ACTIVE'
        LIMIT 1
    ");
    $stmt->execute([':trip_id' => $trip_id]);
    $trip = $stmt->fetch();
    
    if (!$trip) {
        die("Trip voyage scheduled operations not found.");
    }
    
    // Fetch Boarding and Dropping points
    $boarding_stations = $pdo->prepare("SELECT point_name AS name, departure_time AS time FROM boarding_points WHERE route_id = ?");
    $boarding_stations->execute([$trip['route_id']]);
    $boardings = $boarding_stations->fetchAll();
    
    if (empty($boardings)) {
        // Fallback to route json points
        $boardings = json_decode($trip['pickup_points'], true) ?? [];
    }

    $dropping_stations = $pdo->prepare("SELECT point_name AS name, arrival_time AS time FROM dropping_points WHERE route_id = ?");
    $dropping_stations->execute([$trip['route_id']]);
    $droppings = $dropping_stations->fetchAll();

    if (empty($droppings)) {
        // Fallback to route json points
        $droppings = json_decode($trip['drop_points'], true) ?? [];
    }

    // Fetch custom seating layout grid dimensions
    $layout_stmt = $pdo->prepare("SELECT rows_count, cols_count, layout_type FROM bus_layouts WHERE bus_id = ? LIMIT 1");
    $layout_stmt->execute([$trip['bus_id']]);
    $layout = $layout_stmt->fetch();

    $rows_count = $layout ? intval($layout['rows_count']) : ($trip['seat_layout_type'] === '2x1_sleeper' ? 10 : 10);
    $cols_count = $layout ? intval($layout['cols_count']) : ($trip['seat_layout_type'] === '2x1_sleeper' ? 3 : 5);

    // --- Release THIS session's own prior temp_locked seats on fresh page load ---
    // Prevents seats appearing permanently "selected" if user navigated away.
    $session_id_current = session_id();
    $pdo->prepare("
        UPDATE trip_seats
        SET status = 'available', locked_at = NULL, locked_by_session = NULL
        WHERE trip_id = ? AND status = 'temp_locked' AND locked_by_session = ?
    ")->execute([$trip_id, $session_id_current]);

    // --- Auto-release expired temp_locked seats (7-minute window) ---
    $seven_mins_ago = date('Y-m-d H:i:s', strtotime('-7 minutes'));
    $pdo->prepare("
        UPDATE trip_seats
        SET status = 'available', locked_at = NULL, locked_by_session = NULL
        WHERE trip_id = ? AND status = 'temp_locked' AND locked_at <= ?
    ")->execute([$trip_id, $seven_mins_ago]);


    // Fetch seat statuses & pricing overrides
    $seats_stmt = $pdo->prepare("
        SELECT 
            s.seat_number, s.row_pos, s.col_pos, s.seat_type, s.is_active,
            ts.status AS seat_status, ts.locked_at, ts.locked_by_session, ts.hold_expires_at,
            sp.base_price AS trip_base, sp.current_price AS trip_cur, sp.offer_price AS trip_off
        FROM bus_seats s
        LEFT JOIN trip_seats ts ON s.seat_number = ts.seat_number AND ts.trip_id = ?
        LEFT JOIN seat_pricing sp ON s.seat_number = sp.seat_number AND sp.trip_id = ?
        WHERE s.bus_id = ? AND s.is_active = 1
    ");
    $seats_stmt->execute([$trip_id, $trip_id, $trip['bus_id']]);
    $db_seats = $seats_stmt->fetchAll();

    // Map dynamic seats
    $seats_lookup = [];
    $now = date('Y-m-d H:i:s');
    $seven_mins_ago = date('Y-m-d H:i:s', strtotime('-7 minutes'));
    $session_id = session_id();

    // Fill defaults if layout was never configured
    if (empty($db_seats)) {
        if ($trip['seat_layout_type'] === '2x1_sleeper') {
            for ($i = 1; $i <= 15; $i++) {
                $db_seats[] = ['seat_number' => "L$i", 'row_pos' => intval(($i-1)/2), 'col_pos' => ($i-1)%2, 'seat_type' => 'Lower Sleeper', 'is_active' => 1];
                $db_seats[] = ['seat_number' => "U$i", 'row_pos' => intval(($i-1)/2), 'col_pos' => ($i-1)%2, 'seat_type' => 'Upper Sleeper', 'is_active' => 1];
            }
        } else {
            for ($i = 1; $i <= 40; $i++) {
                $db_seats[] = ['seat_number' => strval($i), 'row_pos' => intval(($i-1)/4), 'col_pos' => ($i-1)%4 + (($i-1)%4 >= 2 ? 1 : 0), 'seat_type' => 'Normal', 'is_active' => 1];
            }
        }
    }

    // Load booked genders to check female protection rules
    $gender_stmt = $pdo->prepare("
        SELECT bs.seat_number, bs.passenger_gender 
        FROM booking_seats bs
        JOIN bookings b ON bs.booking_id = b.id
        WHERE b.trip_id = ? AND b.status = 'active'
    ");
    $gender_stmt->execute([$trip_id]);
    $booked_genders = $gender_stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];

    // Parse statuses
    foreach ($db_seats as $s) {
        $seatNum = $s['seat_number'];
        $status = !empty($s['seat_status']) ? $s['seat_status'] : 'available';
        if (is_seat_blocked($pdo, $trip_id, $seatNum)) {
            $status = 'blocked';
        }

        // Check locks expiration (7 minutes)
        if ($status === 'temp_locked') {
            if (empty($s['locked_at']) || $s['locked_at'] <= $seven_mins_ago) {
                $status = 'available';
            } elseif ($s['locked_by_session'] === $session_id) {
                $status = 'selected';
            }
        }
        // Check holds expiration
        if ($status === 'hold' && !empty($s['hold_expires_at']) && $s['hold_expires_at'] < $now) {
            $status = 'available';
        }

        // Apply booked gender coloring overrides
        if ($status === 'booked' && ($booked_genders[$seatNum] ?? '') === 'Female') {
            $status = 'female_booked';
        }

        // Map pricing
        $base = get_actual_seat_price($pdo, $trip_id, $seatNum, $trip['base_fare']);

        $seats_lookup[$seatNum] = [
            'number' => $seatNum,
            'row' => intval($s['row_pos']),
            'col' => intval($s['col_pos']),
            'type' => $s['seat_type'] ?? 'Normal',
            'status' => $status,
            'price' => $base
        ];
    }

    // Filter out seats that are physically overlapped by a sleeper berth starting in the row above them
    $overlapped_seats = [];
    foreach ($seats_lookup as $sNum => $sInfo) {
        $isSleeper = (strpos($sInfo['type'], 'Sleeper') !== false);
        if ($isSleeper) {
            $target_row = $sInfo['row'] + 1;
            $target_col = $sInfo['col'];
            foreach ($seats_lookup as $otherNum => $otherInfo) {
                if ($otherInfo['row'] === $target_row && $otherInfo['col'] === $target_col && $otherNum !== $sNum) {
                    $overlapped_seats[] = $otherNum;
                }
            }
        }
    }
    foreach ($overlapped_seats as $oNum) {
        unset($seats_lookup[$oNum]);
    }

    // Apply adjacent Female Protection rules
    foreach ($seats_lookup as $seatNum => $sInfo) {
        if ($sInfo['status'] === 'female_booked') {
            // Find adjacent column
            $adj_col = -1;
            if ($sInfo['col'] === 0) $adj_col = 1;
            elseif ($sInfo['col'] === 1) $adj_col = 0;
            elseif ($sInfo['col'] === 3) $adj_col = 4;
            elseif ($sInfo['col'] === 4) $adj_col = 3;

            if ($adj_col !== -1) {
                // Find adjacent seat in lookup
                foreach ($seats_lookup as $otherNum => $otherInfo) {
                    if ($otherInfo['row'] === $sInfo['row'] && $otherInfo['col'] === $adj_col && $otherInfo['status'] === 'available') {
                        $seats_lookup[$otherNum]['status'] = 'female_protected';
                    }
                }
            }
        }
    }

    $has_upper_seats = false;
    foreach ($seats_lookup as $sInfo) {
        if (strpos(strtolower($sInfo['type']), 'upper') !== false) {
            $has_upper_seats = true;
            break;
        }
    }

} catch (PDOException $e) {
    die("Error fetching voyage mapping: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Premium experience header card -->
<?php
$exp_stmt = $pdo->prepare("
    SELECT 
        COALESCE((SELECT is_verified FROM bus_verifications WHERE bus_id = b.id), 0) AS is_verified,
        COALESCE((SELECT AVG(rating) FROM bus_reviews WHERE bus_id = b.id AND status = 'approved'), 0.00) AS avg_rating,
        COALESCE((SELECT COUNT(*) FROM bus_reviews WHERE bus_id = b.id AND status = 'approved'), 0) AS total_reviews,
        (SELECT file_path FROM bus_media WHERE bus_id = b.id AND media_type = 'image' ORDER BY sort_order ASC LIMIT 1) AS thumbnail
    FROM buses b
    WHERE b.id = ?
    LIMIT 1
");
$exp_stmt->execute([$trip['bus_id']]);
$exp_info = $exp_stmt->fetch() ?: [];
?>

<div class="row g-4">
    <div class="col-12">
        <div class="glass-card p-4 d-flex flex-wrap align-items-center gap-4 text-white mb-2" style="border-radius: 20px;">
            <div class="position-relative overflow-hidden rounded-3 bg-dark" style="width: 140px; height: 90px; flex-shrink: 0;">
                <?php if (!empty($exp_info['thumbnail'])): ?>
                    <img src="<?= BASE_URL ?>/<?= htmlspecialchars($exp_info['thumbnail']) ?>" class="w-100 h-100 object-fit-cover" alt="Bus Image">
                <?php else: ?>
                    <div class="w-100 h-100 d-flex flex-column justify-content-center align-items-center text-center p-2 text-secondary bg-black bg-opacity-25" style="font-size:0.75rem; color:var(--text-secondary) !important;">
                        <i class="fa-solid fa-bus-simple mb-1 fs-5 text-secondary"></i>
                        <span>No photos</span>
                    </div>
                <?php endif; ?>
            </div>

            <div class="flex-grow-1">
                <div class="d-flex align-items-center gap-2 mb-1">
                    <h4 class="fw-bold text-white mb-0"><?= htmlspecialchars($trip['bus_name']) ?></h4>
                    <?php if (!empty($exp_info['is_verified'])): ?>
                        <span class="text-success small fw-semibold badge bg-success bg-opacity-10 border border-success border-opacity-20 rounded-pill px-2 py-1"><i class="fa-solid fa-circle-check me-1"></i>Verified</span>
                    <?php endif; ?>
                </div>
                
                <div class="d-flex flex-wrap gap-3 align-items-center mb-2">
                    <span class="badge bg-secondary text-uppercase" style="font-size:0.75rem;"><?= htmlspecialchars($trip['bus_type']) ?></span>
                    <?php if (!empty($exp_info['total_reviews'])): ?>
                        <span class="text-warning fw-semibold small"><i class="fa-solid fa-star me-1 text-warning"></i><?= number_format($exp_info['avg_rating'], 1) ?> (<?= $exp_info['total_reviews'] ?> reviews)</span>
                    <?php endif; ?>
                </div>

                <div class="text-secondary small font-monospace"><i class="fa-solid fa-route me-1"></i><?= htmlspecialchars($trip['source']) ?> to <?= htmlspecialchars($trip['destination']) ?> | <?= date('d M Y, H:i', strtotime($trip['departure_time'])) ?></div>
            </div>

            <div class="d-flex flex-column gap-2">
                <button type="button" class="btn btn-secondary-glass btn-sm text-white border-0" onclick="openBusDetails(<?= $trip['bus_id'] ?>, 'photos')"><i class="fa-solid fa-images me-2 text-warning"></i>View Photos</button>
                <button type="button" class="btn btn-secondary-glass btn-sm text-white border-0" onclick="openBusDetails(<?= $trip['bus_id'] ?>, 'amenities')"><i class="fa-solid fa-gift me-2 text-indigo"></i>View Amenities</button>
                <button type="button" class="btn btn-secondary-glass btn-sm text-white border-0" onclick="openBusDetails(<?= $trip['bus_id'] ?>, 'policies')"><i class="fa-solid fa-shield-halved me-2 text-danger"></i>View Policies</button>
            </div>
        </div>
    </div>

    <!-- Seating layout selection -->
    <div class="col-lg-7">
        <div class="glass-card p-4">
            <div class="d-flex align-items-center justify-content-between mb-4 border-bottom border-secondary pb-3 flex-wrap gap-2">
                <div>
                    <h4 class="fw-bold text-white mb-1"><i class="fa-solid fa-chair text-indigo me-2"></i><?= __('select_seats', 'Select Seats') ?></h4>
                    <span class="text-secondary small"><?= __('seat_tap_desc', 'Tap on seats to choose. Orange/Black/Red seats are unavailable.') ?></span>
                </div>
            </div>

            <!-- Seat status legend -->
            <div class="d-flex gap-3 mb-4 justify-content-center flex-wrap small">
                <div class="legend-item"><span class="legend-dot" style="background:#FFFDF8 !important; border:1px solid #D4C9B5 !important;"></span><span class="text-secondary"><?= __('available', 'Available') ?></span></div>
                <div class="legend-item"><span class="legend-dot" style="background:#0F5132 !important; border:1px solid #0a3d22 !important;"></span><span class="text-secondary"><?= __('selected', 'Selected') ?></span></div>
                <div class="legend-item"><span class="legend-dot" style="background:#9CA3AF !important; border:1px solid #6B7280 !important;"></span><span class="text-secondary"><?= __('booked', 'Booked') ?></span></div>
                <div class="legend-item"><span class="legend-dot" style="background:#EAB308 !important; border:1px solid #B45309 !important;"></span><span class="text-secondary"><?= __('hold', 'Hold') ?></span></div>
                <div class="legend-item"><span class="legend-dot" style="background:#F472B6 !important; border:1px solid #EC4899 !important;"></span><span class="text-secondary"><?= __('female_status_legend', 'Female (Booked/Protected)') ?></span></div>
            </div>

            <!-- Seating Grid -->
            <div class="text-center py-4">
                <?php if ($trip['seat_layout_type'] === '2x1_sleeper' && !$layout): ?>
                    <!-- Legacy Sleeper Layout Tabs Fallback -->
                    <ul class="nav nav-pills justify-content-center mb-4 gap-2" role="tablist">
                        <li class="nav-item">
                            <button class="nav-link btn-secondary-glass active px-4 py-2" id="low-tab" data-bs-toggle="pill" data-bs-target="#low-berth" type="button" role="tab"><?= __('lower_deck', 'Lower Berth') ?></button>
                        </li>
                        <li class="nav-item">
                            <button class="nav-link btn-secondary-glass px-4 py-2" id="up-tab" data-bs-toggle="pill" data-bs-target="#up-berth" type="button" role="tab"><?= __('upper_deck', 'Upper Berth') ?></button>
                        </li>
                    </ul>

                    <div class="tab-content">
                        <div class="tab-pane fade show active" id="low-berth" role="tabpanel">
                            <div class="seat-map-container shadow-lg">
                                <div class="seat-grid-sleeper">
                                    <?php 
                                    foreach ($seats_lookup as $num => $s) {
                                        if (strpos($num, 'L') === 0) {
                                            echo '<div class="seat sleeper-berth ' . $s['status'] . '" data-seat="' . $num . '" data-price="' . $s['price'] . '">' .
                                                 '<span>' . $num . '</span>' .
                                                 '<span class="price-lbl">₹' . number_format($s['price'], 0) . '</span>' .
                                                 '</div>';
                                            $i = intval(substr($num, 1));
                                            if ($i % 2 === 0 && ($i + 1) % 3 === 0) {
                                                echo '<div class="seat-walkway"></div>';
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="up-berth" role="tabpanel">
                            <div class="seat-map-container shadow-lg">
                                <div class="seat-grid-sleeper">
                                    <?php 
                                    foreach ($seats_lookup as $num => $s) {
                                        if (strpos($num, 'U') === 0) {
                                            echo '<div class="seat sleeper-berth ' . $s['status'] . '" data-seat="' . $num . '" data-price="' . $s['price'] . '">' .
                                                 '<span>' . $num . '</span>' .
                                                 '<span class="price-lbl">₹' . number_format($s['price'], 0) . '</span>' .
                                                 '</div>';
                                            $i = intval(substr($num, 1));
                                            if ($i % 2 === 0 && ($i + 1) % 3 === 0) {
                                                echo '<div class="seat-walkway"></div>';
                                            }
                                        }
                                    }
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Custom Grid Visual Layout (Seater, Mixed or configured Layouts) -->
                    <?php if ($has_upper_seats): ?>
                        <ul class="nav nav-pills justify-content-center mb-4 gap-2" role="tablist">
                            <li class="nav-item">
                                <button class="nav-link btn-secondary-glass active px-4 py-2" id="low-deck-tab" data-bs-toggle="pill" data-bs-target="#low-deck-berth" type="button" role="tab"><?= __('lower_deck', 'Lower Deck') ?></button>
                            </li>
                            <li class="nav-item">
                                <button class="nav-link btn-secondary-glass px-4 py-2" id="up-deck-tab" data-bs-toggle="pill" data-bs-target="#up-deck-berth" type="button" role="tab"><?= __('upper_deck', 'Upper Deck') ?></button>
                            </li>
                        </ul>

                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="low-deck-berth" role="tabpanel">
                                <div class="seat-map-container shadow-lg overflow-auto" style="max-width: 500px; margin:0 auto;">
                                    <div class="d-flex justify-content-between mb-4 pb-2 border-bottom border-secondary border-opacity-20">
                                        <span class="text-secondary small fw-semibold font-monospace">FRONT / ENGINE</span>
                                        <span class="text-secondary small"><i class="fa-solid fa-steering-wheel"></i> <?= __('driver', 'DRIVER') ?></span>
                                    </div>
                                    <div style="display: inline-grid; gap: 12px; grid-template-rows: repeat(<?= $rows_count ?>, 60px); grid-template-columns: repeat(<?= $cols_count ?>, 60px); position: relative; width: 100%;">
                                        <?php 
                                        for ($r = 0; $r < $rows_count; $r++) {
                                            for ($c = 0; $c < $cols_count; $c++) {
                                                $seat = null;
                                                foreach ($seats_lookup as $sNum => $sInfo) {
                                                    if ($sInfo['row'] === $r && $sInfo['col'] === $c) {
                                                        $seat = $sInfo;
                                                        break;
                                                    }
                                                }
                                                if ($seat && strpos(strtolower($seat['type']), 'upper') === false) {
                                                    $isSleeper = (strpos($seat['type'], 'Sleeper') !== false);
                                                    $sleeperClass = $isSleeper ? ' sleeper-berth' : '';
                                                    $rowSpan = $isSleeper ? 2 : 1;
                                                    $typeClass = ' type-' . strtolower(str_replace(' ', '-', $seat['type']));
                                                    echo '<div class="seat' . $sleeperClass . ' ' . $typeClass . ' ' . $seat['status'] . '" ' .
                                                         'style="grid-row: ' . ($r + 1) . ' / span ' . $rowSpan . '; grid-column: ' . ($c + 1) . ';" ' .
                                                         'data-seat="' . $seat['number'] . '" data-price="' . $seat['price'] . '">' . 
                                                         '<span>' . $seat['number'] . '</span>' .
                                                         '<span class="price-lbl">₹' . number_format($seat['price'], 0) . '</span>' .
                                                         '</div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="tab-pane fade" id="up-deck-berth" role="tabpanel">
                                <div class="seat-map-container shadow-lg overflow-auto" style="max-width: 500px; margin:0 auto;">
                                    <div class="d-flex justify-content-between mb-4 pb-2 border-bottom border-secondary border-opacity-20">
                                        <span class="text-secondary small fw-semibold font-monospace">FRONT / ENGINE</span>
                                        <span class="text-secondary small"><i class="fa-solid fa-steering-wheel"></i> <?= __('driver', 'DRIVER') ?></span>
                                    </div>
                                    <div style="display: inline-grid; gap: 12px; grid-template-rows: repeat(<?= $rows_count ?>, 60px); grid-template-columns: repeat(<?= $cols_count ?>, 60px); position: relative; width: 100%;">
                                        <?php 
                                        for ($r = 0; $r < $rows_count; $r++) {
                                            for ($c = 0; $c < $cols_count; $c++) {
                                                $seat = null;
                                                foreach ($seats_lookup as $sNum => $sInfo) {
                                                    if ($sInfo['row'] === $r && $sInfo['col'] === $c) {
                                                        $seat = $sInfo;
                                                        break;
                                                    }
                                                }
                                                if ($seat && strpos(strtolower($seat['type']), 'upper') !== false) {
                                                    $isSleeper = (strpos($seat['type'], 'Sleeper') !== false);
                                                    $sleeperClass = $isSleeper ? ' sleeper-berth' : '';
                                                    $rowSpan = $isSleeper ? 2 : 1;
                                                    $typeClass = ' type-' . strtolower(str_replace(' ', '-', $seat['type']));
                                                    echo '<div class="seat' . $sleeperClass . ' ' . $typeClass . ' ' . $seat['status'] . '" ' .
                                                         'style="grid-row: ' . ($r + 1) . ' / span ' . $rowSpan . '; grid-column: ' . ($c + 1) . ';" ' .
                                                         'data-seat="' . $seat['number'] . '" data-price="' . $seat['price'] . '">' . 
                                                         '<span>' . $seat['number'] . '</span>' .
                                                         '<span class="price-lbl">₹' . number_format($seat['price'], 0) . '</span>' .
                                                         '</div>';
                                                }
                                            }
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Single Lower Deck (No Upper Seats) -->
                        <div class="seat-map-container shadow-lg overflow-auto" style="max-width: 500px; margin:0 auto;">
                            <div class="d-flex justify-content-between mb-4 pb-2 border-bottom border-secondary border-opacity-20">
                                <span class="text-secondary small fw-semibold font-monospace"><?= __('front_engine', 'FRONT / ENGINE') ?></span>
                                <span class="text-secondary small"><i class="fa-solid fa-steering-wheel"></i> <?= __('driver', 'DRIVER') ?></span>
                            </div>
                            
                            <div style="display: inline-grid; gap: 12px; grid-template-rows: repeat(<?= $rows_count ?>, 60px); grid-template-columns: repeat(<?= $cols_count ?>, 60px); position: relative; width: 100%;">
                                <?php 
                                for ($r = 0; $r < $rows_count; $r++) {
                                    for ($c = 0; $c < $cols_count; $c++) {
                                        // Find mapped seat
                                        $seat = null;
                                        foreach ($seats_lookup as $sNum => $sInfo) {
                                            if ($sInfo['row'] === $r && $sInfo['col'] === $c) {
                                                $seat = $sInfo;
                                                break;
                                            }
                                        }
                                        
                                        if ($seat) {
                                            $isSleeper = (strpos($seat['type'], 'Sleeper') !== false);
                                            $sleeperClass = $isSleeper ? ' sleeper-berth' : '';
                                            $rowSpan = $isSleeper ? 2 : 1;
                                            
                                            $typeClass = ' type-' . strtolower(str_replace(' ', '-', $seat['type']));
                                            echo '<div class="seat' . $sleeperClass . ' ' . $typeClass . ' ' . $seat['status'] . '" ' .
                                                 'style="grid-row: ' . ($r + 1) . ' / span ' . $rowSpan . '; grid-column: ' . ($c + 1) . ';" ' .
                                                 'data-seat="' . $seat['number'] . '" data-price="' . $seat['price'] . '">' . 
                                                 '<span>' . $seat['number'] . '</span>' .
                                                 '<span class="price-lbl">₹' . number_format($seat['price'], 0) . '</span>' .
                                                 '</div>';
                                        }
                                    }
                                }
                                ?>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Right Summary Panel -->
    <div class="col-lg-5">
        <div class="glass-card p-4 h-100">
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-file-invoice-dollar text-indigo me-2"></i><?= __('voyage_details', 'Voyage Details') ?></h4>
            
            <div class="mb-3">
                <span class="text-secondary small d-block"><?= __('operator_fleet', 'Operator fleet / Brand') ?></span>
                <span class="text-white fw-bold fs-5"><?= htmlspecialchars($trip['bus_name']) ?></span>
                <span class="badge bg-indigo ms-1 text-uppercase" style="font-size:0.7rem;"><?= htmlspecialchars($trip['bus_type']) ?></span>
            </div>

            <div class="row mb-4 border-bottom border-secondary border-opacity-30 pb-3">
                <div class="col-6">
                    <span class="text-secondary small d-block"><?= __('voyage_path', 'Voyage Path') ?></span>
                    <span class="text-white fw-semibold"><?= htmlspecialchars($trip['source']) ?> <?= __('to', 'to') ?> <?= htmlspecialchars($trip['destination']) ?></span>
                </div>
                <div class="col-6 text-end">
                    <span class="text-secondary small d-block"><?= __('scheduled_date', 'Scheduled Date') ?></span>
                    <span class="text-white fw-semibold"><?= date('d M, H:i', strtotime($trip['departure_time'])) ?></span>
                </div>
            </div>

            <!-- Customer Experience Badges & Current Fare Info -->
            <?php 
                $pricing = calculate_dynamic_pricing($pdo, $trip_id, $trip['base_fare']);
                $total_seats = intval($trip['total_seats']);
                $booked_stmt = $pdo->prepare("SELECT COUNT(*) FROM trip_seats WHERE trip_id = ? AND status = 'booked'");
                $booked_stmt->execute([$trip_id]);
                $booked_seats = intval($booked_stmt->fetchColumn());
                $remaining_seats = max(0, $total_seats - $booked_seats);
                
                $is_dynamic_active = ($pricing['occupancy_increase_pct'] > 0 || $pricing['time_increase_pct'] > 0);
            ?>

            <?php 
                $show_dynamic_details = (
                    (isset($_SESSION['user_role']) && ($_SESSION['user_role'] === 'admin' || $_SESSION['user_role'] === 'super_admin')) ||
                    (isset($_SESSION['role']) && ($_SESSION['role'] === 'admin' || $_SESSION['role'] === 'super_admin'))
                );
            ?>

            <div class="mb-4 p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10">
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php if ($is_dynamic_active && $show_dynamic_details): ?>
                        <span class="badge bg-warning text-dark d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-chart-line"></i> <?= __('dynamic_pricing_active', 'Dynamic Pricing Active') ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($pricing['occupancy_percent'] > 70): ?>
                        <span class="badge bg-danger text-white d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-fire-flame-curved"></i> <?= __('high_demand_route', 'High Demand Route') ?>
                        </span>
                    <?php endif; ?>

                    <?php if ($remaining_seats <= 5): ?>
                        <span class="badge bg-info text-dark d-flex align-items-center gap-1" style="font-size: 0.75rem;">
                            <i class="fa-solid fa-triangle-exclamation"></i> <?= __('only', 'Only') ?> <?= $remaining_seats ?> <?= __('seats_left', 'seats left') ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <div class="small text-secondary">
                    <?php if ($pricing['occupancy_percent'] > 70): ?>
                        <div class="d-flex align-items-center gap-2 mb-2 text-warning" style="font-size: 0.8rem;">
                            <i class="fa-solid fa-circle-info"></i>
                            <span><?= __('fare_increase_warning', 'Fare may increase as seats fill. Book now to lock this price.') ?></span>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($show_dynamic_details): ?>
                        <div class="d-flex justify-content-between pt-2 border-top border-secondary border-opacity-10">
                            <span><?= __('base_fare', 'Base Fare') ?>:</span>
                            <span class="text-white">₹<?= number_format($trip['base_fare'], 2) ?></span>
                        </div>
                        <?php if ($pricing['occupancy_increase_pct'] > 0): ?>
                            <div class="d-flex justify-content-between text-warning">
                                <span><?= __('high_occupancy', 'High Occupancy') ?> (+<?= $pricing['occupancy_increase_pct'] ?>%):</span>
                                <span>+₹<?= number_format($pricing['occupancy_adjustment'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                        <?php if ($pricing['time_increase_pct'] > 0): ?>
                            <div class="d-flex justify-content-between text-warning">
                                <span><?= __('last_minute_departure', 'Last-minute Departure') ?> (+<?= $pricing['time_increase_pct'] ?>%):</span>
                                <span>+₹<?= number_format($pricing['time_adjustment'], 2) ?></span>
                            </div>
                        <?php endif; ?>
                    <?php endif; ?>
                    
                    <div class="d-flex justify-content-between fw-bold text-white fs-6 mt-1 pt-1 <?= $show_dynamic_details ? 'border-top border-secondary border-opacity-10' : '' ?>">
                        <span><?= __('current_fare', 'Current Fare') ?>:</span>
                        <span class="text-success font-monospace">₹<?= number_format($pricing['final_price'], 2) ?></span>
                    </div>
                </div>
            </div>

            <!-- Proceed Form -->
            <form action="<?= BASE_URL ?>/checkout.php" method="POST" id="seatProceedForm">
                <input type="hidden" name="csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="trip_id" value="<?= $trip_id ?>">
                <input type="hidden" name="selected_seats" id="hidden_selected_seats" value="">

                <!-- Boarding Station -->
                <div class="mb-3">
                    <label class="form-label text-secondary small fw-semibold"><?= __('select_boarding_station', 'Select Boarding Station') ?></label>
                    <select name="boarding_point" id="boarding_point" class="form-select form-control-swift" required>
                        <option value=""><?= __('choose_boarding', 'Choose Boarding...') ?></option>
                        <?php foreach ($boardings as $bs): 
                            $has_time = !empty($bs['time']) && $bs['time'] !== '00:00:00' && $bs['time'] !== '00:00';
                            $label = htmlspecialchars($bs['name']);
                            if ($has_time) $label .= ' (' . date('H:i', strtotime($bs['time'])) . ')';
                        ?>
                            <option value="<?= htmlspecialchars($bs['name']) ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Dropping Station -->
                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold"><?= __('select_dropping_station', 'Select Dropping Station') ?></label>
                    <select name="dropping_point" id="dropping_point" class="form-select form-control-swift" required>
                        <option value=""><?= __('choose_dropping', 'Choose Dropping...') ?></option>
                        <?php foreach ($droppings as $ds): 
                            $has_time = !empty($ds['time']) && $ds['time'] !== '00:00:00' && $ds['time'] !== '00:00';
                            $label = htmlspecialchars($ds['name']);
                            if ($has_time) $label .= ' (' . date('H:i', strtotime($ds['time'])) . ')';
                        ?>
                            <option value="<?= htmlspecialchars($ds['name']) ?>"><?= $label ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Selected seats invoice -->
                <div class="mb-4">
                    <h6 class="text-indigo fw-bold small text-uppercase mb-2"><?= __('selected_seats', 'Selected Seats') ?></h6>
                    <div id="no-seat-warning" class="text-secondary small p-3 rounded bg-dark bg-opacity-20 border border-secondary border-opacity-10 text-center">
                        <?= __('tap_available_seats', 'Tap available seats in the map layout.') ?>
                    </div>
                    <ul class="list-group list-group-flush mb-4" id="selected-seats-list" style="display: none; background:transparent;"></ul>
                </div>

                <!-- Promo Code Section -->
                <div class="mb-4" id="promo-container" style="display: none;">
                    <label class="form-label text-secondary small fw-semibold"><?= __('promo_code', 'Promo Code') ?></label>
                    <div class="input-group">
                        <input type="text" id="promo_code_input" class="form-control form-control-swift" placeholder="e.g. SAVE10">
                        <button class="btn btn-outline-secondary btn-secondary-glass text-white" type="button" id="btnApplyPromo"><?= __('apply', 'Apply') ?></button>
                    </div>
                    <div id="promo-message" class="small mt-1 text-success" style="display: none;"></div>
                </div>
                
                <input type="hidden" name="applied_promo" id="hidden_promo_code" value="">
                <input type="hidden" name="discount_amount" id="hidden_discount_amount" value="0.00">

                <div class="p-3 rounded-4 bg-dark bg-opacity-30 border border-secondary border-opacity-20 mb-4" id="invoice-block" style="display: none;">
                    <div class="d-flex justify-content-between small text-secondary mb-2">
                        <span><?= __('base_ticket_fare', 'Ticket Fare') ?></span>
                        <span id="invoice-base-fare">₹0.00</span>
                    </div>
                    <div class="d-flex justify-content-between small text-secondary mb-2" id="invoice-discount-row" style="display: none !important;">
                        <span><?= __('discount_applied', 'Discount Applied') ?></span>
                        <span class="text-success" id="invoice-discount-val">-₹0.00</span>
                    </div>
                    <div class="d-flex justify-content-between small text-secondary mb-2">
                        <span><?= __('gst', 'GST') ?> (<span id="invoice-gst-rate">0</span>%)</span>
                        <span id="invoice-gst-amount">₹0.00</span>
                    </div>
                    <div class="d-flex justify-content-between small text-secondary mb-2" id="invoice-convenience-row">
                        <span><?= __('convenience_fee', 'Convenience Fee') ?></span>
                        <span id="invoice-convenience-fee">₹0.00</span>
                    </div>
                    <div class="d-flex justify-content-between text-white fw-bold fs-5 pt-2 border-top border-secondary border-opacity-40">
                        <span><?= __('grand_total', 'Grand Total') ?></span>
                        <span class="text-indigo" id="invoice-total">₹0.00</span>
                    </div>
                </div>

                <button type="submit" id="btnProceedCheckout" class="btn btn-primary-gradient w-100 py-3 text-uppercase fw-bold disabled">
                    <i class="fa-solid fa-circle-arrow-right me-2"></i><?= __('proceed_checkout', 'Proceed to Checkout') ?>
                </button>
            </form>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    var selectedSeats = [];
    var maxSeats = 6;
    var csrfToken = '<?= get_csrf_token() ?>';
    var tripId = <?= $trip_id ?>;
    var gstRate = <?= get_gst_rate() ?>;
    var convenienceFee = <?= (isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'agent') ? 0 : 20 ?>;

    // Load initial selection from backend if page was reloaded
    $('.seat.selected').each(function() {
        var num = $(this).data('seat');
        var price = parseFloat($(this).data('price'));
        selectedSeats.push({ number: num, price: price });
    });
    updateInvoice();

    // Click seat handler
    $('.seat').click(function() {
        var cell = $(this);
        var seatNum = cell.data('seat');
        var seatPrice = parseFloat(cell.data('price'));

        if (cell.hasClass('booked') || cell.hasClass('hold') || cell.hasClass('blocked') || cell.hasClass('reserved') || cell.hasClass('female_booked') || cell.hasClass('temp_locked')) {
            return;
        }

        if (cell.hasClass('selected')) {
            // Unlock/Deselect
            $.ajax({
                url: '<?= BASE_URL ?>/ajax/lock_seats.php',
                type: 'POST',
                data: { trip_id: tripId, seat_number: seatNum, action: 'unlock', csrf_token: csrfToken },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        cell.removeClass('selected');
                        selectedSeats = selectedSeats.filter(item => item.number !== seatNum);
                        updateInvoice();
                    }
                }
            });
        } else {
            // Lock/Select
            if (selectedSeats.length >= maxSeats) {
                alert("You can select up to " + maxSeats + " seats.");
                return;
            }

            $.ajax({
                url: '<?= BASE_URL ?>/ajax/lock_seats.php',
                type: 'POST',
                data: { trip_id: tripId, seat_number: seatNum, action: 'lock', csrf_token: csrfToken },
                dataType: 'json',
                success: function(res) {
                    if (res.success) {
                        cell.addClass('selected');
                        selectedSeats.push({ number: seatNum, price: seatPrice });
                        updateInvoice();
                    } else {
                        alert(res.message);
                    }
                },
                error: function() {
                    alert("Server failed to request temporary seat lock.");
                }
            });
        }
    });

    function resetPromo() {
        $('#hidden_promo_code').val('');
        $('#hidden_discount_amount').val('0.00');
        $('#promo_code_input').val('');
        $('#promo-message').hide().text('');
        $('#invoice-discount-row').attr('style', 'display: none !important;');
    }

    $('#btnApplyPromo').click(function() {
        var code = $('#promo_code_input').val().trim();
        var subtotal = 0;
        selectedSeats.forEach(function(s) { subtotal += s.price; });
        
        if (code === '') {
            resetPromo();
            updateInvoice();
            return;
        }

        $.ajax({
            url: '<?= BASE_URL ?>/ajax/apply_promo.php',
            type: 'POST',
            data: { promo_code: code, subtotal: subtotal, csrf_token: csrfToken },
            dataType: 'json',
            success: function(res) {
                if (res.success) {
                    $('#hidden_promo_code').val(res.promo_code);
                    $('#hidden_discount_amount').val(res.discount.toFixed(2));
                    $('#promo-message').show().removeClass('text-danger').addClass('text-success').text(res.message);
                    
                    $('#invoice-discount-row').removeAttr('style');
                    $('#invoice-discount-val').text('-₹' + res.discount.toFixed(2));
                    updateInvoice();
                } else {
                    $('#promo-message').show().removeClass('text-success').addClass('text-danger').text(res.message);
                    $('#hidden_promo_code').val('');
                    $('#hidden_discount_amount').val('0.00');
                    $('#invoice-discount-row').attr('style', 'display: none !important;');
                    updateInvoice();
                }
            },
            error: function() {
                alert("Failed to communicate with discount server.");
            }
        });
    });

    function updateInvoice() {
        if (selectedSeats.length === 0) {
            $('#no-seat-warning').show();
            $('#selected-seats-list').hide();
            $('#invoice-block').hide();
            $('#promo-container').hide();
            $('#btnProceedCheckout').addClass('disabled');
            $('#hidden_selected_seats').val('');
            resetPromo();
            return;
        }

        $('#no-seat-warning').hide();
        $('#selected-seats-list').show().empty();
        $('#invoice-block').show();
        $('#promo-container').show();
        $('#btnProceedCheckout').removeClass('disabled');

        var totalFare = 0;
        var nums = [];

        selectedSeats.forEach(function(s) {
            totalFare += s.price;
            nums.push(s.number);

            $('#selected-seats-list').append(
                '<li class="list-group-item d-flex justify-content-between align-items-center bg-transparent border-secondary border-opacity-10 text-white py-2 px-0 small">' +
                '<span><i class="fa-solid fa-chair text-indigo me-2"></i>Seat ' + s.number + '</span>' +
                '<span class="fw-semibold">₹' + s.price.toFixed(2) + '</span>' +
                '</li>'
            );
        });

        $('#invoice-base-fare').text('₹' + totalFare.toFixed(2));
        $('#hidden_selected_seats').val(nums.join(','));

        var discount = parseFloat($('#hidden_discount_amount').val()) || 0;
        var taxableBase = totalFare - discount;
        if (taxableBase < 0) taxableBase = 0;
        var gstAmount = taxableBase * (gstRate / 100);
        var grandTotal = taxableBase + gstAmount + convenienceFee;

        $('#invoice-gst-rate').text(gstRate);
        $('#invoice-gst-amount').text('₹' + gstAmount.toFixed(2));
        $('#invoice-convenience-fee').text('₹' + convenienceFee.toFixed(2));
        $('#invoice-total').text('₹' + grandTotal.toFixed(2));
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
