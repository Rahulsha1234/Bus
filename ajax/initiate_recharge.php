<?php
/**
 * Initiate Agent Wallet Recharge Session via Payment Gateway
 */
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../config/security.php';
require_once __DIR__ . '/../config/PaymentGateway.php';

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

if ($amount <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid recharge amount.']);
    exit();
}

try {
    $agent_id = $_SESSION['user_id'];
    
    // Fetch wallet
    $wallet_stmt = $pdo->prepare("SELECT id, status FROM agent_wallets WHERE agent_id = ?");
    $wallet_stmt->execute([$agent_id]);
    $wallet = $wallet_stmt->fetch();

    if (!$wallet) {
        echo json_encode(['success' => false, 'message' => 'Agent wallet not initialized.']);
        exit();
    }

    if ($wallet['status'] === 'frozen') {
        echo json_encode(['success' => false, 'message' => 'Your wallet is currently frozen.']);
        exit();
    }

    $pdo->beginTransaction();

    // Create a pending wallet recharge record
    $recharge_stmt = $pdo->prepare("
        INSERT INTO wallet_recharges (wallet_id, amount, status)
        VALUES (?, ?, 'pending')
    ");
    $recharge_stmt->execute([$wallet['id'], $amount]);
    $rechargeId = $pdo->lastInsertId();

    // Initialize Payment Gateway Session
    $gateway = new PaymentGateway($pdo);
    $payRec = $gateway->createPayment('wallet_recharge', null, $amount, null, $rechargeId);
    $initPay = $gateway->initiatePayment($payRec['payment_reference'], $payRec['attempt_reference'], $amount, $agent_id);

    if ($initPay['success'] && isset($initPay['url'])) {
        // Save gateway tracking reference on the wallet recharge record
        $updRec = $pdo->prepare("UPDATE wallet_recharges SET razorpay_order_id = ? WHERE id = ?");
        $updRec->execute([$payRec['payment_reference'], $rechargeId]);

        $pdo->commit();
        echo json_encode([
            'success' => true,
            'redirect' => true,
            'url' => $initPay['url']
        ]);
        exit();
    } else {
        throw new Exception($initPay['message'] ?? 'Gateway initiation failed.');
    }

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo json_encode(['success' => false, 'message' => 'Failed to initialize recharge transaction: ' . $e->getMessage()]);
    exit();
}
