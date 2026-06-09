-- Seed default system settings
INSERT INTO system_settings (setting_key, setting_value) VALUES 
('gst_rate', '5.00'),
('gst_status', '1'), -- 1 = active, 0 = inactive
('gst_name', 'GST'),
('gst_effective_date', CURDATE())
ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value);

-- Alter bookings table
ALTER TABLE bookings ADD COLUMN base_fare DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE bookings ADD COLUMN gst_rate DECIMAL(5,2) NOT NULL DEFAULT 5.00;
ALTER TABLE bookings ADD COLUMN gst_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE bookings ADD COLUMN total_fare_after_tax DECIMAL(10,2) NOT NULL DEFAULT 0.00;

-- Alter cancellations table
ALTER TABLE cancellation_requests ADD COLUMN refund_base_fare DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE cancellation_requests ADD COLUMN refund_gst DECIMAL(10,2) NOT NULL DEFAULT 0.00;
ALTER TABLE cancellation_requests ADD COLUMN total_refund DECIMAL(10,2) NOT NULL DEFAULT 0.00;
