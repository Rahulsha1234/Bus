<?php
/**
 * AJAX Agent Wallet Recharge Handler
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config/security.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'agent') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Only agents can recharge wallets.']);
    exit();
}

$csrf = $_POST['csrf_token'] ?? '';
if (!verify_csrf_token($csrf)) {
    echo json_encode(['success' => false, 'message' => 'Security validation failed.']);
    exit();
}

$amount = floatval($_POST['amount'] ?? 0.00);
$razorpay_payment_id = trim($_POST['razorpay_payment_id'] ?? '');
$razorpay_order_id = trim($_POST['razorpay_order_id'] ?? '');
$razorpay_signature = trim($_POST['razorpay_signature'] ?? '');

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid recharge amount.']);
    exit();
}

if (empty($razorpay_payment_id) || empty($razorpay_order_id) || empty($razorpay_signature)) {
    echo json_encode(['success' => false, 'message' => 'Razorpay payment details are missing.']);
    exit();
}

try {
    $pdo->beginTransaction();

    $agent_id = $_SESSION['user_id'];
    
    // Row lock the wallet
    $wallet_stmt = $pdo->prepare("SELECT id, balance, status FROM agent_wallets WHERE agent_id = ? FOR UPDATE");
    $wallet_stmt->execute([$agent_id]);
    $wallet = $wallet_stmt->fetch();

    if (!$wallet) {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Agent wallet not initialized.']);
        exit();
    }

    if ($wallet['status'] === 'frozen') {
        $pdo->rollBack();
        echo json_encode(['success' => false, 'message' => 'Recharge failed. Your wallet is currently frozen.']);
        exit();
    }

    $balance_before = floatval($wallet['balance']);
    $balance_after = $balance_before + $amount;

    // Log the Razorpay recharge
    $recharge_stmt = $pdo->prepare("
        INSERT INTO wallet_recharges (wallet_id, amount, razorpay_payment_id, razorpay_order_id, razorpay_signature, status)
        VALUES (?, ?, ?, ?, ?, 'success')
    ");
    $recharge_stmt->execute([
        $wallet['id'],
        $amount,
        $razorpay_payment_id,
        $razorpay_order_id,
        $razorpay_signature
    ]);
    $recharge_id = $pdo->lastInsertId();

    // Update wallet balance
    $update_wallet = $pdo->prepare("UPDATE agent_wallets SET balance = ? WHERE id = ?");
    $update_wallet->execute([$balance_after, $wallet['id']]);

    // Write to ledger
    $ledger_stmt = $pdo->prepare("
        INSERT INTO wallet_transactions (
            wallet_id, transaction_type, amount, balance_before, balance_after, 
            reference_type, reference_id, remarks, created_by
        ) VALUES (?, 'recharge', ?, ?, ?, 'recharge', ?, ?, ?)
    ");
    $remarks = "Wallet Recharge via Razorpay. Pay ID: $razorpay_payment_id";
    $ledger_stmt->execute([
        $wallet['id'],
        $amount,
        $balance_before,
        $balance_after,
        $recharge_id,
        $remarks,
        $agent_id
    ]);

    // Log activity
    log_activity($pdo, $agent_id, 'WALLET_RECHARGE', "Wallet recharged with ₹$amount. New Balance: ₹$balance_after. Razorpay Payment ID: $razorpay_payment_id");

    $pdo->commit();
    echo json_encode([
        'success' => true,
        'message' => 'Wallet recharged successfully!',
        'new_balance' => $balance_after
    ]);
    exit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Recharge transaction failed: ' . $e->getMessage()]);
    exit();
}
