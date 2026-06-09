<?php
/**
 * Payment Gateway Callback Redirect Target
 * Finalizes transactions for both Ticket Bookings and Agent Wallet Recharges.
 */
require_once __DIR__ . '/includes/auth_middleware.php';
require_once __DIR__ . '/config/PaymentGateway.php';

$paymentRef = $_REQUEST['merchantTransactionId'] ?? $_REQUEST['transactionId'] ?? '';
$statusParam = $_REQUEST['status'] ?? '';

if (empty($paymentRef)) {
    die("Error: No Payment reference found in callback.");
}

$gateway = new PaymentGateway($pdo);

// Log callback raw payload
$payload = json_encode($_REQUEST);
$gateway->logCallback($paymentRef, $payload);

// Perform status verification check (calls PhonePe API or returns Mock state)
$statusCheck = $gateway->checkStatus($paymentRef);

if (!$statusCheck['success']) {
    $page_title = "Transaction Error";
    require_once __DIR__ . '/includes/header.php';
    echo '<div class="alert alert-danger p-5 rounded-4 mt-5 text-center">
            <h3 class="fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Status Verification Failed</h3>
            <p class="mb-4">Unable to verify transaction state. Please contact customer support with reference: ' . htmlspecialchars($paymentRef) . '</p>
            <a href="' . BASE_URL . '/index.php" class="btn btn-secondary-glass px-4 py-2">Return to Home</a>
          </div>';
    require_once __DIR__ . '/includes/footer.php';
    exit();
}

$status = $statusCheck['status']; // success, failed, pending, cancelled
$amount = $statusCheck['amount'];
$bookingRef = $statusCheck['booking_reference'];
$walletRechargeId = $statusCheck['wallet_recharge_id'];

