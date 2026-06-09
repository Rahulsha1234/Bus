<?php
/**
 * Dynamic Payment Gateway Integration Module (PhonePe / Razorpay Architecture)
 * Supports READY-FOR-PRODUCTION API calls, Webhook processing, signature verifications,
 * and a robust Developer/Mock mode for local sandbox testing.
 */

class PaymentGateway {
    private $pdo;
    private $config = [];

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->loadSettings();
    }

    /**
     * Load settings dynamically from the system_settings table
     */
    private function loadSettings() {
        try {
            $stmt = $this->pdo->query("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'payment_%'");
            while ($row = $stmt->fetch()) {
                $key = str_replace('payment_', '', $row['setting_key']);
                $this->config[$key] = $row['setting_value'];
            }
        } catch (PDOException $e) {
            error_log("Failed to load payment settings: " . $e->getMessage());
        }

        // Apply defaults if empty
        $defaults = [
            'merchant_id' => 'DEMO_MERCHANT',
            'client_id' => 'DEMO_CLIENT',
            'client_secret' => 'DEMO_SECRET',
            'salt_key' => 'DEMO_SALT',
            'salt_index' => '1',
            'environment' => 'sandbox',
            'callback_url' => 'http://localhost/Bus/payment_callback.php',
            'webhook_url' => 'http://localhost/Bus/payment_webhook.php',
            'mock_mode' => '1'
        ];

        foreach ($defaults as $k => $v) {
            if (!isset($this->config[$k]) || trim($this->config[$k]) === '') {
                $this->config[$k] = $v;
            }
        }
    }

    /**
     * Get specific configuration value
     */
    public function getConfig($key) {
        return $this->config[$key] ?? null;
    }

    /**
     * Determine if we are running in Developer Mock Mode
     */
    public function isMockMode() {
        return (string)$this->getConfig('mock_mode') === '1';
    }

    /**
     * Get API Base URL based on environment setting
     */
    private function getApiBaseUrl() {
        if ($this->getConfig('environment') === 'production') {
            return 'https://api.phonepe.com/apis/hermes';
        }
        return 'https://api-preprod.phonepe.com/apis/pg-sandbox';
    }

    /**
     * Generate X-VERIFY signature header
     * SHA256(Base64_Payload + Endpoint + SaltKey) + "###" + SaltIndex
     */
    public function generateXVerify($base64Payload, $endpoint) {
        $saltKey = $this->getConfig('salt_key');
        $saltIndex = $this->getConfig('salt_index');
        $stringToHash = $base64Payload . $endpoint . $saltKey;
        $hash = hash('sha256', $stringToHash);
        return $hash . '###' . $saltIndex;
    }

    /**
     * Check if payment reference is duplicate to prevent duplicate payments
     */
    public function isDuplicateReference($reference) {
        $stmt = $this->pdo->prepare("SELECT COUNT(*) FROM payments WHERE payment_reference = ?");
        $stmt->execute([$reference]);
        return intval($stmt->fetchColumn()) > 0;
    }

    /**
     * Step 1: Create Order & Initialize Payment Record
     */
    public function createPayment($refType, $refId, $amount, $bookingRef = null, $walletRechargeId = null) {
        // Prevent duplicate payments
        $paymentRef = 'TXN_' . strtoupper(substr(uniqid(), 5)) . rand(100, 999);
        if ($bookingRef) {
            // Check if there is an active/successful payment for this booking already
            $stmt = $this->pdo->prepare("SELECT status FROM payments WHERE booking_reference = ? ORDER BY id DESC LIMIT 1");
            $stmt->execute([$bookingRef]);
            $existingStatus = $stmt->fetchColumn();
            if ($existingStatus === 'success') {
                throw new Exception("This booking has already been paid successfully.");
            }
        }

        $isMock = $this->isMockMode() ? 1 : 0;
        $env = $this->getConfig('environment');

        $stmt = $this->pdo->prepare("
            INSERT INTO payments (payment_reference, booking_reference, wallet_recharge_id, amount, status, environment, is_mock)
            VALUES (?, ?, ?, ?, 'pending', ?, ?)
        ");
        $stmt->execute([$paymentRef, $bookingRef, $walletRechargeId, $amount, $env, $isMock]);
        $paymentId = $this->pdo->lastInsertId();

        // Create first attempt record
        $attemptRef = 'ATT_' . strtoupper(substr(uniqid(), 5)) . rand(100, 999);
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_attempts (payment_id, attempt_reference, amount, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $stmt->execute([$paymentId, $attemptRef, $amount]);

        return [
            'payment_id' => $paymentId,
            'payment_reference' => $paymentRef,
            'attempt_reference' => $attemptRef
        ];
    }

    /**
     * Step 2: Initiate Payment API Call
     */
    public function initiatePayment($paymentRef, $attemptRef, $amount, $userId, $customCallback = null, $customWebhook = null) {
        $amountInPaise = intval(round($amount * 100)); // PhonePe requires paise

        $callbackUrl = $customCallback ?: $this->getConfig('callback_url');
        $webhookUrl = $customWebhook ?: $this->getConfig('webhook_url');

        // Append references to callback to ease tracking
        $callbackUrl = $callbackUrl . (strpos($callbackUrl, '?') !== false ? '&' : '?') . 'merchantTransactionId=' . $paymentRef;

        if ($this->isMockMode()) {
            // Under mock mode, redirect directly to our local mock terminal simulation page
            $mockUrl = BASE_URL . '/mock_gateway_terminal.php?payment_reference=' . $paymentRef . '&attempt_reference=' . $attemptRef;
            return [
                'success' => true,
                'redirect' => true,
                'url' => $mockUrl
            ];
        }

        // Production-ready PhonePe API setup
        $payload = [
            'merchantId' => $this->getConfig('merchant_id'),
            'merchantTransactionId' => $paymentRef,
            'merchantUserId' => 'USR_' . $userId,
            'amount' => $amountInPaise,
            'redirectUrl' => $callbackUrl,
            'redirectMode' => 'REDIRECT',
            'callbackUrl' => $webhookUrl,
            'paymentInstrument' => [
                'type' => 'PAY_PAGE'
            ]
        ];

        $jsonPayload = json_encode($payload);
        $base64Payload = base64_encode($jsonPayload);
        $endpoint = '/pg/v1/pay';
        $xVerify = $this->generateXVerify($base64Payload, $endpoint);

        $curlPayload = json_encode(['request' => $base64Payload]);

        // CURL Call
        $ch = curl_init($this->getApiBaseUrl() . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-VERIFY: ' . $xVerify,
            'accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        // Log attempt response
        $stmt = $this->pdo->prepare("UPDATE payment_attempts SET raw_response = ? WHERE attempt_reference = ?");
        $stmt->execute([$response, $attemptRef]);

        if ($httpCode === 200 && $response) {
            $resData = json_decode($response, true);
            if (isset($resData['success']) && $resData['success'] === true && isset($resData['data']['instrumentResponse']['redirectInfo']['url'])) {
                return [
                    'success' => true,
                    'redirect' => true,
                    'url' => $resData['data']['instrumentResponse']['redirectInfo']['url']
                ];
            }
        }

        // Handle error
        $stmt = $this->pdo->prepare("UPDATE payment_attempts SET status = 'failed' WHERE attempt_reference = ?");
        $stmt->execute([$attemptRef]);

        $stmt = $this->pdo->prepare("UPDATE payments SET status = 'failed' WHERE payment_reference = ?");
        $stmt->execute([$paymentRef]);

        // Audit log security / failures
        $this->logAudit('PAYMENT_INIT_FAILED', "Failed to initiate payment for reference: $paymentRef. Response code: $httpCode", $userId);

        return [
            'success' => false,
            'message' => 'Failed to initialize transaction with PhonePe.'
        ];
    }

    /**
     * Step 3: Payment Status Check
     */
    public function checkStatus($paymentRef) {
        // Fetch payment
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE payment_reference = ?");
        $stmt->execute([$paymentRef]);
        $payment = $stmt->fetch();

        if (!$payment) {
            return ['success' => false, 'status' => 'not_found', 'message' => 'Payment reference not found.'];
        }

        if ($this->isMockMode() || (int)$payment['is_mock'] === 1) {
            // Return status recorded in database
            return [
                'success' => true,
                'status' => $payment['status'],
                'amount' => $payment['amount'],
                'payment_reference' => $payment['payment_reference'],
                'booking_reference' => $payment['booking_reference'],
                'wallet_recharge_id' => $payment['wallet_recharge_id']
            ];
        }

        // Query PhonePe Status Endpoint
        $merchantId = $this->getConfig('merchant_id');
        $endpoint = "/pg/v1/status/{$merchantId}/{$paymentRef}";
        $xVerify = hash('sha256', $endpoint . $this->getConfig('salt_key')) . '###' . $this->getConfig('salt_index');

        $ch = curl_init($this->getApiBaseUrl() . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-VERIFY: ' . $xVerify,
            'X-MERCHANT-ID: ' . $merchantId,
            'accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $resData = json_decode($response, true);
            if (isset($resData['success']) && $resData['success'] === true && isset($resData['code'])) {
                $code = $resData['code'];
                $status = 'pending';
                if ($code === 'PAYMENT_SUCCESS') {
                    $status = 'success';
                } elseif ($code === 'PAYMENT_ERROR') {
                    $status = 'failed';
                } elseif ($code === 'PAYMENT_DECLINED') {
                    $status = 'failed';
                } elseif ($code === 'TIMED_OUT') {
                    $status = 'failed';
                }

                // Update database
                $this->updatePaymentStatus($paymentRef, $status, $resData['data']['transactionId'] ?? null, $response);

                return [
                    'success' => true,
                    'status' => $status,
                    'amount' => $payment['amount'],
                    'payment_reference' => $payment['payment_reference'],
                    'booking_reference' => $payment['booking_reference'],
                    'wallet_recharge_id' => $payment['wallet_recharge_id']
                ];
            }
        }

        return [
            'success' => false,
            'status' => $payment['status'],
            'amount' => $payment['amount'],
            'payment_reference' => $payment['payment_reference'],
            'booking_reference' => $payment['booking_reference'],
            'wallet_recharge_id' => $payment['wallet_recharge_id']
        ];
    }

    /**
     * Update Payment Status records safely
     */
    public function updatePaymentStatus($paymentRef, $status, $gatewayTxnId = null, $rawResponse = null) {
        $this->pdo->beginTransaction();
        try {
            // Lock payment row
            $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE payment_reference = ? FOR UPDATE");
            $stmt->execute([$paymentRef]);
            $payment = $stmt->fetch();

            if (!$payment) {
                $this->pdo->rollBack();
                return false;
            }

            // If already processed, skip to avoid duplicate executions
            if (in_array($payment['status'], ['success', 'refunded']) && $status !== 'refunded') {
                $this->pdo->rollBack();
                return true;
            }

            // Update Payments Status
            $stmt = $this->pdo->prepare("UPDATE payments SET status = ? WHERE id = ?");
            $stmt->execute([$status, $payment['id']]);

            // Update associated Attempt status
            $stmt = $this->pdo->prepare("
                UPDATE payment_attempts 
                SET status = ?, gateway_txn_id = ?, raw_response = ?
                WHERE payment_id = ? AND status = 'pending'
                ORDER BY id DESC LIMIT 1
            ");
            $stmt->execute([
                $status === 'success' ? 'success' : ($status === 'failed' ? 'failed' : 'cancelled'),
                $gatewayTxnId,
                $rawResponse,
                $payment['id']
            ]);

            $this->pdo->commit();
            return true;
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Failed updating payment status: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Refund API Structure
     */
    public function processRefund($paymentRef, $amount, $reason = 'Customer Cancelled') {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE payment_reference = ?");
        $stmt->execute([$paymentRef]);
        $payment = $stmt->fetch();

        if (!$payment) {
            return ['success' => false, 'message' => 'Original payment not found.'];
        }

        if ($payment['status'] !== 'success') {
            return ['success' => false, 'message' => 'Can only refund successful payments.'];
        }

        $remainingAmount = $payment['amount'] - $payment['refunded_amount'];
        if ($amount > $remainingAmount) {
            return ['success' => false, 'message' => "Refund amount (₹$amount) exceeds remaining refundable amount (₹$remainingAmount)."];
        }

        $refundRef = 'REF_' . strtoupper(substr(uniqid(), 5)) . rand(100, 999);

        // Log refund pending record
        $stmt = $this->pdo->prepare("
            INSERT INTO payment_refunds (payment_id, refund_reference, amount, status, reason)
            VALUES (?, ?, ?, 'pending', ?)
        ");
        $stmt->execute([$payment['id'], $refundRef, $amount, $reason]);
        $refundId = $this->pdo->lastInsertId();

        if ($this->isMockMode() || (int)$payment['is_mock'] === 1) {
            // Mock Refund Success instantly
            $gatewayRefundId = 'refund_mock_' . bin2hex(random_bytes(8));
            $this->finalizeRefund($refundId, 'success', $gatewayRefundId, '{"mock": "success"}');
            return ['success' => true, 'refund_reference' => $refundRef, 'status' => 'success'];
        }

        // PhonePe Refund API Call
        $amountInPaise = intval(round($amount * 100));
        $payload = [
            'merchantId' => $this->getConfig('merchant_id'),
            'merchantUserId' => 'USR_SYSTEM',
            'merchantTransactionId' => $refundRef,
            'originalTransactionId' => $paymentRef,
            'amount' => $amountInPaise,
            'callbackUrl' => $this->getConfig('webhook_url')
        ];

        $jsonPayload = json_encode($payload);
        $base64Payload = base64_encode($jsonPayload);
        $endpoint = '/pg/v1/refund';
        $xVerify = $this->generateXVerify($base64Payload, $endpoint);

        $curlPayload = json_encode(['request' => $base64Payload]);

        $ch = curl_init($this->getApiBaseUrl() . $endpoint);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $curlPayload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-VERIFY: ' . $xVerify,
            'accept: application/json'
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $resData = json_decode($response, true);
            if (isset($resData['success']) && $resData['success'] === true) {
                // Refund initiated successfully
                $gatewayRefundId = $resData['data']['transactionId'] ?? null;
                $this->finalizeRefund($refundId, 'success', $gatewayRefundId, $response);
                return ['success' => true, 'refund_reference' => $refundRef, 'status' => 'success'];
            }
        }

        // Refund failed
        $this->finalizeRefund($refundId, 'failed', null, $response);
        return ['success' => false, 'message' => 'PhonePe Refund API failed.', 'raw' => $response];
    }

    /**
     * Finalize Refund records
     */
    private function finalizeRefund($refundId, $status, $gatewayRefundId, $rawResponse) {
        $this->pdo->beginTransaction();
        try {
            $stmt = $this->pdo->prepare("SELECT * FROM payment_refunds WHERE id = ? FOR UPDATE");
            $stmt->execute([$refundId]);
            $refund = $stmt->fetch();

            if (!$refund || $refund['status'] !== 'pending') {
                $this->pdo->rollBack();
                return;
            }

            // Update Refund status
            $stmt = $this->pdo->prepare("UPDATE payment_refunds SET status = ?, gateway_refund_id = ?, raw_response = ? WHERE id = ?");
            $stmt->execute([$status, $gatewayRefundId, $rawResponse, $refundId]);

            if ($status === 'success') {
                // Update payment total refunded
                $stmt = $this->pdo->prepare("SELECT payment_id, amount FROM payment_refunds WHERE id = ?");
                $stmt->execute([$refundId]);
                $refData = $stmt->fetch();

                $stmt = $this->pdo->prepare("UPDATE payments SET refunded_amount = refunded_amount + ? WHERE id = ?");
                $stmt->execute([$refData['amount'], $refData['payment_id']]);

                // If refunded amount matches total amount, set status to refunded
                $stmt = $this->pdo->prepare("SELECT amount, refunded_amount FROM payments WHERE id = ?");
                $stmt->execute([$refData['payment_id']]);
                $payInfo = $stmt->fetch();

                if ($payInfo['refunded_amount'] >= $payInfo['amount']) {
                    $stmt = $this->pdo->prepare("UPDATE payments SET status = 'refunded' WHERE id = ?");
                    $stmt->execute([$refData['payment_id']]);
                }
            }

            $this->pdo->commit();
        } catch (Exception $e) {
            $this->pdo->rollBack();
            error_log("Failed finalizing refund: " . $e->getMessage());
        }
    }

    /**
     * Store logs of Callback requests
     */
    public function logCallback($paymentRef, $payload) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $stmt = $this->pdo->prepare("INSERT INTO payment_callbacks (payment_reference, payload, ip_address) VALUES (?, ?, ?)");
            $stmt->execute([$paymentRef, $payload, $ip]);
        } catch (Exception $e) {
            error_log("Failed logging callback: " . $e->getMessage());
        }
    }

    /**
     * Store logs of Webhook events
     */
    public function logWebhook($paymentRef, $payload, $headers) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
            $stmt = $this->pdo->prepare("INSERT INTO payment_webhooks (payment_reference, payload, headers, ip_address) VALUES (?, ?, ?, ?)");
            $stmt->execute([$paymentRef, $payload, $headers, $ip]);
        } catch (Exception $e) {
            error_log("Failed logging webhook: " . $e->getMessage());
        }
    }

    /**
     * Log auditing actions for Security, Duplication, Fraud detection
     */
    public function logAudit($actionType, $details, $userId = null) {
        try {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $userRole = null;
            if ($userId) {
                $stmt = $this->pdo->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
                $stmt->execute([$userId]);
                $userRole = $stmt->fetchColumn();
            } else {
                $userId = $_SESSION['user_id'] ?? null;
                $userRole = $_SESSION['user_role'] ?? null;
            }

            $stmt = $this->pdo->prepare("
                INSERT INTO activity_logs (user_id, action_type, details, ip_address, user_role) 
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$userId, $actionType, $details, $ip, $userRole]);
        } catch (Exception $e) {
            error_log("Failed saving payment audit log: " . $e->getMessage());
        }
    }
}
