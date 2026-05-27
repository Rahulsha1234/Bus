<?php
/**
 * Agent Portal Header
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

// Force role protection
require_role('agent');

$user = get_logged_user();

// Fetch agent's profile details to find their parent operator admin_id
try {
    $agent_stmt = $pdo->prepare("SELECT * FROM agent_profiles WHERE user_id = ? LIMIT 1");
    $agent_stmt->execute([$user['id']]);
    $agent_profile = $agent_stmt->fetch();
    $parent_admin_id = $agent_profile ? intval($agent_profile['admin_id']) : 0;
    
    if ($parent_admin_id === 0) {
        $fallback_stmt = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'approved' ORDER BY id ASC LIMIT 1");
        $fallback_id = $fallback_stmt->fetchColumn();
        if ($fallback_id !== false) {
            $parent_admin_id = intval($fallback_id);
            if ($agent_profile) {
                $update_stmt = $pdo->prepare("UPDATE agent_profiles SET admin_id = ? WHERE user_id = ?");
                $update_stmt->execute([$parent_admin_id, $user['id']]);
            }
        }
    }
} catch (Exception $e) {
    $parent_admin_id = 0;
}
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
        .sidebar-agent {
            min-height: 100dvh;
            border-right: 1px solid var(--border-glass);
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(10px);
            padding: 2rem 1.5rem;
        }
        .sidebar-link {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255,255,255,0.55);
            text-decoration: none;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s ease;
            margin-bottom: 0.25rem;
            border-left: 3px solid transparent;
        }
        .sidebar-link i {
            width: 18px;
            text-align: center;
            font-size: 0.95rem;
        }
        .sidebar-link:hover {
            color: rgba(255,255,255,0.9);
            background: rgba(255, 255, 255, 0.06);
            border-left-color: rgba(129,140,248,0.5);
            padding-left: calc(1rem - 3px);
        }
        .sidebar-link.active {
            color: #ffffff;
            background: rgba(129, 140, 248, 0.15);
            border-left: 3px solid #818cf8;
            padding-left: calc(1rem - 3px);
            font-weight: 600;
        }
        .sidebar-link.active i {
            color: #818cf8;
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
                <span class="text-secondary" style="font-size: 0.75rem; letter-spacing: 0.5px;">AGENT PARTNER</span>
            </div>

            <nav>
                <?php
                $cur = basename($_SERVER['SCRIPT_NAME']);
                ?>
                <a href="<?= BASE_URL ?>/agent/dashboard.php" class="sidebar-link <?= ($cur === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie"></i>Dashboard
                </a>
                <a href="<?= BASE_URL ?>/agent/search.php" class="sidebar-link <?= ($cur === 'search.php' || $cur === 'book.php' || $cur === 'checkout.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-magnifying-glass"></i>Search & Book
                </a>
                <a href="<?= BASE_URL ?>/agent/bookings.php" class="sidebar-link <?= ($cur === 'bookings.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-receipt"></i>My Bookings
                </a>
                
                <hr class="border-secondary my-4">
                
                <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link text-danger">
                    <i class="fa-solid fa-right-from-bracket"></i>Logout
                </a>
            </nav>
        </div>

        <!-- Main Display Panel -->
        <div class="col-md-9 col-lg-10 py-5 px-md-5 main-display-panel">
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
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/agent/search.php">Search & Book</a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/agent/bookings.php">My Bookings</a></li>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item text-danger py-2" href="<?= BASE_URL ?>/logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
                <div>
                    <h2 class="fw-bold text-white mb-0"><?= isset($page_title) ? htmlspecialchars($page_title) : 'Agent Portal' ?></h2>
                    <span class="text-secondary small">Agency Workspace</span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <span class="small text-secondary"><i class="fa-solid fa-briefcase text-indigo me-2"></i><?= htmlspecialchars($agent_profile['agency_name'] ?? $user['username']) ?></span>
                </div>
            </div>
