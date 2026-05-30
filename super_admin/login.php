<?php
/**
 * Super Admin Login Page - Redirected to Universal Login
 */
require_once __DIR__ . '/../includes/auth_middleware.php';
header("Location: " . BASE_URL . "/login.php");
exit();
