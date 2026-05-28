<?php
/**
 * Premium E-Ticket PDF / Print Controller
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$ref = $_GET['ref'] ?? '';
if (empty($ref)) {
    die("Invalid reference.");
}

try {
    // 0. Ensure user is logged in
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
    $curr_user_id = intval($_SESSION['user_id']);
    $curr_user_role = $_SESSION['user_role'] ?? 'customer';

    $is_customer_copy = false;
    if ((isset($_GET['view']) && $_GET['view'] === 'customer') || $curr_user_role === 'customer') {
        $is_customer_copy = true;
    }

    // Fetch Booking details
    $stmt = $pdo->prepare("
        SELECT 
            b.id AS booking_id,
            b.customer_id,
            b.booking_reference,
            b.customer_name,
            b.customer_email,
            b.customer_phone,
            b.total_amount,
            b.discount_amount,
            b.original_fare,
            b.promo_code,
            b.payment_status,
            b.created_at,
            b.boarding_point,
            b.dropping_point,
            t.departure_time,
            t.arrival_time,
            bs.id AS bus_id,
            bs.bus_name,
            bs.bus_number,
            bs.bus_type,
            r.source,
            r.destination
        FROM bookings b
        JOIN trips t ON b.trip_id = t.id
        JOIN buses bs ON t.bus_id = bs.id
        JOIN routes r ON t.route_id = r.id
        WHERE b.booking_reference = :ref
        LIMIT 1
    ");
    $stmt->execute([':ref' => $ref]);
    $booking = $stmt->fetch();

    if (!$booking) {
        die("Ticket Reference not found.");
    }

    // 0.5. Verify ownership for customers
    if ($curr_user_role === 'customer' && intval($booking['customer_id']) !== $curr_user_id) {
        die("Access Denied: You do not have permission to view this ticket receipt.");
    }

    // Fetch Seats/Passengers
    $seats_stmt = $pdo->prepare("
        SELECT seat_number, passenger_name, passenger_age, passenger_gender, price 
        FROM booking_seats 
        WHERE booking_id = :booking_id
    ");
    $seats_stmt->execute([':booking_id' => $booking['booking_id']]);
    $passengers = $seats_stmt->fetchAll();

    // Fetch Operator Contact details
    $op_stmt = $pdo->prepare("SELECT * FROM operator_contacts WHERE bus_id = ? LIMIT 1");
    $op_stmt->execute([$booking['bus_id']]);
    $operator = $op_stmt->fetch() ?: [
        'operator_name' => 'SwiftBus Fleet Operations',
        'contact_number' => '+1 (555) 234-5678',
        'whatsapp_number' => '+1 (555) 234-5678',
        'emergency_number' => '+1 (555) 911-0099',
        'support_email' => 'support@swiftbus-fleet.com'
    ];

} catch (PDOException $e) {
    die("Error retrieving ticket: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>E-Ticket: <?= htmlspecialchars($booking['booking_reference']) ?></title>
    <!-- Google Fonts Inter & JetBrains Mono -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <!-- FontAwesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-cream: #F8F9FA;
            --surface-ivory: #FFFFFF;
            --accent-gold: #0F5132;
            --text-dark: #212529;
            --text-muted: #6C757D;
            --border-warm: #DEE2E6;
        }
        body {
            background-color: var(--bg-cream);
            color: var(--text-dark);
            font-family: 'Inter', sans-serif;
            font-size: 0.95rem;
            padding: 40px 20px;
        }
        .ticket-outer {
            max-width: 800px;
            margin: 0 auto;
            background-color: var(--surface-ivory);
            border: 1px solid var(--border-warm);
            border-radius: 24px;
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.05);
            padding: 45px;
            position: relative;
        }
        .ticket-header {
            border-bottom: 2px dashed var(--border-warm);
            padding-bottom: 30px;
            margin-bottom: 30px;
        }
        .logo-title {
            font-size: 1.8rem;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .logo-title span {
            color: var(--accent-gold);
        }
        .info-label {
            font-size: 0.75rem;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
            margin-bottom: 4px;
        }
        .info-value {
            font-weight: 600;
            color: var(--text-dark);
        }
        .info-value.mono {
            font-family: 'JetBrains Mono', monospace;
            color: var(--accent-gold);
        }
        .badge-status {
            background-color: rgba(200, 169, 107, 0.1);
            color: var(--accent-gold);
            border: 1px solid rgba(200, 169, 107, 0.25);
            font-size: 0.85rem;
            padding: 6px 16px;
            border-radius: 50px;
            font-weight: 700;
        }
        .section-title {
            font-size: 0.85rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--accent-gold);
            border-bottom: 1px solid var(--border-warm);
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .passenger-table th {
            font-size: 0.8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-warm);
            background: transparent;
        }
        .passenger-table td {
            padding: 12px 8px;
            border-bottom: 1px solid rgba(231, 225, 215, 0.5);
            background: transparent;
        }
        .contact-box {
            background-color: var(--bg-cream);
            border: 1px solid var(--border-warm);
            border-radius: 16px;
            padding: 20px;
        }
        .qr-placeholder {
            width: 130px;
            height: 130px;
            padding: 8px;
            background: white;
            border: 1px solid var(--border-warm);
            border-radius: 12px;
            display: inline-block;
        }
        .print-btn-bar {
            max-width: 800px;
            margin: 0 auto 20px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn-gold {
            background-color: var(--accent-gold);
            color: white;
            border: none;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            transition: all 0.2s ease;
        }
        .btn-gold:hover {
            background-color: #b5955a;
            color: white;
        }
        .btn-outline-warm {
            border: 1px solid var(--border-warm);
            color: var(--text-muted);
            background: white;
            padding: 10px 24px;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        .btn-outline-warm:hover {
            background-color: var(--bg-cream);
            color: var(--text-dark);
        }
        @media print {
            body {
                background: white !important;
                padding: 0 !important;
            }
            .ticket-outer {
                border: none !important;
                box-shadow: none !important;
                padding: 0 !important;
            }
            .print-btn-bar {
                display: none !important;
            }
            .contact-box {
                background: white !important;
                border: 1px solid #ccc !important;
            }
        }
    </style>
</head>
<body>

    <div class="print-btn-bar">
        <a href="<?= BASE_URL ?>/index.php" class="btn-outline-warm"><i class="fa-solid fa-arrow-left me-2"></i>Back to Home</a>
        <div class="d-flex gap-2">
            <?php if ($curr_user_role !== 'customer'): ?>
                <?php if ($is_customer_copy): ?>
                    <a href="?ref=<?= urlencode($booking['booking_reference']) ?>" class="btn-outline-warm"><i class="fa-solid fa-user-secret me-2"></i>Switch to Agent Copy</a>
                <?php else: ?>
                    <a href="?ref=<?= urlencode($booking['booking_reference']) ?>&view=customer" class="btn-outline-warm"><i class="fa-solid fa-users me-2"></i>Switch to Customer Copy</a>
                <?php endif; ?>
            <?php endif; ?>
            <button onclick="window.print()" class="btn-gold"><i class="fa-solid fa-print me-2"></i>Print E-Ticket / Save PDF</button>
        </div>
    </div>

    <div class="ticket-outer">
        <!-- Ticket Header -->
        <div class="ticket-header d-flex flex-wrap justify-content-between align-items-center g-3">
            <div>
                <div class="logo-title mb-1">Swift<span>Bus</span></div>
                <div class="text-secondary small font-monospace">OFFICIAL E-TICKET & BOARDING PASS</div>
            </div>
            <div class="text-md-end">
                <span class="badge-status">CONFIRMED & PAID</span>
                <div class="text-muted small mt-2">Booked: <?= date('d M Y, H:i', strtotime($booking['created_at'])) ?></div>
            </div>
        </div>

        <!-- Info row -->
        <div class="row g-4 mb-5">
            <div class="col-md-6 col-sm-6">
                <div class="info-label">Ticket Reference</div>
                <div class="fs-4 fw-bold mono"><?= htmlspecialchars($booking['booking_reference']) ?></div>
            </div>
            <div class="col-md-6 col-sm-6 text-md-end">
                <!-- Inline dynamic mock QR Code using SVG -->
                <div class="qr-placeholder">
                    <svg viewBox="0 0 100 100" style="width:100%; height:100%;">
                        <!-- Dynamic SVG QR Code Design -->
                        <rect x="0" y="0" width="100" height="100" fill="#fff" />
                        <!-- Corner Anchors -->
                        <rect x="10" y="10" width="25" height="25" fill="#1f1f1f" />
                        <rect x="13" y="13" width="19" height="19" fill="#fff" />
                        <rect x="16" y="16" width="13" height="13" fill="#1f1f1f" />

                        <rect x="65" y="10" width="25" height="25" fill="#1f1f1f" />
                        <rect x="68" y="13" width="19" height="19" fill="#fff" />
                        <rect x="71" y="16" width="13" height="13" fill="#1f1f1f" />

                        <rect x="10" y="65" width="25" height="25" fill="#1f1f1f" />
                        <rect x="13" y="68" width="19" height="19" fill="#fff" />
                        <rect x="16" y="71" width="13" height="13" fill="#1f1f1f" />
                        
                        <!-- Mini alignment block -->
                        <rect x="70" y="70" width="10" height="10" fill="#1f1f1f" />
                        <rect x="72" y="72" width="6" height="6" fill="#fff" />
                        <rect x="74" y="74" width="2" height="2" fill="#1f1f1f" />

                        <!-- Random noise data blocks to make it look authentic -->
                        <rect x="40" y="15" width="5" height="10" fill="#1f1f1f" />
                        <rect x="48" y="10" width="10" height="5" fill="#1f1f1f" />
                        <rect x="42" y="25" width="15" height="5" fill="#1f1f1f" />
                        <rect x="15" y="42" width="10" height="5" fill="#1f1f1f" />
                        <rect x="10" y="50" width="5" height="10" fill="#1f1f1f" />
                        <rect x="25" y="48" width="5" height="15" fill="#1f1f1f" />
                        
                        <rect x="40" y="40" width="20" height="20" fill="#1f1f1f" />
                        <rect x="45" y="45" width="10" height="10" fill="#fff" />
                        <rect x="47" y="47" width="6" height="6" fill="#1f1f1f" />
                        
                        <rect x="68" y="40" width="12" height="5" fill="#1f1f1f" />
                        <rect x="75" y="48" width="5" height="12" fill="#1f1f1f" />
                        
                        <rect x="40" y="68" width="15" height="5" fill="#1f1f1f" />
                        <rect x="42" y="75" width="8" height="10" fill="#1f1f1f" />
                        <rect x="55" y="80" width="10" height="5" fill="#1f1f1f" />
                        
                        <rect x="68" y="85" width="12" height="5" fill="#1f1f1f" />
                    </svg>
                </div>
            </div>
        </div>

        <!-- Trip Details Section -->
        <div class="section-title">Trip Information</div>
        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="mb-3">
                    <div class="info-label">Route Voyage</div>
                    <div class="info-value fs-5"><?= htmlspecialchars($booking['source']) ?> <i class="fa-solid fa-arrow-right mx-1 text-muted" style="font-size:0.85rem;"></i> <?= htmlspecialchars($booking['destination']) ?></div>
                </div>
                <div class="mb-3">
                    <div class="info-label">Travel Date / Departure</div>
                    <div class="info-value"><?= date('d M Y, H:i', strtotime($booking['departure_time'])) ?></div>
                </div>
                <div>
                    <div class="info-label">Arrival Time (Est)</div>
                    <div class="info-value"><?= date('d M Y, H:i', strtotime($booking['arrival_time'])) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="mb-3">
                    <div class="info-label">Bus operator / fleet</div>
                    <div class="info-value"><?= htmlspecialchars($booking['bus_name']) ?></div>
                    <span class="badge bg-light border text-dark mt-1 font-monospace fs-7 text-uppercase"><?= htmlspecialchars($booking['bus_type']) ?></span>
                </div>
                <div class="mb-3">
                    <div class="info-label">Bus registration no.</div>
                    <div class="info-value font-monospace"><?= htmlspecialchars($booking['bus_number']) ?></div>
                </div>
            </div>
        </div>

        <!-- Boarding & Dropping milestones -->
        <div class="section-title">Milestones / Stations Selected</div>
        <div class="row g-4 mb-5">
            <div class="col-md-6">
                <div class="p-3 border rounded-3 bg-light bg-opacity-50">
                    <div class="info-label text-success"><i class="fa-solid fa-circle-arrow-up me-1"></i>Boarding Point</div>
                    <div class="info-value fs-6 mt-1"><?= htmlspecialchars($booking['boarding_point']) ?></div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="p-3 border rounded-3 bg-light bg-opacity-50">
                    <div class="info-label text-danger"><i class="fa-solid fa-circle-arrow-down me-1"></i>Dropping Point</div>
                    <div class="info-value fs-6 mt-1"><?= htmlspecialchars($booking['dropping_point']) ?></div>
                </div>
            </div>
        </div>

        <!-- Passenger Table -->
        <div class="section-title">Passenger Information</div>
        <div class="table-responsive mb-5">
            <table class="table passenger-table">
                <thead>
                    <tr>
                        <th>Seat No.</th>
                        <th>Passenger Name</th>
                        <th>Age / Gender</th>
                        <th class="text-end">Fare Amount</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($passengers as $p): ?>
                        <tr>
                            <td class="font-monospace fw-bold text-dark"><?= htmlspecialchars($p['seat_number']) ?></td>
                            <td class="fw-semibold"><?= htmlspecialchars($p['passenger_name']) ?></td>
                            <td><?= htmlspecialchars($p['passenger_age']) ?> Yrs / <?= htmlspecialchars($p['passenger_gender']) ?></td>
                            <td class="text-end fw-semibold">₹<?= number_format($p['price'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Primary Contact & Operator support details -->
        <div class="row g-4">
            <div class="col-md-6">
                <div class="contact-box">
                    <h6 class="fw-bold mb-3"><i class="fa-solid fa-headset text-gold me-2"></i>Bus Operator Support</h6>
                    <div class="small">
                        <div class="mb-2"><strong>Operator:</strong> <?= htmlspecialchars($operator['operator_name']) ?></div>
                        <div class="mb-2"><strong>Contact Number:</strong> <?= htmlspecialchars($operator['contact_number']) ?></div>
                        <div class="mb-2"><strong>WhatsApp Contact:</strong> <?= htmlspecialchars($operator['whatsapp_number']) ?></div>
                        <div class="mb-2"><strong>Emergency Hotline:</strong> <span class="text-danger fw-bold"><?= htmlspecialchars($operator['emergency_number']) ?></span></div>
                        <div><strong>Support Email:</strong> <?= htmlspecialchars($operator['support_email']) ?></div>
                    </div>
                </div>
            </div>
                <div class="mb-3">
                    <div class="info-label">Customer Contact Info</div>
                    <div class="fw-semibold"><?= htmlspecialchars($booking['customer_name']) ?></div>
                    <div class="small text-muted"><?= htmlspecialchars($booking['customer_email']) ?> | <?= htmlspecialchars($booking['customer_phone']) ?></div>
                </div>
                <div>
                    <?php if (!$is_customer_copy && $booking['discount_amount'] > 0): ?>
                        <div class="mb-2">
                            <span class="info-label">Discount Applied (<?= htmlspecialchars($booking['promo_code'] ?? 'Agent Discount') ?>)</span>
                            <span class="text-success fw-bold d-block">-₹<?= number_format($booking['discount_amount'], 2) ?></span>
                        </div>
                    <?php endif; ?>
                    <div class="info-label">Total Fare Paid</div>
                    <div class="fs-2 fw-bold text-dark">
                        ₹<?= number_format($is_customer_copy ? (floatval($booking['original_fare']) > 0 ? floatval($booking['original_fare']) : floatval($booking['total_amount']) + floatval($booking['discount_amount'])) : floatval($booking['total_amount']), 2) ?>
                    </div>
                    <div class="text-muted small" style="font-size:0.75rem;">Payment processed securely via Razorpay</div>
                </div>
            </div>
        </div>

        <div class="mt-5 p-3 text-center text-muted small border-top border-light">
            <i class="fa-solid fa-circle-exclamation me-1"></i> Please carry a valid identity proof (Aadhaar Card, Passport, etc.) along with this E-Ticket copy during the travel.
        </div>

    </div>

</body>
</html>
