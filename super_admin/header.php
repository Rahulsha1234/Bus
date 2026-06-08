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
<html lang="<?= CURRENT_LANG ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= isset($page_title) ? $page_title . ' - ' : '' ?><?= __('super_admin_portal_title', 'Super Admin Portal') ?></title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom Style -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css?v=1.0.5">
    <script>
        (function () {
            const theme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        })();
    </script>
    <script src="<?= BASE_URL ?>/assets/js/theme.js" defer></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.4.min.js"></script>
    <!-- Select2 & DataTables CSS -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">
    
    <style>
        /* Select2 theme override for dark and light modes */
        .select2-container--default .select2-selection--single {
            background-color: var(--bg-swift-input, #ffffff);
            border: 1px solid var(--border-swift-input, #dee2e6);
            border-radius: 12px;
            height: 48px;
            display: flex;
            align-items: center;
            padding-left: 8px;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }
        [data-theme="dark"] .select2-container--default .select2-selection--single {
            background-color: #1a2035 !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
            color: #ffffff !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: var(--text-swift-main, #212529);
            font-family: inherit;
        }
        [data-theme="dark"] .select2-container--default .select2-selection--single .select2-selection__rendered {
            color: #f8fafc !important;
        }
        .select2-container--default .select2-selection--single .select2-selection__arrow {
            height: 46px;
        }
        .select2-dropdown {
            background-color: var(--bg-swift-dropdown, #ffffff);
            border: 1px solid var(--border-swift-dropdown, #dee2e6);
            border-radius: 12px;
            z-index: 9999;
        }
        [data-theme="dark"] .select2-dropdown {
            background-color: #131a30 !important;
            border-color: rgba(255, 255, 255, 0.15) !important;
            color: #ffffff !important;
        }
        .select2-container--default .select2-results__option--highlighted[aria-selected] {
            background-color: #198754 !important;
            color: white !important;
        }
        .select2-container--default .select2-search--dropdown .select2-search__field {
            border: 1px solid var(--border-swift-input, #dee2e6);
            border-radius: 8px;
            background-color: var(--bg-swift-input, #ffffff);
            color: var(--text-swift-main, #212529);
            padding: 8px;
        }
        [data-theme="dark"] .select2-container--default .select2-search--dropdown .select2-search__field {
            background-color: #1a2035 !important;
            border-color: rgba(255, 255, 255, 0.12) !important;
            color: #ffffff !important;
        }
        .select2-container--default .select2-results__option {
            padding: 10px 14px;
        }
        [data-theme="dark"] .select2-container--default .select2-results__option[aria-selected="true"] {
            background-color: rgba(25, 135, 84, 0.2) !important;
            color: #ffffff !important;
        }
        [data-theme="dark"] .select2-container--default .select2-results__option {
            color: #e2e8f0;
        }
        /* DataTables Custom theme styling to match clean modern UI */
        .dataTables_wrapper .dataTables_paginate .paginate_button {
            padding: 0.3em 0.8em !important;
            border-radius: 8px !important;
        }
        .dataTables_wrapper .dataTables_filter input {
            border-radius: 8px !important;
            padding: 5px 10px !important;
        }
        [data-theme="dark"] .dataTables_wrapper {
            color: #f8fafc !important;
        }
        [data-theme="dark"] table.dataTable {
            border-color: rgba(255, 255, 255, 0.08) !important;
        }
        /* Input group support for Select2 */
        .input-group > .select2-container--default {
            flex: 1 1 auto;
            width: 1% !important;
            display: flex !important;
            flex-direction: column;
            justify-content: center;
        }
        .input-group > .select2-container--default .select2-selection--single {
            border-top-left-radius: 0 !important;
            border-bottom-left-radius: 0 !important;
            border-top-right-radius: 12px !important;
            border-bottom-right-radius: 12px !important;
            border-left: 0 !important;
            height: 48px !important;
            width: 100%;
        }
        .input-group > .input-group-text {
            height: 48px !important;
        }
    </style>
    
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
                <span class="text-secondary small font-monospace" style="font-size: 0.7rem; letter-spacing: 0.5px;"><?= __('admin_panel_sub_lbl', 'ADMIN PANEL') ?></span>
            </div>

            <nav>
                <?php 
                $cur = basename($_SERVER['SCRIPT_NAME']);
                ?>
                <a href="<?= BASE_URL ?>/super_admin/dashboard.php" class="sidebar-link <?= ($cur === 'dashboard.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-line"></i><?= __('nav_admin_home', 'Admin Home') ?>
                </a>
                <a href="<?= BASE_URL ?>/super_admin/operators.php" class="sidebar-link <?= ($cur === 'operators.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-user-tie"></i><?= __('nav_manage_operators', 'Manage Operators') ?>
                </a>
                <a href="<?= BASE_URL ?>/super_admin/agents.php" class="sidebar-link <?= ($cur === 'agents.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-users-gear"></i><?= __('nav_manage_agents', 'Manage Agents') ?>
                </a>
                <a href="<?= BASE_URL ?>/super_admin/operations.php" class="sidebar-link <?= ($cur === 'operations.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-wrench"></i><?= __('nav_manage_operations', 'Manage Operations') ?>
                </a>
                <a href="<?= BASE_URL ?>/super_admin/bookings.php" class="sidebar-link <?= ($cur === 'bookings.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-ticket"></i><?= __('nav_all_bookings', 'All Bookings') ?>
                </a>
                <a href="<?= BASE_URL ?>/super_admin/settlements.php" class="sidebar-link <?= ($cur === 'settlements.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-wallet"></i><?= __('nav_settlements', 'Settlements') ?>
                </a>
                <a href="<?= BASE_URL ?>/super_admin/owner_control.php" class="sidebar-link <?= ($cur === 'owner_control.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-shield-halved"></i><?= __('nav_owner_controls', 'Owner Controls') ?>
                </a>
                <a href="<?= BASE_URL ?>/super_admin/audit_logs.php" class="sidebar-link <?= ($cur === 'audit_logs.php') ? 'active' : '' ?>">
                    <i class="fa-solid fa-clock-rotate-left"></i><?= __('nav_activity_logs', 'Activity Logs') ?>
                </a>
                
                <hr class="border-secondary my-4">
                
                <a href="<?= BASE_URL ?>/logout.php" class="sidebar-link text-danger">
                    <i class="fa-solid fa-right-from-bracket"></i><?= __('nav_sign_out', 'Logout') ?>
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
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/dashboard.php"><?= __('nav_admin_home', 'Admin Home') ?></a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/operators.php"><?= __('nav_operators_short', 'Operators') ?></a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/agents.php"><?= __('nav_manage_agents', 'Agents') ?></a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/operations.php"><?= __('nav_manage_operations', 'Manage Operations') ?></a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/bookings.php"><?= __('nav_bookings_short', 'Bookings') ?></a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/settlements.php"><?= __('nav_settlements', 'Settlements') ?></a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/owner_control.php"><?= __('nav_owner_controls', 'Owner Controls') ?></a></li>
                        <li><a class="dropdown-item py-2" href="<?= BASE_URL ?>/super_admin/audit_logs.php"><?= __('nav_activity_logs', 'Activity Logs') ?></a></li>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <!-- Mobile Language Switcher Items -->
                        <li><a class="dropdown-item py-2 rounded <?= CURRENT_LANG === 'en' ? 'text-success fw-bold' : '' ?>" href="?lang=en">English</a></li>
                        <li><a class="dropdown-item py-2 rounded <?= CURRENT_LANG === 'hi' ? 'text-success fw-bold' : '' ?>" href="?lang=hi">हिन्दी</a></li>
                        <li><a class="dropdown-item py-2 rounded <?= CURRENT_LANG === 'ne' ? 'text-success fw-bold' : '' ?>" href="?lang=ne">नेपाली</a></li>
                        <li><hr class="dropdown-divider border-secondary"></li>
                        <li><a class="dropdown-item text-danger py-2" href="<?= BASE_URL ?>/logout.php"><?= __('nav_sign_out', 'Logout') ?></a></li>
                    </ul>
                </div>
            </div>
            
            <div class="d-flex justify-content-between align-items-center mb-5 flex-wrap gap-3">
                <div>
                    <h2 class="fw-bold text-white mb-0"><?= isset($page_title) ? htmlspecialchars($page_title) : __('super_admin_portal_title', 'Super Admin Portal') ?></h2>
                    <span class="text-secondary small"><?= __('system_control_workspace', 'System Control Workspace') ?></span>
                </div>
                <div class="d-flex align-items-center gap-3">
                    <!-- Language Switcher Dropdown -->
                    <div class="dropdown me-2">
                        <button class="btn btn-secondary-glass py-2 px-3 d-flex align-items-center gap-2" type="button"
                            id="langMenuButton" data-bs-toggle="dropdown" aria-expanded="false"
                            style="border-radius: 10px; font-weight: 500; font-size: 0.9rem;">
                            <i class="fa-solid fa-globe text-success"></i>
                            <span>
                                <?php
                                if (CURRENT_LANG === 'hi') echo 'हिन्दी';
                                elseif (CURRENT_LANG === 'ne') echo 'नेपाली';
                                else echo 'English';
                                ?>
                            </span>
                            <i class="fa-solid fa-chevron-down small opacity-75" style="font-size: 0.75rem;"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end glass-card mt-2 p-2 border-0 shadow-lg"
                            aria-labelledby="langMenuButton" style="background: var(--bg-card); border: 1px solid var(--border-glass);">
                            <li><a class="dropdown-item py-2 rounded <?= CURRENT_LANG === 'en' ? 'active bg-success text-white' : '' ?>" href="?lang=en">English</a></li>
                            <li><a class="dropdown-item py-2 rounded <?= CURRENT_LANG === 'hi' ? 'active bg-success text-white' : '' ?>" href="?lang=hi">हिन्दी</a></li>
                            <li><a class="dropdown-item py-2 rounded <?= CURRENT_LANG === 'ne' ? 'active bg-success text-white' : '' ?>" href="?lang=ne">नेपाली</a></li>
                        </ul>
                    </div>

                    <span class="small text-secondary"><i class="fa-solid fa-circle-user text-indigo me-2"></i><?= __('super_admin_owner_role', 'Super Admin / Owner') ?></span>
                </div>
            </div>
