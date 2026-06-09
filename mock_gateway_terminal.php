<?php
/**
 * Developer Mock Payment Gateway Terminal
 * Premium User Interface to simulate PhonePe Gateway success, pending, failed, cancelled.
 */
require_once __DIR__ . '/includes/auth_middleware.php';

$paymentRef = $_GET['payment_reference'] ?? '';
$attemptRef = $_GET['attempt_reference'] ?? '';

if (empty($paymentRef)) {
    die("Error: Payment Reference is required.");
}

// Fetch Payment Details
$stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_reference = ?");
$stmt->execute([$paymentRef]);
$payment = $stmt->fetch();

if (!$payment) {
    die("Error: Invalid Payment Reference.");
}

$page_title = "Developer Mock Payment Terminal";
require_once __DIR__ . '/includes/header.php';
?>

<div class="row justify-content-center mt-5">
    <div class="col-md-6 col-lg-5">
        <div class="glass-card p-5 border border-secondary border-opacity-30 shadow-2xl" style="border-radius: 24px;">
            <div class="text-center mb-4">
                <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-20 mb-3 px-3 py-2 rounded-pill">
                    <i class="fa-solid fa-code me-2"></i>Developer Mode: Mock Gateway Enabled
                </span>
                <h3 class="fw-bold mb-1"><i class="fa-solid fa-shield-halved text-indigo me-2"></i>SwiftPay Secure Terminal</h3>
                <span class="text-secondary small">Simulating payment processing for PhonePe Gateway</span>
            </div>

            <div class="p-4 rounded-4 border border-secondary border-opacity-10 mb-4" style="background: var(--bg-secondary);">
                <div class="d-flex justify-content-between text-secondary mb-2 small">
                    <span>Merchant Reference</span>
                    <span class="font-monospace fw-semibold"><?= htmlspecialchars($paymentRef) ?></span>
                </div>
                <div class="d-flex justify-content-between text-secondary mb-2 small">
                    <span>Transaction Attempt</span>
                    <span class="font-monospace fw-semibold"><?= htmlspecialchars($attemptRef) ?></span>
                </div>
                <div class="d-flex justify-content-between text-secondary mb-2 small">
                    <span>Environment</span>
                    <span class="text-uppercase font-semibold"><?= htmlspecialchars($payment['environment']) ?></span>
                </div>
                <?php if ($payment['booking_reference']): ?>
                    <div class="d-flex justify-content-between text-secondary mb-2 small">
                        <span>Booking Reference</span>
                        <span class="font-monospace fw-semibold"><?= htmlspecialchars($payment['booking_reference']) ?></span>
                    </div>
                <?php elseif ($payment['wallet_recharge_id']): ?>
                    <div class="d-flex justify-content-between text-secondary mb-2 small">
                        <span>Wallet Recharge ID</span>
                        <span class="font-monospace fw-semibold">#<?= htmlspecialchars($payment['wallet_recharge_id']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="border-top border-secondary border-opacity-20 pt-3 mt-3 d-flex justify-content-between align-items-center">
                    <span class="text-secondary fw-semibold">Amount to Charge</span>
                    <span class="text-indigo fw-bold fs-4" style="color: var(--accent-primary) !important;">₹<?= number_format($payment['amount'], 2) ?></span>
                </div>
            </div>

            <h6 class="text-secondary small fw-semibold mb-3">Simulate Payment Gateway Responses</h6>
            <div class="d-grid gap-3">
                <!-- Success -->
                <form action="<?= BASE_URL ?>/payment_callback.php" method="POST">
                    <input type="hidden" name="merchantTransactionId" value="<?= htmlspecialchars($paymentRef) ?>">
                    <input type="hidden" name="status" value="success">
                    <input type="hidden" name="transactionId" value="MOCK_TXN_<?= bin2hex(random_bytes(6)) ?>">
                    <input type="hidden" name="amount" value="<?= intval(round($payment['amount'] * 100)) ?>">
                    <button type="submit" class="btn w-100 py-3 rounded-3 fw-bold text-start d-flex justify-content-between align-items-center" style="background: rgba(25, 135, 84, 0.2); border: 1px solid #198754; color: #198754 !important;">
                        <span><i class="fa-solid fa-circle-check me-2"></i>Simulate Success (PAYMENT_SUCCESS)</span>
                        <i class="fa-solid fa-chevron-right small"></i>
                    </button>
                </form>

                <!-- Failure -->
                <form action="<?= BASE_URL ?>/payment_callback.php" method="POST">
                    <input type="hidden" name="merchantTransactionId" value="<?= htmlspecialchars($paymentRef) ?>">
                    <input type="hidden" name="status" value="failed">
                    <input type="hidden" name="transactionId" value="MOCK_TXN_<?= bin2hex(random_bytes(6)) ?>">
                    <input type="hidden" name="amount" value="<?= intval(round($payment['amount'] * 100)) ?>">
                    <button type="submit" class="btn w-100 py-3 rounded-3 fw-bold text-start d-flex justify-content-between align-items-center" style="background: rgba(220, 53, 69, 0.2); border: 1px solid #dc3545; color: #dc3545 !important;">
                        <span><i class="fa-solid fa-circle-xmark me-2"></i>Simulate Failure (PAYMENT_ERROR)</span>
                        <i class="fa-solid fa-chevron-right small"></i>
                    </button>
                </form>

                <!-- Pending -->
                <form action="<?= BASE_URL ?>/payment_callback.php" method="POST">
                    <input type="hidden" name="merchantTransactionId" value="<?= htmlspecialchars($paymentRef) ?>">
                    <input type="hidden" name="status" value="pending">
                    <input type="hidden" name="transactionId" value="MOCK_TXN_<?= bin2hex(random_bytes(6)) ?>">
                    <input type="hidden" name="amount" value="<?= intval(round($payment['amount'] * 100)) ?>">
                    <button type="submit" class="btn w-100 py-3 rounded-3 fw-bold text-start d-flex justify-content-between align-items-center" style="background: rgba(255, 193, 7, 0.2); border: 1px solid #ffc107; color: #ffc107 !important;">
                        <span><i class="fa-solid fa-spinner fa-spin me-2"></i>Simulate Pending / Timeout</span>
                        <i class="fa-solid fa-chevron-right small"></i>
                    </button>
                </form>

                <!-- Cancelled -->
                <form action="<?= BASE_URL ?>/payment_callback.php" method="POST">
                    <input type="hidden" name="merchantTransactionId" value="<?= htmlspecialchars($paymentRef) ?>">
                    <input type="hidden" name="status" value="cancelled">
                    <input type="hidden" name="transactionId" value="MOCK_TXN_<?= bin2hex(random_bytes(6)) ?>">
                    <input type="hidden" name="amount" value="<?= intval(round($payment['amount'] * 100)) ?>">
                    <button type="submit" class="btn w-100 py-3 rounded-3 fw-bold text-start d-flex justify-content-between align-items-center" style="background: var(--bg-secondary); border: 1px solid var(--border-glass); color: var(--text-main) !important;">
                        <span><i class="fa-solid fa-ban me-2"></i>Simulate User Cancelled</span>
                        <i class="fa-solid fa-chevron-right small"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
