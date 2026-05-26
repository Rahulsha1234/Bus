CREATE DATABASE IF NOT EXISTS bus_booking;
USE bus_booking;

-- Drop dependent tables in reverse order to respect foreign key constraints
DROP TABLE IF EXISTS system_notifications;
DROP TABLE IF EXISTS layout_templates;
DROP TABLE IF EXISTS cancellation_requests;
DROP TABLE IF EXISTS seat_holds;
DROP TABLE IF EXISTS seat_pricing;
DROP TABLE IF EXISTS dropping_points;
DROP TABLE IF EXISTS boarding_points;
DROP TABLE IF EXISTS bus_seats;
DROP TABLE IF EXISTS bus_layouts;
DROP TABLE IF EXISTS operator_contacts;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS weekly_settlements;
DROP TABLE IF EXISTS booking_seats;
DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS trip_seats;
DROP TABLE IF EXISTS trips;
DROP TABLE IF EXISTS routes;
DROP TABLE IF EXISTS buses;
DROP TABLE IF EXISTS agent_profiles;
DROP TABLE IF EXISTS users;

-- 1. Users Table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('customer', 'agent', 'admin') NOT NULL DEFAULT 'customer',
    status ENUM('pending', 'approved', 'suspended') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Agent Profiles Table
CREATE TABLE agent_profiles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    agency_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) NOT NULL,
    commission_rate DECIMAL(5,2) DEFAULT 2.00,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2.5. Layout Templates Table
CREATE TABLE layout_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    template_name VARCHAR(100) NOT NULL,
    rows_count INT NOT NULL DEFAULT 8,
    cols_count INT NOT NULL DEFAULT 5,
    layout_type VARCHAR(50) NOT NULL DEFAULT 'Seater',
    seats_data LONGTEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Buses Table
CREATE TABLE buses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    bus_name VARCHAR(100) NOT NULL,
    bus_number VARCHAR(50) NOT NULL,
    bus_type ENUM('AC Sleeper', 'Non-AC Sleeper', 'AC Seater', 'Non-AC Seater') NOT NULL,
    total_seats INT NOT NULL,
    seat_layout_type VARCHAR(20) DEFAULT '2x2',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Operator Contacts Table
CREATE TABLE operator_contacts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL UNIQUE,
    operator_name VARCHAR(100) NOT NULL,
    contact_number VARCHAR(20) NOT NULL,
    whatsapp_number VARCHAR(20) NOT NULL,
    emergency_number VARCHAR(20) NOT NULL,
    support_email VARCHAR(100) NOT NULL,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Bus Layouts Grid Dimensions
