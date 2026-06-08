-- Wallet System Database Migration Schema

USE bus_booking;

-- 1. Agent Wallets Table
CREATE TABLE IF NOT EXISTS agent_wallets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT UNIQUE NOT NULL,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('active', 'frozen') NOT NULL DEFAULT 'active',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Wallet Ledger / Transactions Table
CREATE TABLE IF NOT EXISTS wallet_transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    transaction_type ENUM('recharge', 'debit', 'refund', 'admin_credit', 'admin_debit') NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    balance_before DECIMAL(10,2) NOT NULL,
    balance_after DECIMAL(10,2) NOT NULL,
    reference_type VARCHAR(50) NULL,
    reference_id INT NULL,
    remarks TEXT NULL,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES agent_wallets(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Wallet Recharges Table (Razorpay references)
CREATE TABLE IF NOT EXISTS wallet_recharges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    wallet_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    razorpay_payment_id VARCHAR(100) NULL,
    razorpay_order_id VARCHAR(100) NULL,
    razorpay_signature VARCHAR(255) NULL,
    status ENUM('pending', 'success', 'failed') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (wallet_id) REFERENCES agent_wallets(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Alter booking_seats to add status column for seat-wise cancellation
ALTER TABLE booking_seats ADD COLUMN status ENUM('active', 'cancel_requested', 'cancelled') NOT NULL DEFAULT 'active';

-- 5. Alter bookings status Enum to support partial cancellations
ALTER TABLE bookings MODIFY COLUMN status ENUM('active', 'partially_cancelled', 'cancelled') NOT NULL DEFAULT 'active';

-- 6. Alter cancellation_requests to add support for partial cancellations and refunds
ALTER TABLE cancellation_requests ADD COLUMN cancelled_seats TEXT NULL;
ALTER TABLE cancellation_requests ADD COLUMN refund_type ENUM('cash', 'wallet') DEFAULT 'cash';

-- 7. Add Performance Indexes
CREATE INDEX idx_wallet_tx_wallet_id ON wallet_transactions(wallet_id);
CREATE INDEX idx_wallet_recharges_wallet_id ON wallet_recharges(wallet_id);
CREATE INDEX idx_agent_wallets_agent_id ON agent_wallets(agent_id);
