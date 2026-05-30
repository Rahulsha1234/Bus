<?php
/**
 * Auth Middleware & Inclusion Bundle
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/../config/security.php';

// Helper to check if a user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// Get logged-in user profile / details
function get_logged_user() {
    return [
        'id' => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? 'Guest',
        'role' => $_SESSION['user_role'] ?? 'customer',
        'email' => $_SESSION['user_email'] ?? ''
    ];
}