CREATE TABLE bus_layouts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL UNIQUE,
    rows_count INT NOT NULL DEFAULT 8,
    cols_count INT NOT NULL DEFAULT 5,
    layout_type VARCHAR(50) NOT NULL DEFAULT 'Seater',
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Bus Seats Grid Coordinates & Configuration
CREATE TABLE bus_seats (
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

-- 7. Routes Table
CREATE TABLE routes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    source VARCHAR(100) NOT NULL,
    destination VARCHAR(100) NOT NULL,
    distance_km INT NOT NULL,
    duration VARCHAR(50) NOT NULL DEFAULT '6 hours',
    pickup_points TEXT NOT NULL, -- JSON formatted array
    drop_points TEXT NOT NULL,   -- JSON formatted array
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 8. Boarding Points Table
CREATE TABLE boarding_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    point_name VARCHAR(100) NOT NULL,
    departure_time VARCHAR(10) NOT NULL, -- Format 'HH:MM'
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 9. Dropping Points Table
CREATE TABLE dropping_points (
    id INT AUTO_INCREMENT PRIMARY KEY,
    route_id INT NOT NULL,
    point_name VARCHAR(100) NOT NULL,
    arrival_time VARCHAR(10) NOT NULL, -- Format 'HH:MM'
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 10. Trips Table
CREATE TABLE trips (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    route_id INT NOT NULL,
    departure_time DATETIME NOT NULL,
    arrival_time DATETIME NOT NULL,
    base_fare DECIMAL(10,2) NOT NULL,
    seat_prices TEXT NULL, -- JSON details if any seats have premium rates
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    FOREIGN KEY (route_id) REFERENCES routes(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 11. Trip Seats Table
CREATE TABLE trip_seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    status ENUM('available', 'selected', 'booked', 'hold', 'cancelled', 'blocked', 'reserved', 'temp_locked', 'female_booked', 'female_protected') DEFAULT 'available',
    hold_expires_at TIMESTAMP NULL,
    locked_at TIMESTAMP NULL,
    locked_by_session VARCHAR(255) NULL,
    gender_restriction ENUM('none', 'female_only', 'female_protected') DEFAULT 'none',
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    UNIQUE KEY unique_trip_seat (trip_id, seat_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 12. Seat Pricing Overrides
CREATE TABLE seat_pricing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    current_price DECIMAL(10,2) NOT NULL,
    offer_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    UNIQUE KEY unique_trip_seat_price (trip_id, seat_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 13. Seat Holds Table
CREATE TABLE seat_holds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    trip_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    held_by_user_id INT NOT NULL,
    held_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE,
    FOREIGN KEY (held_by_user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_trip_seat_hold (trip_id, seat_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 14. Bookings Table
CREATE TABLE bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_reference VARCHAR(50) NOT NULL UNIQUE,
    trip_id INT NOT NULL,
    customer_id INT NULL,
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    total_amount DECIMAL(10,2) NOT NULL,
    admin_commission DECIMAL(10,2) NOT NULL,
    agent_net_earning DECIMAL(10,2) NOT NULL,
    payment_status ENUM('pending', 'paid', 'failed') DEFAULT 'pending',
    payment_gateway VARCHAR(50) DEFAULT 'Razorpay',
    transaction_id VARCHAR(100) NULL,
    boarding_point VARCHAR(255) NULL,
    dropping_point VARCHAR(255) NULL,
    status ENUM('active', 'cancelled') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (trip_id) REFERENCES trips(id),
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 15. Booking Seats Table
CREATE TABLE booking_seats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL,
    seat_number VARCHAR(10) NOT NULL,
    passenger_name VARCHAR(100) NOT NULL,
    passenger_age INT NOT NULL,
    passenger_gender ENUM('Male', 'Female', 'Other') NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 16. Cancellation Requests Table
CREATE TABLE cancellation_requests (
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

-- 17. Weekly Settlements Table
CREATE TABLE weekly_settlements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    agent_id INT NOT NULL,
    week_start DATE NOT NULL,
    week_end DATE NOT NULL,
    total_sales DECIMAL(10,2) NOT NULL,
    commission_payable DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'paid') DEFAULT 'pending',
    marked_paid_at TIMESTAMP NULL,
    marked_paid_by INT NULL,
    FOREIGN KEY (agent_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (marked_paid_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 18. System Settings Table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 19. System Notifications Center
CREATE TABLE system_notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_role ENUM('admin', 'agent') NOT NULL,
    message VARCHAR(255) NOT NULL,
    is_read TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 20. Activity Logs Table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_type VARCHAR(100) NOT NULL,
    details TEXT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    previous_value TEXT NULL,
    new_value TEXT NULL,
    user_role VARCHAR(50) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 21. Initial Settings Seeds
INSERT INTO system_settings (setting_key, setting_value) VALUES 
('maintenance_mode', '0'),
('custom_notice', 'Welcome to the Bus Ticket Booking portal! Book your seats with premium ease.'),
('suspend_agent_panel', '0');

-- 22. Seeds Users
-- admin / admin123
-- agent1 / agent123
-- customer1 / customer123
-- aslitravels / agent123
-- agent2 / agent123
INSERT INTO users (id, username, email, password, role, status) VALUES 
(1, 'admin', 'admin@bus.com', '$2y$10$rtg3zjvffXi2v2oFcDOGauj1l630STGZoYlHmUr80EX4kBYfZCqra', 'admin', 'approved'),
(2, 'agent1', 'agent1@bus.com', '$2y$10$Z16gC5x3COUDIzuFsBz0yOz9l.R6Y1t/.XWlMKbv/6PFhma0Wn7hS', 'agent', 'approved'),
(3, 'customer1', 'customer1@bus.com', '$2y$10$bcXxKmdVIDeOa7ucXFoHSeC0n8.fThcBH5232b7Hdk1neL2BL2Ic6', 'customer', 'approved'),
(4, 'aslitravels', 'aslitravels@bus.com', '$2y$10$Z16gC5x3COUDIzuFsBz0yOz9l.R6Y1t/.XWlMKbv/6PFhma0Wn7hS', 'agent', 'approved'),
(5, 'agent2', 'agent2@bus.com', '$2y$10$Z16gC5x3COUDIzuFsBz0yOz9l.R6Y1t/.XWlMKbv/6PFhma0Wn7hS', 'agent', 'pending');

-- Seed Agent Profiles
INSERT INTO agent_profiles (id, user_id, agency_name, phone, commission_rate) VALUES 
(1, 2, 'Golden Travels', '9876543210', 2.00),
(2, 4, 'Asli Travels', '9876543212', 2.00);

-- Seed Buses
INSERT INTO buses (id, agent_id, bus_name, bus_number, bus_type, total_seats, seat_layout_type, status) VALUES 
(1, 2, 'Golden Deluxe AC Sleeper', 'KA-01-F-1234', 'AC Sleeper', 30, '2x1_sleeper', 'active'),
(2, 2, 'Golden Express AC Seater', 'KA-01-F-5678', 'AC Seater', 40, '2x2_seater', 'active');

-- Seed Routes
INSERT INTO routes (id, agent_id, source, destination, distance_km, pickup_points, drop_points, status) VALUES 
(1, 2, 'Bangalore', 'Mumbai', 1000, 
 '[{"name":"Majestic Bus Stand","time":"20:00"},{"name":"Yeshwanthpur Tollgate","time":"20:30"}]', 
 '[{"name":"Pune Bypass","time":"07:00"},{"name":"Mumbai Sion Circle","time":"08:30"}]', 'active'),
(2, 2, 'Bangalore', 'Chennai', 350, 
 '[{"name":"Majestic Bus Stand","time":"22:00"},{"name":"Indiranagar Metro","time":"22:30"}]', 
 '[{"name":"Poonamallee Bypass","time":"04:30"},{"name":"Koyambedu Bus Terminus","time":"05:00"}]', 'active');

-- Seed Trips (Dynamically scheduled for tomorrow)
INSERT INTO trips (id, bus_id, route_id, departure_time, arrival_time, base_fare, status) VALUES 
(1, 1, 1, DATE_ADD(CONCAT(CURDATE(), ' 20:00:00'), INTERVAL 1 DAY), DATE_ADD(CONCAT(CURDATE(), ' 08:30:00'), INTERVAL 2 DAY), 1200.00, 'active'),
(2, 2, 2, DATE_ADD(CONCAT(CURDATE(), ' 22:00:00'), INTERVAL 1 DAY), DATE_ADD(CONCAT(CURDATE(), ' 05:00:00'), INTERVAL 2 DAY), 600.00, 'active');

-- Seed Seats for Trip 1 (Sleeper - L1 to L15, U1 to U15)
INSERT INTO trip_seats (trip_id, seat_number, status) VALUES 
(1, 'L1', 'available'), (1, 'L2', 'available'), (1, 'L3', 'available'), (1, 'L4', 'available'), (1, 'L5', 'available'),
(1, 'L6', 'available'), (1, 'L7', 'available'), (1, 'L8', 'available'), (1, 'L9', 'available'), (1, 'L10', 'available'),
(1, 'L11', 'available'), (1, 'L12', 'available'), (1, 'L13', 'available'), (1, 'L14', 'available'), (1, 'L15', 'available'),
(1, 'U1', 'available'), (1, 'U2', 'available'), (1, 'U3', 'available'), (1, 'U4', 'available'), (1, 'U5', 'available'),
(1, 'U6', 'available'), (1, 'U7', 'available'), (1, 'U8', 'available'), (1, 'U9', 'available'), (1, 'U10', 'available'),
(1, 'U11', 'available'), (1, 'U12', 'available'), (1, 'U13', 'available'), (1, 'U14', 'available'), (1, 'U15', 'available');

-- Seed Seats for Trip 2 (Seater - 1 to 40)
INSERT INTO trip_seats (trip_id, seat_number, status) VALUES 
(2, '1', 'available'), (2, '2', 'available'), (2, '3', 'available'), (2, '4', 'available'), (2, '5', 'available'),
(2, '6', 'available'), (2, '7', 'available'), (2, '8', 'available'), (2, '9', 'available'), (2, '10', 'available'),
(2, '11', 'available'), (2, '12', 'available'), (2, '13', 'available'), (2, '14', 'available'), (2, '15', 'available'),
(2, '16', 'available'), (2, '17', 'available'), (2, '18', 'available'), (2, '19', 'available'), (2, '20', 'available'),
(2, '21', 'available'), (2, '22', 'available'), (2, '23', 'available'), (2, '24', 'available'), (2, '25', 'available'),
(2, '26', 'available'), (2, '27', 'available'), (2, '28', 'available'), (2, '29', 'available'), (2, '30', 'available'),
(2, '31', 'available'), (2, '32', 'available'), (2, '33', 'available'), (2, '34', 'available'), (2, '35', 'available'),
(2, '36', 'available'), (2, '37', 'available'), (2, '38', 'available'), (2, '39', 'available'), (2, '40', 'available');

SET FOREIGN_KEY_CHECKS = 1;

