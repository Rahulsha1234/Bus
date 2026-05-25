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
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Style -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
</head>
<body>

    <!-- Dynamic Ticker Announcement -->
    <?php if (!empty($GLOBALS['custom_notice'])): ?>
        <div class="notice-marquee">
            <span><i class="fa-solid fa-bullhorn text-warning me-2"></i><?= htmlspecialchars($GLOBALS['custom_notice']) ?></span>
        </div>
    <?php endif; ?>

    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark navbar-swift sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="<?= BASE_URL ?>/index.php">
                <i class="fa-solid fa-bus text-indigo me-2" style="font-size: 1.8rem; color: #818cf8;"></i>
                <span class="text-gradient"><?= SYSTEM_NAME ?></span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent" aria-controls="navbarContent" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarContent">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?= (basename($_SERVER['SCRIPT_NAME']) === 'index.php') ? 'active' : '' ?>" href="<?= BASE_URL ?>/index.php">Search Buses</a>
                    </li>
                </ul>
                
                <div class="d-flex align-items-center gap-3">
                    <?php if (is_logged_in()): ?>
                        <div class="dropdown">
                            <button class="btn btn-secondary-glass dropdown-toggle d-flex align-items-center gap-2 py-2" type="button" id="userMenuButton" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="fa-solid fa-circle-user text-indigo" style="font-size: 1.2rem; color: #818cf8;"></i>
                                <?= htmlspecialchars($user['username']) ?> 
                                <span class="badge bg-secondary ms-1 text-uppercase" style="font-size: 0.7rem;"><?= htmlspecialchars($user['role']) ?></span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end dropdown-menu-dark glass-card mt-2 p-2 border-0" aria-labelledby="userMenuButton">
                                <?php if ($user['role'] === 'admin'): ?>
                                    <li><a class="dropdown-item rounded py-2" href="<?= BASE_URL ?>/admin/dashboard.php"><i class="fa-solid fa-chart-line me-2 text-indigo"></i>Admin Panel</a></li>
                                <?php elseif ($user['role'] === 'agent'): ?>
                                    <li><a class="dropdown-item rounded py-2" href="<?= BASE_URL ?>/agent/dashboard.php"><i class="fa-solid fa-briefcase me-2 text-indigo"></i>Agent Panel</a></li>
                                <?php endif; ?>
                                <li><hr class="dropdown-divider border-secondary"></li>
                                <li><a class="dropdown-item text-danger rounded py-2" href="<?= BASE_URL ?>/logout.php"><i class="fa-solid fa-right-from-bracket me-2"></i>Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <a href="<?= BASE_URL ?>/login.php" class="nav-link text-white me-2">Login</a>
                        <a href="<?= BASE_URL ?>/register.php" class="btn-primary-gradient py-2">Staff Register</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>

    <div class="container my-5">
