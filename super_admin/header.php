<?php
/**
 * Super Admin Panel Header
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

// Role protection
require_role('super_admin');

$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?>Super Admin Portal</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Style -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=1.0.2">
    <script>
        (function () {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js" defer></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    
    <style>
        .sidebar-admin {
            min-height: 100vh;
            border-right: 1px solid var(--border-glass);
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(12px);
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
            border-left: 3px solid var(--accent-violet);
            padding-left: calc(1rem - 3px);
        }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar Navigation -->
        <div class="col-md-3 col-lg-2 px-0 d-none d-md-block sidebar-admin">
            <div class="mb-5 px-3">
                <a href="<?= BASE_URL ?>/index.php" class="text-decoration-none d-flex align-items-center gap-2">
                    <i class="fa-solid fa-bus text-indigo" style="font-size: 1.5rem; color:#818cf8;"></i>
                    <span class="text-gradient fw-bold fs-5"><?= SYSTEM_NAME ?></span>
                </a>
                <span class="text-secondary small font-monospace" style="font-size: 0.7rem; letter-spacing: 0.5px;">ADMIN PANEL</span>
            </div>

            <nav>
                <?php 
                $cur = basename($_SERVER['SCRIPT_NAME']);
                ?>
                <a href="<?= BASE_URL ?>/super_admin/dashboard.php" class="sidebar-link <?= ($cur === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-line"></i>Admin Home
                </a>
                <a href="<?= BASE_URL ?>/super_admin/operators.php" class="sidebar-link <?= ($cur === 'operators.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-tie"></i>Manage Operators
                </a>
                <a href="<?= BASE_URL ?>/super_admin/agents.php" class="sidebar-link <?= ($cur === 'agents.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-gear"></i>Manage Agents
                </a>
                <a href="<?= BASE_URL ?>/super_admin/bookings.php" class="sidebar-link <?= ($cur === 'bookings.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-ticket"></i>All Bookings
                </a>
                <a href="<?= BASE_URL ?>/super_admin/settlements.php" class="sidebar-link <?= ($cur === 'settlements.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-wallet"></i>Settlements
                </a>
                <a href="<?= BASE_URL ?>/super_admin/owner_control.php" class="sidebar-link <?= ($cur === 'owner_control.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-shield-halved"></i>Owner Controls
                </a>
                <a href="<?= BASE_URL ?>/super_admin/audit_logs.php" class="sidebar-link <?= ($cur === 'audit_logs.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i>Activity Logs
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
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/dashboard.php">Admin Home</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/operators.php">Operators</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/agents.php">Agents</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/bookings.php">Bookings</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/settlements.php">Settlements</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/owner_control.php">Owner Controls</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/audit_logs.php">Activity Logs</a></li>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item text-danger py-2" href="<?= BASE_URL ?>/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
                <div>
                    <h2 class="fw-bold text-white mb-0"><?= isset($page_title) ? htmlspecialchars($page_title) : 'Super Admin Portal' ?></h2>
                    <span class="text-secondary small">System Control Workspace</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="small text-secondary"><i class="fa-solid fa-circle-user text-indigo me-2"></i>Super Admin / Owner</span>
                </div>
            </div>
