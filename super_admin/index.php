<?php
/**
 * Super Admin Portal Entry Redirector
 */
require_once __DIR__ . '/../includes/auth_middleware.php';

// Redirect to dashboard if logged in as super_admin, otherwise redirect to super admin login page
if (is_logged_in() && isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'super_admin') {
    header("Location: " . BASE_URL . "/super_admin/dashboard.php");
} else {
    header("Location: " . BASE_URL . "/super_admin/login.php");
}
exit();
