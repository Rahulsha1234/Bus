<?php
/**
 * Main Layout Header
 */
require_once __DIR__ . '/auth_middleware.php';
$user = get_logged_user();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?><?= SYSTEM_NAME ?></title>
    <!-- Google Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
        rel="stylesheet">
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
</head>

<body>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-swift fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center me-4" href="<?= BASE_URL ?>/index.php">
                <i class="fa-solid fa-bus text-success me-2" style="font-size: 1.6rem; color: #198754;"></i>
                <span class="swift-brand-text">SwiftBus</span>
            </a>
            
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse"
                data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false"
                aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarContent">
                <!-- Centered Glass Search Input -->
                <div class="navbar-search-wrapper mx-auto my-2 my-lg-0">
                    <form action="<?= BASE_URL ?>/search.php" method="GET" class="position-relative d-flex align-items-center">
                        <i class="fa-solid fa-magnifying-glass search-icon-left"></i>
                        <input type="text" name="q" class="form-control navbar-search-input" placeholder="Search destinations, routes, buses..." aria-label="Search">
                    </form>
                </div>

                <div class="d-flex align-items-center gap-4 navbar-right-actions">
                    <a href="<?= BASE_URL ?>/bookings.php" class="nav-action-link d-flex flex-column align-items-center justify-content-center">
                        <i class="fa-solid fa-ticket-simple action-icon"></i>
                        <span class="action-label">My Trips</span>
                    </a>
                    
                    <a href="#" class="nav-action-link d-flex flex-column align-items-center justify-content-center">
                        <i class="fa-solid fa-headset action-icon"></i>
                        <span class="action-label">Support</span>
                    </a>

                    <div class="divider-vertical"></div>
                    <?php if (is_logged_in()): ?>
                        <?php
                        $notif_count = 0;
                        $notifs = [];
                        if (in_array($user['role'], ['admin', 'agent'])) {
                            try {
                                if ($user['role'] === 'admin') {
                                    $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE user_role = 'admin' AND user_id IS NULL AND is_read = 0");
                                    $cnt_stmt->execute();
                                    $notif_count = intval($cnt_stmt->fetchColumn());

                                    $list_stmt = $pdo->prepare("SELECT * FROM system_notifications WHERE user_role = 'admin' AND user_id IS NULL ORDER BY created_at DESC LIMIT 5");
                                    $list_stmt->execute();
                                    $notifs = $list_stmt->fetchAll();
                                } else {
                                    $cnt_stmt = $pdo->prepare("SELECT COUNT(*) FROM system_notifications WHERE user_role = 'agent' AND user_id = ? AND is_read = 0");
                                    $cnt_stmt->execute([$user['id']]);
                                    $notif_count = intval($cnt_stmt->fetchColumn());

                                    $list_stmt = $pdo->prepare("SELECT * FROM system_notifications WHERE user_role = 'agent' AND user_id = ? ORDER BY created_at DESC LIMIT 5");
                                    $list_stmt->execute([$user['id']]);
                                    $notifs = $list_stmt->fetchAll();
                                }
                            } catch (Exception $e) {
                                // Fallback silently
                            }
                        }
                        ?>
                        <?php if (in_array($user['role'], ['admin', 'agent'])): ?>
                            <div class="dropdown">
                                <button class="btn btn-secondary-glass position-relative py-2 px-3" type="button"
                                    id="notifMenuButton" data-bs-toggle="dropdown" aria-expanded="false"
                                    onclick="markNotificationsRead();">
                                    <i
                                        class="fa-solid fa-bell <?= ($notif_count > 0) ? 'text-warning text-opacity-75' : 'text-indigo' ?>"></i>
                                    <?php if ($notif_count > 0): ?>
                                        <span
                                            class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger"
                                            style="font-size:0.65rem;">
                                            <?= $notif_count ?>
                                        </span>
                                    <?php endif; ?>
                                </button>
                                <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark glass-card mt-2 p-2 border-0"
                                    aria-labelledby="notifMenuButton" style="width: 300px; font-size: 0.85rem;">
                                    <li
                                        class="dropdown-header text-white-50 border-bottom border-secondary border-opacity-10 pb-2 mb-2 d-flex justify-content-between align-items-center">
                                        <span>Notifications</span>
                                        <?php if ($notif_count > 0): ?>
                                            <span class="badge bg-warning text-dark" style="font-size:0.65rem;">New</span>
                                        <?php endif; ?>
                                    </li>
                                    <?php if (empty($notifs)): ?>
                                        <li class="text-center text-secondary py-3 small">No notifications found</li>
                                    <?php else: ?>
                                        <?php foreach ($notifs as $nt): ?>
                                            <li class="px-3 py-2 rounded mb-1 <?= ($nt['is_read'] == 0) ? 'bg-dark bg-opacity-30 border-start border-3 border-indigo' : '' ?>"
                                                style="white-space: normal;">
                                                <div class="text-white-50" style="font-size:0.8rem;">
                                                    <?= htmlspecialchars($nt['message']) ?></div>
                                                <div class="text-secondary" style="font-size:0.65rem;">
                                                    <?= date('d M, H:i', strtotime($nt['created_at'])) ?></div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        <?php endif; ?>

                        <div class="dropdown">
                            <button class="btn btn-secondary-glass p-0 border-0 profile-avatar-btn"
                                type="button" id="userMenuButton" data-bs-toggle="dropdown"
                                aria-expanded="false" aria-label="Account menu"
                                style="background: transparent; width: 38px; height: 38px; border-radius: 50%; display: flex; align-items: center; justify-content: center; position: relative;">
                                <span class="profile-avatar-initials">
                                    <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                </span>
                                <?php if (in_array($user['role'], ['admin', 'agent'])): ?>
                                    <span class="profile-role-dot" title="<?= ucfirst($user['role']) ?>"></span>
                                <?php endif; ?>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end glass-card mt-2 border-0 p-0 shadow-lg profile-dropdown"
                                aria-labelledby="userMenuButton" style="width: 260px; border-radius: 14px; overflow: hidden;">

                                <!-- Profile Header -->
                                <div class="profile-dropdown-header px-4 py-3 d-flex align-items-center gap-3">
                                    <div class="profile-avatar-lg">
                                        <?= strtoupper(substr($user['username'], 0, 1)) ?>
                                    </div>
                                    <div class="overflow-hidden">
                                        <div class="profile-name text-truncate"><?= htmlspecialchars($user['username']) ?></div>
                                        <span class="profile-role-badge"><?= ucfirst(htmlspecialchars($user['role'])) ?></span>
                                    </div>
                                </div>

                                <div class="profile-dropdown-divider"></div>

                                <!-- Actions -->
                                <div class="px-2 py-2">
                                    <?php if ($user['role'] === 'super_admin'): ?>
                                        <a class="profile-dropdown-item" href="<?= BASE_URL ?>/super_admin/dashboard.php">
                                            <span class="profile-item-icon"><i class="fa-solid fa-gauge-high"></i></span>
                                            <span>Admin Panel</span>
                                        </a>
                                    <?php elseif ($user['role'] === 'admin'): ?>
                                        <a class="profile-dropdown-item" href="<?= BASE_URL ?>/admin/dashboard.php">
                                            <span class="profile-item-icon"><i class="fa-solid fa-briefcase"></i></span>
                                            <span>Operator Panel</span>
                                        </a>
                                    <?php elseif ($user['role'] === 'agent'): ?>
                                        <a class="profile-dropdown-item" href="<?= BASE_URL ?>/agent/dashboard.php">
                                            <span class="profile-item-icon"><i class="fa-solid fa-briefcase"></i></span>
                                            <span>Agent Portal</span>
                                        </a>
                                    <?php endif; ?>

                                    <a class="profile-dropdown-item" href="<?= BASE_URL ?>/bookings.php">
                                        <span class="profile-item-icon"><i class="fa-solid fa-ticket"></i></span>
                                        <span>My Bookings</span>
                                    </a>

                                    <a class="profile-dropdown-item" href="<?= BASE_URL ?>/cancellations.php">
                                        <span class="profile-item-icon"><i class="fa-solid fa-rotate-left"></i></span>
                                        <span>Cancellations</span>
                                    </a>
                                </div>

                                <div class="profile-dropdown-divider"></div>

                                <div class="px-2 py-2">
                                    <a class="profile-dropdown-item profile-logout" href="<?= BASE_URL ?>/logout.php">
                                        <span class="profile-item-icon"><i class="fa-solid fa-right-from-bracket"></i></span>
                                        <span>Sign Out</span>
                                    </a>
                                </div>

                            </div>
                        </div>

                        <script>
                            function markNotificationsRead() {
                                $.ajax({
                                    url: '<?= BASE_URL ?>/ajax/read_notifications.php',
                                    type: 'POST',
                                    dataType: 'json',
                                    success: function (response) {
                                        if (response.success) {
                                            $('#notifMenuButton .badge').remove();
                                            $('#notifMenuButton i').removeClass('text-warning').addClass('text-indigo');
                                        }
                                    }
                                });
                            }
                        </script>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="nav-action-link d-flex flex-column align-items-center justify-content-center">
                            <i class="fa-solid fa-right-to-bracket action-icon"></i>
                            <span class="action-label">Login</span>
                        </a>
                        <a href="<?= BASE_URL ?>/register.php" class="btn-primary-gradient py-2 px-3 ms-2">Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <script>
        $(document).ready(function () {
            <?php
            $is_root_index = (basename($_SERVER['SCRIPT_NAME']) === 'index.php' 
                && strpos($_SERVER['SCRIPT_NAME'], '/admin/') === false 
                && strpos($_SERVER['SCRIPT_NAME'], '/agent/') === false 
                && strpos($_SERVER['SCRIPT_NAME'], '/super_admin/') === false);
            ?>
            const isIndexPage = <?= $is_root_index ? 'true' : 'false' ?>;
            const $navbar = $('.navbar-swift');
            let ticking = false;

            // If not on the homepage (index.php), force the header to have a solid background (scrolled mode)
            if (!isIndexPage) {
                $navbar.addClass('scrolled');
            }

            $(window).on('scroll', function () {
                if (!isIndexPage) return; // Keep it solid white always on other pages

                if (!ticking) {
                    window.requestAnimationFrame(function () {
                        let st = $(window).scrollTop();
                        if (st <= 50) {
                            $navbar.removeClass('scrolled');
                        } else {
                            $navbar.addClass('scrolled');
                        }
                        ticking = false;
                    });
                    ticking = true;
                }
            });
        });
    </script>

    <?php if (!(basename($_SERVER['SCRIPT_NAME']) === 'index.php' && strpos($_SERVER['SCRIPT_NAME'], '/admin/') === false && strpos($_SERVER['SCRIPT_NAME'], '/agent/') === false && strpos($_SERVER['SCRIPT_NAME'], '/super_admin/') === false)): ?>
        <div class="container-fluid px-md-5 my-5" style="margin-top: 5rem !important;">
    <?php endif; ?>