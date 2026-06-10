-- 1. Bus Media Table
CREATE TABLE IF NOT EXISTS bus_media (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    category VARCHAR(50) NOT NULL, -- front_view, interior_front, premium_seats, washroom, etc.
    media_type ENUM('image', 'video') NOT NULL DEFAULT 'image',
    file_path VARCHAR(255) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 2. Bus Predefined/Custom Amenities Table
CREATE TABLE IF NOT EXISTS bus_amenities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    bus_id INT NOT NULL,
    amenity_name VARCHAR(100) NOT NULL,
    category ENUM('comfort', 'technology', 'safety', 'convenience', 'special', 'custom') NOT NULL,
    icon_path VARCHAR(255) NULL, -- for custom amenities
    is_custom TINYINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    UNIQUE KEY unique_bus_amenity (bus_id, amenity_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 3. Bus Specifications Table
CREATE TABLE IF NOT EXISTS bus_specifications (
    bus_id INT PRIMARY KEY,
    manufacturer VARCHAR(100) NULL,
    model VARCHAR(100) NULL,
    year INT NULL,
    fuel_type VARCHAR(50) NULL,
    total_berths INT NOT NULL DEFAULT 0,
    ac_type VARCHAR(50) NULL,
    sleeper_layout VARCHAR(50) NULL,
    description TEXT NULL,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 4. Bus Policies Table
CREATE TABLE IF NOT EXISTS bus_policies (
    bus_id INT PRIMARY KEY,
    cancellation_policy TEXT NULL,
    luggage_policy TEXT NULL,
    child_policy TEXT NULL,
    smoking_policy TEXT NULL,
    pet_policy TEXT NULL,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Customer reviews on completed trips
CREATE TABLE IF NOT EXISTS bus_reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    booking_id INT NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    bus_id INT NOT NULL,
    cleanliness INT NOT NULL,
    staff_behaviour INT NOT NULL,
    punctuality INT NOT NULL,
    comfort INT NOT NULL,
    safety INT NOT NULL,
    rating DECIMAL(3,2) NOT NULL,
    review_text TEXT NULL,
    status ENUM('approved', 'fake_reported', 'removed') NOT NULL DEFAULT 'approved',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Live bus tracking details
CREATE TABLE IF NOT EXISTS bus_tracking (
    bus_id INT PRIMARY KEY,
    latitude DECIMAL(10,8) NOT NULL DEFAULT 0.00000000,
    longitude DECIMAL(11,8) NOT NULL DEFAULT 0.00000000,
    current_location_name VARCHAR(255) NULL,
    eta VARCHAR(50) NULL,
    boarding_status VARCHAR(100) NULL,
    trip_status VARCHAR(100) NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 7. Verification details
CREATE TABLE IF NOT EXISTS bus_verifications (
    bus_id INT PRIMARY KEY,
    is_verified TINYINT NOT NULL DEFAULT 0,
    verified_by INT NULL,
    verified_at TIMESTAMP NULL,
    notes TEXT NULL,
    FOREIGN KEY (bus_id) REFERENCES buses(id) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
