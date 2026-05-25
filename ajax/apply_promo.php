<?php
/**
 * AJAX Promo Code / Discount Handler
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit();
}

$code = trim($_POST['promo_code'] ?? '');
$subtotal = floatval($_POST['subtotal'] ?? 0.00);

if ($subtotal <= 0) {
    echo json_encode(['success' => false, 'message' => 'No fare calculated to apply discount.']);
    exit();
}

// Promo logic helper
$code = strtoupper($code);
$discount = 0.00;

if ($code === 'SAVE10') {
    $discount = $subtotal * 0.10;
} elseif ($code === 'FLAT100') {
    $discount = 100.00;
} elseif ($code === 'SUPER50') {
    $discount = $subtotal * 0.50;
} elseif ($code === 'FREE') {
    $discount = $subtotal;
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid promo code.']);
    exit();
}

// Cap discount to never exceed subtotal price
if ($discount > $subtotal) {
    $discount = $subtotal;
}

$final_fare = $subtotal - $discount;

echo json_encode([
    'success' => true,
    'promo_code' => $code,
    'discount' => round($discount, 2),
    'final_fare' => round($final_fare, 2),
    'message' => 'Promo code applied successfully!'
]);
exit();