// Process Success / Failure Flow
if ($status === 'success') {
    // ----------------------------------------------------
    // CASE A: Booking Payment Confirmation
    // ----------------------------------------------------
    if ($bookingRef) {
        try {
            $pdo->beginTransaction();

            // Lock booking row
            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_reference = ? FOR UPDATE");
            $stmt->execute([$bookingRef]);
            $booking = $stmt->fetch();

            if ($booking && $booking['payment_status'] !== 'paid') {
                // Confirm booking payment
                $upd = $pdo->prepare("UPDATE bookings SET payment_status = 'paid' WHERE id = ?");
                $upd->execute([$booking['id']]);

                // Fetch booked seats
                $seats_stmt = $pdo->prepare("SELECT seat_number FROM booking_seats WHERE booking_id = ?");
                $seats_stmt->execute([$booking['id']]);
                $seats = $seats_stmt->fetchAll(PDO::FETCH_COLUMN);

                // Update Seat hold to Booked
                $seat_placeholders = implode(',', array_fill(0, count($seats), '?'));
                $upd_seats = $pdo->prepare("
                    UPDATE trip_seats 
                    SET status = 'booked', hold_expires_at = NULL, locked_by_session = NULL, locked_at = NULL
                    WHERE trip_id = ? AND seat_number IN ($seat_placeholders)
                ");
                $upd_seats->execute(array_merge([$booking['trip_id']], $seats));

                // Log Activity
                $gateway->logAudit('BOOKING_PAYMENT_CONFIRMED', "Verified payment success for booking $bookingRef. Amount: ₹$amount. Ref: $paymentRef");
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Callback transaction confirm failed: " . $e->getMessage());
        }

        // Redirect to Ticket Success page
        header("Location: " . BASE_URL . "/ticket.php?ref=" . urlencode($bookingRef));
        exit();
    }
    // ----------------------------------------------------
    // CASE B: Agent Wallet Recharge Confirmation
    // ----------------------------------------------------
    elseif ($walletRechargeId) {
        try {
            $pdo->beginTransaction();

            // Lock recharge row
            $stmt = $pdo->prepare("SELECT * FROM wallet_recharges WHERE id = ? FOR UPDATE");
            $stmt->execute([$walletRechargeId]);
            $recharge = $stmt->fetch();

            if ($recharge && $recharge['status'] === 'pending') {
                // Update recharge status
                $upd = $pdo->prepare("UPDATE wallet_recharges SET status = 'success', razorpay_payment_id = ? WHERE id = ?");
                $upd->execute([$paymentRef, $walletRechargeId]);

                // Fetch wallet and lock
                $wallet_stmt = $pdo->prepare("SELECT * FROM agent_wallets WHERE id = ? FOR UPDATE");
                $wallet_stmt->execute([$recharge['wallet_id']]);
                $wallet = $wallet_stmt->fetch();

                if ($wallet) {
                    $balanceBefore = floatval($wallet['balance']);
                    $balanceAfter = $balanceBefore + floatval($recharge['amount']);

                    // Update wallet balance
                    $upd_wallet = $pdo->prepare("UPDATE agent_wallets SET balance = ? WHERE id = ?");
                    $upd_wallet->execute([$balanceAfter, $wallet['id']]);

                    // Add Ledger Entry
                    $ledger = $pdo->prepare("
                        INSERT INTO wallet_transactions (
                            wallet_id, transaction_type, amount, balance_before, balance_after,
                            reference_type, reference_id, remarks, created_by
                        ) VALUES (?, 'recharge', ?, ?, ?, 'recharge', ?, ?, ?)
                    ");
                    $remarks = "Wallet Recharge Successful. Txn Ref: $paymentRef";
                    $ledger->execute([
                        $wallet['id'],
                        $recharge['amount'],
                        $balanceBefore,
                        $balanceAfter,
                        $walletRechargeId,
                        $remarks,
                        $wallet['agent_id']
                    ]);

                    $gateway->logAudit('WALLET_RECHARGE_CONFIRMED', "Verified recharge success for recharge ID #$walletRechargeId. Credited: ₹" . $recharge['amount'], $wallet['agent_id']);
                }
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Callback recharge confirm failed: " . $e->getMessage());
        }

        // Redirect to agent wallet history
        $_SESSION['recharge_success'] = "Wallet Recharge of ₹" . number_format($amount, 2) . " completed successfully!";
        header("Location: " . BASE_URL . "/agent/wallet_history.php");
        exit();
    }
} else {
    // ----------------------------------------------------
    // PAYMENT FAILED / CANCELLED FLOW
    // ----------------------------------------------------
    if ($bookingRef) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM bookings WHERE booking_reference = ? FOR UPDATE");
            $stmt->execute([$bookingRef]);
            $booking = $stmt->fetch();

            if ($booking && $booking['payment_status'] === 'pending') {
                // Cancel booking
                $upd = $pdo->prepare("UPDATE bookings SET payment_status = 'failed', status = 'cancelled' WHERE id = ?");
                $upd->execute([$booking['id']]);

                // Fetch seats
                $seats_stmt = $pdo->prepare("SELECT seat_number FROM booking_seats WHERE booking_id = ?");
                $seats_stmt->execute([$booking['id']]);
                $seats = $seats_stmt->fetchAll(PDO::FETCH_COLUMN);

                // Release seats lock
                if (!empty($seats)) {
                    $seat_placeholders = implode(',', array_fill(0, count($seats), '?'));
                    $rel_seats = $pdo->prepare("
                        UPDATE trip_seats 
                        SET status = 'available', hold_expires_at = NULL, locked_by_session = NULL, locked_at = NULL
                        WHERE trip_id = ? AND seat_number IN ($seat_placeholders)
                    ");
                    $rel_seats->execute(array_merge([$booking['trip_id']], $seats));
                }

                $gateway->logAudit('BOOKING_PAYMENT_FAILED', "Payment failed/cancelled for booking $bookingRef. Status: $status. Ref: $paymentRef");
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log("Callback release seats failed: " . $e->getMessage());
        }

        // Show Failure Screen
        $page_title = "Payment Failed";
        require_once __DIR__ . '/includes/header.php';
        echo '<div class="row justify-content-center mt-5">
                <div class="col-md-6 col-lg-5">
                    <div class="glass-card p-5 border border-danger border-opacity-20 text-center" style="border-radius: 20px;">
                        <i class="fa-solid fa-circle-xmark text-danger mb-4 animate-bounce" style="font-size: 4rem;"></i>
                        <h3 class="fw-bold text-white mb-2">Payment Transaction Failed</h3>
                        <p class="text-secondary small mb-4">Your payment attempts were marked as ' . htmlspecialchars($status) . '. Seats held for your booking have been automatically released.</p>
                        <div class="p-3 rounded-4 bg-dark bg-opacity-20 border border-secondary border-opacity-10 mb-4 text-start font-monospace small text-secondary">
                            <div>Payment Ref: ' . htmlspecialchars($paymentRef) . '</div>
                            <div>Booking Ref: ' . htmlspecialchars($bookingRef) . '</div>
                        </div>
                        <a href="' . BASE_URL . '/index.php" class="btn btn-primary-gradient py-2 px-4 fw-bold w-100 mb-2">Return to Booking Search</a>
                    </div>
                </div>
              </div>';
        require_once __DIR__ . '/includes/footer.php';
        exit();
    } elseif ($walletRechargeId) {
        // Update recharge status
        $upd = $pdo->prepare("UPDATE wallet_recharges SET status = 'failed' WHERE id = ?");
        $upd->execute([$walletRechargeId]);

        $_SESSION['recharge_error'] = "Wallet Recharge failed or was cancelled. (Ref: $paymentRef)";
        header("Location: " . BASE_URL . "/agent/wallet_history.php");
        exit();
    }
}
