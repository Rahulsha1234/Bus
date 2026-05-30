-- SwiftBus Database Migration: Saveable Layout Templates
USE bus_booking;

CREATE TABLE IF NOT EXISTS layout_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    rows_count INT NOT NULL DEFAULT 8,
    cols_count INT NOT NULL DEFAULT 5,
    layout_type VARCHAR(50) NOT NULL DEFAULT 'Seater',
    seats_data LONGTEXT NOT NULL, -- JSON formatted array of seats and details
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
