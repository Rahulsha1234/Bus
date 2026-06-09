-- Database migrations for PhonePe / Razorpay dynamic Payment Gateway integration
USE bus_booking;

-- 1. Create Payments table
CREATE TABLE IF NOT EXISTS payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_reference VARCHAR(100) UNIQUE NOT NULL, -- Merchant Transaction ID
    booking_reference VARCHAR(100) NULL,           -- Link to bookings table if booking
    wallet_recharge_id INT NULL,                    -- Link to wallet recharge if recharge
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'INR',
    gateway_name VARCHAR(50) DEFAULT 'PhonePe',
    status ENUM('pending', 'success', 'failed', 'cancelled', 'refunded') NOT NULL DEFAULT 'pending',
    refunded_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    environment VARCHAR(20) DEFAULT 'sandbox',
    is_mock TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_payments_status (status),
    INDEX idx_payments_ref (payment_reference)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Create Payment Attempts table
CREATE TABLE IF NOT EXISTS payment_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    attempt_reference VARCHAR(100) UNIQUE NOT NULL, -- Unique txn ref for this attempt
    amount DECIMAL(10,2) NOT NULL,
    gateway_txn_id VARCHAR(100) NULL,               -- Provider side ID
    status ENUM('pending', 'success', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    raw_response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE,
    INDEX idx_attempts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Create Payment Refunds table
CREATE TABLE IF NOT EXISTS payment_refunds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    refund_reference VARCHAR(100) UNIQUE NOT NULL, -- Merchant Refund ID
    gateway_refund_id VARCHAR(100) NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    reason TEXT NULL,
    raw_response TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Create Payment Callbacks table
CREATE TABLE IF NOT EXISTS payment_callbacks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_reference VARCHAR(100) NOT NULL,
    payload TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Create Payment Webhooks table
CREATE TABLE IF NOT EXISTS payment_webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_reference VARCHAR(100) NULL,
    payload TEXT NOT NULL,
    headers TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Create Settlement Logs table
CREATE TABLE IF NOT EXISTS settlement_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    payment_id INT NOT NULL,
    settlement_reference VARCHAR(100) NULL,
    amount DECIMAL(10,2) NOT NULL,
    settlement_date DATE NOT NULL,
    raw_log TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (payment_id) REFERENCES payments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Add Settings keys
INSERT INTO system_settings (setting_key, setting_value) VALUES 
('payment_merchant_id', 'DEMO_MERCHANT'),
('payment_client_id', 'DEMO_CLIENT'),
('payment_client_secret', 'DEMO_SECRET'),
('payment_salt_key', 'DEMO_SALT'),
('payment_salt_index', '1'),
('payment_environment', 'sandbox'),
('payment_callback_url', 'http://localhost/Bus/payment_callback.php'),
('payment_webhook_url', 'http://localhost/Bus/payment_webhook.php'),
('payment_mock_mode', '1')
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);
