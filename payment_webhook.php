<?php
/**
 * Payment Gateway Webhook Endpoint
 * Processes background server-to-server notifications securely from PhonePe or Mock triggers.
 */
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/config/PaymentGateway.php';

header('Content-Type: application/json');

$gateway = new PaymentGateway($pdo);

// 1. Fetch raw payload
$rawInput = file_get_contents('php://input');
$headers = json_encode(getallheaders());

// Decode request payload
$payloadData = json_decode($rawInput, true);
$base64Response = $payloadData['response'] ?? '';

if (empty($base64Response)) {
    echo json_encode(['success' => false, 'message' => 'Empty payload response.']);
    exit();
}

// 2. Security Signature Verification (Only if NOT mock mode)
if (!$gateway->isMockMode()) {
    $xVerifyHeader = $_SERVER['HTTP_X_VERIFY'] ?? '';
    if (empty($xVerifyHeader)) {
        $gateway->logAudit('WEBHOOK_SECURITY_ALERT', "Webhook received without X-VERIFY header. IP: " . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Unauthorized signature missing.']);
        exit();
    }

    $expectedSignature = hash('sha256', $base64Response . $gateway->getConfig('salt_key')) . '###' . $gateway->getConfig('salt_index');
    if ($xVerifyHeader !== $expectedSignature) {
        $gateway->logAudit('WEBHOOK_SIGNATURE_MISMATCH', "Webhook signature verification failed. IP: " . ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0'));
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Signature verification failed.']);
        exit();
    }
}

// 3. Decode payload
$decodedJson = base64_decode($base64Response);
$data = json_decode($decodedJson, true);

$paymentRef = $data['data']['merchantTransactionId'] ?? '';
if (empty($paymentRef)) {
    echo json_encode(['success' => false, 'message' => 'Invalid transaction reference.']);
    exit();
}

// Log Webhook Record
$gateway->logWebhook($paymentRef, $decodedJson, $headers);

// Check if transaction exists
$stmt = $pdo->prepare("SELECT * FROM payments WHERE payment_reference = ?");
$stmt->execute([$paymentRef]);
$payment = $stmt->fetch();

if (!$payment) {
    echo json_encode(['success' => false, 'message' => 'Payment reference not found.']);
    exit();
}

$code = $data['code'] ?? '';
$gatewayTxnId = $data['data']['transactionId'] ?? '';
$status = 'pending';

if ($code === 'PAYMENT_SUCCESS') {
    $status = 'success';
} elseif (in_array($code, ['PAYMENT_ERROR', 'PAYMENT_DECLINED', 'TIMED_OUT'])) {
    $status = 'failed';
}

// Prevent double processing if already successful
if ($payment['status'] === 'success' && $status !== 'refunded') {
    echo json_encode(['success' => true, 'message' => 'Already processed.']);
    exit();
}

// 4. Update status and process booking/recharge
$updStatus = $gateway->updatePaymentStatus($paymentRef, $status, $gatewayTxnId, $decodedJson);

if ($updStatus && $status === 'success') {
    $bookingRef = $payment['booking_reference'];
    $walletRechargeId = $payment['wallet_recharge_id'];

    if ($bookingRef) {
        // Confirm booking
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_reference = ? FOR UPDATE");
            $stmt->execute([$bookingRef]);
            $booking = $stmt->fetch();

            if ($booking && $booking['payment_status'] !== 'paid') {
                $upd = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
                $upd->execute([$booking['id']]);

                // Fetch seats
                $seats_stmt = $pdo->prepare("SELECT seat_number FROM booking_seats WHERE booking_id = ?");
                $seats_stmt->execute([$booking['id']]);
                $seats = $seats_stmt->fetchAll(PDO::FETCH_COLUMN);

                $seat_placeholders = implode(',', array_fill(0, count($seats), '?'));
                $upd_seats = $pdo->prepare("
                    UPDATE trip_seats 
                    SET status = 'booked', hold_expires_at = NULL, locked_by_session = NULL, locked_at = NULL
                    WHERE trip_id = ? AND seat_number IN ($seat_placeholders)
                ");
                $upd_seats->execute(array_merge([$booking['trip_id']], $seats));

                $gateway->logAudit('WEBHOOK_BOOKING_CONFIRMED', "Verified payment success via Webhook for booking $bookingRef. Ref: $paymentRef");
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Webhook transaction confirmation failed: " . $e->getMessage());
        }
    } elseif ($walletRechargeId) {
        // Confirm Recharge
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM wallet_recharges WHERE id = ? FOR UPDATE");
            $stmt->execute([$walletRechargeId]);
            $recharge = $stmt->fetch();

            if ($recharge && $recharge['status'] === 'pending') {
                $upd = $pdo->prepare("UPDATE wallet_recharges SET status = 'success', razorpay_payment_id = ? WHERE id = ?");
                $upd->execute([$paymentRef, $walletRechargeId]);

                $wallet_stmt = $pdo->prepare("SELECT * FROM agent_wallets WHERE id = ? FOR UPDATE");
                $wallet_stmt->execute([$recharge['wallet_id']]);
                $wallet = $wallet_stmt->fetch();

                if ($wallet) {
                    $balanceBefore = floatval($wallet['balance']);
                    $balanceAfter = $balanceBefore + floatval($recharge['amount']);

                    $upd_wallet = $pdo->prepare("UPDATE agent_wallets SET balance = ? WHERE id = ?");
                    $upd_wallet->execute([$balanceAfter, $wallet['id']]);

                    $ledger = $pdo->prepare("
                        INSERT INTO wallet_transactions (
                            wallet_id, transaction_type, amount, balance_before, balance_after,
                            reference_type, reference_id, remarks, created_by
                        ) VALUES (?, 'recharge', ?, ?, ?, 'recharge', ?, ?, ?)
                    ");
                    $remarks = "Wallet Recharge Successful via Webhook. Ref: $paymentRef";
                    $ledger->execute([
                        $wallet['id'],
                        $recharge['amount'],
                        $balanceBefore,
                        $balanceAfter,
                        $walletRechargeId,
                        $remarks,
                        $wallet['agent_id']
                    ]);

                    $gateway->logAudit('WEBHOOK_WALLET_RECHARGE_CONFIRMED', "Verified recharge success via Webhook. Recharge ID: #$walletRechargeId. Amount: ₹" . $recharge['amount'], $wallet['agent_id']);
                }
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Webhook wallet recharge confirmation failed: " . $e->getMessage());
        }
    }
} elseif ($updStatus && $status === 'failed') {
    // Release seats on failure webhook
    $bookingRef = $payment['booking_reference'];
    if ($bookingRef) {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_reference = ? FOR UPDATE");
            $stmt->execute([$bookingRef]);
            $booking = $stmt->fetch();

            if ($booking && $booking['payment_status'] === 'pending') {
                $upd = $pdo->prepare("UPDATE bookings SET payment_status = 'failed', status = 'cancelled' WHERE id = ?");
                $upd->execute([$booking['id']]);

                $seats_stmt = $pdo->prepare("SELECT seat_number FROM booking_seats WHERE booking_id = ?");
                $seats_stmt->execute([$booking['id']]);
                $seats = $seats_stmt->fetchAll(PDO::FETCH_COLUMN);

                if (!empty($seats)) {
                    $seat_placeholders = implode(',', array_fill(0, count($seats), '?'));
                    $rel_seats = $pdo->prepare("
                        UPDATE trip_seats 
                        SET status = 'available', hold_expires_at = NULL, locked_by_session = NULL, locked_at = NULL
                        WHERE trip_id = ? AND seat_number IN ($seat_placeholders)
                    ");
                    $rel_seats->execute(array_merge([$booking['trip_id']], $seats));
                }

                $gateway->logAudit('WEBHOOK_BOOKING_PAYMENT_FAILED', "Payment failed for booking $bookingRef. Ref: $paymentRef");
            }
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Webhook booking failure process failed: " . $e->getMessage());
        }
    }
}

echo json_encode(['success' => true, 'message' => 'Webhook processed successfully.']);
exit();
