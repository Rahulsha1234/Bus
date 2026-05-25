<?php
/**
 * Agent Panel Layout Header
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

// Force role protection
require_role('agent');

$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?>Agent Portal</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Style -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <style>
        .sidebar-agent {
            min-height: 100vh;
            border-right: 1px solid var(--border-glass);
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(10px);
            padding: 2rem 1.5rem;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: var(--text-muted);
            text-decoration: none;
            padding: 0.8rem 1rem;
            border-radius: 10px;
            font-weight: 500;
            transition: all 0.2s ease;
            margin-bottom: 0.5rem;
        }
        .sidebar-link:hover, .sidebar-link.active {
            color: white;
            background: rgba(99, 102, 241, 0.1);
            border-left: 3px solid var(--accent-indigo);
            padding-left: calc(1rem - 3px);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-3 col-lg-2 px-0 d-none d-md-block sidebar-agent">
            <div class="mb-5 px-3">
                <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none d-flex align-items-center gap-2">
                    <i class="fa-solid fa-bus text-indigo" style="font-size: 1.5rem; color:#818cf8;"></i>
                    <span class="text-gradient fw-bold fs-5"><?= SYSTEM_NAME ?></span>
                </a>
                <span class="text-secondary" style="font-size: 0.75rem; letter-spacing: 0.5px;">AGENCY DESK</span>
            </div>

            <nav>
                <?php 
                $cur = basename($_SERVER['SCRIPT_NAME']);
                ?>
                <a href="<?= BASE_URL ?>/agent/dashboard.php" class="sidebar-link <?= ($cur === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie"></i>Dashboard
                </a>
                <a href="<?= BASE_URL ?>/agent/buses.php" class="sidebar-link <?= ($cur === 'buses.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-bus-simple"></i>Manage Buses
                </a>
                <a href="<?= BASE_URL ?>/agent/routes.php" class="sidebar-link <?= ($cur === 'routes.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-route"></i>Manage Routes
                </a>
                <a href="<?= BASE_URL ?>/agent/trips.php" class="sidebar-link <?= ($cur === 'trips.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-calendar-days"></i>Schedule Trips
                </a>
                <a href="<?= BASE_URL ?>/agent/seats.php" class="sidebar-link <?= ($cur === 'seats.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-chair"></i>Hold/Release Seats
                </a>
                <a href="<?= BASE_URL ?>/agent/bookings.php" class="sidebar-link <?= ($cur === 'bookings.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-receipt"></i>Bookings List
                </a>
                <a href="<?= BASE_URL ?>/agent/settlements.php" class="sidebar-link <?= ($cur === 'settlements.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-wallet"></i>Settlements
                </a>
                
                <hr class="border-secondary my-4">
                
                <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link text-danger">
                    <i class="fa-solid fa-right-from-bracket"></i>Logout
                </a>
            </nav>
        </div>

        <!-- Main Display Panel -->
        <div class="col-md-9 col-lg-10 py-5 px-md-5">
            <!-- Mobile Header -->
            <div class="d-flex d-md-none justify-content-between align-items-center mb-4 pb-2 border-bottom border-secondary border-opacity-25">
                <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none">
                    <span class="text-gradient fw-bold fs-4"><?= SYSTEM_NAME ?></span>
                </a>
                <div class="dropdown">
                    <button class="btn btn-secondary-glass dropdown-toggle py-1 px-2 small" type="button" data-bs-toggle="dropdown">
                        <i class="fa-solid fa-bars"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark glass-card p-2 border-0 mt-2">
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/agent/dashboard.php">Dashboard</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/agent/buses.php">Buses</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/agent/routes.php">Routes</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/agent/trips.php">Trips</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/agent/seats.php">Seats</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/agent/bookings.php">Bookings</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/agent/settlements.php">Settlements</a></li>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item text-danger py-2" href="<?= BASE_URL ?>/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
                <div>
                    <h2 class="fw-bold text-white mb-0"><?= isset($page_title) ? htmlspecialchars($page_title) : 'Agent Portal' ?></h2>
                    <span class="text-secondary small">Travel Agent Workspace</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="small text-secondary"><i class="fa-solid fa-briefcase text-indigo me-2"></i><?= htmlspecialchars($user['username']) ?></span>
                </div>
            </div>
