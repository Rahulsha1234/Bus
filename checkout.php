<?php
/**
 * Passenger Details & Payment Checkout Controller
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$page_title = "Checkout";

// Process API Booking / Payment Transaction
if (isset($_GET['action']) && $_GET['action'] === 'process_payment') {
    header('Content-Type: application/json');
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!verify_csrf_token($csrf_token)) {
        echo json_encode(['success' => false, 'message' => 'CSRF validation failed. Please refresh.']);
        exit();
    }

    $trip_id = $_POST['trip_id'] ?? '';
    $seats_str = $_POST['selected_seats'] ?? '';
    $cust_name = trim($_POST['contact_name'] ?? '');
    $cust_email = trim($_POST['contact_email'] ?? '');
    $cust_phone = trim($_POST['contact_phone'] ?? '');
    $boarding_point = trim($_POST['boarding_point'] ?? '');
    $dropping_point = trim($_POST['dropping_point'] ?? '');

    $passenger_names = $_POST['passenger_name'] ?? [];
    $passenger_ages = $_POST['passenger_age'] ?? [];
    $passenger_genders = $_POST['passenger_gender'] ?? [];

    $seats = explode(',', $seats_str);

    if (empty($trip_id) || empty($seats_str) || empty($cust_name) || empty($cust_email) || empty($cust_phone) || empty($boarding_point) || empty($dropping_point)) {
        echo json_encode(['success' => false, 'message' => 'Please fill in all contact, boarding, and dropping information.']);
        exit();
    }

    try {
        // Start transaction
        $pdo->beginTransaction();

        // 1. Double check seat availability (holds or booked)
        $now = date('Y-m-d H:i:s');
        $seat_placeholders = implode(',', array_fill(0, count($seats), '?'));
        
        $chk_stmt = $pdo->prepare("
            SELECT seat_number, status, hold_expires_at, locked_by_session 
            FROM trip_seats 
            WHERE trip_id = ? AND seat_number IN ($seat_placeholders)
        ");
        
        $chk_params = array_merge([$trip_id], $seats);
        $chk_stmt->execute($chk_params);
        $db_seats = $chk_stmt->fetchAll();

        foreach ($db_seats as $s) {
            $status = $s['status'];
            $is_held_by_others = ($status === 'hold' && strtotime($s['hold_expires_at']) >= strtotime($now) && $s['locked_by_session'] !== session_id());
            
            if ($status === 'booked' || $is_held_by_others) {
                $pdo->rollBack();
                echo json_encode(['success' => false, 'message' => "Seat " . $s['seat_number'] . " is no longer available. Please select another seat."]);
                exit();
            }
        }

        // 2. Fetch seats layout and check adjacent seat female rules
        $coords_stmt = $pdo->prepare("
            SELECT s.seat_number, s.row_pos, s.col_pos 
            FROM bus_seats s
            JOIN trips t ON s.bus_id = t.bus_id
            WHERE t.id = ? AND s.is_active = 1
        ");
        $coords_stmt->execute([$trip_id]);
        $coords_db = $coords_stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $seat_coords = [];
        foreach ($coords_db as $c) {
            $seat_coords[$c['seat_number']] = [
                'row' => intval($c['row_pos']),
                'col' => intval($c['col_pos'])
            ];
        }

        // Adjacency mapping helper
        $get_adjacent_seats = function($seatNum) use ($seat_coords) {
            if (!isset($seat_coords[$seatNum])) return [];
            $myRow = $seat_coords[$seatNum]['row'];
            $myCol = $seat_coords[$seatNum]['col'];
            
            $adj_col = -1;
            if ($myCol === 0) $adj_col = 1;
            elseif ($myCol === 1) $adj_col = 0;
            elseif ($myCol === 3) $adj_col = 4;
            elseif ($myCol === 4) $adj_col = 3;
            
            if ($adj_col === -1) return [];
            
            $adj = [];
            foreach ($seat_coords as $sNum => $coord) {
                if ($coord['row'] === $myRow && $coord['col'] === $adj_col) {
                    $adj[] = $sNum;
                }
            }
            return $adj;
        };

        // Current transaction passenger map
        $current_passengers = [];
        foreach ($seats as $index => $seat) {
            $gender = $passenger_genders[$index] ?? 'Male';
            $age = intval($passenger_ages[$index] ?? 25);
            $current_passengers[$seat] = [
                'gender' => $gender,
                'age' => $age
            ];
        }

        // Female safety rules check
        foreach ($current_passengers as $seat => $passenger) {
            if ($passenger['gender'] === 'Male' && $passenger['age'] >= 12) {
                $adj_seats = $get_adjacent_seats($seat);
                foreach ($adj_seats as $adj_seat) {
                    if (isset($current_passengers[$adj_seat])) {
                        // Group Booking Exception applies in same transaction
                        continue;
                    }
                    
                    // Check if adjacent seat is booked by Female in previous transaction
                    $prev_chk = $pdo->prepare("
                        SELECT COUNT(*) 
                        FROM booking_seats bs
                        JOIN bookings b ON bs.booking_id = b.id
                        WHERE b.trip_id = ? AND b.status = 'active' AND bs.seat_number = ? AND bs.passenger_gender = 'Female'
                    ");
                    $prev_chk->execute([$trip_id, $adj_seat]);
                    if (intval($prev_chk->fetchColumn()) > 0) {
                        $pdo->rollBack();
                        echo json_encode(['success' => false, 'message' => "Seat $seat is adjacent to a Female Booked seat ($adj_seat) and cannot be booked by a Male passenger."]);
                        exit();
                    }
                }
            }
        }

        // 3. Fetch base fare and discount details of the trip
        $trip_stmt = $pdo->prepare("
            SELECT t.base_fare, t.admin_id AS trip_admin_id, t.discount_type, t.percentage, t.fixed
            FROM trips t
            WHERE t.id = ? 
            LIMIT 1
        ");
        $trip_stmt->execute([$trip_id]);
        $trip_info = $trip_stmt->fetch();
        $base_fare = floatval($trip_info['base_fare']);
        $trip_admin_id = intval($trip_info['trip_admin_id']);
        
        // Recalculate dynamic fare. Upper berths get +100 premium
        $total_amount = 0;
        $seat_fares = [];
        foreach ($seats as $seat) {
            $fare = $base_fare;
            if (strpos($seat, 'U') === 0) {
                $fare += 100;
            }
            $seat_fares[$seat] = $fare;
            $total_amount += $fare;
        }

        // Determine user details and source
        $current_user_id = $_SESSION['user_id'] ?? null;
        $current_role = $_SESSION['user_role'] ?? 'customer';
        
        $booking_source = 'customer';
        $agent_id = null;
        $admin_id = $trip_admin_id; // Always belongs to trip's admin operator
        $calculated_discount = 0.00;
        
        if ($current_role === 'agent') {
            $booking_source = 'agent';
            $agent_id = $current_user_id;
            
            // Calculate agent partner discount based on bus config
            $discount_val = 0;
            if ($trip_info['discount_type'] === 'percentage') {
                $discount_val = floatval($trip_info['percentage']);
            } elseif ($trip_info['discount_type'] === 'fixed') {
                $discount_val = floatval($trip_info['fixed']);
            }
            
            foreach ($seats as $seat) {
                $seat_fare = $seat_fares[$seat];
                $applied_discount = 0;
                if ($trip_info['discount_type'] === 'percentage') {
                    $applied_discount = ($seat_fare * $discount_val) / 100;
                } elseif ($trip_info['discount_type'] === 'fixed') {
                    $applied_discount = $discount_val;
                }
                $calculated_discount += $applied_discount;
            }
        } elseif ($current_role === 'admin' || $current_role === 'super_admin') {
            $booking_source = 'admin';
        } else {
            // Customer booking: Fetch and re-validate promo code discount server-side
            $applied_promo = trim($_POST['applied_promo'] ?? '');
            if (!empty($applied_promo)) {
                $code = strtoupper($applied_promo);
                if ($code === 'SAVE10') {
                    $calculated_discount = $total_amount * 0.10;
                } elseif ($code === 'FLAT100') {
                    $calculated_discount = 100.00;
                } elseif ($code === 'SUPER50') {
                    $calculated_discount = $total_amount * 0.50;
                } elseif ($code === 'FREE') {
                    $calculated_discount = $total_amount;
                }
                
                if ($calculated_discount > $total_amount) {
                    $calculated_discount = $total_amount;
                }
            }
        }
        
        $calculated_discount = round($calculated_discount, 2);
        $final_total = max(0.00, $total_amount - $calculated_discount);

        // 4. Commission Calculations
        $commission_rate = 2.00; // 2%
        $admin_commission = ($final_total * $commission_rate) / 100;
        $agent_net_earning = $final_total - $admin_commission;

        // 5. Create Booking Entry
        $booking_ref = 'SB' . strtoupper(substr(uniqid(), 7)) . rand(10, 99);
        $customer_id = ($booking_source === 'customer') ? $current_user_id : null;

        $booking_stmt = $pdo->prepare("
            INSERT INTO bookings (
                booking_reference, trip_id, customer_id, admin_id, agent_id, customer_name, customer_email, customer_phone, 
                total_amount, admin_commission, agent_net_earning, payment_status, payment_gateway, transaction_id,
                boarding_point, dropping_point, status, discount_amount, promo_code, booking_source, original_fare, discount_applied, final_fare
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'Razorpay', ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?)
        ");
        
        $mock_tx_id = 'pay_mock_' . bin2hex(random_bytes(8));
        $applied_promo_code = ($booking_source === 'customer' && !empty($applied_promo)) ? $applied_promo : null;
        
        $booking_stmt->execute([
            $booking_ref, $trip_id, $customer_id, $admin_id, $agent_id, $cust_name, $cust_email, $cust_phone,
            $final_total, $admin_commission, $agent_net_earning, $mock_tx_id,
            $boarding_point, $dropping_point, $calculated_discount, $applied_promo_code, $booking_source,
            $total_amount, $calculated_discount, $final_total
        ]);
        $booking_id = $pdo->lastInsertId();

        // 6. Create Passengers/Seats entries and update Seat status
        $passenger_stmt = $pdo->prepare("
            INSERT INTO booking_seats (booking_id, seat_number, passenger_name, passenger_age, passenger_gender, price)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $update_seat_stmt = $pdo->prepare("
            UPDATE trip_seats 
            SET status = 'booked', hold_expires_at = NULL, locked_by_session = NULL, locked_at = NULL 
            WHERE trip_id = ? AND seat_number = ?
        ");

        foreach ($seats as $index => $seat) {
            $name = trim($passenger_names[$index] ?? 'Passenger ' . ($index + 1));
            $age = intval($passenger_ages[$index] ?? 25);
            $gender = $passenger_genders[$index] ?? 'Male';
            $price = $seat_fares[$seat];

            $passenger_stmt->execute([$booking_id, $seat, $name, $age, $gender, $price]);
            $update_seat_stmt->execute([$trip_id, $seat]);
        }

        // Notify Operator Admin
        if ($admin_id) {
            $notif_admin = $pdo->prepare("
                INSERT INTO system_notifications (user_id, user_role, message) 
                VALUES (?, 'admin', ?)
            ");
            $notif_admin->execute([$admin_id, "New Booking $booking_ref has been created."]);
        }

        // Log Activity
        log_activity($pdo, $current_user_id, 'BOOKING_SUCCESS', "Successful booking $booking_ref for trip $trip_id. Total: ₹$final_total. Source: $booking_source. Seats: $seats_str.");

        $pdo->commit();
        echo json_encode(['success' => true, 'booking_ref' => $booking_ref]);
        exit();

    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        echo json_encode(['success' => false, 'message' => 'Database transaction failed: ' . $e->getMessage()]);
        exit();
    }
}

// Render regular form details
$trip_id = $_POST['trip_id'] ?? '';
$selected_seats = $_POST['selected_seats'] ?? '';

if (empty($trip_id) || empty($selected_seats)) {
    header("Location: " . BASE_URL . "/index.php");
    exit();
}

// Convert string to array
$seats = explode(',', $selected_seats);

// Establish database holds for 10 minutes to lock these seats
try {
    $now = date('Y-m-d H:i:s');
    $expire_time = date('Y-m-d H:i:s', strtotime('+10 minutes'));
    $session_id = session_id();

    $pdo->beginTransaction();

    // Verify all seats are available
    $seat_placeholders = implode(',', array_fill(0, count($seats), '?'));
    $chk_stmt = $pdo->prepare("
        SELECT seat_number, status, hold_expires_at, locked_by_session 
        FROM trip_seats 
        WHERE trip_id = ? AND seat_number IN ($seat_placeholders)
    ");
    $chk_params = array_merge([$trip_id], $seats);
    $chk_stmt->execute($chk_params);
    $db_seats = $chk_stmt->fetchAll();

    $seat_lookup = [];
    foreach ($db_seats as $s) {
        $status = $s['status'];
        // Hold is expired
        if ($status === 'hold' && strtotime($s['hold_expires_at']) < strtotime($now)) {
            $status = 'available';
        }
        $seat_lookup[$s['seat_number']] = [
            'status' => $status,
            'session' => $s['locked_by_session']
        ];
    }

    foreach ($seats as $seat) {
        $info = $seat_lookup[$seat] ?? ['status' => 'available', 'session' => ''];
        if ($info['status'] === 'booked' || ($info['status'] === 'hold' && $info['session'] !== $session_id)) {
            $pdo->rollBack();
            die("<div style='font-family:sans-serif; text-align:center; padding: 50px;'>
                <h2>Seat Reservation Expired or Conflicted</h2>
                <p>One or more seats you selected (Seat $seat) are currently locked by another customer. Please choose different seats.</p>
                <a href='" . BASE_URL . "/book.php?trip_id=$trip_id' style='display:inline-block; padding: 10px 20px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:5px;'>Return to Seat Layout</a>
            </div>");
        }
    }

    // Update to Hold status
    $hold_stmt = $pdo->prepare("
        INSERT INTO trip_seats (trip_id, seat_number, status, hold_expires_at, locked_by_session)
        VALUES (:trip_id, :seat, 'hold', :expires, :session)
        ON DUPLICATE KEY UPDATE 
            status = 'hold', 
            hold_expires_at = :expires_update, 
            locked_by_session = :session_update
    ");

    foreach ($seats as $seat) {
        $hold_stmt->execute([
            ':trip_id' => $trip_id,
            ':seat' => $seat,
            ':expires' => $expire_time,
            ':session' => $session_id,
            ':expires_update' => $expire_time,
            ':session_update' => $session_id
        ]);
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    die("Seat allocation failed: " . $e->getMessage());
}

// Fetch Trip Details
$trip_stmt = $pdo->prepare("
    SELECT t.base_fare, b.bus_name, r.source, r.destination 
    FROM trips t 
    JOIN buses b ON t.bus_id = b.id
    JOIN routes r ON t.route_id = r.id
    WHERE t.id = ? 
    LIMIT 1
");
$trip_stmt->execute([$trip_id]);
$trip = $trip_stmt->fetch();

$base_fare = floatval($trip['base_fare']);
$total_fare = 0;
$seat_fares = [];
foreach ($seats as $seat) {
    $fare = $base_fare;
    if (strpos($seat, 'U') === 0) {
        $fare += 100; // Upper berth premium
    }
    $seat_fares[$seat] = $fare;
    $total_fare += $fare;
}

// Fetch boarding and dropping points
$boarding_point = $_POST['boarding_point'] ?? '';
$dropping_point = $_POST['dropping_point'] ?? '';

// Fetch promo and discount values
$applied_promo = trim($_POST['applied_promo'] ?? '');
$discount_amount = floatval($_POST['discount_amount'] ?? 0.00);

if (!empty($applied_promo)) {
    $code = strtoupper($applied_promo);
    $calculated_discount = 0.00;
    if ($code === 'SAVE10') {
        $calculated_discount = $total_fare * 0.10;
    } elseif ($code === 'FLAT100') {
        $calculated_discount = 100.00;
    } elseif ($code === 'SUPER50') {
        $calculated_discount = $total_fare * 0.50;
    } elseif ($code === 'FREE') {
        $calculated_discount = $total_fare;
    }
    
    if ($calculated_discount > $total_fare) {
        $calculated_discount = $total_fare;
    }
    $discount_amount = round($calculated_discount, 2);
} else {
    $discount_amount = 0.00;
}
$final_fare = $total_fare - $discount_amount;

// Do not auto-fill customer variables
$default_name = '';
$default_email = '';

// Load all seat coordinates for this bus to determine adjacency
$coords_stmt = $pdo->prepare("
    SELECT s.seat_number, s.row_pos, s.col_pos 
    FROM bus_seats s
    JOIN trips t ON s.bus_id = t.bus_id
    WHERE t.id = ? AND s.is_active = 1
");
$coords_stmt->execute([$trip_id]);
$coords_db = $coords_stmt->fetchAll(PDO::FETCH_ASSOC);

$seat_coords = [];
foreach ($coords_db as $c) {
    $seat_coords[$c['seat_number']] = [
        'row' => intval($c['row_pos']),
        'col' => intval($c['col_pos'])
    ];
}

// Fetch all booked female seats for this trip
$female_booked_stmt = $pdo->prepare("
    SELECT DISTINCT bs.seat_number 
    FROM booking_seats bs
    JOIN bookings b ON bs.booking_id = b.id
    WHERE b.trip_id = ? AND b.status = 'active' AND bs.passenger_gender = 'Female'
");
$female_booked_stmt->execute([$trip_id]);
$female_booked_seats = $female_booked_stmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Map adjacent female booked status for the selected seats
$is_adjacent_to_female = [];
foreach ($seats as $seat) {
    $is_adjacent_to_female[$seat] = false;
    if (isset($seat_coords[$seat])) {
        $myRow = $seat_coords[$seat]['row'];
        $myCol = $seat_coords[$seat]['col'];
        
        $adj_col = -1;
        if ($myCol === 0) $adj_col = 1;
        elseif ($myCol === 1) $adj_col = 0;
        elseif ($myCol === 3) $adj_col = 4;
        elseif ($myCol === 4) $adj_col = 3;
        
        if ($adj_col !== -1) {
            foreach ($seat_coords as $sNum => $coord) {
                if ($coord['row'] === $myRow && $coord['col'] === $adj_col) {
                    if (in_array($sNum, $female_booked_seats)) {
                        $is_adjacent_to_female[$seat] = true;
                        break;
                    }
                }
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="row g-4">
    <!-- Passenger Form Column -->
    <div class="col-lg-8">
        <div class="glass-card p-5" style="border-radius: 20px;">
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-users text-indigo me-2"></i>Passenger Details</h4>
            
            <form id="paymentCheckForm" autocomplete="off">
                <!-- CSRF Token -->
                <input type="hidden" name="csrf_token" id="payment_csrf_token" value="<?= get_csrf_token() ?>">
                <input type="hidden" name="trip_id" value="<?= htmlspecialchars($trip_id) ?>">
                <input type="hidden" name="selected_seats" value="<?= htmlspecialchars($selected_seats) ?>">
                <input type="hidden" name="boarding_point" value="<?= htmlspecialchars($boarding_point) ?>">
                <input type="hidden" name="dropping_point" value="<?= htmlspecialchars($dropping_point) ?>">
                <input type="hidden" name="applied_promo" value="<?= htmlspecialchars($applied_promo) ?>">
                <input type="hidden" name="discount_amount" value="<?= htmlspecialchars($discount_amount) ?>">

                <!-- Loop seats to generate passenger input card -->
                <?php foreach ($seats as $idx => $seat): ?>
                    <div class="p-4 rounded-4 mb-4 border border-secondary border-opacity-20 bg-dark bg-opacity-20">
                        <div class="d-flex align-items-center justify-content-between mb-3 border-bottom border-secondary border-opacity-10 pb-2">
                            <h5 class="text-white fw-bold"><i class="fa-solid fa-chair text-indigo me-2"></i>Passenger #<?= $idx + 1 ?> <span class="text-secondary small font-monospace">(Seat <?= htmlspecialchars($seat) ?>)</span></h5>
                            <span class="badge bg-indigo">₹<?= number_format($seat_fares[$seat], 2) ?></span>
                        </div>
                        <div class="row">
                            <div class="col-md-5 mb-3">
                                <label class="form-label text-secondary small fw-semibold">Full Name</label>
                                <input type="text" name="passenger_name[]" class="form-control form-control-swift" placeholder="Passenger full name" required>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label text-secondary small fw-semibold">Age</label>
                                <input type="number" name="passenger_age[]" class="form-control form-control-swift" placeholder="Age" min="5" max="100" required>
                            </div>
                             <div class="col-md-4 mb-3">
                                <label class="form-label text-secondary small fw-semibold">Gender</label>
                                <select name="passenger_gender[]" class="form-select form-control-swift" required>
                                    <?php if ($is_adjacent_to_female[$seat]): ?>
                                        <option value="Female" selected>Female</option>
                                        <option value="Other">Other</option>
                                    <?php else: ?>
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    <?php endif; ?>
                                </select>
                                <?php if ($is_adjacent_to_female[$seat]): ?>
                                    <div class="text-warning small mt-1 font-semibold" style="font-size:0.75rem;">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Adjacent to Female (Male not allowed)
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Primary Contact details -->
                <h4 class="fw-bold text-white mt-5 mb-4"><i class="fa-solid fa-address-book text-pink me-2"></i>Contact Details</h4>
                <div class="p-4 rounded-4 border border-secondary border-opacity-20 bg-dark bg-opacity-20 mb-4">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Contact Name</label>
                            <input type="text" name="contact_name" class="form-control form-control-swift" value="<?= htmlspecialchars($default_name) ?>" placeholder="Full Name" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Email Address</label>
                            <input type="email" name="contact_email" class="form-control form-control-swift" value="<?= htmlspecialchars($default_email) ?>" placeholder="name@domain.com" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label text-secondary small fw-semibold">Mobile Number</label>
                            <input type="tel" name="contact_phone" class="form-control form-control-swift" placeholder="10-digit number" required>
                        </div>
                    </div>
                </div>

                <div class="d-grid mt-4">
                    <button type="button" id="btnInitiatePayment" class="btn btn-primary-gradient py-3 text-uppercase fw-bold" style="border-radius: 12px; letter-spacing: 0.5px;">
                        <i class="fa-solid fa-credit-card me-2"></i>Initiate Checkout Securely
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Panel -->
    <div class="col-lg-4">
        <div class="glass-card p-4 shadow-lg" style="border-radius: 20px; position: sticky; top: 100px;">
            <h4 class="fw-bold text-white mb-4"><i class="fa-solid fa-receipt text-pink me-2"></i>Fare Details</h4>
            <div class="mb-4">
                <span class="text-secondary small d-block">Voyage Operators</span>
                <span class="text-white fw-bold"><?= htmlspecialchars($trip['bus_name']) ?></span>
            </div>
            
            <div class="mb-4">
                <span class="text-secondary small d-block">Stations Route</span>
                <span class="text-white fw-semibold"><?= htmlspecialchars($trip['source']) ?> to <?= htmlspecialchars($trip['destination']) ?></span>
            </div>

            <div class="border-top border-secondary border-opacity-20 pt-3">
                <div class="d-flex justify-content-between text-secondary small mb-2">
                    <span>Seats (<?= count($seats) ?> berths)</span>
                    <span class="text-white fw-semibold"><?= htmlspecialchars($selected_seats) ?></span>
                </div>
                <div class="d-flex justify-content-between text-secondary small mb-3">
                    <span>Base Ticket Fare</span>
                    <span>₹<?= number_format($total_fare, 2) ?></span>
                </div>
                <?php if ($discount_amount > 0): ?>
                    <div class="d-flex justify-content-between text-secondary small mb-3">
                        <span>Discount Applied (<?= htmlspecialchars($applied_promo) ?>)</span>
                        <span class="text-success">-₹<?= number_format($discount_amount, 2) ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between text-white fw-bold fs-5 pt-3 border-top border-secondary border-opacity-30">
                    <span>Total Price</span>
                    <span class="text-indigo">₹<?= number_format($final_fare, 2) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- MOCK RAZORPAY GATEWAY OVERLAY MODAL -->
<div class="modal fade" id="razorpayModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content glass-card border-secondary text-white shadow-2xl" style="border-radius: 24px; background: #121829;">
            <!-- Modal Header -->
            <div class="modal-header border-secondary p-4 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-2">
                    <span class="bg-indigo p-2 rounded-3 text-white d-flex align-items-center justify-content-center" style="background:#5252ff;"><i class="fa-solid fa-shield-halved"></i></span>
                    <div>
                        <h6 class="modal-title fw-bold text-white mb-0">Razorpay Secure Checkout</h6>
                        <span class="text-secondary small" style="font-size:0.75rem;">Merchant: <?= SYSTEM_NAME ?> Inc.</span>
                    </div>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <!-- Modal Body -->
            <div class="modal-body p-4">
                <div class="text-center mb-4">
                    <span class="text-secondary small d-block">AMOUNT TO PAY</span>
                    <h2 class="fw-bold text-indigo" style="font-size: 2.5rem; color:#818cf8;">₹<?= number_format($final_fare, 2) ?></h2>
                </div>

                <div class="p-3 rounded-4 bg-dark bg-opacity-30 border border-secondary border-opacity-20 mb-4 small text-secondary">
                    <div class="d-flex justify-content-between mb-2"><span>Order Reference</span><span class="text-white font-monospace">ORD-<?= time() ?></span></div>
                    <div class="d-flex justify-content-between"><span>Email Contact</span><span class="text-white" id="modal-email-fill"></span></div>
                </div>

                <div class="mb-4">
                    <label class="form-label text-secondary small fw-semibold">Select Mock Payment Method</label>
                    <div class="d-grid gap-3">
                        <button type="button" class="btn btn-secondary-glass text-start py-3 px-3 d-flex align-items-center justify-content-between w-100 rounded-3 select-payment-opt" data-status="success">
                            <span><i class="fa-solid fa-credit-card text-indigo me-3"></i>Mock Credit Card (Simulate Success)</span>
                            <i class="fa-solid fa-chevron-right text-secondary small"></i>
                        </button>
                        <button type="button" class="btn btn-secondary-glass text-start py-3 px-3 d-flex align-items-center justify-content-between w-100 rounded-3 select-payment-opt" data-status="failed">
                            <span><i class="fa-solid fa-circle-xmark text-danger me-3"></i>Simulate Failed Transaction</span>
                            <i class="fa-solid fa-chevron-right text-secondary small"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer border-secondary p-4 d-flex justify-content-between align-items-center">
                <span class="text-secondary small" style="font-size: 0.75rem;"><i class="fa-solid fa-lock me-1"></i>256-bit SSL Encrypted Connection</span>
                <span class="text-secondary small font-monospace text-uppercase" style="font-size: 0.75rem;">Razorpay v3</span>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    
    // Initiate secure checkout trigger
    $('#btnInitiatePayment').click(function() {
        // Validate form input elements first
        var form = $('#paymentCheckForm')[0];
        if (!form.checkValidity()) {
            form.reportValidity();
            return;
        }

        // Fill modal details
        var email = $('input[name="contact_email"]').val();
        $('#modal-email-fill').text(email);

        // Show Razorpay modal
        $('#razorpayModal').modal('show');
    });

    // Handle Payment Selection Simulation
    $('.select-payment-opt').click(function() {
        var status = $(this).data('status');
        
        if (status === 'failed') {
            alert("Mock Payment Failed: You simulated a failed transaction. Please choose card simulation for success.");
            $('#razorpayModal').modal('hide');
            return;
        }

        // Payment approved - Trigger AJAX execution
        $('#razorpayModal').modal('hide');
        
        // Show interactive processing indicator
        var originalBtnText = $('#btnInitiatePayment').html();
        $('#btnInitiatePayment').html('<i class="fa-solid fa-spinner fa-spin me-2"></i>Processing Secure Transaction...').addClass('disabled');

        // Compile payload
        var formData = $('#paymentCheckForm').serialize();

        $.ajax({
            url: '<?= BASE_URL ?>/checkout.php?action=process_payment',
            type: 'POST',
            data: formData,
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    alert("Mock Payment Successful! Generating your ticket...");
                    window.location.href = '<?= BASE_URL ?>/ticket.php?ref=' + response.booking_ref;
                } else {
                    alert("Booking Error: " + response.message);
                    $('#btnInitiatePayment').html(originalBtnText).removeClass('disabled');
                }
            },
            error: function() {
                alert("CRITICAL ERROR: Failed to communicate with payment processor. Please check connection.");
                $('#btnInitiatePayment').html(originalBtnText).removeClass('disabled');
            }
        });
    });
});
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
