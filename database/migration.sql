-- SwiftBus Database Migration Script
USE bus_booking;

-- 1. Operator Contacts Table
CREATE TABLE IF NOT EXISTS operator_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL UNIQUE,
    operator_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    whatsapp_number VARCHAR(20) NOT NULL,
    emergency_number VARCHAR(20) NOT NULL,
    support_email VARCHAR(100) NOT NULL,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Bus Layouts Grid Dimensions
CREATE TABLE IF NOT EXISTS bus_layouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL UNIQUE,
    rows_count INT NOT NULL DEFAULT 8,
    cols_count INT NOT NULL DEFAULT 5,
    layout_type VARCHAR(50) NOT NULL DEFAULT 'Seater',
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Bus Seats Grid Coordinates & Configuration
CREATE TABLE IF NOT EXISTS bus_seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    row_pos INT NOT NULL,
    col_pos INT NOT NULL,
    seat_type ENUM('Normal', 'Sleeper', 'Upper Sleeper', 'Lower Sleeper', 'Double Sleeper Upper', 'Double Sleeper Lower') NOT NULL DEFAULT 'Normal',
    is_active TINYINT NOT NULL DEFAULT 1,
    base_price DECIMAL(10,2) NOT NULL DEFAULT 500.00,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bus_seat_pos (bus_id, row_pos, col_pos),
    UNIQUE KEY unique_bus_seat_num (bus_id, seat_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Trip Seat Pricing Overrides
CREATE TABLE IF NOT EXISTS seat_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    current_price DECIMAL(10,2) NOT NULL,
    offer_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    UNIQUE KEY unique_trip_seat_price (trip_id, seat_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Persistent Seat Holds Table
CREATE TABLE IF NOT EXISTS seat_holds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    held_by_user_id INT NOT NULL,
    held_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (held_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_trip_seat_hold (trip_id, seat_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Boarding Points Table
CREATE TABLE IF NOT EXISTS boarding_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    point_name VARCHAR(100) NOT NULL,
    departure_time VARCHAR(10) NOT NULL, -- Format 'HH:MM'
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Dropping Points Table
CREATE TABLE IF NOT EXISTS dropping_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    point_name VARCHAR(100) NOT NULL,
    arrival_time VARCHAR(10) NOT NULL, -- Format 'HH:MM'
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Add Boarding/Dropping Columns & Soft Delete to Bookings and other tables
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS boarding_point VARCHAR(255) NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS dropping_point VARCHAR(255) NULL;
ALTER TABLE bookings ADD COLUMN IF NOT EXISTS status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active';

-- Add status fields to route / trip / bus for Soft Deletion support
ALTER TABLE routes ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active';
ALTER TABLE trips ADD COLUMN IF NOT EXISTS status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active';
ALTER TABLE buses ADD COLUMN IF NOT EXISTS status ENUM('active', 'inactive') NOT NULL DEFAULT 'active';

-- 9. Cancellation Requests Table
CREATE TABLE IF NOT EXISTS cancellation_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    request_number VARCHAR(50) NOT NULL UNIQUE,
    refund_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    processed_at TIMESTAMP NULL,
    processed_by INT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (processed_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Extend activity_logs with previous & new values for full Auditing
ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS previous_value TEXT NULL;
ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS new_value TEXT NULL;
ALTER TABLE activity_logs ADD COLUMN IF NOT EXISTS user_role VARCHAR(50) NULL;

-- 11. System Notifications Center
CREATE TABLE IF NOT EXISTS system_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL, -- NULL specifies Super Admin, agent_id specifies specific agent
    user_role ENUM('admin', 'agent') NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Adjust trip_seats status and lock properties
ALTER TABLE trip_seats MODIFY COLUMN status ENUM('available', 'selected', 'booked', 'hold', 'cancelled', 'blocked', 'reserved', 'temp_locked', 'female_booked', 'female_protected') DEFAULT 'available';
ALTER TABLE trip_seats ADD COLUMN IF NOT EXISTS locked_at TIMESTAMP NULL;
ALTER TABLE trip_seats ADD COLUMN IF NOT EXISTS locked_by_session VARCHAR(255) NULL;
ALTER TABLE trip_seats ADD COLUMN IF NOT EXISTS gender_restriction ENUM('none', 'female_only', 'female_protected') DEFAULT 'none';

-- 13. Add duration column to routes
ALTER TABLE routes ADD COLUMN IF NOT EXISTS duration VARCHAR(50) NOT NULL DEFAULT '6 hours';
