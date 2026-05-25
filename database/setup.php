<?php
/**
 * Database Setup and Seeding Script
 */
$host = '127.0.0.1';
$user = 'root';
$pass = '';

try {
    // 1. Connect without DB selected
    $pdo = new PDO("mysql:host=$host", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
    echo "Connected to MySQL successfully.<br>";

    // 2. Read and parse schema.sql
    $schemaFile = __DIR__ . '/schema.sql';
    if (!file_exists($schemaFile)) {
        die("schema.sql not found at: $schemaFile");
    }
    
    $sql = file_get_contents($schemaFile);
    
    // Execute SQL statements
    // We can execute the whole string because PDO supports multiple statements if emulated prepares are on (which is default).
    $pdo->exec($sql);
    echo "Database and schema initialized successfully.<br>";

    // 3. Connect to the new database
    $pdo->exec("USE bus_booking");

    // 4. Seed Users
    echo "Seeding users...<br>";
    $users = [
        [
            'username' => 'admin',
            'email' => 'admin@bus.com',
            'password' => password_hash('admin123', PASSWORD_BCRYPT),
            'role' => 'admin',
            'status' => 'approved'
        ],
        [
            'username' => 'agent1',
            'email' => 'agent1@bus.com',
            'password' => password_hash('agent123', PASSWORD_BCRYPT),
            'role' => 'agent',
            'status' => 'approved'
        ],
        [
            'username' => 'agent2',
            'email' => 'agent2@bus.com',
            'password' => password_hash('agent123', PASSWORD_BCRYPT),
            'role' => 'agent',
            'status' => 'pending'
        ],
        [
            'username' => 'customer1',
            'email' => 'customer1@bus.com',
            'password' => password_hash('customer123', PASSWORD_BCRYPT),
            'role' => 'customer',
            'status' => 'approved'
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, status) VALUES (:username, :email, :password, :role, :status)");
    
    $userIds = [];
    foreach ($users as $u) {
        $stmt->execute([
            ':username' => $u['username'],
            ':email' => $u['email'],
            ':password' => $u['password'],
            ':role' => $u['role'],
            ':status' => $u['status']
        ]);
        $userIds[$u['username']] = $pdo->lastInsertId();
    }
    echo "Users seeded successfully.<br>";

    // 5. Seed Agent Profiles
    echo "Seeding agent profiles...<br>";
    $profiles = [
        [
            'user_id' => $userIds['agent1'],
            'agency_name' => 'Golden Travels',
            'phone' => '9876543210',
            'commission_rate' => 2.00
        ],
        [
            'user_id' => $userIds['agent2'],
            'agency_name' => 'Silver Express',
            'phone' => '9876543211',
            'commission_rate' => 2.00
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO agent_profiles (user_id, agency_name, phone, commission_rate) VALUES (:user_id, :agency_name, :phone, :commission_rate)");
    foreach ($profiles as $p) {
        $stmt->execute([
            ':user_id' => $p['user_id'],
            ':agency_name' => $p['agency_name'],
            ':phone' => $p['phone'],
            ':commission_rate' => $p['commission_rate']
        ]);
    }
    echo "Agent profiles seeded.<br>";

    // 6. Seed Buses
    echo "Seeding buses...<br>";
    $buses = [
        [
            'agent_id' => $userIds['agent1'],
            'bus_name' => 'Golden Deluxe AC Sleeper',
            'bus_number' => 'KA-01-F-1234',
            'bus_type' => 'AC Sleeper',
            'total_seats' => 30,
            'seat_layout_type' => '2x1_sleeper'
        ],
        [
            'agent_id' => $userIds['agent1'],
            'bus_name' => 'Golden Express AC Seater',
            'bus_number' => 'KA-01-F-5678',
            'bus_type' => 'AC Seater',
            'total_seats' => 40,
            'seat_layout_type' => '2x2_seater'
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO buses (agent_id, bus_name, bus_number, bus_type, total_seats, seat_layout_type) VALUES (:agent_id, :bus_name, :bus_number, :bus_type, :total_seats, :seat_layout_type)");
    
    $busIds = [];
    foreach ($buses as $idx => $b) {
        $stmt->execute([
            ':agent_id' => $b['agent_id'],
            ':bus_name' => $b['bus_name'],
            ':bus_number' => $b['bus_number'],
            ':bus_type' => $b['bus_type'],
            ':total_seats' => $b['total_seats'],
            ':seat_layout_type' => $b['seat_layout_type']
        ]);
        $busIds[$idx] = $pdo->lastInsertId();
    }
    echo "Buses seeded.<br>";

    // 7. Seed Routes
    echo "Seeding routes...<br>";
    $pickups1 = [
        ['name' => 'Majestic Bus Stand', 'time' => '20:00'],
        ['name' => 'Yeshwanthpur Tollgate', 'time' => '20:30']
    ];
    $drops1 = [
        ['name' => 'Pune Bypass', 'time' => '07:00'],
        ['name' => 'Mumbai Sion Circle', 'time' => '08:30']
    ];

    $pickups2 = [
        ['name' => 'Majestic Bus Stand', 'time' => '22:00'],
        ['name' => 'Indiranagar Metro', 'time' => '22:30']
    ];
    $drops2 = [
        ['name' => 'Poonamallee Bypass', 'time' => '04:30'],
        ['name' => 'Koyambedu Bus Terminus', 'time' => '05:00']
    ];

    $routes = [
        [
            'agent_id' => $userIds['agent1'],
            'source' => 'Bangalore',
            'destination' => 'Mumbai',
            'distance_km' => 1000,
            'pickup_points' => json_encode($pickups1),
            'drop_points' => json_encode($drops1)
        ],
        [
            'agent_id' => $userIds['agent1'],
            'source' => 'Bangalore',
            'destination' => 'Chennai',
            'distance_km' => 350,
            'pickup_points' => json_encode($pickups2),
            'drop_points' => json_encode($drops2)
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO routes (agent_id, source, destination, distance_km, pickup_points, drop_points) VALUES (:agent_id, :source, :destination, :distance_km, :pickup_points, :drop_points)");
    
    $routeIds = [];
    foreach ($routes as $idx => $r) {
        $stmt->execute([
            ':agent_id' => $r['agent_id'],
            ':source' => $r['source'],
            ':destination' => $r['destination'],
            ':distance_km' => $r['distance_km'],
            ':pickup_points' => $r['pickup_points'],
            ':drop_points' => $r['drop_points']
        ]);
        $routeIds[$idx] = $pdo->lastInsertId();
    }
    echo "Routes seeded.<br>";

    // 8. Seed Trips (for tomorrow and today)
    echo "Seeding trips...<br>";
    $today = date('Y-m-d');
    $tomorrow = date('Y-m-d', strtotime('+1 day'));
    $nextWeek = date('Y-m-d', strtotime('+7 days'));

    $trips = [
        // Trip 1: Bangalore -> Mumbai AC Sleeper tomorrow
        [
            'bus_id' => $busIds[0],
            'route_id' => $routeIds[0],
            'departure_time' => "$tomorrow 20:00:00",
            'arrival_time' => date('Y-m-d H:i:s', strtotime("$tomorrow 20:00:00 +12 hours")),
            'base_fare' => 1200.00,
            'seat_prices' => null
        ],
        // Trip 2: Bangalore -> Chennai AC Seater tomorrow
        [
            'bus_id' => $busIds[1],
            'route_id' => $routeIds[1],
            'departure_time' => "$tomorrow 22:00:00",
            'arrival_time' => date('Y-m-d H:i:s', strtotime("$tomorrow 22:00:00 +7 hours")),
            'base_fare' => 600.00,
            'seat_prices' => null
        ],
        // Trip 3: Bangalore -> Chennai AC Seater today (already running/completed soon)
        [
            'bus_id' => $busIds[1],
            'route_id' => $routeIds[1],
            'departure_time' => "$today 22:00:00",
            'arrival_time' => date('Y-m-d H:i:s', strtotime("$today 22:00:00 +7 hours")),
            'base_fare' => 550.00,
            'seat_prices' => null
        ]
    ];

    $stmt = $pdo->prepare("INSERT INTO trips (bus_id, route_id, departure_time, arrival_time, base_fare, seat_prices) VALUES (:bus_id, :route_id, :departure_time, :arrival_time, :base_fare, :seat_prices)");
    
    $tripIds = [];
    foreach ($trips as $t) {
        $stmt->execute([
            ':bus_id' => $t['bus_id'],
            ':route_id' => $t['route_id'],
            ':departure_time' => $t['departure_time'],
            ':arrival_time' => $t['arrival_time'],
            ':base_fare' => $t['base_fare'],
            ':seat_prices' => $t['seat_prices']
        ]);
        $tripIds[] = $pdo->lastInsertId();
    }
    echo "Trips seeded.<br>";

    // 9. Generate trip seats for these trips
    echo "Generating seats for trips...<br>";
    $seatInsertStmt = $pdo->prepare("INSERT INTO trip_seats (trip_id, seat_number, status) VALUES (:trip_id, :seat_number, 'available')");
    
    // For Sleeper Trip (30 seats: L1 to L15 for Lower, U1 to U15 for Upper)
    $tripSleeperId = $tripIds[0];
    for ($i = 1; $i <= 15; $i++) {
        $seatInsertStmt->execute([':trip_id' => $tripSleeperId, ':seat_number' => "L$i"]);
        $seatInsertStmt->execute([':trip_id' => $tripSleeperId, ':seat_number' => "U$i"]);
    }

    // For Seater Trips (40 seats: 1 to 40)
    for ($tIdx = 1; $tIdx <= 2; $tIdx++) {
        $tripSeaterId = $tripIds[$tIdx];
        for ($i = 1; $i <= 40; $i++) {
            $seatInsertStmt->execute([':trip_id' => $tripSeaterId, ':seat_number' => strval($i)]);
        }
    }
    echo "Trip seats initialized successfully.<br>";

    // 10. Seed some mock bookings to show dashboard graph data
    echo "Seeding mock bookings for yesterday and today...<br>";
    $bookingStmt = $pdo->prepare("
        INSERT INTO bookings (booking_reference, trip_id, customer_id, customer_name, customer_email, customer_phone, total_amount, admin_commission, agent_net_earning, payment_status, payment_gateway, transaction_id, created_at)
        VALUES (:ref, :trip_id, :customer_id, :name, :email, :phone, :amount, :commission, :net, 'paid', 'Razorpay', :tx_id, :created_at)
    ");

    $bookingSeatStmt = $pdo->prepare("
        INSERT INTO booking_seats (booking_id, seat_number, passenger_name, passenger_age, passenger_gender, price)
        VALUES (:booking_id, :seat, :name, :age, :gender, :price)
    ");

    // Booking 1: Seeded Booking for Customer 1 (Trip 3 - Today's trip, booked yesterday)
    $ref1 = 'TXN' . time() . '1';
    $amount1 = 1100.00; // 2 seats
    $comm1 = $amount1 * 0.02;
    $net1 = $amount1 - $comm1;
    $yesterday = date('Y-m-d H:i:s', strtotime('-1 day'));
    
    $bookingStmt->execute([
        ':ref' => $ref1,
        ':trip_id' => $tripIds[2], // Trip 3
        ':customer_id' => $userIds['customer1'],
        ':name' => 'John Doe',
        ':email' => 'customer1@bus.com',
        ':phone' => '9876543219',
        ':amount' => $amount1,
        ':commission' => $comm1,
        ':net' => $net1,
        ':tx_id' => 'pay_mock123456',
        ':created_at' => $yesterday
    ]);
    $bookingId1 = $pdo->lastInsertId();

    // Seats booked for Booking 1
    $bookingSeatStmt->execute([
        ':booking_id' => $bookingId1,
        ':seat' => '1',
        ':name' => 'John Doe',
        ':age' => 30,
        ':gender' => 'Male',
        ':price' => 550.00
    ]);
    $bookingSeatStmt->execute([
        ':booking_id' => $bookingId1,
        ':seat' => '2',
        ':name' => 'Jane Doe',
        ':age' => 28,
        ':gender' => 'Female',
        ':price' => 550.00
    ]);

    // Update trip seat status to 'booked'
    $updateSeatStmt = $pdo->prepare("UPDATE trip_seats SET status = 'booked' WHERE trip_id = :trip_id AND seat_number = :seat");
    $updateSeatStmt->execute([':trip_id' => $tripIds[2], ':seat' => '1']);
    $updateSeatStmt->execute([':trip_id' => $tripIds[2], ':seat' => '2']);


    // Booking 2: Booked today
    $ref2 = 'TXN' . time() . '2';
    $amount2 = 550.00; // 1 seat
    $comm2 = $amount2 * 0.02;
    $net2 = $amount2 - $comm2;
    
    $bookingStmt->execute([
        ':ref' => $ref2,
        ':trip_id' => $tripIds[2], // Trip 3
        ':customer_id' => null, // Guest
        ':name' => 'Alice Smith',
        ':email' => 'alice@gmail.com',
        ':phone' => '9876543212',
        ':amount' => $amount2,
        ':commission' => $comm2,
        ':net' => $net2,
        ':tx_id' => 'pay_mock789012',
        ':created_at' => date('Y-m-d H:i:s')
    ]);
    $bookingId2 = $pdo->lastInsertId();

    $bookingSeatStmt->execute([
        ':booking_id' => $bookingId2,
        ':seat' => '3',
        ':name' => 'Alice Smith',
        ':age' => 24,
        ':gender' => 'Female',
        ':price' => 550.00
    ]);
    $updateSeatStmt->execute([':trip_id' => $tripIds[2], ':seat' => '3']);

    // Seed mock settlement for Agent 1 from previous week
    $settlementStmt = $pdo->prepare("
        INSERT INTO weekly_settlements (agent_id, week_start, week_end, total_sales, commission_payable, status, marked_paid_at, marked_paid_by)
        VALUES (:agent_id, :start, :end, :sales, :commission, 'pending', NULL, NULL)
    ");
    $settlementStmt->execute([
        ':agent_id' => $userIds['agent1'],
        ':start' => date('Y-m-d', strtotime('-14 days')),
        ':end' => date('Y-m-d', strtotime('-7 days')),
        ':sales' => 5000.00,
        ':commission' => 100.00
    ]);

    // Let's log an activity
    $logStmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action_type, details, ip_address)
        VALUES (:user_id, 'SYSTEM_INIT', 'System database initialized and seeded successfully.', '127.0.0.1')
    ");
    $logStmt->execute([':user_id' => $userIds['admin']]);

    echo "<h3>SETUP COMPLETED SUCCESSFULLY!</h3>";
    echo "Use the following credentials to log in:<br>";
    echo "<b>Admin:</b> admin / admin123<br>";
    echo "<b>Agent 1 (Approved):</b> agent1 / agent123<br>";
    echo "<b>Agent 2 (Pending):</b> agent2 / agent123<br>";
    echo "<b>Customer:</b> customer1 / customer123<br>";

} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage() . "<br>";
    echo "Please check if your local WAMP server is running and default MySQL settings are correct.";
}
